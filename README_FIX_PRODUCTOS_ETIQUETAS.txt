TiquePOS - Fix buscador de productos en Etiquetas

Reemplazar manteniendo las mismas rutas:
- Models/Product.php
- Controllers/Product.php
- Views/modules/scripts/labels.js

Cambios principales:
1. El catálogo de etiquetas ya NO usa UNION entre articulo y articulo_variacion.
   Esto evita errores de collations distintas entre ambas tablas.
2. Los productos simples se obtienen desde listarGestionProductos(), la misma
   fuente estable que usa el módulo Productos.
3. Las variantes se agregan en PHP y conservan su SKU propio y SKU padre.
4. Si el endpoint específico de etiquetas fallara por alguna diferencia de
   servidor, el frontend usa automáticamente listar_json_todo como respaldo,
   mostrando al menos los productos simples en vez de una pantalla de error.
5. La búsqueda sigue ejecutándose mientras se escribe (debounce 110 ms).

Después de subir los archivos: Ctrl + F5.
