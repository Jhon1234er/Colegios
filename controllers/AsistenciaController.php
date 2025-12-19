<?php
require_once __DIR__ . '/../models/Asistencia.php';
require_once __DIR__ . '/../helpers/auth.php';

class AsistenciaController {
    private $asistenciaModel;
    
    public function __construct() {
        $this->asistenciaModel = new Asistencia();
        // Asegurar sesión iniciada para poder usar $_SESSION en cualquier contexto
        if (function_exists('start_secure_session')) {
            start_secure_session();
        }
        // Verificar autenticación
        if (!isset($_SESSION['usuario'])) {
            http_response_code(401);
            echo json_encode(['error' => 'No autorizado. Por favor inicie sesión.']);
            exit;
        }
        // Manejar acciones AJAX
        $action = $_GET['action'] ?? '';
        if ($action === 'registrar') {
            $this->registrar(); exit;
        } elseif ($action === 'actualizar') {
            $this->actualizar(); exit;
        } elseif ($action === 'obtener_por_fecha') {
            $this->obtenerPorFecha(); exit;
        } elseif ($action === 'obtener_estadisticas') {
            $this->obtenerEstadisticas(); exit;
        } elseif ($action === 'obtener_por_rango') {
            $this->obtenerPorRango(); exit;
        } elseif ($action === 'registrar_lote') {
            $this->registrarLote(); exit;
        } elseif ($action === 'clase_en_curso') {
            $this->claseEnCurso(); exit;
        } elseif ($action === 'obtener_estudiantes') {
            $this->obtenerEstudiantes(); exit;
        } elseif ($action === 'proxima_hoy') {
            $this->proximaHoy(); exit;
        } elseif ($action === 'iniciar_clase') {
            $this->iniciarClase(); exit;
        }
    }

    /**
     * Devuelve el próximo bloque de clase de HOY para una ficha (si existe y aún no inicia)
     */
    public function proximaHoy() {
        header('Content-Type: application/json');
        try {
            if (!isset($_GET['ficha_id']) || !is_numeric($_GET['ficha_id'])) {
                throw new Exception('ID de ficha no válido');
            }
            $ficha_id = (int)$_GET['ficha_id'];

            require_once __DIR__ . '/../config/db.php';
            $pdo = Database::conectar();
            $sql = "SELECT id, titulo, fecha_inicio, fecha_fin FROM horarios_fichas 
                    WHERE ficha_id = ? AND DATE(fecha_inicio) = CURDATE() AND fecha_inicio > NOW()
                    ORDER BY fecha_inicio ASC LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$ficha_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                echo json_encode(['success' => true, 'horario' => $row]);
            } else {
                echo json_encode(['success' => true, 'horario' => null]);
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Inicia una clase (cambia estado a en_curso) si ya alcanzó su hora de inicio
     */
    public function iniciarClase() {
        header('Content-Type: application/json');
        try {
            // Permitir JSON o formulario
            $horario_id = null;
            if (($_SERVER['CONTENT_TYPE'] ?? '') && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
                $raw = file_get_contents('php://input');
                $j = json_decode($raw, true);
                $horario_id = isset($j['horario_id']) ? (int)$j['horario_id'] : 0;
            } else {
                $horario_id = isset($_POST['horario_id']) ? (int)$_POST['horario_id'] : 0;
            }
            if ($horario_id <= 0) throw new Exception('horario_id requerido');

            require_once __DIR__ . '/../config/db.php';
            $pdo = Database::conectar();
            // Validar que el bloque existe y ya es hora de iniciar
            $stmt = $pdo->prepare("SELECT id, ficha_id, fecha_inicio, fecha_fin FROM horarios_fichas WHERE id = ? LIMIT 1");
            $stmt->execute([$horario_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Horario no encontrado');

            if (strtotime($row['fecha_inicio']) > time()) {
                throw new Exception('Aún no es hora de iniciar');
            }

            // Esquema actual no guarda estado textual; se considera "en curso" por NOW() entre fecha_inicio y fecha_fin.
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    /**
     * Registra la asistencia de un estudiante
     */
    public function registrar() {
        header('Content-Type: application/json');
        
        try {
            // Validar datos de entrada
            $datos = $this->validarDatosAsistencia();
            
            // Resolver profesor_id de forma robusta
            $profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
            if (!$profesor_id) {
                try {
                    require_once __DIR__ . '/../config/db.php';
                    $pdoTmp = Database::conectar();
                    // Preferir columna usuario_id; fallback a usuario
                    try {
                        $st = $pdoTmp->prepare("SELECT id FROM facilitadores WHERE usuario_id = ? LIMIT 1");
                        $st->execute([$_SESSION['usuario']['id'] ?? 0]);
                        $profesor_id = $st->fetchColumn() ?: null;
                    } catch (\PDOException $e1) {
                        if ($e1->getCode() !== '42S22') { /* continuar fallback */ }
                        try {
                            $st2 = $pdoTmp->prepare("SELECT id FROM facilitadores WHERE usuario = ? LIMIT 1");
                            $st2->execute([$_SESSION['usuario']['id'] ?? 0]);
                            $profesor_id = $st2->fetchColumn() ?: null;
                        } catch (\PDOException $e2) { /* noop */ }
                    }
                } catch (\Throwable $_) { /* noop */ }
            }

            // Fallback: si no se resolvió profesor_id desde facilitadores, tomarlo del bloque en curso
            if (!$profesor_id) {
                try {
                    require_once __DIR__ . '/../config/db.php';
                    $pdoTmp2 = Database::conectar();
                    $stH = $pdoTmp2->prepare("SELECT facilitador_id FROM horarios_fichas WHERE ficha_id = ? AND NOW() BETWEEN fecha_inicio AND fecha_fin ORDER BY fecha_inicio DESC LIMIT 1");
                    $stH->execute([$datos['ficha_id']]);
                    $profesor_id = $stH->fetchColumn() ?: null;
                } catch (\Throwable $_) { /* noop */ }
            }

            // Registrar asistencia
            $resultado = $this->asistenciaModel->registrarAsistencia([
                'ficha_id' => $datos['ficha_id'],
                'estudiante_id' => $datos['estudiante_id'],
                'profesor_id' => $profesor_id,
                'fecha' => $datos['fecha'],
                'hora_entrada' => date('H:i:s'),
                'estado' => $datos['estado'],
                'observaciones' => $datos['observaciones'] ?? null,
                'creado_por' => $_SESSION['usuario']['id']
            ]);
            
            if ($resultado) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Asistencia registrada correctamente'
                ]);
            } else {
                throw new Exception('No se pudo registrar la asistencia');
            }
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Actualiza el estado de una asistencia existente
     */
    public function actualizar() {
        header('Content-Type: application/json');
        
        try {
            // Validar datos de entrada
            if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
                throw new Exception('ID de asistencia no válido');
            }
            
            $id = (int)$_POST['id'];
            $estado = strtolower(trim($_POST['estado'] ?? ''));
            // Normalizar variantes legacy a los nuevos estados textuales
            if (in_array($estado, ['no_asistio', 'falla', 'ausente'], true)) {
                $estado = Asistencia::ESTADO_NO_ASISTIO;
            } elseif (in_array($estado, ['tarde', 'tardanza'], true)) {
                $estado = Asistencia::ESTADO_TARDE;
            } elseif (in_array($estado, ['justificado', 'justificada'], true)) {
                $estado = Asistencia::ESTADO_JUSTIFICADO;
            } elseif ($estado === 'presente') {
                $estado = Asistencia::ESTADO_PRESENTE;
            }
            $observaciones = $_POST['observaciones'] ?? '';
            
            // Validar estado
            $estadosValidos = [
                Asistencia::ESTADO_PRESENTE,
                Asistencia::ESTADO_NO_ASISTIO,
                Asistencia::ESTADO_JUSTIFICADO,
                Asistencia::ESTADO_TARDE
            ];
            
            if (!in_array($estado, $estadosValidos)) {
                throw new Exception('Estado de asistencia no válido');
            }
            // Bloquear edición si la clase ya terminó (solo se permite editar durante la clase)
            try {
                require_once __DIR__ . '/../config/db.php';
                $pdoTmp = Database::conectar();
                $stA = $pdoTmp->prepare("SELECT ficha_id, DATE(fecha) AS fecha_dia FROM asistencias WHERE id = ? LIMIT 1");
                $stA->execute([$id]);
                $rowA = $stA->fetch(PDO::FETCH_ASSOC);
                if ($rowA) {
                    $stH = $pdoTmp->prepare("SELECT id FROM horarios_fichas WHERE ficha_id = ? AND DATE(fecha_inicio) = ? AND NOW() BETWEEN fecha_inicio AND fecha_fin LIMIT 1");
                    $stH->execute([(int)$rowA['ficha_id'], $rowA['fecha_dia']]);
                    $enCurso = $stH->fetch(PDO::FETCH_ASSOC);
                    if (!$enCurso) {
                        throw new Exception('La clase ya terminó; no es posible editar desde el tablero del instructor.');
                    }
                }
            } catch (Exception $eChk) {
                throw $eChk;
            }
            
            // Actualizar asistencia
            $resultado = $this->asistenciaModel->actualizarAsistencia(
                $id,
                $estado,
                $observaciones
            );
            
            if ($resultado) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Asistencia actualizada correctamente'
                ]);
            } else {
                throw new Exception('No se pudo actualizar la asistencia');
            }
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Obtiene las asistencias de una ficha en una fecha específica
     */
    public function obtenerPorFecha() {
        header('Content-Type: application/json');
        
        try {
            // Validar parámetros
            if (!isset($_GET['ficha_id']) || !is_numeric($_GET['ficha_id'])) {
                throw new Exception('ID de ficha no válido');
            }
            
            $ficha_id = (int)$_GET['ficha_id'];
            $fecha = $_GET['fecha'] ?? date('Y-m-d');
            
            // Validar fecha
            if (!strtotime($fecha)) {
                throw new Exception('Formato de fecha no válido');
            }
            
            // Obtener asistencias
            $asistencias = $this->asistenciaModel->obtenerAsistenciasPorFicha($ficha_id, $fecha);
            
            echo json_encode([
                'success' => true,
                'data' => $asistencias
            ]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Obtiene las asistencias de una ficha en un rango de fechas [inicio, fin]
     */
    public function obtenerPorRango() {
        header('Content-Type: application/json');
        try {
            if (!isset($_GET['ficha_id']) || !is_numeric($_GET['ficha_id'])) {
                throw new Exception('ID de ficha no válido');
            }
            $ficha_id = (int)$_GET['ficha_id'];
            $fecha_inicio = $_GET['fecha_inicio'] ?? null;
            $fecha_fin = $_GET['fecha_fin'] ?? null;
            if (!$fecha_inicio || !$fecha_fin || !strtotime($fecha_inicio) || !strtotime($fecha_fin)) {
                throw new Exception('Rango de fechas no válido');
            }
            
            // Obtener asistencias dentro del rango solicitado
            $asistencias = $this->asistenciaModel->obtenerAsistenciasPorFichaRango($ficha_id, $fecha_inicio, $fecha_fin);
            echo json_encode(['success' => true, 'data' => $asistencias]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Verifica si existe una clase en curso para una ficha (habilita registro de asistencia)
     */
    public function claseEnCurso() {
        header('Content-Type: application/json');
        try {
            if (!isset($_GET['ficha_id']) || !is_numeric($_GET['ficha_id'])) {
                throw new Exception('ID de ficha no válido');
            }
            $ficha_id = (int)$_GET['ficha_id'];

            require_once __DIR__ . '/../config/db.php';
            $pdo = Database::conectar();
            $sql = "SELECT id, titulo, fecha_inicio, fecha_fin FROM horarios_fichas 
                    WHERE ficha_id = ? AND NOW() BETWEEN fecha_inicio AND fecha_fin
                    ORDER BY fecha_inicio DESC LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$ficha_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                echo json_encode(['success' => true, 'en_curso' => true, 'horario' => $row]);
            } else {
                echo json_encode(['success' => true, 'en_curso' => false]);
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
   /**
     * Obtiene estadísticas de asistencia
     */
    public function obtenerEstadisticas() {
        header('Content-Type: application/json');
        
        try {
            // Validar parámetros
            if (!isset($_GET['estudiante_id']) || !is_numeric($_GET['estudiante_id'])) {
                throw new Exception('ID de estudiante no válido');
            }
            
            $estudiante_id = (int)$_GET['estudiante_id'];
            $ficha_id = isset($_GET['ficha_id']) && is_numeric($_GET['ficha_id']) 
                ? (int)$_GET['ficha_id'] 
                : null;
            
            // Obtener estadísticas
            $estadisticas = $this->asistenciaModel->obtenerEstadisticasEstudiante($estudiante_id, $ficha_id);
            
            if ($estadisticas) {
                // Calcular porcentajes
                $total = (int)$estadisticas['total_clases'];
                $estadisticas['porcentaje_asistencia'] = $total > 0 
                    ? round(($estadisticas['presentes'] / $total) * 100, 2)
                    : 0;
                    
                $estadisticas['porcentaje_fallas'] = $total > 0 
                    ? round(($estadisticas['faltas'] / $total) * 100, 2)
                    : 0;
                
                $estadisticas['porcentaje_tardanzas'] = $total > 0 
                    ? round(($estadisticas['tardanzas'] / $total) * 100, 2)
                    : 0;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $estadisticas
            ]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Valida los datos de entrada para el registro de asistencia
     */
    private function validarDatosAsistencia() {
        // Validar ficha_id
        if (!isset($_POST['ficha_id']) || !is_numeric($_POST['ficha_id'])) {
            throw new Exception('ID de ficha no válido');
        }
        
        // Validar estudiante_id
        if (!isset($_POST['estudiante_id']) || !is_numeric($_POST['estudiante_id'])) {
            throw new Exception('ID de estudiante no válido');
        }
        
        // Validar fecha
        $fecha = $_POST['fecha'] ?? date('Y-m-d');
        if (!strtotime($fecha)) {
            throw new Exception('Formato de fecha no válido');
        }
        
        // Validar estado
        $estado = strtolower(trim($_POST['estado'] ?? ''));
        // Normalizar variantes legacy a los nuevos estados textuales
        if (in_array($estado, ['no_asistio', 'falla', 'ausente'], true)) {
            $estado = Asistencia::ESTADO_NO_ASISTIO;
        } elseif (in_array($estado, ['tarde', 'tardanza'], true)) {
            $estado = Asistencia::ESTADO_TARDE;
        } elseif (in_array($estado, ['justificado', 'justificada'], true)) {
            $estado = Asistencia::ESTADO_JUSTIFICADO;
        } elseif ($estado === 'presente') {
            $estado = Asistencia::ESTADO_PRESENTE;
        }

        $estadosValidos = [
            Asistencia::ESTADO_PRESENTE,
            Asistencia::ESTADO_NO_ASISTIO,
            Asistencia::ESTADO_JUSTIFICADO,
            Asistencia::ESTADO_TARDE
        ];
        
        if (!in_array($estado, $estadosValidos)) {
            throw new Exception('Estado de asistencia no válido');
        }
        
        return [
            'ficha_id' => (int)$_POST['ficha_id'],
            'estudiante_id' => (int)$_POST['estudiante_id'],
            'fecha' => $fecha,
            'estado' => $estado,
            'observaciones' => $_POST['observaciones'] ?? null
        ];
    }

    /**
     * Devuelve los estudiantes de una ficha y el estado de asistencia del día indicado
     */
    public function obtenerEstudiantes() {
        header('Content-Type: application/json');
        try {
            $ficha_id = isset($_GET['ficha_id']) ? (int)$_GET['ficha_id'] : 0;
            $fecha = $_GET['fecha'] ?? date('Y-m-d');
            if ($ficha_id <= 0) { throw new Exception('ID de ficha no válido'); }
            if (!strtotime($fecha)) { throw new Exception('Fecha no válida'); }

            // Listar estudiantes de la ficha con su estado (si existe) para la fecha dada
            $estudiantes = $this->asistenciaModel->obtenerEstudiantesFichaFecha($ficha_id, $fecha);
            // El frontend espera un array directo
            echo json_encode($estudiantes);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Registra asistencias en lote desde un formulario del tablero (una fecha fija y múltiples estudiantes)
     */
    public function registrarLote() {
        header('Content-Type: application/json');
        try {
            // Aceptar JSON o application/x-www-form-urlencoded
            $payload = null;
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (stripos($contentType, 'application/json') !== false) {
                $raw = file_get_contents('php://input');
                $payload = json_decode($raw, true);
                if (!is_array($payload)) { throw new Exception('JSON no válido'); }
            } else {
                $payload = $_POST;
            }

            $ficha_id = $payload['ficha_id'] ?? null;
            $fecha = $payload['fecha'] ?? date('Y-m-d');
            $asistencias = $payload['asistencias'] ?? [];
            if (!$ficha_id || !is_numeric($ficha_id)) throw new Exception('ficha_id es requerido');
            if (!strtotime($fecha)) throw new Exception('Fecha no válida');
            if (!is_array($asistencias) || empty($asistencias)) throw new Exception('No hay asistencias a registrar');

            $profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
            $creado_por = $_SESSION['usuario']['id'] ?? null;

            // Validar que exista clase en curso para esta ficha y fecha (usa NOW entre fecha_inicio y fecha_fin)
            require_once __DIR__ . '/../config/db.php';
            $pdo = Database::conectar();
            $stmt = $pdo->prepare("SELECT id FROM horarios_fichas WHERE ficha_id = ? AND DATE(fecha_inicio) = ? AND NOW() BETWEEN fecha_inicio AND fecha_fin LIMIT 1");
            $stmt->execute([(int)$ficha_id, $fecha]);
            $enCurso = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$enCurso) {
                throw new Exception('NO TIENEN CLASE: cree una clase en el calendario y póngala en curso para registrar asistencia.');
            }

            // Derivar profesor_id si no está en sesión (compatibilidad esquemas usuario_id/usuario)
            if (!$profesor_id) {
                try {
                    try {
                        $st = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario_id = ? LIMIT 1");
                        $st->execute([$_SESSION['usuario']['id'] ?? 0]);
                        $profesor_id = $st->fetchColumn() ?: null;
                    } catch (\PDOException $e1) {
                        if ($e1->getCode() !== '42S22') { /* continuar fallback */ }
                        try {
                            $st2 = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario = ? LIMIT 1");
                            $st2->execute([$_SESSION['usuario']['id'] ?? 0]);
                            $profesor_id = $st2->fetchColumn() ?: null;
                        } catch (\PDOException $e2) { /* noop */ }
                    }
                } catch (\Throwable $_) { /* noop */ }
            }

            // Fallback adicional: si sigue sin resolverse, tomar el facilitador del bloque en curso de hoy
            if (!$profesor_id) {
                try {
                    $stH = $pdo->prepare("SELECT facilitador_id FROM horarios_fichas WHERE ficha_id = ? AND DATE(fecha_inicio) = ? AND NOW() BETWEEN fecha_inicio AND fecha_fin ORDER BY fecha_inicio DESC LIMIT 1");
                    $stH->execute([(int)$ficha_id, $fecha]);
                    $profesor_id = $stH->fetchColumn() ?: null;
                } catch (\Throwable $_) { /* noop */ }
            }

            $ok = true; $errores = [];
            $ausentes = []; // estudiantes marcados como no asistió/falla/ausente
            foreach ($asistencias as $estId => $datos) {
                $estudiante_id = $datos['estudiante_id'] ?? $estId;
                $estado = $datos['estado'] ?? '';
                $obs = $datos['observaciones'] ?? null;
                try {
                    $this->asistenciaModel->registrarAsistencia([
                        'ficha_id' => (int)$ficha_id,
                        'estudiante_id' => (int)$estudiante_id,
                        'profesor_id' => $profesor_id,
                        'fecha' => $fecha,
                        'hora_entrada' => date('H:i:s'),
                        'estado' => $estado,
                        'observaciones' => $obs,
                        'creado_por' => $creado_por
                    ]);
                    // Marcar para notificación si es ausencia
                    $estadoLower = strtolower((string)$estado);
                    if (in_array($estadoLower, ['no_asistio','ausente','falla'])) {
                        $ausentes[] = (int)$estudiante_id;
                    }
                } catch (Exception $ex) {
                    $ok = false;
                    $errores[] = [ 'estudiante_id' => $estudiante_id, 'error' => $ex->getMessage() ];
                }
            }

            // Ya no se notifica aquí; se notificará al finalizar la clase mediante endpoint dedicado

            if ($ok) {
                echo json_encode(['success' => true, 'message' => 'Asistencias registradas']);
            } else {
                http_response_code(207); // Multi-Status parcial
                echo json_encode(['success' => false, 'message' => 'Algunas asistencias no se pudieron registrar', 'errores' => $errores]);
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // Notificar ausentes del día cuando la clase ya finalizó
    public function notificarAusentesDia() {
        header('Content-Type: application/json');
        try {
            // Permitir JSON o form-data
            $payload = null; $ct = $_SERVER['CONTENT_TYPE'] ?? '';
            if (stripos($ct, 'application/json') !== false) {
                $raw = file_get_contents('php://input');
                $payload = json_decode($raw, true);
            } else { $payload = $_POST; }
            $ficha_id = isset($payload['ficha_id']) ? (int)$payload['ficha_id'] : 0;
            $fecha    = isset($payload['fecha']) ? (string)$payload['fecha'] : date('Y-m-d');
            if ($ficha_id <= 0 || !strtotime($fecha)) { throw new Exception('Datos inválidos'); }

            require_once __DIR__ . '/../config/db.php';
            $pdo = Database::conectar();
            // Verificar que la clase del día ya finalizó
            $stH = $pdo->prepare("SELECT id, fecha_fin FROM horarios_fichas WHERE ficha_id = ? AND DATE(fecha_inicio) = ? ORDER BY fecha_fin DESC LIMIT 1");
            $stH->execute([$ficha_id, $fecha]);
            $h = $stH->fetch(PDO::FETCH_ASSOC);
            if (!$h) { echo json_encode(['success'=>false,'message'=>'No hay clase programada ese día']); return; }
            $fin = strtotime($h['fecha_fin']);
            if ($fin === false || time() < $fin) {
                echo json_encode(['success'=>true,'message'=>'Clase aún en curso; no se notifica.']); return;
            }
            // Obtener usuarios ausentes (estado no_asistio) para ese día y ficha
            $stA = $pdo->prepare("SELECT estudiante_id FROM asistencias WHERE ficha_id = ? AND DATE(fecha) = ? AND estado_asistencia = 'no_asistio'");
            $stA->execute([$ficha_id, $fecha]);
            $uids = array_map('intval', $stA->fetchAll(PDO::FETCH_COLUMN));
            if (empty($uids)) { echo json_encode(['success'=>true,'message'=>'Sin ausentes que notificar']); return; }
            // Disparar notificaciones
            $this->notificarAusencias($ficha_id, $uids, $fecha);
            echo json_encode(['success'=>true,'message'=>'Notificaciones enviadas']);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
    }

    // Crea notificaciones individuales por estudiante ausente con CTA para iniciar proceso
    private function notificarAusencias(int $ficha_id, array $estudiantesIds, string $fecha) {
        require_once __DIR__ . '/../config/db.php';
        $pdo = Database::conectar();

        // Datos de ficha
        $stmtF = $pdo->prepare("SELECT id, nombre, numero FROM fichas WHERE id = ?");
        $stmtF->execute([$ficha_id]);
        $ficha = $stmtF->fetch(PDO::FETCH_ASSOC) ?: ['nombre' => 'Ficha'];

        // Destinatarios: asistentes (rol 4) y administradores (rol 1)
        $usuariosDestino = [];
        $qDest = $pdo->query("SELECT id FROM usuarios WHERE rol_id IN (1,4)");
        $usuariosDestino = $qDest->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (!$usuariosDestino) return; // no hay a quién notificar

        // Datos de estudiantes (por usuarios.id)
        if (empty($estudiantesIds)) return;
        $in = implode(',', array_fill(0, count($estudiantesIds), '?'));
        $stmtE = $pdo->prepare("SELECT u.id AS usuario_id, u.nombres, u.apellidos FROM usuarios u WHERE u.id IN ($in)");
        $stmtE->execute($estudiantesIds);
        $estudiantes = $stmtE->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($estudiantes as $est) {
            $aprendiz = trim(($est['nombres'] ?? '') . ' ' . ($est['apellidos'] ?? ''));
            $titulo = 'Ausencia registrada: ' . $aprendiz;
            $mensaje = sprintf(
                "El aprendiz %s no asistió a la ficha %s el %s.",
                htmlspecialchars($aprendiz), htmlspecialchars($ficha['nombre']), htmlspecialchars($fecha)
            );
            foreach ($usuariosDestino as $uId) {
                $stmtNF = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje) VALUES (?, ?, ?)");
                try { $stmtNF->execute([(int)$uId, $titulo, $mensaje]); } catch (Exception $e2) { /* noop */ }
            }
        }
    }
}

// Inicializar el controlador si se accede directamente
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    new AsistenciaController();
}
