<?php
// verify_reset_code.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Concatenar los 6 inputs
$code = (
    ($_POST['code1'] ?? '') .
    ($_POST['code2'] ?? '') .
    ($_POST['code3'] ?? '') .
    ($_POST['code4'] ?? '') .
    ($_POST['code5'] ?? '') .
    ($_POST['code6'] ?? '')
);

// Normalizar
$code = preg_replace('/\D/', '', $code);
if (strlen($code) !== 6) {
    header('Location: /?page=verify_code&error=1'); exit;
}

if (!isset($_SESSION['pw_reset'])) {
    header('Location: /?page=verify_code&error=1'); exit;
}

$pw = $_SESSION['pw_reset'];
$expected = $pw['code'] ?? '';
$expires = $pw['expires'] ?? 0;

if (time() > $expires) {
    unset($_SESSION['pw_reset']);
    header('Location: /?page=forgot_password&exp=1'); exit;
}

// Comparación segura
if (hash_equals((string)$expected, (string)$code)) {
    // Permitir cambio de contraseña — guardamos una marca temporal
    $_SESSION['pwd_change_allowed'] = [
        'user_id' => $pw['user_id'],
        'email' => $pw['email'],
        'allowed_at' => time()
    ];
    // Opcional: eliminar pw_reset para evitar reutilización
    unset($_SESSION['pw_reset']);
    header('Location: /?page=reset_password_code'); exit;
} else {
    header('Location: /?page=verify_code&error=1'); exit;
}
