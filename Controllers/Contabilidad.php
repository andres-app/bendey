<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../Models/Contabilidad.php';

$contabilidad = new Contabilidad();
$idusuario = (int)($_SESSION['idusuario'] ?? 0);

function contabilidadJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function contabilidadFecha(string $value, string $fallback): string
{
    $value = trim($value);
    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
        return $date->format('Y-m-d');
    }
    return $fallback;
}

function contabilidadRegimen(string $value): string
{
    $value = strtoupper(trim($value));
    return in_array($value, ['M-RER', 'M-RMT', 'M-RG'], true) ? $value : 'M-RER';
}

function contabilidadUpper(string $value): string
{
    $value = trim($value);
    return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
}

function contabilidadTipoDocumentoPersona(string $tipo): string
{
    $tipo = contabilidadUpper($tipo);
    if ($tipo === 'DNI' || str_contains($tipo, 'NACIONAL')) {
        return '1';
    }
    if ($tipo === 'RUC') {
        return '6';
    }
    if ($tipo === 'CE' || str_contains($tipo, 'EXTRANJER')) {
        return '4';
    }
    if (str_contains($tipo, 'PASAP')) {
        return '7';
    }
    return $tipo === '' ? '' : '0';
}

function contabilidadNumeroVisible(string $numero): string
{
    $numero = trim($numero);
    if ($numero === '') {
        return '';
    }
    if (preg_match('/^\d+$/', $numero)) {
        $sinCeros = ltrim($numero, '0');
        return $sinCeros === '' ? '0' : $sinCeros;
    }
    return $numero;
}

function contabilidadEstadoComprobante(string $estado): string
{
    $estado = contabilidadUpper($estado);
    if ($estado === '') {
        return 'PENDIENTE';
    }
    $reemplazos = [
        'NO_ENVIADO' => 'NO ENVIADO',
        'NO APLICA' => 'NO APLICA',
        'NO_APLICA' => 'NO APLICA',
        'ACEPTADA' => 'ACEPTADO',
        'ANULADA' => 'ANULADO',
    ];
    return $reemplazos[$estado] ?? $estado;
}

function contabilidadNormalizarRegistros(array $rows, string $regimen): array
{
    $salida = [];
    $correlativo = 0;

    foreach ($rows as $row) {
        $fechaRaw = (string)($row['fecha_hora'] ?? '');
        try {
            $fecha = new DateTime($fechaRaw);
        } catch (Throwable $e) {
            continue;
        }

        $correlativo++;
        $estadoComp = contabilidadEstadoComprobante((string)($row['estado_comprobante'] ?? ''));
        $estadoInterno = contabilidadUpper((string)($row['estado_interno'] ?? ''));
        $anulado = $estadoComp === 'ANULADO' || in_array($estadoInterno, ['ANULADO', 'ANULADA'], true);

        $opExport = (float)($row['total_exportacion'] ?? 0);
        $opGravada = (float)($row['total_gravado'] ?? 0);
        $igv = (float)($row['total_igv'] ?? 0);
        $opExonerada = (float)($row['total_exonerado'] ?? 0);
        $opInafecta = (float)($row['total_inafecto'] ?? 0);
        $descuento = (float)($row['descuento_total'] ?? 0);
        $total = (float)($row['total_documento'] ?? 0);

        if ($anulado) {
            $opExport = 0.0;
            $opGravada = 0.0;
            $igv = 0.0;
            $opExonerada = 0.0;
            $opInafecta = 0.0;
            $descuento = 0.0;
            $total = 0.0;
        }

        $fechaVencimiento = '';
        $fechaVencRaw = trim((string)($row['fecha_vencimiento'] ?? ''));
        if ($fechaVencRaw !== '') {
            try {
                $fechaVencimiento = (new DateTime($fechaVencRaw))->format('d/m/Y');
            } catch (Throwable $e) {
                $fechaVencimiento = '';
            }
        }

        $fechaModificada = '';
        $fechaModRaw = trim((string)($row['fecha_doc_modificado'] ?? ''));
        if ($fechaModRaw !== '') {
            try {
                $fechaModificada = (new DateTime($fechaModRaw))->format('d/m/Y');
            } catch (Throwable $e) {
                $fechaModificada = '';
            }
        }

        $clienteTipo = $anulado ? '' : contabilidadTipoDocumentoPersona((string)($row['cliente_tipo_documento'] ?? ''));
        $clienteNumero = $anulado ? '' : trim((string)($row['cliente_num_documento'] ?? ''));
        $clienteNombre = $anulado ? '' : trim((string)($row['cliente_nombre'] ?? ''));

        $salida[] = [
            'periodo' => (int)$fecha->format('Ym') * 100,
            'cod_unic' => $correlativo,
            'regimen' => $regimen,
            'f_emision' => $fecha->format('d/m/Y'),
            'f_vencimiento' => $fechaVencimiento,
            'tipo_doc' => trim((string)($row['tipo_documento_sunat'] ?? '')),
            'serie' => trim((string)($row['serie_comprobante'] ?? '')),
            'numero' => contabilidadNumeroVisible((string)($row['num_comprobante'] ?? '')),
            'num_maq_reg' => '',
            't_doc' => $clienteTipo,
            'numero_cliente' => $clienteNumero,
            'razon_social' => $clienteNombre,
            'op_export' => round($opExport, 2),
            'op_gravada' => round($opGravada, 2),
            'descuent' => round($descuento, 2),
            'igv' => round($igv, 2),
            'desc_igv' => 0.0,
            'op_exonerada' => round($opExonerada, 2),
            'op_inafecta' => round($opInafecta, 2),
            'isc' => 0.0,
            'op_arroz_p' => 0.0,
            'imp_arroz_p' => 0.0,
            'icbper' => 0.0,
            'otro_tributos' => 0.0,
            'total' => round($total, 2),
            'moneda' => trim((string)($row['moneda_codigo'] ?? 'PEN')) ?: 'PEN',
            'tc' => round((float)($row['tipo_cambio'] ?? 1), 3),
            'fec_comp_modif' => $fechaModificada,
            'tipo_doc_modif' => trim((string)($row['tipo_doc_modificado'] ?? '')),
            'serie_doc_modif' => trim((string)($row['serie_doc_modificado'] ?? '')) ?: '-',
            'num_doc_modif' => contabilidadNumeroVisible((string)($row['numero_doc_modificado'] ?? '')) ?: '0',
            'id_contr' => '',
            'err_tc' => '',
            'comp_mp' => '',
            'estado' => $anulado ? 2 : 1,
            'camp_lib' => '',
            'estado_comp' => $anulado ? 'ANULADO' : $estadoComp,
            'origen' => (string)($row['origen'] ?? 'VENTA'),
            'origen_id' => (int)($row['origen_id'] ?? 0),
            'forma_pago' => trim((string)($row['forma_pago_nombre'] ?? '')),
            'tipo_pago_codigo' => trim((string)($row['tipo_pago_codigo'] ?? '')),
            'cliente_tipo_documento_origen' => trim((string)($row['cliente_tipo_documento'] ?? '')),
        ];
    }

    return $salida;
}

function contabilidadResumen(array $rows): array
{
    $sum = [
        'comprobantes' => count($rows),
        'op_gravada' => 0.0,
        'op_exonerada' => 0.0,
        'op_inafecta' => 0.0,
        'igv' => 0.0,
        'total' => 0.0,
        'observados' => 0,
    ];

    foreach ($rows as $row) {
        $sum['op_gravada'] += (float)$row['op_gravada'];
        $sum['op_exonerada'] += (float)$row['op_exonerada'];
        $sum['op_inafecta'] += (float)$row['op_inafecta'];
        $sum['igv'] += (float)$row['igv'];
        $sum['total'] += (float)$row['total'];
        if (!in_array((string)$row['estado_comp'], ['ACEPTADO', 'ANULADO'], true)) {
            $sum['observados']++;
        }
    }

    foreach (['op_gravada', 'op_exonerada', 'op_inafecta', 'igv', 'total'] as $key) {
        $sum[$key] = round((float)$sum[$key], 2);
    }

    return $sum;
}

function contabilidadColumnasExcel(): array
{
    return [
        'PERIODO' => 'periodo',
        'COD.UNIC.' => 'cod_unic',
        'REGIMEN' => 'regimen',
        'F.EMISIÓN' => 'f_emision',
        'F.VENCIMIENTO' => 'f_vencimiento',
        'TIPO DOC' => 'tipo_doc',
        'SERIE' => 'serie',
        'NUMERO' => 'numero',
        'NUM.MAQ.REG.' => 'num_maq_reg',
        'T.DOC.' => 't_doc',
        'NUMERO' . "\u{00A0}" => 'numero_cliente',
        'RAZÓN SOCIAL' => 'razon_social',
        'OP.EXPORT.' => 'op_export',
        'OP.GRAVADA' => 'op_gravada',
        'DESCUENT.' => 'descuent',
        'IGV' => 'igv',
        'DESC.IGV' => 'desc_igv',
        'OP.EXONERADA' => 'op_exonerada',
        'OP.INAFECTA' => 'op_inafecta',
        'ISC' => 'isc',
        'OP.ARROZ.P.' => 'op_arroz_p',
        'IMP.ARROZ.P.' => 'imp_arroz_p',
        'ICBPER' => 'icbper',
        'OTRO.TRIBUTOS.' => 'otro_tributos',
        'TOTAL' => 'total',
        'MONEDA' => 'moneda',
        'T.C.' => 'tc',
        'FEC.COMP.MODIF.' => 'fec_comp_modif',
        'TIPO.DOC.MODIF.' => 'tipo_doc_modif',
        'SERIE.DOC.MODIF.' => 'serie_doc_modif',
        'NUM.DOC.MODIF.' => 'num_doc_modif',
        'ID.CONTR.' => 'id_contr',
        'ERR.T.C.' => 'err_tc',
        'COMP.M.P.' => 'comp_mp',
        'ESTADO' => 'estado',
        'CAMP.LIB.' => 'camp_lib',
        'ESTADO COMP.' => 'estado_comp',
    ];
}

function contabilidadXlsxCol(int $index): string
{
    $name = '';
    $index++;
    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = intdiv($index, 26);
    }
    return $name;
}

function contabilidadXml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function contabilidadXlsxCell(string $ref, mixed $value, int $style, bool $numeric = false): string
{
    if ($value === null || $value === '') {
        return '<c r="' . $ref . '" s="' . $style . '"/>';
    }

    if ($numeric) {
        $num = is_numeric($value) ? (string)$value : '0';
        return '<c r="' . $ref . '" s="' . $style . '"><v>' . $num . '</v></c>';
    }

    $text = contabilidadXml((string)$value);
    return '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . $text . '</t></is></c>';
}

function contabilidadCrearZipOpenXml(array $archivos, string $ruta): void
{
    $datosLocales = '';
    $directorioCentral = '';
    $entradas = 0;
    $ahora = getdate();
    $anio = max(1980, (int)$ahora['year']);
    $horaDos = (($ahora['hours'] & 0x1F) << 11) | (($ahora['minutes'] & 0x3F) << 5) | ((int)($ahora['seconds'] / 2) & 0x1F);
    $fechaDos = ((($anio - 1980) & 0x7F) << 9) | (($ahora['mon'] & 0x0F) << 5) | ($ahora['mday'] & 0x1F);

    foreach ($archivos as $nombre => $contenido) {
        $nombre = str_replace('\\', '/', (string)$nombre);
        $contenido = (string)$contenido;
        $crc = crc32($contenido);
        if ($crc < 0) {
            $crc += 4294967296;
        }

        $metodo = 0;
        $comprimido = $contenido;
        if (function_exists('gzdeflate')) {
            $tmp = gzdeflate($contenido, 6);
            if ($tmp !== false && strlen($tmp) < strlen($contenido)) {
                $metodo = 8;
                $comprimido = $tmp;
            }
        }

        $tamComprimido = strlen($comprimido);
        $tamOriginal = strlen($contenido);
        $tamNombre = strlen($nombre);
        $offset = strlen($datosLocales);
        $flags = 0x0800;

        $datosLocales .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $metodo,
            $horaDos,
            $fechaDos,
            $crc,
            $tamComprimido,
            $tamOriginal,
            $tamNombre,
            0
        ) . $nombre . $comprimido;

        $directorioCentral .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            20,
            20,
            $flags,
            $metodo,
            $horaDos,
            $fechaDos,
            $crc,
            $tamComprimido,
            $tamOriginal,
            $tamNombre,
            0,
            0,
            0,
            0,
            0,
            $offset
        ) . $nombre;

        $entradas++;
    }

    $offsetCentral = strlen($datosLocales);
    $tamCentral = strlen($directorioCentral);
    $fin = pack(
        'VvvvvVVv',
        0x06054b50,
        0,
        0,
        $entradas,
        $entradas,
        $tamCentral,
        $offsetCentral,
        0
    );

    if (@file_put_contents($ruta, $datosLocales . $directorioCentral . $fin, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo crear el archivo Excel temporal.');
    }
}

function contabilidadFechaExcelSerial(string $value): ?float
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $formats = ['d/m/Y', 'Y-m-d', 'Y-m-d H:i:s'];
    $date = null;
    foreach ($formats as $format) {
        $candidate = DateTime::createFromFormat($format, $value);
        $errors = DateTime::getLastErrors();
        if ($candidate && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            $date = $candidate;
            break;
        }
    }

    if (!$date) {
        try {
            $date = new DateTime($value);
        } catch (Throwable $e) {
            return null;
        }
    }

    $base = new DateTime('1899-12-30 00:00:00');
    $date->setTime(0, 0, 0);
    return (float)$base->diff($date)->days;
}

function contabilidadGuardarOpenXml(array $archivos, string $ruta): void
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $status = $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($status === true) {
            foreach ($archivos as $nombre => $contenido) {
                if (!$zip->addFromString((string)$nombre, (string)$contenido)) {
                    $zip->close();
                    @unlink($ruta);
                    throw new RuntimeException('No se pudo agregar un componente al archivo Excel.');
                }
            }
            $zip->close();
            return;
        }
    }

    contabilidadCrearZipOpenXml($archivos, $ruta);
}

function contabilidadCrearXlsxPlano(array $columnas, array $filas, string $ruta, string $sheetName = 'Lista Productos'): void
{
    if (!$columnas) {
        throw new RuntimeException('El formato Excel no tiene columnas definidas.');
    }

    $lastCol = contabilidadXlsxCol(count($columnas) - 1);
    $lastRow = max(1, count($filas) + 1);
    $sheetRows = [];

    $headerCells = [];
    foreach ($columnas as $i => $columna) {
        $header = (string)($columna['header'] ?? '');
        $headerCells[] = contabilidadXlsxCell(contabilidadXlsxCol($i) . '1', $header, 1, false);
    }
    $sheetRows[] = '<row r="1" ht="28" customHeight="1">' . implode('', $headerCells) . '</row>';

    foreach ($filas as $rIndex => $fila) {
        $excelRow = $rIndex + 2;
        $cells = [];
        foreach ($columnas as $cIndex => $columna) {
            $key = (string)($columna['key'] ?? '');
            $type = (string)($columna['type'] ?? 'text');
            $value = $fila[$key] ?? '';
            $ref = contabilidadXlsxCol($cIndex) . $excelRow;

            if ($type === 'date') {
                $serial = contabilidadFechaExcelSerial((string)$value);
                $cells[] = contabilidadXlsxCell($ref, $serial ?? '', 4, $serial !== null);
            } elseif ($type === 'amount') {
                $isBlank = $value === '' || $value === null;
                $cells[] = contabilidadXlsxCell($ref, $isBlank ? '' : (is_numeric($value) ? round((float)$value, 2) : ''), 3, !$isBlank && is_numeric($value));
            } elseif ($type === 'exchange') {
                $isBlank = $value === '' || $value === null;
                $cells[] = contabilidadXlsxCell($ref, $isBlank ? '' : (is_numeric($value) ? round((float)$value, 3) : ''), 5, !$isBlank && is_numeric($value));
            } elseif ($type === 'number') {
                $isBlank = $value === '' || $value === null;
                $cells[] = contabilidadXlsxCell($ref, $isBlank ? '' : (is_numeric($value) ? $value : ''), 2, !$isBlank && is_numeric($value));
            } else {
                $cells[] = contabilidadXlsxCell($ref, (string)$value, 0, false);
            }
        }
        $sheetRows[] = '<row r="' . $excelRow . '">' . implode('', $cells) . '</row>';
    }

    $colsXml = [];
    foreach ($columnas as $i => $columna) {
        $n = $i + 1;
        $width = max(8, min(42, (float)($columna['width'] ?? 14)));
        $colsXml[] = '<col min="' . $n . '" max="' . $n . '" width="' . $width . '" customWidth="1"/>';
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="A1:' . $lastCol . $lastRow . '"/>'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<cols>' . implode('', $colsXml) . '</cols>'
        . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
        . '<autoFilter ref="A1:' . $lastCol . $lastRow . '"/>'
        . '</worksheet>';

    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="3">'
        . '<numFmt numFmtId="164" formatCode="#0.00;[Red]-#0.00"/>'
        . '<numFmt numFmtId="165" formatCode="dd/mm/yyyy"/>'
        . '<numFmt numFmtId="166" formatCode="0.000"/>'
        . '</numFmts>'
        . '<fonts count="2">'
        . '<font><sz val="10"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="9"/><color rgb="FF1F2937"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEAF8F2"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="2"><border/><border><left style="thin"><color rgb="FFDDE5E1"/></left><right style="thin"><color rgb="FFDDE5E1"/></right><top style="thin"><color rgb="FFDDE5E1"/></top><bottom style="thin"><color rgb="FFDDE5E1"/></bottom></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="6">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '<xf numFmtId="166" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    $safeSheetName = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $sheetName);
    $safeSheetName = trim($safeSheetName) ?: 'Hoja1';
    $safeSheetName = function_exists('mb_substr')
        ? mb_substr($safeSheetName, 0, 31, 'UTF-8')
        : substr($safeSheetName, 0, 31);

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . contabilidadXml($safeSheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    contabilidadGuardarOpenXml([
        '[Content_Types].xml' => $contentTypes,
        '_rels/.rels' => $rootRels,
        'xl/workbook.xml' => $workbookXml,
        'xl/_rels/workbook.xml.rels' => $workbookRels,
        'xl/styles.xml' => $stylesXml,
        'xl/worksheets/sheet1.xml' => $sheetXml,
    ], $ruta);

    if (!is_file($ruta) || filesize($ruta) < 900 || @file_get_contents($ruta, false, null, 0, 2) !== 'PK') {
        @unlink($ruta);
        throw new RuntimeException('El archivo Excel se generó incompleto.');
    }
}

function contabilidadColumnasSunat(): array
{
    $types = [
        'periodo' => 'number', 'cod_unic' => 'number', 'op_export' => 'amount', 'op_gravada' => 'amount',
        'descuent' => 'amount', 'igv' => 'amount', 'desc_igv' => 'amount', 'op_exonerada' => 'amount',
        'op_inafecta' => 'amount', 'isc' => 'amount', 'op_arroz_p' => 'amount', 'imp_arroz_p' => 'amount',
        'icbper' => 'amount', 'otro_tributos' => 'amount', 'total' => 'amount', 'tc' => 'exchange', 'estado' => 'number',
    ];
    $widths = [12,10,12,13,15,10,10,12,14,9,16,34,13,13,12,12,12,14,13,10,13,13,11,14,13,10,10,15,15,16,16,12,11,12,10,12,16];
    $out = [];
    $i = 0;
    foreach (contabilidadColumnasExcel() as $header => $key) {
        $out[] = [
            'header' => str_replace("\u{00A0}", '', (string)$header),
            'key' => $key,
            'type' => $types[$key] ?? 'text',
            'width' => $widths[$i] ?? 14,
        ];
        $i++;
    }
    return $out;
}

function contabilidadColumnasEjb(): array
{
    return [
        ['header'=>'RUC','key'=>'ruc','type'=>'text','width'=>16],
        ['header'=>'Tipo Documento','key'=>'tipo_documento','type'=>'text','width'=>16],
        ['header'=>'Serie Documento','key'=>'serie_documento','type'=>'text','width'=>16],
        ['header'=>'Numero Documento','key'=>'numero_documento','type'=>'text','width'=>18],
        ['header'=>'Fecha Documento','key'=>'fecha_documento','type'=>'date','width'=>15],
        ['header'=>'Fecha Vencimiento','key'=>'fecha_vencimiento','type'=>'date','width'=>16],
        ['header'=>'Moneda','key'=>'moneda','type'=>'text','width'=>10],
        ['header'=>'Importe IGV','key'=>'importe_igv','type'=>'amount','width'=>13],
        ['header'=>'Importe Documento','key'=>'importe_documento','type'=>'amount','width'=>16],
        ['header'=>'Importe Inafecto','key'=>'importe_inafecto','type'=>'amount','width'=>15],
        ['header'=>'Importe ISC','key'=>'importe_isc','type'=>'amount','width'=>12],
        ['header'=>'Otros','key'=>'otros','type'=>'amount','width'=>12],
        ['header'=>'Cuenta de Venta','key'=>'cuenta_venta','type'=>'text','width'=>16],
        ['header'=>'Fecha Documento Ref.','key'=>'fecha_documento_ref','type'=>'date','width'=>18],
        ['header'=>'Tipo Documento Ref.','key'=>'tipo_documento_ref','type'=>'text','width'=>18],
        ['header'=>'Serie Documento Ref.','key'=>'serie_documento_ref','type'=>'text','width'=>19],
        ['header'=>'Número Documento Ref.','key'=>'numero_documento_ref','type'=>'text','width'=>20],
        ['header'=>'Centro Costo','key'=>'centro_costo','type'=>'text','width'=>14],
        ['header'=>'Subsidiario','key'=>'subsidiario','type'=>'text','width'=>14],
        ['header'=>'Cuenta por Cobrar','key'=>'cuenta_cobrar','type'=>'text','width'=>17],
        ['header'=>'Glosa de Comprobante','key'=>'glosa','type'=>'text','width'=>34],
        ['header'=>'Anexo de Venta','key'=>'anexo_venta','type'=>'text','width'=>16],
    ];
}

function contabilidadColumnasSiscont(): array
{
    $headers = [
        'Origen','Num.Voucher','Fecha','Cuenta','Monto Debe','Monto Haber','Moneda S/D','T.Cambio','Doc','Num.Doc',
        'Fec.Doc','Fec.Ven','Cod.Prov.Clie','C.Costo','Presupuesto','F.Efectivo','Glosa','Libro C/V/R',
        'Mto.Neto1','Mto.Neto2','Mto.Neto3','Mto.Neto4','Mto.Neto5','Mto.Neto6','Mto.Neto7','Mto.Neto8','Mto.Neto9','Mto.IGV',
        'Ref.Doc','Ref.Num.Doc','Ref.Fecha','D.Numero','D.Fecha','RUC','R.Social','Tipo','Tip.Doc.Iden','Medio de Pago','Apellido 1','Apellido 2','Nombre','T.Bien'
    ];
    $keys = [
        'origen_siscont','voucher','fecha','cuenta','debe','haber','moneda_sd','tipo_cambio','doc','num_doc',
        'fec_doc','fec_ven','cod_cliente','centro_costo','presupuesto','f_efectivo','glosa','libro_cvr',
        'neto1','neto2','neto3','neto4','neto5','neto6','neto7','neto8','neto9','mto_igv',
        'ref_doc','ref_num_doc','ref_fecha','d_numero','d_fecha','ruc','razon_social','tipo','tipo_doc_iden','medio_pago','apellido1','apellido2','nombre','t_bien'
    ];
    $dateKeys = ['fecha','fec_doc','fec_ven','ref_fecha','d_fecha'];
    $amountKeys = ['debe','haber','neto1','neto2','neto3','neto4','neto5','neto6','neto7','neto8','neto9','mto_igv'];
    $out = [];
    foreach ($headers as $i => $header) {
        $key = $keys[$i];
        $type = in_array($key, $dateKeys, true) ? 'date' : (in_array($key, $amountKeys, true) ? 'amount' : ($key === 'tipo_cambio' ? 'exchange' : ($key === 'voucher' ? 'number' : 'text')));
        $out[] = ['header'=>$header,'key'=>$key,'type'=>$type,'width'=>($i === 16 || $i === 34 ? 30 : 14)];
    }
    return $out;
}

function contabilidadConfigFormatos(): array
{
    // Valores iniciales habituales del PCGE para venta de mercaderías.
    // Están centralizados aquí para que puedan adaptarse a un plan contable particular sin tocar el exportador.
    return [
        'ejb_cuenta_venta' => '701111',
        'ejb_cuenta_cobrar' => '121211',
        'siscont_origen_ventas' => '01',
        'siscont_cuenta_cobrar' => '121211',
        'siscont_cuenta_igv' => '401111',
        'siscont_cuenta_venta' => '701111',
    ];
}

function contabilidadFilasEjb(array $rows): array
{
    $cfg = contabilidadConfigFormatos();
    $salida = [];
    foreach ($rows as $row) {
        $refNumero = '';
        if (!empty($row['serie_doc_modif']) && (string)$row['serie_doc_modif'] !== '-') {
            $refNumero = (string)($row['num_doc_modif'] ?? '');
        }
        $salida[] = [
            'ruc' => (string)($row['numero_cliente'] ?? ''),
            'tipo_documento' => (string)($row['tipo_doc'] ?? ''),
            'serie_documento' => (string)($row['serie'] ?? ''),
            'numero_documento' => (string)($row['numero'] ?? ''),
            'fecha_documento' => (string)($row['f_emision'] ?? ''),
            'fecha_vencimiento' => (string)($row['f_vencimiento'] ?? ''),
            'moneda' => (string)($row['moneda'] ?? 'PEN'),
            'importe_igv' => (float)($row['igv'] ?? 0),
            'importe_documento' => (float)($row['total'] ?? 0),
            'importe_inafecto' => round((float)($row['op_inafecta'] ?? 0) + (float)($row['op_exonerada'] ?? 0), 2),
            'importe_isc' => (float)($row['isc'] ?? 0),
            'otros' => round((float)($row['otro_tributos'] ?? 0) + (float)($row['icbper'] ?? 0), 2),
            'cuenta_venta' => $cfg['ejb_cuenta_venta'],
            'fecha_documento_ref' => (string)($row['fec_comp_modif'] ?? ''),
            'tipo_documento_ref' => (string)($row['tipo_doc_modif'] ?? ''),
            'serie_documento_ref' => ((string)($row['serie_doc_modif'] ?? '-') === '-') ? '' : (string)$row['serie_doc_modif'],
            'numero_documento_ref' => $refNumero,
            'centro_costo' => '',
            'subsidiario' => (string)($row['numero_cliente'] ?? ''),
            'cuenta_cobrar' => $cfg['ejb_cuenta_cobrar'],
            'glosa' => (($row['origen'] ?? '') === 'NOTA_CREDITO' ? 'NOTA DE CRÉDITO ' : 'VENTA ') . (string)($row['serie'] ?? '') . '-' . (string)($row['numero'] ?? ''),
            'anexo_venta' => (string)($row['numero_cliente'] ?? ''),
        ];
    }
    return $salida;
}

function contabilidadFilaSiscontBase(array $row, array $cfg): array
{
    $docCompleto = trim((string)($row['serie'] ?? '')) . '-' . trim((string)($row['numero'] ?? ''));
    $refCompleto = '';
    if (!empty($row['serie_doc_modif']) && (string)$row['serie_doc_modif'] !== '-') {
        $refCompleto = trim((string)$row['serie_doc_modif']) . '-' . trim((string)($row['num_doc_modif'] ?? ''));
    }
    return [
        'origen_siscont' => $cfg['siscont_origen_ventas'],
        'voucher' => (int)($row['cod_unic'] ?? 0),
        'fecha' => (string)($row['f_emision'] ?? ''),
        'cuenta' => '', 'debe' => '', 'haber' => '',
        'moneda_sd' => strtoupper((string)($row['moneda'] ?? 'PEN')) === 'USD' ? 'D' : 'S',
        'tipo_cambio' => (float)($row['tc'] ?? 1),
        'doc' => (string)($row['tipo_doc'] ?? ''),
        'num_doc' => $docCompleto,
        'fec_doc' => (string)($row['f_emision'] ?? ''),
        'fec_ven' => (string)($row['f_vencimiento'] ?? ''),
        'cod_cliente' => (string)($row['numero_cliente'] ?? ''),
        'centro_costo' => '', 'presupuesto' => '', 'f_efectivo' => '',
        'glosa' => (($row['origen'] ?? '') === 'NOTA_CREDITO' ? 'NOTA DE CRÉDITO ' : 'VENTA ') . $docCompleto,
        'libro_cvr' => 'V',
        'neto1' => '', 'neto2' => '', 'neto3' => '', 'neto4' => '', 'neto5' => '', 'neto6' => '', 'neto7' => '', 'neto8' => '', 'neto9' => '', 'mto_igv' => '',
        'ref_doc' => (string)($row['tipo_doc_modif'] ?? ''),
        'ref_num_doc' => $refCompleto,
        'ref_fecha' => (string)($row['fec_comp_modif'] ?? ''),
        'd_numero' => '', 'd_fecha' => '',
        'ruc' => (string)($row['numero_cliente'] ?? ''),
        'razon_social' => (string)($row['razon_social'] ?? ''),
        'tipo' => '1',
        'tipo_doc_iden' => (string)($row['t_doc'] ?? ''),
        'medio_pago' => '',
        'apellido1' => '', 'apellido2' => '', 'nombre' => '', 't_bien' => '',
    ];
}

function contabilidadFilasSiscont(array $rows): array
{
    $cfg = contabilidadConfigFormatos();
    $salida = [];

    foreach ($rows as $row) {
        $total = round((float)($row['total'] ?? 0), 2);
        $igv = round((float)($row['igv'] ?? 0), 2);
        $baseVenta = round(
            (float)($row['op_gravada'] ?? 0)
            + (float)($row['op_exonerada'] ?? 0)
            + (float)($row['op_inafecta'] ?? 0)
            + (float)($row['op_export'] ?? 0),
            2
        );
        $isCreditNote = (($row['origen'] ?? '') === 'NOTA_CREDITO') || $total < 0;
        $base = contabilidadFilaSiscontBase($row, $cfg);

        // Línea de cuentas por cobrar: concentra también los datos tributarios del comprobante.
        $cliente = $base;
        $cliente['cuenta'] = $cfg['siscont_cuenta_cobrar'];
        if ($isCreditNote) {
            $cliente['haber'] = abs($total);
        } else {
            $cliente['debe'] = abs($total);
        }
        $cliente['neto1'] = abs($baseVenta);
        $cliente['mto_igv'] = abs($igv);
        $salida[] = $cliente;

        if (abs($igv) > 0.0001) {
            $tributo = $base;
            $tributo['cuenta'] = $cfg['siscont_cuenta_igv'];
            if ($isCreditNote) {
                $tributo['debe'] = abs($igv);
            } else {
                $tributo['haber'] = abs($igv);
            }
            $salida[] = $tributo;
        }

        $venta = $base;
        $venta['cuenta'] = $cfg['siscont_cuenta_venta'];
        if ($isCreditNote) {
            $venta['debe'] = abs($baseVenta);
        } else {
            $venta['haber'] = abs($baseVenta);
        }
        $salida[] = $venta;
    }

    return $salida;
}

function contabilidadDescargarXlsx(array $columnas, array $filas, string $filename, string $sheetName): void
{
    $tmp = sys_get_temp_dir() . '/conta_' . bin2hex(random_bytes(8)) . '.xlsx';
    contabilidadCrearXlsxPlano($columnas, $filas, $tmp, $sheetName);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: public');
    readfile($tmp);
    @unlink($tmp);
    exit;
}

function contabilidadNombrePeriodo(array $filtros): string
{
    $fecha = DateTime::createFromFormat('Y-m-d', (string)$filtros['fecha_fin']);
    return $fecha ? $fecha->format('m_Y') : date('m_Y');
}


function contabilidadFiltros(Contabilidad $model, int $idusuario): array
{
    $hoy = date('Y-m-d');
    $inicioDefecto = date('Y-m-01');
    $inicio = contabilidadFecha((string)($_REQUEST['fecha_inicio'] ?? ''), $inicioDefecto);
    $fin = contabilidadFecha((string)($_REQUEST['fecha_fin'] ?? ''), $hoy);

    if ($inicio > $fin) {
        [$inicio, $fin] = [$fin, $inicio];
    }

    $tipo = strtoupper(trim((string)($_REQUEST['tipo_documento'] ?? 'TODOS')));
    if (!in_array($tipo, ['TODOS', '01', '03', '07'], true)) {
        $tipo = 'TODOS';
    }

    $idsucursal = max(0, (int)($_REQUEST['idsucursal'] ?? 0));
    if ($idsucursal > 0 && !$model->sucursalPermitida($idusuario, $idsucursal)) {
        throw new RuntimeException('No tienes acceso a la sucursal seleccionada.');
    }

    return [
        'fecha_inicio' => $inicio,
        'fecha_fin' => $fin,
        'tipo_documento' => $tipo,
        'idsucursal' => $idsucursal,
        'regimen' => contabilidadRegimen((string)($_REQUEST['regimen'] ?? 'M-RER')),
    ];
}

function contabilidadCargarDatos(Contabilidad $model, int $idusuario): array
{
    $filtros = contabilidadFiltros($model, $idusuario);
    $base = $model->listarLibroVentas(
        $filtros['fecha_inicio'],
        $filtros['fecha_fin'],
        $filtros['tipo_documento'],
        $filtros['idsucursal'],
        $idusuario
    );
    $rows = contabilidadNormalizarRegistros($base, $filtros['regimen']);
    return [$filtros, $rows];
}

$op = (string)($_GET['op'] ?? $_POST['op'] ?? '');

try {
    if ($idusuario <= 0 || empty($_SESSION['nombre'])) {
        if (str_starts_with($op, 'exportar')) {
            http_response_code(401);
            exit('Sesión expirada.');
        }
        contabilidadJson(['success' => false, 'message' => 'Sesión expirada.'], 401);
    }

    switch ($op) {
        case 'bootstrap':
            contabilidadJson([
                'success' => true,
                'empresa' => $contabilidad->obtenerEmpresa(),
                'sucursales' => $contabilidad->listarSucursalesUsuario($idusuario),
                'defaults' => [
                    'fecha_inicio' => date('Y-m-01'),
                    'fecha_fin' => date('Y-m-d'),
                    'idsucursal' => 0,
                    'regimen' => 'M-RER',
                ],
            ]);
            break;

        case 'libroVentas':
            [$filtros, $rows] = contabilidadCargarDatos($contabilidad, $idusuario);
            contabilidadJson([
                'success' => true,
                'data' => $rows,
                'summary' => contabilidadResumen($rows),
                'filters' => $filtros,
            ]);
            break;

        case 'exportarExcelVentas':
        case 'exportarExcelSunat':
            [$filtros, $rows] = contabilidadCargarDatos($contabilidad, $idusuario);
            $empresa = $contabilidad->obtenerEmpresa();
            $ruc = preg_replace('/\D+/', '', (string)($empresa['documento'] ?? '')) ?: 'RUC';
            $periodo = contabilidadNombrePeriodo($filtros);
            contabilidadDescargarXlsx(
                contabilidadColumnasSunat(),
                $rows,
                $ruc . '_LibroVentas_' . $periodo . '.xlsx',
                'Lista Productos'
            );

        case 'exportarFormatoEjb':
            [$filtros, $rows] = contabilidadCargarDatos($contabilidad, $idusuario);
            $empresa = $contabilidad->obtenerEmpresa();
            $ruc = preg_replace('/\D+/', '', (string)($empresa['documento'] ?? '')) ?: 'RUC';
            $periodo = contabilidadNombrePeriodo($filtros);
            contabilidadDescargarXlsx(
                contabilidadColumnasEjb(),
                contabilidadFilasEjb($rows),
                $ruc . '_fomartoEJB_ventas_' . $periodo . '.xlsx',
                'Lista Productos'
            );

        case 'exportarAsientoContable':
            [$filtros, $rows] = contabilidadCargarDatos($contabilidad, $idusuario);
            $periodo = contabilidadNombrePeriodo($filtros);
            contabilidadDescargarXlsx(
                contabilidadColumnasSiscont(),
                contabilidadFilasSiscont($rows),
                'Asiento_Contable_' . $periodo . '.xlsx',
                'Asiento Contables Mensual'
            );

        case 'exportarTxtPleVentas':
            [$filtros, $rows] = contabilidadCargarDatos($contabilidad, $idusuario);
            $periodoInicio = substr(str_replace('-', '', $filtros['fecha_inicio']), 0, 6);
            $periodoFin = substr(str_replace('-', '', $filtros['fecha_fin']), 0, 6);
            if ($periodoInicio !== $periodoFin) {
                throw new RuntimeException('El TXT PLE 14.1 se genera por un solo período mensual. Selecciona fechas del mismo mes.');
            }

            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $empresa = $contabilidad->obtenerEmpresa();
            $ruc = preg_replace('/\D+/', '', (string)($empresa['documento'] ?? '')) ?: 'RUC';
            $filename = 'Libro_Ventas_PLE_14_1_' . $ruc . '_' . $periodoInicio . '.txt';
            header('Content-Type: text/plain; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $columns = array_values(contabilidadColumnasExcel());
            foreach ($rows as $row) {
                $values = [];
                foreach ($columns as $key) {
                    $value = $row[$key] ?? '';
                    if (is_float($value)) {
                        $value = number_format($value, $key === 'tc' ? 3 : 2, '.', '');
                    }
                    $values[] = str_replace(["\r", "\n", '|'], [' ', ' ', '/'], (string)$value);
                }
                echo implode('|', $values) . "|\r\n";
            }
            exit;

        default:
            contabilidadJson(['success' => false, 'message' => 'Operación no válida.'], 400);
    }
} catch (Throwable $e) {
    if (str_starts_with($op, 'exportar')) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'No se pudo generar el archivo: ' . $e->getMessage();
        exit;
    }
    contabilidadJson(['success' => false, 'message' => $e->getMessage()], 500);
}
