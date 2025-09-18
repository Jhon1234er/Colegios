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
            
            // 🔹 Normalizar jornada
            $colegio['jornada'] = $this->normalizarCampo($colegio['jornada']);

            // 🔹 Normalizar grados
            $colegio['grados'] = $this->normalizarCampo($colegio['grados']);

            // 🔹 Calendario siempre en JSON (evitar pasar null a json_decode)
            $cal = $colegio['calendario'] ?? '';
            $colegio['calendario'] = ($cal !== '' && $cal !== null) ? (json_decode($cal, true) ?? []) : [];
        }

        return $colegios;
    }

    private function normalizarCampo($valor) {
        // Si está vacío
        if (!$valor) {
            return [];
        }

        // Intentar decodificar como JSON
        $json = json_decode($valor, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }

        // Si no era JSON, interpretarlo como lista separada por comas
        return array_map('trim', explode(',', $valor));
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
                (nombre, codigo_dane, nit, tipo_institucion, direccion, telefono, correo, municipio, departamento, jornada, grados, calendario)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // 🔹 Asegurar que se guarden como JSON
            $datos['jornada']    = json_encode($datos['jornada']);
            $datos['grados']     = json_encode($datos['grados']);
            $datos['calendario'] = json_encode($datos['calendario']);

            $stmt->execute([
                $datos['nombre'],
                $datos['codigo_dane'],
                $datos['nit'],
                $datos['tipo_institucion'],
                $datos['direccion'],
                $datos['telefono'],
                $datos['correo'],
                $datos['municipio'],
                $datos['departamento'],
                $datos['jornada'],
                $datos['grados'],
                $datos['calendario']
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
        $colegio = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($colegio) {
            $colegio['materias']   = $this->obtenerMateriasPorColegio($id);
            $colegio['jornada']    = $this->normalizarCampo($colegio['jornada']);
            $colegio['grados']     = $this->normalizarCampo($colegio['grados']);
            $colegio['calendario'] = json_decode($colegio['calendario'], true) ?? [];
        }

        return $colegio;
    }

    public function buscarPorNombre($q) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM colegios 
            WHERE nombre LIKE ? 
               OR codigo_dane LIKE ? 
               OR municipio LIKE ? 
               OR departamento LIKE ?
            ORDER BY nombre ASC
        ");
        $searchTerm = '%' . $q . '%';
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $colegios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($colegios as &$colegio) {
            $colegio['materias']   = $this->obtenerMateriasPorColegio($colegio['id']);
            $colegio['jornada']    = $this->normalizarCampo($colegio['jornada']);
            $colegio['grados']     = $this->normalizarCampo($colegio['grados']);
            $colegio['calendario'] = json_decode($colegio['calendario'], true) ?? [];
        }

        return $colegios;
    }

    public function contarColegios() {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS total FROM colegios");
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
}
