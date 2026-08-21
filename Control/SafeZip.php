<?php

declare(strict_types=1);

/**
 * Lector ZIP mínimo y seguro para releases TiquePOS.
 * Fallback cuando ZipArchive no está disponible.
 * Soporta ZIP estándar (no ZIP64), métodos STORE (0) y DEFLATE (8).
 */
class TiquePOSSafeZip
{
    private string $path;
    /** @var array<int,array<string,int|string|bool>> */
    private array $entries = array();

    public function __construct(string $path)
    {
        if (!is_file($path)) {
            throw new RuntimeException('ZIP no encontrado.');
        }
        $this->path = $path;
        $this->parseCentralDirectory();
    }

    public function entries(): array
    {
        return $this->entries;
    }

    private function parseCentralDirectory(): void
    {
        $fp = fopen($this->path, 'rb');
        if (!$fp) {
            throw new RuntimeException('No se pudo abrir el ZIP.');
        }
        $size = filesize($this->path);
        if ($size === false || $size < 22) {
            fclose($fp);
            throw new RuntimeException('ZIP inválido o vacío.');
        }

        $tailSize = min((int)$size, 65557);
        fseek($fp, (int)$size - $tailSize);
        $tail = fread($fp, $tailSize);
        $pos = strrpos($tail, "PK\x05\x06");
        if ($pos === false || strlen($tail) < $pos + 22) {
            fclose($fp);
            throw new RuntimeException('No se encontró el directorio central del ZIP.');
        }
        $eocd = unpack(
            'vdisk/vdisk_start/ventries_disk/ventries/Vcd_size/Vcd_offset/vcomment_len',
            substr($tail, $pos + 4, 18)
        );
        if (!$eocd) {
            fclose($fp);
            throw new RuntimeException('EOCD del ZIP inválido.');
        }
        if ((int)$eocd['disk'] !== 0 || (int)$eocd['disk_start'] !== 0) {
            fclose($fp);
            throw new RuntimeException('No se admiten ZIP multipartes.');
        }
        if ((int)$eocd['entries'] === 0xFFFF || (int)$eocd['cd_offset'] === 0xFFFFFFFF || (int)$eocd['cd_size'] === 0xFFFFFFFF) {
            fclose($fp);
            throw new RuntimeException('ZIP64 no está soportado por el extractor alternativo. Activa ZipArchive para releases ZIP64.');
        }

        fseek($fp, (int)$eocd['cd_offset']);
        $total = (int)$eocd['entries'];
        $uncompressedTotal = 0;
        for ($i = 0; $i < $total; $i++) {
            $sig = fread($fp, 4);
            if ($sig !== "PK\x01\x02") {
                fclose($fp);
                throw new RuntimeException('Cabecera central ZIP inválida.');
            }
            $fixed = fread($fp, 42);
            if (strlen($fixed) !== 42) {
                fclose($fp);
                throw new RuntimeException('Cabecera central ZIP incompleta.');
            }
            $h = unpack(
                'vversion_made/vversion_needed/vflags/vmethod/vmtime/vmdate/Vcrc/Vcompressed/Vuncompressed/vname_len/vextra_len/vcomment_len/vdisk_start/vint_attr/Vext_attr/Vlocal_offset',
                $fixed
            );
            if (!$h) {
                fclose($fp);
                throw new RuntimeException('No se pudo interpretar una entrada ZIP.');
            }
            $nameLen = (int)$h['name_len'];
            $extraLen = (int)$h['extra_len'];
            $commentLen = (int)$h['comment_len'];
            $name = $nameLen > 0 ? fread($fp, $nameLen) : '';
            if (strlen($name) !== $nameLen) {
                fclose($fp);
                throw new RuntimeException('Nombre ZIP incompleto.');
            }
            if ($extraLen > 0) { fseek($fp, $extraLen, SEEK_CUR); }
            if ($commentLen > 0) { fseek($fp, $commentLen, SEEK_CUR); }

            if ((int)$h['compressed'] === 0xFFFFFFFF || (int)$h['uncompressed'] === 0xFFFFFFFF || (int)$h['local_offset'] === 0xFFFFFFFF) {
                fclose($fp);
                throw new RuntimeException('Entrada ZIP64 no soportada: ' . $name);
            }
            if (((int)$h['flags'] & 0x1) !== 0) {
                fclose($fp);
                throw new RuntimeException('No se permiten archivos ZIP cifrados.');
            }
            $method = (int)$h['method'];
            if (!in_array($method, array(0, 8), true)) {
                fclose($fp);
                throw new RuntimeException('Método de compresión ZIP no soportado en ' . $name . '.');
            }
            $mode = (((int)$h['ext_attr']) >> 16) & 0170000;
            if ($mode === 0120000) {
                fclose($fp);
                throw new RuntimeException('No se permiten enlaces simbólicos en releases.');
            }
            $uncompressedTotal += (int)$h['uncompressed'];
            if ($uncompressedTotal > 600 * 1024 * 1024) {
                fclose($fp);
                throw new RuntimeException('El release excede 600 MB descomprimido.');
            }
            $this->entries[] = array(
                'name' => str_replace('\\', '/', $name),
                'flags' => (int)$h['flags'],
                'method' => $method,
                'crc' => (int)$h['crc'],
                'compressed' => (int)$h['compressed'],
                'uncompressed' => (int)$h['uncompressed'],
                'local_offset' => (int)$h['local_offset'],
                'is_dir' => substr($name, -1) === '/',
            );
        }
        fclose($fp);
    }

    public function extractEntry(array $entry, string $destination): void
    {
        if (!empty($entry['is_dir'])) {
            return;
        }
        $fp = fopen($this->path, 'rb');
        if (!$fp) {
            throw new RuntimeException('No se pudo abrir el ZIP para extraer.');
        }
        fseek($fp, (int)$entry['local_offset']);
        if (fread($fp, 4) !== "PK\x03\x04") {
            fclose($fp);
            throw new RuntimeException('Cabecera local ZIP inválida.');
        }
        $fixed = fread($fp, 26);
        if (strlen($fixed) !== 26) {
            fclose($fp);
            throw new RuntimeException('Cabecera local ZIP incompleta.');
        }
        $h = unpack('vversion/vflags/vmethod/vmtime/vmdate/Vcrc/Vcompressed/Vuncompressed/vname_len/vextra_len', $fixed);
        if (!$h) {
            fclose($fp);
            throw new RuntimeException('Entrada ZIP local inválida.');
        }
        fseek($fp, (int)$h['name_len'] + (int)$h['extra_len'], SEEK_CUR);

        $dir = dirname($destination);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            fclose($fp);
            throw new RuntimeException('No se pudo crear el directorio de extracción.');
        }
        $out = fopen($destination, 'wb');
        if (!$out) {
            fclose($fp);
            throw new RuntimeException('No se pudo crear el archivo extraído.');
        }

        $remaining = (int)$entry['compressed'];
        $method = (int)$entry['method'];
        $hash = hash_init('crc32b');
        $written = 0;
        $inflater = null;
        if ($method === 8) {
            $inflater = inflate_init(ZLIB_ENCODING_RAW);
            if ($inflater === false) {
                fclose($out); fclose($fp);
                throw new RuntimeException('No se pudo inicializar DEFLATE.');
            }
        }
        while ($remaining > 0) {
            $chunk = fread($fp, min(65536, $remaining));
            if ($chunk === false || $chunk === '') {
                fclose($out); fclose($fp);
                throw new RuntimeException('ZIP truncado durante extracción.');
            }
            $remaining -= strlen($chunk);
            if ($method === 0) {
                $decoded = $chunk;
            } else {
                $flush = $remaining === 0 ? ZLIB_FINISH : ZLIB_SYNC_FLUSH;
                $decoded = inflate_add($inflater, $chunk, $flush);
                if ($decoded === false) {
                    fclose($out); fclose($fp);
                    throw new RuntimeException('Datos DEFLATE inválidos.');
                }
            }
            if ($decoded !== '') {
                hash_update($hash, $decoded);
                $written += strlen($decoded);
                if (fwrite($out, $decoded) === false) {
                    fclose($out); fclose($fp);
                    throw new RuntimeException('No se pudo escribir el archivo extraído.');
                }
            }
        }
        fclose($out); fclose($fp);

        if ($written !== (int)$entry['uncompressed']) {
            @unlink($destination);
            throw new RuntimeException('Tamaño descomprimido inválido en ' . (string)$entry['name']);
        }
        $actualCrc = strtolower(hash_final($hash));
        $expectedCrc = strtolower(sprintf('%08x', ((int)$entry['crc']) & 0xffffffff));
        if (!hash_equals($expectedCrc, $actualCrc)) {
            @unlink($destination);
            throw new RuntimeException('CRC32 inválido en ' . (string)$entry['name']);
        }
    }
}
