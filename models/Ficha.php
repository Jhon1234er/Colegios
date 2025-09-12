<?php
require_once __DIR__ . '/../config/db.php';

class Ficha {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::conectar();
    }

    // Crear ficha y relacionarla con el profesor que la creó
    public function guardar($nombre, $numero, $cupo_total, $profesor_id, $dias_semana = []) {
        try {
            $this->pdo->beginTransaction();

            // 🔑 generar token único
            $token = bin2hex(random_bytes(16)); 

            // Convertir días de semana a JSON
            $dias_json = !empty($dias_semana) ? json_encode($dias_semana) : json_encode(['lunes', 'martes', 'miercoles', 'jueves', 'viernes']);

            // 1️⃣ Insertar ficha
            $stmt = $this->pdo->prepare("
                INSERT INTO fichas (nombre, numero, cupo_total, cupo_usado, token, estado, dias_semana) 
                VALUES (?, ?, ?, 0, ?, 'activa', ?)
            ");
            $stmt->execute([$nombre, $numero, $cupo_total, $token, $dias_json]);

            // Obtener el ID de la ficha creada
            $ficha_id = $this->pdo->lastInsertId();

            // 2️⃣ Verificar que el profesor exista
            $stmtCheck = $this->pdo->prepare("SELECT id FROM profesores WHERE id = ?");
            $stmtCheck->execute([$profesor_id]);
            $profesor = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$profesor) {
                throw new Exception("❌ Error: El profesor con ID {$profesor_id} no existe en la tabla profesores.");
            }

            // 3️⃣ Insertar relación en profesor_ficha
            $stmt2 = $this->pdo->prepare("
                INSERT INTO profesor_ficha (profesor_id, ficha_id) 
                VALUES (?, ?)
            ");
            $stmt2->execute([$profesor_id, $ficha_id]);

            $this->pdo->commit();
            return $ficha_id;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            die("❌ Error al guardar ficha: " . $e->getMessage());
        }
    }

    // 🔹 Obtener todas las fichas (para admin, no filtra)
    public function obtenerTodas() {
        $stmt = $this->pdo->prepare("
            SELECT id, nombre, numero, cupo_total, cupo_usado, estado, token
            FROM fichas
            ORDER BY id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 🔹 Obtener fichas solo de un profesor
    public function obtenerTodasPorProfesor($profesor_id) {
        $stmt = $this->pdo->prepare("
            SELECT f.id, f.nombre, f.numero, f.cupo_total, f.cupo_usado, f.estado, f.token, f.dias_semana
            FROM fichas f
            INNER JOIN profesor_ficha pf ON f.id = pf.ficha_id
            WHERE pf.profesor_id = ?
            ORDER BY f.id DESC
        ");
        $stmt->execute([$profesor_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Contar fichas
    public function contarFichas() {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM fichas");
        return (int) $stmt->fetchColumn();
    }

    // Obtener ficha por ID
    public function obtenerPorId($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM fichas WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 🔎 Buscar ficha por token (para el link compartido sin login)
    public function buscarPorToken($token) {
        $stmt = $this->pdo->prepare("SELECT * FROM fichas WHERE token = ? AND estado = 'activa'");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Actualizar cupo usado (cuando se registra un aprendiz)
    public function incrementarCupo($id) {
        $stmt = $this->pdo->prepare("
            UPDATE fichas 
            SET cupo_usado = cupo_usado + 1 
            WHERE id = ? AND cupo_usado < cupo_total
        ");
        return $stmt->execute([$id]);
    }

    // Cambiar estado de ficha
    public function actualizarEstado($id, $estado) {
        $stmt = $this->pdo->prepare("UPDATE fichas SET estado = ? WHERE id = ?");
        return $stmt->execute([$estado, $id]);
    }

    // Obtener fichas por colegio (a través de profesores)
    public function obtenerPorColegio($colegioId) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT f.id, f.nombre, f.numero
            FROM fichas f
            INNER JOIN profesor_ficha pf ON f.id = pf.ficha_id
            INNER JOIN profesores p ON pf.profesor_id = p.id
            WHERE p.colegio_id = ?
            ORDER BY f.nombre ASC, f.numero ASC
        ");
        $stmt->execute([$colegioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // Obtener fichas compartidas aceptadas para un profesor
    public function obtenerCompartidasAceptadas($usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT f.id, f.nombre, f.numero, f.cupo_total, f.cupo_usado, f.estado, f.dias_semana,
                   u_lider.nombres as lider_nombres, u_lider.apellidos as lider_apellidos,
                   'compartida' as tipo_ficha
            FROM fichas f
            INNER JOIN fichas_compartidas fc ON f.id = fc.ficha_id
            INNER JOIN profesores p_compartido ON fc.profesor_compartido_id = p_compartido.id
            INNER JOIN profesores p_lider ON fc.profesor_lider_id = p_lider.id
            INNER JOIN usuarios u_lider ON p_lider.usuario_id = u_lider.id
            WHERE p_compartido.usuario_id = ? AND fc.estado = 'aceptada'
            ORDER BY f.nombre ASC, f.numero ASC
        ");
        $stmt->execute([$usuario_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
