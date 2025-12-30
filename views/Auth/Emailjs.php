<?php
// emailjs.php - handler + test form
// Coloca este archivo en la ruta que tu router cargue para ?page=emailjs
// (o carga directamente controllers/Auth/Emailjs.php que incluya este archivo)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../models/Usuario.php';

// CONFIG: ajusta aquí o usa variables de entorno
$EMAILJS_SERVICE  = getenv('EMAILJS_SERVICE_ID') ?: 'service_fk7d6uk';
$EMAILJS_TEMPLATE = getenv('EMAILJS_TEMPLATE_RESET') ?: 'template_j2lb4z7';
$EMAILJS_PUBLIC   = getenv('EMAILJS_PUBLIC_KEY') ?: 'VZx6kptencm_V-2Hg';

// debug toggle vía ?debug=1
$debug = isset($_REQUEST['debug']) && (string)$_REQUEST['debug'] === '1';

// GET: mostrar formulario de prueba
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
    $_SESSION['csrf_token'] = $csrf;
    ?>
    <!doctype html>
    <html lang="es">
    <head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Prueba EmailJS</title></head>
    <body style="font-family:Arial,Helvetica,sans-serif; padding:20px;">
      <h2>Prueba EmailJS (form)</h2>
      <p>Solo para pruebas: envía un tipo+numero que exista en tu BD.</p>
      <form method="POST" action="?page=emailjs&debug=1">
        <label>Tipo:
          <select name="tipo_id">
            <option value="CC">CC</option>
            <option value="CE">CE</option>
          </select>
        </label><br><br>
        <label>Número: <input name="numero_id" value=""></label><br><br>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <button type="submit">Enviar prueba (debug)</button>
      </form>
      <p>Cuando termines, restaura el form real a la ruta normal.</p>
    </body>
    </html>
    <?php
    exit;
}

// POST: procesar petición
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

// --- recibir y validar input
$tipo = trim((string)($_POST['tipo_id'] ?? ''));
$numero = trim((string)($_POST['numero_id'] ?? ''));
$csrf = $_POST['csrf_token'] ?? null;

if ($tipo === '' || $numero === '') {
    if ($debug) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'missing_tipo_or_numero']);
        exit;
    }
    header('Location: /?page=forgot_password&err=1');
    exit;
}

// Validar CSRF
if (!empty($csrf) && isset($_SESSION['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $csrf)) {
    if ($debug) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'invalid_csrf']);
        exit;
    }
    header('Location: /?page=forgot_password&err=1');
    exit;
}

// --- buscar usuario en BD
function findUserByIdLocal(string $tipo, string $numero) {
    $usuarioModel = new Usuario();
    $u = $usuarioModel->buscarPorDocumento($tipo, $numero);
    if (!$u) return null;

    $email = '';
    if (!empty($u['correo_electronico'])) $email = trim((string)$u['correo_electronico']);
    elseif (!empty($u['email'])) $email = trim((string)$u['email']);
    elseif (!empty($u['correo_institucional'])) $email = trim((string)$u['correo_institucional']);

    $name = trim(((string)($u['nombres'] ?? $u['nombre'] ?? '')) . ' ' . ((string)($u['apellidos'] ?? '')));
    if ($name === '') $name = $email;

    return [
        'id' => (int)($u['id'] ?? 0),
        'email' => $email,
        'name' => $name
    ];
}

$user = findUserByIdLocal($tipo, $numero);

// ======= NUEVO: código aleatorio de 6 dígitos + 5 minutos =======
$code = str_pad(strval(random_int(0, 999999)), 6, "0", STR_PAD_LEFT);
$expires = time() + 300; // 5 minutos (300 segundos)
// ================================================================

$_SESSION['pw_reset'] = [
    'user_id' => $user['id'] ?? null,
    'email'   => $user['email'] ?? null,
    'name'    => $user['name'] ?? null,
    'code'    => $code,
    'expires' => $expires
];

// Reiniciar estado de reenvíos y bloqueos para este flujo
$_SESSION['pw_reset_resend_count'] = 0;
unset($_SESSION['pw_reset_block_until'], $_SESSION['pw_reset_last_chance']);

// preparar template_params
$template_params = [
    'code' => $code,
    'to_email' => $_SESSION['pw_reset']['email'] ?? '',
    'to_name'  => $_SESSION['pw_reset']['name'] ?? ''
];

$logFile = __DIR__ . '/emailjs_debug.log';

if (empty($template_params['to_email'])) {
    $msg = date('c') . " | NO_EMAIL | tipo={$tipo} numero={$numero} | session=" . json_encode($_SESSION['pw_reset']) . PHP_EOL;
    @file_put_contents($logFile, $msg, FILE_APPEND);

    if ($debug) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'sent' => false, 'reason' => 'no_email_for_user', 'session' => $_SESSION['pw_reset']], JSON_PRETTY_PRINT);
        exit;
    }
    header('Location: /?page=forgot_password&sent=1');
    exit;
}

// construir payload
$payload = [
    'service_id'      => $EMAILJS_SERVICE,
    'template_id'     => $EMAILJS_TEMPLATE,
    'user_id'         => $EMAILJS_PUBLIC,
    'template_params' => $template_params
];

// ejecutar cURL
$ch = curl_init('https://api.emailjs.com/api/v1.0/email/send');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$response = curl_exec($ch);
$httpcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

// log
$logLine = date('c') . " | HTTP={$httpcode} | CURL_ERR=" . ($curlErr ?: '-') . " | RESP=" . ($response ?: '-') . " | PAYLOAD=" . json_encode($template_params) . PHP_EOL;
@file_put_contents($logFile, $logLine, FILE_APPEND);
error_log("EmailJS: " . $logLine);

// debug output o redirect normal
if ($debug) {
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => ($httpcode >= 200 && $httpcode < 300),
        'httpcode' => $httpcode,
        'curl_error' => $curlErr,
        'response' => $response,
        'payload' => $payload,
        'session_pw_reset' => $_SESSION['pw_reset']
    ], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    exit;
}

// normal: ir a la vista donde se ingresa el código
header('Location: /?page=verify_code');
exit;
