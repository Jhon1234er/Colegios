<?php
require_once __DIR__ . '/../config/db.php';

class Colegio {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::conectar();
    }

    public function obtenerTodos() {
        $stmt = $this->pdo->query("SELECT * FROM colegios");
        $colegios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($colegios as &$colegio) {
            $colegio['materias'] = $this->obtenerMateriasPorColegio($colegio['id']);
        }

        return $colegios;
    }

    public function obtenerMateriasPorColegio($colegio_id) {
        $stmt = $this->pdo->prepare("
            SELECT m.id, m.nombre
            FROM materias m
            INNER JOIN colegio_materia cm ON m.id = cm.materia_id
            WHERE cm.colegio_id = ?
        ");
        $stmt->execute([$colegio_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardar($datos, $materias = []) {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("INSERT INTO colegios 
                (nombre, codigo_dane, nit, tipo_institucion, direccion, telefono, correo, municipio, departamento)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $datos['nombre'],
                $datos['codigo_dane'],
                $datos['nit'],
                $datos['tipo_institucion'],
                $datos['direccion'],
                $datos['telefono'],
                $datos['correo'],
                $datos['municipio'],
                $datos['departamento']
            ]);

            $colegio_id = $this->pdo->lastInsertId();

            if (!empty($materias)) {
                $stmtMateria = $this->pdo->prepare("INSERT INTO colegio_materia (colegio_id, materia_id) VALUES (?, ?)");
                foreach ($materias as $materia_id) {
                    $stmtMateria->execute([$colegio_id, $materia_id]);
                }
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function eliminar($id) {
        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare("DELETE FROM colegio_materia WHERE colegio_id = ?")->execute([$id]);
            $this->pdo->prepare("DELETE FROM colegios WHERE id = ?")->execute([$id]);
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function obtenerPorId($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM colegios WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
