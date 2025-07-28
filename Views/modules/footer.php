<footer class="main-footer">
    <div class="footer-left">
        Todos los derechos reservados &copy; <div class="bullet"></div> tiquepos.com
    </div>
    <div class="footer-right">
        v. 1.8
    </div>
</footer>
</div>
</div>

<!-- ✅ Framework JS base de Stisla -->
<script src="Assets/js/app.min.js"></script>

<!-- ✅ Select2 de Stisla -->
<script src="Assets/bundles/select2/dist/js/select2.full.min.js"></script>

<!-- ✅ DataTables -->
<script src="Assets/bundles/datatables/datatables.min.js"></script>
<script src="Assets/bundles/datatables/DataTables-1.10.16/js/dataTables.bootstrap4.min.js"></script>
<script src="Assets/bundles/datatables/export-tables/dataTables.buttons.min.js"></script>
<script src="Assets/bundles/datatables/export-tables/buttons.flash.min.js"></script>
<script src="Assets/bundles/datatables/export-tables/jszip.min.js"></script>
<script src="Assets/bundles/datatables/export-tables/pdfmake.min.js"></script>
<script src="Assets/bundles/datatables/export-tables/vfs_fonts.js"></script>
<script src="Assets/bundles/datatables/export-tables/buttons.print.min.js"></script>

<!-- ✅ SweetAlert integrado (si usas plugin local) -->
<script src="Assets/bundles/sweetalert/sweetalert.min.js"></script>

<!-- ✅ Scripts principales de Stisla -->
<script src="Assets/js/scripts.js"></script>

<!-- ✅ Script personalizado de cada módulo -->
<?php
$url = $_GET['url'] ?? '';
if ($url === 'producto') {
    echo '<script src="Views/modules/scripts/product.js"></script>';
}
?>

</body>
</html>
