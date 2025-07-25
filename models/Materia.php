<?php
require_once __DIR__ . '/../config/db.php';

class Materia {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::conectar();
    }

    public function obtenerTodas() {
        $stmt = $this->pdo->query("SELECT * FROM materias");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardar($nombre, $codigo) {
        $stmt = $this->pdo->prepare("INSERT INTO materias (nombre, codigo) VALUES (?, ?)");
        return $stmt->execute([$nombre, $codigo]);
    }
    public function obtenerPorId($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM materias WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function eliminar($id) {
        $stmt = $this->pdo->prepare("DELETE FROM materias WHERE id = ?");
        return $stmt->execute([$id]);
    }
    public function obtenerPorColegio($colegioId) {
        $pdo = Database::conectar();
        $stmt = $pdo->prepare("
            SELECT m.id, m.nombre
            FROM materias m
            JOIN colegio_materia cm ON m.id = cm.materia_id
            WHERE cm.colegio_id = ?
        ");
        $stmt->execute([$colegioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function contarMaterias() {
        $pdo = Database::conectar();
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM materias");
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
}
