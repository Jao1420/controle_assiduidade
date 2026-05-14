<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/justificativas.php';

security_bootstrap(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

enforce_csrf_or_exit_json();

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Corpo JSON inválido']);
    exit;
}

// ---- Resolve lista de IDs (single ou batch) ---------------
$usuarioIds = [];
if (!empty($data['usuario_ids']) && is_array($data['usuario_ids'])) {
    foreach ($data['usuario_ids'] as $id) {
        $id = (int)$id;
        if ($id > 0) $usuarioIds[] = $id;
    }
} elseif (!empty($data['usuario_id'])) {
    $id = (int)$data['usuario_id'];
    if ($id > 0) $usuarioIds[] = $id;
}

if (empty($usuarioIds)) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de usuário inválido']);
    exit;
}

$date          = trim($data['data']          ?? '');
$justificativa = trim($data['justificativa'] ?? '');
$observacao    = trim($data['observacao']    ?? '');

if (mb_strlen($observacao, 'UTF-8') > 500) {
    http_response_code(400);
    echo json_encode(['error' => 'Observação muito longa (máx. 500 caracteres)']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato de data inválido']);
    exit;
}

$validCodes = array_keys(JUSTIFICATIVAS);
if (!in_array($justificativa, $validCodes, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Justificativa inválida']);
    exit;
}

$pdo = getConnection();

// ---- Verify all users exist and are active ----------------
$placeholders = implode(',', array_fill(0, count($usuarioIds), '?'));
$stmt = $pdo->prepare(
    "SELECT id FROM usuarios WHERE id IN ($placeholders) AND ativo = 1"
);
$stmt->execute($usuarioIds);
$found = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');

if (count($found) !== count($usuarioIds)) {
    http_response_code(404);
    echo json_encode(['error' => 'Um ou mais funcionários não encontrados']);
    exit;
}

// ---- Upsert for each user ---------------------------------
$stmt = $pdo->prepare(
    "INSERT INTO ocorrencias (usuario_id, data, justificativa, observacao)
     VALUES (:uid, :data, :just, :obs)
     ON DUPLICATE KEY UPDATE
         justificativa = VALUES(justificativa),
         observacao    = VALUES(observacao),
         updated_at    = NOW()"
);

$pdo->beginTransaction();
try {
    foreach ($usuarioIds as $uid) {
        $stmt->execute([
            ':uid'  => $uid,
            ':data' => $date,
            ':just' => $justificativa,
            ':obs'  => $observacao,
        ]);
    }
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno ao salvar ocorrência']);
    exit;
}

echo json_encode([
    'success'       => true,
    'justificativa' => $justificativa,
    'saved'         => count($usuarioIds),
]);
