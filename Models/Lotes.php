<?php
declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';
require_once __DIR__ . '/Product.php';

/** Persistencia, transacciones y consultas del inventario por lote. */
final class Lotes
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conexion::conectar();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function configurar(int $id, bool $lotes, bool $vence, int $dias): string
    {
        if ($id <= 0 || $dias < 1 || $dias > 3650) {
            throw new RuntimeException('Producto o número de días inválido.');
        }
        $this->pdo->beginTransaction();
        try {
            $actual = $this->run('SELECT nombre FROM articulo WHERE idarticulo=? FOR UPDATE', [$id])->fetch(PDO::FETCH_ASSOC);
            if (!$actual) throw new RuntimeException('Producto no encontrado.');
            // Product utiliza la misma conexión PDO compartida en Config/Conexion.php.
            (new Product())->guardarConfiguracionLotes($id, $lotes || $vence, $vence, $dias);
            $this->pdo->commit();
            return 'Configuración actualizada para ' . $actual['nombre'] . '.';
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Identifica existencias de un ingreso previo o corrige fecha/número de un lote existente.
     * No suma ni resta unidades; la actualización y su auditoría son atómicas.
     */
    public function guardarIdentificacion(int $id, string $numero, ?string $fecha, string $motivo, int $usuario, string $nonce): string
    {
        $numero = trim($numero);
        $motivo = trim($motivo);
        if ($id <= 0 || $usuario <= 0 || $numero === '' || strlen($numero) > 80) {
            throw new RuntimeException('Selecciona el ingreso e introduce un número de lote válido (máximo 80 caracteres).');
        }
        $tam = function_exists('mb_strlen') ? mb_strlen($motivo, 'UTF-8') : strlen($motivo);
        if ($tam < 8 || $tam > 255) throw new RuntimeException('Indica el motivo de la identificación o corrección (8 a 255 caracteres).');
        if ($fecha === '') $fecha = null;
        if ($fecha !== null) {
            $valida = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $fecha) || !$valida || $valida->format('Y-m-d') !== $fecha) {
                throw new RuntimeException('Fecha inválida: selecciona un día del calendario, por ejemplo 28/09/2027.');
            }
        }
        $this->pdo->beginTransaction();
        try {
            // Orden fijo de bloqueos con las otras operaciones sobre inventario.
            $clave = $this->run('SELECT idarticulo FROM detalle_ingreso WHERE iddetalle_ingreso=?', [$id])->fetchColumn();
            if (!$clave) throw new RuntimeException('Ingreso no encontrado.');
            $articulo = $this->run('SELECT idarticulo,nombre,controla_vencimiento FROM articulo WHERE idarticulo=? FOR UPDATE', [(int)$clave])->fetch(PDO::FETCH_ASSOC);
            $fila = $this->run("SELECT di.iddetalle_ingreso,di.idarticulo,di.idvariacion,di.numero_lote,di.fecha_vencimiento,di.stock_venta
                FROM detalle_ingreso di WHERE di.iddetalle_ingreso=? AND di.tipo_detalle='INVENTARIO'
                AND di.afecta_stock=1 AND di.estado=1 FOR UPDATE", [$id])->fetch(PDO::FETCH_ASSOC);
            if (!$fila || (int)$fila['stock_venta'] <= 0 || (int)$fila['idarticulo'] !== (int)$articulo['idarticulo']) {
                throw new RuntimeException('Este ingreso no dispone de unidades identificables.');
            }
            if ((int)$articulo['controla_vencimiento'] === 1 && $fecha === null) {
                throw new RuntimeException('El producto tiene control de vencimiento: debes indicar una fecha.');
            }
            // Un número de lote identifica una misma partida para la misma variante.
            // Una corrección debe actualizar TODOS los ingresos de esa partida,
            // incluso agotados, o el catálogo terminaría con fechas contradictorias.
            $numeroAnterior = $fila['numero_lote'] ?: null;
            $esLoteRegistrado = $numeroAnterior !== null;
            if ($esLoteRegistrado) {
                $filasPartida = $this->run("SELECT iddetalle_ingreso,numero_lote,fecha_vencimiento
                    FROM detalle_ingreso WHERE idarticulo=? AND idvariacion <=> ? AND numero_lote=?
                    AND tipo_detalle='INVENTARIO' AND afecta_stock=1 AND estado=1
                    ORDER BY iddetalle_ingreso FOR UPDATE",
                    [(int)$fila['idarticulo'], $fila['idvariacion'], $numeroAnterior])->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $filasPartida = [$fila];
            }
            if (!$filasPartida) throw new RuntimeException('No se encontraron existencias para ese lote.');
            // Si se renombra un lote, no se permite fusionarlo con una partida
            // que tenga vencimiento distinto.
            if (!$esLoteRegistrado || $numeroAnterior !== $numero) {
                $conflicto = $this->run("SELECT iddetalle_ingreso FROM detalle_ingreso
                    WHERE idarticulo=? AND idvariacion <=> ? AND numero_lote=?
                      AND tipo_detalle='INVENTARIO' AND afecta_stock=1 AND estado=1
                      AND NOT (fecha_vencimiento <=> ?)
                    LIMIT 1",
                    [(int)$fila['idarticulo'], $fila['idvariacion'], $numero, $fecha])->fetchColumn();
                if ($conflicto !== false) throw new RuntimeException('El lote de destino ya tiene otro vencimiento. Comprueba la etiqueta antes de unir partidas.');
            }
            $modificados = 0;
            foreach ($filasPartida as $partida) {
                $actualNumero = $partida['numero_lote'] ?: null;
                $actualFecha = $partida['fecha_vencimiento'] ?: null;
                if ($actualNumero === $numero && $actualFecha === $fecha) continue;
                $detalleId = (int)$partida['iddetalle_ingreso'];
                $this->run('UPDATE detalle_ingreso SET numero_lote=?, fecha_vencimiento=? WHERE iddetalle_ingreso=?', [$numero, $fecha, $detalleId]);
                $idempotencia = substr(hash('sha256', $nonce . '|' . $detalleId), 0, 32);
                $this->run('INSERT INTO inventario_lote_identificacion
                    (iddetalle_ingreso,idarticulo,idvariacion,idusuario,lote_anterior,lote_nuevo,fecha_anterior,fecha_nueva,motivo,idempotencia)
                    VALUES (?,?,?,?,?,?,?,?,?,?)', [$detalleId,(int)$fila['idarticulo'],$fila['idvariacion'],$usuario,
                        $actualNumero,$numero,$actualFecha,$fecha,$motivo,$idempotencia]);
                ++$modificados;
            }
            if ($modificados === 0) throw new RuntimeException('Este lote ya tiene la misma identificación y vencimiento.');
            $this->pdo->commit();
            return 'Lote ' . $numero . ' actualizado en ' . $modificados . ' ingreso(s). Vencimiento: ' . ($fecha ? date('d/m/Y',strtotime($fecha)) : 'sin fecha') . '. El stock no fue modificado.';
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Ingresos históricos con stock físico que aún carecen de identificador de lote. */
    public function porIdentificar(string $busqueda, int $pagina): array
    {
        $q = '%' . $busqueda . '%';
        $from = " FROM detalle_ingreso di JOIN articulo a ON a.idarticulo=di.idarticulo
            LEFT JOIN articulo_variacion v ON v.idvariacion=di.idvariacion
            LEFT JOIN almacen al ON al.idalmacen=COALESCE(di.idalmacen,a.idalmacen)
            WHERE di.tipo_detalle='INVENTARIO' AND di.afecta_stock=1 AND di.estado=1
            AND di.stock_venta>0 AND (di.numero_lote IS NULL OR di.numero_lote='')
            AND (a.nombre LIKE ? OR a.codigo LIKE ? OR v.sku LIKE ?)";
        $n = (int)$this->run('SELECT COUNT(*)' . $from, [$q,$q,$q])->fetchColumn();
        $paginasPendientes = max(1,(int)ceil($n/20));
        $paginaPendientes = min(max(1,$pagina),$paginasPendientes);
        $pendientes = $this->run('SELECT di.iddetalle_ingreso,di.idarticulo,di.idvariacion,di.stock_venta,
            di.fecha_vencimiento,a.nombre AS producto,a.codigo,COALESCE(v.combinacion,\'Producto simple\') AS presentacion,
            al.nombre AS almacen,di.idingreso' . $from .
            ' ORDER BY a.nombre,di.iddetalle_ingreso LIMIT 20 OFFSET ' . (($paginaPendientes-1)*20),[$q,$q,$q])->fetchAll(PDO::FETCH_ASSOC);
        return compact('n','paginasPendientes','paginaPendientes','pendientes');
    }

    public function darDeBaja(int $id, int $cantidad, string $tipo, string $motivo, int $usuario, string $nonce): string
    {
        if ($id <= 0 || $cantidad <= 0) throw new RuntimeException('Especifica el lote y una cantidad positiva.');
        if (!in_array($tipo, ['BAJA_VENCIMIENTO', 'BAJA_DANO', 'BAJA_OTRO'], true)) {
            throw new RuntimeException('Tipo de baja inválido.');
        }
        $length = function_exists('mb_strlen') ? mb_strlen($motivo, 'UTF-8') : strlen($motivo);
        if ($length < 8 || $length > 255) {
            throw new RuntimeException('El sustento debe tener entre 8 y 255 caracteres.');
        }
        $this->pdo->beginTransaction();
        try {
            $lote = $this->run("SELECT di.iddetalle_ingreso,di.idarticulo,di.idvariacion,di.numero_lote,di.stock_venta,di.precio_compra,
                a.nombre,a.stock,a.controla_lotes,a.controla_vencimiento
                FROM detalle_ingreso di JOIN articulo a ON a.idarticulo=di.idarticulo
                WHERE di.iddetalle_ingreso=? AND di.tipo_detalle='INVENTARIO' AND di.afecta_stock=1 AND di.estado=1 FOR UPDATE", [$id])->fetch(PDO::FETCH_ASSOC);
            if (!$lote || (int)$lote['stock_venta'] < $cantidad) {
                throw new RuntimeException('El lote ya no tiene suficiente stock disponible.');
            }
            if (!(int)$lote['controla_lotes'] && !(int)$lote['controla_vencimiento']) {
                throw new RuntimeException('Solo se permiten bajas de productos con control de lotes.');
            }
            if ((int)$lote['stock'] < $cantidad) {
                throw new RuntimeException('Inconsistencia entre stock de artículo y de lote. Requiere conciliación.');
            }
            $idvariacion = (int)($lote['idvariacion'] ?? 0);
            if ($idvariacion > 0) {
                $var = $this->run('SELECT stock FROM articulo_variacion WHERE idvariacion=? AND idarticulo=? FOR UPDATE',
                    [$idvariacion, (int)$lote['idarticulo']])->fetchColumn();
                if ($var === false || (int)$var < $cantidad) {
                    throw new RuntimeException('La variante no dispone de ese saldo.');
                }
            }
            $afectadas = $this->run('UPDATE detalle_ingreso SET stock_venta=stock_venta-?, stock_estado=IF(stock_venta-?<=0,0,1) WHERE iddetalle_ingreso=? AND stock_venta>=?',
                [$cantidad, $cantidad, $id, $cantidad])->rowCount();
            if ($afectadas !== 1) throw new RuntimeException('Otro proceso modificó este lote. Inténtalo de nuevo.');
            if ($idvariacion > 0) {
                $afectadas = $this->run('UPDATE articulo_variacion SET stock=stock-? WHERE idvariacion=? AND idarticulo=? AND stock>=?',
                    [$cantidad, $idvariacion, (int)$lote['idarticulo'], $cantidad])->rowCount();
                if ($afectadas !== 1) throw new RuntimeException('El stock de la variante cambió.');
            }
            $afectadas = $this->run('UPDATE articulo SET stock=stock-? WHERE idarticulo=? AND stock>=?',
                [$cantidad, (int)$lote['idarticulo'], $cantidad])->rowCount();
            if ($afectadas !== 1) throw new RuntimeException('El stock total del producto cambió.');

            $restante = (int)$lote['stock_venta'] - $cantidad;
            $costo = (float)$lote['precio_compra'];
            $this->run('INSERT INTO inventario_lote_ajuste (iddetalle_ingreso,idarticulo,idvariacion,idusuario,tipo,cantidad,costo_unitario,stock_antes,stock_despues,motivo,idempotencia) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [
                $id, (int)$lote['idarticulo'], $idvariacion > 0 ? $idvariacion : null, $usuario,
                $tipo, $cantidad, $costo, (int)$lote['stock_venta'], $restante, $motivo, $nonce
            ]);
            $referencia = 'Baja lote ' . (string)$lote['numero_lote'] . ' #' . $id;
            $referencia = function_exists('mb_substr') ? mb_substr($referencia, 0, 64, 'UTF-8') : substr($referencia, 0, 64);
            $this->run("INSERT INTO kardex (iddetalle,idarticulo,fecha,detalle,cantidadi,costoui,totali,cantidads,costous,totals,cantidadex,costouex,totalex,tipo,estado)
                VALUES (?,?,CURDATE(),?,0,0,0,?,?,?,?,?,?,'Salida','Activo')", [
                $id, (int)$lote['idarticulo'], $referencia, $cantidad, $costo,
                round($cantidad * $costo, 2), (int)$lote['stock'] - $cantidad,
                $costo, round(((int)$lote['stock'] - $cantidad) * $costo, 2)
            ]);
            $this->pdo->commit();
            return 'Baja registrada y auditada: ' . $cantidad . ' unidad(es) del lote ' . $lote['numero_lote'] . '.';
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function filtroLotes(string $busqueda, string $estado, int $almacen): array
    {
        $where = ["di.tipo_detalle='INVENTARIO'", 'di.afecta_stock=1', 'di.estado=1',
            'di.stock_venta>0', 'di.numero_lote IS NOT NULL', "di.numero_lote<>''"];
        $params = [];
        if ($busqueda !== '') {
            $where[] = '(a.nombre LIKE ? OR a.codigo LIKE ? OR di.numero_lote LIKE ? OR av.sku LIKE ?)';
            $buscar = '%' . $busqueda . '%';
            $params = [$buscar, $buscar, $buscar, $buscar];
        }
        if ($almacen > 0) {
            $where[] = 'COALESCE(di.idalmacen,a.idalmacen)=?';
            $params[] = $almacen;
        }
        if ($estado === 'vencidos') $where[] = 'di.fecha_vencimiento<CURDATE()';
        if ($estado === 'proximos') $where[] = 'di.fecha_vencimiento>=CURDATE() AND di.fecha_vencimiento<=DATE_ADD(CURDATE(), INTERVAL a.dias_alerta_vencimiento DAY)';
        if ($estado === 'vigentes') $where[] = 'di.fecha_vencimiento>DATE_ADD(CURDATE(), INTERVAL a.dias_alerta_vencimiento DAY)';
        if ($estado === 'sin_fecha') $where[] = 'di.fecha_vencimiento IS NULL';
        $joins = ' FROM detalle_ingreso di JOIN articulo a ON a.idarticulo=di.idarticulo LEFT JOIN articulo_variacion av ON av.idvariacion=di.idvariacion LEFT JOIN almacen al ON al.idalmacen=COALESCE(di.idalmacen,a.idalmacen) ';
        $base = 'SELECT di.iddetalle_ingreso,a.idarticulo,a.codigo,a.nombre AS producto,a.dias_alerta_vencimiento,COALESCE(av.combinacion,\'Producto simple\') AS presentacion,al.nombre AS almacen,di.numero_lote,di.fecha_vencimiento,di.stock_venta,di.precio_compra,DATEDIFF(di.fecha_vencimiento,CURDATE()) AS dias_restantes';
        return [$joins . ' WHERE ' . implode(' AND ', $where), $params, $base];
    }

    public function obtenerLotes(string $busqueda, string $estado, int $almacen, int $pagina, int $limite = 25): array
    {
        [$from, $params, $base] = $this->filtroLotes($busqueda, $estado, $almacen);
        $count = (int)$this->run('SELECT COUNT(*)' . $from, $params)->fetchColumn();
        $paginas = max(1, (int)ceil($count / $limite));
        $pagina = min(max(1, $pagina), $paginas);
        $desde = ($pagina - 1) * $limite;
        $rows = $this->run($base . $from . ' ORDER BY CASE WHEN di.fecha_vencimiento IS NULL THEN 1 ELSE 0 END,di.fecha_vencimiento ASC,a.nombre,di.iddetalle_ingreso LIMIT ' . $limite . ' OFFSET ' . $desde, $params)->fetchAll(PDO::FETCH_ASSOC);
        return compact('count', 'paginas', 'pagina', 'desde', 'rows', 'limite');
    }

    public function exportarLotes(string $busqueda, string $estado, int $almacen): iterable
    {
        [$from, $params, $base] = $this->filtroLotes($busqueda, $estado, $almacen);
        $count = (int)$this->run('SELECT COUNT(*)' . $from, $params)->fetchColumn();
        // Paginación evita cargar todo el inventario en RAM.
        for ($offset = 0; $offset < $count; $offset += 500) {
            $stmt = $this->run($base . $from . ' ORDER BY a.nombre,di.fecha_vencimiento,di.iddetalle_ingreso LIMIT 500 OFFSET ' . $offset, $params);
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) yield $r;
            $stmt->closeCursor();
        }
    }

    public function resumen(): array
    {
        return $this->run("SELECT COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN di.fecha_vencimiento<CURDATE() THEN 1 ELSE 0 END),0) AS vencidos,
            COALESCE(SUM(CASE WHEN di.fecha_vencimiento>=CURDATE() AND di.fecha_vencimiento<=DATE_ADD(CURDATE(),INTERVAL a.dias_alerta_vencimiento DAY) THEN 1 ELSE 0 END),0) AS proximos,
            COALESCE(SUM(CASE WHEN di.fecha_vencimiento<CURDATE() THEN di.stock_venta ELSE 0 END),0) AS unidades_vencidas
            FROM detalle_ingreso di JOIN articulo a ON a.idarticulo=di.idarticulo
            WHERE di.tipo_detalle='INVENTARIO' AND di.afecta_stock=1 AND di.estado=1 AND di.stock_venta>0
            AND di.numero_lote IS NOT NULL AND di.numero_lote<>''")->fetch(PDO::FETCH_ASSOC);
    }

    public function almacenes(): array
    {
        return $this->run('SELECT idalmacen,nombre FROM almacen WHERE estado=1 ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function productos(string $busqueda, int $pagina): array
    {
        $buscar = '%' . $busqueda . '%';
        $totalProductos = (int)$this->run('SELECT COUNT(*) FROM articulo WHERE nombre LIKE ? OR codigo LIKE ?', [$buscar, $buscar])->fetchColumn();
        $totalPaginasProductos = max(1, (int)ceil($totalProductos / 15));
        $paginaProductos = min(max(1, $pagina), $totalPaginasProductos);
        $productos = $this->run("SELECT a.idarticulo,a.codigo,a.nombre,a.stock,a.controla_lotes,a.controla_vencimiento,a.dias_alerta_vencimiento,
            (SELECT COALESCE(SUM(v.stock),0) FROM articulo_variacion v WHERE v.idarticulo=a.idarticulo) AS stock_variaciones
            FROM articulo a WHERE a.nombre LIKE ? OR a.codigo LIKE ? ORDER BY a.nombre LIMIT 15 OFFSET " . (($paginaProductos - 1) * 15),
            [$buscar, $buscar])->fetchAll(PDO::FETCH_ASSOC);
        return compact('totalProductos', 'totalPaginasProductos', 'paginaProductos', 'productos');
    }

    /** Indica las migraciones de auditoría aún pendientes sin provocar errores HTTP 500. */
    public function auditoriasPendientes(): array
    {
        $tablas = ['inventario_lote_ajuste', 'inventario_lote_identificacion'];
        $presentes = $this->run(
            "SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN (?,?)",
            $tablas
        )->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_diff($tablas, $presentes));
    }

    public function identificacionesRecientes(): array
    {
        if (in_array('inventario_lote_identificacion', $this->auditoriasPendientes(), true)) return [];
        return $this->run('SELECT h.created_at,h.lote_anterior,h.lote_nuevo,h.fecha_anterior,h.fecha_nueva,h.motivo,
            a.nombre AS producto,u.nombre AS usuario,h.iddetalle_ingreso
            FROM inventario_lote_identificacion h JOIN articulo a ON a.idarticulo=h.idarticulo
            JOIN usuario u ON u.idusuario=h.idusuario
            ORDER BY h.ididentificacion DESC LIMIT 12')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajustesRecientes(): array
    {
        if (in_array('inventario_lote_ajuste', $this->auditoriasPendientes(), true)) return [];
        return $this->run('SELECT aj.created_at,aj.tipo,aj.cantidad,aj.motivo,aj.stock_despues,aj.iddetalle_ingreso,
            a.nombre AS producto,u.nombre AS usuario,di.numero_lote
            FROM inventario_lote_ajuste aj JOIN articulo a ON a.idarticulo=aj.idarticulo
            JOIN detalle_ingreso di ON di.iddetalle_ingreso=aj.iddetalle_ingreso
            JOIN usuario u ON u.idusuario=aj.idusuario ORDER BY aj.idajuste DESC LIMIT 12')->fetchAll(PDO::FETCH_ASSOC);
    }
}
