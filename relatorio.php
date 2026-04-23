<?php
require_once 'config/database.php';
require_once 'config/justificativas.php';
include 'includes/header.php';

$pdo    = getConnection();
$turnos = ['Comercial','Segundo Turno','Terceiro Turno'];

// ---- Filtros -----------------------------------------------
$dataIni = $_GET['data_ini'] ?? date('Y-m-01');
$dataFim = $_GET['data_fim'] ?? date('Y-m-t');
$turno   = $_GET['turno']    ?? '';

// Sanitize dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataIni)) $dataIni = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) $dataFim = date('Y-m-t');
if ($dataIni > $dataFim) [$dataIni, $dataFim] = [$dataFim, $dataIni];

if ($turno && !in_array($turno, $turnos, true)) $turno = '';

// ---- Dias úteis no período (seg-sex) ----------------------
$diasUteisNoPeriodo = 0;
$dtCur = new DateTime($dataIni);
$dtEnd = new DateTime($dataFim);
while ($dtCur <= $dtEnd) {
    if ((int)$dtCur->format('N') <= 5) $diasUteisNoPeriodo++;
    $dtCur->modify('+1 day');
}

// ---- Funcionários ativos ----------------------------------
$sqlUsers = "SELECT id, prontuario, nome, turno FROM usuarios WHERE ativo = 1";
$paramsU  = [];
if ($turno) {
    $sqlUsers .= " AND turno = :turno";
    $paramsU[':turno'] = $turno;
}
$sqlUsers .= " ORDER BY turno, nome";
$stmtU = $pdo->prepare($sqlUsers);
$stmtU->execute($paramsU);
$usuarios = $stmtU->fetchAll();

// ---- Ocorrências no período --------------------------------
if ($usuarios) {
    $uIds    = array_column($usuarios, 'id');
    $inClause = implode(',', array_fill(0, count($uIds), '?'));

    $stmtOc = $pdo->prepare(
        "SELECT usuario_id, justificativa, COUNT(*) AS total
         FROM ocorrencias
         WHERE data BETWEEN ? AND ? AND usuario_id IN ($inClause)
         GROUP BY usuario_id, justificativa"
    );
    $stmtOc->execute(array_merge([$dataIni, $dataFim], $uIds));
    $rawOc = $stmtOc->fetchAll();
} else {
    $rawOc = [];
}

// Index by [usuario_id][justificativa] = count
$ocMap = [];
foreach ($rawOc as $row) {
    $ocMap[$row['usuario_id']][$row['justificativa']] = (int)$row['total'];
}

// ---- Build report rows ------------------------------------
$rows = [];
foreach ($usuarios as $u) {
    $uid = $u['id'];
    $ocUser = $ocMap[$uid] ?? [];

    // Total dias com ocorrência diferente de P
    $diasAusencia = array_sum(array_filter($ocUser, fn($k) => $k !== 'P', ARRAY_FILTER_USE_KEY));

    // Dias de presença = dias úteis - ausências
    $diasPresenca = max(0, $diasUteisNoPeriodo - $diasAusencia);
    $pct = $diasUteisNoPeriodo > 0
        ? round($diasPresenca / $diasUteisNoPeriodo * 100, 1)
        : 0;

    $rows[] = [
        'prontuario'    => $u['prontuario'],
        'nome'          => $u['nome'],
        'turno'         => $u['turno'],
        'diasPresenca'  => $diasPresenca,
        'diasAusencia'  => $diasAusencia,
        'pct'           => $pct,
        'oc'            => $ocUser,
    ];
}

// ---- Resumo global ----------------------------------------
$totalFunc = count($rows);
$mediaPresenca = $totalFunc > 0 ? round(array_sum(array_column($rows, 'pct')) / $totalFunc, 1) : 0;
$totalFaltas   = array_sum(array_column(array_map(fn($r) => ['f' => $r['oc']['F'] ?? 0], $rows), 'f'));

// ---- Totais por justificativa no período ------------------
$totaisJust = [];
foreach (array_keys(JUSTIFICATIVAS) as $code) {
    if ($code === 'P') continue;
    $tot = 0;
    foreach ($rows as $r) $tot += $r['oc'][$code] ?? 0;
    if ($tot > 0) $totaisJust[$code] = $tot;
}
arsort($totaisJust);
?>

<!-- Page header -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="page-title mb-0">
        <i class="bi bi-bar-chart-fill me-2"></i>Relatório de Absenteísmo
    </h4>
    <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Imprimir
    </button>
</div>

<!-- Filters -->
<form method="GET" class="app-card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-sm-3 col-md-2">
            <label class="form-label form-label-sm fw-semibold mb-1">Data Início</label>
            <input type="date" name="data_ini" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($dataIni) ?>">
        </div>
        <div class="col-sm-3 col-md-2">
            <label class="form-label form-label-sm fw-semibold mb-1">Data Fim</label>
            <input type="date" name="data_fim" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($dataFim) ?>">
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
        <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-funnel me-1"></i>Gerar
            </button>
        </div>
    </div>
</form>

<!-- KPI Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-card p-3 text-center">
            <div class="fw-bold fs-2"><?= $totalFunc ?></div>
            <div class="text-muted small">Funcionários</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card p-3 text-center">
            <div class="fw-bold fs-2"><?= $diasUteisNoPeriodo ?></div>
            <div class="text-muted small">Dias Úteis no Período</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card p-3 text-center">
            <div class="fw-bold fs-2" style="color:#43A047"><?= $mediaPresenca ?>%</div>
            <div class="text-muted small">Média de Presença</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card p-3 text-center">
            <div class="fw-bold fs-2" style="color:#E53935"><?= $totalFaltas ?></div>
            <div class="text-muted small">Total Faltas (F)</div>
        </div>
    </div>
</div>

<!-- Totais por justificativa -->
<?php if (!empty($totaisJust)): ?>
<div class="app-card p-3 mb-4">
    <h6 class="fw-bold text-muted mb-3">
        <i class="bi bi-tags me-1"></i>Ocorrências por Tipo no Período
    </h6>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($totaisJust as $code => $tot):
            $j = JUSTIFICATIVAS[$code];
        ?>
        <div class="d-flex align-items-center gap-1 px-2 py-1 rounded"
             style="background:<?= $j['bg'] ?>;color:<?= $j['text'] ?>;font-size:.78rem;
                    <?= $j['bold'] ? 'font-weight:700' : '' ?>">
            <span><?= $code ?></span>
            <span class="opacity-75 small">—</span>
            <span><?= $j['label'] ?></span>
            <span class="ms-1 fw-bold">(<?= $tot ?>)</span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Detailed table -->
<div class="app-card p-3">
    <h6 class="fw-bold text-muted mb-3">
        <i class="bi bi-table me-1"></i>
        Detalhamento por Funcionário
        <small class="text-muted ms-2 fw-normal">
            (<?= date('d/m/Y', strtotime($dataIni)) ?> a <?= date('d/m/Y', strtotime($dataFim)) ?>)
        </small>
    </h6>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 table-app" id="tblRelatorio">
            <thead>
                <tr>
                    <th>Prontuário</th>
                    <th>Nome</th>
                    <th>Turno</th>
                    <th class="text-center">Presença</th>
                    <th class="text-center">Ausências</th>
                    <th class="text-center" style="width:120px">% Presença</th>
                    <?php foreach (array_keys($totaisJust) as $code):
                        $j = JUSTIFICATIVAS[$code];
                    ?>
                    <th class="text-center"
                        title="<?= $j['label'] ?>"
                        style="background:<?= $j['bg'] ?>;color:<?= $j['text'] ?>;
                               font-size:.68rem;<?= $j['bold'] ? 'font-weight:700' : '' ?>">
                        <?= $code ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="<?= 6 + count($totaisJust) ?>"
                        class="text-center text-muted py-4">
                        <i class="bi bi-inbox me-2"></i>Nenhum dado no período selecionado
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="fw-medium"><?= htmlspecialchars($r['prontuario']) ?></td>
                    <td><?= htmlspecialchars($r['nome']) ?></td>
                    <td>
                        <span class="turno-badge turno-<?= htmlspecialchars($r['turno']) ?>">
                            <?= htmlspecialchars($r['turno']) ?>
                        </span>
                    </td>
                    <td class="text-center"><?= $r['diasPresenca'] ?></td>
                    <td class="text-center <?= $r['diasAusencia'] > 0 ? 'text-danger fw-semibold' : '' ?>">
                        <?= $r['diasAusencia'] ?>
                    </td>
                    <td class="text-center">
                        <?php
                        $pctVal  = $r['pct'];
                        $barColor = $pctVal >= 95 ? '#43A047'
                                  : ($pctVal >= 80 ? '#F9A825' : '#E53935');
                        ?>
                        <div class="d-flex align-items-center gap-1">
                            <div class="flex-grow-1 rounded" style="height:6px;background:#eee">
                                <div style="width:<?= $pctVal ?>%;height:100%;background:<?= $barColor ?>;border-radius:3px;transition:width .3s"></div>
                            </div>
                            <span style="font-size:.75rem;min-width:38px;color:<?= $barColor ?>;font-weight:600">
                                <?= $pctVal ?>%
                            </span>
                        </div>
                    </td>
                    <?php foreach (array_keys($totaisJust) as $code): ?>
                    <td class="text-center">
                        <?php $v = $r['oc'][$code] ?? 0; ?>
                        <?= $v > 0 ? "<span style='font-size:.75rem'>$v</span>" : '<span class="text-muted" style="font-size:.7rem">—</span>' ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                <!-- Totals row -->
                <tr class="table-dark fw-semibold">
                    <td colspan="3">Total / Média</td>
                    <td class="text-center">
                        <?= array_sum(array_column($rows, 'diasPresenca')) ?>
                    </td>
                    <td class="text-center">
                        <?= array_sum(array_column($rows, 'diasAusencia')) ?>
                    </td>
                    <td class="text-center"><?= $mediaPresenca ?>%</td>
                    <?php foreach (array_keys($totaisJust) as $code): ?>
                    <td class="text-center"><?= $totaisJust[$code] ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
@media print {
    .app-navbar, form, .btn, .app-footer { display: none !important; }
    .main-content { padding: 0 !important; }
    .app-card { box-shadow: none !important; border: 1px solid #ddd; }
}
</style>

<?php include 'includes/footer.php'; ?>
