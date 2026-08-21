-- TiquePOS 1.9.0 · DOWN compatible de devoluciones
-- No se eliminan idapertura/numero_operacion ni su FK/índice para no perder
-- trazabilidad creada después del despliegue. El código anterior simplemente
-- ignora estas columnas. La migración UP es idempotente para futuras reaplicaciones.
SELECT 'Rollback compatible: esquema de devoluciones conservado' AS tiquepos_rollback;
