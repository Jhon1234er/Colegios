<?php
require_once __DIR__ . '/../models/Asistencia.php';
require_once __DIR__ . '/../helpers/auth.php';

class AsistenciaController {
    private $asistenciaModel;
    
    public function __construct() {
        $this->asistenciaModel = new Asistencia();
        
        // Verificar autenticación
        if (!isset($_SESSION['usuario'])) {
            http_response_code(401);
            echo json_encode(['error' => 'No autorizado. Por favor inicie sesión.']);
            exit;
        }
        
        // Manejar acciones AJAX
        $action = $_GET['action'] ?? '';
        
        if ($action === 'registrar') {
            $this->registrar();
        } elseif ($action === 'actualizar') {
            $this->actualizar();
        } elseif ($action === 'obtener_por_fecha') {
            $this->obtenerPorFecha();
        } elseif ($action === 'obtener_estadisticas') {
            $this->obtenerEstadisticas();
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
            
            // Registrar asistencia
            $resultado = $this->asistenciaModel->registrarAsistencia([
                'ficha_id' => $datos['ficha_id'],
                'estudiante_id' => $datos['estudiante_id'],
                'profesor_id' => $_SESSION['usuario']['profesor_id'],
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
            $estado = $_POST['estado'] ?? '';
            $observaciones = $_POST['observaciones'] ?? '';
            
            // Validar estado
            $estadosValidos = [
                Asistencia::ESTADO_PRESENTE,
                Asistencia::ESTADO_FALLA,
                Asistencia::ESTADO_JUSTIFICADA,
                Asistencia::ESTADO_TARDANZA
            ];
            
            if (!in_array($estado, $estadosValidos)) {
                throw new Exception('Estado de asistencia no válido');
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
        $estado = $_POST['estado'] ?? '';
        $estadosValidos = [
            Asistencia::ESTADO_PRESENTE,
            Asistencia::ESTADO_FALLA,
            Asistencia::ESTADO_JUSTIFICADA,
            Asistencia::ESTADO_TARDANZA
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
}

// Inicializar el controlador si se accede directamente
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    new AsistenciaController();
}
