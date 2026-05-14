<?php
/**
 * Security bootstrap and CSRF helpers.
 */

function security_bootstrap(bool $isApi = false): void
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Resource-Policy: same-origin');

    if (!$isApi) {
        // Keep CSP permissive enough for current inline scripts/styles and CDN assets.
        header(
            "Content-Security-Policy: default-src 'self'; " .
            "script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; " .
            "style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; " .
            "font-src 'self' https://cdn.jsdelivr.net data:; " .
            "img-src 'self' data:; " .
            "connect-src 'self'; " .
            "frame-ancestors 'none'; " .
            "base-uri 'self'; " .
            "form-action 'self'"
        );
    }

    $bootstrapped = true;
}

function get_csrf_token(): string
{
    security_bootstrap();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(): bool
{
    security_bootstrap(true);

    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $bodyToken = '';

    if (isset($_POST['csrf_token'])) {
        $bodyToken = (string) $_POST['csrf_token'];
    }

    $token = $headerToken !== '' ? $headerToken : $bodyToken;
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    return is_string($token)
        && $token !== ''
        && is_string($sessionToken)
        && $sessionToken !== ''
        && hash_equals($sessionToken, $token);
}

function enforce_csrf_or_exit_json(): void
{
    if (!verify_csrf_token()) {
        http_response_code(419);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Token CSRF inválido ou ausente']);
        exit;
    }
}
