<?php
require_once __DIR__ . '/../config/db.php';

class ColegioMateria {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::conectar();
    }

    public function asignarMaterias($colegioId, $materias) {
        // Borrar anteriores
        $stmt = $this->pdo->prepare("DELETE FROM colegio_materia WHERE colegio_id = ?");
        $stmt->execute([$colegioId]);

        // Insertar nuevas
        $stmt = $this->pdo->prepare("INSERT INTO colegio_materia (colegio_id, materia_id) VALUES (?, ?)");
        foreach ($materias as $materiaId) {
            $stmt->execute([$colegioId, $materiaId]);
        }
    }

    public function obtenerMateriasPorColegio($colegioId) {
        $stmt = $this->pdo->prepare("
            SELECT m.id, m.nombre
            FROM materias m
            JOIN colegio_materia cm ON m.id = cm.materia_id
            WHERE cm.colegio_id = ?
        ");
        $stmt->execute([$colegioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
