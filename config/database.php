<?php
/**
 * Returns a singleton PDO connection using credentials from .env
 */

/**
 * Normalizes .env values (trim + optional quote removal).
 */
function normalizeEnvValue(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $first = $value[0];
    $last  = $value[strlen($value) - 1];
    if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
        $value = substr($value, 1, -1);
    }

    return trim($value);
}

/**
 * Ensures the minimal schema exists for first run environments.
 */
function ensureSchema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS usuarios (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            prontuario VARCHAR(50) NOT NULL,
            nome VARCHAR(150) NOT NULL,
            turno VARCHAR(50) NOT NULL,
            setor VARCHAR(50) NOT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_usuarios_prontuario (prontuario)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS ocorrencias (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT UNSIGNED NOT NULL,
            data DATE NOT NULL,
            justificativa VARCHAR(10) NOT NULL,
            observacao VARCHAR(500) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_ocorrencias_usuario_data (usuario_id, data),
            KEY idx_ocorrencias_data (data),
            CONSTRAINT fk_ocorrencias_usuario FOREIGN KEY (usuario_id)
                REFERENCES usuarios(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function getConnection(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $env = [];
    $envPath = __DIR__ . '/../.env';
    if (file_exists($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $key = trim($k);
            if ($key === '') {
                continue;
            }

            // Remove UTF-8 BOM if present in the first key.
            if (str_starts_with($key, "\xEF\xBB\xBF")) {
                $key = substr($key, 3);
            }

            $env[$key] = normalizeEnvValue($v);
        }
    }

    $requiredKeys = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USERNAME'];
    foreach ($requiredKeys as $requiredKey) {
        if (!array_key_exists($requiredKey, $env) || $env[$requiredKey] === '') {
            http_response_code(500);
            die(json_encode(['error' => 'Configuração de banco ausente no arquivo .env.']));
        }
    }

    $host   = $env['DB_HOST'];
    $port   = $env['DB_PORT'];
    $dbname = $env['DB_NAME'];
    $user   = $env['DB_USERNAME'];
    $pass   = $env['DB_PASSWORD'] ?? '';

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
            $user,
            $pass,
            $options
        );
    } catch (PDOException $e) {
        // Fallback: if DB is missing, connect to server and create it.
        if ((int)$e->getCode() === 1049 || stripos($e->getMessage(), 'Unknown database') !== false) {
            try {
                $bootstrapPdo = new PDO(
                    "mysql:host={$host};port={$port};charset=utf8mb4",
                    $user,
                    $pass,
                    $options
                );
                $safeDbName = str_replace('`', '``', $dbname);
                $bootstrapPdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                $pdo = new PDO(
                    "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
                    $user,
                    $pass,
                    $options
                );
            } catch (PDOException $e2) {
                http_response_code(500);
                die(json_encode(['error' => 'Falha na conexão com o banco de dados.']));
            }
        } else {
            http_response_code(500);
            // Do not expose DB details to the client
            die(json_encode(['error' => 'Falha na conexão com o banco de dados.']));
        }
    }

    ensureSchema($pdo);

    return $pdo;
}
