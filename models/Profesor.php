<?php
require_once __DIR__ . '/../config/db.php';

class Profesor {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::conectar();
    }

    public function obtenerTodos() {
        $stmt = $this->pdo->query("
            SELECT u.nombres, u.apellidos, u.tipo_documento, u.numero_documento,
                   u.correo_electronico, u.telefono,
                   p.id AS profesor_id,
                   p.titulo_academico, p.especialidad, p.fecha_ingreso,
                   p.rh, p.correo_institucional, p.tip_contrato,
                   c.nombre AS colegio
            FROM profesores p
            JOIN usuarios u ON p.usuario_id = u.id
            LEFT JOIN colegios c ON p.colegio_id = c.id
            ORDER BY p.fecha_ingreso DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardar($datos) {
        try {
            $this->pdo->beginTransaction();

            // 1. Insertar usuario
            $stmtUsuario = $this->pdo->prepare("
                INSERT INTO usuarios 
                (nombres, apellidos, tipo_documento, numero_documento, correo_electronico, telefono, fecha_nacimiento, genero, password_hash, rol_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $passwordHash = password_hash($datos['password'], PASSWORD_DEFAULT);

            $stmtUsuario->execute([
                $datos['nombres'],
                $datos['apellidos'],
                $datos['tipo_documento'],
                $datos['numero_documento'],
                $datos['correo_electronico'],
                $datos['telefono'],
                $datos['fecha_nacimiento'],
                $datos['genero'],
                $passwordHash,
                $datos['rol_id'] // siempre 2
            ]);

            $usuario_id = $this->pdo->lastInsertId();

            // 2. Insertar profesor
            $stmtProfesor = $this->pdo->prepare("
                INSERT INTO profesores
                (usuario_id, titulo_academico, especialidad, fecha_ingreso, rh, correo_institucional, tip_contrato) 
                VALUES (?, ?, ?, NOW(), ?, ?, ?)
            ");
            $stmtProfesor->execute([
                $usuario_id,
                $datos['titulo_academico'],
                $datos['especialidad'],
                $datos['rh'],
                $datos['correo_institucional'],
                $datos['tip_contrato']
            ]);

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function contarProfesores() {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS total FROM profesores");
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function buscarPorNombre($q) {
        $stmt = $this->pdo->prepare("
            SELECT u.nombres, u.apellidos, u.tipo_documento, u.numero_documento,
                   u.correo_electronico, u.telefono,
                   p.id AS id,
                   p.titulo_academico, p.especialidad, p.fecha_ingreso,
                   p.tip_contrato, p.rh, p.correo_institucional
            FROM profesores p
            JOIN usuarios u ON p.usuario_id = u.id
            WHERE u.nombres LIKE ? 
               OR u.apellidos LIKE ? 
               OR p.especialidad LIKE ?
               OR u.numero_documento LIKE ?
            ORDER BY u.apellidos ASC, u.nombres ASC
        ");
        $searchTerm = '%' . $q . '%';
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las fichas asignadas a un profesor con sus respectivos horarios
     * 
     * @param int $profesor_id ID del profesor
     * @return array Lista de fichas con información de horarios
     */
    public function obtenerFichasPorProfesor($profesor_id) {
        try {
            // Obtener las fichas asignadas al profesor
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT f.id, f.numero AS numero_ficha, f.nombre, f.jornada
                FROM fichas f
                INNER JOIN profesor_ficha pf ON f.id = pf.ficha_id
                WHERE pf.profesor_id = ?
                ORDER BY f.numero
            ");
            $stmt->execute([$profesor_id]);
            $fichas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Procesar cada ficha para agregar información de horarios
            return array_map(function($ficha) use ($profesor_id) {
                // Obtener los próximos horarios programados para esta ficha
                $stmt = $this->pdo->prepare("
                    SELECT 
                        id, 
                        DATE(fecha_inicio) as fecha,
                        TIME(fecha_inicio) as hora_inicio,
                        TIME(fecha_fin) as hora_fin,
                        fecha_inicio,
                        fecha_fin,
                        DAYOFWEEK(fecha_inicio) as dia_numero,
                        CASE 
                            WHEN DAYOFWEEK(fecha_inicio) = 1 THEN 'domingo'
                            WHEN DAYOFWEEK(fecha_inicio) = 2 THEN 'lunes'
                            WHEN DAYOFWEEK(fecha_inicio) = 3 THEN 'martes'
                            WHEN DAYOFWEEK(fecha_inicio) = 4 THEN 'miércoles'
                            WHEN DAYOFWEEK(fecha_inicio) = 5 THEN 'jueves'
                            WHEN DAYOFWEEK(fecha_inicio) = 6 THEN 'viernes'
                            WHEN DAYOFWEEK(fecha_inicio) = 7 THEN 'sábado'
                        END as dia_semana,
                        titulo,
                        aula,
                        color,
                        estado
                    FROM horarios_fichas 
                    WHERE ficha_id = ? 
                    AND profesor_id = ?
                    AND fecha_inicio >= CURDATE()
                    ORDER BY fecha_inicio
                    LIMIT 5  -- Limitar a los próximos 5 horarios para determinar días
                ");
                $stmt->execute([$ficha['id'], $profesor_id]);
                $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Procesar los horarios
                if (!empty($horarios)) {
                    // Extraer los días de la semana únicos
                    $dias_semana = array_values(array_unique(array_column($horarios, 'dia_semana')));
                    
                    // Usar el primer horario para los valores por defecto
                    $primerHorario = $horarios[0];
                    
                    $ficha['hora_inicio'] = $primerHorario['hora_inicio'];
                    $ficha['hora_fin'] = $primerHorario['hora_fin'];
                    $ficha['dia_semana'] = $primerHorario['dia_semana'];
                    $ficha['dias_semana'] = $dias_semana;
                    $ficha['horario_actual'] = $primerHorario;
                    $ficha['proximos_horarios'] = $horarios;
                } else {
                    // Valores por defecto si no hay horarios programados
                    $ficha['hora_inicio'] = '07:00:00';
                    $ficha['hora_fin'] = '17:00:00';
                    $ficha['dia_semana'] = 'lunes';
                    $ficha['dias_semana'] = ['lunes', 'miércoles', 'viernes']; // Días comunes de clase
                    $ficha['horario_actual'] = null;
                    $ficha['proximos_horarios'] = [];
                }
                
                return $ficha;
            }, $fichas);
            
        } catch (Exception $e) {
            error_log("Error en obtenerFichasPorProfesor: " . $e->getMessage());
            error_log("Trace: " . $e->getTraceAsString());
            return [];
        }
    }


    public function obtenerPorColegio($colegioId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.id AS profesor_id,
                u.nombres,
                u.apellidos,
                u.correo_electronico,
                u.telefono,
                p.correo_institucional,
                p.tip_contrato,
                m.nombre AS materia
            FROM profesores p
            JOIN usuarios u ON p.usuario_id = u.id
            LEFT JOIN materia_profesor mp ON p.id = mp.profesor_id
            LEFT JOIN materias m ON mp.materia_id = m.id
            WHERE p.colegio_id = ?
            ORDER BY u.apellidos, u.nombres
        ");
        $stmt->execute([$colegioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerMateriasPorProfesor($profesor_id) {
        $stmt = $this->pdo->prepare("
            SELECT m.nombre
            FROM materias m
            INNER JOIN materia_profesor mp ON m.id = mp.materia_id
            WHERE mp.profesor_id = ?
            ORDER BY m.nombre
        ");
        $stmt->execute([$profesor_id]);
        $materias = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $materias;
    }

    public function obtenerTodosExcepto($usuarioId) {
        $sql = "SELECT p.id, u.nombres, u.apellidos, p.tip_contrato 
                FROM profesores p 
                INNER JOIN usuarios u ON p.usuario_id = u.id 
                WHERE u.id != ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
