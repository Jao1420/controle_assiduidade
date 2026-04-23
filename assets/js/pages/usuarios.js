/**
 * pages/usuarios.js
 * CRUD de funcionários: listar, adicionar, editar, desativar.
 * Usa API (api.js) e showToast (toast.js) globais.
 */
document.addEventListener('DOMContentLoaded', function () {
    let allUsers = [];
    let deleteId = null;

    const userModal   = new bootstrap.Modal(document.getElementById('userModal'));
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

    // ---- Carregar lista ------------------------------------
    async function loadUsers() {
        try {
            const data = await API.get('/controle_absenteismo/api/usuarios.php');
            if (data.error) { console.error(data.error); return; }
            allUsers = data;
            populateSetores(allUsers);
            renderTable(allUsers);
        } catch (e) {
            console.error(e);
            showToast('Falha ao carregar funcionários.');
        }
    }

    // ---- Popular filtro de setores -------------------------
    function populateSetores(users) {
        const sel = document.getElementById('filterSetor');
        const current = sel.value;
        const setores = [...new Set(users.map(u => u.setor).filter(Boolean))].sort();
        sel.innerHTML = '<option value="">Todos os Setores</option>' +
            setores.map(s => `<option value="${esc(s)}">${esc(s)}</option>`).join('');
        if (setores.includes(current)) sel.value = current;
    }

    // ---- Renderizar tabela ---------------------------------
    function renderTable(users) {
        const tbody = document.getElementById('tbodyUsuarios');
        document.getElementById('countLabel').textContent =
            `${users.length} funcionário(s) exibido(s)`;

        if (users.length === 0) {
            tbody.innerHTML =
                '<tr><td colspan="6" class="text-center text-muted py-4">' +
                '<i class="bi bi-inbox me-2"></i>Nenhum funcionário encontrado</td></tr>';
            return;
        }

        tbody.innerHTML = users.map(u => `
            <tr>
                <td class="fw-medium">${esc(u.prontuario)}</td>
                <td>${esc(u.nome)}</td>
                <td><span class="turno-badge turno-${esc(u.turno)}">${esc(u.turno)}</span></td>
                <td>${esc(u.setor)}</td>
                <td class="text-center">
                    <span class="badge bg-secondary">${u.total_ocorrencias ?? 0}</span>
                </td>
                <td class="text-center">
                    <button class="btn btn-xs btn-outline-primary me-1 btn-edit"
                            data-id="${u.id}" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-xs btn-outline-danger btn-delete"
                            data-id="${u.id}" data-nome="${esc(u.nome)}" title="Desativar">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');

        tbody.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', () => openEdit(parseInt(btn.dataset.id)));
        });
        tbody.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', () => openDelete(parseInt(btn.dataset.id), btn.dataset.nome));
        });
    }

    function esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ---- Filtros -------------------------------------------
    function applyFilter() {
        const turno = document.getElementById('filterTurno').value;
        const setor = document.getElementById('filterSetor').value;
        const busca = document.getElementById('filterBusca').value.toLowerCase();
        renderTable(allUsers.filter(u =>
            (!turno || u.turno === turno) &&
            (!setor || u.setor === setor) &&
            (!busca  || u.nome.toLowerCase().includes(busca) ||
                        u.prontuario.toLowerCase().includes(busca))
        ));
    }

    document.getElementById('filterTurno').addEventListener('change', applyFilter);
    document.getElementById('filterSetor').addEventListener('change', applyFilter);
    document.getElementById('filterBusca').addEventListener('input',  applyFilter);

    // ---- Adicionar -----------------------------------------
    document.getElementById('btnAdd').addEventListener('click', () => {
        clearForm();
        document.getElementById('userModalTitle').innerHTML =
            '<i class="bi bi-person-plus me-2"></i>Novo Funcionário';
        document.getElementById('userId').value = '';
        userModal.show();
    });

    // ---- Editar --------------------------------------------
    function openEdit(id) {
        const u = allUsers.find(x => x.id === id);
        if (!u) return;
        clearForm();
        document.getElementById('userModalTitle').innerHTML =
            '<i class="bi bi-pencil me-2"></i>Editar Funcionário';
        document.getElementById('userId').value      = u.id;
        document.getElementById('fProntuario').value = u.prontuario;
        document.getElementById('fNome').value       = u.nome;
        document.getElementById('fTurno').value      = u.turno;
        document.getElementById('fSetor').value      = u.setor ?? '';
        userModal.show();
    }

    function clearForm() {
        ['fProntuario', 'fNome', 'fTurno', 'fSetor'].forEach(id => {
            document.getElementById(id).value = '';
            document.getElementById(id).classList.remove('is-invalid');
        });
        document.getElementById('fSetor').value = '';
        document.getElementById('formAlert').classList.add('d-none');
    }

    // ---- Salvar (criar ou atualizar) -----------------------
    document.getElementById('btnSaveUser').addEventListener('click', async () => {
        const uid        = document.getElementById('userId').value;
        const prontuario = document.getElementById('fProntuario').value.trim();
        const nome       = document.getElementById('fNome').value.trim();
        const turno      = document.getElementById('fTurno').value;
        const setor      = document.getElementById('fSetor').value.trim();

        let valid = true;
        if (!prontuario) { setInvalid('fProntuario', 'Prontuário obrigatório'); valid = false; }
        else             { clearInvalid('fProntuario'); }

        if (!nome)       { setInvalid('fNome', 'Nome obrigatório'); valid = false; }
        else             { clearInvalid('fNome'); }

        if (!turno)      { setInvalid('fTurno', 'Selecione um turno'); valid = false; }
        else             { clearInvalid('fTurno'); }

        if (!setor)      { setInvalid('fSetor', 'Setor obrigatório'); valid = false; }
        else             { clearInvalid('fSetor'); }

        if (!valid) return;

        const payload = { action: uid ? 'update' : 'create', prontuario, nome, turno, setor };
        if (uid) payload.id = parseInt(uid);

        try {
            const data = await API.post('/controle_absenteismo/api/usuarios.php', payload);
            if (data.error) {
                const alertEl = document.getElementById('formAlert');
                alertEl.textContent = data.error;
                alertEl.classList.remove('d-none');
                return;
            }
            userModal.hide();
            showToast(uid ? 'Funcionário atualizado!' : 'Funcionário cadastrado!', 'success');
            await loadUsers();
        } catch {
            showToast('Erro de comunicação com o servidor.');
        }
    });

    function setInvalid(id, msg) {
        const el = document.getElementById(id);
        el.classList.add('is-invalid');
        document.getElementById('err' + id.charAt(0).toUpperCase() + id.slice(1)).textContent = msg;
    }

    function clearInvalid(id) {
        document.getElementById(id).classList.remove('is-invalid');
    }

    // ---- Desativar -----------------------------------------
    function openDelete(id, nome) {
        deleteId = id;
        document.getElementById('deleteNome').textContent = nome;
        deleteModal.show();
    }

    document.getElementById('btnConfirmDelete').addEventListener('click', async () => {
        if (!deleteId) return;
        try {
            const data = await API.post('/controle_absenteismo/api/usuarios.php', {
                action: 'delete',
                id: deleteId,
            });
            if (data.error) { showToast(data.error); return; }
            deleteModal.hide();
            showToast('Funcionário desativado.', 'success');
            await loadUsers();
        } catch {
            showToast('Erro de comunicação com o servidor.');
        }
    });

    // ---- Init ----------------------------------------------
    loadUsers();
});
