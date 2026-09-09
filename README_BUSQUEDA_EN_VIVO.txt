TIQUEPOS - ETIQUETAS / BUSQUEDA EN VIVO

Archivos modificados:
- Models/Product.php
- Controllers/Product.php
- Views/modules/scripts/labels.js

Cambios principales:
1. La busqueda del modulo Etiquetas ahora consulta la base de datos mediante AJAX mientras se escribe (debounce 110 ms).
2. Busca por nombre, SKU simple, SKU de variante, SKU padre, variante, categoria y almacen.
3. Buscar el SKU padre muestra las variantes asociadas, cada una con su SKU imprimible.
4. Se corrigio la consulta de variantes que intentaba leer articulo_variacion.imagen; la imagen ahora se toma de articulo.imagen.
5. Las peticiones anteriores se cancelan al seguir escribiendo, evitando que respuestas viejas reemplacen resultados nuevos.
6. Los productos seleccionados se conservan aunque cambie la busqueda.

Despues de reemplazar los archivos, usar Ctrl+F5 en el navegador.
