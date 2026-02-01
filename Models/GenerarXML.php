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
        // ===============================
        // 1. OBTENER VENTA
        // ===============================
        $venta = $this->conexion->getData(
            "SELECT * FROM venta WHERE idventa = ?",
            [$idventa]
        );

        if (!$venta) {
            return false;
        }

        // ===============================
        // 2. OBTENER CLIENTE
        // ===============================
        $cliente = $this->conexion->getData(
            "SELECT * FROM persona WHERE idpersona = ?",
            [$venta['idcliente']]
        );

        if (!$cliente) {
            return false;
        }

        // ===============================
        // 3. OBTENER DETALLE
        // ===============================
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
        // 4. DATOS BÁSICOS
        // ===============================
        $serie  = $venta['serie_comprobante'];
        $numero = $venta['num_comprobante'];
        $fecha  = $venta['fecha_hora'];

        $tipo = ($venta['tipo_comprobante'] === 'Factura Electrónica') ? '01' : '03';

        // ===============================
        // 5. CREAR XML
        // ===============================
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $invoice = $xml->createElement('Invoice');
        $invoice->setAttribute('xmlns', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $invoice->setAttribute('xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $invoice->setAttribute('xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $xml->appendChild($invoice);

        // ===============================
        // CABECERA
        // ===============================
        $invoice->appendChild($xml->createElement('cbc:UBLVersionID', '2.1'));
        $invoice->appendChild($xml->createElement('cbc:CustomizationID', '2.0'));
        $invoice->appendChild($xml->createElement('cbc:ID', "$serie-$numero"));
        $invoice->appendChild($xml->createElement('cbc:IssueDate', substr($fecha, 0, 10)));
        $invoice->appendChild($xml->createElement('cbc:InvoiceTypeCode', $tipo));
        $invoice->appendChild($xml->createElement('cbc:DocumentCurrencyCode', 'PEN'));

        // ===============================
        // EMISOR (FIJO POR AHORA)
        // ===============================
        $supplier = $xml->createElement('cac:AccountingSupplierParty');
        $party = $xml->createElement('cac:Party');

        $ruc = $xml->createElement('cbc:ID', '20123456789'); // TODO: traer de BD
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

        // ===============================
        // CLIENTE
        // ===============================
        $customer = $xml->createElement('cac:AccountingCustomerParty');
        $partyC = $xml->createElement('cac:Party');

        $doc = $xml->createElement('cbc:ID', $cliente['num_documento']);
        $doc->setAttribute('schemeID', $cliente['tipo_documento']); // 1 DNI / 6 RUC

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

        // ===============================
        // DETALLE
        // ===============================
        $i = 1;
        foreach ($detalle as $item) {

            $cantidad = (float) $item['cantidad'];
            $precio   = (float) $item['precio_venta'];
            $subtotal = $cantidad * $precio;

            $descripcion = $item['nombre_articulo'];

            $line = $xml->createElement('cac:InvoiceLine');
            $line->appendChild($xml->createElement('cbc:ID', $i));

            $qty = $xml->createElement('cbc:InvoicedQuantity', $cantidad);
            $qty->setAttribute('unitCode', 'NIU');
            $line->appendChild($qty);

            $amount = $xml->createElement(
                'cbc:LineExtensionAmount',
                number_format($subtotal, 2, '.', '')
            );
            $amount->setAttribute('currencyID', 'PEN');
            $line->appendChild($amount);

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

        // ===============================
        // TOTAL
        // ===============================
        $total = $xml->createElement('cac:LegalMonetaryTotal');
        $payable = $xml->createElement(
            'cbc:PayableAmount',
            number_format($venta['total_venta'], 2, '.', '')
        );
        $payable->setAttribute('currencyID', 'PEN');
        $total->appendChild($payable);
        $invoice->appendChild($total);

        // ===============================
        // GUARDAR XML (ESCALABLE)
        // ===============================
        $rutaAbsoluta = $this->getRutaXML(
            '20123456789', // RUC (luego desde BD)
            $tipo,
            $serie,
            $numero,
            $fecha
        );

        $xml->save($rutaAbsoluta);

        // devolver ruta relativa para BD
        return str_replace(__DIR__ . '/../', '', $rutaAbsoluta);
    }

    // ===============================
    // RUTA ESCALABLE DE XML
    // ===============================
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
}
