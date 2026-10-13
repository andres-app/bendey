<?php
/** Prueba unitaria local. No usa base de datos ni APISUNAT. */
declare(strict_types=1);
require_once __DIR__.'/../Models/GreDocument.php';
function ok(bool $pass, string $message): void { if (!$pass) throw new RuntimeException($message); }
$datos = [
    'guia' => [
        'emisor_ruc'=>'20123456789','emisor_nombre'=>'EMPRESA EJEMPLO SAC',
        'destinatario_tipo_documento'=>'RUC','destinatario_num_documento'=>'20612345678',
        'destinatario_nombre'=>'CLIENTE EJEMPLO SAC','fecha_inicio_traslado'=>'2026-10-14',
        'modalidad_transporte'=>'02','vehiculo_placa'=>'ABC123',
        'conductor_num_documento'=>'12345678','conductor_tipo_documento'=>'DNI',
        'conductor_nombre'=>'JUAN PEREZ','conductor_licencia'=>'Q01234567',
        'motivo_traslado'=>'01','peso_bruto'=>'3.500', 'observacion'=>'',
        'numero_bultos'=>1,'partida_ubigeo'=>'150101','partida_direccion'=>'AV. EJEMPLO 100',
        'llegada_ubigeo'=>'150102','llegada_direccion'=>'CALLE DEMO 200'
    ],
    'items'=>[['codigo'=>'SKU-01','descripcion'=>'PRODUCTO DEMO','cantidad'=>2,'unidad_sunat'=>'NIU']],
    'documentos'=>[['tipo_documento'=>'01','serie'=>'F001','numero'=>'00000002']]
];
$fecha = new DateTimeImmutable('2026-10-13T15:00:00-05:00');
$doc = GreDocument::construir($datos,'T001',1,$fecha);
ok($doc['fileName']==='20123456789-09-T001-00000001','Nombre de documento incorrecto.');
ok($doc['documentBody']['cbc:ID']['_text']==='T001-00000001','Número GRE incorrecto.');
ok($doc['documentBody']['cbc:DespatchAdviceTypeCode']['_text']==='09','Tipo debe ser 09.');
ok(count($doc['documentBody']['cac:DespatchLine'])===1,'Debe tener una línea.');
ok($doc['documentBody']['cac:Shipment']['cac:ShipmentStage']['cac:DriverPerson']['cbc:FirstName']['_text']==='JUAN','No asigna conductor.');
ok($doc['documentBody']['cac:Shipment']['cbc:GrossWeightMeasure']['_attributes']['unitCode']==='KGM','Unidad peso incorrecta.');
ok(isset($doc['documentBody']['cac:AdditionalDocumentReference'][0]), 'Falta documento de origen.');
$datos['guia']['modalidad_transporte']='01';
$datos['guia']['transportista_ruc']='20123456789';
$datos['guia']['transportista_nombre']='TRANSPORTES EJEMPLO';
$doc=GreDocument::construir($datos,'T001',2,$fecha);
ok(isset($doc['documentBody']['cac:Shipment']['cac:ShipmentStage']['cac:CarrierParty']), 'Falta transportista público.');
$bloqueo=false;
try { GreDocument::construir($datos,'F001',0,$fecha); } catch(InvalidArgumentException $e) {$bloqueo=true;}
ok($bloqueo,'Se aceptó serie/correlativo inválido.');
echo "OK - Generación de GRE 09, comprobante origen, conductor, transporte privado/público y numeración.\n";
