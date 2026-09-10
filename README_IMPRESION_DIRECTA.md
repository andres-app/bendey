# TiquePOS · Impresión directa con QZ Tray

TiquePOS utiliza **QZ Tray 2.2.6** para la impresión directa de etiquetas.

## Qué se eliminó

Se eliminó por completo el Conector de impresoras TiquePOS: ejecutable, GZIP, instalador CMD, fuente Go, endpoints PHP, token local y puerto 17654. TiquePOS ya no distribuye ni ejecuta binarios propios para imprimir.

## Instalación oficial

La pantalla **Inventario → Etiquetas / Códigos de barras** contiene el botón **Descargar QZ Tray · sitio oficial**. El botón abre exclusivamente:

https://qz.io/download/?os=windows

El usuario descarga QZ Tray desde el sitio oficial del fabricante.

## Flujo

1. Instalar y abrir QZ Tray.
2. Regresar a TiquePOS.
3. Pulsar **Detectar**.
4. Autorizar TiquePOS cuando QZ Tray lo solicite.
5. Elegir la TSC TE200 u otra impresora instalada.
6. Pulsar **Imprimir directo**.

TiquePOS envía TSPL nativo mediante `qz.print()`; el modo **Usar diálogo del sistema** se mantiene como respaldo.

## Seguridad

La integración estándar no contiene claves privadas ni certificados falsos. QZ Tray puede solicitar autorización para solicitudes no firmadas. La impresión completamente silenciosa requiere configurar firma digital de las solicitudes QZ con credenciales válidas.
