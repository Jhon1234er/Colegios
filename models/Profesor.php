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
                u.correo_electronico, u.telefono, c.nombre AS colegio,
                p.id AS profesor_id,
                p.titulo_academico, p.especialidad, p.fecha_ingreso
            FROM profesores p
            JOIN usuarios u ON p.usuario_id = u.id
            JOIN colegios c ON p.colegio_id = c.id
            ORDER BY p.fecha_ingreso DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerMateriasPorProfesor($profesor_id) {
        $stmt = $this->pdo->prepare("
            SELECT m.nombre 
            FROM materia_profesor pm
            JOIN materias m ON pm.materia_id = m.id
            WHERE pm.profesor_id = ?
        ");
        $stmt->execute([$profesor_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function guardar($datos) {
        try {
            $this->pdo->beginTransaction();

            // Insertar usuario
            $stmtUsuario = $this->pdo->prepare("INSERT INTO usuarios 
                (nombres, apellidos, tipo_documento, numero_documento, correo_electronico, telefono, fecha_nacimiento, genero, password_hash, rol_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?,?)");

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
                2 // rol_id fijo para profesor
            ]);

            $usuario_id = $this->pdo->lastInsertId();

            // Insertar profesor
            $stmtProfesor = $this->pdo->prepare("INSERT INTO profesores
                (usuario_id, colegio_id, titulo_academico, especialidad, fecha_ingreso,rh,correo_institucional,tip_contrato) 
                VALUES (?, ?, ?, ?, NOW(),?, ?, ?)");

            $stmtProfesor->execute([
                $usuario_id,
                $datos['colegio_id'],
                $datos['titulo_academico'],
                $datos['especialidad'],
                $datos['rh'],
                $datos['correo_institucional'],
                $datos['tip_contrato']
            ]);

            $profesor_id = $this->pdo->lastInsertId();

            // Insertar materias del profesor
            if (!empty($datos['materias']) && is_array($datos['materias'])) {
                $stmtMateria = $this->pdo->prepare("INSERT INTO materia_profesor (profesor_id, materia_id) VALUES (?, ?)");
                foreach ($datos['materias'] as $materia_id) {
                    $stmtMateria->execute([$profesor_id, $materia_id]);
                }
            }
            // Guardar fichas del profesor
            if (!empty($datos['fichas']) && is_array($datos['fichas'])) {
                $stmtFicha = $this->pdo->prepare("INSERT INTO profesor_ficha (profesor_id, ficha_id) VALUES (?, ?)");
                foreach ($datos['fichas'] as $ficha_id) {
                    $stmtFicha->execute([$profesor_id, $ficha_id]);
                }
            }


            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    public function contarProfesores() {
        $pdo = Database::conectar();
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM profesores");
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function obtenerPorColegio($colegioId) {
        $pdo = Database::conectar();
        $stmt = $pdo->prepare("
            SELECT CONCAT(u.nombres, ' ', u.apellidos) AS nombre, m.nombre AS materia
            FROM profesores p
            JOIN usuarios u ON p.usuario_id = u.id
            JOIN materia_profesor mp ON p.id = mp.profesor_id
            JOIN materias m ON mp.materia_id = m.id
            WHERE p.colegio_id = ?
        ");
        $stmt->execute([$colegioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function buscarPorNombre($q) {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.nombres, u.apellidos, u.tipo_documento, u.numero_documento,
                u.correo_electronico, u.telefono, c.nombre AS colegio,
                p.titulo_academico, p.especialidad, p.fecha_ingreso,
                p.tip_contrato,
                m.nombre AS materia
            FROM profesores p
            JOIN usuarios u ON p.usuario_id = u.id
            JOIN colegios c ON p.colegio_id = c.id
            LEFT JOIN materia_profesor mp ON p.id = mp.profesor_id
            LEFT JOIN materias m ON mp.materia_id = m.id
            WHERE u.nombres LIKE ? OR u.apellidos LIKE ?
            ORDER BY p.fecha_ingreso DESC
        ");
        $stmt->execute(['%' . $q . '%', '%' . $q . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function obtenerFichasPorProfesor($profesor_id) {
        $pdo = Database::conectar();
        $sql = "SELECT f.id, f.nombre 
                FROM fichas f
                INNER JOIN profesor_ficha pf ON f.id = pf.ficha_id
                WHERE pf.profesor_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$profesor_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


}
