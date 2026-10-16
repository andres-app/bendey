(function (window) {
    'use strict';

    function formBody(values) {
        const body = new URLSearchParams();
        Object.keys(values).forEach(key => body.append(key, String(values[key] ?? '')));
        return body;
    }

    async function request(op, tipo, id, extra) {
        const response = await fetch('Controllers/DocumentDelivery.php?op=' + encodeURIComponent(op), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-CSRF-Token': String(window.TIQUEPOS_DOCUMENT_CSRF || '')
            },
            body: formBody(Object.assign({ tipo, id }, extra || {})).toString()
        });
        let payload = null;
        try { payload = await response.json(); } catch (_) {}
        if (!response.ok || !payload || payload.success !== true) {
            throw new Error(payload?.message || payload?.mensaje || 'No se pudo completar la entrega del documento.');
        }
        return payload.data || {};
    }

    async function info(tipo, id) {
        return request('info', tipo, id, {});
    }

    function showMessage(icon, title, text) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire({ icon, title, text });
        }
        window.alert((title ? title + '\n\n' : '') + String(text || ''));
        return Promise.resolve();
    }

    async function askValue(options) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            const result = await window.Swal.fire({
                icon: options.icon || 'info',
                title: options.title,
                text: options.text || '',
                input: options.input || 'text',
                inputValue: options.value || '',
                inputPlaceholder: options.placeholder || '',
                confirmButtonText: options.confirm || 'Continuar',
                showCancelButton: true,
                cancelButtonText: 'Cancelar',
                inputValidator: options.validator
            });
            if (!result.isConfirmed) return null;
            return String(result.value || '').trim();
        }
        const value = window.prompt(options.title, options.value || '');
        return value === null ? null : String(value).trim();
    }

    window.tiqueposEnviarDocumentoEmail = async function (tipo, id, emailInicial) {
        try {
            let initial = String(emailInicial || '').trim();
            if (!initial) {
                const data = await info(tipo, id);
                initial = String(data.email || '').trim();
            }
            const email = await askValue({
                title: 'Enviar comprobante por correo',
                text: 'El mensaje incluirá un enlace seguro al PDF. Si la empresa habilitó adjuntos, también intentará adjuntar el archivo.',
                input: 'email',
                value: initial,
                placeholder: 'cliente@correo.com',
                confirm: 'Enviar correo',
                validator: value => {
                    const v = String(value || '').trim();
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return 'Ingrese un correo válido.';
                    return undefined;
                }
            });
            if (email === null) return null;
            const data = await request('email', tipo, id, { email });
            await showMessage('success', 'Correo enviado', data.mensaje || 'El documento fue enviado correctamente.');
            return data;
        } catch (error) {
            await showMessage('error', 'No se pudo enviar', error.message || 'Revise la configuración SMTP.');
            return null;
        }
    };

    window.tiqueposCompartirDocumentoWhatsApp = async function (tipo, id, telefonoInicial) {
        try {
            let initial = String(telefonoInicial || '').replace(/\D/g, '');
            if (!initial) {
                const data = await info(tipo, id);
                initial = String(data.telefono || '').replace(/\D/g, '');
            }
            if (/^51\d{9}$/.test(initial)) initial = initial.slice(2);
            const telefono = await askValue({
                title: 'Compartir por WhatsApp',
                text: 'Se abrirá WhatsApp con un enlace seguro al PDF del documento.',
                input: 'tel',
                value: initial,
                placeholder: '987654321',
                confirm: 'Abrir WhatsApp',
                validator: value => {
                    const digits = String(value || '').replace(/\D/g, '');
                    if (!/^9\d{8}$/.test(digits) && !/^519\d{8}$/.test(digits)) return 'Ingrese un celular válido.';
                    return undefined;
                }
            });
            if (telefono === null) return null;
            const data = await request('whatsapp', tipo, id, { telefono });
            if (!data.whatsapp_url) throw new Error('No se pudo generar el enlace de WhatsApp.');
            window.open(data.whatsapp_url, '_blank', 'noopener');
            return data;
        } catch (error) {
            await showMessage('error', 'No se pudo compartir', error.message || 'Revise el celular del cliente.');
            return null;
        }
    };

    window.tiqueposAbrirPdfDocumento = async function (tipo, id) {
        try {
            const data = await info(tipo, id);
            if (!data.pdf_url) throw new Error('No se pudo generar el enlace PDF.');
            window.open(data.pdf_url, '_blank', 'noopener');
            return data.pdf_url;
        } catch (error) {
            await showMessage('error', 'PDF no disponible', error.message || 'No se pudo abrir el documento.');
            return null;
        }
    };
})(window);
