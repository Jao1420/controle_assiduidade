<?php
require_once 'config/database.php';
require_once 'config/justificativas.php';
include 'includes/header.php';

$pdo = getConnection();

// ---- Filtros -----------------------------------------------
$mes   = isset($_GET['mes'])   ? max(1, min(12, (int)$_GET['mes']))  : (int)date('n');
$ano   = isset($_GET['ano'])   ? max(2020, min(2099, (int)$_GET['ano'])) : (int)date('Y');
$turno = isset($_GET['turno']) ? $_GET['turno'] : '';
$setor = isset($_GET['setor']) ? trim($_GET['setor']) : '';

$turnos = ['Comercial','Segundo Turno','Terceiro Turno'];
if ($turno && !in_array($turno, $turnos, true)) {
    $turno = '';
}

// Setores disponíveis
$setoresRows = $pdo->query("SELECT DISTINCT setor FROM usuarios WHERE ativo = 1 AND setor <> '' ORDER BY setor")->fetchAll(PDO::FETCH_COLUMN);
if ($setor && !in_array($setor, $setoresRows, true)) {
    $setor = '';
}

$diasNoMes = (int) date('t', mktime(0,0,0,$mes,1,$ano));
$dataInicio = sprintf('%04d-%02d-01', $ano, $mes);
$dataFim    = sprintf('%04d-%02d-%02d', $ano, $mes, $diasNoMes);

// ---- Funcionários -----------------------------------------
$sql = "SELECT id, prontuario, nome, turno, setor FROM usuarios WHERE ativo = 1";
$params = [];
if ($turno !== '') {
    $sql .= " AND turno = :turno";
    $params[':turno'] = $turno;
}
if ($setor !== '') {
    $sql .= " AND setor = :setor";
    $params[':setor'] = $setor;
}
$sql .= " ORDER BY turno, nome";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

// ---- Ocorrências do mês -----------------------------------
$stmt = $pdo->prepare(
    "SELECT usuario_id, data, justificativa, observacao
     FROM ocorrencias
     WHERE data BETWEEN :ini AND :fim"
);
$stmt->execute([':ini' => $dataInicio, ':fim' => $dataFim]);

$ocorrencias = [];
foreach ($stmt->fetchAll() as $row) {
    $ocorrencias[$row['usuario_id']][$row['data']] = [
        'justificativa' => $row['justificativa'],
        'observacao'    => $row['observacao'],
    ];
}

// ---- Helpers -----------------------------------------------
$diasSemana = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
$mesesNome  = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
               'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

$anoAtual = (int)date('Y');
$anosOpts = range($anoAtual - 4, $anoAtual + 2);

// Dados para JS
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$justJs  = json_encode(JUSTIFICATIVAS, $jsonFlags);
$usersJs = json_encode(array_map(fn($u) => [
    'id'         => (int)$u['id'],
    'nome'       => $u['nome'],
    'prontuario' => $u['prontuario'],
    'turno'      => $u['turno'],
], $usuarios), $jsonFlags);

// Todos os funcionários ativos (sem filtro de turno) para autocomplete de nome
$stmtAll   = $pdo->query("SELECT id, prontuario, nome, turno FROM usuarios WHERE ativo = 1 ORDER BY nome");
$allUsersJs = json_encode(array_map(fn($u) => [
    'id'         => (int)$u['id'],
    'nome'       => $u['nome'],
    'prontuario' => $u['prontuario'],
    'turno'      => $u['turno'],
], $stmtAll->fetchAll()), $jsonFlags);
?>

<!-- Page header -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="page-title mb-0">
        <i class="bi bi-table me-2"></i>Controle de Presença
    </h4>
    <a href="/controle_absenteismo/usuarios.php" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-people me-1"></i>Gerenciar Funcionários
    </a>
</div>

<!-- Filters -->
<form method="GET" class="app-card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-sm-3 col-md-2">
            <label class="form-label form-label-sm fw-semibold mb-1">Mês</label>
            <select name="mes" class="form-select form-select-sm">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m === $mes ? 'selected' : '' ?>>
                        <?= $mesesNome[$m] ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-sm-2 col-md-2">
            <label class="form-label form-label-sm fw-semibold mb-1">Ano</label>
            <select name="ano" class="form-select form-select-sm">
                <?php foreach ($anosOpts as $a): ?>
                    <option value="<?= $a ?>" <?= $a === $ano ? 'selected' : '' ?>><?= $a ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-3 col-md-2">
            <label class="form-label form-label-sm fw-semibold mb-1">Turno</label>
            <select name="turno" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($turnos as $t): ?>
                    <option value="<?= $t ?>" <?= $t === $turno ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-3 col-md-2">
            <label class="form-label form-label-sm fw-semibold mb-1">Setor</label>
            <select name="setor" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($setoresRows as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $s === $setor ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-funnel me-1"></i>Filtrar
            </button>
        </div>
        <!-- <div class="col-12 mt-1">
            <div class="alert alert-info py-1 px-2 mb-0 small d-flex align-items-center gap-2">
                <i class="bi bi-hand-index-thumb-fill fs-6 flex-shrink-0"></i>
                <span>
                    <strong>Como usar:</strong> clique em uma célula para registrar uma ocorrência.
                    Selecione um ou mais colaboradores com os checkboxes para aplicar a mesma ocorrência a todos de uma vez.
                    Clique no cabeçalho de um dia para atribuir feriado em lote.
                    Sábados e domingos ficam em branco por padrão.
                </span>
            </div>
        </div> -->
    </div>
</form>

<!-- Quick-register form -->
<div class="app-card p-3 mb-3">
    <h6 class="fw-semibold mb-2">
        <i class="bi bi-pencil-square me-2"></i>Registro Rápido de Ocorrência
    </h6>
    <div class="row g-2 align-items-end">
        <div class="col-auto">
            <label class="form-label form-label-sm fw-semibold mb-1">Buscar por</label>
            <select id="qrMode" class="form-select form-select-sm">
                <option value="prontuario">Prontuário</option>
                <option value="nome">Nome</option>
            </select>
        </div>
        <div class="col-5 col-sm-2 col-lg-2">
            <label class="form-label form-label-sm fw-semibold mb-1" for="qrProntuario" id="qrProntuarioLabel">Prontuário</label>
            <input type="text" id="qrProntuario" class="form-control form-control-sm"
                   placeholder="Ex.: 12345" autocomplete="off">
        </div>
        <div class="col-7 col-sm-4 col-lg-3">
            <label class="form-label form-label-sm fw-semibold mb-1" for="qrNome" id="qrNomeLabel">Funcionário</label>
            <input type="text" id="qrNome" class="form-control form-control-sm"
                   readonly placeholder="—" style="background:#f8f9fa"
                   list="qrNomeList" autocomplete="off">
            <datalist id="qrNomeList"></datalist>
        </div>
        <div class="col-5 col-sm-3 col-lg-2">
            <label class="form-label form-label-sm fw-semibold mb-1" for="qrData">Data</label>
            <input type="date" id="qrData" class="form-control form-control-sm"
                   value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-7 col-sm-4 col-lg-2">
            <label class="form-label form-label-sm fw-semibold mb-1" for="qrTipo">Tipo</label>
            <select id="qrTipo" class="form-select form-select-sm">
                <?php foreach (JUSTIFICATIVAS as $code => $j): ?>
                <option value="<?= $code ?>"><?= $code ?> — <?= htmlspecialchars($j['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col col-sm-5 col-lg-3">
            <label class="form-label form-label-sm fw-semibold mb-1" for="qrObs">Observação (opcional)</label>
            <input type="text" id="qrObs" class="form-control form-control-sm" placeholder="...">
        </div>
        <div class="col-auto">
            <button type="button" id="qrSalvar" class="btn btn-primary btn-sm" disabled>
                <i class="bi bi-check-circle me-1"></i>Salvar
            </button>
        </div>
    </div>
    <div id="qrStatus" class="form-text mt-1" style="min-height:1rem"></div>
</div>

<!-- Legend -->
<div class="app-card p-2 mb-3">
    <div class="legend-section mb-1">
        <span class="legend-section-title">Ausências / Afastamentos</span>
        <div class="legend-grid">
            <?php foreach (JUSTIFICATIVAS as $code => $j): if ($j['group'] !== 'ausencia') continue; ?>
            <div class="legend-item">
                <span class="legend-badge"
                      style="background:<?= $j['bg'] ?>;color:<?= $j['text'] ?>;
                             <?= $j['bold'] ? 'font-weight:700' : '' ?>">
                    <?= $code ?>
                </span>
                <span class="text-muted"><?= $j['label'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="legend-section">
        <span class="legend-section-title text-warning-emphasis">Trabalho em Feriado / Fim de Semana</span>
        <div class="legend-grid">
            <?php foreach (JUSTIFICATIVAS as $code => $j): if ($j['group'] !== 'trabalho') continue; ?>
            <div class="legend-item">
                <span class="legend-badge"
                      style="background:<?= $j['bg'] ?>;color:<?= $j['text'] ?>;
                             <?= $j['bold'] ? 'font-weight:700' : '' ?>">
                    <?= $code ?>
                </span>
                <span class="text-muted"><?= $j['label'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Multi-select toolbar (hidden until a checkbox is checked) -->
<div id="selectionToolbar" class="selection-toolbar d-none">
    <span id="selectionCount" class="fw-semibold"></span>
    <button type="button" class="btn btn-sm btn-outline-light ms-2" id="btnDeselectAll">
        <i class="bi bi-x-circle me-1"></i>Desmarcar todos
    </button>
    <span class="ms-2 text-white-50 small">Clique em uma célula da grade para aplicar a ocorrência aos selecionados.</span>
</div>

<!-- Grid -->
<?php if (empty($usuarios)): ?>
<div class="app-card p-5 text-center text-muted">
    <i class="bi bi-people fs-1 d-block mb-2"></i>
    Nenhum funcionário encontrado.
    <a href="/controle_absenteismo/usuarios.php" class="ms-1">Cadastrar funcionários</a>.
</div>
<?php else: ?>
<!-- Grid search -->
<div class="d-flex align-items-center gap-2 mb-2">
    <div class="input-group input-group-sm" style="max-width:300px">
        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
        <input type="text" id="gridSearch" class="form-control"
               placeholder="Filtrar por nome ou prontuário…">
        <button type="button" class="btn btn-outline-secondary d-none" id="gridSearchClear" title="Limpar">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <span id="gridSearchCount" class="text-muted small"></span>
</div>
<div class="grid-wrapper">
    <table class="grid-table" id="gridTable">
        <thead>
            <tr>
                <!-- Checkbox "select all" -->
                <th class="th-check" title="Selecionar todos">
                    <input type="checkbox" id="chkAll" class="form-check-input form-check-input-sm">
                </th>
                <th class="th-name">
                    <?= $mesesNome[$mes] ?> / <?= $ano ?>
                    &nbsp;<small class="opacity-75">(<?= count($usuarios) ?> func.)</small>
                </th>
                <?php for ($d = 1; $d <= $diasNoMes; $d++):
                    $dt  = mktime(0,0,0,$mes,$d,$ano);
                    $dow = (int)date('w', $dt);
                    $isWeekend = ($dow === 0 || $dow === 6);
                    $dateStr   = sprintf('%04d-%02d-%02d', $ano, $mes, $d);
                ?>
                <th class="th-day <?= $isWeekend ? 'weekend' : '' ?> th-day-clickable"
                    data-date="<?= $dateStr ?>"
                    data-dow="<?= $dow ?>"
                    title="Clique para atribuir feriado — <?= $diasSemana[$dow] ?> <?= $d ?>/<?= $mes ?>">
                    <div><?= $diasSemana[$dow] ?></div>
                    <div style="font-size:.9em;opacity:.85"><?= $d ?></div>
                </th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u):
            $uid = $u['id'];
        ?>
            <tr class="employee-row" data-uid="<?= $uid ?>">
                <!-- Per-employee checkbox -->
                <td class="td-check">
                    <input type="checkbox" class="form-check-input form-check-input-sm emp-chk"
                           value="<?= $uid ?>" data-nome="<?= htmlspecialchars($u['nome'], ENT_QUOTES) ?>">
                </td>
                <td class="td-name">
                    <div class="fw-medium" style="font-size:.75rem;line-height:1.2">
                        <?= htmlspecialchars($u['nome']) ?>
                    </div>
                    <div>
                        <span class="turno-badge turno-<?= htmlspecialchars($u['turno']) ?>"
                              style="font-size:.6rem;padding:1px 6px">
                            <?= htmlspecialchars($u['turno']) ?>
                        </span>
                        <span class="text-muted" style="font-size:.62rem">
                            <?= htmlspecialchars($u['prontuario']) ?>
                        </span>
                    </div>
                </td>
                <?php for ($d = 1; $d <= $diasNoMes; $d++):
                    $dateStr   = sprintf('%04d-%02d-%02d', $ano, $mes, $d);
                    $dow       = (int)date('w', mktime(0,0,0,$mes,$d,$ano));
                    $isWeekend = ($dow === 0 || $dow === 6);

                    $rec  = $ocorrencias[$uid][$dateStr] ?? null;

                    // Weekends are blank by default; weekdays default to P
                    if ($rec) {
                        $code = $rec['justificativa'];
                        $obs  = $rec['observacao'] ?? '';
                        $j    = JUSTIFICATIVAS[$code];
                        $style = "background:{$j['bg']};color:{$j['text']};" . ($j['bold'] ? 'font-weight:700;' : '');
                        $displayCode = $code;
                    } elseif ($isWeekend) {
                        $code  = '';
                        $obs   = '';
                        $style = '';           // blank — CSS .grid-cell.weekend-blank handles it
                        $displayCode = '';
                    } else {
                        $code  = 'P';
                        $obs   = '';
                        $j     = JUSTIFICATIVAS['P'];
                        $style = "background:{$j['bg']};color:{$j['text']};";
                        $displayCode = 'P';
                    }
                ?>
                <td class="grid-cell <?= $isWeekend ? ($rec ? 'weekend' : 'weekend weekend-blank') : '' ?>"
                    style="<?= $style ?>"
                    data-uid="<?= $uid ?>"
                    data-nome="<?= htmlspecialchars($u['nome'], ENT_QUOTES) ?>"
                    data-date="<?= $dateStr ?>"
                    data-code="<?= $code ?>"
                    data-obs="<?= htmlspecialchars($obs, ENT_QUOTES) ?>"
                    data-weekend="<?= $isWeekend ? '1' : '0' ?>"
                    title="<?= htmlspecialchars($u['nome']) ?> — <?= $dateStr ?><?= $code ? ' — ' . (JUSTIFICATIVAS[$code]['label'] ?? '') : '' ?>">
                    <?= $displayCode ?>
                </td>
                <?php endfor; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ====================================================
     Modal de Ocorrência (célula individual ou em lote)
     ==================================================== -->
<div class="modal fade" id="occModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title mb-0" id="occModalTitle">
                    <i class="bi bi-calendar-event me-2"></i>Registrar Ocorrência
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pb-2">
                <p class="mb-1 text-muted small" id="occModalSubtitle"></p>
                <!-- Badge shown when multiple employees are selected -->
                <div id="batchBadge" class="d-none mb-2">
                    <span class="badge bg-warning text-dark">
                        <i class="bi bi-people-fill me-1"></i>
                        <span id="batchCount"></span> colaboradores selecionados — a ocorrência será aplicada a todos.
                    </span>
                </div>
                <hr class="my-2">

                <div class="mb-2">
                    <label class="form-label fw-semibold small mb-1">Ausências / Afastamentos</label>
                    <div class="just-grid" id="justGridAusencia"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small mb-1">
                        <span class="badge me-1" style="background:#E65100">HE</span>
                        Trabalho em Feriado / Fim de Semana
                    </label>
                    <div class="just-grid just-grid-trabalho" id="justGridTrabalho"></div>
                </div>

                <div class="mb-2">
                    <label for="obsInput" class="form-label fw-semibold small mb-1">Observação (opcional):</label>
                    <textarea id="obsInput" class="form-control form-control-sm" rows="2"
                              placeholder="Detalhe adicional..."></textarea>
                </div>
            </div>
            <div class="modal-footer py-2 gap-2">
                <button type="button" class="btn btn-sm btn-outline-danger" id="btnLimpar">
                    <i class="bi bi-x-circle me-1"></i>Limpar
                </button>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="btnSalvar">
                    <i class="bi bi-check-circle me-1"></i>Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================
     Modal de Atribuição de Feriado (cabeçalho do dia)
     ==================================================== -->
<div class="modal fade" id="holidayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header py-2" style="background:#00CED1;color:#fff">
                <h6 class="modal-title mb-0">
                    <i class="bi bi-calendar-star me-2"></i>Atribuir Feriado / Dia Especial —
                    <span id="holidayModalDate"></span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pb-2">
                <p class="text-muted small mb-2">
                    Classifique cada colaborador para este dia. Quem <strong>não trabalhou</strong> recebe
                    <span class="badge" style="background:#00CED1">FER</span>.
                    Quem foi trabalhar recebe
                    <span class="badge" style="background:#E65100">HE</span> ou
                    <span class="badge" style="background:#1565C0">BH</span>.
                </p>

                <!-- Quick-set buttons -->
                <div class="d-flex gap-2 flex-wrap mb-3">
                    <button type="button" class="btn btn-sm hol-all" data-code="FER"
                            style="background:#00CED1;color:#fff">
                        <i class="bi bi-check-all me-1"></i>Todos: Feriado (FER)
                    </button>
                    <button type="button" class="btn btn-sm hol-all" data-code="HE"
                            style="background:#E65100;color:#fff">
                        <i class="bi bi-check-all me-1"></i>Todos: Hora Extra (HE)
                    </button>
                    <button type="button" class="btn btn-sm hol-all" data-code="BH"
                            style="background:#1565C0;color:#fff">
                        <i class="bi bi-check-all me-1"></i>Todos: Banco de Horas (BH)
                    </button>
                </div>

                <div style="max-height:400px;overflow-y:auto">
                    <table class="table table-sm table-bordered mb-0" id="holidayTable">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th>Colaborador</th>
                                <th>Turno</th>
                                <th style="min-width:220px">Classificação</th>
                            </tr>
                        </thead>
                        <tbody id="holidayTableBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 gap-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="btnSalvarFeriado">
                    <i class="bi bi-check-circle me-1"></i>Salvar Atribuições
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Dados para controle.js -->
<script id="app-data"      type="application/json"><?= $justJs     ?></script>
<script id="app-users"     type="application/json"><?= $usersJs    ?></script>
<script id="app-all-users" type="application/json"><?= $allUsersJs ?></script>

<?php
$pageScripts = [
    '/controle_absenteismo/assets/js/modules/api.js',
    '/controle_absenteismo/assets/js/modules/toast.js',
    '/controle_absenteismo/assets/js/pages/controle.js',
];
include 'includes/footer.php';
