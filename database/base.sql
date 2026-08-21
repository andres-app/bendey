-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 19-08-2026 a las 20:34:05
-- Versión del servidor: 11.8.8-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Plantilla limpia TiquePOS - sin datos de cliente
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `almacen`
--

CREATE TABLE `almacen` (
  `idalmacen` int(11) NOT NULL,
  `idsucursal` int(11) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `ubicacion` varchar(100) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `articulo`
--

CREATE TABLE `articulo` (
  `idarticulo` int(11) NOT NULL,
  `idcategoria` int(11) NOT NULL,
  `idsubcategoria` int(11) DEFAULT NULL,
  `idmedida` int(11) NOT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `stock` int(11) DEFAULT NULL,
  `precio_compra` decimal(11,2) DEFAULT NULL,
  `precio_venta` decimal(11,2) DEFAULT NULL,
  `codigo_afectacion_igv` char(2) NOT NULL DEFAULT '10',
  `porcentaje_igv` decimal(5,2) NOT NULL DEFAULT 18.00,
  `unidad_medida_sunat` varchar(3) NOT NULL DEFAULT 'NIU',
  `codigo_producto_sunat` varchar(16) DEFAULT NULL,
  `descripcion` varchar(256) DEFAULT NULL,
  `imagen` varchar(50) DEFAULT NULL,
  `condicion` tinyint(4) DEFAULT 1,
  `idalmacen` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `articulo_variacion`
--

CREATE TABLE `articulo_variacion` (
  `idvariacion` int(11) NOT NULL,
  `idarticulo` int(11) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `precio_venta` decimal(10,2) DEFAULT NULL,
  `precio_compra` decimal(10,2) DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `estado` tinyint(4) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `combinacion` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `atributo`
--

CREATE TABLE `atributo` (
  `idatributo` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` tinyint(4) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `atributo_valor`
--

CREATE TABLE `atributo_valor` (
  `idvalor` int(11) NOT NULL,
  `idatributo` int(11) NOT NULL,
  `valor` varchar(100) NOT NULL,
  `estado` tinyint(4) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `caja_apertura`
--

CREATE TABLE `caja_apertura` (
  `idapertura` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `monto_apertura` decimal(10,2) NOT NULL,
  `idusuario` int(11) DEFAULT NULL,
  `idsucursal` int(11) DEFAULT NULL,
  `idcaja` int(11) DEFAULT NULL,
  `idusuario_apertura` int(11) DEFAULT NULL,
  `idusuario_responsable` int(11) DEFAULT NULL,
  `estado` enum('ABIERTA','CERRADA') DEFAULT 'ABIERTA',
  `created_at` datetime DEFAULT current_timestamp(),
  `fecha_cierre` datetime DEFAULT NULL,
  `idusuario_caja_abierta` int(11) GENERATED ALWAYS AS (case when `estado` = 'ABIERTA' then `idusuario` else NULL end) STORED,
  `idcaja_fisica_abierta` int(11) GENERATED ALWAYS AS (case when `estado` = 'ABIERTA' then `idcaja` else NULL end) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `caja_cierre`
--

CREATE TABLE `caja_cierre` (
  `idcierre` int(11) NOT NULL,
  `caja_apertura_id` int(11) NOT NULL,
  `usuario_cierre` int(11) NOT NULL,
  `fecha_cierre` datetime NOT NULL,
  `total_sistema` decimal(10,2) NOT NULL,
  `monto_contado` decimal(10,2) NOT NULL,
  `diferencia` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `caja_fisica`
--

CREATE TABLE `caja_fisica` (
  `idcaja` int(11) NOT NULL,
  `idsucursal` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `permite_efectivo` tinyint(1) NOT NULL DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categoria`
--

CREATE TABLE `categoria` (
  `idcategoria` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(256) DEFAULT NULL,
  `condicion` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categoria_compra`
--

CREATE TABLE `categoria_compra` (
  `idcategoria_compra` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cobranza`
--

CREATE TABLE `cobranza` (
  `idcobranza` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `idventa` int(11) NOT NULL,
  `idcliente` int(11) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `monto_total` decimal(11,2) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `observacion` varchar(255) DEFAULT NULL,
  `estado` enum('REGISTRADA','ANULADA') NOT NULL DEFAULT 'REGISTRADA',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cobranza_detalle`
--

CREATE TABLE `cobranza_detalle` (
  `idcobranza_detalle` int(11) NOT NULL,
  `idcobranza` int(11) NOT NULL,
  `idventa_cuota` int(11) NOT NULL,
  `monto_aplicado` decimal(11,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cobranza_pago`
--

CREATE TABLE `cobranza_pago` (
  `idcobranza_pago` int(11) NOT NULL,
  `idcobranza` int(11) NOT NULL,
  `idforma_pago` int(11) NOT NULL,
  `idcuenta_financiera` int(11) DEFAULT NULL,
  `idapertura` int(11) DEFAULT NULL,
  `monto` decimal(11,2) NOT NULL,
  `numero_operacion` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comp_pago`
--

CREATE TABLE `comp_pago` (
  `id_comp_pago` int(11) NOT NULL,
  `nombre` varchar(45) NOT NULL,
  `letra_serie` varchar(3) NOT NULL,
  `serie_comprobante` varchar(3) NOT NULL,
  `num_comprobante` varchar(8) NOT NULL,
  `condicion` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_caja`
--

CREATE TABLE `configuracion_caja` (
  `idsucursal` int(11) NOT NULL,
  `modo` enum('LEGACY','CAJA_UNICA','MULTICAJA') NOT NULL DEFAULT 'LEGACY',
  `modo_objetivo` enum('CAJA_UNICA','MULTICAJA') DEFAULT NULL,
  `idcaja_unica` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuenta_financiera`
--

CREATE TABLE `cuenta_financiera` (
  `idcuenta_financiera` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo` enum('CAJA','BANCO','BILLETERA','PASARELA','OTRO') NOT NULL,
  `entidad` varchar(100) DEFAULT NULL,
  `moneda` char(3) NOT NULL DEFAULT 'PEN',
  `referencia` varchar(150) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `datos_negocio`
--

CREATE TABLE `datos_negocio` (
  `id_negocio` int(11) NOT NULL,
  `nombre` varchar(80) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `ndocumento` varchar(20) NOT NULL,
  `documento` varchar(15) DEFAULT NULL,
  `direccion` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `telefono` int(20) NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `pais` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `ciudad` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `nombre_impuesto` varchar(10) NOT NULL,
  `monto_impuesto` float(4,2) NOT NULL,
  `moneda` varchar(10) NOT NULL,
  `simbolo` varchar(10) NOT NULL,
  `condicion` tinyint(4) NOT NULL DEFAULT 1,
  `token_reniec_sunat` varchar(255) DEFAULT NULL,
  `apisunat_persona_id` varchar(100) DEFAULT NULL,
  `apisunat_persona_token` text DEFAULT NULL,
  `apisunat_production` tinyint(1) NOT NULL DEFAULT 1,
  `venta_tipo_comprobante_predeterminado` varchar(80) DEFAULT NULL,
  `venta_tipo_pago_predeterminado` varchar(50) DEFAULT NULL,
  `venta_idforma_pago_predeterminada` int(11) DEFAULT NULL,
  `venta_modo_envio_predeterminado` varchar(20) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `tipo_operacion_sunat_predeterminado` char(4) NOT NULL DEFAULT '0101',
  `codigo_afectacion_igv_predeterminado` char(2) NOT NULL DEFAULT '10',
  `porcentaje_igv_predeterminado` decimal(5,2) NOT NULL DEFAULT 18.00,
  `unidad_medida_sunat_predeterminada` varchar(3) NOT NULL DEFAULT 'NIU',
  `permitir_cambio_afectacion_venta` tinyint(1) NOT NULL DEFAULT 0,
  `precios_incluyen_impuesto` tinyint(1) NOT NULL DEFAULT 1,
  `venta_campos_visibles` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_ingreso`
--

CREATE TABLE `detalle_ingreso` (
  `iddetalle_ingreso` int(11) NOT NULL,
  `idingreso` int(11) NOT NULL,
  `tipo_detalle` enum('INVENTARIO','NO_INVENTARIO') NOT NULL DEFAULT 'INVENTARIO',
  `idarticulo` int(11) DEFAULT NULL,
  `descripcion` varchar(250) DEFAULT NULL,
  `idcategoria_compra` int(11) DEFAULT NULL,
  `idalmacen` int(11) DEFAULT NULL,
  `idmedida` int(11) DEFAULT NULL,
  `afecta_stock` tinyint(1) NOT NULL DEFAULT 1,
  `cantidad` decimal(12,3) NOT NULL,
  `stock_venta` int(11) NOT NULL DEFAULT 0,
  `precio_compra` decimal(11,2) NOT NULL,
  `precio_venta` decimal(11,2) DEFAULT NULL,
  `importe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` tinyint(4) NOT NULL DEFAULT 1,
  `stock_estado` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_venta`
--

CREATE TABLE `detalle_venta` (
  `iddetalle_venta` int(11) NOT NULL,
  `idventa` int(11) NOT NULL,
  `idarticulo` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_compra` decimal(11,2) NOT NULL,
  `precio_venta` decimal(11,2) NOT NULL,
  `descuento` decimal(11,2) NOT NULL,
  `codigo_afectacion_igv` char(2) NOT NULL DEFAULT '10',
  `porcentaje_igv` decimal(5,2) NOT NULL DEFAULT 18.00,
  `unidad_medida_sunat` varchar(3) NOT NULL DEFAULT 'NIU',
  `codigo_producto_sunat` varchar(16) DEFAULT NULL,
  `codigo_tributo` char(4) NOT NULL DEFAULT '1000',
  `nombre_tributo` varchar(20) NOT NULL DEFAULT 'IGV',
  `tipo_tributo` varchar(10) NOT NULL DEFAULT 'VAT',
  `valor_unitario_sin_igv` decimal(12,6) NOT NULL DEFAULT 0.000000,
  `base_imponible` decimal(12,2) NOT NULL DEFAULT 0.00,
  `monto_igv` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_linea` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `forma_pago`
--

CREATE TABLE `forma_pago` (
  `idforma_pago` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `es_efectivo` tinyint(1) DEFAULT 0,
  `es_combinado` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `condicion` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `forma_pago_destino`
--

CREATE TABLE `forma_pago_destino` (
  `idforma_pago` int(11) NOT NULL,
  `idcuenta_financiera` int(11) DEFAULT NULL,
  `requiere_caja_abierta` tinyint(1) NOT NULL DEFAULT 0,
  `requiere_operacion` tinyint(1) NOT NULL DEFAULT 0,
  `permite_cobranza` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ingreso`
--

CREATE TABLE `ingreso` (
  `idingreso` int(11) NOT NULL,
  `f_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `idproveedor` int(11) NOT NULL,
  `idusuario` int(11) DEFAULT NULL,
  `idsucursal` int(11) DEFAULT NULL,
  `tipo_comprobante` varchar(20) NOT NULL,
  `serie_comprobante` varchar(7) DEFAULT NULL,
  `num_comprobante` varchar(10) NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `impuesto` decimal(4,2) NOT NULL,
  `total_compra` decimal(11,2) NOT NULL,
  `tipo_compra` enum('INVENTARIO','NO_INVENTARIO','MIXTA') NOT NULL DEFAULT 'INVENTARIO',
  `observacion` varchar(255) DEFAULT NULL,
  `estado` varchar(20) NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `kardex`
--

CREATE TABLE `kardex` (
  `id` int(11) NOT NULL,
  `iddetalle` int(11) NOT NULL,
  `idarticulo` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `detalle` varchar(64) NOT NULL,
  `cantidadi` int(11) NOT NULL,
  `costoui` decimal(11,2) NOT NULL,
  `totali` decimal(11,2) NOT NULL,
  `cantidads` int(11) NOT NULL,
  `costous` decimal(11,2) NOT NULL,
  `totals` decimal(11,2) NOT NULL,
  `cantidadex` int(11) NOT NULL,
  `costouex` decimal(11,2) NOT NULL,
  `totalex` decimal(11,2) NOT NULL,
  `tipo` varchar(45) NOT NULL,
  `estado` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `medida`
--

CREATE TABLE `medida` (
  `idmedida` int(12) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `condicion` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimiento_financiero`
--

CREATE TABLE `movimiento_financiero` (
  `idmovimiento` int(11) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `tipo` enum('INGRESO','EGRESO') NOT NULL,
  `origen` enum('VENTA','COBRANZA','APERTURA','CIERRE','AJUSTE','NOTA_CREDITO','OTRO') NOT NULL,
  `idreferencia` int(11) DEFAULT NULL,
  `idcobranza_pago` int(11) DEFAULT NULL,
  `idforma_pago` int(11) NOT NULL,
  `idcuenta_financiera` int(11) DEFAULT NULL,
  `idapertura` int(11) DEFAULT NULL,
  `monto` decimal(11,2) NOT NULL,
  `concepto` varchar(255) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `estado` enum('ACTIVO','ANULADO') NOT NULL DEFAULT 'ACTIVO',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `nota_credito`
--

CREATE TABLE `nota_credito` (
  `idnota_credito` int(11) NOT NULL,
  `idventa` int(11) NOT NULL,
  `idcliente` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `idsucursal` int(11) DEFAULT NULL,
  `idcaja` int(11) DEFAULT NULL,
  `idapertura` int(11) DEFAULT NULL,
  `tipo_documento_modificado` char(2) NOT NULL,
  `serie_documento_modificado` varchar(7) NOT NULL,
  `numero_documento_modificado` varchar(10) NOT NULL,
  `document_id_origen` varchar(100) DEFAULT NULL,
  `tipo_comprobante` varchar(45) NOT NULL DEFAULT 'Nota de Crédito Electrónica',
  `serie_comprobante` varchar(7) NOT NULL,
  `num_comprobante` varchar(10) NOT NULL,
  `codigo_motivo` char(2) NOT NULL,
  `sustento` varchar(250) NOT NULL,
  `tipo_afectacion` enum('TOTAL','PARCIAL','AJUSTE') NOT NULL DEFAULT 'TOTAL',
  `tipo_operacion_sunat` char(4) NOT NULL DEFAULT '0101',
  `fecha_hora` datetime NOT NULL,
  `moneda` char(3) NOT NULL DEFAULT 'PEN',
  `impuesto` decimal(5,2) NOT NULL DEFAULT 18.00,
  `valor_venta` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_gravado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_exonerado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_inafecto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_exportacion` decimal(12,2) NOT NULL DEFAULT 0.00,
  `descuento_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `igv` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_nota` decimal(12,2) NOT NULL DEFAULT 0.00,
  `afecta_stock` tinyint(1) NOT NULL DEFAULT 0,
  `genera_devolucion_dinero` tinyint(1) NOT NULL DEFAULT 0,
  `afecta_cuentas_cobrar` tinyint(1) NOT NULL DEFAULT 0,
  `stock_aplicado` tinyint(1) NOT NULL DEFAULT 0,
  `finanzas_aplicadas` tinyint(1) NOT NULL DEFAULT 0,
  `cuotas_aplicadas` tinyint(1) NOT NULL DEFAULT 0,
  `modo_envio` enum('INMEDIATO','MANUAL') NOT NULL DEFAULT 'INMEDIATO',
  `estado` enum('BORRADOR','REGISTRADA','ANULADA') NOT NULL DEFAULT 'REGISTRADA',
  `cliente_tipo_documento` varchar(20) DEFAULT NULL,
  `cliente_num_documento` varchar(20) DEFAULT NULL,
  `cliente_nombre` varchar(180) NOT NULL,
  `cliente_direccion` varchar(250) DEFAULT NULL,
  `cliente_email` varchar(150) DEFAULT NULL,
  `observacion` varchar(500) DEFAULT NULL,
  `fecha_aplicacion_stock` datetime DEFAULT NULL,
  `fecha_aplicacion_finanzas` datetime DEFAULT NULL,
  `fecha_aplicacion_cuotas` datetime DEFAULT NULL,
  `idusuario_anulacion` int(11) DEFAULT NULL,
  `fecha_anulacion` datetime DEFAULT NULL,
  `motivo_anulacion` varchar(250) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `nota_credito_cuota_ajuste`
--

CREATE TABLE `nota_credito_cuota_ajuste` (
  `idnota_credito_cuota_ajuste` int(11) NOT NULL,
  `idnota_credito` int(11) NOT NULL,
  `idventa_cuota` int(11) NOT NULL,
  `monto_antes` decimal(12,2) NOT NULL,
  `monto_pagado_antes` decimal(12,2) NOT NULL,
  `monto_reducido` decimal(12,2) NOT NULL,
  `monto_despues` decimal(12,2) NOT NULL,
  `monto_pagado_despues` decimal(12,2) NOT NULL,
  `estado_antes` varchar(20) NOT NULL,
  `estado_despues` varchar(20) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `nota_credito_detalle`
--

CREATE TABLE `nota_credito_detalle` (
  `iddetalle_nota_credito` int(11) NOT NULL,
  `idnota_credito` int(11) NOT NULL,
  `iddetalle_venta` int(11) NOT NULL,
  `idarticulo` int(11) NOT NULL,
  `codigo_articulo` varchar(50) DEFAULT NULL,
  `descripcion_articulo` varchar(250) NOT NULL,
  `unidad_codigo` varchar(3) NOT NULL DEFAULT 'NIU',
  `codigo_afectacion_igv` char(2) NOT NULL DEFAULT '10',
  `porcentaje_igv` decimal(5,2) NOT NULL DEFAULT 18.00,
  `codigo_producto_sunat` varchar(16) DEFAULT NULL,
  `codigo_tributo` char(4) NOT NULL DEFAULT '1000',
  `nombre_tributo` varchar(20) NOT NULL DEFAULT 'IGV',
  `tipo_tributo` varchar(10) NOT NULL DEFAULT 'VAT',
  `cantidad_original` decimal(12,3) NOT NULL,
  `cantidad_nota` decimal(12,3) NOT NULL,
  `costo_unitario` decimal(12,6) NOT NULL DEFAULT 0.000000,
  `precio_unitario_con_igv` decimal(12,6) NOT NULL,
  `valor_unitario_sin_igv` decimal(12,6) NOT NULL,
  `descuento_linea` decimal(12,2) NOT NULL DEFAULT 0.00,
  `valor_venta` decimal(12,2) NOT NULL DEFAULT 0.00,
  `igv` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_linea` decimal(12,2) NOT NULL DEFAULT 0.00,
  `devuelve_stock` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `nota_credito_motivo`
--

CREATE TABLE `nota_credito_motivo` (
  `codigo` char(2) NOT NULL,
  `descripcion` varchar(180) NOT NULL,
  `permite_factura` tinyint(1) NOT NULL DEFAULT 1,
  `permite_boleta` tinyint(1) NOT NULL DEFAULT 1,
  `afecta_stock_default` tinyint(1) NOT NULL DEFAULT 0,
  `permite_parcial` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `nota_credito_pago`
--

CREATE TABLE `nota_credito_pago` (
  `idnota_credito_pago` int(11) NOT NULL,
  `idnota_credito` int(11) NOT NULL,
  `idforma_pago` int(11) NOT NULL,
  `idcuenta_financiera` int(11) DEFAULT NULL,
  `idmovimiento` int(11) DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `nota_credito_sunat`
--

CREATE TABLE `nota_credito_sunat` (
  `idnota_credito_sunat` int(11) NOT NULL,
  `idnota_credito` int(11) NOT NULL,
  `document_id` varchar(100) DEFAULT NULL,
  `file_name` varchar(80) DEFAULT NULL,
  `tipo_documento_sunat` char(2) NOT NULL DEFAULT '07',
  `production` tinyint(1) NOT NULL DEFAULT 1,
  `xml` text DEFAULT NULL,
  `xml_local` varchar(255) DEFAULT NULL,
  `cdr` text DEFAULT NULL,
  `cdr_local` varchar(255) DEFAULT NULL,
  `pdf` text DEFAULT NULL,
  `estado_sunat` varchar(30) NOT NULL DEFAULT 'NO_ENVIADO',
  `mensaje_sunat` text DEFAULT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `faults` longtext DEFAULT NULL,
  `notes` longtext DEFAULT NULL,
  `request_json` longtext DEFAULT NULL,
  `response_json` longtext DEFAULT NULL,
  `intentos_consulta` int(11) NOT NULL DEFAULT 0,
  `fecha_ultima_consulta` datetime DEFAULT NULL,
  `fecha_descarga_archivos` datetime DEFAULT NULL,
  `hash` varchar(255) DEFAULT NULL,
  `fecha_envio` datetime DEFAULT NULL,
  `fecha_respuesta` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permiso`
--

CREATE TABLE `permiso` (
  `idpermiso` int(11) NOT NULL,
  `nombre` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `persona`
--

CREATE TABLE `persona` (
  `idpersona` int(11) NOT NULL,
  `tipo_persona` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo_documento` varchar(20) DEFAULT NULL,
  `num_documento` varchar(20) DEFAULT NULL,
  `direccion` varchar(70) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `resumen_diario_boleta`
--

CREATE TABLE `resumen_diario_boleta` (
  `idresumen` bigint(20) UNSIGNED NOT NULL,
  `id_negocio` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `fecha_documentos` date NOT NULL,
  `correlativo` int(10) UNSIGNED NOT NULL,
  `codigo_resumen` varchar(50) NOT NULL,
  `file_name` varchar(120) DEFAULT NULL,
  `document_id` varchar(100) DEFAULT NULL,
  `ticket` varchar(180) DEFAULT NULL,
  `production` tinyint(1) NOT NULL DEFAULT 1,
  `cantidad_documentos` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_gravado` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_exonerado` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_inafecto` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_exportacion` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_igv` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_documentos` decimal(14,2) NOT NULL DEFAULT 0.00,
  `estado_sunat` varchar(30) NOT NULL DEFAULT 'NO_ENVIADO',
  `mensaje_sunat` text DEFAULT NULL,
  `xml` text DEFAULT NULL,
  `xml_local` varchar(255) DEFAULT NULL,
  `cdr` text DEFAULT NULL,
  `cdr_local` varchar(255) DEFAULT NULL,
  `pdf` text DEFAULT NULL,
  `faults` longtext DEFAULT NULL,
  `notes` longtext DEFAULT NULL,
  `request_json` longtext DEFAULT NULL,
  `response_json` longtext DEFAULT NULL,
  `intentos_envio` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `intentos_consulta` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `fecha_generacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_envio` datetime DEFAULT NULL,
  `fecha_ultima_consulta` datetime DEFAULT NULL,
  `fecha_respuesta` datetime DEFAULT NULL,
  `fecha_descarga_archivos` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `resumen_diario_boleta_detalle`
--

CREATE TABLE `resumen_diario_boleta_detalle` (
  `iddetalle_resumen` bigint(20) UNSIGNED NOT NULL,
  `idresumen` bigint(20) UNSIGNED NOT NULL,
  `idventa` int(11) NOT NULL,
  `codigo_condicion` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `tipo_documento_sunat` char(2) NOT NULL DEFAULT '03',
  `serie_comprobante` varchar(7) NOT NULL,
  `num_comprobante` varchar(10) NOT NULL,
  `total_gravado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_exonerado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_inafecto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_exportacion` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_igv` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_venta` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `subcategoria`
--

CREATE TABLE `subcategoria` (
  `idsubcategoria` int(11) NOT NULL,
  `idcategoria` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sucursal`
--

CREATE TABLE `sucursal` (
  `idsucursal` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `direccion` varchar(255) NOT NULL,
  `codigo_establecimiento_sunat` varchar(10) DEFAULT NULL,
  `principal` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `hereda_configuracion_tributaria` tinyint(1) NOT NULL DEFAULT 1,
  `tipo_operacion_sunat` char(4) DEFAULT NULL,
  `codigo_afectacion_igv_predeterminada` char(2) DEFAULT NULL,
  `porcentaje_igv_predeterminado` decimal(5,2) DEFAULT NULL,
  `unidad_medida_sunat_predeterminada` varchar(3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sunat_catalogo_07_afectacion_igv`
--

CREATE TABLE `sunat_catalogo_07_afectacion_igv` (
  `codigo` char(2) NOT NULL,
  `descripcion` varchar(120) NOT NULL,
  `porcentaje_predeterminado` decimal(5,2) NOT NULL DEFAULT 0.00,
  `codigo_tributo` char(4) NOT NULL,
  `nombre_tributo` varchar(20) NOT NULL,
  `tipo_tributo` varchar(10) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sunat_catalogo_51_tipo_operacion`
--

CREATE TABLE `sunat_catalogo_51_tipo_operacion` (
  `codigo` char(4) NOT NULL,
  `descripcion` varchar(220) NOT NULL,
  `comprobantes` varchar(80) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` smallint(5) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipo_pago`
--

CREATE TABLE `tipo_pago` (
  `idtipopago` int(11) NOT NULL,
  `nombre` varchar(45) NOT NULL,
  `descripcion` varchar(45) DEFAULT NULL,
  `estado` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `idusuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo_documento` varchar(20) NOT NULL,
  `num_documento` varchar(20) NOT NULL,
  `direccion` varchar(70) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `cargo` varchar(20) DEFAULT NULL,
  `login` varchar(20) NOT NULL,
  `clave` varchar(64) NOT NULL,
  `imagen` varchar(50) NOT NULL,
  `condicion` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_almacen`
--

CREATE TABLE `usuario_almacen` (
  `idusuario_almacen` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `idalmacen` int(11) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_caja`
--

CREATE TABLE `usuario_caja` (
  `idusuario_caja` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `idcaja` int(11) NOT NULL,
  `rol` varchar(30) NOT NULL,
  `puede_abrir` tinyint(1) NOT NULL DEFAULT 0,
  `puede_cerrar` tinyint(1) NOT NULL DEFAULT 0,
  `puede_operar` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_permiso`
--

CREATE TABLE `usuario_permiso` (
  `idusuario_permiso` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `idpermiso` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_sucursal`
--

CREATE TABLE `usuario_sucursal` (
  `idusuario_sucursal` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `idsucursal` int(11) NOT NULL,
  `puede_vender` tinyint(1) NOT NULL DEFAULT 0,
  `puede_cobrar` tinyint(1) NOT NULL DEFAULT 0,
  `puede_abrir_caja` tinyint(1) NOT NULL DEFAULT 0,
  `puede_cerrar_caja` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `variacion_atributo_valor`
--

CREATE TABLE `variacion_atributo_valor` (
  `idvariacion` int(11) NOT NULL,
  `idvalor` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `venta`
--

CREATE TABLE `venta` (
  `idventa` int(11) NOT NULL,
  `f_registro` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `idcliente` int(11) NOT NULL,
  `idusuario` int(11) NOT NULL,
  `idsucursal` int(11) DEFAULT NULL,
  `idcaja` int(11) DEFAULT NULL,
  `idapertura` int(11) DEFAULT NULL,
  `tipo_operacion_sunat` char(4) NOT NULL DEFAULT '0101',
  `tipo_comprobante` varchar(45) NOT NULL,
  `serie_comprobante` varchar(7) DEFAULT NULL,
  `num_comprobante` varchar(10) NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `impuesto` decimal(4,2) DEFAULT NULL,
  `moneda_codigo` char(3) NOT NULL DEFAULT 'PEN',
  `tipo_cambio_sunat` decimal(12,6) NOT NULL DEFAULT 1.000000,
  `guia_remision` varchar(50) DEFAULT NULL,
  `direccion_cliente` varchar(255) DEFAULT NULL,
  `celular_cliente` varchar(30) DEFAULT NULL,
  `total_gravado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_exonerado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_inafecto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_exportacion` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_igv` decimal(12,2) NOT NULL DEFAULT 0.00,
  `precios_incluyen_impuesto` tinyint(1) NOT NULL DEFAULT 1,
  `descuento_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descuento_porcentaje` decimal(5,2) NOT NULL DEFAULT 0.00,
  `total_venta` decimal(11,2) DEFAULT NULL,
  `tipo_pago` varchar(45) NOT NULL,
  `num_transac` varchar(45) DEFAULT NULL,
  `estado` varchar(20) DEFAULT NULL,
  `idforma_pago` int(11) DEFAULT NULL,
  `modo_envio_sunat` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `venta_cuota`
--

CREATE TABLE `venta_cuota` (
  `idventa_cuota` int(11) NOT NULL,
  `idventa` int(11) NOT NULL,
  `numero_cuota` smallint(5) UNSIGNED NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `monto` decimal(11,2) NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `monto_pagado` decimal(11,2) NOT NULL DEFAULT 0.00,
  `fecha_pago` datetime DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'PENDIENTE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `venta_pago`
--

CREATE TABLE `venta_pago` (
  `idventa_pago` int(11) NOT NULL,
  `idventa` int(11) NOT NULL,
  `idforma_pago` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `venta_sunat`
--

CREATE TABLE `venta_sunat` (
  `idventa_sunat` int(11) NOT NULL,
  `idventa` int(11) NOT NULL,
  `document_id` varchar(100) DEFAULT NULL,
  `file_name` varchar(80) DEFAULT NULL,
  `tipo_documento_sunat` char(2) DEFAULT NULL,
  `production` tinyint(1) NOT NULL DEFAULT 1,
  `xml` text DEFAULT NULL,
  `xml_local` varchar(255) DEFAULT NULL,
  `cdr` text DEFAULT NULL,
  `cdr_local` varchar(255) DEFAULT NULL,
  `pdf` text DEFAULT NULL,
  `estado_sunat` varchar(30) NOT NULL DEFAULT 'PENDIENTE',
  `mensaje_sunat` text DEFAULT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `faults` longtext DEFAULT NULL,
  `notes` longtext DEFAULT NULL,
  `request_json` longtext DEFAULT NULL,
  `response_json` longtext DEFAULT NULL,
  `intentos_consulta` int(11) NOT NULL DEFAULT 0,
  `fecha_ultima_consulta` datetime DEFAULT NULL,
  `fecha_descarga_archivos` datetime DEFAULT NULL,
  `hash` varchar(255) DEFAULT NULL,
  `fecha_envio` datetime DEFAULT NULL,
  `fecha_respuesta` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `almacen`
--
ALTER TABLE `almacen`
  ADD PRIMARY KEY (`idalmacen`),
  ADD KEY `idx_almacen_sucursal` (`idsucursal`);

--
-- Indices de la tabla `articulo`
--
ALTER TABLE `articulo`
  ADD PRIMARY KEY (`idarticulo`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `fk_articulo_categoria_idx` (`idcategoria`),
  ADD KEY `idmedida` (`idmedida`),
  ADD KEY `fk_articulo_almacen` (`idalmacen`),
  ADD KEY `idx_almacen` (`idalmacen`);

--
-- Indices de la tabla `articulo_variacion`
--
ALTER TABLE `articulo_variacion`
  ADD PRIMARY KEY (`idvariacion`),
  ADD KEY `idarticulo` (`idarticulo`);

--
-- Indices de la tabla `atributo`
--
ALTER TABLE `atributo`
  ADD PRIMARY KEY (`idatributo`);

--
-- Indices de la tabla `atributo_valor`
--
ALTER TABLE `atributo_valor`
  ADD PRIMARY KEY (`idvalor`),
  ADD KEY `idatributo` (`idatributo`);

--
-- Indices de la tabla `caja_apertura`
--
ALTER TABLE `caja_apertura`
  ADD PRIMARY KEY (`idapertura`),
  ADD UNIQUE KEY `uk_usuario_caja_abierta` (`idusuario_caja_abierta`),
  ADD UNIQUE KEY `uk_caja_fisica_abierta` (`idcaja_fisica_abierta`),
  ADD KEY `idx_caja_apertura_sucursal` (`idsucursal`),
  ADD KEY `idx_caja_apertura_caja` (`idcaja`),
  ADD KEY `idx_caja_apertura_usuario_apertura` (`idusuario_apertura`),
  ADD KEY `idx_caja_apertura_usuario_responsable` (`idusuario_responsable`);

--
-- Indices de la tabla `caja_cierre`
--
ALTER TABLE `caja_cierre`
  ADD PRIMARY KEY (`idcierre`),
  ADD KEY `caja_apertura_id` (`caja_apertura_id`);

--
-- Indices de la tabla `caja_fisica`
--
ALTER TABLE `caja_fisica`
  ADD PRIMARY KEY (`idcaja`),
  ADD UNIQUE KEY `uk_caja_sucursal_codigo` (`idsucursal`,`codigo`),
  ADD KEY `idx_caja_sucursal` (`idsucursal`);

--
-- Indices de la tabla `categoria`
--
ALTER TABLE `categoria`
  ADD PRIMARY KEY (`idcategoria`),
  ADD UNIQUE KEY `nombre_UNIQUE` (`nombre`);

--
-- Indices de la tabla `categoria_compra`
--
ALTER TABLE `categoria_compra`
  ADD PRIMARY KEY (`idcategoria_compra`),
  ADD UNIQUE KEY `uq_categoria_compra_nombre` (`nombre`);

--
-- Indices de la tabla `cobranza`
--
ALTER TABLE `cobranza`
  ADD PRIMARY KEY (`idcobranza`),
  ADD UNIQUE KEY `uk_cobranza_codigo` (`codigo`),
  ADD KEY `idx_cobranza_venta` (`idventa`),
  ADD KEY `idx_cobranza_cliente` (`idcliente`),
  ADD KEY `idx_cobranza_usuario` (`idusuario`),
  ADD KEY `idx_cobranza_fecha` (`fecha_hora`),
  ADD KEY `idx_cobranza_estado` (`estado`);

--
-- Indices de la tabla `cobranza_detalle`
--
ALTER TABLE `cobranza_detalle`
  ADD PRIMARY KEY (`idcobranza_detalle`),
  ADD UNIQUE KEY `uk_cobranza_cuota` (`idcobranza`,`idventa_cuota`),
  ADD KEY `idx_cobranza_detalle_cuota` (`idventa_cuota`);

--
-- Indices de la tabla `cobranza_pago`
--
ALTER TABLE `cobranza_pago`
  ADD PRIMARY KEY (`idcobranza_pago`),
  ADD KEY `idx_cobranza_pago_cobranza` (`idcobranza`),
  ADD KEY `idx_cobranza_pago_forma` (`idforma_pago`),
  ADD KEY `idx_cobranza_pago_cuenta` (`idcuenta_financiera`),
  ADD KEY `idx_cobranza_pago_apertura` (`idapertura`);

--
-- Indices de la tabla `comp_pago`
--
ALTER TABLE `comp_pago`
  ADD PRIMARY KEY (`id_comp_pago`);

--
-- Indices de la tabla `configuracion_caja`
--
ALTER TABLE `configuracion_caja`
  ADD PRIMARY KEY (`idsucursal`),
  ADD KEY `idx_configuracion_idcaja_unica` (`idcaja_unica`);

--
-- Indices de la tabla `cuenta_financiera`
--
ALTER TABLE `cuenta_financiera`
  ADD PRIMARY KEY (`idcuenta_financiera`),
  ADD UNIQUE KEY `uk_cuenta_financiera_nombre` (`nombre`),
  ADD KEY `idx_cuenta_financiera_tipo` (`tipo`),
  ADD KEY `idx_cuenta_financiera_activo` (`activo`);

--
-- Indices de la tabla `datos_negocio`
--
ALTER TABLE `datos_negocio`
  ADD PRIMARY KEY (`id_negocio`);

--
-- Indices de la tabla `detalle_ingreso`
--
ALTER TABLE `detalle_ingreso`
  ADD PRIMARY KEY (`iddetalle_ingreso`),
  ADD KEY `fk_detalle_ingreso_idx` (`idingreso`),
  ADD KEY `fk_detalle_articulo_idx` (`idarticulo`),
  ADD KEY `idx_detalle_tipo` (`tipo_detalle`),
  ADD KEY `idx_detalle_categoria_compra` (`idcategoria_compra`),
  ADD KEY `idx_detalle_almacen` (`idalmacen`),
  ADD KEY `idx_detalle_medida` (`idmedida`),
  ADD KEY `idx_detalle_afecta_stock` (`afecta_stock`);

--
-- Indices de la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  ADD PRIMARY KEY (`iddetalle_venta`),
  ADD KEY `fk_detalle_venta_venta_idx` (`idventa`),
  ADD KEY `fk_detalle_venta_articulo_idx` (`idarticulo`),
  ADD KEY `idx_venta` (`idventa`);

--
-- Indices de la tabla `forma_pago`
--
ALTER TABLE `forma_pago`
  ADD PRIMARY KEY (`idforma_pago`);

--
-- Indices de la tabla `forma_pago_destino`
--
ALTER TABLE `forma_pago_destino`
  ADD PRIMARY KEY (`idforma_pago`),
  ADD KEY `idx_forma_destino_cuenta` (`idcuenta_financiera`);

--
-- Indices de la tabla `ingreso`
--
ALTER TABLE `ingreso`
  ADD PRIMARY KEY (`idingreso`),
  ADD KEY `fk_ingreso_persona_idx` (`idproveedor`),
  ADD KEY `fk_ingreso_usuario_idx` (`idusuario`),
  ADD KEY `idx_ingreso_sucursal` (`idsucursal`),
  ADD KEY `idx_ingreso_tipo_compra` (`tipo_compra`);

--
-- Indices de la tabla `kardex`
--
ALTER TABLE `kardex`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `medida`
--
ALTER TABLE `medida`
  ADD PRIMARY KEY (`idmedida`);

--
-- Indices de la tabla `movimiento_financiero`
--
ALTER TABLE `movimiento_financiero`
  ADD PRIMARY KEY (`idmovimiento`),
  ADD KEY `idx_movimiento_fecha` (`fecha_hora`),
  ADD KEY `idx_movimiento_tipo` (`tipo`),
  ADD KEY `idx_movimiento_origen` (`origen`),
  ADD KEY `idx_movimiento_referencia` (`idreferencia`),
  ADD KEY `idx_movimiento_cobranza_pago` (`idcobranza_pago`),
  ADD KEY `idx_movimiento_forma_pago` (`idforma_pago`),
  ADD KEY `idx_movimiento_cuenta` (`idcuenta_financiera`),
  ADD KEY `idx_movimiento_apertura` (`idapertura`),
  ADD KEY `idx_movimiento_usuario` (`idusuario`),
  ADD KEY `idx_movimiento_estado` (`estado`);

--
-- Indices de la tabla `nota_credito`
--
ALTER TABLE `nota_credito`
  ADD PRIMARY KEY (`idnota_credito`),
  ADD UNIQUE KEY `uk_nota_credito_comprobante` (`serie_comprobante`,`num_comprobante`),
  ADD KEY `idx_nc_venta` (`idventa`),
  ADD KEY `idx_nc_cliente` (`idcliente`),
  ADD KEY `idx_nc_usuario` (`idusuario`),
  ADD KEY `idx_nc_fecha` (`fecha_hora`),
  ADD KEY `idx_nc_estado` (`estado`),
  ADD KEY `idx_nc_motivo` (`codigo_motivo`),
  ADD KEY `idx_nc_sucursal` (`idsucursal`),
  ADD KEY `idx_nc_caja` (`idcaja`),
  ADD KEY `idx_nc_apertura` (`idapertura`),
  ADD KEY `idx_nc_usuario_anulacion` (`idusuario_anulacion`);

--
-- Indices de la tabla `nota_credito_cuota_ajuste`
--
ALTER TABLE `nota_credito_cuota_ajuste`
  ADD PRIMARY KEY (`idnota_credito_cuota_ajuste`),
  ADD UNIQUE KEY `uk_nc_cuota` (`idnota_credito`,`idventa_cuota`),
  ADD KEY `idx_ncca_nota` (`idnota_credito`),
  ADD KEY `idx_ncca_cuota` (`idventa_cuota`);

--
-- Indices de la tabla `nota_credito_detalle`
--
ALTER TABLE `nota_credito_detalle`
  ADD PRIMARY KEY (`iddetalle_nota_credito`),
  ADD UNIQUE KEY `uk_nc_detalle_venta` (`idnota_credito`,`iddetalle_venta`),
  ADD KEY `idx_ncd_nota` (`idnota_credito`),
  ADD KEY `idx_ncd_venta_detalle` (`iddetalle_venta`),
  ADD KEY `idx_ncd_articulo` (`idarticulo`);

--
-- Indices de la tabla `nota_credito_motivo`
--
ALTER TABLE `nota_credito_motivo`
  ADD PRIMARY KEY (`codigo`);

--
-- Indices de la tabla `nota_credito_pago`
--
ALTER TABLE `nota_credito_pago`
  ADD PRIMARY KEY (`idnota_credito_pago`),
  ADD UNIQUE KEY `uk_nc_pago_movimiento` (`idmovimiento`),
  ADD KEY `idx_ncp_nota` (`idnota_credito`),
  ADD KEY `idx_ncp_forma_pago` (`idforma_pago`),
  ADD KEY `idx_ncp_cuenta` (`idcuenta_financiera`);

--
-- Indices de la tabla `nota_credito_sunat`
--
ALTER TABLE `nota_credito_sunat`
  ADD PRIMARY KEY (`idnota_credito_sunat`),
  ADD UNIQUE KEY `uk_nc_sunat_nota` (`idnota_credito`),
  ADD UNIQUE KEY `uk_nc_sunat_document_id` (`document_id`),
  ADD KEY `idx_nc_sunat_estado` (`estado_sunat`),
  ADD KEY `idx_nc_sunat_fecha_envio` (`fecha_envio`);

--
-- Indices de la tabla `permiso`
--
ALTER TABLE `permiso`
  ADD PRIMARY KEY (`idpermiso`);

--
-- Indices de la tabla `persona`
--
ALTER TABLE `persona`
  ADD PRIMARY KEY (`idpersona`);

--
-- Indices de la tabla `resumen_diario_boleta`
--
ALTER TABLE `resumen_diario_boleta`
  ADD PRIMARY KEY (`idresumen`),
  ADD UNIQUE KEY `uk_resumen_codigo` (`codigo_resumen`),
  ADD UNIQUE KEY `uk_resumen_fecha_correlativo` (`id_negocio`,`fecha_documentos`,`correlativo`),
  ADD UNIQUE KEY `uk_resumen_document_id` (`document_id`),
  ADD KEY `idx_resumen_fecha` (`fecha_documentos`),
  ADD KEY `idx_resumen_estado` (`estado_sunat`),
  ADD KEY `idx_resumen_usuario` (`idusuario`);

--
-- Indices de la tabla `resumen_diario_boleta_detalle`
--
ALTER TABLE `resumen_diario_boleta_detalle`
  ADD PRIMARY KEY (`iddetalle_resumen`),
  ADD UNIQUE KEY `uk_resumen_detalle_venta` (`idresumen`,`idventa`),
  ADD KEY `idx_detalle_resumen` (`idresumen`),
  ADD KEY `idx_detalle_venta` (`idventa`),
  ADD KEY `idx_detalle_condicion` (`codigo_condicion`);

--
-- Indices de la tabla `subcategoria`
--
ALTER TABLE `subcategoria`
  ADD PRIMARY KEY (`idsubcategoria`);

--
-- Indices de la tabla `sucursal`
--
ALTER TABLE `sucursal`
  ADD PRIMARY KEY (`idsucursal`),
  ADD UNIQUE KEY `uk_sucursal_codigo` (`codigo`),
  ADD UNIQUE KEY `uk_sucursal_codigo_sunat` (`codigo_establecimiento_sunat`);

--
-- Indices de la tabla `sunat_catalogo_07_afectacion_igv`
--
ALTER TABLE `sunat_catalogo_07_afectacion_igv`
  ADD PRIMARY KEY (`codigo`);

--
-- Indices de la tabla `sunat_catalogo_51_tipo_operacion`
--
ALTER TABLE `sunat_catalogo_51_tipo_operacion`
  ADD PRIMARY KEY (`codigo`);

--
-- Indices de la tabla `tipo_pago`
--
ALTER TABLE `tipo_pago`
  ADD PRIMARY KEY (`idtipopago`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`idusuario`),
  ADD UNIQUE KEY `login_UNIQUE` (`login`);

--
-- Indices de la tabla `usuario_almacen`
--
ALTER TABLE `usuario_almacen`
  ADD PRIMARY KEY (`idusuario_almacen`),
  ADD UNIQUE KEY `uq_usuario_almacen` (`idusuario`,`idalmacen`),
  ADD KEY `idx_usuario_almacen_usuario` (`idusuario`),
  ADD KEY `idx_usuario_almacen_almacen` (`idalmacen`);

--
-- Indices de la tabla `usuario_caja`
--
ALTER TABLE `usuario_caja`
  ADD PRIMARY KEY (`idusuario_caja`),
  ADD UNIQUE KEY `uk_usuario_caja` (`idusuario`,`idcaja`),
  ADD KEY `idx_usuario_caja_usuario` (`idusuario`),
  ADD KEY `idx_usuario_caja_caja` (`idcaja`),
  ADD KEY `idx_usuario_caja_rol` (`rol`),
  ADD KEY `idx_usuario_caja_activa` (`idusuario`,`idcaja`,`activo`);

--
-- Indices de la tabla `usuario_permiso`
--
ALTER TABLE `usuario_permiso`
  ADD PRIMARY KEY (`idusuario_permiso`),
  ADD KEY `fk_u_permiso_usuario_idx` (`idusuario`),
  ADD KEY `fk_usuario_permiso_idx` (`idpermiso`);

--
-- Indices de la tabla `usuario_sucursal`
--
ALTER TABLE `usuario_sucursal`
  ADD PRIMARY KEY (`idusuario_sucursal`),
  ADD UNIQUE KEY `uk_usuario_sucursal` (`idusuario`,`idsucursal`),
  ADD KEY `idx_usuario_sucursal_usuario` (`idusuario`),
  ADD KEY `idx_usuario_sucursal_sucursal` (`idsucursal`),
  ADD KEY `idx_usuario_sucursal_activa` (`idusuario`,`idsucursal`,`activo`);

--
-- Indices de la tabla `variacion_atributo_valor`
--
ALTER TABLE `variacion_atributo_valor`
  ADD PRIMARY KEY (`idvariacion`,`idvalor`),
  ADD KEY `idvalor` (`idvalor`);

--
-- Indices de la tabla `venta`
--
ALTER TABLE `venta`
  ADD PRIMARY KEY (`idventa`),
  ADD UNIQUE KEY `uk_venta_comprobante` (`tipo_comprobante`,`serie_comprobante`,`num_comprobante`),
  ADD KEY `fk_venta_persona_idx` (`idcliente`),
  ADD KEY `fk_venta_usuario_idx` (`idusuario`),
  ADD KEY `idforma_pago` (`idforma_pago`),
  ADD KEY `idx_venta_fecha_hora` (`fecha_hora`),
  ADD KEY `idx_fecha_hora` (`fecha_hora`),
  ADD KEY `idx_fecha` (`fecha_hora`),
  ADD KEY `idx_venta_sucursal` (`idsucursal`),
  ADD KEY `idx_venta_caja` (`idcaja`),
  ADD KEY `idx_venta_apertura` (`idapertura`),
  ADD KEY `idx_venta_modo_envio_sunat` (`modo_envio_sunat`);

--
-- Indices de la tabla `venta_cuota`
--
ALTER TABLE `venta_cuota`
  ADD PRIMARY KEY (`idventa_cuota`),
  ADD UNIQUE KEY `uk_venta_numero_cuota` (`idventa`,`numero_cuota`),
  ADD KEY `idx_venta_cuota_estado` (`estado`),
  ADD KEY `idx_venta_cuota_vencimiento` (`fecha_vencimiento`);

--
-- Indices de la tabla `venta_pago`
--
ALTER TABLE `venta_pago`
  ADD PRIMARY KEY (`idventa_pago`),
  ADD KEY `idforma_pago` (`idforma_pago`),
  ADD KEY `idx_idventa` (`idventa`);

--
-- Indices de la tabla `venta_sunat`
--
ALTER TABLE `venta_sunat`
  ADD PRIMARY KEY (`idventa_sunat`),
  ADD UNIQUE KEY `uk_idventa` (`idventa`),
  ADD UNIQUE KEY `uk_document_id` (`document_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `almacen`
--
ALTER TABLE `almacen`
  MODIFY `idalmacen` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `articulo`
--
ALTER TABLE `articulo`
  MODIFY `idarticulo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `articulo_variacion`
--
ALTER TABLE `articulo_variacion`
  MODIFY `idvariacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `atributo`
--
ALTER TABLE `atributo`
  MODIFY `idatributo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `atributo_valor`
--
ALTER TABLE `atributo_valor`
  MODIFY `idvalor` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `caja_apertura`
--
ALTER TABLE `caja_apertura`
  MODIFY `idapertura` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `caja_cierre`
--
ALTER TABLE `caja_cierre`
  MODIFY `idcierre` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `caja_fisica`
--
ALTER TABLE `caja_fisica`
  MODIFY `idcaja` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `categoria`
--
ALTER TABLE `categoria`
  MODIFY `idcategoria` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `categoria_compra`
--
ALTER TABLE `categoria_compra`
  MODIFY `idcategoria_compra` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cobranza`
--
ALTER TABLE `cobranza`
  MODIFY `idcobranza` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cobranza_detalle`
--
ALTER TABLE `cobranza_detalle`
  MODIFY `idcobranza_detalle` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cobranza_pago`
--
ALTER TABLE `cobranza_pago`
  MODIFY `idcobranza_pago` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `comp_pago`
--
ALTER TABLE `comp_pago`
  MODIFY `id_comp_pago` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cuenta_financiera`
--
ALTER TABLE `cuenta_financiera`
  MODIFY `idcuenta_financiera` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `datos_negocio`
--
ALTER TABLE `datos_negocio`
  MODIFY `id_negocio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_ingreso`
--
ALTER TABLE `detalle_ingreso`
  MODIFY `iddetalle_ingreso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  MODIFY `iddetalle_venta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `forma_pago`
--
ALTER TABLE `forma_pago`
  MODIFY `idforma_pago` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ingreso`
--
ALTER TABLE `ingreso`
  MODIFY `idingreso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `kardex`
--
ALTER TABLE `kardex`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `medida`
--
ALTER TABLE `medida`
  MODIFY `idmedida` int(12) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `movimiento_financiero`
--
ALTER TABLE `movimiento_financiero`
  MODIFY `idmovimiento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `nota_credito`
--
ALTER TABLE `nota_credito`
  MODIFY `idnota_credito` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `nota_credito_cuota_ajuste`
--
ALTER TABLE `nota_credito_cuota_ajuste`
  MODIFY `idnota_credito_cuota_ajuste` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `nota_credito_detalle`
--
ALTER TABLE `nota_credito_detalle`
  MODIFY `iddetalle_nota_credito` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `nota_credito_pago`
--
ALTER TABLE `nota_credito_pago`
  MODIFY `idnota_credito_pago` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `nota_credito_sunat`
--
ALTER TABLE `nota_credito_sunat`
  MODIFY `idnota_credito_sunat` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `permiso`
--
ALTER TABLE `permiso`
  MODIFY `idpermiso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `persona`
--
ALTER TABLE `persona`
  MODIFY `idpersona` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `resumen_diario_boleta`
--
ALTER TABLE `resumen_diario_boleta`
  MODIFY `idresumen` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `resumen_diario_boleta_detalle`
--
ALTER TABLE `resumen_diario_boleta_detalle`
  MODIFY `iddetalle_resumen` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `subcategoria`
--
ALTER TABLE `subcategoria`
  MODIFY `idsubcategoria` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sucursal`
--
ALTER TABLE `sucursal`
  MODIFY `idsucursal` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipo_pago`
--
ALTER TABLE `tipo_pago`
  MODIFY `idtipopago` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `idusuario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuario_almacen`
--
ALTER TABLE `usuario_almacen`
  MODIFY `idusuario_almacen` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuario_caja`
--
ALTER TABLE `usuario_caja`
  MODIFY `idusuario_caja` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuario_permiso`
--
ALTER TABLE `usuario_permiso`
  MODIFY `idusuario_permiso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuario_sucursal`
--
ALTER TABLE `usuario_sucursal`
  MODIFY `idusuario_sucursal` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `venta`
--
ALTER TABLE `venta`
  MODIFY `idventa` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `venta_cuota`
--
ALTER TABLE `venta_cuota`
  MODIFY `idventa_cuota` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `venta_pago`
--
ALTER TABLE `venta_pago`
  MODIFY `idventa_pago` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `venta_sunat`
--
ALTER TABLE `venta_sunat`
  MODIFY `idventa_sunat` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `almacen`
--
ALTER TABLE `almacen`
  ADD CONSTRAINT `fk_almacen_sucursal` FOREIGN KEY (`idsucursal`) REFERENCES `sucursal` (`idsucursal`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `articulo`
--
ALTER TABLE `articulo`
  ADD CONSTRAINT `fk_articulo_almacen` FOREIGN KEY (`idalmacen`) REFERENCES `almacen` (`idalmacen`),
  ADD CONSTRAINT `fk_articulo_categoria` FOREIGN KEY (`idcategoria`) REFERENCES `categoria` (`idcategoria`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `articulo_variacion`
--
ALTER TABLE `articulo_variacion`
  ADD CONSTRAINT `articulo_variacion_ibfk_1` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `atributo_valor`
--
ALTER TABLE `atributo_valor`
  ADD CONSTRAINT `atributo_valor_ibfk_1` FOREIGN KEY (`idatributo`) REFERENCES `atributo` (`idatributo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `caja_apertura`
--
ALTER TABLE `caja_apertura`
  ADD CONSTRAINT `fk_caja_apertura_caja_restrict` FOREIGN KEY (`idcaja`) REFERENCES `caja_fisica` (`idcaja`),
  ADD CONSTRAINT `fk_caja_apertura_sucursal` FOREIGN KEY (`idsucursal`) REFERENCES `sucursal` (`idsucursal`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_caja_apertura_usuario_apertura` FOREIGN KEY (`idusuario_apertura`) REFERENCES `usuario` (`idusuario`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_caja_apertura_usuario_responsable` FOREIGN KEY (`idusuario_responsable`) REFERENCES `usuario` (`idusuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `caja_cierre`
--
ALTER TABLE `caja_cierre`
  ADD CONSTRAINT `fk_caja_apertura` FOREIGN KEY (`caja_apertura_id`) REFERENCES `caja_apertura` (`idapertura`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `caja_fisica`
--
ALTER TABLE `caja_fisica`
  ADD CONSTRAINT `fk_caja_fisica_sucursal` FOREIGN KEY (`idsucursal`) REFERENCES `sucursal` (`idsucursal`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `cobranza`
--
ALTER TABLE `cobranza`
  ADD CONSTRAINT `fk_cobranza_cliente` FOREIGN KEY (`idcliente`) REFERENCES `persona` (`idpersona`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cobranza_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cobranza_venta` FOREIGN KEY (`idventa`) REFERENCES `venta` (`idventa`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `cobranza_detalle`
--
ALTER TABLE `cobranza_detalle`
  ADD CONSTRAINT `fk_cobranza_detalle_cobranza` FOREIGN KEY (`idcobranza`) REFERENCES `cobranza` (`idcobranza`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cobranza_detalle_cuota` FOREIGN KEY (`idventa_cuota`) REFERENCES `venta_cuota` (`idventa_cuota`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `cobranza_pago`
--
ALTER TABLE `cobranza_pago`
  ADD CONSTRAINT `fk_cobranza_pago_apertura` FOREIGN KEY (`idapertura`) REFERENCES `caja_apertura` (`idapertura`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cobranza_pago_cobranza` FOREIGN KEY (`idcobranza`) REFERENCES `cobranza` (`idcobranza`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cobranza_pago_cuenta` FOREIGN KEY (`idcuenta_financiera`) REFERENCES `cuenta_financiera` (`idcuenta_financiera`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cobranza_pago_forma` FOREIGN KEY (`idforma_pago`) REFERENCES `forma_pago` (`idforma_pago`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `configuracion_caja`
--
ALTER TABLE `configuracion_caja`
  ADD CONSTRAINT `fk_configuracion_caja_sucursal` FOREIGN KEY (`idsucursal`) REFERENCES `sucursal` (`idsucursal`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_configuracion_caja_unica` FOREIGN KEY (`idcaja_unica`) REFERENCES `caja_fisica` (`idcaja`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  ADD CONSTRAINT `fk_detalle_venta_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_detalle_venta_venta` FOREIGN KEY (`idventa`) REFERENCES `venta` (`idventa`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `forma_pago_destino`
--
ALTER TABLE `forma_pago_destino`
  ADD CONSTRAINT `fk_forma_destino_cuenta` FOREIGN KEY (`idcuenta_financiera`) REFERENCES `cuenta_financiera` (`idcuenta_financiera`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_forma_destino_forma_pago` FOREIGN KEY (`idforma_pago`) REFERENCES `forma_pago` (`idforma_pago`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `movimiento_financiero`
--
ALTER TABLE `movimiento_financiero`
  ADD CONSTRAINT `fk_movimiento_apertura` FOREIGN KEY (`idapertura`) REFERENCES `caja_apertura` (`idapertura`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_movimiento_cobranza_pago` FOREIGN KEY (`idcobranza_pago`) REFERENCES `cobranza_pago` (`idcobranza_pago`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_movimiento_cuenta` FOREIGN KEY (`idcuenta_financiera`) REFERENCES `cuenta_financiera` (`idcuenta_financiera`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_movimiento_forma_pago` FOREIGN KEY (`idforma_pago`) REFERENCES `forma_pago` (`idforma_pago`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_movimiento_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `nota_credito`
--
ALTER TABLE `nota_credito`
  ADD CONSTRAINT `fk_nc_apertura` FOREIGN KEY (`idapertura`) REFERENCES `caja_apertura` (`idapertura`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_nc_caja` FOREIGN KEY (`idcaja`) REFERENCES `caja_fisica` (`idcaja`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_nc_cliente` FOREIGN KEY (`idcliente`) REFERENCES `persona` (`idpersona`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_nc_motivo` FOREIGN KEY (`codigo_motivo`) REFERENCES `nota_credito_motivo` (`codigo`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_nc_sucursal` FOREIGN KEY (`idsucursal`) REFERENCES `sucursal` (`idsucursal`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_nc_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_nc_usuario_anulacion` FOREIGN KEY (`idusuario_anulacion`) REFERENCES `usuario` (`idusuario`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_nc_venta` FOREIGN KEY (`idventa`) REFERENCES `venta` (`idventa`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `nota_credito_cuota_ajuste`
--
ALTER TABLE `nota_credito_cuota_ajuste`
  ADD CONSTRAINT `fk_ncca_cuota` FOREIGN KEY (`idventa_cuota`) REFERENCES `venta_cuota` (`idventa_cuota`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ncca_nota` FOREIGN KEY (`idnota_credito`) REFERENCES `nota_credito` (`idnota_credito`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `nota_credito_detalle`
--
ALTER TABLE `nota_credito_detalle`
  ADD CONSTRAINT `fk_ncd_articulo` FOREIGN KEY (`idarticulo`) REFERENCES `articulo` (`idarticulo`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ncd_detalle_venta` FOREIGN KEY (`iddetalle_venta`) REFERENCES `detalle_venta` (`iddetalle_venta`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ncd_nota` FOREIGN KEY (`idnota_credito`) REFERENCES `nota_credito` (`idnota_credito`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `nota_credito_pago`
--
ALTER TABLE `nota_credito_pago`
  ADD CONSTRAINT `fk_ncp_cuenta` FOREIGN KEY (`idcuenta_financiera`) REFERENCES `cuenta_financiera` (`idcuenta_financiera`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ncp_forma_pago` FOREIGN KEY (`idforma_pago`) REFERENCES `forma_pago` (`idforma_pago`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ncp_movimiento` FOREIGN KEY (`idmovimiento`) REFERENCES `movimiento_financiero` (`idmovimiento`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ncp_nota` FOREIGN KEY (`idnota_credito`) REFERENCES `nota_credito` (`idnota_credito`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `nota_credito_sunat`
--
ALTER TABLE `nota_credito_sunat`
  ADD CONSTRAINT `fk_nc_sunat_nota` FOREIGN KEY (`idnota_credito`) REFERENCES `nota_credito` (`idnota_credito`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `resumen_diario_boleta`
--
ALTER TABLE `resumen_diario_boleta`
  ADD CONSTRAINT `fk_resumen_negocio` FOREIGN KEY (`id_negocio`) REFERENCES `datos_negocio` (`id_negocio`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_resumen_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `resumen_diario_boleta_detalle`
--
ALTER TABLE `resumen_diario_boleta_detalle`
  ADD CONSTRAINT `fk_resumen_detalle_resumen` FOREIGN KEY (`idresumen`) REFERENCES `resumen_diario_boleta` (`idresumen`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_resumen_detalle_venta` FOREIGN KEY (`idventa`) REFERENCES `venta` (`idventa`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuario_almacen`
--
ALTER TABLE `usuario_almacen`
  ADD CONSTRAINT `fk_usuario_almacen_almacen` FOREIGN KEY (`idalmacen`) REFERENCES `almacen` (`idalmacen`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuario_almacen_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuario_caja`
--
ALTER TABLE `usuario_caja`
  ADD CONSTRAINT `fk_usuario_caja_caja` FOREIGN KEY (`idcaja`) REFERENCES `caja_fisica` (`idcaja`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuario_caja_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuario_permiso`
--
ALTER TABLE `usuario_permiso`
  ADD CONSTRAINT `fk_u_permiso_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_usuario_permiso` FOREIGN KEY (`idpermiso`) REFERENCES `permiso` (`idpermiso`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `usuario_sucursal`
--
ALTER TABLE `usuario_sucursal`
  ADD CONSTRAINT `fk_usuario_sucursal_sucursal` FOREIGN KEY (`idsucursal`) REFERENCES `sucursal` (`idsucursal`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuario_sucursal_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `variacion_atributo_valor`
--
ALTER TABLE `variacion_atributo_valor`
  ADD CONSTRAINT `variacion_atributo_valor_ibfk_1` FOREIGN KEY (`idvariacion`) REFERENCES `articulo_variacion` (`idvariacion`) ON DELETE CASCADE,
  ADD CONSTRAINT `variacion_atributo_valor_ibfk_2` FOREIGN KEY (`idvalor`) REFERENCES `atributo_valor` (`idvalor`) ON DELETE CASCADE;

--
-- Filtros para la tabla `venta`
--
ALTER TABLE `venta`
  ADD CONSTRAINT `fk_venta_apertura` FOREIGN KEY (`idapertura`) REFERENCES `caja_apertura` (`idapertura`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_venta_caja` FOREIGN KEY (`idcaja`) REFERENCES `caja_fisica` (`idcaja`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_venta_persona` FOREIGN KEY (`idcliente`) REFERENCES `persona` (`idpersona`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_venta_sucursal` FOREIGN KEY (`idsucursal`) REFERENCES `sucursal` (`idsucursal`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_venta_usuario` FOREIGN KEY (`idusuario`) REFERENCES `usuario` (`idusuario`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `venta_ibfk_1` FOREIGN KEY (`idforma_pago`) REFERENCES `forma_pago` (`idforma_pago`);

--
-- Filtros para la tabla `venta_cuota`
--
ALTER TABLE `venta_cuota`
  ADD CONSTRAINT `fk_venta_cuota_venta` FOREIGN KEY (`idventa`) REFERENCES `venta` (`idventa`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `venta_pago`
--
ALTER TABLE `venta_pago`
  ADD CONSTRAINT `venta_pago_ibfk_1` FOREIGN KEY (`idventa`) REFERENCES `venta` (`idventa`),
  ADD CONSTRAINT `venta_pago_ibfk_2` FOREIGN KEY (`idforma_pago`) REFERENCES `forma_pago` (`idforma_pago`);

--
-- Filtros para la tabla `venta_sunat`
--
ALTER TABLE `venta_sunat`
  ADD CONSTRAINT `venta_sunat_ibfk_1` FOREIGN KEY (`idventa`) REFERENCES `venta` (`idventa`);


-- --------------------------------------------------------
-- CATÁLOGOS GENÉRICOS TIQUEPOS
-- --------------------------------------------------------
INSERT INTO `categoria_compra` (`idcategoria_compra`, `nombre`, `descripcion`, `estado`) VALUES
(1, 'Servicios básicos', 'Agua, energía eléctrica, internet, telefonía y similares', 1),
(2, 'Alquileres', 'Alquiler de local, equipos u otros bienes', 1),
(3, 'Publicidad y marketing', 'Publicidad, impresión, diseño y promoción', 1),
(4, 'Transporte y movilidad', 'Fletes, delivery, combustible y movilidad', 1),
(5, 'Mantenimiento y reparaciones', 'Mantenimiento de equipos, local y mobiliario', 1),
(6, 'Útiles de oficina', 'Papelería y materiales administrativos', 1),
(7, 'Limpieza', 'Materiales y servicios de limpieza', 1),
(8, 'Honorarios profesionales', 'Servicios profesionales y técnicos', 1),
(9, 'Comisiones bancarias', 'Comisiones, portes y gastos financieros', 1),
(10, 'Suscripciones', 'Sistemas, plataformas y servicios recurrentes', 1),
(11, 'Otros gastos', 'Compras no inventariables sin una categoría específica', 1);

INSERT INTO `comp_pago` (`id_comp_pago`, `nombre`, `letra_serie`, `serie_comprobante`, `num_comprobante`, `condicion`) VALUES
(1, 'Factura Electrónica', 'F', '001', '00000000', 1),
(2, 'Boleta Electrónica', 'B', '001', '00000000', 1),
(3, 'Nota de venta', 'NV', '001', '00000000', 1),
(4, 'Recibo', 'R', '001', '00000000', 1),
(5, 'Cotización', 'C', '001', '00000000', 1),
(6, 'Nota de Crédito - Factura', 'F', 'C01', '00000000', 1),
(7, 'Nota de Crédito - Boleta', 'B', 'C01', '00000000', 1);

INSERT INTO `cuenta_financiera` (`idcuenta_financiera`, `nombre`, `tipo`, `entidad`, `moneda`, `referencia`, `activo`) VALUES
(1, 'Caja principal', 'CAJA', NULL, 'PEN', 'Efectivo del establecimiento', 1),
(2, 'Yape', 'BILLETERA', NULL, 'PEN', 'Billetera Yape', 1),
(3, 'Plin', 'BILLETERA', NULL, 'PEN', 'Billetera Plin', 1),
(4, 'Tarjeta', 'PASARELA', NULL, 'PEN', 'Cobros con tarjeta', 1);

INSERT INTO `forma_pago` (`idforma_pago`, `nombre`, `es_efectivo`, `es_combinado`, `activo`, `condicion`) VALUES
(1, 'Efectivo', 1, 0, 1, 1),
(2, 'Yape', 0, 0, 1, 1),
(3, 'Plin', 0, 0, 1, 1),
(4, 'Tarjeta', 0, 0, 1, 1),
(5, 'Mixto', 0, 1, 1, 1);

INSERT INTO `forma_pago_destino` (`idforma_pago`, `idcuenta_financiera`, `requiere_caja_abierta`, `requiere_operacion`, `permite_cobranza`) VALUES
(1, 1, 1, 0, 1),
(2, 2, 0, 1, 1),
(3, 3, 0, 1, 1),
(4, 4, 0, 1, 1),
(5, NULL, 0, 0, 0);

INSERT INTO `medida` (`idmedida`, `codigo`, `nombre`, `condicion`) VALUES
(1, 'NIU', 'Unidad', 1),
(2, 'ZZ', 'Servicios', 1),
(3, 'KG', 'Kilogramo', 1);

INSERT INTO `nota_credito_motivo` (`codigo`, `descripcion`, `permite_factura`, `permite_boleta`, `afecta_stock_default`, `permite_parcial`, `activo`) VALUES
('01', 'Anulación de la operación', 1, 1, 1, 0, 1),
('02', 'Anulación por error en el RUC', 1, 1, 0, 0, 0),
('03', 'Corrección por error en la descripción', 1, 1, 0, 0, 0),
('04', 'Descuento global', 1, 0, 0, 0, 0),
('05', 'Descuento por ítem', 1, 0, 0, 1, 0),
('06', 'Devolución total', 1, 1, 1, 0, 1),
('07', 'Devolución por ítem', 1, 1, 1, 1, 1),
('08', 'Bonificación', 1, 0, 0, 1, 0),
('09', 'Disminución en el valor', 1, 1, 0, 1, 0),
('10', 'Otros conceptos', 1, 1, 0, 1, 0),
('11', 'Ajustes de operaciones de exportación', 1, 1, 0, 1, 0),
('12', 'Ajustes afectos al IVAP', 1, 1, 0, 1, 0),
('13', 'Corrección del monto neto pendiente o de las cuotas', 1, 0, 0, 1, 0);

INSERT INTO `permiso` (`idpermiso`, `nombre`) VALUES
(1, 'Escritorio'), (2, 'Almacen'), (3, 'Compras'), (4, 'Ventas'),
(5, 'Acceso'), (6, 'Consulta Compras'), (7, 'Consulta Ventas'), (8, 'Configuracion');

INSERT INTO `sunat_catalogo_07_afectacion_igv` (`codigo`, `descripcion`, `porcentaje_predeterminado`, `codigo_tributo`, `nombre_tributo`, `tipo_tributo`, `activo`, `orden`) VALUES
('10', 'Gravado - Operación onerosa', 18.00, '1000', 'IGV', 'VAT', 1, 10),
('20', 'Exonerado - Operación onerosa', 0.00, '9997', 'EXO', 'VAT', 1, 20),
('30', 'Inafecto - Operación onerosa', 0.00, '9998', 'INA', 'FRE', 1, 30),
('40', 'Exportación', 0.00, '9995', 'EXP', 'FRE', 1, 40);

INSERT INTO `sunat_catalogo_51_tipo_operacion` (`codigo`, `descripcion`, `comprobantes`, `activo`, `orden`) VALUES
('0101', 'Venta interna no sujeta a detracción, retención o percepción', 'Factura, Boleta', 1, 10),
('0112', 'Venta interna - Sustenta Gastos Deducibles Persona Natural', 'Factura', 1, 20),
('0113', 'Venta interna - NRUS', 'Boleta', 1, 30),
('0200', 'Exportación de bienes', 'Factura, Boleta', 1, 40),
('0201', 'Exportación de servicios - Prestación realizada íntegramente en el país', 'Factura, Boleta', 1, 50),
('0202', 'Exportación de servicios - Hospedaje a no domiciliados', 'Factura, Boleta', 1, 60),
('0203', 'Exportación de servicios - Transporte de navieras', 'Factura, Boleta', 1, 70),
('0204', 'Exportación de servicios - Servicios a naves y aeronaves extranjeras', 'Factura, Boleta', 1, 80),
('0205', 'Exportación de servicios - Paquete turístico', 'Factura, Boleta', 1, 90),
('0206', 'Exportación de servicios - Servicios complementarios al transporte de carga', 'Factura, Boleta', 1, 100),
('0207', 'Exportación de servicios - Suministro de energía a sujetos domiciliados en ZED', 'Factura, Boleta', 1, 110),
('0208', 'Exportación de servicios - Prestación realizada parcialmente en el extranjero', 'Factura, Boleta', 1, 120),
('0301', 'Operaciones con carta de porte aéreo emitidas en el ámbito nacional', 'Factura, Boleta', 1, 130),
('0302', 'Operaciones de transporte ferroviario de pasajeros', 'Factura, Boleta', 1, 140),
('0401', 'Venta a no domiciliados que no califica como exportación', 'Factura, Boleta', 1, 150),
('1001', 'Operación sujeta a detracción', 'Factura, Boleta', 1, 160),
('1002', 'Operación sujeta a detracción - Recursos hidrobiológicos', 'Factura, Boleta', 1, 170),
('1003', 'Operación sujeta a detracción - Transporte de pasajeros', 'Factura, Boleta', 1, 180),
('1004', 'Operación sujeta a detracción - Transporte de carga', 'Factura, Boleta', 1, 190),
('2001', 'Operación sujeta a percepción', 'Factura, Boleta', 1, 200);

INSERT INTO `tipo_pago` (`idtipopago`, `nombre`, `descripcion`, `estado`) VALUES
(1, 'Contado', 'Pago inmediato', 1),
(2, 'Crédito', 'Pago diferido', 1);


COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
