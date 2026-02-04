$(document).ready(function () {
    $('#tbllistado').DataTable({
        responsive: true,
        autoWidth: false,
        scrollX: false,
        ajax: {
            url: 'Controllers/Sunat.php?op=listar',
            type: 'GET',
            dataType: 'json'
        },
        columns: [
            { data: "0", className: "text-center" },
            { data: "1" },
            { data: "2" },
            { data: "3", className: "text-right" },
            { data: "4", className: "text-center" },
            { data: "5", className: "text-center" },
            { data: "6", className: "text-center" },
            { data: "7" },
            { data: "8", className: "text-center" }
        ]
    });

});

function verDetalle(idventa) {

    $.post(
        'Controllers/Sunat.php?op=detalle',
        { idventa },
        function (r) {

            let botones = {};
            let footer = '';

            // ========= FASE A: TEXTO DEL BOTÓN =========
            if (!r.xml) {
                botones = { confirmButtonText: 'Generar XML' };

            } else if (r.xml && !r.cdr && r.estado !== 'EN_PROCESO') {
                botones = { confirmButtonText: 'Enviar a SUNAT' };

            } else if (r.estado === 'EN_PROCESO') {
                botones = { confirmButtonText: 'Consultar SUNAT' };

            } else {
                botones = { confirmButtonText: 'Ver CDR' };
                footer = `<a href="${r.cdr}" target="_blank">Descargar CDR</a>`;
            }

            Swal.fire({
                title: '📄 Comprobante Electrónico',
                html: `
                    <div style="text-align:left;font-size:13px">
                        <strong>${r.comprobante}</strong><br>
                        Cliente: ${r.cliente}<br>
                        Total: S/ ${r.total}<br>
                        Estado SUNAT: <strong>${r.estado}</strong>
                    </div>
                `,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#4f6bed',
                cancelButtonText: 'Cerrar',
                footer,
                ...botones
            }).then((result) => {

                if (!result.isConfirmed) return;

                // ========= FASE B: ACCIÓN =========
                if (!r.xml) {
                    generarXML(idventa);

                } else if (r.xml && !r.cdr && r.estado !== 'EN_PROCESO') {
                    enviarSunat(idventa);

                } else if (r.estado === 'EN_PROCESO') {
                    consultarEstado(idventa);

                } else {
                    window.open(r.cdr, '_blank');
                }
            });

        },
        'json'
    );
}



function generarXML(idventa) {

    console.log('ID enviado:', idventa); // 👈 DEBUG

    Swal.fire({
        title: 'Generando XML',
        text: 'Por favor espera...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    $.post(
        'Controllers/Sunat.php?op=generarxml',
        { idventa: idventa }, // 👈 CLAVE
        function (response) {

            Swal.close();
            console.log(response); // 👈 DEBUG

            if (response.status) {
                Swal.fire('Éxito', response.message, 'success');
                $('#tbllistado').DataTable().ajax.reload(null, false);
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        'json'
    );
}

function enviarSunat(idventa) {

    Swal.fire({
        title: 'Enviando a SUNAT',
        text: 'Espere un momento...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    $.post(
        'Controllers/Sunat.php?op=enviarsunat',
        { idventa: idventa },
        function (r) {

            Swal.close();

            if (r.status) {
                Swal.fire('SUNAT', r.message, 'success');
                $('#tbllistado').DataTable().ajax.reload(null, false);
            } else {
                Swal.fire('Error SUNAT', r.message, 'error');
            }

        },
        'json'
    );
}

function consultarEstado(idventa) {

    Swal.fire({
        title: 'Consultando SUNAT',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    $.post(
        'Controllers/Sunat.php?op=getStatus',
        { idventa },
        function (r) {
            Swal.close();

            if (r.status) {
                Swal.fire('SUNAT', r.mensaje, 'success');
                $('#tbllistado').DataTable().ajax.reload(null, false);
            } else {
                Swal.fire('Error SUNAT', r.mensaje, 'error');
            }
        },
        'json'
    );
}
