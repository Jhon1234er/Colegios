<?php
require_once __DIR__ . '/../config/db.php';

class ColegioMateria {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::conectar();
    }

    public function guardar($colegio_id, $materia_id) {
        $stmt = $this->pdo->prepare("INSERT INTO colegio_materia (colegio_id, materia_id) VALUES (?, ?)");
        return $stmt->execute([$colegio_id, $materia_id]);
    }

    public function eliminarPorColegio($colegio_id) {
        $stmt = $this->pdo->prepare("DELETE FROM colegio_materia WHERE colegio_id = ?");
        return $stmt->execute([$colegio_id]);
    }

    public function obtenerMateriasPorColegio($colegio_id) {
        $stmt = $this->pdo->prepare("
            SELECT m.*
            FROM materias m
            JOIN colegio_materia cm ON cm.materia_id = m.id
            WHERE cm.colegio_id = ?
        ");
        $stmt->execute([$colegio_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
