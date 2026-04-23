<?php
require_once 'config/database.php';
require_once 'config/justificativas.php';
include 'includes/header.php';

$pdo    = getConnection();
$turnos = ['Comercial','Segundo Turno','Terceiro Turno'];

// Totals per turno for summary
$stmt = $pdo->query(
    "SELECT turno, COUNT(*) AS total FROM usuarios WHERE ativo = 1 GROUP BY turno ORDER BY turno"
);
$totalPorTurno = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$totalGeral = array_sum($totalPorTurno);
?>

<!-- Page header -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="page-title mb-0">
        <i class="bi bi-people-fill me-2"></i>Funcionários
    </h4>
    <button class="btn btn-primary btn-sm" id="btnAdd">
        <i class="bi bi-plus-circle me-1"></i>Novo Funcionário
    </button>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-card p-3 text-center">
            <div class="fw-bold fs-2"><?= $totalGeral ?></div>
            <div class="text-muted small">Total Ativos</div>
        </div>
    </div>
    <?php foreach ($turnos as $t): ?>
    <div class="col-6 col-md-3">
        <div class="kpi-card p-3 text-center">
            <div class="fw-bold fs-2"><?= $totalPorTurno[$t] ?? 0 ?></div>
            <div class="mt-1">
                <span class="turno-badge turno-<?= $t ?>"><?= $t ?></span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Users Table -->
<div class="app-card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h6 class="fw-bold text-muted mb-0">Lista de Funcionários</h6>
        <div class="d-flex gap-2 flex-wrap">
            <select id="filterTurno" class="form-select form-select-sm" style="width:160px">
                <option value="">Todos os Turnos</option>
                <?php foreach ($turnos as $t): ?>
                    <option value="<?= $t ?>"><?= $t ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filterSetor" class="form-select form-select-sm" style="width:160px">
                <option value="">Todos os Setores</option>
            </select>
            <input type="search" id="filterBusca" class="form-control form-control-sm"
                   placeholder="Buscar nome / prontuário..." style="width:220px">
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 table-app" id="tblUsuarios">
            <thead>
                <tr>
                    <th style="width:110px">Prontuário</th>
                    <th>Nome</th>
                    <th style="width:130px">Turno</th>
                    <th style="width:100px">Setor</th>
                    <th style="width:90px" class="text-center">Ocorrências</th>
                    <th style="width:110px" class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody id="tbodyUsuarios">
                <tr><td colspan="5" class="text-center text-muted py-4">Carregando...</td></tr>
            </tbody>
        </table>
    </div>
    <div class="mt-2">
        <small class="text-muted" id="countLabel"></small>
    </div>
</div>

<!-- ====================================================
     Modal Adicionar / Editar
     ==================================================== -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title mb-0" id="userModalTitle">
                    <i class="bi bi-person-plus me-2"></i>Novo Funcionário
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="userId">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Prontuário *</label>
                    <input type="text" id="fProntuario" class="form-control form-control-sm"
                           placeholder="Ex.: 00123" maxlength="50">
                    <div class="invalid-feedback" id="errProntuario"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Nome Completo *</label>
                    <input type="text" id="fNome" class="form-control form-control-sm"
                           placeholder="Nome do funcionário" maxlength="150">
                    <div class="invalid-feedback" id="errNome"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Turno *</label>
                    <select id="fTurno" class="form-select form-select-sm">
                        <option value="">Selecione...</option>
                        <?php foreach ($turnos as $t): ?>
                            <option value="<?= $t ?>"><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback" id="errTurno"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Setor *</label>
                    <input type="text" id="fSetor" class="form-control form-control-sm"
                           placeholder="Ex.: SMD" maxlength="50" value=" " required>
                    <div class="invalid-feedback" id="errSetor"></div>
                </div>
                <div id="formAlert" class="alert alert-danger d-none py-2 small"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="btnSaveUser">
                    <i class="bi bi-check-circle me-1"></i>Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmar Exclusão -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title mb-0"><i class="bi bi-trash me-2"></i>Confirmar Exclusão</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1 small">Deseja desativar o funcionário:</p>
                <p class="fw-bold mb-0" id="deleteNome"></p>
                <small class="text-muted">As ocorrências serão mantidas.</small>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-danger" id="btnConfirmDelete">
                    <i class="bi bi-trash me-1"></i>Desativar
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = [
    '/controle_absenteismo/assets/js/modules/api.js',
    '/controle_absenteismo/assets/js/modules/toast.js',
    '/controle_absenteismo/assets/js/pages/usuarios.js',
];

include 'includes/footer.php';
?>
