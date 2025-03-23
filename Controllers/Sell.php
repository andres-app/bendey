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
$tipo_pago = isset($_POST["tipo_pago"]) ? $_POST["tipo_pago"] : "";
$num_transac = isset($_POST["num_transac"]) ? $_POST["num_transac"] : "";



switch ($_GET["op"]) {
	case 'guardaryeditar':
		require_once "../Models/Person.php";
		$person = new Person();

		// Datos del cliente
		$tipo_documento = $_POST["tipo_documento"];
		$num_documento = $_POST["num_documento"];
		$nombre = $_POST["nombre"]; // Nombre del cliente
		$direccion = $_POST["direccion"] ?? ''; // Dirección si está disponible

		// Verificar si el cliente ya está registrado
		$clienteExistente = $person->mostrarPorDocumento($num_documento);

		if (!$clienteExistente) {
			// Si el cliente no existe, lo insertamos en la tabla 'persona'
			$idcliente = $person->insertar("Cliente", $nombre, $tipo_documento, $num_documento, $direccion, "", "");
			// echo "Cliente insertado, ID: " . $idcliente; // Depuración
		} else {
			// Si el cliente ya existe, obtenemos su id
			$idcliente = $clienteExistente['idpersona'];
			// echo "Cliente ya registrado, ID: " . $idcliente; // Depuración
		}

		// Verificar que el idcliente sea válido antes de registrar la venta
		if (!is_numeric($idcliente)) {
			echo "Error: ID del cliente no es válido. Valor recibido: " . $idcliente;
			exit;
		}

		// Calcular Subtotal, Impuesto e Importe Total antes de registrar la venta
		$total_venta = 0;
		$tasa_impuesto = 0.18; // 18% de IGV en Perú

		// Calculamos el total sumando los precios de venta multiplicados por la cantidad de cada artículo
		for ($i = 0; $i < count($_POST["idarticulo"]); $i++) {
			$cantidad = $_POST["cantidad"][$i];
			$precio_venta = $_POST["precio_venta"][$i];
			$total_venta += $cantidad * $precio_venta;
		}

		// Calcular el IGV sobre el total
		$igv = $total_venta * $tasa_impuesto;

		// El subtotal es la diferencia entre el total y el IGV
		$subtotal = $total_venta - $igv;

		// Mostramos los valores para verificar
// echo "Subtotal: S/ " . number_format($subtotal, 2, '.', '') . "<br>";
// echo "IGV (18%): S/ " . number_format($igv, 2, '.', '') . "<br>";
// echo "Total: S/ " . number_format($total_venta, 2, '.', '') . "<br>";



		// Registrar la venta con los cálculos actualizados
// Asegurar que impuesto tenga un valor numérico o NULL
		$impuesto = isset($impuesto) && $impuesto !== '' ? (float) $impuesto : null;

		// Registrar la venta con los cálculos actualizados
		$rspta = $sell->insertar(
			$idcliente,
			$idusuario,
			$tipo_comprobante,
			$serie_comprobante,
			$num_comprobante,
			$impuesto, // Ahora impuesto es NULL si está vacío
			$total_venta,
			$tipo_pago,
			$num_transac,
			$_POST["idingreso"],
			$_POST["idarticulo"],
			$_POST["cantidad"],
			$_POST["precio_compra"],
			$_POST["precio_venta"],
			$_POST["descuento"]
		);

		echo $rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos";
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
		}
		;
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
				"0" => '<a target="_blank" href="' . $base_url . $pdf_path . $reg['idventa'] . '"> 
                <button class="btn btn-primary btn-sm">
                    <i class="far fa-file-pdf"></i>
                </button>
            </a>' . ' ' .
					'<a target="_blank" href="' . $urlt . $reg['idventa'] . '"> 
                <button class="btn btn-success btn-sm">
                    <i class="fas fa-print"></i>
                </button>
            </a>' . ' ' .
					(($reg['estado'] == 'Aceptado') ?
						'<button class="btn btn-info btn-sm" onclick="mostrar(' . $reg['idventa'] . ')">
                    <i class="fas fa-eye"></i>
                </button>' . ' ' .
						'<a target="_blank" href="https://wa.me/?text=' . urlencode('Detalle de la venta: ' . $reg['idventa'] . ' - Ver PDF: ' . $base_url . $pdf_path . $reg['idventa']) . '"> 
                    <button class="btn btn-success btn-sm">
                        <i class="fab fa-whatsapp"></i>
                    </button>
                </a>' . ' ' .
						'<a href="editsale?op=new&id=' . $reg['idventa'] . '"> 
                    <button class="btn btn-warning btn-sm">
                        <i class="fas fa-pen"></i>
                    </button>
                </a>' . ' ' .
						'<button class="btn btn-danger btn-sm" onclick="anular(' . $reg['idventa'] . ')">
                    <i class="fas fa-times"></i>
                </button>' :
						'<button class="btn btn-info btn-sm" onclick="mostrar(' . $reg['idventa'] . ')">
                    <i class="fas fa-eye"></i>
                </button>'
					),
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
			"sEcho" => 1,//info para datatables
			"iTotalRecords" => count($data),//enviamos el total de registros al datatable
			"iTotalDisplayRecords" => count($data),//enviamos el total de registros a visualizar
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
			$btncolor = '';
			if ($reg['stock'] <= 10) {
				$btncolor = '<button class="btn btn-danger btn-sm">' . $reg['stock'] . '</button>';
			} elseif ($reg['stock'] > 10 && $reg['stock'] < 30) {
				$btncolor = '<button class="btn btn-warning btn-sm">' . $reg['stock'] . '</button>';
			} elseif ($reg['stock'] >= 30) {
				$btncolor = '<button class="btn btn-success btn-sm">' . $reg['stock'] . '</button>';
			}
			$data[] = array(
				"0" => '<button class="btn btn-success btn-sm" id="addetalle" name="' . $reg['idarticulo'] . '" onclick="agregarDetalle(' . $reg['idingreso'] . ',' . $reg['idarticulo'] . ',\'' . $reg['nombre'] . '\',' . $reg['precio_compra'] . ',' . $reg['precio_venta'] . ',' . $reg['stock'] . ',' . $op . ')"><span class="fa fa-plus"></span> Añadir</button>',
				"1" => $reg['nombre'],
				"2" => $reg['codigo'],
				"3" => $btncolor,
				"4" => "<img src='Assets/img/products/" . $reg['imagen'] . "' height='40px' width='40px'>"

			);
		}

		$results = array(
			"sEcho" => 1,//info para datatables
			"iTotalRecords" => count($data),//enviamos el total de registros al datatable
			"iTotalDisplayRecords" => count($data),//enviamos el total de registros a visualizar
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


}
?>