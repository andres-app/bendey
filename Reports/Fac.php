<?php
// exemple de facture avec mysqli
// gere le multi-page
// Ver 1.0 THONGSOUME Jean-Paul
//
require('../fpdf182/fpdf.php');
	
	// le mettre au debut car plante si on declare $mysqli avant !
$pdf = new FPDF( 'P', 'mm', 'A4' );

//datos de la empresa
require_once "../modelos/Negocio.php";
$cnegocio = new Negocio();
$rsptan = $cnegocio->listar();
$regn=$rsptan->fetch_object();
$empresa = $regn->nombre;
$ndocumento = $regn->ndocumento;
$documento = $regn->documento;
$direccion = $regn->direccion;
$telefono = $regn->telefono;  
$email = $regn->email;
$pais = $regn->pais;
$ciudad = $regn->ciudad;
$nombre_impuesto = $regn->nombre_impuesto;
$monto_impuesto = $regn->monto_impuesto;
$moneda = $regn->moneda;
$simbolo = $regn->simbolo;
$new_simbolo='';
$sim_euro='€';
$sim_yen='¥';
$sim_libra='£';
if ($simbolo==$sim_euro) {
  $new_simbolo=EURO;
}elseif($simbolo==$sim_yen){
  $new_simbolo=JPY;
}elseif ($simbolo==$sim_libra) {
  $new_simbolo=GBP;
}else{
  $new_simbolo=$simbolo;
}


$logoe="../files/negocio/".$regn->logo."";
$ext_logo="png";

//obtenemos los datos de la cabecera de la venta actual
require_once "../modelos/Venta.php";
$venta= new Venta();
$rsptav=$venta->ventacabecera($_GET["id"]);

//recorremos todos los valores que obtengamos
$regv=$rsptav->fetch_object();

// on sup les 2 cm en bas
$pdf->SetAutoPagebreak(False);
$pdf->SetMargins(0,0,0);

$nb_page = 2;

$num_page = 1; 
$limit_inf = 1; 
$limit_sup = 5;
	While ($num_page <= $nb_page)
	{
		$pdf->AddPage();
		
		//LOGO DE LA EMPRESA
		$pdf->Image($logoe, 5, 10, 30, 20);

		//NUMERO DEP PAGINA
		$pdf->SetXY( 120, 5 ); $pdf->SetFont( "Arial", "B", 12 ); $pdf->Cell( 160, 8, $num_page . '/' . $nb_page, 0, 0, 'C');
		
		$num_fact = utf8_decode($regv->tipo_comprobante." N° " .$regv->serie_comprobante.'-' .$regv->num_comprobante);
		$pdf->SetLineWidth(0.1); $pdf->SetFillColor(192); $pdf->Rect(120, 15, 85, 8, "DF");
		$pdf->SetXY( 120, 15 ); $pdf->SetFont( "Arial", "B", 12 ); $pdf->Cell( 85, 8, $num_fact, 0, 0, 'C');
		
		//NOMBRE DEL ARCHIVO PARA GUARDAR
		$nom_file = "fact_" . $regv->fecha .'-' .$regv->serie_comprobante.'-' .$regv->num_comprobante . ".pdf";
		
		//MOSTRAR FECHA
		$champ_date = date("Y-m-d");
		$pdf->SetFont('Arial','',11); $pdf->SetXY( 122, 30 );
		$pdf->Cell( 60, 8, "Fecha: " . $regv->fecha, 0, 0, '');
		
		// si es la última página, muestra el total
		if ($num_page == $nb_page)
		{
			$ivgimp=$regv->impuesto+100;
			$igv_total=$ivgimp/100;
			$subtotal=$regv->total_venta/$igv_total;
			$igv=$regv->total_venta-$subtotal;
			$totalv=$igv+$subtotal; 
			//los totales, solo se muestra el HT. el marco después de las líneas, comienza en 213
			$pdf->SetLineWidth(0.1); $pdf->SetFillColor(192); $pdf->Rect(5, 213, 90, 8, "DF");
			// HT, VAT y TTC se calculan después
			$nombre_format_francais = "Subtotal : " . number_format($subtotal, 2, ',', ' ') . $new_simbolo; 
			$pdf->SetFont('Arial','',10); $pdf->SetXY( 95, 213 ); $pdf->Cell( 63, 8, $nombre_format_francais, 0, 0, 'C');
			// abajo a la derecha
			$pdf->SetFont('Arial','B',8); $pdf->SetXY( 181, 227 ); $pdf->Cell( 24, 6, number_format($subtotal, 2, ',', ' '), 0, 0, 'R');

			// totales del marco de la línea vertical, 8 alto -> 213 + 8 = 221
			$pdf->Rect(5, 213, 200, 8, "D"); $pdf->Line(95, 213, 95, 221); $pdf->Line(158, 213, 158, 221);

			// regulación
			$pdf->SetXY( 5, 225 ); $pdf->Cell( 38, 5, "Modo de pago :", 0, 0, 'R'); $pdf->Cell( 55, 5, 10, 0, 0, 'L');
			// plazo
			$champ_date = date('d/m/Y');
			$pdf->SetXY( 5, 230 ); $pdf->Cell( 38, 5, "Fecha de vencimiento :", 0, 0, 'R'); $pdf->Cell( 38, 5, $champ_date, 0, 0, 'L');
		}
	
		// DATOS DEL CLIENTE
		$pdf->SetFont('Arial','',11); $x = 5 ; $y = 40;
		$pdf->SetXY( $x, $y ); $pdf->Cell( 100, 8, 'Cliente:'.utf8_decode($regv->cliente), 0, 0, 'L'); $y += 4;
		$pdf->SetXY( $x, $y ); $pdf->Cell( 100, 8, 'Domicilio:'.utf8_decode($regv->direccion), 0, 0, 'L'); $y += 4;
		$pdf->SetXY( $x, $y ); $pdf->Cell( 100, 8, 'Telefono:'.utf8_decode($regv->telefono), 0, 0, 'L'); $y += 4;
		$pdf->SetXY( $x, $y ); $pdf->Cell( 100, 8, 'Correo:'.utf8_decode($regv->email), 0, 0, 'L'); $y += 4;
		
		//OBSERVACIONES
		$pdf->SetFont( "Arial", "BU", 10 ); $pdf->SetXY( 5, 65 ) ; $pdf->Cell($pdf->GetStringWidth("Observaciones"), 0, "Observaciones", 0, "L");
		$pdf->SetFont( "Arial", "", 10 ); $pdf->SetXY( 5, 68 ) ; $pdf->MultiCell(190, 4, 'esto es un campo para poder agregar alguna observacion sobre la venta en caso de que ocurra algun problema', 0, "L");
		// ***********************
		// el marco de los artículos
		// ***********************
		// marco con 18 líneas como máximo! y 118 alto -> 95 + 118 = 213 para líneas verticales
		$pdf->SetLineWidth(0.1); $pdf->Rect(5, 75, 200, 138, "D");
		// marco de título de columna
		$pdf->Line(5, 85, 205, 85);
		// columnas de líneas verticales
		$pdf->Line(30, 75, 30, 213);
		$pdf->Line(135, 75, 135, 213); 
		$pdf->Line(153, 75, 153, 213); 
		$pdf->Line(168, 75, 168, 213); 
		$pdf->Line(187, 75, 187, 213);
		// título de la columna
		$pdf->SetXY( 5, 76 ); $pdf->SetFont('Arial','B',8); $pdf->Cell( 25, 8,utf8_decode("Código"), 0, 0, 'C');
		$pdf->SetXY( 30, 76 ); $pdf->SetFont('Arial','B',8); $pdf->Cell( 105, 8,utf8_decode("Descripción"), 0, 0, 'C');
		$pdf->SetXY( 135, 76 ); $pdf->SetFont('Arial','B',8); $pdf->Cell( 18, 8, "Cantidad", 0, 0, 'C');
		$pdf->SetXY( 153, 76 ); $pdf->SetFont('Arial','B',8); $pdf->Cell( 15, 8, "P.U.", 0, 0, 'C');
		$pdf->SetXY( 168, 76 ); $pdf->SetFont('Arial','B',8); $pdf->Cell( 19, 8, "Descuento", 0, 0, 'C');
		$pdf->SetXY( 187, 76 ); $pdf->SetFont('Arial','B',8); $pdf->Cell( 18, 8, "TOTAL", 0, 0, 'C');
		
		// les articles
		$pdf->SetFont('Arial','',8);
		$y = 77;
		// 1ª página = LÍMITE 0,18; 2da página = LIMIT 18,36 etc ...
		//obtenemos todos los detalles del a venta actual
		$rsptad=$venta->ventadetalles($_GET["id"]);

		while($regd=$rsptad->fetch_object()){

			//CODIGO
			$pdf->SetXY( 5, $y+9 ); $pdf->Cell( 25, 5, $regd->codigo, 0, 0, 'L');
			//DESCRIPCION
			$pdf->SetXY( 30, $y+9 ); $pdf->Cell( 105, 5, utf8_decode("$regd->articulo"), 0, 0, 'L');
			//CANTIDAD
			$pdf->SetXY( 135, $y+9 ); $pdf->Cell( 18, 5, $regd->cantidad, 0, 0, 'R');
			//PRECIO UNIDAD
			$pdf->SetXY( 153, $y+9 ); $pdf->Cell( 15, 5, $regd->precio_venta, 0, 0, 'R');
			//DESCUENTO
			$pdf->SetXY( 168, $y+9 ); $pdf->Cell( 19, 5, $regd->descuento, 0, 0, 'R');
			//TOTAL
			$pdf->SetXY( 187, $y+9 ); $pdf->Cell( 18, 5, $regd->subtotal, 0, 0, 'R');
			
			$pdf->Line(5, $y+14, 205, $y+14);
			
			$y += 6;


		}

		// si es la última página, muestra el marco de IVA
		if ($num_page == $nb_page)
		{
			// el detalle de los totales, comienza en 221 después del marco de totales
			$pdf->SetLineWidth(0.1); $pdf->Rect(130, 221, 75, 24, "D");
			// les traits verticaux
			$pdf->Line(147, 221, 147, 245); $pdf->Line(164, 221, 164, 245); $pdf->Line(181, 221, 181, 245);
			// les traits horizontaux pas de 6 et demarre a 221
			$pdf->Line(130, 227, 205, 227); $pdf->Line(130, 233, 205, 233); $pdf->Line(130, 239, 205, 239);
			// les titres
			$pdf->SetFont('Arial','B',8); $pdf->SetXY( 181, 221 ); $pdf->Cell( 24, 6, "TOTALES", 0, 0, 'C');
			$pdf->SetFont('Arial','',8);
			$pdf->SetXY( 105, 221 ); $pdf->Cell( 25, 6, $nombre_impuesto, 0, 0, 'R');
			$pdf->SetXY( 105, 227 ); $pdf->Cell( 25, 6, "Subtotal", 0, 0, 'R');
			$pdf->SetXY( 105, 233 ); $pdf->Cell( 25, 6, "Total ".$nombre_impuesto, 0, 0, 'R');
			$pdf->SetXY( 105, 239 ); $pdf->Cell( 25, 6, "Total", 0, 0, 'R');


			$x = 130;

				$pdf->SetXY( $x, 221 ); $pdf->Cell( 17, 6, $regv->impuesto . ' %', 0, 0, 'C');
				$taux = 10;


				$subtotalv = number_format($subtotal, 2, ',', ' ');
				$pdf->SetXY( $x, 227 ); $pdf->Cell( 17, 6, $subtotalv, 0, 0, 'R');
				$col_ht = 25;
				
				$igv_v = number_format($igv, 2, ',', ' ');
				$pdf->SetXY( $x, 233 ); $pdf->Cell( 17, 6, $igv_v, 0, 0, 'R');
				
				$totalneto = number_format($totalv, 2, ',', ' ');
				$pdf->SetXY( $x, 239 ); $pdf->Cell( 17, 6, $totalneto, 0, 0, 'R');

				
				$x += 17;

			$nombre_format_francais = "NETO A PAGAR : " . number_format($totalv, 2, ',', ' ') . ' '.$new_simbolo;
			$pdf->SetFont('Arial','B',12); 
			$pdf->SetXY( 5, 213 ); $pdf->Cell( 90, 8, $nombre_format_francais, 0, 0, 'C');
			// en bas � droite
			$pdf->SetFont('Arial','B',8); 
			$pdf->SetXY( 181, 239 ); $pdf->Cell( 24, 6, number_format($totalv, 2, ',', ' '), 0, 0, 'R');
			// TVA
			$nombre_format_francais = "Total ".$nombre_impuesto.": " . number_format($igv, 2, ',', ' ') . $new_simbolo;
			$pdf->SetFont('Arial','',10); 
			$pdf->SetXY( 158, 213 ); 
			$pdf->Cell( 47, 8, $nombre_format_francais, 0, 0, 'C');
			// en bas � droite
			$pdf->SetFont('Arial','B',8); 
			$pdf->SetXY( 181, 233 ); 
			$pdf->Cell( 24, 6, number_format($igv, 2, ',', ' '), 0, 0, 'R');
		}

		// **************************
		// PIE DE PAGINA
		// **************************
		$pdf->SetLineWidth(0.1); $pdf->Rect(5, 260, 200, 6, "D");
		$pdf->SetXY( 1, 260 ); $pdf->SetFont('Arial','',7);
		$pdf->Cell( $pdf->GetPageWidth(), 7, utf8_decode("Cláusula de reserva de propiedad (ley 80.335 de 12 de mayo de 1980) : Los bienes vendidos siguen siendo de nuestra propiedad hasta el pago total de los mismos."), 0, 0, 'C');
		
		$y1 = 270;
		//Positionnement en bas et tout centrer
		$pdf->SetXY( 1, $y1 ); $pdf->SetFont('Arial','B',10);
		$pdf->Cell( $pdf->GetPageWidth(), 5, utf8_decode($empresa), 0, 0, 'C');
		
		$pdf->SetFont('Arial','',10);
		
		$pdf->SetXY( 1, $y1 + 4 ); 
		$pdf->Cell( $pdf->GetPageWidth(), 5,$ndocumento." :".$documento, 0, 0, 'C');
		
		$pdf->SetXY( 1, $y1 + 8 );
		$pdf->Cell( $pdf->GetPageWidth(), 5, utf8_decode("Direcíon:".$direccion), 0, 0, 'C');

		$pdf->SetXY( 1, $y1 + 12 );
		$pdf->Cell( $pdf->GetPageWidth(), 5, "Telf: ".$telefono, 0, 0, 'C');

		$pdf->SetXY( 1, $y1 + 16 );
		$pdf->Cell( $pdf->GetPageWidth(), 5, "Email: ".$email, 0, 0, 'C');
		
		// par page de 18 lignes
		$num_page++; $limit_inf += 5; $limit_sup += 5; 
	}
	
	$pdf->Output("I", $nom_file);
?>
