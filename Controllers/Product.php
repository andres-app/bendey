<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('America/Lima');

require_once __DIR__ . '/../Models/Product.php';

$product = new Product();

$idarticulo = isset($_POST['idarticulo']) ? $_POST['idarticulo'] : '';
$idsubcategoria = isset($_POST['idsubcategoria']) ? $_POST['idsubcategoria'] : '';
$idcategoria = isset($_POST['idcategoria']) ? $_POST['idcategoria'] : '';
$idmedida = isset($_POST['idmedida']) ? $_POST['idmedida'] : '';
$idalmacen = isset($_POST['idalmacen']) ? $_POST['idalmacen'] : '';
$codigo = isset($_POST['codigo']) ? $_POST['codigo'] : '';
$nombre = isset($_POST['nombre']) ? $_POST['nombre'] : '';
$stock = isset($_POST['stock']) ? $_POST['stock'] : '';
$precio_compra = isset($_POST['precio_compra']) ? $_POST['precio_compra'] : null;
$precio_venta = isset($_POST['precio_venta']) ? $_POST['precio_venta'] : null;
$descripcion = isset($_POST['descripcion']) ? $_POST['descripcion'] : '';
$imagen = isset($_POST['imagen']) ? $_POST['imagen'] : '';

/**
 * Devuelve una respuesta JSON y finaliza la ejecución.
 */
function responderProductoJson(
    bool $success,
    string $mensaje,
    ?array $producto = null,
    int $codigoHttp = 200,
    array $extra = []
): void {
    http_response_code($codigoHttp);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    $respuesta = array_merge(
        [
            'success' => $success,
            'mensaje' => $mensaje
        ],
        $extra
    );

    if ($producto !== null) {
        $respuesta['producto'] = $producto;
    }

    echo json_encode(
        $respuesta,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

switch ($_GET['op'] ?? '') {

    /* =========================================================
       DATOS NECESARIOS PARA EL FORMULARIO RÁPIDO
       Usa exactamente las columnas de la base de datos:
       - categoria.condicion
       - subcategoria.estado
       - medida.condicion
       - almacen.estado
       ========================================================= */
    case 'datosRapidos':

        $idusuario = (int)($_SESSION['idusuario'] ?? 0);
        $permisoVentas = (int)($_SESSION['ventas'] ?? 0);

        if ($idusuario <= 0 || $permisoVentas !== 1) {
            responderProductoJson(
                false,
                'La sesión no es válida o no tiene permiso para vender.',
                null,
                403
            );
        }

        try {
            $conexion = new Conexion();

            $categorias = $conexion->getDataAll(
                "SELECT idcategoria, nombre
                 FROM categoria
                 WHERE condicion = 1
                 ORDER BY nombre ASC"
            );

            $subcategorias = $conexion->getDataAll(
                "SELECT idsubcategoria, idcategoria, nombre
                 FROM subcategoria
                 WHERE estado = 1
                 ORDER BY nombre ASC"
            );

            $medidas = $conexion->getDataAll(
                "SELECT idmedida, codigo, nombre
                 FROM medida
                 WHERE condicion = 1
                 ORDER BY
                    CASE WHEN UPPER(codigo) = 'NIU' THEN 0 ELSE 1 END,
                    nombre ASC"
            );

            $almacenes = $conexion->getDataAll(
                "SELECT idalmacen, nombre
                 FROM almacen
                 WHERE estado = 1
                 ORDER BY
                    CASE WHEN UPPER(nombre) LIKE '%PRINCIPAL%' THEN 0 ELSE 1 END,
                    nombre ASC"
            );

            responderProductoJson(
                true,
                'Datos del producto rápido cargados.',
                null,
                200,
                [
                    'datos' => [
                        'categorias' => is_array($categorias) ? $categorias : [],
                        'subcategorias' => is_array($subcategorias) ? $subcategorias : [],
                        'medidas' => is_array($medidas) ? $medidas : [],
                        'almacenes' => is_array($almacenes) ? $almacenes : []
                    ]
                ]
            );
        } catch (Throwable $error) {
            error_log('[DATOS PRODUCTO RÁPIDO] ' . $error->getMessage());

            responderProductoJson(
                false,
                'No se pudieron cargar los datos para registrar el producto.',
                null,
                500
            );
        }

        break;

    /* =========================================================
       CREAR PRODUCTO RÁPIDO DESDE NUEVA VENTA
       Reutiliza Product::insertar(), que ya crea:
       - artículo
       - ingreso de stock inicial
       - detalle_ingreso
       - kardex
       ========================================================= */
    case 'guardarRapido':

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderProductoJson(
                false,
                'Método no permitido.',
                null,
                405
            );
        }

        $idusuario = (int)($_SESSION['idusuario'] ?? 0);
        $permisoVentas = (int)($_SESSION['ventas'] ?? 0);

        if ($idusuario <= 0 || $permisoVentas !== 1) {
            responderProductoJson(
                false,
                'La sesión no es válida o no tiene permiso para vender.',
                null,
                403
            );
        }

        try {
            $conexion = new Conexion();

            $idcategoriaRapida = (int)($_POST['idcategoria'] ?? 0);
            $idsubcategoriaRapida = (int)($_POST['idsubcategoria'] ?? 0);
            $idmedidaRapida = (int)($_POST['idmedida'] ?? 0);
            $idalmacenRapido = (int)($_POST['idalmacen'] ?? 0);

            $nombreRapido = preg_replace(
                '/\s+/u',
                ' ',
                trim((string)($_POST['nombre'] ?? ''))
            );

            $codigoRapido = mb_strtoupper(
                trim((string)($_POST['codigo'] ?? '')),
                'UTF-8'
            );

            $codigoRapido = preg_replace(
                '/[^A-Z0-9._\-]/u',
                '',
                $codigoRapido
            );

            $stockRapido = filter_var(
                $_POST['stock'] ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                        'max_range' => 999999
                    ]
                ]
            );

            $precioCompraRapido = round(
                (float)($_POST['precio_compra'] ?? 0),
                2
            );

            $precioVentaRapido = round(
                (float)($_POST['precio_venta'] ?? 0),
                2
            );

            if ($idcategoriaRapida <= 0) {
                throw new RuntimeException('Debe seleccionar una categoría.');
            }

            if ($nombreRapido === '') {
                throw new RuntimeException('Debe ingresar el nombre del producto.');
            }

            if (mb_strlen($nombreRapido, 'UTF-8') > 100) {
                throw new RuntimeException(
                    'El nombre del producto no puede superar 100 caracteres.'
                );
            }

            if ($stockRapido === false) {
                throw new RuntimeException(
                    'La cantidad disponible debe ser un número entero mayor que cero.'
                );
            }

            if ($precioCompraRapido < 0) {
                throw new RuntimeException(
                    'El costo por unidad no puede ser negativo.'
                );
            }

            if ($precioVentaRapido <= 0) {
                throw new RuntimeException(
                    'El precio al cliente debe ser mayor que cero.'
                );
            }

            $categoriaRapida = $conexion->getData(
                "SELECT idcategoria, nombre
                 FROM categoria
                 WHERE idcategoria = ?
                   AND condicion = 1
                 LIMIT 1",
                [$idcategoriaRapida]
            );

            if (!$categoriaRapida) {
                throw new RuntimeException(
                    'La categoría seleccionada no existe o está desactivada.'
                );
            }

            $subcategoriaRapida = null;

            if ($idsubcategoriaRapida > 0) {
                $subcategoriaRapida = $conexion->getData(
                    "SELECT idsubcategoria, nombre
                     FROM subcategoria
                     WHERE idsubcategoria = ?
                       AND idcategoria = ?
                       AND estado = 1
                     LIMIT 1",
                    [$idsubcategoriaRapida, $idcategoriaRapida]
                );

                if (!$subcategoriaRapida) {
                    throw new RuntimeException(
                        'La subcategoría no pertenece a la categoría seleccionada o está desactivada.'
                    );
                }
            }

            if ($idmedidaRapida <= 0) {
                $medidaPredeterminada = $conexion->getData(
                    "SELECT idmedida
                     FROM medida
                     WHERE condicion = 1
                     ORDER BY CASE WHEN UPPER(codigo) = 'NIU' THEN 0 ELSE 1 END,
                              idmedida ASC
                     LIMIT 1"
                );

                $idmedidaRapida = (int)($medidaPredeterminada['idmedida'] ?? 0);
            }

            $medidaRapida = $conexion->getData(
                "SELECT idmedida, codigo, nombre
                 FROM medida
                 WHERE idmedida = ?
                   AND condicion = 1
                 LIMIT 1",
                [$idmedidaRapida]
            );

            if (!$medidaRapida) {
                throw new RuntimeException(
                    'La unidad de venta seleccionada no existe o está desactivada.'
                );
            }

            if ($idalmacenRapido <= 0) {
                $almacenPredeterminado = $conexion->getData(
                    "SELECT idalmacen
                     FROM almacen
                     WHERE estado = 1
                     ORDER BY CASE WHEN UPPER(nombre) LIKE '%PRINCIPAL%' THEN 0 ELSE 1 END,
                              idalmacen ASC
                     LIMIT 1"
                );

                $idalmacenRapido = (int)($almacenPredeterminado['idalmacen'] ?? 0);
            }

            $almacenRapido = $conexion->getData(
                "SELECT idalmacen, nombre
                 FROM almacen
                 WHERE idalmacen = ?
                   AND estado = 1
                 LIMIT 1",
                [$idalmacenRapido]
            );

            if (!$almacenRapido) {
                throw new RuntimeException(
                    'El almacén seleccionado no existe o está desactivado.'
                );
            }

            if ($codigoRapido === '') {
                for ($intento = 0; $intento < 10; $intento++) {
                    $candidato = 'RAP-' . date('ymdHis') . '-' . random_int(10, 99);

                    if (!$product->verificarCodigo($candidato)) {
                        $codigoRapido = $candidato;
                        break;
                    }
                }

                if ($codigoRapido === '') {
                    throw new RuntimeException(
                        'No se pudo generar un código único para el producto.'
                    );
                }
            }

            if (strlen($codigoRapido) > 50) {
                throw new RuntimeException(
                    'El código del producto no puede superar 50 caracteres.'
                );
            }

            if ($product->verificarCodigo($codigoRapido)) {
                throw new RuntimeException(
                    'Ya existe un producto con el código ' . $codigoRapido . '.'
                );
            }

            $idproductoRapido = (int)$product->insertar(
                $idcategoriaRapida,
                $idsubcategoriaRapida > 0 ? $idsubcategoriaRapida : null,
                $idmedidaRapida,
                $idalmacenRapido,
                $codigoRapido,
                $nombreRapido,
                (int)$stockRapido,
                $precioCompraRapido,
                $precioVentaRapido,
                'Creado desde el modal de venta rápida',
                'default.png'
            );

            if ($idproductoRapido <= 0) {
                throw new RuntimeException('No se pudo registrar el producto.');
            }

            $loteRapido = $conexion->getData(
                "SELECT
                    iddetalle_ingreso AS idingreso,
                    stock_venta AS stock,
                    precio_compra,
                    precio_venta
                 FROM detalle_ingreso
                 WHERE idarticulo = ?
                   AND stock_venta > 0
                   AND estado = 1
                   AND stock_estado = 1
                 ORDER BY iddetalle_ingreso DESC
                 LIMIT 1",
                [$idproductoRapido]
            );

            if (!$loteRapido) {
                throw new RuntimeException(
                    'El producto fue creado, pero no se encontró su stock inicial disponible.'
                );
            }

            responderProductoJson(
                true,
                'Producto registrado y agregado al pedido.',
                [
                    'idingreso' => (int)$loteRapido['idingreso'],
                    'idarticulo' => $idproductoRapido,
                    'idcategoria' => $idcategoriaRapida,
                    'idsubcategoria' => $idsubcategoriaRapida > 0
                        ? $idsubcategoriaRapida
                        : null,
                    'idmedida' => $idmedidaRapida,
                    'idalmacen' => $idalmacenRapido,
                    'codigo' => $codigoRapido,
                    'nombre' => $nombreRapido,
                    'precio_compra' => (float)$loteRapido['precio_compra'],
                    'precio_venta' => (float)$loteRapido['precio_venta'],
                    'stock' => (int)$loteRapido['stock'],
                    'categoria' => (string)($categoriaRapida['nombre'] ?? ''),
                    'subcategoria' => (string)($subcategoriaRapida['nombre'] ?? ''),
                    'medida' => trim(
                        (string)($medidaRapida['nombre'] ?? '') .
                            ' (' . (string)($medidaRapida['codigo'] ?? '') . ')'
                    ),
                    'almacen' => (string)($almacenRapido['nombre'] ?? ''),
                    'imagen' => 'default.png'
                ]
            );
        } catch (Throwable $error) {
            error_log('[PRODUCTO RÁPIDO] ' . $error->getMessage());

            responderProductoJson(
                false,
                $error->getMessage(),
                null,
                400
            );
        }

        break;

    case 'guardaryeditar':

        /* Generar código antes de verificar duplicados. */
        if (empty($codigo)) {
            $codigo = 'VAR-' . uniqid();
        }

        $rspta = $product->verificarCodigo($codigo);

        if (empty($idarticulo)) {
            if (empty($rspta['codigo'])) {

                $archivoImagen = $_FILES['imagen'] ?? null;

                if (
                    !$archivoImagen
                    || !isset($archivoImagen['tmp_name'])
                    || !file_exists($archivoImagen['tmp_name'])
                    || !is_uploaded_file($archivoImagen['tmp_name'])
                ) {
                    $imagen = empty($_POST['imagenactual'])
                        ? 'default.png'
                        : $_POST['imagenactual'];
                } else {
                    if (
                        !empty($_POST['imagenactual'])
                        && $_POST['imagenactual'] !== 'default.png'
                    ) {
                        $rutaImagenAnterior =
                            '../Assets/img/products/' . $_POST['imagenactual'];

                        if (is_file($rutaImagenAnterior)) {
                            unlink($rutaImagenAnterior);
                        }
                    }

                    $ext = explode('.', $archivoImagen['name']);

                    if (
                        in_array(
                            $archivoImagen['type'],
                            ['image/jpg', 'image/jpeg', 'image/png'],
                            true
                        )
                    ) {
                        $imagen = round(microtime(true)) . '.' . end($ext);

                        move_uploaded_file(
                            $archivoImagen['tmp_name'],
                            '../Assets/img/products/' . $imagen
                        );
                    }
                }

                $idproducto = $product->insertar(
                    $idcategoria,
                    $idsubcategoria,
                    $idmedida,
                    $idalmacen,
                    $codigo,
                    $nombre,
                    $stock,
                    $precio_compra,
                    $precio_venta,
                    $descripcion,
                    $imagen
                );

                if (isset($_POST['variaciones_json'])) {
                    $variaciones = json_decode(
                        $_POST['variaciones_json'],
                        true
                    );

                    if (!is_array($variaciones)) {
                        $variaciones = [];
                    }

                    $variacionesValidas = array_filter(
                        $variaciones,
                        function ($v) {
                            return ($v['stock'] ?? 0) > 0
                                && ($v['precio_compra'] ?? 0) > 0
                                && ($v['precio_venta'] ?? 0) > 0;
                        }
                    );

                    if (count($variacionesValidas) > 0) {
                        $idusuario = $_SESSION['idusuario'] ?? 1;
                        $idproveedor = 1;
                        $num = str_pad(
                            (string)rand(1, 9999999),
                            7,
                            '0',
                            STR_PAD_LEFT
                        );

                        $totalCompra = array_sum(
                            array_map(
                                function ($v) {
                                    return $v['stock']
                                        * $v['precio_compra'];
                                },
                                $variacionesValidas
                            )
                        );

                        $sqlIngreso = "INSERT INTO ingreso
                            (idproveedor, idusuario, tipo_comprobante,
                             serie_comprobante, num_comprobante, fecha_hora,
                             impuesto, total_compra, estado)
                            VALUES (?, ?, 'Stock Inicial', 'INI', ?, NOW(),
                                    0, ?, 'Aceptado')";

                        $idIngreso = $product->ejecutarSQLReturnId(
                            $sqlIngreso,
                            [
                                $idproveedor,
                                $idusuario,
                                $num,
                                $totalCompra
                            ]
                        );
                    }

                    foreach ($variaciones as $var) {
                        $combinacion = $var['combinacion'] ?? '';
                        $sku = $var['sku'] ?? '';
                        $stock_var = $var['stock'] ?? 0;
                        $precio_compra_var = $var['precio_compra'] ?? 0;
                        $precio_venta_var = $var['precio_venta'] ?? 0;

                        $product->insertarVariacion(
                            $idproducto,
                            $combinacion,
                            $sku,
                            $stock_var,
                            $precio_compra_var,
                            $precio_venta_var
                        );

                        if (
                            isset($idIngreso)
                            && $stock_var > 0
                            && $precio_compra_var > 0
                            && $precio_venta_var > 0
                        ) {
                            $sqlDetalle = "INSERT INTO detalle_ingreso
                                (idarticulo, idingreso, cantidad, stock_venta,
                                 precio_compra, precio_venta, estado,
                                 stock_estado)
                                VALUES (?, ?, ?, ?, ?, ?, 1, 1)";

                            $product->ejecutarSQL(
                                $sqlDetalle,
                                [
                                    $idproducto,
                                    $idIngreso,
                                    $stock_var,
                                    $stock_var,
                                    $precio_compra_var,
                                    $precio_venta_var
                                ]
                            );

                            $sqlKardex = "INSERT INTO kardex
                                (iddetalle, idarticulo, fecha, detalle,
                                 cantidadi, costoui, totali,
                                 cantidads, costous, totals,
                                 cantidadex, costouex, totalex,
                                 tipo, estado)
                                VALUES (?, ?, NOW(), ?, ?, ?, ?,
                                        0, 0, 0, ?, ?, ?,
                                        'Ingreso', 'Activo')";

                            $detalle = 'Stock Inicial INI-'
                                . $num
                                . ' ('
                                . $combinacion
                                . ')';

                            $total = $stock_var * $precio_compra_var;

                            $product->ejecutarSQL(
                                $sqlKardex,
                                [
                                    $idIngreso,
                                    $idproducto,
                                    $detalle,
                                    $stock_var,
                                    $precio_compra_var,
                                    $total,
                                    $stock_var,
                                    $precio_compra_var,
                                    $total
                                ]
                            );
                        }
                    }
                }

                echo $idproducto
                    ? 'Datos registrados correctamente'
                    : 'No se pudo registrar los datos';
            } else {
                echo "No se puede registrar...! \n código de producto duplicado";
            }
        }

        break;

    case 'desactivar':
        $rspta = $product->desactivar($idarticulo);
        echo $rspta
            ? 'Datos desactivados correctamente'
            : 'No se pudo desactivar los datos';
        break;

    case 'activar':
        $rspta = $product->activar($idarticulo);
        echo $rspta
            ? 'Datos activados correctamente'
            : 'No se pudo activar los datos';
        break;

    case 'mostrar':
        $rspta = $product->mostrar($idarticulo);
        echo json_encode($rspta);
        break;

    case 'listar':
        $rspta = $product->listar();
        $data = [];

        foreach ($rspta as $reg) {
            $stockcolor = '';

            if ($reg['stock'] <= 10) {
                $stockcolor = '<button class="btn btn-danger btn-sm">'
                    . $reg['stock']
                    . '</button>';
            } elseif ($reg['stock'] > 10 && $reg['stock'] < 30) {
                $stockcolor = '<button class="btn btn-warning btn-sm">'
                    . $reg['stock']
                    . '</button>';
            } elseif ($reg['stock'] >= 30) {
                $stockcolor = '<button class="btn btn-success btn-sm">'
                    . $reg['stock']
                    . '</button>';
            }

            $data[] = [
                '0' => $reg['codigo'],
                '1' => $reg['nombre']
                    . '<br><span style="font-size:0.95em; color:#888;">('
                    . ($reg['almacen'] ?? 'Sin almacén')
                    . ')</span>',
                '2' => $reg['categoria'],
                '3' => $reg['subcategoria'],
                '4' => $reg['medida'],
                '5' => $stockcolor,
                '6' => !empty($reg['imagen'])
                    ? "<img src='Assets/img/products/"
                    . $reg['imagen']
                    . "' height='50px'>"
                    : 'Sin imagen',
                '7' => $reg['precio_compra']
                    ? $reg['precio_compra']
                    : '<a href="buy"><button class="btn btn-warning btn-sm"><i class="fas fa-plus"></i></button></a>',
                '8' => $reg['precio_venta']
                    ? $reg['precio_venta']
                    : '<a href="buy"><button class="btn btn-warning btn-sm"><i class="fas fa-plus"></i></button></a>',
                '9' => $reg['condicion']
                    ? '<div class="badge badge-success">Aceptado</div>'
                    : '<div class="badge badge-danger">Desactivado</div>',
                '10' => $reg['condicion']
                    ? '<button class="btn btn-warning btn-sm" onclick="mostrar('
                    . $reg['idarticulo']
                    . ')"><i class="fas fa-pencil-alt"></i></button> '
                    . '<button class="btn btn-danger btn-sm" onclick="desactivar('
                    . $reg['idarticulo']
                    . ')"><i class="fas fa-times"></i></button>'
                    : '<button class="btn btn-warning btn-sm" onclick="mostrar('
                    . $reg['idarticulo']
                    . ')"><i class="fas fa-pencil-alt"></i></button> '
                    . '<button class="btn btn-primary btn-sm" onclick="activar('
                    . $reg['idarticulo']
                    . ')"><i class="fas fa-check"></i></button>'
            ];
        }

        $results = [
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data
        ];

        echo json_encode($results);
        break;

    case 'selectArticulo':
        $rspta = $product->select();
        echo '<option value="">Seleccione...</option>';

        foreach ($rspta as $reg) {
            echo '<option value="'
                . $reg['idarticulo']
                . '">'
                . $reg['nombre']
                . '</option>';
        }
        break;

    case 'listar_json':
        $rspta = $product->listarActivosVenta();
        echo json_encode($rspta);
        break;

    case 'subirMasivo':
        if (
            isset($_FILES['archivo_productos'])
            && $_FILES['archivo_productos']['error'] === UPLOAD_ERR_OK
        ) {
            $nombreTmp = $_FILES['archivo_productos']['tmp_name'];
            $resultados = $product->cargarMasivoDesdeCSV($nombreTmp);

            echo json_encode([
                'success' => true,
                'exitosos' => $resultados['exitosos'] ?? [],
                'errores' => $resultados['errores'] ?? []
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'mensaje' => 'No se recibió ningún archivo válido.'
            ]);
        }
        break;

    case 'listar_json_todo':
        $productosSimples = $product->listarActivosVenta();
        echo json_encode($productosSimples);
        break;

    case 'variaciones_por_articulo':
        if (isset($_POST['idarticulo'])) {
            $id = $_POST['idarticulo'];
            $variaciones = $product->listarVariacionesPorArticulo($id);
            echo json_encode($variaciones);
        } else {
            echo json_encode([]);
        }
        break;
}
