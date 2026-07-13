<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';

class ApiSunatDocument
{
    private Conexion $conexion;

    private const MONEDA = 'PEN';
    private const TASA_IGV = 18.00;
    private const FACTOR_IGV = 1.18;

    public function __construct(?Conexion $conexion = null)
    {
        $this->conexion = $conexion instanceof Conexion
            ? $conexion
            : new Conexion();
    }

    /**
     * Construye el payload tributario de una factura o boleta.
     * Este método NO envía nada a APISUNAT.
     */
    public function construir(int $idventa): array
    {
        if ($idventa <= 0) {
            throw new InvalidArgumentException(
                'El ID de venta no es válido.'
            );
        }

        $venta = $this->obtenerVenta($idventa);
        $empresa = $this->obtenerEmpresa();
        $detalles = $this->obtenerDetalles($idventa);

        if (empty($detalles)) {
            throw new RuntimeException(
                'La venta no tiene productos.'
            );
        }

        $tipoSunat = $this->obtenerTipoSunat(
            (string)$venta['tipo_comprobante']
        );

        $serie = strtoupper(
            trim((string)$venta['serie_comprobante'])
        );

        $numero = str_pad(
            (string)(int)$venta['num_comprobante'],
            8,
            '0',
            STR_PAD_LEFT
        );

        $this->validarSerie(
            $tipoSunat,
            $serie
        );

        $rucEmisor = $this->obtenerRucEmpresa(
            $empresa
        );

        $razonSocial = trim(
            (string)($empresa['nombre'] ?? '')
        );

        $direccionEmpresa = trim(
            (string)($empresa['direccion'] ?? '')
        );

        if ($razonSocial === '') {
            throw new RuntimeException(
                'Falta la razón social en datos_negocio.'
            );
        }

        if ($direccionEmpresa === '') {
            throw new RuntimeException(
                'Falta la dirección del negocio.'
            );
        }

        $descuentoTotal = round(
            (float)($venta['descuento_total'] ?? 0),
            2
        );

        /*
         * Primera etapa:
         * para evitar emitir incorrectamente en producción,
         * solo se enviarán ventas sin descuentos.
         */
        if ($descuentoTotal > 0) {
            throw new RuntimeException(
                'La primera versión de APISUNAT no enviará ventas con descuento. Registra una venta sin descuento para la prueba inicial.'
            );
        }

        /*
|--------------------------------------------------------------------------
| CONDICIÓN DE PAGO SUNAT
|--------------------------------------------------------------------------
| tipo_pago puede almacenarse como:
| 1 = Contado
| 4 = Crédito
| o directamente como texto.
|
| La forma de pago Efectivo, Yape, Tarjeta, etc. es diferente
| de la condición tributaria Contado/Crédito.
*/
        /*
|--------------------------------------------------------------------------
| CONDICIÓN DE PAGO
|--------------------------------------------------------------------------
| Valores admitidos:
| - 1 o Contado
| - 4 o Crédito
*/
        $tipoPago = $this->normalizarTexto(
            (string)(
                $venta['tipo_pago']
                ?? ''
            )
        );

        $esContado = (
            $tipoPago === '1'
            || $tipoPago === 'CONTADO'
            || str_contains(
                $tipoPago,
                'CONTADO'
            )
        );

        $esCredito = (
            $tipoPago === '4'
            || $tipoPago === 'CREDITO'
            || str_contains(
                $tipoPago,
                'CREDITO'
            )
        );

        if (!$esContado && !$esCredito) {
            throw new RuntimeException(
                'No se pudo determinar si la venta es al contado o al crédito.'
            );
        }

        if (
            $esCredito
            && $tipoSunat !== '01'
        ) {
            throw new RuntimeException(
                'El pago al crédito está habilitado únicamente para facturas electrónicas.'
            );
        }

        $cliente = [
            'tipo_documento' => trim(
                (string)($venta['tipo_documento'] ?? '')
            ),
            'num_documento' => preg_replace(
                '/\D/',
                '',
                (string)($venta['num_documento'] ?? '')
            ),
            'nombre' => trim(
                (string)($venta['cliente'] ?? '')
            ),
            'direccion' => trim(
                (string)($venta['direccion_cliente'] ?? '')
            ),
            'email' => trim(
                (string)($venta['email_cliente'] ?? '')
            )
        ];

        $tipoDocumentoCliente =
            $this->obtenerTipoDocumentoCliente(
                $cliente['tipo_documento'],
                $cliente['num_documento']
            );

        $this->validarCliente(
            $tipoSunat,
            $tipoDocumentoCliente,
            $cliente
        );

        $fechaHora = new DateTimeImmutable(
            (string)$venta['fecha_hora'],
            new DateTimeZone('America/Lima')
        );

        $lineas = [];
        $totalBase = 0.00;
        $totalIgv = 0.00;
        $totalDocumentoCalculado = 0.00;

        $numeroLinea = 1;

        foreach ($detalles as $detalle) {
            $cantidad = (float)$detalle['cantidad'];
            $precioConIgv = round(
                (float)$detalle['precio_venta'],
                2
            );

            if ($cantidad <= 0) {
                throw new RuntimeException(
                    'Existe un producto con cantidad inválida.'
                );
            }

            if ($precioConIgv < 0) {
                throw new RuntimeException(
                    'Existe un producto con precio inválido.'
                );
            }

            $importeConIgv = round(
                $cantidad * $precioConIgv,
                2
            );

            $baseLinea = round(
                $importeConIgv / self::FACTOR_IGV,
                2
            );

            $igvLinea = round(
                $importeConIgv - $baseLinea,
                2
            );

            $precioUnitarioSinIgv = round(
                $precioConIgv / self::FACTOR_IGV,
                6
            );

            $unidad = $this->normalizarUnidadSunat(
                (string)($detalle['unidad_codigo'] ?? '')
            );

            $descripcion = trim(
                (string)($detalle['nombre_articulo'] ?? '')
            );

            if ($descripcion === '') {
                throw new RuntimeException(
                    'Existe un producto sin descripción.'
                );
            }

            $lineas[] = [
                'cbc:ID' => [
                    '_text' => $numeroLinea
                ],

                'cbc:InvoicedQuantity' => [
                    '_attributes' => [
                        'unitCode' => $unidad
                    ],
                    '_text' => $this->numeroJson(
                        $cantidad,
                        2
                    )
                ],

                'cbc:LineExtensionAmount' => [
                    '_attributes' => [
                        'currencyID' => self::MONEDA
                    ],
                    '_text' => $this->numeroJson(
                        $baseLinea,
                        2
                    )
                ],

                'cac:PricingReference' => [
                    'cac:AlternativeConditionPrice' => [
                        'cbc:PriceAmount' => [
                            '_attributes' => [
                                'currencyID' => self::MONEDA
                            ],
                            '_text' => $this->numeroJson(
                                $precioConIgv,
                                2
                            )
                        ],

                        'cbc:PriceTypeCode' => [
                            '_text' => '01'
                        ]
                    ]
                ],

                'cac:TaxTotal' => [
                    'cbc:TaxAmount' => [
                        '_attributes' => [
                            'currencyID' => self::MONEDA
                        ],
                        '_text' => $this->numeroJson(
                            $igvLinea,
                            2
                        )
                    ],

                    'cac:TaxSubtotal' => [
                        [
                            'cbc:TaxableAmount' => [
                                '_attributes' => [
                                    'currencyID' => self::MONEDA
                                ],
                                '_text' => $this->numeroJson(
                                    $baseLinea,
                                    2
                                )
                            ],

                            'cbc:TaxAmount' => [
                                '_attributes' => [
                                    'currencyID' => self::MONEDA
                                ],
                                '_text' => $this->numeroJson(
                                    $igvLinea,
                                    2
                                )
                            ],

                            'cac:TaxCategory' => [
                                'cbc:Percent' => [
                                    '_text' => self::TASA_IGV
                                ],

                                'cbc:TaxExemptionReasonCode' => [
                                    '_text' => '10'
                                ],

                                'cac:TaxScheme' => [
                                    'cbc:ID' => [
                                        '_text' => '1000'
                                    ],

                                    'cbc:Name' => [
                                        '_text' => 'IGV'
                                    ],

                                    'cbc:TaxTypeCode' => [
                                        '_text' => 'VAT'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],

                'cac:Item' => [
                    'cbc:Description' => [
                        '_text' => $descripcion
                    ]
                ],

                'cac:Price' => [
                    'cbc:PriceAmount' => [
                        '_attributes' => [
                            'currencyID' => self::MONEDA
                        ],
                        '_text' => $this->numeroJson(
                            $precioUnitarioSinIgv,
                            6
                        )
                    ]
                ]
            ];

            $totalBase += $baseLinea;
            $totalIgv += $igvLinea;
            $totalDocumentoCalculado += $importeConIgv;

            $numeroLinea++;
        }

        $totalBase = round($totalBase, 2);
        $totalIgv = round($totalIgv, 2);
        $totalDocumentoCalculado = round(
            $totalDocumentoCalculado,
            2
        );

        $totalVenta = round(
            (float)$venta['total_venta'],
            2
        );

        /*
|--------------------------------------------------------------------------
| TÉRMINOS DE PAGO SUNAT
|--------------------------------------------------------------------------
*/
        $paymentTerms = null;
        $fechaUltimaCuota = null;
        $resumenCuotas = [];

        if ($tipoSunat === '01') {
            /*
    |--------------------------------------------------------------------------
    | FACTURA AL CONTADO
    |--------------------------------------------------------------------------
    */
            if ($esContado) {
                $paymentTerms = [
                    'cbc:ID' => [
                        '_text' => 'FormaPago'
                    ],

                    'cbc:PaymentMeansID' => [
                        '_text' => 'Contado'
                    ]
                ];
            }

            /*
    |--------------------------------------------------------------------------
    | FACTURA AL CRÉDITO
    |--------------------------------------------------------------------------
    */
            if ($esCredito) {
                $cuotas = $this->obtenerCuotas(
                    $idventa
                );

                if (empty($cuotas)) {
                    throw new RuntimeException(
                        'La factura al crédito no tiene un cronograma de cuotas.'
                    );
                }

                $paymentTerms = [];

                /*
         * En esta implementación no existen detracciones,
         * retenciones u otras deducciones.
         * Por eso el monto neto pendiente es el total.
         */
                $paymentTerms[] = [
                    'cbc:ID' => [
                        '_text' => 'FormaPago'
                    ],

                    'cbc:PaymentMeansID' => [
                        '_text' => 'Credito'
                    ],

                    'cbc:Amount' => [
                        '_attributes' => [
                            'currencyID' => self::MONEDA
                        ],

                        '_text' => $this->numeroJson(
                            $totalVenta,
                            2
                        )
                    ]
                ];

                $sumaCuotas = 0.00;
                $numeroEsperado = 1;

                foreach ($cuotas as $cuota) {
                    $numeroCuota = (int)(
                        $cuota['numero_cuota']
                        ?? 0
                    );

                    if (
                        $numeroCuota
                        !== $numeroEsperado
                    ) {
                        throw new RuntimeException(
                            'El cronograma de cuotas no es correlativo.'
                        );
                    }

                    $codigoCuota = trim(
                        (string)(
                            $cuota['codigo']
                            ?? ''
                        )
                    );

                    if ($codigoCuota === '') {
                        $codigoCuota = 'Cuota'
                            . str_pad(
                                (string)$numeroCuota,
                                3,
                                '0',
                                STR_PAD_LEFT
                            );
                    }

                    if (
                        !preg_match(
                            '/^Cuota\d{3}$/',
                            $codigoCuota
                        )
                    ) {
                        throw new RuntimeException(
                            'Código de cuota inválido: '
                                . $codigoCuota
                        );
                    }

                    $montoCuota = round(
                        (float)(
                            $cuota['monto']
                            ?? 0
                        ),
                        2
                    );

                    if ($montoCuota <= 0) {
                        throw new RuntimeException(
                            $codigoCuota
                                . ' tiene un monto inválido.'
                        );
                    }

                    $fechaVencimiento = trim(
                        (string)(
                            $cuota['fecha_vencimiento']
                            ?? ''
                        )
                    );

                    if (
                        !preg_match(
                            '/^\d{4}-\d{2}-\d{2}$/',
                            $fechaVencimiento
                        )
                    ) {
                        throw new RuntimeException(
                            $codigoCuota
                                . ' no tiene una fecha válida.'
                        );
                    }

                    try {
                        $fechaCuota =
                            new DateTimeImmutable(
                                $fechaVencimiento,
                                new DateTimeZone(
                                    'America/Lima'
                                )
                            );
                    } catch (Throwable $errorFecha) {
                        throw new RuntimeException(
                            $codigoCuota
                                . ' tiene una fecha inválida.'
                        );
                    }

                    if (
                        $fechaCuota->format('Y-m-d')
                        !== $fechaVencimiento
                    ) {
                        throw new RuntimeException(
                            $codigoCuota
                                . ' tiene una fecha inválida.'
                        );
                    }

                    $paymentTerms[] = [
                        'cbc:ID' => [
                            '_text' => 'FormaPago'
                        ],

                        'cbc:PaymentMeansID' => [
                            '_text' => $codigoCuota
                        ],

                        'cbc:Amount' => [
                            '_attributes' => [
                                'currencyID' => self::MONEDA
                            ],

                            '_text' => $this->numeroJson(
                                $montoCuota,
                                2
                            )
                        ],

                        'cbc:PaymentDueDate' => [
                            '_text' => $fechaVencimiento
                        ]
                    ];

                    $resumenCuotas[] = [
                        'codigo' =>
                        $codigoCuota,

                        'monto' =>
                        $montoCuota,

                        'fecha_vencimiento' =>
                        $fechaVencimiento
                    ];

                    $sumaCuotas += $montoCuota;

                    $fechaUltimaCuota =
                        $fechaVencimiento;

                    $numeroEsperado++;
                }

                $sumaCuotas = round(
                    $sumaCuotas,
                    2
                );

                if (
                    abs(
                        $sumaCuotas - $totalVenta
                    ) > 0.01
                ) {
                    throw new RuntimeException(
                        'La suma de las cuotas no coincide con el total de la factura.'
                    );
                }
            }
        }

        /*
         * Para evitar diferencias de redondeo:
         * el IGV final es la diferencia entre total y base.
         */
        $totalIgv = round(
            $totalVenta - $totalBase,
            2
        );

        $fileName = $rucEmisor
            . '-'
            . $tipoSunat
            . '-'
            . $serie
            . '-'
            . $numero;

        $documentBody = [
            'cbc:UBLVersionID' => [
                '_text' => '2.1'
            ],

            'cbc:CustomizationID' => [
                '_text' => '2.0'
            ],

            'cbc:ID' => [
                '_text' => $serie . '-' . $numero
            ],

            'cbc:IssueDate' => [
                '_text' => $fechaHora->format('Y-m-d')
            ],

            'cbc:IssueTime' => [
                '_text' => $fechaHora->format('H:i:s')
            ],

            'cbc:InvoiceTypeCode' => [
                '_attributes' => [
                    'listID' => '0101'
                ],
                '_text' => $tipoSunat
            ],

            'cbc:Note' => [
                [
                    '_text' => $this->importeEnLetras(
                        $totalVenta
                    ),
                    '_attributes' => [
                        'languageLocaleID' => '1000'
                    ]
                ]
            ],

            'cbc:DocumentCurrencyCode' => [
                '_text' => self::MONEDA
            ],

            'cac:AccountingSupplierParty' => [
                'cac:Party' => [
                    'cac:PartyIdentification' => [
                        'cbc:ID' => [
                            '_attributes' => [
                                'schemeID' => '6'
                            ],
                            '_text' => $rucEmisor
                        ]
                    ],

                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => [
                            '_text' => $razonSocial
                        ],

                        'cac:RegistrationAddress' => [
                            'cbc:AddressTypeCode' => [
                                '_text' => '0000'
                            ],

                            'cac:AddressLine' => [
                                'cbc:Line' => [
                                    '_text' => $direccionEmpresa
                                ]
                            ]
                        ]
                    ]
                ]
            ],

            'cac:AccountingCustomerParty' => [
                'cac:Party' => [
                    'cac:PartyIdentification' => [
                        'cbc:ID' => [
                            '_attributes' => [
                                'schemeID' =>
                                $tipoDocumentoCliente
                            ],
                            '_text' =>
                            $cliente['num_documento']
                        ]
                    ],

                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => [
                            '_text' => $cliente['nombre']
                        ],

                        'cac:RegistrationAddress' => [
                            'cac:AddressLine' => [
                                'cbc:Line' => [
                                    '_text' =>
                                    $cliente['direccion'] !== ''
                                        ? $cliente['direccion']
                                        : '-'
                                ]
                            ]
                        ]
                    ]
                ]
            ],

            'cac:TaxTotal' => [
                'cbc:TaxAmount' => [
                    '_attributes' => [
                        'currencyID' => self::MONEDA
                    ],
                    '_text' => $this->numeroJson(
                        $totalIgv,
                        2
                    )
                ],

                'cac:TaxSubtotal' => [
                    [
                        'cbc:TaxableAmount' => [
                            '_attributes' => [
                                'currencyID' => self::MONEDA
                            ],
                            '_text' => $this->numeroJson(
                                $totalBase,
                                2
                            )
                        ],

                        'cbc:TaxAmount' => [
                            '_attributes' => [
                                'currencyID' => self::MONEDA
                            ],
                            '_text' => $this->numeroJson(
                                $totalIgv,
                                2
                            )
                        ],

                        'cac:TaxCategory' => [
                            'cac:TaxScheme' => [
                                'cbc:ID' => [
                                    '_text' => '1000'
                                ],

                                'cbc:Name' => [
                                    '_text' => 'IGV'
                                ],

                                'cbc:TaxTypeCode' => [
                                    '_text' => 'VAT'
                                ]
                            ]
                        ]
                    ]
                ]
            ],

            'cac:LegalMonetaryTotal' => [
                'cbc:LineExtensionAmount' => [
                    '_attributes' => [
                        'currencyID' => self::MONEDA
                    ],
                    '_text' => $this->numeroJson(
                        $totalBase,
                        2
                    )
                ],

                'cbc:TaxInclusiveAmount' => [
                    '_attributes' => [
                        'currencyID' => self::MONEDA
                    ],
                    '_text' => $this->numeroJson(
                        $totalVenta,
                        2
                    )
                ],

                'cbc:PayableAmount' => [
                    '_attributes' => [
                        'currencyID' => self::MONEDA
                    ],
                    '_text' => $this->numeroJson(
                        $totalVenta,
                        2
                    )
                ]
            ],

            'cac:InvoiceLine' => $lineas
        ];

        /*
|--------------------------------------------------------------------------
| FORMA DE PAGO DE LA FACTURA
|--------------------------------------------------------------------------
| SUNAT exige obligatoriamente FormaPago + Contado/Credito
| para las facturas electrónicas.
|
| Se inserta antes de cac:TaxTotal para mantener el orden UBL.
*/
        /*
|--------------------------------------------------------------------------
| FECHA DE VENCIMIENTO GENERAL
|--------------------------------------------------------------------------
| En factura al crédito se usa la fecha de la última cuota.
*/
        if (
            $tipoSunat === '01'
            && $esCredito
            && $fechaUltimaCuota !== null
        ) {
            $documentBody = $this->insertarAntesDeClave(
                $documentBody,
                'cbc:InvoiceTypeCode',
                'cbc:DueDate',
                [
                    '_text' => $fechaUltimaCuota
                ]
            );
        }

        /*
|--------------------------------------------------------------------------
| FORMA DE PAGO Y CUOTAS
|--------------------------------------------------------------------------
*/
        if (
            $tipoSunat === '01'
            && $paymentTerms !== null
        ) {
            $documentBody = $this->insertarAntesDeClave(
                $documentBody,
                'cac:TaxTotal',
                'cac:PaymentTerms',
                $paymentTerms
            );
        }

        return [
            'idventa' => $idventa,
            'fileName' => $fileName,
            'tipoSunat' => $tipoSunat,
            'serie' => $serie,
            'numero' => $numero,
            'formaPagoSunat' =>
            $tipoSunat === '01'
                ? (
                    $esCredito
                    ? 'Credito'
                    : 'Contado'
                )
                : null,

            'cuotas' =>
            $esCredito
                ? $resumenCuotas
                : [],
            'customerEmail' => filter_var(
                $cliente['email'],
                FILTER_VALIDATE_EMAIL
            )
                ? $cliente['email']
                : null,
            'documentBody' => $documentBody,
            'totales' => [
                'gravada' => $totalBase,
                'igv' => $totalIgv,
                'total' => $totalVenta
            ]
        ];
    }

    private function obtenerVenta(int $idventa): array
    {
        $venta = $this->conexion->getData(
            "SELECT
                v.idventa,
                v.tipo_comprobante,
                v.serie_comprobante,
                v.num_comprobante,
                v.fecha_hora,
                v.impuesto,
                v.total_venta,
                v.descuento_total,
                v.descuento_porcentaje,
                v.tipo_pago,
                v.estado,

                p.tipo_documento,
                p.num_documento,
                p.nombre AS cliente,
                p.direccion AS direccion_cliente,
                p.email AS email_cliente,
                p.telefono AS telefono_cliente

             FROM venta v

             INNER JOIN persona p
                ON p.idpersona = v.idcliente

             WHERE v.idventa = ?

             LIMIT 1",
            [$idventa]
        );

        if (!$venta) {
            throw new RuntimeException(
                'No se encontró la venta.'
            );
        }

        if (
            $this->normalizarTexto(
                (string)$venta['estado']
            ) === 'ANULADO'
        ) {
            throw new RuntimeException(
                'No se puede enviar una venta anulada.'
            );
        }

        return $venta;
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

        if (!$empresa) {
            throw new RuntimeException(
                'No existe una empresa activa en datos_negocio.'
            );
        }

        return $empresa;
    }

    /*
|--------------------------------------------------------------------------
| OBTENER CRONOGRAMA DE CUOTAS
|--------------------------------------------------------------------------
*/
    private function obtenerCuotas(
        int $idventa
    ): array {
        $resultado = $this->conexion->getDataAll(
            "SELECT
            idventa_cuota,
            idventa,
            numero_cuota,
            codigo,
            monto,
            fecha_vencimiento,
            monto_pagado,
            fecha_pago,
            estado
         FROM venta_cuota
         WHERE idventa = ?
         ORDER BY numero_cuota ASC",
            [$idventa]
        );

        return is_array($resultado)
            ? $resultado
            : [];
    }

    private function obtenerDetalles(
        int $idventa
    ): array {
        return $this->conexion->getDataAll(
            "SELECT
                dv.iddetalle_venta,
                dv.idarticulo,
                dv.cantidad,
                dv.precio_venta,
                dv.descuento,

                a.codigo,
                a.nombre AS nombre_articulo,

                m.codigo AS unidad_codigo,
                m.nombre AS unidad_nombre

             FROM detalle_venta dv

             INNER JOIN articulo a
                ON a.idarticulo = dv.idarticulo

             LEFT JOIN medida m
                ON m.idmedida = a.idmedida

             WHERE dv.idventa = ?
               AND dv.estado = 1

             ORDER BY dv.iddetalle_venta ASC",
            [$idventa]
        );
    }

    private function obtenerTipoSunat(
        string $tipoComprobante
    ): string {
        $tipo = $this->normalizarTexto(
            $tipoComprobante
        );

        if (str_contains($tipo, 'FACTURA')) {
            return '01';
        }

        if (str_contains($tipo, 'BOLETA')) {
            return '03';
        }

        throw new RuntimeException(
            'Solo se pueden enviar facturas y boletas electrónicas.'
        );
    }

    private function validarSerie(
        string $tipoSunat,
        string $serie
    ): void {
        if (
            $tipoSunat === '01'
            && !preg_match('/^F[A-Z0-9]{3}$/', $serie)
        ) {
            throw new RuntimeException(
                'La factura debe tener una serie como F001.'
            );
        }

        if (
            $tipoSunat === '03'
            && !preg_match('/^B[A-Z0-9]{3}$/', $serie)
        ) {
            throw new RuntimeException(
                'La boleta debe tener una serie como B001.'
            );
        }
    }

    private function obtenerRucEmpresa(
        array $empresa
    ): string {
        $candidatos = [
            $empresa['documento'] ?? '',
            $empresa['ndocumento'] ?? ''
        ];

        foreach ($candidatos as $candidato) {
            $numero = preg_replace(
                '/\D/',
                '',
                (string)$candidato
            );

            if (strlen($numero) === 11) {
                return $numero;
            }
        }

        throw new RuntimeException(
            'No se encontró un RUC válido de 11 dígitos en datos_negocio.'
        );
    }

    private function obtenerTipoDocumentoCliente(
        string $tipoDocumento,
        string $numeroDocumento
    ): string {
        $tipo = $this->normalizarTexto(
            $tipoDocumento
        );

        if (
            $tipo === 'RUC'
            || $tipo === '6'
            || strlen($numeroDocumento) === 11
        ) {
            return '6';
        }

        if (
            $tipo === 'DNI'
            || $tipo === '1'
            || strlen($numeroDocumento) === 8
        ) {
            return '1';
        }

        if (
            $tipo === 'CE'
            || $tipo === '4'
        ) {
            return '4';
        }

        throw new RuntimeException(
            'El tipo de documento del cliente no es válido.'
        );
    }

    private function validarCliente(
        string $tipoSunat,
        string $schemeId,
        array $cliente
    ): void {
        if ($cliente['nombre'] === '') {
            throw new RuntimeException(
                'Falta el nombre del cliente.'
            );
        }

        if (
            $schemeId === '1'
            && strlen($cliente['num_documento']) !== 8
        ) {
            throw new RuntimeException(
                'El DNI del cliente debe tener 8 dígitos.'
            );
        }

        if (
            $schemeId === '6'
            && strlen($cliente['num_documento']) !== 11
        ) {
            throw new RuntimeException(
                'El RUC del cliente debe tener 11 dígitos.'
            );
        }

        if (
            $tipoSunat === '01'
            && $schemeId !== '6'
        ) {
            throw new RuntimeException(
                'Una factura requiere un cliente con RUC.'
            );
        }
    }

    /*
|--------------------------------------------------------------------------
| INSERTAR ELEMENTO RESPETANDO EL ORDEN UBL
|--------------------------------------------------------------------------
*/
    private function insertarAntesDeClave(
        array $contenido,
        string $claveReferencia,
        string $nuevaClave,
        mixed $nuevoValor
    ): array {
        $resultado = [];
        $insertado = false;

        foreach ($contenido as $clave => $valor) {
            if (
                !$insertado
                && $clave === $claveReferencia
            ) {
                $resultado[$nuevaClave] =
                    $nuevoValor;

                $insertado = true;
            }

            $resultado[$clave] = $valor;
        }

        if (!$insertado) {
            $resultado[$nuevaClave] =
                $nuevoValor;
        }

        return $resultado;
    }

    private function normalizarUnidadSunat(
        string $codigo
    ): string {
        $codigo = strtoupper(
            trim($codigo)
        );

        if (
            preg_match('/^[A-Z0-9]{3}$/', $codigo)
        ) {
            return $codigo;
        }

        return 'NIU';
    }

    private function normalizarTexto(
        string $texto
    ): string {
        $texto = trim($texto);

        $texto = iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $texto
        ) ?: $texto;

        return strtoupper($texto);
    }

    private function numeroJson(
        float $numero,
        int $decimales
    ): int|float {
        $numero = round(
            $numero,
            $decimales
        );

        if (
            $decimales <= 2
            && abs($numero - round($numero)) < 0.000001
        ) {
            return (int)round($numero);
        }

        return $numero;
    }

    private function importeEnLetras(
        float $importe
    ): string {
        $entero = (int)floor($importe);

        $centimos = (int)round(
            ($importe - $entero) * 100
        );

        if ($centimos >= 100) {
            $entero++;
            $centimos = 0;
        }

        $texto = $this->convertirEnteroALetras(
            $entero
        );

        $texto = $this->ajustarMasculino(
            $texto
        );

        return mb_strtoupper(
            trim($texto)
                . ' CON '
                . str_pad(
                    (string)$centimos,
                    2,
                    '0',
                    STR_PAD_LEFT
                )
                . '/100 SOLES',
            'UTF-8'
        );
    }

    private function ajustarMasculino(
        string $texto
    ): string {
        $texto = preg_replace(
            '/VEINTIUNO$/u',
            'VEINTIUN',
            $texto
        );

        $texto = preg_replace(
            '/Y UNO$/u',
            'Y UN',
            $texto
        );

        $texto = preg_replace(
            '/UNO$/u',
            'UN',
            $texto
        );

        return (string)$texto;
    }

    private function convertirEnteroALetras(
        int $numero
    ): string {
        if ($numero === 0) {
            return 'CERO';
        }

        if ($numero < 0) {
            return 'MENOS '
                . $this->convertirEnteroALetras(
                    abs($numero)
                );
        }

        $unidades = [
            0 => '',
            1 => 'UNO',
            2 => 'DOS',
            3 => 'TRES',
            4 => 'CUATRO',
            5 => 'CINCO',
            6 => 'SEIS',
            7 => 'SIETE',
            8 => 'OCHO',
            9 => 'NUEVE',
            10 => 'DIEZ',
            11 => 'ONCE',
            12 => 'DOCE',
            13 => 'TRECE',
            14 => 'CATORCE',
            15 => 'QUINCE',
            16 => 'DIECISEIS',
            17 => 'DIECISIETE',
            18 => 'DIECIOCHO',
            19 => 'DIECINUEVE',
            20 => 'VEINTE',
            21 => 'VEINTIUNO',
            22 => 'VEINTIDOS',
            23 => 'VEINTITRES',
            24 => 'VEINTICUATRO',
            25 => 'VEINTICINCO',
            26 => 'VEINTISEIS',
            27 => 'VEINTISIETE',
            28 => 'VEINTIOCHO',
            29 => 'VEINTINUEVE'
        ];

        if ($numero < 30) {
            return $unidades[$numero];
        }

        if ($numero < 100) {
            $decenas = [
                3 => 'TREINTA',
                4 => 'CUARENTA',
                5 => 'CINCUENTA',
                6 => 'SESENTA',
                7 => 'SETENTA',
                8 => 'OCHENTA',
                9 => 'NOVENTA'
            ];

            $decena = intdiv(
                $numero,
                10
            );

            $resto = $numero % 10;

            return $decenas[$decena]
                . (
                    $resto > 0
                    ? ' Y '
                    . $this->convertirEnteroALetras(
                        $resto
                    )
                    : ''
                );
        }

        if ($numero === 100) {
            return 'CIEN';
        }

        if ($numero < 1000) {
            $centenas = [
                1 => 'CIENTO',
                2 => 'DOSCIENTOS',
                3 => 'TRESCIENTOS',
                4 => 'CUATROCIENTOS',
                5 => 'QUINIENTOS',
                6 => 'SEISCIENTOS',
                7 => 'SETECIENTOS',
                8 => 'OCHOCIENTOS',
                9 => 'NOVECIENTOS'
            ];

            $centena = intdiv(
                $numero,
                100
            );

            $resto = $numero % 100;

            return $centenas[$centena]
                . (
                    $resto > 0
                    ? ' '
                    . $this->convertirEnteroALetras(
                        $resto
                    )
                    : ''
                );
        }

        if ($numero < 1000000) {
            $miles = intdiv(
                $numero,
                1000
            );

            $resto = $numero % 1000;

            $textoMiles = $miles === 1
                ? 'MIL'
                : $this->ajustarMasculino(
                    $this->convertirEnteroALetras(
                        $miles
                    )
                ) . ' MIL';

            return $textoMiles
                . (
                    $resto > 0
                    ? ' '
                    . $this->convertirEnteroALetras(
                        $resto
                    )
                    : ''
                );
        }

        if ($numero < 1000000000) {
            $millones = intdiv(
                $numero,
                1000000
            );

            $resto = $numero % 1000000;

            $textoMillones = $millones === 1
                ? 'UN MILLON'
                : $this->ajustarMasculino(
                    $this->convertirEnteroALetras(
                        $millones
                    )
                ) . ' MILLONES';

            return $textoMillones
                . (
                    $resto > 0
                    ? ' '
                    . $this->convertirEnteroALetras(
                        $resto
                    )
                    : ''
                );
        }

        throw new RuntimeException(
            'El importe es demasiado grande para convertirlo a letras.'
        );
    }
}
