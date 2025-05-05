<?php

// ===============================================
// Archivo: enviar_a_sunat_completo.php
// Proceso Completo: Generar XML, Firmar, Comprimir ZIP, Enviar a SUNAT
// ===============================================

require __DIR__ . '/../vendor/autoload.php';

use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

// Datos empresa
$ruc = "20609068800";
$usuarioSol = "OODICERA";
$claveSol = "itylvelon";

$serie = "F001";
$numero = "00000026";
$idventa = 114; // Aquí pon el ID correcto de la venta a enviar

$xmlDir = __DIR__ . '/xml/';
$zipDir = __DIR__ . '/zip/';
$certDir = __DIR__ . '/certificados/';

$nombreXML = "$ruc-01-$serie-$numero.xml";
$nombreZIP = "$ruc-01-$serie-$numero.zip";

// ===============================================
// 1. Generar XML Base
// ===============================================

$doc = new DOMDocument('1.0', 'UTF-8');
$doc->formatOutput = true;

$Invoice = $doc->createElementNS("urn:oasis:names:specification:ubl:schema:xsd:Invoice-2", "Invoice");
$Invoice->setAttribute("xmlns:cac", "urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2");
$Invoice->setAttribute("xmlns:cbc", "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2");
$doc->appendChild($Invoice);

$cbc_ns = "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2";
$cac_ns = "urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2";

function createElementNS($doc, $ns, $name, $value = null) {
    $el = $doc->createElementNS($ns, $name);
    if ($value !== null) $el->nodeValue = $value;
    return $el;
}

$Invoice->appendChild(createElementNS($doc, $cbc_ns, "cbc:UBLVersionID", "2.1"));
$Invoice->appendChild(createElementNS($doc, $cbc_ns, "cbc:CustomizationID", "2.0"));
$Invoice->appendChild(createElementNS($doc, $cbc_ns, "cbc:ID", "$serie-$numero"));
$Invoice->appendChild(createElementNS($doc, $cbc_ns, "cbc:IssueDate", date('Y-m-d')));
$Invoice->appendChild(createElementNS($doc, $cbc_ns, "cbc:InvoiceTypeCode", "01"));
$Invoice->appendChild(createElementNS($doc, $cbc_ns, "cbc:DocumentCurrencyCode", "PEN"));

$supplier = $doc->createElementNS($cac_ns, "cac:AccountingSupplierParty");
$party = $doc->createElementNS($cac_ns, "cac:Party");
$party->appendChild(createElementNS($doc, $cbc_ns, "cbc:RegistrationName", "FELICITY GIRLS E.I.R.L."));
$party->appendChild(createElementNS($doc, $cbc_ns, "cbc:CompanyID", $ruc));
$supplier->appendChild($party);
$Invoice->appendChild($supplier);

$customer = $doc->createElementNS($cac_ns, "cac:AccountingCustomerParty");
$party_c = $doc->createElementNS($cac_ns, "cac:Party");
$party_c->appendChild(createElementNS($doc, $cbc_ns, "cbc:RegistrationName", "Cliente Prueba"));
$party_c->appendChild(createElementNS($doc, $cbc_ns, "cbc:CompanyID", "12345678"));
$customer->appendChild($party_c);
$Invoice->appendChild($customer);

$legalTotal = $doc->createElementNS($cac_ns, "cac:LegalMonetaryTotal");
$payable = createElementNS($doc, $cbc_ns, "cbc:PayableAmount", "118.00");
$payable->setAttribute("currencyID", "PEN");
$legalTotal->appendChild($payable);
$Invoice->appendChild($legalTotal);

$doc->save($xmlDir . $nombreXML);

// ===============================================
// 2. Firmar XML
// ===============================================

$pfxPath = $certDir . 'certi.pfx';
$pfxPassword = 'Dev2804751';

$pfx = file_get_contents($pfxPath);
if (!openssl_pkcs12_read($pfx, $certs, $pfxPassword)) {
    die('❌ Error leyendo PFX.');
}

$privateKey = $certs['pkey'];
$publicCert = $certs['cert'];

$doc = new DOMDocument();
$doc->load($xmlDir . $nombreXML);
$root = $doc->documentElement;

$objDSig = new XMLSecurityDSig();
$objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
$objDSig->addReference(
    $doc,
    XMLSecurityDSig::SHA1,
    ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
    ['uri' => "#$serie-$numero"]
);

$objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type'=>'private']);
$objKey->loadKey($privateKey, false);

$objDSig->sign($objKey);
$objDSig->add509Cert($publicCert);
$objDSig->appendSignature($root);

$doc->save($xmlDir . $nombreXML);

// ===============================================
// 3. Comprimir ZIP
// ===============================================

if (!is_dir($zipDir)) mkdir($zipDir, 0777, true);

$zip = new ZipArchive();
if ($zip->open($zipDir . $nombreZIP, ZipArchive::CREATE) === TRUE) {
    $zip->addFile($xmlDir . $nombreXML, $nombreXML);
    $zip->close();
} else {
    die("❌ Error creando ZIP");
}

// ===============================================
// 4. Enviar a SUNAT
// ===============================================

$zipBase64 = base64_encode(file_get_contents($zipDir . $nombreZIP));

$soapRequest = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://service.sunat.gob.pe">
  <soapenv:Header/>
  <soapenv:Body>
    <ser:sendBill>
      <fileName>{$nombreZIP}</fileName>
      <contentFile>{$zipBase64}</contentFile>
    </ser:sendBill>
  </soapenv:Body>
</soapenv:Envelope>
XML;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
curl_setopt($ch, CURLOPT_USERPWD, "$ruc$usuarioSol:$claveSol");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: text/xml; charset=utf-8",
    "SOAPAction: \"\"",
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $soapRequest);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    die("❌ Error CURL: " . curl_error($ch));
}
curl_close($ch);

header('Content-Type: text/xml');
echo $response;

?>
