-- TiquePOS · Fase 2/3 · Trazabilidad financiera de compras
-- Ejecutar UNA SOLA VEZ sobre app.tiquepos.com antes de subir el código.

START TRANSACTION;

ALTER TABLE movimiento_financiero
    MODIFY COLUMN origen
    ENUM(
        'VENTA',
        'COBRANZA',
        'APERTURA',
        'CIERRE',
        'AJUSTE',
        'NOTA_CREDITO',
        'COMPRA',
        'OTRO'
    ) NOT NULL;

ALTER TABLE ingreso
    ADD COLUMN condicion_pago
        ENUM('NO_DEFINIDO','CONTADO','CREDITO')
        NOT NULL DEFAULT 'NO_DEFINIDO'
        AFTER total_compra,
    ADD COLUMN idforma_pago INT(11) NULL
        AFTER condicion_pago,
    ADD COLUMN idcuenta_financiera INT(11) NULL
        AFTER idforma_pago,
    ADD COLUMN idapertura INT(11) NULL
        AFTER idcuenta_financiera,
    ADD COLUMN numero_operacion VARCHAR(80) NULL
        AFTER idapertura,
    ADD COLUMN estado_pago
        ENUM('NO_DEFINIDO','PAGADO','PENDIENTE','ANULADO')
        NOT NULL DEFAULT 'NO_DEFINIDO'
        AFTER numero_operacion;

ALTER TABLE ingreso
    ADD KEY idx_ingreso_condicion_pago (condicion_pago),
    ADD KEY idx_ingreso_forma_pago (idforma_pago),
    ADD KEY idx_ingreso_cuenta_financiera (idcuenta_financiera),
    ADD KEY idx_ingreso_apertura (idapertura),
    ADD KEY idx_ingreso_estado_pago (estado_pago);

ALTER TABLE ingreso
    ADD CONSTRAINT fk_ingreso_forma_pago
        FOREIGN KEY (idforma_pago)
        REFERENCES forma_pago (idforma_pago)
        ON UPDATE CASCADE,
    ADD CONSTRAINT fk_ingreso_cuenta_financiera
        FOREIGN KEY (idcuenta_financiera)
        REFERENCES cuenta_financiera (idcuenta_financiera)
        ON UPDATE CASCADE,
    ADD CONSTRAINT fk_ingreso_apertura
        FOREIGN KEY (idapertura)
        REFERENCES caja_apertura (idapertura)
        ON UPDATE CASCADE;

COMMIT;
