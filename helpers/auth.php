<?php
// helpers/auth.php

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
    if (empty($_SESSION['__init'])) {
        session_regenerate_id(true);
        $_SESSION['__init'] = time();
    }
}

function require_login(): void {
    if (empty($_SESSION['usuario'])) {
        header('Location: /');
        exit;
    }
}

/**
 * Verifica que el usuario tenga el rol requerido
 * @param int|array $roles Uno o varios roles permitidos
 */
function require_role(int|array $roles): void {
    if (empty($_SESSION['usuario'])) {
        header('Location: /');
        exit;
    }

    $userRole = (int)($_SESSION['usuario']['rol_id'] ?? 0);

    // Asegurar que $roles siempre sea un array
    $roles = is_array($roles) ? $roles : [$roles];

    if (!in_array($userRole, $roles, true)) {
        http_response_code(403);
        echo "Acceso denegado.";
        exit;
    }
}

/** CSRF **/
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string {
    $t = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="'.$t.'">';
}

function csrf_validate(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(400);
            echo "Token CSRF inválido.";
            exit;
        }
    }
}
