<?php
require_once __DIR__ . '/../models/Ficha.php';
require_once __DIR__ . '/../models/Aprendiz.php';
require_once __DIR__ . '/../config/db.php';

class FichaController {
    private $fichaModel;

    public function __construct() {
        $this->fichaModel = new Ficha();
    }

    // 📌 Listar fichas solo del profesor logueado
    public function index() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $usuario_id = $_SESSION['usuario']['id'] ?? null;

        if (!$usuario_id) {
            die("⚠️ Error: No hay sesión de usuario activa.");
        }

        // Buscar facilitador_id (soporta ambos esquemas)
        $pdo = Database::conectar();
        $profesor = null;
        try {
            $stmt = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario_id = ?");
            $stmt->execute([$usuario_id]);
            $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S22') { throw $e; }
        }
        if (!$profesor) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario = ?");
                $stmt->execute([$usuario_id]);
                $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                if ($e->getCode() !== '42S22') { throw $e; }
            }
        }

        if (!$profesor) {
            die("⚠️ Error: No existe un facilitador vinculado a este usuario.");
        }

        $profesor_id = $profesor['id'];

        // 🔹 Obtener solo las fichas de este profesor
        $fichas = $this->fichaModel->obtenerTodasPorProfesor($profesor_id);

        require __DIR__ . '/../views/Ficha/crear_ficha.php';
    }

    // 📌 Mostrar formulario para crear ficha
    public function crear() {
        require __DIR__ . '/../views/Ficha/crear_ficha.php';
    }

    // 📌 Guardar nueva ficha
    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // nombre proviene de la denominación del curso seleccionado (campo oculto)
            $nombre = trim($_POST['nombre'] ?? '');
            $numero = trim($_POST['numero'] ?? ''); // código del curso autocompletado
            $cupo_total = (int) ($_POST['cupo_total'] ?? 0);
            $dias_checked = $_POST['dias_semana'] ?? [];
            $jornadas = $_POST['jornadas'] ?? [];
            // Construir estructura dia=>jornada para guardar en JSON
            $dias_semana = [];
            foreach ($dias_checked as $dia) {
                $dias_semana[$dia] = $jornadas[$dia] ?? null;
            }
            $jornada = null; // jornada general ya no se usa, se maneja por día

            if (!empty($nombre) && !empty($numero) && $cupo_total > 0 && !empty($dias_semana)) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                $usuario_id = $_SESSION['usuario']['id'] ?? null;

                if ($usuario_id) {
                    $pdo = Database::conectar();
                    $profesor = null;
                    try {
                        $stmt = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario_id = ?");
                        $stmt->execute([$usuario_id]);
                        $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (\PDOException $e) {
                        if ($e->getCode() !== '42S22') { throw $e; }
                    }
                    if (!$profesor) {
                        try {
                            $stmt = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario = ?");
                            $stmt->execute([$usuario_id]);
                            $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
                        } catch (\PDOException $e) {
                            if ($e->getCode() !== '42S22') { throw $e; }
                        }
                    }

                    if ($profesor) {
                        $profesor_id = $profesor['id'];

                        $ficha_id = $this->fichaModel->guardar($nombre, $numero, $cupo_total, $profesor_id, $dias_semana, $jornada);

                        if ($ficha_id) {
                            header("Location: ?page=fichas&action=index");
                            exit;
                        } else {
                            die("⚠️ Error: no se pudo guardar la ficha en la BD.");
                        }
                    } else {
                        die("⚠️ Error: No existe un facilitador vinculado a este usuario.");
                    }
                } else {
                    die("⚠️ Error: No hay sesión de usuario activa.");
                }
            } else {
                die("⚠️ Error: Debes completar todos los campos.");
            }
        }

        header("Location: ?page=fichas&action=crear");
        exit;
    }

    // 📌 Ver ficha por ID con aprendices
    public function ver() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            header("Location: /?page=fichas&action=index");
            exit;
        }

        $ficha = $this->fichaModel->obtenerPorId($id);

        if (!$ficha) {
            die("❌ Ficha no encontrada.");
        }

        // 🔹 Traer aprendices de esta ficha
        $aprendizModel = new Aprendiz();
        $estudiantes = $aprendizModel->obtenerTodos($ficha['id']);

        require __DIR__ . '/../views/Ficha/ver.php';
    }

    // 📌 Suspender (eliminar lógicamente) ficha
    public function eliminar() {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $this->fichaModel->actualizarEstado($id, 'cerrada');
        }
        header("Location: /?page=fichas&action=index");
        exit;
    }

    // 📌 Compartir ficha con otros profesores
    public function compartirFicha() {
        header('Content-Type: application/json');
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $fichaId = $input['ficha_id'] ?? null;
        $profesores = $input['profesores'] ?? [];
        // Variables alternas para compatibilidad de payloads (evitar Notices y facilitar mapeos)
        $altFichaId      = $input['ficha'] ?? $input['ficha_id'] ?? null;
        $altReceptorFac  = $input['facilitador_compartido'] ?? $input['profesor_compartido_id'] ?? null;
        $altLiderFac     = $input['facilitador_lider'] ?? $input['profesor_lider_id'] ?? null;
        
        if (!$fichaId || empty($profesores)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            return;
        }

        $usuarioActual = $_SESSION['usuario']['id'] ?? null;
        if (!$usuarioActual) {
            echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
            return;
        }

        try {
            $pdo = Database::conectar();
            $pdo->beginTransaction();
            $actualizado = false; // si cambiamos el estado en esta llamada
            $debeNotificar = false; // solo notificamos al líder si hubo cambio ahora

            // Normalizar IDs alternos si vienen incompletos en payload alterno
            if ($altFichaId && (!$altReceptorFac || $altReceptorFac === '0') && !empty($input['receptor_usuario_id'])) {
                try {
                    // Mapear usuario -> facilitador
                    try {
                        $stMap = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario_id = ? LIMIT 1");
                        $stMap->execute([(int)$input['receptor_usuario_id']]);
                        $altReceptorFac = (int)($stMap->fetchColumn() ?: 0);
                    } catch (\PDOException $eM1) {
                        if ($eM1->getCode() !== '42S22') { throw $eM1; }
                        $stMap = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario = ? LIMIT 1");
                        $stMap->execute([(int)$input['receptor_usuario_id']]);
                        $altReceptorFac = (int)($stMap->fetchColumn() ?: 0);
                    }
                } catch (\Throwable $_) { /* noop */ }
            }

            if ($altFichaId && (!$altLiderFac || $altLiderFac === '0') && $altReceptorFac) {
                // Intentar derivar el líder desde la propia tabla de compartidos
                try {
                    $stDer = $pdo->prepare("SELECT facilitador_lider FROM fichas_compartidas WHERE ficha = ? AND facilitador_compartido = ? AND (estado = 'Pendiente' OR estado IS NULL) ORDER BY creado_en DESC LIMIT 1");
                    $stDer->execute([$altFichaId, $altReceptorFac]);
                    $altLiderFac = (int)($stDer->fetchColumn() ?: 0);
                } catch (\PDOException $eD1) {
                    if ($eD1->getCode() !== '42S22') { throw $eD1; }
                    try {
                        $stDer = $pdo->prepare("SELECT facilitador_lider FROM fichas_compartidas WHERE ficha_id = ? AND facilitador_compartido = ? AND (estado = 'Pendiente' OR estado IS NULL) ORDER BY creado_en DESC LIMIT 1");
                        $stDer->execute([$altFichaId, $altReceptorFac]);
                        $altLiderFac = (int)($stDer->fetchColumn() ?: 0);
                    } catch (\PDOException $eD2) { if ($eD2->getCode() !== '42S22') { throw $eD2; } }
                }
            }

            // Obtener profesor_id del usuario actual (ambos esquemas)
            $profesorLider = null;
            try {
                $stmt = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario_id = ?");
                $stmt->execute([$usuarioActual]);
                $profesorLider = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                if ($e->getCode() !== '42S22') { throw $e; }
            }
            if (!$profesorLider) {
                $stmt = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario = ?");
                $stmt->execute([$usuarioActual]);
                $profesorLider = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$profesorLider) {
                throw new Exception('Profesor líder no encontrado');
            }

            $profesorLiderId = $profesorLider['id'];

            // Obtener información de la ficha
            $stmt = $pdo->prepare("SELECT nombre FROM fichas WHERE id = ?");
            $stmt->execute([$fichaId]);
            $ficha = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ficha) {
                throw new Exception('Ficha no encontrada');
            }

            // Obtener nombre del profesor líder (usuario actual)
            $stmt = $pdo->prepare("SELECT nombres, apellidos FROM usuarios WHERE id = ?");
            $stmt->execute([$usuarioActual]);
            $usuarioLider = $stmt->fetch(PDO::FETCH_ASSOC);

            $nombreLider = $usuarioLider['nombres'] . ' ' . $usuarioLider['apellidos'];

            // Crear solicitudes pendientes y notificaciones
            foreach ($profesores as $profesorId) {
                // Verificar si ya existe una relación (pendiente o aceptada)
                try {
                    $stmt = $pdo->prepare("\n                        SELECT estado_id FROM fichas_compartidas \n                        WHERE ficha_id = ? AND profesor_lider_id = ? AND profesor_compartido_id = ? \n                        AND estado_id IN (1,2)
                    ");
                } catch (\PDOException $e) {
                    if ($e->getCode() !== '42S22') { throw $e; }
                    // Fallback 1: usar columnas facilitador_* con ficha_id
                    try {
                        $stmt = $pdo->prepare("\n                            SELECT estado_id FROM fichas_compartidas \n                            WHERE ficha_id = ? AND facilitador_lider = ? AND facilitador_compartido = ? \n                            AND estado_id IN (1,2)
                        ");
                    } catch (\PDOException $e0) {
                        if ($e0->getCode() !== '42S22') { throw $e0; }
                        // Fallback 2: usar 'ficha' + profesor_*
                        try {
                            $stmt = $pdo->prepare("\n                                SELECT estado_id FROM fichas_compartidas \n                                WHERE ficha = ? AND profesor_lider_id = ? AND profesor_compartido_id = ? \n                                AND estado_id IN (1,2)
                            ");
                        } catch (\PDOException $e1) {
                            if ($e1->getCode() !== '42S22') { throw $e1; }
                            // Fallback 3: usar 'ficha' + facilitador_*
                            try {
                                $stmt = $pdo->prepare("\n                                    SELECT estado_id FROM fichas_compartidas \n                                    WHERE ficha = ? AND facilitador_lider = ? AND facilitador_compartido = ? \n                                    AND estado_id IN (1,2)
                                ");
                            } catch (\PDOException $e1b) {
                                if ($e1b->getCode() !== '42S22') { throw $e1b; }
                                // Fallback 4: estado textual con combinaciones
                                try {
                                    $stmt = $pdo->prepare("\n                                        SELECT estado FROM fichas_compartidas \n                                        WHERE ficha_id = ? AND profesor_lider_id = ? AND profesor_compartido_id = ? \n                                        AND estado IN ('Pendiente','Aceptada')
                                    ");
                                } catch (\PDOException $e2) {
                                    if ($e2->getCode() !== '42S22') { throw $e2; }
                                    try {
                                        $stmt = $pdo->prepare("\n                                            SELECT estado FROM fichas_compartidas \n                                            WHERE ficha_id = ? AND facilitador_lider = ? AND facilitador_compartido = ? \n                                            AND estado IN ('Pendiente','Aceptada')
                                        ");
                                    } catch (\PDOException $e2b) {
                                        if ($e2b->getCode() !== '42S22') { throw $e2b; }
                                        try {
                                            $stmt = $pdo->prepare("\n                                                SELECT estado FROM fichas_compartidas \n                                                WHERE ficha = ? AND profesor_lider_id = ? AND profesor_compartido_id = ? \n                                                AND estado IN ('Pendiente','Aceptada')
                                            ");
                                        } catch (\PDOException $e2c) {
                                            if ($e2c->getCode() !== '42S22') { throw $e2c; }
                                            $stmt = $pdo->prepare("\n                                                SELECT estado FROM fichas_compartidas \n                                                WHERE ficha = ? AND facilitador_lider = ? AND facilitador_compartido = ? \n                                                AND estado IN ('Pendiente','Aceptada')
                                            ");
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                // Mapear profesorId (facilitador) a usuario_id (fallback a 'usuario')
                $usuarioCompartidoId = 0;
                try {
                    $stU = $pdo->prepare("SELECT usuario_id FROM facilitadores WHERE id = ?");
                    $stU->execute([$profesorId]);
                    $usuarioCompartidoId = (int)($stU->fetchColumn() ?: 0);
                } catch (\PDOException $eMap2) {
                    if ($eMap2->getCode() !== '42S22') { throw $eMap2; }
                    $stU = $pdo->prepare("SELECT usuario FROM facilitadores WHERE id = ?");
                    $stU->execute([$profesorId]);
                    $usuarioCompartidoId = (int)($stU->fetchColumn() ?: 0);
                }
                try {
                    $stmt->execute([$fichaId, $usuarioActual, $usuarioCompartidoId]);
                } catch (\PDOException $eX) {
                    if ($eX->getCode() !== '42S22') { throw $eX; }
                    // Intentar variantes con columnas facilitador_*
                    try {
                        $stmt = $pdo->prepare("\n                            SELECT estado_id FROM fichas_compartidas \n                            WHERE ficha_id = ? AND facilitador_lider = ? AND facilitador_compartido = ? \n                            AND estado_id IN (1,2)
                        ");
                        $stmt->execute([$fichaId, $profesorLiderId, $profesorId]);
                    } catch (\PDOException $eX0) {
                        if ($eX0->getCode() !== '42S22') { throw $eX0; }
                        try {
                            $stmt = $pdo->prepare("\n                                SELECT estado_id FROM fichas_compartidas \n                                WHERE ficha = ? AND facilitador_lider = ? AND facilitador_compartido = ? \n                                AND estado_id IN (1,2)
                            ");
                            $stmt->execute([$fichaId, $profesorLiderId, $profesorId]);
                        } catch (\PDOException $eY) {
                            if ($eY->getCode() !== '42S22') { throw $eY; }
                            $stmt = $pdo->prepare("\n                                SELECT estado FROM fichas_compartidas \n                                WHERE ficha = ? AND facilitador_lider = ? AND facilitador_compartido = ? \n                                AND estado IN ('Pendiente','Aceptada')
                            ");
                            $stmt->execute([$fichaId, $profesorLiderId, $profesorId]);
                        }
                    }
                }
                $relacionExistente = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($relacionExistente) {
                    continue; // Saltar este profesor si ya tiene la ficha o hay solicitud pendiente
                }
                
                // Insertar solicitud pendiente en fichas_compartidas
                try {
                    $stmt = $pdo->prepare("\n                        INSERT INTO fichas_compartidas \n                        (ficha_id, profesor_lider_id, profesor_compartido_id, estado_id) \n                        VALUES (?, ?, ?, 1)
                    ");
                    $stmt->execute([$fichaId, $usuarioActual, $usuarioCompartidoId]);
                } catch (\PDOException $e) {
                    if ($e->getCode() !== '42S22') { throw $e; }
                    try {
                        // Variante con columnas facilitador_* y estado_id
                        $stmt = $pdo->prepare("\n                            INSERT INTO fichas_compartidas \n                            (ficha_id, facilitador_lider, facilitador_compartido, estado_id) \n                            VALUES (?, ?, ?, 1)
                        ");
                        $stmt->execute([$fichaId, $profesorLiderId, $profesorId]);
                    } catch (\PDOException $e2) {
                        if ($e2->getCode() !== '42S22') { throw $e2; }
                        // Fallback: probar estado textual y/o 'ficha'
                        try {
                            $stmt = $pdo->prepare("\n                                INSERT INTO fichas_compartidas \n                                (ficha_id, profesor_lider_id, profesor_compartido_id, estado) \n                                VALUES (?, ?, ?, 'Pendiente')
                            ");
                            $stmt->execute([$fichaId, $usuarioActual, $usuarioCompartidoId]);
                        } catch (\PDOException $e3) {
                            if ($e3->getCode() !== '42S22') { throw $e3; }
                            try {
                                $stmt = $pdo->prepare("\n                                    INSERT INTO fichas_compartidas \n                                    (ficha_id, facilitador_lider, facilitador_compartido, estado) \n                                    VALUES (?, ?, ?, 'Pendiente')
                                ");
                                $stmt->execute([$fichaId, $profesorLiderId, $profesorId]);
                            } catch (\PDOException $e4) {
                                if ($e4->getCode() !== '42S22') { throw $e4; }
                                try {
                                    $stmt = $pdo->prepare("\n                                        INSERT INTO fichas_compartidas \n                                        (ficha, facilitador_lider, facilitador_compartido, estado_id) \n                                        VALUES (?, ?, ?, 1)
                                    ");
                                    $stmt->execute([$fichaId, $profesorLiderId, $profesorId]);
                                } catch (\PDOException $e5) {
                                    if ($e5->getCode() !== '42S22') { throw $e5; }
                                    $stmt = $pdo->prepare("\n                                        INSERT INTO fichas_compartidas \n                                        (ficha, facilitador_lider, facilitador_compartido, estado) \n                                        VALUES (?, ?, ?, 'Pendiente')
                                    ");
                                    $stmt->execute([$fichaId, $profesorLiderId, $profesorId]);
                                }
                            }
                        }
                    }
                }
                $solicitudId = $pdo->lastInsertId();

                // Resolver usuario receptor leyendo la fila insertada (robusto a esquemas)
                $usuarioReceptorId = 0;
                if ($solicitudId) {
                    try {
                        // Intento: columna profesor_compartido_id (usuarios.id)
                        $stR = $pdo->prepare("SELECT profesor_compartido_id AS uid FROM fichas_compartidas WHERE id = ?");
                        $stR->execute([$solicitudId]);
                        $usuarioReceptorId = (int)($stR->fetchColumn() ?: 0);
                    } catch (\PDOException $eRc1) {
                        if ($eRc1->getCode() !== '42S22') { throw $eRc1; }
                    }
                    if ($usuarioReceptorId <= 0) {
                        try {
                            // Fallback: columna facilitador_compartido (mapear a usuarios.id)
                            $stR = $pdo->prepare("SELECT facilitador_compartido AS fid FROM fichas_compartidas WHERE id = ?");
                            $stR->execute([$solicitudId]);
                            $fid = (int)($stR->fetchColumn() ?: 0);
                            if ($fid > 0) {
                                try {
                                    $stU = $pdo->prepare("SELECT usuario_id FROM facilitadores WHERE id = ?");
                                    $stU->execute([$fid]);
                                    $usuarioReceptorId = (int)($stU->fetchColumn() ?: 0);
                                } catch (\PDOException $eUc1) {
                                    if ($eUc1->getCode() !== '42S22') { throw $eUc1; }
                                    $stU = $pdo->prepare("SELECT usuario FROM facilitadores WHERE id = ?");
                                    $stU->execute([$fid]);
                                    $usuarioReceptorId = (int)($stU->fetchColumn() ?: 0);
                                }
                            }
                        } catch (\PDOException $eRc2) {
                            if ($eRc2->getCode() !== '42S22') { throw $eRc2; }
                        }
                    }
                }

                // Fallback: usar el mapeo directo si no se pudo resolver desde la fila insertada
                if ($usuarioReceptorId <= 0) {
                    $usuarioReceptorId = (int)$usuarioCompartidoId;
                }

                if ($usuarioReceptorId > 0) {
                    // Crear notificación con botones de acción (si la tabla tiene columnas) o simple en su defecto
                    $titulo = "Solicitud para compartir ficha";
                    $mensaje = "El facilitador {$nombreLider} quiere compartir la ficha {$ficha['nombre']} contigo.";
                    $botonesAccion = json_encode(['aceptar' => 'Aceptar', 'rechazar' => 'Rechazar'], JSON_UNESCAPED_UNICODE);
                    $datosAccion   = json_encode(['solicitud_id' => (int)$solicitudId], JSON_UNESCAPED_UNICODE);

                    try {
                        // Preferir insertar con campos completos
                        try {
                            $stmt = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, leido, creado_en, botones_accion, datos_accion) VALUES (?, ?, ?, 0, NOW(), ?, ?)");
                            $stmt->execute([ $usuarioReceptorId, $titulo, $mensaje, $botonesAccion, $datosAccion ]);
                        } catch (\PDOException $eNotA) {
                            if ($eNotA->getCode() !== '42S22') { throw $eNotA; }
                            try {
                                // Sin botones_accion/datos_accion, pero con leido/creado_en
                                $stmt = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, leido, creado_en) VALUES (?, ?, ?, 0, NOW())");
                                $stmt->execute([ $usuarioReceptorId, $titulo, $mensaje ]);
                            } catch (\PDOException $eNotB) {
                                if ($eNotB->getCode() !== '42S22') { throw $eNotB; }
                                try {
                                    // Variante con columna 'usuario' y campos extra
                                    $stmt = $pdo->prepare("INSERT INTO notificaciones (usuario, titulo, mensaje, leido, creado_en, botones_accion, datos_accion) VALUES (?, ?, ?, 0, NOW(), ?, ?)");
                                    $stmt->execute([ $usuarioReceptorId, $titulo, $mensaje, $botonesAccion, $datosAccion ]);
                                } catch (\PDOException $eNotC) {
                                    if ($eNotC->getCode() !== '42S22') { throw $eNotC; }
                                    // Último fallback: 'usuario' básico con leido/creado_en
                                    $stmt = $pdo->prepare("INSERT INTO notificaciones (usuario, titulo, mensaje, leido, creado_en) VALUES (?, ?, ?, 0, NOW())");
                                    $stmt->execute([ $usuarioReceptorId, $titulo, $mensaje ]);
                                }
                            }
                        }
                    } catch (\Throwable $ignored) {
                        // No interrumpir el flujo si falla la notificación
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Solicitudes de compartir enviadas exitosamente']);
            return;

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            return;
        }
    }

    // Verificar estado de compartir ficha para cada profesor
    public function verificarEstadoCompartir() {
        header('Content-Type: application/json');
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $fichaId = $input['ficha_id'] ?? null;
        $profesores = $input['profesores'] ?? [];
        
        if (!$fichaId || empty($profesores)) {
            echo json_encode([]);
            return;
        }

        $usuarioActual = $_SESSION['usuario']['id'] ?? null;
        if (!$usuarioActual) {
            echo json_encode([]);
            return;
        }

        try {
            $pdo = Database::conectar();
            
            // Obtener profesor_id del usuario actual (soporta usuario_id y usuario)
            $profesorLider = null;
            try {
                $stmt = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario_id = ?");
                $stmt->execute([$usuarioActual]);
                $profesorLider = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (\PDOException $eUser) {
                if ($eUser->getCode() !== '42S22') { throw $eUser; }
            }
            if (!$profesorLider) {
                $stmt = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario = ?");
                $stmt->execute([$usuarioActual]);
                $profesorLider = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$profesorLider) {
                echo json_encode([]);
                return;
            }

            $profesorLiderId = $profesorLider['id'];
            $estados = [];

            // Verificar estado para cada profesor
            foreach ($profesores as $profesorId) {
                // Mapear facilitador.id -> usuarios.id (para esquema con profesor_*)
                $usuarioCompartidoId = 0;
                try {
                    $stU = $pdo->prepare("SELECT usuario_id FROM facilitadores WHERE id = ?");
                    $stU->execute([$profesorId]);
                    $usuarioCompartidoId = (int)($stU->fetchColumn() ?: 0);
                } catch (\PDOException $eMap) {
                    if ($eMap->getCode() !== '42S22') { throw $eMap; }
                    $stU = $pdo->prepare("SELECT usuario FROM facilitadores WHERE id = ?");
                    $stU->execute([$profesorId]);
                    $usuarioCompartidoId = (int)($stU->fetchColumn() ?: 0);
                }

                $estado = 'disponible';

                // 1) Esquema facilitador_* con estado_id y ficha_id
                try {
                    $stmt = $pdo->prepare("\n                        SELECT estado_id FROM fichas_compartidas\n                        WHERE ficha_id = ? AND facilitador_lider = ? AND facilitador_compartido = ?\n                          AND estado_id IN (1,2)\n                    ");
                    $stmt->execute([$fichaId, $profesorLiderId, $profesorId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $estado = ((int)$row['estado_id'] === 2) ? 'aceptada' : 'pendiente';
                        $estados[$profesorId] = $estado;
                        continue;
                    }
                } catch (\PDOException $e1) {
                    if ($e1->getCode() !== '42S22') { throw $e1; }
                }

                // 2) Esquema facilitador_* con estado_id y ficha
                try {
                    $stmt = $pdo->prepare("\n                        SELECT estado_id FROM fichas_compartidas\n                        WHERE ficha = ? AND facilitador_lider = ? AND facilitador_compartido = ?\n                          AND estado_id IN (1,2)\n                    ");
                    $stmt->execute([$fichaId, $profesorLiderId, $profesorId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $estado = ((int)$row['estado_id'] === 2) ? 'aceptada' : 'pendiente';
                        $estados[$profesorId] = $estado;
                        continue;
                    }
                } catch (\PDOException $e2) {
                    if ($e2->getCode() !== '42S22') { throw $e2; }
                }

                // 3) Esquema facilitador_* con estado textual
                try {
                    $stmt = $pdo->prepare("\n                        SELECT estado FROM fichas_compartidas\n                        WHERE ficha_id = ? AND facilitador_lider = ? AND facilitador_compartido = ?\n                          AND UPPER(estado) IN ('PENDIENTE','ACEPTADA')\n                    ");
                    $stmt->execute([$fichaId, $profesorLiderId, $profesorId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row && isset($row['estado'])) {
                        $estadoTxt = strtolower((string)$row['estado']);
                        $estados[$profesorId] = ($estadoTxt === 'aceptada') ? 'aceptada' : 'pendiente';
                        continue;
                    }
                } catch (\PDOException $e3) {
                    if ($e3->getCode() !== '42S22') { throw $e3; }
                }

                // 4) facilitador_* + ficha + estado textual
                try {
                    $stmt = $pdo->prepare("\n                        SELECT estado FROM fichas_compartidas\n                        WHERE ficha = ? AND facilitador_lider = ? AND facilitador_compartido = ?\n                          AND UPPER(estado) IN ('PENDIENTE','ACEPTADA')\n                    ");
                    $stmt->execute([$fichaId, $profesorLiderId, $profesorId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row && isset($row['estado'])) {
                        $estadoTxt = strtolower((string)$row['estado']);
                        $estados[$profesorId] = ($estadoTxt === 'aceptada') ? 'aceptada' : 'pendiente';
                        continue;
                    }
                } catch (\PDOException $e4) {
                    if ($e4->getCode() !== '42S22') { throw $e4; }
                }

                // 5) Esquema profesor_* (usuarios.*) con estado_id y ficha_id
                try {
                    $stmt = $pdo->prepare("\n                        SELECT estado_id FROM fichas_compartidas\n                        WHERE ficha_id = ? AND profesor_lider_id = ? AND profesor_compartido_id = ?\n                          AND estado_id IN (1,2)\n                    ");
                    $stmt->execute([$fichaId, $usuarioActual, $usuarioCompartidoId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $estado = ((int)$row['estado_id'] === 2) ? 'aceptada' : 'pendiente';
                        $estados[$profesorId] = $estado;
                        continue;
                    }
                } catch (\PDOException $e5) {
                    if ($e5->getCode() !== '42S22') { throw $e5; }
                }

                // 6) profesor_* con ficha y estado_id
                try {
                    $stmt = $pdo->prepare("\n                        SELECT estado_id FROM fichas_compartidas\n                        WHERE ficha = ? AND profesor_lider_id = ? AND profesor_compartido_id = ?\n                          AND estado_id IN (1,2)\n                    ");
                    $stmt->execute([$fichaId, $usuarioActual, $usuarioCompartidoId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $estado = ((int)$row['estado_id'] === 2) ? 'aceptada' : 'pendiente';
                        $estados[$profesorId] = $estado;
                        continue;
                    }
                } catch (\PDOException $e6) {
                    if ($e6->getCode() !== '42S22') { throw $e6; }
                }

                // 7) profesor_* con estado textual
                try {
                    $stmt = $pdo->prepare("\n                        SELECT estado FROM fichas_compartidas\n                        WHERE ficha_id = ? AND profesor_lider_id = ? AND profesor_compartido_id = ?\n                          AND UPPER(estado) IN ('PENDIENTE','ACEPTADA')\n                    ");
                    $stmt->execute([$fichaId, $usuarioActual, $usuarioCompartidoId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row && isset($row['estado'])) {
                        $estadoTxt = strtolower((string)$row['estado']);
                        $estados[$profesorId] = ($estadoTxt === 'aceptada') ? 'aceptada' : 'pendiente';
                        continue;
                    }
                } catch (\PDOException $e7) {
                    if ($e7->getCode() !== '42S22') { throw $e7; }
                }

                // 8) profesor_* con ficha + estado textual
                try {
                    $stmt = $pdo->prepare("\n                        SELECT estado FROM fichas_compartidas\n                        WHERE ficha = ? AND profesor_lider_id = ? AND profesor_compartido_id = ?\n                          AND UPPER(estado) IN ('PENDIENTE','ACEPTADA')\n                    ");
                    $stmt->execute([$fichaId, $usuarioActual, $usuarioCompartidoId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row && isset($row['estado'])) {
                        $estadoTxt = strtolower((string)$row['estado']);
                        $estados[$profesorId] = ($estadoTxt === 'aceptada') ? 'aceptada' : 'pendiente';
                        continue;
                    }
                } catch (\PDOException $e8) {
                    if ($e8->getCode() !== '42S22') { throw $e8; }
                }

                // Si no se encontró registro en ninguna variante
                $estados[$profesorId] = 'disponible';
            }

            echo json_encode($estados);

        } catch (Exception $e) {
            echo json_encode([]);
        }
    }

    // Responder a solicitud de compartir ficha
    public function responder_solicitud() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $solicitudId = $input['solicitud_id'] ?? null;
        $respuesta = $input['respuesta'] ?? null; // 'aceptar' o 'rechazar'
        // Payload alterno sin ID
        $altFichaId  = $input['ficha_id'] ?? null;
        $altLiderFac = $input['lider_facilitador_id'] ?? null;
        $altReceptorFac = $input['receptor_facilitador_id'] ?? null;

        $tieneId = !empty($solicitudId);
        $tieneAlt = $altFichaId && $altLiderFac && $altReceptorFac;

        if ((!$tieneId && !$tieneAlt) || !in_array($respuesta, ['aceptar', 'rechazar'])) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
            return;
        }

        try {
            $pdo = Database::conectar();
            $pdo->beginTransaction();

            // 1) Cargar solicitud simple y validar estado pendiente
            $sol = null;
            if ($tieneId) {
                $stmt = $pdo->prepare("SELECT * FROM fichas_compartidas WHERE id = ? LIMIT 1");
                $stmt->execute([$solicitudId]);
                $sol = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                // Buscar por combinaciones de columnas
                // Variante principal del esquema legacy
                try {
                    $stmt = $pdo->prepare("SELECT * FROM fichas_compartidas WHERE ficha = ? AND facilitador_lider = ? AND facilitador_compartido = ? AND (estado = 'Pendiente' OR estado IS NULL) LIMIT 1");
                    $stmt->execute([$altFichaId, $altLiderFac, $altReceptorFac]);
                    $sol = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (\PDOException $eA) {
                    if ($eA->getCode() !== '42S22') { throw $eA; }
                }
                if (!$sol) {
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM fichas_compartidas WHERE ficha_id = ? AND facilitador_lider = ? AND facilitador_compartido = ? AND (estado = 'Pendiente' OR estado IS NULL) LIMIT 1");
                        $stmt->execute([$altFichaId, $altLiderFac, $altReceptorFac]);
                        $sol = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (\PDOException $eB) {
                        if ($eB->getCode() !== '42S22') { throw $eB; }
                    }
                }
                if (!$sol) {
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM fichas_compartidas WHERE ficha_id = ? AND profesor_lider_id = ? AND profesor_compartido_id = ? AND (estado = 'Pendiente' OR estado IS NULL) LIMIT 1");
                        // Para esta variante, receptor es usuarios.id; si no coincide, no habrá fila
                        $stmt->execute([$altFichaId, $altLiderFac, ($input['receptor_usuario_id'] ?? 0)]);
                        $sol = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (\PDOException $eC) {
                        if ($eC->getCode() !== '42S22') { throw $eC; }
                    }
                }
                // Fallbacks adicionales: por (ficha + receptor) y luego por (receptor) solamente
                if (!$sol) {
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM fichas_compartidas WHERE ficha = ? AND facilitador_compartido = ? AND (estado = 'Pendiente' OR estado IS NULL) ORDER BY creado_en DESC LIMIT 1");
                        $stmt->execute([$altFichaId, $altReceptorFac]);
                        $sol = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (\PDOException $eD) {
                        if ($eD->getCode() !== '42S22') { throw $eD; }
                        try {
                            $stmt = $pdo->prepare("SELECT * FROM fichas_compartidas WHERE ficha_id = ? AND facilitador_compartido = ? AND (estado = 'Pendiente' OR estado IS NULL) ORDER BY creado_en DESC LIMIT 1");
                            $stmt->execute([$altFichaId, $altReceptorFac]);
                            $sol = $stmt->fetch(PDO::FETCH_ASSOC);
                        } catch (\PDOException $eE) {
                            if ($eE->getCode() !== '42S22') { throw $eE; }
                        }
                    }
                }
                if (!$sol) {
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM fichas_compartidas WHERE facilitador_compartido = ? AND (estado = 'Pendiente' OR estado IS NULL) ORDER BY creado_en DESC LIMIT 1");
                        $stmt->execute([$altReceptorFac]);
                        $sol = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (\PDOException $eF) {
                        if ($eF->getCode() !== '42S22') { throw $eF; }
                    }
                }
            }

            if (!$sol && $tieneAlt) {
                $actualizado = false;
                // Intentar actualización directa por payload alterno (más laxa)
                if ($respuesta === 'aceptar') {
                    // Intentar textual primero
                    try {
                        $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado = 'Aceptada' WHERE ficha = ? AND facilitador_lider = ? AND facilitador_compartido = ? AND (estado = 'Pendiente' OR estado IS NULL) LIMIT 1");
                        $stUp->execute([$altFichaId, $altLiderFac, $altReceptorFac]);
                        $actualizado = ($stUp->rowCount() > 0);
                    } catch (\PDOException $e0) { if ($e0->getCode() !== '42S22') { throw $e0; } }
                    if (!$actualizado) {
                        try {
                            $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado_id = 2 WHERE ficha = ? AND facilitador_lider = ? AND facilitador_compartido = ? AND (estado_id = 1 OR estado_id IS NULL) LIMIT 1");
                            $stUp->execute([$altFichaId, $altLiderFac, $altReceptorFac]);
                            $actualizado = ($stUp->rowCount() > 0);
                        } catch (\PDOException $e1) { if ($e1->getCode() !== '42S22') { throw $e1; } }
                    }
                    if (!$actualizado) {
                        try {
                            $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado = 'Aceptada' WHERE ficha_id = ? AND facilitador_lider = ? AND facilitador_compartido = ? AND (estado = 'Pendiente' OR estado IS NULL) LIMIT 1");
                            $stUp->execute([$altFichaId, $altLiderFac, $altReceptorFac]);
                            $actualizado = ($stUp->rowCount() > 0);
                        } catch (\PDOException $e2) { if ($e2->getCode() !== '42S22') { throw $e2; } }
                    }
                    if (!$actualizado && !empty($input['receptor_usuario_id'])) {
                        try {
                            $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado = 'Aceptada' WHERE ficha_id = ? AND profesor_compartido_id = ? AND (estado = 'Pendiente' OR estado IS NULL) LIMIT 1");
                            $stUp->execute([$altFichaId, (int)$input['receptor_usuario_id']]);
                            $actualizado = ($stUp->rowCount() > 0);
                        } catch (\PDOException $e4) { if ($e4->getCode() !== '42S22') { throw $e4; } }
                    }
                    // Fallback mínimo: por ficha + receptor (ignorando líder)
                    if (!$actualizado && $altReceptorFac) {
                        try {
                            $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado = 'Aceptada' WHERE ficha = ? AND facilitador_compartido = ? AND (estado = 'Pendiente' OR estado IS NULL) LIMIT 1");
                            $stUp->execute([$altFichaId, $altReceptorFac]);
                            $actualizado = ($stUp->rowCount() > 0);
                        } catch (\PDOException $e5) {
                            if ($e5->getCode() !== '42S22') { throw $e5; }
                            try {
                                $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado_id = 2 WHERE ficha_id = ? AND facilitador_compartido = ? AND (estado_id = 1 OR estado_id IS NULL) LIMIT 1");
                                $stUp->execute([$altFichaId, $altReceptorFac]);
                                $actualizado = ($stUp->rowCount() > 0);
                            } catch (\PDOException $e6) { if ($e6->getCode() !== '42S22') { throw $e6; } }
                        }
                    }
                } elseif (!$sol && $tieneAlt && $respuesta === 'rechazar') {
                    // Intentar textual primero
                    try {
                        $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado = 'Rechazada' WHERE ficha = ? AND facilitador_lider = ? AND facilitador_compartido = ? AND (estado = 'Pendiente' OR estado IS NULL) LIMIT 1");
                        $stUp->execute([$altFichaId, $altLiderFac, $altReceptorFac]);
                        $actualizado = ($stUp->rowCount() > 0);
                    } catch (\PDOException $e0) { if ($e0->getCode() !== '42S22') { throw $e0; } }
                    if (!$actualizado) {
                        try {
                            $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado_id = 3 WHERE ficha = ? AND facilitador_lider = ? AND facilitador_compartido = ? AND (estado_id = 1 OR estado_id IS NULL) LIMIT 1");
                            $stUp->execute([$altFichaId, $altLiderFac, $altReceptorFac]);
                            $actualizado = ($stUp->rowCount() > 0);
                        } catch (\PDOException $e1) { if ($e1->getCode() !== '42S22') { throw $e1; } }
                    }
                    if (!$actualizado) {
                        try {
                            $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado = 'Rechazada' WHERE ficha_id = ? AND facilitador_lider = ? AND facilitador_compartido = ? AND (estado = 'Pendiente' OR estado IS NULL) LIMIT 1");
                            $stUp->execute([$altFichaId, $altLiderFac, $altReceptorFac]);
                            $actualizado = ($stUp->rowCount() > 0);
                        } catch (\PDOException $e2) { if ($e2->getCode() !== '42S22') { throw $e2; } }
                    }
                    if (!$actualizado && !empty($input['receptor_usuario_id'])) {
                        try {
                            $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado = 'Rechazada' WHERE ficha_id = ? AND profesor_compartido_id = ? AND (estado = 'Pendiente' OR estado IS NULL) LIMIT 1");
                            $stUp->execute([$altFichaId, (int)$input['receptor_usuario_id']]);
                            $actualizado = ($stUp->rowCount() > 0);
                        } catch (\PDOException $e4) { if ($e4->getCode() !== '42S22') { throw $e4; } }
                    }
                    // Fallback mínimo: por ficha + receptor (ignorando líder)
                    if (!$actualizado && $altReceptorFac) {
                        try {
                            $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado = 'Rechazada' WHERE ficha = ? AND facilitador_compartido = ? AND (estado = 'Pendiente' OR estado IS NULL) LIMIT 1");
                            $stUp->execute([$altFichaId, $altReceptorFac]);
                            $actualizado = ($stUp->rowCount() > 0);
                        } catch (\PDOException $e5) {
                            if ($e5->getCode() !== '42S22') { throw $e5; }
                            try {
                                $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado_id = 3 WHERE ficha_id = ? AND facilitador_compartido = ? AND (estado_id = 1 OR estado_id IS NULL) LIMIT 1");
                                $stUp->execute([$altFichaId, $altReceptorFac]);
                                $actualizado = ($stUp->rowCount() > 0);
                            } catch (\PDOException $e6) { if ($e6->getCode() !== '42S22') { throw $e6; } }
                        }
                    }
                }

                if ($actualizado) {
                    // Cargar la fila actualizada para continuar flujo (notificación al líder)
                    try {
                        $stGet = $pdo->prepare("SELECT * FROM fichas_compartidas WHERE ficha = ? AND facilitador_compartido = ? ORDER BY creado_en DESC LIMIT 1");
                        $stGet->execute([$altFichaId, $altReceptorFac]);
                        $sol = $stGet->fetch(PDO::FETCH_ASSOC) ?: $sol;
                    } catch (\PDOException $eG1) {
                        if ($eG1->getCode() !== '42S22') { throw $eG1; }
                        try {
                            $stGet = $pdo->prepare("SELECT * FROM fichas_compartidas WHERE ficha_id = ? AND facilitador_compartido = ? ORDER BY creado_en DESC LIMIT 1");
                            $stGet->execute([$altFichaId, $altReceptorFac]);
                            $sol = $stGet->fetch(PDO::FETCH_ASSOC) ?: $sol;
                        } catch (\PDOException $eG2) { if ($eG2->getCode() !== '42S22') { throw $eG2; } }
                    }
                }
            }

            if (!$sol) { throw new Exception('No encontramos la solicitud. Puede que ya haya sido procesada o la notificación esté desactualizada.'); }

            $estaPendiente = true;
            if (array_key_exists('estado_id', $sol)) {
                $val = $sol['estado_id'];
                $estaPendiente = ($val === null || (int)$val === 1);
            } elseif (array_key_exists('estado', $sol)) {
                $val = $sol['estado'];
                $estaPendiente = ($val === null || $val === '' || strcasecmp((string)$val, 'Pendiente') === 0);
            }
            if (!$estaPendiente && !$actualizado) {
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Solicitud ya procesada']);
                return;
            }

            // 2) Obtener nombre de la ficha (ficha_id o ficha)
            $fichaIdRow = $sol['ficha_id'] ?? ($sol['ficha'] ?? null);
            $fichaNombre = '';
            if ($fichaIdRow) {
                try {
                    $stF = $pdo->prepare("SELECT nombre FROM fichas WHERE id = ?");
                    $stF->execute([$fichaIdRow]);
                    $fichaNombre = (string)($stF->fetchColumn() ?: '');
                } catch (\Throwable $tF) { $fichaNombre = ''; }
            }

            // 3) Construir mensaje usando el usuario actual como receptor
            $receptorNombres   = $_SESSION['usuario']['nombres']  ?? '';
            $receptorApellidos = $_SESSION['usuario']['apellidos']?? '';

            if ($respuesta === 'aceptar') {
                // Actualizar a aceptada
                if ($tieneId) {
                    try {
                        $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado_id = 2 WHERE id = ?");
                        $stUp->execute([$solicitudId]);
                        $actualizado = ($stUp->rowCount() > 0);
                    } catch (\PDOException $eUp) {
                        if ($eUp->getCode() !== '42S22') { throw $eUp; }
                        $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado = 'Aceptada' WHERE id = ?");
                        $stUp->execute([$solicitudId]);
                        $actualizado = ($stUp->rowCount() > 0);
                    }
                } else {
                    // Construir WHERE con las columnas reales presentes en $sol
                    $where = [];
                    $params = [];
                    if (array_key_exists('ficha_id', $sol)) { $where[] = 'ficha_id = ?'; $params[] = (int)$sol['ficha_id']; }
                    elseif (array_key_exists('ficha', $sol)) { $where[] = 'ficha = ?'; $params[] = (int)$sol['ficha']; }
                    if (array_key_exists('facilitador_lider', $sol)) { $where[] = 'facilitador_lider = ?'; $params[] = (int)$sol['facilitador_lider']; }
                    elseif (array_key_exists('profesor_lider_id', $sol)) { $where[] = 'profesor_lider_id = ?'; $params[] = (int)$sol['profesor_lider_id']; }
                    elseif (array_key_exists('profesor_lider', $sol)) { $where[] = 'profesor_lider = ?'; $params[] = (int)$sol['profesor_lider']; }
                    if (array_key_exists('facilitador_compartido', $sol)) { $where[] = 'facilitador_compartido = ?'; $params[] = (int)$sol['facilitador_compartido']; }
                    elseif (array_key_exists('profesor_compartido_id', $sol)) { $where[] = 'profesor_compartido_id = ?'; $params[] = (int)$sol['profesor_compartido_id']; }
                    elseif (array_key_exists('profesor_compartido', $sol)) { $where[] = 'profesor_compartido = ?'; $params[] = (int)$sol['profesor_compartido']; }
                    $sqlWhere = implode(' AND ', $where);
                    // 1) Intentar con WHERE textual (no referenciar estado_id)
                    try {
                        $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado = 'Aceptada' WHERE $sqlWhere AND (estado = 'Pendiente' OR estado IS NULL) LIMIT 1");
                        $stUp->execute($params);
                        $actualizado = ($stUp->rowCount() > 0);
                    } catch (\PDOException $eTxt) {
                        if ($eTxt->getCode() !== '42S22') { throw $eTxt; }
                        // 2) Fallback: WHERE por estado_id
                        try {
                            $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado_id = 2 WHERE $sqlWhere AND (estado_id = 1 OR estado_id IS NULL) LIMIT 1");
                            $stUp->execute($params);
                            $actualizado = ($stUp->rowCount() > 0);
                        } catch (\PDOException $eId) { if ($eId->getCode() !== '42S22') { throw $eId; } }
                    }
                }
                $titulo  = 'Ficha compartida: aceptada';
                $mensaje = "El facilitador {$receptorNombres} {$receptorApellidos} ha aceptado compartir la ficha {$fichaNombre}.";
            } else {
                // Actualizar a rechazada
                if ($tieneId) {
                    try {
                        $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado_id = 3 WHERE id = ?");
                        $stUp->execute([$solicitudId]);
                        $actualizado = ($stUp->rowCount() > 0);
                    } catch (\PDOException $eUp) {
                        if ($eUp->getCode() !== '42S22') { throw $eUp; }
                        $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado = 'Rechazada' WHERE id = ?");
                        $stUp->execute([$solicitudId]);
                        $actualizado = ($stUp->rowCount() > 0);
                    }
                } else {
                    // Construir WHERE con las columnas reales presentes en $sol
                    $where = [];
                    $params = [];
                    if (array_key_exists('ficha_id', $sol)) { $where[] = 'ficha_id = ?'; $params[] = (int)$sol['ficha_id']; }
                    elseif (array_key_exists('ficha', $sol)) { $where[] = 'ficha = ?'; $params[] = (int)$sol['ficha']; }
                    if (array_key_exists('facilitador_lider', $sol)) { $where[] = 'facilitador_lider = ?'; $params[] = (int)$sol['facilitador_lider']; }
                    elseif (array_key_exists('profesor_lider_id', $sol)) { $where[] = 'profesor_lider_id = ?'; $params[] = (int)$sol['profesor_lider_id']; }
                    elseif (array_key_exists('profesor_lider', $sol)) { $where[] = 'profesor_lider = ?'; $params[] = (int)$sol['profesor_lider']; }
                    if (array_key_exists('facilitador_compartido', $sol)) { $where[] = 'facilitador_compartido = ?'; $params[] = (int)$sol['facilitador_compartido']; }
                    elseif (array_key_exists('profesor_compartido_id', $sol)) { $where[] = 'profesor_compartido_id = ?'; $params[] = (int)$sol['profesor_compartido_id']; }
                    elseif (array_key_exists('profesor_compartido', $sol)) { $where[] = 'profesor_compartido = ?'; $params[] = (int)$sol['profesor_compartido']; }
                    $sqlWhere = implode(' AND ', $where);
                    // 1) Intentar con WHERE textual (no referenciar estado_id)
                    try {
                        $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado = 'Rechazada' WHERE $sqlWhere AND (estado = 'Pendiente' OR estado IS NULL) LIMIT 1");
                        $stUp->execute($params);
                        $actualizado = ($stUp->rowCount() > 0);
                    } catch (\PDOException $eTxt) {
                        if ($eTxt->getCode() !== '42S22') { throw $eTxt; }
                        // 2) Fallback: WHERE por estado_id
                        try {
                            $stUp = $pdo->prepare("UPDATE fichas_compartidas SET estado_id = 3 WHERE $sqlWhere AND (estado_id = 1 OR estado_id IS NULL) LIMIT 1");
                            $stUp->execute($params);
                            $actualizado = ($stUp->rowCount() > 0);
                        } catch (\PDOException $eId) { if ($eId->getCode() !== '42S22') { throw $eId; } }
                    }
                }
                $titulo  = 'Ficha compartida: rechazada';
                $mensaje = "El facilitador {$receptorNombres} {$receptorApellidos} ha rechazado compartir la ficha {$fichaNombre}.";
            }

            // 4) Determinar usuario destino (solicitante/líder): profesor_lider_id (usuarios.id), profesor_lider (usuarios.id) o facilitador_lider -> usuarios.id
            $usuarioDestinoId = 0;
            if (!empty($sol['profesor_lider_id'])) {
                $usuarioDestinoId = (int)$sol['profesor_lider_id'];
            } elseif (!empty($sol['profesor_lider'])) {
                $usuarioDestinoId = (int)$sol['profesor_lider'];
            } elseif (!empty($sol['facilitador_lider'])) {
                $facId = (int)$sol['facilitador_lider'];
                try {
                    $stU = $pdo->prepare("SELECT usuario_id FROM facilitadores WHERE id = ?");
                    $stU->execute([$facId]);
                    $usuarioDestinoId = (int)($stU->fetchColumn() ?: 0);
                } catch (\PDOException $eMap) {
                    if ($eMap->getCode() !== '42S22') { throw $eMap; }
                    $stU = $pdo->prepare("SELECT usuario FROM facilitadores WHERE id = ?");
                    $stU->execute([$facId]);
                    $usuarioDestinoId = (int)($stU->fetchColumn() ?: 0);
                }
            }

            // 5) Insertar notificación solo si tenemos destino válido (con fallbacks y campos extra)
            if ($usuarioDestinoId > 0 && $actualizado) {
                try {
                    try {
                        $stN = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, leido, creado_en) VALUES (?, ?, ?, 0, NOW())");
                        $stN->execute([$usuarioDestinoId, $titulo, $mensaje]);
                    } catch (\PDOException $eN1) {
                        if ($eN1->getCode() !== '42S22') { throw $eN1; }
                        try {
                            $stN = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje) VALUES (?, ?, ?)");
                            $stN->execute([$usuarioDestinoId, $titulo, $mensaje]);
                        } catch (\PDOException $eN2) {
                            if ($eN2->getCode() !== '42S22') { throw $eN2; }
                            try {
                                $stN = $pdo->prepare("INSERT INTO notificaciones (usuario, titulo, mensaje, leido, creado_en) VALUES (?, ?, ?, 0, NOW())");
                                $stN->execute([$usuarioDestinoId, $titulo, $mensaje]);
                            } catch (\PDOException $eN3) {
                                if ($eN3->getCode() !== '42S22') { throw $eN3; }
                                $stN = $pdo->prepare("INSERT INTO notificaciones (usuario, titulo, mensaje) VALUES (?, ?, ?)");
                                $stN->execute([$usuarioDestinoId, $titulo, $mensaje]);
                            }
                        }
                    }
                } catch (\Throwable $ignored) { /* no abortar */ }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => $actualizado ? 'Solicitud actualizada' : 'Solicitud ya procesada']);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}