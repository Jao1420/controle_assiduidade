<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/justificativas.php';

security_bootstrap(true);

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getConnection();

// ---- GET: list users --------------------------------------
if ($method === 'GET') {
    $stmt = $pdo->query(
        "SELECT u.id, u.prontuario, u.nome, u.turno, u.setor,
                COUNT(o.id) AS total_ocorrencias
         FROM usuarios u
         LEFT JOIN ocorrencias o ON o.usuario_id = u.id
         WHERE u.ativo = 1
         GROUP BY u.id
         ORDER BY u.turno, u.nome"
    );
    echo json_encode($stmt->fetchAll());
    exit;
}

// ---- POST: create / update / delete -----------------------
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

enforce_csrf_or_exit_json();

$raw    = file_get_contents('php://input');
$data   = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Corpo JSON inválido']);
    exit;
}

$action = $data['action'] ?? '';

$turnos = ['Comercial','Segundo Turno','Terceiro Turno'];

if ($action === 'create') {
    $prontuario = trim($data['prontuario'] ?? '');
    $nome       = trim($data['nome']       ?? '');
    $turno      = $data['turno']           ?? '';
    $setor      = trim($data['setor']      ?? '');

    if (mb_strlen($prontuario, 'UTF-8') > 50 || mb_strlen($nome, 'UTF-8') > 150 || mb_strlen($setor, 'UTF-8') > 50) {
        http_response_code(400);
        echo json_encode(['error' => 'Campos excedem o tamanho permitido']);
        exit;
    }

    if (!$prontuario || !$nome || !in_array($turno, $turnos, true) || !$setor) {
        http_response_code(400);
        echo json_encode(['error' => 'Dados inválidos']);
        exit;
    }

    // Check unique prontuario
    $chk = $pdo->prepare("SELECT id FROM usuarios WHERE prontuario = :p");
    $chk->execute([':p' => $prontuario]);
    if ($chk->fetch()) {
        echo json_encode(['error' => "Prontuário '$prontuario' já cadastrado."]);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO usuarios (prontuario, nome, turno, setor) VALUES (:p, :n, :t, :s)"
    );
    $stmt->execute([':p' => $prontuario, ':n' => $nome, ':t' => $turno, ':s' => $setor]);
    echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);

} elseif ($action === 'update') {
    $id         = (int)($data['id']        ?? 0);
    $prontuario = trim($data['prontuario'] ?? '');
    $nome       = trim($data['nome']       ?? '');
    $turno      = $data['turno']           ?? '';
    $setor      = trim($data['setor']      ?? '');

    if (mb_strlen($prontuario, 'UTF-8') > 50 || mb_strlen($nome, 'UTF-8') > 150 || mb_strlen($setor, 'UTF-8') > 50) {
        http_response_code(400);
        echo json_encode(['error' => 'Campos excedem o tamanho permitido']);
        exit;
    }

    if (!$id || !$prontuario || !$nome || !in_array($turno, $turnos, true) || !$setor) {
        http_response_code(400);
        echo json_encode(['error' => 'Dados inválidos']);
        exit;
    }

    // Check unique prontuario (excluding current id)
    $chk = $pdo->prepare("SELECT id FROM usuarios WHERE prontuario = :p AND id <> :id");
    $chk->execute([':p' => $prontuario, ':id' => $id]);
    if ($chk->fetch()) {
        echo json_encode(['error' => "Prontuário '$prontuario' já está em uso."]);
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE usuarios SET prontuario = :p, nome = :n, turno = :t, setor = :s WHERE id = :id AND ativo = 1"
    );
    $stmt->execute([':p' => $prontuario, ':n' => $nome, ':t' => $turno, ':s' => $setor, ':id' => $id]);
    echo json_encode(['success' => true]);

} elseif ($action === 'delete') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID inválido']);
        exit;
    }
    // Soft delete
    $stmt = $pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = :id");
    $stmt->execute([':id' => $id]);
    echo json_encode(['success' => true]);

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Ação inválida']);
}
