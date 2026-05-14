<?php
require_once 'config/database.php';
require_once 'config/justificativas.php';
include 'includes/header.php';

$pdo   = getConnection();
$today = date('Y-m-d');

// ---- Filtros -----------------------------------------------
$setor = isset($_GET['setor']) ? trim($_GET['setor']) : '';
$setoresRows = $pdo->query("SELECT DISTINCT setor FROM usuarios WHERE ativo = 1 AND setor <> '' ORDER BY setor")->fetchAll(PDO::FETCH_COLUMN);
if ($setor && !in_array($setor, $setoresRows, true)) {
    $setor = '';
}

// Condição de setor para os queries
$setorJoin   = $setor !== '' ? " JOIN usuarios _us ON _us.id = o.usuario_id AND _us.setor = " . $pdo->quote($setor) : '';
$setorWhere  = $setor !== '' ? " AND u.setor = " . $pdo->quote($setor) : '';
$setorWhere2 = $setor !== '' ? " AND setor = " . $pdo->quote($setor) : '';

$mesAtual    = date('Y-m');
$primeiroDia = date('Y-m-01');
$ultimoDia   = date('Y-m-t');

/* ---- KPIs ------------------------------------------------ */
$totalFunc = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1{$setorWhere2}")->fetchColumn();

// Presentes hoje: total - quem tem ocorrência diferente de 'P' hoje
$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT o.usuario_id)
     FROM ocorrencias o
     JOIN usuarios u ON u.id = o.usuario_id
     WHERE o.data = :d AND o.justificativa <> 'P'{$setorWhere}"
);
$stmt->execute([':d' => $today]);
$comOcorrencia = (int) $stmt->fetchColumn();
$presentesHoje = max(0, $totalFunc - $comOcorrencia);
$pctPresentes  = $totalFunc > 0 ? round($presentesHoje / $totalFunc * 100, 1) : 0;

// Faltas não justificadas hoje
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM ocorrencias o
     JOIN usuarios u ON u.id = o.usuario_id
     WHERE o.data = :d AND o.justificativa = 'F'{$setorWhere}"
);
$stmt->execute([':d' => $today]);
$faltasHoje = (int) $stmt->fetchColumn();

// Total de ocorrências no mês (excluindo P explícito)
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM ocorrencias o
     JOIN usuarios u ON u.id = o.usuario_id
     WHERE DATE_FORMAT(o.data,'%Y-%m') = :m AND o.justificativa <> 'P'{$setorWhere}"
);
$stmt->execute([':m' => $mesAtual]);
$totalOcMes = (int) $stmt->fetchColumn();

/* ---- Chart: ocorrências por justificativa no mês --------- */
$stmt = $pdo->prepare(
    "SELECT o.justificativa, COUNT(*) AS total
     FROM ocorrencias o
     JOIN usuarios u ON u.id = o.usuario_id
     WHERE DATE_FORMAT(o.data,'%Y-%m') = :m AND o.justificativa <> 'P'{$setorWhere}
     GROUP BY o.justificativa
     ORDER BY total DESC"
);
$stmt->execute([':m' => $mesAtual]);
$ocPorJust = $stmt->fetchAll();

$chartJustLabels = [];
$chartJustData   = [];
$chartJustColors = [];
$chartJustKeys   = [];
foreach ($ocPorJust as $row) {
    $j = JUSTIFICATIVAS[$row['justificativa']] ?? null;
    if (!$j) continue;
    $chartJustLabels[] = $row['justificativa'] . ' — ' . $j['label'];
    $chartJustData[]   = (int) $row['total'];
    $chartJustColors[] = $j['bg'];
    $chartJustKeys[]   = $row['justificativa'];
}

/* ---- Chart: detalhes por justificativa (nome + data) ----- */
$stmt = $pdo->prepare(
    "SELECT o.justificativa, o.data, u.nome, u.prontuario
     FROM ocorrencias o
     JOIN usuarios u ON u.id = o.usuario_id
     WHERE DATE_FORMAT(o.data,'%Y-%m') = :m AND o.justificativa <> 'P'{$setorWhere}
     ORDER BY o.data DESC"
);
$stmt->execute([':m' => $mesAtual]);
$justDetails = [];
foreach ($stmt->fetchAll() as $row) {
    $justDetails[$row['justificativa']][] = [
        'nome'       => $row['nome'],
        'prontuario' => $row['prontuario'],
        'data'       => date('d/m/Y', strtotime($row['data'])),
    ];
}

/* ---- Chart: % presença dias úteis do mês até hoje -------- */
$stmt = $pdo->prepare(
    "SELECT o.data, COUNT(DISTINCT o.usuario_id) AS qtd
     FROM ocorrencias o
     JOIN usuarios u ON u.id = o.usuario_id
     WHERE o.data BETWEEN :ini AND :fim AND o.justificativa <> 'P'{$setorWhere}
     GROUP BY o.data"
);
$stmt->execute([':ini' => $primeiroDia, ':fim' => $today]);
$ausenciasPorDia = [];
foreach ($stmt->fetchAll() as $row) {
    $ausenciasPorDia[$row['data']] = (int) $row['qtd'];
}

$chartDias   = [];
$chartPct    = [];
$dt = new DateTime($primeiroDia);
$dtHoje = new DateTime($today);
while ($dt <= $dtHoje) {
    if ((int) $dt->format('N') <= 5) { // segunda a sexta
        $d   = $dt->format('Y-m-d');
        $aus = $ausenciasPorDia[$d] ?? 0;
        $pct = $totalFunc > 0 ? round(max(0, $totalFunc - $aus) / $totalFunc * 100, 1) : 0;
        $chartDias[] = $dt->format('d/m');
        $chartPct[]  = $pct;
    }
    $dt->modify('+1 day');
}

/* ---- Últimas 10 ocorrências ------------------------------ */
$stmt = $pdo->prepare(
    "SELECT o.data, o.justificativa, o.observacao,
            u.nome, u.prontuario, u.turno
     FROM ocorrencias o
     JOIN usuarios u ON u.id = o.usuario_id
     WHERE o.justificativa <> 'P'{$setorWhere}
     ORDER BY o.data DESC, o.updated_at DESC
     LIMIT 10"
);
$stmt->execute();
$ultimasOc = $stmt->fetchAll();

$diaSemana = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
$mesNome   = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
?>

<!-- Page header -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="page-title mb-0">
        <i class="bi bi-speedometer2 me-2"></i>Dashboard
    </h4>
    <span class="text-muted small">
        <i class="bi bi-clock me-1"></i>
        <?= $diaSemana[(int)date('w')] ?>, <?= date('d') ?> de <?= $mesNome[(int)date('n')] ?> de <?= date('Y') ?>
    </span>
</div>

<!-- Filtro de setor -->
<form method="GET" class="app-card p-3 mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-sm-4 col-md-3">
            <label class="form-label form-label-sm fw-semibold mb-1">Setor</label>
            <select name="setor" class="form-select form-select-sm">
                <option value="">Todos os Setores</option>
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
            <?php if ($setor !== ''): ?>
            <a href="?" class="btn btn-outline-secondary btn-sm ms-1">
                <i class="bi bi-x me-1"></i>Limpar
            </a>
            <?php endif; ?>
        </div>
        <?php if ($setor !== ''): ?>
        <div class="col-12">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                <i class="bi bi-funnel-fill me-1"></i>Setor: <?= htmlspecialchars($setor) ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
</form>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="kpi-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon" style="background:#E3F2FD">
                    <i class="bi bi-people-fill fs-4 text-primary"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Funcionários</div>
                    <div class="fw-bold fs-2 lh-1"><?= $totalFunc ?></div>
                    <div class="text-muted" style="font-size:.7rem">ativos</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon" style="background:#E8F5E9">
                    <i class="bi bi-check-circle-fill fs-4" style="color:#43A047"></i>
                </div>
                <div>
                    <div class="text-muted small">Presentes Hoje</div>
                    <div class="fw-bold fs-2 lh-1"><?= $pctPresentes ?>%</div>
                    <div class="text-muted" style="font-size:.7rem"><?= $presentesHoje ?> de <?= $totalFunc ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon" style="background:#FFEBEE">
                    <i class="bi bi-x-circle-fill fs-4" style="color:#E53935"></i>
                </div>
                <div>
                    <div class="text-muted small">Faltas Hoje (F)</div>
                    <div class="fw-bold fs-2 lh-1"><?= $faltasHoje ?></div>
                    <div class="text-muted" style="font-size:.7rem">não justificadas</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon" style="background:#FFF8E1">
                    <i class="bi bi-calendar3 fs-4" style="color:#F9A825"></i>
                </div>
                <div>
                    <div class="text-muted small">Ocorrências no Mês</div>
                    <div class="fw-bold fs-2 lh-1"><?= $totalOcMes ?></div>
                    <div class="text-muted" style="font-size:.7rem"><?= $mesNome[(int)date('n')] ?>/<?= date('Y') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="app-card p-3 h-100">
            <h6 class="fw-bold text-muted mb-3">
                <i class="bi bi-bar-chart-horizontal me-1"></i>
                Ocorrências por Tipo — <?= $mesNome[(int)date('n')] ?>/<?= date('Y') ?>
            </h6>
            <div style="position:relative;height:<?= max(240, count($chartJustData) * 34 + 20) ?>px">
                <canvas id="chartJust"></canvas>
                <?php if (empty($chartJustData)): ?>
                <div class="d-flex align-items-center justify-content-center h-100 position-absolute top-0 start-0 w-100">
                    <span class="text-muted small">Nenhuma ocorrência registrada neste mês</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="app-card p-3 h-100">
            <h6 class="fw-bold text-muted mb-3">
                <i class="bi bi-graph-up me-1"></i>
                % Presença Dias Úteis — <?= $mesNome[(int)date('n')] ?>/<?= date('Y') ?>
            </h6>
            <div style="position:relative;height:240px">
                <canvas id="chartPresenca"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Últimas ocorrências -->
<div class="app-card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-muted mb-0">
            <i class="bi bi-clock-history me-1"></i>Últimas Ocorrências Registradas
        </h6>
        <a href="/controle_absenteismo/controle.php" class="btn btn-sm btn-outline-primary">
            Ver Controle Completo
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th style="width:90px">Data</th>
                    <th style="width:90px">Prontuário</th>
                    <th>Nome</th>
                    <th style="width:110px">Turno</th>
                    <th style="width:200px">Justificativa</th>
                    <th>Observação</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($ultimasOc)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="bi bi-inbox me-2"></i>Nenhuma ocorrência registrada
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($ultimasOc as $oc):
                    $j = JUSTIFICATIVAS[$oc['justificativa']] ?? JUSTIFICATIVAS['P'];
                ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($oc['data'])) ?></td>
                    <td class="fw-medium"><?= htmlspecialchars($oc['prontuario']) ?></td>
                    <td><?= htmlspecialchars($oc['nome']) ?></td>
                    <td>
                        <span class="turno-badge turno-<?= htmlspecialchars($oc['turno']) ?>">
                            <?= htmlspecialchars($oc['turno']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge px-2 py-1"
                              style="background:<?= $j['bg'] ?>;color:<?= $j['text'] ?>;font-size:.72rem;
                                     <?= $j['bold'] ? 'font-weight:700' : '' ?>">
                            <?= $oc['justificativa'] ?> — <?= $j['label'] ?>
                        </span>
                    </td>
                    <td class="text-muted small"><?= htmlspecialchars($oc['observacao'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Dados para o dashboard.js -->
<script id="app-data" type="application/json"><?= json_encode([
    'justLabels'  => $chartJustLabels,
    'justData'    => $chartJustData,
    'justColors'  => $chartJustColors,
    'justKeys'    => $chartJustKeys,
    'justDetails' => $justDetails,
    'dias'        => $chartDias,
    'presenca'    => $chartPct,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<!-- Modal: detalhe do slice do gráfico de pizza -->
<div class="modal fade" id="donutDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2" id="donutDetailHeader">
                <h6 class="modal-title mb-0" id="donutDetailTitle"></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:95px">Data</th>
                            <th style="width:110px">Prontuário</th>
                            <th>Nome</th>
                        </tr>
                    </thead>
                    <tbody id="donutDetailBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = [
    '/controle_absenteismo/assets/js/modules/api.js',
    '/controle_absenteismo/assets/js/modules/toast.js',
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
    '/controle_absenteismo/assets/js/pages/dashboard.js',
];
include 'includes/footer.php';
