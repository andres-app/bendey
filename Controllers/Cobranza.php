<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Cobranza.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function responderCobranza(array $data): void
{
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );
    exit;
}

$idusuario = (int)($_SESSION['idusuario'] ?? 0);
$permisoVentas = (int)($_SESSION['ventas'] ?? 0);

if ($idusuario <= 0 || $permisoVentas !== 1) {
    responderCobranza([
        'success' => false,
        'mensaje' => 'No tiene una sesión válida o permiso para gestionar cobranzas.'
    ]);
}

$cobranza = new Cobranza();
$op = $_GET['op'] ?? '';

try {
    switch ($op) {
        case 'listar':
            $registros = $cobranza->listarCuentasPorCobrar();
            $data = [];

            foreach ($registros as $registro) {
                $idventa = (int)$registro['idventa'];
                $saldo = (float)$registro['saldo'];
                $estado = strtoupper((string)$registro['estado_cobranza']);

                switch ($estado) {
                    case 'PAGADO':
                        $claseEstado = 'success';
                        break;
                    case 'PARCIAL':
                        $claseEstado = 'warning';
                        break;
                    case 'VENCIDO':
                        $claseEstado = 'danger';
                        break;
                    default:
                        $claseEstado = 'info';
                        break;
                }

                $boton = $saldo > 0
                    ? '<button class="btn btn-success btn-sm" onclick="abrirCobranza('
                        . $idventa
                        . ')" title="Registrar pago"><i class="fas fa-hand-holding-usd"></i></button>'
                    : '<button class="btn btn-secondary btn-sm" disabled title="Sin saldo"><i class="fas fa-check"></i></button>';

                $data[] = [
                    '0' => $boton,
                    '1' => htmlspecialchars((string)$registro['fecha'], ENT_QUOTES, 'UTF-8'),
                    '2' => htmlspecialchars((string)$registro['cliente'], ENT_QUOTES, 'UTF-8'),
                    '3' => htmlspecialchars(
                        (string)$registro['serie_comprobante']
                        . '-'
                        . (string)$registro['num_comprobante'],
                        ENT_QUOTES,
                        'UTF-8'
                    ),
                    '4' => 'S/ ' . number_format((float)$registro['total_credito'], 2),
                    '5' => 'S/ ' . number_format((float)$registro['total_pagado'], 2),
                    '6' => 'S/ ' . number_format($saldo, 2),
                    '7' => htmlspecialchars(
                        (string)($registro['proximo_vencimiento'] ?? '-'),
                        ENT_QUOTES,
                        'UTF-8'
                    ),
                    '8' => '<span class="badge badge-' . $claseEstado . '">'
                        . htmlspecialchars($estado, ENT_QUOTES, 'UTF-8')
                        . '</span>',
                    '9' => htmlspecialchars((string)$registro['estado_sunat'], ENT_QUOTES, 'UTF-8')
                ];
            }

            responderCobranza([
                'sEcho' => 1,
                'iTotalRecords' => count($data),
                'iTotalDisplayRecords' => count($data),
                'aaData' => $data
            ]);
            break;

        case 'detalle':
            $idventa = (int)($_GET['idventa'] ?? 0);

            responderCobranza([
                'success' => true,
                'data' => $cobranza->obtenerDetalleVenta($idventa)
            ]);
            break;

        case 'formas_pago':
            responderCobranza([
                'success' => true,
                'data' => $cobranza->obtenerFormasPagoCobranza()
            ]);
            break;

        case 'registrar':
            $contenido = file_get_contents('php://input');
            $payload = json_decode($contenido ?: '{}', true);

            if (!is_array($payload)) {
                throw new RuntimeException('El contenido enviado no es válido.');
            }

            $resultado = $cobranza->registrar(
                (int)($payload['idventa'] ?? 0),
                $idusuario,
                is_array($payload['aplicaciones'] ?? null)
                    ? $payload['aplicaciones']
                    : [],
                is_array($payload['pagos'] ?? null)
                    ? $payload['pagos']
                    : [],
                trim((string)($payload['observacion'] ?? ''))
            );

            responderCobranza($resultado);
            break;

        default:
            responderCobranza([
                'success' => false,
                'mensaje' => 'Operación no válida.'
            ]);
    }
} catch (Throwable $e) {
    error_log('[CONTROLADOR COBRANZA] ' . $e->getMessage());

    responderCobranza([
        'success' => false,
        'mensaje' => $e->getMessage()
    ]);
}
