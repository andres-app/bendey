<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../Models/Atributo.php';

function responderJson($ok, $mensaje = '', $extra = [], $codigoHttp = 200)
{
    http_response_code($codigoHttp);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => (bool)$ok, 'mensaje' => $mensaje], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['nombre']) || (int)($_SESSION['almacen'] ?? 0) !== 1) {
    responderJson(false, 'No tiene autorización para gestionar atributos.', [], 403);
}

$atributo = new Atributo();
$op = $_GET['op'] ?? '';
$idatributo = isset($_POST['idatributo']) ? (int)$_POST['idatributo'] : 0;
$nombre = trim($_POST['nombre'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');

switch ($op) {
    case 'guardaryeditar':
        if ($nombre === '') responderJson(false, 'El nombre del atributo es obligatorio.', [], 422);
        if (mb_strlen($nombre) > 100) responderJson(false, 'El nombre no puede superar los 100 caracteres.', [], 422);
        if (mb_strlen($descripcion) > 255) responderJson(false, 'La descripción no puede superar los 255 caracteres.', [], 422);
        if ($atributo->existeNombre($nombre, $idatributo)) responderJson(false, 'Ya existe un atributo con ese nombre.', [], 409);

        if ($idatributo === 0) {
            $respuesta = $atributo->insertar($nombre, $descripcion);
            responderJson((bool)$respuesta, $respuesta ? 'Atributo registrado correctamente.' : 'No se pudo registrar el atributo.', [], $respuesta ? 200 : 500);
        }

        if (empty($atributo->mostrar($idatributo))) responderJson(false, 'El atributo que intenta editar no existe.', [], 404);
        $respuesta = $atributo->editar($idatributo, $nombre, $descripcion);
        responderJson((bool)$respuesta, $respuesta ? 'Atributo actualizado correctamente.' : 'No se pudo actualizar el atributo.', [], $respuesta ? 200 : 500);
        break;

    case 'estadisticas':
        $stats = $atributo->estadisticas();
        responderJson(true, '', ['data' => [
            'total' => isset($stats['total']) ? (int)$stats['total'] : 0,
            'activos' => isset($stats['activos']) ? (int)$stats['activos'] : 0,
            'inactivos' => isset($stats['inactivos']) ? (int)$stats['inactivos'] : 0,
        ]]);
        break;

    case 'desactivar':
        if ($idatributo <= 0) responderJson(false, 'Identificador de atributo inválido.', [], 422);
        if (empty($atributo->mostrar($idatributo))) responderJson(false, 'El atributo no existe.', [], 404);
        $respuesta = $atributo->desactivar($idatributo);
        responderJson((bool)$respuesta, $respuesta ? 'Atributo desactivado correctamente.' : 'No se pudo desactivar el atributo.', [], $respuesta ? 200 : 500);
        break;

    case 'activar':
        if ($idatributo <= 0) responderJson(false, 'Identificador de atributo inválido.', [], 422);
        if (empty($atributo->mostrar($idatributo))) responderJson(false, 'El atributo no existe.', [], 404);
        $respuesta = $atributo->activar($idatributo);
        responderJson((bool)$respuesta, $respuesta ? 'Atributo activado correctamente.' : 'No se pudo activar el atributo.', [], $respuesta ? 200 : 500);
        break;

    case 'mostrar':
        if ($idatributo <= 0) responderJson(false, 'Identificador de atributo inválido.', [], 422);
        $registro = $atributo->mostrar($idatributo);
        if (empty($registro)) responderJson(false, 'El atributo no existe.', [], 404);
        responderJson(true, '', ['data' => $registro]);
        break;

    case 'listar':
        $respuesta = $atributo->listar();
        $data = [];

        foreach ($respuesta as $reg) {
            $id = (int)$reg['idatributo'];
            $estado = (int)$reg['estado'];
            $nombrePlano = (string)$reg['nombre'];
            $nombreHtml = htmlspecialchars($nombrePlano, ENT_QUOTES, 'UTF-8');
            $descripcionPlano = trim((string)($reg['descripcion'] ?? ''));
            $descripcionHtml = htmlspecialchars($descripcionPlano, ENT_QUOTES, 'UTF-8');
            $nombreJs = htmlspecialchars(json_encode($nombrePlano, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');

            $celdaNombre = '<div class="attribute-name-cell">'
                . '<span class="attribute-name-icon"><i class="fas fa-sliders-h"></i></span>'
                . '<span class="attribute-name-text">' . $nombreHtml . '</span>'
                . '</div>';

            $celdaDescripcion = $descripcionPlano !== ''
                ? '<span class="attribute-description">' . $descripcionHtml . '</span>'
                : '<span class="attribute-description is-empty">Sin descripción</span>';

            $botonValores = '<button type="button" class="attribute-values-btn" onclick="gestionarValores(' . $id . ', ' . $nombreJs . ')" title="Gestionar valores">'
                . '<i class="fas fa-layer-group"></i><span>Gestionar</span></button>';

            $estadoHtml = $estado === 1
                ? '<span class="attribute-status attribute-status--active">Activo</span>'
                : '<span class="attribute-status attribute-status--inactive">Inactivo</span>';

            $botonEditar = '<button type="button" class="attribute-icon-btn attribute-icon-btn--edit" onclick="editarAtributo(' . $id . ')" title="Editar" aria-label="Editar atributo"><i class="fas fa-pencil-alt"></i></button>';
            $botonEstado = $estado === 1
                ? '<button type="button" class="attribute-icon-btn attribute-icon-btn--danger" onclick="desactivar(' . $id . ')" title="Desactivar" aria-label="Desactivar atributo"><i class="fas fa-times"></i></button>'
                : '<button type="button" class="attribute-icon-btn attribute-icon-btn--activate" onclick="activar(' . $id . ')" title="Activar" aria-label="Activar atributo"><i class="fas fa-check"></i></button>';

            $data[] = [
                $id,
                $celdaNombre,
                $celdaDescripcion,
                $botonValores,
                $estadoHtml,
                '<div class="attribute-actions">' . $botonEditar . $botonEstado . '</div>',
            ];
        }

        responderJson(true, '', [
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data,
        ]);
        break;

    case 'select':
        header('Content-Type: text/html; charset=utf-8');
        $respuesta = $atributo->select();
        echo '<option value="">Seleccione...</option>';
        foreach ($respuesta as $reg) {
            echo '<option value="' . (int)$reg['idatributo'] . '">' . htmlspecialchars((string)$reg['nombre'], ENT_QUOTES, 'UTF-8') . '</option>';
        }
        exit;

    case 'listarValores':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($id > 0 ? $atributo->listarValores($id) : [], JSON_UNESCAPED_UNICODE);
        exit;

    case 'atributos_activos':
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($atributo->select(), JSON_UNESCAPED_UNICODE);
        exit;

    default:
        responderJson(false, 'Operación no válida.', [], 400);
}
