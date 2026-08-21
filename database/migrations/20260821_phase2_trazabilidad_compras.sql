-- TiquePOS 1.9.0 · Migración de trazabilidad financiera de compras
-- Estrategia EXPAND: idempotente y compatible con rollback de código.
-- MariaDB 11.x / MySQL compatible en sentencias principales.

-- 1) El nuevo origen COMPRA es aditivo y compatible con código anterior.
ALTER TABLE movimiento_financiero
    MODIFY COLUMN origen ENUM(
        'VENTA',
        'COBRANZA',
        'APERTURA',
        'CIERRE',
        'AJUSTE',
        'NOTA_CREDITO',
        'COMPRA',
        'OTRO'
    ) NOT NULL;

-- 2) Campos financieros de compras. IF NOT EXISTS permite reaplicar
--    la migración después de un rollback de código sin perder datos.
ALTER TABLE ingreso
    ADD COLUMN IF NOT EXISTS condicion_pago
        ENUM('NO_DEFINIDO','CONTADO','CREDITO')
        NOT NULL DEFAULT 'NO_DEFINIDO'
        AFTER total_compra;

ALTER TABLE ingreso
    ADD COLUMN IF NOT EXISTS idforma_pago INT(11) NULL
        AFTER condicion_pago;

ALTER TABLE ingreso
    ADD COLUMN IF NOT EXISTS idcuenta_financiera INT(11) NULL
        AFTER idforma_pago;

ALTER TABLE ingreso
    ADD COLUMN IF NOT EXISTS idapertura INT(11) NULL
        AFTER idcuenta_financiera;

ALTER TABLE ingreso
    ADD COLUMN IF NOT EXISTS numero_operacion VARCHAR(80) NULL
        AFTER idapertura;

ALTER TABLE ingreso
    ADD COLUMN IF NOT EXISTS estado_pago
        ENUM('NO_DEFINIDO','PAGADO','PENDIENTE','ANULADO')
        NOT NULL DEFAULT 'NO_DEFINIDO'
        AFTER numero_operacion;

ALTER TABLE ingreso
    ADD INDEX IF NOT EXISTS idx_ingreso_condicion_pago (condicion_pago);
ALTER TABLE ingreso
    ADD INDEX IF NOT EXISTS idx_ingreso_forma_pago (idforma_pago);
ALTER TABLE ingreso
    ADD INDEX IF NOT EXISTS idx_ingreso_cuenta_financiera (idcuenta_financiera);
ALTER TABLE ingreso
    ADD INDEX IF NOT EXISTS idx_ingreso_apertura (idapertura);
ALTER TABLE ingreso
    ADD INDEX IF NOT EXISTS idx_ingreso_estado_pago (estado_pago);

-- 3) FKs idempotentes. Se usa INFORMATION_SCHEMA porque ADD CONSTRAINT
--    no dispone de IF NOT EXISTS de forma portable.
SET @tp_fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ingreso'
      AND CONSTRAINT_NAME = 'fk_ingreso_forma_pago'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @tp_sql = IF(
    @tp_fk_exists = 0,
    'ALTER TABLE ingreso ADD CONSTRAINT fk_ingreso_forma_pago FOREIGN KEY (idforma_pago) REFERENCES forma_pago (idforma_pago) ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE tp_stmt FROM @tp_sql;
EXECUTE tp_stmt;
DEALLOCATE PREPARE tp_stmt;

SET @tp_fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ingreso'
      AND CONSTRAINT_NAME = 'fk_ingreso_cuenta_financiera'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @tp_sql = IF(
    @tp_fk_exists = 0,
    'ALTER TABLE ingreso ADD CONSTRAINT fk_ingreso_cuenta_financiera FOREIGN KEY (idcuenta_financiera) REFERENCES cuenta_financiera (idcuenta_financiera) ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE tp_stmt FROM @tp_sql;
EXECUTE tp_stmt;
DEALLOCATE PREPARE tp_stmt;

SET @tp_fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ingreso'
      AND CONSTRAINT_NAME = 'fk_ingreso_apertura'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @tp_sql = IF(
    @tp_fk_exists = 0,
    'ALTER TABLE ingreso ADD CONSTRAINT fk_ingreso_apertura FOREIGN KEY (idapertura) REFERENCES caja_apertura (idapertura) ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE tp_stmt FROM @tp_sql;
EXECUTE tp_stmt;
DEALLOCATE PREPARE tp_stmt;
