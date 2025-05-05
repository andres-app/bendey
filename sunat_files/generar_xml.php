<?php
require_once __DIR__ . '/../Config/config.php';
global $conn;

$idventa = $_GET['id'] ?? 0;
if (!$idventa) {
    die("❌ ID de venta no proporcionado.");
}

// 1. Obtener venta y cliente
$stmt = $conn->prepare("SELECT v.*, p.nombre AS cliente_nombre, p.num_documento, p.direccion
                        FROM venta v 
                        JOIN persona p ON v.idcliente = p.idpersona 
                        WHERE v.idventa = ?");
$stmt->execute([$idventa]);
$venta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venta) die("❌ Venta no encontrada.");

// 2. Obtener detalles
$stmt = $conn->prepare("SELECT dv.*, a.nombre AS articulo_nombre 
                        FROM detalle_venta dv 
                        JOIN articulo a ON dv.idarticulo = a.idarticulo 
                        WHERE dv.idventa = ?");
$stmt->execute([$idventa]);
$detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Preparar datos para XML
$emisor = [
    "ruc" => "20609068800",
    "razonSocial" => "FELICITY GIRLS E.I.R.L.",
    "direccion" => "Av. Principal 123, Lima",
];

$cliente = [
    "tipoDoc" => "1", // 1 = DNI, 6 = RUC
    "numDoc" => $venta['num_documento'],
    "nombre" => $venta['cliente_nombre']
];

$serie = $venta['serie_comprobante'];
$numero = str_pad($venta['num_comprobante'], 8, '0', STR_PAD_LEFT);
$fecha = substr($venta['fecha_hora'], 0, 10);

$subtotal = 0;
$items = [];
foreach ($detalles as $d) {
    $subtotal += ($d['precio_venta'] - $d['descuento']) * $d['cantidad'];
    $items[] = [
        "descripcion" => $d['articulo_nombre'],
        "cantidad" => $d['cantidad'],
        "valorUnitario" => number_format(($d['precio_venta'] / 1.18), 2, '.', ''),
        "precioVenta" => number_format($d['precio_venta'], 2, '.', '')
    ];
}
$igv = $subtotal * 0.18;
$total = $subtotal + $igv;

// 4. Crear XML
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

$Invoice = $xml->createElementNS("urn:oasis:names:specification:ubl:schema:xsd:Invoice-2", "Invoice");
$Invoice->setAttribute("xmlns:cac", "urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2");
$Invoice->setAttribute("xmlns:cbc", "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2");
$xml->appendChild($Invoice);
$Invoice->setAttribute("ID", "$serie-$numero"); // 👈 ESTE ES CLAVE

$cbc_ns = "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2";
$cac_ns = "urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2";

function createElementNS($doc, $ns, $name, $value = null) {
    $el = $doc->createElementNS($ns, $name);
    if ($value !== null) $el->nodeValue = $value;
    return $el;
}

// NUEVAS ETIQUETAS UBL
$Invoice->appendChild(createElementNS($xml, $cbc_ns, "cbc:UBLVersionID", "2.1"));
$Invoice->appendChild(createElementNS($xml, $cbc_ns, "cbc:CustomizationID", "2.0"));
$Invoice->appendChild(createElementNS($xml, $cbc_ns, "cbc:ID", "$serie-$numero"));
$Invoice->appendChild(createElementNS($xml, $cbc_ns, "cbc:IssueDate", $fecha));
$Invoice->appendChild(createElementNS($xml, $cbc_ns, "cbc:InvoiceTypeCode", "01"));
$Invoice->appendChild(createElementNS($xml, $cbc_ns, "cbc:DocumentCurrencyCode", "PEN"));

// Emisor
$supplier = $xml->createElementNS($cac_ns, "cac:AccountingSupplierParty");
$party = $xml->createElementNS($cac_ns, "cac:Party");
$party->appendChild(createElementNS($xml, $cbc_ns, "cbc:RegistrationName", $emisor['razonSocial']));
$party->appendChild(createElementNS($xml, $cbc_ns, "cbc:CompanyID", $emisor['ruc']));
$supplier->appendChild($party);
$Invoice->appendChild($supplier);

// Cliente
$customer = $xml->createElementNS($cac_ns, "cac:AccountingCustomerParty");
$party_c = $xml->createElementNS($cac_ns, "cac:Party");
$party_c->appendChild(createElementNS($xml, $cbc_ns, "cbc:RegistrationName", $cliente['nombre']));
$party_c->appendChild(createElementNS($xml, $cbc_ns, "cbc:CompanyID", $cliente['numDoc']));
$customer->appendChild($party_c);
$Invoice->appendChild($customer);

// Totales
$legalTotal = $xml->createElementNS($cac_ns, "cac:LegalMonetaryTotal");
$payable = createElementNS($xml, $cbc_ns, "cbc:PayableAmount", number_format($total, 2, '.', ''));
$payable->setAttribute("currencyID", "PEN");
$legalTotal->appendChild($payable);
$Invoice->appendChild($legalTotal);

// Items
foreach ($items as $i => $item) {
    $line = $xml->createElementNS($cac_ns, "cac:InvoiceLine");
    $line->appendChild(createElementNS($xml, $cbc_ns, "cbc:ID", $i + 1));
    $qty = createElementNS($xml, $cbc_ns, "cbc:InvoicedQuantity", $item['cantidad']);
    $qty->setAttribute("unitCode", "NIU");
    $line->appendChild($qty);
    $amount = createElementNS($xml, $cbc_ns, "cbc:LineExtensionAmount", $item['precioVenta']);
    $amount->setAttribute("currencyID", "PEN");
    $line->appendChild($amount);
    $itemNode = $xml->createElementNS($cac_ns, "cac:Item");
    $itemNode->appendChild(createElementNS($xml, $cbc_ns, "cbc:Description", $item['descripcion']));
    $line->appendChild($itemNode);
    $price = $xml->createElementNS($cac_ns, "cac:Price");
    $price->appendChild(createElementNS($xml, $cbc_ns, "cbc:PriceAmount", $item['precioVenta']));
    $line->appendChild($price);
    $Invoice->appendChild($line);
}

// Guardar XML
$nombreXML = "20609068800-01-$serie-$numero.xml";
$xmlDir = __DIR__ . '/xml/';
if (!is_dir($xmlDir)) {
    mkdir($xmlDir, 0777, true);
}
file_put_contents($xmlDir . $nombreXML, $xml->saveXML());

echo "✅ XML generado: $nombreXML";
