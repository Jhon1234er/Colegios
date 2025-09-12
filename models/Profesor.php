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

    public function obtenerFichasPorProfesor($profesor_id) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT f.id, f.numero AS numero_ficha, f.nombre
            FROM fichas f
            INNER JOIN profesor_ficha pf ON f.id = pf.ficha_id
            WHERE pf.profesor_id = ?
            ORDER BY f.numero
        ");
        $stmt->execute([$profesor_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
