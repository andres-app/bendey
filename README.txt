TiquePOS - Corrección buscador de etiquetas + rediseño Tailwind

Archivos modificados:
- Models/Product.php
- Views/modules/labels.php
- Views/modules/scripts/labels.js

Cambios principales:
1. Las variantes ahora incluyen codigo_padre (articulo.codigo) en el catálogo de etiquetas.
2. Buscar por Grupo/SKU padre muestra todas las variantes del producto.
3. Se priorizan coincidencias exactas de SKU y SKU padre.
4. El listado muestra SKU de variante y, cuando aplica, SKU padre.
5. Rediseño completo del módulo con Tailwind aislado (prefijo tw-, preflight desactivado).
6. El buscador separa visualmente el icono del texto.
7. Mejoras visuales en selección, cantidades, resumen, configuración, impresora y vista previa.
