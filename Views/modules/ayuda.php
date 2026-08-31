<?php
ob_start();
session_start();

if (!isset($_SESSION['nombre'])) {
    header('location: login');
    exit();
}

require 'header.php';
require 'sidebar.php';
?>

<style>
    :root {
        --help-primary: #6777ef;
        --help-primary-soft: #eef0ff;
        --help-success: #47c363;
        --help-warning: #ffa426;
        --help-danger: #fc544b;
        --help-info: #3abaf4;
        --help-dark: #34395e;
        --help-text: #5b6078;
        --help-muted: #98a6ad;
        --help-border: #edf0f5;
        --help-bg: #f7f8fc;
    }

    .help-page {
        color: var(--help-text);
    }

    .help-hero {
        position: relative;
        overflow: hidden;
        border-radius: 18px;
        padding: 34px;
        margin-bottom: 24px;
        background: linear-gradient(135deg, #5d6dee 0%, #7382f5 100%);
        color: #fff;
        box-shadow: 0 10px 28px rgba(103, 119, 239, .18);
    }

    .help-hero:after {
        content: "";
        position: absolute;
        width: 260px;
        height: 260px;
        border-radius: 50%;
        right: -75px;
        top: -105px;
        background: rgba(255, 255, 255, .08);
    }

    .help-hero:before {
        content: "";
        position: absolute;
        width: 170px;
        height: 170px;
        border-radius: 50%;
        right: 110px;
        bottom: -110px;
        background: rgba(255, 255, 255, .06);
    }

    .help-hero h1 {
        color: #fff;
        font-size: 30px;
        font-weight: 700;
        margin-bottom: 8px;
        position: relative;
        z-index: 2;
    }

    .help-hero p {
        max-width: 780px;
        margin-bottom: 22px;
        color: rgba(255,255,255,.9);
        font-size: 15px;
        line-height: 1.7;
        position: relative;
        z-index: 2;
    }

    .help-search-wrap {
        max-width: 720px;
        position: relative;
        z-index: 2;
    }

    .help-search-wrap i {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: #8d95b8;
        font-size: 15px;
    }

    #helpSearch {
        height: 50px;
        border: 0;
        border-radius: 12px;
        padding-left: 48px;
        padding-right: 18px;
        box-shadow: 0 6px 18px rgba(35, 43, 95, .15);
        font-size: 14px;
    }

    .help-layout {
        display: grid;
        grid-template-columns: 250px minmax(0, 1fr);
        gap: 24px;
        align-items: start;
    }

    .help-nav {
        position: sticky;
        top: 90px;
    }

    .help-nav-card,
    .help-content-card {
        border: 0;
        border-radius: 15px;
        box-shadow: 0 4px 18px rgba(45, 55, 90, .06);
    }

    .help-nav-title {
        font-weight: 700;
        color: var(--help-dark);
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: .5px;
        margin-bottom: 12px;
    }

    .help-nav a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 9px;
        color: #687188;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: .2s ease;
        margin-bottom: 3px;
    }

    .help-nav a:hover,
    .help-nav a.active {
        background: var(--help-primary-soft);
        color: var(--help-primary);
    }

    .help-nav a i {
        width: 18px;
        text-align: center;
    }

    .help-section {
        scroll-margin-top: 95px;
        margin-bottom: 22px;
    }

    .help-section-title {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 14px;
    }

    .help-section-title .icon {
        width: 42px;
        height: 42px;
        border-radius: 11px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--help-primary-soft);
        color: var(--help-primary);
        flex-shrink: 0;
    }

    .help-section-title h3 {
        color: var(--help-dark);
        font-size: 19px;
        font-weight: 700;
        margin: 0;
    }

    .help-section-title p {
        margin: 2px 0 0;
        color: var(--help-muted);
        font-size: 12px;
    }

    .help-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .help-item {
        border: 1px solid var(--help-border);
        border-radius: 12px;
        padding: 18px;
        background: #fff;
        transition: .2s ease;
    }

    .help-item:hover {
        border-color: #dfe3fb;
        box-shadow: 0 5px 18px rgba(103, 119, 239, .07);
        transform: translateY(-1px);
    }

    .help-item h5 {
        color: var(--help-dark);
        font-size: 14px;
        font-weight: 700;
        margin-bottom: 9px;
        line-height: 1.45;
    }

    .help-item p,
    .help-item li {
        font-size: 13px;
        line-height: 1.7;
    }

    .help-item p:last-child {
        margin-bottom: 0;
    }

    .help-item ul {
        padding-left: 18px;
        margin-bottom: 0;
    }

    .help-tag {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border-radius: 20px;
        padding: 4px 9px;
        font-size: 11px;
        font-weight: 700;
        margin-bottom: 9px;
    }

    .tag-primary { background: #eef0ff; color: #6777ef; }
    .tag-success { background: #eaf8ed; color: #39a952; }
    .tag-warning { background: #fff4e4; color: #d98a13; }
    .tag-danger  { background: #ffeded; color: #e4463e; }
    .tag-info    { background: #e8f7fd; color: #239ed4; }
    .tag-dark    { background: #eef0f4; color: #505773; }

    .help-alert {
        border-radius: 12px;
        padding: 15px 17px;
        display: flex;
        gap: 12px;
        align-items: flex-start;
        font-size: 13px;
        line-height: 1.65;
        margin-bottom: 14px;
    }

    .help-alert i {
        margin-top: 3px;
    }

    .help-alert-info {
        background: #eef8fd;
        color: #34728e;
        border: 1px solid #d8eff9;
    }

    .help-alert-warning {
        background: #fff8ec;
        color: #8b691f;
        border: 1px solid #f6e5c2;
    }

    .help-alert-danger {
        background: #fff0ef;
        color: #9a4b47;
        border: 1px solid #f7d9d6;
    }

    .deadline-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        overflow: hidden;
        border: 1px solid var(--help-border);
        border-radius: 12px;
        font-size: 13px;
    }

    .deadline-table th {
        background: #f8f9fd;
        color: var(--help-dark);
        font-weight: 700;
        padding: 12px 14px;
        border-bottom: 1px solid var(--help-border);
    }

    .deadline-table td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--help-border);
        vertical-align: top;
        line-height: 1.55;
    }

    .deadline-table tr:last-child td {
        border-bottom: 0;
    }

    .doc-step {
        display: flex;
        gap: 12px;
        margin-bottom: 13px;
    }

    .doc-step:last-child { margin-bottom: 0; }

    .doc-step-number {
        width: 29px;
        height: 29px;
        border-radius: 50%;
        flex: 0 0 29px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--help-primary-soft);
        color: var(--help-primary);
        font-size: 12px;
        font-weight: 800;
    }

    .doc-step strong {
        display: block;
        color: var(--help-dark);
        margin-bottom: 2px;
        font-size: 13px;
    }

    .doc-step span {
        font-size: 12px;
        line-height: 1.55;
    }

    .faq-item {
        border: 1px solid var(--help-border);
        border-radius: 11px;
        overflow: hidden;
        background: #fff;
        margin-bottom: 9px;
    }

    .faq-question {
        width: 100%;
        border: 0;
        background: #fff;
        padding: 15px 16px;
        text-align: left;
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        color: var(--help-dark);
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }

    .faq-question:focus {
        outline: none;
    }

    .faq-question i {
        color: var(--help-primary);
        transition: .2s ease;
    }

    .faq-item.open .faq-question i {
        transform: rotate(180deg);
    }

    .faq-answer {
        display: none;
        padding: 0 16px 15px;
        font-size: 13px;
        line-height: 1.7;
    }

    .faq-item.open .faq-answer {
        display: block;
    }

    .help-no-results {
        display: none;
        text-align: center;
        border: 1px dashed #d8dcea;
        border-radius: 13px;
        padding: 35px 20px;
        color: var(--help-muted);
        background: #fff;
    }

    .help-no-results i {
        display: block;
        font-size: 30px;
        margin-bottom: 10px;
        color: #c3c9db;
    }

    .help-footer-note {
        margin-top: 10px;
        padding: 16px;
        border-radius: 12px;
        background: #f8f9fd;
        border: 1px solid var(--help-border);
        font-size: 12px;
        line-height: 1.65;
        color: #7a8298;
    }

    .text-strong {
        color: var(--help-dark);
        font-weight: 700;
    }

    @media (max-width: 991.98px) {
        .help-layout {
            grid-template-columns: 1fr;
        }

        .help-nav {
            position: static;
        }

        .help-nav-card .card-body {
            display: flex;
            gap: 6px;
            overflow-x: auto;
            padding-bottom: 14px;
        }

        .help-nav-title {
            display: none;
        }

        .help-nav a {
            white-space: nowrap;
            margin-bottom: 0;
        }
    }

    @media (max-width: 767.98px) {
        .help-hero {
            padding: 25px 20px;
            border-radius: 14px;
        }

        .help-hero h1 {
            font-size: 24px;
        }

        .help-grid {
            grid-template-columns: 1fr;
        }

        .deadline-table {
            display: block;
            overflow-x: auto;
            white-space: normal;
        }
    }
</style>

<div class="main-content help-page">
    <section class="section">
        <div class="section-body">

            <div class="help-hero">
                <h1><i class="fas fa-book-open mr-2"></i> Centro de Ayuda</h1>
                <p>
                    Guía rápida para conocer qué documento utilizar en cada operación,
                    cómo corregir un comprobante y qué debes tener en cuenta al emitir
                    facturas, boletas, notas de crédito, notas de débito, notas de venta
                    y cotizaciones.
                </p>

                <div class="help-search-wrap">
                    <i class="fas fa-search"></i>
                    <input
                        type="text"
                        id="helpSearch"
                        class="form-control"
                        placeholder="Buscar: factura, boleta, nota de crédito, plazo, cotización..."
                        autocomplete="off"
                    >
                </div>
            </div>

            <div class="help-layout">

                <aside class="help-nav">
                    <div class="card help-nav-card">
                        <div class="card-body">
                            <div class="help-nav-title">Documentación</div>

                            <a href="#inicio" class="active">
                                <i class="fas fa-home"></i> Información básica
                            </a>
                            <a href="#plazos">
                                <i class="far fa-clock"></i> Emisión y envío
                            </a>
                            <a href="#factura">
                                <i class="fas fa-file-invoice"></i> Factura
                            </a>
                            <a href="#boleta">
                                <i class="fas fa-receipt"></i> Boleta
                            </a>
                            <a href="#credito">
                                <i class="fas fa-file-medical"></i> Nota de crédito
                            </a>
                            <a href="#debito">
                                <i class="fas fa-file-invoice-dollar"></i> Nota de débito
                            </a>
                            <a href="#notaventa">
                                <i class="fas fa-sticky-note"></i> Nota de venta
                            </a>
                            <a href="#cotizacion">
                                <i class="fas fa-calculator"></i> Cotización
                            </a>
                            <a href="#estados">
                                <i class="fas fa-cloud-upload-alt"></i> Estados SUNAT
                            </a>
                            <a href="#faq">
                                <i class="fas fa-question-circle"></i> Preguntas frecuentes
                            </a>
                        </div>
                    </div>
                </aside>

                <main id="helpContent">

                    <div id="noHelpResults" class="help-no-results">
                        <i class="fas fa-search"></i>
                        <strong>No encontramos resultados</strong>
                        <div>Prueba con palabras como “boleta”, “factura”, “crédito”, “SUNAT” o “cotización”.</div>
                    </div>

                    <!-- INFORMACIÓN BÁSICA -->
                    <section id="inicio" class="help-section help-searchable">
                        <div class="card help-content-card">
                            <div class="card-body">
                                <div class="help-section-title">
                                    <span class="icon"><i class="fas fa-info-circle"></i></span>
                                    <div>
                                        <h3>Información básica</h3>
                                        <p>Qué documento escoger según el tipo de operación.</p>
                                    </div>
                                </div>

                                <div class="help-alert help-alert-info">
                                    <i class="fas fa-lightbulb"></i>
                                    <div>
                                        <strong>Regla práctica:</strong>
                                        si la operación ya ocurrió y corresponde emitir un comprobante,
                                        utiliza <strong>factura o boleta</strong>. Si solo estás informando
                                        precios antes de confirmar una venta, utiliza una <strong>cotización</strong>.
                                        Una <strong>nota de venta</strong> sirve como control comercial interno,
                                        pero no reemplaza al comprobante de pago exigible.
                                    </div>
                                </div>

                                <div class="help-grid">
                                    <article class="help-item">
                                        <span class="help-tag tag-primary"><i class="fas fa-building"></i> Empresa / RUC</span>
                                        <h5>¿El cliente necesita sustentar gasto o crédito fiscal?</h5>
                                        <p>
                                            Normalmente corresponde emitir una <strong>factura</strong>,
                                            siempre que se cumplan los requisitos aplicables.
                                        </p>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-success"><i class="fas fa-user"></i> Consumidor final</span>
                                        <h5>¿La venta es para una persona como consumidor final?</h5>
                                        <p>
                                            Normalmente corresponde emitir una <strong>boleta de venta</strong>.
                                        </p>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-warning"><i class="fas fa-undo"></i> Disminuir / corregir</span>
                                        <h5>¿Necesitas anular, devolver o reducir un importe?</h5>
                                        <p>
                                            Revisa si corresponde una <strong>nota de crédito</strong>
                                            vinculada al comprobante original.
                                        </p>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-danger"><i class="fas fa-plus"></i> Aumentar</span>
                                        <h5>¿Necesitas incrementar un costo posterior?</h5>
                                        <p>
                                            Revisa si corresponde una <strong>nota de débito</strong>
                                            vinculada al comprobante original.
                                        </p>
                                    </article>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- PLAZOS -->
                    <section id="plazos" class="help-section help-searchable">
                        <div class="card help-content-card">
                            <div class="card-body">
                                <div class="help-section-title">
                                    <span class="icon"><i class="far fa-clock"></i></span>
                                    <div>
                                        <h3>Emisión y envío a SUNAT</h3>
                                        <p>No confundas el momento de emitir con el plazo técnico de envío.</p>
                                    </div>
                                </div>

                                <div class="help-alert help-alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <div>
                                        <strong>Recomendación del sistema:</strong>
                                        emite y envía el comprobante a SUNAT/OSE en el mismo momento de la operación.
                                        Los plazos máximos existen para determinadas modalidades de emisión,
                                        pero no deberían utilizarse como una espera habitual.
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="deadline-table">
                                        <thead>
                                            <tr>
                                                <th>Situación</th>
                                                <th>¿Cuándo?</th>
                                                <th>Recomendación</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><strong>Venta de bienes</strong></td>
                                                <td>Al entregar el bien o recibir el pago, lo que ocurra primero.</td>
                                                <td>Emitir inmediatamente.</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Prestación de servicios</strong></td>
                                                <td>
                                                    Cuando ocurra primero: culminación del servicio,
                                                    cobro parcial/total o vencimiento del plazo pactado.
                                                </td>
                                                <td>No postergar la emisión.</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Factura y nota vinculada en Facturador SUNAT (SFS)</strong></td>
                                                <td>
                                                    Puede remitirse en la fecha de emisión o dentro del plazo
                                                    máximo previsto por SUNAT para esa modalidad.
                                                </td>
                                                <td>Enviar el mismo día.</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Boletas desde sistemas del contribuyente / OSE</strong></td>
                                                <td>
                                                    El plazo depende de si se envían individualmente o mediante
                                                    resumen diario y de la modalidad SEE/OSE utilizada.
                                                </td>
                                                <td>El sistema debe automatizar el envío.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="help-alert help-alert-info mt-3 mb-0">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div>
                                        <strong>Importante:</strong>
                                        “Emitido al cliente” no siempre significa “aceptado por SUNAT”.
                                        Verifica el estado o CDR. Si SUNAT/OSE rechaza el comprobante,
                                        este no obtiene la validez tributaria esperada y debe corregirse
                                        según el motivo del rechazo.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- FACTURA -->
                    <section id="factura" class="help-section help-searchable">
                        <div class="card help-content-card">
                            <div class="card-body">
                                <div class="help-section-title">
                                    <span class="icon"><i class="fas fa-file-invoice"></i></span>
                                    <div>
                                        <h3>Factura electrónica</h3>
                                        <p>Para operaciones donde el adquirente requiere un comprobante con efectos tributarios.</p>
                                    </div>
                                </div>

                                <div class="help-grid">
                                    <article class="help-item">
                                        <span class="help-tag tag-primary">Cuándo usarla</span>
                                        <h5>¿Cuándo se emite una factura?</h5>
                                        <p>
                                            Principalmente cuando el adquirente cuenta con <strong>RUC</strong>
                                            y necesita sustentar costo, gasto o ejercer crédito fiscal,
                                            siempre que corresponda según las normas tributarias.
                                        </p>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-info">Datos</span>
                                        <h5>Antes de emitir</h5>
                                        <ul>
                                            <li>Verifica el RUC del cliente.</li>
                                            <li>Confirma razón social.</li>
                                            <li>Revisa descripción, cantidad y precio.</li>
                                            <li>Verifica impuestos y moneda.</li>
                                            <li>Confirma forma y condiciones de pago.</li>
                                        </ul>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-warning">Error común</span>
                                        <h5>¿Puedo cambiar una factura después de emitirla?</h5>
                                        <p>
                                            No se debe editar directamente un comprobante ya emitido y validado.
                                            La corrección se realiza mediante los mecanismos permitidos,
                                            normalmente una <strong>nota de crédito</strong> o documento relacionado.
                                        </p>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-success">Ejemplo</span>
                                        <h5>Ejemplo sencillo</h5>
                                        <p>
                                            Una empresa compra S/ 1,000 en mercadería y solicita comprobante
                                            con su RUC para registrar el gasto. Corresponde evaluar la emisión
                                            de una <strong>factura</strong>.
                                        </p>
                                    </article>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- BOLETA -->
                    <section id="boleta" class="help-section help-searchable">
                        <div class="card help-content-card">
                            <div class="card-body">
                                <div class="help-section-title">
                                    <span class="icon"><i class="fas fa-receipt"></i></span>
                                    <div>
                                        <h3>Boleta de venta electrónica</h3>
                                        <p>Comprobante utilizado normalmente para consumidores finales.</p>
                                    </div>
                                </div>

                                <div class="help-grid">
                                    <article class="help-item">
                                        <span class="help-tag tag-success">Consumidor final</span>
                                        <h5>¿Cuándo se emite una boleta?</h5>
                                        <p>
                                            Cuando se vende un bien o presta un servicio a un
                                            <strong>consumidor final</strong>.
                                        </p>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-info">Efecto tributario</span>
                                        <h5>¿La boleta da crédito fiscal?</h5>
                                        <p>
                                            Como regla general, <strong>no permite ejercer crédito fiscal</strong>
                                            ni sustentar costo o gasto, salvo las excepciones previstas por la normativa.
                                        </p>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-warning">Ventas pequeñas</span>
                                        <h5>¿Qué ocurre con operaciones muy pequeñas?</h5>
                                        <p>
                                            SUNAT contempla reglas específicas para ventas a consumidores finales
                                            que no superan S/ 5. Si el cliente exige su comprobante, debe entregarse.
                                        </p>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-primary">Ejemplo</span>
                                        <h5>Ejemplo sencillo</h5>
                                        <p>
                                            Una persona compra un producto para uso personal y no requiere
                                            factura. Normalmente corresponde emitir una <strong>boleta</strong>.
                                        </p>
                                    </article>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- NOTA DE CRÉDITO -->
                    <section id="credito" class="help-section help-searchable">
                        <div class="card help-content-card">
                            <div class="card-body">
                                <div class="help-section-title">
                                    <span class="icon"><i class="fas fa-file-medical"></i></span>
                                    <div>
                                        <h3>Nota de crédito</h3>
                                        <p>Se vincula a un comprobante previo para disminuir, corregir o dejar sin efecto ciertos importes.</p>
                                    </div>
                                </div>

                                <div class="help-alert help-alert-info">
                                    <i class="fas fa-link"></i>
                                    <div>
                                        La nota de crédito <strong>no es una venta nueva</strong>.
                                        Debe estar vinculada al comprobante que se desea modificar.
                                    </div>
                                </div>

                                <div class="help-grid">
                                    <article class="help-item">
                                        <span class="help-tag tag-warning">Casos frecuentes</span>
                                        <h5>¿Cuándo se hace una nota de crédito?</h5>
                                        <ul>
                                            <li>Anulación permitida de una operación.</li>
                                            <li>Devolución total o parcial.</li>
                                            <li>Descuento posterior.</li>
                                            <li>Bonificación.</li>
                                            <li>Disminución del valor facturado.</li>
                                            <li>Determinadas correcciones permitidas.</li>
                                        </ul>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-danger">No confundir</span>
                                        <h5>¿Sirve para aumentar el importe?</h5>
                                        <p>
                                            No. Si el objetivo es aumentar un importe por un gasto o costo posterior,
                                            revisa si corresponde una <strong>nota de débito</strong>.
                                        </p>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-info">Ejemplo</span>
                                        <h5>Devolución</h5>
                                        <p>
                                            Se emitió una factura por S/ 500 y luego el cliente devuelve
                                            productos por S/ 100. La operación puede requerir una
                                            nota de crédito por la disminución correspondiente.
                                        </p>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-warning">Cuidado</span>
                                        <h5>Errores de cliente o descripción</h5>
                                        <p>
                                            Existen reglas y plazos especiales para determinados supuestos
                                            de corrección o anulación. Antes de procesarlos, verifica el motivo
                                            exacto y la modalidad de emisión utilizada.
                                        </p>
                                    </article>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- NOTA DE DÉBITO -->
                    <section id="debito" class="help-section help-searchable">
                        <div class="card help-content-card">
                            <div class="card-body">
                                <div class="help-section-title">
                                    <span class="icon"><i class="fas fa-file-invoice-dollar"></i></span>
                                    <div>
                                        <h3>Nota de débito</h3>
                                        <p>Documento vinculado a una factura o boleta previamente emitida.</p>
                                    </div>
                                </div>

                                <div class="help-grid">
                                    <article class="help-item">
                                        <span class="help-tag tag-danger">Incremento</span>
                                        <h5>¿Cuándo se hace una nota de débito?</h5>
                                        <p>
                                            Se utiliza para recuperar o cargar determinados
                                            <strong>gastos o costos incurridos posteriormente</strong>
                                            a la emisión del comprobante original, de acuerdo con las reglas aplicables.
                                        </p>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-warning">Importante</span>
                                        <h5>¿Puedo usarla para cualquier cobro adicional?</h5>
                                        <p>
                                            No necesariamente. El motivo debe corresponder a un supuesto permitido.
                                            SUNAT señala que este documento no aplica para penalidades en la modalidad descrita.
                                        </p>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-info">Ejemplo</span>
                                        <h5>Costo posterior</h5>
                                        <p>
                                            Si después de emitir el comprobante aparece un gasto adicional
                                            recuperable y permitido, puede corresponder una nota de débito
                                            vinculada al documento original.
                                        </p>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-dark">Diferencia rápida</span>
                                        <h5>Crédito vs. débito</h5>
                                        <p>
                                            <strong>Nota de crédito:</strong> normalmente disminuye o revierte.<br>
                                            <strong>Nota de débito:</strong> normalmente incrementa por conceptos permitidos.
                                        </p>
                                    </article>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- NOTA DE VENTA -->
                    <section id="notaventa" class="help-section help-searchable">
                        <div class="card help-content-card">
                            <div class="card-body">
                                <div class="help-section-title">
                                    <span class="icon"><i class="fas fa-sticky-note"></i></span>
                                    <div>
                                        <h3>Nota de venta</h3>
                                        <p>Documento comercial interno. No debe confundirse con un comprobante de pago tributario.</p>
                                    </div>
                                </div>

                                <div class="help-alert help-alert-danger">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <div>
                                        <strong>La nota de venta no reemplaza una factura ni una boleta.</strong>
                                        Puede servir para pedidos, separación, control interno o registro comercial,
                                        pero si la operación genera obligación de emitir comprobante de pago,
                                        deberá generarse el comprobante correspondiente.
                                    </div>
                                </div>

                                <div class="help-grid">
                                    <article class="help-item">
                                        <span class="help-tag tag-dark">Uso interno</span>
                                        <h5>¿Para qué sirve?</h5>
                                        <ul>
                                            <li>Registrar pedidos.</li>
                                            <li>Controlar ventas pendientes.</li>
                                            <li>Preparar una operación antes de facturar.</li>
                                            <li>Reservar productos o servicios.</li>
                                        </ul>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-danger">No sustituye</span>
                                        <h5>¿Tiene el mismo valor que una factura o boleta?</h5>
                                        <p>
                                            No debe presentarse como sustituto del comprobante de pago
                                            exigido por la normativa tributaria.
                                        </p>
                                    </article>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- COTIZACIÓN -->
                    <section id="cotizacion" class="help-section help-searchable">
                        <div class="card help-content-card">
                            <div class="card-body">
                                <div class="help-section-title">
                                    <span class="icon"><i class="fas fa-calculator"></i></span>
                                    <div>
                                        <h3>Cotizaciones</h3>
                                        <p>Propuesta comercial previa a una venta o prestación de servicio.</p>
                                    </div>
                                </div>

                                <div class="help-grid">
                                    <article class="help-item">
                                        <span class="help-tag tag-primary">Antes de vender</span>
                                        <h5>¿Cuándo hago una cotización?</h5>
                                        <p>
                                            Cuando el cliente todavía está evaluando la compra y deseas
                                            informarle productos, servicios, cantidades, precios,
                                            descuentos y condiciones antes de confirmar la operación.
                                        </p>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-warning">No es comprobante</span>
                                        <h5>¿La cotización reemplaza una factura o boleta?</h5>
                                        <p>
                                            No. La cotización es una propuesta comercial.
                                            Cuando la operación se concreta, debe emitirse el documento
                                            que corresponda según la operación.
                                        </p>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-info">Sugerencia</span>
                                        <h5>¿Qué información debería incluir?</h5>
                                        <ul>
                                            <li>Datos del cliente.</li>
                                            <li>Productos o servicios.</li>
                                            <li>Cantidades y precios.</li>
                                            <li>Impuestos, si corresponde.</li>
                                            <li>Validez de la oferta.</li>
                                            <li>Condiciones de pago y entrega.</li>
                                        </ul>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-success">Flujo</span>
                                        <h5>Flujo recomendado</h5>
                                        <div class="doc-step">
                                            <span class="doc-step-number">1</span>
                                            <div><strong>Cotización</strong><span>El cliente evalúa la propuesta.</span></div>
                                        </div>
                                        <div class="doc-step">
                                            <span class="doc-step-number">2</span>
                                            <div><strong>Aprobación</strong><span>Se confirma precio, cantidad y condiciones.</span></div>
                                        </div>
                                        <div class="doc-step">
                                            <span class="doc-step-number">3</span>
                                            <div><strong>Venta</strong><span>Se genera factura o boleta cuando corresponda.</span></div>
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- ESTADOS SUNAT -->
                    <section id="estados" class="help-section help-searchable">
                        <div class="card help-content-card">
                            <div class="card-body">
                                <div class="help-section-title">
                                    <span class="icon"><i class="fas fa-cloud-upload-alt"></i></span>
                                    <div>
                                        <h3>Estados de envío a SUNAT / OSE</h3>
                                        <p>Cómo interpretar de forma sencilla la respuesta del comprobante.</p>
                                    </div>
                                </div>

                                <div class="help-grid">
                                    <article class="help-item">
                                        <span class="help-tag tag-warning">Pendiente</span>
                                        <h5>Pendiente de envío</h5>
                                        <p>
                                            El comprobante fue generado en el sistema pero aún debe
                                            procesarse o remitirse al servicio correspondiente.
                                        </p>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-info">En proceso</span>
                                        <h5>Enviado / procesando</h5>
                                        <p>
                                            El comprobante fue remitido y el sistema espera la respuesta
                                            de SUNAT/OSE.
                                        </p>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-success">Correcto</span>
                                        <h5>Aceptado</h5>
                                        <p>
                                            El documento superó las validaciones del servicio.
                                            Conserva el XML y la constancia/respuesta correspondiente.
                                        </p>
                                    </article>

                                    <article class="help-item">
                                        <span class="help-tag tag-danger">Revisar</span>
                                        <h5>Rechazado</h5>
                                        <p>
                                            El comprobante no superó una validación.
                                            Revisa el mensaje de SUNAT/OSE, corrige la causa y sigue
                                            el procedimiento correspondiente.
                                        </p>
                                    </article>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- FAQ -->
                    <section id="faq" class="help-section help-searchable">
                        <div class="card help-content-card">
                            <div class="card-body">
                                <div class="help-section-title">
                                    <span class="icon"><i class="fas fa-question-circle"></i></span>
                                    <div>
                                        <h3>Preguntas frecuentes</h3>
                                        <p>Respuestas rápidas para las dudas más comunes.</p>
                                    </div>
                                </div>

                                <div class="faq-list">

                                    <div class="faq-item">
                                        <button type="button" class="faq-question">
                                            <span>¿Puedo emitir una boleta y luego convertirla directamente en factura?</span>
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="faq-answer">
                                            No se debe “editar” el tipo de comprobante ya emitido como si fuera
                                            el mismo documento. Si hubo un error, debe evaluarse el procedimiento
                                            de corrección/anulación permitido y luego emitir el comprobante correcto
                                            cuando corresponda.
                                        </div>
                                    </div>

                                    <div class="faq-item">
                                        <button type="button" class="faq-question">
                                            <span>¿Qué hago si escribí mal los datos del cliente?</span>
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="faq-answer">
                                            No modifiques manualmente un XML o comprobante ya emitido.
                                            Revisa el tipo de error, el estado del comprobante y el mecanismo
                                            permitido para corregirlo. Algunos supuestos tienen reglas y plazos específicos.
                                        </div>
                                    </div>

                                    <div class="faq-item">
                                        <button type="button" class="faq-question">
                                            <span>¿La nota de crédito sirve para una devolución?</span>
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="faq-answer">
                                            Sí, las devoluciones son uno de los casos típicos asociados
                                            a una nota de crédito, siempre vinculándola al comprobante previo
                                            y consignando correctamente el motivo.
                                        </div>
                                    </div>

                                    <div class="faq-item">
                                        <button type="button" class="faq-question">
                                            <span>¿La nota de débito es lo contrario de la nota de crédito?</span>
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="faq-answer">
                                            De forma práctica, la nota de crédito suele disminuir o revertir importes,
                                            mientras que la nota de débito se emplea para determinados gastos o costos
                                            posteriores que incrementan lo adeudado. Cada documento debe utilizarse
                                            solo en los supuestos permitidos.
                                        </div>
                                    </div>

                                    <div class="faq-item">
                                        <button type="button" class="faq-question">
                                            <span>¿Una cotización tiene que enviarse a SUNAT?</span>
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="faq-answer">
                                            Una cotización es una propuesta comercial y no es el comprobante
                                            de pago de la operación. No debe confundirse con la factura o boleta
                                            que corresponda cuando la venta o servicio se concrete.
                                        </div>
                                    </div>

                                    <div class="faq-item">
                                        <button type="button" class="faq-question">
                                            <span>¿Una nota de venta reemplaza la boleta?</span>
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="faq-answer">
                                            No. Puede utilizarse como documento comercial o control interno,
                                            pero no debe sustituir el comprobante de pago que corresponda emitir.
                                        </div>
                                    </div>

                                    <div class="faq-item">
                                        <button type="button" class="faq-question">
                                            <span>¿Debo esperar el último día del plazo para enviar a SUNAT?</span>
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="faq-answer">
                                            No. La mejor práctica es remitir el comprobante inmediatamente
                                            y comprobar su aceptación. De esa manera, cualquier rechazo puede
                                            detectarse y corregirse oportunamente.
                                        </div>
                                    </div>

                                    <div class="faq-item">
                                        <button type="button" class="faq-question">
                                            <span>¿Qué hago si SUNAT rechaza un comprobante?</span>
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="faq-answer">
                                            Lee el código y mensaje de rechazo, revisa los datos enviados
                                            y corrige la causa. Un documento rechazado no debe tratarse como
                                            si hubiera sido aceptado correctamente.
                                        </div>
                                    </div>

                                    <div class="faq-item">
                                        <button type="button" class="faq-question">
                                            <span>¿Puedo borrar una factura aceptada para que desaparezca?</span>
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="faq-answer">
                                            No debe eliminarse del sistema como si nunca hubiera existido.
                                            Los comprobantes emitidos deben mantener trazabilidad.
                                            Si corresponde corregir o dejar sin efecto una operación,
                                            utiliza el procedimiento tributario aplicable.
                                        </div>
                                    </div>

                                    <div class="faq-item">
                                        <button type="button" class="faq-question">
                                            <span>¿Dónde reviso si mi comprobante fue aceptado?</span>
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="faq-answer">
                                            Revisa el módulo de estado de comprobantes de tu sistema
                                            y la respuesta SUNAT/OSE (CDR cuando corresponda).
                                            También puede verificarse la validez mediante los servicios
                                            de consulta habilitados por SUNAT.
                                        </div>
                                    </div>

                                </div>

                                <div class="help-footer-note">
                                    <strong>Nota informativa:</strong>
                                    esta sección funciona como guía general de uso del sistema.
                                    Las reglas tributarias pueden variar según el régimen, tipo de operación,
                                    sistema de emisión electrónica y modificaciones normativas.
                                    Ante un caso tributario particular, valida la operación con SUNAT
                                    o con el profesional contable responsable de la empresa.
                                </div>
                            </div>
                        </div>
                    </section>

                </main>
            </div>
        </div>
    </section>
</div>

<script>
(function () {
    const search = document.getElementById('helpSearch');
    const sections = Array.from(document.querySelectorAll('.help-searchable'));
    const noResults = document.getElementById('noHelpResults');
    const navLinks = Array.from(document.querySelectorAll('.help-nav a'));

    function normalizeText(text) {
        return (text || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    if (search) {
        search.addEventListener('input', function () {
            const query = normalizeText(this.value.trim());
            let visible = 0;

            sections.forEach(section => {
                const content = normalizeText(section.innerText);
                const match = !query || content.includes(query);

                section.style.display = match ? '' : 'none';

                if (match) {
                    visible++;
                }
            });

            if (noResults) {
                noResults.style.display = visible === 0 ? 'block' : 'none';
            }
        });
    }

    document.querySelectorAll('.faq-question').forEach(button => {
        button.addEventListener('click', function () {
            const item = this.closest('.faq-item');
            item.classList.toggle('open');
        });
    });

    navLinks.forEach(link => {
        link.addEventListener('click', function () {
            navLinks.forEach(item => item.classList.remove('active'));
            this.classList.add('active');
        });
    });

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;

                const id = entry.target.getAttribute('id');
                const target = document.querySelector('.help-nav a[href="#' + id + '"]');

                if (target) {
                    navLinks.forEach(item => item.classList.remove('active'));
                    target.classList.add('active');
                }
            });
        }, {
            rootMargin: '-110px 0px -65% 0px',
            threshold: 0
        });

        sections.forEach(section => observer.observe(section));
    }
})();
</script>

<?php
require 'footer.php';
ob_end_flush();
?>
