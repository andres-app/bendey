<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../Models/AtributoValor.php';

function responderJsonValor($ok, $mensaje = '', $extra = [], $codigoHttp = 200)
{
    http_response_code($codigoHttp);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        array_merge(
            [
                'ok' => (bool)$ok,
                'mensaje' => $mensaje,
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

if (!isset($_SESSION['nombre']) || (int)($_SESSION['almacen'] ?? 0) !== 1) {
    responderJsonValor(false, 'No tiene autorización para gestionar valores de atributos.', [], 403);
}

$modelo = new AtributoValor();
$op = $_GET['op'] ?? '';

$idvalor = isset($_POST['idvalor']) ? (int)$_POST['idvalor'] : 0;
$idatributo = isset($_POST['idatributo']) ? (int)$_POST['idatributo'] : 0;
$valorTexto = trim($_POST['valor'] ?? '');

switch ($op) {
    case 'guardaryeditar':
        if ($valorTexto === '') {
            responderJsonValor(false, 'El valor es obligatorio.', [], 422);
        }

        if (mb_strlen($valorTexto) > 100) {
            responderJsonValor(false, 'El valor no puede superar los 100 caracteres.', [], 422);
        }

        if ($idvalor === 0) {
            if ($idatributo <= 0 || !$modelo->atributoExiste($idatributo)) {
                responderJsonValor(false, 'El atributo seleccionado no existe.', [], 422);
            }

            if ($modelo->existeValor($idatributo, $valorTexto)) {
                responderJsonValor(false, 'Ese valor ya existe para el atributo.', [], 409);
            }

            $respuesta = $modelo->insertar($idatributo, $valorTexto);
            responderJsonValor(
                (bool)$respuesta,
                $respuesta ? 'Valor registrado correctamente.' : 'No se pudo registrar el valor.',
                [],
                $respuesta ? 200 : 500
            );
        }

        $registroActual = $modelo->mostrar($idvalor);

        if (empty($registroActual)) {
            responderJsonValor(false, 'El valor que intenta editar no existe.', [], 404);
        }

        $idatributoReal = (int)$registroActual['idatributo'];

        if ($modelo->existeValor($idatributoReal, $valorTexto, $idvalor)) {
            responderJsonValor(false, 'Ese valor ya existe para el atributo.', [], 409);
        }

        $respuesta = $modelo->editar($idvalor, $valorTexto);
        responderJsonValor(
            (bool)$respuesta,
            $respuesta ? 'Valor actualizado correctamente.' : 'No se pudo actualizar el valor.',
            [],
            $respuesta ? 200 : 500
        );
        break;

    case 'listar':
        $id = isset($_GET['idatributo']) ? (int)$_GET['idatributo'] : 0;

        if ($id <= 0) {
            responderJsonValor(false, 'Identificador de atributo inválido.', [
                'sEcho' => 1,
                'iTotalRecords' => 0,
                'iTotalDisplayRecords' => 0,
                'aaData' => [],
            ], 422);
        }

        $respuesta = $modelo->listarPorAtributo($id);
        $data = [];

        foreach ($respuesta as $reg) {
            $idRegistro = (int)$reg['idvalor'];
            $estado = (int)$reg['estado'];
            $valorHtml = htmlspecialchars((string)$reg['valor'], ENT_QUOTES, 'UTF-8');

            $botonEditar = '<button type="button" class="btn btn-warning btn-sm" '
                . 'onclick="editarValor(' . $idRegistro . ')" title="Editar">'
                . '<i class="fas fa-pencil-alt"></i>'
                . '</button>';

            if ($estado === 1) {
                $estadoHtml = '<span class="badge badge-success">Activo</span>';
                $botonEstado = '<button type="button" class="btn btn-danger btn-sm" '
                    . 'onclick="desactivarValor(' . $idRegistro . ')" title="Desactivar">'
                    . '<i class="fas fa-times"></i>'
                    . '</button>';
            } else {
                $estadoHtml = '<span class="badge badge-danger">Inactivo</span>';
                $botonEstado = '<button type="button" class="btn btn-primary btn-sm" '
                    . 'onclick="activarValor(' . $idRegistro . ')" title="Activar">'
                    . '<i class="fas fa-check"></i>'
                    . '</button>';
            }

            $data[] = [
                $valorHtml,
                $estadoHtml,
                $botonEditar . ' ' . $botonEstado,
            ];
        }

        responderJsonValor(true, '', [
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data,
        ]);
        break;

    case 'mostrar':
        if ($idvalor <= 0) {
            responderJsonValor(false, 'Identificador de valor inválido.', [], 422);
        }

        $registro = $modelo->mostrar($idvalor);

        if (empty($registro)) {
            responderJsonValor(false, 'El valor no existe.', [], 404);
        }

        responderJsonValor(true, '', ['data' => $registro]);
        break;

    case 'desactivar':
        if ($idvalor <= 0) {
            responderJsonValor(false, 'Identificador de valor inválido.', [], 422);
        }

        if (empty($modelo->mostrar($idvalor))) {
            responderJsonValor(false, 'El valor no existe.', [], 404);
        }

        $respuesta = $modelo->desactivar($idvalor);
        responderJsonValor(
            (bool)$respuesta,
            $respuesta ? 'Valor desactivado correctamente.' : 'No se pudo desactivar el valor.',
            [],
            $respuesta ? 200 : 500
        );
        break;

    case 'activar':
        if ($idvalor <= 0) {
            responderJsonValor(false, 'Identificador de valor inválido.', [], 422);
        }

        if (empty($modelo->mostrar($idvalor))) {
            responderJsonValor(false, 'El valor no existe.', [], 404);
        }

        $respuesta = $modelo->activar($idvalor);
        responderJsonValor(
            (bool)$respuesta,
            $respuesta ? 'Valor activado correctamente.' : 'No se pudo activar el valor.',
            [],
            $respuesta ? 200 : 500
        );
        break;

    case 'valores_por_atributo':
        $id = isset($_GET['idatributo']) ? (int)$_GET['idatributo'] : 0;

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            $id > 0 ? $modelo->listarActivosPorAtributo($id) : [],
            JSON_UNESCAPED_UNICODE
        );
        exit;

    default:
        responderJsonValor(false, 'Operación no válida.', [], 400);
}
