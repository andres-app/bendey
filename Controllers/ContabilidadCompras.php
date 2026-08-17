<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('America/Lima');
require_once __DIR__ . '/../Models/ContabilidadCompras.php';

$model = new ContabilidadCompras();
$idusuario = (int)($_SESSION['idusuario'] ?? 0);

function ccJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ccSesion(int $idusuario): void
{
    if ($idusuario <= 0 || !isset($_SESSION['nombre'])) {
        ccJson(['success' => false, 'message' => 'La sesión ha expirado.'], 401);
    }
}

function ccFecha(string $value, string $fallback): string
{
    $d = DateTime::createFromFormat('Y-m-d', trim($value));
    $e = DateTime::getLastErrors();
    return $d && ($e === false || ($e['warning_count'] === 0 && $e['error_count'] === 0))
        ? $d->format('Y-m-d') : $fallback;
}

function ccPeriodo(string $value): string
{
    $value = trim($value);
    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) ? $value : date('Y-m');
}

function ccRegimen(string $value): string
{
    $value = strtoupper(trim($value));
    return in_array($value, ['M-RER', 'M-RMT', 'M-RG'], true) ? $value : 'M-RER';
}

function ccTipoDocumentoSunat(string $tipo): string
{
    $tipo = strtoupper(trim($tipo));
    return match ($tipo) {
        'FACTURA', 'FACTURA ELECTRÓNICA', 'FACTURA ELECTRONICA' => '01',
        'BOLETA', 'BOLETA DE VENTA', 'BOLETA ELECTRÓNICA', 'BOLETA ELECTRONICA' => '03',
        'TICKET' => '12',
        'RECIBO' => '14',
        default => '00',
    };
}

function ccTipoDocProveedor(string $tipo, string $numero): string
{
    $tipo = strtoupper(trim($tipo));
    $numero = preg_replace('/\D+/', '', $numero) ?? '';
    if ($tipo === 'RUC' || strlen($numero) === 11) return '6';
    if ($tipo === 'DNI' || strlen($numero) === 8) return '1';
    if ($tipo === 'CE' || str_contains($tipo, 'EXTRANJ')) return '4';
    if (str_contains($tipo, 'PASAP')) return '7';
    return '';
}

function ccNumeroVisible(string $numero): string
{
    $numero = trim($numero);
    if ($numero !== '' && preg_match('/^\d+$/', $numero)) {
        $v = ltrim($numero, '0');
        return $v === '' ? '0' : $v;
    }
    return $numero;
}

function ccMesesDiferencia(string $desdeYm, string $hastaYm): int
{
    [$ya, $ma] = array_map('intval', explode('-', $desdeYm));
    [$yb, $mb] = array_map('intval', explode('-', $hastaYm));
    return (($yb - $ya) * 12) + ($mb - $ma);
}

function ccNormalizar(array $rows, string $periodo, string $regimen): array
{
    $salida = [];
    $correlativo = 0;

    foreach ($rows as $row) {
        try {
            $fecha = new DateTime((string)$row['fecha_hora']);
        } catch (Throwable $e) {
            continue;
        }

        $correlativo++;
        $tipoDoc = ccTipoDocumentoSunat((string)$row['tipo_comprobante']);
        $impuestoPct = max(0.0, (float)($row['impuesto'] ?? 0));
        $total = round((float)($row['total_compra'] ?? 0), 2);
        $base = $impuestoPct > 0 ? round($total / (1 + ($impuestoPct / 100)), 2) : 0.0;
        $igv = $impuestoPct > 0 ? round($total - $base, 2) : 0.0;

        $emisionYm = $fecha->format('Y-m');
        $difMeses = ccMesesDiferencia($emisionYm, $periodo);
        $documentoDaCredito = in_array($tipoDoc, ['01', '14'], true) && $impuestoPct > 0;
        $fueraPeriodo = $difMeses < 0;

        if (!$documentoDaCredito) {
            $estadoPle = 0;
        } elseif ($difMeses === 0) {
            $estadoPle = 1;
        } elseif ($difMeses > 0 && $difMeses <= 12) {
            $estadoPle = 6;
        } elseif ($difMeses > 12) {
            $estadoPle = 7;
        } else {
            $estadoPle = 1;
        }

        $baseCredito = 0.0;
        $igvCredito = 0.0;
        $baseSinCredito = 0.0;
        $igvSinCredito = 0.0;
        $noGravada = 0.0;

        if ($impuestoPct <= 0) {
            $noGravada = $total;
        } elseif ($documentoDaCredito && in_array($estadoPle, [1, 6], true)) {
            $baseCredito = $base;
            $igvCredito = $igv;
        } else {
            $baseSinCredito = $base;
            $igvSinCredito = $igv;
        }

        $proveedorNumero = trim((string)($row['proveedor_num_documento'] ?? ''));
        $proveedorTipo = ccTipoDocProveedor((string)($row['proveedor_tipo_documento'] ?? ''), $proveedorNumero);
        $cuo = $regimen === 'M-RER' ? 'RER' : ('COMPRA' . (int)$row['idingreso']);

        $salida[] = [
            'periodo' => str_replace('-', '', $periodo) . '00',
            'cod_unic' => $correlativo,
            'regimen' => $regimen,
            'f_emision' => $fecha->format('d/m/Y'),
            'f_vencimiento' => '',
            'tipo_doc' => $tipoDoc,
            'serie' => strtoupper(trim((string)$row['serie_comprobante'])),
            'dua_dsi' => '',
            'numero' => ccNumeroVisible((string)$row['num_comprobante']),
            'numero_final' => '',
            't_doc' => $proveedorTipo,
            'numero_proveedor' => $proveedorNumero,
            'razon_social' => trim((string)$row['proveedor_nombre']),
            'op_gravada' => $baseCredito,
            'igv' => $igvCredito,
            'op_gravada_mixta' => 0.0,
            'igv_mixto' => 0.0,
            'op_sin_credito' => $baseSinCredito,
            'igv_sin_credito' => $igvSinCredito,
            'op_no_gravada' => $noGravada,
            'isc' => 0.0,
            'otros_tributos' => 0.0,
            'total' => $total,
            'moneda' => 'PEN',
            'tc' => 1.000,
            'fec_comp_modif' => '',
            'tipo_doc_modif' => '',
            'serie_doc_modif' => '',
            'cod_aduana' => '',
            'num_doc_modif' => '',
            'fec_detraccion' => '',
            'num_detraccion' => '',
            'retencion' => '',
            'clasificacion_bs' => '',
            'id_contrato' => '',
            'err_tc' => '',
            'err_no_habido' => '',
            'err_exoneracion' => '',
            'err_dni' => '',
            'medio_pago' => '',
            'estado' => $estadoPle,
            '_idingreso' => (int)$row['idingreso'],
            '_cuo_ple' => $cuo,
            '_correlativo_ple' => 'M' . $correlativo,
            '_fuera_periodo' => $fueraPeriodo,
            '_tipo_original' => (string)$row['tipo_comprobante'],
            '_impuesto_pct' => $impuestoPct,
        ];
    }

    return $salida;
}

function ccResumen(array $rows): array
{
    $r = ['registros' => count($rows), 'base' => 0.0, 'igv' => 0.0, 'no_gravada' => 0.0, 'total' => 0.0, 'observados' => 0];
    foreach ($rows as $row) {
        $r['base'] += (float)$row['op_gravada'] + (float)$row['op_gravada_mixta'] + (float)$row['op_sin_credito'];
        $r['igv'] += (float)$row['igv'] + (float)$row['igv_mixto'] + (float)$row['igv_sin_credito'];
        $r['no_gravada'] += (float)$row['op_no_gravada'];
        $r['total'] += (float)$row['total'];
        if (!empty($row['_fuera_periodo'])) $r['observados']++;
    }
    foreach (['base','igv','no_gravada','total'] as $k) $r[$k] = round($r[$k], 2);
    return $r;
}

function ccPleValue(mixed $value, bool $money = false, bool $exchange = false): string
{
    if ($value === null || $value === '') return '';
    if ($money) return number_format((float)$value, 2, '.', '');
    if ($exchange) return number_format((float)$value, 3, '.', '');
    $text = trim((string)$value);
    $text = str_replace(["\r", "\n", '|'], [' ', ' ', ' '], $text);
    return $text;
}

function ccPleLine(array $r): string
{
    $fields = [
        $r['periodo'], $r['_cuo_ple'], $r['_correlativo_ple'], $r['f_emision'], $r['f_vencimiento'],
        $r['tipo_doc'], $r['serie'], $r['dua_dsi'], $r['numero'], $r['numero_final'],
        $r['t_doc'], $r['numero_proveedor'], $r['razon_social'],
        ccPleValue($r['op_gravada'], true), ccPleValue($r['igv'], true),
        ccPleValue($r['op_gravada_mixta'], true), ccPleValue($r['igv_mixto'], true),
        ccPleValue($r['op_sin_credito'], true), ccPleValue($r['igv_sin_credito'], true),
        ccPleValue($r['op_no_gravada'], true), ccPleValue($r['isc'], true), ccPleValue($r['otros_tributos'], true),
        ccPleValue($r['total'], true), $r['moneda'], ccPleValue($r['tc'], false, true),
        $r['fec_comp_modif'], $r['tipo_doc_modif'], $r['serie_doc_modif'], $r['cod_aduana'], $r['num_doc_modif'],
        $r['fec_detraccion'], $r['num_detraccion'], $r['retencion'], $r['clasificacion_bs'], $r['id_contrato'],
        $r['err_tc'], $r['err_no_habido'], $r['err_exoneracion'], $r['err_dni'], $r['medio_pago'], $r['estado']
    ];
    return implode('|', array_map(static fn($v) => ccPleValue($v), $fields)) . '|';
}

$op = (string)($_GET['op'] ?? '');

try {
    ccSesion($idusuario);

    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');

    if ($op === 'bootstrap') {
        ccJson([
            'success' => true,
            'empresa' => $model->obtenerEmpresa(),
            'sucursales' => $model->listarSucursalesUsuario($idusuario),
            'defaults' => [
                'fecha_inicio' => $monthStart,
                'fecha_fin' => $today,
                'periodo' => date('Y-m'),
                'idsucursal' => (int)($_SESSION['idsucursal'] ?? 0),
            ],
        ]);
    }

    $fechaInicio = ccFecha((string)($_GET['fecha_inicio'] ?? ''), $monthStart);
    $fechaFin = ccFecha((string)($_GET['fecha_fin'] ?? ''), $today);
    if ($fechaInicio > $fechaFin) {
        ccJson(['success' => false, 'message' => 'El rango de fechas no es válido.'], 422);
    }

    $buscarPor = (string)($_GET['buscar_por'] ?? 'emision');
    $buscarPor = in_array($buscarPor, ['emision', 'registro'], true) ? $buscarPor : 'emision';
    $tipoDocumento = strtoupper(trim((string)($_GET['tipo_documento'] ?? 'TODOS')));
    $tipoDocumento = in_array($tipoDocumento, ['TODOS','01','03','12','14','00'], true) ? $tipoDocumento : 'TODOS';
    $idsucursal = max(0, (int)($_GET['idsucursal'] ?? 0));
    $periodo = ccPeriodo((string)($_GET['periodo'] ?? date('Y-m')));
    $regimen = ccRegimen((string)($_GET['regimen'] ?? 'M-RER'));

    if (!$model->sucursalPermitida($idusuario, $idsucursal)) {
        ccJson(['success' => false, 'message' => 'No tiene acceso a la sucursal seleccionada.'], 403);
    }

    $raw = $model->listarCompras($fechaInicio, $fechaFin, $buscarPor, $tipoDocumento, $idsucursal, $idusuario);
    $rows = ccNormalizar($raw, $periodo, $regimen);

    if ($op === 'libroCompras') {
        ccJson([
            'success' => true,
            'data' => $rows,
            'summary' => ccResumen($rows),
            'periodo' => $periodo,
            'regimen' => $regimen,
        ]);
    }

    if ($op === 'exportarTxtPleCompras') {
        $observados = array_values(array_filter($rows, static fn($r) => !empty($r['_fuera_periodo'])));
        if ($observados) {
            ccJson([
                'success' => false,
                'message' => 'Hay comprobantes cuya fecha de emisión es posterior al período contable seleccionado. Corrija el período o el rango antes de generar el TXT SUNAT.'
            ], 422);
        }

        $empresa = $model->obtenerEmpresa();
        $ruc = preg_replace('/\D+/', '', (string)($empresa['documento'] ?? '')) ?? '';
        if (strlen($ruc) !== 11) {
            ccJson(['success' => false, 'message' => 'El RUC de la empresa no está configurado correctamente.'], 422);
        }

        $periodoPle = str_replace('-', '', $periodo) . '00';
        $contenido = count($rows) > 0 ? '1' : '0';
        $filename = 'LE' . $ruc . $periodoPle . '08010000' . '1' . $contenido . '1' . '1' . '.txt';
        $content = count($rows) ? implode("\r\n", array_map('ccPleLine', $rows)) . "\r\n" : '';

        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('X-Content-Type-Options: nosniff');
        echo $content;
        exit;
    }

    ccJson(['success' => false, 'message' => 'Operación no válida.'], 404);
} catch (Throwable $e) {
    error_log('[CONTABILIDAD COMPRAS] ' . $e->getMessage());
    ccJson(['success' => false, 'message' => 'No se pudo procesar el Libro Electrónico de Compras.'], 500);
}
