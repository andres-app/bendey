<?php

declare(strict_types=1);

require_once __DIR__ . '/Updater.php';

class TiquePOSControlAgent
{
    private string $baseUrl;
    private string $clientKey;
    private string $secret;
    private string $installUuid;

    public function __construct()
    {
        $this->baseUrl = rtrim((string)CONTROL_URL, '/');
        $this->clientKey = (string)CONTROL_CLIENT_KEY;
        $this->secret = (string)CONTROL_CLIENT_SECRET;
        if ($this->clientKey === '' || $this->secret === '' || strpos($this->clientKey, 'PEGAR_') === 0 || strpos($this->secret, 'PEGAR_') === 0) {
            throw new RuntimeException('Faltan CONTROL_CLIENT_KEY / CONTROL_CLIENT_SECRET en Config/local.php.');
        }
        $this->installUuid = $this->loadInstallUuid();
    }

    private function controlDir(): string
    {
        return __DIR__ . '/../storage/control';
    }

    private function loadInstallUuid(): string
    {
        $dir = $this->controlDir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear storage/control.');
        }
        $path = $dir . '/install_uuid';
        if (is_file($path)) {
            $value = trim((string)file_get_contents($path));
            if (preg_match('/^[A-Za-z0-9_-]{8,100}$/', $value)) {
                return $value;
            }
        }
        $uuid = 'INS-' . strtoupper(bin2hex(random_bytes(16)));
        if (file_put_contents($path, $uuid, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo crear install_uuid.');
        }
        return $uuid;
    }

    private function currentVersion(): string
    {
        $path = __DIR__ . '/../VERSION';
        return is_file($path) ? trim((string)file_get_contents($path)) : '0.0.0';
    }

    private function dbVersion(): string
    {
        try {
            $pdo = new PDO('mysql:host='.HOST.';port='.PORT.';dbname='.DB_NAME.';charset='.CHARSET, DB_USER, DB_PASS, array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
            return (string)$pdo->query('SELECT VERSION()')->fetchColumn();
        } catch (Throwable $e) {
            return '';
        }
    }

    private function canonicalHeaders(string $method, string $url, string $body): array
    {
        $path = (string)(parse_url($url, PHP_URL_PATH) ?: '/');
        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(16));
        $canonical = strtoupper($method)."\n".$path."\n".$timestamp."\n".$nonce."\n".hash('sha256',$body);
        $signature = hash_hmac('sha256',$canonical,$this->secret);
        return array(
            'X-TiquePOS-Client: '.$this->clientKey,
            'X-TiquePOS-Install: '.$this->installUuid,
            'X-TiquePOS-Timestamp: '.$timestamp,
            'X-TiquePOS-Nonce: '.$nonce,
            'X-TiquePOS-Signature: '.$signature,
            'Accept: application/json',
        );
    }

    private function request(string $method, string $path, string $body = '', ?string $downloadTo = null): array
    {
        $url = $this->baseUrl . $path;
        $headers = $this->canonicalHeaders($method, $url, $body);
        if ($body !== '') {
            $headers[] = 'Content-Type: application/json; charset=utf-8';
            $headers[] = 'Content-Length: '.strlen($body);
        }
        $timeout = defined('CONTROL_HTTP_TIMEOUT') ? max(10,(int)CONTROL_HTTP_TIMEOUT) : 45;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>$timeout,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'TiquePOS-Control-Agent/1.0'));
            if ($body !== '') { curl_setopt($ch,CURLOPT_POSTFIELDS,$body); }
            $fp = null;
            if ($downloadTo !== null) {
                $fp = fopen($downloadTo,'wb');
                if (!$fp) { curl_close($ch); throw new RuntimeException('No se pudo crear el archivo de descarga.'); }
                curl_setopt($ch,CURLOPT_FILE,$fp);
            } else {
                curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
            }
            $response = curl_exec($ch);
            $status = (int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if (is_resource($fp)) { fclose($fp); }
            if ($response === false || $status < 200 || $status >= 300) {
                if ($downloadTo !== null && is_file($downloadTo)) { @unlink($downloadTo); }
                throw new RuntimeException('API TiquePOS HTTP '.$status.($error!==''?' · '.$error:''));
            }
            return array('status'=>$status,'body'=>$downloadTo===null?(string)$response:'');
        }

        $context = stream_context_create(array('http'=>array('method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$body,'timeout'=>$timeout,'ignore_errors'=>true),'ssl'=>array('verify_peer'=>true,'verify_peer_name'=>true)));
        if ($downloadTo !== null) {
            $in = @fopen($url,'rb',false,$context);
            if (!$in) { throw new RuntimeException('No se pudo descargar el release desde el servidor central.'); }
            $out = fopen($downloadTo,'wb');
            if (!$out) { fclose($in); throw new RuntimeException('No se pudo crear el archivo de descarga.'); }
            stream_copy_to_stream($in,$out); fclose($in); fclose($out); $response='';
        } else {
            $response = @file_get_contents($url,false,$context);
            if ($response === false) { throw new RuntimeException('No se pudo conectar con '.CONTROL_URL); }
        }
        $status=0;
        foreach((array)($http_response_header??array()) as $line){if(preg_match('#^HTTP/\S+\s+(\d{3})#',$line,$m)){$status=(int)$m[1];}}
        if($status<200||$status>=300){if($downloadTo!==null&&is_file($downloadTo)){@unlink($downloadTo);}throw new RuntimeException('API TiquePOS HTTP '.$status);}
        return array('status'=>$status,'body'=>(string)$response);
    }

    private function saveLicense(string $licenseJson, string $signature): void
    {
        $pubPath=__DIR__.'/../Config/control_public.pem';$pub=is_file($pubPath)?(string)file_get_contents($pubPath):'';$sig=base64_decode($signature,true);
        if($pub===''||$sig===false||openssl_verify($licenseJson,$sig,$pub,OPENSSL_ALGO_SHA256)!==1){throw new RuntimeException('La firma de licencia recibida no es válida.');}
        $payload=json_decode($licenseJson,true);if(!is_array($payload)||strcasecmp((string)($payload['domain']??''),(string)CONTROL_DOMAIN)!==0||!hash_equals($this->clientKey,(string)($payload['client_key']??''))){throw new RuntimeException('La licencia recibida no corresponde a esta instalación.');}
        $path=$this->controlDir().'/license.json';$tmp=$path.'.tmp';$box=json_encode(array('license_json'=>$licenseJson,'signature'=>$signature),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if(file_put_contents($tmp,$box,LOCK_EX)===false||!rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('No se pudo guardar la licencia local.');}
    }

    private function sendResult(int $deploymentId, string $status, string $message, string $version='', bool $rollbackAvailable=false): void
    {
        $body=json_encode(array('deployment_id'=>$deploymentId,'status'=>$status,'message'=>$message,'version'=>$version,'rollback_available'=>$rollbackAvailable?1:0),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $this->request('POST','/api/v1/result.php',$body);
    }

    public function sync(): array
    {
        $payload=array('domain'=>(string)CONTROL_DOMAIN,'version'=>$this->currentVersion(),'php_version'=>PHP_VERSION,'db_version'=>$this->dbVersion(),'server_software'=>(string)($_SERVER['SERVER_SOFTWARE']??PHP_SAPI),'zip_available'=>(class_exists('ZipArchive') || (function_exists('inflate_init') && function_exists('hash_init')))?1:0,'openssl_available'=>function_exists('openssl_verify')?1:0);
        $body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $response=$this->request('POST','/api/v1/heartbeat.php',$body);
        $data=json_decode((string)$response['body'],true);
        if(!is_array($data)||empty($data['success'])){throw new RuntimeException('Respuesta inválida del servidor central.');}
        $this->saveLicense((string)$data['license_json'],(string)$data['license_signature']);
        $result=array('success'=>true,'license'=>json_decode((string)$data['license_json'],true),'deployment'=>null);
        if(!empty($data['deployment'])&&is_array($data['deployment'])){
            $d=$data['deployment'];$id=(int)($d['deployment_id']??0);
            if($id>0){
                try{
                    $action=strtolower(trim((string)($d['action']??'update')));
                    $updater=new TiquePOSUpdater(__DIR__.'/..');
                    if($action==='rollback'){
                        $from=trim((string)($d['from_version']??$this->currentVersion()));
                        $target=trim((string)($d['target_version']??''));
                        $sourceId=(int)($d['source_deployment_id']??0);
                        $this->sendResult($id,'APPLYING','Preparando rollback manual '.$from.' → '.$target.'...',$this->currentVersion());
                        $applied=$updater->rollbackDeployment($sourceId,$from,$target,$id);
                        $this->sendResult($id,'SUCCESS','Rollback manual aplicado correctamente: '.$from.' → '.$applied['version'].'.',$applied['version']);
                        $result['deployment']=array('id'=>$id,'status'=>'SUCCESS','operation'=>'ROLLBACK','version'=>$applied['version']);
                    }else{
                        $this->sendResult($id,'APPLYING','Descargando y validando release...',$this->currentVersion());
                        $downloadDir=$this->controlDir().'/downloads';if(!is_dir($downloadDir)){mkdir($downloadDir,0775,true);}
                        $zip=$downloadDir.'/deployment_'.$id.'.zip';$this->request('GET',(string)$d['download_path'],'',$zip);
                        $applied=$updater->apply($zip,$d);
                        $this->sendResult($id,'SUCCESS','Actualización aplicada correctamente.',$applied['version'],!empty($applied['rollback_available']));
                        $result['deployment']=array('id'=>$id,'status'=>'SUCCESS','operation'=>'UPDATE','version'=>$applied['version']);
                    }
                }catch(Throwable $e){
                    try{$this->sendResult($id,'FAILED',$e->getMessage(),$this->currentVersion());}catch(Throwable $ignored){}
                    $this->log('DEPLOYMENT '.$id.' FAILED: '.$e->getMessage());
                    $result['deployment']=array('id'=>$id,'status'=>'FAILED','message'=>$e->getMessage());
                }
            }
        }
        $this->log('SYNC OK · '.$this->currentVersion());
        return $result;
    }

    private function log(string $message): void
    {
        $dir=$this->controlDir();if(!is_dir($dir)){@mkdir($dir,0775,true);}@file_put_contents($dir.'/agent.log','['.date('Y-m-d H:i:s').'] '.$message.PHP_EOL,FILE_APPEND|LOCK_EX);
    }
}
