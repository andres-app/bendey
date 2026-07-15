<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../Models/User.php';

$user = new User();

$idusuarioc = isset($_POST['idusuarioc'])
    ? $_POST['idusuarioc']
    : '';

$clavec = isset($_POST['clavec'])
    ? $_POST['clavec']
    : '';

$idusuario = isset($_POST['idusuario'])
    ? $_POST['idusuario']
    : '';

$nombre = isset($_POST['nombre'])
    ? $_POST['nombre']
    : '';

$tipo_documento = isset($_POST['tipo_documento'])
    ? $_POST['tipo_documento']
    : '';

$num_documento = isset($_POST['num_documento'])
    ? $_POST['num_documento']
    : '';

$direccion = isset($_POST['direccion'])
    ? $_POST['direccion']
    : '';

$telefono = isset($_POST['telefono'])
    ? $_POST['telefono']
    : '';

$email = isset($_POST['email'])
    ? $_POST['email']
    : '';

$cargo = isset($_POST['cargo'])
    ? $_POST['cargo']
    : '';

$login = isset($_POST['login'])
    ? $_POST['login']
    : '';

$clave = isset($_POST['clave'])
    ? $_POST['clave']
    : '';

$imagen = isset($_POST['imagen'])
    ? $_POST['imagen']
    : '';

$op = trim(
    (string)($_GET['op'] ?? '')
);

switch ($op) {

    case 'guardaryeditar':

        if (
            !isset($_FILES['imagen'])
            || !file_exists($_FILES['imagen']['tmp_name'])
            || !is_uploaded_file($_FILES['imagen']['tmp_name'])
        ) {
            $imagen = $_POST['imagenactual'] ?? '';
        } else {
            $ext = explode(
                '.',
                $_FILES['imagen']['name']
            );

            $tipoImagen =
                $_FILES['imagen']['type']
                ?? '';

            if (
                $tipoImagen === 'image/jpg'
                || $tipoImagen === 'image/jpeg'
                || $tipoImagen === 'image/png'
            ) {
                $imagen =
                    round(microtime(true))
                    . '.'
                    . end($ext);

                move_uploaded_file(
                    $_FILES['imagen']['tmp_name'],
                    __DIR__
                        . '/../Assets/img/users/'
                        . $imagen
                );
            }
        }

        $clavehash = hash(
            'SHA256',
            $clave
        );

        $permisos = isset($_POST['permiso'])
            && is_array($_POST['permiso'])
            ? $_POST['permiso']
            : [];

        if (empty($idusuario)) {
            $rspta = $user->insertar(
                $nombre,
                $tipo_documento,
                $num_documento,
                $direccion,
                $telefono,
                $email,
                $cargo,
                $login,
                $clavehash,
                $imagen,
                $permisos
            );

            echo $rspta
                ? 'Datos registrados correctamente'
                : 'No se pudo registrar todos los datos del user';
        } else {
            $rspta = $user->editar(
                $idusuario,
                $nombre,
                $tipo_documento,
                $num_documento,
                $direccion,
                $telefono,
                $email,
                $cargo,
                $login,
                $imagen,
                $permisos
            );

            echo $rspta
                ? 'Datos actualizados correctamente'
                : 'No se pudo actualizar los datos';
        }

        break;

    case 'desactivar':

        $rspta = $user->desactivar(
            $idusuario
        );

        echo $rspta
            ? 'Datos desactivados correctamente'
            : 'No se pudo desactivar los datos';

        break;

    case 'activar':

        $rspta = $user->activar(
            $idusuario
        );

        echo $rspta
            ? 'Datos activados correctamente'
            : 'No se pudo activar los datos';

        break;

    case 'mostrar':

        $rspta = $user->mostrar(
            $idusuario
        );

        echo json_encode(
            $rspta,
            JSON_UNESCAPED_UNICODE
        );

        break;

    case 'editar_clave':

        $clavehash = hash(
            'SHA256',
            $clavec
        );

        $rspta = $user->editar_clave(
            $idusuarioc,
            $clavehash
        );

        echo $rspta
            ? 'Password actualizado correctamente'
            : 'No se pudo actualizar el password';

        break;

    case 'mostrar_clave':

        $rspta = $user->mostrar_clave(
            $idusuario
        );

        echo json_encode(
            $rspta,
            JSON_UNESCAPED_UNICODE
        );

        break;

    case 'listar':

        $rspta = $user->listar();

        $data = [];

        foreach ($rspta as $reg) {
            $acciones = '';

            if ((int)$reg['condicion'] === 1) {
                $acciones =
                    '<button class="btn btn-warning btn-sm" '
                    . 'onclick="mostrar('
                    . (int)$reg['idusuario']
                    . ')">'
                    . '<i class="fas fa-user-edit"></i>'
                    . '</button> '
                    . '<button class="btn btn-info btn-sm" '
                    . 'onclick="mostrar_clave('
                    . (int)$reg['idusuario']
                    . ')">'
                    . '<i class="fas fa-key"></i>'
                    . '</button> '
                    . '<button class="btn btn-danger btn-sm" '
                    . 'onclick="desactivar('
                    . (int)$reg['idusuario']
                    . ')">'
                    . '<i class="fas fa-user-times"></i>'
                    . '</button>';
            } else {
                $acciones =
                    '<button class="btn btn-warning btn-sm" '
                    . 'onclick="mostrar('
                    . (int)$reg['idusuario']
                    . ')">'
                    . '<i class="fas fa-user-edit"></i>'
                    . '</button> '
                    . '<button class="btn btn-info btn-sm" '
                    . 'onclick="mostrar_clave('
                    . (int)$reg['idusuario']
                    . ')">'
                    . '<i class="fas fa-key"></i>'
                    . '</button> '
                    . '<button class="btn btn-success btn-sm" '
                    . 'onclick="activar('
                    . (int)$reg['idusuario']
                    . ')">'
                    . '<i class="fas fa-user-check"></i>'
                    . '</button>';
            }

            $data[] = [
                '0' => $acciones,
                '1' => $reg['nombre'],
                '2' => $reg['tipo_documento'],
                '3' => $reg['num_documento'],
                '4' => $reg['telefono'],
                '5' => $reg['email'],
                '6' => $reg['login'],

                '7' =>
                "<img alt='image' src='Assets/img/users/"
                    . $reg['imagen']
                    . "' height='50px' width='50px'>",

                '8' =>
                (int)$reg['condicion'] === 1
                    ? '<div class="badge badge-success">Activo</div>'
                    : '<div class="badge badge-danger">Inactivo</div>'
            ];
        }

        echo json_encode(
            [
                'sEcho' => 1,
                'iTotalRecords' => count($data),
                'iTotalDisplayRecords' => count($data),
                'aaData' => $data
            ],
            JSON_UNESCAPED_UNICODE
        );

        break;

    case 'permisos':

        require_once __DIR__ . '/../Models/Permiso.php';

        $permiso = new Permiso();

        $rspta = $permiso->listar();

        $id = (int)(
            $_GET['id']
            ?? 0
        );

        $marcados = $user->listarmarcados(
            (string)$id
        );

        $valores = [];

        foreach ($marcados as $per) {
            $valores[] =
                (int)$per['idpermiso'];
        }

        foreach ($rspta as $reg) {
            $idPermiso =
                (int)$reg['idpermiso'];

            $checked = in_array(
                $idPermiso,
                $valores,
                true
            )
                ? 'checked'
                : '';

            echo
            '<li>'
                . '<input type="checkbox" '
                . $checked
                . ' name="permiso[]" value="'
                . $idPermiso
                . '">'
                . htmlspecialchars(
                    (string)$reg['nombre'],
                    ENT_QUOTES,
                    'UTF-8'
                )
                . '</li>';
        }

        break;

    case 'verificar':

        $logina = trim(
            (string)(
                $_POST['nombre']
                ?? ''
            )
        );

        $clavea = (string)(
            $_POST['clave']
            ?? ''
        );

        if (
            $logina === ''
            || $clavea === ''
        ) {
            echo '0';
            break;
        }

        $clavehash = hash(
            'SHA256',
            $clavea
        );

        $rspta = $user->verificar(
            $logina,
            $clavehash
        );

        if (!$rspta) {
            echo '0';
            break;
        }

        /*
        |--------------------------------------------------------------------------
        | REGENERAR SESIÓN
        |--------------------------------------------------------------------------
        */
        session_regenerate_id(true);

        /*
        |--------------------------------------------------------------------------
        | DATOS DEL USUARIO
        |--------------------------------------------------------------------------
        */
        $_SESSION['idusuario'] =
            (int)$rspta['idusuario'];

        $_SESSION['nombre'] =
            (string)$rspta['nombre'];

        $_SESSION['imagen'] =
            (string)$rspta['imagen'];

        $_SESSION['login'] =
            (string)$rspta['login'];

        $_SESSION['cargo'] =
            (string)$rspta['cargo'];

        /*
        |--------------------------------------------------------------------------
        | PERMISOS DEL SISTEMA
        |--------------------------------------------------------------------------
        */
        $marcados = $user->listarmarcados(
            (string)$rspta['idusuario']
        );

        $valores = [];

        foreach ($marcados as $per) {
            $valores[] =
                (int)$per['idpermiso'];
        }

        $_SESSION['dashboard'] =
            in_array(1, $valores, true)
            ? 1
            : 0;

        $_SESSION['almacen'] =
            in_array(2, $valores, true)
            ? 1
            : 0;

        $_SESSION['compras'] =
            in_array(3, $valores, true)
            ? 1
            : 0;

        $_SESSION['ventas'] =
            in_array(4, $valores, true)
            ? 1
            : 0;

        $_SESSION['users'] =
            in_array(5, $valores, true)
            ? 1
            : 0;

        $_SESSION['datebuy'] =
            in_array(6, $valores, true)
            ? 1
            : 0;

        $_SESSION['clientdatesales'] =
            in_array(7, $valores, true)
            ? 1
            : 0;

        $_SESSION['settings'] =
            in_array(8, $valores, true)
            ? 1
            : 0;

        /*
        |--------------------------------------------------------------------------
        | CONTEXTO INICIAL DE CAJA
        |--------------------------------------------------------------------------
        | En esta fase el sistema sigue funcionando en LEGACY.
        |--------------------------------------------------------------------------
        */
        $_SESSION['idsucursal_activa'] = 0;
        $_SESSION['modo_caja'] = 'LEGACY';
        $_SESSION['modo_caja_objetivo'] = '';
        $_SESSION['idcaja_activa'] = 0;
        $_SESSION['idcaja_preparada'] = 0;
        $_SESSION['idapertura_activa'] = 0;

        /*
        |--------------------------------------------------------------------------
        | CARGAR EMPRESA
        |--------------------------------------------------------------------------
        */
        try {
            require_once __DIR__
                . '/../Models/Company.php';

            $company = new Company();

            $empresas = $company->listar();

            if (
                is_array($empresas)
                && isset($empresas[0])
            ) {
                $nombreEmpresa =
                    (string)(
                        $empresas[0]['nombre']
                        ?? ''
                    );

                /*
                 * Se conserva la clave antigua para no romper vistas.
                 */
                $_SESSION['nombreEmrpesa'] =
                    $nombreEmpresa;

                /*
                 * Nombre corregido para nuevos módulos.
                 */
                $_SESSION['nombreEmpresa'] =
                    $nombreEmpresa;
            }
        } catch (Throwable $e) {
            error_log(
                '[LOGIN EMPRESA] '
                    . $e->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | CARGAR SUCURSAL Y CONFIGURACIÓN DE CAJA
        |--------------------------------------------------------------------------
        */
        try {
            require_once __DIR__
                . '/../Models/ConfiguracionCaja.php';

            $configuracionCaja =
                new ConfiguracionCaja();

            $configuracion =
                $configuracionCaja
                ->obtenerSucursalPrincipal();

            if (is_array($configuracion)) {
                $idsucursalActiva =
                    (int)(
                        $configuracion['idsucursal']
                        ?? 0
                    );

                $modoCaja = strtoupper(
                    trim(
                        (string)(
                            $configuracion['modo']
                            ?? 'LEGACY'
                        )
                    )
                );

                $modoObjetivo = strtoupper(
                    trim(
                        (string)(
                            $configuracion['modo_objetivo']
                            ?? ''
                        )
                    )
                );

                if (
                    !in_array(
                        $modoCaja,
                        [
                            'LEGACY',
                            'CAJA_UNICA',
                            'MULTICAJA'
                        ],
                        true
                    )
                ) {
                    $modoCaja = 'LEGACY';
                }

                $_SESSION['idsucursal_activa'] =
                    $idsucursalActiva;

                $_SESSION['modo_caja'] =
                    $modoCaja;

                $_SESSION['modo_caja_objetivo'] =
                    $modoObjetivo;

                /*
                 * Solo se selecciona automáticamente la caja
                 * cuando el modo real sea CAJA_UNICA.
                 */
                if ($modoCaja === 'CAJA_UNICA') {
                    $_SESSION['idcaja_activa'] =
                        (int)(
                            $configuracion['idcaja_unica']
                            ?? 0
                        );
                }
            }
        } catch (Throwable $e) {
            /*
             * No bloqueamos el acceso.
             * El sistema continúa en LEGACY.
             */
            error_log(
                '[LOGIN CONTEXTO CAJA] '
                    . $e->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | RESPUESTA EXACTA PARA login.js
        |--------------------------------------------------------------------------
        */
        echo '1';

        break;

    case 'salir':

        session_unset();
        session_destroy();

        header(
            'Location: ../index.php'
        );

        exit;

    default:

        http_response_code(404);
        echo 'Operación no válida';

        break;
}
