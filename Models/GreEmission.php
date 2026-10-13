<?php

declare(strict_types=1);

require_once __DIR__.'/GuiaRemision.php';
require_once __DIR__.'/GreDocument.php';
require_once __DIR__.'/ApiSunat.php';

/**
 * Motor GRE compartido para pruebas y producción. Instalación cerrada por defecto.
 * No modifica stock, Kardex, pagos ni ventas.
 */
final class GreEmission
{
    private PDO $db;
    private GuiaRemision $gre;

    public function __construct()
    {
        $this->db = Conexion::conectar();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->gre = new GuiaRemision($this->db);
    }

    private function consultarUno(string $sql, array $datos): ?array
    {
        $s = $this->db->prepare($sql);
        $s->execute($datos);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        return $r === false ? null : $r;
    }

    private function ejecutar(string $sql, array $datos): void
    {
        $s = $this->db->prepare($sql);
        $s->execute($datos);
    }

    private function ajuste(): array
    {
        $file = __DIR__.'/../Config/Gre.php';
        return is_file($file) && is_array($x = require $file) ? $x : [];
    }

    /**
     * Solo permite emitir después de certificar documentBody con APISUNAT.
     * No depende del dominio: el ambiente proviene de la empresa configurada.
     * Las dos banderas se activan de forma explícita por instalación.
     * @return array{0:ApiSunat,1:bool}
     */
    private function validarEnvio(): array
    {
        $cfg = $this->ajuste();
        if (($cfg['documentbody_gre_20_validado'] ?? false) !== true) {
            throw new InvalidArgumentException('GRE 2.0: el JSON aún no está certificado por APISUNAT. Se permite únicamente previsualización y diagnóstico.');
        }
        $api = new ApiSunat();
        $produccion = $api->esProduccion();
        $clave = $produccion ? 'permitir_envio_produccion' : 'permitir_envio_desarrollo';
        if (($cfg[$clave] ?? false) !== true) {
            throw new InvalidArgumentException('La emisión GRE está deshabilitada en este ambiente. Active el permiso de instalación luego de probar el formato y la conexión.');
        }
        return [$api, $produccion];
    }

    public function estadoEmision(): array
    {
        $empresa = $this->consultarUno("SELECT apisunat_production, documento AS ruc,
            (LENGTH(TRIM(COALESCE(apisunat_persona_id,''))) > 0 AND LENGTH(TRIM(COALESCE(apisunat_persona_token,''))) > 0) AS credenciales
            FROM datos_negocio WHERE condicion=1 ORDER BY id_negocio LIMIT 1", []);
        $prod = $empresa && (int)$empresa['apisunat_production'] === 1;
        $cfg = $this->ajuste();
        $validado = ($cfg['documentbody_gre_20_validado'] ?? false) === true;
        $habilitar = ($cfg[$prod ? 'permitir_envio_produccion' : 'permitir_envio_desarrollo'] ?? false) === true;
        $credenciales = $empresa && (int)$empresa['credenciales'] === 1;
        return [
            'ambiente' => $prod ? 'PRODUCCION' : 'DESARROLLO',
            'emision_habilitada' => $validado && $habilitar && $credenciales,
            'documentbody_gre_20_validado' => $validado,
            'credenciales_configuradas' => $credenciales,
            'mensaje' => $validado && $habilitar && $credenciales
                ? 'La emisión está autorizada en esta instalación. Verifique los datos de la guía.'
                : 'Solo borradores y consultas hasta validar el JSON GRE 2.0 y activar el ambiente.'
        ];
    }

    /**
     * Diagnóstico de GRE sin emisión ni escrituras. Puede usarse sin credenciales.
     */
    public function diagnosticarAmbiente(int $id, int $usuario): array
    {
        $errores = [];
        $advertencias = [];
        $filtro = '';
        $params = [$usuario];
        if ($id > 0) {
            $detalle = $this->gre->ver($id, $usuario);
            $guia = $detalle['guia'];
            if (!in_array((string)$guia['estado'], ['BORRADOR', 'PREPARADA'], true)) {
                throw new InvalidArgumentException('Solo se permite el diagnóstico de guías sin emisión previa.');
            }
            $pre = $this->gre->prevalidar($id, $usuario);
            $errores = $pre['errores'] ?? [];
            $advertencias = $pre['advertencias'] ?? [];
            $filtro = ' AND gs.idserie=?';
            $params[] = (int)$guia['idserie'];
        }
        $serie = $this->consultarUno('SELECT gs.idserie,gs.idsucursal,gs.serie,gs.siguiente_correlativo
            FROM guia_remision_serie gs
            INNER JOIN usuario_sucursal us ON us.idsucursal=gs.idsucursal AND us.idusuario=? AND us.activo=1
            WHERE gs.activo=1' . $filtro . ' ORDER BY gs.idserie ASC LIMIT 1', $params);
        if (!$serie) {
            throw new InvalidArgumentException('No existe una serie GRE activa accesible para este usuario.');
        }
        $empresa = $this->consultarUno("SELECT documento AS ruc, apisunat_production,
            (CHAR_LENGTH(TRIM(COALESCE(apisunat_persona_id,''))) > 0 AND
             CHAR_LENGTH(TRIM(COALESCE(apisunat_persona_token,''))) > 0) AS credenciales_presentes
            FROM datos_negocio WHERE condicion=1 ORDER BY id_negocio LIMIT 1", []);
        if (!$empresa) {
            throw new InvalidArgumentException('No se encontró una empresa activa configurada.');
        }
        $prod = (int)$empresa['apisunat_production'] === 1;
        $ambiente = $prod ? 'PRODUCCION' : 'DESARROLLO';
        $ruc = trim((string)($empresa['ruc'] ?? ''));
        $configurada = (int)$empresa['credenciales_presentes'] === 1;
        $respuesta = [
            'success' => false,
            'last_number' => null,
            'suggested_number' => null,
            'http_code' => 0,
            'message' => 'Sin credenciales APISUNAT en este ambiente. Solo se verificó la configuración local.'
        ];
        if (!preg_match('/^[0-9]{11}$/D', $ruc)) {
            $errores[] = 'El RUC del negocio no tiene 11 dígitos.';
        } elseif ($configurada) {
            // Instanciar solo si hay credenciales. Su contenido nunca se devuelve al navegador.
            $api = new ApiSunat();
            if ($api->esProduccion() !== $prod || $api->obtenerRucEmpresa() !== $ruc) {
                throw new InvalidArgumentException('El ambiente o RUC de APISUNAT no coincide con los datos del negocio.');
            }
            $respuesta = $api->verificarSerieGreAmbiente((string)$serie['serie']);
        }
        $avisos = $errores;
        if (($respuesta['success'] ?? false) === true) {
            $local = (int)$serie['siguiente_correlativo'];
            $remoto = (int)$respuesta['suggested_number'];
            if ($remoto > $local) {
                $avisos[] = 'El siguiente correlativo remoto es superior al local. No se modificó la numeración.';
            } elseif ($local > $remoto) {
                $avisos[] = 'El siguiente correlativo local es superior al remoto. Revise los documentos registrados.';
            }
        } elseif (!$configurada) {
            $avisos[] = 'Configure credenciales para el ambiente indicado únicamente cuando vaya a realizar una prueba con APISUNAT.';
        }
        return [
            'ambiente' => $ambiente,
            'operacion' => 'CONSULTA_NO_EMISORA',
            'credenciales_configuradas' => $configurada,
            'conexion_confirmada' => ($respuesta['success'] ?? false) === true,
            'serie' => (string)$serie['serie'],
            'correlativo_local' => (int)$serie['siguiente_correlativo'],
            'ultimo_correlativo_proveedor' => $respuesta['last_number'] ?? null,
            'siguiente_correlativo_proveedor' => $respuesta['suggested_number'] ?? null,
            'http_code' => $respuesta['http_code'] ?? 0,
            'mensaje' => $respuesta['message'],
            'errores_guia' => $errores,
            'advertencias' => $advertencias,
            'bloqueos_para_emision' => $avisos,
            'emision_habilitada' => $this->estadoEmision()['emision_habilitada'],
            'nota' => 'Diagnóstico de solo lectura. No emite GRE y no cambia la numeración.'
        ];
    }

    private function obtenerPreparada(int $id, int $usuario): array
    {
        $datos = $this->gre->ver($id, $usuario);
        if ((string)$datos['guia']['estado'] !== 'PREPARADA') {
            throw new InvalidArgumentException('Solo se puede generar el documento de una guía preparada sin envío previo.');
        }
        $prev = $this->gre->prevalidar($id, $usuario);
        if (!$prev['valida']) throw new InvalidArgumentException('Corrija la guía: '.implode(' | ', $prev['errores']));
        // El cuerpo propuesto cubre únicamente los escenarios comprobables en desarrollo.
        if (!in_array((string)$datos['guia']['motivo_traslado'], ['01','04'], true)) {
            throw new InvalidArgumentException('Este motivo requiere datos adicionales. Por ahora use GRE Remitente por venta o traslado entre establecimientos.');
        }
        if ($datos['guia']['motivo_traslado'] === '04' && $datos['guia']['modalidad_transporte'] === '01') {
            throw new InvalidArgumentException('Traslado entre establecimientos con transporte público: falta confirmar campos específicos con el proveedor.');
        }
        return $datos;
    }

    public function previsualizar(int $id, int $usuario): array
    {
        $datos = $this->obtenerPreparada($id, $usuario);
        $g = $datos['guia'];
        $s = $this->consultarUno('SELECT serie,siguiente_correlativo FROM guia_remision_serie WHERE idserie=? AND idsucursal=? AND activo=1', [(int)$g['idserie'], (int)$g['idsucursal']]);
        if (!$s) throw new InvalidArgumentException('Serie de guía no disponible.');
        $doc = GreDocument::construir($datos, (string)$s['serie'], (int)$s['siguiente_correlativo'], new DateTimeImmutable('now', new DateTimeZone('America/Lima')));
        return [
            'advertencia' => 'SOLO PREVISUALIZACIÓN. El correlativo es provisional; no se ha enviado a SUNAT ni reservado numeración. Es necesario validar documentBody GRE 2.0 con APISUNAT.',
            'fileName' => $doc['fileName'],
            'documentBody' => $doc['documentBody']
        ];
    }

    public function emitir(int $id, int $usuario): array
    {
        // Validación del ambiente antes de reservar serie ni escribir en la BD.
        [$api, $produccion] = $this->validarEnvio();
        $datos = $this->obtenerPreparada($id, $usuario);
        if ((string)$datos['guia']['emisor_ruc'] !== $api->obtenerRucEmpresa()) {
            throw new InvalidArgumentException('El RUC de la GRE y el RUC de las credenciales APISUNAT no coinciden.');
        }
        // La consulta remota ocurre FUERA de la transacción. No emitir si el
        // correlativo del proveedor no coincide exactamente con el local.
        $serieLocal = $this->consultarUno('SELECT serie,siguiente_correlativo FROM guia_remision_serie WHERE idserie=? AND idsucursal=? AND activo=1',
            [(int)$datos['guia']['idserie'],(int)$datos['guia']['idsucursal']]);
        if (!$serieLocal) throw new InvalidArgumentException('No existe serie GRE activa.');
        $remote = $api->verificarSerieGreAmbiente((string)$serieLocal['serie']);
        if (($remote['success'] ?? false) !== true ||
            (int)($remote['suggested_number'] ?? 0) !== (int)$serieLocal['siguiente_correlativo']) {
            throw new InvalidArgumentException('Numeración GRE local y APISUNAT no coinciden o no fue posible confirmarla. No se reservó ni envió ninguna guía.');
        }
        $numeroConfirmado = (int)$remote['suggested_number'];
        $doc = [];
        $this->db->beginTransaction();
        try {
            $g = $this->consultarUno('SELECT idguia,idserie,idsucursal,estado,serie,correlativo FROM guia_remision WHERE idguia=? FOR UPDATE', [$id]);
            if (!$g || $g['estado'] !== 'PREPARADA' || $g['serie'] !== null || $g['correlativo'] !== null) {
                throw new InvalidArgumentException('La guía ya fue procesada o está siendo enviada. Actualice el listado.');
            }
            if ((int)$g['idserie'] !== (int)$datos['guia']['idserie'] || (int)$g['idsucursal'] !== (int)$datos['guia']['idsucursal']) {
                throw new InvalidArgumentException('Los datos de la guía cambiaron durante la emisión.');
            }
            $s = $this->consultarUno('SELECT idserie,serie,siguiente_correlativo FROM guia_remision_serie WHERE idserie=? AND idsucursal=? AND activo=1 FOR UPDATE', [(int)$g['idserie'], (int)$g['idsucursal']]);
            if (!$s || !preg_match('/^T[A-Z0-9]{3}$/D', (string)$s['serie'])) {
                throw new InvalidArgumentException('La serie GRE no está activa o no comienza con T.');
            }
            $numero = (int)$s['siguiente_correlativo'];
            if ($numero !== $numeroConfirmado || (string)$s['serie'] !== (string)$serieLocal['serie']) {
                throw new InvalidArgumentException('La serie cambió mientras se comprobaba APISUNAT. Actualice y vuelva a verificar.');
            }
            if ($numero < 1 || $numero > 99999999) throw new InvalidArgumentException('Se agotó o se alteró el correlativo GRE.');
            $now = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
            $doc = GreDocument::construir($datos, (string)$s['serie'], $numero, $now);
            $this->ejecutar('UPDATE guia_remision_serie SET siguiente_correlativo=? WHERE idserie=?', [$numero + 1, (int)$s['idserie']]);
            $this->ejecutar("UPDATE guia_remision SET serie=?,correlativo=?,fecha_emision=?,estado='PENDIENTE' WHERE idguia=?", [(string)$s['serie'], $numero, $now->format('Y-m-d H:i:s'), $id]);
            // Evitar token o credenciales en las trazas de la BD.
            $this->ejecutar("INSERT INTO guia_remision_sunat(idguia,file_name,estado_sunat,mensaje,request_json,production,intentos_envio)
                VALUES(?,?,'EN_COLA','Solicitud GRE pendiente de respuesta',?,?,1)", [
                    $id, $doc['fileName'], json_encode(['fileName'=>$doc['fileName'],'documentBody'=>$doc['documentBody']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR), (int)$produccion
                ]);
            $this->ejecutar('INSERT INTO guia_remision_auditoria(idguia,idusuario,accion,detalle) VALUES(?,?,?,?)', [$id,$usuario,'GRE_NUMERO_RESERVADO','Se reservó el correlativo; aún no existe aceptación SUNAT.']);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        // Ninguna petición HTTP se realiza dentro de la transacción MySQL.
        try {
            $respuesta = $api->enviarComprobante($doc['fileName'], $doc['documentBody']);
            $documentId = trim((string)($respuesta['documentId'] ?? ''));
            $http = (int)($respuesta['http_code'] ?? 0);
            if (($respuesta['success'] ?? false) === true && $documentId !== '' && ($respuesta['production'] ?? null) === $produccion) {
                $this->ejecutar("UPDATE guia_remision_sunat SET document_id=?,estado_sunat='PENDIENTE',mensaje=?,fecha_envio=NOW(),response_json=? WHERE idguia=? AND document_id IS NULL", [
                    $documentId, 'APISUNAT recibió la GRE; pendiente de consultar resultado.',
                    json_encode(['status'=>'PENDIENTE','documentId'=>$documentId,'http_code'=>$http], JSON_THROW_ON_ERROR), $id
                ]);
                $this->ejecutar('INSERT INTO guia_remision_auditoria(idguia,idusuario,accion,detalle) VALUES(?,?,?,?)', [$id,$usuario,'GRE_ENVIO_RECIBIDO','Solicitud recibida por APISUNAT; ID '.substr($documentId,0,75)]);
                return ['estado'=>'PENDIENTE', 'estado_sunat'=>'PENDIENTE', 'documentId'=>$documentId, 'ambiente'=>$produccion?'PRODUCCION':'DESARROLLO', 'message'=>'APISUNAT recibió la solicitud. Debe consultar el estado y obtener el CDR antes de acreditar el traslado.'];
            }
            $mensaje = substr(trim((string)($respuesta['message'] ?? 'El proveedor no confirmó recepción de la GRE de prueba.')),0,500);
            $this->guardarFallo($id, $usuario, 'ERROR_ENVIO', $mensaje, $http);
            return ['estado'=>'PENDIENTE','estado_sunat'=>'ERROR_ENVIO','message'=>$mensaje.' No reenvíe a ciegas: verifique primero en APISUNAT.'];
        } catch (Throwable $e) {
            error_log('[GRE] Fallo de envío guía '.$id.': '.$e->getMessage());
            $this->guardarFallo($id, $usuario, 'ESTADO_INCIERTO', 'No se pudo confirmar si APISUNAT recibió la solicitud. Revisar en su portal antes de reintentar.', 0);
            return ['estado'=>'PENDIENTE','estado_sunat'=>'ESTADO_INCIERTO','message'=>'El estado de recepción es incierto. No se volverá a enviar automáticamente.'];
        }
    }

    private function guardarFallo(int $id, int $usuario, string $estado, string $mensaje, int $http): void
    {
        $this->ejecutar('UPDATE guia_remision_sunat SET estado_sunat=?,mensaje=?,response_json=?,fecha_envio=NOW() WHERE idguia=?', [
            $estado,$mensaje,json_encode(['status'=>$estado,'http_code'=>$http],JSON_THROW_ON_ERROR),$id
        ]);
        $this->ejecutar('INSERT INTO guia_remision_auditoria(idguia,idusuario,accion,detalle) VALUES(?,?,?,?)', [$id,$usuario,'GRE_ERROR_ENVIO',substr($mensaje,0,500)]);
    }

    private static function urlProveedor($raw): ?string
    {
        $url = trim((string)$raw);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return null;
        $p = parse_url($url);
        $host = strtolower((string)($p['host'] ?? ''));
        if (strtolower((string)($p['scheme'] ?? '')) !== 'https' ||
            !($host === 'apisunat.com' || substr($host, -13) === '.apisunat.com')) return null;
        return $url;
    }

    /**
     * Recuperación de un envío incierto con documentId obtenido del portal APISUNAT.
     * NO reenvía el documento y exige coincidencia exacta de tipo, archivo y ambiente.
     */
    public function reconciliar(int $id, int $usuario, string $documentId): array
    {
        $documentId = trim($documentId);
        if (!preg_match('/^[A-Za-z0-9_-]{10,120}$/D', $documentId)) {
            throw new InvalidArgumentException('ID de documento APISUNAT inválido.');
        }
        $datos = $this->gre->ver($id,$usuario);
        if ((string)$datos['guia']['estado'] !== 'PENDIENTE') {
            throw new InvalidArgumentException('Solo puede reconciliar guías con envío pendiente o incierto.');
        }
        $reg = $this->consultarUno('SELECT production,file_name,document_id,estado_sunat FROM guia_remision_sunat WHERE idguia=?',[$id]);
        if (!$reg || $reg['document_id'] !== null ||
            !in_array($reg['estado_sunat'], ['ERROR_ENVIO','ESTADO_INCIERTO','EN_COLA'], true)) {
            throw new InvalidArgumentException('Esta guía no admite reconciliación manual.');
        }
        $api = new ApiSunat();
        $prod = $api->esProduccion();
        if ((int)$reg['production'] !== (int)$prod) {
            throw new InvalidArgumentException('El ambiente de APISUNAT no coincide con el envío reservado.');
        }
        $res = $api->consultarDocumento($documentId);
        $doc = $res['document'] ?? null;
        if (($res['success'] ?? false) !== true || !is_array($doc) ||
            !isset($doc['production']) || !is_bool($doc['production']) || $doc['production'] !== $prod ||
            (string)($res['type'] ?? '') !== '09' ||
            (string)($res['fileName'] ?? '') !== (string)$reg['file_name']) {
            throw new InvalidArgumentException('El documento remoto no coincide exactamente con la GRE local.');
        }
        $this->db->beginTransaction();
        try {
            $s = $this->db->prepare("UPDATE guia_remision_sunat SET document_id=?,estado_sunat='PENDIENTE',mensaje='documentId reconciliado sin reenviar',fecha_consulta=NOW() WHERE idguia=? AND document_id IS NULL AND estado_sunat IN ('ERROR_ENVIO','ESTADO_INCIERTO','EN_COLA')");
            $s->execute([$documentId,$id]);
            if ($s->rowCount() !== 1) throw new RuntimeException('Otro proceso ya modificó este documento. Actualice la vista.');
            $this->ejecutar('INSERT INTO guia_remision_auditoria(idguia,idusuario,accion,detalle) VALUES(?,?,?,?)',[$id,$usuario,'GRE_RECONCILIADA','Se vinculó el documento remoto sin reenvío.']);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        return $this->consultar($id,$usuario);
    }

    public function consultar(int $id, int $usuario): array
    {
        $datos = $this->gre->ver($id, $usuario);
        $g = $datos['guia'];
        $documentId = trim((string)($g['document_id'] ?? ''));
        if ($documentId === '') throw new InvalidArgumentException('No hay documentId. Si el envío quedó incierto, localícelo primero en el portal APISUNAT.');
        $reg = $this->consultarUno('SELECT production,file_name,estado_sunat FROM guia_remision_sunat WHERE idguia=?', [$id]);
        if (!$reg) throw new InvalidArgumentException('No hay solicitud GRE registrada.');
        $api = new ApiSunat();
        $prod = $api->esProduccion();
        if ((int)$reg['production'] !== (int)$prod) {
            throw new InvalidArgumentException('El ambiente del documento no coincide con las credenciales activas.');
        }
        $res = $api->consultarDocumento($documentId);
        $remoto = $res['document'] ?? null;
        if (($res['success'] ?? false) !== true || !is_array($remoto) ||
            !array_key_exists('production', $remoto) || !is_bool($remoto['production']) ||
            $remoto['production'] !== $prod || (string)($res['type'] ?? '') !== '09') {
            throw new RuntimeException('APISUNAT no confirmó GRE tipo 09 y ambiente esperado. No se alteró el estado local.');
        }
        $esperado = (string)$reg['file_name'];
        if ($esperado === '' || $esperado !== (string)($res['fileName'] ?? '') ||
            !preg_match('/^\d{11}-09-T[A-Z0-9]{3}-\d{8}$/D', $esperado)) {
            throw new RuntimeException('El nombre del documento no coincide con la guía local. No se actualizó su estado.');
        }
        $estado = strtoupper(trim((string)$res['status']));
        if (!in_array($estado, ['PENDIENTE','ACEPTADO','RECHAZADO','EXCEPCION'], true)) {
            throw new RuntimeException('Estado de APISUNAT no reconocido.');
        }
        $estadoDb = $prod ? $estado : ($estado === 'PENDIENTE' ? $estado : $estado.'_PRUEBA');
        $xml = self::urlProveedor($res['xml'] ?? '');
        $cdr = self::urlProveedor($res['cdr'] ?? '');
        // En producción la aceptación debe tener evidencia documental.
        if ($prod && $estado === 'ACEPTADO' && (!$xml || !$cdr)) {
            throw new RuntimeException('APISUNAT informó aceptación pero aún no entregó XML y CDR. Reintente la consulta; no se marcó como aceptada.');
        }
        $pdf = null;
        if ($xml && preg_match('/^[A-Za-z0-9_-]{10,120}$/D', $documentId)) {
            $pdf = 'https://back.apisunat.com/documents/'.rawurlencode($documentId).'/getPDF/A4/'.rawurlencode($esperado.'.pdf');
        }
        $mensaje = ($prod ? 'Producción' : 'Desarrollo').': '.$estado;
        $this->db->beginTransaction();
        try {
            $actual = $this->consultarUno('SELECT estado FROM guia_remision WHERE idguia=? FOR UPDATE', [$id]);
            if (!$actual || $actual['estado'] === 'ANULADA') throw new RuntimeException('La guía local no admite consulta de emisión.');
            $terminales = ['ACEPTADA','RECHAZADA','EXCEPCION'];
            if ($prod && in_array($actual['estado'], $terminales, true) &&
                $actual['estado'] !== (['ACEPTADO'=>'ACEPTADA','RECHAZADO'=>'RECHAZADA','EXCEPCION'=>'EXCEPCION'][$estado] ?? null)) {
                // Nunca sobrescribir un estado terminal con datos divergentes.
                throw new RuntimeException('La guía ya tiene un estado terminal diferente. Revisión manual requerida.');
            }
            $this->ejecutar('UPDATE guia_remision_sunat SET estado_sunat=?,mensaje=?,fecha_consulta=NOW(),fecha_respuesta=CASE WHEN ?=\'PENDIENTE\' THEN fecha_respuesta ELSE NOW() END,response_json=?,xml_url=?,cdr_url=?,pdf_url=? WHERE idguia=? AND document_id=?', [
                $estadoDb, $mensaje, $estado,
                json_encode(['status'=>$estado,'documentId'=>$documentId,'production'=>$prod,'http_code'=>(int)($res['http_code']??0),'notes'=>$res['notes']??[],'faults'=>$res['faults']??[]], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                $xml,$cdr,$pdf,$id,$documentId
            ]);
            if ($prod && $estado !== 'PENDIENTE') {
                $nuevo = ['ACEPTADO'=>'ACEPTADA','RECHAZADO'=>'RECHAZADA','EXCEPCION'=>'EXCEPCION'][$estado];
                $this->ejecutar("UPDATE guia_remision SET estado=? WHERE idguia=? AND estado='PENDIENTE'",[$nuevo,$id]);
            }
            $this->ejecutar('INSERT INTO guia_remision_auditoria(idguia,idusuario,accion,detalle) VALUES(?,?,?,?)', [$id,$usuario,'GRE_CONSULTA',substr($mensaje,0,500)]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        return ['estado'=>$prod ? ($estado === 'PENDIENTE' ? 'PENDIENTE' : ['ACEPTADO'=>'ACEPTADA','RECHAZADO'=>'RECHAZADA','EXCEPCION'=>'EXCEPCION'][$estado]) : 'PENDIENTE',
            'estado_sunat'=>$estadoDb,'documentId'=>$documentId,'ambiente'=>$prod?'PRODUCCION':'DESARROLLO',
            'message'=>$mensaje.($prod ? '' : ' (sin validez tributaria).')];
    }
}
