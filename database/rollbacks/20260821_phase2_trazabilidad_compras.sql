-- TiquePOS 1.9.0 · DOWN compatible de trazabilidad de compras
-- Política EXPAND-CONTRACT:
-- NO se eliminan columnas, índices, FKs ni el valor COMPRA del ENUM.
-- El código 1.8.8 ignora estos campos adicionales y continúa funcionando.
-- Mantener el esquema evita pérdida de pagos/movimientos creados en 1.9.0.
-- La migración UP es idempotente, por lo que puede ejecutarse de nuevo al
-- volver a actualizar después de un rollback.
SELECT 'Rollback compatible: esquema financiero aditivo conservado' AS tiquepos_rollback;
