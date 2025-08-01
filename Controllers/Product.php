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
$precio_venta = isset($_POST["precio_venta"]) ? $_POST["precio_venta"] : null;
$descripcion = isset($_POST["descripcion"]) ? $_POST["descripcion"] : "";
$imagen = isset($_POST["imagen"]) ? $_POST["imagen"] : "";

switch ($_GET["op"]) {
	case 'guardaryeditar':

		$rspta = $product->verificarCodigo($codigo);

		if (empty($idarticulo)) {
			if (empty($rspta['codigo'])) {

				// ⚠️ Generar código automático si está vacío
				if (empty($codigo)) {
					$codigo = 'VAR-' . uniqid();
				}

				// ✅ Subir imagen si existe
				if (!file_exists($_FILES['imagen']['tmp_name']) || !is_uploaded_file($_FILES['imagen']['tmp_name'])) {
					$imagen = empty($_POST["imagenactual"]) ? 'default.png' : $_POST["imagenactual"];
				} else {
					if (!empty($_POST["imagenactual"]) && $_POST["imagenactual"] != 'default.png') {
						unlink("../Assets/img/products/" . $_POST["imagenactual"]);
					}
					$ext = explode(".", $_FILES["imagen"]["name"]);
					if (in_array($_FILES['imagen']['type'], ["image/jpg", "image/jpeg", "image/png"])) {
						$imagen = round(microtime(true)) . '.' . end($ext);
						move_uploaded_file($_FILES["imagen"]["tmp_name"], "../Assets/img/products/" . $imagen);
					}
				}

				// ✅ Insertar producto principal
				$idproducto = $product->insertar(
					$idcategoria,
					$idsubcategoria,
					$idmedida,
					$idalmacen,
					$codigo,
					$nombre,
					$stock,
					$precio_compra,
					$precio_venta,
					$descripcion,
					$imagen
				);

				// ✅ Insertar variaciones
				if (isset($_POST['variaciones_json'])) {
					$variaciones = json_decode($_POST['variaciones_json'], true);

					foreach ($variaciones as $var) {
						$combinacion = $var['combinacion'] ?? '';
						$sku = $var['sku'] ?? '';
						$stock_var = $var['stock'] ?? 0;
						$precio_compra_var = $var['precio_compra'] ?? 0;
						$precio_venta_var = $var['precio_venta'] ?? 0;

						$product->insertarVariacion($idproducto, $combinacion, $sku, $stock_var, $precio_compra_var, $precio_venta_var);
					}
				}

				echo $idproducto ? "Datos registrados correctamente" : "No se pudo registrar los datos";
			} else {
				echo "No se puede registrar...! \n código de producto duplicado";
			}
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
				"9" => ($reg['condicion']) ? '<div class="badge badge-success">Aceptado</div>' : '<div class="badge badge-danger">Desactivado</div>',
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
		if (isset($_FILES['archivo_productos']) && $_FILES['archivo_productos']['error'] === UPLOAD_ERR_OK) {
			$nombreTmp = $_FILES['archivo_productos']['tmp_name'];
			require_once "../Models/Product.php";
			$product = new Product();

			$resultados = $product->cargarMasivoDesdeCSV($nombreTmp);

			echo json_encode([
				"success" => true,
				"exitosos" => $resultados['exitosos'] ?? [],
				"errores" => $resultados['errores'] ?? []
			]);
		} else {
			echo json_encode([
				"success" => false,
				"mensaje" => "No se recibió ningún archivo válido."
			]);
		}
		break;

	case 'listar_json_todo':
		$productosSimples = $product->listarActivosVenta();
		// Solo manda productos simples/padres, NO las variaciones aquí
		echo json_encode($productosSimples);
		break;

	case 'variaciones_por_articulo':
		if (isset($_POST["idarticulo"])) {
			$id = $_POST["idarticulo"];
			$variaciones = $product->listarVariacionesPorArticulo($id);
			echo json_encode($variaciones);
		} else {
			echo json_encode([]);
		}
		break;
}
