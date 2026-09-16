<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';

final class TiendaWeb
{
    private Conexion $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
        $this->asegurarEsquema();
        $this->asegurarConfiguracionInicial();
    }

    private function tablaExiste(string $tabla): bool
    {
        return (int)$this->conexion->getValue(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$tabla]
        ) > 0;
    }

    private function asegurarEsquema(): void
    {
        if (!$this->tablaExiste('tienda_configuracion')) {
            $this->conexion->setData(
            "CREATE TABLE IF NOT EXISTS tienda_configuracion (
                idconfig INT NOT NULL AUTO_INCREMENT,
                id_negocio INT NOT NULL,
                slug VARCHAR(80) NOT NULL,
                activo TINYINT(1) NOT NULL DEFAULT 0,
                titulo VARCHAR(120) DEFAULT NULL,
                subtitulo VARCHAR(255) DEFAULT NULL,
                descripcion TEXT DEFAULT NULL,
                whatsapp VARCHAR(25) DEFAULT NULL,
                instagram VARCHAR(180) DEFAULT NULL,
                facebook VARCHAR(180) DEFAULT NULL,
                color_primario VARCHAR(7) NOT NULL DEFAULT '#00A46A',
                mostrar_stock TINYINT(1) NOT NULL DEFAULT 1,
                mostrar_codigo TINYINT(1) NOT NULL DEFAULT 1,
                mostrar_descripcion TINYINT(1) NOT NULL DEFAULT 1,
                mostrar_sin_stock TINYINT(1) NOT NULL DEFAULT 1,
                mostrar_precios TINYINT(1) NOT NULL DEFAULT 1,
                boton_whatsapp TINYINT(1) NOT NULL DEFAULT 1,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (idconfig),
                UNIQUE KEY uq_tienda_config_negocio (id_negocio),
                UNIQUE KEY uq_tienda_config_slug (slug),
                CONSTRAINT fk_tienda_config_negocio
                    FOREIGN KEY (id_negocio) REFERENCES datos_negocio (id_negocio)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!$this->tablaExiste('tienda_producto')) {
            $this->conexion->setData(
                "CREATE TABLE IF NOT EXISTS tienda_producto (
                idarticulo INT NOT NULL,
                publicado TINYINT(1) NOT NULL DEFAULT 1,
                destacado TINYINT(1) NOT NULL DEFAULT 0,
                orden INT NOT NULL DEFAULT 0,
                actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (idarticulo),
                KEY idx_tienda_producto_publicado (publicado, destacado, orden),
                CONSTRAINT fk_tienda_producto_articulo
                    FOREIGN KEY (idarticulo) REFERENCES articulo (idarticulo)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    private function empresaActiva(): ?array
    {
        $row = $this->conexion->getData(
            "SELECT id_negocio, nombre, documento, direccion, telefono, email,
                    pais, ciudad, moneda, simbolo, logo
             FROM datos_negocio
             WHERE condicion = 1
             ORDER BY id_negocio ASC
             LIMIT 1"
        );

        return is_array($row) ? $row : null;
    }

    private function asegurarConfiguracionInicial(): void
    {
        $empresa = $this->empresaActiva();
        if (!$empresa) {
            return;
        }

        $idNegocio = (int)$empresa['id_negocio'];
        $existe = (int)$this->conexion->getValue(
            'SELECT COUNT(*) FROM tienda_configuracion WHERE id_negocio = ?',
            [$idNegocio]
        );

        if ($existe > 0) {
            return;
        }

        $slug = $this->slugDisponible((string)($empresa['nombre'] ?? 'mi-tienda'), $idNegocio);
        $this->conexion->setData(
            "INSERT INTO tienda_configuracion
                (id_negocio, slug, activo, titulo, subtitulo, whatsapp)
             VALUES (?, ?, 0, ?, ?, ?)",
            [
                $idNegocio,
                $slug,
                trim((string)($empresa['nombre'] ?? 'Mi tienda')),
                'Conoce nuestros productos y precios actualizados.',
                $this->normalizarTelefono((string)($empresa['telefono'] ?? ''))
            ]
        );
    }

    private function slugBase(string $texto): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return 'mi-tienda';
        }

        if (function_exists('iconv')) {
            $convertido = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
            if (is_string($convertido) && $convertido !== '') {
                $texto = $convertido;
            }
        } else {
            $texto = strtr($texto, [
                'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
                'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N'
            ]);
        }

        $texto = strtolower($texto);
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';
        $texto = trim($texto, '-');
        $texto = substr($texto, 0, 60);

        return $texto !== '' ? $texto : 'mi-tienda';
    }

    private function slugDisponible(string $base, int $idNegocio): string
    {
        $slug = $this->slugBase($base);
        $candidato = $slug;
        $n = 2;

        while ((int)$this->conexion->getValue(
            'SELECT COUNT(*) FROM tienda_configuracion WHERE slug = ? AND id_negocio <> ?',
            [$candidato, $idNegocio]
        ) > 0) {
            $sufijo = '-' . $n;
            $candidato = substr($slug, 0, max(1, 60 - strlen($sufijo))) . $sufijo;
            $n++;
        }

        return $candidato;
    }

    private function normalizarTelefono(string $telefono): string
    {
        $telefono = trim($telefono);
        $prefijo = str_starts_with($telefono, '+') ? '+' : '';
        $digitos = preg_replace('/\D+/', '', $telefono) ?? '';
        return $digitos !== '' ? $prefijo . substr($digitos, 0, 18) : '';
    }

    private function normalizarUrlSocial(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? substr($url, 0, 180) : '';
    }

    public function obtenerConfiguracion(): array
    {
        $empresa = $this->empresaActiva();
        if (!$empresa) {
            return [];
        }

        $config = $this->conexion->getData(
            'SELECT * FROM tienda_configuracion WHERE id_negocio = ? LIMIT 1',
            [(int)$empresa['id_negocio']]
        );

        return is_array($config) ? array_merge($empresa, $config) : $empresa;
    }

    public function guardarConfiguracion(array $data): array
    {
        $empresa = $this->empresaActiva();
        if (!$empresa) {
            throw new RuntimeException('No existe una empresa activa.');
        }

        $idNegocio = (int)$empresa['id_negocio'];
        $slug = $this->slugBase((string)($data['slug'] ?? ''));
        if (strlen($slug) < 3 || strlen($slug) > 60) {
            throw new InvalidArgumentException('La URL pública debe tener entre 3 y 60 caracteres.');
        }

        $slug = $this->slugDisponible($slug, $idNegocio);
        $color = strtoupper(trim((string)($data['color_primario'] ?? '#00A46A')));
        if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
            $color = '#00A46A';
        }

        $titulo = trim((string)($data['titulo'] ?? ''));
        $subtitulo = trim((string)($data['subtitulo'] ?? ''));
        $descripcion = trim((string)($data['descripcion'] ?? ''));
        $whatsapp = $this->normalizarTelefono((string)($data['whatsapp'] ?? ''));
        $instagram = $this->normalizarUrlSocial((string)($data['instagram'] ?? ''));
        $facebook = $this->normalizarUrlSocial((string)($data['facebook'] ?? ''));

        $sql = "UPDATE tienda_configuracion
                SET slug = ?, activo = ?, titulo = ?, subtitulo = ?, descripcion = ?,
                    whatsapp = ?, instagram = ?, facebook = ?, color_primario = ?,
                    mostrar_stock = ?, mostrar_codigo = ?, mostrar_descripcion = ?,
                    mostrar_sin_stock = ?, mostrar_precios = ?, boton_whatsapp = ?
                WHERE id_negocio = ?";

        $this->conexion->setData($sql, [
            $slug,
            !empty($data['activo']) ? 1 : 0,
            $titulo !== '' ? substr($titulo, 0, 120) : null,
            $subtitulo !== '' ? substr($subtitulo, 0, 255) : null,
            $descripcion !== '' ? $descripcion : null,
            $whatsapp !== '' ? $whatsapp : null,
            $instagram !== '' ? $instagram : null,
            $facebook !== '' ? $facebook : null,
            $color,
            !empty($data['mostrar_stock']) ? 1 : 0,
            !empty($data['mostrar_codigo']) ? 1 : 0,
            !empty($data['mostrar_descripcion']) ? 1 : 0,
            !empty($data['mostrar_sin_stock']) ? 1 : 0,
            !empty($data['mostrar_precios']) ? 1 : 0,
            !empty($data['boton_whatsapp']) ? 1 : 0,
            $idNegocio
        ]);

        return $this->obtenerConfiguracion();
    }

    public function listarProductosAdmin(): array
    {
        $sql = "SELECT
                    a.idarticulo,
                    a.codigo,
                    a.nombre,
                    a.descripcion,
                    a.imagen,
                    a.stock,
                    a.precio_venta,
                    a.condicion,
                    c.nombre AS categoria,
                    COALESCE(tp.publicado, 1) AS publicado,
                    COALESCE(tp.destacado, 0) AS destacado,
                    COALESCE(v.cantidad_variaciones, 0) AS cantidad_variaciones,
                    COALESCE(v.stock_variaciones, a.stock, 0) AS stock_mostrado,
                    CASE
                        WHEN COALESCE(v.cantidad_variaciones, 0) > 0
                            THEN COALESCE(v.precio_min, a.precio_venta, 0)
                        ELSE COALESCE(a.precio_venta, 0)
                    END AS precio_min,
                    CASE
                        WHEN COALESCE(v.cantidad_variaciones, 0) > 0
                            THEN COALESCE(v.precio_max, a.precio_venta, 0)
                        ELSE COALESCE(a.precio_venta, 0)
                    END AS precio_max
                FROM articulo a
                INNER JOIN categoria c ON c.idcategoria = a.idcategoria
                LEFT JOIN tienda_producto tp ON tp.idarticulo = a.idarticulo
                LEFT JOIN (
                    SELECT idarticulo,
                           COUNT(*) AS cantidad_variaciones,
                           SUM(COALESCE(stock, 0)) AS stock_variaciones,
                           MIN(CASE WHEN precio_venta > 0 THEN precio_venta END) AS precio_min,
                           MAX(CASE WHEN precio_venta > 0 THEN precio_venta END) AS precio_max
                    FROM articulo_variacion
                    WHERE estado = 1
                    GROUP BY idarticulo
                ) v ON v.idarticulo = a.idarticulo
                ORDER BY COALESCE(tp.destacado, 0) DESC, c.nombre ASC, a.nombre ASC";

        return $this->conexion->getDataAll($sql);
    }

    public function guardarEstadoProducto(int $idArticulo, bool $publicado, bool $destacado): void
    {
        if ($idArticulo <= 0) {
            throw new InvalidArgumentException('Producto no válido.');
        }

        $existe = (int)$this->conexion->getValue(
            'SELECT COUNT(*) FROM articulo WHERE idarticulo = ?',
            [$idArticulo]
        );
        if ($existe === 0) {
            throw new RuntimeException('El producto ya no existe.');
        }

        $this->conexion->setData(
            "INSERT INTO tienda_producto (idarticulo, publicado, destacado)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE publicado = VALUES(publicado), destacado = VALUES(destacado)",
            [$idArticulo, $publicado ? 1 : 0, $destacado ? 1 : 0]
        );
    }

    public function establecerPublicacionTodos(bool $publicado): void
    {
        $valor = $publicado ? 1 : 0;
        $this->conexion->setData(
            "INSERT INTO tienda_producto (idarticulo, publicado, destacado)
             SELECT idarticulo, ?, 0 FROM articulo
             ON DUPLICATE KEY UPDATE publicado = VALUES(publicado)",
            [$valor]
        );
    }

    public function obtenerCatalogoPublico(string $slug): ?array
    {
        $slug = $this->slugBase($slug);
        $config = $this->conexion->getData(
            "SELECT tc.*, dn.nombre AS empresa_nombre, dn.documento, dn.direccion,
                    dn.telefono AS empresa_telefono, dn.email AS empresa_email,
                    dn.pais, dn.ciudad, dn.moneda, dn.simbolo, dn.logo
             FROM tienda_configuracion tc
             INNER JOIN datos_negocio dn ON dn.id_negocio = tc.id_negocio
             WHERE tc.slug = ? AND tc.activo = 1 AND dn.condicion = 1
             LIMIT 1",
            [$slug]
        );

        if (!is_array($config)) {
            return null;
        }

        $sql = "SELECT
                    a.idarticulo,
                    a.codigo,
                    a.nombre,
                    a.descripcion,
                    a.imagen,
                    a.stock,
                    a.precio_venta,
                    c.idcategoria,
                    c.nombre AS categoria,
                    COALESCE(tp.destacado, 0) AS destacado,
                    COALESCE(v.cantidad_variaciones, 0) AS cantidad_variaciones,
                    COALESCE(v.stock_variaciones, a.stock, 0) AS stock_mostrado,
                    CASE
                        WHEN COALESCE(v.cantidad_variaciones, 0) > 0
                            THEN COALESCE(v.precio_min, a.precio_venta, 0)
                        ELSE COALESCE(a.precio_venta, 0)
                    END AS precio_min,
                    CASE
                        WHEN COALESCE(v.cantidad_variaciones, 0) > 0
                            THEN COALESCE(v.precio_max, a.precio_venta, 0)
                        ELSE COALESCE(a.precio_venta, 0)
                    END AS precio_max
                FROM articulo a
                INNER JOIN categoria c ON c.idcategoria = a.idcategoria AND c.condicion = 1
                LEFT JOIN tienda_producto tp ON tp.idarticulo = a.idarticulo
                LEFT JOIN (
                    SELECT idarticulo,
                           COUNT(*) AS cantidad_variaciones,
                           SUM(COALESCE(stock, 0)) AS stock_variaciones,
                           MIN(CASE WHEN precio_venta > 0 THEN precio_venta END) AS precio_min,
                           MAX(CASE WHEN precio_venta > 0 THEN precio_venta END) AS precio_max
                    FROM articulo_variacion
                    WHERE estado = 1
                    GROUP BY idarticulo
                ) v ON v.idarticulo = a.idarticulo
                WHERE a.condicion = 1
                  AND COALESCE(tp.publicado, 1) = 1
                ORDER BY COALESCE(tp.destacado, 0) DESC, c.nombre ASC, a.nombre ASC";

        $productos = $this->conexion->getDataAll($sql);

        if ((int)($config['mostrar_sin_stock'] ?? 1) !== 1) {
            $productos = array_values(array_filter(
                $productos,
                static fn(array $p): bool => (float)($p['stock_mostrado'] ?? 0) > 0
            ));
        }

        $ids = array_map(static fn(array $p): int => (int)$p['idarticulo'], $productos);
        $variacionesPorArticulo = [];

        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $variaciones = $this->conexion->getDataAll(
                "SELECT idvariacion, idarticulo, sku, stock, precio_venta, imagen, combinacion
                 FROM articulo_variacion
                 WHERE estado = 1 AND idarticulo IN ($placeholders)
                 ORDER BY idarticulo ASC, idvariacion ASC",
                $ids
            );

            foreach ($variaciones as $variacion) {
                $idArticulo = (int)$variacion['idarticulo'];
                $variacionesPorArticulo[$idArticulo][] = $variacion;
            }
        }

        $categorias = [];
        foreach ($productos as &$producto) {
            $idArticulo = (int)$producto['idarticulo'];
            $producto['variaciones'] = $variacionesPorArticulo[$idArticulo] ?? [];
            $categoria = trim((string)($producto['categoria'] ?? ''));
            if ($categoria !== '') {
                $categorias[$categoria] = $categoria;
            }
        }
        unset($producto);

        return [
            'config' => $config,
            'productos' => $productos,
            'categorias' => array_values($categorias)
        ];
    }
}
