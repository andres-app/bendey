<?php
require_once __DIR__ . '/../Config/Conexion.php';

class GenerarXML
{
    private $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    // ===============================
    // GENERAR XML DE COMPROBANTE
    // ===============================
    public function generar($idventa)
    {
        /* ===============================
       1. OBTENER VENTA
    =============================== */
        $venta = $this->conexion->getData(
            "SELECT * FROM venta WHERE idventa = ?",
            [$idventa]
        );

        if (!$venta) {
            return false;
        }

        /* ===============================
       2. OBTENER CLIENTE
    =============================== */
        $cliente = $this->conexion->getData(
            "SELECT * FROM persona WHERE idpersona = ?",
            [$venta['idcliente']]
        );

        if (!$cliente) {
            return false;
        }

        /* ===============================
       3. OBTENER DETALLE
    =============================== */
        $detalle = $this->conexion->getDataAll(
            "SELECT dv.*, a.nombre AS nombre_articulo
         FROM detalle_venta dv
         INNER JOIN articulo a ON dv.idarticulo = a.idarticulo
         WHERE dv.idventa = ?",
            [$idventa]
        );

        if (!$detalle || count($detalle) === 0) {
            return false;
        }

        // ===============================
        // DATOS DEL EMISOR (ANTES DEL XML)
        // ===============================
        $rucEmisor    = '20609068800';
        $nombreEmisor = 'FELICITY GIRLS E.I.R.L.';

        /* ===============================
       4. DATOS BÁSICOS
    =============================== */
        $serie  = (string)$venta['serie_comprobante'];
        $numero = (string)$venta['num_comprobante'];
        $fecha  = (string)$venta['fecha_hora'];

        // 01 Factura / 03 Boleta
        $tipo = ($venta['tipo_comprobante'] === 'Factura Electrónica') ? '01' : '03';

        /* ===============================
       5. CÁLCULO IGV (18%)
       (precio_venta viene CON IGV)
    =============================== */
        $igvRate   = 0.18;
        $opGravada = 0.00; // base imponible
        $totalIgv  = 0.00;

        foreach ($detalle as $item) {
            $cantidad = (float)$item['cantidad'];
            $precio   = (float)$item['precio_venta']; // CON IGV

            $base = round(($cantidad * $precio) / (1 + $igvRate), 2);
            $igv  = round($base * $igvRate, 2);

            $opGravada += $base;
            $totalIgv  += $igv;
        }

        /* ===============================
       6. CREAR XML
    =============================== */
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $invoice = $xml->createElement('Invoice');
        $invoice->setAttribute('xmlns', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $invoice->setAttribute('xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $invoice->setAttribute('xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $invoice->setAttribute('xmlns:ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        $invoice->setAttribute('xmlns:ds', 'http://www.w3.org/2000/09/xmldsig#');

        // ✅ ID SUNAT UNA SOLA VEZ (OBLIGATORIO) -> se usa en la firma (URI="#Id")
        $idSunat = $rucEmisor . '-' . $tipo . '-' . $serie . '-' . ((int)$numero);
        $invoice->setAttribute('Id', $idSunat);

        $xml->appendChild($invoice);

        /* ===============================
       CABECERA
    =============================== */
        $invoice->appendChild($xml->createElement('cbc:UBLVersionID', '2.1'));
        $invoice->appendChild($xml->createElement('cbc:CustomizationID', '2.0'));

        $numeroSinCeros = (int)$numero;

        // ✅ cbc:ID SOLO SERIE-CORREL (SIN RUC)
        $invoice->appendChild(
            $xml->createElement('cbc:ID', $serie . '-' . $numeroSinCeros)
        );

        // ✅ IssueDate correcto (AAAA-MM-DD)
        $invoice->appendChild(
            $xml->createElement('cbc:IssueDate', substr($fecha, 0, 10))
        );

        // ✅ InvoiceTypeCode con atributos SUNAT (lo que estabas poniendo)
        $invoiceType = $xml->createElement('cbc:InvoiceTypeCode', $tipo);
        $invoiceType->setAttribute('listAgencyName', 'PE:SUNAT');
        $invoiceType->setAttribute('listName', 'SUNAT:Identificador de Tipo de Documento');
        $invoiceType->setAttribute('listURI', 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01');
        $invoice->appendChild($invoiceType);

        $invoice->appendChild($xml->createElement('cbc:DocumentCurrencyCode', 'PEN'));

        // ===============================
        // SIGNATURE BLOCK (GUIA SUNAT)
        // (Esto NO es la firma XMLDSig; es el bloque UBL de referencia)
        // ===============================
        $signature = $xml->createElement('cac:Signature');
        $signature->appendChild($xml->createElement('cbc:ID', 'IDSignCF'));

        $signatoryParty = $xml->createElement('cac:SignatoryParty');
        $partyId = $xml->createElement('cac:PartyIdentification');
        $partyId->appendChild($xml->createElement('cbc:ID', $rucEmisor));
        $signatoryParty->appendChild($partyId);

        $partyName = $xml->createElement('cac:PartyName');
        $partyName->appendChild($xml->createElement('cbc:Name', $nombreEmisor));
        $signatoryParty->appendChild($partyName);

        $signature->appendChild($signatoryParty);

        $dsAttach = $xml->createElement('cac:DigitalSignatureAttachment');
        $extRef = $xml->createElement('cac:ExternalReference');
        $extRef->appendChild($xml->createElement('cbc:URI', '#SignatureCF'));
        $dsAttach->appendChild($extRef);

        $signature->appendChild($dsAttach);
        $invoice->appendChild($signature);

        /* ===============================
       EMISOR (FIJO POR AHORA)
    =============================== */
        $supplier = $xml->createElement('cac:AccountingSupplierParty');
        $party = $xml->createElement('cac:Party');

        $ruc = $xml->createElement('cbc:ID', $rucEmisor);
        $ruc->setAttribute('schemeID', '6');

        $party->appendChild(
            $xml->createElement('cac:PartyIdentification')
        )->appendChild($ruc);

        $legalEntity = $xml->createElement('cac:PartyLegalEntity');

        $regName = $xml->createElement('cbc:RegistrationName', $nombreEmisor);
        $companyId = $xml->createElement('cbc:CompanyID', $rucEmisor);
        $companyId->setAttribute('schemeID', '6');

        $legalEntity->appendChild($regName);
        $legalEntity->appendChild($companyId);

        $party->appendChild($legalEntity);

        $supplier->appendChild($party);
        $invoice->appendChild($supplier);


        // ===============================
        // CLIENTE
        // ===============================
        $customer = $xml->createElement('cac:AccountingCustomerParty');
        $partyC   = $xml->createElement('cac:Party');

        // Tipo de documento SUNAT
        $tipoDocCli = strtoupper(trim((string)$cliente['tipo_documento']));

        // Extraer número de documento de forma segura
        $rawDoc = trim((string)(
            $cliente['num_documento']
            ?? $cliente['dni']
            ?? $cliente['documento']
            ?? ''
        ));

        $numDoc = preg_replace('/[^0-9]/', '', $rawDoc);

        // ===============================
        // REGLAS SUNAT
        // ===============================

        // FACTURA → RUC OBLIGATORIO
        if ($tipo === '01' && $numDoc === '') {
            throw new Exception('Factura requiere RUC del cliente.');
        }

        // BOLETA SIN DNI → 00000000 + schemeID = 0
        if ($tipo === '03' && $numDoc === '') {
            $schemeID = '0';
            $numDoc   = '00000000';
        } else {
            $schemeID = match ($tipoDocCli) {
                'DNI', '1' => '1',
                'RUC', '6' => '6',
                'CE',  '4' => '4',
                default => '1'
            };
        }

        // XML
        $docId = $xml->createElement('cbc:ID', $numDoc);
        $docId->setAttribute('schemeID', $schemeID);

        $partyC->appendChild(
            $xml->createElement('cac:PartyIdentification')
        )->appendChild($docId);

        $customer->appendChild($partyC);
        $invoice->appendChild($customer);


        /* ===============================
       DETALLE + IGV POR ÍTEM
       ✅ AQUÍ VA PricingReference (Paso 5)
       ✅ AQUÍ VA TaxCategory/IGV (Paso 6)
    =============================== */
        $i = 1;
        foreach ($detalle as $item) {

            $cantidad    = (float)$item['cantidad'];
            $precio      = (float)$item['precio_venta']; // CON IGV
            $descripcion = (string)$item['nombre_articulo'];

            // Base sin IGV e IGV
            $base = round(($cantidad * $precio) / (1 + $igvRate), 2);
            $igv  = round($base * $igvRate, 2);

            $line = $xml->createElement('cac:InvoiceLine');
            $line->appendChild($xml->createElement('cbc:ID', $i));

            $qty = $xml->createElement('cbc:InvoicedQuantity', number_format($cantidad, 2, '.', ''));
            $qty->setAttribute('unitCode', 'NIU');
            $line->appendChild($qty);

            $amount = $xml->createElement(
                'cbc:LineExtensionAmount',
                number_format($base, 2, '.', '')
            );
            $amount->setAttribute('currencyID', 'PEN');
            $line->appendChild($amount);

            // ======================================================
            // ✅ PASO 5: PricingReference (precio unitario CON IGV)
            // ======================================================
            $pricingRef = $xml->createElement('cac:PricingReference');

            $altCondPrice = $xml->createElement('cac:AlternativeConditionPrice');

            $priceWithIgv = $xml->createElement(
                'cbc:PriceAmount',
                number_format($precio, 2, '.', '')
            );
            $priceWithIgv->setAttribute('currencyID', 'PEN');

            // 01 = Precio unitario con IGV (catálogo SUNAT)
            $priceType = $xml->createElement('cbc:PriceTypeCode', '01');

            $altCondPrice->appendChild($priceWithIgv);
            $altCondPrice->appendChild($priceType);
            $pricingRef->appendChild($altCondPrice);

            $line->appendChild($pricingRef);

            // ======================================================
            // ✅ PASO 6: IGV por línea (TaxTotal -> TaxSubtotal -> TaxCategory)
            // ======================================================
            $taxTotal = $xml->createElement('cac:TaxTotal');

            $taxAmount = $xml->createElement(
                'cbc:TaxAmount',
                number_format($igv, 2, '.', '')
            );
            $taxAmount->setAttribute('currencyID', 'PEN');
            $taxTotal->appendChild($taxAmount);

            $taxSubtotal = $xml->createElement('cac:TaxSubtotal');

            $taxable = $xml->createElement(
                'cbc:TaxableAmount',
                number_format($base, 2, '.', '')
            );
            $taxable->setAttribute('currencyID', 'PEN');
            $taxSubtotal->appendChild($taxable);

            $taxIgv = $xml->createElement(
                'cbc:TaxAmount',
                number_format($igv, 2, '.', '')
            );
            $taxIgv->setAttribute('currencyID', 'PEN');
            $taxSubtotal->appendChild($taxIgv);

            $taxCategory = $xml->createElement('cac:TaxCategory');
            $taxCategory->appendChild($xml->createElement('cbc:ID', 'S'));
            $taxCategory->appendChild($xml->createElement('cbc:Percent', '18.00'));
            $taxCategory->appendChild($xml->createElement('cbc:TaxExemptionReasonCode', '10'));
            $taxScheme = $xml->createElement('cac:TaxScheme');
            $taxScheme->appendChild($xml->createElement('cbc:ID', '1000'));
            $taxScheme->appendChild($xml->createElement('cbc:Name', 'IGV'));
            $taxScheme->appendChild($xml->createElement('cbc:TaxTypeCode', 'VAT'));

            $taxCategory->appendChild($taxScheme);

            $taxSubtotal->appendChild($taxCategory);

            $taxTotal->appendChild($taxSubtotal);
            $line->appendChild($taxTotal);

            // Item
            $line->appendChild(
                $xml->createElement('cac:Item')
            )->appendChild(
                $xml->createElement('cbc:Description', $descripcion)
            );

            // Price (precio unitario CON IGV)
            $precioSinIgv = round($precio / 1.18, 6);

            $priceAmount = $xml->createElement(
                'cbc:PriceAmount',
                number_format($precioSinIgv, 6, '.', '')
            );

            $priceAmount->setAttribute('currencyID', 'PEN');

            $line->appendChild(
                $xml->createElement('cac:Price')
            )->appendChild($priceAmount);

            $invoice->appendChild($line);
            $i++;
        }

        /* ===============================
       IGV TOTAL DEL DOCUMENTO
    =============================== */
        $taxTotalDoc = $xml->createElement('cac:TaxTotal');

        $taxAmountDoc = $xml->createElement(
            'cbc:TaxAmount',
            number_format($totalIgv, 2, '.', '')
        );
        $taxAmountDoc->setAttribute('currencyID', 'PEN');
        $taxTotalDoc->appendChild($taxAmountDoc);

        $taxSubtotalDoc = $xml->createElement('cac:TaxSubtotal');

        $taxableDoc = $xml->createElement(
            'cbc:TaxableAmount',
            number_format($opGravada, 2, '.', '')
        );
        $taxableDoc->setAttribute('currencyID', 'PEN');
        $taxSubtotalDoc->appendChild($taxableDoc);

        $taxIgvDoc = $xml->createElement(
            'cbc:TaxAmount',
            number_format($totalIgv, 2, '.', '')
        );
        $taxIgvDoc->setAttribute('currencyID', 'PEN');
        $taxSubtotalDoc->appendChild($taxIgvDoc);
        $taxCategoryDoc = $xml->createElement('cac:TaxCategory');
        $taxCategoryDoc->appendChild($xml->createElement('cbc:ID', 'S'));
        $taxCategoryDoc->appendChild($xml->createElement('cbc:Percent', '18.00'));
        $taxCategoryDoc->appendChild($xml->createElement('cbc:TaxExemptionReasonCode', '10'));


        $taxSchemeDoc = $xml->createElement('cac:TaxScheme');
        $taxSchemeDoc->appendChild($xml->createElement('cbc:ID', '1000'));
        $taxSchemeDoc->appendChild($xml->createElement('cbc:Name', 'IGV'));
        $taxSchemeDoc->appendChild($xml->createElement('cbc:TaxTypeCode', 'VAT'));

        $taxCategoryDoc->appendChild($taxSchemeDoc);
        $taxSubtotalDoc->appendChild($taxCategoryDoc);
        $taxTotalDoc->appendChild($taxSubtotalDoc);
        $invoice->appendChild($taxTotalDoc);

        /* ===============================
       TOTAL A PAGAR
    =============================== */
        $total = $xml->createElement('cac:LegalMonetaryTotal');

        $lineExt = $xml->createElement(
            'cbc:LineExtensionAmount',
            number_format($opGravada, 2, '.', '')
        );
        $lineExt->setAttribute('currencyID', 'PEN');

        $taxIncl = $xml->createElement(
            'cbc:TaxInclusiveAmount',
            number_format((float)$venta['total_venta'], 2, '.', '')
        );
        $taxIncl->setAttribute('currencyID', 'PEN');

        $payable = $xml->createElement(
            'cbc:PayableAmount',
            number_format((float)$venta['total_venta'], 2, '.', '')
        );
        $payable->setAttribute('currencyID', 'PEN');

        $total->appendChild($lineExt);
        $total->appendChild($taxIncl);
        $total->appendChild($payable);

        $invoice->appendChild($total);


        /* ===============================
       GUARDAR + FIRMAR
    =============================== */
        $rutaAbsoluta = $this->getRutaXML(
            $rucEmisor,
            $tipo,
            $serie,
            $numero,
            $fecha
        );

        // FIRMAR (inserta ext:UBLExtensions + ds:Signature dentro)
        $this->firmarXML($xml);

        // Guardar firmado
        $xml->save($rutaAbsoluta);

        return str_replace(__DIR__ . '/../', '', $rutaAbsoluta);
    }


    /* ===============================
       RUTA ESCALABLE DE XML
    =============================== */
    private function getRutaXML($ruc, $tipo, $serie, $numero, $fecha)
    {
        $year  = date('Y', strtotime($fecha));
        $month = date('m', strtotime($fecha));

        $tipoDir = ($tipo === '01') ? 'factura' : 'boleta';

        $base = __DIR__ . "/../xml/$ruc/$year/$month/$tipoDir/";

        if (!is_dir($base)) {
            mkdir($base, 0777, true);
        }

        $numeroSinCeros = (int)$numero;
        return $base . "20609068800-$tipo-$serie-$numeroSinCeros.XML";
    }

    private function firmarXML(DOMDocument $doc): void
    {
        require_once __DIR__ . '/../vendor/autoload.php';

        // ===============================
        // 1) Cargar certificado P12
        // ===============================
        $pfxPath = realpath(__DIR__ . '/../certificado/cert.p12');
        if ($pfxPath === false || !file_exists($pfxPath)) {
            throw new Exception('No se encontró el certificado P12 en /certificado/cert.p12');
        }

        $pfxContent = file_get_contents($pfxPath);
        if ($pfxContent === false || trim($pfxContent) === '') {
            throw new Exception('No se pudo leer el certificado P12.');
        }

        $certs = [];
        $pfxPassword = 'Felicity1'; // <-- tu clave
        if (!openssl_pkcs12_read($pfxContent, $certs, $pfxPassword)) {
            $errors = [];
            while ($msg = openssl_error_string()) {
                $errors[] = $msg;
            }
            throw new Exception('Error abriendo P12: ' . (count($errors) ? implode(' | ', $errors) : 'sin detalle'));
        }

        if (empty($certs['pkey']) || empty($certs['cert'])) {
            throw new Exception('El P12 no contiene llave privada o certificado.');
        }

        // ===============================
        // 2) Normalizar XML antes de firmar
        // ===============================
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        $root = $doc->documentElement;
        if (!$root) {
            throw new Exception('XML inválido: no hay nodo raíz.');
        }

        // ===============================
        // 3) Validar Id SUNAT y registrarlo como ID real
        // ===============================
        $idSunat = $root->getAttribute('Id');
        if (empty($idSunat)) {
            throw new Exception('Invoice SIN atributo Id (Id SUNAT).');
        }

        // CLAVE: registrar el atributo Id como tipo ID para que funcione URI="#..."
        $root->setIdAttribute('Id', true);

        // ===============================
        // 4) Asegurar UBLExtensions / ExtensionContent
        // ===============================
        $contentNode = null;

        // Buscar si ya existe ext:ExtensionContent
        $extContents = $doc->getElementsByTagNameNS(
            'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
            'ExtensionContent'
        );

        if ($extContents->length > 0) {
            $contentNode = $extContents->item(0);
        } else {
            // Crear UBLExtensions al inicio (como guía)
            $ublExtensions = $doc->createElement('ext:UBLExtensions');
            $ublExtension  = $doc->createElement('ext:UBLExtension');
            $contentNode   = $doc->createElement('ext:ExtensionContent');

            $ublExtension->appendChild($contentNode);
            $ublExtensions->appendChild($ublExtension);

            $root->insertBefore($ublExtensions, $root->firstChild);
        }

        if (!$contentNode) {
            throw new Exception('No se pudo obtener/crear ext:ExtensionContent.');
        }

        // ===============================
        // 5) Firmar (XMLSecLibs)
        // ===============================
        $dsig = new \RobRichards\XMLSecLibs\XMLSecurityDSig();
        $dsig->setCanonicalMethod(\RobRichards\XMLSecLibs\XMLSecurityDSig::EXC_C14N);

        // Referencia al root usando el Id SUNAT
        $dsig->addReference(
            $root,
            \RobRichards\XMLSecLibs\XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['uri' => '#' . $idSunat]
        );

        $key = new \RobRichards\XMLSecLibs\XMLSecurityKey(
            \RobRichards\XMLSecLibs\XMLSecurityKey::RSA_SHA256,
            ['type' => 'private']
        );
        $key->loadKey($certs['pkey'], false);

        // Firma dentro de ExtensionContent
        $dsig->sign($key, $contentNode);

        // Agregar certificado X509
        $dsig->add509Cert($certs['cert'], true, false, ['subjectName' => true]);

        // ===============================
        // 6) Poner Id al ds:Signature para que calce con #SignatureCF
        // ===============================
        $signatures = $doc->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature');
        if ($signatures->length > 0) {
            $signatures->item(0)->setAttribute('Id', 'SignatureCF');
        }
    }
}
