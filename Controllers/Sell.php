<?php
require_once "../Models/Sell.php";
if (strlen(session_id()) < 1)
	session_start();

$sell = new Sell();

$idventa = isset($_POST["idventa"]) ? $_POST["idventa"] : "";
$idcliente = isset($_POST["idcliente"]) ? $_POST["idcliente"] : "";
$idusuario = $_SESSION["idusuario"];
$tipo_comprobante = isset($_POST["tipo_comprobante"]) ? $_POST["tipo_comprobante"] : "";
$serie_comprobante = isset($_POST["serie_comprobante"]) ? $_POST["serie_comprobante"] : "";
$num_comprobante = isset($_POST["num_comprobante"]) ? $_POST["num_comprobante"] : "";
$impuesto = isset($_POST["impuesto"]) ? $_POST["impuesto"] : "";
$total_venta = isset($_POST["total_venta"]) ? $_POST["total_venta"] : "";
$idforma_pago = $_POST['idforma_pago'] ?? null;

// Obtener nombre de forma de pago
$tipo_pago = null;

if ($idforma_pago) {
	$fp = $sell->getConexion()->getData(
		"SELECT nombre FROM forma_pago WHERE idforma_pago = ?",
		[$idforma_pago]
	);

	if ($fp) {
		$tipo_pago = $fp['nombre'];
	}
}

$num_transac = isset($_POST["num_transac"]) ? $_POST["num_transac"] : "";



switch ($_GET["op"]) {
	case 'guardaryeditar':

		require_once "../Models/Person.php";
		require_once "../Models/Voucher.php";

		$person  = new Person();
		$voucher = new Voucher();

		try {

			// ======================================
			// 🔐 INICIAR TRANSACCIÓN (CORREGIDO)
			// ======================================
			$sell->getConexion()->beginTransaction();

			// ======================================
			// 1) CLIENTE (CORREGIDO)
			// ======================================
			$idcliente = $_POST["idcliente"] ?? '';

			if (empty($idcliente)) {

				// Solo si NO se seleccionó cliente
				$tipo_documento = $_POST["tipo_documento"] ?? '';
				$num_documento  = $_POST["num_documento"] ?? '';
				$nombre_cli     = $_POST["nombre_cli"] ?? '';
				$direccion      = $_POST["direccion"] ?? '';

				$cliente = $person->mostrarPorDocumento($num_documento);

				if (!$cliente) {
					$idcliente = $person->insertar(
						"Cliente",
						$nombre_cli,
						$tipo_documento,
						$num_documento,
						$direccion,
						"",
						""
					);
				} else {
					$idcliente = $cliente['idpersona'];
				}
			}

			if (!is_numeric($idcliente) || $idcliente <= 0) {
				throw new Exception("No se pudo determinar el cliente de la venta");
			}


			// ======================================
			// 2) VALIDAR PRODUCTOS
			// ======================================
			if (
				!isset($_POST["idarticulo"]) ||
				!is_array($_POST["idarticulo"]) ||
				count($_POST["idarticulo"]) === 0
			) {
				throw new Exception("Debe agregar al menos un producto antes de procesar la venta.");
			}

			// ======================================
			// 3) CALCULAR TOTAL
			// ======================================
			$total_venta = 0;

			for ($i = 0; $i < count($_POST["idarticulo"]); $i++) {
				$cantidad     = (float) ($_POST["cantidad"][$i] ?? 0);
				$precio_venta = (float) ($_POST["precio_venta"][$i] ?? 0);
				$total_venta += $cantidad * $precio_venta;
			}

			// ======================================
			// 🔐 4) OBTENER CORRELATIVO BLOQUEADO
			// ======================================
			$corr = $voucher->obtenerCorrelativoBloqueado($tipo_comprobante);

			if (!$corr) {
				throw new Exception("No existe correlativo activo para el comprobante.");
			}

			$serie_comprobante = $corr['serie']; // B001 / F001
			$num_comprobante   = str_pad(
				$corr['num_comprobante'] + 1,
				7,
				"0",
				STR_PAD_LEFT
			);

			// Evidencia técnica
			error_log("[VENTA] {$tipo_comprobante} {$serie_comprobante}-{$num_comprobante}");

			// ======================================
			// 5) INSERTAR VENTA
			// ======================================
			$idventa = $sell->insertar(
				$idcliente,
				$idusuario,
				$tipo_comprobante,
				$serie_comprobante,
				$num_comprobante,
				null,
				$total_venta,
				$tipo_pago,      // texto (Efectivo / Mixto)
				$num_transac,
				$idforma_pago,   // 👈 NUEVO
				$_POST["idingreso"],
				$_POST["idarticulo"],
				$_POST["cantidad"],
				$_POST["precio_compra"],
				$_POST["precio_venta"],
				$_POST["descuento"],
			);

			if (!$idventa) {
				throw new Exception("Error al registrar la venta.");
			}

			// ======================================
			// 6) REGISTRAR PAGOS (NORMAL / MIXTO)
			// ======================================
			if (!empty($_POST['pagos']) && is_array($_POST['pagos'])) {

				foreach ($_POST['pagos'] as $pago) {

					if (empty($pago['metodo']) || empty($pago['monto'])) {
						continue;
					}

					// Obtener ID de forma de pago por nombre
					$sqlFp = "SELECT idforma_pago FROM forma_pago WHERE nombre = ?";
					$fp = $sell->getConexion()->getData($sqlFp, [$pago['metodo']]);

					if (!$fp) {
						throw new Exception("Forma de pago inválida: " . $pago['metodo']);
					}

					$sqlPago = "
            INSERT INTO venta_pago (idventa, idforma_pago, monto)
            VALUES (?, ?, ?)
        ";

					$sell->getConexion()->setData($sqlPago, [
						$idventa,
						$fp['idforma_pago'],
						$pago['monto']
					]);
				}
			} else {

				// ======================================
				// PAGO NORMAL (1 SOLO MÉTODO)
				// ======================================
				$sqlFp = "SELECT idforma_pago FROM forma_pago WHERE nombre = ?";
				$fp = $sell->getConexion()->getData($sqlFp, [$tipo_pago]);

				if (!$fp) {
					throw new Exception("Forma de pago inválida");
				}

				$sell->getConexion()->setData(
					"INSERT INTO venta_pago (idventa, idforma_pago, monto)
         VALUES (?, ?, ?)",
					[$idventa, $fp['idforma_pago'], $total_venta]
				);
			}


			// ======================================
			// 🔄 6) ACTUALIZAR CORRELATIVO
			// ======================================
			$voucher->actualizarCorrelativoPorId(
				$corr['id_comp_pago'],
				$num_comprobante
			);

			// ======================================
			// ✅ COMMIT (CORREGIDO)
			// ======================================
			$sell->getConexion()->commit();

			echo json_encode([
				"success" => true,
				"idventa" => $idventa,
				"mensaje" => "Venta registrada correctamente"
			]);
		} catch (Exception $e) {

			// ❌ ROLLBACK (CORREGIDO)
			$sell->getConexion()->rollBack();

			echo json_encode([
				"success" => false,
				"mensaje" => $e->getMessage()
			]);
		}

		break;



	case 'anular':
		$rspta = $sell->anular($idventa);
		echo $rspta ? "Ingreso anulado correctamente" : "No se pudo anular el ingreso";
		break;

	case 'mostrar':
		$rspta = $sell->mostrar($idventa);
		echo json_encode($rspta);
		//echo $rspta;
		break;

	case 'pagos':
		$rspta = $sell->obtenerPagosVenta($_GET['idventa']);
		echo json_encode($rspta);
		break;



	//_______________________________________________________________________________________________________
	//opcion para mostrar la numeracion y la serie_comprobante de la factura
	case 'mostrar_numero':

		//mostrando el numero de factura de la tabla comprobantes
		require_once "../Models/Voucher.php";
		$comprobantes = new Voucher();
		//$tipo_comprobante='Factura';
		$tipo_comprobante = $_REQUEST["tipo_comprobante"];
		$rspta = $comprobantes->mostrar_numero($tipo_comprobante);
		foreach ($rspta as $reg) {
			$numero_comp = (int) $reg['num_comprobante'];
		}

		$numero_venta = $numero_comp;

		//mostramos el numero de comprobante de la tabla ventas
		$rspta = $sell->numero_venta($tipo_comprobante);
		foreach ($rspta as $regv) {
			$numero_venta = (int) $regv['num_comprobante'];
		}

		$new_numero = '';

		//validamos si el numero de comprobante de la sell ya llego al limite para ir a la siguiente numeracion
		if ($numero_venta == 9999999 or empty($numero_venta)) {
			(int) $new_numero = '0000001';
			echo json_encode($new_numero);
		} elseif ($numero_venta == 9999999) {
			(int) $new_numero = '0000001';
			echo json_encode($new_numero);
		} else {
			$suma_numero = $numero_venta + 1;
			echo json_encode($suma_numero);
		}

		break;

	case 'mostrar_serie':

		//mostrando el numero de factura de la tabla comprobantes
		require_once "../Models/Voucher.php";
		$comprobantes = new Voucher();
		//$tipo_comprobante='Factura';
		$tipo_comprobante = $_REQUEST["tipo_comprobante"];
		$rspta = $comprobantes->mostrar_serie($tipo_comprobante);
		foreach ($rspta as $reg) {
			$serie_comp = $reg['serie_comprobante'];
			$num_comp = $reg['num_comprobante'];
			$letra_s = $reg['letra_serie'];
		}
		$serie_com_comp = (int) $serie_comp;
		$num_com_comp = (int) $num_comp;

		//mostramos la serie de comprobante de la tabla ventas
		$rsptav = $sell->numero_serie($tipo_comprobante);
		$numeros = $serie_com_comp;
		$numeroco = $num_com_comp;

		foreach ($rsptav as $regv) {
			$numeros = $regv['serie_comprobante'];
			$numeroco = $regv['num_comprobante'];
		}
		$ns = substr($numeros, -3);
		$nums = (int) $ns;
		$nuew_serie = 0;
		$numc = (int) $numeroco;
		if ($numc == 9999999 or empty($numeroco)) {
			$nuew_serie = $nums + 1;
			$serie = array(
				"letra" => $letra_s,
				"serie" => $nuew_serie
			);
			echo json_encode($serie);
		} else {
			$serie = array(
				"letra" => $letra_s,
				"serie" => $nums
			);
			echo json_encode($serie);
		}
		break;
	//opcion para mostrar la numeracion y la serie_comprobante de la boleta

	//______________________________________________________________________________________________

	case 'listarDetalle':
		require_once "../Models/Company.php";
		$cnegocio = new Company();
		$rsptan = $cnegocio->listar();

		if (empty($rsptan)) {
			$smoneda = 'Simbolo de moneda';
		} else {
			$smoneda = $rsptan[0]['simbolo'];
			$nom_imp = $rsptan[0]['nombre_impuesto'];
		}

		// Recibimos el idventa
		$id = $_GET['id'];

		$rspta = $sell->listarDetalle($id);
		$total_venta = 0;

		echo ' <thead style="background-color:#A9D0F5">
			<th>Opciones</th>
			<th>Articulo</th>
			<th>Cantidad</th>
			<th>Precio Venta</th>
			<th>Descuento</th>
			<th>Total</th>
		   </thead>';

		foreach ($rspta as $reg) {
			// Cálculo del total por artículo
			$total_articulo = $reg['precio_venta'] * $reg['cantidad'] - $reg['descuento'];
			$total_venta += $total_articulo; // Sumamos el total de cada artículo al total de la venta

			echo '<tr class="filas">
				<td></td>
				<td>' . $reg['nombre'] . '</td>
				<td>' . $reg['cantidad'] . '</td>
				<td>' . number_format($reg['precio_venta'], 2) . '</td>
				<td>' . number_format($reg['descuento'], 2) . '</td>
				<td>' . number_format($total_articulo, 2) . '</td>
			  </tr>';
		}

		// Cálculo del IGV y el subtotal
		$igv = round($total_venta * 0.18, 2); // IGV es el 18% del total de la venta
		$subtotal = round($total_venta - $igv, 2); // Subtotal es la diferencia entre el total y el IGV

		// Mostramos el pie de la tabla
		echo '<tfoot>
			<th><span>SubTotal</span><br><span id="valor_impuestoc">' . $nom_imp . ' 18%</span><br><span>TOTAL</span></th>
			<th></th>
			<th></th>
			<th></th>
			<th></th>
			<th>
				<span class="pull-right" id="total">' . $smoneda . ' ' . number_format((float) $subtotal, 2, '.', '') . '</span><br>
				<span class="pull-right" id="most_imp">' . $smoneda . ' ' . number_format((float) $igv, 2, '.', '') . '</span><br>
				<span class="pull-right" id="most_total" maxlength="4">' . $smoneda . ' ' . number_format((float) $total_venta, 2, '.', '') . '</span>
			</th>
		</tfoot>';

		break;


	case 'listarDetalle_editar':
		require_once "../Models/Company.php";
		$cnegocio = new Company();
		$rsptan = $cnegocio->listar();
		$regn = $rsptan[0];
		if (empty($regn)) {
			$smoneda = 'Simbolo de moneda';
		} else {
			$smoneda = $regn['simbolo'];
			$nom_imp = $regn['nombre_impuesto'];
		};
		//recibimos el idventa
		$id = $_GET['id'];

		$rspta = $sell->listarDetalle($id);
		$total = 0;
		$data = array();

		foreach ($rspta as $reg) {
			$data[] = array(
				"Idingreso" => $reg['idarticulo'],
				"Idarticulo" => $reg['idarticulo'],
				"Articulo" => $reg['nombre'],
				"Pcompra" => $reg['precio_compra'],
				"Pventa" => $reg['precio_venta'],
				"Cantidad" => $reg['cantidad'],
				"Stock" => $reg['stock'],
			);
		}
		$results = array(
			"Datos" => $data
		);
		echo json_encode($data);
		break;

	case 'listar':
		$rspta = $sell->listar();
		$data = array();

		foreach ($rspta as $reg) {

			$urlt = 'Reports/80mm.php?id=';
			$url = 'Reports/a4.php?id=';
			$url = 'Reports/58mm.php?id=';

			// Obtener la URL base dinámica
			$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
			$host = $_SERVER['HTTP_HOST'];

			// Obtener el directorio base del proyecto
			$project_root = rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/\\') . '/';

			// Construir la URL base completa sin Controllers
			$base_url = $protocol . $host . $project_root;

			// Ajusta la ruta al PDF para reflejar la estructura correcta de tu servidor
			$pdf_path = 'Reports/a4.php?id='; // Ruta pública relativa desde el raíz del proyecto

			$data[] = array(
				"0" => '
					<div class="btn-group">
						<button class="btn btn-info btn-sm" title="Ver" onclick="mostrar(' . $reg['idventa'] . ')">
							<i class="fas fa-eye"></i>
						</button>
						<button class="btn btn-success btn-sm" title="Imprimir Ticket" onclick="window.open(\'' . $base_url . 'Reports/80mm.php?id=' . $reg['idventa'] . '\', \'_blank\')">
							<i class="fas fa-print"></i>
						</button>
						<button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Más">
							<span>...</span>
						</button>
						<div class="dropdown-menu">
							<a class="dropdown-item" href="' . $base_url . 'Reports/a4.php?id=' . $reg['idventa'] . '" target="_blank">
								<i class="far fa-file-pdf"></i> Imprimir A4
							</a>
							<a class="dropdown-item" href="https://wa.me/?text=' . urlencode('Detalle de la venta: ' . $reg['idventa'] . ' - Ver PDF: ' . $base_url . 'Reports/a4.php?id=' . $reg['idventa']) . '" target="_blank">
								<i class="fab fa-whatsapp"></i> WhatsApp
							</a>
							' . (($reg['estado'] == 'Aceptado') ? '
							<a class="dropdown-item text-danger" href="#" onclick="anular(' . $reg['idventa'] . ')">
								<i class="fas fa-times"></i> Anular
							</a>
							' : '') . '
						</div>
					</div>
				',
				"1" => $reg['fecha'],
				"2" => $reg['cliente'],
				"3" => $reg['usuario'],
				"4" => $reg['tipo_comprobante'],
				"5" => $reg['serie_comprobante'] . '-' . $reg['num_comprobante'],
				"6" => $reg['total_venta'],
				"7" => ($reg['estado'] == 'Aceptado') ?
					'<div class="badge badge-success">Aceptado</div>' :
					'<div class="badge badge-danger">Anulado</div>'
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


	case 'selectCliente':
		require_once "../Models/Person.php";
		$persona = new Person();

		$rspta = $persona->listarc();
		echo '<option value="">seleccione...</option>';
		foreach ($rspta as $reg) {

			echo '<option value=' . $reg['idpersona'] . '>' . $reg['nombre'] . '</option>';
		}
		break;

	case 'cantidad_articulos':
		require_once "../Models/Product.php";
		$articulo = new Product();
		$rsptav = $articulo->cantidadarticulos();

		echo json_encode($rsptav);
		break;

	case 'listarArticulos':
		require_once "../Models/Product.php";
		$articulo = new Product();

		$rspta = $articulo->listarActivosVenta();
		$data = array();
		$op = 1;

		foreach ($rspta as $reg) {
			// Valores seguros por defecto
			$idingreso = isset($reg['idingreso']) ? $reg['idingreso'] : 0;
			$precio_compra = isset($reg['precio_compra']) ? $reg['precio_compra'] : 0;
			$precio_venta = isset($reg['precio_venta']) ? $reg['precio_venta'] : 0;
			$stock = isset($reg['stock']) ? $reg['stock'] : 0;
			$nombre = isset($reg['nombre']) ? addslashes($reg['nombre']) : 'Sin nombre';

			$btncolor = '';
			if ($stock <= 10) {
				$btncolor = '<button class="btn btn-danger btn-sm">' . $stock . '</button>';
			} elseif ($stock > 10 && $stock < 30) {
				$btncolor = '<button class="btn btn-warning btn-sm">' . $stock . '</button>';
			} elseif ($stock >= 30) {
				$btncolor = '<button class="btn btn-success btn-sm">' . $stock . '</button>';
			}

			$data[] = array(
				"0" => '<button class="btn btn-success btn-sm" id="addetalle" name="' . $reg['idarticulo'] . '" onclick="agregarDetalle(' . $idingreso . ',' . $reg['idarticulo'] . ',\'' . $nombre . '\',' . $precio_compra . ',' . $precio_venta . ',' . $stock . ',' . $op . ')"><span class="fa fa-plus"></span> Añadir</button>',
				"1" => $reg['nombre'] . '<br><span style="font-size:0.95em; color:#888;">(' . ($reg['almacen'] ?? 'Sin almacén') . ')</span>',
				"2" => $reg['codigo'],
				"3" => $btncolor,
				"4" => "<img src='Assets/img/products/" . $reg['imagen'] . "' height='40px' width='40px'>"
			);
		}

		$results = array(
			"sEcho" => 1,
			"iTotalRecords" => count($data),
			"iTotalDisplayRecords" => count($data),
			"aaData" => $data
		);
		echo json_encode($results);
		break;

	case 'selectComprobante':
		require_once "../Models/Voucher.php";
		$comprobantes = new Voucher();

		$rspta = $comprobantes->select();
		echo '<option value="">Seleccione...</option>';
		foreach ($rspta as $reg) {
			echo '<option value="' . $reg['nombre'] . '">' . $reg['nombre'] . '</option>';
		}
		break;

	case 'selectTipopago':
		require_once "../Models/Paymentstype.php";
		$tipopago = new Paymentstype();

		$rspta = $tipopago->select();
		foreach ($rspta as $reg) {
			echo '<option value="' . $reg['nombre'] . '">' . $reg['nombre'] . '</option>';
		}
		break;

	case 'listarCategorias':
		require_once "../Models/Product.php";
		$product = new Product();
		$categorias = $product->listarCategoriasActivas();
		echo json_encode($categorias);
		break;

	case 'listarArticulosPorCategoria':
		require_once "../Models/Product.php";
		$product = new Product();
		$idcategoria = $_GET['idcategoria'];
		$articulos = $product->listarActivosVentaPorCategoria($idcategoria);
		echo json_encode($articulos);
		break;

	case 'listarArticulosModal':
		require_once "../Models/Product.php";
		$product = new Product();
		$rspta = $product->listarActivosVenta();
		echo json_encode($rspta);
		break;

	case 'selectFormaPago':
		require_once __DIR__ . '/../Models/FormaPago.php';

		$formaPago = new FormaPago();
		$rspta = $formaPago->select();

		echo '<option value="">Seleccione</option>';

		foreach ($rspta as $r) {
			echo '<option value="' . $r['idforma_pago'] . '"
							 data-nombre="' . $r['nombre'] . '"
							 data-efectivo="' . $r['es_efectivo'] . '">
						' . $r['nombre'] . '
					  </option>';
		}
		break;
}
