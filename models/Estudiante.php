<?php
require_once __DIR__ . '/../config/db.php';

class Estudiante {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::conectar();
    }

    // -------------------------
    // GUARDAR ESTUDIANTE
    // -------------------------
    public function guardar($datos) {
        try {
            $this->pdo->beginTransaction();

            // Insertar en usuarios
            $stmtUsuario = $this->pdo->prepare("
                INSERT INTO usuarios 
                (nombres, apellidos, tipo_documento, numero_documento, correo_electronico, telefono,
                 fecha_nacimiento, genero, password_hash, rol_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtUsuario->execute([
                $datos['nombres'],
                $datos['apellidos'],
                $datos['tipo_documento'],
                $datos['numero_documento'],
                $datos['correo_electronico'],
                $datos['telefono'],
                $datos['fecha_nacimiento'],
                $datos['genero'],
                password_hash($datos['password'], PASSWORD_DEFAULT),
                3 // rol estudiante
            ]);

            $usuario_id = $this->pdo->lastInsertId();

            // Insertar en estudiantes
            $stmtEstudiante = $this->pdo->prepare("
                INSERT INTO estudiantes (
                    usuario_id, colegio_id, ficha_id, grado, grupo, jornada, fecha_ingreso,
                    nombre_completo_acudiente, tipo_documento_acudiente, numero_documento_acudiente,
                    telefono_acudiente, parentesco, ocupacion, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtEstudiante->execute([
                $usuario_id,
                $datos['colegio_id'],
                $datos['ficha_id'],
                $datos['grado'],
                $datos['grupo'],
                $datos['jornada'],
                $datos['fecha_ingreso'],
                $datos['nombre_completo_acudiente'],
                $datos['tipo_documento_acudiente'],
                $datos['numero_documento_acudiente'],
                $datos['telefono_acudiente'],
                $datos['parentesco'],
                $datos['ocupacion'],
                $datos['estado'] ?? 'Activo'
            ]);

            // 🔹 Actualizar el cupo usado de la ficha
            $stmtCupo = $this->pdo->prepare("
                UPDATE fichas
                SET cupo_usado = cupo_usado + 1
                WHERE id = ?
            ");
            $stmtCupo->execute([$datos['ficha_id']]);

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            die("❌ Error al guardar estudiante: " . $e->getMessage());
        }
    }

    // -------------------------
    // OBTENER TODOS (opcional por ficha)
    // -------------------------
    public function obtenerTodos($ficha_id = null) {
        if ($ficha_id === null) {
            $stmt = $this->pdo->query("
                SELECT 
                    e.id,
                    u.nombres,
                    u.apellidos,
                    CONCAT(u.nombres,' ',u.apellidos) AS nombre_completo,
                    u.tipo_documento,
                    u.numero_documento,
                    u.correo_electronico,
                    u.telefono,
                    e.grado,
                    e.grupo,
                    e.jornada,
                    e.fecha_ingreso,
                    e.estado,
                    c.nombre AS colegio,
                    f.nombre AS ficha,
                    e.nombre_completo_acudiente,
                    e.tipo_documento_acudiente,
                    e.numero_documento_acudiente,
                    e.telefono_acudiente,
                    e.parentesco,
                    e.ocupacion,
                    e.ficha_id
                FROM estudiantes e
                INNER JOIN usuarios u ON e.usuario_id = u.id
                INNER JOIN colegios c ON e.colegio_id = c.id
                INNER JOIN fichas f ON f.id = e.ficha_id
                ORDER BY f.nombre, u.apellidos, u.nombres
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT 
                    e.id,
                    u.nombres,
                    u.apellidos,
                    CONCAT(u.nombres,' ',u.apellidos) AS nombre_completo,
                    u.tipo_documento,
                    u.numero_documento,
                    u.correo_electronico,
                    u.telefono,
                    e.grado,
                    e.grupo,
                    e.jornada,
                    e.fecha_ingreso,
                    e.estado,
                    c.nombre AS colegio,
                    f.nombre AS ficha,
                    e.nombre_completo_acudiente,
                    e.tipo_documento_acudiente,
                    e.numero_documento_acudiente,
                    e.telefono_acudiente,
                    e.parentesco,
                    e.ocupacion,
                    e.ficha_id
                FROM estudiantes e
                INNER JOIN usuarios u ON e.usuario_id = u.id
                INNER JOIN colegios c ON e.colegio_id = c.id
                INNER JOIN fichas f ON f.id = e.ficha_id
                WHERE e.ficha_id = ?
                ORDER BY u.apellidos, u.nombres
            ");
            $stmt->execute([$ficha_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // -------------------------
    // CONTAR ESTUDIANTES
    // -------------------------
    public function contarEstudiantes() {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS total FROM estudiantes");
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    // -------------------------
    // POR COLEGIO
    // -------------------------
    public function obtenerPorColegio($colegioId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                CONCAT(u.nombres,' ',u.apellidos) AS nombre_completo,
                u.tipo_documento,
                u.numero_documento,
                e.grado,
                e.jornada,
                e.estado,
                f.nombre AS ficha,
                e.nombre_completo_acudiente,
                e.telefono_acudiente,
                e.parentesco
            FROM estudiantes e
            INNER JOIN usuarios u ON e.usuario_id = u.id
            INNER JOIN fichas f ON e.ficha_id = f.id
            WHERE e.colegio_id = ?
            ORDER BY f.nombre, u.apellidos, u.nombres
        ");
        $stmt->execute([$colegioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -------------------------
    // BUSCAR POR NOMBRE
    // -------------------------
    public function buscarPorNombre($q) {
        $stmt = $this->pdo->prepare("
            SELECT 
                CONCAT(u.nombres,' ',u.apellidos) AS nombre_completo,
                e.grado,
                e.jornada,
                e.estado,
                f.nombre AS ficha,
                e.nombre_completo_acudiente,
                e.telefono_acudiente,
                e.parentesco
            FROM estudiantes e
            INNER JOIN usuarios u ON e.usuario_id = u.id
            INNER JOIN fichas f ON e.ficha_id = f.id
            WHERE u.nombres LIKE ? OR u.apellidos LIKE ?
        ");
        $searchTerm = '%' . $q . '%';
        $stmt->execute([$searchTerm, $searchTerm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
