-- TiquePOS - Página web / catálogo público
-- Ejecutar solo si se desea crear las tablas manualmente en una instalación existente.
-- El módulo también intenta crearlas automáticamente la primera vez que se abre "Mi página web".

CREATE TABLE IF NOT EXISTS `tienda_configuracion` (
  `idconfig` int NOT NULL AUTO_INCREMENT,
  `id_negocio` int NOT NULL,
  `slug` varchar(80) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 0,
  `titulo` varchar(120) DEFAULT NULL,
  `subtitulo` varchar(255) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `whatsapp` varchar(25) DEFAULT NULL,
  `instagram` varchar(180) DEFAULT NULL,
  `facebook` varchar(180) DEFAULT NULL,
  `color_primario` varchar(7) NOT NULL DEFAULT '#00A46A',
  `mostrar_stock` tinyint(1) NOT NULL DEFAULT 1,
  `mostrar_codigo` tinyint(1) NOT NULL DEFAULT 1,
  `mostrar_descripcion` tinyint(1) NOT NULL DEFAULT 1,
  `mostrar_sin_stock` tinyint(1) NOT NULL DEFAULT 1,
  `mostrar_precios` tinyint(1) NOT NULL DEFAULT 1,
  `boton_whatsapp` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`idconfig`),
  UNIQUE KEY `uq_tienda_config_negocio` (`id_negocio`),
  UNIQUE KEY `uq_tienda_config_slug` (`slug`),
  CONSTRAINT `fk_tienda_config_negocio` FOREIGN KEY (`id_negocio`) REFERENCES `datos_negocio` (`id_negocio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tienda_producto` (
  `idarticulo` int NOT NULL,
  `publicado` tinyint(1) NOT NULL DEFAULT 1,
  `destacado` tinyint(1) NOT NULL DEFAULT 0,
  `orden` int NOT NULL DEFAULT 0,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`idarticulo`),
  KEY `idx_tienda_producto_publicado` (`publicado`,`destacado`,`orden`),
  CONSTRAINT `fk_tienda_producto_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
