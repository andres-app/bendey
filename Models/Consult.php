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
$sql="SELECT * FROM kardex WHERE idarticulo='$idarticulo' AND estado='Activo' ORDER BY fecha DESC";
    return  $this->conexion->getDataAll($sql);
}



}

 ?>
