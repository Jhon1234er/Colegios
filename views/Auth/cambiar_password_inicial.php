<?php
// cambiar_password_inicial_guardar.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/db.php';

// CSRF
$csrf = $_POST['csrf_token'] ?? '';
if (!empty($csrf) && isset($_SESSION['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $csrf)) {
    $_SESSION['pwd_init_error'] = 'Token CSRF inválido.';
    header('Location: /?page=reset_password_code'); exit;
}

// Verificar autorización para cambiar password
if (!isset($_SESSION['pwd_change_allowed'])) {
    $_SESSION['pwd_init_error'] = 'No autorizado para cambiar contraseña.';
    header('Location: /?page=reset_password_code'); exit;
}

// Obtener y validar contraseñas del formulario
$p1 = $_POST['password_nueva'] ?? '';
$p2 = $_POST['password_confirmacion'] ?? '';
if ($p1 !== $p2) {
    $_SESSION['pwd_init_error'] = 'Las contraseñas no coinciden.';
    header('Location: /?page=reset_password_code'); exit;
}

// Política de contraseña: al menos 1 mayúscula, 1 minúscula, 1 número, 1 especial, 9+ caracteres
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{9,}$/', $p1)) {
    $_SESSION['pwd_init_error'] = 'La contraseña no cumple la política requerida.';
    header('Location: /?page=reset_password_code'); exit;
}

// Obtener user_id autorizado
$user_id = $_SESSION['pwd_change_allowed']['user_id'] ?? null;
$email = $_SESSION['pwd_change_allowed']['email'] ?? null;

// Si no existe user_id, no actualizar DB (opcional: si deseas enviar un correo aun así, manejalo)
if ($user_id === null) {
    // No podemos actualizar una cuenta desconocida; por seguridad, borramos la sesión y mostramos error
    unset($_SESSION['pwd_change_allowed']);
    $_SESSION['pwd_init_error'] = 'No se pudo identificar la cuenta.';
    header('Location: /?page=reset_password_code'); exit;
}

// Hash de la contraseña — usa password_hash
$hash = password_hash($p1, PASSWORD_DEFAULT);

// Función para actualizar la contraseña en la DB — implementación real usando Database
function updateUserPassword($user_id, $hash) {
    try {
        $pdo = Database::conectar();
        $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?');
        return $stmt->execute([$hash, (int)$user_id]);
    } catch (PDOException $e) {
        error_log('Error al actualizar contraseña inicial: ' . $e->getMessage());
        return false;
    }
}

$ok = updateUserPassword($user_id, $hash);

if ($ok) {
    // limpiar marcadores de sesión
    unset($_SESSION['pwd_change_allowed']);
    // Redirigir al login o al panel
    header('Location: /?page=login&reset=1'); exit;
} else {
    $_SESSION['pwd_init_error'] = 'Error al guardar la contraseña. Inténtalo más tarde.';
    header('Location: /?page=cambiar_password_inicial'); exit;
}
