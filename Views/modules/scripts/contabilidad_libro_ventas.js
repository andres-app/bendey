(function ($) {
    'use strict';

    const state = {
        table: null,
        symbol: 'S/.',
        company: null,
        generated: false,
        loading: false,
        dateSnapshot: null
    };

    const $id = (id) => document.getElementById(id);

    function showMessage(title, text, icon = 'info') {
        if (typeof window.swal === 'function') {
            window.swal({ title, text, icon, button: 'Entendido' });
            return;
        }
        window.alert(`${title}\n\n${text}`);
    }

    function setLoading(value) {
        state.loading = Boolean(value);
        const overlay = $id('contaLoading');
        if (overlay) {
            overlay.classList.toggle('is-visible', state.loading);
            overlay.setAttribute('aria-hidden', state.loading ? 'false' : 'true');
        }
        ['btnContaGenerar', 'btnContaRefresh', 'btnContaExcel'].forEach((id) => {
            const btn = $id(id);
            if (btn) btn.disabled = state.loading;
        });
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function parseIsoDate(value) {
        const parts = String(value || '').split('-').map(Number);
        if (parts.length !== 3 || !parts[0] || !parts[1] || !parts[2]) return null;
        return new Date(parts[0], parts[1] - 1, parts[2]);
    }

    function isoDate(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function displayIso(value) {
        const date = parseIsoDate(value);
        if (!date) return '';
        return `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()}`;
    }

    function displayDdMmYyyy(value) {
        const parts = String(value || '').split('/');
        if (parts.length !== 3) return value || '';
        return `${parts[0].padStart(2, '0')}/${parts[1].padStart(2, '0')}/${parts[2]}`;
    }

    function sortableDdMmYyyy(value) {
        const parts = String(value || '').split('/');
        if (parts.length !== 3) return 0;
        return Number(`${parts[2]}${parts[1].padStart(2, '0')}${parts[0].padStart(2, '0')}`);
    }

    function updateDateLabel() {
        const start = $id('contaFechaInicio')?.value || '';
        const end = $id('contaFechaFin')?.value || '';
        const label = $id('contaDateLabel');
        if (!label) return;
        if (!start || !end) {
            label.textContent = 'Selecciona un rango';
            return;
        }
        label.textContent = `${displayIso(start)} - ${displayIso(end)}`;
    }

    function openDatePopover() {
        const popover = $id('contaDatePopover');
        const trigger = $id('contaDateTrigger');
        if (!popover || !trigger) return;
        state.dateSnapshot = {
            start: $id('contaFechaInicio')?.value || '',
            end: $id('contaFechaFin')?.value || ''
        };
        popover.hidden = false;
        trigger.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
    }

    function closeDatePopover() {
        const popover = $id('contaDatePopover');
        const trigger = $id('contaDateTrigger');
        if (!popover || !trigger) return;
        popover.hidden = true;
        trigger.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
    }

    function applyPreset(name) {
        const today = new Date();
        let start = new Date(today.getFullYear(), today.getMonth(), 1);
        let end = new Date(today.getFullYear(), today.getMonth() + 1, 0);

        if (name === 'prev-month') {
            start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            end = new Date(today.getFullYear(), today.getMonth(), 0);
        } else if (name === '30days') {
            end = new Date(today.getFullYear(), today.getMonth(), today.getDate());
            start = new Date(end);
            start.setDate(start.getDate() - 29);
        } else if (name === 'year') {
            start = new Date(today.getFullYear(), 0, 1);
            end = new Date(today.getFullYear(), 11, 31);
        }

        if (end > today && name !== 'prev-month') {
            end = today;
        }

        $id('contaFechaInicio').value = isoDate(start);
        $id('contaFechaFin').value = isoDate(end);
        updateDateLabel();
    }

    function money(value) {
        const number = Number(value || 0);
        return `${state.symbol} ${number.toLocaleString('es-PE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        })}`;
    }

    function numberCell(data, type) {
        const value = Number(data || 0);
        if (type === 'sort' || type === 'type') return value;
        const formatted = value.toLocaleString('es-PE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        return value < 0
            ? `<span class="conta-money-negative">${formatted}</span>`
            : formatted;
    }

    function exchangeCell(data, type) {
        const value = Number(data || 0);
        if (type === 'sort' || type === 'type') return value;
        return value.toFixed(3);
    }

    function statusCell(data, type) {
        const value = String(data || 'PENDIENTE').toUpperCase();
        if (type !== 'display') return value;
        let klass = 'neutral';
        if (value === 'ACEPTADO') klass = 'accepted';
        else if (value === 'ANULADO') klass = 'cancelled';
        else if (value.includes('RECHAZ')) klass = 'rejected';
        else if (value.includes('PEND') || value.includes('NO ENVI')) klass = 'pending';
        return `<span class="conta-state-pill ${klass}">${escapeHtml(value)}</span>`;
    }

    function dateCell(data, type) {
        if (type === 'sort' || type === 'type') return sortableDdMmYyyy(data);
        return escapeHtml(displayDdMmYyyy(data));
    }

    function textCell(data) {
        return escapeHtml(data ?? '');
    }

    function initTable() {
        if (!$.fn.DataTable) {
            showMessage('DataTable no disponible', 'No se pudo inicializar la tabla del libro de ventas.', 'error');
            return;
        }

        state.table = $('#tablaLibroVentas').DataTable({
            data: [],
            autoWidth: false,
            deferRender: true,
            pageLength: 10,
            lengthChange: false,
            searching: true,
            ordering: true,
            order: [[0, 'desc'], [3, 'desc'], [7, 'desc']],
            // Misma paginación DataTables/Bootstrap usada en los demás módulos.
            // Info a la izquierda y Previous / páginas / Next a la derecha.
            dom: 'rt<"row align-items-center mt-3"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            pagingType: 'simple_numbers',
            language: {
                emptyTable: 'No hay comprobantes para el rango seleccionado.',
                zeroRecords: 'No se encontraron coincidencias.',
                info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                infoEmpty: 'Showing 0 to 0 of 0 entries',
                infoFiltered: '(filtered from _MAX_ total entries)',
                paginate: {
                    previous: 'Previous',
                    next: 'Next'
                }
            },
            columns: [
                { data: 'periodo', render: textCell },
                { data: 'cod_unic', render: textCell },
                { data: 'regimen', render: textCell },
                { data: 'f_emision', render: dateCell },
                { data: 'f_vencimiento', render: dateCell },
                { data: 'tipo_doc', render: textCell },
                { data: 'serie', render: textCell },
                { data: 'numero', render: textCell },
                { data: 'num_maq_reg', render: textCell },
                { data: 't_doc', render: textCell },
                { data: 'numero_cliente', render: textCell },
                { data: 'razon_social', render: textCell },
                { data: 'op_export', render: numberCell, className: 'text-right' },
                { data: 'op_gravada', render: numberCell, className: 'text-right' },
                { data: 'descuent', render: numberCell, className: 'text-right' },
                { data: 'igv', render: numberCell, className: 'text-right' },
                { data: 'desc_igv', render: numberCell, className: 'text-right' },
                { data: 'op_exonerada', render: numberCell, className: 'text-right' },
                { data: 'op_inafecta', render: numberCell, className: 'text-right' },
                { data: 'isc', render: numberCell, className: 'text-right' },
                { data: 'op_arroz_p', render: numberCell, className: 'text-right' },
                { data: 'imp_arroz_p', render: numberCell, className: 'text-right' },
                { data: 'icbper', render: numberCell, className: 'text-right' },
                { data: 'otro_tributos', render: numberCell, className: 'text-right' },
                { data: 'total', render: numberCell, className: 'text-right' },
                { data: 'moneda', render: textCell },
                { data: 'tc', render: exchangeCell, className: 'text-right' },
                { data: 'fec_comp_modif', render: dateCell },
                { data: 'tipo_doc_modif', render: textCell },
                { data: 'serie_doc_modif', render: textCell },
                { data: 'num_doc_modif', render: textCell },
                { data: 'id_contr', render: textCell },
                { data: 'err_tc', render: textCell },
                { data: 'comp_mp', render: textCell },
                { data: 'estado', render: textCell },
                { data: 'camp_lib', render: textCell },
                { data: 'estado_comp', render: statusCell }
            ],
            createdRow: function (row, data) {
                if (data && data.origen === 'NOTA_CREDITO') {
                    row.classList.add('conta-credit-note');
                }
            }
        });

        $('#contaSearch').on('input', function () {
            state.table.search(this.value || '').draw();
        });

        $('#contaLength').on('change', function () {
            state.table.page.len(Number(this.value || 10)).draw();
        });
    }

    function currentParams() {
        return new URLSearchParams({
            fecha_inicio: $id('contaFechaInicio')?.value || '',
            fecha_fin: $id('contaFechaFin')?.value || '',
            tipo_documento: $id('contaTipoDocumento')?.value || 'TODOS',
            idsucursal: $id('contaSucursal')?.value || '0',
            regimen: $id('contaRegimen')?.value || 'M-RER'
        });
    }

    function validateFilters() {
        const start = $id('contaFechaInicio')?.value || '';
        const end = $id('contaFechaFin')?.value || '';
        if (!start || !end) {
            showMessage('Selecciona las fechas', 'Debes indicar una fecha inicial y una fecha final.', 'warning');
            return false;
        }
        if (start > end) {
            showMessage('Rango inválido', 'La fecha inicial no puede ser posterior a la fecha final.', 'warning');
            return false;
        }
        return true;
    }

    function updateSummary(summary) {
        const s = summary || {};
        $id('contaStatCount').textContent = Number(s.comprobantes || 0).toLocaleString('es-PE');
        $id('contaStatGravada').textContent = money(s.op_gravada || 0);
        $id('contaStatNoGravada').textContent = money(Number(s.op_exonerada || 0) + Number(s.op_inafecta || 0));
        $id('contaStatIgv').textContent = money(s.igv || 0);
        $id('contaStatTotal').textContent = money(s.total || 0);

        const observed = Number(s.observados || 0);
        $id('contaStatObserved').textContent = observed > 0
            ? `${observed} comprobante${observed === 1 ? '' : 's'} por revisar`
            : 'Sin observaciones';
    }

    function updateTableMeta(rows) {
        const count = Array.isArray(rows) ? rows.length : 0;
        const start = displayIso($id('contaFechaInicio')?.value || '');
        const end = displayIso($id('contaFechaFin')?.value || '');
        const branchSelect = $id('contaSucursal');
        const branch = branchSelect?.options[branchSelect.selectedIndex]?.text || 'Todas';
        $id('contaTableMeta').textContent = `${count.toLocaleString('es-PE')} registro${count === 1 ? '' : 's'} · ${start} al ${end} · ${branch}`;
    }

    async function loadBootstrap() {
        const response = await fetch('Controllers/Contabilidad.php?op=bootstrap', {
            credentials: 'same-origin',
            cache: 'no-store'
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'No se pudo cargar la configuración contable.');
        }

        state.company = data.empresa || {};
        state.symbol = String(state.company.simbolo || 'S/.').trim() || 'S/.';

        const branch = $id('contaSucursal');
        const selectedDefault = Number(data.defaults?.idsucursal || 0);
        (data.sucursales || []).forEach((item) => {
            const option = document.createElement('option');
            option.value = String(item.idsucursal || 0);
            option.textContent = item.principal == 1
                ? `${item.nombre} · Principal`
                : String(item.nombre || `Sucursal ${item.idsucursal}`);
            branch.appendChild(option);
        });

        if (selectedDefault > 0 && Array.from(branch.options).some((o) => Number(o.value) === selectedDefault)) {
            branch.value = String(selectedDefault);
        }

        const defaults = data.defaults || {};
        $id('contaFechaInicio').value = defaults.fecha_inicio || isoDate(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
        $id('contaFechaFin').value = defaults.fecha_fin || isoDate(new Date());

        const savedRegime = window.localStorage.getItem('tiquepos.contabilidad.regimen');
        if (savedRegime && ['M-RER', 'M-RMT', 'M-RG'].includes(savedRegime)) {
            $id('contaRegimen').value = savedRegime;
        } else if (defaults.regimen) {
            $id('contaRegimen').value = defaults.regimen;
        }

        updateDateLabel();
    }

    async function generateReport() {
        if (state.loading || !validateFilters()) return;
        setLoading(true);
        try {
            const params = currentParams();
            const response = await fetch(`Controllers/Contabilidad.php?op=libroVentas&${params.toString()}`, {
                credentials: 'same-origin',
                cache: 'no-store'
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'No se pudo generar el libro de ventas.');
            }

            const rows = Array.isArray(data.data) ? data.data : [];
            state.table.clear();
            state.table.rows.add(rows).draw();
            state.generated = true;
            updateSummary(data.summary || {});
            updateTableMeta(rows);
        } catch (error) {
            console.error(error);
            showMessage('No se pudo generar el reporte', error.message || 'Ocurrió un error inesperado.', 'error');
        } finally {
            setLoading(false);
        }
    }

    function download(kind) {
        if (!validateFilters()) return;

        if (kind === 'txt') {
            const start = $id('contaFechaInicio').value.slice(0, 7);
            const end = $id('contaFechaFin').value.slice(0, 7);
            if (start !== end) {
                showMessage(
                    'Selecciona un solo mes',
                    'El TXT PLE 14.1 se genera por período mensual. Para rangos de varios meses utiliza Excel o CSV.',
                    'warning'
                );
                return;
            }
        }

        const opMap = {
            'sunat-xlsx': 'exportarExcelSunat',
            xlsx: 'exportarExcelSunat',
            ejb: 'exportarFormatoEjb',
            siscont: 'exportarAsientoContable',
            txt: 'exportarTxtPleVentas'
        };
        const op = opMap[kind];
        if (!op) return;
        const params = currentParams();
        window.location.href = `Controllers/Contabilidad.php?op=${encodeURIComponent(op)}&${params.toString()}`;
        closeOptionsMenu();
    }

    function openOptionsMenu() {
        const menu = $id('contaOpcionesMenu');
        const button = $id('btnContaOpciones');
        if (!menu || !button) return;
        menu.hidden = false;
        button.setAttribute('aria-expanded', 'true');
    }

    function closeOptionsMenu() {
        const menu = $id('contaOpcionesMenu');
        const button = $id('btnContaOpciones');
        if (!menu || !button) return;
        menu.hidden = true;
        button.setAttribute('aria-expanded', 'false');
    }

    function bindEvents() {
        $id('contaDateTrigger')?.addEventListener('click', (event) => {
            event.stopPropagation();
            const popover = $id('contaDatePopover');
            if (popover.hidden) openDatePopover();
            else closeDatePopover();
        });

        $id('contaDatePopover')?.addEventListener('click', (event) => event.stopPropagation());

        document.querySelectorAll('[data-conta-range]').forEach((button) => {
            button.addEventListener('click', () => applyPreset(button.dataset.contaRange));
        });

        $id('contaDateApply')?.addEventListener('click', () => {
            if (!validateFilters()) return;
            updateDateLabel();
            closeDatePopover();
        });

        $id('contaDateCancel')?.addEventListener('click', () => {
            if (state.dateSnapshot) {
                $id('contaFechaInicio').value = state.dateSnapshot.start;
                $id('contaFechaFin').value = state.dateSnapshot.end;
            }
            updateDateLabel();
            closeDatePopover();
        });

        $id('contaFechaInicio')?.addEventListener('change', updateDateLabel);
        $id('contaFechaFin')?.addEventListener('change', updateDateLabel);

        $id('btnContaOpciones')?.addEventListener('click', (event) => {
            event.stopPropagation();
            const menu = $id('contaOpcionesMenu');
            if (menu.hidden) openOptionsMenu();
            else closeOptionsMenu();
        });

        $id('contaOpcionesMenu')?.addEventListener('click', (event) => event.stopPropagation());
        document.querySelectorAll('[data-export]').forEach((button) => {
            button.addEventListener('click', () => download(button.dataset.export));
        });

        document.addEventListener('click', () => {
            closeDatePopover();
            closeOptionsMenu();
        });

        $id('btnContaGenerar')?.addEventListener('click', generateReport);
        $id('btnContaRefresh')?.addEventListener('click', generateReport);
        $id('btnContaExcel')?.addEventListener('click', () => download('sunat-xlsx'));

        $id('contaRegimen')?.addEventListener('change', function () {
            window.localStorage.setItem('tiquepos.contabilidad.regimen', this.value);
        });

        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeDatePopover();
                closeOptionsMenu();
            }
        });
    }

    async function init() {
        initTable();
        bindEvents();
        setLoading(true);
        try {
            await loadBootstrap();
        } catch (error) {
            console.error(error);
            showMessage('No se pudo iniciar Contabilidad', error.message || 'Verifica la conexión y vuelve a intentarlo.', 'error');
            setLoading(false);
            return;
        }
        setLoading(false);
        await generateReport();
    }

    $(document).ready(init);
})(jQuery);
