<?php
require_once __DIR__ . '/../config/db.php';

class Estudiante {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::conectar();
    }

    // -------------------------
    // GUARDAR ESTUDIANTE (uso interno - profesores/admins)
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
    // GUARDAR ESTUDIANTE (uso público desde registro.php)
    // -------------------------
    public function guardarPublico($datos) {
        try {
            $this->pdo->beginTransaction();

            // 📌 Obtener colegio desde la ficha a través del profesor
            $stmtFicha = $this->pdo->prepare("
                SELECT p.colegio_id 
                FROM fichas f
                INNER JOIN profesor_ficha pf ON f.id = pf.ficha_id
                INNER JOIN profesores p ON pf.profesor_id = p.id
                WHERE f.id = ?
                LIMIT 1
            ");
            $stmtFicha->execute([$datos['ficha_id']]);
            $ficha = $stmtFicha->fetch(PDO::FETCH_ASSOC);

            if (!$ficha) {
                throw new Exception("Ficha no encontrada o sin profesor asignado para registro público.");
            }

            $colegio_id = $ficha['colegio_id'];

            // Generar contraseña automática = número de documento
            $passwordPlano = $datos['numero_documento'];
            $passwordHash  = password_hash($passwordPlano, PASSWORD_DEFAULT);

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
                $passwordHash,
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
                $colegio_id, // 🔹 se obtiene de la ficha
                $datos['ficha_id'],
                $datos['grado'],
                $datos['grupo'],
                $datos['jornada'],
                date('Y-m-d'), // ingreso automático hoy
                $datos['nombre_completo_acudiente'],
                $datos['tipo_documento_acudiente'],
                $datos['numero_documento_acudiente'],
                $datos['telefono_acudiente'],
                $datos['parentesco'],
                $datos['ocupacion'],
                'Activo'
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
            die("❌ Error al guardar estudiante público: " . $e->getMessage());
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
            INNER JOIN fichas f ON f.id = e.ficha_id
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
                e.id,
                u.nombres,
                u.apellidos,
                CONCAT(u.nombres,' ',u.apellidos) AS nombre_completo,
                u.tipo_documento,
                u.numero_documento,
                u.correo_electronico AS email,
                u.telefono,
                e.grado,
                e.grupo,
                e.jornada,
                e.fecha_ingreso,
                e.estado,
                c.nombre AS colegio_nombre,
                f.nombre AS ficha_nombre,
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
            WHERE u.nombres LIKE ? 
               OR u.apellidos LIKE ? 
               OR u.numero_documento LIKE ?
               OR f.nombre LIKE ?
               OR c.nombre LIKE ?
            ORDER BY u.apellidos ASC, u.nombres ASC
        ");
        $searchTerm = '%' . $q . '%';
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarPorFicha($ficha_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total FROM estudiantes WHERE ficha_id = ?");
        $stmt->execute([$ficha_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
}
