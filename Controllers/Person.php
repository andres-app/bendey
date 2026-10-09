<?php
require_once "../Models/Person.php";

$person = new Person();

$idpersona = isset($_POST["idpersona"]) ? $_POST["idpersona"] : "";
$tipo_persona = isset($_POST["tipo_persona"]) ? $_POST["tipo_persona"] : "";
$nombre = isset($_POST["nombre"]) ? $_POST["nombre"] : "";
$tipo_documento = isset($_POST["tipo_documento"]) ? $_POST["tipo_documento"] : "";
$num_documento = isset($_POST["num_documento"]) ? $_POST["num_documento"] : "";
$direccion = isset($_POST["direccion"]) ? $_POST["direccion"] : "";
$telefono = isset($_POST["telefono"]) ? $_POST["telefono"] : "";
$email = isset($_POST["email"]) ? $_POST["email"] : "";
$es_preferencial = ($tipo_persona === "Cliente" && (int)($_POST["es_preferencial"] ?? 0) === 1) ? 1 : 0;

switch ($_GET["op"]) {
	case 'guardaryeditar':
		if (empty($idpersona)) {
			$rspta = $person->insertar($tipo_persona, $nombre, $tipo_documento, $num_documento, $direccion, $telefono, $email, $es_preferencial);
			echo $rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos";
		} else {
			$rspta = $person->editar($idpersona, $tipo_persona, $nombre, $tipo_documento, $num_documento, $direccion, $telefono, $email, $es_preferencial);
			echo $rspta ? "Datos actualizados correctamente" : "No se pudo actualizar los datos";
		}
		break;

	case 'actualizarDireccionClientePos':
		header('Content-Type: application/json; charset=utf-8');
		$idpersonaPos = isset($_POST['idpersona']) ? (int)$_POST['idpersona'] : 0;
		$direccionPos = isset($_POST['direccion']) ? trim((string)$_POST['direccion']) : '';

		if ($idpersonaPos <= 0 || $direccionPos === '') {
			http_response_code(422);
			echo json_encode([
				'estado' => false,
				'mensaje' => 'Cliente o dirección inválidos.'
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			break;
		}

		$rspta = $person->actualizarDireccionCliente($idpersonaPos, $direccionPos);
		echo json_encode([
			'estado' => (bool)$rspta,
			'mensaje' => $rspta ? 'Dirección actualizada correctamente.' : 'No se pudo actualizar la dirección del cliente.'
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		break;

	case 'eliminar':
		$rspta = $person->eliminar($idpersona);
		echo $rspta ? "Datos eliminados correctamente" : "No se pudo eliminar los datos";
		break;

	case 'mostrar':
		$rspta = $person->mostrar($idpersona);
		echo json_encode($rspta);
		break;

	case 'listarp':
		$rspta = $person->listarp();
		$data = array();
		$escape = static function ($valor) {
			return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
		};

		foreach ($rspta as $reg) {
			$idpersonaSeguro = (int)($reg['idpersona'] ?? 0);
			$tipoDocumentoSeguro = $escape($reg['tipo_documento'] ?? '');
			$numeroDocumentoSeguro = $escape($reg['num_documento'] ?? '');
			$direccionSegura = $escape($reg['direccion'] ?? '');

			if ($tipoDocumentoSeguro !== '' || $numeroDocumentoSeguro !== '') {
				$documentoHtml = '<span class="supplier-document-cell">' .
					($tipoDocumentoSeguro !== '' ? '<span class="supplier-document-type">' . $tipoDocumentoSeguro . '</span>' : '') .
					($tipoDocumentoSeguro !== '' && $numeroDocumentoSeguro !== '' ? '<span class="supplier-document-separator"> | </span>' : '') .
					($numeroDocumentoSeguro !== '' ? '<span class="supplier-document-number">' . $numeroDocumentoSeguro . '</span>' : '') .
				'</span>';
			} else {
				$documentoHtml = '<span class="supplier-empty-value">Sin documento</span>';
			}

			$direccionHtml = $direccionSegura !== ''
				? '<span class="supplier-address-cell"><i class="fas fa-map-marker-alt" aria-hidden="true"></i><span>' . $direccionSegura . '</span></span>'
				: '<span class="supplier-empty-value">Sin dirección</span>';

			$data[] = array(
				"0" => '<button type="button" class="supplier-table-action supplier-table-action-edit" onclick="mostrar(' . $idpersonaSeguro . ')" title="Editar proveedor" aria-label="Editar proveedor"><i class="fas fa-pencil-alt"></i></button>' .
					'<button type="button" class="supplier-table-action supplier-table-action-delete" onclick="eliminar(' . $idpersonaSeguro . ')" title="Eliminar proveedor" aria-label="Eliminar proveedor"><i class="fas fa-trash-alt"></i></button>',
				"1" => $escape($reg['nombre'] ?? ''),
				"2" => $documentoHtml,
				"3" => $direccionHtml,
				"4" => $escape($reg['telefono'] ?? ''),
				"5" => $escape($reg['email'] ?? '')
			);
		}

		$results = array(
			"sEcho" => 1,
			"iTotalRecords" => count($data),
			"iTotalDisplayRecords" => count($data),
			"aaData" => $data
		);

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		break;

	case 'listarc':
        $rspta = $person->listarc();
        $data = [];
        $esc = static function ($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
        foreach ($rspta as $reg) {
            $id = (int)$reg['idpersona'];
            $nombre = $esc($reg['nombre']);
            $data[] = [
                '0' => '<div class="tiq-action-group"><button type="button" class="tiq-action" aria-label="Editar cliente" title="Editar cliente" onclick="tiqEditarCliente('.$id.')"><svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></button><button type="button" class="tiq-action tiq-action-price" aria-label="Precios del cliente" title="Precios del cliente" onclick="tiqAbrirPreciosCliente('.$id.')"><svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4 12.4 21.6a2 2 0 0 1-2.8 0l-7.2-7.2a2 2 0 0 1-.6-1.4V4a2 2 0 0 1 2-2h9a2 2 0 0 1 1.4.6l6.4 6.4a3 3 0 0 1 0 4.4Z"/><circle cx="7.5" cy="7.5" r="1"/></svg></button></div>',
                '1' => $nombre,
                '2' => $esc($reg['tipo_documento']),
                '3' => $esc($reg['num_documento']),
                '4' => $esc($reg['telefono']),
                '5' => $esc($reg['email']),
                '6' => (int)($reg['es_preferencial'] ?? 0) === 1 ? '<span class="badge badge-success">Preferencial</span>' : 'Regular'
            ];
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['sEcho'=>1,'iTotalRecords'=>count($data),'iTotalDisplayRecords'=>count($data),'aaData'=>$data], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        break;

	case 'selectProveedor':
		$rspta = $person->selectp();
		echo '<option value="">Seleccione...</option>';
		foreach ($rspta as $reg) {
			echo '<option value="' . $reg['idpersona'] . '">' . $reg['nombre'] . '</option>';
		}
		break;

	case 'selectCliente':
		$rspta = $person->selectc();
		echo '<option value="">Seleccione...</option>';
		foreach ($rspta as $reg) {
			echo '<option value="' . $reg['idpersona'] . '">' . $reg['nombre'] . '</option>';
		}
		break;

	case 'getCustomerInfo':
		if (empty($_POST["tipo_documento"]) || empty($_POST["num_documento"])) {
			echo json_encode(["estado" => false, "mensaje" => "Datos incompletos"]);
			exit;
		}
		$tipo_documento = $_POST["tipo_documento"];
		$num_documento = $_POST["num_documento"];
		if (strlen($num_documento) < 8) { // O la longitud que consideres válida para DNI/RUC
			echo json_encode(["estado" => false, "mensaje" => "Documento inválido"]);
			exit;
		}
		$respuesta = $person->getCustomerInfo($num_documento, $tipo_documento);
		echo $respuesta;
		break;


	case 'getCustomerByDocument':
		if (!isset($_POST["num_documento"])) {
			echo json_encode(["estado" => false, "mensaje" => "Falta el número de documento"]);
			exit;
		}
		$num_documento = $_POST["num_documento"];
		$cliente = $person->mostrarPorDocumento($num_documento);

		if ($cliente && !empty($cliente['nombre'])) {
			echo json_encode(['estado' => true, 'resultado' => $cliente]);
		} else {
			echo json_encode(['estado' => false]);
		}
		break;




}
