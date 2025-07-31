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
}