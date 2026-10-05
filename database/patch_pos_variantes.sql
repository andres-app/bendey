-- TiquePOS - soporte de variantes en el POS
-- Ejecutar una sola vez sobre la base de datos existente.

SET @db := DATABASE();

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db
          AND TABLE_NAME = 'detalle_venta'
          AND COLUMN_NAME = 'idvariacion'
    ),
    'SELECT 1',
    'ALTER TABLE detalle_venta ADD COLUMN idvariacion INT(11) NULL AFTER idarticulo'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @db
          AND TABLE_NAME = 'detalle_venta'
          AND INDEX_NAME = 'idx_detalle_venta_variacion'
    ),
    'SELECT 1',
    'ALTER TABLE detalle_venta ADD INDEX idx_detalle_venta_variacion (idvariacion)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = @db
          AND TABLE_NAME = 'detalle_venta'
          AND CONSTRAINT_NAME = 'fk_detalle_venta_variacion'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ),
    'SELECT 1',
    'ALTER TABLE detalle_venta ADD CONSTRAINT fk_detalle_venta_variacion FOREIGN KEY (idvariacion) REFERENCES articulo_variacion(idvariacion) ON DELETE SET NULL ON UPDATE CASCADE'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
