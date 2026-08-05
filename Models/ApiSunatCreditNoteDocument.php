<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';
require_once __DIR__ . '/CreditNote.php';

/**
 * Construye el documentBody UBL 2.1 para una Nota de Crédito (tipo 07).
 */
class ApiSunatCreditNoteDocument
{
    private Conexion $conexion;
    private CreditNote $notas;


    public function __construct(
        ?Conexion $conexion = null,
        ?CreditNote $notas = null
    ) {
        $this->conexion = $conexion instanceof Conexion
            ? $conexion
            : new Conexion();

        $this->notas = $notas instanceof CreditNote
            ? $notas
            : new CreditNote($this->conexion);
    }


    public function construir(int $idnotaCredito): array
    {
        $notaCompleta = $this->notas->obtenerNota($idnotaCredito);

        if (!is_array($notaCompleta)) {
            throw new RuntimeException(
                'No se encontró la nota de crédito.'
            );
        }

        $nota = $notaCompleta['cabecera'];
        $detalles = is_array($notaCompleta['detalles'] ?? null)
            ? $notaCompleta['detalles']
            : [];

        if ((string)$nota['estado'] === 'ANULADA') {
            throw new RuntimeException(
                'No se puede enviar una nota de crédito anulada.'
            );
        }
        if (count($detalles) === 0) {
            throw new RuntimeException(
                'La nota de crédito no tiene productos.'
            );
        }

        $empresa = $this->obtenerEmpresa();
        $rucEmisor = $this->obtenerRucEmpresa($empresa);
        $razonSocial = trim((string)($empresa['nombre'] ?? ''));
        $direccionEmpresa = trim((string)($empresa['direccion'] ?? ''));
        if ($razonSocial === '' || $direccionEmpresa === '') {
            throw new RuntimeException(
                'Complete la razón social y dirección del negocio.'
            );
        }

        $serie = strtoupper(trim((string)$nota['serie_comprobante']));
        $numero = str_pad(
            (string)(int)$nota['num_comprobante'],
            8,
            '0',
            STR_PAD_LEFT
        );
        $tipoOrigen = trim((string)$nota['tipo_documento_modificado']);
        $this->validarSerie($tipoOrigen, $serie);

        $moneda = strtoupper(trim((string)($nota['moneda'] ?? 'PEN')));
        if (!preg_match('/^[A-Z]{3}$/', $moneda)) {
            $moneda = 'PEN';
        }

        $fechaHora = new DateTimeImmutable(
            (string)$nota['fecha_hora'],
            new DateTimeZone('America/Lima')
        );
        $tipoDocumentoCliente = $this->obtenerTipoDocumentoCliente(
            (string)($nota['cliente_tipo_documento'] ?? ''),
            (string)($nota['cliente_num_documento'] ?? '')
        );

        $lineas = [];
        $gruposTributo = [];
        $totalBase = 0.00;
        $totalIgv = 0.00;
        $total = 0.00;
        $numeroLinea = 1;

        foreach ($detalles as $detalle) {
            $cantidad = round((float)($detalle['cantidad_nota'] ?? 0), 3);
            $baseLinea = round((float)($detalle['valor_venta'] ?? 0), 2);
            $igvLinea = round((float)($detalle['igv'] ?? 0), 2);
            $totalLinea = round((float)($detalle['total_linea'] ?? 0), 2);
            $precioConImpuesto = round((float)($detalle['precio_unitario_con_igv'] ?? 0), 6);
            $valorUnitario = round((float)($detalle['valor_unitario_sin_igv'] ?? 0), 6);
            $afectacion = trim((string)($detalle['codigo_afectacion_igv'] ?? '10'));
            if (!in_array($afectacion, ['10','20','30','40'], true)) {
                $afectacion = '10';
            }
            $porcentaje = $afectacion === '10'
                ? round((float)($detalle['porcentaje_igv'] ?? $nota['impuesto'] ?? 18), 2)
                : 0.00;
            $unidad = $this->normalizarUnidad(
                (string)($detalle['unidad_codigo'] ?? 'NIU')
            );
            $descripcion = trim((string)($detalle['descripcion_articulo'] ?? ''));
            $codigo = trim((string)($detalle['codigo_articulo'] ?? ''));

            if ($cantidad <= 0 || $descripcion === '') {
                throw new RuntimeException(
                    'Existe un detalle inválido en la nota de crédito.'
                );
            }

            if ($totalLinea <= 0) {
                $totalLinea = round($baseLinea + $igvLinea, 2);
            }
            if ($baseLinea <= 0 && $totalLinea > 0) {
                if ($afectacion === '10' && $porcentaje > 0) {
                    $baseLinea = round($totalLinea / (1 + ($porcentaje / 100)), 2);
                    $igvLinea = round($totalLinea - $baseLinea, 2);
                } else {
                    $baseLinea = $totalLinea;
                    $igvLinea = 0.00;
                }
            }
            if ($precioConImpuesto <= 0) {
                $precioConImpuesto = round($totalLinea / $cantidad, 6);
            }
            if ($valorUnitario <= 0) {
                $valorUnitario = round($baseLinea / $cantidad, 6);
            }

            $tributo = $this->datosTributoPorAfectacion($afectacion);
            $clave = $afectacion . '|' . $tributo['codigo'];
            if (!isset($gruposTributo[$clave])) {
                $gruposTributo[$clave] = [
                    'porcentaje' => $porcentaje,
                    'codigo' => $tributo['codigo'],
                    'nombre' => $tributo['nombre'],
                    'tipo' => $tributo['tipo'],
                    'base' => 0.00,
                    'impuesto' => 0.00
                ];
            }
            $gruposTributo[$clave]['base'] += $baseLinea;
            $gruposTributo[$clave]['impuesto'] += $igvLinea;

            $item = [
                'cbc:Description' => ['_text' => $descripcion]
            ];
            if ($codigo !== '') {
                $item['cac:SellersItemIdentification'] = [
                    'cbc:ID' => ['_text' => $codigo]
                ];
            }
            $codigoProductoSunat = trim((string)($detalle['codigo_producto_sunat'] ?? ''));
            if ($codigoProductoSunat !== '') {
                $item['cac:CommodityClassification'] = [
                    'cbc:ItemClassificationCode' => [
                        '_attributes' => ['listID' => 'UNSPSC'],
                        '_text' => $codigoProductoSunat
                    ]
                ];
            }

            $lineas[] = [
                'cbc:ID' => ['_text' => $numeroLinea],
                'cbc:CreditedQuantity' => [
                    '_attributes' => [
                        'unitCode' => $unidad,
                        'unitCodeListID' => 'UN/ECE rec 20',
                        'unitCodeListAgencyName' => 'United Nations Economic Commission for Europe'
                    ],
                    '_text' => $this->numeroJson($cantidad, 3)
                ],
                'cbc:LineExtensionAmount' => [
                    '_attributes' => ['currencyID' => $moneda],
                    '_text' => $this->numeroJson($baseLinea, 2)
                ],
                'cac:PricingReference' => [
                    'cac:AlternativeConditionPrice' => [
                        'cbc:PriceAmount' => [
                            '_attributes' => ['currencyID' => $moneda],
                            '_text' => $this->numeroJson($precioConImpuesto, 6)
                        ],
                        'cbc:PriceTypeCode' => ['_text' => '01']
                    ]
                ],
                'cac:TaxTotal' => [
                    'cbc:TaxAmount' => [
                        '_attributes' => ['currencyID' => $moneda],
                        '_text' => $this->numeroJson($igvLinea, 2)
                    ],
                    'cac:TaxSubtotal' => [[
                        'cbc:TaxableAmount' => [
                            '_attributes' => ['currencyID' => $moneda],
                            '_text' => $this->numeroJson($baseLinea, 2)
                        ],
                        'cbc:TaxAmount' => [
                            '_attributes' => ['currencyID' => $moneda],
                            '_text' => $this->numeroJson($igvLinea, 2)
                        ],
                        'cac:TaxCategory' => [
                            'cbc:Percent' => ['_text' => $this->numeroJson($porcentaje, 2)],
                            'cbc:TaxExemptionReasonCode' => ['_text' => $afectacion],
                            'cac:TaxScheme' => [
                                'cbc:ID' => ['_text' => $tributo['codigo']],
                                'cbc:Name' => ['_text' => $tributo['nombre']],
                                'cbc:TaxTypeCode' => ['_text' => $tributo['tipo']]
                            ]
                        ]
                    ]]
                ],
                'cac:Item' => $item,
                'cac:Price' => [
                    'cbc:PriceAmount' => [
                        '_attributes' => ['currencyID' => $moneda],
                        '_text' => $this->numeroJson($valorUnitario, 6)
                    ]
                ]
            ];

            $totalBase += $baseLinea;
            $totalIgv += $igvLinea;
            $total += $totalLinea;
            $numeroLinea++;
        }

        $totalBase = round($totalBase, 2);
        $totalIgv = round($totalIgv, 2);
        $total = round($total, 2);
        $totalRegistrado = round((float)$nota['total_nota'], 2);

        if (abs($total - $totalRegistrado) > 0.05) {
            throw new RuntimeException(
                'El detalle de la nota no coincide con el total registrado.'
            );
        }
        $total = $totalRegistrado;

        $subtotalesTributo = [];
        foreach ($gruposTributo as $grupo) {
            $subtotalesTributo[] = [
                'cbc:TaxableAmount' => [
                    '_attributes' => ['currencyID' => $moneda],
                    '_text' => $this->numeroJson(round((float)$grupo['base'], 2), 2)
                ],
                'cbc:TaxAmount' => [
                    '_attributes' => ['currencyID' => $moneda],
                    '_text' => $this->numeroJson(round((float)$grupo['impuesto'], 2), 2)
                ],
                'cac:TaxCategory' => [
                    'cbc:Percent' => ['_text' => $this->numeroJson((float)$grupo['porcentaje'], 2)],
                    'cac:TaxScheme' => [
                        'cbc:ID' => ['_text' => $grupo['codigo']],
                        'cbc:Name' => ['_text' => $grupo['nombre']],
                        'cbc:TaxTypeCode' => ['_text' => $grupo['tipo']]
                    ]
                ]
            ];
        }

        $comprobanteOrigen = strtoupper(
            trim((string)$nota['serie_documento_modificado'])
        ) . '-' . str_pad(
            (string)(int)$nota['numero_documento_modificado'],
            8,
            '0',
            STR_PAD_LEFT
        );

        $documentBody = [
            'cbc:UBLVersionID' => ['_text' => '2.1'],
            'cbc:CustomizationID' => ['_text' => '2.0'],
            'cbc:ID' => ['_text' => $serie . '-' . $numero],
            'cbc:IssueDate' => ['_text' => $fechaHora->format('Y-m-d')],
            'cbc:IssueTime' => ['_text' => $fechaHora->format('H:i:s')],
            'cbc:Note' => [[
                '_attributes' => ['languageLocaleID' => '1000'],
                '_text' => $this->importeEnLetras($totalRegistrado)
            ]],
            'cbc:DocumentCurrencyCode' => ['_text' => $moneda],
            'cac:DiscrepancyResponse' => [
                'cbc:ReferenceID' => ['_text' => $comprobanteOrigen],
                'cbc:ResponseCode' => [
                    '_text' => str_pad((string)$nota['codigo_motivo'], 2, '0', STR_PAD_LEFT)
                ],
                'cbc:Description' => ['_text' => trim((string)$nota['sustento'])]
            ],
            'cac:BillingReference' => [
                'cac:InvoiceDocumentReference' => [
                    'cbc:ID' => ['_text' => $comprobanteOrigen],
                    'cbc:DocumentTypeCode' => ['_text' => $tipoOrigen]
                ]
            ],
            'cac:AccountingSupplierParty' => [
                'cac:Party' => [
                    'cac:PartyIdentification' => [
                        'cbc:ID' => [
                            '_attributes' => ['schemeID' => '6'],
                            '_text' => $rucEmisor
                        ]
                    ],
                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => ['_text' => $razonSocial],
                        'cac:RegistrationAddress' => [
                            'cbc:AddressTypeCode' => ['_text' => '0000'],
                            'cac:AddressLine' => [
                                'cbc:Line' => ['_text' => $direccionEmpresa]
                            ]
                        ]
                    ]
                ]
            ],
            'cac:AccountingCustomerParty' => [
                'cac:Party' => [
                    'cac:PartyIdentification' => [
                        'cbc:ID' => [
                            '_attributes' => ['schemeID' => $tipoDocumentoCliente],
                            '_text' => preg_replace('/\D/', '', (string)$nota['cliente_num_documento'])
                        ]
                    ],
                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => ['_text' => (string)$nota['cliente_nombre']],
                        'cac:RegistrationAddress' => [
                            'cac:AddressLine' => [
                                'cbc:Line' => [
                                    '_text' => trim((string)($nota['cliente_direccion'] ?? '')) !== ''
                                        ? (string)$nota['cliente_direccion']
                                        : '-'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'cac:TaxTotal' => [
                'cbc:TaxAmount' => [
                    '_attributes' => ['currencyID' => $moneda],
                    '_text' => $this->numeroJson($totalIgv, 2)
                ],
                'cac:TaxSubtotal' => $subtotalesTributo
            ],
            'cac:LegalMonetaryTotal' => [
                'cbc:LineExtensionAmount' => [
                    '_attributes' => ['currencyID' => $moneda],
                    '_text' => $this->numeroJson($totalBase, 2)
                ],
                'cbc:TaxExclusiveAmount' => [
                    '_attributes' => ['currencyID' => $moneda],
                    '_text' => $this->numeroJson($totalBase, 2)
                ],
                'cbc:TaxInclusiveAmount' => [
                    '_attributes' => ['currencyID' => $moneda],
                    '_text' => $this->numeroJson($totalRegistrado, 2)
                ],
                'cbc:PayableAmount' => [
                    '_attributes' => ['currencyID' => $moneda],
                    '_text' => $this->numeroJson($totalRegistrado, 2)
                ]
            ],
            'cac:CreditNoteLine' => $lineas
        ];

        $fileName = $rucEmisor . '-07-' . $serie . '-' . $numero;
        $email = trim((string)($nota['cliente_email'] ?? ''));

        return [
            'idnota_credito' => $idnotaCredito,
            'fileName' => $fileName,
            'tipoSunat' => '07',
            'serie' => $serie,
            'numero' => $numero,
            'tipoDocumentoOrigen' => $tipoOrigen,
            'comprobanteOrigen' => $comprobanteOrigen,
            'customerEmail' => filter_var($email, FILTER_VALIDATE_EMAIL)
                ? $email
                : null,
            'documentBody' => $documentBody,
            'totales' => [
                'gravada' => round((float)($nota['total_gravado'] ?? 0), 2),
                'exonerada' => round((float)($nota['total_exonerado'] ?? 0), 2),
                'inafecta' => round((float)($nota['total_inafecto'] ?? 0), 2),
                'exportacion' => round((float)($nota['total_exportacion'] ?? 0), 2),
                'igv' => $totalIgv,
                'total' => $totalRegistrado
            ]
        ];
    }


    private function obtenerEmpresa(): array
    {
        $empresa = $this->conexion->getData(
            "SELECT *
             FROM datos_negocio
             WHERE condicion = 1
             ORDER BY id_negocio DESC
             LIMIT 1"
        );

        if (!is_array($empresa)) {
            throw new RuntimeException(
                'No existe una empresa activa en datos_negocio.'
            );
        }

        return $empresa;
    }

    private function obtenerRucEmpresa(array $empresa): string
    {
        foreach ([
            $empresa['documento'] ?? '',
            $empresa['ndocumento'] ?? ''
        ] as $candidato) {
            $numero = preg_replace('/\D/', '', (string)$candidato);

            if (strlen($numero) === 11) {
                return $numero;
            }
        }

        throw new RuntimeException(
            'No se encontró un RUC válido en datos_negocio.'
        );
    }

    private function validarSerie(string $tipoOrigen, string $serie): void
    {
        if (!preg_match('/^[FB][A-Z0-9]{3}$/', $serie)) {
            throw new RuntimeException(
                'La serie de la nota debe tener cuatro caracteres y comenzar con F o B.'
            );
        }

        if ($tipoOrigen === '01' && $serie[0] !== 'F') {
            throw new RuntimeException(
                'La nota que modifica una factura debe usar una serie que comience con F.'
            );
        }

        if ($tipoOrigen === '03' && $serie[0] !== 'B') {
            throw new RuntimeException(
                'La nota que modifica una boleta debe usar una serie que comience con B.'
            );
        }
    }

    private function obtenerTipoDocumentoCliente(
        string $tipo,
        string $numero
    ): string {
        $texto = strtoupper(trim($tipo));
        $numero = preg_replace('/\D/', '', $numero);

        if ($texto === 'RUC' || $texto === '6' || strlen($numero) === 11) {
            return '6';
        }

        if ($texto === 'DNI' || $texto === '1' || strlen($numero) === 8) {
            return '1';
        }

        if ($texto === 'CE' || $texto === '4') {
            return '4';
        }

        throw new RuntimeException(
            'El documento del cliente de la nota no es válido.'
        );
    }


    private function datosTributoPorAfectacion(
        string $afectacion
    ): array {
        return match ($afectacion) {
            '20' => ['codigo' => '9997', 'nombre' => 'EXO', 'tipo' => 'VAT'],
            '30' => ['codigo' => '9998', 'nombre' => 'INA', 'tipo' => 'FRE'],
            '40' => ['codigo' => '9995', 'nombre' => 'EXP', 'tipo' => 'FRE'],
            default => ['codigo' => '1000', 'nombre' => 'IGV', 'tipo' => 'VAT']
        };
    }

    private function normalizarUnidad(string $codigo): string
    {
        $codigo = strtoupper(trim($codigo));

        return preg_match('/^[A-Z0-9]{2,3}$/', $codigo)
            ? $codigo
            : 'NIU';
    }

    private function numeroJson(float $numero, int $decimales): int|float
    {
        $numero = round($numero, $decimales);

        if (
            $decimales <= 2
            && abs($numero - round($numero)) < 0.000001
        ) {
            return (int)round($numero);
        }

        return $numero;
    }

    private function importeEnLetras(float $importe): string
    {
        require_once __DIR__ . '/../Libraries/NumeroALetras.php';

        $formatter = new NumeroALetras();
        $texto = strtoupper(trim((string)$formatter->toWords($importe)));

        return $texto . ' SOLES';
    }
}
