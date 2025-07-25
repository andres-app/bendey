<?php
require_once "../Models/Product.php";

$product = new Product();

$idarticulo = isset($_POST["idarticulo"]) ? $_POST["idarticulo"] : "";
$idsubcategoria = isset($_POST["idsubcategoria"]) ? $_POST["idsubcategoria"] : "";
$idcategoria = isset($_POST["idcategoria"]) ? $_POST["idcategoria"] : "";
$idmedida = isset($_POST["idmedida"]) ? $_POST["idmedida"] : "";
$idalmacen = isset($_POST["idalmacen"]) ? $_POST["idalmacen"] : "";
$codigo = isset($_POST["codigo"]) ? $_POST["codigo"] : "";
$nombre = isset($_POST["nombre"]) ? $_POST["nombre"] : "";
$stock = isset($_POST["stock"]) ? $_POST["stock"] : "";
$precio_compra = isset($_POST["precio_compra"]) ? $_POST["precio_compra"] : null;
$precio_venta  = isset($_POST["precio_venta"]) ? $_POST["precio_venta"] : null;
$descripcion = isset($_POST["descripcion"]) ? $_POST["descripcion"] : "";
$imagen = isset($_POST["imagen"]) ? $_POST["imagen"] : "";

switch ($_GET["op"]) {
	case 'guardaryeditar':

		$rspta = $product->verificarCodigo($codigo);


		if (!file_exists($_FILES['imagen']['tmp_name']) || !is_uploaded_file($_FILES['imagen']['tmp_name'])) {
			(empty($_POST["imagenactual"])) ? $imagen = 'default.png' : $imagen = $_POST["imagenactual"];
		} else {
			if (!empty($_POST["imagenactual"]) && $_POST["imagenactual"] != 'default.png') {
				unlink("../Assets/img/products/" . $_POST["imagenactual"]);
			}
			$ext = explode(".", $_FILES["imagen"]["name"]);
			if ($_FILES['imagen']['type'] == "image/jpg" || $_FILES['imagen']['type'] == "image/jpeg" || $_FILES['imagen']['type'] == "image/png") {
				$imagen = round(microtime(true)) . '.' . end($ext);
				move_uploaded_file($_FILES["imagen"]["tmp_name"], "../Assets/img/products/" . $imagen);
			}
		}
		if (empty($idarticulo)) {
			if (empty($rspta['codigo'])) {
				$rspta = $product->insertar($idcategoria, $idsubcategoria, $idmedida, $idalmacen, $codigo, $nombre, $stock, $precio_compra, $precio_venta, $descripcion, $imagen);
				echo $rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos";
			} else {
				echo "No se puede registrar...! \n codigo de producto duplicado";
			}
		} else {
			$rspta = $product->editar($idarticulo, $idcategoria, $idsubcategoria, $idmedida, $idalmacen, $codigo, $nombre, $stock, $precio_compra, $precio_venta, $descripcion, $imagen);
			echo $rspta ? "Datos actualizados correctamente" : "No se pudo actualizar los datos";
		}

		break;


	case 'desactivar':
		$rspta = $product->desactivar($idarticulo);
		echo $rspta ? "Datos desactivados correctamente" : "No se pudo desactivar los datos";
		break;
	case 'activar':
		$rspta = $product->activar($idarticulo);
		echo $rspta ? "Datos activados correctamente" : "No se pudo activar los datos";
		break;

	case 'mostrar':
		$rspta = $product->mostrar($idarticulo);
		echo json_encode($rspta);
		break;

	case 'listar':
		$rspta = $product->listar();
		$data = array();

		foreach ($rspta as $reg) {
			$stockcolor = '';
			if ($reg['stock'] <= 10) {
				$stockcolor = '<button class="btn btn-danger btn-sm">' . $reg['stock'] . '</button>';
			} elseif ($reg['stock'] > 10 && $reg['stock'] < 30) {
				$stockcolor = '<button class="btn btn-warning btn-sm">' . $reg['stock'] . '</button>';
			} elseif ($reg['stock'] >= 30) {
				$stockcolor = '<button class="btn btn-success btn-sm">' . $reg['stock'] . '</button>';
			}

			$data[] = array(
				"0" => $reg['codigo'],
				"1" => $reg['nombre'] . '<br><span style="font-size:0.95em; color:#888;">(' . ($reg['almacen'] ?? 'Sin almacén') . ')</span>',
				"2" => $reg['categoria'],
				"3" => $reg['subcategoria'], // ← Añade esta columna
				"4" => $reg['medida'],
				"5" => $stockcolor,
				"6" => (!empty($reg['imagen']) ? "<img src='Assets/img/products/" . $reg['imagen'] . "' height='50px'>" : 'Sin imagen'),
				"7" => ($reg['precio_compra']) ? $reg['precio_compra'] : '<a href="buy"> <button class="btn btn-warning btn-sm"><i class="fas fa-plus"></i></button></a>',
				"8" => ($reg['precio_venta']) ? $reg['precio_venta'] : '<a href="buy"> <button class="btn btn-warning btn-sm"><i class="fas fa-plus"></i></button></a>',
				"9" => ($reg['condicion']) ? '<div class="badge badge-success">Activado</div>' : '<div class="badge badge-danger">Desactivado</div>',
				"10" => ($reg['condicion']) ?
					'<button class="btn btn-warning btn-sm" onclick="mostrar(' . $reg['idarticulo'] . ')"><i class="fas fa-pencil-alt"></i></button>' .
					' ' . '<button class="btn btn-danger btn-sm" onclick="desactivar(' . $reg['idarticulo'] . ')"><i class="fas fa-times"></i></button>'
					:
					'<button class="btn btn-warning btn-sm" onclick="mostrar(' . $reg['idarticulo'] . ')"><i class="fas fa-pencil-alt"></i></button>' .
					' ' . '<button class="btn btn-primary btn-sm" onclick="activar(' . $reg['idarticulo'] . ')"><i class="fas fa-check"></i></button>'
			);
		}
		$results = array(
			"sEcho" => 1, //info para datatables
			"iTotalRecords" => count($data), //enviamos el total de registros al datatable
			"iTotalDisplayRecords" => count($data), //enviamos el total de registros a visualizar
			"aaData" => $data
		);
		echo json_encode($results);
		break;

	case 'selectArticulo':
		$rspta = $product->select();
		echo '<option value="">Seleccione...</option>';
		foreach ($rspta as $reg) {
			echo '<option value="' . $reg['idarticulo'] . '">' . $reg['nombre'] . '</option>';
		}
		break;

	case 'listar_json':
		require_once "../Models/Product.php";
		$product = new Product();
		// Usa listarActivosVenta porque trae precios y más datos
		$rspta = $product->listarActivosVenta();
		// Devolver JSON
		echo json_encode($rspta);
		break;

	case 'subirMasivo':
		// Cambia 'archivo_csv' por 'archivo_productos'
		if (isset($_FILES['archivo_productos']) && $_FILES['archivo_productos']['error'] === UPLOAD_ERR_OK) {
			$nombreTmp = $_FILES['archivo_productos']['tmp_name'];
			require_once "../Models/Product.php";
			$product = new Product();

			$mensajes = $product->cargarMasivoDesdeCSV($nombreTmp);

			echo json_encode([
				"success" => true,
				"message" => implode("<br>", $mensajes)
			]);
		} else {
			echo json_encode([
				"success" => false,
				"message" => "No se recibió ningún archivo válido."
			]);
		}
		break;
}
