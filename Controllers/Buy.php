<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('America/Lima');

require_once __DIR__ . '/../Models/Buy.php';

$buy = new Buy();

function responderJson(
    bool $success,
    string $mensaje,
    array $extra = [],
    int $codigoHttp = 200
): void {
    http_response_code($codigoHttp);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'mensaje' => $mensaje
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function exigirSesionCompras(): void
{
    $idusuario = (int)($_SESSION['idusuario'] ?? 0);
    $permisoCompras = (int)($_SESSION['compras'] ?? 0);

    if ($idusuario <= 0 || $permisoCompras !== 1) {
        responderJson(
            false,
            'La sesión no es válida o no tiene permiso para registrar compras.',
            [],
            403
        );
    }
}

$op = (string)($_GET['op'] ?? '');

try {
    switch ($op) {
        case 'guardaryeditar':
            exigirSesionCompras();

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderJson(false, 'Método no permitido.', [], 405);
            }

            $idingreso = (int)($_POST['idingreso'] ?? 0);

            if ($idingreso > 0) {
                responderJson(
                    false,
                    'La edición de compras antiguas no está habilitada en esta etapa. Anule y registre nuevamente si corresponde.',
                    [],
                    409
                );
            }

            $detallesJson = (string)($_POST['detalles_json'] ?? '');
            $detalles = json_decode($detallesJson, true);

            if (!is_array($detalles)) {
                responderJson(
                    false,
                    'El detalle de la compra no tiene un formato válido.',
                    [],
                    422
                );
            }

            $cabecera = [
                'idproveedor' => (int)($_POST['idproveedor'] ?? 0),
                'idusuario' => (int)($_SESSION['idusuario'] ?? 0),
                'idsucursal' => (int)(
                    $_SESSION['idsucursal_activa']
                    ?? $_SESSION['idsucursal']
                    ?? 0
                ),
                'idcaja' => (int)($_SESSION['idcaja_activa'] ?? 0),
                'idapertura' => (int)($_SESSION['idapertura_activa'] ?? 0),
                'modo_caja' => (string)($_SESSION['modo_caja'] ?? 'LEGACY'),
                'tipo_comprobante' => (string)($_POST['tipo_comprobante'] ?? ''),
                'serie_comprobante' => (string)($_POST['serie_comprobante'] ?? ''),
                'num_comprobante' => (string)($_POST['num_comprobante'] ?? ''),
                'fecha_hora' => (string)($_POST['fecha_hora'] ?? ''),
                'impuesto' => (float)($_POST['impuesto'] ?? 0),
                'observacion' => (string)($_POST['observacion'] ?? ''),
                'idtipopago' => (int)($_POST['idtipopago'] ?? 0),
                'idforma_pago' => (int)($_POST['idforma_pago'] ?? 0),
                'numero_operacion' => (string)($_POST['numero_operacion'] ?? '')
            ];

            $resultado = $buy->insertar($cabecera, $detalles);

            responderJson(
                true,
                'Compra registrada correctamente.',
                [
                    'idingreso' => (int)$resultado['idingreso'],
                    'tipo_compra' => (string)$resultado['tipo_compra'],
                    'total_compra' => (float)$resultado['total_compra'],
                    'condicion_pago' => (string)$resultado['condicion_pago'],
                    'estado_pago' => (string)$resultado['estado_pago'],
                    'forma_pago' => $resultado['forma_pago']
                ]
            );
            break;

        case 'anular':
            exigirSesionCompras();

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderJson(false, 'Método no permitido.', [], 405);
            }

            $idingreso = (int)($_POST['idingreso'] ?? 0);
            $resultado = $buy->anular(
                $idingreso,
                [
                    'idusuario' => (int)($_SESSION['idusuario'] ?? 0),
                    'idsucursal' => (int)(
                        $_SESSION['idsucursal_activa']
                        ?? $_SESSION['idsucursal']
                        ?? 0
                    ),
                    'idcaja' => (int)($_SESSION['idcaja_activa'] ?? 0),
                    'idapertura' => (int)($_SESSION['idapertura_activa'] ?? 0),
                    'modo_caja' => (string)($_SESSION['modo_caja'] ?? 'LEGACY')
                ]
            );

            responderJson(
                true,
                (string)($resultado['mensaje'] ?? 'Compra anulada correctamente.')
            );
            break;

        case 'mostrar':
            exigirSesionCompras();

            $idingreso = (int)($_POST['idingreso'] ?? $_GET['idingreso'] ?? 0);
            $compra = $buy->mostrar($idingreso);

            if (!$compra) {
                responderJson(false, 'La compra no existe.', [], 404);
            }

            responderJson(true, 'Compra encontrada.', ['compra' => $compra]);
            break;

        case 'listarDetalle':
            exigirSesionCompras();

            $idingreso = (int)($_GET['id'] ?? $_POST['idingreso'] ?? 0);
            $detalles = $buy->listarDetalle($idingreso);

            responderJson(true, 'Detalle cargado.', ['detalles' => $detalles]);
            break;

        case 'listar':
            exigirSesionCompras();

            $registros = $buy->listar();
            $data = [];

            foreach ($registros as $reg) {
                $idingreso = (int)$reg['idingreso'];
                $estado = (string)$reg['estado'];
                $tipoCompra = (string)$reg['tipo_compra'];

                $badgeTipo = match ($tipoCompra) {
                    'MIXTA' => '<span class="badge badge-info">Mixta</span>',
                    'NO_INVENTARIO' => '<span class="badge badge-secondary">Gasto / servicio</span>',
                    default => '<span class="badge badge-primary">Inventario</span>'
                };

                $opciones = '<button type="button" class="btn btn-info btn-sm" '
                    . 'onclick="mostrarCompra(' . $idingreso . ')" title="Ver compra">'
                    . '<i class="fas fa-eye"></i></button>';

                if ($estado === 'Aceptado') {
                    $opciones .= ' <button type="button" class="btn btn-danger btn-sm" '
                        . 'onclick="anularCompra(' . $idingreso . ')" title="Anular compra">'
                        . '<i class="fas fa-times"></i></button>';
                }

                $documento = trim(
                    (string)$reg['serie_comprobante']
                    . '-'
                    . (string)$reg['num_comprobante'],
                    '-'
                );

                $data[] = [
                    '0' => $opciones,
                    '1' => htmlspecialchars((string)$reg['fecha'], ENT_QUOTES, 'UTF-8'),
                    '2' => htmlspecialchars((string)$reg['proveedor'], ENT_QUOTES, 'UTF-8'),
                    '3' => htmlspecialchars((string)$reg['usuario'], ENT_QUOTES, 'UTF-8'),
                    '4' => htmlspecialchars((string)$reg['tipo_comprobante'], ENT_QUOTES, 'UTF-8'),
                    '5' => htmlspecialchars($documento, ENT_QUOTES, 'UTF-8'),
                    '6' => $badgeTipo,
                    '7' => 'S/ ' . number_format((float)$reg['total_compra'], 2, '.', ','),
                    '8' => $estado === 'Aceptado'
                        ? '<span class="badge badge-success">Aceptado</span>'
                        : '<span class="badge badge-danger">Anulado</span>'
                ];
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(
                [
                    'sEcho' => 1,
                    'iTotalRecords' => count($data),
                    'iTotalDisplayRecords' => count($data),
                    'aaData' => $data
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            exit;

        case 'datosFormulario':
            exigirSesionCompras();

            responderJson(
                true,
                'Datos del formulario cargados.',
                ['datos' => $buy->datosFormulario()]
            );
            break;

        case 'listarArticulos':
        case 'productosCompra':
            exigirSesionCompras();

            responderJson(
                true,
                'Productos cargados.',
                ['productos' => $buy->listarProductosCompra()]
            );
            break;

        case 'selectProveedor':
            exigirSesionCompras();

            require_once __DIR__ . '/../Models/Person.php';
            $person = new Person();
            $proveedores = $person->listarp();

            header('Content-Type: text/html; charset=utf-8');
            echo '<option value="">Seleccione un proveedor...</option>';

            foreach ($proveedores as $reg) {
                $documento = trim((string)($reg['num_documento'] ?? ''));
                $texto = trim((string)$reg['nombre']);
                if ($documento !== '') {
                    $texto .= ' · ' . $documento;
                }

                echo '<option value="'
                    . (int)$reg['idpersona']
                    . '">'
                    . htmlspecialchars($texto, ENT_QUOTES, 'UTF-8')
                    . '</option>';
            }
            exit;

        case 'consultarProveedorApi':
            exigirSesionCompras();

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderJson(false, 'Método no permitido.', [], 405);
            }

            require_once __DIR__ . '/../Models/Person.php';
            $person = new Person();

            $tipoDocumento = strtoupper(trim((string)($_POST['tipo_documento'] ?? '')));
            $numeroDocumento = preg_replace('/\D/', '', (string)($_POST['num_documento'] ?? ''));

            if (!in_array($tipoDocumento, ['DNI', 'RUC'], true)) {
                responderJson(false, 'Selecciona DNI o RUC para consultar.', [], 422);
            }

            $longitudEsperada = $tipoDocumento === 'DNI' ? 8 : 11;
            if (strlen($numeroDocumento) !== $longitudEsperada) {
                responderJson(
                    false,
                    $tipoDocumento . ' debe tener ' . $longitudEsperada . ' dígitos.',
                    [],
                    422
                );
            }

            $existente = $person->mostrarPorDocumento($numeroDocumento);
            if ($existente) {
                if (strcasecmp((string)($existente['tipo_persona'] ?? ''), 'Proveedor') === 0) {
                    responderJson(
                        true,
                        'El proveedor ya se encuentra registrado.',
                        [
                            'existente' => true,
                            'proveedor' => [
                                'idpersona' => (int)$existente['idpersona'],
                                'tipo_documento' => (string)($existente['tipo_documento'] ?? $tipoDocumento),
                                'num_documento' => (string)($existente['num_documento'] ?? $numeroDocumento),
                                'nombre' => (string)($existente['nombre'] ?? ''),
                                'direccion' => (string)($existente['direccion'] ?? ''),
                                'telefono' => (string)($existente['telefono'] ?? ''),
                                'email' => (string)($existente['email'] ?? '')
                            ]
                        ]
                    );
                }

                responderJson(
                    false,
                    'El documento ya está registrado como ' . (string)($existente['tipo_persona'] ?? 'persona') . '.',
                    [],
                    409
                );
            }

            $respuestaApi = json_decode(
                (string)$person->getCustomerInfo($numeroDocumento, $tipoDocumento),
                true
            );

            if (!is_array($respuestaApi) || empty($respuestaApi['estado'])) {
                responderJson(
                    false,
                    (string)($respuestaApi['mensaje'] ?? 'El servicio de consulta no devolvió información para el documento.'),
                    ['api' => $respuestaApi],
                    422
                );
            }

            responderJson(
                true,
                'Datos encontrados correctamente.',
                [
                    'existente' => false,
                    'tipo_documento' => $tipoDocumento,
                    'num_documento' => $numeroDocumento,
                    'resultado' => is_array($respuestaApi['resultado'] ?? null)
                        ? $respuestaApi['resultado']
                        : []
                ]
            );
            break;

        case 'crearProveedor':
            exigirSesionCompras();

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderJson(false, 'Método no permitido.', [], 405);
            }

            require_once __DIR__ . '/../Models/Person.php';
            $person = new Person();

            $tipoDocumento = strtoupper(trim((string)($_POST['tipo_documento'] ?? 'RUC')));
            $numeroDocumento = preg_replace('/\D/', '', (string)($_POST['num_documento'] ?? ''));
            $nombreProveedor = trim((string)($_POST['nombre'] ?? ''));
            $direccionProveedor = trim((string)($_POST['direccion'] ?? ''));
            $telefonoProveedor = trim((string)($_POST['telefono'] ?? ''));
            $emailProveedor = trim((string)($_POST['email'] ?? ''));

            if (!in_array($tipoDocumento, ['DNI', 'RUC'], true)) {
                responderJson(false, 'El tipo de documento no es válido.', [], 422);
            }

            $longitudEsperada = $tipoDocumento === 'DNI' ? 8 : 11;
            if (strlen($numeroDocumento) !== $longitudEsperada) {
                responderJson(false, 'El número de documento no es válido.', [], 422);
            }

            if ($nombreProveedor === '') {
                responderJson(false, 'El nombre o razón social es obligatorio.', [], 422);
            }

            if ($emailProveedor !== '' && !filter_var($emailProveedor, FILTER_VALIDATE_EMAIL)) {
                responderJson(false, 'El correo electrónico no es válido.', [], 422);
            }

            $existente = $person->mostrarPorDocumento($numeroDocumento);
            if ($existente) {
                if (strcasecmp((string)($existente['tipo_persona'] ?? ''), 'Proveedor') === 0) {
                    responderJson(
                        true,
                        'El proveedor ya estaba registrado y fue seleccionado.',
                        [
                            'existente' => true,
                            'proveedor' => [
                                'idpersona' => (int)$existente['idpersona'],
                                'nombre' => (string)($existente['nombre'] ?? ''),
                                'num_documento' => (string)($existente['num_documento'] ?? $numeroDocumento)
                            ]
                        ]
                    );
                }

                responderJson(false, 'El documento ya está registrado con otro tipo de persona.', [], 409);
            }

            $idpersona = (int)$person->insertar(
                'Proveedor',
                $nombreProveedor,
                $tipoDocumento,
                $numeroDocumento,
                $direccionProveedor,
                $telefonoProveedor,
                $emailProveedor
            );

            if ($idpersona <= 0) {
                responderJson(false, 'No se pudo registrar el proveedor.', [], 500);
            }

            responderJson(
                true,
                'Proveedor registrado correctamente.',
                [
                    'existente' => false,
                    'proveedor' => [
                        'idpersona' => $idpersona,
                        'tipo_documento' => $tipoDocumento,
                        'num_documento' => $numeroDocumento,
                        'nombre' => $nombreProveedor,
                        'direccion' => $direccionProveedor,
                        'telefono' => $telefonoProveedor,
                        'email' => $emailProveedor
                    ]
                ]
            );
            break;

        case 'previsualizarProductosMasivos':
            exigirSesionCompras();

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderJson(false, 'Método no permitido.', [], 405);
            }

            if (
                !isset($_FILES['archivo_productos'])
                || $_FILES['archivo_productos']['error'] !== UPLOAD_ERR_OK
            ) {
                responderJson(false, 'No se recibió un archivo válido.', [], 400);
            }

            require_once __DIR__ . '/../Libraries/ProductImportReader.php';

            $archivo = $_FILES['archivo_productos'];
            if ((int)($archivo['size'] ?? 0) > 8 * 1024 * 1024) {
                responderJson(false, 'El archivo supera el máximo permitido de 8 MB.', [], 422);
            }

            $nombreOriginal = basename((string)($archivo['name'] ?? ''));
            $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
            if (!in_array($extension, ['csv', 'xlsx'], true)) {
                responderJson(false, 'Solo se permiten archivos CSV o XLSX.', [], 422);
            }

            $filas = ProductImportReader::read(
                (string)$archivo['tmp_name'],
                $nombreOriginal
            );

            responderJson(
                true,
                'Archivo preparado para revisión.',
                [
                    'filas' => $filas,
                    'total' => count($filas),
                    'tipo' => $extension
                ]
            );
            break;

        case 'descargarPlantillaCompraCsv':
            exigirSesionCompras();

            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="plantilla_productos_compra.csv"');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('X-Content-Type-Options: nosniff');

            echo "\xEF\xBB\xBF";
            $salida = fopen('php://output', 'wb');
            fputcsv($salida, [
                'Producto', 'SKU', 'Cantidad', 'PrecioCompra', 'PrecioVenta',
                'Categoria', 'Subcategoria', 'Almacen', 'UnidadMedida'
            ], ',');
            fclose($salida);
            exit;

        default:
            responderJson(false, 'Operación no válida.', [], 404);
    }
} catch (Throwable $error) {
    error_log('[COMPRAS] ' . $error->getMessage());

    responderJson(
        false,
        $error->getMessage(),
        [],
        400
    );
}
