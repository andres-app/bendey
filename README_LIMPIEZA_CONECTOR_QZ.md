# Limpieza del Conector propio y migración a QZ Tray

Esta versión elimina todos los componentes del Conector de impresoras TiquePOS y vuelve a QZ Tray.

Eliminado:
- `Assets/connectors/`
- `Tools/PrintConnector/`
- endpoints `binario_conector_impresoras` y `descargar_conector_impresoras`
- token `tiquepos_print_connector_token_v1`
- acceso local a `127.0.0.1:17654`
- botón y textos de descarga del Conector TiquePOS

Añadido:
- conexión con `qz.websocket.connect()`
- detección mediante `qz.printers.find()`
- impresión TSPL mediante `qz.print()`
- botón hacia la descarga oficial `https://qz.io/download/?os=windows`
- impresión normal como respaldo
