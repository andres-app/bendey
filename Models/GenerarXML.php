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
        // Composer autoload (una sola vez por request está bien, aquí lo dejo por seguridad)
        require_once __DIR__ . '/../vendor/autoload.php';
    
        // 1) Ruta absoluta del P12
        $pfxPath = __DIR__ . '/../certificado/cert.p12';
        $pfxPath = realpath($pfxPath);
    
        if ($pfxPath === false || !file_exists($pfxPath)) {
            throw new Exception('No se encontró el certificado P12: ' . (__DIR__ . '/../certificado/cert.p12'));
        }
    
        // 2) Leer P12
        $pfxContent = file_get_contents($pfxPath);
        if ($pfxContent === false || strlen($pfxContent) === 0) {
            throw new Exception('PHP no pudo leer el certificado P12: ' . $pfxPath);
        }
    
        // 3) Password EXACTO (ojo con espacios)
        $pfxPassword = 'Felicity1';
    
        // 4) Extraer llaves del P12
        $certs = [];
        $ok = openssl_pkcs12_read($pfxContent, $certs, $pfxPassword);
    
        if (!$ok) {
            // Mensaje OpenSSL real
            $errors = [];
            while ($msg = openssl_error_string()) {
                $errors[] = $msg;
            }
            throw new Exception(
                "Error al abrir el certificado P12 (clave o formato). " .
                "Archivo: {$pfxPath}. " .
                "OpenSSL: " . (count($errors) ? implode(' | ', $errors) : 'sin detalle')
            );
        }
    
        if (empty($certs['pkey']) || empty($certs['cert'])) {
            throw new Exception('El P12 se abrió, pero no contiene llave privada o certificado público (pkey/cert).');
        }
    
        $privateKey = $certs['pkey']; // PEM de la llave privada
        $publicCert = $certs['cert']; // PEM del certificado público
    
        // 5) Asegurar formato XML correcto
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;
    
        $root = $doc->documentElement;
        if (!$root) {
            throw new Exception('XML inválido: no se encontró documentElement.');
        }
    
        // 6) Insertar ext:UBLExtensions como PRIMER nodo (SUNAT)
        // Si ya existe, NO lo duplicamos
        $existing = $doc->getElementsByTagName('UBLExtensions');
        if ($existing->length === 0) {
            $ublExtensions = $doc->createElement('ext:UBLExtensions');
            $ext = $doc->createElement('ext:UBLExtension');
            $content = $doc->createElement('ext:ExtensionContent');
    
            $ext->appendChild($content);
            $ublExtensions->appendChild($ext);
    
            $root->insertBefore($ublExtensions, $root->firstChild);
        } else {
            // Si existe, usa el primer ExtensionContent
            $contentNodes = $doc->getElementsByTagName('ExtensionContent');
            if ($contentNodes->length === 0) {
                throw new Exception('Ya existe UBLExtensions pero no se encontró ExtensionContent.');
            }
            $content = $contentNodes->item(0);
        }
    
        // 7) Crear firma XMLDSIG
        $objDSig = new \RobRichards\XMLSecLibs\XMLSecurityDSig();
        $objDSig->setCanonicalMethod(\RobRichards\XMLSecLibs\XMLSecurityDSig::EXC_C14N);
    
        // IMPORTANTE: SUNAT suele esperar el ID de referencia.
        // Si tu root NO tiene ID="SignSUNAT", lo ponemos.
        if (!$root->hasAttribute('Id')) {
            $root->setAttribute('Id', 'SignSUNAT');
        }
    
        // Agregar referencia
        $objDSig->addReference(
            $root,
            \RobRichards\XMLSecLibs\XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['uri' => '#SignSUNAT']
        );
    
        // 8) Firmar con RSA SHA256
        $objKey = new \RobRichards\XMLSecLibs\XMLSecurityKey(
            \RobRichards\XMLSecLibs\XMLSecurityKey::RSA_SHA256,
            ['type' => 'private']
        );
    
        // Cargar llave privada desde string PEM
        $objKey->loadKey($privateKey, false);
    
        // Firmar insertando dentro de ExtensionContent
        $objDSig->sign($objKey, $content);
    
        // Agregar certificado al XML
        $objDSig->add509Cert($publicCert, true, false, ['subjectName' => true]);
    
        // 9) Retornar hash del XML firmado (Digest SHA256 base64)
        $signedXml = $doc->saveXML();
        if ($signedXml === false) {
            throw new Exception('No se pudo serializar el XML firmado.');
        }
    
        return base64_encode(hash('sha256', $signedXml, true));
    }    
    
}
