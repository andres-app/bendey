<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
date_default_timezone_set('America/Lima');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/../Models/GuiaRemision.php';
require_once __DIR__ . '/../Models/GreEmission.php';

function greRespuesta(array $dato, int $code=200): void
{
    http_response_code($code);
    echo json_encode($dato, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

if (empty($_SESSION['nombre']) || (int)($_SESSION['guias_remision']??0)!==1) {
    greRespuesta(['success'=>false,'message'=>'Sin permiso para Guías de Remisión. Cierre sesión e ingrese nuevamente si se le acaba de habilitar el acceso.'],403);
}

$op=(string)($_GET['op']??$_POST['op']??'');
$esPost=in_array($op,['guardar','preparar','volverBorrador','descartar','emitir','consultar','diagnosticoAmbiente'],true);
if ($esPost) {
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST') greRespuesta(['success'=>false,'message'=>'Método no permitido.'],405);
    $token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??$_POST['csrf_token']??'');
    if ($token==='' || !hash_equals((string)($_SESSION['gre_csrf']??''),$token)) greRespuesta(['success'=>false,'message'=>'La sesión ha caducado. Actualice la página.'],419);
}

try {
    $model=new GuiaRemision();
    $u=(int)$_SESSION['idusuario'];
    switch($op) {
        case 'catalogos': $data=$model->catalogos($u); break;
        case 'listar': $data=$model->listar($u,$_GET); break;
        case 'venta': $data=$model->venta((int)($_GET['idventa']??0),$u); break;
        case 'ver': $data=$model->ver((int)($_GET['idguia']??0),$u); break;
        case 'prevalidar': $data=$model->prevalidar((int)($_GET['idguia']??0),$u); break;
        case 'previsualizar': $data=(new GreEmission())->previsualizar((int)($_GET['idguia']??0),$u); break;
        case 'consultar': $data=(new GreEmission())->consultar((int)($_POST['idguia']??0),$u); break;
        case 'diagnosticoAmbiente': $data=(new GreEmission())->diagnosticarAmbiente((int)($_POST['idguia']??0),$u); break;
        case 'lotes': $data=$model->lotes((int)($_GET['idarticulo']??0),(int)($_GET['idvariacion']??0),(int)($_GET['idalmacen']??0),$u); break;
        case 'guardar':
            $body=json_decode((string)file_get_contents('php://input'),true);
            if (!is_array($body) || json_last_error()!==JSON_ERROR_NONE) throw new InvalidArgumentException('JSON inválido.');
            $data=$model->guardar($body,$_SESSION);
            break;
        case 'preparar':
            $model->preparar((int)($_POST['idguia']??0),$u);
            $data=['estado'=>'PREPARADA']; break;
        case 'volverBorrador':
            $model->volverBorrador((int)($_POST['idguia']??0),$u);
            $data=['estado'=>'BORRADOR']; break;
        case 'descartar':
            $model->anular((int)($_POST['idguia']??0),$u);
            $data=['estado'=>'ANULADA']; break;
        case 'emitir':
            $data=(new GreEmission())->emitirDesarrollo((int)($_POST['idguia']??0),$u);
            break;
        default: greRespuesta(['success'=>false,'message'=>'Operación desconocida.'],404);
    }
    greRespuesta(['success'=>true,'data'=>$data]);
} catch (InvalidArgumentException $e) {
    greRespuesta(['success'=>false,'message'=>$e->getMessage()],422);
} catch (Throwable $e) {
    error_log('[GRE] '.$e->getMessage());
    greRespuesta(['success'=>false,'message'=>'No se pudo completar la operación. Verifique que se importó la migración GRE y revise los registros del servidor.'],500);
}
