<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../config/db.php';

class AuthController {

    private function emailjsSend(string $templateId, array $templateParams, ?string &$error = null, ?int &$httpCode = null, ?string &$responseBody = null): bool {
        // Intentar recoger de variables de entorno, fallback a valores por defecto
        $serviceId = getenv('EMAILJS_SERVICE_ID') ?: 'service_fk7d6uk';
        $publicKey = getenv('EMAILJS_PUBLIC_KEY') ?: 'nGBBTWGl1H3n6cggD';

        if (empty($serviceId) || empty($publicKey) || empty($templateId)) {
            $error = 'EmailJS config incompleta';
            return false;
        }

        $payloadArr = [
            'service_id'      => $serviceId,
            'template_id'     => $templateId,
            'public_key'      => $publicKey,
            'template_params' => $templateParams,
        ];

        $payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://api.emailjs.com/api/v1.0/email/send');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $httpCode = $code;
        $responseBody = $resp;

        if ($err) {
            $error = 'curl_error: ' . $err;
            return false;
        }

        if ($code < 200 || $code >= 300) {
            $error = 'HTTP ' . $code . ' - ' . ($resp ?? 'empty response');
            return false;
        }

        // éxito
        return true;
    }

    private function ensureResetsTable($pdo) {
        $sql = "CREATE TABLE IF NOT EXISTS password_resets (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            usuario_id BIGINT NOT NULL,
            token VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_user (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
        $pdo->exec($sql);
    }

    private function maskEmail(string $email): string {
        $email = trim($email);
        if ($email === '' || !str_contains($email, '@')) {
            return $email;
        }
        [$local, $domain] = explode('@', $email, 2);
        $localLen = strlen($local);
        if ($localLen <= 1) {
            return $local . '@' . $domain;
        }
        $visible = substr($local, 0, min(3, $localLen));
        $masked = str_repeat('*', max(1, $localLen - strlen($visible)));
        return $visible . $masked . '@' . $domain;
    }

    // 👉 Muestra formulario de login y genera token CSRF
    public function loginForm() {
        start_secure_session();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        // Flash error (si existe)
        $error = $_SESSION['login_error'] ?? null;
        if (isset($_SESSION['login_error'])) { unset($_SESSION['login_error']); }
        include __DIR__ . '/../views/login.php';
    }

    // 👉 Procesa login
    public static function login($correo, $password) {
        start_secure_session();
        csrf_validate(); // 🔒 valida token enviado

        // Throttling básico por sesión
        $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
        $_SESSION['login_blocked_until'] = $_SESSION['login_blocked_until'] ?? 0;

        if (time() < $_SESSION['login_blocked_until']) {
            $remaining = max(0, (int)($_SESSION['login_blocked_until'] - time()));
            $mins = intdiv($remaining, 60);
            $secs = $remaining % 60;
            $texto = $mins > 0 ? ("$mins min " . ($secs > 0 ? "$secs s" : '')) : ("$secs s");
            $_SESSION['login_error'] = 'Demasiados intentos fallidos. Inténtalo de nuevo en ' . $texto . '.';
            return false;
        }

        $usuarioModel = new Usuario();
        $usuario = $usuarioModel->buscarPorCorreo($correo);

        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            // Bloquear acceso si la cuenta no está activa (estado != 1)
            if (array_key_exists('estado', $usuario)) {
                $est = (int)$usuario['estado'];
                if ($est !== 1) {
                    $_SESSION['login_error'] = ($est === 0)
                        ? 'Tu cuenta está pendiente de activación por el administrador.'
                        : 'Tu cuenta no está activa. Contacta al administrador.';
                    return false;
                }
            }
            session_regenerate_id(true);

            $pdo = Database::conectar();

            $_SESSION['usuario'] = [
                'id'        => (int)$usuario['id'],
                'rol_id'    => (int)$usuario['rol_id'],
                'nombres'   => $usuario['nombres'],
                'apellidos' => $usuario['apellidos'],
                'genero'    => $usuario['genero'] ?? null,
            ];

            // Si es profesor, anexar IDs (facilitadores)
            if ((int)$usuario['rol_id'] === 2) {
                $stmt = $pdo->prepare("SELECT id, tipo_contrato FROM facilitadores WHERE usuario = ?");
                $stmt->execute([$usuario['id']]);
                $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($profesor) {
                    $_SESSION['usuario']['profesor_id']  = (int)$profesor['id'];
                    $_SESSION['usuario']['tip_contrato'] = $profesor['tipo_contrato'] ?? null;
                }
            }

            // Forzar cambio de contraseña en primer inicio si aplica
            $_SESSION['must_change_password'] = 0;
            $needChange = false;
            // 1) Chequear si existe columna debe_cambiar_password
            try {
                $stFlag = $pdo->prepare("SELECT debe_cambiar_password FROM usuarios WHERE id = ?");
                $stFlag->execute([$usuario['id']]);
                $flag = $stFlag->fetchColumn();
                if ($flag !== false) {
                    $flagStr = strtolower(trim((string)$flag));
                    // Solo valores explícitos activan el flujo
                    if (in_array($flagStr, ['1','true','si','yes'], true)) {
                        $needChange = true;
                    }
                }
            } catch (\PDOException $eFlag) {
                if ($eFlag->getCode() !== '42S22') { throw $eFlag; }
                // 2) Si la columna NO existe, aplicar heurística de seguridad
                $pwd = (string)$password;
                $doc = (string)($usuario['numero_documento'] ?? '');
                $isWeak = !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{9,}$/', $pwd);
                if ($doc !== '' && hash_equals($pwd, $doc)) { $needChange = true; }
                elseif ($isWeak) { $needChange = true; }
            }
            if ($needChange) { $_SESSION['must_change_password'] = 1; }

            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_blocked_until'] = 0;
            return true;
        }

        // Fallo: sumar intento
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_blocked_until'] = time() + 60; // 1 min
        }
        // Mensaje de error genérico
        $_SESSION['login_error'] = 'Correo o contraseña incorrectos';
        return false;
    }

    // 👉 Formulario para cambio de contraseña inicial (forzado)
    public function cambiarPasswordInicialForm() {
        start_secure_session();
        require_login();
        $must = (int)($_SESSION['must_change_password'] ?? 0);
        if (!$must) { header('Location: ?'); exit; }
        $error = $_SESSION['pwd_init_error'] ?? null; unset($_SESSION['pwd_init_error']);
        include __DIR__ . '/../views/Auth/cambiar_password_inicial.php';
    }

    // 👉 Guardar nuevo password cumpliendo política
    public function cambiarPasswordInicialGuardar() {
        start_secure_session();
        require_login();
        csrf_validate();
        $uid = (int)($_SESSION['usuario']['id'] ?? 0);
        if ($uid <= 0) { header('Location: ?page=login'); exit; }

        $pwd = (string)($_POST['password_nueva'] ?? '');
        $rep = (string)($_POST['password_confirmacion'] ?? '');
        $policy = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{9,}$/';
        if ($pwd === '' || $rep === '' || $pwd !== $rep) {
            $_SESSION['pwd_init_error'] = 'Las contraseñas no coinciden.';
            header('Location: ?page=cambiar_password_inicial');
            exit;
        }
        if (!preg_match($policy, $pwd)) {
            $_SESSION['pwd_init_error'] = 'Debe tener mínimo 9 caracteres, incluir mayúscula, minúscula, número y caracter especial.';
            header('Location: ?page=cambiar_password_inicial');
            exit;
        }

        $usuarioModel = new Usuario();
        $ok = $usuarioModel->actualizarPassword($uid, password_hash($pwd, PASSWORD_DEFAULT));
        if ($ok) {
            // Intentar desmarcar flag si existe
            try {
                $pdo = Database::conectar();
                $st = $pdo->prepare("UPDATE usuarios SET debe_cambiar_password = 0 WHERE id = ?");
                $st->execute([$uid]);
            } catch (\PDOException $e) { /* esquema legacy sin columna */ }
            $_SESSION['must_change_password'] = 0;
            // Redirigir al dashboard según rol
            $rol = (int)($_SESSION['usuario']['rol_id'] ?? 0);
            if ($rol === 1) header('Location: ?page=dashboard');
            elseif ($rol === 2) header('Location: ?page=dashboard_profesor');
            elseif ($rol === 4) header('Location: ?page=asistente');
            else header('Location: ?');
            exit;
        } else {
            $_SESSION['pwd_init_error'] = 'No se pudo actualizar la contraseña. Intenta de nuevo.';
            header('Location: ?page=cambiar_password_inicial');
            exit;
        }
    }

    // 👉 Registro
    public static function registrar($data) {
        start_secure_session();
        csrf_validate();

        // Compatibilidad: si viene 'celular' desde el formulario, mapear a 'telefono'
        if ((empty($data['telefono']) || $data['telefono'] === null) && !empty($data['celular'])) {
            $data['telefono'] = $data['celular'];
        }

        if (empty($data['tipo_documento']) || empty($data['genero']) || empty($data['fecha_nacimiento'])) {
            return "Por favor selecciona un tipo de documento, género y fecha de nacimiento válidos.";
        }

        // Convertir fecha de nacimiento de dd/mm/yyyy a yyyy-mm-dd si es necesario
        if (!empty($data['fecha_nacimiento']) && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data['fecha_nacimiento'])) {
            $partes = explode('/', $data['fecha_nacimiento']);
            if (count($partes) === 3) {
                $data['fecha_nacimiento'] = $partes[2] . '-' . $partes[1] . '-' . $partes[0];
            }
        }

        $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        unset($data['password']);

        $usuarioModel = new Usuario();
        $pdo = Database::conectar();

        try {
            $pdo->beginTransaction();

            // Usar tipo_documento directo en usuarios (sin catálogo)
            $data['tipo_documento'] = strtoupper(trim((string)$data['tipo_documento']));

            // Usar genero directo (M/F/Otro) en usuarios
            $data['genero'] = strtoupper(trim((string)$data['genero']));

            $usuario_id = $usuarioModel->registrar($data, $pdo);
            if (!$usuario_id) {
                throw new Exception("Error al insertar el usuario.");
            }

            if ((int)$data['rol_id'] === 1) {
                // Esquema administradores: (usuario_id, fecha_designacion)
                $stmtAdmin = $pdo->prepare("INSERT INTO administradores (usuario_id, fecha_designacion) VALUES (?, CURDATE())");
                $stmtAdmin->execute([$usuario_id]);
            }

            // Inserción en asistentes si corresponde (rol 4) y se envió 'area'
            if ((int)$data['rol_id'] === 4) {
                $area = trim((string)($data['area'] ?? ''));
                if ($area !== '') {
                    $stmtAsist = $pdo->prepare("INSERT INTO asistentes (usuario_id, area) VALUES (?, ?)");
                    $stmtAsist->execute([(int)$usuario_id, $area]);
                }
            }

            $pdo->commit();
            // Redirección con indicador de éxito para activar modal
            $rolNuevo = (int)($data['rol_id'] ?? 0);
            if ($rolNuevo === 1) {
                header('Location: /?page=dashboard&success=1');
            } else {
                header('Location: /?page=login&registro=ok');
            }
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return "Error de registro: " . $e->getMessage();
        }
    }

    public function index() {
        $usuarioModel = new Usuario();
        $totalUsuarios = $usuarioModel->contarUsuarios();
        require 'views/dashboard.php';
    }

    public function forgotForm() {
        start_secure_session();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        include __DIR__ . '/../views/Auth/forgot_password.php';
    }

    public function forgotSend() {
        start_secure_session();
        csrf_validate();
        $usuarioModel = new Usuario();
        $pdo = Database::conectar();
        $this->ensureResetsTable($pdo);

        // Nuevo flujo: por tipo y número de documento -> envía código de 6 dígitos
        $tipoId   = trim($_POST['tipo_id'] ?? '');
        $numeroId = trim($_POST['numero_id'] ?? '');

        if ($tipoId !== '' && $numeroId !== '') {
            // Guardar datos para verificación posterior
            $_SESSION['reset_data'] = [
                'tipo_id'   => $tipoId,
                'numero_id' => $numeroId,
            ];

            $u = $usuarioModel->buscarPorDocumento($tipoId, $numeroId);
            if ($u) {
                // Limpiar códigos anteriores
                try { $stDel = $pdo->prepare('DELETE FROM password_resets WHERE usuario_id = ?'); $stDel->execute([(int)$u['id']]); } catch (\Throwable $_) {}

                $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expMinutes = 10;
                $exp = date('Y-m-d H:i:s', time() + $expMinutes * 60);
                $stmt = $pdo->prepare('INSERT INTO password_resets (usuario_id, token, expires_at) VALUES (?, ?, ?)');
                $stmt->execute([(int)$u['id'], $code, $exp]);

                // Enviar con plantilla RESET: to_name, to_email, code, expiration_minutes
                $tplId = getenv('EMAILJS_TEMPLATE_RESET') ?: 'template_j2lb4z7';
                if ($tplId !== '') {
                    // Tomar correo principal; si está vacío, usar correo institucional
                    $emailTo = (string)($u['correo_electronico'] ?? '');
                    if ($emailTo === '' && !empty($u['correo_institucional'])) {
                        $emailTo = (string)$u['correo_institucional'];
                    }

                    $params = [
                        'to_name'            => trim(($u['nombres'] ?? '') . ' ' . ($u['apellidos'] ?? '')),
                        'to_email'           => $emailTo,
                        'code'               => $code,
                        'expiration_minutes' => (string)$expMinutes,
                    ];
                    if ($emailTo !== '') {
                        $_SESSION['reset_email_mask'] = $this->maskEmail($emailTo);
                    }
                    $err = null;
                    $httpCode = null;
                    $respBody = null;
                    $ok = $this->emailjsSend($tplId, $params, $err, $httpCode, $respBody);
                    if (!$ok) {
                        error_log('EmailJS forgotSend (documento) error: ' . ($err ?? 'unknown') . ' | http=' . ($httpCode ?? '-') . ' | resp=' . ($respBody ?? '-'));
                        @file_put_contents(__DIR__ . '/../views/Auth/emailjs_error.log', date('c') . " | ERR=" . ($err ?? '-') . " | HTTP=" . ($httpCode ?? '-') . " | RESP=" . ($respBody ?? '-') . PHP_EOL, FILE_APPEND);
                    } else {
                        error_log('EmailJS forgotSend (documento) ok: http=' . ($httpCode ?? '-') . ' resp=' . ($respBody ?? '-'));
                    }
                }
            }
            // Siempre redirigir a la vista de verificación (no revelar existencia)
            header('Location: /?page=verify_code');
            exit;
        }

        // Compatibilidad: flujo anterior por correo electrónico directo
        $email = trim($_POST['correo'] ?? '');
        if ($email === '') { header('Location: /?page=forgot_password&sent=1'); exit; }
        $u = $usuarioModel->buscarPorCorreo($email);
        if (!$u) { header('Location: /?page=forgot_password&sent=1'); exit; }
        $token = bin2hex(random_bytes(16));
        $exp   = date('Y-m-d H:i:s', time() + 3600);
        $stmt  = $pdo->prepare('INSERT INTO password_resets (usuario_id, token, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([(int)$u['id'], $token, $exp]);
        $tplId = getenv('EMAILJS_TEMPLATE_RESET') ?: 'template_j2lb4z7';
        if ($tplId !== '') {
            $params = [
                'to_name'            => trim(($u['nombres'] ?? '') . ' ' . ($u['apellidos'] ?? '')),
                'to_email'           => $email,
                'code'               => $token,
                'expiration_minutes' => '60',
            ];
            if ($email !== '') {
                $_SESSION['reset_email_mask'] = $this->maskEmail($email);
            }
            $err = null;
            $httpCode = null;
            $respBody = null;
            $ok = $this->emailjsSend($tplId, $params, $err, $httpCode, $respBody);
            if (!$ok) {
                error_log('EmailJS forgotSend (correo) error: ' . ($err ?? 'unknown') . ' | http=' . ($httpCode ?? '-') . ' | resp=' . ($respBody ?? '-'));
                @file_put_contents(__DIR__ . '/../views/Auth/emailjs_error.log', date('c') . " | ERR=" . ($err ?? '-') . " | HTTP=" . ($httpCode ?? '-') . " | RESP=" . ($respBody ?? '-') . PHP_EOL, FILE_APPEND);
            } else {
                error_log('EmailJS forgotSend (correo) ok: http=' . ($httpCode ?? '-') . ' resp=' . ($respBody ?? '-'));
            }
        }
        header('Location: /?page=reset_password&token=' . urlencode($token));
        exit;
    }

    public function verifyCodeForm() {
        start_secure_session();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        include __DIR__ . '/../views/Auth/verify_code.php';
    }

    public function verifyCode() {
        start_secure_session();
        csrf_validate();
        $inputs = [];
        for ($i = 1; $i <= 6; $i++) {
            $inputs[] = (string)($_POST['code' . $i] ?? '');
        }
        $code = implode('', $inputs);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            header('Location: /?page=verify_code&error=1');
            exit;
        }

        $resetData = $_SESSION['reset_data'] ?? null;
        if (!$resetData) { header('Location: /?page=forgot_password'); exit; }

        $usuarioModel = new Usuario();
        $u = $usuarioModel->buscarPorDocumento($resetData['tipo_id'] ?? '', $resetData['numero_id'] ?? '');
        if (!$u) { header('Location: /?page=verify_code&error=1'); exit; }

        $pdo = Database::conectar();
        $this->ensureResetsTable($pdo);
        $stmt = $pdo->prepare('SELECT token, expires_at FROM password_resets WHERE usuario_id = ? AND token = ? ORDER BY creado_en DESC LIMIT 1');
        $stmt->execute([(int)$u['id'], $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || strtotime($row['expires_at']) < time()) {
            header('Location: /?page=verify_code&error=1');
            exit;
        }

        // Generar token largo y actualizar registro para continuar al formulario de nueva contraseña
        $longToken = bin2hex(random_bytes(16));
        $newExp = date('Y-m-d H:i:s', time() + 1800); // 30 min
        $stUp = $pdo->prepare('UPDATE password_resets SET token = ?, expires_at = ? WHERE usuario_id = ? AND token = ?');
        $stUp->execute([$longToken, $newExp, (int)$u['id'], $code]);

        header('Location: /?page=reset_password&token=' . urlencode($longToken));
        exit;
    }

    public function resetForm() {
        start_secure_session();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $token = $_GET['token'] ?? '';
        include __DIR__ . '/../views/Auth/reset_password.php';
    }

    public function resetSubmit() {
        start_secure_session();
        csrf_validate();
        $token = trim($_POST['token'] ?? '');
        $pass  = (string)($_POST['password'] ?? '');
        if ($token === '' || strlen($pass) < 6) { header('Location: /?page=reset_password&token=' . urlencode($token) . '&err=1'); exit; }
        $pdo = Database::conectar();
        $this->ensureResetsTable($pdo);
        $stmt = $pdo->prepare('SELECT usuario_id, expires_at FROM password_resets WHERE token = ? LIMIT 1');
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || strtotime($row['expires_at']) < time()) {
            header('Location: /?page=reset_password&token=' . urlencode($token) . '&exp=1');
            exit;
        }
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $pdo->beginTransaction();
        $stU = $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?');
        $stU->execute([$hash, (int)$row['usuario_id']]);
        $stD = $pdo->prepare('DELETE FROM password_resets WHERE token = ?');
        $stD->execute([$token]);
        $pdo->commit();
        header('Location: /?page=login&reset=ok');
        exit;
    }
}
