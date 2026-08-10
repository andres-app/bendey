<?php

ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['nombre'])) {
    header('Location: login');
    exit;
}

require 'header.php';
require 'sidebar.php';
?>

<style>
    .sire-proximamente-page .card {
        border: 0;
        border-radius: 18px;
        box-shadow: 0 12px 35px rgba(15, 23, 42, .07);
    }

    .sire-proximamente-page .card-header {
        min-height: 78px;
        padding: 18px 22px;
        border-bottom: 1px solid #edf1ef;
        background: #fff;
    }

    .sire-proximamente-page .card-header h4 {
        margin: 0 0 4px;
        color: #253129;
        font-size: 1.08rem;
        font-weight: 700;
    }

    .sire-proximamente-page .card-header p {
        margin: 0;
        color: #7d8981;
        font-size: .8rem;
    }

    .sire-coming-soon {
        min-height: 390px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 36px 20px;
        text-align: center;
    }

    .sire-coming-soon-inner {
        width: 100%;
        max-width: 520px;
    }

    .sire-coming-soon-icon {
        width: 82px;
        height: 82px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 18px;
        border-radius: 24px;
        color: #6777ef;
        background: #eef0ff;
        font-size: 2rem;
    }

    .sire-coming-soon h3 {
        margin-bottom: 8px;
        color: #26332b;
        font-size: 1.35rem;
        font-weight: 700;
    }

    .sire-coming-soon p {
        max-width: 450px;
        margin: 0 auto;
        color: #7b8780;
        font-size: .88rem;
        line-height: 1.6;
    }

    .sire-coming-soon-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 18px;
        padding: 7px 12px;
        border: 1px solid #dde3ff;
        border-radius: 999px;
        color: #5b68cf;
        background: #f8f8ff;
        font-size: .75rem;
        font-weight: 600;
    }
</style>

<div class="main-content sire-proximamente-page">
    <section class="section">
        <div class="section-body">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h4>SIRE Compras</h4>
                        <p>Sistema Integrado de Registros Electrónicos</p>
                    </div>
                </div>

                <div class="card-body">
                    <div class="sire-coming-soon">
                        <div class="sire-coming-soon-inner">
                            <span class="sire-coming-soon-icon">
                                <i class="fas fa-file-invoice"></i>
                            </span>

                            <h3>Funcionalidad próximamente</h3>

                            <p>
                                Esta sección ya está habilitada para navegación.
                                La integración y gestión de SIRE se incorporará
                                en una siguiente etapa.
                            </p>

                            <span class="sire-coming-soon-badge">
                                <i class="fas fa-tools"></i>
                                En desarrollo
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
require 'footer.php';
ob_end_flush();
?>
