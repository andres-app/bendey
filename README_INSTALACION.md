# TiquePOS · instalación limpia

## Objetivo

Este paquete instala una instancia independiente de TiquePOS. No es multiempresa: cada cliente conserva su propio hosting, su propia base de datos y su propia configuración. La administración de licencia/versiones se vincula con `admin.tiquepos.com` mediante el agente de Control.

## Instalación en app.tiquepos.com

1. Crear una base de datos MySQL/MariaDB **vacía** y asignarle un usuario con permisos completos sobre esa base.
2. Subir el contenido de este ZIP a la raíz de `app.tiquepos.com`.
3. Abrir `https://app.tiquepos.com/install/`.
4. Completar:
   - dominio;
   - host, puerto, nombre, usuario y contraseña de MySQL;
   - nombre/RUC/datos de la empresa;
   - usuario administrador;
   - configuración de Cloudflare Turnstile;
   - vínculo con `admin.tiquepos.com`.
5. El instalador comprueba que la BD esté vacía, importa `database/base.sql` y crea únicamente los datos propios de esta nueva empresa.
6. Al finalizar crea `Config/local.php` y `storage/installed.lock`; desde ese momento el instalador queda bloqueado.
7. La copia temporal de los secretos usada por el bootstrap se elimina al finalizar; las credenciales permanentes quedan en `Config/local.php`.
8. Crear un cron cada 2 minutos que ejecute `Control/sync.php` para heartbeat, licencia y despliegues.

## Base limpia

`database/base.sql` contiene las 50 tablas de la aplicación y conserva únicamente catálogos/datos genéricos necesarios para iniciar:

- tipos de comprobante con correlativos en cero;
- contado/crédito;
- efectivo, Yape, Plin, tarjeta y pago mixto;
- destinos/cuentas financieras genéricas;
- unidades de medida;
- motivos de nota de crédito;
- permisos del sistema;
- categorías genéricas de compras;
- catálogos SUNAT de afectación IGV y tipo de operación.

No contiene empresa, RUC, usuarios, clientes, proveedores, productos, categorías comerciales de Felicity, ventas, compras, caja histórica, cobranzas, correlativos usados, XML/CDR ni demás información transaccional de `sunat.felicitygirls.shop`.

## Datos creados por el instalador

Con los datos ingresados crea una sola empresa, sucursal principal, almacén principal, caja principal y primer usuario administrador. La instalación queda independiente de cualquier otro cliente.

## Archivos persistentes que no se reemplazan en actualizaciones

- `Config/local.php`
- `Config/control_public.pem`
- `storage/`
- `storage/images/products/`
- `storage/images/company/`
- `storage/images/users/`
- archivos/documentos generados por la empresa

## Cloudflare Turnstile

Para `app.tiquepos.com` el paquete ya incorpora la Site Key entregada para el widget existente y permite usar la Secret Key de bootstrap sin volver a escribirla. El login usa renderizado explícito y cada intento se valida en servidor mediante Siteverify, verificando además `action=login` y que el `hostname` coincida con el dominio configurado.

En Cloudflare, el widget existente debe tener autorizado `app.tiquepos.com` como hostname. Si se cambia de widget para otro cliente, el instalador permite ingresar su nueva Site Key y Secret Key.
