<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Almacen.php';

$almacen = new Almacen();

$idalmacen = isset($_POST['idalmacen']) ? (int)$_POST['idalmacen'] : 0;
$nombre = trim((string)($_POST['nombre'] ?? ''));
$ubicacion = trim((string)($_POST['ubicacion'] ?? ''));
$descripcion = trim((string)($_POST['descripcion'] ?? ''));
$op = $_GET['op'] ?? '';

function responderAlmacenJson(bool $ok, string $mensaje = '', array $extra = [], int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => $ok, 'mensaje' => $mensaje], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

switch ($op) {
    case 'guardaryeditar':
        if ($nombre === '') {
            responderAlmacenJson(false, 'El nombre del almacén es obligatorio.', [], 422);
        }
        if (mb_strlen($nombre) > 100 || mb_strlen($ubicacion) > 200 || mb_strlen($descripcion) > 200) {
            responderAlmacenJson(false, 'Uno de los campos supera la longitud permitida.', [], 422);
        }

        if ($idalmacen <= 0) {
            $rspta = $almacen->insertar($nombre, $ubicacion, $descripcion);
            responderAlmacenJson((bool)$rspta, $rspta ? 'Almacén registrado correctamente.' : 'No se pudo registrar el almacén.', [], $rspta ? 200 : 500);
        }

        $rspta = $almacen->editar($idalmacen, $nombre, $ubicacion, $descripcion);
        responderAlmacenJson((bool)$rspta, $rspta ? 'Almacén actualizado correctamente.' : 'No se pudo actualizar el almacén.', [], $rspta ? 200 : 500);
        break;

    case 'estadisticas':
        $stats = $almacen->estadisticas();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'total' => isset($stats['total']) ? (int)$stats['total'] : 0,
            'activos' => isset($stats['activos']) ? (int)$stats['activos'] : 0,
            'inactivos' => isset($stats['inactivos']) ? (int)$stats['inactivos'] : 0,
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'desactivar':
        $rspta = $almacen->desactivar($idalmacen);
        responderAlmacenJson((bool)$rspta, $rspta ? 'Almacén desactivado correctamente.' : 'No se pudo desactivar el almacén.', [], $rspta ? 200 : 500);
        break;

    case 'activar':
        $rspta = $almacen->activar($idalmacen);
        responderAlmacenJson((bool)$rspta, $rspta ? 'Almacén activado correctamente.' : 'No se pudo activar el almacén.', [], $rspta ? 200 : 500);
        break;

    case 'mostrar':
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($almacen->mostrar((string)$idalmacen), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    case 'listar':
        header('Content-Type: application/json; charset=utf-8');
        $rspta = $almacen->listar();
        $data = [];

        foreach ($rspta as $reg) {
            $id = (int)$reg['idalmacen'];
            $activo = (int)$reg['estado'] === 1;
            $nombreSeguro = htmlspecialchars((string)$reg['nombre'], ENT_QUOTES, 'UTF-8');
            $ubicacion = trim((string)($reg['ubicacion'] ?? ''));
            $descripcion = trim((string)($reg['descripcion'] ?? ''));
            $ubicacionSeguro = htmlspecialchars($ubicacion, ENT_QUOTES, 'UTF-8');
            $descripcionSeguro = htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8');

            $celdaNombre = '<div class="warehouse-name-cell">'
                . '<span class="warehouse-name-icon"><i class="fas fa-warehouse"></i></span>'
                . '<span class="warehouse-name-text">' . $nombreSeguro . '</span>'
                . '</div>';

            $celdaUbicacion = $ubicacion !== ''
                ? '<span class="warehouse-secondary"><i class="fas fa-map-marker-alt" style="margin-right:6px;color:#94a3b8"></i>' . $ubicacionSeguro . '</span>'
                : '<span class="warehouse-secondary is-empty">Sin ubicación</span>';

            $celdaDescripcion = $descripcion !== ''
                ? '<span class="warehouse-secondary">' . $descripcionSeguro . '</span>'
                : '<span class="warehouse-secondary is-empty">Sin descripción</span>';

            $estadoHtml = $activo
                ? '<span class="warehouse-status warehouse-status--active">Activo</span>'
                : '<span class="warehouse-status warehouse-status--inactive">Inactivo</span>';

            $botonEditar = '<button type="button" class="warehouse-icon-btn warehouse-icon-btn--edit" onclick="editarAlmacen(' . $id . ')" title="Editar" aria-label="Editar almacén"><i class="fas fa-pencil-alt"></i></button>';
            $botonEstado = $activo
                ? '<button type="button" class="warehouse-icon-btn warehouse-icon-btn--danger" onclick="desactivar(' . $id . ')" title="Desactivar" aria-label="Desactivar almacén"><i class="fas fa-times"></i></button>'
                : '<button type="button" class="warehouse-icon-btn warehouse-icon-btn--activate" onclick="activar(' . $id . ')" title="Activar" aria-label="Activar almacén"><i class="fas fa-check"></i></button>';

            $data[] = [
                $id,
                $celdaNombre,
                $celdaUbicacion,
                $celdaDescripcion,
                $estadoHtml,
                '<div class="warehouse-actions">' . $botonEditar . $botonEstado . '</div>',
            ];
        }

        echo json_encode([
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    case 'selectAlmacen':
        $idSeleccionado = isset($_POST['idseleccionado']) ? (int)$_POST['idseleccionado'] : 0;
        $rspta = $almacen->select();
        echo '<option value="">Seleccione un almacén</option>';
        foreach ($rspta as $reg) {
            $id = (int)$reg['idalmacen'];
            $selected = $id === $idSeleccionado ? ' selected' : '';
            echo '<option value="' . $id . '"' . $selected . '>' . htmlspecialchars((string)$reg['nombre'], ENT_QUOTES, 'UTF-8') . '</option>';
        }
        break;

    default:
        http_response_code(400);
        echo 'Operación no válida';
        break;
}
