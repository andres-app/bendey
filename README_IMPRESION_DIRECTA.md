# TiquePOS - Impresión directa de etiquetas

## Qué agrega

- Modo **Impresión directa** en Inventario > Etiquetas / Códigos de barras.
- Detección de impresoras instaladas mediante QZ Tray.
- Recuerda la impresora elegida en cada dispositivo.
- Prioriza automáticamente impresoras cuyo nombre contenga TSC o TE200.
- Envía comandos **TSPL** directamente a la impresora, sin abrir el diálogo de impresión del sistema.
- Conserva **Usar diálogo del sistema** como respaldo.
- Respeta 1, 2 o 3 columnas, ancho/alto y gap configurados en TiquePOS.
- Usa CODE128 con el SKU del producto o variante.

## Requisito del equipo

Instalar y ejecutar QZ Tray en el PC que tiene conectada la impresora:
https://qz.io/download/

La interfaz de TiquePOS carga el conector web QZ Tray 2.2.6 desde jsDelivr.

## TSC TE200

El perfil incluido usa 203 DPI y 108 mm de ancho imprimible. La salida directa se genera en TSPL y usa CODEPAGE UTF-8.

## Flujo

1. Abrir Etiquetas / Códigos de barras.
2. Seleccionar Impresión directa.
3. Pulsar Detectar.
4. Autorizar TiquePOS en QZ Tray si lo solicita.
5. Elegir la TSC TE200.
6. Seleccionar productos, cantidades y formato.
7. Pulsar Imprimir directo.

## Impresión completamente silenciosa

La versión actual elimina el diálogo de impresión de Windows/macOS. QZ Tray puede seguir mostrando avisos de autorización si las solicitudes no están firmadas.

Para producción 100 % silenciosa se debe configurar el certificado de confianza y firma de mensajes de QZ, manteniendo la clave privada únicamente en el servidor. No se incluye una clave privada de ejemplo dentro del proyecto por seguridad.
