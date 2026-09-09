# Conector de impresoras TiquePOS

## Identidad corporativa

- Producto: **TiquePOS Print Connector**
- Ejecutable: `TiquePOSPrintConnector.exe`
- Empresa: **TIQUEPOS S.A.C.**
- RUC: **20609518597**
- Versión del conector: **1.0.1**

> Nota: el campo **Publisher / Editor verificado** que muestra Windows proviene de la firma Authenticode. Para producción, el certificado de firma debe estar emitido a **TIQUEPOS S.A.C.**; no basta con incrustar el nombre de la empresa en el ejecutable.

## Objetivo

Permitir impresión directa desde TiquePOS a impresoras instaladas en Windows sin QZ Tray y sin abrir el diálogo de impresión del sistema.

La primera integración está optimizada para etiquetas TSC TE200 enviando TSPL como trabajo RAW al spooler de Windows.

## Flujo para el usuario

1. Abrir **Inventario > Etiquetas / Códigos de barras**.
2. Pulsar **Descargar Conector TiquePOS**.
3. Ejecutar `Instalar-Conector-TiquePOS.cmd` una sola vez.
4. Volver a TiquePOS y pulsar **Detectar**.
5. Seleccionar la TSC TE200.
6. Pulsar **Imprimir directo**.

El instalador:

- descarga `Assets/connectors/TiquePOSPrintConnector.exe` desde la misma instalación de TiquePOS;
- verifica el SHA-256 antes de instalar;
- instala por usuario en `%LOCALAPPDATA%\TiquePOS\PrintConnector`;
- no necesita privilegios de administrador;
- registra inicio automático en `HKCU\Software\Microsoft\Windows\CurrentVersion\Run`;
- vincula el conector al origen web de TiquePOS y a un token local del navegador.

## Seguridad

El servicio local escucha exclusivamente en `127.0.0.1:17654`.

Cada petición debe cumplir simultáneamente:

- `Origin` idéntico al dominio TiquePOS usado al instalar;
- token de 256 bits en `X-TiquePOS-Token`;
- CORS explícito para ese origen;
- límite de 4 MB por trabajo de impresión.

El token se envía al endpoint de descarga por POST para no aparecer en la URL.

## API local

- `GET /v1/status`
- `GET /v1/printers`
- `POST /v1/print/raw`

Puerto: `17654` en loopback.

## Navegadores modernos

Algunos navegadores pueden mostrar una autorización única para que el sitio acceda a un servicio del propio dispositivo/loopback. Esto es independiente del diálogo de impresión. Una vez autorizado, la impresión directa no abre el selector de impresoras de Windows.

## Producción / firma de código

El ejecutable generado en este paquete no está firmado con un certificado Authenticode de una entidad confiable. Para distribución comercial a muchos equipos se recomienda firmar `TiquePOSPrintConnector.exe` y, de ser posible, el instalador, con un certificado de firma de código emitido a **TIQUEPOS S.A.C.**. Esto reduce advertencias de SmartScreen y permite verificar editorial/origen en Windows.

## Código fuente

El fuente se encuentra en `Tools/PrintConnector/main_windows.go`.

Compilar para Windows x64:

```bash
GOOS=windows GOARCH=amd64 CGO_ENABLED=0 go build -trimpath -ldflags="-s -w -H=windowsgui" -o Assets/connectors/TiquePOSPrintConnector.exe Tools/PrintConnector/main_windows.go
```
