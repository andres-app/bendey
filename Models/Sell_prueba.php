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
       /* $sql="INSERT INTO $this->tableName (idcliente,idusuario,tipo_comprobante,serie_comprobante,num_comprobante,fecha_hora,impuesto,total_venta,tipo_pago,num_transac,estado) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
        $arrData = array($idcliente,$idusuario,$tipo_comprobante,$serie_comprobante,$num_comprobante,$fecha_hora,$impuesto,$total_venta,$tipo_pago,$num_transac,'Aceptado');
        $idventanew= $this->conexion-> getReturnId($sql,$arrData);
        $detalle=$tipo_comprobante.' '.$serie_comprobante.'-'.$num_comprobante;
        $num_elementos=0;
        $sw=true;*/

        /*while ($num_elementos < count($idarticulo)) {
            //REGISTRO DE DATOS EN EL DETALLE DE VENTAS
            $sql_detalle="INSERT INTO $this->tableNameDetalle (idventa,idarticulo,cantidad,precio_compra,precio_venta,descuento,estado) VALUES(?,?,?,?,?,?,?)";
            $arrDatadet = array($idventanew,$idarticulo[$num_elementos],$cantidad[$num_elementos],$precio_compra[$num_elementos],$precio_venta[$num_elementos],$descuento[$num_elementos],'1');
            $this->conexion->setData($sql_detalle,$arrDatadet)or $sw=false;

            $num_elementos=$num_elementos+1;
        }*/

        //ACTUALIZAR STOCK DESPUES DE REALIZAR UNA VENTA
       /* $sql_stock="SELECT idarticulo, cantidad FROM $this->tableNameDetalle WHERE idventa='$idventanew'";
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
        $sw=true;*/
//$i=5;
/*do {
 echo $i;
}while($i>0);*/
$elementos=0;
$articulos=[1];
$cantidad=[2];
$stockVenta=2;
while($elementos<count($articulos)){
    //echo $articulos.'</br>';


$restante=$cantidad[$elementos];

echo 'Empezando while PASO 1</br>';
echo 'Cantidad venta '.$restante.' Stock disponible '.$stockVenta.'</br>';

if($restante<$stockVenta){
    echo 'Dentro de, si es menor a stock a vender PASO 2</br>';
echo 'Cantidad venta '.$restante.' Stock disponible '.$stockVenta.'</br>';
    $stockVenta=$stockVenta-$restante;
    //echo $stockVenta.'stock venta</br>';

}else{
    $a=0;
    do{
                        $a=$a+$restante;
        if($a <= $restante){
        echo 'dentro de do while PASO 3</br>';
echo 'Cantidad venta '.$restante.' Stock disponible '.$stockVenta.'</br>';

        if($restante<$stockVenta){
            echo 'Dentro de do while, si es menor a stock a vender PASO 4</br>';
echo 'Cantidad venta '.$restante.' Stock disponible '.$stockVenta.'</br>';
            //echo $stockVenta.'stock venta</br>';

        }elseif($restante==$stockVenta){
                        echo 'Dentro de do while, cantidad a vender es igual a sotck disponible PASO 5</br>';
echo 'Cantidad venta '.$restante.' Stock disponible '.$stockVenta.'</br>';

$cant_restante=$cant_restante-$stVenta;
            
        }elseif($restante>$stockVenta){
                                    echo 'Dentro de do while, cantidad a vender es mayor a sotck disponible PASO 6</br>';
echo 'Cantidad venta '.$restante.' Stock disponible '.$stockVenta.'</br>';
        }

      //  $a=$a+$restante;
        //echo 'aa: '.$a;
    }else{

        echo 'aa: '.$a;
        echo 'nada ';
    }

        //$a <= $cant_restante 2
    }while($a <= $restante);
    //while($stockVenta>$restante);
}

    $elementos++;
}

        /*while ($num_elementos < count($idarticulo)) {
            //ID DETALLE INGRESO ANTIGUO 1 :CANTIDAD VENDIDO 4
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

                    $a=$a+$cant_restante;
                } while ($a <= $cant_restante);

            }
            
            $num_elementos=$num_elementos+1;
        }*/

        //return $sw;
    }
}

?>
