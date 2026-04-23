<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$usuarioId = (int)($data['usuario_id'] ?? 0);
$date      = trim($data['data']        ?? '');

// ---- Validation -------------------------------------------
if ($usuarioId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de usuário inválido']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato de data inválido']);
    exit;
}

$pdo  = getConnection();
$stmt = $pdo->prepare(
    "DELETE FROM ocorrencias WHERE usuario_id = :uid AND data = :data"
);
$stmt->execute([':uid' => $usuarioId, ':data' => $date]);

echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
