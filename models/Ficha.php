<?php
require_once __DIR__ . '/../config/db.php';

class Ficha {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::conectar();
    }

    public function guardar($nombre, $colegio_id) {
        $stmt = $this->pdo->prepare("INSERT INTO fichas (nombre, colegio_id) VALUES (?, ?)");
        return $stmt->execute([$nombre, $colegio_id]);
    }

    public function obtenerTodas() {
        $stmt = $this->pdo->prepare("
            SELECT f.id, f.nombre, c.nombre AS colegio
            FROM fichas f
            LEFT JOIN colegios c ON f.colegio_id = c.id
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}