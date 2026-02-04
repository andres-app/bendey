<?php
require_once __DIR__ . '/../Config/Conexion.php';

class EnviarSunat
{
    // ============================
    // CONFIG (mueve esto a Config)
    // ============================
    private string $wsdlBeta = 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService?wsdl';



    // Usuario SOL: normalmente "RUC + USUARIO" (ej: 2060...MODDATOS) o el que uses.
    // OJO: esto varía según tu forma de autenticación, pero WS-Security va sí o sí.
    private string $solUser = '20609068800FELICITY';

    private string $solPass = 'Felicity1';          // 👈 CAMBIA



    // ============================
    // PUBLIC: ENVIAR A SUNAT
    // ============================
    public function enviar($idventa): array
    {
        $conexion = new Conexion();

        // ==================================================
        // 1) TRAER DATOS (XML + datos para nombre SUNAT)
        // ==================================================
        $sql = "SELECT
        v.idventa,
        v.tipo_comprobante,
        v.serie_comprobante,
        v.num_comprobante,
        v.fecha_hora,
        vs.xml AS ruta_xml
    FROM venta v
    INNER JOIN venta_sunat vs ON vs.idventa = v.idventa
    WHERE v.idventa = ?
    LIMIT 1";



        $r = $conexion->getData($sql, [(int)$idventa]);

        if (!is_array($r) || empty($r['ruta_xml'])) {
            return [
                'status'  => false,
                'estado'  => 'ERROR',
                'mensaje' => 'No existe XML para enviar a SUNAT',
                'cdr'     => ''
            ];
        }

        $rutaXMLRel = $r['ruta_xml']; // ej: xml/2026/01/....xml
        $rutaXMLAbs = realpath(__DIR__ . '/../' . ltrim($rutaXMLRel, '/'));

        if (!$rutaXMLAbs || !file_exists($rutaXMLAbs)) {
            return [
                'status'  => false,
                'estado'  => 'ERROR',
                'mensaje' => 'No se encontró el archivo XML en disco: ' . $rutaXMLRel,
                'cdr'     => ''
            ];
        }

        // ==================================================
        // 2) ARMA NOMBRE SUNAT DEL ARCHIVO (REGla oficial)
        //    RUC-TIPO-SERIE-CORREL (sin ceros obligatorios)
        // ==================================================
        // Si tu RUC lo tienes en BD, úsalo. Aquí lo pongo fijo para ejemplo.
        $ruc = '20609068800'; // 👈 CAMBIA o jálalo de tu tabla empresa

        $tipo = $this->mapTipoSunat((string)$r['tipo_comprobante']); // 01/03/07/08
        $serie = trim((string)$r['serie_comprobante']);
        $correl = (int)$r['num_comprobante']; // ✅
        $baseName = $ruc . '-' . $tipo . '-' . $serie . '-' . $correl; // ✅ como pide SUNAT

        if (!preg_match('/^\d{11}-(01|03|07|08)-[A-Z0-9]{4}-\d+$/', $baseName)) {
            return [
                'status'  => false,
                'estado'  => 'ERROR',
                'mensaje' => 'Nombre de archivo SUNAT inválido: ' . $baseName,
                'cdr'     => ''
            ];
        }


        // ==================================================
        // 3) AÑO/MES PARA ORDENAR CDR
        //    - prioridad: fecha_venta
        //    - fallback: ruta del XML (xml/YYYY/MM)
        // ==================================================
        [$anio, $mes] = $this->obtenerAnioMes($rutaXMLRel, (string)($r['fecha_hora'] ?? ''));

        if (!file_exists($rutaXMLAbs)) {
            return [
                'status' => false,
                'mensaje' => 'XML no existe físicamente: ' . $rutaXMLAbs
            ];
        }

        $xmlContent = file_get_contents($rutaXMLAbs);

        if ($xmlContent === false || trim($xmlContent) === '') {
            return [
                'status' => false,
                'mensaje' => 'XML vacío o ilegible antes de ZIP'
            ];
        }

        // 🔥 VALIDACIÓN CRÍTICA
        if (!str_contains($xmlContent, '<Invoice')) {
            return [
                'status' => false,
                'mensaje' => 'El archivo XML no es un comprobante válido'
            ];
        }



        // ==================================================
        // 4) CREAR ZIP A ENVIAR (SUNAT SAFE)
        // ==================================================

        $tmpDir = __DIR__ . '/../temp_sunat/';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $zipToSendAbs  = $tmpDir . $baseName . '.ZIP';
        $xmlInsideName = $baseName . '.XML';
        $tmpXmlPath    = $tmpDir . $xmlInsideName;

        // Validar XML original
        if (!file_exists($rutaXMLAbs)) {
            return [
                'status' => false,
                'mensaje' => 'XML no existe en disco: ' . $rutaXMLAbs
            ];
        }

        // Copiar XML con nombre EXACTO SUNAT
        if (!copy($rutaXMLAbs, $tmpXmlPath)) {
            return [
                'status' => false,
                'mensaje' => 'No se pudo copiar XML a carpeta temporal'
            ];
        }

        // Eliminar ZIP previo
        if (file_exists($zipToSendAbs)) {
            unlink($zipToSendAbs);
        }

        // Crear ZIP desde ARCHIVO FÍSICO
        $zip = new ZipArchive();
        if ($zip->open($zipToSendAbs, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return [
                'status' => false,
                'mensaje' => 'No se pudo crear ZIP SUNAT'
            ];
        }

        // 🔥 CLAVE: addFile, NO addFromString
        $zip->addFile($tmpXmlPath, $xmlInsideName);
        $zip->close();

        // ============================
        // DEBUG REAL DEL ZIP (TEMPORAL)
        // ============================
        error_log('SUNAT DEBUG ZIP PATH: ' . $zipToSendAbs);

        error_log('SUNAT DEBUG ZIP SIZE: ' . filesize($zipToSendAbs));

        $zipB64 = base64_encode(file_get_contents($zipToSendAbs));
        error_log('SUNAT DEBUG ZIP BASE64 SIZE: ' . strlen($zipB64));


        // Validación REAL del ZIP
        $zipCheck = new ZipArchive();
        $zipCheck->open($zipToSendAbs);

        if ($zipCheck->numFiles !== 1) {
            return [
                'status' => false,
                'mensaje' => 'ZIP inválido, archivos: ' . $zipCheck->numFiles
            ];
        }

        $inside = $zipCheck->getNameIndex(0);
        $zipCheck->close();

        if ($inside !== $xmlInsideName) {
            return [
                'status' => false,
                'mensaje' => 'Nombre interno incorrecto: ' . $inside
            ];
        }



        // ==================================================
        // 5) CONSUMIR SOAP REAL (BETA) -> sendBill
        // ==================================================
        try {
            $client = new SoapClient($this->wsdlBeta, [
                'trace'      => 1,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'soap_version' => SOAP_1_1,
                'encoding'   => 'UTF-8',
                'connection_timeout' => 30,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer'       => true,
                        'verify_peer_name'  => true,
                        // Si tu entorno tiene problemas de CA, se puede ajustar,
                        // pero NO lo recomiendo para prod.
                    ]
                ])
            ]);

            // Header WS-Security UsernameToken (obligatorio)
            $client->__setSoapHeaders($this->buildWSSecurityHeader($this->solUser, $this->solPass));

            // SUNAT espera:
            // - fileName: "{RUC}-{TIPO}-{SERIE}-{CORREL}.ZIP"
            // - contentFile: base64(zip_bytes)
            $zipBytes = file_get_contents($zipToSendAbs);
            if ($zipBytes === false) {
                return [
                    'status'  => false,
                    'estado'  => 'ERROR',
                    'mensaje' => 'No se pudo leer el ZIP temporal antes del envío',
                    'cdr'     => ''
                ];
            }

            error_log('ZIP SIZE: ' . filesize($zipToSendAbs));

            $response = $client->sendBill(
                $baseName . '.ZIP',
                base64_encode($zipBytes)
            );


            // ==================================================
            // 6) RESPUESTA SUNAT (CDR o TICKET)
            // ==================================================

            if (isset($response->applicationResponse)) {

                // 👉 CASO 1: SUNAT DEVUELVE CDR DIRECTO
                $cdrZipBytes = base64_decode($response->applicationResponse);

                $cdrDirAbs = __DIR__ . '/../cdr/' . $ruc . '/' . $anio . '/' . $mes . '/';
                if (!is_dir($cdrDirAbs)) {
                    mkdir($cdrDirAbs, 0777, true);
                }

                $cdrFileAbs = $cdrDirAbs . 'R-' . $baseName . '.zip';
                file_put_contents($cdrFileAbs, $cdrZipBytes);

                $cdrInfo = $this->leerRespuestaCdr($cdrFileAbs);

                return [
                    'status'  => true,
                    'estado'  => ($cdrInfo['code'] === '0') ? 'ACEPTADO' : 'RECHAZADO',
                    'mensaje' => $cdrInfo['desc'],
                    'cdr'     => 'cdr/' . $ruc . '/' . $anio . '/' . $mes . '/R-' . $baseName . '.zip'
                ];
            }

            // 👉 CASO 2: SUNAT NO DEVUELVE CDR (COLA / TICKET)
            return [
                'status'  => true,
                'estado'  => 'EN_PROCESO',
                'mensaje' => 'Comprobante enviado a SUNAT. CDR pendiente (usar getStatus).',
                'cdr'     => ''
            ];


            $cdrZipBytes = base64_decode($appResponseB64);
            if ($cdrZipBytes === false) {
                return [
                    'status'  => false,
                    'estado'  => 'ERROR',
                    'mensaje' => 'No se pudo decodificar el CDR (base64)',
                    'cdr'     => ''
                ];
            }

            // ==================================================
            // 7) GUARDAR CDR: /cdr/RUC/YYYY/MM/R-{baseName}.zip
            // ==================================================
            $cdrDirAbs = __DIR__ . '/../cdr/' . $ruc . '/' . $anio . '/' . $mes . '/';
            if (!is_dir($cdrDirAbs)) {
                mkdir($cdrDirAbs, 0777, true);
            }

            $cdrFileAbs = $cdrDirAbs . 'R-' . $baseName . '.zip';
            file_put_contents($cdrFileAbs, $cdrZipBytes);

            $cdrRutaRel = 'cdr/' . $ruc . '/' . $anio . '/' . $mes . '/R-' . $baseName . '.zip';

            // ==================================================
            // 8) LEER RESPUESTA DEL CDR (ResponseCode/Description)
            // ==================================================
            $cdrInfo = $this->leerRespuestaCdr($cdrFileAbs);

            // ResponseCode 0 => aceptado (en general)
            $estado = ($cdrInfo['code'] === '0') ? 'ACEPTADO' : 'RECHAZADO';
            $mensaje = $cdrInfo['desc'] ?: 'Respuesta recibida desde SUNAT';

            return [
                'status'  => true,
                'estado'  => $estado,
                'mensaje' => $mensaje,
                'cdr'     => $cdrRutaRel
            ];
        } catch (SoapFault $e) {
            return [
                'status'  => false,
                'estado'  => 'ERROR',
                'mensaje' => 'SOAP Fault: ' . $e->getMessage(),
                'cdr'     => ''
            ];
        } catch (Exception $e) {
            return [
                'status'  => false,
                'estado'  => 'ERROR',
                'mensaje' => 'Error: ' . $e->getMessage(),
                'cdr'     => ''
            ];
        }
    }

    // ============================
    // HELPERS
    // ============================

    private function mapTipoSunat(string $tipoComprobante): string
    {
        $t = strtoupper(trim($tipoComprobante));

        // FACTURA
        if ($t === '01' || str_contains($t, 'FACTURA')) {
            return '01';
        }

        // BOLETA
        if ($t === '03' || str_contains($t, 'BOLETA')) {
            return '03';
        }

        // NOTA DE CRÉDITO
        if ($t === '07' || str_contains($t, 'CREDITO')) {
            return '07';
        }

        // NOTA DE DÉBITO
        if ($t === '08' || str_contains($t, 'DEBITO')) {
            return '08';
        }

        throw new Exception('Tipo de comprobante no válido para SUNAT: ' . $tipoComprobante);
    }


    private function obtenerAnioMes(string $rutaXMLRel, string $fechaVenta): array
    {
        // 1) por fecha_venta si existe (recomendado)
        if (!empty($fechaVenta)) {
            $ts = strtotime($fechaVenta);
            if ($ts) {
                return [date('Y', $ts), date('m', $ts)];
            }
        }

        // 2) fallback por ruta: xml/YYYY/MM/archivo.xml
        $p = explode('/', str_replace('\\', '/', $rutaXMLRel));
        // buscamos "xml" y tomamos los 2 siguientes
        $idx = array_search('xml', $p, true);
        if ($idx !== false && isset($p[$idx + 1], $p[$idx + 2])) {
            $anio = $p[$idx + 1];
            $mes  = $p[$idx + 2];
            if (preg_match('/^\d{4}$/', $anio) && preg_match('/^\d{2}$/', $mes)) {
                return [$anio, $mes];
            }
        }

        // 3) último recurso: hoy
        return [date('Y'), date('m')];
    }

    private function buildWSSecurityHeader(string $username, string $password): SoapHeader
    {
        // WS-Security UsernameToken (texto plano) - estándar usado por SUNAT. :contentReference[oaicite:2]{index=2}
        $xml = '
        <wsse:Security SOAP-ENV:mustUnderstand="1"
            xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
            xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
            <wsse:UsernameToken>
                <wsse:Username>' . htmlspecialchars($username, ENT_XML1) . '</wsse:Username>
                <wsse:Password>' . htmlspecialchars($password, ENT_XML1) . '</wsse:Password>
            </wsse:UsernameToken>
        </wsse:Security>';

        $securityVar = new SoapVar($xml, XSD_ANYXML);
        return new SoapHeader(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
            'Security',
            $securityVar,
            true
        );
    }

    private function leerRespuestaCdr(string $cdrZipAbs): array
    {
        $code = '';
        $desc = '';

        $zip = new ZipArchive();
        if ($zip->open($cdrZipAbs) === true) {

            // busca el primer .xml dentro del zip
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name && preg_match('/\.xml$/i', $name)) {
                    $xmlStr = $zip->getFromName($name);
                    if ($xmlStr) {
                        $doc = new DOMDocument();
                        $doc->loadXML($xmlStr);

                        $xp = new DOMXPath($doc);
                        $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

                        $nCode = $xp->query('//cbc:ResponseCode')->item(0);
                        $nDesc = $xp->query('//cbc:Description')->item(0);

                        $code = $nCode ? trim($nCode->nodeValue) : '';
                        $desc = $nDesc ? trim($nDesc->nodeValue) : '';
                    }
                    break;
                }
            }
            $zip->close();
        }

        return ['code' => $code, 'desc' => $desc];
    }

    public function consultarEstado($ruc, $tipo, $serie, $correl): array
    {
        try {
            $client = new SoapClient($this->wsdlBeta, [
                'trace' => 1,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'soap_version' => SOAP_1_1,
                'encoding' => 'UTF-8'
            ]);

            // WS-Security
            $client->__setSoapHeaders(
                $this->buildWSSecurityHeader($this->solUser, $this->solPass)
            );

            $params = [
                'rucComprobante'  => $ruc,
                'tipoComprobante' => $tipo,   // 01 / 03
                'serieComprobante' => $serie,
                'numeroComprobante' => (int)$correl
            ];

            $response = $client->__soapCall('getStatus', [$params]);

            error_log('SUNAT getStatus RAW RESPONSE: ' . print_r($response, true));


            // SUNAT responde con:
            // statusCode
            // statusMessage
            // content (CDR base64 si existe)

            $code = (string)($response->statusCode ?? '');
            $msg  = (string)($response->statusMessage ?? '');

            // AÚN EN PROCESO
            if ($code === '98') {
                return [
                    'status' => true,
                    'estado' => 'EN_PROCESO',
                    'mensaje' => $msg ?: 'SUNAT aún no procesa el comprobante'
                ];
            }

            // ERROR / RECHAZO
            // 98 → AÚN EN PROCESO
            if ($code === '98') {
                return [
                    'status' => true,
                    'estado' => 'EN_PROCESO',
                    'mensaje' => $msg ?: 'SUNAT aún no procesa el comprobante'
                ];
            }

            // 0 → ACEPTADO
            if ($code === '0') {
                // sigue leyendo el CDR (como ya lo haces)
            }

            // 99 u otros → NO INFORMADO (NO ES RECHAZO)
            if ($code === '99' || $code === '') {
                return [
                    'status' => true,
                    'estado' => 'NO_INFORMADO',
                    'mensaje' => $msg ?: 'SUNAT aún no registra el comprobante'
                ];
            }

            // otros códigos → RECHAZADO REAL
            return [
                'status' => false,
                'estado' => 'RECHAZADO',
                'mensaje' => $msg ?: 'SUNAT rechazó el comprobante'
            ];


            // =========================
            // CDR DISPONIBLE
            // =========================
            if (!isset($response->content)) {
                return [
                    'status' => false,
                    'estado' => 'ERROR',
                    'mensaje' => 'SUNAT aceptó pero no devolvió CDR'
                ];
            }

            $cdrZip = base64_decode($response->content);
            if ($cdrZip === false) {
                return [
                    'status' => false,
                    'estado' => 'ERROR',
                    'mensaje' => 'No se pudo decodificar CDR'
                ];
            }

            // Guardar CDR
            $anio = date('Y');
            $mes  = date('m');

            $cdrDir = __DIR__ . "/../cdr/$ruc/$anio/$mes/";
            if (!is_dir($cdrDir)) {
                mkdir($cdrDir, 0777, true);
            }

            $baseName = "$ruc-$tipo-$serie-$correl";
            $cdrPath = $cdrDir . "R-$baseName.zip";

            file_put_contents($cdrPath, $cdrZip);

            // 🔎 LEER RESPUESTA REAL DEL CDR
            $cdrInfo = $this->leerRespuestaCdr($cdrPath);

            // SUNAT:
            // ResponseCode = 0  → ACEPTADO
            // ResponseCode != 0 → RECHAZADO
            $aceptado = ($cdrInfo['code'] === '0');

            return [
                'status'  => $aceptado,
                'estado'  => $aceptado ? 'ACEPTADO' : 'RECHAZADO',
                'code'    => $cdrInfo['code'],
                'mensaje' => $cdrInfo['desc'],
                'cdr'     => "cdr/$ruc/$anio/$mes/R-$baseName.zip"
            ];
        } catch (SoapFault $e) {
            return [
                'status' => false,
                'estado' => 'ERROR',
                'mensaje' => 'SOAP Fault getStatus: ' . $e->getMessage()
            ];
        }
    }
}
