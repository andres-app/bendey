<?php
//Models/Sell.php
require_once __DIR__ . '/../Config/Conexion.php';
class Sell
{

    private $tableName = 'venta';
    private $tableNameDetalle = 'detalle_venta';
    private $tableNameKardex = 'kardex';
    private $conexion;

    //implementamos nuestro constructor
    public function __construct()
    {
        $this->conexion = new Conexion();
    }


    // Método insertar registro con historial tributario por línea.
    public function insertar(
        $idcliente,
        $idusuario,
        $tipo_comprobante,
        $serie_comprobante,
        $num_comprobante,
        $impuesto,
        $total_venta,
        $descuento_total,
        $descuento_porcentaje,
        $tipo_pago,
        $num_transac,
        $idforma_pago,
        $idingreso,
        $idarticulo,
        $cantidad,
        $precio_compra,
        $precio_venta,
        $idsucursal = null,
        $idcaja = null,
        $idapertura = null,
        array $tributacion = []
    ) {
        date_default_timezone_set('America/Lima');
        $fecha_hora = date('Y-m-d H:i:s');

        $idsucursal = (int)$idsucursal > 0
            ? (int)$idsucursal
            : null;
        $idcaja = (int)$idcaja > 0
            ? (int)$idcaja
            : null;
        $idapertura = (int)$idapertura > 0
            ? (int)$idapertura
            : null;

        $descuentosItem = array_fill(
            0,
            is_array($idarticulo) ? count($idarticulo) : 0,
            0.00
        );

        if (
            !isset($tributacion['lineas'])
            || !is_array($tributacion['lineas'])
            || count($tributacion['lineas']) !== count($idarticulo)
        ) {
            $tributacion = $this->calcularTributacionVenta(
                is_array($idarticulo) ? $idarticulo : [],
                is_array($cantidad) ? $cantidad : [],
                is_array($precio_venta) ? $precio_venta : [],
                $descuentosItem,
                (float)$descuento_total,
                $idsucursal,
                null
            );
        }

        $total_venta = round(
            (float)($tributacion['total_venta'] ?? $total_venta),
            2
        );
        $impuesto = round(
            (float)($tributacion['porcentaje_igv_predeterminado'] ?? $impuesto),
            2
        );

        $sql = "INSERT INTO {$this->tableName} (
            idcliente,
            idusuario,
            idsucursal,
            idcaja,
            idapertura,
            tipo_operacion_sunat,
            tipo_comprobante,
            serie_comprobante,
            num_comprobante,
            fecha_hora,
            impuesto,
            moneda_codigo,
            total_gravado,
            total_exonerado,
            total_inafecto,
            total_exportacion,
            total_igv,
            precios_incluyen_impuesto,
            total_venta,
            descuento_total,
            descuento_porcentaje,
            tipo_pago,
            num_transac,
            estado,
            idforma_pago
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $arrData = [
            $idcliente,
            $idusuario,
            $idsucursal,
            $idcaja,
            $idapertura,
            (string)($tributacion['tipo_operacion_sunat'] ?? '0101'),
            $tipo_comprobante,
            $serie_comprobante,
            $num_comprobante,
            $fecha_hora,
            $impuesto,
            (string)($tributacion['moneda_codigo'] ?? 'PEN'),
            round((float)($tributacion['total_gravado'] ?? 0), 2),
            round((float)($tributacion['total_exonerado'] ?? 0), 2),
            round((float)($tributacion['total_inafecto'] ?? 0), 2),
            round((float)($tributacion['total_exportacion'] ?? 0), 2),
            round((float)($tributacion['total_igv'] ?? 0), 2),
            (int)($tributacion['precios_incluyen_impuesto'] ?? 1),
            $total_venta,
            round((float)$descuento_total, 2),
            round((float)$descuento_porcentaje, 2),
            $tipo_pago,
            $num_transac,
            'Aceptado',
            $idforma_pago
        ];

        $idventanew = $this->conexion->setDataReturnId(
            $sql,
            $arrData
        );

        if (!$idventanew) {
            return false;
        }

        $detalleComprobante = $tipo_comprobante
            . ' '
            . $serie_comprobante
            . '-'
            . $num_comprobante;
        $sw = true;

        foreach ($tributacion['lineas'] as $indice => $linea) {
            $sqlDetalle = "INSERT INTO {$this->tableNameDetalle} (
                idventa,
                idarticulo,
                cantidad,
                precio_compra,
                precio_venta,
                descuento,
                codigo_afectacion_igv,
                porcentaje_igv,
                unidad_medida_sunat,
                codigo_producto_sunat,
                codigo_tributo,
                nombre_tributo,
                tipo_tributo,
                valor_unitario_sin_igv,
                base_imponible,
                monto_igv,
                total_linea,
                estado
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

            $arrDetalle = [
                $idventanew,
                (int)$idarticulo[$indice],
                (float)$cantidad[$indice],
                round((float)$precio_compra[$indice], 6),
                round((float)$precio_venta[$indice], 6),
                round((float)($linea['descuento_linea'] ?? 0), 2),
                (string)($linea['codigo_afectacion_igv'] ?? '10'),
                round((float)($linea['porcentaje_igv'] ?? 0), 2),
                (string)($linea['unidad_medida_sunat'] ?? 'NIU'),
                (string)($linea['codigo_producto_sunat'] ?? ''),
                (string)($linea['codigo_tributo'] ?? '1000'),
                (string)($linea['nombre_tributo'] ?? 'IGV'),
                (string)($linea['tipo_tributo'] ?? 'VAT'),
                round((float)($linea['valor_unitario_sin_igv'] ?? 0), 6),
                round((float)($linea['base_imponible'] ?? 0), 2),
                round((float)($linea['monto_igv'] ?? 0), 2),
                round((float)($linea['total_linea'] ?? 0), 2),
                1
            ];

            if (!$this->conexion->setData($sqlDetalle, $arrDetalle)) {
                $sw = false;
            }
        }

        // Actualizar stock de artículo.
        $sqlStock = "SELECT idarticulo, cantidad
                     FROM {$this->tableNameDetalle}
                     WHERE idventa = ?";
        $res = $this->conexion->getDataAll(
            $sqlStock,
            [$idventanew]
        );

        foreach (is_array($res) ? $res : [] as $reg) {
            if (!$this->conexion->setData(
                "UPDATE articulo
                 SET stock = stock - ?
                 WHERE idarticulo = ?",
                [
                    (float)$reg['cantidad'],
                    (int)$reg['idarticulo']
                ]
            )) {
                $sw = false;
            }
        }

        // Kardex: conserva la lógica FIFO existente.
        foreach ($idarticulo as $indice => $idArticuloActual) {
            $cantidadPendiente = (int)($cantidad[$indice] ?? 0);

            while ($cantidadPendiente > 0) {
                $lote = $this->conexion->getData(
                    "SELECT
                        iddetalle_ingreso,
                        stock_venta,
                        precio_compra
                     FROM detalle_ingreso
                     WHERE idarticulo = ?
                       AND COALESCE(stock_venta, 0) > 0
                     ORDER BY
                        CASE WHEN stock_estado = '1' THEN 0 ELSE 1 END,
                        iddetalle_ingreso ASC
                     LIMIT 1",
                    [(int)$idArticuloActual]
                );

                if (!is_array($lote)) {
                    break;
                }

                $stockDisponible = (int)($lote['stock_venta'] ?? 0);
                $idDetalleIngreso = (int)($lote['iddetalle_ingreso'] ?? 0);
                $costoUnitario = (float)($lote['precio_compra'] ?? 0);

                if ($stockDisponible <= 0 || $idDetalleIngreso <= 0) {
                    break;
                }

                $cantidadSalida = min(
                    $cantidadPendiente,
                    $stockDisponible
                );

                if (!$this->conexion->setData(
                    "UPDATE detalle_ingreso
                     SET stock_venta = stock_venta - ?
                     WHERE iddetalle_ingreso = ?",
                    [$cantidadSalida, $idDetalleIngreso]
                )) {
                    $sw = false;
                }

                $cantidadExistente = $stockDisponible - $cantidadSalida;
                $totalSalida = $cantidadSalida * $costoUnitario;
                $totalExistencia = $cantidadExistente * $costoUnitario;

                if (!$this->conexion->setData(
                    "INSERT INTO {$this->tableNameKardex} (
                        iddetalle,
                        idarticulo,
                        fecha,
                        detalle,
                        cantidads,
                        costous,
                        totals,
                        cantidadex,
                        costouex,
                        totalex,
                        tipo,
                        estado
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                    [
                        $idventanew,
                        (int)$idArticuloActual,
                        $fecha_hora,
                        $detalleComprobante,
                        $cantidadSalida,
                        $costoUnitario,
                        $totalSalida,
                        $cantidadExistente,
                        $costoUnitario,
                        $totalExistencia,
                        'Salida',
                        'Activo'
                    ]
                )) {
                    $sw = false;
                }

                $cantidadPendiente -= $cantidadSalida;
            }
        }

        return ($sw && $idventanew)
            ? $idventanew
            : false;
    }


    //FUNCION PARA EDITAR
    public function editar($idventa, $idcliente, $tipo_comprobante, $serie_comprobante, $num_comprobante, $impuesto, $total_venta, $tipo_pago, $num_transac, $idarticulo, $nuevostock, $cantidad, $precio_compra, $precio_venta, $descuento)
    {
        $sw = true;
        $sql = "UPDATE $this->tableName SET idcliente=?, tipo_comprobante=?, serie_comprobante=?, num_comprobante=?, impuesto=?, total_venta=?, tipo_pago=?, num_transac=? WHERE idventa=?";

        $arrData = array($idcliente, $tipo_comprobante, $serie_comprobante, $num_comprobante, $impuesto, $total_venta, $tipo_pago, $num_transac, $idventa);
        $this->conexion->setData($sql, $arrData) or $sw = false;


        //ELIMINAR DATOS DE DETALLE DE INGRESO
        $sql_del = "DELETE FROM $this->tableNameDetalle WHERE idventa=?";
        $arrDataDel = array($idventa);
        $this->conexion->setData($sql_del, $arrDataDel) or $sw = false;

        $num_elementos = 0;
        while ($num_elementos < count($idarticulo)) {

            $sql_detalle = "INSERT INTO $this->tableNameDetalle (idventa,idarticulo,cantidad,precio_venta,descuento,estado) VALUES(?,?,?,?,?,?)";
            $arrDatadet = array($idventa, $idarticulo[$num_elementos], $cantidad[$num_elementos], $precio_venta[$num_elementos], $descuento[$num_elementos], '1');
            $this->conexion->setData($sql_detalle, $arrDatadet) or $sw = false;

            $num_elementos = $num_elementos + 1;
        }
        //ACTUALIZAR STOCK DESPUES DE EDITAR UNA VENTA
        $sql_stock = "SELECT idarticulo, cantidad FROM $this->tableNameDetalle WHERE idventa='$idventa'";
        $res = $this->conexion->getDataAll($sql_stock);
        $idart = 0;
        foreach ($res as $reg) {
            //$cantidad[$idart] = isset($reg['cantidad'])? $cantidad[$idart]=$reg['cantidad']:null;
            $idarticulo[$idart] = isset($reg['idarticulo']) ? $idarticulo[$idart] = $reg['idarticulo'] : null;
            $sql_detalle = "UPDATE articulo SET stock= stock+'$nuevostock[$idart]' WHERE idarticulo=?";
            //ejecutarConsulta($sql_detalle) or $sw=false;
            $arrData = array($idarticulo[$idart]);
            $this->conexion->setData($sql_detalle, $arrData) or $sw = false;
            $idart = $idart + 1;
        }

        //ACTUALIZAR EL KARDEX
        date_default_timezone_set('America/Lima');
        $fecha_hora = date('Y-m-d H:i:s');
        $detalle = $tipo_comprobante . ' ' . $serie_comprobante . '-' . $num_comprobante;
        //ELIMINAR DATOS DE DETALLE DE INGRESO
        $sql_del = "DELETE FROM $this->tableNameKardex WHERE iddetalle=? AND tipo=?";
        $arrDataDel = array($idventa, 'Salida');
        $this->conexion->setData($sql_del, $arrDataDel) or $sw = false;
        //INGRESAR DATOS PARA EL KARDEX 
        /* $elementos=0;
         while ($elementos < count($idarticulo)) {
        //SELECCIONAR NUEVO STOCK PARA EL KARDEX
         $sql_stock="SELECT stock FROM articulo WHERE idarticulo='$idarticulo[$elementos]'";
         $res= $this->conexion->getDataAll($sql_stock);
         $idart=0; 
         foreach($res as $reg){
             $cantidadex[$elementos] = isset($reg['stock'])? $cantidadex[$elementos]=$reg['stock']:null;

             $totalKardex=$cantidad[$elementos]*$precio_venta[$elementos];
             $totalex=$cantidadex[$elementos]*$precio_compra[$elementos];
             $sql_kardex="INSERT INTO $this->tableNameKardex (iddetalle,idarticulo,fecha,detalle,cantidads,costous,totals,cantidadex,costouex,totalex,tipo,estado) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)";
             $arrKardex = array($idventa ,$idarticulo[$elementos],$fecha_hora,$detalle,$cantidad[$elementos],$precio_venta[$elementos],$totalKardex,$cantidadex[$elementos],$precio_compra[$elementos],$totalex,'Salida','Activo');
             $this->conexion->setData($sql_kardex,$arrKardex)or $sw=false;
         }

             $elementos=$elementos+1;
         }*/
        $num_elementos = 0;
        while ($num_elementos < count($idarticulo)) {

            $sqlIdViejo = "SELECT iddetalle_ingreso FROM detalle_ingreso WHERE idarticulo=? AND stock_estado='1' ORDER BY iddetalle_ingreso ASC LIMIT 0,1";
            $arrDataViejo = array($idarticulo[$num_elementos]);
            $idIn = $this->conexion->getData($sqlIdViejo, $arrDataViejo);
            $idViejo = isset($idIn['iddetalle_ingreso']) ? $idViejo = $idIn['iddetalle_ingreso'] : null;

            $sqlStockViejo = "SELECT stock_venta, precio_compra FROM detalle_ingreso WHERE iddetalle_ingreso=?";
            $arrDataViejoStock = array($idViejo);
            $stockVenta = $this->conexion->getData($sqlStockViejo, $arrDataViejoStock);
            (int) $sotckDisponible = isset($stockVenta['stock_venta']) ? (int) $sotckDisponible = $stockVenta['stock_venta'] : null;
            $stPrecioCompra = isset($stockVenta['precio_compra']) ? $stPrecioCompra = $stockVenta['precio_compra'] : null;

            $cantVenta = (int) $cantidad[$num_elementos];

            if ($cantVenta < $sotckDisponible) {

                $sql_update = "UPDATE detalle_ingreso SET stock_venta=stock_venta-'$cantVenta' WHERE iddetalle_ingreso=?";
                $arrUpdate = array($idViejo);
                $this->conexion->setData($sql_update, $arrUpdate) or $sw = false;

                //DATOS PARA EL KARDEX
                $cantidadExistente = $sotckDisponible - $cantVenta;
                $totalKardex = $cantVenta * $stPrecioCompra;
                $totalex = $cantidadExistente * $stPrecioCompra;
                $sql_kardex = "INSERT INTO $this->tableNameKardex (iddetalle,idarticulo,fecha,detalle,cantidads,costous,totals,cantidadex,costouex,totalex,tipo,estado) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)";
                $arrKardex = array($idventa, $idarticulo[$num_elementos], $fecha_hora, $detalle, $cantVenta, $stPrecioCompra, $totalKardex, $cantidadExistente, $stPrecioCompra, $totalex, 'Salida', 'Activo');
                $this->conexion->setData($sql_kardex, $arrKardex) or $sw = false;
            } else {
                do {

                    $sqlIdViejo = "SELECT iddetalle_ingreso FROM detalle_ingreso WHERE idarticulo=? AND stock_estado='1' ORDER BY iddetalle_ingreso ASC LIMIT 0,1";
                    $arrDataViejo = array($idarticulo[$num_elementos]);
                    $idIn = $this->conexion->getData($sqlIdViejo, $arrDataViejo);
                    $idViejo = isset($idIn['iddetalle_ingreso']) ? $idViejo = $idIn['iddetalle_ingreso'] : null;

                    $sqlStockViejo = "SELECT stock_venta, precio_compra FROM detalle_ingreso WHERE iddetalle_ingreso=?";
                    $arrDataViejoStock = array($idViejo);
                    $stockVenta = $this->conexion->getData($sqlStockViejo, $arrDataViejoStock);
                    (int) $sotckDisponible = isset($stockVenta['stock_venta']) ? (int) $sotckDisponible = $stockVenta['stock_venta'] : null;
                    $stPrecioCompra = isset($stockVenta['precio_compra']) ? $stPrecioCompra = $stockVenta['precio_compra'] : null;

                    if ($cantVenta < $sotckDisponible) {

                        $sql_update = "UPDATE detalle_ingreso SET stock_venta=stock_venta-'$cantVenta' WHERE iddetalle_ingreso=?";
                        $arrUpdate = array($idViejo);
                        $this->conexion->setData($sql_update, $arrUpdate) or $sw = false;

                        //DATOS PARA EL KARDEX
                        $cantidadExistente = $sotckDisponible - $cantVenta;
                        $totalKardex = $cantVenta * $stPrecioCompra;
                        $totalex = $cantidadExistente * $stPrecioCompra;
                        $sql_kardex = "INSERT INTO $this->tableNameKardex (iddetalle,idarticulo,fecha,detalle,cantidads,costous,totals,cantidadex,costouex,totalex,tipo,estado) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)";
                        $arrKardex = array($idventa, $idarticulo[$num_elementos], $fecha_hora, $detalle, $cantVenta, $stPrecioCompra, $totalKardex, $cantidadExistente, $stPrecioCompra, $totalex, 'Salida', 'Activo');
                        $this->conexion->setData($sql_kardex, $arrKardex) or $sw = false;

                        $cantVenta = $cantVenta - $sotckDisponible;
                    } elseif ($cantVenta == $sotckDisponible) {

                        $sql_update = "UPDATE detalle_ingreso SET stock_venta='0',stock_estado='0' WHERE iddetalle_ingreso=?";
                        $arrUpdate = array($idViejo);
                        $this->conexion->setData($sql_update, $arrUpdate) or $sw = false;

                        $cantidadExistente = (int) $sotckDisponible - (int) $cantVenta;

                        $totalKardex = $cantVenta * $stPrecioCompra;
                        $totalex = $cantidadExistente * $stPrecioCompra;
                        $costus = $stPrecioCompra * $cantidadExistente;

                        $sql_kardex = "INSERT INTO $this->tableNameKardex (iddetalle,idarticulo,fecha,detalle,cantidads,costous,totals,cantidadex,costouex,totalex,tipo,estado) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)";
                        $arrKardex = array($idventa, $idarticulo[$num_elementos], $fecha_hora, $detalle, $cantVenta, $stPrecioCompra, $totalKardex, $cantidadExistente, $costus, $totalex, 'Salida', 'Activo');
                        $this->conexion->setData($sql_kardex, $arrKardex) or $sw = false;
                        //  }
                        $cantVenta = $cantVenta - $sotckDisponible;
                    } elseif ($cantVenta > $sotckDisponible) {

                        $sql_update = "UPDATE detalle_ingreso SET stock_venta='0',stock_estado='0' WHERE iddetalle_ingreso=?";
                        $arrUpdate = array($idViejo);
                        $this->conexion->setData($sql_update, $arrUpdate) or $sw = false;

                        $cantVenta = $cantVenta - $sotckDisponible;

                        $cantidadExistente = $sotckDisponible - $sotckDisponible;

                        $totalKardex = $sotckDisponible * $stPrecioCompra;
                        $totalex = $cantidadExistente * $stPrecioCompra;
                        $costus = $stPrecioCompra * $cantidadExistente;

                        $sql_kardex = "INSERT INTO $this->tableNameKardex (iddetalle,idarticulo,fecha,detalle,cantidads,costous,totals,cantidadex,costouex,totalex,tipo,estado) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)";
                        $arrKardex = array($idventa, $idarticulo[$num_elementos], $fecha_hora, $detalle, $sotckDisponible, $stPrecioCompra, $totalKardex, $cantidadExistente, $costus, $totalex, 'Salida', 'Activo');
                        $this->conexion->setData($sql_kardex, $arrKardex) or $sw = false;
                    }
                } while ($cantVenta >= 1);
            }

            $num_elementos = $num_elementos + 1;
        }


        return $sw;
    }

    public function anular($idventa)
    {
        $sw = true;
        $sql = "UPDATE $this->tableName SET estado='Anulado' WHERE idventa=?";
        $arrData = array($idventa);
        $this->conexion->setData($sql, $arrData);
        $sql_detalle = "UPDATE $this->tableNameDetalle SET estado='0' WHERE idventa=?";
        $arrDataDetalle = array($idventa);
        $this->conexion->setData($sql_detalle, $arrDataDetalle) or $sw = false;

        //ACTUALIZAR STOCK DESPUES DE ANULAR UNA VENTA
        $sql_stock = "SELECT idarticulo, cantidad FROM $this->tableNameDetalle WHERE idventa='$idventa'";
        $res = $this->conexion->getDataAll($sql_stock);
        $idart = 0;
        foreach ($res as $reg) {
            $cantidad[$idart] = isset($reg['cantidad']) ? $cantidad[$idart] = $reg['cantidad'] : null;
            $idarticulo[$idart] = isset($reg['idarticulo']) ? $idarticulo[$idart] = $reg['idarticulo'] : null;
            $sql_detalle = "UPDATE articulo SET stock= stock+'$cantidad[$idart]' WHERE idarticulo=?";
            //ejecutarConsulta($sql_detalle) or $sw=false;
            $arrData = array($idarticulo[$idart]);
            $this->conexion->setData($sql_detalle, $arrData) or $sw = false;
            $idart = $idart + 1;
        }

        //ACTUALIZAR KARDEX
        $sql_k = "SELECT * FROM kardex WHERE iddetalle='$idventa' AND tipo='Salida'";
        $resk = $this->conexion->getDataAll($sql_k);
        $idart = 0;
        foreach ($resk as $reg) {
            //ACTUALIZAR KARDEX
            $sql_kardex = "UPDATE kardex SET estado= 'Anulado' WHERE iddetalle=? AND idarticulo=? AND tipo=?";
            //ejecutarConsulta($sql_kardex) or $sw=false;
            $arrDataKardex = array($idventa, $idarticulo[$idart], 'Salida');
            $this->conexion->setData($sql_kardex, $arrDataKardex) or $sw = false;
            //echo $idarticulo[$idart];
            $idart = $idart + 1;
        }
        return $sw;
    }

    //implementar un metodopara mostrar los datos de unregistro a modificar
    public function mostrar($idventa)
    {
        $sql = "SELECT
                v.idventa,
                DATE_FORMAT(
                    v.fecha_hora,
                    '%d/%m/%Y %H:%i:%s'
                ) AS fecha,

                v.idcliente,
                COALESCE(p.tipo_documento, '') AS tipo_documento_cliente,
                COALESCE(p.num_documento, '') AS num_documento_cliente,

                COALESCE(
                    p.nombre,
                    'SIN CLIENTE'
                ) AS cliente,

                COALESCE(
                    u.nombre,
                    'SIN USUARIO'
                ) AS usuario,

                v.tipo_comprobante,
                v.serie_comprobante,
                v.num_comprobante,
                v.total_venta,
                v.descuento_total,
                v.descuento_porcentaje,
                v.impuesto,
                v.estado,
                v.tipo_pago,
                v.idforma_pago,

                COALESCE(
                    fp.nombre,
                    'No especificado'
                ) AS forma_pago

            FROM venta v

            LEFT JOIN persona p
                ON p.idpersona = v.idcliente

            LEFT JOIN usuario u
                ON u.idusuario = v.idusuario

            LEFT JOIN forma_pago fp
                ON fp.idforma_pago = v.idforma_pago

            WHERE v.idventa = ?

            LIMIT 1";

        return $this->conexion->getData(
            $sql,
            [(int)$idventa]
        );
    }


    public function listarDetalle($idventa)
    {
        $sql = "SELECT
                    dv.idventa,
                    dv.idarticulo,
                    a.codigo AS sku,
                    a.nombre,
                    a.stock,
                    dv.cantidad,
                    dv.precio_compra,
                    dv.precio_venta,
                    dv.descuento,
                    dv.codigo_afectacion_igv,
                    dv.porcentaje_igv,
                    dv.base_imponible,
                    dv.monto_igv,
                    CASE
                        WHEN dv.total_linea > 0 THEN dv.total_linea
                        ELSE GREATEST(dv.cantidad * dv.precio_venta - dv.descuento, 0)
                    END AS subtotal,
                    CASE
                        WHEN dv.cantidad > 0 AND dv.total_linea > 0
                        THEN dv.total_linea / dv.cantidad
                        ELSE dv.precio_venta
                    END AS precio_unitario_con_impuesto,
                    COALESCE(
                        NULLIF(cat.descripcion, ''),
                        CASE dv.codigo_afectacion_igv
                            WHEN '20' THEN 'Exonerado'
                            WHEN '30' THEN 'Inafecto'
                            WHEN '40' THEN 'Exportación'
                            ELSE 'Gravado'
                        END
                    ) AS afectacion_descripcion,
                    v.total_venta,
                    v.descuento_total,
                    v.descuento_porcentaje,
                    v.impuesto,
                    v.total_gravado,
                    v.total_exonerado,
                    v.total_inafecto,
                    v.total_exportacion,
                    v.total_igv
                FROM detalle_venta dv
                INNER JOIN articulo a
                    ON a.idarticulo = dv.idarticulo
                INNER JOIN venta v
                    ON v.idventa = dv.idventa
                LEFT JOIN sunat_catalogo_07_afectacion_igv cat
                    ON cat.codigo = dv.codigo_afectacion_igv
                WHERE dv.idventa = ?
                ORDER BY dv.iddetalle_venta ASC";

        return $this->conexion->getDataAll(
            $sql,
            [(int)$idventa]
        );
    }

    //listar registros
    // LISTAR VENTAS CON ESTADO REAL DE SUNAT
    public function listar()
    {
        $sql = "
        SELECT
            v.idventa,

            DATE_FORMAT(
                v.fecha_hora,
                '%d/%m/%Y %H:%i'
            ) AS fecha,

            v.idcliente,
            COALESCE(p.num_documento, '') AS num_documento,

            COALESCE(
                p.nombre,
                'SIN CLIENTE'
            ) AS cliente,

            COALESCE(
                u.nombre,
                'SIN USUARIO'
            ) AS usuario,

            v.tipo_comprobante,
            v.serie_comprobante,
            v.num_comprobante,
            v.total_venta,
            v.impuesto,

            COALESCE(
                NULLIF(
                    (
                        SELECT GROUP_CONCAT(
                            DISTINCT fpv.nombre
                            ORDER BY fpv.nombre
                            SEPARATOR ' + '
                        )
                        FROM venta_pago vp
                        INNER JOIN forma_pago fpv
                            ON fpv.idforma_pago = vp.idforma_pago
                        WHERE vp.idventa = v.idventa
                    ),
                    ''
                ),
                fp.nombre,
                'No especificado'
            ) AS metodo_pago,

            /* Estado local de la venta */
            v.estado,

            /* Notas de crédito ya registradas para la venta */
            COALESCE((
                SELECT SUM(nc.total_nota)
                FROM nota_credito nc
                WHERE nc.idventa = v.idventa
                  AND nc.estado <> 'ANULADA'
            ), 0) AS total_notas_credito,

            COALESCE((
                SELECT COUNT(*)
                FROM nota_credito nc
                WHERE nc.idventa = v.idventa
                  AND nc.estado <> 'ANULADA'
            ), 0) AS cantidad_notas_credito,

            /* Datos registrados por APISUNAT */
            vs.idventa_sunat,
            vs.document_id,
            vs.estado_sunat AS estado_sunat_original,
            vs.mensaje_sunat,

            /* Estado SUNAT unificado */
            CASE
                WHEN v.estado <> 'Aceptado'
                THEN 'ANULADO'

                WHEN v.tipo_comprobante NOT IN (
                    'Factura Electrónica',
                    'Boleta Electrónica'
                )
                THEN 'NO_APLICA'

                WHEN vs.idventa_sunat IS NULL
                THEN 'NO_ENVIADO'

                WHEN COALESCE(
                    vs.document_id,
                    ''
                ) = ''
                THEN 'NO_ENVIADO'

                WHEN COALESCE(
                    vs.estado_sunat,
                    ''
                ) = ''
                THEN 'PENDIENTE'

                ELSE UPPER(
                    TRIM(
                        vs.estado_sunat
                    )
                )
            END AS estado_sunat

        FROM venta v

        LEFT JOIN persona p
            ON p.idpersona = v.idcliente

        LEFT JOIN usuario u
            ON u.idusuario = v.idusuario

        LEFT JOIN forma_pago fp
            ON fp.idforma_pago = v.idforma_pago

        LEFT JOIN venta_sunat vs
            ON vs.idventa = v.idventa

        WHERE v.tipo_comprobante <> 'Cotizacion'

        ORDER BY v.idventa DESC
    ";

        $resultado = $this->conexion->getDataAll(
            $sql
        );

        return is_array($resultado)
            ? $resultado
            : [];
    }



    public function ventacabecera($idventa)
    {
        $sql = "SELECT
            v.estado,
            v.idventa,
            v.idcliente,
            p.nombre AS cliente,
            p.direccion,
            p.tipo_documento,
            p.num_documento,
            p.email,
            p.telefono,
            v.idusuario,
            u.nombre AS usuario,
            v.tipo_comprobante,
            v.serie_comprobante,
            v.num_comprobante,
            DATE(v.fecha_hora) AS fecha,
            v.fecha_hora,
            v.tipo_operacion_sunat,
            v.impuesto,
            v.moneda_codigo,
            v.precios_incluyen_impuesto,
            v.total_gravado,
            v.total_exonerado,
            v.total_inafecto,
            v.total_exportacion,
            v.total_igv,
            v.total_venta,
            v.descuento_total,
            v.descuento_porcentaje,
            v.tipo_pago,
            v.idforma_pago,
            COALESCE(vs.estado_sunat, 'NO_APLICA') AS estado_sunat,
            COALESCE(vs.mensaje_sunat, '') AS mensaje_sunat
        FROM venta v
        INNER JOIN persona p ON v.idcliente = p.idpersona
        INNER JOIN usuario u ON v.idusuario = u.idusuario
        LEFT JOIN venta_sunat vs ON vs.idventa = v.idventa
        WHERE v.idventa = ?";

        return $this->conexion->getDataAll(
            $sql,
            [(int)$idventa]
        );
    }



    public function ventadetalles($idventa)
    {
        $sql = "SELECT
                a.nombre AS articulo,
                a.codigo AS sku,
                d.cantidad,
                d.precio_venta,
                d.descuento,
                d.codigo_afectacion_igv,
                d.porcentaje_igv,
                d.unidad_medida_sunat,
                d.codigo_producto_sunat,
                d.codigo_tributo,
                d.nombre_tributo,
                d.tipo_tributo,
                d.valor_unitario_sin_igv,
                d.base_imponible,
                d.monto_igv,
                d.total_linea,
                COALESCE(
                    NULLIF(cat.descripcion, ''),
                    CASE d.codigo_afectacion_igv
                        WHEN '10' THEN 'Gravado'
                        WHEN '20' THEN 'Exonerado'
                        WHEN '30' THEN 'Inafecto'
                        WHEN '40' THEN 'Exportación'
                        ELSE 'Sin clasificación'
                    END
                ) AS afectacion_descripcion,
                CASE
                    WHEN d.total_linea > 0 THEN d.total_linea
                    ELSE (d.cantidad * d.precio_venta - d.descuento)
                END AS subtotal,
                CASE
                    WHEN d.cantidad > 0 AND d.total_linea > 0
                    THEN d.total_linea / d.cantidad
                    ELSE d.precio_venta
                END AS precio_unitario_con_impuesto
            FROM {$this->tableNameDetalle} d
            INNER JOIN articulo a ON d.idarticulo = a.idarticulo
            LEFT JOIN sunat_catalogo_07_afectacion_igv cat
                ON cat.codigo = d.codigo_afectacion_igv
            WHERE d.idventa = ?
            ORDER BY d.iddetalle_venta ASC";

        return $this->conexion->getDataAll(
            $sql,
            [(int)$idventa]
        );
    }


    public function listarCotizaciones()
    {
        $sql = "SELECT 
                v.idventa,
                DATE(v.fecha_hora) as fecha,
                v.idcliente,
                COALESCE(p.nombre, 'SIN CLIENTE') AS cliente,
                u.nombre AS usuario,
                v.tipo_comprobante,
                v.serie_comprobante,
                v.num_comprobante,
                v.total_venta,
                v.impuesto,
                v.estado
            FROM venta v
            LEFT JOIN persona p ON v.idcliente = p.idpersona
            LEFT JOIN usuario u ON v.idusuario = u.idusuario
            WHERE v.tipo_comprobante = 'Cotizacion'
            ORDER BY v.idventa DESC";

        return $this->conexion->getDataAll($sql);
    }


    /**
     * Obtiene una venta como plantilla para registrar una nueva venta.
     *
     * No copia correlativo, estado SUNAT, movimientos de caja ni fechas
     * anteriores. Los productos se contrastan con el stock disponible actual.
     */
    public function obtenerDatosDuplicacion(int $idventa): ?array
    {
        if ($idventa <= 0) {
            return null;
        }

        $cabecera = $this->conexion->getData(
            "SELECT
                v.idventa,
                v.idcliente,
                v.tipo_comprobante,
                v.serie_comprobante,
                v.num_comprobante,
                v.total_venta,
                v.descuento_total,
                v.descuento_porcentaje,
                v.tipo_pago,
                v.idforma_pago,
                v.estado,

                p.tipo_documento,
                p.num_documento,
                p.nombre AS cliente,
                p.direccion,
                p.telefono,
                p.email,

                (
                    SELECT COUNT(*)
                    FROM venta_cuota vc
                    WHERE vc.idventa = v.idventa
                ) AS numero_cuotas

             FROM venta v

             INNER JOIN persona p
                ON p.idpersona = v.idcliente

             WHERE v.idventa = ?

             LIMIT 1",
            [$idventa]
        );

        if (!is_array($cabecera)) {
            return null;
        }

        $detalles = $this->conexion->getDataAll(
            "SELECT
                dv.iddetalle_venta,
                dv.idarticulo,
                dv.cantidad,
                dv.precio_compra AS precio_compra_original,
                dv.precio_venta,
                dv.descuento,
                dv.codigo_afectacion_igv,
                dv.porcentaje_igv,
                dv.unidad_medida_sunat,
                dv.codigo_producto_sunat,

                a.codigo,
                a.nombre AS articulo,
                COALESCE(a.stock, 0) AS stock_disponible,
                a.condicion AS articulo_activo,

                COALESCE(
                    (
                        SELECT di.iddetalle_ingreso
                        FROM detalle_ingreso di
                        WHERE di.idarticulo = dv.idarticulo
                          AND COALESCE(di.stock_venta, 0) > 0
                        ORDER BY
                            CASE
                                WHEN di.stock_estado = '1' THEN 0
                                ELSE 1
                            END,
                            di.iddetalle_ingreso ASC
                        LIMIT 1
                    ),
                    0
                ) AS idingreso,

                COALESCE(
                    (
                        SELECT di.precio_compra
                        FROM detalle_ingreso di
                        WHERE di.idarticulo = dv.idarticulo
                          AND COALESCE(di.stock_venta, 0) > 0
                        ORDER BY
                            CASE
                                WHEN di.stock_estado = '1' THEN 0
                                ELSE 1
                            END,
                            di.iddetalle_ingreso ASC
                        LIMIT 1
                    ),
                    dv.precio_compra,
                    0
                ) AS precio_compra_actual

             FROM detalle_venta dv

             INNER JOIN articulo a
                ON a.idarticulo = dv.idarticulo

             WHERE dv.idventa = ?
               AND dv.estado = 1

             ORDER BY dv.iddetalle_venta ASC",
            [$idventa]
        );

        return [
            'cabecera' => $cabecera,
            'detalles' => is_array($detalles)
                ? $detalles
                : []
        ];
    }


    public function obtenerPagosVenta($idventa)
    {
        $sql = "SELECT 
                    fp.nombre,
                    vp.monto
                FROM venta_pago vp
                INNER JOIN forma_pago fp 
                    ON fp.idforma_pago = vp.idforma_pago
                WHERE vp.idventa = ?";

        return $this->conexion->getDataAll($sql, [$idventa]);
    }

    public function obtenerCuotasVenta($idventa)
    {
        $sql = "SELECT
                vc.idventa_cuota,
                vc.idventa,
                vc.numero_cuota,
                vc.codigo,
                vc.monto,

                DATE_FORMAT(
                    vc.fecha_vencimiento,
                    '%d/%m/%Y'
                ) AS fecha_vencimiento,

                vc.monto_pagado,

                CASE
                    WHEN vc.fecha_pago IS NULL
                    THEN NULL
                    ELSE DATE_FORMAT(
                        vc.fecha_pago,
                        '%d/%m/%Y %H:%i'
                    )
                END AS fecha_pago,

                GREATEST(
                    vc.monto - vc.monto_pagado,
                    0
                ) AS saldo,

                vc.estado

            FROM venta_cuota vc

            WHERE vc.idventa = ?

            ORDER BY vc.numero_cuota ASC";

        $resultado = $this->conexion->getDataAll(
            $sql,
            [(int)$idventa]
        );

        return is_array($resultado)
            ? $resultado
            : [];
    }

    public function buscarProductoPorCodigo($codigo)
    {
        $codigo = preg_replace(
            '/[\x00-\x1F\x7F]/u',
            '',
            trim((string)$codigo)
        );

        if ($codigo === '') {
            return null;
        }

        /*
         * La búsqueda es global: no depende de la categoría activa.
         * TRIM/UPPER evita fallos por espacios o diferencias de mayúsculas.
         * Se toma el primer lote con stock disponible (FIFO).
         */
        $sql = "SELECT
                    di.iddetalle_ingreso AS idingreso,
                    a.idarticulo,
                    TRIM(CAST(a.codigo AS CHAR)) AS codigo,
                    a.nombre,
                    di.precio_compra,
                    di.precio_venta,
                    a.codigo_afectacion_igv,
                    a.porcentaje_igv,
                    a.unidad_medida_sunat,
                    a.codigo_producto_sunat,
                    di.stock_venta AS stock
                FROM articulo a
                INNER JOIN detalle_ingreso di
                    ON di.idarticulo = a.idarticulo
                WHERE a.condicion = 1
                  AND UPPER(TRIM(CAST(a.codigo AS CHAR))) = UPPER(TRIM(?))
                  AND COALESCE(di.stock_venta, 0) > 0
                ORDER BY
                    CASE
                        WHEN di.stock_estado = '1' THEN 0
                        ELSE 1
                    END,
                    di.iddetalle_ingreso ASC
                LIMIT 1";

        return $this->conexion->getData(
            $sql,
            [$codigo]
        );
    }


    //funcion para selecciolnar el numero de factura
    public function numero_venta($tipo_comprobante)
    {

        $sql = "SELECT num_comprobante FROM $this->tableName WHERE tipo_comprobante='$tipo_comprobante' ORDER BY idventa DESC limit 1 ";
        return $this->conexion->getDataAll($sql);
    }
    //funcion para seleccionar la serie de la factura
    public function numero_serie($tipo_comprobante)
    {

        $sql = "SELECT serie_comprobante ,num_comprobante FROM $this->tableName WHERE tipo_comprobante='$tipo_comprobante' ORDER BY idventa DESC limit 1";

        return $this->conexion->getDataAll($sql);
    }

    public function listarActivosVenta()
    {
        $sql = "SELECT 
				a.idarticulo,
				a.codigo,
				a.nombre,
				a.precio_venta,
				a.stock,
				a.imagen 
			FROM articulo a 
			WHERE a.condicion = 1 AND a.stock > 0";
        return $this->conexion->getData($sql);
    }


    /**
     * Configuración tributaria efectiva de empresa/sucursal.
     */
    public function obtenerConfiguracionTributariaEfectiva(
        ?int $idsucursal = null
    ): array {
        $empresa = $this->conexion->getData(
            "SELECT
                id_negocio,
                monto_impuesto,
                moneda,
                simbolo,
                tipo_operacion_sunat_predeterminado,
                codigo_afectacion_igv_predeterminado,
                porcentaje_igv_predeterminado,
                unidad_medida_sunat_predeterminada,
                permitir_cambio_afectacion_venta,
                precios_incluyen_impuesto
             FROM datos_negocio
             WHERE condicion = 1
             ORDER BY id_negocio DESC
             LIMIT 1"
        );

        if (!is_array($empresa)) {
            throw new RuntimeException(
                'No existe una configuración tributaria activa.'
            );
        }

        $configuracion = [
            'tipo_operacion_sunat' => trim(
                (string)(
                    $empresa['tipo_operacion_sunat_predeterminado']
                    ?? '0101'
                )
            ),
            'codigo_afectacion_igv' => trim(
                (string)(
                    $empresa['codigo_afectacion_igv_predeterminado']
                    ?? '10'
                )
            ),
            'porcentaje_igv' => round(
                (float)(
                    $empresa['porcentaje_igv_predeterminado']
                    ?? $empresa['monto_impuesto']
                    ?? 18
                ),
                2
            ),
            'unidad_medida_sunat' => strtoupper(
                trim(
                    (string)(
                        $empresa['unidad_medida_sunat_predeterminada']
                        ?? 'NIU'
                    )
                )
            ),
            'permitir_cambio_afectacion_venta' =>
                (int)($empresa['permitir_cambio_afectacion_venta'] ?? 0),
            'precios_incluyen_impuesto' =>
                (int)($empresa['precios_incluyen_impuesto'] ?? 1),
            'moneda_codigo' => 'PEN',
            'moneda' => trim((string)($empresa['moneda'] ?? 'SOLES')),
            'simbolo' => trim((string)($empresa['simbolo'] ?? 'S/'))
        ];

        $idsucursal = (int)$idsucursal;

        if ($idsucursal > 0) {
            $sucursal = $this->conexion->getData(
                "SELECT
                    hereda_configuracion_tributaria,
                    tipo_operacion_sunat,
                    codigo_afectacion_igv_predeterminada,
                    porcentaje_igv_predeterminado,
                    unidad_medida_sunat_predeterminada
                 FROM sucursal
                 WHERE idsucursal = ?
                   AND activo = 1
                 LIMIT 1",
                [$idsucursal]
            );

            if (
                is_array($sucursal)
                && (int)($sucursal['hereda_configuracion_tributaria'] ?? 1) === 0
            ) {
                $configuracion['tipo_operacion_sunat'] = trim(
                    (string)(
                        $sucursal['tipo_operacion_sunat']
                        ?? $configuracion['tipo_operacion_sunat']
                    )
                );
                $configuracion['codigo_afectacion_igv'] = trim(
                    (string)(
                        $sucursal['codigo_afectacion_igv_predeterminada']
                        ?? $configuracion['codigo_afectacion_igv']
                    )
                );
                $configuracion['porcentaje_igv'] = round(
                    (float)(
                        $sucursal['porcentaje_igv_predeterminado']
                        ?? $configuracion['porcentaje_igv']
                    ),
                    2
                );
                $configuracion['unidad_medida_sunat'] = strtoupper(
                    trim(
                        (string)(
                            $sucursal['unidad_medida_sunat_predeterminada']
                            ?? $configuracion['unidad_medida_sunat']
                        )
                    )
                );
            }
        }

        if ($configuracion['tipo_operacion_sunat'] === '') {
            $configuracion['tipo_operacion_sunat'] = '0101';
        }
        if (!in_array($configuracion['codigo_afectacion_igv'], ['10','20','30','40'], true)) {
            $configuracion['codigo_afectacion_igv'] = '10';
        }
        if ($configuracion['codigo_afectacion_igv'] !== '10') {
            $configuracion['porcentaje_igv'] = 0.00;
        }
        if ($configuracion['unidad_medida_sunat'] === '') {
            $configuracion['unidad_medida_sunat'] = 'NIU';
        }

        return $configuracion;
    }

    public function listarTiposOperacionSunat(): array
    {
        $resultado = $this->conexion->getDataAll(
            "SELECT codigo, descripcion, comprobantes
             FROM sunat_catalogo_51_tipo_operacion
             WHERE activo = 1
             ORDER BY orden ASC, codigo ASC"
        );

        return is_array($resultado)
            ? $resultado
            : [];
    }

    /**
     * Calcula y valida los importes tributarios de la venta.
     * La clasificación siempre se obtiene del producto en base de datos.
     */
    public function calcularTributacionVenta(
        array $idarticulos,
        array $cantidades,
        array $preciosVenta,
        array $descuentosItem,
        float $descuentoGlobal,
        ?int $idsucursal = null,
        ?string $tipoOperacionSolicitada = null
    ): array {
        $cantidadLineas = count($idarticulos);

        if (
            $cantidadLineas === 0
            || count($cantidades) !== $cantidadLineas
            || count($preciosVenta) !== $cantidadLineas
        ) {
            throw new RuntimeException(
                'Los datos tributarios del detalle están incompletos.'
            );
        }

        if (count($descuentosItem) !== $cantidadLineas) {
            $descuentosItem = array_fill(0, $cantidadLineas, 0.00);
        }

        $configuracion = $this->obtenerConfiguracionTributariaEfectiva(
            $idsucursal
        );

        $tipoOperacion = trim((string)$tipoOperacionSolicitada);

        if (
            $tipoOperacion === ''
            || (int)$configuracion['permitir_cambio_afectacion_venta'] !== 1
        ) {
            $tipoOperacion = (string)$configuracion['tipo_operacion_sunat'];
        }

        $tipoExiste = $this->conexion->getData(
            "SELECT codigo
             FROM sunat_catalogo_51_tipo_operacion
             WHERE codigo = ?
               AND activo = 1
             LIMIT 1",
            [$tipoOperacion]
        );

        if (!is_array($tipoExiste)) {
            throw new RuntimeException(
                'El tipo de operación SUNAT seleccionado no es válido.'
            );
        }

        $idsUnicos = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $idarticulos),
                    static fn(int $id): bool => $id > 0
                )
            )
        );

        if (count($idsUnicos) !== count(array_unique(array_map('intval', $idarticulos)))) {
            throw new RuntimeException('Existe un producto inválido en la venta.');
        }

        $placeholders = implode(',', array_fill(0, count($idsUnicos), '?'));
        $productos = $this->conexion->getDataAll(
            "SELECT
                a.idarticulo,
                a.codigo,
                a.nombre,
                a.condicion,
                a.codigo_afectacion_igv,
                a.porcentaje_igv,
                a.unidad_medida_sunat,
                a.codigo_producto_sunat,
                c.codigo_tributo,
                c.nombre_tributo,
                c.tipo_tributo
             FROM articulo a
             LEFT JOIN sunat_catalogo_07_afectacion_igv c
                ON c.codigo = a.codigo_afectacion_igv
             WHERE a.idarticulo IN ({$placeholders})",
            $idsUnicos
        );

        $mapaProductos = [];
        foreach (is_array($productos) ? $productos : [] as $producto) {
            $mapaProductos[(int)$producto['idarticulo']] = $producto;
        }

        $preciosIncluyen = (int)$configuracion['precios_incluyen_impuesto'] === 1;
        $lineasPrevias = [];
        $subtotalDocumento = 0.00;

        foreach ($idarticulos as $indice => $idArticulo) {
            $idArticulo = (int)$idArticulo;
            $producto = $mapaProductos[$idArticulo] ?? null;

            if (!is_array($producto) || (int)($producto['condicion'] ?? 0) !== 1) {
                throw new RuntimeException(
                    'Uno de los productos ya no se encuentra activo.'
                );
            }

            $cantidad = round((float)$cantidades[$indice], 3);
            $precio = round((float)$preciosVenta[$indice], 6);
            $descuentoItem = max(
                round((float)$descuentosItem[$indice], 2),
                0.00
            );

            if ($cantidad <= 0 || $precio < 0) {
                throw new RuntimeException(
                    'Existe una cantidad o precio de venta inválido.'
                );
            }

            $afectacion = trim((string)($producto['codigo_afectacion_igv'] ?? ''));
            if (!in_array($afectacion, ['10','20','30','40'], true)) {
                $afectacion = (string)$configuracion['codigo_afectacion_igv'];
            }

            $porcentaje = $afectacion === '10'
                ? round((float)($producto['porcentaje_igv'] ?? $configuracion['porcentaje_igv']), 2)
                : 0.00;

            if ($afectacion === '10' && $porcentaje <= 0) {
                $porcentaje = max((float)$configuracion['porcentaje_igv'], 0.00);
            }

            $factor = $afectacion === '10' && $porcentaje > 0
                ? 1 + ($porcentaje / 100)
                : 1.00;

            $importeEntrada = round($cantidad * $precio, 6);
            $descuentoItem = min($descuentoItem, round($importeEntrada, 2));
            $importeEntradaNeto = max($importeEntrada - $descuentoItem, 0.00);

            $importeAntesDescuento = $preciosIncluyen
                ? $importeEntrada
                : ($afectacion === '10' ? $importeEntrada * $factor : $importeEntrada);
            $importeDespuesItem = $preciosIncluyen
                ? $importeEntradaNeto
                : ($afectacion === '10' ? $importeEntradaNeto * $factor : $importeEntradaNeto);

            $importeAntesDescuento = round($importeAntesDescuento, 2);
            $importeDespuesItem = round($importeDespuesItem, 2);
            $subtotalDocumento += $importeDespuesItem;

            $unidad = strtoupper(trim((string)($producto['unidad_medida_sunat'] ?? '')));
            if ($unidad === '') {
                $unidad = (string)$configuracion['unidad_medida_sunat'];
            }

            $tributo = $this->datosTributoPorAfectacion(
                $afectacion,
                $producto
            );

            $lineasPrevias[] = [
                'indice' => $indice,
                'idarticulo' => $idArticulo,
                'codigo_articulo' => trim((string)($producto['codigo'] ?? '')),
                'descripcion_articulo' => trim((string)($producto['nombre'] ?? '')),
                'cantidad' => $cantidad,
                'precio_entrada' => $precio,
                'importe_antes_descuento' => $importeAntesDescuento,
                'importe_despues_item' => $importeDespuesItem,
                'codigo_afectacion_igv' => $afectacion,
                'porcentaje_igv' => $porcentaje,
                'factor' => $factor,
                'unidad_medida_sunat' => $unidad,
                'codigo_producto_sunat' => trim((string)($producto['codigo_producto_sunat'] ?? '')),
                'codigo_tributo' => $tributo['codigo_tributo'],
                'nombre_tributo' => $tributo['nombre_tributo'],
                'tipo_tributo' => $tributo['tipo_tributo']
            ];
        }

        $subtotalDocumento = round($subtotalDocumento, 2);
        $descuentoGlobal = max(round($descuentoGlobal, 2), 0.00);
        $descuentoGlobal = min($descuentoGlobal, $subtotalDocumento);

        $lineas = [];
        $descuentoAsignado = 0.00;
        $totalGravado = 0.00;
        $totalExonerado = 0.00;
        $totalInafecto = 0.00;
        $totalExportacion = 0.00;
        $totalIgv = 0.00;
        $totalVenta = 0.00;
        $ultimaLinea = count($lineasPrevias) - 1;

        foreach ($lineasPrevias as $posicion => $linea) {
            if ($posicion === $ultimaLinea) {
                $descuentoGlobalLinea = round(
                    $descuentoGlobal - $descuentoAsignado,
                    2
                );
            } else {
                $descuentoGlobalLinea = $subtotalDocumento > 0
                    ? round(
                        $descuentoGlobal
                        * ((float)$linea['importe_despues_item'] / $subtotalDocumento),
                        2
                    )
                    : 0.00;
                $descuentoAsignado += $descuentoGlobalLinea;
            }

            $descuentoGlobalLinea = min(
                max($descuentoGlobalLinea, 0.00),
                (float)$linea['importe_despues_item']
            );

            $totalLinea = round(
                (float)$linea['importe_despues_item'] - $descuentoGlobalLinea,
                2
            );

            $afectacion = (string)$linea['codigo_afectacion_igv'];
            $factor = (float)$linea['factor'];

            if ($afectacion === '10' && $factor > 1) {
                $base = round($totalLinea / $factor, 2);
                $igv = round($totalLinea - $base, 2);
                $totalGravado += $base;
            } else {
                $base = $totalLinea;
                $igv = 0.00;

                if ($afectacion === '20') {
                    $totalExonerado += $base;
                } elseif ($afectacion === '30') {
                    $totalInafecto += $base;
                } elseif ($afectacion === '40') {
                    $totalExportacion += $base;
                }
            }

            $cantidad = (float)$linea['cantidad'];
            $descuentoLinea = round(
                (float)$linea['importe_antes_descuento'] - $totalLinea,
                2
            );

            $linea['descuento_global_linea'] = $descuentoGlobalLinea;
            $linea['descuento_linea'] = max($descuentoLinea, 0.00);
            $linea['base_imponible'] = $base;
            $linea['monto_igv'] = $igv;
            $linea['total_linea'] = $totalLinea;
            $linea['valor_unitario_sin_igv'] = $cantidad > 0
                ? round($base / $cantidad, 6)
                : 0.00;
            $linea['precio_unitario_con_impuesto'] = $cantidad > 0
                ? round($totalLinea / $cantidad, 6)
                : 0.00;

            $lineas[] = $linea;
            $totalIgv += $igv;
            $totalVenta += $totalLinea;
        }

        $esOperacionExportacion = str_starts_with(
            $tipoOperacion,
            '02'
        );

        if ($totalExportacion > 0.009 && !$esOperacionExportacion) {
            throw new RuntimeException(
                'Los productos configurados como exportación requieren un tipo de operación SUNAT de exportación.'
            );
        }

        if (
            $esOperacionExportacion
            && (
                $totalGravado > 0.009
                || $totalExonerado > 0.009
                || $totalInafecto > 0.009
            )
        ) {
            throw new RuntimeException(
                'Una operación de exportación no puede mezclar productos de venta interna.'
            );
        }

        if ($esOperacionExportacion && $totalExportacion <= 0.009) {
            throw new RuntimeException(
                'El tipo de operación seleccionado es de exportación, pero la venta no contiene productos configurados como exportación.'
            );
        }

        return [
            'tipo_operacion_sunat' => $tipoOperacion,
            'moneda_codigo' => (string)$configuracion['moneda_codigo'],
            'precios_incluyen_impuesto' => $preciosIncluyen ? 1 : 0,
            'porcentaje_igv_predeterminado' => round((float)$configuracion['porcentaje_igv'], 2),
            'subtotal_documento' => $subtotalDocumento,
            'descuento_global' => $descuentoGlobal,
            'total_gravado' => round($totalGravado, 2),
            'total_exonerado' => round($totalExonerado, 2),
            'total_inafecto' => round($totalInafecto, 2),
            'total_exportacion' => round($totalExportacion, 2),
            'total_igv' => round($totalIgv, 2),
            'total_venta' => round($totalVenta, 2),
            'lineas' => $lineas,
            'configuracion' => $configuracion
        ];
    }

    private function datosTributoPorAfectacion(
        string $afectacion,
        array $producto = []
    ): array {
        $predeterminados = [
            '10' => ['1000', 'IGV', 'VAT'],
            '20' => ['9997', 'EXO', 'VAT'],
            '30' => ['9998', 'INA', 'FRE'],
            '40' => ['9995', 'EXP', 'FRE']
        ];

        $datos = $predeterminados[$afectacion]
            ?? $predeterminados['10'];

        return [
            'codigo_tributo' => trim((string)($producto['codigo_tributo'] ?? $datos[0])) ?: $datos[0],
            'nombre_tributo' => trim((string)($producto['nombre_tributo'] ?? $datos[1])) ?: $datos[1],
            'tipo_tributo' => trim((string)($producto['tipo_tributo'] ?? $datos[2])) ?: $datos[2]
        ];
    }


    /**
     * Reporte detallado de ventas: una fila por producto.
     * Si se reciben IDs, limita el resultado a las ventas filtradas en ListSales.
     */
    public function listarReporteVentasDetallado(array $idsVenta = []): array
    {
        $idsVenta = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $idsVenta),
                    static fn(int $id): bool => $id > 0
                )
            )
        );

        if (count($idsVenta) === 0) {
            return [];
        }

        $placeholders = implode(
            ',',
            array_fill(0, count($idsVenta), '?')
        );

        $sql = "
            SELECT
                v.idventa,

                DATE_FORMAT(
                    v.fecha_hora,
                    '%d/%m/%Y %H:%i'
                ) AS fecha,

                v.tipo_comprobante,
                v.serie_comprobante,
                v.num_comprobante,

                COALESCE(
                    p.num_documento,
                    ''
                ) AS num_documento,

                COALESCE(
                    p.nombre,
                    'SIN CLIENTE'
                ) AS cliente,

                COALESCE(
                    a.codigo,
                    ''
                ) AS sku,

                COALESCE(
                    a.nombre,
                    'Producto'
                ) AS producto,

                dv.cantidad,

                CASE
                    WHEN dv.cantidad > 0
                         AND COALESCE(dv.total_linea, 0) > 0
                    THEN dv.total_linea / dv.cantidad
                    ELSE dv.precio_venta
                END AS precio,

                COALESCE(
                    NULLIF(
                        (
                            SELECT GROUP_CONCAT(
                                DISTINCT fpv.nombre
                                ORDER BY fpv.nombre
                                SEPARATOR ' + '
                            )
                            FROM venta_pago vp
                            INNER JOIN forma_pago fpv
                                ON fpv.idforma_pago = vp.idforma_pago
                            WHERE vp.idventa = v.idventa
                        ),
                        ''
                    ),
                    fp.nombre,
                    'No especificado'
                ) AS metodo_pago,

                v.total_venta,

                CASE
                    WHEN v.estado <> 'Aceptado'
                    THEN 'ANULADO'

                    WHEN v.tipo_comprobante NOT IN (
                        'Factura Electrónica',
                        'Boleta Electrónica'
                    )
                    THEN 'NO_APLICA'

                    WHEN vs.idventa_sunat IS NULL
                    THEN 'NO_ENVIADO'

                    WHEN COALESCE(
                        vs.document_id,
                        ''
                    ) = ''
                    THEN 'NO_ENVIADO'

                    WHEN COALESCE(
                        vs.estado_sunat,
                        ''
                    ) = ''
                    THEN 'PENDIENTE'

                    ELSE UPPER(
                        TRIM(
                            vs.estado_sunat
                        )
                    )
                END AS estado_sunat

            FROM venta v

            INNER JOIN detalle_venta dv
                ON dv.idventa = v.idventa

            INNER JOIN articulo a
                ON a.idarticulo = dv.idarticulo

            LEFT JOIN persona p
                ON p.idpersona = v.idcliente

            LEFT JOIN forma_pago fp
                ON fp.idforma_pago = v.idforma_pago

            LEFT JOIN venta_sunat vs
                ON vs.idventa = v.idventa

            WHERE v.tipo_comprobante <> 'Cotizacion'
              AND v.idventa IN ({$placeholders})

            ORDER BY
                v.idventa DESC,
                dv.iddetalle_venta ASC
        ";

        $resultado = $this->conexion->getDataAll(
            $sql,
            $idsVenta
        );

        return is_array($resultado)
            ? $resultado
            : [];
    }


    public function getConexion()
    {
        return $this->conexion;
    }
}
