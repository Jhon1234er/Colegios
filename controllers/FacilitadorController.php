<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Facilitador.php';

class FacilitadorController {
    private $facilitadorModel;

    public function __construct() {
        $this->facilitadorModel = new Facilitador();
    }

    /**
     * Envía correo usando EmailJS REST API.
     * Requiere variables de entorno: EMAILJS_SERVICE_ID, EMAILJS_PUBLIC_KEY
     */
    private function emailjsSend(string $templateId, array $templateParams, ?string &$error = null): bool {
        $serviceId = getenv('EMAILJS_SERVICE_ID') ?: '';
        $publicKey = getenv('EMAILJS_PUBLIC_KEY') ?: '';
        if ($serviceId === '' || $publicKey === '' || $templateId === '') {
            $error = 'EmailJS config incompleta';
            return false;
        }
        $payload = json_encode([
            'service_id'      => $serviceId,
            'template_id'     => $templateId,
            'user_id'         => $publicKey, // EmailJS usa public key en clientes
            'template_params' => $templateParams,
        ], JSON_UNESCAPED_UNICODE);
        $ch = curl_init('https://api.emailjs.com/api/v1.0/email/send');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err) { $error = $err; return false; }
        if ($code < 200 || $code >= 300) { $error = 'HTTP ' . $code . ' ' . (string)$resp; return false; }
        return true;
    }

    private function companyEmail(): string {
        $host = $_SERVER['HTTP_HOST'] ?? 'tudominio.com';
        $domain = preg_replace('/^www\./i', '', (string)$host);
        $env = getenv('COMPANY_EMAIL') ?: getenv('SUPPORT_EMAIL') ?: getenv('APP_SUPPORT_EMAIL');
        if ($env && filter_var($env, FILTER_VALIDATE_EMAIL)) { return $env; }
        return 'soporte@' . $domain;
    }

    /* 📌 Listado de profesores */
    public function index() {
        $profesores = $this->facilitadorModel->obtenerTodos();
        include __DIR__ . '/../views/Facilitador/lista.php';
    }

    /* 📌 Formulario crear facilitador */
    public function crear() {
        include __DIR__ . '/../views/Facilitador/crear.php';
    }

    /* 📌 Guardar facilitador nuevo */
    public function guardar() {
        start_secure_session();
        require_login();
        require_role(1);
        csrf_validate();

        $datos = [
            'nombres'              => trim($_POST['nombres'] ?? ''),
            'apellidos'            => trim($_POST['apellidos'] ?? ''),
            'tipo_documento'       => $_POST['tipo_documento'] ?? '',
            'numero_documento'     => trim($_POST['numero_documento'] ?? ''),
            'correo_electronico'   => trim($_POST['correo_electronico'] ?? ''),
            'telefono'             => trim(($_POST['telefono'] ?? '') !== '' ? ($_POST['telefono']) : ($_POST['celular'] ?? '')),
            'fecha_nacimiento'     => $_POST['fecha_nacimiento'] ?? null,
            'genero'               => $_POST['genero'] ?? null,
            'genero_otro'          => trim($_POST['genero_otro'] ?? ''),
            'eps'                  => trim($_POST['eps'] ?? ''),
            'eps_otro'             => trim($_POST['eps_otro'] ?? ''),
            // contraseña auto-generada si no viene: número de documento
            'password'             => $_POST['password'] ?? ($_POST['numero_documento'] ?? bin2hex(random_bytes(4))),
            'titulo_academico'     => trim($_POST['titulo_academico'] ?? ''),
            'especialidad'         => trim($_POST['especialidad'] ?? ''),
            'rh'                   => trim($_POST['rh'] ?? ''),
            'correo_institucional' => trim($_POST['correo_institucional'] ?? ''),
            'municipio'            => trim($_POST['municipio'] ?? ''),
            'direccion'            => trim($_POST['direccion'] ?? ''),
            'barrio'               => trim($_POST['barrio'] ?? ''),
            'estrato'              => $_POST['estrato'] ?? null,
            'tip_contrato'         => $_POST['tip_contrato'] ?? '',
            'rol_id'               => 2
        ];

        // Normalizar fecha_nacimiento dd/mm/yyyy -> yyyy-mm-dd
        if (!empty($datos['fecha_nacimiento']) && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $datos['fecha_nacimiento'])) {
            $p = explode('/', $datos['fecha_nacimiento']);
            if (count($p) === 3) { $datos['fecha_nacimiento'] = $p[2] . '-' . $p[1] . '-' . $p[0]; }
        }

        try {
            $ids = $this->facilitadorModel->guardar($datos);
            // Forzar cambio de contraseña al primer inicio si existe columna
            try {
                $pdo = Database::conectar();
                $st = $pdo->prepare("UPDATE usuarios SET debe_cambiar_password = 1 WHERE id = ?");
                $st->execute([(int)$ids[0]]);
            } catch (\PDOException $eFlag) { if ($eFlag->getCode() !== '42S22') throw $eFlag; }
            header('Location: /?page=dashboard&success=1');
            exit;
        } catch (Exception $e) {
            echo "❌ Error al guardar facilitador: " . $e->getMessage();
        }
    }

    /* 📌 Registro público de facilitador (sin login) */
    public function guardarPublico() {
        start_secure_session();
        csrf_validate();

        $datos = [
            'nombres'              => trim($_POST['nombres'] ?? ''),
            'apellidos'            => trim($_POST['apellidos'] ?? ''),
            'tipo_documento'       => $_POST['tipo_documento'] ?? '',
            'numero_documento'     => trim($_POST['numero_documento'] ?? ''),
            'correo_electronico'   => trim($_POST['correo_electronico'] ?? ''),
            'telefono'             => trim(($_POST['telefono'] ?? '') !== '' ? ($_POST['telefono']) : ($_POST['celular'] ?? '')),
            'fecha_nacimiento'     => $_POST['fecha_nacimiento'] ?? null,
            'genero'               => $_POST['genero'] ?? null,
            'genero_otro'          => trim($_POST['genero_otro'] ?? ''),
            'eps'                  => trim($_POST['eps'] ?? ''),
            'eps_otro'             => trim($_POST['eps_otro'] ?? ''),
            'password'             => $_POST['password'] ?? ($_POST['numero_documento'] ?? bin2hex(random_bytes(4))),
            'titulo_academico'     => trim($_POST['titulo_academico'] ?? ''),
            'especialidad'         => trim($_POST['especialidad'] ?? ''),
            'rh'                   => trim($_POST['rh'] ?? ''),
            'correo_institucional' => trim($_POST['correo_institucional'] ?? ''),
            'municipio'            => trim($_POST['municipio'] ?? ''),
            'direccion'            => trim($_POST['direccion'] ?? ''),
            'barrio'               => trim($_POST['barrio'] ?? ''),
            'estrato'              => $_POST['estrato'] ?? null,
            'tip_contrato'         => $_POST['tip_contrato'] ?? '',
            'rol_id'               => 2,
            // Estado 0 = pendiente de activación (solo para registro público)
            'estado'               => 0
        ];

        // Normalizar fecha_nacimiento dd/mm/yyyy -> yyyy-mm-dd
        if (!empty($datos['fecha_nacimiento']) && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $datos['fecha_nacimiento'])) {
            $p = explode('/', $datos['fecha_nacimiento']);
            if (count($p) === 3) { $datos['fecha_nacimiento'] = $p[2] . '-' . $p[1] . '-' . $p[0]; }
        }

        // Validaciones mínimas requeridas
        $req = ['nombres','apellidos','tipo_documento','numero_documento','correo_electronico','titulo_academico','especialidad','tip_contrato','telefono'];
        foreach ($req as $k) {
            if (empty($datos[$k])) {
                echo "❌ Faltan datos obligatorios: " . htmlspecialchars($k);
                return;
            }
        }

        try {
            [$usuario_id, $facilitador_id] = $this->facilitadorModel->guardar($datos);
            // Forzar cambio de contraseña al primer inicio si existe columna
            try {
                $pdo = Database::conectar();
                $st = $pdo->prepare("UPDATE usuarios SET debe_cambiar_password = 1 WHERE id = ?");
                $st->execute([(int)$usuario_id]);
            } catch (\PDOException $eFlag) { if ($eFlag->getCode() !== '42S22') throw $eFlag; }

            // Notificar a administradores y asistentes para aprobación
            try {
                $pdo = Database::conectar();
                $stmt = $pdo->query("SELECT id FROM usuarios WHERE rol_id IN (1,4)");
                $destinatarios = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
                if (!empty($destinatarios)) {
                    $titulo = 'Nuevo registro de facilitador';
                    $nombre = trim(($datos['nombres'] ?? '') . ' ' . ($datos['apellidos'] ?? ''));
                    $msg = 'Se registró ' . htmlspecialchars($nombre) . '. Revisa la solicitud: <a href="/?page=facilitadores_pendientes">Revisar</a>';
                    foreach ($destinatarios as $uid) {
                        try {
                            $stN = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, leido, creado_en) VALUES (?, ?, ?, 0, NOW())");
                            $stN->execute([(int)$uid, $titulo, $msg]);
                        } catch (\PDOException $eN1) {
                            if ($eN1->getCode() !== '42S22') { throw $eN1; }
                            try {
                                $stN = $pdo->prepare("INSERT INTO notificaciones (usuario, titulo, mensaje, leido, creado_en) VALUES (?, ?, ?, 0, NOW())");
                                $stN->execute([(int)$uid, $titulo, $msg]);
                            } catch (\PDOException $eN2) {
                                if ($eN2->getCode() !== '42S22') { throw $eN2; }
                                try {
                                    $stN = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, estado_id, creado_en) VALUES (?, ?, ?, 1, NOW())");
                                    $stN->execute([(int)$uid, $titulo, $msg]);
                                } catch (\PDOException $eN3) {
                                    if ($eN3->getCode() !== '42S22') { throw $eN3; }
                                    $stN = $pdo->prepare("INSERT INTO notificaciones (usuario, titulo, mensaje, estado_id, creado_en) VALUES (?, ?, ?, 1, NOW())");
                                    $stN->execute([(int)$uid, $titulo, $msg]);
                                }
                            }
                        }
                    }
                    // Enviar correo EmailJS a cada admin/asistente
                    try {
                        $tplIdAdmin = getenv('EMAILJS_TEMPLATE_NEW_REG') ?: '';
                        if ($tplIdAdmin !== '') {
                            $base = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
                            $panelUrl = $base . '/?page=facilitadores_pendientes';
                            // Obtener correos de admins/asistentes
                            $stE = $pdo->query("SELECT correo_electronico, nombres, apellidos FROM usuarios WHERE rol_id IN (1,4) AND correo_electronico IS NOT NULL AND correo_electronico != ''");
                            $rowsE = $stE->fetchAll(PDO::FETCH_ASSOC) ?: [];
                            foreach ($rowsE as $rowE) {
                                $to = trim((string)$rowE['correo_electronico']);
                                if ($to === '') continue;
                                $params = [
                                    'to_email'  => $to,
                                    'to_name'   => trim(($rowE['nombres'] ?? '') . ' ' . ($rowE['apellidos'] ?? '')),
                                    'subject'   => 'Nuevo registro de facilitador',
                                    'message'   => 'Se registró ' . $nombre . '. Ingresa al panel para aprobar o rechazar.',
                                    'panel_url' => $panelUrl
                                ];
                                $this->emailjsSend($tplIdAdmin, $params, $errMailAdmin);
                            }
                        }
                    } catch (\Throwable $_) { /* noop correo admin */ }
                }
            } catch (\Throwable $_) { /* noop */ }

            header('Location: /?page=login&registro_facilitador=ok');
            exit;
        } catch (Exception $e) {
            echo "❌ Error al registrar facilitador: " . $e->getMessage();
        }
    }

    /* Contar facilitadores (para dashboard) */
    public function contar() {
        $totalFacilitadores = $this->facilitadorModel->contarFacilitadores();
        require 'views/dashboard.php'; 
    }

    public function obtenerFichasPorFacilitador() {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $usuario_id = $_SESSION['usuario']['id'] ?? null;
            if (!$usuario_id) {
                // Sin usuario en sesión: devolver lista vacía (HTTP 200)
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([]);
                exit;
            }

            // Obtener facilitador_id del usuario actual (soportar usuario_id y usuario)
            $pdo = Database::conectar();
            $facilitador = null;
            try {
                $stmtL = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario_id = ?");
                $stmtL->execute([$usuario_id]);
                $facilitador = $stmtL->fetch(PDO::FETCH_ASSOC);
            } catch (\PDOException $eUid) {
                if ($eUid->getCode() !== '42S22') { throw $eUid; }
            }
            if (!$facilitador) {
                $stmtL = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario = ?");
                $stmtL->execute([$usuario_id]);
                $facilitador = $stmtL->fetch(PDO::FETCH_ASSOC);
            }

            if (!$facilitador) {
                // Usuario sin perfil de facilitador asignado: retornar vacío
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([]);
                exit;
            }

            $facilitador_id = (int)$facilitador['id'];

            $fichasPropias = $this->facilitadorModel->obtenerFichasPorFacilitador($facilitador_id);
            foreach ($fichasPropias as &$ficha) { $ficha['tipo'] = 'propia'; }

            $fichasCompartidas = [];
            try {
                $stmtC = $pdo->prepare("\n                    SELECT DISTINCT f.id, f.nombre, f.dias_semana, COALESCE(f.numero, f.id) AS numero\n                    FROM fichas f\n                    INNER JOIN fichas_compartidas fc ON f.id = fc.ficha\n                    WHERE fc.facilitador_compartido = ? AND fc.estado = 'Aceptada'\n                    ORDER BY f.nombre\n                ");
                $stmtC->execute([$facilitador_id]);
                $fichasCompartidas = $stmtC->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\PDOException $eC1) {
                if ($eC1->getCode() !== '42S22') { throw $eC1; }
                try {
                    $stmtC = $pdo->prepare("\n                        SELECT DISTINCT f.id, f.nombre, f.dias_semana, COALESCE(f.numero, f.id) AS numero\n                        FROM fichas f\n                        INNER JOIN fichas_compartidas fc ON f.id = fc.ficha_id\n                        WHERE fc.facilitador_compartido = ? AND fc.estado = 'Aceptada'\n                        ORDER BY f.nombre\n                    ");
                    $stmtC->execute([$facilitador_id]);
                    $fichasCompartidas = $stmtC->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (\PDOException $eC2) {
                    if ($eC2->getCode() !== '42S22') { throw $eC2; }
                    try {
                        $stmtC = $pdo->prepare("\n                            SELECT DISTINCT f.id, f.nombre, f.dias_semana, COALESCE(f.numero, f.id) AS numero\n                            FROM fichas f\n                            INNER JOIN fichas_compartidas fc ON f.id = fc.ficha_id\n                            WHERE fc.profesor_compartido_id = ? AND fc.estado = 'Aceptada'\n                            ORDER BY f.nombre\n                        ");
                        $stmtC->execute([$usuario_id]);
                        $fichasCompartidas = $stmtC->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (\PDOException $eC3) { if ($eC3->getCode() !== '42S22') { throw $eC3; } }
                }
            }

            foreach ($fichasCompartidas as &$fc) { $fc['tipo'] = 'compartida'; }

            $map = [];
            foreach ($fichasPropias as $f) { $map[(int)$f['id']] = $f; }
            foreach ($fichasCompartidas as $f) { $map[(int)$f['id']] = array_merge($map[(int)$f['id']] ?? [], $f); }
            $todasLasFichas = array_values($map);

            // Asegurar que todos los elementos tengan la misma estructura
            foreach ($todasLasFichas as &$ficha) {
                // Asegurar que existan los campos requeridos
                $ficha['id'] = $ficha['id'] ?? 0;
                // numero_ficha debe reflejar el código/número visible de la ficha
                $ficha['numero_ficha'] = $ficha['numero_ficha'] ?? ($ficha['numero'] ?? $ficha['id'] ?? '');
                $ficha['nombre'] = $ficha['nombre'] ?? 'Sin nombre';
                $ficha['tipo'] = $ficha['tipo'] ?? 'desconocido';
                $ficha['dias_semana'] = $ficha['dias_semana'] ?? ['lunes', 'miércoles', 'viernes'];
                $ficha['hora_inicio'] = $ficha['hora_inicio'] ?? '07:00:00';
                $ficha['hora_fin'] = $ficha['hora_fin'] ?? '17:00:00';
            }

            // Enviar respuesta JSON
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($todasLasFichas, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
            
        } catch (Exception $e) {
            // Si es error de esquema/tabla, responder 200 vacío para no romper el dashboard
            $isSchemaErr = ($e instanceof \PDOException) && in_array($e->getCode(), ['42S22','42S02'], true);
            if ($isSchemaErr) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([]);
                exit;
            }
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => true,
                'message' => 'Error al cargar las fichas: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    
    /**
     * Método para manejar la ruta profesorficha
     */
    public function facilitadorficha() { $this->obtenerFichasPorFacilitador(); }

    /* 📌 Listar registros públicos pendientes de aprobación */
    public function pendientes() {
        require_login(); require_role([1,4]);
        $pdo = Database::conectar();
        $rows = [];
        try {
            $sql = "SELECT u.id AS usuario_id, u.nombres, u.apellidos, u.correo_electronico, f.id AS facilitador_id, f.titulo_academico, f.especialidad, f.tipo_contrato, u.creado_en
                    FROM usuarios u
                    INNER JOIN facilitadores f ON f.usuario = u.id
                    WHERE u.rol_id = 2 AND (u.estado = 0 OR u.estado IS NULL)
                    ORDER BY u.creado_en DESC";
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S22' && $e->getCode() !== '42S02') { throw $e; }
            // Fallbacks mínimos si columnas no existen
            try {
                $sql = "SELECT u.id AS usuario_id, u.nombres, u.apellidos, u.correo_electronico, f.id AS facilitador_id, f.titulo_academico, f.especialidad, f.tipo_contrato, u.creado_en
                        FROM usuarios u
                        INNER JOIN facilitadores f ON f.usuario_id = u.id
                        WHERE u.rol_id = 2 AND (u.estado = 0 OR u.estado IS NULL)
                        ORDER BY u.creado_en DESC";
                $stmt = $pdo->query($sql);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\PDOException $e2) { $rows = []; }
        }
        include __DIR__ . '/../views/Facilitador/pendientes.php';
    }

    /* 📌 Aprobar registro público */
    public function aprobarPublico() {
        require_login(); require_role([1,4]);
        $usuario_id = (int)($_POST['usuario_id'] ?? 0);
        if ($usuario_id <= 0) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'usuario_id inválido']); return; }
        header('Content-Type: application/json');
        $pdo = Database::conectar();
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET estado = 1 WHERE id = ?");
            $stmt->execute([$usuario_id]);
            // Notificar al usuario
            $titulo = 'Cuenta activada';
            $msg = 'Tu cuenta de facilitador ha sido activada. Ya puedes iniciar sesión.';
            try {
                $stN = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, leido, creado_en) VALUES (?, ?, ?, 0, NOW())");
                $stN->execute([$usuario_id, $titulo, $msg]);
            } catch (\PDOException $eN1) {
                if ($eN1->getCode() !== '42S22') { throw $eN1; }
                try {
                    $stN = $pdo->prepare("INSERT INTO notificaciones (usuario, titulo, mensaje, leido, creado_en) VALUES (?, ?, ?, 0, NOW())");
                    $stN->execute([$usuario_id, $titulo, $msg]);
                } catch (\PDOException $eN2) {
                    if ($eN2->getCode() !== '42S22') { throw $eN2; }
                    try {
                        $stN = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, estado_id, creado_en) VALUES (?, ?, ?, 1, NOW())");
                        $stN->execute([$usuario_id, $titulo, $msg]);
                    } catch (\PDOException $eN3) {
                        if ($eN3->getCode() !== '42S22') { throw $eN3; }
                        $stN = $pdo->prepare("INSERT INTO notificaciones (usuario, titulo, mensaje, estado_id, creado_en) VALUES (?, ?, ?, 1, NOW())");
                        $stN->execute([$usuario_id, $titulo, $msg]);
                    }
                }
            }
            // Enviar correo con EmailJS (aprobación)
            try {
                $stU = $pdo->prepare("SELECT correo_electronico, nombres, apellidos FROM usuarios WHERE id = ?");
                $stU->execute([$usuario_id]);
                $u = $stU->fetch(PDO::FETCH_ASSOC) ?: [];
                $to = trim((string)($u['correo_electronico'] ?? ''));
                if ($to !== '') {
                    $tplId = getenv('EMAILJS_TEMPLATE_WELCOME')
                        ?: getenv('EMAILJS_TEMPLATE_APPROVED')
                        ?: getenv('EMAILJS_TEMPLATE_REJECTED')
                        ?: getenv('EMAILJS_TEMPLATE_ACCOUNT_STATUS')
                        ?: '';
                    $params = [
                        'to_email'         => $to,
                        'to_name'          => trim(($u['nombres'] ?? '') . ' ' . ($u['apellidos'] ?? '')),
                        'estado_activa'    => true,
                        'estado_bloqueada' => false,
                        'company_email'    => $this->companyEmail(),
                    ];
                    if (!$this->emailjsSend($tplId, $params, $errMail)) { error_log('EmailJS account_status approve error: ' . (string)$errMail); }
                }
            } catch (\Throwable $_) { /* noop */ }

            // Marcar notificación como leída si llega el id o por coincidencia de mensaje
            try {
                $notifId = (int)($_POST['notificacion_id'] ?? 0);
                if ($notifId > 0) {
                    try {
                        $stM = $pdo->prepare("UPDATE notificaciones SET leido = 1 WHERE id = ?");
                        $stM->execute([$notifId]);
                    } catch (\PDOException $eM1) {
                        if ($eM1->getCode() !== '42S22') { throw $eM1; }
                        $stM = $pdo->prepare("UPDATE notificaciones SET estado = 'leida' WHERE id = ?");
                        $stM->execute([$notifId]);
                    }
                } else {
                    // Marcar por texto (Se registró <nombre>) para el usuario actual
                    $adminId = (int)($_SESSION['usuario']['id'] ?? 0);
                    $fullName = trim((string)(($u['nombres'] ?? '') . ' ' . ($u['apellidos'] ?? '')));
                    if ($adminId && $fullName !== '') {
                        try {
                            $stM2 = $pdo->prepare("UPDATE notificaciones SET leido = 1 WHERE usuario_id = ? AND (leido = 0 OR leido IS NULL) AND mensaje LIKE ?");
                            $stM2->execute([$adminId, '%'.$fullName.'%']);
                        } catch (\PDOException $eM2) {
                            if ($eM2->getCode() !== '42S22') { throw $eM2; }
                            try {
                                $stM3 = $pdo->prepare("UPDATE notificaciones SET estado = 'leida' WHERE usuario_id = ? AND (estado = 'no_leida' OR estado IS NULL) AND mensaje LIKE ?");
                                $stM3->execute([$adminId, '%'.$fullName.'%']);
                            } catch (\PDOException $_e) { /* noop */ }
                        }
                    }
                }
            } catch (\Throwable $_) { /* noop */ }
            echo json_encode(['success'=>true]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
    }

    /* 📌 Rechazar registro público */
    public function rechazarPublico() {
        require_login(); require_role([1,4]);
        $usuario_id = (int)($_POST['usuario_id'] ?? 0);
        if ($usuario_id <= 0) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'usuario_id inválido']); return; }
        header('Content-Type: application/json');
        $pdo = Database::conectar();
        try {
            // Marcar estado -1 como rechazado (si no existe, usar 0 y notificar igualmente)
            try {
                $st = $pdo->prepare("UPDATE usuarios SET estado = -1 WHERE id = ?");
                $st->execute([$usuario_id]);
            } catch (\PDOException $eX) {
                if ($eX->getCode() !== '42S22') { throw $eX; }
                $st = $pdo->prepare("UPDATE usuarios SET estado = 0 WHERE id = ?");
                $st->execute([$usuario_id]);
            }
            // Notificar al usuario
            $titulo = 'Registro rechazado';
            $msg = 'Tu registro de facilitador no fue aprobado en esta ocasión.';
            try {
                $stN = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, leido, creado_en) VALUES (?, ?, ?, 0, NOW())");
                $stN->execute([$usuario_id, $titulo, $msg]);
            } catch (\PDOException $eN1) {
                if ($eN1->getCode() !== '42S22') { throw $eN1; }
                try {
                    $stN = $pdo->prepare("INSERT INTO notificaciones (usuario, titulo, mensaje, leido, creado_en) VALUES (?, ?, ?, 0, NOW())");
                    $stN->execute([$usuario_id, $titulo, $msg]);
                } catch (\PDOException $eN2) {
                    if ($eN2->getCode() !== '42S22') { throw $eN2; }
                    try {
                        $stN = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, estado_id, creado_en) VALUES (?, ?, ?, 1, NOW())");
                        $stN->execute([$usuario_id, $titulo, $msg]);
                    } catch (\PDOException $eN3) {
                        if ($eN3->getCode() !== '42S22') { throw $eN3; }
                        $stN = $pdo->prepare("INSERT INTO notificaciones (usuario, titulo, mensaje, estado_id, creado_en) VALUES (?, ?, ?, 1, NOW())");
                        $stN->execute([$usuario_id, $titulo, $msg]);
                    }
                }
            }
            // Enviar correo con EmailJS (rechazo)
            try {
                $stU = $pdo->prepare("SELECT correo_electronico, nombres, apellidos FROM usuarios WHERE id = ?");
                $stU->execute([$usuario_id]);
                $u = $stU->fetch(PDO::FETCH_ASSOC) ?: [];
                $to = trim((string)($u['correo_electronico'] ?? ''));
                if ($to !== '') {
                    $tplId = getenv('EMAILJS_TEMPLATE_WELCOME')
                        ?: getenv('EMAILJS_TEMPLATE_REJECTED')
                        ?: getenv('EMAILJS_TEMPLATE_APPROVED')
                        ?: getenv('EMAILJS_TEMPLATE_ACCOUNT_STATUS')
                        ?: '';
                    $params = [
                        'to_email'         => $to,
                        'to_name'          => trim(($u['nombres'] ?? '') . ' ' . ($u['apellidos'] ?? '')),
                        'estado_activa'    => false,
                        'estado_bloqueada' => true,
                        'company_email'    => $this->companyEmail(),
                    ];
                    if (!$this->emailjsSend($tplId, $params, $errMail)) { error_log('EmailJS account_status reject error: ' . (string)$errMail); }
                }
            } catch (\Throwable $_) { /* noop */ }
            echo json_encode(['success'=>true]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
    }
}
