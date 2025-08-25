<?php
require_once __DIR__ . '/../config/db.php';

class Materia {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::conectar();
    }

    public function guardar(array $data): bool {
        $stmt = $this->pdo->prepare("INSERT INTO materias (nombre) VALUES (?)");
        return $stmt->execute([$data['nombre']]);
    }

    public function obtenerTodas(): array {
        $stmt = $this->pdo->query("SELECT * FROM materias ORDER BY nombre");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM materias WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function actualizarNombre($id, $nombre) {
        $stmt = $this->pdo->prepare("UPDATE materias SET nombre = ? WHERE id = ?");
        return $stmt->execute([$nombre, $id]);
    }

    public function eliminar($id) {
        $stmt = $this->pdo->prepare("DELETE FROM materias WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function contarMaterias() {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS total FROM materias");
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function obtenerPorColegio($colegioId): array {
        $stmt = $this->pdo->prepare("
            SELECT m.id, m.nombre
            FROM materias m
            INNER JOIN colegio_materia cm ON m.id = cm.materia_id
            WHERE cm.colegio_id = ?
            ORDER BY m.nombre
        ");
        $stmt->execute([$colegioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
