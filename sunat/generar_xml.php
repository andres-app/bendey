<?php
// generar_xml.php

// Datos del emisor (simulados)
$emisor = [
    "ruc" => "2147483647",
    "razonSocial" => "Mi empresa",
    "nombreComercial" => "Mi empresa",
    "direccion" => "Av. Tomas Valle 124",
    "ubigeo" => "150101",
    "departamento" => "LIMA",
    "provincia" => "LIMA",
    "distrito" => "LIMA",
    "correo" => "ventas@miempresa.com"
];

// Datos del cliente
$cliente = [
    "tipoDoc" => "1",
    "numDoc" => "72607251",
    "nombre" => "ANDRES MARTIN SILVA BASAURI"
];

// Datos de la factura
$factura = [
    "serie" => "F001",
    "numero" => "0000002",
    "fechaEmision" => "2025-04-19",
    "moneda" => "PEN",
    "subtotal" => "32.80",
    "igv" => "7.20",
    "total" => "40.00",
    "items" => [
        ["descripcion" => "Galleta x 6 pack", "cantidad" => "1", "valorUnitario" => "16.95", "precioVenta" => "20.00"],
        ["descripcion" => "Fideos", "cantidad" => "1", "valorUnitario" => "16.95", "precioVenta" => "20.00"]
    ]
];

$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

$Invoice = $xml->createElement("Invoice");
$Invoice->setAttribute("xmlns", "urn:oasis:names:specification:ubl:schema:xsd:Invoice-2");
$xml->appendChild($Invoice);

$cbc_ns = "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2";
$cac_ns = "urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2";

function createElementNS($doc, $ns, $name, $value = null) {
    $el = $doc->createElementNS($ns, $name);
    if ($value !== null) {
        $el->nodeValue = $value;
    }
    return $el;
}

$Invoice->appendChild(createElementNS($xml, $cbc_ns, "cbc:ID", $factura['serie'] . '-' . $factura['numero']));
$Invoice->appendChild(createElementNS($xml, $cbc_ns, "cbc:IssueDate", $factura['fechaEmision']));
$Invoice->appendChild(createElementNS($xml, $cbc_ns, "cbc:InvoiceTypeCode", "01"));
$Invoice->appendChild(createElementNS($xml, $cbc_ns, "cbc:DocumentCurrencyCode", $factura['moneda']));

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
$payable = createElementNS($xml, $cbc_ns, "cbc:PayableAmount", $factura['total']);
$payable->setAttribute("currencyID", "PEN");
$legalTotal->appendChild($payable);
$Invoice->appendChild($legalTotal);

// Items
foreach ($factura['items'] as $i => $item) {
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

// Guardar el XML
file_put_contents("xml/20123456789-01-F001-0000002.xml", $xml->saveXML());
echo "XML generado con éxito.";
?>
