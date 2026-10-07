<?php
//incluir la conexion de base de datos
require_once __DIR__ . '/../Config/Conexion.php';
class Consult{


  private $tableName='categoria';
  private $conexion;

	//implementamos nuestro constructor
	public function __construct(){
		$this->conexion = new Conexion();
	}

  //listar registros
  public function comprasfecha($fecha_inicio,$fecha_fin){
    $sql="SELECT DATE(i.fecha_hora) as fecha, u.nombre as usuario, p.nombre as proveedor, i.tipo_comprobante, i.serie_comprobante, i.num_comprobante, i.total_compra,i.impuesto,i.estado FROM ingreso i INNER JOIN persona p ON i.idproveedor=p.idpersona INNER JOIN usuario u ON i.idusuario=u.idusuario WHERE DATE(i.fecha_hora)>='$fecha_inicio' AND DATE(i.fecha_hora)<='$fecha_fin'";
      return  $this->conexion->getDataAll($sql); 
  }

public function ventasfecha(
    $fecha_inicio,
    $fecha_fin
  ) {
    $ini = $fecha_inicio . ' 00:00:00';

    $fin = date(
      'Y-m-d',
      strtotime($fecha_fin . ' +1 day')
    ) . ' 00:00:00';

    $sql = "
      SELECT
        v.idventa,

        DATE_FORMAT(
          v.fecha_hora,
          '%d/%m/%Y %H:%i'
        ) AS fecha,

        COALESCE(
          u.nombre,
          'SIN USUARIO'
        ) AS usuario,

        COALESCE(
          p.nombre,
          'SIN CLIENTE'
        ) AS cliente,

        v.tipo_comprobante,
        v.serie_comprobante,
        v.num_comprobante,

        v.total_venta,

        COALESCE(
          nc_aceptadas.total_notas_credito,
          0
        ) AS total_notas_credito,

        ROUND(
          v.total_venta
          - COALESCE(
              nc_aceptadas.total_notas_credito,
              0
            ),
          2
        ) AS total_neto,

        v.impuesto,
        v.estado,

        v.idsucursal,
        v.idcaja,
        v.idapertura,

        COALESCE(
          s.nombre,
          'LEGACY'
        ) AS sucursal,

        CASE
          WHEN v.idcaja IS NULL
          THEN 'LEGACY'

          ELSE CONCAT(
            COALESCE(
              cf.codigo,
              'SIN CÓDIGO'
            ),
            ' - ',
            COALESCE(
              cf.nombre,
              'CAJA NO ENCONTRADA'
            )
          )
        END AS caja,

        CASE
          WHEN v.idapertura IS NULL
          THEN 'SIN VÍNCULO FÍSICO'

          ELSE CAST(
            v.idapertura AS CHAR
          )
        END AS apertura,

        CASE
          WHEN v.idcaja IS NULL
           AND v.idapertura IS NULL
          THEN 'LEGACY'

          ELSE 'CAJA_FISICA'
        END AS modo_caja

      FROM venta AS v

      LEFT JOIN persona AS p
        ON p.idpersona = v.idcliente

      LEFT JOIN usuario AS u
        ON u.idusuario = v.idusuario

      LEFT JOIN sucursal AS s
        ON s.idsucursal = v.idsucursal

      LEFT JOIN caja_fisica AS cf
        ON cf.idcaja = v.idcaja

      LEFT JOIN (
        SELECT
          nc.idventa,
          SUM(nc.total_nota)
            AS total_notas_credito

        FROM nota_credito AS nc

        INNER JOIN nota_credito_sunat AS ncs
          ON ncs.idnota_credito =
             nc.idnota_credito

        WHERE nc.estado = 'REGISTRADA'
          AND UPPER(ncs.estado_sunat) =
              'ACEPTADO'

        GROUP BY nc.idventa
      ) AS nc_aceptadas
        ON nc_aceptadas.idventa =
           v.idventa

      WHERE v.fecha_hora >= ?
        AND v.fecha_hora < ?

      ORDER BY
        v.fecha_hora DESC,
        v.idventa DESC
    ";

    $resultado = $this->conexion->getDataAll(
      $sql,
      [
        $ini,
        $fin
      ]
    );

    return is_array($resultado)
      ? $resultado
      : [];
  }

public function ventasfechacliente(
    $fecha_inicio,
    $fecha_fin,
    $idcliente
  ) {
    $ini = $fecha_inicio . ' 00:00:00';

    $fin = date(
      'Y-m-d',
      strtotime($fecha_fin . ' +1 day')
    ) . ' 00:00:00';

    $sql = "
      SELECT
        DATE(v.fecha_hora) AS fecha,
        u.nombre AS usuario,
        p.nombre AS cliente,
        v.tipo_comprobante,
        v.serie_comprobante,
        v.num_comprobante,
        v.total_venta,

        COALESCE(
          nc_aceptadas.total_notas_credito,
          0
        ) AS total_notas_credito,

        ROUND(
          v.total_venta
          - COALESCE(
              nc_aceptadas.total_notas_credito,
              0
            ),
          2
        ) AS total_neto,

        v.impuesto,
        v.estado

      FROM venta AS v

      INNER JOIN persona AS p
        ON v.idcliente = p.idpersona

      INNER JOIN usuario AS u
        ON v.idusuario = u.idusuario

      LEFT JOIN (
        SELECT
          nc.idventa,
          SUM(nc.total_nota)
            AS total_notas_credito

        FROM nota_credito AS nc

        INNER JOIN nota_credito_sunat AS ncs
          ON ncs.idnota_credito =
             nc.idnota_credito

        WHERE nc.estado = 'REGISTRADA'
          AND UPPER(ncs.estado_sunat) =
              'ACEPTADO'

        GROUP BY nc.idventa
      ) AS nc_aceptadas
        ON nc_aceptadas.idventa =
           v.idventa

      WHERE v.fecha_hora >= ?
        AND v.fecha_hora < ?
        AND v.idcliente = ?

      ORDER BY
        v.fecha_hora DESC,
        v.idventa DESC
    ";

    $resultado = $this->conexion->getDataAll(
      $sql,
      [
        $ini,
        $fin,
        (int)$idcliente
      ]
    );

    return is_array($resultado)
      ? $resultado
      : [];
  }

  public function totalcomprahoy(){
    $sql="SELECT IFNULL(SUM(total_compra),0) as total_compra FROM ingreso WHERE DATE(fecha_hora)=curdate()";
    return  $this->conexion->getDataAll($sql); 
  }

public function totalventahoy() {
    $sql = "
      SELECT
        ROUND(
          COALESCE(ventas.ventas_brutas, 0),
          2
        ) AS ventas_brutas,

        ROUND(
          COALESCE(notas.notas_credito, 0),
          2
        ) AS notas_credito,

        ROUND(
          COALESCE(ventas.ventas_brutas, 0)
          - COALESCE(notas.notas_credito, 0),
          2
        ) AS ventas_netas,

        ROUND(
          COALESCE(ventas.ventas_brutas, 0)
          - COALESCE(notas.notas_credito, 0),
          2
        ) AS total_venta

      FROM (
        SELECT
          SUM(v.total_venta)
            AS ventas_brutas

        FROM venta AS v

        WHERE DATE(v.fecha_hora) = CURDATE()
          AND v.estado = 'Aceptado'
      ) AS ventas

      CROSS JOIN (
        SELECT
          SUM(nc.total_nota)
            AS notas_credito

        FROM nota_credito AS nc

        INNER JOIN nota_credito_sunat AS ncs
          ON ncs.idnota_credito =
             nc.idnota_credito

        WHERE DATE(nc.fecha_hora) =
              CURDATE()
          AND nc.estado = 'REGISTRADA'
          AND UPPER(ncs.estado_sunat) =
              'ACEPTADO'
      ) AS notas
    ";

    return $this->conexion->getDataAll($sql);
  }

  public function comprasultimos_10dias(){
    $sql="SELECT DATE_FORMAT(fecha_hora,'%M') AS fecha, SUM(total_compra) AS total FROM ingreso GROUP BY MONTH(fecha_hora) ORDER BY fecha_hora DESC LIMIT 0,12";
    return  $this->conexion->getDataAll($sql); 
  }

public function ventasultimos_12meses() {
    $sql = "
      SELECT
        movimientos.fecha,

        ROUND(
          SUM(movimientos.ventas_brutas),
          2
        ) AS ventas_brutas,

        ROUND(
          SUM(movimientos.notas_credito),
          2
        ) AS notas_credito,

        ROUND(
          SUM(movimientos.ventas_brutas)
          - SUM(movimientos.notas_credito),
          2
        ) AS total

      FROM (
        SELECT
          DATE_FORMAT(
            v.fecha_hora,
            '%Y-%m-01'
          ) AS fecha,

          SUM(v.total_venta)
            AS ventas_brutas,

          0 AS notas_credito

        FROM venta AS v

        WHERE v.estado = 'Aceptado'

        GROUP BY
          DATE_FORMAT(
            v.fecha_hora,
            '%Y-%m'
          )

        UNION ALL

        SELECT
          DATE_FORMAT(
            nc.fecha_hora,
            '%Y-%m-01'
          ) AS fecha,

          0 AS ventas_brutas,

          SUM(nc.total_nota)
            AS notas_credito

        FROM nota_credito AS nc

        INNER JOIN nota_credito_sunat AS ncs
          ON ncs.idnota_credito =
             nc.idnota_credito

        WHERE nc.estado = 'REGISTRADA'
          AND UPPER(ncs.estado_sunat) =
              'ACEPTADO'

        GROUP BY
          DATE_FORMAT(
            nc.fecha_hora,
            '%Y-%m'
          )
      ) AS movimientos

      GROUP BY movimientos.fecha

      ORDER BY movimientos.fecha DESC

      LIMIT 12
    ";

    $resultado = $this->conexion->getDataAll(
      $sql
    );

    return is_array($resultado)
      ? array_reverse($resultado)
      : [];
  }

public function ventasultimos_12meses_grafica() {
    $sql = "
      SELECT
        movimientos.fecha,

        ROUND(
          SUM(movimientos.ventas_brutas),
          2
        ) AS ventas_brutas,

        ROUND(
          SUM(movimientos.notas_credito),
          2
        ) AS notas_credito,

        ROUND(
          SUM(movimientos.ventas_brutas)
          - SUM(movimientos.notas_credito),
          2
        ) AS total

      FROM (
        SELECT
          DATE_FORMAT(
            v.fecha_hora,
            '%Y-%m-01'
          ) AS fecha,

          SUM(v.total_venta)
            AS ventas_brutas,

          0 AS notas_credito

        FROM venta AS v

        WHERE v.estado = 'Aceptado'

        GROUP BY
          DATE_FORMAT(
            v.fecha_hora,
            '%Y-%m'
          )

        UNION ALL

        SELECT
          DATE_FORMAT(
            nc.fecha_hora,
            '%Y-%m-01'
          ) AS fecha,

          0 AS ventas_brutas,

          SUM(nc.total_nota)
            AS notas_credito

        FROM nota_credito AS nc

        INNER JOIN nota_credito_sunat AS ncs
          ON ncs.idnota_credito =
             nc.idnota_credito

        WHERE nc.estado = 'REGISTRADA'
          AND UPPER(ncs.estado_sunat) =
              'ACEPTADO'

        GROUP BY
          DATE_FORMAT(
            nc.fecha_hora,
            '%Y-%m'
          )
      ) AS movimientos

      GROUP BY movimientos.fecha

      ORDER BY movimientos.fecha DESC

      LIMIT 12
    ";

    $resultado = $this->conexion->getDataAll(
      $sql
    );

    return is_array($resultado)
      ? array_reverse($resultado)
      : [];
  }

  public function comparsultimos_12meses_grafica(){
    $sql="SELECT DATE_FORMAT(fecha_hora,'%M') AS fecha, SUM(total_compra) AS total FROM ingreso GROUP BY MONTH(fecha_hora) ORDER BY fecha_hora DESC LIMIT 0,12";
    return  $this->conexion->getDataAll($sql); 
}

public function ventas_grafica() {
    $sql = "
      SELECT
        movimientos.fecha,

        ROUND(
          SUM(movimientos.ventas_brutas),
          2
        ) AS ventas_brutas,

        ROUND(
          SUM(movimientos.notas_credito),
          2
        ) AS notas_credito,

        ROUND(
          SUM(movimientos.ventas_brutas)
          - SUM(movimientos.notas_credito),
          2
        ) AS total

      FROM (
        SELECT
          DATE_FORMAT(
            v.fecha_hora,
            '%Y-%m-01'
          ) AS fecha,

          SUM(v.total_venta)
            AS ventas_brutas,

          0 AS notas_credito

        FROM venta AS v

        WHERE v.estado = 'Aceptado'

        GROUP BY
          DATE_FORMAT(
            v.fecha_hora,
            '%Y-%m'
          )

        UNION ALL

        SELECT
          DATE_FORMAT(
            nc.fecha_hora,
            '%Y-%m-01'
          ) AS fecha,

          0 AS ventas_brutas,

          SUM(nc.total_nota)
            AS notas_credito

        FROM nota_credito AS nc

        INNER JOIN nota_credito_sunat AS ncs
          ON ncs.idnota_credito =
             nc.idnota_credito

        WHERE nc.estado = 'REGISTRADA'
          AND UPPER(ncs.estado_sunat) =
              'ACEPTADO'

        GROUP BY
          DATE_FORMAT(
            nc.fecha_hora,
            '%Y-%m'
          )
      ) AS movimientos

      GROUP BY movimientos.fecha

      ORDER BY movimientos.fecha DESC

      LIMIT 12
    ";

    $resultado = $this->conexion->getDataAll(
      $sql
    );

    return is_array($resultado)
      ? array_reverse($resultado)
      : [];
  }
  public function compras_grafica(){
    $sql="SELECT DATE(fecha_hora) AS fecha, SUM(total_compra) AS total FROM ingreso GROUP BY MONTH(fecha_hora) ORDER BY fecha_hora DESC LIMIT 0,12";
    return  $this->conexion->getDataAll($sql); 
  }

  public function cantidadclientes(){
    $sql="SELECT COUNT(*) totalc FROM persona WHERE tipo_persona='Cliente'";
    return  $this->conexion->getDataAll($sql); 
  }

  public function cantidadproveedores(){
    $sql="SELECT COUNT(*) totalp FROM persona WHERE tipo_persona='Proveedor'";
    return  $this->conexion->getDataAll($sql); 
  }

  public function cantidadarticulos(){
    $sql="SELECT COUNT(*) totalar FROM articulo WHERE condicion=1";
    return  $this->conexion->getDataAll($sql); 
  }
  public function totalstock(){
    $sql="SELECT SUM(stock) AS totalstock FROM articulo";
    return  $this->conexion->getDataAll($sql); 
  }

  public function cantidadcategorias(){
    $sql="SELECT COUNT(*) totalca FROM categoria WHERE condicion=1";
    return  $this->conexion->getDataAll($sql); 
  }

  public function listaventasarticulos($fecha_inicio,$fecha_fin){
    $sql="SELECT a.nombre AS articulo, a.codigo, SUM(d.cantidad) AS cantidad, SUM(d.precio_venta)AS precio_venta, d.descuento, SUM(d.cantidad*d.precio_venta-d.descuento) AS subtotal FROM detalle_venta d INNER JOIN articulo a ON d.idarticulo=a.idarticulo INNER JOIN venta v ON v.idventa=d.idventa WHERE DATE(v.fecha_hora)>='$fecha_inicio' AND DATE(v.fecha_hora)<='$fecha_fin' GROUP BY a.codigo";
  return  $this->conexion->getDataAll($sql); 
  }

  public function listacomprasarticulos($fecha_inicio,$fecha_fin){
    $sql="SELECT a.nombre AS articulo, a.codigo, SUM(d.cantidad) AS cantidad, SUM(d.precio_compra)AS precio_compra, SUM(d.cantidad*d.precio_compra) AS subtotal FROM detalle_ingreso d INNER JOIN articulo a ON d.idarticulo=a.idarticulo INNER JOIN ingreso i ON i.idingreso=d.idingreso WHERE DATE(i.fecha_hora)>='$fecha_inicio' AND DATE(i.fecha_hora)<='$fecha_fin' GROUP BY a.codigo";
  return  $this->conexion->getDataAll($sql); 
  }

  public function cateogriasMasVendidas(){
    $sql="SELECT SUM(dv.cantidad) as cantidad,c.nombre AS categoria FROM detalle_venta dv INNER JOIN articulo a ON dv.idarticulo=a.idarticulo INNER JOIN categoria c ON a.idcategoria=c.idcategoria GROUP BY c.nombre";
    return  $this->conexion->getDataAll($sql);

  }
/*public function kardex_ingreso($idarticulo){
$sql="SELECT DATE_FORMAT(fecha, '%Y %m %d') AS fecha,m.detalle,IF(tipo=0,m.cantidad,0) AS cantidadi,IF(tipo=0,m.preciou,0) AS costoui,IF(tipo=0,m.total,0) AS totali ,IF(tipo=1,m.cantidad,0) AS cantidads,IF(tipo=1,m.preciou,0) AS costous ,IF(tipo=1,m.total,0) AS totals, IF(tipo=0,m.cantidad,0) AS cantidadex ,(SELECT precio_venta FROM detalle_ingreso WHERE idarticulo=a.idarticulo ORDER BY iddetalle_ingreso DESC LIMIT 0,1) AS costouex ,((SELECT precio_venta FROM detalle_ingreso WHERE idarticulo=a.idarticulo ORDER BY iddetalle_ingreso DESC LIMIT 0,1) )* a.stock AS totalex  FROM

(SELECT 0 As tipo,CONCAT(i.tipo_comprobante,' ',i.serie_comprobante,'-',i.num_comprobante) AS detalle, di.idarticulo, di.cantidad AS cantidad, di.precio_compra AS preciou, i.fecha_hora AS fecha,i.total_compra AS total,i.f_registro AS f_registro FROM ingreso i INNER JOIN detalle_ingreso di ON i.idingreso=di.idingreso WHERE i.estado='Aceptado'
 UNION ALL
  SELECT 1 As tipo,CONCAT(v.tipo_comprobante,' ',v.serie_comprobante,'-',v.num_comprobante) AS detalle, dv.idarticulo, dv.cantidad AS cantidad, dv.precio_venta AS preciou, v.fecha_hora AS fecha, v.total_venta AS total,v.f_registro AS f_registro FROM venta v INNER JOIN detalle_venta dv ON v.idventa=dv.idventa WHERE v.estado='Aceptado' )
 AS m INNER JOIN articulo a ON m.idarticulo = a.idarticulo WHERE m.idarticulo='$idarticulo' ORDER BY f_registro ASC";
    return  $this->conexion->getDataAll($sql); 
}*/


public function kardex_ingreso($idarticulo){
    $sql="SELECT * FROM kardex WHERE idarticulo=? AND estado='Activo' ORDER BY fecha DESC, id DESC";
    return $this->conexion->getDataAll($sql, [(int)$idarticulo]);
}

  /**
   * Catálogos del reporte de inventario valorizado.
   */
  public function filtrosInventarioValorizado(){
    return [
      'categorias' => $this->conexion->getDataAll(
        "SELECT idcategoria, nombre FROM categoria WHERE condicion=1 ORDER BY nombre ASC"
      ),
      'subcategorias' => $this->conexion->getDataAll(
        "SELECT idsubcategoria, idcategoria, nombre FROM subcategoria WHERE estado=1 ORDER BY nombre ASC"
      ),
      'almacenes' => $this->conexion->getDataAll(
        "SELECT idalmacen, nombre FROM almacen WHERE estado=1 ORDER BY nombre ASC"
      ),
      'productos' => $this->conexion->getDataAll(
        "SELECT idarticulo, codigo, nombre, stock FROM articulo WHERE condicion=1 ORDER BY nombre ASC"
      )
    ];
  }

  /**
   * Reporte de inventario valorizado.
   *
   * El saldo físico proviene de articulo.stock. El saldo valorizado se obtiene
   * de las capas FIFO aún disponibles (detalle_ingreso.stock_venta). De esta
   * forma el valor actual no se recalcula usando el último precio de compra.
   */
  public function inventarioValorizado(
    $fechaInicio,
    $fechaFin,
    $idcategoria=0,
    $idsubcategoria=0,
    $idalmacen=0,
    $buscar=''
  ){
    $where = ["a.condicion = 1"];
    $params = [$fechaInicio, $fechaInicio, $fechaFin, $fechaInicio, $fechaFin];

    if ((int)$idcategoria > 0) {
      $where[] = "a.idcategoria = ?";
      $params[] = (int)$idcategoria;
    }
    if ((int)$idsubcategoria > 0) {
      $where[] = "a.idsubcategoria = ?";
      $params[] = (int)$idsubcategoria;
    }
    if ((int)$idalmacen > 0) {
      $where[] = "a.idalmacen = ?";
      $params[] = (int)$idalmacen;
    }
    $buscar = trim((string)$buscar);
    if ($buscar !== '') {
      $where[] = "(a.nombre LIKE ? OR a.codigo LIKE ?)";
      $params[] = '%' . $buscar . '%';
      $params[] = '%' . $buscar . '%';
    }

    $sql = "SELECT
              a.idarticulo,
              COALESCE(a.codigo, '') AS codigo,
              a.nombre AS producto,
              COALESCE(c.nombre, 'SIN CATEGORÍA') AS categoria,
              COALESCE(sc.nombre, 'SIN SUBCATEGORÍA') AS subcategoria,
              COALESCE(al.nombre, 'SIN ALMACÉN') AS almacen,
              COALESCE(a.stock, 0) AS saldo_actual,
              COALESCE(a.precio_compra, 0) AS ultimo_costo_referencia,
              COALESCE(e.entradas, 0) AS entradas,
              COALESCE(e.valor_entradas, 0) AS valor_entradas,
              COALESCE(s.salidas, 0) AS salidas,
              COALESCE(s.valor_salidas, 0) AS valor_salidas,
              COALESCE(l.stock_fifo, 0) AS stock_fifo,
              COALESCE(l.valor_fifo, 0) AS saldo_valorizado,
              CASE
                WHEN COALESCE(l.stock_fifo, 0) > 0
                THEN ROUND(l.valor_fifo / l.stock_fifo, 4)
                ELSE COALESCE(a.precio_compra, 0)
              END AS costo_promedio_actual,
              (COALESCE(a.stock, 0) - COALESCE(l.stock_fifo, 0)) AS diferencia_stock,
              COALESCE(k0.saldo_inicial, 0) AS saldo_inicial,
              COALESCE(k0.valor_inicial, 0) AS valor_saldo_inicial,
              COALESCE(v.cantidad_variantes, 0) AS cantidad_variantes
            FROM articulo a
            LEFT JOIN categoria c ON c.idcategoria = a.idcategoria
            LEFT JOIN subcategoria sc ON sc.idsubcategoria = a.idsubcategoria
            LEFT JOIN almacen al ON al.idalmacen = a.idalmacen
            LEFT JOIN (
              SELECT
                k.idarticulo,
                COALESCE(SUM(k.cantidadi), 0) - COALESCE(SUM(k.cantidads), 0) AS saldo_inicial,
                COALESCE(SUM(k.totali), 0) - COALESCE(SUM(k.totals), 0) AS valor_inicial
              FROM kardex k
              WHERE k.estado = 'Activo'
                AND k.fecha < ?
              GROUP BY k.idarticulo
            ) k0 ON k0.idarticulo = a.idarticulo
            LEFT JOIN (
              SELECT
                di.idarticulo,
                SUM(di.cantidad) AS entradas,
                SUM(di.cantidad * di.precio_compra) AS valor_entradas
              FROM detalle_ingreso di
              INNER JOIN ingreso i ON i.idingreso = di.idingreso
              WHERE di.tipo_detalle = 'INVENTARIO'
                AND di.afecta_stock = 1
                AND di.estado = 1
                AND i.estado = 'Aceptado'
                AND DATE(i.fecha_hora) BETWEEN ? AND ?
              GROUP BY di.idarticulo
            ) e ON e.idarticulo = a.idarticulo
            LEFT JOIN (
              SELECT
                dv.idarticulo,
                SUM(dv.cantidad) AS salidas,
                SUM(dv.cantidad * dv.precio_compra) AS valor_salidas
              FROM detalle_venta dv
              INNER JOIN venta vt ON vt.idventa = dv.idventa
              WHERE dv.estado = 1
                AND vt.estado = 'Aceptado'
                AND LOWER(COALESCE(vt.tipo_comprobante, '')) NOT LIKE '%cotiz%'
                AND DATE(vt.fecha_hora) BETWEEN ? AND ?
              GROUP BY dv.idarticulo
            ) s ON s.idarticulo = a.idarticulo
            LEFT JOIN (
              SELECT
                di.idarticulo,
                SUM(GREATEST(COALESCE(di.stock_venta, 0), 0)) AS stock_fifo,
                SUM(
                  GREATEST(COALESCE(di.stock_venta, 0), 0)
                  * COALESCE(di.precio_compra, 0)
                ) AS valor_fifo
              FROM detalle_ingreso di
              INNER JOIN ingreso i ON i.idingreso = di.idingreso
              WHERE di.tipo_detalle = 'INVENTARIO'
                AND di.afecta_stock = 1
                AND di.estado = 1
                AND i.estado = 'Aceptado'
              GROUP BY di.idarticulo
            ) l ON l.idarticulo = a.idarticulo
            LEFT JOIN (
              SELECT idarticulo, COUNT(*) AS cantidad_variantes
              FROM articulo_variacion
              WHERE estado = 1
              GROUP BY idarticulo
            ) v ON v.idarticulo = a.idarticulo
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.nombre ASC, sc.nombre ASC, a.nombre ASC";

    return $this->conexion->getDataAll($sql, $params);
  }


  /**
   * Movimiento diario del inventario para el dashboard.
   * Usa las mismas fuentes del reporte valorizado para que los gráficos
   * cuadren con las entradas y salidas mostradas en los indicadores.
   */
  public function movimientosInventarioDiarios(
    $fechaInicio,
    $fechaFin,
    $idcategoria=0,
    $idsubcategoria=0,
    $idalmacen=0,
    $buscar=''
  ){
    $filtros = [];
    $paramsFiltro = [];

    if ((int)$idcategoria > 0) {
      $filtros[] = "a.idcategoria = ?";
      $paramsFiltro[] = (int)$idcategoria;
    }
    if ((int)$idsubcategoria > 0) {
      $filtros[] = "a.idsubcategoria = ?";
      $paramsFiltro[] = (int)$idsubcategoria;
    }
    if ((int)$idalmacen > 0) {
      $filtros[] = "a.idalmacen = ?";
      $paramsFiltro[] = (int)$idalmacen;
    }

    $buscar = trim((string)$buscar);
    if ($buscar !== '') {
      $filtros[] = "(a.nombre LIKE ? OR a.codigo LIKE ?)";
      $paramsFiltro[] = '%' . $buscar . '%';
      $paramsFiltro[] = '%' . $buscar . '%';
    }

    $filtroSql = count($filtros) ? ' AND ' . implode(' AND ', $filtros) : '';

    $sql = "SELECT
              m.fecha,
              SUM(m.entradas) AS entradas,
              SUM(m.salidas) AS salidas,
              SUM(m.valor_entradas) AS valor_entradas,
              SUM(m.valor_salidas) AS valor_salidas
            FROM (
              SELECT
                DATE(i.fecha_hora) AS fecha,
                SUM(di.cantidad) AS entradas,
                0 AS salidas,
                SUM(di.cantidad * di.precio_compra) AS valor_entradas,
                0 AS valor_salidas
              FROM detalle_ingreso di
              INNER JOIN ingreso i ON i.idingreso = di.idingreso
              INNER JOIN articulo a ON a.idarticulo = di.idarticulo
              WHERE di.tipo_detalle = 'INVENTARIO'
                AND di.afecta_stock = 1
                AND di.estado = 1
                AND i.estado = 'Aceptado'
                AND DATE(i.fecha_hora) BETWEEN ? AND ?"
                . $filtroSql .
              " GROUP BY DATE(i.fecha_hora)

              UNION ALL

              SELECT
                DATE(vt.fecha_hora) AS fecha,
                0 AS entradas,
                SUM(dv.cantidad) AS salidas,
                0 AS valor_entradas,
                SUM(dv.cantidad * dv.precio_compra) AS valor_salidas
              FROM detalle_venta dv
              INNER JOIN venta vt ON vt.idventa = dv.idventa
              INNER JOIN articulo a ON a.idarticulo = dv.idarticulo
              WHERE dv.estado = 1
                AND vt.estado = 'Aceptado'
                AND LOWER(COALESCE(vt.tipo_comprobante, '')) NOT LIKE '%cotiz%'
                AND DATE(vt.fecha_hora) BETWEEN ? AND ?"
                . $filtroSql .
              " GROUP BY DATE(vt.fecha_hora)
            ) m
            GROUP BY m.fecha
            ORDER BY m.fecha ASC";

    $params = array_merge(
      [$fechaInicio, $fechaFin],
      $paramsFiltro,
      [$fechaInicio, $fechaFin],
      $paramsFiltro
    );

    return $this->conexion->getDataAll($sql, $params);
  }

  /**
   * Kardex valorizado reconstruido con saldo acumulado.
   * No usa cantidadex/totalex históricos porque esos campos antiguos podían
   * representar solo el remanente de una capa FIFO y no el saldo global.
   */
  public function kardexValorizado($idarticulo, $fechaInicio, $fechaFin){
    $idarticulo = (int)$idarticulo;

    $producto = $this->conexion->getData(
      "SELECT
          a.idarticulo,
          COALESCE(a.codigo, '') AS codigo,
          a.nombre AS producto,
          COALESCE(a.stock, 0) AS saldo_actual,
          COALESCE(c.nombre, 'SIN CATEGORÍA') AS categoria,
          COALESCE(sc.nombre, 'SIN SUBCATEGORÍA') AS subcategoria,
          COALESCE(al.nombre, 'SIN ALMACÉN') AS almacen
       FROM articulo a
       LEFT JOIN categoria c ON c.idcategoria = a.idcategoria
       LEFT JOIN subcategoria sc ON sc.idsubcategoria = a.idsubcategoria
       LEFT JOIN almacen al ON al.idalmacen = a.idalmacen
       WHERE a.idarticulo = ?
       LIMIT 1",
      [$idarticulo]
    );

    if (!$producto) {
      return false;
    }

    $saldoAnterior = $this->conexion->getData(
      "SELECT
          COALESCE(SUM(cantidadi),0) - COALESCE(SUM(cantidads),0) AS cantidad,
          COALESCE(SUM(totali),0) - COALESCE(SUM(totals),0) AS valor
       FROM kardex
       WHERE idarticulo = ?
         AND estado = 'Activo'
         AND fecha < ?",
      [$idarticulo, $fechaInicio]
    );

    $movimientos = $this->conexion->getDataAll(
      "SELECT
          MIN(id) AS id,
          fecha,
          iddetalle,
          detalle,
          tipo,
          SUM(cantidadi) AS cantidadi,
          SUM(totali) AS totali,
          CASE WHEN SUM(cantidadi) > 0
               THEN SUM(totali) / SUM(cantidadi)
               ELSE 0 END AS costoui,
          SUM(cantidads) AS cantidads,
          SUM(totals) AS totals,
          CASE WHEN SUM(cantidads) > 0
               THEN SUM(totals) / SUM(cantidads)
               ELSE 0 END AS costous
       FROM kardex
       WHERE idarticulo = ?
         AND estado = 'Activo'
         AND fecha BETWEEN ? AND ?
       GROUP BY fecha, iddetalle, detalle, tipo
       ORDER BY fecha ASC, MIN(id) ASC",
      [$idarticulo, $fechaInicio, $fechaFin]
    );

    $fifoActual = $this->conexion->getData(
      "SELECT
          COALESCE(SUM(GREATEST(COALESCE(di.stock_venta,0),0)),0) AS stock_fifo,
          COALESCE(SUM(
            GREATEST(COALESCE(di.stock_venta,0),0) * COALESCE(di.precio_compra,0)
          ),0) AS valor_fifo
       FROM detalle_ingreso di
       INNER JOIN ingreso i ON i.idingreso = di.idingreso
       WHERE di.idarticulo = ?
         AND di.tipo_detalle = 'INVENTARIO'
         AND di.afecta_stock = 1
         AND di.estado = 1
         AND i.estado = 'Aceptado'",
      [$idarticulo]
    );

    $saldoCantidad = (float)($saldoAnterior['cantidad'] ?? 0);
    $saldoValor = (float)($saldoAnterior['valor'] ?? 0);
    $detalleMovimientos = [];

    foreach ($movimientos as $mov) {
      $entradaCantidad = (float)($mov['cantidadi'] ?? 0);
      $entradaValor = (float)($mov['totali'] ?? 0);
      $salidaCantidad = (float)($mov['cantidads'] ?? 0);
      $salidaValor = (float)($mov['totals'] ?? 0);

      $saldoCantidad += $entradaCantidad - $salidaCantidad;
      $saldoValor += $entradaValor - $salidaValor;

      if (abs($saldoCantidad) < 0.000001) {
        $saldoCantidad = 0;
      }
      if (abs($saldoValor) < 0.005) {
        $saldoValor = 0;
      }

      $detalleMovimientos[] = [
        'fecha' => $mov['fecha'],
        'detalle' => $mov['detalle'],
        'tipo' => $mov['tipo'],
        'entrada_cantidad' => $entradaCantidad,
        'entrada_costo' => (float)($mov['costoui'] ?? 0),
        'entrada_total' => $entradaValor,
        'salida_cantidad' => $salidaCantidad,
        'salida_costo' => (float)($mov['costous'] ?? 0),
        'salida_total' => $salidaValor,
        'saldo_cantidad' => $saldoCantidad,
        'saldo_costo' => $saldoCantidad > 0 ? ($saldoValor / $saldoCantidad) : 0,
        'saldo_total' => $saldoValor
      ];
    }

    $stockFifo = (float)($fifoActual['stock_fifo'] ?? 0);
    $valorFifo = (float)($fifoActual['valor_fifo'] ?? 0);

    return [
      'producto' => $producto,
      'saldo_inicial' => [
        'cantidad' => (float)($saldoAnterior['cantidad'] ?? 0),
        'valor' => (float)($saldoAnterior['valor'] ?? 0),
        'costo' => ((float)($saldoAnterior['cantidad'] ?? 0)) > 0
          ? ((float)($saldoAnterior['valor'] ?? 0) / (float)$saldoAnterior['cantidad'])
          : 0
      ],
      'actual' => [
        'saldo_fisico' => (float)($producto['saldo_actual'] ?? 0),
        'stock_fifo' => $stockFifo,
        'costo_promedio_fifo' => $stockFifo > 0 ? $valorFifo / $stockFifo : 0,
        'saldo_valorizado' => $valorFifo,
        'diferencia_stock' => (float)($producto['saldo_actual'] ?? 0) - $stockFifo
      ],
      'movimientos' => $detalleMovimientos
    ];
  }

}

 ?>
