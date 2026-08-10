<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';

class ResumenBoletas
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Conexion::conectar();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function listarPendientes(string $fecha): array
    {
        $this->validarFecha($fecha);

        $sql = "
            SELECT
                v.idventa,
                DATE_FORMAT(v.fecha_hora, '%d/%m/%Y %H:%i') AS fecha,
                v.fecha_hora,
                v.tipo_comprobante,
                v.serie_comprobante,
                v.num_comprobante,
                CONCAT(v.serie_comprobante, '-', v.num_comprobante) AS comprobante,
                COALESCE(NULLIF(TRIM(p.nombre), ''), 'CLIENTE VARIOS') AS cliente,
                COALESCE(NULLIF(TRIM(p.num_documento), ''), '—') AS documento_cliente,
                v.moneda_codigo,
                v.total_gravado,
                v.total_exonerado,
                v.total_inafecto,
                v.total_exportacion,
                v.total_igv,
                v.total_venta,
                v.modo_envio_sunat
            FROM venta v
            LEFT JOIN persona p
                ON p.idpersona = v.idcliente
            LEFT JOIN venta_sunat vs
                ON vs.idventa = v.idventa
            WHERE v.tipo_comprobante = 'Boleta Electrónica'
              AND UPPER(TRIM(COALESCE(v.estado, ''))) = 'ACEPTADO'
              AND UPPER(TRIM(COALESCE(v.modo_envio_sunat, ''))) = 'RESUMEN_DIARIO'
              AND DATE(v.fecha_hora) = :fecha
              AND (
                    vs.idventa_sunat IS NULL
                    OR (
                        COALESCE(TRIM(vs.document_id), '') = ''
                        AND UPPER(TRIM(COALESCE(vs.estado_sunat, ''))) IN ('', 'NO_ENVIADO')
                    )
              )
              AND NOT EXISTS (
                    SELECT 1
                    FROM resumen_diario_boleta_detalle rdd
                    INNER JOIN resumen_diario_boleta rd
                        ON rd.idresumen = rdd.idresumen
                    WHERE rdd.idventa = v.idventa
                      AND rdd.codigo_condicion = 1
                      AND UPPER(TRIM(COALESCE(rd.estado_sunat, 'NO_ENVIADO')))
                          NOT IN ('RECHAZADO', 'ERROR')
              )
            ORDER BY v.fecha_hora ASC, v.idventa ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':fecha' => $fecha]);

        return $stmt->fetchAll() ?: [];
    }

    public function listarResumenes(int $limite = 100): array
    {
        $limite = max(1, min($limite, 300));

        $sql = "
            SELECT
                rd.idresumen,
                rd.fecha_documentos,
                DATE_FORMAT(rd.fecha_documentos, '%d/%m/%Y') AS fecha_documentos_texto,
                DATE_FORMAT(rd.fecha_generacion, '%d/%m/%Y %H:%i') AS fecha_generacion,
                rd.correlativo,
                rd.codigo_resumen,
                rd.file_name,
                rd.document_id,
                rd.ticket,
                rd.production,
                rd.cantidad_documentos,
                rd.total_documentos,
                rd.estado_sunat,
                rd.mensaje_sunat,
                rd.fecha_envio,
                rd.fecha_respuesta,
                COALESCE(u.nombre, '') AS usuario
            FROM resumen_diario_boleta rd
            LEFT JOIN usuario u
                ON u.idusuario = rd.idusuario
            ORDER BY rd.idresumen DESC
            LIMIT {$limite}
        ";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll() ?: [];
    }

    public function detalle(int $idresumen): ?array
    {
        if ($idresumen <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                rd.*,
                DATE_FORMAT(rd.fecha_documentos, '%d/%m/%Y') AS fecha_documentos_texto,
                DATE_FORMAT(rd.fecha_generacion, '%d/%m/%Y %H:%i') AS fecha_generacion_texto,
                COALESCE(u.nombre, '') AS usuario
            FROM resumen_diario_boleta rd
            LEFT JOIN usuario u
                ON u.idusuario = rd.idusuario
            WHERE rd.idresumen = :idresumen
            LIMIT 1
        ");
        $stmt->execute([':idresumen' => $idresumen]);
        $cabecera = $stmt->fetch();

        if (!$cabecera) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                rdd.iddetalle_resumen,
                rdd.idventa,
                rdd.codigo_condicion,
                rdd.tipo_documento_sunat,
                rdd.serie_comprobante,
                rdd.num_comprobante,
                CONCAT(rdd.serie_comprobante, '-', rdd.num_comprobante) AS comprobante,
                rdd.total_gravado,
                rdd.total_exonerado,
                rdd.total_inafecto,
                rdd.total_exportacion,
                rdd.total_igv,
                rdd.total_venta,
                DATE_FORMAT(v.fecha_hora, '%d/%m/%Y %H:%i') AS fecha_venta,
                COALESCE(NULLIF(TRIM(p.nombre), ''), 'CLIENTE VARIOS') AS cliente
            FROM resumen_diario_boleta_detalle rdd
            INNER JOIN venta v
                ON v.idventa = rdd.idventa
            LEFT JOIN persona p
                ON p.idpersona = v.idcliente
            WHERE rdd.idresumen = :idresumen
            ORDER BY v.fecha_hora ASC, rdd.iddetalle_resumen ASC
        ");
        $stmt->execute([':idresumen' => $idresumen]);

        return [
            'resumen' => $cabecera,
            'detalle' => $stmt->fetchAll() ?: []
        ];
    }

    public function crear(string $fecha, array $idsVenta, int $idusuario): array
    {
        $this->validarFecha($fecha);

        $hoy = new DateTimeImmutable('today', new DateTimeZone('America/Lima'));
        $fechaObj = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha, new DateTimeZone('America/Lima'));

        if (!$fechaObj || $fechaObj > $hoy) {
            throw new RuntimeException('No se puede crear un resumen para una fecha futura.');
        }

        $idsVenta = array_values(array_unique(array_filter(
            array_map('intval', $idsVenta),
            static fn (int $id): bool => $id > 0
        )));

        if ($idsVenta === []) {
            throw new RuntimeException('Seleccione al menos una boleta pendiente.');
        }

        if ($idusuario <= 0) {
            throw new RuntimeException('No se pudo identificar al usuario que crea el resumen.');
        }

        $this->pdo->beginTransaction();

        try {
            $empresa = $this->obtenerEmpresaActiva();
            $ventas = $this->obtenerVentasElegiblesBloqueadas($fecha, $idsVenta);

            if (count($ventas) !== count($idsVenta)) {
                throw new RuntimeException(
                    'Una o más boletas ya no están disponibles para Resumen Diario. Actualice el listado y vuelva a intentarlo.'
                );
            }

            $correlativo = $this->obtenerSiguienteCorrelativo(
                (int)$empresa['id_negocio'],
                $fecha
            );

            $codigoResumen = sprintf(
                'RC-%s-%d',
                str_replace('-', '', $fecha),
                $correlativo
            );

            $totales = [
                'gravado' => 0.0,
                'exonerado' => 0.0,
                'inafecto' => 0.0,
                'exportacion' => 0.0,
                'igv' => 0.0,
                'total' => 0.0
            ];

            foreach ($ventas as $venta) {
                $totales['gravado'] += (float)($venta['total_gravado'] ?? 0);
                $totales['exonerado'] += (float)($venta['total_exonerado'] ?? 0);
                $totales['inafecto'] += (float)($venta['total_inafecto'] ?? 0);
                $totales['exportacion'] += (float)($venta['total_exportacion'] ?? 0);
                $totales['igv'] += (float)($venta['total_igv'] ?? 0);
                $totales['total'] += (float)($venta['total_venta'] ?? 0);
            }

            foreach ($totales as $clave => $valor) {
                $totales[$clave] = round($valor, 2);
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO resumen_diario_boleta (
                    id_negocio,
                    idusuario,
                    fecha_documentos,
                    correlativo,
                    codigo_resumen,
                    production,
                    cantidad_documentos,
                    total_gravado,
                    total_exonerado,
                    total_inafecto,
                    total_exportacion,
                    total_igv,
                    total_documentos,
                    estado_sunat
                ) VALUES (
                    :id_negocio,
                    :idusuario,
                    :fecha_documentos,
                    :correlativo,
                    :codigo_resumen,
                    :production,
                    :cantidad_documentos,
                    :total_gravado,
                    :total_exonerado,
                    :total_inafecto,
                    :total_exportacion,
                    :total_igv,
                    :total_documentos,
                    'NO_ENVIADO'
                )
            ");

            $stmt->execute([
                ':id_negocio' => (int)$empresa['id_negocio'],
                ':idusuario' => $idusuario,
                ':fecha_documentos' => $fecha,
                ':correlativo' => $correlativo,
                ':codigo_resumen' => $codigoResumen,
                ':production' => (int)($empresa['apisunat_production'] ?? 1) === 1 ? 1 : 0,
                ':cantidad_documentos' => count($ventas),
                ':total_gravado' => $totales['gravado'],
                ':total_exonerado' => $totales['exonerado'],
                ':total_inafecto' => $totales['inafecto'],
                ':total_exportacion' => $totales['exportacion'],
                ':total_igv' => $totales['igv'],
                ':total_documentos' => $totales['total']
            ]);

            $idresumen = (int)$this->pdo->lastInsertId();

            $stmtDetalle = $this->pdo->prepare("
                INSERT INTO resumen_diario_boleta_detalle (
                    idresumen,
                    idventa,
                    codigo_condicion,
                    tipo_documento_sunat,
                    serie_comprobante,
                    num_comprobante,
                    total_gravado,
                    total_exonerado,
                    total_inafecto,
                    total_exportacion,
                    total_igv,
                    total_venta
                ) VALUES (
                    :idresumen,
                    :idventa,
                    1,
                    '03',
                    :serie,
                    :numero,
                    :gravado,
                    :exonerado,
                    :inafecto,
                    :exportacion,
                    :igv,
                    :total
                )
            ");

            foreach ($ventas as $venta) {
                $stmtDetalle->execute([
                    ':idresumen' => $idresumen,
                    ':idventa' => (int)$venta['idventa'],
                    ':serie' => (string)$venta['serie_comprobante'],
                    ':numero' => (string)$venta['num_comprobante'],
                    ':gravado' => round((float)($venta['total_gravado'] ?? 0), 2),
                    ':exonerado' => round((float)($venta['total_exonerado'] ?? 0), 2),
                    ':inafecto' => round((float)($venta['total_inafecto'] ?? 0), 2),
                    ':exportacion' => round((float)($venta['total_exportacion'] ?? 0), 2),
                    ':igv' => round((float)($venta['total_igv'] ?? 0), 2),
                    ':total' => round((float)($venta['total_venta'] ?? 0), 2)
                ]);
            }

            $this->pdo->commit();

            return [
                'idresumen' => $idresumen,
                'codigo_resumen' => $codigoResumen,
                'cantidad_documentos' => count($ventas),
                'total_documentos' => $totales['total'],
                'estado_sunat' => 'NO_ENVIADO'
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function descartar(int $idresumen): bool
    {
        if ($idresumen <= 0) {
            throw new RuntimeException('El resumen seleccionado no es válido.');
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    idresumen,
                    estado_sunat,
                    document_id,
                    ticket,
                    fecha_envio
                FROM resumen_diario_boleta
                WHERE idresumen = :idresumen
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([':idresumen' => $idresumen]);
            $resumen = $stmt->fetch();

            if (!$resumen) {
                throw new RuntimeException('No se encontró el resumen seleccionado.');
            }

            $estado = strtoupper(trim((string)($resumen['estado_sunat'] ?? '')));
            $documentId = trim((string)($resumen['document_id'] ?? ''));
            $ticket = trim((string)($resumen['ticket'] ?? ''));

            if ($estado !== 'NO_ENVIADO' || $documentId !== '' || $ticket !== '' || !empty($resumen['fecha_envio'])) {
                throw new RuntimeException('Solo se pueden descartar resúmenes que todavía no fueron enviados.');
            }

            $stmt = $this->pdo->prepare(
                'DELETE FROM resumen_diario_boleta WHERE idresumen = :idresumen'
            );
            $stmt->execute([':idresumen' => $idresumen]);

            $this->pdo->commit();
            return $stmt->rowCount() === 1;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function obtenerEmpresaActiva(): array
    {
        $stmt = $this->pdo->query("
            SELECT id_negocio, apisunat_production
            FROM datos_negocio
            WHERE condicion = 1
            ORDER BY id_negocio ASC
            LIMIT 1
        ");

        $empresa = $stmt->fetch();

        if (!$empresa) {
            throw new RuntimeException('No existe una empresa activa configurada.');
        }

        return $empresa;
    }

    private function obtenerVentasElegiblesBloqueadas(string $fecha, array $idsVenta): array
    {
        $marcadores = implode(',', array_fill(0, count($idsVenta), '?'));

        $sql = "
            SELECT
                v.idventa,
                v.serie_comprobante,
                v.num_comprobante,
                v.total_gravado,
                v.total_exonerado,
                v.total_inafecto,
                v.total_exportacion,
                v.total_igv,
                v.total_venta
            FROM venta v
            LEFT JOIN venta_sunat vs
                ON vs.idventa = v.idventa
            WHERE v.idventa IN ({$marcadores})
              AND v.tipo_comprobante = 'Boleta Electrónica'
              AND UPPER(TRIM(COALESCE(v.estado, ''))) = 'ACEPTADO'
              AND UPPER(TRIM(COALESCE(v.modo_envio_sunat, ''))) = 'RESUMEN_DIARIO'
              AND DATE(v.fecha_hora) = ?
              AND (
                    vs.idventa_sunat IS NULL
                    OR (
                        COALESCE(TRIM(vs.document_id), '') = ''
                        AND UPPER(TRIM(COALESCE(vs.estado_sunat, ''))) IN ('', 'NO_ENVIADO')
                    )
              )
              AND NOT EXISTS (
                    SELECT 1
                    FROM resumen_diario_boleta_detalle rdd
                    INNER JOIN resumen_diario_boleta rd
                        ON rd.idresumen = rdd.idresumen
                    WHERE rdd.idventa = v.idventa
                      AND rdd.codigo_condicion = 1
                      AND UPPER(TRIM(COALESCE(rd.estado_sunat, 'NO_ENVIADO')))
                          NOT IN ('RECHAZADO', 'ERROR')
              )
            ORDER BY v.idventa ASC
            FOR UPDATE
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([...$idsVenta, $fecha]);

        return $stmt->fetchAll() ?: [];
    }

    private function obtenerSiguienteCorrelativo(int $idNegocio, string $fecha): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(MAX(correlativo), 0) AS ultimo
            FROM resumen_diario_boleta
            WHERE id_negocio = :id_negocio
              AND fecha_documentos = :fecha
            FOR UPDATE
        ");
        $stmt->execute([
            ':id_negocio' => $idNegocio,
            ':fecha' => $fecha
        ]);

        $fila = $stmt->fetch();
        return max(1, ((int)($fila['ultimo'] ?? 0)) + 1);
    }

    private function validarFecha(string $fecha): void
    {
        $fecha = trim($fecha);
        $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha, new DateTimeZone('America/Lima'));
        $errores = DateTimeImmutable::getLastErrors();

        if (
            !$objeto
            || ($errores !== false && (($errores['warning_count'] ?? 0) > 0 || ($errores['error_count'] ?? 0) > 0))
            || $objeto->format('Y-m-d') !== $fecha
        ) {
            throw new InvalidArgumentException('La fecha seleccionada no es válida.');
        }
    }
}
