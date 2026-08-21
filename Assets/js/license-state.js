(function (window, document) {
    'use strict';

    const LICENSE_CODES = new Set([
        'SUSPENDED',
        'BLOCKED',
        'EXPIRED',
        'OFFLINE_EXPIRED',
        'UNVERIFIED'
    ]);

    let visible = false;

    function copyFor(code, fallbackMessage) {
        const status = String(code || 'UNVERIFIED').toUpperCase();
        const map = {
            SUSPENDED: {
                eyebrow: 'Estado de servicio',
                title: 'Servicio temporalmente suspendido',
                message: 'El acceso a TiquePOS para esta empresa está temporalmente suspendido. Tus datos permanecen seguros y no se ha eliminado ninguna información.',
                help: 'Para reactivar el servicio, comunícate con el administrador de tu cuenta.',
                badge: 'SUSPENDIDO'
            },
            BLOCKED: {
                eyebrow: 'Estado de servicio',
                title: 'Acceso temporalmente restringido',
                message: 'Esta instalación de TiquePOS se encuentra temporalmente restringida.',
                help: 'Comunícate con el administrador de tu cuenta para revisar el estado del servicio.',
                badge: 'RESTRINGIDO'
            },
            EXPIRED: {
                eyebrow: 'Estado de servicio',
                title: 'Periodo de servicio finalizado',
                message: 'El periodo habilitado para esta instalación ha finalizado. Tus datos permanecen almacenados de forma segura.',
                help: 'Renueva o reactiva el servicio para continuar utilizando TiquePOS.',
                badge: 'VENCIDO'
            },
            OFFLINE_EXPIRED: {
                eyebrow: 'Verificación de servicio',
                title: 'No pudimos validar el servicio',
                message: 'TiquePOS no ha podido renovar la validación de esta instalación dentro del periodo permitido.',
                help: 'Intenta nuevamente en unos minutos. Si continúa, comunícate con el administrador de tu cuenta.',
                badge: 'POR VERIFICAR'
            },
            UNVERIFIED: {
                eyebrow: 'Verificación de servicio',
                title: 'Estamos verificando tu servicio',
                message: fallbackMessage || 'No fue posible confirmar temporalmente el estado de esta instalación.',
                help: 'Intenta nuevamente en unos instantes.',
                badge: 'POR VERIFICAR'
            }
        };
        return map[status] || map.UNVERIFIED;
    }

    function ensureStyles() {
        if (document.getElementById('tp-license-ui-styles')) return;
        const style = document.createElement('style');
        style.id = 'tp-license-ui-styles';
        style.textContent = `
            #tpLicenseStateOverlay{position:fixed;inset:0;z-index:2147483647;display:grid;place-items:center;padding:22px;background:rgba(244,247,246,.94);backdrop-filter:blur(9px);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#17211d}
            #tpLicenseStateOverlay *{box-sizing:border-box}
            #tpLicenseStateOverlay .tp-license-card{width:min(100%,510px);background:#fff;border:1px solid #e1e9e5;border-radius:26px;box-shadow:0 26px 80px rgba(15,23,42,.13);padding:34px 34px 30px;text-align:center}
            #tpLicenseStateOverlay .tp-license-brand{display:inline-flex;align-items:center;font-size:27px;font-weight:850;letter-spacing:-.04em;color:#121b17}
            #tpLicenseStateOverlay .tp-license-brand span{color:#00a46a}
            #tpLicenseStateOverlay .tp-license-icon{width:66px;height:66px;margin:24px auto 18px;border-radius:20px;display:grid;place-items:center;background:#fff7e8;color:#d58b13;border:1px solid #f6dfb5}
            #tpLicenseStateOverlay .tp-license-icon svg{width:32px;height:32px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
            #tpLicenseStateOverlay .tp-license-eyebrow{font-size:12px;font-weight:760;letter-spacing:.08em;text-transform:uppercase;color:#718079}
            #tpLicenseStateOverlay h1{margin:8px 0 10px;font-size:23px;line-height:1.2;letter-spacing:-.025em;color:#16211c}
            #tpLicenseStateOverlay .tp-license-message{margin:0 auto;color:#66736d;line-height:1.62;font-size:14px;max-width:410px}
            #tpLicenseStateOverlay .tp-license-help{margin:16px auto 0;padding:13px 15px;background:#f6f9f8;border-radius:14px;color:#44534c;font-size:13px;line-height:1.5}
            #tpLicenseStateOverlay .tp-license-badge{display:inline-flex;margin-top:18px;padding:7px 10px;border-radius:999px;background:#fff7e8;color:#ad6c08;font-size:10px;font-weight:850;letter-spacing:.08em}
            #tpLicenseStateOverlay .tp-license-actions{display:flex;gap:10px;justify-content:center;margin-top:22px}
            #tpLicenseStateOverlay .tp-license-retry{appearance:none;border:0;border-radius:13px;background:#00a46a;color:#fff;padding:12px 20px;font:inherit;font-weight:750;cursor:pointer;box-shadow:0 9px 24px rgba(0,164,106,.18)}
            #tpLicenseStateOverlay .tp-license-retry:hover{background:#008d5b}
            #tpLicenseStateOverlay .tp-license-note{margin-top:16px;color:#95a19b;font-size:11px}
            @media(max-width:560px){#tpLicenseStateOverlay{padding:14px}#tpLicenseStateOverlay .tp-license-card{padding:28px 20px 25px;border-radius:22px}#tpLicenseStateOverlay h1{font-size:21px}}
        `;
        document.head.appendChild(style);
    }

    function show(payload) {
        const code = String(payload?.license_status || payload?.code || payload?.error_code || 'UNVERIFIED').toUpperCase();
        if (!LICENSE_CODES.has(code) || visible) return false;
        visible = true;
        ensureStyles();
        const ui = copyFor(code, payload?.message);
        const overlay = document.createElement('div');
        overlay.id = 'tpLicenseStateOverlay';
        overlay.setAttribute('role', 'alertdialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML = `
            <section class="tp-license-card">
                <div class="tp-license-brand">Tique<span>POS</span></div>
                <div class="tp-license-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9"/><path d="M12 7v5l3 2"/><path d="M18 3v5M15.5 5.5h5"/></svg>
                </div>
                <div class="tp-license-eyebrow">${ui.eyebrow}</div>
                <h1>${ui.title}</h1>
                <p class="tp-license-message">${ui.message}</p>
                <div class="tp-license-help">${ui.help}</div>
                <span class="tp-license-badge">${ui.badge}</span>
                <div class="tp-license-actions">
                    <button type="button" class="tp-license-retry" id="tpLicenseRetry">Reintentar</button>
                </div>
                <div class="tp-license-note">La información de tu empresa permanece intacta.</div>
            </section>`;
        document.body.appendChild(overlay);
        const button = document.getElementById('tpLicenseRetry');
        if (button) button.addEventListener('click', () => window.location.reload());
        return true;
    }

    async function payloadFromFetchResponse(response) {
        if (!response || response.status !== 423) return null;
        try {
            const clone = response.clone();
            const type = clone.headers.get('content-type') || '';
            if (type.includes('application/json')) return await clone.json();
            const text = await clone.text();
            try { return JSON.parse(text); } catch { return { license_status: 'UNVERIFIED', message: text }; }
        } catch (_) {
            return { license_status: 'UNVERIFIED' };
        }
    }

    const originalFetch = window.fetch;
    if (typeof originalFetch === 'function') {
        window.fetch = async function () {
            const response = await originalFetch.apply(this, arguments);
            if (response && response.status === 423) {
                const payload = await payloadFromFetchResponse(response);
                show(payload || { license_status: 'UNVERIFIED' });
            }
            return response;
        };
    }

    function hookJquery() {
        const $ = window.jQuery;
        if (!$ || !$.fn || document.documentElement.dataset.tpLicenseAjaxHook === '1') return;
        document.documentElement.dataset.tpLicenseAjaxHook = '1';
        $(document).ajaxError(function (_event, xhr) {
            if (!xhr || Number(xhr.status) !== 423) return;
            let payload = xhr.responseJSON;
            if (!payload && xhr.responseText) {
                try { payload = JSON.parse(xhr.responseText); } catch (_) {}
            }
            show(payload || { license_status: 'UNVERIFIED' });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hookJquery, { once: true });
    } else {
        hookJquery();
    }

    window.TiquePOSLicenseUI = { show: show };
})(window, document);
