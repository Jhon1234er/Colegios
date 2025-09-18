<?php
require_once __DIR__ . '/../config/db.php';

class Materia {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::conectar();
    }

    public function guardar(array $data): bool {
        $stmt = $this->pdo->prepare("INSERT INTO materias (codigo, denominacion, duracion, version, linea_tecnoacademia, nombre, descripcion) VALUES (?,?,?,?,?,?,?)");
        $nombre = $data['nombre'] ?? $data['denominacion'] ?? '';
        return $stmt->execute([
            $data['codigo'] ?? null,
            $data['denominacion'] ?? null,
            $data['duracion'] ?? null,
            isset($data['version']) ? (int)$data['version'] : null,
            $data['linea_tecnoacademia'] ?? null,
            $nombre,
            $data['descripcion'] ?? null
        ]);
    }

    public function guardarMultiple(array $rows): int {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO materias (codigo, denominacion, duracion, version, linea_tecnoacademia, nombre) VALUES (?,?,?,?,?,?)");
            $count = 0;
            foreach ($rows as $r) {
                $codigo = $r['codigo'] ?? null;
                $denom  = $r['denominacion'] ?? null;
                $dur    = $r['duracion'] ?? null;
                $ver    = isset($r['version']) ? (int)$r['version'] : null;
                $linea  = $r['linea_tecnoacademia'] ?? null;
                $nombre = $r['nombre'] ?? $denom;
                $stmt->execute([$codigo, $denom, $dur, $ver, $linea, $nombre]);
                $count++;
            }
            $this->pdo->commit();
            return $count;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function obtenerTodas(): array {
        $stmt = $this->pdo->query("SELECT * FROM materias ORDER BY nombre");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPaginado(int $limit, int $offset): array {
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        $stmt = $this->pdo->prepare("SELECT * FROM materias ORDER BY nombre LIMIT ? OFFSET ?");
        // bindValue con tipos enteros
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarConFiltros(?string $q, ?string $estado, ?string $linea): int {
        $sql = "SELECT COUNT(*) FROM materias WHERE 1=1";
        $params = [];
        if ($q !== null && $q !== '') {
            $sql .= " AND (codigo LIKE ? OR denominacion LIKE ? OR nombre LIKE ?)";
            $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
        }
        if ($estado !== null && $estado !== '') {
            $sql .= " AND estado = ?"; $params[] = $estado;
        }
        if ($linea !== null && $linea !== '') {
            $sql .= " AND linea_tecnoacademia = ?"; $params[] = $linea;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function obtenerPaginadoConFiltros(?string $q, ?string $estado, ?string $linea, int $limit, int $offset): array {
        $sql = "SELECT * FROM materias WHERE 1=1";
        $params = [];
        if ($q !== null && $q !== '') {
            $sql .= " AND (codigo LIKE ? OR denominacion LIKE ? OR nombre LIKE ?)";
            $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
        }
        if ($estado !== null && $estado !== '') {
            $sql .= " AND estado = ?"; $params[] = $estado;
        }
        if ($linea !== null && $linea !== '') {
            $sql .= " AND linea_tecnoacademia = ?"; $params[] = $linea;
        }
        $sql .= " ORDER BY nombre LIMIT ? OFFSET ?";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $i => $val) { $stmt->bindValue($i+1, $val, PDO::PARAM_STR); }
        $stmt->bindValue(count($params)+1, max(1,$limit), PDO::PARAM_INT);
        $stmt->bindValue(count($params)+2, max(0,$offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerLineasDistinct(): array {
        $stmt = $this->pdo->query("SELECT DISTINCT linea_tecnoacademia AS linea FROM materias WHERE linea_tecnoacademia IS NOT NULL AND linea_tecnoacademia <> '' ORDER BY linea");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function obtenerPorId($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM materias WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function actualizar($id, array $data) {
        $stmt = $this->pdo->prepare("UPDATE materias SET codigo=?, denominacion=?, duracion=?, version=?, linea_tecnoacademia=?, nombre=?, descripcion=? WHERE id = ?");
        $nombre = $data['nombre'] ?? $data['denominacion'] ?? '';
        return $stmt->execute([
            $data['codigo'] ?? null,
            $data['denominacion'] ?? null,
            $data['duracion'] ?? null,
            isset($data['version']) ? (int)$data['version'] : null,
            $data['linea_tecnoacademia'] ?? null,
            $nombre,
            $data['descripcion'] ?? null,
            $id
        ]);
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

    // Estado: activa/suspendida
    public function suspender(int $id): bool {
        try {
            $this->pdo->beginTransaction();
            // Cambiar estado de la materia
            $stmt = $this->pdo->prepare("UPDATE materias SET estado='suspendida' WHERE id = ?");
            $stmt->execute([$id]);

            // Nota: No se eliminan relaciones. La suspensión se controla por estado
            // y debe respetarse en los listados/acciones que consulten materias.

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function activar(int $id): bool {
        $stmt = $this->pdo->prepare("UPDATE materias SET estado='activa' WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
