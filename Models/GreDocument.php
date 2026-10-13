<?php

declare(strict_types=1);

/**
 * Adaptador UBL DespatchAdvice 2.1 / GRE 2.0 para el formato de nodos
 * documentBody que emplea el conector APISUNAT de este proyecto.
 * El contrato debe probarse en DESARROLLO con el proveedor antes de producción.
 */
final class GreDocument
{
    private static function el(string $texto, array $attrs = []): array
    {
        return $attrs ? ['_attributes' => $attrs, '_text' => $texto] : ['_text' => $texto];
    }

    private static function idParty(string $tipo, string $numero, string $nombre): array
    {
        return [
            'cac:PartyIdentification' => ['cbc:ID' => self::el($numero, [
                'schemeID' => self::tipoIdentidad($tipo),
                'schemeName' => 'Documento de Identidad',
                'schemeAgencyName' => 'PE:SUNAT',
                'schemeURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06'
            ])],
            'cac:PartyLegalEntity' => ['cbc:RegistrationName' => self::el($nombre)]
        ];
    }

    private static function tipoIdentidad(string $tipo): string
    {
        $ids = ['RUC' => '6', '6' => '6', 'DNI' => '1', '1' => '1', 'CE' => '4', '4' => '4', 'PASAPORTE' => '7', '7' => '7'];
        $t = strtoupper(trim($tipo));
        if (!isset($ids[$t])) throw new InvalidArgumentException('Documento de identidad sin equivalencia SUNAT.');
        return $ids[$t];
    }

    private static function direccion(string $ubigeo, string $direccion, bool $salida = false): array
    {
        return [
            'cbc:ID' => self::el($ubigeo, ['schemeAgencyName' => 'PE:INEI', 'schemeName' => 'Ubigeos']),
            'cac:AddressLine' => ['cbc:Line' => self::el($direccion)]
        ];
    }

    /** @param array{guia:array,items:array,documentos:array} $datos */
    public static function construir(array $datos, string $serie, int $correlativo, DateTimeImmutable $emision): array
    {
        $g = $datos['guia'];
        $items = $datos['items'];
        if (!$items || !preg_match('/^T[A-Z0-9]{3}$/D', $serie) || $correlativo < 1 || $correlativo > 99999999) {
            throw new InvalidArgumentException('No es posible construir la GRE sin productos, serie y correlativo válidos.');
        }
        $ruc = trim((string)$g['emisor_ruc']);
        if (!preg_match('/^\d{11}$/D', $ruc)) throw new InvalidArgumentException('Configure un RUC emisor válido.');
        $numero = str_pad((string)$correlativo, 8, '0', STR_PAD_LEFT);
        $transport = [
            'cbc:TransportModeCode' => self::el((string)$g['modalidad_transporte'], [
                'listName' => 'Modalidad de traslado', 'listAgencyName' => 'PE:SUNAT',
                'listURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo18'
            ]),
            'cac:TransitPeriod' => ['cbc:StartDate' => self::el((string)$g['fecha_inicio_traslado'])]
        ];
        if ($g['modalidad_transporte'] === '01') {
            $transport['cac:CarrierParty'] = self::idParty('RUC', (string)$g['transportista_ruc'], (string)$g['transportista_nombre']);
        } else {
            // SUNAT exige nombres y apellidos separados. No inferirlos cuando son ambiguos.
            $nombreConductor = preg_split('/\s+/u', trim((string)$g['conductor_nombre']));
            if (!is_array($nombreConductor) || count($nombreConductor) < 2) {
                throw new InvalidArgumentException('El conductor necesita nombres y apellidos; registre al menos dos palabras.');
            }
            $apellido = array_pop($nombreConductor);
            $nombres = implode(' ', $nombreConductor);
            $transport['cac:DriverPerson'] = [
                'cbc:ID' => self::el((string)$g['conductor_num_documento'], [
                    'schemeID' => self::tipoIdentidad((string)$g['conductor_tipo_documento']),
                    'schemeName' => 'Documento de Identidad', 'schemeAgencyName' => 'PE:SUNAT'
                ]),
                'cbc:FirstName' => self::el($nombres),
                'cbc:FamilyName' => self::el($apellido),
                'cbc:JobTitle' => self::el('Principal'),
                'cac:IdentityDocumentReference' => ['cbc:ID' => self::el((string)$g['conductor_licencia'])]
            ];
        }
        $shipment = [
            'cbc:ID' => self::el('SUNAT_Envio'),
            'cbc:HandlingCode' => self::el((string)$g['motivo_traslado'], [
                'listAgencyName' => 'PE:SUNAT', 'listName' => 'Motivo de traslado',
                'listURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo20'
            ]),
            'cbc:GrossWeightMeasure' => self::el(number_format((float)$g['peso_bruto'], 3, '.', ''), ['unitCode' => 'KGM'])
        ];
        if (trim((string)$g['observacion']) !== '') $shipment['cbc:HandlingInstructions'] = self::el((string)$g['observacion']);
        if ($g['numero_bultos'] !== null && (int)$g['numero_bultos'] > 0) {
            $shipment['cbc:TotalTransportHandlingUnitQuantity'] = self::el((string)(int)$g['numero_bultos']);
        }
        $shipment['cac:ShipmentStage'] = $transport;
        $shipment['cac:Delivery'] = [
            'cac:DeliveryAddress' => self::direccion((string)$g['llegada_ubigeo'], (string)$g['llegada_direccion']),
            'cac:Despatch' => ['cac:DespatchAddress' => self::direccion((string)$g['partida_ubigeo'], (string)$g['partida_direccion'], true)]
        ];
        if ($g['modalidad_transporte'] === '02') {
            $shipment['cac:TransportHandlingUnit'] = [
                'cac:TransportEquipment' => ['cbc:ID' => self::el((string)$g['vehiculo_placa'])]
            ];
        }
        $lineas = [];
        $ordinal = 0;
        foreach ($items as $item) {
            $ordinal++;
            if ((float)$item['cantidad'] <= 0 || trim((string)$item['descripcion']) === '') {
                throw new InvalidArgumentException('Detalle inválido en la línea '.$ordinal.'.');
            }
            $lineas[] = [
                'cbc:ID' => self::el((string)$ordinal),
                'cbc:DeliveredQuantity' => self::el(number_format((float)$item['cantidad'], 3, '.', ''), [
                    'unitCode' => (string)$item['unidad_sunat']
                ]),
                'cac:OrderLineReference' => ['cbc:LineID' => self::el((string)$ordinal)],
                'cac:Item' => [
                    'cbc:Description' => self::el((string)$item['descripcion']),
                    'cac:SellersItemIdentification' => ['cbc:ID' => self::el((string)($item['codigo'] ?: 'ITEM-'.$ordinal))]
                ]
            ];
        }
        $body = [
            'cbc:UBLVersionID' => self::el('2.1'),
            'cbc:CustomizationID' => self::el('2.0'),
            'cbc:ID' => self::el($serie.'-'.$numero),
            'cbc:IssueDate' => self::el($emision->format('Y-m-d')),
            'cbc:IssueTime' => self::el($emision->format('H:i:s')),
            'cbc:DespatchAdviceTypeCode' => self::el('09', [
                'listAgencyName' => 'PE:SUNAT', 'listName' => 'Tipo de Documento',
                'listURI' => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01'
            ]),
            'cac:Signature' => [
                'cbc:ID' => self::el($ruc),
                'cac:SignatoryParty' => [
                    'cac:PartyIdentification' => ['cbc:ID' => self::el($ruc)],
                    'cac:PartyName' => ['cbc:Name' => self::el((string)$g['emisor_nombre'])]
                ],
                'cac:DigitalSignatureAttachment' => ['cac:ExternalReference' => ['cbc:URI' => self::el('#TIQUEPOS-GRE-SIGN')]]
            ],
            'cac:DespatchSupplierParty' => ['cac:Party' => self::idParty('RUC', $ruc, (string)$g['emisor_nombre'])],
            'cac:DeliveryCustomerParty' => ['cac:Party' => self::idParty((string)$g['destinatario_tipo_documento'], (string)$g['destinatario_num_documento'], (string)$g['destinatario_nombre'])],
            'cac:Shipment' => $shipment,
            'cac:DespatchLine' => $lineas
        ];
        $referencias = [];
        foreach ($datos['documentos'] as $doc) {
            $tipo = trim((string)$doc['tipo_documento']);
            if (!in_array($tipo, ['01','03'], true)) continue;
            $referencias[] = [
                'cbc:ID' => self::el((string)$doc['serie'].'-'.str_pad((string)(int)$doc['numero'], 8, '0', STR_PAD_LEFT)),
                'cbc:DocumentTypeCode' => self::el($tipo)
            ];
        }
        if ($referencias) {
            $a = array_slice($body, 0, 6, true);
            $a['cac:AdditionalDocumentReference'] = $referencias;
            $body = $a + array_slice($body, 6, null, true);
        }
        return [
            'fileName' => $ruc.'-09-'.$serie.'-'.$numero,
            'documentBody' => $body,
            'metadata' => ['serie' => $serie, 'correlativo' => $correlativo, 'tipo' => '09']
        ];
    }
}
