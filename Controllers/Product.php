<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('America/Lima');

require_once __DIR__ . '/../Models/Product.php';
require_once __DIR__ . '/../Libraries/MediaStorage.php';

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


/**
 * Obtiene y valida la configuración tributaria de un producto.
 */
function obtenerDatosTributariosProducto(
    Conexion $conexion,
    array $fuente = []
): array {
    $empresa = $conexion->getData(
        "SELECT
            codigo_afectacion_igv_predeterminado,
            porcentaje_igv_predeterminado,
            unidad_medida_sunat_predeterminada
         FROM datos_negocio
         WHERE condicion = 1
         ORDER BY id_negocio DESC
         LIMIT 1"
    );

    $afectacion = trim((string)(
        $fuente['codigo_afectacion_igv']
        ?? $empresa['codigo_afectacion_igv_predeterminado']
        ?? '10'
    ));

    if (!in_array($afectacion, ['10', '20', '30', '40'], true)) {
        throw new RuntimeException('La afectación al IGV del producto no es válida.');
    }

    $porcentaje = round((float)(
        $fuente['porcentaje_igv']
        ?? $empresa['porcentaje_igv_predeterminado']
        ?? 18
    ), 2);

    if ($afectacion === '10') {
        if ($porcentaje <= 0 || $porcentaje > 100) {
            throw new RuntimeException('La tasa de IGV del producto no es válida.');
        }
    } else {
        $porcentaje = 0.00;
    }

    $unidad = strtoupper(trim((string)(
        $fuente['unidad_medida_sunat']
        ?? $empresa['unidad_medida_sunat_predeterminada']
        ?? 'NIU'
    )));

    if (!preg_match('/^[A-Z0-9]{2,3}$/', $unidad)) {
        throw new RuntimeException('La unidad SUNAT del producto no es válida.');
    }

    $codigoSunat = strtoupper(trim((string)(
        $fuente['codigo_producto_sunat']
        ?? ''
    )));

    if (
        $codigoSunat !== ''
        && !preg_match('/^[A-Z0-9._-]{4,16}$/', $codigoSunat)
    ) {
        throw new RuntimeException('El código de producto SUNAT no es válido.');
    }

    return [
        'codigo_afectacion_igv' => $afectacion,
        'porcentaje_igv' => $porcentaje,
        'unidad_medida_sunat' => $unidad,
        'codigo_producto_sunat' => $codigoSunat !== '' ? $codigoSunat : null
    ];
}


/**
 * Permiso base del módulo Productos/Almacén.
 */
function productoPuedeImportarMasivo(): bool
{
    return isset($_SESSION['nombre']) && (int)($_SESSION['almacen'] ?? 0) === 1;
}

function normalizarTextoMasivoProducto($valor): string
{
    $texto = trim((string)$valor);
    $texto = strtr($texto, [
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n'
    ]);
    $texto = function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
    $texto = preg_replace('/\s+/u', ' ', $texto);
    return trim((string)$texto);
}

function catalogosMasivosProducto(Conexion $conexion): array
{
    $categorias = $conexion->getDataAll(
        "SELECT idcategoria, nombre FROM categoria WHERE condicion = 1 ORDER BY nombre ASC"
    );

    $subcategorias = $conexion->getDataAll(
        "SELECT s.idsubcategoria, s.idcategoria, s.nombre, c.nombre AS categoria
         FROM subcategoria s
         LEFT JOIN categoria c ON c.idcategoria = s.idcategoria
         WHERE s.estado = 1
         ORDER BY c.nombre ASC, s.nombre ASC"
    );

    $almacenes = $conexion->getDataAll(
        "SELECT idalmacen, nombre FROM almacen WHERE estado = 1
         ORDER BY CASE WHEN UPPER(nombre) LIKE '%PRINCIPAL%' THEN 0 ELSE 1 END, nombre ASC"
    );

    $medidas = $conexion->getDataAll(
        "SELECT idmedida, codigo, nombre FROM medida WHERE condicion = 1
         ORDER BY CASE WHEN UPPER(codigo) = 'NIU' THEN 0 ELSE 1 END, nombre ASC"
    );

    return [
        'categorias' => is_array($categorias) ? $categorias : [],
        'subcategorias' => is_array($subcategorias) ? $subcategorias : [],
        'almacenes' => is_array($almacenes) ? $almacenes : [],
        'medidas' => is_array($medidas) ? $medidas : []
    ];
}

function idDesdeValorCatalogoMasivo($valor, array $items, string $idKey, array $camposNombre): int
{
    $texto = trim((string)$valor);
    if ($texto === '') {
        return 0;
    }

    if (preg_match('/^\s*(\d+)\s*(?:-|$)/', $texto, $coincidencia)) {
        $id = (int)$coincidencia[1];
        foreach ($items as $item) {
            if ((int)($item[$idKey] ?? 0) === $id) {
                return $id;
            }
        }
    }

    if (ctype_digit($texto)) {
        $id = (int)$texto;
        foreach ($items as $item) {
            if ((int)($item[$idKey] ?? 0) === $id) {
                return $id;
            }
        }
    }

    $objetivo = normalizarTextoMasivoProducto($texto);

    foreach ($items as $item) {
        $id = (int)($item[$idKey] ?? 0);
        $candidatos = [];
        foreach ($camposNombre as $campo) {
            if (isset($item[$campo]) && trim((string)$item[$campo]) !== '') {
                $candidatos[] = (string)$item[$campo];
            }
        }

        if (isset($item['nombre'])) {
            $candidatos[] = $id . ' - ' . $item['nombre'];
        }
        if (isset($item['nombre'], $item['codigo'])) {
            $candidatos[] = $item['nombre'] . ' (' . $item['codigo'] . ')';
            $candidatos[] = $id . ' - ' . $item['nombre'] . ' (' . $item['codigo'] . ')';
        }
        if (isset($item['nombre'], $item['categoria'])) {
            $candidatos[] = $item['nombre'] . ' · ' . $item['categoria'];
            $candidatos[] = $id . ' - ' . $item['nombre'] . ' · ' . $item['categoria'];
        }

        foreach ($candidatos as $candidato) {
            if (normalizarTextoMasivoProducto($candidato) === $objetivo) {
                return $id;
            }
        }
    }

    return 0;
}

function normalizarCabeceraMasivaProducto($valor): string
{
    return preg_replace('/[^a-z0-9]+/', '', normalizarTextoMasivoProducto($valor));
}

function mapaCabecerasMasivasProducto(array $cabeceras): array
{
    $alias = [
        'nombre' => ['nombre', 'producto', 'nombreproducto'],
        'codigo' => ['codigo', 'sku', 'codigoproducto'],
        'stock' => ['stock', 'existencia', 'existencias', 'cantidad'],
        'precio_compra' => ['preciocompra', 'costocompra', 'costo', 'pcompra'],
        'precio_venta' => ['precioventa', 'venta', 'pventa'],
        'categoria' => ['categoria', 'idcategoria'],
        'subcategoria' => ['subcategoria', 'idsubcategoria'],
        'almacen' => ['almacen', 'idalmacen'],
        'medida' => ['medida', 'idmedida', 'unidad', 'unidaddemedida', 'unidadmedida']
    ];

    $resultado = [];
    foreach ($cabeceras as $indice => $cabecera) {
        $normalizada = normalizarCabeceraMasivaProducto($cabecera);
        foreach ($alias as $campo => $opciones) {
            if (in_array($normalizada, $opciones, true)) {
                $resultado[$campo] = $indice;
                break;
            }
        }
    }

    return $resultado;
}

function matrizAFilasMasivasProducto(array $matriz): array
{
    if (!$matriz) {
        return [];
    }

    $cabeceras = array_shift($matriz);
    $mapa = mapaCabecerasMasivasProducto(is_array($cabeceras) ? $cabeceras : []);
    $campos = ['nombre','codigo','stock','precio_compra','precio_venta','categoria','subcategoria','almacen','medida'];

    if (count($mapa) < 4) {
        // Compatibilidad con la plantilla histórica de 9 columnas en orden fijo.
        $mapa = array_combine($campos, range(0, 8));
        array_unshift($matriz, $cabeceras);
    }

    $filas = [];
    foreach ($matriz as $fila) {
        if (!is_array($fila)) {
            continue;
        }

        $item = [];
        foreach ($campos as $campo) {
            $indice = $mapa[$campo] ?? null;
            $item[$campo] = $indice !== null && array_key_exists($indice, $fila)
                ? trim((string)$fila[$indice])
                : '';
        }

        $hayDatos = false;
        foreach ($item as $valor) {
            if (trim((string)$valor) !== '') {
                $hayDatos = true;
                break;
            }
        }

        if ($hayDatos) {
            $filas[] = $item;
        }

        if (count($filas) >= 1000) {
            break;
        }
    }

    return $filas;
}

function detectarDelimitadorCsvProducto(string $ruta): string
{
    $linea = '';
    $handle = @fopen($ruta, 'rb');
    if ($handle) {
        $linea = (string)fgets($handle);
        fclose($handle);
    }

    $candidatos = [',' => 0, ';' => 0, "\t" => 0];
    foreach (array_keys($candidatos) as $delimitador) {
        $candidatos[$delimitador] = count(str_getcsv($linea, $delimitador));
    }
    arsort($candidatos);
    return (string)array_key_first($candidatos);
}

function leerCsvMasivoProducto(string $ruta): array
{
    $delimitador = detectarDelimitadorCsvProducto($ruta);
    $handle = @fopen($ruta, 'rb');
    if (!$handle) {
        throw new RuntimeException('No se pudo abrir el archivo CSV.');
    }

    $matriz = [];
    while (($data = fgetcsv($handle, 0, $delimitador)) !== false) {
        if (!$matriz && isset($data[0])) {
            $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$data[0]);
        }
        $matriz[] = $data;
        if (count($matriz) > 1001) {
            break;
        }
    }
    fclose($handle);

    return matrizAFilasMasivasProducto($matriz);
}

function columnaExcelAIndiceProducto(string $referencia): int
{
    if (!preg_match('/^([A-Z]+)/i', $referencia, $m)) {
        return 0;
    }
    $letras = strtoupper($m[1]);
    $indice = 0;
    for ($i = 0, $l = strlen($letras); $i < $l; $i++) {
        $indice = $indice * 26 + (ord($letras[$i]) - 64);
    }
    return max(0, $indice - 1);
}

function leerEntradaPharProducto(string $archivoZip, string $rutaInterna): ?string
{
    try {
        $ruta = 'phar://' . $archivoZip . '/' . $rutaInterna;
        if (!file_exists($ruta)) {
            return null;
        }
        $contenido = @file_get_contents($ruta);
        return $contenido === false ? null : $contenido;
    } catch (Throwable $e) {
        return null;
    }
}

function textoXmlCeldaProducto(string $fragmento): string
{
    $partes = [];
    if (preg_match_all('/<(?:[A-Za-z0-9_]+:)?t\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?t>/si', $fragmento, $matches)) {
        foreach ($matches[1] as $texto) {
            $partes[] = html_entity_decode(strip_tags((string)$texto), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
    }
    return implode('', $partes);
}

function atributoXmlProducto(string $atributos, string $nombre): string
{
    if (preg_match('/(?:^|\s)' . preg_quote($nombre, '/') . '="([^"]*)"/i', $atributos, $m)) {
        return html_entity_decode((string)$m[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
    return '';
}

function leerXlsxMasivoProducto(string $ruta): array
{
    if (!class_exists('PharData')) {
        throw new RuntimeException('El servidor no tiene habilitado el lector necesario para archivos XLSX.');
    }

    // Phar necesita una extensión ZIP reconocible para leer el contenedor OpenXML.
    $tmpZip = sys_get_temp_dir() . '/tp_xlsx_' . bin2hex(random_bytes(8)) . '.zip';
    if (!@copy($ruta, $tmpZip)) {
        throw new RuntimeException('No se pudo preparar el archivo XLSX para su lectura.');
    }

    try {
        $sharedStrings = [];
        $xmlShared = leerEntradaPharProducto($tmpZip, 'xl/sharedStrings.xml');
        if ($xmlShared && preg_match_all('/<(?:[A-Za-z0-9_]+:)?si\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?si>/si', $xmlShared, $siMatches)) {
            foreach ($siMatches[1] as $si) {
                $sharedStrings[] = textoXmlCeldaProducto((string)$si);
            }
        }

        $xmlSheet = leerEntradaPharProducto($tmpZip, 'xl/worksheets/sheet1.xml');
        if (!$xmlSheet) {
            throw new RuntimeException('El Excel no contiene una primera hoja legible.');
        }

        $matriz = [];
        if (!preg_match_all('/<(?:[A-Za-z0-9_]+:)?row\b([^>]*)>(.*?)<\/(?:[A-Za-z0-9_]+:)?row>/si', $xmlSheet, $rowMatches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($rowMatches as $rowMatch) {
            $fila = [];
            $rowBody = (string)($rowMatch[2] ?? '');

            if (preg_match_all('/<(?:[A-Za-z0-9_]+:)?c\b([^>]*)>(.*?)<\/(?:[A-Za-z0-9_]+:)?c>/si', $rowBody, $cellMatches, PREG_SET_ORDER)) {
                foreach ($cellMatches as $cellMatch) {
                    $atributos = (string)($cellMatch[1] ?? '');
                    $body = (string)($cellMatch[2] ?? '');
                    $ref = atributoXmlProducto($atributos, 'r');
                    $tipo = atributoXmlProducto($atributos, 't');
                    $indice = columnaExcelAIndiceProducto($ref);
                    $valor = '';

                    if ($tipo === 'inlineStr') {
                        $valor = textoXmlCeldaProducto($body);
                    } else {
                        $crudo = '';
                        if (preg_match('/<(?:[A-Za-z0-9_]+:)?v\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?v>/si', $body, $vMatch)) {
                            $crudo = html_entity_decode(strip_tags((string)$vMatch[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                        }

                        if ($tipo === 's' && $crudo !== '' && isset($sharedStrings[(int)$crudo])) {
                            $valor = $sharedStrings[(int)$crudo];
                        } elseif ($tipo === 'b') {
                            $valor = $crudo === '1' ? '1' : '0';
                        } elseif ($tipo === 'str') {
                            $valor = $crudo;
                        } else {
                            $valor = $crudo;
                        }
                    }

                    $fila[$indice] = $valor;
                }
            }

            if ($fila) {
                $max = max(array_keys($fila));
                $normalizada = [];
                for ($i = 0; $i <= $max; $i++) {
                    $normalizada[] = $fila[$i] ?? '';
                }
                $matriz[] = $normalizada;
            }

            if (count($matriz) > 1001) {
                break;
            }
        }

        return matrizAFilasMasivasProducto($matriz);
    } finally {
        @unlink($tmpZip);
    }
}

function leerArchivoMasivoProducto(string $ruta, string $nombreOriginal): array
{
    $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    if ($extension === 'csv') {
        return leerCsvMasivoProducto($ruta);
    }
    if ($extension === 'xlsx') {
        return leerXlsxMasivoProducto($ruta);
    }
    throw new RuntimeException('Solo se permiten archivos .csv o .xlsx.');
}

function xmlProducto($valor): string
{
    return htmlspecialchars((string)$valor, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function columnaExcelProducto(int $numero): string
{
    $resultado = '';
    while ($numero > 0) {
        $numero--;
        $resultado = chr(65 + ($numero % 26)) . $resultado;
        $numero = intdiv($numero, 26);
    }
    return $resultado;
}

function xmlHojaListasProducto(array $catalogos): string
{
    $columnas = [
        'A' => array_map(fn($x) => $x['idcategoria'] . ' - ' . $x['nombre'], $catalogos['categorias']),
        'B' => array_map(fn($x) => $x['idsubcategoria'] . ' - ' . $x['nombre'] . (!empty($x['categoria']) ? ' · ' . $x['categoria'] : ''), $catalogos['subcategorias']),
        'C' => array_map(fn($x) => $x['idalmacen'] . ' - ' . $x['nombre'], $catalogos['almacenes']),
        'D' => array_map(fn($x) => $x['idmedida'] . ' - ' . $x['nombre'] . (!empty($x['codigo']) ? ' (' . $x['codigo'] . ')' : ''), $catalogos['medidas'])
    ];

    $titulos = ['A' => 'Categorias', 'B' => 'Subcategorias', 'C' => 'Almacenes', 'D' => 'Unidades'];
    $max = max(array_merge([1], array_values(array_map('count', $columnas))));
    $rows = '';

    for ($r = 1; $r <= $max + 1; $r++) {
        $cells = '';
        foreach (['A', 'B', 'C', 'D'] as $col) {
            $valor = $r === 1 ? $titulos[$col] : ($columnas[$col][$r - 2] ?? '');
            if ($valor === '') {
                continue;
            }
            $estilo = $r === 1 ? ' s="1"' : '';
            $cells .= '<c r="' . $col . $r . '" t="inlineStr"' . $estilo . '><is><t>' . xmlProducto($valor) . '</t></is></c>';
        }
        if ($cells !== '') {
            $rows .= '<row r="' . $r . '">' . $cells . '</row>';
        }
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetPr><outlinePr summaryBelow="1" summaryRight="1"/><pageSetUpPr/></sheetPr>'
        . '<dimension ref="A1:D' . ($max + 1) . '"/>'
        . '<sheetViews><sheetView workbookViewId="0"><selection activeCell="A1" sqref="A1"/></sheetView></sheetViews>'
        . '<sheetFormatPr baseColWidth="8" defaultRowHeight="15"/>'
        . '<cols><col min="1" max="4" width="34" customWidth="1"/></cols>'
        . '<sheetData>' . $rows . '</sheetData>'
        . '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
        . '</worksheet>';
}

function xmlHojaProductosPlantilla(array $catalogos): string
{
    $headers = ['Producto', 'SKU', 'Stock', 'PrecioCompra', 'PrecioVenta', 'Categoria', 'Subcategoria', 'Almacen', 'UnidadMedida'];
    $cells = '';
    foreach ($headers as $i => $header) {
        $col = columnaExcelProducto($i + 1);
        $cells .= '<c r="' . $col . '1" t="inlineStr" s="1"><is><t>' . xmlProducto($header) . '</t></is></c>';
    }

    $validaciones = [];
    if (count($catalogos['categorias'])) {
        $validaciones[] = '<dataValidation type="list" allowBlank="0" showDropDown="0" showInputMessage="1" showErrorMessage="1" errorStyle="stop" promptTitle="Categoría" prompt="Selecciona ID + nombre." errorTitle="Categoría inválida" error="Selecciona una categoría de la lista." sqref="F2:F501"><formula1>CategoriasLista</formula1></dataValidation>';
    }
    if (count($catalogos['subcategorias'])) {
        $validaciones[] = '<dataValidation type="list" allowBlank="1" showDropDown="0" showInputMessage="1" showErrorMessage="1" errorStyle="stop" promptTitle="Subcategoría" prompt="Selecciona ID + nombre." errorTitle="Subcategoría inválida" error="Selecciona una subcategoría de la lista." sqref="G2:G501"><formula1>SubcategoriasLista</formula1></dataValidation>';
    }
    if (count($catalogos['almacenes'])) {
        $validaciones[] = '<dataValidation type="list" allowBlank="0" showDropDown="0" showInputMessage="1" showErrorMessage="1" errorStyle="stop" promptTitle="Almacén" prompt="Selecciona ID + nombre." errorTitle="Almacén inválido" error="Selecciona un almacén de la lista." sqref="H2:H501"><formula1>AlmacenesLista</formula1></dataValidation>';
    }
    if (count($catalogos['medidas'])) {
        $validaciones[] = '<dataValidation type="list" allowBlank="0" showDropDown="0" showInputMessage="1" showErrorMessage="1" errorStyle="stop" promptTitle="Unidad" prompt="Selecciona ID + nombre." errorTitle="Unidad inválida" error="Selecciona una unidad de la lista." sqref="I2:I501"><formula1>MedidasLista</formula1></dataValidation>';
    }
    $validaciones[] = '<dataValidation type="whole" operator="greaterThanOrEqual" allowBlank="0" showErrorMessage="1" errorStyle="stop" errorTitle="Stock inválido" error="Ingresa un stock igual o mayor a cero." sqref="C2:C501"><formula1>0</formula1></dataValidation>';
    $validaciones[] = '<dataValidation type="decimal" operator="greaterThanOrEqual" allowBlank="0" showErrorMessage="1" errorStyle="stop" errorTitle="Precio inválido" error="Ingresa un precio igual o mayor a cero." sqref="D2:E501"><formula1>0</formula1></dataValidation>';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetPr><outlinePr summaryBelow="1" summaryRight="1"/><pageSetUpPr/></sheetPr>'
        . '<dimension ref="A1:I501"/>'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/></sheetView></sheetViews>'
        . '<sheetFormatPr baseColWidth="8" defaultRowHeight="18"/>'
        . '<cols><col min="1" max="1" width="30" customWidth="1"/><col min="2" max="2" width="18" customWidth="1"/><col min="3" max="5" width="15" customWidth="1"/><col min="6" max="9" width="30" customWidth="1"/></cols>'
        . '<sheetData><row r="1" ht="24" customHeight="1">' . $cells . '</row></sheetData>'
        . '<autoFilter ref="A1:I501"/>'
        . '<dataValidations count="' . count($validaciones) . '">' . implode('', $validaciones) . '</dataValidations>'
        . '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
        . '</worksheet>';
}

/**
 * Crea un ZIP OpenXML sin depender de ZipArchive/PharData.
 * Esto evita fallos habituales de hosting al descargar la plantilla XLSX.
 */
function crearZipOpenXmlProducto(array $archivos, string $ruta): void
{
    $datosLocales = '';
    $directorioCentral = '';
    $entradas = 0;
    $ahora = getdate();
    $anio = max(1980, (int)$ahora['year']);
    $horaDos = (($ahora['hours'] & 0x1F) << 11) | (($ahora['minutes'] & 0x3F) << 5) | ((int)($ahora['seconds'] / 2) & 0x1F);
    $fechaDos = ((($anio - 1980) & 0x7F) << 9) | (($ahora['mon'] & 0x0F) << 5) | ($ahora['mday'] & 0x1F);

    foreach ($archivos as $nombre => $contenido) {
        $nombre = str_replace('\\', '/', (string)$nombre);
        $contenido = (string)$contenido;
        $crc = crc32($contenido);
        if ($crc < 0) {
            $crc += 4294967296;
        }

        $metodo = 0;
        $comprimido = $contenido;
        if (function_exists('gzdeflate')) {
            $tmp = gzdeflate($contenido, 6);
            if ($tmp !== false && strlen($tmp) < strlen($contenido)) {
                $metodo = 8;
                $comprimido = $tmp;
            }
        }

        $tamComprimido = strlen($comprimido);
        $tamOriginal = strlen($contenido);
        $tamNombre = strlen($nombre);
        $offset = strlen($datosLocales);
        $flags = 0x0800; // Nombres UTF-8.

        $datosLocales .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $metodo,
            $horaDos,
            $fechaDos,
            $crc,
            $tamComprimido,
            $tamOriginal,
            $tamNombre,
            0
        ) . $nombre . $comprimido;

        $directorioCentral .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            20,
            20,
            $flags,
            $metodo,
            $horaDos,
            $fechaDos,
            $crc,
            $tamComprimido,
            $tamOriginal,
            $tamNombre,
            0,
            0,
            0,
            0,
            0,
            $offset
        ) . $nombre;

        $entradas++;
    }

    $offsetCentral = strlen($datosLocales);
    $tamCentral = strlen($directorioCentral);
    $fin = pack(
        'VvvvvVVv',
        0x06054b50,
        0,
        0,
        $entradas,
        $entradas,
        $tamCentral,
        $offsetCentral,
        0
    );

    if (@file_put_contents($ruta, $datosLocales . $directorioCentral . $fin, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo crear el archivo Excel temporal.');
    }
}

function crearPlantillaXlsxProducto(array $catalogos, string $ruta): void
{
    $cantCat = max(1, count($catalogos['categorias']));
    $cantSub = max(1, count($catalogos['subcategorias']));
    $cantAlm = max(1, count($catalogos['almacenes']));
    $cantMed = max(1, count($catalogos['medidas']));
    $creado = gmdate('Y-m-d\\TH:i:s\\Z');

    $archivos = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
        'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Plantilla de productos TiquePOS</dc:title><dc:creator>TiquePOS</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . $creado . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $creado . '</dcterms:modified></cp:coreProperties>',
        'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>TiquePOS</Application><AppVersion>1.0</AppVersion></Properties>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><workbookPr/><bookViews><workbookView visibility="visible" minimized="0" showHorizontalScroll="1" showVerticalScroll="1" showSheetTabs="1" firstSheet="0" activeTab="0"/></bookViews><sheets><sheet name="Productos" sheetId="1" state="visible" r:id="rId1"/><sheet name="Listas" sheetId="2" state="hidden" r:id="rId2"/></sheets><definedNames><definedName name="CategoriasLista">\'Listas\'!$A$2:$A$' . ($cantCat + 1) . '</definedName><definedName name="SubcategoriasLista">\'Listas\'!$B$2:$B$' . ($cantSub + 1) . '</definedName><definedName name="AlmacenesLista">\'Listas\'!$C$2:$C$' . ($cantAlm + 1) . '</definedName><definedName name="MedidasLista">\'Listas\'!$D$2:$D$' . ($cantMed + 1) . '</definedName><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'Productos\'!$A$1:$I$501</definedName></definedNames><calcPr calcId="124519" fullCalcOnLoad="1"/></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="0"/><fonts count="2"><font><name val="Calibri"/><family val="2"/><sz val="11"/></font><font><b val="1"/><color rgb="FFFFFFFF"/><name val="Calibri"/><sz val="11"/></font></fonts><fills count="3"><fill><patternFill/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF00A46A"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles><tableStyles count="0" defaultTableStyle="TableStyleMedium9" defaultPivotStyle="PivotStyleLight16"/></styleSheet>',
        'xl/worksheets/sheet1.xml' => xmlHojaProductosPlantilla($catalogos),
        'xl/worksheets/sheet2.xml' => xmlHojaListasProducto($catalogos)
    ];

    crearZipOpenXmlProducto($archivos, $ruta);

    if (!is_file($ruta) || filesize($ruta) < 1000) {
        throw new RuntimeException('La plantilla Excel se generó incompleta.');
    }

    $cabecera = @file_get_contents($ruta, false, null, 0, 2);
    if ($cabecera !== 'PK') {
        @unlink($ruta);
        throw new RuntimeException('La plantilla Excel no tiene un contenedor OpenXML válido.');
    }
}


switch ($_GET['op'] ?? '') {

    /* =========================================================
       MINI EXCEL / IMPORTACIÓN MASIVA DE PRODUCTOS
       ========================================================= */
    case 'datosImportacion':
        if (!productoPuedeImportarMasivo()) {
            responderProductoJson(false, 'No tiene permiso para gestionar productos.', null, 403);
        }

        try {
            $conexionImportacion = new Conexion();
            responderProductoJson(
                true,
                'Catálogos cargados.',
                null,
                200,
                ['datos' => catalogosMasivosProducto($conexionImportacion)]
            );
        } catch (Throwable $error) {
            error_log('[CATALOGOS IMPORTACION] ' . $error->getMessage());
            responderProductoJson(false, 'No se pudieron cargar los catálogos de importación.', null, 500);
        }
        break;

    case 'descargarPlantillaCsv':
        if (!productoPuedeImportarMasivo()) {
            http_response_code(403);
            exit('No autorizado');
        }

        $nombreCsv = 'plantilla_productos.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nombreCsv . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');

        // BOM UTF-8 para conservar tildes/ñ correctamente en Excel.
        echo "\xEF\xBB\xBF";
        $salida = fopen('php://output', 'wb');
        fputcsv($salida, [
            'Producto', 'SKU', 'Stock', 'PrecioCompra', 'PrecioVenta',
            'Categoria', 'Subcategoria', 'Almacen', 'UnidadMedida'
        ], ',');
        fclose($salida);
        exit;

    case 'descargarPlantillaExcel':
        if (!productoPuedeImportarMasivo()) {
            http_response_code(403);
            exit('No autorizado');
        }

        try {
            $conexionPlantilla = new Conexion();
            $catalogosPlantilla = catalogosMasivosProducto($conexionPlantilla);
            $rutaTemporal = sys_get_temp_dir() . '/plantilla_productos_' . bin2hex(random_bytes(8)) . '.xlsx';
            crearPlantillaXlsxProducto($catalogosPlantilla, $rutaTemporal);

            $nombreExcel = 'plantilla_productos.xlsx';
            // Evita que espacios, warnings o buffers previos corrompan el contenedor XLSX.
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $nombreExcel . '"');
            header('Content-Transfer-Encoding: binary');
            header('Content-Length: ' . filesize($rutaTemporal));
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: public');
            header('X-Content-Type-Options: nosniff');
            readfile($rutaTemporal);
            @unlink($rutaTemporal);
            exit;
        } catch (Throwable $error) {
            error_log('[PLANTILLA XLSX] ' . $error->getMessage());
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            exit('No se pudo generar la plantilla Excel: ' . $error->getMessage());
        }

    case 'previsualizarMasivo':
        if (!productoPuedeImportarMasivo()) {
            responderProductoJson(false, 'No tiene permiso para importar productos.', null, 403);
        }

        if (
            !isset($_FILES['archivo_productos'])
            || $_FILES['archivo_productos']['error'] !== UPLOAD_ERR_OK
        ) {
            responderProductoJson(false, 'No se recibió un archivo válido.', null, 400);
        }

        try {
            $archivo = $_FILES['archivo_productos'];
            if ((int)($archivo['size'] ?? 0) > 8 * 1024 * 1024) {
                throw new RuntimeException('El archivo supera el máximo permitido de 8 MB.');
            }

            $nombreOriginal = basename((string)($archivo['name'] ?? ''));
            $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
            if (!in_array($extension, ['csv', 'xlsx'], true)) {
                throw new RuntimeException('Solo se permiten archivos CSV o XLSX.');
            }

            $filasArchivo = leerArchivoMasivoProducto(
                (string)$archivo['tmp_name'],
                $nombreOriginal
            );

            responderProductoJson(
                true,
                'Archivo preparado para revisión.',
                null,
                200,
                [
                    'filas' => $filasArchivo,
                    'total' => count($filasArchivo),
                    'tipo' => $extension
                ]
            );
        } catch (Throwable $error) {
            error_log('[PREVISUALIZAR IMPORTACION] ' . $error->getMessage());
            responderProductoJson(false, $error->getMessage(), null, 400);
        }
        break;

    case 'importarMasivoJson':
        if (!productoPuedeImportarMasivo()) {
            responderProductoJson(false, 'No tiene permiso para importar productos.', null, 403);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderProductoJson(false, 'Método no permitido.', null, 405);
        }

        try {
            $filas = json_decode((string)($_POST['filas'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($filas) || !$filas) {
                throw new RuntimeException('No hay filas para importar.');
            }
            if (count($filas) > 1000) {
                throw new RuntimeException('El máximo por importación es de 1000 productos.');
            }

            $conexionMasiva = new Conexion();
            $catalogos = catalogosMasivosProducto($conexionMasiva);
            $tributosMasivos = obtenerDatosTributariosProducto($conexionMasiva, []);
            $exitosos = [];
            $errores = [];
            $filasExitosas = [];
            $skuLote = [];

            foreach ($filas as $indice => $fila) {
                if (!is_array($fila)) {
                    $errores[] = 'Fila ' . ($indice + 1) . ': formato inválido.';
                    continue;
                }

                $filaCliente = trim((string)($fila['fila_cliente'] ?? ($indice + 1)));
                $nombreFila = preg_replace('/\s+/u', ' ', trim((string)($fila['nombre'] ?? '')));
                $codigoFila = trim((string)($fila['codigo'] ?? ''));
                $stockCrudo = trim((string)($fila['stock'] ?? '0'));
                $compraCruda = str_replace(',', '.', trim((string)($fila['precio_compra'] ?? '0')));
                $ventaCruda = str_replace(',', '.', trim((string)($fila['precio_venta'] ?? '0')));

                $prefijo = 'Fila ' . ($indice + 1) . ($codigoFila !== '' ? ' [' . $codigoFila . ']' : '') . ': ';

                if ($nombreFila === '' || $codigoFila === '') {
                    $errores[] = $prefijo . 'nombre y SKU son obligatorios.';
                    continue;
                }
                if (strlen($codigoFila) > 50) {
                    $errores[] = $prefijo . 'el SKU supera 50 caracteres.';
                    continue;
                }
                if (!preg_match('/^\d+$/', $stockCrudo)) {
                    $errores[] = $prefijo . 'el stock debe ser un número entero igual o mayor a 0.';
                    continue;
                }
                if (!is_numeric($compraCruda) || (float)$compraCruda < 0) {
                    $errores[] = $prefijo . 'el precio de compra no es válido.';
                    continue;
                }
                if (!is_numeric($ventaCruda) || (float)$ventaCruda <= 0) {
                    $errores[] = $prefijo . 'el precio de venta debe ser mayor a 0.';
                    continue;
                }

                $idCategoriaFila = idDesdeValorCatalogoMasivo(
                    $fila['categoria'] ?? $fila['idcategoria'] ?? '',
                    $catalogos['categorias'],
                    'idcategoria',
                    ['nombre']
                );
                $idSubcategoriaFila = idDesdeValorCatalogoMasivo(
                    $fila['subcategoria'] ?? $fila['idsubcategoria'] ?? '',
                    $catalogos['subcategorias'],
                    'idsubcategoria',
                    ['nombre']
                );
                $idAlmacenFila = idDesdeValorCatalogoMasivo(
                    $fila['almacen'] ?? $fila['idalmacen'] ?? '',
                    $catalogos['almacenes'],
                    'idalmacen',
                    ['nombre']
                );
                $idMedidaFila = idDesdeValorCatalogoMasivo(
                    $fila['medida'] ?? $fila['idmedida'] ?? '',
                    $catalogos['medidas'],
                    'idmedida',
                    ['nombre', 'codigo']
                );

                if ($idCategoriaFila <= 0) {
                    $errores[] = $prefijo . 'la categoría no existe o está desactivada.';
                    continue;
                }
                if ($idAlmacenFila <= 0) {
                    $errores[] = $prefijo . 'el almacén no existe o está desactivado.';
                    continue;
                }
                if ($idMedidaFila <= 0) {
                    $errores[] = $prefijo . 'la unidad de medida no existe o está desactivada.';
                    continue;
                }

                if ($idSubcategoriaFila > 0) {
                    $subValida = false;
                    foreach ($catalogos['subcategorias'] as $subItem) {
                        if (
                            (int)$subItem['idsubcategoria'] === $idSubcategoriaFila
                            && (int)$subItem['idcategoria'] === $idCategoriaFila
                        ) {
                            $subValida = true;
                            break;
                        }
                    }
                    if (!$subValida) {
                        $errores[] = $prefijo . 'la subcategoría no pertenece a la categoría elegida.';
                        continue;
                    }
                }

                $skuNormal = normalizarTextoMasivoProducto($codigoFila);
                if (isset($skuLote[$skuNormal])) {
                    $errores[] = $prefijo . 'el SKU está repetido dentro de esta importación.';
                    continue;
                }
                $skuLote[$skuNormal] = true;

                if ($product->verificarCodigo($codigoFila)) {
                    $errores[] = $prefijo . 'ya existe un producto con ese SKU.';
                    continue;
                }

                $resultadoFila = $product->insertarImportacionSegura(
                    $idCategoriaFila,
                    $idSubcategoriaFila > 0 ? $idSubcategoriaFila : null,
                    $idMedidaFila,
                    $idAlmacenFila,
                    $codigoFila,
                    $nombreFila,
                    (int)$stockCrudo,
                    (float)$compraCruda,
                    (float)$ventaCruda,
                    'Importado desde carga masiva',
                    'default.png',
                    $tributosMasivos['codigo_afectacion_igv'],
                    $tributosMasivos['porcentaje_igv'],
                    $tributosMasivos['unidad_medida_sunat'],
                    $tributosMasivos['codigo_producto_sunat']
                );

                if (!($resultadoFila['success'] ?? false)) {
                    $errores[] = $prefijo . 'no se pudo registrar. ' . trim((string)($resultadoFila['error'] ?? ''));
                    continue;
                }

                $exitosos[] = "{$codigoFila} · {$nombreFila}";
                $filasExitosas[] = $filaCliente;
            }

            responderProductoJson(
                true,
                $errores ? 'Importación terminada con observaciones.' : 'Importación completada.',
                null,
                200,
                [
                    'exitosos' => $exitosos,
                    'errores' => $errores,
                    'filas_exitosas' => $filasExitosas,
                    'total_exitosos' => count($exitosos),
                    'total_errores' => count($errores)
                ]
            );
        } catch (JsonException $error) {
            responderProductoJson(false, 'Los datos enviados para importar no son válidos.', null, 400);
        } catch (Throwable $error) {
            error_log('[IMPORTACION MASIVA JSON] ' . $error->getMessage());
            responderProductoJson(false, $error->getMessage(), null, 400);
        }
        break;


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

            $tributosProductoRapido = obtenerDatosTributariosProducto(
                $conexion,
                []
            );

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
                'default.png',
                $tributosProductoRapido['codigo_afectacion_igv'],
                $tributosProductoRapido['porcentaje_igv'],
                $tributosProductoRapido['unidad_medida_sunat'],
                $tributosProductoRapido['codigo_producto_sunat']
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
                    'imagen' => 'default.png',
                    'codigo_afectacion_igv' => $tributosProductoRapido['codigo_afectacion_igv'],
                    'porcentaje_igv' => $tributosProductoRapido['porcentaje_igv'],
                    'unidad_medida_sunat' => $tributosProductoRapido['unidad_medida_sunat'],
                    'codigo_producto_sunat' => $tributosProductoRapido['codigo_producto_sunat']
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

        $idarticulo = (int)($_POST['idarticulo'] ?? 0);
        $idcategoria = (int)($_POST['idcategoria'] ?? 0);
        $idsubcategoria = (int)($_POST['idsubcategoria'] ?? 0);
        $idmedida = (int)($_POST['idmedida'] ?? 0);
        $idalmacen = (int)($_POST['idalmacen'] ?? 0);

        $codigo = trim((string)($_POST['codigo'] ?? ''));
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $stock = max(0, (int)($_POST['stock'] ?? 0));
        $precio_compra = max(0, (float)($_POST['precio_compra'] ?? 0));
        $precio_venta = max(0, (float)($_POST['precio_venta'] ?? 0));
        $descripcion = trim((string)($_POST['descripcion'] ?? ''));

        try {
            $conexionTributaria = new Conexion();
            $tributosProducto = obtenerDatosTributariosProducto(
                $conexionTributaria,
                $_POST
            );
        } catch (Throwable $errorTributario) {
            echo $errorTributario->getMessage();
            break;
        }

        if ($idcategoria <= 0) {
            echo 'Debe seleccionar una categoría';
            break;
        }

        if ($idmedida <= 0) {
            echo 'Debe seleccionar una unidad de medida';
            break;
        }

        if ($idalmacen <= 0) {
            echo 'Debe seleccionar un almacén';
            break;
        }

        if ($nombre === '') {
            echo 'Debe ingresar el nombre del producto';
            break;
        }

        if ($codigo === '') {
            $codigo = 'VAR-' . uniqid();
        }

        /*
             * Permitir el mismo código cuando pertenece
             * al producto que estamos editando.
             */
        $productoConCodigo = $product->verificarCodigo($codigo);

        if (
            !empty($productoConCodigo)
            && (int)($productoConCodigo['idarticulo'] ?? 0) !== $idarticulo
        ) {
            echo 'No se puede guardar. El código del producto ya existe';
            break;
        }

        $imagenActual = basename(
            trim((string)($_POST['imagenactual'] ?? 'default.png'))
        );

        if ($imagenActual === '') {
            $imagenActual = 'default.png';
        }

        $imagen = $imagenActual;
        $archivoImagen = $_FILES['imagen'] ?? null;

        if (
            $archivoImagen
            && isset($archivoImagen['error'])
            && $archivoImagen['error'] === UPLOAD_ERR_OK
            && isset($archivoImagen['tmp_name'])
            && is_uploaded_file($archivoImagen['tmp_name'])
        ) {
            $tiposPermitidos = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png'
            ];

            $tipoReal = mime_content_type($archivoImagen['tmp_name']);

            if (!isset($tiposPermitidos[$tipoReal])) {
                echo 'La imagen debe ser JPG o PNG';
                break;
            }

            $nombreImagenNueva =
                round(microtime(true))
                . '-'
                . random_int(100, 999)
                . '.'
                . $tiposPermitidos[$tipoReal];

            $directorioImagenes = tiquepos_media_dir('products');
            $rutaDestino = $directorioImagenes
                . DIRECTORY_SEPARATOR
                . $nombreImagenNueva;

            if (!is_dir($directorioImagenes) || !is_writable($directorioImagenes)) {
                echo 'No se pudo preparar la carpeta de imágenes de productos';
                break;
            }

            if (!move_uploaded_file($archivoImagen['tmp_name'], $rutaDestino)) {
                echo 'No se pudo guardar la imagen';
                break;
            }

            $imagen = $nombreImagenNueva;

            if (
                $imagenActual !== 'default.png'
                && $imagenActual !== $imagen
            ) {
                $rutaAnterior = tiquepos_media_path(
                    'products',
                    $imagenActual
                );

                if ($rutaAnterior !== '' && is_file($rutaAnterior)) {
                    unlink($rutaAnterior);
                }
            }
        }

        $idsubcategoriaFinal =
            $idsubcategoria > 0
            ? $idsubcategoria
            : null;

        if ($idarticulo <= 0) {

            $resultado = $product->insertar(
                $idcategoria,
                $idsubcategoriaFinal,
                $idmedida,
                $idalmacen,
                $codigo,
                $nombre,
                $stock,
                $precio_compra,
                $precio_venta,
                $descripcion,
                $imagen,
                $tributosProducto['codigo_afectacion_igv'],
                $tributosProducto['porcentaje_igv'],
                $tributosProducto['unidad_medida_sunat'],
                $tributosProducto['codigo_producto_sunat']
            );

            echo $resultado
                ? 'Producto registrado correctamente'
                : 'No se pudo registrar el producto';
        } else {

            $resultado = $product->editar(
                $idarticulo,
                $idcategoria,
                $idsubcategoriaFinal,
                $idmedida,
                $idalmacen,
                $codigo,
                $nombre,
                $stock,
                $precio_compra,
                $precio_venta,
                $descripcion,
                $imagen,
                $tributosProducto['codigo_afectacion_igv'],
                $tributosProducto['porcentaje_igv'],
                $tributosProducto['unidad_medida_sunat'],
                $tributosProducto['codigo_producto_sunat']
            );

            echo $resultado
                ? 'Producto actualizado correctamente'
                : 'No se pudo actualizar el producto';
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
                    ? "<img src='storage/images/products/"
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
        header('Content-Type: application/json; charset=utf-8');
        $productosGestion = $product->listarGestionProductos();
        echo json_encode(
            is_array($productosGestion) ? $productosGestion : [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
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
