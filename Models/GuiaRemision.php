<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';

/**
 * GRE Remitente. Registro logístico independiente de ventas y de Kardex.
 * No produce movimientos de stock ni acredita aceptación SUNAT.
 */
final class GuiaRemision
{
    private PDO $db;

    public const MOTIVOS = [
        '01' => 'Venta', '02' => 'Compra', '03' => 'Venta con entrega a terceros',
        '04' => 'Traslado entre establecimientos de la misma empresa',
        '05' => 'Consignación', '06' => 'Devolución',
        '07' => 'Recojo de bienes transformados', '08' => 'Importación',
        '09' => 'Exportación', '13' => 'Otros',
        '14' => 'Venta sujeta a confirmación del comprador',
        '17' => 'Traslado de bienes para transformación',
        '18' => 'Traslado por emisor itinerante',
        '19' => 'Traslado de mercancía extranjera'
    ];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?: Conexion::conectar();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    private function all(string $sql, array $p = []): array
    {
        $s = $this->db->prepare($sql);
        $s->execute($p);
        return $s->fetchAll() ?: [];
    }

    private function one(string $sql, array $p = []): ?array
    {
        $s = $this->db->prepare($sql);
        $s->execute($p);
        $row = $s->fetch();
        return $row === false ? null : $row;
    }

    private function run(string $sql, array $p = []): void
    {
        $s = $this->db->prepare($sql);
        $s->execute($p);
    }

    private static function texto($value, int $max = 255): string
    {
        $s = trim((string)$value);
        if ((function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s)) > $max) {
            throw new InvalidArgumentException('Uno de los campos supera la longitud permitida (' . $max . ').');
        }
        return $s;
    }

    private static function fecha($value): string
    {
        $s = trim((string)$value);
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
        if (!$d || $d->format('Y-m-d') !== $s) {
            throw new InvalidArgumentException('La fecha de inicio de traslado no es válida.');
        }
        return $s;
    }

    private static function cantidad($value, string $campo = 'Cantidad'): string
    {
        if (!is_numeric($value) || (float)$value <= 0 || (float)$value > 99999999) {
            throw new InvalidArgumentException($campo . ' debe ser mayor que cero.');
        }
        $n = round((float)$value, 3);
        if (abs($n - (float)$value) > 0.000001) {
            throw new InvalidArgumentException($campo . ': máximo tres decimales.');
        }
        return number_format($n, 3, '.', '');
    }

    private function auditar(int $id, int $usuario, string $accion, string $detalle = ''): void
    {
        $this->run('INSERT INTO guia_remision_auditoria (idguia,idusuario,accion,detalle) VALUES (?,?,?,?)',
            [$id, $usuario, $accion, self::texto($detalle, 500)]);
    }

    public function catalogos(int $usuario): array
    {
        return [
            'negocio' => $this->one('SELECT id_negocio,nombre,ndocumento,documento,direccion FROM datos_negocio WHERE condicion=1 ORDER BY id_negocio LIMIT 1'),
            'sucursales' => $this->all('SELECT s.idsucursal,s.nombre,s.direccion,s.codigo_establecimiento_sunat FROM sucursal s INNER JOIN usuario_sucursal us ON us.idsucursal=s.idsucursal AND us.idusuario=? AND us.activo=1 WHERE s.activo=1 ORDER BY s.nombre', [$usuario]),
            'almacenes' => $this->all('SELECT a.idalmacen,a.idsucursal,a.nombre,a.ubicacion FROM almacen a INNER JOIN usuario_almacen ua ON ua.idalmacen=a.idalmacen AND ua.idusuario=? AND ua.activo=1 WHERE a.estado=1 ORDER BY a.nombre', [$usuario]),
            'series' => $this->all('SELECT gs.idserie,gs.idsucursal,gs.serie FROM guia_remision_serie gs INNER JOIN usuario_sucursal us ON us.idsucursal=gs.idsucursal AND us.idusuario=? AND us.activo=1 WHERE gs.activo=1 ORDER BY gs.serie', [$usuario]),
            'clientes' => $this->all("SELECT idpersona,nombre,tipo_documento,num_documento,direccion,email FROM persona WHERE tipo_persona='Cliente' AND idpersona<>1 ORDER BY nombre LIMIT 500"),
            'productos' => $this->all('SELECT a.idarticulo,a.idalmacen,a.codigo,a.nombre,a.unidad_medida_sunat,a.controla_lotes,a.controla_vencimiento,a.stock FROM articulo a INNER JOIN usuario_almacen ua ON ua.idalmacen=a.idalmacen AND ua.idusuario=? AND ua.activo=1 WHERE a.condicion=1 ORDER BY a.nombre LIMIT 2000', [$usuario]),
            'variantes' => $this->all('SELECT v.idvariacion,v.idarticulo,v.sku,v.combinacion,v.stock FROM articulo_variacion v WHERE v.estado=1 ORDER BY v.idarticulo,v.idvariacion LIMIT 4000'),
            'transportistas' => $this->all('SELECT idtransportista,ruc,razon_social FROM transporte_transportista WHERE activo=1 ORDER BY razon_social'),
            'vehiculos' => $this->all('SELECT idvehiculo,placa,marca FROM transporte_vehiculo WHERE activo=1 ORDER BY placa'),
            'conductores' => $this->all('SELECT idconductor,tipo_documento,num_documento,nombre,licencia FROM transporte_conductor WHERE activo=1 ORDER BY nombre'),
            'motivos' => self::MOTIVOS
        ];
    }

    public function lotes(int $articulo, int $variante, int $almacen, int $usuario): array
    {
        if (!$this->one('SELECT ua.idalmacen FROM usuario_almacen ua WHERE ua.idalmacen=? AND ua.idusuario=? AND ua.activo=1', [$almacen,$usuario])) {
            throw new InvalidArgumentException('No tiene acceso al almacén consultado.');
        }
        return $this->all('SELECT di.iddetalle_ingreso,di.numero_lote,di.fecha_vencimiento,di.stock_venta AS disponible
            FROM detalle_ingreso di WHERE di.idarticulo=? AND (di.idvariacion <=> ?) AND di.idalmacen=?
            AND di.tipo_detalle=\'INVENTARIO\' AND di.stock_venta>0
            ORDER BY CASE WHEN di.fecha_vencimiento IS NULL THEN 1 ELSE 0 END,di.fecha_vencimiento,di.iddetalle_ingreso',
            [$articulo, $variante > 0 ? $variante : null, $almacen]);
    }

    public function venta(int $id, int $usuario): array
    {
        $v = $this->one('SELECT v.idventa,v.idsucursal,v.idcliente,v.tipo_comprobante,v.serie_comprobante,v.num_comprobante,v.estado,v.direccion_cliente,
              p.nombre AS destinatario_nombre,p.tipo_documento AS destinatario_tipo_documento,p.num_documento AS destinatario_num_documento,
              p.email AS destinatario_email,p.direccion AS direccion_registrada
              FROM venta v INNER JOIN persona p ON p.idpersona=v.idcliente
              INNER JOIN usuario_sucursal us ON us.idsucursal=v.idsucursal AND us.idusuario=? AND us.activo=1
              WHERE v.idventa=? LIMIT 1', [$usuario, $id]);
        if (!$v || (int)$v['idcliente']===1 || !in_array($v['tipo_comprobante'], ['Factura Electrónica','Boleta Electrónica','Nota de venta'], true)
            || strtoupper((string)$v['estado']) !== 'ACEPTADO') {
            throw new InvalidArgumentException('La venta no existe, no es válida para una GRE o pertenece a otra sucursal.');
        }
        $items = $this->all('SELECT dv.iddetalle_venta,dv.idarticulo,dv.idvariacion,dv.cantidad,dv.unidad_medida_sunat,a.nombre,
            a.codigo,a.controla_lotes,av.combinacion,av.sku,
            (SELECT COALESCE(SUM(gd.cantidad),0) FROM guia_remision_detalle gd
             INNER JOIN guia_remision gr ON gr.idguia=gd.idguia
             WHERE gd.iddetalle_venta=dv.iddetalle_venta AND gr.estado<>\'ANULADA\') AS reservado
            FROM detalle_venta dv INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
            LEFT JOIN articulo_variacion av ON av.idvariacion=dv.idvariacion
            WHERE dv.idventa=? AND dv.estado=1 ORDER BY dv.iddetalle_venta', [$id]);
        foreach ($items as &$it) {
            $it['disponible'] = max(0, (float)$it['cantidad'] - (float)$it['reservado']);
            $it['lotes'] = $this->all('SELECT vdl.iddetalle_ingreso,di.numero_lote,di.fecha_vencimiento,vdl.cantidad,
                (SELECT COALESCE(SUM(gl.cantidad),0) FROM guia_remision_detalle_lote gl
                 INNER JOIN guia_remision_detalle gd ON gd.iddetalle=gl.iddetalle
                 INNER JOIN guia_remision gr ON gr.idguia=gd.idguia
                 WHERE gd.iddetalle_venta=? AND gl.iddetalle_ingreso=vdl.iddetalle_ingreso AND gr.estado<>\'ANULADA\') AS reservado
                 FROM venta_detalle_lote vdl INNER JOIN detalle_ingreso di ON di.iddetalle_ingreso=vdl.iddetalle_ingreso
                 WHERE vdl.iddetalle_venta=? ORDER BY di.fecha_vencimiento,vdl.idventa_detalle_lote',
                 [(int)$it['iddetalle_venta'], (int)$it['iddetalle_venta']]);
        }
        unset($it);
        return ['venta' => $v, 'items' => $items];
    }

    public function listar(int $usuario, array $filtros = []): array
    {
        $where = ['us.idusuario=?','us.activo=1'];
        $args = [$usuario];
        $estado = trim((string)($filtros['estado'] ?? ''));
        if ($estado !== '' && in_array($estado, ['BORRADOR','PREPARADA','PENDIENTE','ACEPTADA','RECHAZADA','EXCEPCION','ANULADA'], true)) {
            $where[] = 'g.estado=?'; $args[] = $estado;
        }
        $buscar = trim((string)($filtros['buscar'] ?? ''));
        if ($buscar !== '') {
            $where[] = '(g.destinatario_nombre LIKE ? OR g.destinatario_num_documento LIKE ? OR CONCAT(COALESCE(g.serie,\'\'),\'-\',COALESCE(g.correlativo,\'\')) LIKE ?)';
            for ($i=0; $i<3; $i++) $args[] = '%' . (function_exists('mb_substr') ? mb_substr($buscar, 0, 60) : substr($buscar, 0, 60)) . '%';
        }
        return $this->all('SELECT g.idguia,g.idventa,g.idsucursal,g.estado,g.motivo_traslado,g.motivo_descripcion,
           g.fecha_inicio_traslado,g.created_at,g.serie,g.correlativo,g.destinatario_nombre,g.destinatario_num_documento,
           g.modalidad_transporte,s.nombre AS sucursal,gs.serie AS serie_configurada,
           COALESCE(gs1.estado_sunat,\'NO_ENVIADO\') AS estado_sunat
           FROM guia_remision g INNER JOIN usuario_sucursal us ON us.idsucursal=g.idsucursal
           INNER JOIN sucursal s ON s.idsucursal=g.idsucursal
           INNER JOIN guia_remision_serie gs ON gs.idserie=g.idserie
           LEFT JOIN guia_remision_sunat gs1 ON gs1.idguia=g.idguia
           WHERE ' . implode(' AND ', $where) . ' ORDER BY g.idguia DESC LIMIT 300', $args);
    }

    public function ver(int $id, int $usuario): array
    {
        $cab = $this->one('SELECT g.*,gs.serie AS serie_configurada,s.nombre AS sucursal,n.nombre AS emisor_nombre,n.ndocumento AS emisor_ruc,
            sn.estado_sunat,sn.mensaje AS mensaje_sunat,sn.document_id,sn.xml_url,sn.cdr_url,sn.pdf_url
            FROM guia_remision g INNER JOIN guia_remision_serie gs ON gs.idserie=g.idserie
            INNER JOIN sucursal s ON s.idsucursal=g.idsucursal
            INNER JOIN datos_negocio n ON n.id_negocio=g.id_negocio
            INNER JOIN usuario_sucursal us ON us.idsucursal=g.idsucursal AND us.idusuario=? AND us.activo=1
            LEFT JOIN guia_remision_sunat sn ON sn.idguia=g.idguia WHERE g.idguia=? LIMIT 1', [$usuario, $id]);
        if (!$cab) throw new InvalidArgumentException('La guía no existe o no tienes acceso a su sucursal.');
        $items = $this->all('SELECT gd.* FROM guia_remision_detalle gd WHERE gd.idguia=? ORDER BY gd.orden,gd.iddetalle', [$id]);
        foreach ($items as &$item) {
            $item['lotes'] = $this->all('SELECT iddetalle_ingreso,numero_lote,fecha_vencimiento,cantidad FROM guia_remision_detalle_lote WHERE iddetalle=?', [(int)$item['iddetalle']]);
        }
        unset($item);
        return ['guia'=>$cab,'items'=>$items,'documentos'=>$this->all('SELECT tipo_documento,serie,numero,idventa FROM guia_remision_documento WHERE idguia=?', [$id]),
            'auditoria'=>$this->all('SELECT a.accion,a.detalle,a.created_at,u.nombre FROM guia_remision_auditoria a JOIN usuario u ON u.idusuario=a.idusuario WHERE a.idguia=? ORDER BY a.idlog DESC LIMIT 50', [$id])];
    }

    public function guardar(array $data, array $session): array
    {
        $usuario = (int)($session['idusuario'] ?? 0);
        $id = (int)($data['idguia'] ?? 0);
        $serie = (int)($data['idserie'] ?? 0);
        $almacen = (int)($data['idalmacen'] ?? 0);
        $idventa = (int)($data['idventa'] ?? 0);
        $motivo = self::texto($data['motivo_traslado'] ?? '', 2);
        $modalidad = self::texto($data['modalidad_transporte'] ?? '', 2);
        if (!isset(self::MOTIVOS[$motivo]) || !in_array($modalidad, ['01','02'], true)) {
            throw new InvalidArgumentException('Seleccione un motivo y modalidad de traslado válidos.');
        }
        $fecha = self::fecha($data['fecha_inicio_traslado'] ?? '');
        $tipoDoc = strtoupper(self::texto($data['destinatario_tipo_documento'] ?? '', 20));
        $numDoc = self::texto($data['destinatario_num_documento'] ?? '', 20);
        $nombre = self::texto($data['destinatario_nombre'] ?? '', 180);
        $direccion = self::texto($data['destinatario_direccion'] ?? '', 255);
        $email = self::texto($data['destinatario_email'] ?? '', 150);
        $ubigeoPartida = self::texto($data['partida_ubigeo'] ?? '', 6);
        $ubigeoLlegada = self::texto($data['llegada_ubigeo'] ?? '', 6);
        $direccionPartida = self::texto($data['partida_direccion'] ?? '', 255);
        $direccionLlegada = self::texto($data['llegada_direccion'] ?? '', 255);
        $peso = self::cantidad($data['peso_bruto'] ?? '', 'Peso bruto en kg');
        $bultos = ($data['numero_bultos'] ?? '') === '' ? null : (int)$data['numero_bultos'];
        if ($bultos !== null && ($bultos < 0 || $bultos > 99999999)) throw new InvalidArgumentException('Número de bultos inválido.');
        if ($nombre === '' || $numDoc === '' || $direccion === '' || $direccionPartida === '' || $direccionLlegada === '') {
            throw new InvalidArgumentException('Complete la identificación y direcciones de remitente/destinatario.');
        }
        if (!preg_match('/^\d{6}$/', $ubigeoPartida) || !preg_match('/^\d{6}$/', $ubigeoLlegada)) {
            throw new InvalidArgumentException('El ubigeo de partida y llegada debe tener seis dígitos.');
        }
        if (($tipoDoc === 'RUC' || $tipoDoc === '6') && !preg_match('/^\d{11}$/', $numDoc)) throw new InvalidArgumentException('El RUC del destinatario debe tener 11 dígitos.');
        if (($tipoDoc === 'DNI' || $tipoDoc === '1') && !preg_match('/^\d{8}$/', $numDoc)) throw new InvalidArgumentException('El DNI del destinatario debe tener 8 dígitos.');
        if (!in_array($tipoDoc, ['RUC','6','DNI','1','CE','4','PASAPORTE','7'], true)) throw new InvalidArgumentException('Tipo de documento del destinatario no permitido.');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Correo electrónico inválido.');
        $transportistaRuc = self::texto($data['transportista_ruc'] ?? '', 11);
        $transportistaNombre = self::texto($data['transportista_nombre'] ?? '', 180);
        $placa = strtoupper(self::texto($data['vehiculo_placa'] ?? '', 20));
        $condTipo = self::texto($data['conductor_tipo_documento'] ?? '', 20);
        $condDoc = self::texto($data['conductor_num_documento'] ?? '', 20);
        $condNombre = self::texto($data['conductor_nombre'] ?? '', 180);
        $licencia = strtoupper(self::texto($data['conductor_licencia'] ?? '', 40));
        if ($modalidad === '01' && (!preg_match('/^\d{11}$/', $transportistaRuc) || $transportistaNombre === '')) {
            throw new InvalidArgumentException('Transporte público: complete RUC y razón social del transportista.');
        }
        if ($modalidad === '02' && ($placa === '' || $condDoc === '' || $condNombre === '' || $licencia === '' || $condTipo === '')) {
            throw new InvalidArgumentException('Transporte privado: complete placa y datos del conductor/licencia.');
        }
        $items = $data['items'] ?? [];
        if (!is_array($items) || count($items) < 1 || count($items) > 150) {
            throw new InvalidArgumentException('Agregue entre 1 y 150 productos.');
        }
        $this->db->beginTransaction();
        try {
            $s = $this->one('SELECT gs.idserie,gs.idsucursal FROM guia_remision_serie gs INNER JOIN usuario_sucursal us ON us.idsucursal=gs.idsucursal AND us.idusuario=? AND us.activo=1 WHERE gs.idserie=? AND gs.activo=1 FOR UPDATE', [$usuario,$serie]);
            if (!$s) throw new InvalidArgumentException('No tiene acceso a la serie y sucursal seleccionada.');
            $sucursal = (int)$s['idsucursal'];
            if ($almacen <= 0) throw new InvalidArgumentException('Seleccione un almacén de origen autorizado.');
            if (!$this->one('SELECT a.idalmacen FROM almacen a INNER JOIN usuario_almacen ua ON ua.idalmacen=a.idalmacen AND ua.idusuario=? AND ua.activo=1 WHERE a.idalmacen=? AND a.idsucursal=? AND a.estado=1', [$usuario,$almacen,$sucursal])) {
                throw new InvalidArgumentException('Almacén no autorizado para la sucursal seleccionada.');
            }
            $negocio = $this->one('SELECT id_negocio FROM datos_negocio WHERE condicion=1 ORDER BY id_negocio LIMIT 1');
            if (!$negocio) throw new RuntimeException('Configure primero los datos del negocio.');
            $old = null;
            if ($id > 0) {
                $old = $this->one('SELECT idguia,estado,idventa,idsucursal FROM guia_remision WHERE idguia=? FOR UPDATE', [$id]);
                if (!$old || $old['estado'] !== 'BORRADOR' || (int)$old['idsucursal'] !== $sucursal) throw new InvalidArgumentException('Solo se puede editar un borrador de su misma sucursal.');
                if ((int)$old['idventa'] !== $idventa) throw new InvalidArgumentException('No se puede cambiar el documento de origen de una guía.');
            }
            $ventaCab = null;
            if ($idventa > 0) {
                $ventaCab = $this->one('SELECT * FROM venta WHERE idventa=? FOR UPDATE', [$idventa]);
                if (!$ventaCab || (int)$ventaCab['idcliente']===1 || (int)$ventaCab['idsucursal'] !== $sucursal ||
                    !in_array($ventaCab['tipo_comprobante'], ['Factura Electrónica','Boleta Electrónica','Nota de venta'], true) || strtoupper((string)$ventaCab['estado']) !== 'ACEPTADO') {
                    throw new InvalidArgumentException('La venta origen no es válida o es de otra sucursal.');
                }
                if ($motivo !== '01' && $motivo !== '03' && $motivo !== '14') throw new InvalidArgumentException('Una venta debe vincularse con un motivo de traslado por venta.');
                if ((int)$ventaCab['idcliente'] !== (int)($data['destinatario_idpersona'] ?? 0)) {
                    throw new InvalidArgumentException('La guía de una venta debe conservar al cliente original.');
                }
            }
            $clienteId = (int)($data['destinatario_idpersona'] ?? 0);
            if ($clienteId > 0) {
                $persona=$this->one('SELECT idpersona,num_documento FROM persona WHERE idpersona=? AND tipo_persona=\'Cliente\'', [$clienteId]);
                if (!$persona || $persona['num_documento']!==$numDoc) {
                    throw new InvalidArgumentException('Documento del destinatario no coincide con el cliente seleccionado.');
                }
            }
            $campos = [
                'id_negocio'=>(int)$negocio['id_negocio'], 'idsucursal'=>$sucursal, 'idalmacen'=>$almacen?:null,
                'idusuario'=>$usuario, 'idventa'=>$idventa?:null, 'idserie'=>$serie,
                'fecha_inicio_traslado'=>$fecha,'motivo_traslado'=>$motivo,'motivo_descripcion'=>self::MOTIVOS[$motivo],
                'modalidad_transporte'=>$modalidad,'destinatario_idpersona'=>$clienteId?:null,
                'destinatario_tipo_documento'=>$tipoDoc,'destinatario_num_documento'=>$numDoc,
                'destinatario_nombre'=>$nombre,'destinatario_direccion'=>$direccion,'destinatario_email'=>$email?:null,
                'partida_ubigeo'=>$ubigeoPartida,'partida_direccion'=>$direccionPartida,
                'llegada_ubigeo'=>$ubigeoLlegada,'llegada_direccion'=>$direccionLlegada,
                'transportista_ruc'=>$modalidad==='01'?$transportistaRuc:null,
                'transportista_nombre'=>$modalidad==='01'?$transportistaNombre:null,
                'vehiculo_placa'=>$modalidad==='02'?$placa:null,
                'conductor_tipo_documento'=>$modalidad==='02'?$condTipo:null,
                'conductor_num_documento'=>$modalidad==='02'?$condDoc:null,
                'conductor_nombre'=>$modalidad==='02'?$condNombre:null,
                'conductor_licencia'=>$modalidad==='02'?$licencia:null,
                'peso_bruto'=>$peso,'numero_bultos'=>$bultos,'observacion'=>self::texto($data['observacion'] ?? '',500)
            ];
            if ($id > 0) {
                $parts=[]; foreach ($campos as $k=>$_) $parts[]=$k.'=:'.$k;
                $campos['idguia']=$id;
                $this->run('UPDATE guia_remision SET '.implode(',',$parts).' WHERE idguia=:idguia',$campos);
                $this->run('DELETE FROM guia_remision_detalle WHERE idguia=?',[$id]);
                $this->run('DELETE FROM guia_remision_documento WHERE idguia=?',[$id]);
            } else {
                $cols=array_keys($campos);
                $this->run('INSERT INTO guia_remision ('.implode(',',$cols).') VALUES (:'.implode(',:',$cols).')',$campos);
                $id=(int)$this->db->lastInsertId();
            }
            $checkedIds=[];
            $lotesDeEstaGuia=[];
            foreach ($items as $i=>$item) {
                if (!is_array($item)) throw new InvalidArgumentException('Detalle de producto inválido.');
                $idarticulo=(int)($item['idarticulo']??0);
                $idvar=(int)($item['idvariacion']??0);
                $detalleVenta=(int)($item['iddetalle_venta']??0);
                $cant=self::cantidad($item['cantidad']??'', 'Cantidad de producto');
                $producto=$this->one('SELECT idarticulo,nombre,codigo,unidad_medida_sunat,idalmacen,controla_lotes FROM articulo WHERE idarticulo=? AND condicion=1',[$idarticulo]);
                if (!$producto) throw new InvalidArgumentException('Uno de los productos no existe o está inactivo.');
                if ((int)$producto['idalmacen'] !== $almacen) {
                    throw new InvalidArgumentException('El producto no pertenece al almacén de la guía.');
                }
                if ($idventa===0 && $idvar===0 && $this->one('SELECT idvariacion FROM articulo_variacion WHERE idarticulo=? AND estado=1 LIMIT 1', [$idarticulo])) {
                    throw new InvalidArgumentException('Seleccione la variante del producto ' . $producto['nombre'] . '.');
                }
                $variante=null;
                if ($idvar>0) {
                    $variante=$this->one('SELECT idvariacion,sku,combinacion FROM articulo_variacion WHERE idvariacion=? AND idarticulo=? AND estado=1',[$idvar,$idarticulo]);
                    if (!$variante) throw new InvalidArgumentException('La variante no pertenece al producto seleccionado.');
                }
                if ($idventa>0) {
                    $d=$this->one('SELECT dv.iddetalle_venta,dv.cantidad,dv.idarticulo,dv.idvariacion FROM detalle_venta dv WHERE dv.iddetalle_venta=? AND dv.idventa=? AND dv.estado=1',[$detalleVenta,$idventa]);
                    if (!$d || (int)$d['idarticulo']!==$idarticulo || (int)$d['idvariacion']!==$idvar || in_array($detalleVenta,$checkedIds,true)) throw new InvalidArgumentException('El detalle no corresponde a la venta, o está duplicado.');
                    $checkedIds[]=$detalleVenta;
                    $ocupado=$this->one('SELECT COALESCE(SUM(gd.cantidad),0) AS total FROM guia_remision_detalle gd JOIN guia_remision g ON g.idguia=gd.idguia WHERE gd.iddetalle_venta=? AND g.idguia<>? AND g.estado<>\'ANULADA\'',[$detalleVenta,$id]);
                    if ((float)$cant+(float)$ocupado['total']>(float)$d['cantidad']+0.000001) throw new InvalidArgumentException('La cantidad de una línea supera el saldo de la venta, considerando otras guías.');
                } elseif ($detalleVenta>0) throw new InvalidArgumentException('Una guía independiente no puede vincular detalles de ventas.');
                $codigo=(string)($variante['sku']??$producto['codigo']??'');
                $descripcion=(string)$producto['nombre'] . ($variante ? ' - '.$variante['combinacion'] : '');
                $this->run('INSERT INTO guia_remision_detalle(idguia,idarticulo,idvariacion,iddetalle_venta,codigo,descripcion,unidad_sunat,cantidad,orden)
                        VALUES (?,?,?,?,?,?,?,?,?)',[$id,$idarticulo,$idvar?:null,$detalleVenta?:null,$codigo,$descripcion,$producto['unidad_medida_sunat'],$cant,$i+1]);
                $iddet=(int)$this->db->lastInsertId();
                $lotes=$item['lotes']??[];
                if (!is_array($lotes)) throw new InvalidArgumentException('Formato de lotes no válido.');
                $qtyLotes=0.0;
                $lotesLinea=[];
                foreach ($lotes as $l) {
                    $idlote=(int)($l['iddetalle_ingreso']??0);
                    if ($idlote<=0 || in_array($idlote,$lotesLinea,true)) throw new InvalidArgumentException('Lote repetido o inválido.');
                    $lotesLinea[]=$idlote;
                    $q=self::cantidad($l['cantidad']??'', 'Cantidad de lote');
                    $origen=$this->one('SELECT di.iddetalle_ingreso,di.idarticulo,di.idvariacion,di.idalmacen,di.numero_lote,di.fecha_vencimiento,di.stock_venta FROM detalle_ingreso di WHERE di.iddetalle_ingreso=?',[$idlote]);
                    if (!$origen || (int)$origen['idarticulo']!==$idarticulo || (int)$origen['idvariacion']!==$idvar || ($almacen>0 && (int)$origen['idalmacen']!==$almacen)) throw new InvalidArgumentException('El lote no pertenece al producto/almacén.');
                    if ($idventa>0) {
                        $asignado=$this->one('SELECT COALESCE(SUM(cantidad),0) AS total FROM venta_detalle_lote WHERE iddetalle_venta=? AND iddetalle_ingreso=?',[$detalleVenta,$idlote]);
                        $otros=$this->one('SELECT COALESCE(SUM(gl.cantidad),0) AS total FROM guia_remision_detalle_lote gl JOIN guia_remision_detalle gd ON gd.iddetalle=gl.iddetalle JOIN guia_remision gr ON gr.idguia=gd.idguia WHERE gd.iddetalle_venta=? AND gl.iddetalle_ingreso=? AND gr.idguia<>? AND gr.estado<>\'ANULADA\'',[$detalleVenta,$idlote,$id]);
                        if ((float)$q+(float)$otros['total']>(float)$asignado['total']+0.000001) throw new InvalidArgumentException('El lote supera lo asignado en la venta.');
                    } else {
                        $otrasGuias=$this->one('SELECT COALESCE(SUM(gl.cantidad),0) AS total
                            FROM guia_remision_detalle_lote gl
                            INNER JOIN guia_remision_detalle gd ON gd.iddetalle=gl.iddetalle
                            INNER JOIN guia_remision gr ON gr.idguia=gd.idguia
                            WHERE gl.iddetalle_ingreso=? AND gr.idventa IS NULL AND gr.estado<>\'ANULADA\' AND gr.idguia<>?', [$idlote,$id]);
                        if ((float)$q+(float)($lotesDeEstaGuia[$idlote]??0)+(float)$otrasGuias['total']>(float)$origen['stock_venta']+0.000001) {
                            throw new InvalidArgumentException('Cantidad de lote superior al stock libre considerando otras guías.');
                        }
                    }
                    $this->run('INSERT INTO guia_remision_detalle_lote(iddetalle,iddetalle_ingreso,numero_lote,fecha_vencimiento,cantidad) VALUES (?,?,?,?,?)',[$iddet,$idlote,$origen['numero_lote'],$origen['fecha_vencimiento'],$q]);
                    $qtyLotes+=(float)$q;
                    $lotesDeEstaGuia[$idlote]=($lotesDeEstaGuia[$idlote]??0)+(float)$q;
                }
                if ($qtyLotes>(float)$cant+0.000001 || ((int)$producto['controla_lotes']===1 && abs($qtyLotes-(float)$cant)>0.000001)) {
                    throw new InvalidArgumentException('Los lotes deben cubrir exactamente la cantidad trasladada del producto controlado.');
                }
            }
            if ($ventaCab) {
                $tipo = $ventaCab['tipo_comprobante']==='Factura Electrónica'?'01':($ventaCab['tipo_comprobante']==='Boleta Electrónica'?'03':'NV');
                $this->run('INSERT INTO guia_remision_documento(idguia,idventa,tipo_documento,serie,numero) VALUES(?,?,?,?,?)',[$id,$idventa,$tipo,$ventaCab['serie_comprobante'],$ventaCab['num_comprobante']]);
            }
            $this->auditar($id,$usuario,$old?'ACTUALIZAR_BORRADOR':'CREAR_BORRADOR','Registro local. No enviado a SUNAT ni aplicado a stock.');
            $this->db->commit();
            return ['idguia'=>$id,'estado'=>'BORRADOR'];
        } catch(Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function preparar(int $id, int $usuario): void
    {
        $this->db->beginTransaction();
        try {
            $g=$this->one('SELECT g.* FROM guia_remision g JOIN usuario_sucursal us ON us.idsucursal=g.idsucursal AND us.idusuario=? AND us.activo=1 WHERE g.idguia=? FOR UPDATE',[$usuario,$id]);
            if (!$g || $g['estado']!=='BORRADOR') throw new InvalidArgumentException('Solo un borrador puede marcarse preparado.');
            if ($g['fecha_inicio_traslado'] < date('Y-m-d')) throw new InvalidArgumentException('Actualice la fecha de traslado antes de preparar la guía.');
            $items=$this->one('SELECT COUNT(*) AS n FROM guia_remision_detalle WHERE idguia=?',[$id]);
            if ((int)$items['n']===0) throw new InvalidArgumentException('La guía no tiene productos.');
            $this->run("UPDATE guia_remision SET estado='PREPARADA' WHERE idguia=?",[$id]);
            $this->auditar($id,$usuario,'PREPARAR','Lista para validación final del conector GRE. No autorizada para traslado.');
            $this->db->commit();
        } catch(Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function volverBorrador(int $id, int $usuario): void
    {
        $this->db->beginTransaction();
        try {
            $g=$this->one('SELECT g.idguia FROM guia_remision g INNER JOIN usuario_sucursal us ON us.idsucursal=g.idsucursal AND us.idusuario=? AND us.activo=1 WHERE g.idguia=? AND g.estado=\'PREPARADA\' FOR UPDATE',[$usuario,$id]);
            if (!$g) throw new InvalidArgumentException('Solo una guía preparada sin envío puede editarse.');
            $this->run("UPDATE guia_remision SET estado='BORRADOR' WHERE idguia=?",[$id]);
            $this->auditar($id,$usuario,'VOLVER_BORRADOR','Se habilitó la edición.');
            $this->db->commit();
        } catch(Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function anular(int $id, int $usuario): void
    {
        $this->db->beginTransaction();
        try {
            $g=$this->one('SELECT g.idguia,g.estado FROM guia_remision g INNER JOIN usuario_sucursal us ON us.idsucursal=g.idsucursal AND us.idusuario=? AND us.activo=1 WHERE g.idguia=? FOR UPDATE',[$usuario,$id]);
            if (!$g || !in_array($g['estado'],['BORRADOR','PREPARADA'],true)) throw new InvalidArgumentException('Solo puede descartar guías locales no enviadas.');
            $this->run("UPDATE guia_remision SET estado='ANULADA' WHERE idguia=?",[$id]);
            $this->auditar($id,$usuario,'DESCARTAR','Borrador local descartado. Se liberan las cantidades reservadas.');
            $this->db->commit();
        } catch(Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
}
