<?php

declare(strict_types=1);

/**
 * Lector liviano para archivos de productos (CSV/XLSX) usado por Compras.
 * No depende de PhpSpreadsheet y mantiene compatibilidad con la plantilla
 * del módulo Inventario > Productos > Importar productos.
 */
final class ProductImportReader
{
    private static function normalizeText($value): string
    {
        $text = trim((string)$value);
        $text = strtr($text, [
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n'
        ]);
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string)$text);
    }

    private static function normalizeHeader($value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', self::normalizeText($value));
    }

    private static function headerMap(array $headers): array
    {
        $aliases = [
            'tipo' => ['tipo', 'tipoproducto', 'clase'],
            'grupo' => ['grupo', 'skupadre', 'codigopadre', 'gruposku', 'gruposkupadre'],
            'nombre' => ['nombre', 'producto', 'nombreproducto'],
            'codigo' => ['codigo', 'sku', 'codigoproducto'],
            'variante' => ['variante', 'variacion', 'combinacion', 'descripcionvariante'],
            'stock' => ['stock', 'existencia', 'existencias', 'cantidad', 'cantidadcomprada'],
            'precio_compra' => ['preciocompra', 'costocompra', 'costo', 'pcompra', 'costounitario'],
            'precio_venta' => ['precioventa', 'venta', 'pventa'],
            'categoria' => ['categoria', 'idcategoria'],
            'subcategoria' => ['subcategoria', 'idsubcategoria'],
            'almacen' => ['almacen', 'idalmacen'],
            'medida' => ['medida', 'idmedida', 'unidad', 'unidaddemedida', 'unidadmedida'],
            'codigo_afectacion_igv' => ['afectacionigv', 'afectacion', 'tributacion', 'igv', 'codigoafectacionigv']
        ];

        $result = [];
        foreach ($headers as $index => $header) {
            $normalized = self::normalizeHeader($header);
            foreach ($aliases as $field => $options) {
                if (in_array($normalized, $options, true)) {
                    $result[$field] = $index;
                    break;
                }
            }
        }

        return $result;
    }

    private static function matrixToRows(array $matrix): array
    {
        if (!$matrix) {
            return [];
        }

        $headers = array_shift($matrix);
        $map = self::headerMap(is_array($headers) ? $headers : []);
        $fields = [
            'tipo', 'grupo', 'nombre', 'codigo', 'variante', 'stock',
            'precio_compra', 'precio_venta', 'categoria', 'subcategoria',
            'almacen', 'medida', 'codigo_afectacion_igv'
        ];

        if (count($map) < 4) {
            // Compatibilidad con una plantilla simple de compras de 9 columnas.
            $historic = ['nombre','codigo','stock','precio_compra','precio_venta','categoria','subcategoria','almacen','medida'];
            $map = array_combine($historic, range(0, 8));
            array_unshift($matrix, $headers);
        }

        $rows = [];
        foreach ($matrix as $row) {
            if (!is_array($row)) {
                continue;
            }

            $item = [];
            foreach ($fields as $field) {
                $index = $map[$field] ?? null;
                $item[$field] = $index !== null && array_key_exists($index, $row)
                    ? trim((string)$row[$index])
                    : '';
            }

            $hasData = false;
            foreach ($item as $field => $value) {
                if (in_array($field, ['tipo', 'codigo_afectacion_igv'], true)) {
                    continue;
                }
                if (trim((string)$value) !== '') {
                    $hasData = true;
                    break;
                }
            }

            if ($hasData) {
                if ($item['tipo'] === '') {
                    $item['tipo'] = 'Simple';
                }
                $rows[] = $item;
            }

            if (count($rows) >= 500) {
                break;
            }
        }

        return $rows;
    }

    private static function detectCsvDelimiter(string $path): string
    {
        $line = '';
        $handle = @fopen($path, 'rb');
        if ($handle) {
            $line = (string)fgets($handle);
            fclose($handle);
        }

        $candidates = [',' => 0, ';' => 0, "\t" => 0];
        foreach (array_keys($candidates) as $delimiter) {
            $candidates[$delimiter] = count(str_getcsv($line, $delimiter));
        }
        arsort($candidates);
        return (string)array_key_first($candidates);
    }

    private static function readCsv(string $path): array
    {
        $delimiter = self::detectCsvDelimiter($path);
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            throw new RuntimeException('No se pudo abrir el archivo CSV.');
        }

        $matrix = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (!$matrix && isset($data[0])) {
                $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$data[0]);
            }
            $matrix[] = $data;
            if (count($matrix) > 501) {
                break;
            }
        }
        fclose($handle);

        return self::matrixToRows($matrix);
    }

    private static function excelColumnToIndex(string $reference): int
    {
        if (!preg_match('/^([A-Z]+)/i', $reference, $m)) {
            return 0;
        }
        $letters = strtoupper($m[1]);
        $index = 0;
        for ($i = 0, $length = strlen($letters); $i < $length; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return max(0, $index - 1);
    }

    private static function readPharEntry(string $zipFile, string $innerPath): ?string
    {
        try {
            $path = 'phar://' . $zipFile . '/' . $innerPath;
            if (!file_exists($path)) {
                return null;
            }
            $content = @file_get_contents($path);
            return $content === false ? null : $content;
        } catch (Throwable $error) {
            return null;
        }
    }

    private static function xmlCellText(string $fragment): string
    {
        $parts = [];
        if (preg_match_all('/<(?:[A-Za-z0-9_]+:)?t\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?t>/si', $fragment, $matches)) {
            foreach ($matches[1] as $text) {
                $parts[] = html_entity_decode(strip_tags((string)$text), ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }
        return implode('', $parts);
    }

    private static function xmlAttribute(string $attributes, string $name): string
    {
        if (preg_match('/(?:^|\s)' . preg_quote($name, '/') . '="([^"]*)"/i', $attributes, $m)) {
            return html_entity_decode((string)$m[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        return '';
    }

    private static function readXlsx(string $path): array
    {
        if (!class_exists('PharData')) {
            throw new RuntimeException('El servidor no tiene habilitado el lector necesario para archivos XLSX.');
        }

        $tmpZip = sys_get_temp_dir() . '/tp_buy_xlsx_' . bin2hex(random_bytes(8)) . '.zip';
        if (!@copy($path, $tmpZip)) {
            throw new RuntimeException('No se pudo preparar el archivo XLSX para su lectura.');
        }

        try {
            $sharedStrings = [];
            $xmlShared = self::readPharEntry($tmpZip, 'xl/sharedStrings.xml');
            if ($xmlShared && preg_match_all('/<(?:[A-Za-z0-9_]+:)?si\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?si>/si', $xmlShared, $siMatches)) {
                foreach ($siMatches[1] as $si) {
                    $sharedStrings[] = self::xmlCellText((string)$si);
                }
            }

            $xmlSheet = self::readPharEntry($tmpZip, 'xl/worksheets/sheet1.xml');
            if (!$xmlSheet) {
                throw new RuntimeException('El Excel no contiene una primera hoja legible.');
            }

            $matrix = [];
            if (!preg_match_all('/<(?:[A-Za-z0-9_]+:)?row\b([^>]*)>(.*?)<\/(?:[A-Za-z0-9_]+:)?row>/si', $xmlSheet, $rowMatches, PREG_SET_ORDER)) {
                return [];
            }

            foreach ($rowMatches as $rowMatch) {
                $row = [];
                $rowBody = (string)($rowMatch[2] ?? '');

                if (preg_match_all('/<(?:[A-Za-z0-9_]+:)?c\b([^>]*)>(.*?)<\/(?:[A-Za-z0-9_]+:)?c>/si', $rowBody, $cellMatches, PREG_SET_ORDER)) {
                    foreach ($cellMatches as $cellMatch) {
                        $attributes = (string)($cellMatch[1] ?? '');
                        $body = (string)($cellMatch[2] ?? '');
                        $reference = self::xmlAttribute($attributes, 'r');
                        $type = self::xmlAttribute($attributes, 't');
                        $index = self::excelColumnToIndex($reference);
                        $value = '';

                        if ($type === 'inlineStr') {
                            $value = self::xmlCellText($body);
                        } else {
                            $raw = '';
                            if (preg_match('/<(?:[A-Za-z0-9_]+:)?v\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?v>/si', $body, $vMatch)) {
                                $raw = html_entity_decode(strip_tags((string)$vMatch[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                            }

                            if ($type === 's' && $raw !== '' && isset($sharedStrings[(int)$raw])) {
                                $value = $sharedStrings[(int)$raw];
                            } elseif ($type === 'b') {
                                $value = $raw === '1' ? '1' : '0';
                            } else {
                                $value = $raw;
                            }
                        }

                        $row[$index] = $value;
                    }
                }

                if ($row) {
                    $max = max(array_keys($row));
                    $normalized = [];
                    for ($i = 0; $i <= $max; $i++) {
                        $normalized[] = $row[$i] ?? '';
                    }
                    $matrix[] = $normalized;
                }

                if (count($matrix) > 501) {
                    break;
                }
            }

            return self::matrixToRows($matrix);
        } finally {
            @unlink($tmpZip);
        }
    }

    public static function read(string $path, string $originalName): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === 'csv') {
            return self::readCsv($path);
        }
        if ($extension === 'xlsx') {
            return self::readXlsx($path);
        }
        throw new RuntimeException('Solo se permiten archivos .csv o .xlsx.');
    }
}
