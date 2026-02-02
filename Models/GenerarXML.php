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

        /* ===============================
           4. DATOS BÁSICOS
        =============================== */
        $serie  = $venta['serie_comprobante'];
        $numero = $venta['num_comprobante'];
        $fecha  = $venta['fecha_hora'];

        $tipo = ($venta['tipo_comprobante'] === 'Factura Electrónica') ? '01' : '03';

        /* ===============================
           5. CÁLCULO IGV (18%)
        =============================== */
        $igvRate = 0.18;
        $opGravada = 0;
        $totalIgv  = 0;

        foreach ($detalle as $item) {
            $cantidad = (float)$item['cantidad'];
            $precio   = (float)$item['precio_venta']; // precio con IGV

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

        /* 🔐 Namespaces obligatorios para firma digital */
        $invoice->setAttribute('xmlns:ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        $invoice->setAttribute('xmlns:ds', 'http://www.w3.org/2000/09/xmldsig#');

        /* 🔑 ID del nodo raíz (SUNAT lo exige para firmar) */
        $invoice->setAttribute('Id', 'SignSUNAT');

        $xml->appendChild($invoice);


        /* ===============================
           CABECERA
        =============================== */
        $invoice->appendChild($xml->createElement('cbc:UBLVersionID', '2.1'));
        $invoice->appendChild($xml->createElement('cbc:CustomizationID', '2.0'));
        $invoice->appendChild($xml->createElement('cbc:ID', "$serie-$numero"));
        $invoice->appendChild($xml->createElement('cbc:IssueDate', substr($fecha, 0, 10)));

        $invoiceType = $xml->createElement('cbc:InvoiceTypeCode', $tipo);
        $invoiceType->setAttribute('listID', '0101');
        $invoice->appendChild($invoiceType);

        $invoice->appendChild($xml->createElement('cbc:DocumentCurrencyCode', 'PEN'));

        /* ===============================
           EMISOR (FIJO POR AHORA)
        =============================== */
        $supplier = $xml->createElement('cac:AccountingSupplierParty');
        $party = $xml->createElement('cac:Party');

        $ruc = $xml->createElement('cbc:ID', '20123456789');
        $ruc->setAttribute('schemeID', '6');

        $party->appendChild(
            $xml->createElement('cac:PartyIdentification')
        )->appendChild($ruc);

        $party->appendChild(
            $xml->createElement('cac:PartyName')
        )->appendChild(
            $xml->createElement('cbc:Name', 'MI EMPRESA SAC')
        );

        $supplier->appendChild($party);
        $invoice->appendChild($supplier);

        /* ===============================
           CLIENTE
        =============================== */
        $customer = $xml->createElement('cac:AccountingCustomerParty');
        $partyC = $xml->createElement('cac:Party');

        $doc = $xml->createElement('cbc:ID', $cliente['num_documento']);
        $doc->setAttribute('schemeID', $cliente['tipo_documento']);

        $partyC->appendChild(
            $xml->createElement('cac:PartyIdentification')
        )->appendChild($doc);

        $partyC->appendChild(
            $xml->createElement('cac:PartyName')
        )->appendChild(
            $xml->createElement('cbc:Name', $cliente['nombre'])
        );

        $customer->appendChild($partyC);
        $invoice->appendChild($customer);

        /* ===============================
           DETALLE + IGV POR ÍTEM
        =============================== */
        $i = 1;
        foreach ($detalle as $item) {

            $cantidad = (float)$item['cantidad'];
            $precio   = (float)$item['precio_venta'];
            $descripcion = $item['nombre_articulo'];

            $base = round(($cantidad * $precio) / (1 + $igvRate), 2);
            $igv  = round($base * $igvRate, 2);

            $line = $xml->createElement('cac:InvoiceLine');
            $line->appendChild($xml->createElement('cbc:ID', $i));

            $qty = $xml->createElement('cbc:InvoicedQuantity', $cantidad);
            $qty->setAttribute('unitCode', 'NIU');
            $line->appendChild($qty);

            $amount = $xml->createElement(
                'cbc:LineExtensionAmount',
                number_format($base, 2, '.', '')
            );
            $amount->setAttribute('currencyID', 'PEN');
            $line->appendChild($amount);

            /* IGV POR LÍNEA */
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

            $taxScheme = $xml->createElement('cac:TaxScheme');
            $taxScheme->appendChild($xml->createElement('cbc:ID', '1000'));
            $taxScheme->appendChild($xml->createElement('cbc:Name', 'IGV'));
            $taxScheme->appendChild($xml->createElement('cbc:TaxTypeCode', 'VAT'));

            $taxCategory->appendChild($taxScheme);
            $taxSubtotal->appendChild($taxCategory);
            $taxTotal->appendChild($taxSubtotal);
            $line->appendChild($taxTotal);

            $line->appendChild(
                $xml->createElement('cac:Item')
            )->appendChild(
                $xml->createElement('cbc:Description', $descripcion)
            );

            $price = $xml->createElement(
                'cbc:PriceAmount',
                number_format($precio, 2, '.', '')
            );
            $price->setAttribute('currencyID', 'PEN');

            $line->appendChild(
                $xml->createElement('cac:Price')
            )->appendChild($price);

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
        $payable = $xml->createElement(
            'cbc:PayableAmount',
            number_format($venta['total_venta'], 2, '.', '')
        );
        $payable->setAttribute('currencyID', 'PEN');
        $total->appendChild($payable);
        $invoice->appendChild($total);

        /* ===============================
           GUARDAR XML
        =============================== */
        $rutaAbsoluta = $this->getRutaXML(
            '20123456789',
            $tipo,
            $serie,
            $numero,
            $fecha
        );

        // ===============================
        // FIRMAR XML
        // ===============================
        $hash = $this->firmarXML($xml);

        // ===============================
        // GUARDAR XML FIRMADO
        // ===============================
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

        return $base . "$serie-$numero.xml";
    }

    private function firmarXML(DOMDocument $doc): string
    {
        require_once __DIR__ . '/../vendor/autoload.php';

        $pfxPath = realpath(__DIR__ . '/../certificado/cert.p12');

        if ($pfxPath === false) {
            throw new Exception('No se encontró el certificado P12');
        }
        
        $pfxPassword = 'Felicity1'; // 👈 cambia esto

        $pfxContent = file_get_contents($pfxPath);
        if ($pfxContent === false) {
            throw new Exception('No se pudo leer el certificado P12');
        }

        $certs = [];
        if (!openssl_pkcs12_read($pfxContent, $certs, $pfxPassword)) {
            throw new Exception('Error al abrir el certificado P12');
        }

        $privateKey = $certs['pkey'];
        $publicCert = $certs['cert'];

        $root = $doc->documentElement; // Invoice

        // ext:UBLExtensions (debe ser el primer nodo)
        $ublExtensions = $doc->createElement('ext:UBLExtensions');
        $ext = $doc->createElement('ext:UBLExtension');
        $content = $doc->createElement('ext:ExtensionContent');

        $ext->appendChild($content);
        $ublExtensions->appendChild($ext);
        $root->insertBefore($ublExtensions, $root->firstChild);

        $objDSig = new \RobRichards\XMLSecLibs\XMLSecurityDSig();
        $objDSig->setCanonicalMethod(
            \RobRichards\XMLSecLibs\XMLSecurityDSig::EXC_C14N
        );

        $objDSig->addReference(
            $root,
            \RobRichards\XMLSecLibs\XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['uri' => '#SignSUNAT']
        );

        $objKey = new \RobRichards\XMLSecLibs\XMLSecurityKey(
            \RobRichards\XMLSecLibs\XMLSecurityKey::RSA_SHA256,
            ['type' => 'private']
        );
        $objKey->loadKey($privateKey, false);

        $objDSig->sign($objKey, $content);
        $objDSig->add509Cert($publicCert, true, false, ['subjectName' => true]);

        // devolver hash del XML firmado
        return base64_encode(
            hash('sha256', $doc->saveXML(), true)
        );
    }
}
