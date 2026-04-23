<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$prontuario = trim($_GET['prontuario'] ?? '');
if ($prontuario === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Prontuário obrigatório']);
    exit;
}

$pdo  = getConnection();
$stmt = $pdo->prepare(
    "SELECT id, prontuario, nome, turno FROM usuarios WHERE prontuario = :p AND ativo = 1 LIMIT 1"
);
$stmt->execute([':p' => $prontuario]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'Funcionário não encontrado']);
    exit;
}

echo json_encode([
    'id'         => (int)$user['id'],
    'prontuario' => $user['prontuario'],
    'nome'       => $user['nome'],
    'turno'      => $user['turno'],
]);
