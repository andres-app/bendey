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
                $apisunat_production
            );

            echo $resultado
                ? 'Datos actualizados correctamente'
                : 'No se pudo actualizar la configuración';

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
        http_response_code(500);
        echo $e->getMessage();
        exit;
    }

    responderCompanyJson([
        'success' => false,
        'mensaje' => $e->getMessage()
    ], 500);
}