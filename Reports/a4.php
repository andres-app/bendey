<?php
// activamos almacenamiento en el buffer
ob_start();
if (strlen(session_id()) < 1) {
    session_start();
}

if (!isset($_SESSION['nombre'])) {
    echo "Debe ingresar al sistema correctamente para visualizar el reporte";
    exit;
} else {
    if ($_SESSION['ventas'] == 1) {

        // incluimos el archivo factura
        require('Factura.php');

        // datos de la empresa
        require_once "../Models/Company.php";
        $cnegocio = new Company();
        $rsptan = $cnegocio->listar();

        if (!$rsptan || empty($rsptan)) {
            echo "Error al obtener los datos de la empresa";
            exit;
        }

        // Aseguramos que cada campo esté definido y tenga un valor por defecto si no existe
        $empresa = isset($rsptan[0]['nombre']) ? $rsptan[0]['nombre'] : 'Nombre no definido';
        $ndocumento = isset($rsptan[0]['ndocumento']) ? $rsptan[0]['ndocumento'] : 'N/D';
        $documento = isset($rsptan[0]['documento']) ? $rsptan[0]['documento'] : 'Documento no disponible';
        $direccion = isset($rsptan[0]['direccion']) ? $rsptan[0]['direccion'] : 'Dirección no disponible';
        $telefono = isset($rsptan[0]['telefono']) ? $rsptan[0]['telefono'] : 'Teléfono no disponible';
        $email = isset($rsptan[0]['email']) ? $rsptan[0]['email'] : 'Email no disponible';

        // Verificar si el logo existe, de lo contrario usar un logo por defecto
        $logoe = "../Assets/img/company/" . $rsptan[0]['logo'];
        $logo_default = "../Assets/img/company/default_logo.png";

        if (!file_exists($logoe)) {
            $logoe = $logo_default;
        }

        // obtenemos los datos de la cabecera de la venta actual
        require_once "../Models/Sell.php";
        $venta = new Sell();
        $rsptav = $venta->ventacabecera($_GET["id"]);

        if (!$rsptav || empty($rsptav)) {
            echo "Error al obtener los datos de la venta";
            exit;
        }

        // recorremos todos los valores que obtengamos
        $regv = $rsptav[0];

        // Proporcionamos valores por defecto si están vacíos
        $cliente = !empty($regv['cliente']) ? $regv['cliente'] : '--';
        $direccion_cliente = !empty($regv['direccion']) ? $regv['direccion'] : '--';
        $num_documento = !empty($regv['num_documento']) ? $regv['num_documento'] : '--';
        $email_cliente = !empty($regv['email']) ? $regv['email'] : '--';
        $telefono_cliente = !empty($regv['telefono']) ? $regv['telefono'] : '--';

        // configuración de la factura
        $pdf = new PDF_Invoice('P', 'mm', 'A4');
        $pdf->AddPage();

        // enviamos datos de la empresa al método addSociete de la clase factura
        $pdf->addSociete(
            utf8_decode($empresa),
            $ndocumento . ": " . $documento . "\n" .
            utf8_decode("Dirección: ") . utf8_decode($direccion) . "\n" .
            utf8_decode("Telfono: ") . $telefono . "\n" .
            "Email: " . $email,
            $logoe,
            25,
            25
        );

        // Factura
        $pdf->fact_dev($regv['tipo_comprobante'], $regv['serie_comprobante'] . '-' . $regv['num_comprobante']);
        $pdf->addDate($regv['fecha']);

        // Enviamos los datos del cliente al método addClientAdresse de la clase factura
        $pdf->addClientAdresse(
            utf8_decode($cliente),
            "Domicilio: " . utf8_decode($direccion_cliente),
            $regv['tipo_documento'] . ": " . $num_documento,
            "Email: " . $email_cliente,
            "Celular: " . $telefono_cliente
        );

        // Detalles de la venta
        $rsptad = $venta->ventadetalles($_GET["id"]);

        if (!$rsptad || empty($rsptad)) {
            echo "Error al obtener los detalles de la venta";
            exit;
        }

        // Mostramos los detalles de la venta
        $cols = array(
            "CODIGO" => 23,
            "DESCRIPCION" => 78,
            "CANTIDAD" => 22,
            "P.U." => 25,
            "DSCTO" => 20,
            "IMPORTE" => 22
        );
        $pdf->addCols($cols);
        $pdf->addLineFormat($cols);
        $y = 85;

        foreach ($rsptad as $regd) {
            $line = array(
                "CODIGO" => $regd['codigo'],
                "DESCRIPCION" => utf8_decode($regd['articulo']),
                "CANTIDAD" => $regd['cantidad'],
                "P.U." => $regd['precio_venta'],
                "DSCTO" => $regd['descuento'],
                "IMPORTE" => $regd['subtotal']
            );
            $size = $pdf->addLine($y, $line);
            $y += $size + 2;
        }

        // Cálculos de SUBTOTAL, IGV (18%) y TOTAL
        $total_venta = $regv['total_venta']; // Total de la venta
        $subtotal = $total_venta / 1.18; // Calculamos el subtotal sin IGV
        $igv = $total_venta - $subtotal; // El IGV es la diferencia entre el total y el subtotal

        // Ajustamos la posición del rectángulo más abajo y hacia la derecha
        $pdf->Rect(140, 250, 60, 30); // Coordenadas x, y, ancho, alto

        // Posicionamos los totales dentro del rectángulo en el PDF
        $pdf->SetFont('Arial', 'B', 10);

        // SUBTOTAL
        $pdf->SetXY(140, 255); // Coordenadas de la celda
        $pdf->Cell(30, 6, 'SUBTOTAL:', 0, 0, 'R');
        $pdf->Cell(30, 6, number_format($subtotal, 2, '.', ','), 0, 1, 'R');

        // IGV 18%
        $pdf->SetXY(140, 261); // Coordenadas de la celda
        $pdf->Cell(30, 6, 'IGV 18%:', 0, 0, 'R');
        $pdf->Cell(30, 6, number_format($igv, 2, '.', ','), 0, 1, 'R');

        // TOTAL
        $pdf->SetXY(140, 267); // Coordenadas de la celda
        $pdf->Cell(30, 6, 'TOTAL:', 0, 0, 'R');
        $pdf->Cell(30, 6, number_format($total_venta, 2, '.', ','), 0, 1, 'R');

        // Generar PDF
        $pdf->Output('Reporte de Venta', 'I');
    } else {
        echo "No tiene permiso para visualizar el reporte";
    }
}

ob_end_flush();
