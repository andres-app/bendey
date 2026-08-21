-- TiquePOS 1.9.0 · Trazabilidad de devoluciones por Nota de Crédito
-- Estrategia EXPAND: idempotente y compatible con rollback de código.

ALTER TABLE nota_credito_pago
    ADD COLUMN IF NOT EXISTS idapertura INT(11) NULL
        AFTER idcuenta_financiera;

ALTER TABLE nota_credito_pago
    ADD COLUMN IF NOT EXISTS numero_operacion VARCHAR(80) NULL
        AFTER idapertura;

ALTER TABLE nota_credito_pago
    ADD INDEX IF NOT EXISTS idx_ncp_apertura (idapertura);

SET @tp_fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'nota_credito_pago'
      AND CONSTRAINT_NAME = 'fk_ncp_apertura'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @tp_sql = IF(
    @tp_fk_exists = 0,
    'ALTER TABLE nota_credito_pago ADD CONSTRAINT fk_ncp_apertura FOREIGN KEY (idapertura) REFERENCES caja_apertura (idapertura) ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE tp_stmt FROM @tp_sql;
EXECUTE tp_stmt;
DEALLOCATE PREPARE tp_stmt;
