<?php 
//incluir la conexion de base de datos
//incluir la conexion de base de datos
require_once "Connect.php";
class Sell{

    private $tableName='venta';
    private $tableNameDetalle='detalle_venta';
    private $tableNameKardex='kardex';
    private $conexion;

	//implementamos nuestro constructor
	public function __construct(){
		$this->conexion = new Conexion();
	}

    //metodo insertar registro
    public function insertar($idcliente,$idusuario,$tipo_comprobante,$serie_comprobante,$num_comprobante,$impuesto,$total_venta,$tipo_pago,$num_transac,$idingreso,$idarticulo,$cantidad,$precio_compra,$precio_venta,$descuento){
        date_default_timezone_set('America/Lima');
        $fecha_hora = date("Y-m-d");
        $sql="INSERT INTO $this->tableName (idcliente,idusuario,tipo_comprobante,serie_comprobante,num_comprobante,fecha_hora,impuesto,total_venta,tipo_pago,num_transac,estado) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
        $arrData = array($idcliente,$idusuario,$tipo_comprobante,$serie_comprobante,$num_comprobante,$fecha_hora,$impuesto,$total_venta,$tipo_pago,$num_transac,'Aceptado');
        $idventanew= $this->conexion-> getReturnId($sql,$arrData);
        $detalle=$tipo_comprobante.' '.$serie_comprobante.'-'.$num_comprobante;
        $num_elementos=0;
        $sw=true;

        while ($num_elementos < count($idarticulo)) {
            //REGISTRO DE DATOS EN EL DETALLE DE VENTAS
            $sql_detalle="INSERT INTO $this->tableNameDetalle (idventa,idarticulo,cantidad,precio_compra,precio_venta,descuento,estado) VALUES(?,?,?,?,?,?,?)";
            $arrDatadet = array($idventanew,$idarticulo[$num_elementos],$cantidad[$num_elementos],$precio_compra[$num_elementos],$precio_venta[$num_elementos],$descuento[$num_elementos],'1');
            $this->conexion->setData($sql_detalle,$arrDatadet)or $sw=false;

            $num_elementos=$num_elementos+1;
        }

        //ACTUALIZAR STOCK DESPUES DE REALIZAR UNA VENTA
        $sql_stock="SELECT idarticulo, cantidad FROM $this->tableNameDetalle WHERE idventa='$idventanew'";
        $res= $this->conexion->getDataAll($sql_stock);
        $idart=0;
        foreach($res as $reg){
            $cantidad[$idart] = isset($reg['cantidad'])? $cantidad[$idart]=$reg['cantidad']:null;
            $idarticulo[$idart] = isset($reg['idarticulo'])? $idarticulo[$idart]=$reg['idarticulo']:null;
            $sql_detalle="UPDATE articulo SET stock= stock-'$cantidad[$idart]' WHERE idarticulo=?";
            //ejecutarConsulta($sql_detalle) or $sw=false;
            $arrData=array($idarticulo[$idart]);
            $this->conexion-> setData($sql_detalle,$arrData) or $sw=false;
            $idart= $idart+1;

        }

        $num_elementos=0;
        $sw=true;

        while ($num_elementos < count($idarticulo)) {
            //ID DETALLE INGRESO ANTIGUO 1 :CANTIDAD VENDIDO 4
            //echo 'PASO 1';
            $sqlIdViejo="SELECT iddetalle_ingreso FROM detalle_ingreso WHERE idarticulo=? AND stock_estado='1' ORDER BY iddetalle_ingreso ASC LIMIT 0,1";
            $arrDataViejo = array($idarticulo[$num_elementos]);
		    $idIn= $this->conexion->getData($sqlIdViejo,$arrDataViejo);
            $idViejo = isset($idIn['iddetalle_ingreso'])? $idViejo=$idIn['iddetalle_ingreso']:null;
            //$idViejo= $idIn['iddetalle_ingreso'];

            //STOCK ANTIGUO SEGUN IDINGRESO ANTIGUO 10 :STOCK PARA VENDER 4
            $sqlStockViejo="SELECT stock_venta, precio_compra FROM detalle_ingreso WHERE iddetalle_ingreso=?";
            $arrDataViejoStock = array($idViejo);
		    $stockVenta= $this->conexion->getData($sqlStockViejo,$arrDataViejoStock);
            (int)$stVenta = isset($stockVenta['stock_venta'])? (int)$stVenta=$stockVenta['stock_venta']:null;
            $stPrecioCompra = isset($stockVenta['precio_compra'])? $stPrecioCompra=$stockVenta['precio_compra']:null;
            //$stVenta=$stockVenta['stock_venta'];
            //$stPrecioCompra=$stockVenta['precio_compra'];

            //RESTANTE 4 :STOCK A VENDER 4
            $cant_restante=(int)$cantidad[$num_elementos]; 
            //echo $cant_restante[$num_elementos];
            if($cant_restante<$stVenta){
               // echo 'menor '.$cant_restante;
                $sql_update="UPDATE detalle_ingreso SET stock_venta=stock_venta-'$cant_restante' WHERE iddetalle_ingreso=?";
                $arrUpdate=array($idViejo);
                $this->conexion->setData($sql_update,$arrUpdate) or $sw=false;


                //DATOS PARA EL KARDEX
                //t-cantidad=1-3
                $t_cantidad=$stVenta-$cant_restante;
                $totalKardex=$cant_restante*$stPrecioCompra;
                $totalex=$t_cantidad*$stPrecioCompra;
                $sql_kardex="INSERT INTO $this->tableNameKardex (iddetalle,idarticulo,fecha,detalle,cantidads,costous,totals,cantidadex,costouex,totalex,tipo,estado) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)";
                $arrKardex = array($idventanew ,$idarticulo[$num_elementos],$fecha_hora,$detalle,$cant_restante,$stPrecioCompra,$totalKardex,$t_cantidad,$stPrecioCompra,$totalex,'Salida','Activo');
                $this->conexion->setData($sql_kardex,$arrKardex)or $sw=false;

            }else{
                //RESTANTE 3 :STOCK A VENDER 1
                $a=0;
                do {
                    $a=$a+$cant_restante;
                    if($a <= $cant_restante){
                    // echo $cant_restante;
                    //ID INGRESO ANTIGUO 1  2
                    $sqlIdViejo="SELECT iddetalle_ingreso FROM detalle_ingreso WHERE idarticulo=? AND stock_estado='1' ORDER BY iddetalle_ingreso ASC LIMIT 0,1";
                    $arrDataViejo = array($idarticulo[$num_elementos]);
                    $idIn= $this->conexion->getData($sqlIdViejo,$arrDataViejo);
                    $idViejo = isset($idIn['iddetalle_ingreso'])? $idViejo=$idIn['iddetalle_ingreso']:null;
                    //$idViejo= $idIn['iddetalle_ingreso'];

                    //STOCK ANTIGUO SEGUN IDINGRESO ANTIGUO 10  20 :STOCK A VENDER 1
                    $sqlStockViejo="SELECT stock_venta, precio_compra FROM detalle_ingreso WHERE iddetalle_ingreso=?";
                    $arrDataViejoStock = array($idViejo);
                    $stockVenta= $this->conexion->getData($sqlStockViejo,$arrDataViejoStock);
                    (int)$stVenta = isset($stockVenta['stock_venta'])? (int)$stVenta=$stockVenta['stock_venta']:null;
                    $stPrecioCompra = isset($stockVenta['precio_compra'])? $stPrecioCompra=$stockVenta['precio_compra']:null;
                    //$stVenta=$stockVenta['stock_venta'];
                   // $stPrecioCompra=$stockVenta['precio_compra'];
                    //echo $stVenta;
                    //RESTANTE 3 :STOCK A VENDER 1  
                    if($cant_restante<$stVenta){ 
                    // echo 'menor '.$cant_restante;
                        $sql_update="UPDATE detalle_ingreso SET stock_venta=stock_venta-'$cant_restante' WHERE iddetalle_ingreso=?";
                        $arrUpdate=array($idViejo);
                        $this->conexion->setData($sql_update,$arrUpdate) or $sw=false;

                        //DATOS PARA EL KARDEX
                        $t_cantidad=$stVenta-$cant_restante;
                        $totalKardex=$cant_restante*$stPrecioCompra;
                        $totalex=$t_cantidad*$stPrecioCompra;
                        $sql_kardex="INSERT INTO $this->tableNameKardex (iddetalle,idarticulo,fecha,detalle,cantidads,costous,totals,cantidadex,costouex,totalex,tipo,estado) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)";
                        $arrKardex = array($idventanew ,$idarticulo[$num_elementos],$fecha_hora,$detalle,$cant_restante,$stPrecioCompra,$totalKardex,$t_cantidad,$stPrecioCompra,$totalex,'Salida','Activo');
                        $this->conexion->setData($sql_kardex,$arrKardex)or $sw=false;
                    //RESTANTE 3 :STOCK A VENDER 1
                    }elseif($cant_restante==$stVenta){

                        //echo 'restante: '.$cant_restante;
                        echo 'stock: '.$stVenta;
                        $sql_update="UPDATE detalle_ingreso SET stock_venta='0',stock_estado='0' WHERE iddetalle_ingreso=?";
                        $arrUpdate=array($idViejo);
                        $this->conexion->setData($sql_update,$arrUpdate) or $sw=false;
                        $cant_restante=$cant_restante-$stVenta;
                        //DATOS PARA EL KARDEX
                        if(empty($stVenta)){
                        $t_cantidad=(int)$stVenta-(int)$cant_restante;
                        //echo $t_cantidad;
                        $totalKardex=$cant_restante*$stPrecioCompra;
                        $totalex=$t_cantidad*$stPrecioCompra;
                        $costus=$stPrecioCompra*$t_cantidad;
                        //echo 'stock disponible: '. $stVenta;
                        $sql_kardex="INSERT INTO $this->tableNameKardex (iddetalle,idarticulo,fecha,detalle,cantidads,costous,totals,cantidadex,costouex,totalex,tipo,estado) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)";
                        $arrKardex = array($idventanew ,$idarticulo[$num_elementos],$fecha_hora,$detalle,$cant_restante,$stPrecioCompra,$totalKardex,$t_cantidad,$costus,$totalex,'Salida','Activo');
                        $this->conexion->setData($sql_kardex,$arrKardex)or $sw=false;}

                    //RESTANTE 3 :STOCK A VENDER 1
                    }elseif($cant_restante>$stVenta){
                        //CANTIDAD RESTANTE 4 SOTCK VENTA  0
                       // echo 'mayor '.$cant_restante;
                        $sql_update="UPDATE detalle_ingreso SET stock_venta='0',stock_estado='0' WHERE iddetalle_ingreso=?";
                        $arrUpdate=array($idViejo);
                        $this->conexion->setData($sql_update,$arrUpdate) or $sw=false;

                        $cant_restante=$cant_restante-$stVenta;
                        //3-1
                        //DATOS PARA EL KARDEX
                        //t-canditad=1-3 === -2
                        $t_cantidad=$stVenta-$stVenta;
                       // $cant_res=$cantidad[$num_elementos]-$t_cantidad;
                        //echo $cant_restante;
                        $totalKardex=$stVenta*$stPrecioCompra;
                        $totalex=$t_cantidad*$stPrecioCompra;
                        $costus=$stPrecioCompra*$t_cantidad;
                        // echo 'mayor '.$cant_restante;
                        $sql_kardex="INSERT INTO $this->tableNameKardex (iddetalle,idarticulo,fecha,detalle,cantidads,costous,totals,cantidadex,costouex,totalex,tipo,estado) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)";
                        $arrKardex = array($idventanew ,$idarticulo[$num_elementos],$fecha_hora,$detalle,$stVenta,$stPrecioCompra,$totalKardex,$t_cantidad,$costus,$totalex,'Salida','Activo');
                        $this->conexion->setData($sql_kardex,$arrKardex)or $sw=false;

                      //  $cant_restante=$cant_restante-$stVenta;



                    }

                    }


                    //$a=$a+$cant_restante;
                } while ($a <= $cant_restante);

            }
            
            $num_elementos=$num_elementos+1;
        }



        /*$elementos=0;
        while ($elementos < count($idarticulo)) {

        //ID DETALLE INGRESO ANTIGUO 1
        $sqlIdViejo="SELECT iddetalle_ingreso FROM detalle_ingreso WHERE idarticulo=? AND stock_estado='1' ORDER BY iddetalle_ingreso ASC LIMIT 0,1";
        $arrDataViejo = array($idarticulo[$elementos]);
		$idIn= $this->conexion->getData($sqlIdViejo,$arrDataViejo);
        $idViejo= $idIn['iddetalle_ingreso'];

        //STOCK ANTIGUO SEGUN IDINGRESO ANTIGUO 10  20
        $sqlStockViejo="SELECT stock_venta FROM detalle_ingreso WHERE iddetalle_ingreso=?";
        $arrDataViejoStock = array($idViejo);
        $stockVenta= $this->conexion->getData($sqlStockViejo,$arrDataViejoStock);
        $stVenta=$stockVenta['stock_venta'];

        //DATOS PARA EL KARDEX
        $totalKardex=$cantidad[$elementos]*$precio_compra[$elementos];
        $totalex=$stVenta*$precio_compra[$elementos];
        $sql_kardex="INSERT INTO $this->tableNameKardex (iddetalle,idarticulo,fecha,detalle,cantidads,costous,totals,cantidadex,costouex,totalex,tipo,estado) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)";
        $arrKardex = array($idventanew ,$idarticulo[$elementos],$fecha_hora,$detalle,$cantidad[$elementos],$precio_compra[$elementos],$totalKardex,$stVenta,$precio_compra[$elementos],$totalex,'Salida','Activo');
        $this->conexion->setData($sql_kardex,$arrKardex)or $sw=false;
        $elementos=$elementos+1;
        }*/




        return $sw;
    }










    //FUNCION PARA EDITAR
    public function editar($idventa,$idcliente,$tipo_comprobante,$serie_comprobante,$num_comprobante,$impuesto,$total_venta,$tipo_pago,$num_transac,$idarticulo,$nuevostock,$cantidad,$precio_compra,$precio_venta,$descuento){
        $sw=true;
        $sql="UPDATE $this->tableName SET idcliente=?, tipo_comprobante=?, serie_comprobante=?, num_comprobante=?, impuesto=?, total_venta=?, tipo_pago=?, num_transac=? WHERE idventa=?";

        $arrData = array($idcliente,$tipo_comprobante,$serie_comprobante,$num_comprobante,$impuesto,$total_venta,$tipo_pago,$num_transac,$idventa);
        $this->conexion-> setData($sql,$arrData) or $sw=false;


        //ELIMINAR DATOS DE DETALLE DE INGRESO
        $sql_del="DELETE FROM $this->tableNameDetalle WHERE idventa=?";
        $arrDataDel = array($idventa);
        $this->conexion-> setData($sql_del,$arrDataDel) or $sw=false;

        $num_elementos=0;
        while ($num_elementos < count($idarticulo)) {

            $sql_detalle="INSERT INTO $this->tableNameDetalle (idventa,idarticulo,cantidad,precio_venta,descuento,estado) VALUES(?,?,?,?,?,?)";
            $arrDatadet = array($idventa,$idarticulo[$num_elementos],$cantidad[$num_elementos],$precio_venta[$num_elementos],$descuento[$num_elementos],'1');
            $this->conexion->setData($sql_detalle,$arrDatadet)or $sw=false;

            $num_elementos=$num_elementos+1;
        }
        //ACTUALIZAR STOCK DESPUES DE EDITAR UNA VENTA
        $sql_stock="SELECT idarticulo, cantidad FROM $this->tableNameDetalle WHERE idventa='$idventa'";
        $res= $this->conexion->getDataAll($sql_stock);
        $idart=0;
        foreach($res as $reg){
            //$cantidad[$idart] = isset($reg['cantidad'])? $cantidad[$idart]=$reg['cantidad']:null;
            $idarticulo[$idart] = isset($reg['idarticulo'])? $idarticulo[$idart]=$reg['idarticulo']:null;
            $sql_detalle="UPDATE articulo SET stock= stock+'$nuevostock[$idart]' WHERE idarticulo=?";
            //ejecutarConsulta($sql_detalle) or $sw=false;
            $arrData=array($idarticulo[$idart]);
            $this->conexion-> setData($sql_detalle,$arrData) or $sw=false;
            $idart= $idart+1;

        }

        //ACTUALIZAR EL KARDEX
        date_default_timezone_set('America/Lima');
        $fecha_hora = date("Y-m-d");
        $detalle=$tipo_comprobante.' '.$serie_comprobante.'-'.$num_comprobante;
        //ELIMINAR DATOS DE DETALLE DE INGRESO
        $sql_del="DELETE FROM $this->tableNameKardex WHERE iddetalle=? AND tipo=?";
        $arrDataDel = array($idventa,'Salida');
        $this->conexion-> setData($sql_del,$arrDataDel) or $sw=false;
        //INGRESAR DATOS PARA EL KARDEX 
        $elementos=0;
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
        }

        return $sw;
    }

    public function anular($idventa){
        $sw=true; 
        $sql="UPDATE $this->tableName SET estado='Anulado' WHERE idventa=?";
        $arrData=array($idventa);
        $this->conexion->setData($sql,$arrData);
        $sql_detalle="UPDATE $this->tableNameDetalle SET estado='0' WHERE idventa=?"; 	
        $arrDataDetalle=array($idventa);
        $this->conexion->setData($sql_detalle,$arrDataDetalle) or $sw=false;

        //ACTUALIZAR STOCK DESPUES DE ANULAR UNA VENTA
        $sql_stock="SELECT idarticulo, cantidad FROM $this->tableNameDetalle WHERE idventa='$idventa'";
        $res= $this->conexion->getDataAll($sql_stock);
        $idart=0;
        foreach($res as $reg){
            $cantidad[$idart] = isset($reg['cantidad'])? $cantidad[$idart]=$reg['cantidad']:null;
            $idarticulo[$idart] = isset($reg['idarticulo'])? $idarticulo[$idart]=$reg['idarticulo']:null;
            $sql_detalle="UPDATE articulo SET stock= stock+'$cantidad[$idart]' WHERE idarticulo=?";
            //ejecutarConsulta($sql_detalle) or $sw=false;
            $arrData=array($idarticulo[$idart]);
            $this->conexion-> setData($sql_detalle,$arrData) or $sw=false;
            $idart= $idart+1;

        }

        //ACTUALIZAR KARDEX
        $sql_k="SELECT * FROM kardex WHERE iddetalle='$idventa' AND tipo='Salida'";
        $resk= $this->conexion->getDataAll($sql_k);
        $idart=0;
        foreach($resk as $reg){
            //ACTUALIZAR KARDEX
            $sql_kardex="UPDATE kardex SET estado= 'Anulado' WHERE iddetalle=? AND idarticulo=? AND tipo=?";
            //ejecutarConsulta($sql_kardex) or $sw=false;
            $arrDataKardex=array($idventa,$idarticulo[$idart],'Salida');
            $this->conexion-> setData($sql_kardex,$arrDataKardex) or $sw=false;
            //echo $idarticulo[$idart];
            $idart= $idart+1;

        }        
        return $sw;
    }

    //implementar un metodopara mostrar los datos de unregistro a modificar
    public function mostrar($idventa){
       $sql="SELECT v.idventa,DATE(v.fecha_hora) as fecha,v.idcliente,p.nombre as cliente,u.idusuario,u.nombre as usuario, v.tipo_comprobante,v.serie_comprobante,v.num_comprobante,v.total_venta,v.impuesto,v.estado FROM $this->tableName v INNER JOIN persona p ON v.idcliente=p.idpersona INNER JOIN usuario u ON v.idusuario=u.idusuario WHERE idventa=?";
		$arrData = array($idventa);
		return  $this->conexion->getData($sql,$arrData); 
       /* $articulo=15;
            $sqlIdViejo="SELECT idingreso FROM detalle_ingreso WHERE idarticulo=? AND stock_estado='1' ORDER BY iddetalle_ingreso ASC LIMIT 0,1";
            		$arrDataViejo = array($articulo);
		$idIn= $this->conexion->getData($sqlIdViejo,$arrDataViejo);
        return $idIn['idingreso'];*/
    }

    public function listarDetalle($idventa){
        $sql="SELECT dv.idventa,dv.idarticulo,a.nombre,a.stock, dv.cantidad,dv.precio_compra,dv.precio_venta,dv.descuento,(dv.cantidad*dv.precio_venta-dv.descuento) as subtotal, v.total_venta, v.impuesto FROM detalle_venta dv INNER JOIN articulo a ON dv.idarticulo=a.idarticulo INNER JOIN venta v ON v.idventa=dv.idventa WHERE dv.idventa='$idventa'"; 
		return  $this->conexion->getDataAll($sql); 
    }

    //listar registros
    public function listar(){
        $sql="SELECT v.idventa,DATE(v.fecha_hora) as fecha,v.idcliente,p.nombre as cliente,u.idusuario,u.nombre as usuario, v.tipo_comprobante,v.serie_comprobante,v.num_comprobante,v.total_venta,v.impuesto,v.estado FROM $this->tableName v INNER JOIN persona p ON v.idcliente=p.idpersona INNER JOIN usuario u ON v.idusuario=u.idusuario ORDER BY v.idventa DESC";
		return  $this->conexion->getDataAll($sql); 
    }


    public function ventacabecera($idventa){
        $sql= "SELECT v.estado, v.idventa, v.idcliente, p.nombre AS cliente, p.direccion, p.tipo_documento, p.num_documento, p.email, p.telefono, v.idusuario, u.nombre AS usuario, v.tipo_comprobante, v.serie_comprobante, v.num_comprobante, DATE(v.fecha_hora) AS fecha, v.impuesto, v.total_venta FROM $this->tableName v INNER JOIN persona p ON v.idcliente=p.idpersona INNER JOIN usuario u ON v.idusuario=u.idusuario WHERE v.idventa='$idventa'";
		return  $this->conexion->getDataAll($sql); 
    }

    public function ventadetalles($idventa){
        $sql="SELECT a.nombre AS articulo, a.codigo, d.cantidad, d.precio_venta, d.descuento, (d.cantidad*d.precio_venta-d.descuento) AS subtotal FROM $this->tableNameDetalle d INNER JOIN articulo a ON d.idarticulo=a.idarticulo WHERE d.idventa='$idventa'";
		return  $this->conexion->getDataAll($sql); 
    }



    //funcion para selecciolnar el numero de factura
    public function numero_venta($tipo_comprobante){ 
            
        $sql="SELECT num_comprobante FROM $this->tableName WHERE tipo_comprobante='$tipo_comprobante' ORDER BY idventa DESC limit 1 ";
		return  $this->conexion->getDataAll($sql); 
            
    }
    //funcion para seleccionar la serie de la factura
    public function numero_serie($tipo_comprobante){

        $sql="SELECT serie_comprobante ,num_comprobante FROM $this->tableName WHERE tipo_comprobante='$tipo_comprobante' ORDER BY idventa DESC limit 1";

		return  $this->conexion->getDataAll($sql); 
    } 

}

 ?>
