<?php

declare(strict_types=1);

require_once __DIR__ . '/SafeZip.php';

class TiquePOSUpdater
{
    private string $root;
    private string $controlDir;

    public function __construct(string $root)
    {
        $real = realpath($root);
        if ($real === false) {
            throw new RuntimeException('Raíz de aplicación inválida.');
        }
        $this->root = rtrim($real, DIRECTORY_SEPARATOR);
        $this->controlDir = $this->root . '/storage/control';
    }

    public function apply(string $zipPath, array $deployment): array
    {
        if (!function_exists('openssl_verify')) {
            throw new RuntimeException('El servidor requiere OpenSSL para validar releases.');
        }
        if (!is_file($zipPath)) {
            throw new RuntimeException('No se encontró el ZIP descargado.');
        }

        $version = trim((string)($deployment['version'] ?? ''));
        $expectedSha = strtolower(trim((string)($deployment['sha256'] ?? '')));
        $signature = (string)($deployment['signature'] ?? '');
        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $version) || !preg_match('/^[a-f0-9]{64}$/', $expectedSha)) {
            throw new RuntimeException('Metadatos del release inválidos.');
        }
        $actualSha = strtolower((string)hash_file('sha256', $zipPath));
        if (!hash_equals($expectedSha, $actualSha)) {
            throw new RuntimeException('SHA-256 del release no coincide. El archivo fue rechazado.');
        }
        $this->verifyReleaseSignature($version, $actualSha, $signature);

        $deploymentId = (int)($deployment['deployment_id'] ?? 0);
        $fromVersion = $this->currentVersion();
        $stage = $this->controlDir . '/staging/' . $deploymentId . '_' . date('Ymd_His');
        $backup = $this->controlDir . '/backups/' . $deploymentId . '_' . $fromVersion . '_to_' . $version . '_' . date('Ymd_His');
        $this->ensureDir($stage);
        $this->ensureDir($backup);

        $manifest = array('delete'=>array(),'migrations'=>array(),'rollback_migrations'=>array());
        $copied = array();
        $appliedMigrations = array();
        $migrationStateBefore = $this->loadMigrationState();

        try {
            $manifest = $this->extractSafely($zipPath, $stage);
            $manifest = $this->enrichManifestWithPackagedMigrations(
                $manifest,
                $stage
            );
            $this->validateReleaseManifest(
                $manifest,
                $stage,
                $version
            );

            $maintenance = $this->controlDir . '/maintenance.flag';
            $this->ensureDir($this->controlDir);
            if (file_put_contents($maintenance, json_encode(array('version'=>$version,'started_at'=>date('c'))), LOCK_EX) === false) {
                throw new RuntimeException('No se pudo activar el modo mantenimiento.');
            }

            $files = $this->listFiles($stage);
            foreach ($files as $source) {
                $relative = $this->relativeTo($source, $stage);
                if ($relative === 'tiquepos-release.json' || $this->isProtected($relative)) {
                    continue;
                }
                $target = $this->root . '/' . $relative;
                $this->backupTarget($target, $relative, $backup, $copied);
                $this->ensureDir(dirname($target));
                if (!copy($source, $target)) {
                    throw new RuntimeException('No se pudo actualizar: ' . $relative);
                }
                @chmod($target, 0644);
            }

            foreach ((array)($manifest['delete'] ?? array()) as $relative) {
                $relative = $this->normalizeRelative((string)$relative);
                if ($relative === '' || $this->isProtected($relative)) {
                    throw new RuntimeException('El release intentó eliminar una ruta protegida: ' . $relative);
                }
                $target = $this->root . '/' . $relative;
                if (is_file($target)) {
                    $this->backupTarget($target, $relative, $backup, $copied);
                    if (!unlink($target)) {
                        throw new RuntimeException('No se pudo eliminar el archivo obsoleto: ' . $relative);
                    }
                }
            }

            $this->runMigrations($manifest, $stage, $appliedMigrations);

            $versionPath = $this->root . '/VERSION';
            $this->backupTarget($versionPath, 'VERSION', $backup, $copied);
            if (file_put_contents($versionPath . '.tmp', $version . PHP_EOL, LOCK_EX) === false || !rename($versionPath . '.tmp', $versionPath)) {
                @unlink($versionPath . '.tmp');
                throw new RuntimeException('No se pudo actualizar VERSION.');
            }

            $this->persistRollbackMetadata(
                $backup,
                $stage,
                $manifest,
                $deploymentId,
                $fromVersion,
                $version,
                $copied,
                $migrationStateBefore,
                $appliedMigrations
            );

            @unlink($maintenance);
            $this->writeDeploymentLog($deploymentId, 'SUCCESS', 'Release ' . $version . ' aplicado.');
            $this->removeTree($stage);
            $this->cleanupBackups(5);
            return array('success'=>true,'version'=>$version,'backup'=>$backup,'migrations'=>$appliedMigrations,'rollback_available'=>true);
        } catch (Throwable $e) {
            $this->rollbackMigrations($manifest, $stage, $appliedMigrations);
            $this->saveMigrationState($migrationStateBefore);
            $this->rollbackFiles($backup, $copied);
            @unlink($this->controlDir . '/maintenance.flag');
            $this->writeDeploymentLog($deploymentId, 'FAILED', $e->getMessage());
            $this->removeTree($stage);
            throw $e;
        }
    }

    public function rollbackDeployment(int $sourceDeploymentId, string $fromVersion, string $targetVersion, int $rollbackDeploymentId = 0): array
    {
        $fromVersion = trim($fromVersion);
        $targetVersion = trim($targetVersion);
        if ($sourceDeploymentId <= 0 || !preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $fromVersion) || !preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $targetVersion)) {
            throw new RuntimeException('Solicitud de rollback inválida.');
        }

        $current = $this->currentVersion();
        if (!hash_equals($fromVersion, $current)) {
            throw new RuntimeException('Rollback rechazado: la instalación está en ' . $current . ' y la orden espera ' . $fromVersion . '.');
        }

        [$backup, $meta] = $this->findRollbackMetadata($sourceDeploymentId);
        if (!hash_equals((string)($meta['to_version'] ?? ''), $fromVersion) || !hash_equals((string)($meta['from_version'] ?? ''), $targetVersion)) {
            throw new RuntimeException('El backup disponible no corresponde a ' . $fromVersion . ' → ' . $targetVersion . '.');
        }
        if (($meta['status'] ?? 'ACTIVE') !== 'ACTIVE') {
            throw new RuntimeException('Este backup ya fue utilizado o no está disponible para rollback.');
        }

        $changes = (array)($meta['changes'] ?? array());
        if (!$changes) {
            throw new RuntimeException('El backup no contiene un manifiesto de archivos para restaurar.');
        }

        $appliedMigrations = (array)($meta['applied_migrations'] ?? array());
        $sqlMap = (array)($meta['rollback_sql'] ?? array());
        foreach ($appliedMigrations as $up) {
            if (empty($sqlMap[$up]['down']) || empty($sqlMap[$up]['up'])) {
                throw new RuntimeException('Rollback de BD no disponible para la migración: ' . $up);
            }
        }

        $safety = $this->controlDir . '/rollback_safety/' . max(0, $rollbackDeploymentId) . '_' . $fromVersion . '_' . date('Ymd_His');
        $this->ensureDir($safety);
        $this->backupCurrentFiles($changes, $safety);
        $migrationStateCurrent = $this->loadMigrationState();
        $maintenance = $this->controlDir . '/maintenance.flag';
        $rolledBackMigrations = array();

        try {
            if (file_put_contents($maintenance, json_encode(array('rollback'=>true,'from'=>$fromVersion,'to'=>$targetVersion,'started_at'=>date('c'))), LOCK_EX) === false) {
                throw new RuntimeException('No se pudo activar el modo mantenimiento para rollback.');
            }

            if ($appliedMigrations) {
                $pdo = $this->db();
                foreach (array_reverse($appliedMigrations) as $up) {
                    $downFile = $backup . '/' . (string)$sqlMap[$up]['down'];
                    if (!is_file($downFile)) {
                        throw new RuntimeException('Falta SQL DOWN de rollback: ' . $up);
                    }
                    $this->executeSqlStatements($pdo, (string)file_get_contents($downFile));
                    $rolledBackMigrations[] = (string)$up;
                }
            }

            $this->restoreFilesFromUpdateBackup($backup, $changes);
            $beforeState = (array)($meta['migration_state_before'] ?? array());
            $this->saveMigrationState($beforeState);

            $versionNow = $this->currentVersion();
            if (!hash_equals($targetVersion, $versionNow)) {
                if (file_put_contents($this->root . '/VERSION.tmp', $targetVersion . PHP_EOL, LOCK_EX) === false || !rename($this->root . '/VERSION.tmp', $this->root . '/VERSION')) {
                    @unlink($this->root . '/VERSION.tmp');
                    throw new RuntimeException('No se pudo restaurar VERSION durante el rollback.');
                }
            }

            $meta['status'] = 'ROLLED_BACK';
            $meta['rolled_back_at'] = date('c');
            $meta['rollback_deployment_id'] = $rollbackDeploymentId;
            $this->saveRollbackMetadata($backup, $meta);
            @unlink($maintenance);
            $this->writeDeploymentLog($rollbackDeploymentId, 'ROLLBACK_SUCCESS', $fromVersion . ' → ' . $targetVersion);
            $this->cleanupSafetyBackups(5);
            return array('success'=>true,'version'=>$targetVersion,'backup'=>$backup,'safety_backup'=>$safety);
        } catch (Throwable $e) {
            // Si ya se ejecutaron DOWN, intentamos reconstruir el estado previo con sus UP.
            if ($rolledBackMigrations) {
                try {
                    $pdo = $this->db();
                    foreach (array_reverse($rolledBackMigrations) as $up) {
                        $upFile = $backup . '/' . (string)$sqlMap[$up]['up'];
                        if (is_file($upFile)) {
                            $this->executeSqlStatements($pdo, (string)file_get_contents($upFile));
                        }
                    }
                } catch (Throwable $dbRecoveryError) {
                    $this->writeDeploymentLog($rollbackDeploymentId, 'ROLLBACK_DB_RECOVERY_WARNING', $dbRecoveryError->getMessage());
                }
            }
            $this->saveMigrationState($migrationStateCurrent);
            $this->restoreSafetyFiles($safety);
            @unlink($maintenance);
            $this->writeDeploymentLog($rollbackDeploymentId, 'ROLLBACK_FAILED', $e->getMessage());
            throw $e;
        }
    }

    private function currentVersion(): string
    {
        $path = $this->root . '/VERSION';
        return is_file($path) ? trim((string)file_get_contents($path)) : '0.0.0';
    }

    private function persistRollbackMetadata(string $backup, string $stage, array $manifest, int $deploymentId, string $fromVersion, string $toVersion, array $changes, array $migrationStateBefore, array $appliedMigrations): void
    {
        $sqlMap = array();
        $rollbackMap = (array)($manifest['rollback_migrations'] ?? array());
        $sqlDir = $backup . '/_rollback_sql';
        if ($appliedMigrations) {
            $this->ensureDir($sqlDir);
        }
        foreach ($appliedMigrations as $idx => $up) {
            $up = $this->normalizeRelative((string)$up);
            $down = isset($rollbackMap[$up]) ? $this->normalizeRelative((string)$rollbackMap[$up]) : '';
            if ($down === '') {
                continue;
            }
            $upSource = $stage . '/' . $up;
            $downSource = $stage . '/' . $down;
            if (!is_file($upSource) || !is_file($downSource)) {
                continue;
            }
            $base = sprintf('%03d_%s', (int)$idx, preg_replace('/[^A-Za-z0-9_.-]+/', '_', basename($up)));
            $upRel = '_rollback_sql/' . $base . '.up.sql';
            $downRel = '_rollback_sql/' . $base . '.down.sql';
            if (!copy($upSource, $backup . '/' . $upRel) || !copy($downSource, $backup . '/' . $downRel)) {
                throw new RuntimeException('No se pudo conservar SQL de rollback para ' . $up);
            }
            $sqlMap[$up] = array('up'=>$upRel,'down'=>$downRel);
        }

        $meta = array(
            'schema'=>1,
            'deployment_id'=>$deploymentId,
            'from_version'=>$fromVersion,
            'to_version'=>$toVersion,
            'created_at'=>date('c'),
            'status'=>'ACTIVE',
            'changes'=>$changes,
            'migration_state_before'=>$migrationStateBefore,
            'applied_migrations'=>$appliedMigrations,
            'rollback_sql'=>$sqlMap,
        );
        $this->saveRollbackMetadata($backup, $meta);
    }

    private function saveRollbackMetadata(string $backup, array $meta): void
    {
        $path = $backup . '/rollback.json';
        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, json_encode($meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), LOCK_EX) === false || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('No se pudo guardar metadata de rollback.');
        }
    }

    private function findRollbackMetadata(int $sourceDeploymentId): array
    {
        $base = $this->controlDir . '/backups';
        foreach (array_reverse(glob($base . '/*/rollback.json') ?: array()) as $path) {
            $meta = json_decode((string)file_get_contents($path), true);
            if (is_array($meta) && (int)($meta['deployment_id'] ?? 0) === $sourceDeploymentId) {
                return array(dirname($path), $meta);
            }
        }
        throw new RuntimeException('No existe backup de rollback para el despliegue #' . $sourceDeploymentId . '. Solo las versiones instaladas con rollback manual habilitado pueden revertirse.');
    }

    private function backupCurrentFiles(array $changes, string $safety): void
    {
        $states = array();
        foreach ($changes as $relative => $ignored) {
            $relative = $this->normalizeRelative((string)$relative);
            $target = $this->root . '/' . $relative;
            $exists = is_file($target);
            $states[$relative] = $exists ? 'EXISTED' : 'MISSING';
            if ($exists) {
                $dest = $safety . '/files/' . $relative;
                $this->ensureDir(dirname($dest));
                if (!copy($target, $dest)) {
                    throw new RuntimeException('No se pudo crear backup preventivo de ' . $relative);
                }
            }
        }
        file_put_contents($safety . '/safety.json', json_encode(array('created_at'=>date('c'),'states'=>$states), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private function restoreSafetyFiles(string $safety): void
    {
        $path = $safety . '/safety.json';
        if (!is_file($path)) {
            return;
        }
        $meta = json_decode((string)file_get_contents($path), true);
        $states = is_array($meta) ? (array)($meta['states'] ?? array()) : array();
        foreach ($states as $relative => $state) {
            $relative = $this->normalizeRelative((string)$relative);
            $target = $this->root . '/' . $relative;
            if ($state === 'EXISTED') {
                $source = $safety . '/files/' . $relative;
                if (is_file($source)) {
                    $this->ensureDir(dirname($target));
                    @copy($source, $target);
                }
            } else {
                @unlink($target);
            }
        }
    }

    private function restoreFilesFromUpdateBackup(string $backup, array $changes): void
    {
        foreach (array_reverse(array_keys($changes)) as $relative) {
            $relative = $this->normalizeRelative((string)$relative);
            $target = $this->root . '/' . $relative;
            if ((string)$changes[$relative] === 'NEW') {
                @unlink($target);
                continue;
            }
            $source = $backup . '/' . $relative;
            if (!is_file($source)) {
                throw new RuntimeException('Backup incompleto: falta ' . $relative);
            }
            $this->ensureDir(dirname($target));
            if (!copy($source, $target)) {
                throw new RuntimeException('No se pudo restaurar ' . $relative);
            }
        }
    }

    private function cleanupSafetyBackups(int $keep): void
    {
        $base = $this->controlDir . '/rollback_safety';
        if (!is_dir($base)) { return; }
        $dirs = array_filter(glob($base . '/*') ?: array(), 'is_dir');
        usort($dirs, static fn($a,$b)=>filemtime($b)<=>filemtime($a));
        foreach (array_slice($dirs, $keep) as $dir) { $this->removeTree($dir); }
    }

    private function verifyReleaseSignature(string $version, string $sha, string $signature): void
    {
        $pubPath = $this->root . '/Config/control_public.pem';
        $pub = is_file($pubPath) ? (string)file_get_contents($pubPath) : '';
        $sig = base64_decode($signature, true);
        if ($pub === '' || $sig === false || openssl_verify($version . "\n" . $sha, $sig, $pub, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Firma RSA del release inválida. Actualización rechazada.');
        }
    }

    private function extractSafely(string $zipPath, string $stage): array
    {
        if (class_exists('ZipArchive')) {
            return $this->extractWithZipArchive($zipPath, $stage);
        }
        return $this->extractWithSafeZip($zipPath, $stage);
    }

    private function extractWithZipArchive(string $zipPath, string $stage): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('No se pudo abrir el ZIP del release.');
        }
        $names = array();
        $total = 0;
        for ($i=0; $i<$zip->numFiles; $i++) {
            $name = str_replace('\\','/',(string)$zip->getNameIndex($i));
            if ($name === '' || strpos($name, '__MACOSX/') === 0) { continue; }
            if ($name[0] === '/' || preg_match('#(^|/)\.\.(/|$)#',$name)) { $zip->close(); throw new RuntimeException('Ruta insegura dentro del ZIP: '.$name); }
            $stat=$zip->statIndex($i);$total+=(int)($stat['size']??0);
            if($total>600*1024*1024){$zip->close();throw new RuntimeException('El release excede 600 MB descomprimido.');}
            if(method_exists($zip,'getExternalAttributesIndex')){$ops=0;$attr=0;if($zip->getExternalAttributesIndex($i,$ops,$attr)){$mode=($attr>>16)&0170000;if($mode===0120000){$zip->close();throw new RuntimeException('No se permiten enlaces simbólicos en releases.');}}}
            $names[]=$name;
        }
        if(!$names){$zip->close();throw new RuntimeException('El ZIP está vacío.');}
        $prefix=$this->commonRootPrefix($names);
        $manifest=array('delete'=>array(),'migrations'=>array(),'rollback_migrations'=>array());
        for($i=0;$i<$zip->numFiles;$i++){
            $raw=str_replace('\\','/',(string)$zip->getNameIndex($i));
            if($raw===''||strpos($raw,'__MACOSX/')===0){continue;}
            $logical=$prefix!==''&&strpos($raw,$prefix)===0?substr($raw,strlen($prefix)):$raw;
            $logical=$this->normalizeRelative($logical);
            if($logical===''){continue;}
            if(substr($raw,-1)==='/'){continue;}
            if($this->isProtected($logical)){continue;}
            $stream=$zip->getStream($raw);if(!$stream){$zip->close();throw new RuntimeException('No se pudo leer '.$logical.' del release.');}
            $dest=$stage.'/'.$logical;$this->ensureDir(dirname($dest));$out=fopen($dest,'wb');if(!$out){fclose($stream);$zip->close();throw new RuntimeException('No se pudo preparar '.$logical);}
            stream_copy_to_stream($stream,$out);fclose($stream);fclose($out);
            if($logical==='tiquepos-release.json'){$json=json_decode((string)file_get_contents($dest),true);if(!is_array($json)){$zip->close();throw new RuntimeException('tiquepos-release.json no es válido.');}$manifest=array_merge($manifest,$json);}
        }
        $zip->close();
        return $manifest;
    }

    private function extractWithSafeZip(string $zipPath, string $stage): array
    {
        $reader = new TiquePOSSafeZip($zipPath);
        $entries = $reader->entries();
        if (!$entries) {
            throw new RuntimeException('El ZIP está vacío.');
        }
        $names = array();
        foreach ($entries as $entry) {
            $name = str_replace('\\','/',(string)$entry['name']);
            if ($name === '' || strpos($name, '__MACOSX/') === 0) { continue; }
            if ($name[0] === '/' || preg_match('#(^|/)\.\.(/|$)#',$name)) { throw new RuntimeException('Ruta insegura dentro del ZIP: '.$name); }
            $names[] = $name;
        }
        $prefix = $this->commonRootPrefix($names);
        $manifest = array('delete'=>array(),'migrations'=>array(),'rollback_migrations'=>array());
        foreach ($entries as $entry) {
            $raw = str_replace('\\','/',(string)$entry['name']);
            if ($raw === '' || strpos($raw, '__MACOSX/') === 0 || !empty($entry['is_dir'])) { continue; }
            $logical = $prefix !== '' && strpos($raw,$prefix) === 0 ? substr($raw,strlen($prefix)) : $raw;
            $logical = $this->normalizeRelative($logical);
            if ($logical === '' || $this->isProtected($logical)) { continue; }
            $dest = $stage . '/' . $logical;
            $reader->extractEntry($entry, $dest);
            if ($logical === 'tiquepos-release.json') {
                $json = json_decode((string)file_get_contents($dest), true);
                if (!is_array($json)) { throw new RuntimeException('tiquepos-release.json no es válido.'); }
                $manifest = array_merge($manifest, $json);
            }
        }
        return $manifest;
    }

    private function commonRootPrefix(array $names): string
    {
        $first = null;
        $fileCount = 0;
        foreach ($names as $name) {
            $name = str_replace('\\', '/', (string)$name);
            if ($name === '' || substr($name, -1) === '/') { continue; }
            $trim = trim($name, '/');
            if ($trim === '') { continue; }
            $parts = explode('/', $trim);
            if (count($parts) < 2) { return ''; }
            $fileCount++;
            if ($first === null) { $first = $parts[0]; }
            elseif ($first !== $parts[0]) { return ''; }
        }
        return $fileCount > 0 && $first !== null ? $first . '/' : '';
    }

    private function isProtected(string $relative): bool
    {
        $p = ltrim(str_replace('\\','/',$relative),'/');
        $exact = array(
            'Config/local.php',
            'Config/local.php.example',
            'Config/control_public.pem',
            '.env',
            'dev_verify.php',
            'database/base.sql',
            'Reports/error_log'
        );
        if (in_array($p,$exact,true)) { return true; }
        $prefixes = array(
            'storage/',
            'install/',
            'Assets/img/company/',
            'Assets/img/products/',
            'Assets/img/users/'
        );
        foreach($prefixes as $prefix){if(strpos($p,$prefix)===0){return true;}}
        return false;
    }

    private function normalizeRelative(string $path): string
    {
        $path=str_replace('\\','/',$path);$path=preg_replace('#/+#','/',$path);$path=ltrim((string)$path,'/');
        if($path===''||preg_match('#(^|/)\.\.(/|$)#',$path)||strpos($path,"\0")!==false){throw new RuntimeException('Ruta de release inválida.');}
        return $path;
    }

    private function listFiles(string $dir): array
    {
        $out=array();$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));
        foreach($it as $file){if($file->isFile()){$out[]=$file->getPathname();}}
        sort($out,SORT_STRING);return $out;
    }

    private function relativeTo(string $path, string $base): string
    {
        return ltrim(str_replace('\\','/',substr($path,strlen(rtrim($base,DIRECTORY_SEPARATOR)))),'/');
    }

    private function backupTarget(string $target, string $relative, string $backup, array &$changes): void
    {
        if (isset($changes[$relative])) { return; }
        $existed = is_file($target);
        $changes[$relative] = $existed ? 'EXISTED' : 'NEW';
        if ($existed) {
            $dest=$backup.'/'.$relative;$this->ensureDir(dirname($dest));if(!copy($target,$dest)){throw new RuntimeException('No se pudo respaldar '.$relative);}
        }
    }

    private function rollbackFiles(string $backup, array $changes): void
    {
        foreach(array_reverse(array_keys($changes)) as $relative){$target=$this->root.'/'.$relative;if($changes[$relative]==='NEW'){@unlink($target);continue;}$source=$backup.'/'.$relative;if(is_file($source)){$this->ensureDir(dirname($target));@copy($source,$target);}}
    }

    /**
     * Completa el manifiesto con pares UP/DOWN incluidos dentro del ZIP.
     * Esto hace que futuros releases Git incrementales no dependan de que
     * el generador central recuerde declarar manualmente cada migración.
     * Las declaraciones explícitas del manifest siempre se conservan.
     */
    private function enrichManifestWithPackagedMigrations(
        array $manifest,
        string $stage
    ): array {
        $migrations = array_values(
            array_unique(
                array_map(
                    'strval',
                    (array)($manifest['migrations'] ?? array())
                )
            )
        );

        $rollback = (array)(
            $manifest['rollback_migrations']
            ?? array()
        );

        $metaPath = $stage
            . '/database/migrations/release-migrations.json';

        if (is_file($metaPath)) {
            $meta = json_decode(
                (string)file_get_contents($metaPath),
                true
            );

            if (is_array($meta)) {
                foreach ((array)($meta['migrations'] ?? array()) as $up) {
                    $up = $this->normalizeRelative((string)$up);
                    if ($up !== '' && !in_array($up, $migrations, true)) {
                        $migrations[] = $up;
                    }
                }

                foreach ((array)($meta['rollback_migrations'] ?? array()) as $up => $down) {
                    $up = $this->normalizeRelative((string)$up);
                    $down = $this->normalizeRelative((string)$down);
                    if ($up !== '' && $down !== '') {
                        $rollback[$up] = $down;
                    }
                }
            }
        }

        /*
         * Convención oficial compartida con Control Center:
         *
         *   database/migrations/foo.sql
         *   database/rollbacks/foo.sql
         *
         * Se conserva compatibilidad con paquetes antiguos que hayan usado
         * database/migrations/foo_DOWN.sql.
         */
        $migrationDir = $stage . '/database/migrations';

        if (is_dir($migrationDir)) {
            $candidates = glob($migrationDir . '/*.sql') ?: array();
            sort($candidates, SORT_STRING);

            foreach ($candidates as $file) {
                $name = basename($file);

                if (preg_match('/_DOWN\.sql$/i', $name)) {
                    continue;
                }

                $up = 'database/migrations/' . $name;

                $officialDown =
                    'database/rollbacks/' . $name;

                $legacyDownName = preg_replace(
                    '/\.sql$/i',
                    '_DOWN.sql',
                    $name
                );

                $legacyDown =
                    'database/migrations/' . $legacyDownName;

                $down = '';

                if (is_file($stage . '/' . $officialDown)) {
                    $down = $officialDown;
                } elseif (is_file($stage . '/' . $legacyDown)) {
                    $down = $legacyDown;
                }

                if ($down === '') {
                    continue;
                }

                if (!in_array($up, $migrations, true)) {
                    $migrations[] = $up;
                }

                if (empty($rollback[$up])) {
                    $rollback[$up] = $down;
                }
            }
        }

        $manifest['migrations'] = $migrations;
        $manifest['rollback_migrations'] = $rollback;

        return $manifest;
    }

    /**
     * Rechaza antes de tocar archivos cualquier release con un plan de BD
     * incompleto. También impide aplicar por error un instalador completo
     * como si fuera un UPDATE.
     */
    private function validateReleaseManifest(
        array $manifest,
        string $stage,
        string $expectedVersion
    ): void {
        $scope = strtolower(
            trim((string)($manifest['package_scope'] ?? 'update'))
        );

        $allowedScopes = array(
            'update',
            'incremental',
            'delta'
        );

        if (!in_array($scope, $allowedScopes, true)) {
            throw new RuntimeException(
                'El paquete no es un UPDATE incremental válido. Scope: '
                . ($scope !== '' ? $scope : 'vacío')
            );
        }

        $manifestVersion = trim(
            (string)($manifest['version'] ?? '')
        );

        if (
            $manifestVersion !== ''
            && !hash_equals($expectedVersion, $manifestVersion)
        ) {
            throw new RuntimeException(
                'La versión del manifest no coincide con el deployment.'
            );
        }

        $migrations = (array)(
            $manifest['migrations']
            ?? array()
        );
        $rollback = (array)(
            $manifest['rollback_migrations']
            ?? array()
        );

        foreach ($migrations as $upRaw) {
            $up = $this->normalizeRelative((string)$upRaw);
            if ($up === '') {
                throw new RuntimeException(
                    'El manifest contiene una migración vacía.'
                );
            }

            if (!preg_match('#^database/migrations/[^/]+\.sql$#i', $up)) {
                throw new RuntimeException(
                    'Ruta de migración no permitida: ' . $up
                );
            }

            if (preg_match('/_DOWN\.sql$/i', $up)) {
                throw new RuntimeException(
                    'Una migración DOWN no puede declararse como UP: ' . $up
                );
            }

            if (!is_file($stage . '/' . $up)) {
                throw new RuntimeException(
                    'Migración declarada pero ausente del release: ' . $up
                );
            }

            $down = isset($rollback[$up])
                ? $this->normalizeRelative((string)$rollback[$up])
                : '';

            if ($down === '' || !is_file($stage . '/' . $down)) {
                throw new RuntimeException(
                    'Toda migración UP debe incluir su DOWN: ' . $up
                );
            }

            $downEsOficial = (bool)preg_match(
                '#^database/rollbacks/[^/]+\.sql$#i',
                $down
            );

            $downEsLegacy = (bool)preg_match(
                '#^database/migrations/[^/]+_DOWN\.sql$#i',
                $down
            );

            if (!$downEsOficial && !$downEsLegacy) {
                throw new RuntimeException(
                    'Ruta DOWN no permitida: ' . $down
                );
            }
        }
    }

    private function runMigrations(array $manifest, string $stage, array &$applied): void
    {
        $list = (array)($manifest['migrations'] ?? array());
        if (!$list) {
            return;
        }

        $state = $this->loadMigrationState();
        $pdo = $this->db();

        foreach ($list as $relative) {
            $relative = $this->normalizeRelative((string)$relative);
            $file = $stage . '/' . $relative;
            if (!is_file($file)) {
                throw new RuntimeException('Migración no encontrada: ' . $relative);
            }

            $hash = (string)hash_file('sha256', $file);
            if (isset($state[$relative]) && hash_equals((string)$state[$relative], $hash)) {
                continue;
            }

            $sql = (string)file_get_contents($file);
            try {
                $this->executeSqlStatements($pdo, $sql);
            } catch (Throwable $e) {
                // MySQL puede hacer COMMIT implícito en sentencias DDL. Por eso una
                // migración fallida necesita un "down" explícito para garantizar
                // que no queden cambios parciales.
                $rollbackError = $this->rollbackSingleMigration($manifest, $stage, $relative, $pdo);
                $message = 'Falló migración ' . $relative . ': ' . $e->getMessage();
                if ($rollbackError !== '') {
                    $message .= ' | Además falló su rollback: ' . $rollbackError;
                }
                throw new RuntimeException($message, 0, $e);
            }

            $state[$relative] = $hash;
            $this->saveMigrationState($state);
            $applied[] = $relative;
        }
    }

    private function rollbackMigrations(array $manifest, string $stage, array $applied): void
    {
        if (!$applied) {
            return;
        }

        $pdo = $this->db();
        $errors = array();
        foreach (array_reverse($applied) as $up) {
            $error = $this->rollbackSingleMigration($manifest, $stage, (string)$up, $pdo);
            if ($error !== '') {
                $errors[] = $up . ': ' . $error;
            }
        }

        if ($errors) {
            $this->writeDeploymentLog(0, 'ROLLBACK_WARNING', implode(' | ', $errors));
        }
    }

    private function rollbackSingleMigration(array $manifest, string $stage, string $up, PDO $pdo): string
    {
        $map = (array)($manifest['rollback_migrations'] ?? array());
        $down = (string)($map[$up] ?? '');
        if ($down === '') {
            return 'No se declaró migración de reversión.';
        }

        try {
            $down = $this->normalizeRelative($down);
            $file = $stage . '/' . $down;
            if (!is_file($file)) {
                return 'No se encontró el archivo de reversión: ' . $down;
            }
            $sql = (string)file_get_contents($file);
            $this->executeSqlStatements($pdo, $sql);
            return '';
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    private function executeSqlStatements(PDO $pdo, string $sql): void
    {
        foreach ($this->splitSql($sql) as $statement) {
            if (trim($statement) === '') {
                continue;
            }

            $stmt = $pdo->prepare($statement);
            $stmt->execute();
            if ($stmt->columnCount() > 0) {
                // Consumir cualquier result set evita el error 2014 de PDO/MySQL
                // cuando la siguiente sentencia se ejecuta en la misma conexión.
                $stmt->fetchAll(PDO::FETCH_NUM);
            }
            $stmt->closeCursor();
        }
    }

    private function db(): PDO
    {
        $options = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        );
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        }

        return new PDO(
            'mysql:host=' . HOST . ';port=' . PORT . ';dbname=' . DB_NAME . ';charset=' . CHARSET,
            DB_USER,
            DB_PASS,
            $options
        );
    }

    private function splitSql(string $sql): array
    {
        $out=array();$buf='';$len=strlen($sql);$quote=null;$lineComment=false;$blockComment=false;
        for($i=0;$i<$len;$i++){$c=$sql[$i];$n=$i+1<$len?$sql[$i+1]:'';
            if($lineComment){if($c==="\n"){$lineComment=false;$buf.=$c;}continue;}
            if($blockComment){if($c==='*'&&$n==='/'){$blockComment=false;$i++;}continue;}
            if($quote===null){if($c==='-'&&$n==='-'&&($i+2>=$len||ctype_space($sql[$i+2]))){$lineComment=true;$i++;continue;}if($c==='#'){$lineComment=true;continue;}if($c==='/'&&$n==='*'){$blockComment=true;$i++;continue;}if($c==="'"||$c==='"'||$c==='`'){$quote=$c;$buf.=$c;continue;}if($c===';'){if(trim($buf)!==''){$out[]=trim($buf);}$buf='';continue;}$buf.=$c;continue;}
            $buf.=$c;if($c==='\\'&&$quote!=='`'&&$i+1<$len){$buf.=$sql[++$i];continue;}if($c===$quote){if($i+1<$len&&$sql[$i+1]===$quote){$buf.=$sql[++$i];continue;}$quote=null;}
        }
        if(trim($buf)!==''){$out[]=trim($buf);}return $out;
    }

    private function loadMigrationState(): array
    {
        $path=$this->controlDir.'/migrations.json';if(!is_file($path)){return array();}$data=json_decode((string)file_get_contents($path),true);return is_array($data)?$data:array();
    }
    private function saveMigrationState(array $state): void
    {
        $this->ensureDir($this->controlDir);$path=$this->controlDir.'/migrations.json';$tmp=$path.'.tmp';file_put_contents($tmp,json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX);rename($tmp,$path);
    }
    private function ensureDir(string $dir): void
    {
        if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir)){throw new RuntimeException('No se pudo crear directorio: '.$dir);}
    }
    private function removeTree(string $dir): void
    {
        if(!is_dir($dir)){return;}$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $item){$item->isDir()?@rmdir($item->getPathname()):@unlink($item->getPathname());}@rmdir($dir);
    }
    private function cleanupBackups(int $keep): void
    {
        $base=$this->controlDir.'/backups';if(!is_dir($base)){return;}$dirs=array_filter(glob($base.'/*')?:array(),'is_dir');usort($dirs,static fn($a,$b)=>filemtime($b)<=>filemtime($a));foreach(array_slice($dirs,$keep) as $dir){$this->removeTree($dir);}
    }
    private function writeDeploymentLog(int $id,string $status,string $message): void
    {
        $this->ensureDir($this->controlDir);@file_put_contents($this->controlDir.'/deployments.log','['.date('Y-m-d H:i:s').'] #'.$id.' '.$status.' '.$message.PHP_EOL,FILE_APPEND|LOCK_EX);
    }
}
