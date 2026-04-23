/**
 * pages/controle.js
 * - Célula individual: clique → modal → salvar/limpar
 * - Multi-select: checkboxes por funcionário + "select all"
 *   → ao clicar célula com N selecionados, salva para todos
 * - Cabeçalho do dia: clique → modal de feriado em lote
 * - Sábados/Domingos: branco por padrão; aceita HE/BH/FER
 */
document.addEventListener('DOMContentLoaded', function () {
    const raw      = document.getElementById('app-data');
    const rawUsers = document.getElementById('app-users');
    if (!raw) return;

    const JUST  = JSON.parse(raw.textContent);
    const USERS = rawUsers ? JSON.parse(rawUsers.textContent) : [];

    // ── Monta grids de justificativa no modal ─────────────────
    const justGridAus  = document.getElementById('justGridAusencia');
    const justGridTrab = document.getElementById('justGridTrabalho');

    Object.entries(JUST).forEach(([code, j]) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'just-btn';
        btn.dataset.code = code;
        btn.style.cssText = `background:${j.bg};color:${j.text};${j.bold ? 'font-weight:700' : ''}`;
        btn.innerHTML = `<span style="font-size:.9em">${code}</span><br><small>${j.label}</small>`;
        btn.addEventListener('click', () => selectJust(code));
        (j.group === 'trabalho' ? justGridTrab : justGridAus).appendChild(btn);
    });

    // ── Estado do modal de ocorrência ─────────────────────────
    let currentUid   = null;
    let currentDate  = null;
    let selectedCode = null;
    let _modal       = null;

    function getModal() {
        if (!_modal) _modal = new bootstrap.Modal(document.getElementById('occModal'));
        return _modal;
    }

    function selectJust(code) {
        selectedCode = code;
        document.querySelectorAll('.just-btn').forEach(btn => {
            btn.classList.toggle('active-just', btn.dataset.code === code);
        });
    }

    // ── Multi-select state ────────────────────────────────────
    const toolbar       = document.getElementById('selectionToolbar');
    const selCount      = document.getElementById('selectionCount');
    const btnDeselectAll = document.getElementById('btnDeselectAll');
    const chkAll        = document.getElementById('chkAll');

    function getSelectedUids() {
        return Array.from(document.querySelectorAll('.emp-chk:checked'))
                    .map(c => parseInt(c.value));
    }

    function updateToolbar() {
        const uids = getSelectedUids();
        if (uids.length > 0) {
            toolbar.classList.remove('d-none');
            selCount.textContent = `${uids.length} colaborador${uids.length > 1 ? 'es' : ''} selecionado${uids.length > 1 ? 's' : ''}`;
        } else {
            toolbar.classList.add('d-none');
        }
        // Sync "select all" state
        const total = document.querySelectorAll('.emp-chk').length;
        chkAll.indeterminate = uids.length > 0 && uids.length < total;
        chkAll.checked       = uids.length === total && total > 0;

        // Highlight selected rows
        document.querySelectorAll('.employee-row').forEach(row => {
            const uid = parseInt(row.dataset.uid);
            row.classList.toggle('row-selected', uids.includes(uid));
        });
    }

    // Select all / deselect all
    chkAll && chkAll.addEventListener('change', () => {
        document.querySelectorAll('.emp-chk').forEach(c => { c.checked = chkAll.checked; });
        updateToolbar();
    });

    btnDeselectAll && btnDeselectAll.addEventListener('click', () => {
        document.querySelectorAll('.emp-chk').forEach(c => { c.checked = false; });
        if (chkAll) chkAll.checked = false;
        updateToolbar();
    });

    document.getElementById('gridTable')?.addEventListener('change', e => {
        if (e.target.classList.contains('emp-chk')) updateToolbar();
    });

    // ── Clique na célula da grade ─────────────────────────────
    const gridTable = document.getElementById('gridTable');
    if (gridTable) {
        gridTable.addEventListener('click', function (e) {
            const cell = e.target.closest('.grid-cell');
            if (!cell) return;

            currentUid  = cell.dataset.uid;
            currentDate = cell.dataset.date;
            const code  = cell.dataset.code;
            const obs   = cell.dataset.obs;

            const selectedUids = getSelectedUids();
            const isBatch = selectedUids.length > 1 ||
                            (selectedUids.length === 1 && selectedUids[0] !== parseInt(currentUid));

            // Modal title
            if (isBatch) {
                document.getElementById('occModalTitle').innerHTML =
                    `<i class="bi bi-people-fill me-2"></i>Registrar em Lote`;
            } else {
                const nome = cell.dataset.nome;
                document.getElementById('occModalTitle').innerHTML =
                    `<i class="bi bi-calendar-event me-2"></i>${nome}`;
            }

            // Subtitle / date
            const [y, m, d] = currentDate.split('-');
            const diasSem   = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
            const dow       = new Date(`${currentDate}T12:00:00`).getDay();
            const label     = JUST[code]?.label ?? (cell.dataset.weekend === '1' ? 'Sem expediente' : '');
            document.getElementById('occModalSubtitle').textContent =
                `${diasSem[dow]}, ${d}/${m}/${y}` + (code ? ` — Atual: ${code} (${label})` : '');

            // Batch badge
            const batchBadge = document.getElementById('batchBadge');
            const batchCount = document.getElementById('batchCount');
            if (isBatch) {
                const count = selectedUids.length;
                batchCount.textContent = count;
                batchBadge.classList.remove('d-none');
            } else {
                batchBadge.classList.add('d-none');
            }

            // Pre-select current code in the modal
            document.getElementById('obsInput').value = isBatch ? '' : obs;
            selectJust(code || 'P');
            getModal().show();
        });
    }

    // ── Botão Salvar ──────────────────────────────────────────
    document.getElementById('btnSalvar')?.addEventListener('click', async () => {
        if (!selectedCode) return;
        const obs  = document.getElementById('obsInput').value.trim();
        const uids = getSelectedUids();

        if (uids.length > 1 || (uids.length === 1 && uids[0] !== parseInt(currentUid))) {
            // Batch: include clicked cell's uid too if not already selected
            const allUids = uids.includes(parseInt(currentUid))
                ? uids
                : [...uids, parseInt(currentUid)];
            await saveBatch(allUids, currentDate, selectedCode, obs);
        } else {
            await saveOcorrencia(currentUid, currentDate, selectedCode, obs);
        }
    });

    // ── Botão Limpar ──────────────────────────────────────────
    document.getElementById('btnLimpar')?.addEventListener('click', async () => {
        const uids = getSelectedUids();
        if (uids.length > 1 || (uids.length === 1 && uids[0] !== parseInt(currentUid))) {
            const allUids = uids.includes(parseInt(currentUid))
                ? uids
                : [...uids, parseInt(currentUid)];
            await deleteBatch(allUids, currentDate);
        } else {
            await deleteOcorrencia(currentUid, currentDate);
        }
    });

    // ── API: save single ──────────────────────────────────────
    async function saveOcorrencia(uid, date, code, obs) {
        try {
            const data = await API.post('/controle_absenteismo/api/save_ocorrencia.php', {
                usuario_id:    parseInt(uid),
                data:          date,
                justificativa: code,
                observacao:    obs,
            });
            if (data.error) { showToast('Erro: ' + data.error); return; }
            updateCell(uid, date, code, obs);
            getModal().hide();
            showToast('Ocorrência salva!', 'success');
        } catch { showToast('Erro de comunicação com o servidor.'); }
    }

    // ── API: save batch ───────────────────────────────────────
    async function saveBatch(uids, date, code, obs) {
        try {
            const data = await API.post('/controle_absenteismo/api/save_ocorrencia.php', {
                usuario_ids:   uids,
                data:          date,
                justificativa: code,
                observacao:    obs,
            });
            if (data.error) { showToast('Erro: ' + data.error); return; }
            uids.forEach(uid => updateCell(uid, date, code, obs));
            getModal().hide();
            showToast(`Ocorrência aplicada a ${data.saved} colaboradores!`, 'success');
        } catch { showToast('Erro de comunicação com o servidor.'); }
    }

    // ── API: delete single ────────────────────────────────────
    async function deleteOcorrencia(uid, date) {
        try {
            const data = await API.post('/controle_absenteismo/api/delete_ocorrencia.php', {
                usuario_id: parseInt(uid),
                data:       date,
            });
            if (data.error) { showToast('Erro: ' + data.error); return; }
            const isWeekend = getIsWeekend(date);
            updateCell(uid, date, isWeekend ? '' : 'P', '');
            getModal().hide();
            showToast('Ocorrência removida.', 'success');
        } catch { showToast('Erro de comunicação com o servidor.'); }
    }

    // ── API: delete batch ─────────────────────────────────────
    async function deleteBatch(uids, date) {
        try {
            await Promise.all(uids.map(uid =>
                API.post('/controle_absenteismo/api/delete_ocorrencia.php', {
                    usuario_id: parseInt(uid), data: date
                })
            ));
            const isWeekend = getIsWeekend(date);
            uids.forEach(uid => updateCell(uid, date, isWeekend ? '' : 'P', ''));
            getModal().hide();
            showToast(`Ocorrências removidas (${uids.length}).`, 'success');
        } catch { showToast('Erro de comunicação com o servidor.'); }
    }

    // ── Helper: is-weekend? ───────────────────────────────────
    function getIsWeekend(date) {
        const dow = new Date(`${date}T12:00:00`).getDay();
        return dow === 0 || dow === 6;
    }

    // ── Atualiza célula visualmente ───────────────────────────
    function updateCell(uid, date, code, obs) {
        const cell = document.querySelector(`.grid-cell[data-uid="${uid}"][data-date="${date}"]`);
        if (!cell) return;
        const isWeekend = cell.dataset.weekend === '1';

        if (!code || code === '') {
            // blank weekend
            cell.style.background  = '';
            cell.style.color       = '';
            cell.style.fontWeight  = '';
            cell.textContent       = '';
            cell.dataset.code      = '';
            cell.dataset.obs       = '';
            cell.classList.add('weekend-blank');
        } else {
            const j = JUST[code] ?? JUST['P'];
            cell.style.background  = j.bg;
            cell.style.color       = j.text;
            cell.style.fontWeight  = j.bold ? '700' : '400';
            cell.textContent       = code;
            cell.dataset.code      = code;
            cell.dataset.obs       = obs;
            cell.classList.remove('weekend-blank');
            cell.title = `${cell.dataset.nome} — ${date} — ${j.label}`;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // Modal de Feriado (cabeçalho do dia)
    // ═══════════════════════════════════════════════════════════
    let holidayDate    = null;
    let _holidayModal  = null;

    function getHolidayModal() {
        if (!_holidayModal)
            _holidayModal = new bootstrap.Modal(document.getElementById('holidayModal'));
        return _holidayModal;
    }

    // Render the holiday table rows
    function buildHolidayTable() {
        const tbody = document.getElementById('holidayTableBody');
        tbody.innerHTML = '';
        USERS.forEach(u => {
            const tr = document.createElement('tr');
            tr.dataset.uid = u.id;
            tr.innerHTML = `
                <td style="font-size:.8rem">${escHtml(u.nome)}</td>
                <td style="font-size:.75rem">${escHtml(u.turno)}</td>
                <td>
                    <div class="hol-radio-group d-flex gap-2 flex-wrap">
                        <label class="hol-radio hol-fer">
                            <input type="radio" name="hol_${u.id}" value="FER" checked>
                            <span>Feriado</span>
                        </label>
                        <label class="hol-radio hol-he">
                            <input type="radio" name="hol_${u.id}" value="HE">
                            <span>Hora Extra</span>
                        </label>
                        <label class="hol-radio hol-bh">
                            <input type="radio" name="hol_${u.id}" value="BH">
                            <span>Banco de Horas</span>
                        </label>
                    </div>
                </td>`;
            tbody.appendChild(tr);
        });
    }

    // Cabeçalho do dia → abrir modal
    gridTable?.addEventListener('click', function (e) {
        const th = e.target.closest('.th-day-clickable');
        if (!th) return;
        holidayDate = th.dataset.date;
        const [y, m, d] = holidayDate.split('-');
        const diasSem = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
        const dow = new Date(`${holidayDate}T12:00:00`).getDay();
        document.getElementById('holidayModalDate').textContent =
            `${diasSem[dow]}, ${d}/${m}/${y}`;
        buildHolidayTable();
        getHolidayModal().show();
    });

    // "Todos: X" botões
    document.getElementById('holidayModal')?.addEventListener('click', e => {
        const btn = e.target.closest('.hol-all');
        if (!btn) return;
        const code = btn.dataset.code;
        document.querySelectorAll(`[name^="hol_"]`).forEach(radio => {
            if (radio.value === code) radio.checked = true;
        });
    });

    // Salvar atribuições do feriado
    document.getElementById('btnSalvarFeriado')?.addEventListener('click', async () => {
        if (!holidayDate) return;

        // Collect assignments grouped by code
        const byCode = {};
        document.querySelectorAll('#holidayTableBody tr').forEach(tr => {
            const uid = parseInt(tr.dataset.uid);
            const radio = tr.querySelector(`input[name="hol_${uid}"]:checked`);
            if (!radio) return;
            const code = radio.value;
            if (!byCode[code]) byCode[code] = [];
            byCode[code].push(uid);
        });

        try {
            // One batch request per code group
            await Promise.all(
                Object.entries(byCode).map(([code, uids]) =>
                    API.post('/controle_absenteismo/api/save_ocorrencia.php', {
                        usuario_ids:   uids,
                        data:          holidayDate,
                        justificativa: code,
                        observacao:    '',
                    })
                )
            );
            // Update cells
            Object.entries(byCode).forEach(([code, uids]) => {
                uids.forEach(uid => updateCell(uid, holidayDate, code, ''));
            });
            getHolidayModal().hide();
            const total = Object.values(byCode).reduce((s, a) => s + a.length, 0);
            showToast(`Atribuições salvas para ${total} colaboradores!`, 'success');
        } catch { showToast('Erro ao salvar atribuições.'); }
    });

    // Helper
    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ═══════════════════════════════════════════════════════════
    // Barra de pesquisa da grade
    // ═══════════════════════════════════════════════════════════
    (function () {
        const input     = document.getElementById('gridSearch');
        const btnClear  = document.getElementById('gridSearchClear');
        const countEl   = document.getElementById('gridSearchCount');
        if (!input) return;

        const totalRows = document.querySelectorAll('.employee-row').length;

        function applyFilter() {
            const q = input.value.trim().toLowerCase();
            btnClear.classList.toggle('d-none', q === '');
            let visible = 0;
            document.querySelectorAll('.employee-row').forEach(row => {
                const uid  = row.dataset.uid;
                const user = USERS.find(u => u.id === parseInt(uid));
                const nome = (user?.nome       ?? '').toLowerCase();
                const pron = (user?.prontuario ?? '').toLowerCase();
                const show = !q || nome.includes(q) || pron.includes(q);
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            if (q) {
                countEl.textContent = `${visible} de ${totalRows} encontrado${visible !== 1 ? 's' : ''}`;
            } else {
                countEl.textContent = '';
            }
        }

        input.addEventListener('input', applyFilter);
        btnClear.addEventListener('click', () => {
            input.value = '';
            applyFilter();
            input.focus();
        });
    }());

    // ═══════════════════════════════════════════════════════════
    // Registro Rápido de Ocorrência
    // ═══════════════════════════════════════════════════════════
    (function () {
        const inPron   = document.getElementById('qrProntuario');
        const inNome   = document.getElementById('qrNome');
        const inData   = document.getElementById('qrData');
        const selTipo  = document.getElementById('qrTipo');
        const inObs    = document.getElementById('qrObs');
        const btnQr    = document.getElementById('qrSalvar');
        const divSt    = document.getElementById('qrStatus');
        const selMode  = document.getElementById('qrMode');
        const datalist = document.getElementById('qrNomeList');
        if (!inPron) return;

        // All active users (unfiltered) for name autocomplete
        const rawAll  = document.getElementById('app-all-users');
        const ALL_USR = rawAll ? JSON.parse(rawAll.textContent) : [];

        // Populate datalist with every active employee name
        ALL_USR.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.nome;
            opt.dataset.uid  = u.id;
            opt.dataset.pron = u.prontuario;
            opt.dataset.turno = u.turno;
            datalist.appendChild(opt);
        });

        let qrUserId    = null;
        let searchTimer = null;

        function resetUser() {
            qrUserId = null;
            btnQr.disabled = true;
            divSt.textContent = '';
            divSt.className = 'form-text';
        }

        function applyUser(u) {
            qrUserId = parseInt(u.id);
            divSt.textContent = 'Turno: ' + u.turno;
            divSt.className = 'form-text text-success';
            btnQr.disabled = false;
            if (selMode.value === 'prontuario') {
                inNome.value  = u.nome;
            } else {
                inPron.value  = u.prontuario;
            }
        }

        // ── Modo: prontuário (padrão) ──────────────────────────
        function setPronMode() {
            inPron.readOnly            = false;
            inPron.style.background    = '';
            inPron.placeholder         = 'Ex.: 12345';
            inNome.readOnly            = true;
            inNome.style.background    = '#f8f9fa';
            inNome.placeholder         = '—';
            inNome.removeAttribute('list');
            resetUser();
            inPron.value = '';
            inNome.value = '';
            inPron.focus();
        }

        // ── Modo: nome ─────────────────────────────────────────
        function setNomeMode() {
            inNome.readOnly            = false;
            inNome.style.background    = '';
            inNome.placeholder         = 'Digite o nome…';
            inNome.setAttribute('list', 'qrNomeList');
            inPron.readOnly            = true;
            inPron.style.background    = '#f8f9fa';
            inPron.placeholder         = '—';
            resetUser();
            inPron.value = '';
            inNome.value = '';
            inNome.focus();
        }

        selMode.addEventListener('change', () => {
            selMode.value === 'nome' ? setNomeMode() : setPronMode();
        });

        // ── Busca por prontuário ───────────────────────────────
        inPron.addEventListener('input', function () {
            if (selMode.value !== 'prontuario') return;
            clearTimeout(searchTimer);
            const val = this.value.trim();
            if (!val) { resetUser(); inNome.value = ''; return; }

            const local = ALL_USR.find(u => u.prontuario === val);
            if (local) { applyUser(local); return; }

            divSt.textContent = 'Buscando…';
            divSt.className = 'form-text text-muted';
            btnQr.disabled = true;
            qrUserId = null;
            inNome.value = '';

            searchTimer = setTimeout(async () => {
                try {
                    const resp = await fetch(
                        '/controle_absenteismo/api/buscar_funcionario.php?prontuario=' +
                        encodeURIComponent(val)
                    );
                    const data = await resp.json();
                    if (data.id) {
                        applyUser(data);
                    } else {
                        divSt.textContent = 'Não encontrado.';
                        divSt.className = 'form-text text-danger';
                    }
                } catch {
                    divSt.textContent = 'Erro ao buscar.';
                    divSt.className = 'form-text text-danger';
                }
            }, 400);
        });

        // ── Busca por nome (via datalist) ──────────────────────
        inNome.addEventListener('input', function () {
            if (selMode.value !== 'nome') return;
            const val = this.value.trim();
            if (!val) { resetUser(); inPron.value = ''; return; }
            // exact match → user picked from datalist
            const match = ALL_USR.find(u => u.nome === val);
            if (match) {
                applyUser(match);
            } else {
                resetUser();
                inPron.value = '';
            }
        });

        btnQr.addEventListener('click', async () => {
            if (!qrUserId) return;
            const date = inData.value;
            const code = selTipo.value;
            const obs  = inObs.value.trim();

            if (!date) { showToast('Informe a data de referência.', 'danger'); return; }
            if (!code) { showToast('Selecione o tipo de ocorrência.', 'danger'); return; }

            btnQr.disabled = true;
            try {
                const res = await API.post('/controle_absenteismo/api/save_ocorrencia.php', {
                    usuario_id:    qrUserId,
                    data:          date,
                    justificativa: code,
                    observacao:    obs,
                });
                if (res.error) {
                    showToast('Erro: ' + res.error, 'danger');
                    btnQr.disabled = false;
                    return;
                }
                updateCell(qrUserId, date, code, obs);
                inObs.value = '';
                showToast('Ocorrência registrada!', 'success');
            } catch {
                showToast('Erro de comunicação com o servidor.', 'danger');
            } finally {
                btnQr.disabled = false;
            }
        });
    }());
});
