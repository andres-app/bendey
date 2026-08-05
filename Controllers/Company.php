<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../Models/Company.php';

$company = new Company();

$op = trim(
    (string)($_GET['op'] ?? '')
);

/*
|--------------------------------------------------------------------------
| Respuesta JSON
|--------------------------------------------------------------------------
*/
function responderCompanyJson(
    mixed $respuesta,
    int $codigoHttp = 200
): void {
    http_response_code(
        $codigoHttp
    );

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    echo json_encode(
        $respuesta,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LOGO DE LA EMPRESA
|--------------------------------------------------------------------------
*/
function guardarLogoEmpresaSubido(
    array $archivo,
    int $idNegocio
): array {
    $error = (int)($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        return [];
    }

    if ($error !== UPLOAD_ERR_OK) {
        $mensajes = [
            UPLOAD_ERR_INI_SIZE =>
                'El logo supera el tamaño permitido por el servidor.',
            UPLOAD_ERR_FORM_SIZE =>
                'El logo supera el tamaño permitido.',
            UPLOAD_ERR_PARTIAL =>
                'El logo se cargó de manera incompleta.',
            UPLOAD_ERR_NO_TMP_DIR =>
                'No existe el directorio temporal para cargar el logo.',
            UPLOAD_ERR_CANT_WRITE =>
                'No se pudo escribir el logo en el servidor.',
            UPLOAD_ERR_EXTENSION =>
                'Una extensión del servidor bloqueó la carga del logo.'
        ];

        throw new RuntimeException(
            $mensajes[$error]
            ?? 'No se pudo cargar el logo.'
        );
    }

    $rutaTemporal = (string)($archivo['tmp_name'] ?? '');
    $tamano = (int)($archivo['size'] ?? 0);

    if (
        $rutaTemporal === ''
        || !is_uploaded_file($rutaTemporal)
    ) {
        throw new RuntimeException(
            'El archivo recibido no es una carga válida.'
        );
    }

    if (
        $tamano <= 0
        || $tamano > (2 * 1024 * 1024)
    ) {
        throw new RuntimeException(
            'El logo debe pesar como máximo 2 MB.'
        );
    }

    if (!function_exists('finfo_open')) {
        throw new RuntimeException(
            'La extensión Fileinfo de PHP no está disponible.'
        );
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    if ($finfo === false) {
        throw new RuntimeException(
            'No se pudo validar el tipo del logo.'
        );
    }

    $mime = (string)finfo_file(
        $finfo,
        $rutaTemporal
    );

    finfo_close($finfo);

    $formatos = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        /*
         * FPDF no procesa WEBP directamente.
         * Se acepta, pero se guarda convertido a PNG.
         */
        'image/webp' => 'png'
    ];

    if (!isset($formatos[$mime])) {
        throw new RuntimeException(
            'El logo debe ser PNG, JPG o WEBP.'
        );
    }

    $medidas = @getimagesize(
        $rutaTemporal
    );

    if (!is_array($medidas)) {
        throw new RuntimeException(
            'El archivo seleccionado no es una imagen válida.'
        );
    }

    $ancho = (int)($medidas[0] ?? 0);
    $alto = (int)($medidas[1] ?? 0);

    if (
        $ancho < 80
        || $alto < 80
    ) {
        throw new RuntimeException(
            'El logo debe tener al menos 80 × 80 píxeles.'
        );
    }

    if (
        $ancho > 6000
        || $alto > 6000
    ) {
        throw new RuntimeException(
            'Las dimensiones del logo son demasiado grandes.'
        );
    }

    $directorio =
        dirname(__DIR__)
        . '/Assets/img/company';

    if (
        !is_dir($directorio)
        && !mkdir(
            $directorio,
            0755,
            true
        )
        && !is_dir($directorio)
    ) {
        throw new RuntimeException(
            'No se pudo crear el directorio de logos.'
        );
    }

    if (!is_writable($directorio)) {
        throw new RuntimeException(
            'Assets/img/company no tiene permisos de escritura.'
        );
    }

    $nombre =
        'empresa_'
        . $idNegocio
        . '_'
        . date('Ymd_His')
        . '_'
        . bin2hex(random_bytes(4))
        . '.'
        . $formatos[$mime];

    $destino =
        $directorio
        . DIRECTORY_SEPARATOR
        . $nombre;

    if ($mime === 'image/webp') {
        if (
            !function_exists('imagecreatefromwebp')
            || !function_exists('imagepng')
        ) {
            throw new RuntimeException(
                'El servidor no puede convertir imágenes WEBP. '
                . 'Utiliza un logo PNG o JPG.'
            );
        }

        $imagenWebp = @imagecreatefromwebp(
            $rutaTemporal
        );

        if ($imagenWebp === false) {
            throw new RuntimeException(
                'No se pudo leer el logo WEBP.'
            );
        }

        imagealphablending(
            $imagenWebp,
            false
        );

        imagesavealpha(
            $imagenWebp,
            true
        );

        $guardadoWebp = imagepng(
            $imagenWebp,
            $destino,
            6
        );

        imagedestroy(
            $imagenWebp
        );

        if (!$guardadoWebp) {
            throw new RuntimeException(
                'No se pudo convertir el logo WEBP a PNG.'
            );
        }
    } elseif (!move_uploaded_file(
        $rutaTemporal,
        $destino
    )) {
        throw new RuntimeException(
            'No se pudo guardar el nuevo logo.'
        );
    }

    @chmod(
        $destino,
        0644
    );

    return [
        'nombre' => $nombre,
        'ruta' => $destino
    ];
}

function eliminarLogoEmpresaFisico(
    string $nombreLogo
): void {
    $nombreLogo = basename(
        trim($nombreLogo)
    );

    if (
        $nombreLogo === ''
        || $nombreLogo === 'default_logo.png'
    ) {
        return;
    }

    if (
        !preg_match(
            '/^[A-Za-z0-9._-]+\.(png|jpe?g|webp)$/i',
            $nombreLogo
        )
    ) {
        return;
    }

    $ruta =
        dirname(__DIR__)
        . '/Assets/img/company/'
        . $nombreLogo;

    if (is_file($ruta)) {
        @unlink($ruta);
    }
}

/*
|--------------------------------------------------------------------------
| Validar sesión
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['nombre'])) {
    responderCompanyJson([
        'success' => false,
        'mensaje' => 'Acceso no autorizado.'
    ], 403);
}

try {
    switch ($op) {

        /*
        |--------------------------------------------------------------------------
        | GUARDAR / EDITAR
        |--------------------------------------------------------------------------
        */
        case 'guardaryeditar':

            if (
                (int)($_SESSION['settings'] ?? 0)
                !== 1
            ) {
                http_response_code(403);
                echo 'No tiene permiso para modificar la configuración.';
                exit;
            }

            if (
                ($_SERVER['REQUEST_METHOD'] ?? '')
                !== 'POST'
            ) {
                http_response_code(405);
                echo 'La operación requiere una petición POST.';
                exit;
            }

            $id_negocio = (int)(
                $_POST['id_negocio']
                ?? 0
            );

            if ($id_negocio <= 0) {
                $id_negocio =
                    $company->obtenerIdNegocioActivo();
            }

            $logoActual =
                $company->obtenerLogo(
                    $id_negocio
                );

            $eliminarLogo =
                (int)(
                    $_POST['eliminar_logo']
                    ?? 0
                ) === 1;

            $logoSubido = [];
            $logoNuevo = $logoActual;

            $nombre = trim(
                (string)(
                    $_POST['nombre']
                    ?? ''
                )
            );

            $ndocumento = trim(
                (string)(
                    $_POST['ndocumento']
                    ?? 'RUC'
                )
            );

            if ($ndocumento === '') {
                $ndocumento = 'RUC';
            }

            $documento = preg_replace(
                '/\D/',
                '',
                (string)(
                    $_POST['documento']
                    ?? ''
                )
            );

            $direccion = trim(
                (string)(
                    $_POST['direccion']
                    ?? ''
                )
            );

            $telefono = trim(
                (string)(
                    $_POST['telefono']
                    ?? ''
                )
            );

            $email = trim(
                (string)(
                    $_POST['email']
                    ?? ''
                )
            );

            $pais = trim(
                (string)(
                    $_POST['pais']
                    ?? ''
                )
            );

            $ciudad = trim(
                (string)(
                    $_POST['ciudad']
                    ?? ''
                )
            );

            $nombre_impuesto = trim(
                (string)(
                    $_POST['nombre_impuesto']
                    ?? ''
                )
            );

            $monto_impuesto = (float)(
                $_POST['monto_impuesto']
                ?? 0
            );

            $moneda = trim(
                (string)(
                    $_POST['moneda']
                    ?? ''
                )
            );

            $simbolo = trim(
                (string)(
                    $_POST['simbolo']
                    ?? ''
                )
            );

            $token_reniec_sunat = trim(
                (string)(
                    $_POST['tokendniruc']
                    ?? ''
                )
            );

            $apisunat_persona_id = trim(
                (string)(
                    $_POST['apisunat_persona_id']
                    ?? ''
                )
            );

            /*
             * Puede llegar vacío para conservar
             * el token ya registrado.
             */
            $apisunat_persona_token = trim(
                (string)(
                    $_POST['apisunat_persona_token']
                    ?? ''
                )
            );

            $apisunat_production =
                (int)(
                    $_POST['apisunat_production']
                    ?? 1
                ) === 1
                    ? 1
                    : 0;

            $venta_tipo_comprobante_predeterminado = trim(
                (string)(
                    $_POST['venta_tipo_comprobante_predeterminado']
                    ?? ''
                )
            );

            $venta_tipo_pago_predeterminado = trim(
                (string)(
                    $_POST['venta_tipo_pago_predeterminado']
                    ?? ''
                )
            );

            $venta_idforma_pago_predeterminada = (int)(
                $_POST['venta_idforma_pago_predeterminada']
                ?? 0
            );

            $venta_modo_envio_predeterminado = strtolower(
                trim(
                    (string)(
                        $_POST['venta_modo_envio_predeterminado']
                        ?? ''
                    )
                )
            );

            $tipo_operacion_sunat_predeterminado = trim(
                (string)(
                    $_POST['tipo_operacion_sunat_predeterminado']
                    ?? '0101'
                )
            );

            $codigo_afectacion_igv_predeterminado = trim(
                (string)(
                    $_POST['codigo_afectacion_igv_predeterminado']
                    ?? '10'
                )
            );

            $porcentaje_igv_predeterminado = round(
                (float)(
                    $_POST['porcentaje_igv_predeterminado']
                    ?? $monto_impuesto
                ),
                2
            );

            $unidad_medida_sunat_predeterminada = strtoupper(
                trim(
                    (string)(
                        $_POST['unidad_medida_sunat_predeterminada']
                        ?? 'NIU'
                    )
                )
            );

            $permitir_cambio_afectacion_venta = isset(
                $_POST['permitir_cambio_afectacion_venta']
            ) ? 1 : 0;

            $precios_incluyen_impuesto = isset(
                $_POST['precios_incluyen_impuesto']
            ) ? 1 : 0;

            if ($nombre === '') {
                throw new RuntimeException(
                    'Debe ingresar el nombre de la empresa.'
                );
            }

            if (!preg_match(
                '/^\d{11}$/',
                $documento
            )) {
                throw new RuntimeException(
                    'El RUC debe tener exactamente 11 dígitos.'
                );
            }

            if ($direccion === '') {
                throw new RuntimeException(
                    'Debe ingresar la dirección de la empresa.'
                );
            }

            if ($telefono === '') {
                throw new RuntimeException(
                    'Debe ingresar el teléfono de la empresa.'
                );
            }

            if (
                $email !== ''
                && !filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                throw new RuntimeException(
                    'El correo electrónico no es válido.'
                );
            }

            if (
                $monto_impuesto < 0
                || $monto_impuesto > 100
            ) {
                throw new RuntimeException(
                    'El porcentaje del impuesto no es válido.'
                );
            }

            if (
                $apisunat_persona_id !== ''
                && !preg_match(
                    '/^[A-Za-z0-9_-]{10,100}$/',
                    $apisunat_persona_id
                )
            ) {
                throw new RuntimeException(
                    'El Persona ID de APISUNAT no es válido.'
                );
            }

            if (
                $apisunat_persona_token !== ''
                && strlen(
                    $apisunat_persona_token
                ) < 20
            ) {
                throw new RuntimeException(
                    'El Persona Token de APISUNAT parece incompleto.'
                );
            }

            if (
                mb_strlen(
                    $venta_tipo_comprobante_predeterminado,
                    'UTF-8'
                ) > 80
            ) {
                throw new RuntimeException(
                    'El tipo de comprobante predeterminado no es válido.'
                );
            }

            if (
                mb_strlen(
                    $venta_tipo_pago_predeterminado,
                    'UTF-8'
                ) > 50
            ) {
                throw new RuntimeException(
                    'El tipo de pago predeterminado no es válido.'
                );
            }

            if (
                $venta_modo_envio_predeterminado !== ''
                && !in_array(
                    $venta_modo_envio_predeterminado,
                    ['inmediato', 'manual'],
                    true
                )
            ) {
                throw new RuntimeException(
                    'El modo de envío predeterminado no es válido.'
                );
            }

            $tiposOperacionPermitidos = [
                '0101', '0112', '0113',
                '0200', '0201', '0202', '0203', '0204',
                '0205', '0206', '0207', '0208',
                '0301', '0302', '0401',
                '1001', '1002', '1003', '1004',
                '2001'
            ];

            if (!in_array(
                $tipo_operacion_sunat_predeterminado,
                $tiposOperacionPermitidos,
                true
            )) {
                throw new RuntimeException(
                    'El tipo de operación SUNAT no es válido.'
                );
            }

            if (!in_array(
                $codigo_afectacion_igv_predeterminado,
                ['10', '20', '30', '40'],
                true
            )) {
                throw new RuntimeException(
                    'La afectación al IGV predeterminada no es válida.'
                );
            }

            if ($codigo_afectacion_igv_predeterminado === '10') {
                if (
                    $porcentaje_igv_predeterminado <= 0
                    || $porcentaje_igv_predeterminado > 100
                ) {
                    throw new RuntimeException(
                        'La tasa de una operación gravada no es válida.'
                    );
                }
            } else {
                $porcentaje_igv_predeterminado = 0.00;
            }

            if (!preg_match(
                '/^[A-Z0-9]{2,3}$/',
                $unidad_medida_sunat_predeterminada
            )) {
                throw new RuntimeException(
                    'La unidad de medida SUNAT predeterminada no es válida.'
                );
            }

            $tipoPagoNormalizado = mb_strtoupper(
                $venta_tipo_pago_predeterminado,
                'UTF-8'
            );

            $tipoPagoNormalizado = str_replace(
                ['Á', 'É', 'Í', 'Ó', 'Ú'],
                ['A', 'E', 'I', 'O', 'U'],
                $tipoPagoNormalizado
            );

            $comprobanteNormalizado = mb_strtoupper(
                $venta_tipo_comprobante_predeterminado,
                'UTF-8'
            );

            if (
                (
                    $tipoPagoNormalizado === '4'
                    || str_contains(
                        $tipoPagoNormalizado,
                        'CREDITO'
                    )
                )
                && !str_contains(
                    $comprobanteNormalizado,
                    'FACTURA'
                )
            ) {
                throw new RuntimeException(
                    'El pago al crédito solo puede configurarse de forma predeterminada para Factura Electrónica.'
                );
            }


            /*
             * El archivo se procesa recién después de validar
             * todos los campos de configuración.
             */
            if (
                isset($_FILES['logo'])
                && is_array($_FILES['logo'])
                && (int)(
                    $_FILES['logo']['error']
                    ?? UPLOAD_ERR_NO_FILE
                ) !== UPLOAD_ERR_NO_FILE
            ) {
                $logoSubido =
                    guardarLogoEmpresaSubido(
                        $_FILES['logo'],
                        $id_negocio
                    );

                $logoNuevo = (string)(
                    $logoSubido['nombre']
                    ?? $logoActual
                );

                $eliminarLogo = false;
            } elseif ($eliminarLogo) {
                $logoNuevo = '';
            }

            $resultado = $company->editar(
                $id_negocio,
                $nombre,
                $ndocumento,
                $documento,
                $direccion,
                $telefono,
                $email,
                $pais,
                $ciudad,
                $nombre_impuesto,
                $monto_impuesto,
                $moneda,
                $simbolo,
                $token_reniec_sunat,
                $apisunat_persona_id,
                $apisunat_persona_token,
                $apisunat_production,
                $venta_tipo_comprobante_predeterminado,
                $venta_tipo_pago_predeterminado,
                $venta_idforma_pago_predeterminada,
                $venta_modo_envio_predeterminado,
                $tipo_operacion_sunat_predeterminado,
                $codigo_afectacion_igv_predeterminado,
                $porcentaje_igv_predeterminado,
                $unidad_medida_sunat_predeterminada,
                $permitir_cambio_afectacion_venta,
                $precios_incluyen_impuesto
            );

            if (!$resultado) {
                if (
                    isset($logoSubido['ruta'])
                    && is_file(
                        (string)$logoSubido['ruta']
                    )
                ) {
                    @unlink(
                        (string)$logoSubido['ruta']
                    );
                }

                echo 'No se pudo actualizar la configuración';
                exit;
            }

            if ($logoNuevo !== $logoActual) {
                $logoActualizado =
                    $company->actualizarLogo(
                        $id_negocio,
                        $logoNuevo
                    );

                if (!$logoActualizado) {
                    if (
                        isset($logoSubido['ruta'])
                        && is_file(
                            (string)$logoSubido['ruta']
                        )
                    ) {
                        @unlink(
                            (string)$logoSubido['ruta']
                        );
                    }

                    throw new RuntimeException(
                        'Los datos se guardaron, pero no se pudo actualizar el logo.'
                    );
                }

                if (
                    $logoActual !== ''
                    && $logoActual !== $logoNuevo
                ) {
                    eliminarLogoEmpresaFisico(
                        $logoActual
                    );
                }
            }

            echo 'Datos actualizados correctamente';
            exit;

        /*
        |--------------------------------------------------------------------------
        | MOSTRAR
        |--------------------------------------------------------------------------
        */
        case 'mostrar':

            $idNegocio = (int)(
                $_GET['id_negocio']
                ?? $_POST['id_negocio']
                ?? 0
            );

            if ($idNegocio <= 0) {
                $idNegocio =
                    $company->obtenerIdNegocioActivo();
            }

            responderCompanyJson(
                $company->mostrarSeguro(
                    $idNegocio
                )
            );

            break;

        /*
        |--------------------------------------------------------------------------
        | MOSTRAR DATOS DE EMPRESA ACTIVA
        |--------------------------------------------------------------------------
        */
        case 'mostrar_datos':

            responderCompanyJson(
                $company->mostrarActivoSeguro()
            );

            break;

        /*
        |--------------------------------------------------------------------------
        | MOSTRAR IMPUESTO
        |--------------------------------------------------------------------------
        */
        case 'mostrar_impuesto':

            $resultado =
                $company->mostrar_impuesto();

            $numeroImpuesto = 0;

            foreach ($resultado as $registro) {
                $numeroImpuesto = (float)(
                    $registro['monto_impuesto']
                    ?? 0
                );
            }

            responderCompanyJson(
                $numeroImpuesto
            );

            break;

        /*
        |--------------------------------------------------------------------------
        | NOMBRE DEL IMPUESTO
        |--------------------------------------------------------------------------
        */
        case 'nombre_impuesto':

            $resultado =
                $company->nombre_impuesto();

            $nombreImpuesto = '';

            foreach ($resultado as $registro) {
                $nombreImpuesto = (string)(
                    $registro['nombre_impuesto']
                    ?? ''
                );
            }

            responderCompanyJson(
                $nombreImpuesto
            );

            break;

        /*
        |--------------------------------------------------------------------------
        | SÍMBOLO
        |--------------------------------------------------------------------------
        */
        case 'mostrar_simbolo':

            $resultado =
                $company->mostrar_simbolo();

            $simbolo = '';

            foreach ($resultado as $registro) {
                $simbolo = (string)(
                    $registro['simbolo']
                    ?? ''
                );
            }

            responderCompanyJson(
                $simbolo
            );

            break;

        /*
        |--------------------------------------------------------------------------
        | LISTAR EMPRESAS
        |--------------------------------------------------------------------------
        */
        case 'listar':

            $resultado = $company->listar();
            $data = [];

            foreach ($resultado as $registro) {
                $idNegocio = (int)(
                    $registro['id_negocio']
                    ?? 0
                );

                $data[] = [
                    '0' =>
                        '<button
                            class="btn btn-warning btn-xs"
                            onclick="mostrar('
                        . $idNegocio
                        . ')">
                            <i class="fas fa-edit"></i>
                         </button>',

                    '1' => htmlspecialchars(
                        (string)(
                            $registro['nombre']
                            ?? ''
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ),

                    '2' => htmlspecialchars(
                        (string)(
                            ($registro['ndocumento'] ?? '')
                            . ' '
                            . ($registro['documento'] ?? '')
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ),

                    '3' => htmlspecialchars(
                        (string)(
                            $registro['direccion']
                            ?? ''
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ),

                    '4' => htmlspecialchars(
                        (string)(
                            $registro['telefono']
                            ?? ''
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ),

                    '5' => htmlspecialchars(
                        (string)(
                            $registro['email']
                            ?? ''
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ),

                    '6' => htmlspecialchars(
                        trim(
                            (string)(
                                ($registro['ciudad'] ?? '')
                                . ' - '
                                . ($registro['pais'] ?? '')
                            )
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ),

                    '7' => htmlspecialchars(
                        (string)(
                            ($registro['nombre_impuesto'] ?? '')
                            . ' '
                            . ($registro['monto_impuesto'] ?? 0)
                            . ' %'
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ),

                    '8' => htmlspecialchars(
                        (string)(
                            ($registro['simbolo'] ?? '')
                            . ' '
                            . ($registro['moneda'] ?? '')
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ),

                    '9' =>
                        (int)(
                            $registro['condicion']
                            ?? 0
                        ) === 1
                            ? '<span class="badge badge-success">Activo</span>'
                            : '<span class="badge badge-danger">Inactivo</span>'
                ];
            }

            responderCompanyJson([
                'sEcho' => 1,
                'iTotalRecords' =>
                    count($data),
                'iTotalDisplayRecords' =>
                    count($data),
                'aaData' => $data
            ]);

            break;

        /*
        |--------------------------------------------------------------------------
        | OPERACIÓN INVÁLIDA
        |--------------------------------------------------------------------------
        */
        default:

            responderCompanyJson([
                'success' => false,
                'mensaje' =>
                    'Operación no válida.'
            ], 404);
    }
} catch (Throwable $e) {
    error_log(
        '[COMPANY CONTROLLER] '
        . $e->getMessage()
        . ' | Archivo: '
        . $e->getFile()
        . ' | Línea: '
        . $e->getLine()
    );

    if ($op === 'guardaryeditar') {
        if (
            isset($logoSubido['ruta'])
            && is_file(
                (string)$logoSubido['ruta']
            )
        ) {
            @unlink(
                (string)$logoSubido['ruta']
            );
        }

        http_response_code(500);
        echo $e->getMessage();
        exit;
    }

    responderCompanyJson([
        'success' => false,
        'mensaje' => $e->getMessage()
    ], 500);
}