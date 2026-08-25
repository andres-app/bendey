<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Libraries/MediaStorage.php';
require_once __DIR__ . '/../Libraries/PasswordResetMailer.php';

$user = new User();

function postTexto($clave, $predeterminado = '')
{
    return trim((string)($_POST[$clave] ?? $predeterminado));
}

function postEntero($clave, $predeterminado = 0)
{
    return (int)($_POST[$clave] ?? $predeterminado);
}

function postListaEnteros($clave)
{
    $valores = $_POST[$clave] ?? array();
    $valores = is_array($valores) ? $valores : array($valores);
    $valores = array_values(array_unique(array_map('intval', $valores)));

    return array_values(
        array_filter(
            $valores,
            function ($valor) {
                return $valor > 0;
            }
        )
    );
}

function postBandera($clave)
{
    return isset($_POST[$clave]) && (int)$_POST[$clave] === 1 ? 1 : 0;
}

function responderJson($ok, $mensaje, $datos = array())
{
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        array_merge(
            array(
                'ok' => (bool)$ok,
                'mensaje' => (string)$mensaje
            ),
            $datos
        ),
        JSON_UNESCAPED_UNICODE
    );
}

function escapar($valor)
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}


function obtenerConfiguracionRecuperacion()
{
    $ruta = __DIR__ . '/../Config/password_reset.php';

    if (!is_file($ruta)) {
        return array();
    }

    $config = require $ruta;
    return is_array($config) ? $config : array();
}

function tokenRecuperacionValido($token)
{
    return is_string($token)
        && strlen($token) === 64
        && ctype_xdigit($token);
}

/**
 * Valida un token de Cloudflare Turnstile contra Siteverify.
 * La clave secreta nunca se envía al navegador.
 */
function validarTurnstile($token)
{
    $token = trim((string)$token);

    if ($token === '' || strlen($token) > 2048) {
        return array(
            'ok' => false,
            'mensaje' => 'Completa nuevamente la verificación de seguridad.'
        );
    }

    $rutaConfiguracion = __DIR__ . '/../Config/turnstile.php';

    if (!is_file($rutaConfiguracion)) {
        error_log('[TURNSTILE] No existe Config/turnstile.php');

        return array(
            'ok' => false,
            'mensaje' => 'La verificación de seguridad no está configurada.'
        );
    }

    $configuracion = require $rutaConfiguracion;

    if (!is_array($configuracion)) {
        error_log('[TURNSTILE] Configuración inválida.');

        return array(
            'ok' => false,
            'mensaje' => 'La verificación de seguridad no está configurada.'
        );
    }

    $claveSecreta = trim((string)($configuracion['secret_key'] ?? ''));
    $accionEsperada = trim((string)($configuracion['expected_action'] ?? 'login'));
    $hostsPermitidos = $configuracion['allowed_hostnames'] ?? array();
    $hostsPermitidos = is_array($hostsPermitidos) ? $hostsPermitidos : array();
    $hostsPermitidos = array_values(
        array_filter(
            array_map(
                static function ($host) {
                    return strtolower(trim((string)$host));
                },
                $hostsPermitidos
            )
        )
    );

    if (empty($hostsPermitidos)) {
        error_log('[TURNSTILE] No hay hostnames permitidos configurados.');

        return array(
            'ok' => false,
            'mensaje' => 'La verificación de seguridad no está configurada para este dominio.'
        );
    }

    if (
        $claveSecreta === ''
        || strpos($claveSecreta, 'REEMPLAZA_') === 0
    ) {
        error_log('[TURNSTILE] Falta configurar secret_key.');

        return array(
            'ok' => false,
            'mensaje' => 'La verificación de seguridad no está configurada.'
        );
    }

    $datos = array(
        'secret' => $claveSecreta,
        'response' => $token
    );

    $ipRemota = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));

    if ($ipRemota !== '') {
        $datos['remoteip'] = $ipRemota;
    }

    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $respuestaHttp = false;

    if (function_exists('curl_init')) {
        $curl = curl_init($url);

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($datos),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/x-www-form-urlencoded'
                ),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            )
        );

        $respuestaHttp = curl_exec($curl);
        $errorCurl = curl_error($curl);
        $codigoHttp = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($respuestaHttp === false || $codigoHttp < 200 || $codigoHttp >= 300) {
            error_log(
                '[TURNSTILE] Error HTTP/cURL. Código: '
                    . $codigoHttp
                    . '. Detalle: '
                    . $errorCurl
            );

            return array(
                'ok' => false,
                'mensaje' => 'No se pudo validar la seguridad. Intenta nuevamente.'
            );
        }
    } elseif ((bool)ini_get('allow_url_fopen')) {
        $contexto = stream_context_create(
            array(
                'http' => array(
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => http_build_query($datos),
                    'timeout' => 10,
                    'ignore_errors' => true
                )
            )
        );

        $respuestaHttp = @file_get_contents($url, false, $contexto);

        if ($respuestaHttp === false) {
            error_log('[TURNSTILE] No se pudo conectar mediante file_get_contents.');

            return array(
                'ok' => false,
                'mensaje' => 'No se pudo validar la seguridad. Intenta nuevamente.'
            );
        }
    } else {
        error_log('[TURNSTILE] El servidor no tiene cURL ni allow_url_fopen disponible.');

        return array(
            'ok' => false,
            'mensaje' => 'El servidor no puede validar la seguridad en este momento.'
        );
    }

    $resultado = json_decode((string)$respuestaHttp, true);

    if (!is_array($resultado)) {
        error_log('[TURNSTILE] La respuesta de Siteverify no es JSON válido.');

        return array(
            'ok' => false,
            'mensaje' => 'No se pudo validar la seguridad. Intenta nuevamente.'
        );
    }

    if (empty($resultado['success'])) {
        $codigos = $resultado['error-codes'] ?? array('desconocido');
        $codigos = is_array($codigos) ? implode(', ', $codigos) : (string)$codigos;

        error_log('[TURNSTILE] Token rechazado: ' . $codigos);

        return array(
            'ok' => false,
            'mensaje' => 'La verificación de seguridad venció o no es válida. Intenta nuevamente.'
        );
    }

    if (
        $accionEsperada !== ''
        && (string)($resultado['action'] ?? '') !== $accionEsperada
    ) {
        error_log('[TURNSTILE] La acción recibida no coincide con la esperada.');

        return array(
            'ok' => false,
            'mensaje' => 'La verificación de seguridad no corresponde a este formulario.'
        );
    }

    $hostname = strtolower(trim((string)($resultado['hostname'] ?? '')));

    if ($hostname === '' || !in_array($hostname, $hostsPermitidos, true)) {
        error_log('[TURNSTILE] Hostname no permitido: ' . $hostname);

        return array(
            'ok' => false,
            'mensaje' => 'La verificación de seguridad no corresponde a este sitio.'
        );
    }

    return array(
        'ok' => true,
        'mensaje' => 'Verificación correcta.'
    );
}


function avatarUsuarioPredeterminado()
{
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="180" viewBox="0 0 180 180">'
        . '<rect width="180" height="180" rx="28" fill="#f1f3f8"/>'
        . '<circle cx="90" cy="67" r="31" fill="#aeb7c8"/>'
        . '<path d="M35 157c5-34 27-52 55-52s50 18 55 52" fill="#aeb7c8"/>'
        . '</svg>';

    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function guardarImagenUsuario($imagenActual)
{
    if (
        !isset($_FILES['imagen'])
        || !isset($_FILES['imagen']['tmp_name'])
        || !is_uploaded_file($_FILES['imagen']['tmp_name'])
    ) {
        return basename((string)$imagenActual);
    }

    $archivo = $_FILES['imagen'];

    if ((int)($archivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo cargar la imagen seleccionada.');
    }

    if ((int)($archivo['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('La imagen no puede superar los 5 MB.');
    }

    $mime = '';

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo) {
            $mime = (string)finfo_file($finfo, $archivo['tmp_name']);
            finfo_close($finfo);
        }
    }

    if ($mime === '') {
        $mime = (string)($archivo['type'] ?? '');
    }

    $extensiones = array(
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    );

    if (!isset($extensiones[$mime])) {
        throw new RuntimeException('Solo se permiten imágenes JPG, PNG o WEBP.');
    }

    $directorio = tiquepos_media_dir('users') . DIRECTORY_SEPARATOR;

    if (!is_dir($directorio) || !is_writable($directorio)) {
        throw new RuntimeException('No se pudo preparar la carpeta de imágenes de usuarios.');
    }

    $nombre = 'user_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4))
        . '.' . $extensiones[$mime];

    if (!move_uploaded_file($archivo['tmp_name'], $directorio . $nombre)) {
        throw new RuntimeException('No se pudo guardar la imagen del usuario.');
    }

    return $nombre;
}

$op = trim((string)($_GET['op'] ?? ''));

switch ($op) {
    case 'guardaryeditar':
        try {
            $idusuario = postEntero('idusuario');
            $nombre = postTexto('nombre');
            $tipoDocumento = postTexto('tipo_documento');
            $numDocumento = postTexto('num_documento');
            $direccion = postTexto('direccion');
            $telefono = postTexto('telefono');
            $email = postTexto('email');
            $cargo = postTexto('cargo');
            $login = postTexto('login');
            $clave = (string)($_POST['clave'] ?? '');
            $imagenActual = postTexto('imagenactual');
            $permisos = isset($_POST['permiso']) && is_array($_POST['permiso'])
                ? $_POST['permiso']
                : array();

            $idsucursal = postEntero('idsucursal');
            $idcajas = postListaEnteros('idcaja');
            $idalmacen = postEntero('idalmacen');
            $rol = strtoupper(postTexto('rol', 'VENDEDOR'));

            $puedeVender = postBandera('puede_vender');
            $puedeCobrar = postBandera('puede_cobrar');
            $puedeAbrirCajaSucursal = postBandera('puede_abrir_caja_sucursal');
            $puedeCerrarCajaSucursal = postBandera('puede_cerrar_caja_sucursal');

            $puedeAbrirCaja = postBandera('puede_abrir');
            $puedeCerrarCaja = postBandera('puede_cerrar');
            $puedeOperarCaja = postBandera('puede_operar');

            if ($nombre === '' || $login === '' || $tipoDocumento === '') {
                responderJson(false, 'Completa el nombre, tipo de documento y usuario de acceso.');
                break;
            }

            if ($idsucursal <= 0) {
                responderJson(false, 'Selecciona la sucursal del usuario.');
                break;
            }

            if (empty($idcajas)) {
                responderJson(false, 'Selecciona al menos una caja para conservar el rol operativo.');
                break;
            }

            if (!$user->validarCajasActivasSucursal($idcajas, $idsucursal)) {
                responderJson(
                    false,
                    'Una de las cajas seleccionadas no pertenece a la sucursal o se encuentra inactiva.'
                );
                break;
            }

            if (!in_array($rol, array('ADMINISTRADOR', 'CAJERO', 'VENDEDOR'), true)) {
                responderJson(false, 'El rol seleccionado no es válido.');
                break;
            }

            if ($idusuario <= 0 && $clave === '') {
                responderJson(false, 'La contraseña es obligatoria para un usuario nuevo.');
                break;
            }

            if ($user->existeLogin($login, $idusuario)) {
                responderJson(false, 'El nombre de usuario ya está siendo utilizado.');
                break;
            }

            $imagen = guardarImagenUsuario($imagenActual);

            if ($idusuario <= 0) {
                $claveHash = hash('SHA256', $clave);

                $respuesta = $user->insertar(
                    $nombre,
                    $tipoDocumento,
                    $numDocumento,
                    $direccion,
                    $telefono,
                    $email,
                    $cargo,
                    $login,
                    $claveHash,
                    $imagen,
                    $permisos,
                    $idsucursal,
                    $idcajas,
                    $idalmacen,
                    $rol,
                    $puedeVender,
                    $puedeCobrar,
                    $puedeAbrirCajaSucursal,
                    $puedeCerrarCajaSucursal,
                    $puedeAbrirCaja,
                    $puedeCerrarCaja,
                    $puedeOperarCaja
                );

                responderJson(
                    $respuesta,
                    $respuesta
                        ? 'Usuario registrado y asignado correctamente.'
                        : 'El usuario se creó, pero alguna asignación no pudo guardarse.'
                );
            } else {
                $respuesta = $user->editar(
                    $idusuario,
                    $nombre,
                    $tipoDocumento,
                    $numDocumento,
                    $direccion,
                    $telefono,
                    $email,
                    $cargo,
                    $login,
                    $imagen,
                    $permisos,
                    $idsucursal,
                    $idcajas,
                    $idalmacen,
                    $rol,
                    $puedeVender,
                    $puedeCobrar,
                    $puedeAbrirCajaSucursal,
                    $puedeCerrarCajaSucursal,
                    $puedeAbrirCaja,
                    $puedeCerrarCaja,
                    $puedeOperarCaja
                );

                if ($respuesta && (int)($_SESSION['idusuario'] ?? 0) === $idusuario) {
                    $_SESSION['nombre'] = $nombre;
                    $_SESSION['imagen'] = tiquepos_user_image_filename(
                        $idusuario,
                        $imagen
                    );
                    $_SESSION['login'] = $login;
                    $_SESSION['cargo'] = $cargo;
                }

                responderJson(
                    $respuesta,
                    $respuesta
                        ? 'Usuario y asignaciones actualizados correctamente.'
                        : 'No se pudo completar la actualización.'
                );
            }
        } catch (Throwable $e) {
            error_log('[USUARIOS GUARDAR] ' . $e->getMessage());
            responderJson(false, $e->getMessage());
        }
        break;

    case 'desactivar':
        $respuesta = $user->desactivar(postEntero('idusuario'));
        responderJson(
            $respuesta,
            $respuesta
                ? 'Usuario desactivado correctamente.'
                : 'No se pudo desactivar el usuario.'
        );
        break;

    case 'activar':
        $respuesta = $user->activar(postEntero('idusuario'));
        responderJson(
            $respuesta,
            $respuesta
                ? 'Usuario activado correctamente.'
                : 'No se pudo activar el usuario.'
        );
        break;

    case 'mostrar':
        header('Content-Type: application/json; charset=utf-8');

        $idusuarioMostrar = postEntero('idusuario');
        $datosUsuario = $user->mostrar($idusuarioMostrar);

        if (is_array($datosUsuario) && !empty($datosUsuario)) {
            $datosUsuario['imagen'] = tiquepos_user_image_filename(
                $idusuarioMostrar,
                (string)($datosUsuario['imagen'] ?? '')
            );
        }

        echo json_encode(
            $datosUsuario,
            JSON_UNESCAPED_UNICODE
        );
        break;

    case 'editar_clave':
        $idusuario = postEntero('idusuarioc');
        $clave = (string)($_POST['clavec'] ?? '');

        if ($idusuario <= 0 || $clave === '') {
            responderJson(false, 'Ingresa una contraseña válida.');
            break;
        }

        $respuesta = $user->editar_clave(
            $idusuario,
            hash('SHA256', $clave)
        );

        responderJson(
            $respuesta,
            $respuesta
                ? 'Contraseña actualizada correctamente.'
                : 'No se pudo actualizar la contraseña.'
        );
        break;

    case 'mostrar_clave':
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            $user->mostrar_clave(postEntero('idusuario')),
            JSON_UNESCAPED_UNICODE
        );
        break;

    case 'catalogos':
        responderJson(
            true,
            'Catálogos cargados.',
            array(
                'sucursales' => $user->listarSucursales(),
                'cajas' => $user->listarCajas(),
                'almacenes' => $user->listarAlmacenes()
            )
        );
        break;

    case 'listar':
        $registros = $user->listar();
        $sucursales = $user->listarSucursales();
        $cajas = $user->listarCajas();
        $almacenes = $user->listarAlmacenes();

        $mapaSucursales = array();
        foreach ($sucursales as $sucursal) {
            $mapaSucursales[(int)$sucursal['idsucursal']] = (string)$sucursal['nombre'];
        }

        $mapaCajas = array();
        foreach ($cajas as $caja) {
            $mapaCajas[(int)$caja['idcaja']] = (string)$caja['etiqueta'];
        }

        $mapaAlmacenes = array();
        foreach ($almacenes as $almacen) {
            $mapaAlmacenes[(int)$almacen['idalmacen']] = (string)$almacen['etiqueta'];
        }

        $data = array();

        foreach ($registros as $reg) {
            $idusuario = (int)$reg['idusuario'];
            $activo = (int)$reg['condicion'] === 1;

            $acciones =
                '<div class="btn-group btn-group-sm" role="group">'
                . '<button type="button" class="btn btn-light text-primary" '
                . 'title="Editar" onclick="mostrar(' . $idusuario . ')">'
                . '<i class="fas fa-user-edit"></i></button>'
                . '<button type="button" class="btn btn-light text-info" '
                . 'title="Cambiar contraseña" onclick="mostrar_clave(' . $idusuario . ')">'
                . '<i class="fas fa-key"></i></button>';

            if ($activo) {
                $acciones .=
                    '<button type="button" class="btn btn-light text-danger" '
                    . 'title="Desactivar" onclick="desactivar(' . $idusuario . ')">'
                    . '<i class="fas fa-user-slash"></i></button>';
            } else {
                $acciones .=
                    '<button type="button" class="btn btn-light text-success" '
                    . 'title="Activar" onclick="activar(' . $idusuario . ')">'
                    . '<i class="fas fa-user-check"></i></button>';
            }

            $acciones .= '</div>';

            $imagen = tiquepos_user_image_filename(
                $idusuario,
                (string)($reg['imagen'] ?? '')
            );
            $foto = $imagen !== ''
                ? tiquepos_media_url('users', $imagen)
                : avatarUsuarioPredeterminado();

            $nombreUsuario =
                '<div class="d-flex align-items-center">'
                . '<img src="' . escapar($foto) . '" alt="" '
                . 'class="rounded-circle mr-2" width="38" height="38" '
                . 'style="object-fit:cover;">'
                . '<div><strong>' . escapar($reg['nombre']) . '</strong>'
                . '<div class="text-muted small">@' . escapar($reg['login']) . '</div></div>'
                . '</div>';

            $documento =
                '<span class="text-muted small">' . escapar($reg['tipo_documento']) . '</span>'
                . '<div>' . escapar($reg['num_documento']) . '</div>';

            $contacto =
                '<div>' . escapar($reg['telefono']) . '</div>'
                . '<div class="text-muted small">' . escapar($reg['email']) . '</div>';

            $rol = strtoupper((string)($reg['rol'] ?? 'SIN ROL'));
            $claseRol = 'badge-secondary';

            if ($rol === 'ADMINISTRADOR') {
                $claseRol = 'badge-primary';
            } elseif ($rol === 'CAJERO') {
                $claseRol = 'badge-info';
            } elseif ($rol === 'VENDEDOR') {
                $claseRol = 'badge-light';
            }

            $idsucursal = (int)($reg['idsucursal'] ?? 0);
            $idcajasTexto = trim((string)($reg['idcajas'] ?? ''));
            $idcajas = $idcajasTexto === ''
                ? array()
                : array_values(
                    array_filter(
                        array_map('intval', explode(',', $idcajasTexto))
                    )
                );

            $nombresCajas = array();

            foreach ($idcajas as $idcaja) {
                $nombresCajas[] = $mapaCajas[$idcaja] ?? ('Caja #' . $idcaja);
            }

            $idalmacen = (int)($reg['idalmacen'] ?? 0);

            $data[] = array(
                '0' => $acciones,
                '1' => $nombreUsuario,
                '2' => $documento,
                '3' => $contacto,
                '4' => '<span class="badge ' . $claseRol . '">' . escapar($rol) . '</span>',
                '5' => escapar($mapaSucursales[$idsucursal] ?? 'Sin sucursal'),
                '6' => escapar(!empty($nombresCajas) ? implode(', ', $nombresCajas) : 'Sin caja'),
                '7' => escapar($mapaAlmacenes[$idalmacen] ?? 'Sin almacén'),
                '8' => $activo
                    ? '<span class="badge badge-success">Activo</span>'
                    : '<span class="badge badge-danger">Inactivo</span>'
            );
        }

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            array(
                'sEcho' => 1,
                'iTotalRecords' => count($data),
                'iTotalDisplayRecords' => count($data),
                'aaData' => $data
            ),
            JSON_UNESCAPED_UNICODE
        );
        break;

    case 'permisos':
        require_once __DIR__ . '/../Models/Permiso.php';

        $permiso = new Permiso();
        $lista = $permiso->listar();
        $idusuario = (int)($_GET['id'] ?? 0);
        $marcados = $user->listarmarcados($idusuario);
        $valores = array();

        foreach ($marcados as $item) {
            $valores[] = (int)$item['idpermiso'];
        }

        foreach ($lista as $reg) {
            $idpermiso = (int)$reg['idpermiso'];
            $checked = in_array($idpermiso, $valores, true) ? ' checked' : '';

            echo '<label class="permission-item">'
                . '<input type="checkbox" name="permiso[]" value="' . $idpermiso . '"' . $checked . '>'
                . '<span class="permission-check"><i class="fas fa-check"></i></span>'
                . '<span>' . escapar($reg['nombre']) . '</span>'
                . '</label>';
        }
        break;

    case 'solicitar_otp':
        try {

            $loginRecuperacion = trim((string)($_POST['login'] ?? ''));
            $tokenTurnstile = trim((string)($_POST['cf-turnstile-response'] ?? ''));


            if ($loginRecuperacion === '') {

                responderJson(
                    false,
                    'Ingresa tu correo electrónico.'
                );

                break;
            }



            $validacionTurnstile = validarTurnstile($tokenTurnstile);


            if (empty($validacionTurnstile['ok'])) {

                responderJson(
                    false,
                    (string)($validacionTurnstile['mensaje'] ?? 'No se pudo validar la seguridad.')
                );

                break;
            }




            $configReset = obtenerConfiguracionRecuperacion();

            $minutosVigencia = max(
                1,
                (int)($configReset['otp_expiration_minutes'] ?? 10)
            );


            $ventanaSolicitudes = max(
                1,
                (int)($configReset['request_window_minutes'] ?? 15)
            );


            $maxSolicitudesIp = max(
                1,
                (int)($configReset['max_requests_per_ip'] ?? 5)
            );


            $ipRemota = trim(
                (string)($_SERVER['REMOTE_ADDR'] ?? '')
            );




            if (
                $ipRemota !== ''
                && $user->contarSolicitudesRecuperacionPorIp(
                    $ipRemota,
                    $ventanaSolicitudes
                ) >= $maxSolicitudesIp
            ) {

                responderJson(
                    false,
                    'Se alcanzó el límite temporal de solicitudes. Intenta nuevamente más tarde.'
                );

                break;
            }




            /*
         * Buscar por correo electrónico
         */
            $usuarioReset = $user->buscarParaRecuperacion(
                $loginRecuperacion
            );



            if (empty($usuarioReset)) {

                responderJson(
                    false,
                    'No encontramos un usuario activo con ese correo electrónico.'
                );

                break;
            }




            $correoReset = trim(
                (string)($usuarioReset['email'] ?? '')
            );



            if (!filter_var($correoReset, FILTER_VALIDATE_EMAIL)) {

                responderJson(
                    false,
                    'Este usuario no tiene un correo válido registrado.'
                );

                break;
            }




            $tokenReset = bin2hex(
                random_bytes(32)
            );


            $codigoOtp = (string)random_int(
                100000,
                999999
            );


            $tokenHash = hash(
                'sha256',
                $tokenReset
            );


            $otpHash = hash_hmac(
                'sha256',
                $codigoOtp,
                $tokenReset
            );




            $user->invalidarRecuperacionesActivas(
                (int)$usuarioReset['idusuario']
            );



            $idSolicitud = $user->crearSolicitudRecuperacion(
                (int)$usuarioReset['idusuario'],
                $tokenHash,
                $otpHash,
                $minutosVigencia,
                $ipRemota
            );



            if ((int)$idSolicitud <= 0) {

                responderJson(
                    false,
                    'No se pudo generar el código de recuperación.'
                );

                break;
            }





            $mailer = new PasswordResetMailer(
                $configReset
            );



            $enviado = $mailer->enviarOtp(
                $correoReset,
                (string)($usuarioReset['nombre'] ?? ''),
                $codigoOtp,
                $minutosVigencia
            );




            if (!$enviado) {


                $user->invalidarSolicitudRecuperacion(
                    (int)$idSolicitud
                );



                responderJson(
                    false,
                    'No se pudo enviar el correo OTP. Revisa la configuración SMTP.'
                );


                break;
            }




            responderJson(
                true,
                'Código enviado al correo registrado.',
                array(
                    'reset_token' => $tokenReset,
                    'expires_minutes' => $minutosVigencia
                )
            );
        } catch (Throwable $e) {


            error_log(
                '[PASSWORD RESET SMTP] '
                    . $e->getMessage()
            );



            responderJson(
                false,
                '[SMTP ERROR] '
                    . $e->getMessage()
            );
        }

        break;

    case 'verificar_otp':
        try {
            $tokenReset = trim((string)($_POST['reset_token'] ?? ''));
            $codigoOtp = trim((string)($_POST['otp'] ?? ''));
            $configReset = obtenerConfiguracionRecuperacion();
            $maxIntentosOtp = max(1, (int)($configReset['max_otp_attempts'] ?? 5));

            if (!tokenRecuperacionValido($tokenReset) || !preg_match('/^\d{6}$/', $codigoOtp)) {
                responderJson(false, 'Ingresa el código OTP de 6 dígitos.');
                break;
            }

            $solicitud = $user->obtenerSolicitudRecuperacion(hash('sha256', $tokenReset));

            if (
                empty($solicitud)
                || !empty($solicitud['used_at'])
                || (int)($solicitud['condicion'] ?? 0) !== 1
            ) {
                responderJson(false, 'La solicitud de recuperación ya no es válida.');
                break;
            }

            if ((int)($solicitud['vigente'] ?? 0) !== 1) {
                $user->invalidarSolicitudRecuperacion((int)$solicitud['idreset']);
                responderJson(false, 'El código OTP venció. Solicita uno nuevo.');
                break;
            }

            if (!empty($solicitud['verified_at'])) {
                responderJson(true, 'Código verificado correctamente.');
                break;
            }

            if ((int)$solicitud['attempts'] >= $maxIntentosOtp) {
                $user->invalidarSolicitudRecuperacion((int)$solicitud['idreset']);
                responderJson(false, 'Se agotaron los intentos. Solicita un nuevo código OTP.');
                break;
            }

            $otpCalculado = hash_hmac('sha256', $codigoOtp, $tokenReset);

            if (!hash_equals((string)$solicitud['otp_hash'], $otpCalculado)) {
                $user->incrementarIntentosRecuperacion((int)$solicitud['idreset']);
                responderJson(false, 'El código OTP es incorrecto.');
                break;
            }

            if (!$user->marcarRecuperacionVerificada((int)$solicitud['idreset'])) {
                responderJson(false, 'No se pudo verificar el código OTP.');
                break;
            }

            responderJson(true, 'Código verificado correctamente.');
        } catch (Throwable $e) {
            error_log('[PASSWORD RESET] Verificación OTP: ' . $e->getMessage());
            responderJson(false, 'No se pudo verificar el código OTP.');
        }
        break;

    case 'restablecer_clave':
        try {
            $tokenReset = trim((string)($_POST['reset_token'] ?? ''));
            $claveNueva = (string)($_POST['clave'] ?? '');

            if (!tokenRecuperacionValido($tokenReset)) {
                responderJson(false, 'La solicitud de recuperación no es válida.');
                break;
            }

            if ($claveNueva === '') {
                responderJson(false, 'Ingresa la nueva contraseña.');
                break;
            }

            $solicitud = $user->obtenerSolicitudRecuperacion(hash('sha256', $tokenReset));

            if (
                empty($solicitud)
                || !empty($solicitud['used_at'])
                || empty($solicitud['verified_at'])
                || (int)($solicitud['vigente'] ?? 0) !== 1
                || (int)($solicitud['condicion'] ?? 0) !== 1
            ) {
                responderJson(
                    false,
                    'La verificación venció o ya fue utilizada. Solicita un nuevo código OTP.'
                );
                break;
            }

            $actualizado = $user->finalizarRecuperacion(
                (int)$solicitud['idreset'],
                (int)$solicitud['idusuario'],
                hash('SHA256', $claveNueva)
            );

            responderJson(
                $actualizado,
                $actualizado
                    ? 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.'
                    : 'No se pudo actualizar la contraseña.'
            );
        } catch (Throwable $e) {
            error_log('[PASSWORD RESET] Cambio de clave: ' . $e->getMessage());
            responderJson(false, 'No se pudo actualizar la contraseña.');
        }
        break;

    case 'verificar':
        header('Content-Type: text/plain; charset=utf-8');

        $login = trim((string)($_POST['nombre'] ?? ''));
        $clave = (string)($_POST['clave'] ?? '');
        $tokenTurnstile = trim((string)($_POST['cf-turnstile-response'] ?? ''));

        if ($login === '' || $clave === '') {
            echo '0';
            break;
        }

        $validacionTurnstile = validarTurnstile($tokenTurnstile);

        if (empty($validacionTurnstile['ok'])) {
            echo (string)($validacionTurnstile['mensaje'] ?? 'No se pudo validar la seguridad.');
            break;
        }

        $respuesta = $user->verificar(
            $login,
            hash('SHA256', $clave)
        );

        if (!$respuesta) {
            echo '0';
            break;
        }

        session_regenerate_id(true);

        $_SESSION['idusuario'] = (int)$respuesta['idusuario'];
        $_SESSION['nombre'] = (string)$respuesta['nombre'];
        $_SESSION['imagen'] = tiquepos_user_image_filename(
            (int)$respuesta['idusuario'],
            (string)($respuesta['imagen'] ?? '')
        );
        $_SESSION['login'] = (string)$respuesta['login'];
        $_SESSION['cargo'] = (string)$respuesta['cargo'];

        $marcados = $user->listarmarcados((int)$respuesta['idusuario']);
        $valores = array();

        foreach ($marcados as $item) {
            $valores[] = (int)$item['idpermiso'];
        }

        $_SESSION['dashboard'] = in_array(1, $valores, true) ? 1 : 0;
        $_SESSION['almacen'] = in_array(2, $valores, true) ? 1 : 0;
        $_SESSION['compras'] = in_array(3, $valores, true) ? 1 : 0;
        $_SESSION['ventas'] = in_array(4, $valores, true) ? 1 : 0;
        $_SESSION['users'] = in_array(5, $valores, true) ? 1 : 0;
        $_SESSION['datebuy'] = in_array(6, $valores, true) ? 1 : 0;
        $_SESSION['clientdatesales'] = in_array(7, $valores, true) ? 1 : 0;
        $_SESSION['settings'] = in_array(8, $valores, true) ? 1 : 0;

        $_SESSION['idsucursal_activa'] = 0;
        $_SESSION['modo_caja'] = 'LEGACY';
        $_SESSION['modo_caja_objetivo'] = '';
        $_SESSION['idcaja_activa'] = 0;
        $_SESSION['idcaja_preparada'] = 0;
        $_SESSION['idapertura_activa'] = 0;
        $_SESSION['idalmacen_activo'] = 0;
        $_SESSION['cajas_asignadas'] = array();

        $_SESSION['rol_caja'] = 'VENDEDOR';
        $_SESSION['puede_vender'] = 0;
        $_SESSION['puede_cobrar'] = 0;
        $_SESSION['puede_abrir_caja'] = 0;
        $_SESSION['puede_cerrar_caja'] = 0;
        $_SESSION['puede_operar_caja'] = 0;

        try {
            require_once __DIR__ . '/../Models/Company.php';

            $company = new Company();
            $empresas = $company->listar();

            if (is_array($empresas) && isset($empresas[0])) {
                $nombreEmpresa = (string)($empresas[0]['nombre'] ?? '');
                $_SESSION['nombreEmrpesa'] = $nombreEmpresa;
                $_SESSION['nombreEmpresa'] = $nombreEmpresa;
            }
        } catch (Throwable $e) {
            error_log('[LOGIN EMPRESA] ' . $e->getMessage());
        }

        try {
            require_once __DIR__ . '/../Models/ConfiguracionCaja.php';

            $configuracionCaja = new ConfiguracionCaja();
            $configuracion = $configuracionCaja->obtenerSucursalPrincipal();

            if (is_array($configuracion)) {
                $idsucursal = (int)($configuracion['idsucursal'] ?? 0);
                $modoCaja = strtoupper(trim((string)($configuracion['modo'] ?? 'LEGACY')));
                $modoObjetivo = strtoupper(trim((string)($configuracion['modo_objetivo'] ?? '')));

                if (!in_array($modoCaja, array('LEGACY', 'CAJA_UNICA', 'MULTICAJA'), true)) {
                    $modoCaja = 'LEGACY';
                }

                $_SESSION['idsucursal_activa'] = $idsucursal;
                $_SESSION['modo_caja'] = $modoCaja;
                $_SESSION['modo_caja_objetivo'] = $modoObjetivo;

                if ($modoCaja === 'CAJA_UNICA') {
                    $_SESSION['idcaja_activa'] = (int)($configuracion['idcaja_unica'] ?? 0);
                    $_SESSION['idcaja_preparada'] = $_SESSION['idcaja_activa'];
                }
            }
        } catch (Throwable $e) {
            error_log('[LOGIN CONTEXTO CAJA] ' . $e->getMessage());
        }

        try {
            $asignacion = $user->obtenerAsignacionOperativa(
                (int)$respuesta['idusuario']
            );

            if (!empty($asignacion)) {
                if ((int)$asignacion['idsucursal'] > 0) {
                    $_SESSION['idsucursal_activa'] = (int)$asignacion['idsucursal'];
                }

                $_SESSION['idalmacen_activo'] = (int)$asignacion['idalmacen'];
                $_SESSION['rol_caja'] = (string)$asignacion['rol'];
                $_SESSION['puede_vender'] = (int)$asignacion['puede_vender'];
                $_SESSION['puede_cobrar'] = (int)$asignacion['puede_cobrar'];
                $_SESSION['puede_abrir_caja'] = (int)$asignacion['puede_abrir_caja'];
                $_SESSION['puede_cerrar_caja'] = (int)$asignacion['puede_cerrar_caja'];
                $_SESSION['puede_operar_caja'] = (int)$asignacion['puede_operar_caja'];

                $cajasAsignadas = isset($asignacion['idcajas'])
                    && is_array($asignacion['idcajas'])
                    ? array_values(array_unique(array_map('intval', $asignacion['idcajas'])))
                    : array();

                $_SESSION['cajas_asignadas'] = $cajasAsignadas;

                /*
                 * En MULTICAJA la caja siempre debe ser seleccionada
                 * expresamente por el usuario al iniciar sesión,
                 * incluso cuando solo tenga una caja autorizada.
                 */
                if ($_SESSION['modo_caja'] === 'MULTICAJA') {
                    $_SESSION['idcaja_activa'] = 0;
                    $_SESSION['idcaja_preparada'] = 0;
                    $_SESSION['idapertura_activa'] = 0;
                }
            }
        } catch (Throwable $e) {
            error_log('[LOGIN ASIGNACION USUARIO] ' . $e->getMessage());
        }

        echo '1';
        break;

    case 'salir':
        session_unset();
        session_destroy();

        header('Location: ../index.php');
        exit;

    default:
        http_response_code(404);
        echo 'Operación no válida';
        break;
}
