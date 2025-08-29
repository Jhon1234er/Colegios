<?php
require_once __DIR__ . '/../config/db.php';

class Ficha {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::conectar();
    }

    // Crear ficha y relacionarla con el profesor que la creó
    public function guardar($nombre, $numero, $cupo_total, $profesor_id) {
        try {
            $this->pdo->beginTransaction();

            // 🔑 generar token único
            $token = bin2hex(random_bytes(16)); 

            // 1️⃣ Insertar ficha
            $stmt = $this->pdo->prepare("
                INSERT INTO fichas (nombre, numero, cupo_total, cupo_usado, token, estado) 
                VALUES (?, ?, ?, 0, ?, 'activa')
            ");
            $stmt->execute([$nombre, $numero, $cupo_total, $token]);

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
            SELECT f.id, f.nombre, f.numero, f.cupo_total, f.cupo_usado, f.estado, f.token
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

    // Obtener ficha por token (para el link compartido)
    public function obtenerPorToken($token) {
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
}
