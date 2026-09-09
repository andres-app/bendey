# TiquePOS Print Connector

Conector local de impresión directa para Windows.

**Titular:** TIQUEPOS S.A.C.  
**RUC:** 20609518597  
**Producto:** TiquePOS Print Connector  
**Ejecutable:** `TiquePOSPrintConnector.exe`

- Escucha solo en `127.0.0.1:17654`.
- Acepta únicamente el origen de TiquePOS configurado durante la instalación.
- Requiere un token local de vinculación enviado en `X-TiquePOS-Token`.
- Enumera impresoras del usuario mediante Windows Print Spooler.
- Envía trabajos `RAW` al spooler, ideal para TSPL en TSC TE200.
- Se instala en `%LOCALAPPDATA%\TiquePOS\PrintConnector` y registra inicio automático en HKCU.

Compilación:

```bash
GOOS=windows GOARCH=amd64 go build -trimpath -ldflags="-s -w -H=windowsgui" -o TiquePOSPrintConnector.exe main_windows.go
```
