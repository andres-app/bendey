<?php
/** Gestión de tarifas por producto, sin alterar precios históricos. */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../Config/Conexion.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
function salidaTarifa($data, int $code = 200) { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
if (empty($_SESSION['idusuario']) && empty($_SESSION['nombre'])) salidaTarifa(['success'=>false,'mensaje'=>'Sesión expirada'],401);
$op = $_GET['op'] ?? '';
if (!in_array($op,['listar','guardar','eliminar','reporte','asignarCliente','listarCliente','catalogos','eliminarCliente','modo','listarModos'],true)) salidaTarifa(['success'=>false,'mensaje'=>'Operación inválida'],400);
try {
    $db = Conexion::conectar();
    $id = filter_var($_REQUEST['idarticulo'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    if ($op === 'listarModos') {
        $q=$db->query('SELECT idarticulo,idvariacion,usa_lista FROM producto_precio_modo');
        salidaTarifa(['success'=>true,'data'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
    }
    if ($op === 'catalogos') {
        // Consultas separadas: evita errores de UNION/ORDER BY entre motores MariaDB y MySQL.
        $clientes = $db->query("SELECT idpersona, nombre, num_documento FROM persona WHERE TRIM(LOWER(tipo_persona))='cliente' ORDER BY nombre LIMIT 5000")->fetchAll(PDO::FETCH_ASSOC);
        $productos = $db->query("SELECT idarticulo, nombre, codigo, precio_venta, 0 AS idvariacion FROM articulo WHERE condicion=1 ORDER BY nombre LIMIT 5000")->fetchAll(PDO::FETCH_ASSOC);
        $variaciones = $db->query("SELECT a.idarticulo, CONCAT(a.nombre,' / ',COALESCE(NULLIF(v.combinacion,''),v.sku,'Variación')) AS nombre, v.sku AS codigo, v.precio_venta, v.idvariacion FROM articulo_variacion v INNER JOIN articulo a ON a.idarticulo=v.idarticulo WHERE a.condicion=1 AND v.estado=1 ORDER BY a.nombre, v.idvariacion LIMIT 5000")->fetchAll(PDO::FETCH_ASSOC);
        salidaTarifa(['success'=>true,'clientes'=>$clientes,'productos'=>array_merge($productos,$variaciones)]);
    }
    if ($op === 'reporte') {
        $rows = $db->query("SELECT a.codigo,a.nombre AS producto,a.precio_venta AS precio_base,l.nombre AS lista,l.precio,l.idvariacion, ROUND(l.precio-a.precio_compra,2) AS utilidad FROM producto_lista_precio l JOIN articulo a ON a.idarticulo=l.idarticulo ORDER BY a.nombre,l.nombre")->fetchAll(PDO::FETCH_ASSOC);
        salidaTarifa(['success'=>true,'data'=>$rows]);
    }
    if ($op === 'listarCliente') {
        $cliente = (int)($_GET['idcliente'] ?? 0);
        $q=$db->prepare('SELECT idcliente,idarticulo,COALESCE(idvariacion,0) idvariacion,precio FROM cliente_precio_especial WHERE idcliente=? AND activo=1');$q->execute([$cliente]);
        salidaTarifa(['success'=>true,'data'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !in_array($op,['listar'],true)) salidaTarifa(['success'=>false,'mensaje'=>'Método no permitido'],405);
    if ($id<1) salidaTarifa(['success'=>false,'mensaje'=>'Producto inválido'],422);
    $chk=$db->prepare('SELECT idarticulo FROM articulo WHERE idarticulo=?');$chk->execute([$id]);
    if (!$chk->fetchColumn()) salidaTarifa(['success'=>false,'mensaje'=>'Producto inexistente'],404);
    if ($op==='modo') {
        $variant=(int)($_POST['idvariacion']??0);$use=(int)($_POST['usa_lista']??0)===1?1:0;
        if ($variant<0) salidaTarifa(['success'=>false,'mensaje'=>'Variación inválida'],422);
        if ($variant) { $v=$db->prepare('SELECT idvariacion FROM articulo_variacion WHERE idarticulo=? AND idvariacion=?');$v->execute([$id,$variant]);if(!$v->fetchColumn()) salidaTarifa(['success'=>false,'mensaje'=>'Variación no pertenece al producto'],422); }
        if ($use) { $v=$db->prepare('SELECT COUNT(*) FROM producto_lista_precio WHERE idarticulo=? AND idvariacion=?');$v->execute([$id,$variant]);if(!(int)$v->fetchColumn()) salidaTarifa(['success'=>false,'mensaje'=>'Agrega al menos un precio antes de activar la lista'],422); }
        $q=$db->prepare('INSERT INTO producto_precio_modo(idarticulo,idvariacion,usa_lista) VALUES(?,?,?) ON DUPLICATE KEY UPDATE usa_lista=VALUES(usa_lista)');$q->execute([$id,$variant,$use]);
        salidaTarifa(['success'=>true,'mensaje'=>'Modo actualizado']);
    }
    if ($op==='listar') {
        $q=$db->prepare('SELECT idlista,idarticulo,idvariacion,nombre,precio,predeterminado FROM producto_lista_precio WHERE idarticulo=? ORDER BY idvariacion,predeterminado DESC,nombre');$q->execute([$id]);
        $rows=$q->fetchAll(PDO::FETCH_ASSOC);
        $m=$db->prepare('SELECT idvariacion,usa_lista FROM producto_precio_modo WHERE idarticulo=?');$m->execute([$id]);
        salidaTarifa(['success'=>true,'data'=>$rows,'modos'=>$m->fetchAll(PDO::FETCH_ASSOC)]);
    }
    if ($op==='asignarCliente' || $op==='eliminarCliente') {
        $cliente=(int)($_POST['idcliente']??0); $variacion=(int)($_POST['idvariacion']??0);
        $q=$db->prepare("SELECT idpersona FROM persona WHERE idpersona=? AND tipo_persona='Cliente'");$q->execute([$cliente]);
        if (!$q->fetchColumn() || $variacion<0) salidaTarifa(['success'=>false,'mensaje'=>'Cliente inválido'],422);
        if ($variacion) {$q=$db->prepare('SELECT idvariacion FROM articulo_variacion WHERE idvariacion=? AND idarticulo=?');$q->execute([$variacion,$id]);if(!$q->fetchColumn())salidaTarifa(['success'=>false,'mensaje'=>'Variación inválida'],422);}
        $price=filter_var($_POST['precio']??null,FILTER_VALIDATE_FLOAT);
        if ($op==='asignarCliente' && ($price===false || !is_finite((float)$price) || $price<=0 || $price>999999999)) salidaTarifa(['success'=>false,'mensaje'=>'Precio inválido'],422);
        $db->beginTransaction();
        $q=$db->prepare('SELECT idprecio FROM cliente_precio_especial WHERE idcliente=? AND idarticulo=? AND COALESCE(idvariacion,0)=? ORDER BY actualizado_en DESC,idprecio DESC LIMIT 1 FOR UPDATE');$q->execute([$cliente,$id,$variacion]);
        $existing=(int)$q->fetchColumn();
        if ($op==='asignarCliente') {
            if ($existing) {$q=$db->prepare('UPDATE cliente_precio_especial SET precio=?,activo=1 WHERE idprecio=?');$q->execute([(float)$price,$existing]);}
            else {$q=$db->prepare('INSERT INTO cliente_precio_especial(idcliente,idarticulo,idvariacion,precio,activo) VALUES(?,?,?,?,1)');$q->execute([$cliente,$id,$variacion,(float)$price]);}
        } else {
            $q=$db->prepare('UPDATE cliente_precio_especial SET activo=0 WHERE idcliente=? AND idarticulo=? AND COALESCE(idvariacion,0)=?');$q->execute([$cliente,$id,$variacion]);
        }
        $db->commit(); salidaTarifa(['success'=>true,'mensaje'=>'Acuerdo actualizado']);
    }
    $idlista = (int)($_POST['idlista'] ?? 0);
    if ($op==='eliminar') {
        $q=$db->prepare('DELETE FROM producto_lista_precio WHERE idlista=? AND idarticulo=?');$q->execute([$idlista,$id]);
        salidaTarifa(['success'=>true,'mensaje'=>'Precio eliminado']);
    }
    $nombre=trim((string)($_POST['nombre']??''));
    $raw=(string)($_POST['precio']??'');
    $precio=filter_var($raw,FILTER_VALIDATE_FLOAT);
    $idvariacion=(int)($_POST['idvariacion']??0);
    $default=(int)($_POST['predeterminado']??0)===1 ? 1:0;
    if ($nombre==='' || mb_strlen($nombre)>80 || $precio===false || !is_finite((float)$precio) || $precio<=0 || $precio>999999999 || $idvariacion<0) salidaTarifa(['success'=>false,'mensaje'=>'Verifica nombre y precio positivo'],422);
    if ($idvariacion) {
        $check=$db->prepare('SELECT idvariacion FROM articulo_variacion WHERE idvariacion=? AND idarticulo=?');$check->execute([$idvariacion,$id]);
        if (!$check->fetchColumn()) salidaTarifa(['success'=>false,'mensaje'=>'Variación inválida'],422);
    }
    $db->beginTransaction();
    if ($default) { $q=$db->prepare('UPDATE producto_lista_precio SET predeterminado=0 WHERE idarticulo=? AND idvariacion=?');$q->execute([$id,$idvariacion]); }
    if ($idlista) {
        $q=$db->prepare('UPDATE producto_lista_precio SET nombre=?,precio=?,predeterminado=? WHERE idlista=? AND idarticulo=? AND idvariacion=?');
        $q->execute([$nombre,$precio,$default,$idlista,$id,$idvariacion]);
        if (!$q->rowCount()) { $c=$db->prepare('SELECT idlista FROM producto_lista_precio WHERE idlista=? AND idarticulo=? AND idvariacion=?');$c->execute([$idlista,$id,$idvariacion]);if (!$c->fetchColumn()) throw new RuntimeException('Precio no encontrado en esta variación'); }
    } else {
        $q=$db->prepare('INSERT INTO producto_lista_precio(idarticulo,idvariacion,nombre,precio,predeterminado) VALUES(?,?,?,?,?)');$q->execute([$id,$idvariacion,$nombre,$precio,$default]);
    }
    $db->commit(); salidaTarifa(['success'=>true,'mensaje'=>'Precio guardado correctamente']);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('[LISTA PRECIOS] '.$e->getMessage());
    salidaTarifa(['success'=>false,'mensaje'=>'Error de servidor al consultar listas de precios. Consulta el registro de errores PHP.','codigo'=>'PRECIOS_SERVIDOR'],500);
}
