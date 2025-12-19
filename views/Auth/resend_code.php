<?php
// resend_code.php: reenvía un nuevo código usando la sesión pw_reset
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../models/Usuario.php';
require_once __DIR__ . '/../../config/db.php';

$pw = $_SESSION['pw_reset'] ?? null;
if (!$pw || empty($pw['user_id']) || empty($pw['email'])) {
    header('Location: /?page=forgot_password');
    exit;
}

$userId = (int)$pw['user_id'];
$email  = (string)$pw['email'];
$name   = (string)($pw['name'] ?? '');

// Bloqueo temporal si se excede el máximo de reenvíos
$MAX_RESENDS   = 4;           // máximo de reenvíos permitidos
$BLOCK_SECONDS = 300;         // 5 minutos de bloqueo

$currentResends = (int)($_SESSION['pw_reset_resend_count'] ?? 0);
$blockUntil     = isset($_SESSION['pw_reset_block_until']) ? (int)$_SESSION['pw_reset_block_until'] : 0;

if ($blockUntil > time()) {
    header('Location: /?page=verify_code&blocked=1');
    exit;
}

if ($currentResends >= $MAX_RESENDS) {
    // Activar bloqueo y notificar a administradores (mejor esfuerzo)
    $_SESSION['pw_reset_block_until'] = time() + $BLOCK_SECONDS;

    try {
        $pdo = Database::conectar();
        $stmt = $pdo->query("SELECT id, correo_electronico, nombres, apellidos FROM usuarios WHERE rol_id = 1");
        $admins = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $titulo = 'Intentos de reenvío de código excedidos';
        $bloqueoMin = (int) round($BLOCK_SECONDS / 60);
        $nombreMostrar = $name !== '' ? $name : ('ID ' . $userId);
        $msg = 'El usuario ' . $nombreMostrar . ' ha superado el límite de reenvíos de código de recuperación. '
             . 'El envío de códigos quedó bloqueado durante aproximadamente ' . $bloqueoMin . ' minutos.';
        foreach ($admins as $ad) {
            try {
                $stN = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, leido, creado_en) VALUES (?, ?, ?, 0, NOW())");
                $stN->execute([(int)$ad['id'], $titulo, $msg]);
            } catch (\PDOException $eN) {
                // Ignorar errores de esquema para no romper el flujo
            }
        }
    } catch (\Throwable $e) {
        error_log('Error al notificar admins por exceso de reenvíos: ' . $e->getMessage());
    }

    header('Location: /?page=verify_code&blocked=1');
    exit;
}

// Marcar si este será el último intento permitido
if ($currentResends + 1 === $MAX_RESENDS) {
    $_SESSION['pw_reset_last_chance'] = 1;
}

$_SESSION['pw_reset_resend_count'] = $currentResends + 1;

$code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = time() + 300; // 5 minutos

$_SESSION['pw_reset']['code']    = $code;
$_SESSION['pw_reset']['expires'] = $expires;

$EMAILJS_SERVICE  = getenv('EMAILJS_SERVICE_ID') ?: 'service_fk7d6uk';
$EMAILJS_TEMPLATE = getenv('EMAILJS_TEMPLATE_RESET') ?: 'template_j2lb4z7';
$EMAILJS_PUBLIC   = getenv('EMAILJS_PUBLIC_KEY') ?: 'VZx6kptencm_V-2Hg';

$template_params = [
    'code'     => $code,
    'to_email' => $email,
    'to_name'  => $name,
];

$payload = [
    'service_id'      => $EMAILJS_SERVICE,
    'template_id'     => $EMAILJS_TEMPLATE,
    'user_id'         => $EMAILJS_PUBLIC,
    'template_params' => $template_params,
];

$ch = curl_init('https://api.emailjs.com/api/v1.0/email/send');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$response = curl_exec($ch);
$httpcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($httpcode < 200 || $httpcode >= 300 || $curlErr) {
    error_log('EmailJS resend_code error: HTTP=' . $httpcode . ' CURL=' . ($curlErr ?: '-') . ' RESP=' . ($response ?: '-'));
}

header('Location: /?page=verify_code');
exit;
