<?php
require_once __DIR__ . '/../config/db.php';

class Estudiante {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::conectar();
    }

    public function guardar($datos) {
        try {
            $this->pdo->beginTransaction();

            // Insertar en usuarios
            $stmtUsuario = $this->pdo->prepare("INSERT INTO usuarios (nombres, apellidos, tipo_documento, numero_documento, correo_electronico, telefono,fecha_nacimiento, genero, password_hash,  rol_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
                3 // Estudiante
            ]);

            $usuario_id = $this->pdo->lastInsertId();

            // Insertar en estudiantes
            $stmtEstudiante = $this->pdo->prepare("INSERT INTO estudiantes (
                usuario_id, colegio_id, ficha_id, grado, grupo, jornada, fecha_ingreso,
                nombre_completo_acudiente, tipo_documento_acudiente, numero_documento_acudiente,
                telefono_acudiente, parentesco, ocupacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )");

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
                $datos['ocupacion']
            ]);

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            die("❌ Error al guardar estudiante: " . $e->getMessage());
        }
    }

    public function obtenerTodos($ficha_id = null) {
        if ($ficha_id === null) {
            // Mostrar todos los estudiantes (solo para admin)
            $stmt = $this->pdo->query("
                SELECT 
                    e.id,
                    u.nombres,
                    u.apellidos,
                    u.tipo_documento,
                    u.numero_documento,
                    u.correo_electronico,
                    u.telefono,
                    e.grado,
                    e.grupo,
                    e.jornada,
                    e.fecha_ingreso,
                    c.nombre AS colegio,
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
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Mostrar solo por ficha
            $stmt = $this->pdo->prepare("
                SELECT 
                    e.id,
                    u.nombres,
                    u.apellidos,
                    u.tipo_documento,
                    u.numero_documento,
                    u.correo_electronico,
                    u.telefono,
                    e.grado,
                    e.grupo,
                    e.jornada,
                    e.fecha_ingreso,
                    c.nombre AS colegio,
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
                WHERE e.ficha_id = ?
            ");
            $stmt->execute([$ficha_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }



    public function contarEstudiantes() {
        $pdo = Database::conectar();
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM estudiantes");
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
public function obtenerPorColegio($colegioId) {
    $pdo = Database::conectar();
    $stmt = $pdo->prepare("
        SELECT 
            CONCAT(u.nombres, ' ', u.apellidos) AS nombre_completo,
            e.grado,
            e.jornada,
            f.nombre AS ficha,
            e.nombre_completo_acudiente,
            e.telefono_acudiente,
            e.parentesco
        FROM estudiantes e
        JOIN usuarios u ON e.usuario_id = u.id
        JOIN fichas f ON e.ficha_id = f.id
        WHERE e.colegio_id = ?
    ");
    $stmt->execute([$colegioId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    public function buscarPorNombre($q) {
        $stmt = $this->pdo->prepare("
            SELECT CONCAT(u.nombres, ' ', u.apellidos) AS nombre_completo, 
                e.grado, 
                e.jornada, 
                e.nombre_completo_acudiente, 
                e.telefono_acudiente, 
                e.parentesco,
                f.nombre AS ficha
            FROM estudiantes e
            JOIN usuarios u ON e.usuario_id = u.id
            JOIN fichas f ON e.ficha_id = f.id
            WHERE u.nombres LIKE ? OR u.apellidos LIKE ?
        ");
        $searchTerm = '%' . $q . '%';
        $stmt->execute([$searchTerm, $searchTerm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}