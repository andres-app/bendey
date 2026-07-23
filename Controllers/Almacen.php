<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Almacen.php';

$almacen = new Almacen();

$idalmacen = isset($_POST['idalmacen']) ? (int)$_POST['idalmacen'] : 0;
$nombre = trim((string)($_POST['nombre'] ?? ''));
$ubicacion = trim((string)($_POST['ubicacion'] ?? ''));
$descripcion = trim((string)($_POST['descripcion'] ?? ''));

switch ($_GET['op'] ?? '') {

    case 'guardaryeditar':
        if ($idalmacen <= 0) {
            $rspta = $almacen->insertar($nombre, $ubicacion, $descripcion);

            echo $rspta
                ? '✅ Almacén registrado correctamente'
                : '❌ No se pudo registrar el almacén';
        } else {
            $rspta = $almacen->editar(
                $idalmacen,
                $nombre,
                $ubicacion,
                $descripcion
            );

            echo $rspta
                ? '✅ Almacén actualizado correctamente'
                : '❌ No se pudo actualizar el almacén';
        }
        break;

    case 'desactivar':
        $rspta = $almacen->desactivar($idalmacen);

        echo $rspta
            ? '🔴 Almacén desactivado correctamente'
            : '❌ No se pudo desactivar';
        break;

    case 'activar':
        $rspta = $almacen->activar($idalmacen);

        echo $rspta
            ? '🟢 Almacén activado correctamente'
            : '❌ No se pudo activar';
        break;

    case 'mostrar':
        header('Content-Type: application/json; charset=utf-8');

        $rspta = $almacen->mostrar((string)$idalmacen);

        echo json_encode(
            $rspta,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        break;

    case 'listar':
        header('Content-Type: application/json; charset=utf-8');

        $rspta = $almacen->listar();
        $data = [];

        foreach ($rspta as $reg) {
            $id = (int)$reg['idalmacen'];
            $activo = (int)$reg['estado'] === 1;

            $data[] = [
                '0' => $id,
                '1' => $reg['nombre'],
                '2' => $reg['ubicacion'],
                '3' => $reg['descripcion'],
                '4' => $activo
                    ? '<span class="badge badge-success">Activo</span>'
                    : '<span class="badge badge-danger">Inactivo</span>',
                '5' => $activo
                    ? '<button class="btn btn-warning btn-sm" onclick="mostrar(' . $id . ')">'
                        . '<i class="fas fa-pencil-alt"></i>'
                        . '</button> '
                        . '<button class="btn btn-danger btn-sm" onclick="desactivar(' . $id . ')">'
                        . '<i class="fas fa-times"></i>'
                        . '</button>'
                    : '<button class="btn btn-warning btn-sm" onclick="mostrar(' . $id . ')">'
                        . '<i class="fas fa-pencil-alt"></i>'
                        . '</button> '
                        . '<button class="btn btn-primary btn-sm" onclick="activar(' . $id . ')">'
                        . '<i class="fas fa-check"></i>'
                        . '</button>'
            ];
        }

        echo json_encode(
            [
                'sEcho' => 1,
                'iTotalRecords' => count($data),
                'iTotalDisplayRecords' => count($data),
                'aaData' => $data
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        break;

    case 'selectAlmacen':
        $idSeleccionado = isset($_POST['idseleccionado'])
            ? (int)$_POST['idseleccionado']
            : 0;

        $rspta = $almacen->select();

        echo '<option value="">Seleccione un almacén</option>';

        foreach ($rspta as $reg) {
            $id = (int)$reg['idalmacen'];
            $selected = $id === $idSeleccionado
                ? ' selected'
                : '';

            echo '<option value="' . $id . '"' . $selected . '>'
                . htmlspecialchars(
                    (string)$reg['nombre'],
                    ENT_QUOTES,
                    'UTF-8'
                )
                . '</option>';
        }
        break;

    default:
        http_response_code(400);
        echo 'Operación no válida';
        break;
}
