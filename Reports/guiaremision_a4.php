<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['nombre']) || (int)($_SESSION['guias_remision']??0)!==1) {
    http_response_code(403); exit('No autorizado');
}
require_once __DIR__.'/../Models/GuiaRemision.php';
require_once __DIR__.'/../Libraries/fpdf182/fpdf.php';

function grePdfTexto($str): string
{
    $x=iconv('UTF-8','windows-1252//TRANSLIT//IGNORE',(string)$str);
    return $x!==false?$x:(string)$str;
}
function grePdfCelda(FPDF $pdf,string $titulo,string $valor,int $ancho=90): void
{
    $pdf->SetFont('Arial','B',8); $pdf->Cell($ancho,6,grePdfTexto($titulo),0,1);
    $pdf->SetFont('Arial','',9); $pdf->MultiCell(190,5,grePdfTexto($valor),0,'L');
    $pdf->Ln(1);
}
try {
    $id=(int)($_GET['id']??0);
    if($id<=0) throw new RuntimeException('Guía inválida.');
    $model=new GuiaRemision();$all=$model->ver($id,(int)$_SESSION['idusuario']);
    $g=$all['guia'];
    $pdf=new FPDF('P','mm','A4');
    $pdf->SetAutoPageBreak(true,18);
    $pdf->AddPage();
    $pdf->SetTextColor(170,55,55);$pdf->SetFont('Arial','B',12);
    $pdf->Cell(190,12,grePdfTexto('BORRADOR INTERNO - NO VALIDO PARA TRASLADO'),1,1,'C');
    $pdf->Ln(5);$pdf->SetTextColor(35,55,44);
    $pdf->SetFont('Arial','B',16);$pdf->Cell(125,9,grePdfTexto('GUIA DE REMISION REMITENTE'),0,0);
    $pdf->SetFont('Arial','B',11);$pdf->Cell(65,9,grePdfTexto('#'.$id),0,1,'R');
    $pdf->SetFont('Arial','',9);$pdf->Cell(190,7,grePdfTexto('Registro interno / Estado: '.$g['estado']),0,1);
    $pdf->Ln(2);
    grePdfCelda($pdf,'EMISOR',$g['emisor_nombre'].' | RUC: '.$g['emisor_ruc'].' | Sucursal: '.$g['sucursal']);
    grePdfCelda($pdf,'DESTINATARIO',$g['destinatario_nombre'].' - '.$g['destinatario_tipo_documento'].' '.$g['destinatario_num_documento']);
    grePdfCelda($pdf,'MOTIVO Y TRANSPORTE',$g['motivo_descripcion'].' | '.($g['modalidad_transporte']==='01'?'Público':'Privado'));
    grePdfCelda($pdf,'PARTIDA','Ubigeo '.$g['partida_ubigeo'].' - '.$g['partida_direccion']);
    grePdfCelda($pdf,'LLEGADA','Ubigeo '.$g['llegada_ubigeo'].' - '.$g['llegada_direccion']);
    grePdfCelda($pdf,'FECHA DE TRASLADO Y PESO',$g['fecha_inicio_traslado'].' | Peso bruto '.$g['peso_bruto'].' kg');
    $transp=$g['modalidad_transporte']==='01' ? $g['transportista_ruc'].' - '.$g['transportista_nombre'] :
        'Placa: '.$g['vehiculo_placa'].' | Conductor: '.$g['conductor_nombre'].' | Licencia: '.$g['conductor_licencia'];
    grePdfCelda($pdf,'TRANSPORTISTA',$transp);
    $pdf->Ln(2);$pdf->SetFont('Arial','B',10);$pdf->Cell(190,8,grePdfTexto('DETALLE DE MERCADERIA'),0,1);
    $pdf->SetFillColor(235,246,239);$pdf->SetFont('Arial','B',9);
    $pdf->Cell(24,8,'Codigo',1,0,'L',true);
    $pdf->Cell(125,8,'Descripcion',1,0,'L',true);
    $pdf->Cell(23,8,'Cantidad',1,0,'R',true);
    $pdf->Cell(18,8,'Unidad',1,1,'C',true);
    $pdf->SetFont('Arial','',8);
    foreach($all['items'] as $item){
        $descripcion=(string)$item['descripcion'];
        if(!empty($item['lotes'])){
            $l=[];foreach($item['lotes'] as $lot){$l[]='Lote: '.($lot['numero_lote']?:'#'.$lot['iddetalle_ingreso']).' ('.$lot['cantidad'].')'.($lot['fecha_vencimiento']?' - vence '.$lot['fecha_vencimiento']: '');}
            $descripcion.=' | '.implode('; ',$l);
        }
        $y=$pdf->GetY();if($y>245){$pdf->AddPage();$y=$pdf->GetY();}
        $txt=grePdfTexto($descripcion);
        $lines=max(1,(int)ceil($pdf->GetStringWidth($txt)/119));
        $h=max(9,$lines*5+3);
        if($pdf->GetY()+$h>270){$pdf->AddPage();}
        $x=$pdf->GetX();$y=$pdf->GetY();
        $pdf->Cell(24,$h,grePdfTexto(substr((string)$item['codigo'],0,14)),1);
        $pdf->SetXY($x+24,$y);$pdf->MultiCell(125,5,$txt,0,'L');
        $pdf->Rect($x+24,$y,125,$h);
        $pdf->SetXY($x+149,$y);$pdf->Cell(23,$h,number_format((float)$item['cantidad'],3),1,0,'R');
        $pdf->Cell(18,$h,grePdfTexto($item['unidad_sunat']),1,1,'C');
    }
    $pdf->Ln(6);
    $pdf->SetFont('Arial','B',9);$pdf->SetTextColor(155,45,45);
    $pdf->MultiCell(190,6,grePdfTexto('AVISO: Este archivo es una vista de un registro interno. NO ES UNA GRE EMITIDA, NO TIENE CODIGO QR DE SUNAT, NO INCLUYE XML/CDR y NO SUSTENTA EL TRASLADO DE BIENES.'),1,'C');
    if(ob_get_length())ob_end_clean();
    header('X-Content-Type-Options: nosniff');
    $pdf->Output('I','guia-borrador-'.$id.'.pdf');
} catch(Throwable $e) {
    error_log('[GRE PDF] '.$e->getMessage());http_response_code(400);echo 'No se pudo abrir la vista PDF de esta guía.';
}
