<?php
// Activamos almacenamiento en el buffer
ob_start();

// Incluye las clases necesarias
require_once "../Models/Sell.php";
require_once "../Models/Company.php";
include('../Libraries/phpqrcode/qrlib.php');
include('../Libraries/fpdf182/fpdf.php');

// Verifica si el ID de venta está presente
if (!isset($_GET['id'])) {
    echo "Acceso no autorizado";
    exit();
}

$id_venta = $_GET['id'];

// Obtener datos de la venta y la empresa
$venta = new Sell();
$rspta = $venta->ventacabecera($id_venta);
$reg = $rspta[0];

$cnegocio = new Company();
$rsptan = $cnegocio->listar();
$regn = $rsptan[0];

// Datos de la empresa
$empresa = $regn['nombre'];
$ndocumento = $regn['ndocumento'];
$documento = $regn['documento'];
$direccion = $regn['direccion']; 
$telefono = $regn['telefono'];
$email = $regn['email'];
$pais = $regn['pais'];
$ciudad = $regn['ciudad'];
$nombre_impuesto = $regn['nombre_impuesto'];
$monto_impuesto = $regn['monto_impuesto'];
$moneda = $regn['moneda'];
$simbolo = $regn['simbolo'];
$new_simbolo = '';
$sim_euro = '€';
$sim_yen = '¥';
$sim_libra = '£';

if ($simbolo == $sim_euro) {
    $new_simbolo = 'EURO';
} elseif ($simbolo == $sim_yen) {
    $new_simbolo = 'JPY';
} elseif ($simbolo == $sim_libra) {
    $new_simbolo = 'GBP';
} else {
    $new_simbolo = $simbolo;
}

// Construir la URL del QR
$url = 'https://' . $_SERVER['HTTP_HOST'] . '/Reports/58mm.php?id=' . $id_venta;

// Genera el código QR y guárdalo en un archivo temporal
$filename = '../Assets/qr_' . $reg['num_comprobante'] . '.png';
QRcode::png($url, $filename, QR_ECLEVEL_L, 3);

// Generación del PDF
$pdf = new FPDF($orientation='P', $unit='mm', array(58, 350));
$pdf->AddPage();
$pdf->SetFont('Helvetica', 'B', 12); // Letra Helvetica, negrita (Bold), tamaño 12
$textypos = 5;
$pdf->setY(2); 
$pdf->setX(2);
$pdf->Cell(54, $textypos, utf8_decode($empresa), 0, 0, 'C');
$pdf->SetFont('Helvetica', '', 10);
$pdf->setY(7); 
$pdf->setX(2);
$pdf->Cell(54, $textypos, utf8_decode($ndocumento . ": " . $documento), 0, 0, 'C');
$pdf->setY(11); 
$pdf->setX(2);
$pdf->Cell(54, $textypos, utf8_decode("Direc: " . $direccion), 0, 0, 'C');
$pdf->setY(15); 
$pdf->setX(2);
$pdf->Cell(54, $textypos, utf8_decode("Telf: " . $telefono), 0, 0, 'C');
$pdf->setY(19);
$pdf->setX(2);
$pdf->Cell(54, $textypos, utf8_decode($ciudad), 0, 0, 'C');

// Añade un salto de línea entre ciudad y fecha
$pdf->Ln(5);

$pdf->setX(2);
$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(54, $textypos, utf8_decode("Fecha: " . $reg['fecha']));
$pdf->setY(27); 
$pdf->setX(2);
$pdf->Cell(54, $textypos, utf8_decode("Cliente: " . $reg['cliente']));
$pdf->setY(30); 
$pdf->setX(2);
$pdf->Cell(54, $textypos, utf8_decode("Atendió: " . $_SESSION['nombre']));
$pdf->Ln(5);
$pdf->setX(2);
$pdf->Cell(54, 0, '', 'T');
$pdf->setY(35); 
$pdf->setX(2);
$pdf->Cell(54, $textypos, utf8_decode(strtoupper($reg['tipo_comprobante']) . " N°: " . $reg['serie_comprobante'] . " - " . $reg['num_comprobante']));

$pdf->Ln(5);

// Si está anulado la venta
$text = $reg['estado'];
if ($text == 'Anulado') {
    $pdf->SetFont('Helvetica', 'B', 30);
    $pdf->SetTextColor(245, 183, 177);
    $pdf->setX(12);
    $pdf->Cell(80, 20, strtoupper($text));
    $pdf->SetTextColor(0, 0, 0);
}

// Columnas
$pdf->Ln(5);
$pdf->setX(2);
$pdf->Cell(54, 0, '', 'T');
$pdf->SetFont('Helvetica', 'B', 7);
$pdf->setX(2);
$pdf->Cell(25, 4, 'ARTICULO', 0);
$pdf->setX(27);
$pdf->Cell(8, 4, 'UND', 0, 0, 'R');
$pdf->setX(35);
$pdf->Cell(11, 4, 'PRECIO', 0, 0, 'R');
$pdf->setX(46);
$pdf->Cell(10, 4, 'TOTAL', 0, 0, 'R');
$pdf->Ln(4);
$pdf->setX(2);
$pdf->Cell(54, 0, '', 'T');
$pdf->Ln(2);

$total = 0;
$rsptad = $venta->ventadetalles($id_venta);
$cantidad = 0;
foreach ($rsptad as $regd) {
    // Productos
    $pdf->SetFont('Helvetica', '', 7);
    $pdf->setX(2); 
    $pdf->MultiCell(25, 4, $regd['articulo'], 0, 'L');
    $pdf->setX(27);  
    $pdf->Cell(8, -5, $regd['cantidad'], 0, 0, 'R');
    $pdf->setX(35); 
    $pdf->Cell(11, -5, number_format(round($regd['precio_venta'], 2), 2, '.', ' ,'), 0, 0, 'R');
    $pdf->setX(46); 
    $pdf->Cell(10, -5, number_format(round($regd['subtotal'], 2), 2, '.', ' ,'), 0, 0, 'R');
    $cantidad += $regd['cantidad'];
    $pdf->Ln(2);
}

// Sumatorio de los productos y el IVA
$total_venta = $reg['total_venta']; // Ejemplo: 20.00
$igv = round($total_venta * 18 / 100, 2); // Calcula el IGV
$subtotal = round($total_venta - $igv, 2); // Calcula el subtotal sin IGV

// Formato de los valores en el PDF
$pdf->setX(2);
$pdf->Cell(54, 0, '', 'T');  
$pdf->Ln(0);

// Impresión del subtotal
$pdf->setX(2);    
$pdf->Cell(25, 10, 'SUBTOTAL');    
$pdf->Cell(20, 10, '', 0);
$pdf->setX(42);
$pdf->Cell(15, 10, $new_simbolo . ' ' . number_format($subtotal, 2, '.', ','), 0, 0, 'R');
$pdf->Ln(3);

// Impresión del IGV
$pdf->setX(2);    
$pdf->Cell(25, 10, $nombre_impuesto . ' 18%', 0);    
$pdf->Cell(20, 10, '', 0);
$pdf->setX(42);
$pdf->Cell(15, 10, $new_simbolo . ' ' . number_format($igv, 2, '.', ','), 0, 0, 'R');
$pdf->Ln(3);

// Impresión del total
$pdf->setX(2);
$pdf->Cell(25, 10, 'TOTAL', 0);
$pdf->Cell(20, 10, '', 0);
$pdf->setX(42);
$pdf->Cell(15, 10, $new_simbolo . ' ' . number_format($total_venta, 2, '.', ','), 0, 0, 'R');

// Agrega la imagen del código QR
$pdf->Image($filename, 15, 90, 30, 30); // Ajusta las coordenadas y el tamaño según tus necesidades

// Pie de página  
$pdf->Ln(2);   
$pdf->setX(2);
$pdf->Cell(54, $textypos + 10, utf8_decode('CANT. ARTICULOS: ' . $cantidad));
$pdf->setX(2);
$pdf->Cell(54, $textypos + 25, utf8_decode('¡GRACIAS POR SU COMPRA!'), 0, 0, 'C');

// Salida del archivo
$pdf->Output($reg['tipo_comprobante'] . '_' . $reg['serie_comprobante'] . '_' . $reg['num_comprobante'] . '.pdf', 'I');

// Elimina el archivo temporal del código QR
unlink($filename);

ob_end_flush();
?>
