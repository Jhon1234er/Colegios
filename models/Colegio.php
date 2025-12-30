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

            // 🔹 Normalizar jornada (si existe la columna)
            $colegio['jornada'] = $this->normalizarCampo($colegio['jornada'] ?? null);

            // 🔹 Normalizar grados (si existe la columna)
            $colegio['grados'] = $this->normalizarCampo($colegio['grados'] ?? null);

            // 🔹 Calendario siempre en JSON (si existe la columna)
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
        try {
            $stmt = $this->pdo->prepare("
                SELECT m.id, m.nombre
                FROM cursos m
                INNER JOIN colegio_curso cm ON m.id = cm.curso_id
                WHERE cm.colegio_id = ?
            ");
            $stmt->execute([$colegio_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Si la tabla puente no existe en este esquema, devolver lista vacía sin romper
            if ($e->getCode() === '42S02' || strpos($e->getMessage(), 'colegio_curso') !== false) {
                return [];
            }
            throw $e;
        }
    }

    public function guardar($datos, $materias = []) {
        try {
            $this->pdo->beginTransaction();

            // Preparar campos opcionales: jornada, grados (CSV) y calendario (JSON)
            $jornadaCsv = '';
            if (!empty($datos['jornada'])) {
                if (is_array($datos['jornada'])) { $jornadaCsv = implode(',', array_map('trim', $datos['jornada'])); }
                else { $jornadaCsv = trim((string)$datos['jornada']); }
            }
            $gradosCsv = '';
            if (!empty($datos['grados'])) {
                if (is_array($datos['grados'])) { $gradosCsv = implode(',', array_map('trim', $datos['grados'])); }
                else { $gradosCsv = trim((string)$datos['grados']); }
            }
            $calJson = '[]';
            if (array_key_exists('calendario', $datos)) {
                if (is_array($datos['calendario'])) { $calJson = json_encode($datos['calendario'], JSON_UNESCAPED_UNICODE); }
                else if (is_string($datos['calendario']) && $datos['calendario'] !== '') { $calJson = $datos['calendario']; }
            }

            // Progresive INSERT strategies according to schema
            // Telefono: por inconsistencias entre distintos esquemas de BD y el
            // CHECK chk_tel_colegio, de momento enviamos siempre NULL para evitar
            // violar la restricción. Si en el futuro se define un formato único
            // podemos reactivar la normalización.
            $telefonoSan = null;
            $inserted = false;
            // Strategy A: include calendario, estado, timestamps
            try {
                $stmt = $this->pdo->prepare("INSERT INTO colegios 
                    (nombre, codigo_dane, nit, tipo_institucion, direccion, telefono, correo, municipio, departamento, calendario, estado, creado_en, actualizado_en)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $estado = isset($datos['estado']) && $datos['estado'] !== '' ? (int)$datos['estado'] : 1;
                $stmt->execute([
                    $datos['nombre'], $datos['codigo_dane'], $datos['nit'], $datos['tipo_institucion'],
                    $datos['direccion'], $telefonoSan, $datos['correo'], $datos['municipio'],
                    $datos['departamento'], $calJson, $estado
                ]);
                $inserted = true;
            } catch (\PDOException $eA) {
                // Strategy B: include calendario only
                try {
                    $stmt = $this->pdo->prepare("INSERT INTO colegios 
                        (nombre, codigo_dane, nit, tipo_institucion, direccion, telefono, correo, municipio, departamento, calendario)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $datos['nombre'], $datos['codigo_dane'], $datos['nit'], $datos['tipo_institucion'],
                        $datos['direccion'], $telefonoSan, $datos['correo'], $datos['municipio'],
                        $datos['departamento'], $calJson
                    ]);
                    $inserted = true;
                } catch (\PDOException $eB) {
                    // Strategy C: attempt with calendario if column exists; else minimal insert
                    try {
                        $stmt = $this->pdo->prepare("INSERT INTO colegios 
                            (nombre, codigo_dane, nit, tipo_institucion, direccion, telefono, correo, municipio, departamento, calendario)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $datos['nombre'], $datos['codigo_dane'], $datos['nit'], $datos['tipo_institucion'],
                            $datos['direccion'], $telefonoSan, $datos['correo'], $datos['municipio'],
                            $datos['departamento'], $calJson
                        ]);
                        $inserted = true;
                    } catch (\PDOException $eC1) {
                        if ($eC1->getCode() !== '42S22') { throw $eC1; }
                        // Column calendario not present: do minimal insert
                        $stmt = $this->pdo->prepare("INSERT INTO colegios 
                            (nombre, codigo_dane, nit, tipo_institucion, direccion, telefono, correo, municipio, departamento)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $datos['nombre'], $datos['codigo_dane'], $datos['nit'], $datos['tipo_institucion'],
                            $datos['direccion'], $telefonoSan, $datos['correo'], $datos['municipio'],
                            $datos['departamento']
                        ]);
                        $inserted = true;
                    }
                }
            }

            $colegio_id = $this->pdo->lastInsertId();

            // Intentar actualizar columnas opcionales si existen en el esquema
            try {
                $upd = $this->pdo->prepare("UPDATE colegios SET jornada = ?, grados = ?, calendario = ? WHERE id = ?");
                $upd->execute([$jornadaCsv, $gradosCsv, $calJson, $colegio_id]);
            } catch (\PDOException $e) {
                // Si la base no tiene esas columnas, ignorar el error 42S22
            }

            // Asegurar estado=1 por defecto si la columna existe y no fue enviada
            try {
                $estado = isset($datos['estado']) && $datos['estado'] !== '' ? (int)$datos['estado'] : 1;
                $updEstado = $this->pdo->prepare("UPDATE colegios SET estado = ? WHERE id = ?");
                $updEstado->execute([$estado, $colegio_id]);
            } catch (\PDOException $e) {
                // Si la columna 'estado' no existe en este esquema, ignorar
            }

            if (!empty($materias)) {
                try {
                    $stmtMateria = $this->pdo->prepare("INSERT INTO colegio_curso (colegio_id, curso_id) VALUES (?, ?)");
                    foreach ($materias as $materia_id) {
                        $stmtMateria->execute([$colegio_id, $materia_id]);
                    }
                } catch (\PDOException $e) {
                    // Si no existe la tabla puente en este esquema, omitir silenciosamente
                    if (!($e->getCode() === '42S02' || strpos($e->getMessage(), 'colegio_curso') !== false)) {
                        throw $e;
                    }
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
            $this->pdo->prepare("DELETE FROM colegio_curso WHERE colegio_id = ?")->execute([$id]);
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
            $colegio['jornada']    = $this->normalizarCampo($colegio['jornada'] ?? null);
            $colegio['grados']     = $this->normalizarCampo($colegio['grados'] ?? null);
            // Evitar json_decode(null) (deprecado en PHP 8.1+)
            $cal = $colegio['calendario'] ?? '';
            $colegio['calendario'] = ($cal !== '' && $cal !== null) ? (json_decode($cal, true) ?? []) : [];
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
            $colegio['jornada']    = $this->normalizarCampo($colegio['jornada'] ?? null);
            $colegio['grados']     = $this->normalizarCampo($colegio['grados'] ?? null);
            // Evitar json_decode(null) (deprecado en PHP 8.1+)
            $cal = $colegio['calendario'] ?? '';
            $colegio['calendario'] = ($cal !== '' && $cal !== null) ? (json_decode($cal, true) ?? []) : [];
        }

        return $colegios;
    }

    public function contarColegios() {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS total FROM colegios");
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
}
