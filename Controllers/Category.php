<?php
require_once "../Models/Category.php";

$category = new Category();

$idcategoria = isset($_POST["idcategoria"]) ? $_POST["idcategoria"] : "";
$nombre = isset($_POST["nombre"]) ? $_POST["nombre"] : "";
$descripcion = isset($_POST["descripcion"]) ? $_POST["descripcion"] : "";

switch ($_GET["op"]) {
    case 'guardaryeditar':
        if (empty($idcategoria)) {
            $rspta = $category->insertar($nombre, $descripcion);
            echo $rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos";
        } else {
            $rspta = $category->editar($idcategoria, $nombre, $descripcion);
            echo $rspta ? "Datos actualizados correctamente" : "No se pudo actualizar los datos";
        }
        break;

    case 'editarNombre':
        $nombre = trim($nombre);
        if (empty($idcategoria) || $nombre === '') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('ok' => false, 'mensaje' => 'Completa el nombre de la categoría.'));
            break;
        }

        $rspta = $category->editarNombre($idcategoria, $nombre);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'ok' => (bool)$rspta,
            'mensaje' => $rspta ? 'Nombre actualizado correctamente' : 'No se pudo actualizar el nombre'
        ));
        break;

    case 'estadisticas':
        $stats = $category->estadisticas();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'total' => isset($stats['total']) ? (int)$stats['total'] : 0,
            'activas' => isset($stats['activas']) ? (int)$stats['activas'] : 0,
            'inactivas' => isset($stats['inactivas']) ? (int)$stats['inactivas'] : 0
        ));
        break;

    case 'desactivar':
        $rspta = $category->desactivar($idcategoria);
        echo $rspta ? "Datos desactivados correctamente" : "No se pudo desactivar los datos";
        break;

    case 'activar':
        $rspta = $category->activar($idcategoria);
        echo $rspta ? "Datos activados correctamente" : "No se pudo activar los datos";
        break;

    case 'mostrar':
        $rspta = $category->mostrar($idcategoria);
        echo json_encode($rspta);
        break;

    case 'listar':
        $rspta = $category->listar();
        $data = array();

        foreach ($rspta as $reg) {
            $id = (int)$reg['idcategoria'];
            $nombreSeguro = htmlspecialchars($reg['nombre'], ENT_QUOTES, 'UTF-8');
            $nombreUrl = rawurlencode($reg['nombre']);

            $celdaNombre =
                '<div class="category-name-cell">' .
                    '<span class="category-name-icon"><i class="fas fa-tag"></i></span>' .
                    '<span class="category-name-text">' . $nombreSeguro . '</span>' .
                '</div>';

            $botonSub =
                '<button type="button" class="category-sub-btn" ' .
                    'onclick="verSubcategorias(' . $id . ', decodeURIComponent(\'' . $nombreUrl . '\'))" ' .
                    'title="Ver subcategorías">' .
                    '<i class="fas fa-sitemap"></i><span>Gestionar</span>' .
                '</button>';

            if ($reg['condicion']) {
                $botonesEstado =
                    '<div class="category-actions">' .
                        '<button type="button" class="category-icon-btn category-icon-btn--edit" onclick="editarCategoria(' . $id . ')" title="Editar" aria-label="Editar categoría"><i class="fas fa-pencil-alt"></i></button>' .
                        '<button type="button" class="category-icon-btn category-icon-btn--danger" onclick="desactivar(' . $id . ')" title="Desactivar" aria-label="Desactivar categoría"><i class="fas fa-times"></i></button>' .
                    '</div>';
                $estado = '<span class="category-status category-status--active">Activa</span>';
            } else {
                $botonesEstado =
                    '<div class="category-actions">' .
                        '<button type="button" class="category-icon-btn category-icon-btn--edit" onclick="editarCategoria(' . $id . ')" title="Editar" aria-label="Editar categoría"><i class="fas fa-pencil-alt"></i></button>' .
                        '<button type="button" class="category-icon-btn category-icon-btn--activate" onclick="activar(' . $id . ')" title="Activar" aria-label="Activar categoría"><i class="fas fa-check"></i></button>' .
                    '</div>';
                $estado = '<span class="category-status category-status--inactive">Inactiva</span>';
            }

            $data[] = array(
                "0" => $celdaNombre,
                "1" => $botonSub,
                "2" => $estado,
                "3" => $botonesEstado,
            );
        }

        echo json_encode(array(
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data,
        ));
        break;

    case 'selectCategoria':
        $rspta = $category->select();
        echo '<option value="">Seleccione...</option>';
        foreach ($rspta as $reg) {
            echo '<option value="' . $reg['idcategoria'] . '">' . htmlspecialchars($reg['nombre'], ENT_QUOTES, 'UTF-8') . '</option>';
        }
        break;

    case 'listar_json':
        $rspta = $category->select();
        echo json_encode($rspta);
        break;
}
