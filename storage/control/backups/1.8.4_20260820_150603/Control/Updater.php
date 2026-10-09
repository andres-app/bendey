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
        $stage = $this->controlDir . '/staging/' . $deploymentId . '_' . date('Ymd_His');
        $backup = $this->controlDir . '/backups/' . $version . '_' . date('Ymd_His');
        $this->ensureDir($stage);
        $this->ensureDir($backup);

        $manifest = array('delete'=>array(),'migrations'=>array(),'rollback_migrations'=>array());
        $copied = array();
        $appliedMigrations = array();
        $migrationStateBefore = $this->loadMigrationState();

        try {
            $manifest = $this->extractSafely($zipPath, $stage);
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

            $appliedMigrations = $this->runMigrations($manifest, $stage);

            $versionPath = $this->root . '/VERSION';
            $this->backupTarget($versionPath, 'VERSION', $backup, $copied);
            if (file_put_contents($versionPath . '.tmp', $version . PHP_EOL, LOCK_EX) === false || !rename($versionPath . '.tmp', $versionPath)) {
                @unlink($versionPath . '.tmp');
                throw new RuntimeException('No se pudo actualizar VERSION.');
            }

            @unlink($maintenance);
            $this->writeDeploymentLog($deploymentId, 'SUCCESS', 'Release ' . $version . ' aplicado.');
            $this->removeTree($stage);
            $this->cleanupBackups(5);
            return array('success'=>true,'version'=>$version,'backup'=>$backup,'migrations'=>$appliedMigrations);
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
        $exact = array('Config/local.php','Config/control_public.pem','Reports/error_log');
        if (in_array($p,$exact,true)) { return true; }
        $prefixes = array('storage/','Assets/img/company/','Assets/img/products/','Assets/img/users/');
        foreach($prefixes as $prefix){if(strpos($p,$prefix)===0){return true;}}
        if (preg_match('#^Assets/qr_[^/]+\.png$#i',$p)) { return true; }
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

    private function runMigrations(array $manifest, string $stage): array
    {
        $list=(array)($manifest['migrations']??array());if(!$list){return array();}
        $state=$this->loadMigrationState();$applied=array();$pdo=$this->db();
        foreach($list as $relative){$relative=$this->normalizeRelative((string)$relative);$file=$stage.'/'.$relative;if(!is_file($file)){throw new RuntimeException('Migración no encontrada: '.$relative);}$hash=hash_file('sha256',$file);if(isset($state[$relative])&&hash_equals((string)$state[$relative],(string)$hash)){continue;}
            $sql=(string)file_get_contents($file);$pdo->beginTransaction();try{foreach($this->splitSql($sql) as $statement){if(trim($statement)!==''){$pdo->exec($statement);}}$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}throw new RuntimeException('Falló migración '.$relative.': '.$e->getMessage());}
            $state[$relative]=$hash;$this->saveMigrationState($state);$applied[]=$relative;
        }
        return $applied;
    }

    private function rollbackMigrations(array $manifest, string $stage, array $applied): void
    {
        if(!$applied){return;}$map=(array)($manifest['rollback_migrations']??array());$pdo=null;
        foreach(array_reverse($applied) as $up){$down=(string)($map[$up]??'');if($down===''){continue;}try{$down=$this->normalizeRelative($down);$file=$stage.'/'.$down;if(!is_file($file)){continue;}if(!$pdo){$pdo=$this->db();}$sql=(string)file_get_contents($file);foreach($this->splitSql($sql) as $statement){if(trim($statement)!==''){$pdo->exec($statement);}}}catch(Throwable $ignored){}}
    }

    private function db(): PDO
    {
        return new PDO('mysql:host='.HOST.';port='.PORT.';dbname='.DB_NAME.';charset='.CHARSET,DB_USER,DB_PASS,array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false));
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
