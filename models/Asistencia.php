<?php
class Asistencia {
    private $pdo;

    public function __construct() {
        require_once __DIR__ . '/../config/db.php';
        $this->pdo = Database::conectar();

        if (!$this->pdo) {
            throw new Exception('No se pudo conectar a la base de datos desde Asistencia.');
        }
    }

    // Estados normalizados usados por la UI y almacenados en BD
    public const ESTADO_PRESENTE     = 'presente';
    public const ESTADO_NO_ASISTIO   = 'no_asistio';
    public const ESTADO_TARDE        = 'tarde';
    public const ESTADO_JUSTIFICADO  = 'justificado';

    /**
     * Normaliza cualquier variante textual a uno de los estados canicos
     */
    private function normalizarEstado(string $estado): string {
        $e = strtolower(trim($estado));
        if (in_array($e, ['no_asistio', 'falla', 'ausente'], true)) {
            return self::ESTADO_NO_ASISTIO;
        }
        if (in_array($e, ['tarde', 'tardanza'], true)) {
            return self::ESTADO_TARDE;
        }
        if (in_array($e, ['justificado', 'justificada'], true)) {
            return self::ESTADO_JUSTIFICADO;
        }
        return self::ESTADO_PRESENTE;
    }

    /**
     * Estadísticas GLOBALes de fallas por ficha (todas las fichas de todos los colegios).
     * Devuelve el mismo formato que obtenerFallasPorFicha(), pero sin filtro por colegio.
     *
     * Estructura de retorno: [
     *   [
     *     'ficha_id'        => int,
     *     'ficha_nombre'    => string|null,
     *     'numero_ficha'    => string|int,
     *     'total_asistencias' => int,
     *     'total_fallas'      => int,
     *   ],
     *   ...
     * ]
     */
    public function obtenerFallasGlobales(): array {
        // Variante con estado_asistencia_id numérico
        try {
            $sql = "SELECT 
                        f.id AS ficha_id,
                        f.nombre AS ficha_nombre,
                        COALESCE(f.numero, f.id) AS numero_ficha,
                        COUNT(a.id) AS total_asistencias,
                        COUNT(CASE WHEN a.estado_asistencia_id IS NOT NULL AND a.estado_asistencia_id <> 1 THEN 1 END) AS total_fallas
                    FROM fichas f
                    LEFT JOIN asistencias a ON f.id = a.ficha_id
                    GROUP BY f.id, f.nombre, numero_ficha";
            $stmt = $this->pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows)) {
                return $rows;
            }
        } catch (\PDOException $e) {
            // 42S22 = Unknown column, 42S02 = tabla desconocida
            $code = $e->getCode();
            if ($code !== '42S22' && $code !== '42S02'
                && strpos($e->getMessage(), 'Unknown column') === false
                && strpos($e->getMessage(), 'Base table or view not found') === false) {
                throw $e;
            }
        }

        // Variante con estado_asistencia textual ('presente', 'no_asistio', etc.)
        try {
            $sql = "SELECT 
                        f.id AS ficha_id,
                        f.nombre AS ficha_nombre,
                        COALESCE(f.numero, f.id) AS numero_ficha,
                        COUNT(a.id) AS total_asistencias,
                        COUNT(CASE WHEN a.estado_asistencia IS NOT NULL AND a.estado_asistencia <> 'presente' THEN 1 END) AS total_fallas
                    FROM fichas f
                    LEFT JOIN asistencias a ON f.id = a.ficha_id
                    GROUP BY f.id, f.nombre, numero_ficha";
            $stmt = $this->pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows)) {
                return $rows;
            }
        } catch (\PDOException $e) {
            $code = $e->getCode();
            if ($code !== '42S22' && $code !== '42S02'
                && strpos($e->getMessage(), 'Unknown column') === false
                && strpos($e->getMessage(), 'Base table or view not found') === false) {
                throw $e;
            }
        }

        return [];
    }

    public function obtenerFallasPorFicha($colegio_id) {
        // Intentar en orden: ficha_colegio (nuevo esquema) -> fichas.colegio_id -> fichas.colegio -> puente con facilitadores (facilitador_ficha -> p.colegio_id) -> profesor_ficha
        // Siempre usar estados textuales: distinto de 'presente' cuenta como falla.
        // Además exponemos total_asistencias para saber cuándo una ficha aún no tiene registros.
        // 0) Esquema nuevo: tabla puente ficha_colegio
        try {
            $sql0 = "SELECT 
                        f.id AS ficha_id,
                        f.nombre AS ficha_nombre,
                        COALESCE(f.numero, f.id) AS numero_ficha,
                        COUNT(a.id) AS total_asistencias,
                        COUNT(CASE WHEN a.estado_asistencia_id IS NOT NULL AND a.estado_asistencia_id <> 1 THEN 1 END) AS total_fallas
                     FROM ficha_colegio fc
                     INNER JOIN fichas f ON f.id = fc.ficha_id
                     LEFT JOIN asistencias a ON f.id = a.ficha_id
                     WHERE fc.colegio_id = ?
                     GROUP BY f.id, f.nombre, numero_ficha";
            $stmt0 = $this->pdo->prepare($sql0);
            $stmt0->execute([$colegio_id]);
            $rows0 = $stmt0->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows0)) return $rows0;
        } catch (\PDOException $e0) {
            if (!in_array($e0->getCode(), ['42S22','42S02'], true)) { throw $e0; }
        }
        // 0a) Esquema nuevo con estado_asistencia textual
        try {
            $sql0a = "SELECT 
                        f.id AS ficha_id,
                        f.nombre AS ficha_nombre,
                        COALESCE(f.numero, f.id) AS numero_ficha,
                        COUNT(a.id) AS total_asistencias,
                        COUNT(CASE WHEN a.estado_asistencia IS NOT NULL AND a.estado_asistencia <> 'presente' THEN 1 END) AS total_fallas
                     FROM ficha_colegio fc
                     INNER JOIN fichas f ON f.id = fc.ficha_id
                     LEFT JOIN asistencias a ON f.id = a.ficha_id
                     WHERE fc.colegio_id = ?
                     GROUP BY f.id, f.nombre, numero_ficha";
            $stmt0a = $this->pdo->prepare($sql0a);
            $stmt0a->execute([$colegio_id]);
            $rows0a = $stmt0a->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows0a)) return $rows0a;
        } catch (\PDOException $e0a) {
            if (!in_array($e0a->getCode(), ['42S22','42S02'], true)) { throw $e0a; }
        }
        // 1) Por fichas.colegio_id
        try {
            $stmt = $this->pdo->prepare("SELECT f.id AS ficha_id, f.nombre AS ficha_nombre, COALESCE(f.numero, f.id) AS numero_ficha, COUNT(a.id) AS total_asistencias, COUNT(CASE WHEN a.estado_asistencia_id IS NOT NULL AND a.estado_asistencia_id <> 1 THEN 1 END) AS total_fallas FROM fichas f LEFT JOIN asistencias a ON f.id = a.ficha_id WHERE f.colegio_id = ? GROUP BY f.id, f.nombre, numero_ficha");
            $stmt->execute([$colegio_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows)) return $rows;
        } catch (\PDOException $e) {
            if (!in_array($e->getCode(), ['42S22','42S02'], true)) { throw $e; }
        }
        // 1a) Texto por fichas.colegio_id
        try {
            $stmt = $this->pdo->prepare("SELECT f.id AS ficha_id, f.nombre AS ficha_nombre, COALESCE(f.numero, f.id) AS numero_ficha, COUNT(a.id) AS total_asistencias, COUNT(CASE WHEN a.estado_asistencia IS NOT NULL AND a.estado_asistencia <> 'presente' THEN 1 END) AS total_fallas FROM fichas f LEFT JOIN asistencias a ON f.id = a.ficha_id WHERE f.colegio_id = ? GROUP BY f.id, f.nombre, numero_ficha");
            $stmt->execute([$colegio_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows)) return $rows;
        } catch (\PDOException $e) {
            if (!in_array($e->getCode(), ['42S22','42S02'], true)) { throw $e; }
        }
        // 2) Legacy: fichas.colegio
        try {
            $stmt = $this->pdo->prepare("SELECT f.id AS ficha_id, f.nombre AS ficha_nombre, COALESCE(f.numero, f.id) AS numero_ficha, COUNT(a.id) AS total_asistencias, COUNT(CASE WHEN a.estado_asistencia_id IS NOT NULL AND a.estado_asistencia_id <> 1 THEN 1 END) AS total_fallas FROM fichas f LEFT JOIN asistencias a ON f.id = a.ficha_id WHERE f.colegio = ? GROUP BY f.id, f.nombre, numero_ficha");
            $stmt->execute([$colegio_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows)) return $rows;
        } catch (\PDOException $e) {
            if (!in_array($e->getCode(), ['42S22','42S02'], true)) { throw $e; }
        }
        // 2a) Texto legacy: fichas.colegio
        try {
            $stmt = $this->pdo->prepare("SELECT f.id AS ficha_id, f.nombre AS ficha_nombre, COALESCE(f.numero, f.id) AS numero_ficha, COUNT(a.id) AS total_asistencias, COUNT(CASE WHEN a.estado_asistencia IS NOT NULL AND a.estado_asistencia <> 'presente' THEN 1 END) AS total_fallas FROM fichas f LEFT JOIN asistencias a ON f.id = a.ficha_id WHERE f.colegio = ? GROUP BY f.id, f.nombre, numero_ficha");
            $stmt->execute([$colegio_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows)) return $rows;
        } catch (\PDOException $e) {
            if (!in_array($e->getCode(), ['42S22','42S02'], true)) { throw $e; }
        }
        // 3) Puente facilitador_ficha -> facilitadores.colegio_id
        try {
            $sql = "SELECT f.id AS ficha_id, f.nombre AS ficha_nombre, COALESCE(f.numero, f.id) AS numero_ficha, COUNT(a.id) AS total_asistencias, COUNT(CASE WHEN a.estado_asistencia_id IS NOT NULL AND a.estado_asistencia_id <> 1 THEN 1 END) AS total_fallas FROM fichas f INNER JOIN facilitador_ficha pf ON f.id = pf.ficha_id INNER JOIN facilitadores p ON pf.facilitador_id = p.id LEFT JOIN asistencias a ON f.id = a.ficha_id WHERE p.colegio_id = ? GROUP BY f.id, f.nombre, numero_ficha";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$colegio_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows)) return $rows;
        } catch (\PDOException $e) {
            if (!in_array($e->getCode(), ['42S22','42S02'], true)) { throw $e; }
        }
        // 3a) Texto puente facilitador_ficha
        try {
            $sql = "SELECT f.id AS ficha_id, f.nombre AS ficha_nombre, COALESCE(f.numero, f.id) AS numero_ficha, COUNT(a.id) AS total_asistencias, COUNT(CASE WHEN a.estado_asistencia IS NOT NULL AND a.estado_asistencia <> 'presente' THEN 1 END) AS total_fallas FROM fichas f INNER JOIN facilitador_ficha pf ON f.id = pf.ficha_id INNER JOIN facilitadores p ON pf.facilitador_id = p.id LEFT JOIN asistencias a ON f.id = a.ficha_id WHERE p.colegio_id = ? GROUP BY f.id, f.nombre, numero_ficha";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$colegio_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows)) return $rows;
        } catch (\PDOException $e) {
            if (!in_array($e->getCode(), ['42S22','42S02'], true)) { throw $e; }
        }
        // 4) Puente profesor_ficha (legacy)
        try {
            $sql2 = "SELECT f.id AS ficha_id, f.nombre AS ficha_nombre, COALESCE(f.numero, f.id) AS numero_ficha, COUNT(a.id) AS total_asistencias, COUNT(CASE WHEN a.estado_asistencia_id IS NOT NULL AND a.estado_asistencia_id <> 1 THEN 1 END) AS total_fallas FROM fichas f INNER JOIN profesor_ficha pf ON f.id = pf.ficha_id INNER JOIN facilitadores p ON pf.profesor_id = p.id LEFT JOIN asistencias a ON f.id = a.ficha_id WHERE p.colegio_id = ? GROUP BY f.id, f.nombre, numero_ficha";
            $stmt2 = $this->pdo->prepare($sql2);
            $stmt2->execute([$colegio_id]);
            $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows)) return $rows;
        } catch (\PDOException $e2) {
            if (!in_array($e2->getCode(), ['42S22','42S02'], true)) { throw $e2; }
        }
        // 4a) Texto puente profesor_ficha
        try {
            $sql2 = "SELECT f.id AS ficha_id, f.nombre AS ficha_nombre, COALESCE(f.numero, f.id) AS numero_ficha, COUNT(a.id) AS total_asistencias, COUNT(CASE WHEN a.estado_asistencia IS NOT NULL AND a.estado_asistencia <> 'presente' THEN 1 END) AS total_fallas FROM fichas f INNER JOIN profesor_ficha pf ON f.id = pf.ficha_id INNER JOIN facilitadores p ON pf.profesor_id = p.id LEFT JOIN asistencias a ON f.id = a.ficha_id WHERE p.colegio_id = ? GROUP BY f.id, f.nombre, numero_ficha";
            $stmt2 = $this->pdo->prepare($sql2);
            $stmt2->execute([$colegio_id]);
            $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows)) return $rows;
        } catch (\PDOException $e2) {
            if (!in_array($e2->getCode(), ['42S22','42S02'], true)) { throw $e2; }
        }
        // 5) Último fallback: fichas derivadas desde aprendices.colegio_id
        try {
            $sql5 = "SELECT f.id AS ficha_id, f.nombre AS ficha_nombre, COALESCE(f.numero, f.id) AS numero_ficha, COUNT(a.id) AS total_asistencias, COUNT(CASE WHEN a.estado_asistencia_id IS NOT NULL AND a.estado_asistencia_id <> 1 THEN 1 END) AS total_fallas FROM aprendices ap INNER JOIN fichas f ON f.id = ap.ficha_id LEFT JOIN asistencias a ON f.id = a.ficha_id WHERE ap.colegio_id = ? GROUP BY f.id, f.nombre, numero_ficha";
            $stmt5 = $this->pdo->prepare($sql5);
            $stmt5->execute([$colegio_id]);
            $rows5 = $stmt5->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows5)) return $rows5;
        } catch (\PDOException $e5) {
            if (!in_array($e5->getCode(), ['42S22','42S02'], true)) { throw $e5; }
        }
        return [];
    }

    public function obtenerEstudiantesConFallas($colegio_id, $min_fallas = 3) {
        // 0) Esquema nuevo: relación por ficha_colegio (fichas↔colegios) + asistencias
        try {
            $sql0 = "
                SELECT 
                    u.id AS aprendiz_id,
                    u.nombres,
                    u.apellidos,
                    f.nombre AS ficha_nombre,
                    COALESCE(f.numero, f.id) AS numero_ficha,  
                    COUNT(CASE WHEN a.estado_asistencia_id IS NOT NULL AND a.estado_asistencia_id <> 1 THEN 1 END) AS total_fallas
                FROM ficha_colegio fc
                INNER JOIN fichas f ON f.id = fc.ficha_id
                INNER JOIN aprendices a_pr ON a_pr.ficha_id = f.id
                INNER JOIN usuarios u ON u.id = a_pr.usuario_id
                LEFT JOIN asistencias a ON a.estudiante_id = u.id AND a.ficha_id = f.id
                WHERE fc.colegio_id = :colegio_id
                GROUP BY u.id, u.nombres, u.apellidos, f.nombre, COALESCE(f.numero, f.id)
                HAVING total_fallas >= :min_fallas
                ORDER BY total_fallas DESC
            ";
            $stmt0 = $this->pdo->prepare($sql0);
            $stmt0->execute(['colegio_id' => $colegio_id, 'min_fallas' => $min_fallas]);
            $rows0 = $stmt0->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows0)) return $rows0;
        } catch (\PDOException $e0) {
            if (!in_array($e0->getCode(), ['42S02','42S22'], true)) { throw $e0; }
        }

        // 0) Esquema actual: contar por estado_asistencia_id (<> 1 = no presente), usando claves modernas usuario_id / ficha_id
        try {
            $sql = "
                SELECT 
                    u.id AS aprendiz_id,
                    u.nombres,
                    u.apellidos,
                    f.nombre AS ficha_nombre,
                    COALESCE(f.numero, f.id) AS numero_ficha,
                    COUNT(CASE WHEN a.estado_asistencia_id IS NOT NULL AND a.estado_asistencia_id <> 1 THEN 1 END) AS total_fallas
                FROM aprendices a_pr
                INNER JOIN usuarios u ON u.id = a_pr.usuario_id
                INNER JOIN fichas f ON f.id = a_pr.ficha_id
                LEFT JOIN asistencias a ON a.estudiante_id = u.id AND a.ficha_id = f.id
                WHERE f.colegio_id = :colegio_id
                GROUP BY u.id, u.nombres, u.apellidos, f.nombre, COALESCE(f.numero, f.id)
                HAVING total_fallas >= :min_fallas
                ORDER BY total_fallas DESC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['colegio_id' => $colegio_id, 'min_fallas' => $min_fallas]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows)) return $rows;
        } catch (\PDOException $e0) {
            if (!in_array($e0->getCode(), ['42S02','42S22'], true)) { throw $e0; }
        }

        // 0b) Esquema mixto: a_pr.ficha (legacy) + estado_asistencia_id numérico
        try {
            $sql0b = "
                SELECT 
                    u.id AS aprendiz_id,
                    u.nombres,
                    u.apellidos,
                    f.nombre AS ficha_nombre,
                    COALESCE(f.numero, f.codigo, f.id) AS numero_ficha,
                    COUNT(CASE WHEN a.estado_asistencia_id IS NOT NULL AND a.estado_asistencia_id <> 1 THEN 1 END) AS total_fallas
                FROM aprendices a_pr
                INNER JOIN usuarios u ON u.id = a_pr.usuario
                INNER JOIN fichas f ON f.id = a_pr.ficha
                LEFT JOIN asistencias a ON a.estudiante_id = u.id AND a.ficha_id = f.id
                WHERE f.colegio_id = :colegio_id
                GROUP BY u.id, u.nombres, u.apellidos, f.nombre, COALESCE(f.numero, f.codigo, f.id)
                HAVING total_fallas >= :min_fallas
                ORDER BY total_fallas DESC
            ";
            $stmt0b = $this->pdo->prepare($sql0b);
            $stmt0b->execute(['colegio_id' => $colegio_id, 'min_fallas' => $min_fallas]);
            $rows0b = $stmt0b->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows0b)) return $rows0b;
        } catch (\PDOException $e0b) {
            if (!in_array($e0b->getCode(), ['42S02','42S22'], true)) { throw $e0b; }
        }

        // 1) Texto: fichas.colegio_id
        try {
            $sql = "
                SELECT 
                    u.id AS aprendiz_id,
                    u.nombres,
                    u.apellidos,
                    f.nombre AS ficha_nombre,
                    COALESCE(f.numero, f.codigo, f.id) AS numero_ficha,
                    COUNT(CASE WHEN a.estado_asistencia = 'no_asistio' THEN 1 END) AS total_fallas
                FROM aprendices a_pr
                INNER JOIN usuarios u ON u.id = a_pr.usuario_id
                INNER JOIN fichas f ON f.id = a_pr.ficha_id
                LEFT JOIN asistencias a ON a.estudiante_id = u.id AND a.ficha_id = f.id
                WHERE f.colegio_id = :colegio_id
                GROUP BY u.id, u.nombres, u.apellidos, f.nombre, COALESCE(f.numero, f.codigo, f.id)
                HAVING total_fallas >= :min_fallas
                ORDER BY total_fallas DESC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'colegio_id' => $colegio_id,
                'min_fallas' => $min_fallas
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows)) return $rows;
        } catch (\PDOException $e) {
            if (!in_array($e->getCode(), ['42S02','42S22'], true)) { throw $e; }
        }
        // 2) Texto: fichas.colegio (legacy)
        try {
            $sql2 = "
                SELECT 
                    u.id AS aprendiz_id,
                    u.nombres,
                    u.apellidos,
                    f.nombre AS ficha_nombre,
                    COALESCE(f.numero, f.codigo, f.id) AS numero_ficha,
                    COUNT(CASE WHEN a.estado_asistencia = 'no_asistio' THEN 1 END) AS total_fallas
                FROM aprendices a_pr
                INNER JOIN usuarios u ON u.id = a_pr.usuario_id
                INNER JOIN fichas f ON f.id = a_pr.ficha_id
                LEFT JOIN asistencias a ON a.estudiante_id = u.id AND a.ficha_id = f.id
                WHERE f.colegio = :colegio_id
                GROUP BY u.id, u.nombres, u.apellidos, f.nombre, COALESCE(f.numero, f.codigo, f.id)
                HAVING total_fallas >= :min_fallas
                ORDER BY total_fallas DESC
            ";
            $stmt2 = $this->pdo->prepare($sql2);
            $stmt2->execute(['colegio_id' => $colegio_id, 'min_fallas' => $min_fallas]);
            $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows)) return $rows;
        } catch (\PDOException $e2) {
            if (!in_array($e2->getCode(), ['42S02','42S22'], true)) { throw $e2; }
        }
        // 3) Texto: puente facilitador_ficha -> p.colegio_id
        try {
            $sql3 = "
                SELECT 
                    u.id AS aprendiz_id,
                    u.nombres,
                    u.apellidos,
                    f.nombre AS ficha_nombre,
                    COALESCE(f.numero, f.codigo, f.id) AS numero_ficha,
                    COUNT(CASE WHEN a.estado_asistencia = 'no_asistio' THEN 1 END) AS total_fallas
                FROM aprendices a_pr
                INNER JOIN usuarios u ON u.id = a_pr.usuario_id
                INNER JOIN fichas f ON f.id = a_pr.ficha_id
                INNER JOIN facilitador_ficha pf ON f.id = pf.ficha_id
                INNER JOIN facilitadores p ON pf.facilitador_id = p.id
                LEFT JOIN asistencias a ON a.estudiante_id = u.id AND a.ficha_id = f.id
                WHERE p.colegio_id = :colegio_id
                GROUP BY u.id, u.nombres, u.apellidos, f.nombre, COALESCE(f.numero, f.codigo, f.id)
                HAVING total_fallas >= :min_fallas
                ORDER BY total_fallas DESC
            ";
            $stmt3 = $this->pdo->prepare($sql3);
            $stmt3->execute(['colegio_id' => $colegio_id, 'min_fallas' => $min_fallas]);
            $rows = $stmt3->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows)) return $rows;
        } catch (\PDOException $e3) {
            if (!in_array($e3->getCode(), ['42S02','42S22'], true)) { throw $e3; }
        }
        // 4) Texto: puente profesor_ficha (legacy)
        try {
            $sql4 = "
                SELECT 
                    u.id AS aprendiz_id,
                    u.nombres,
                    u.apellidos,
                    f.nombre AS ficha_nombre,
                    COALESCE(f.numero, f.codigo, f.id) AS numero_ficha,
                    COUNT(CASE WHEN a.estado_asistencia = 'no_asistio' THEN 1 END) AS total_fallas
                FROM aprendices a_pr
                INNER JOIN usuarios u ON u.id = a_pr.usuario_id
                INNER JOIN fichas f ON f.id = a_pr.ficha_id
                INNER JOIN profesor_ficha pf ON f.id = pf.ficha_id
                INNER JOIN facilitadores p ON pf.profesor_id = p.id
                LEFT JOIN asistencias a ON a.estudiante_id = u.id AND a.ficha_id = f.id
                WHERE p.colegio_id = :colegio_id
                GROUP BY u.id, u.nombres, u.apellidos, f.nombre, COALESCE(f.numero, f.codigo, f.id)
                HAVING total_fallas >= :min_fallas
                ORDER BY total_fallas DESC
            ";
            $stmt4 = $this->pdo->prepare($sql4);
            $stmt4->execute(['colegio_id' => $colegio_id, 'min_fallas' => $min_fallas]);
            $rows = $stmt4->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows)) return $rows;
        } catch (\PDOException $e4) {
            if (!in_array($e4->getCode(), ['42S02','42S22'], true)) { throw $e4; }
        }
        // 5) Fallback por aprendices.colegio_id (cuando fichas.colegio_id no está)
        try {
            $sql5 = "
                SELECT 
                    u.id AS aprendiz_id,
                    u.nombres,
                    u.apellidos,
                    f.nombre AS ficha_nombre,
                    COALESCE(f.numero, f.id) AS numero_ficha,
                    COUNT(CASE WHEN a.estado_asistencia_id IS NOT NULL AND a.estado_asistencia_id <> 1 THEN 1 END) AS total_fallas
                FROM aprendices a_pr
                INNER JOIN usuarios u ON u.id = a_pr.usuario_id
                INNER JOIN fichas f ON f.id = a_pr.ficha_id
                LEFT JOIN asistencias a ON a.estudiante_id = u.id AND a.ficha_id = f.id
                WHERE a_pr.colegio_id = :colegio_id
                GROUP BY u.id, u.nombres, u.apellidos, f.nombre, COALESCE(f.numero, f.id)
                HAVING total_fallas >= :min_fallas
                ORDER BY total_fallas DESC
            ";
            $stmt5 = $this->pdo->prepare($sql5);
            $stmt5->execute(['colegio_id' => $colegio_id, 'min_fallas' => $min_fallas]);
            $rows5 = $stmt5->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows5)) return $rows5;
        } catch (\PDOException $e5) {
            if (!in_array($e5->getCode(), ['42S02','42S22'], true)) { throw $e5; }
        }
        return [];
    }

    // Registrar o actualizar (upsert) asistencia individual
    // Adaptado al esquema actual:
    // - fecha_dia es columna GENERATED ALWAYS AS (CAST(fecha AS DATE)) STORED
    // - NUNCA se escribe fecha_dia desde PHP, solo se establece fecha (datetime)
    public function registrarAsistencia(array $data) {
        // $data: ficha_id, estudiante_id (usuarios.id), profesor_id (facilitadores.id), fecha (Y-m-d), estado (ui), observaciones?, creado_por?
        $estadoTexto = $this->normalizarEstado($data['estado'] ?? '');

        // Normalizar fecha a YYYY-MM-DD y luego a datetime (usamos medianoche por simplicidad)
        $fechaIn = isset($data['fecha']) ? trim((string)$data['fecha']) : '';
        $fechaDia = date('Y-m-d');
        if ($fechaIn !== '' && $fechaIn !== '0000-00-00') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaIn)) {
                $y = (int)substr($fechaIn,0,4); $m = (int)substr($fechaIn,5,2); $d = (int)substr($fechaIn,8,2);
                if (checkdate($m, $d, $y)) { $fechaDia = $fechaIn; }
            } else {
                $ts = @strtotime($fechaIn);
                if ($ts !== false) { $fechaDia = date('Y-m-d', $ts); }
            }
        }
        $fichaId = (int)$data['ficha_id'];
        $estudianteId = (int)$data['estudiante_id'];
        $facilitadorId = isset($data['profesor_id']) ? (int)$data['profesor_id'] : null;
        $observaciones = $data['observaciones'] ?? null;

        // 1) Buscar asistencia existente por clave única (ficha, estudiante, fecha_dia)
        $stmt = $this->pdo->prepare(
            "SELECT id FROM asistencias WHERE ficha_id = ? AND estudiante_id = ? AND fecha_dia = ? LIMIT 1"
        );
        $stmt->execute([$fichaId, $estudianteId, $fechaDia]);
        $existente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existente) {
            // 2) UPDATE si ya existe
            $sql = "UPDATE asistencias
                    SET estado_asistencia = ?, observaciones = ?, facilitador_id = ?
                    WHERE id = ?";
            $upd = $this->pdo->prepare($sql);
            return $upd->execute([$estadoTexto, $observaciones, $facilitadorId, (int)$existente['id']]);
        }

        // 3) INSERT si no existe: NO incluimos fecha ni fecha_dia; MySQL pondrá CURRENT_TIMESTAMP en fecha y generará fecha_dia
        $sqlIns = "INSERT INTO asistencias
                    (ficha_id, estudiante_id, facilitador_id, estado_asistencia, observaciones)
                    VALUES (?, ?, ?, ?, ?)";
        $ins = $this->pdo->prepare($sqlIns);
        return $ins->execute([$fichaId, $estudianteId, $facilitadorId, $estadoTexto, $observaciones]);
    }

    // Actualizar una asistencia por id
    public function actualizarAsistencia(int $id, string $estado, ?string $observaciones = null) {
        $estadoTexto = $this->normalizarEstado($estado);
        $sql = "UPDATE asistencias SET estado_asistencia = ?, observaciones = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$estadoTexto, $observaciones, $id]);
    }

    // Obtener asistencias por ficha y fecha exacta (soporta fecha_dia y mapea estudiante_id a usuarios.id cuando sea necesario)
    public function obtenerAsistenciasPorFicha(int $ficha_id, string $fecha) {
        // Intento con fecha OR fecha_dia y join a aprendices->usuarios por usuario_id
        try {
            $sql = "
                SELECT 
                    a.id,
                    COALESCE(u.id, a.estudiante_id) AS estudiante_id,
                    a.ficha_id,
                    COALESCE(DATE(a.fecha), a.fecha_dia) AS fecha,
                    a.estado_asistencia AS estado
                FROM asistencias a
                LEFT JOIN aprendices ap ON ap.id = a.estudiante_id
                LEFT JOIN usuarios u ON u.id = ap.usuario_id
                WHERE a.ficha_id = ? AND (DATE(a.fecha) = ? OR a.fecha_dia = ?)
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ficha_id, $fecha, $fecha]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e1) {
            if ($e1->getCode() !== '42S22' && stripos($e1->getMessage(), 'Unknown column') === false) { throw $e1; }
            // Fallback: aprendices.usuario (legacy) y sin fecha_dia
            try {
                $sql = "
                    SELECT 
                        a.id,
                        COALESCE(u.id, a.estudiante_id) AS estudiante_id,
                        a.ficha_id,
                        DATE(a.fecha) AS fecha,
                        a.estado_asistencia AS estado_asistencia_id
                    FROM asistencias a
                    LEFT JOIN aprendices ap ON ap.id = a.estudiante_id
                    LEFT JOIN usuarios u ON u.id = ap.usuario
                    WHERE a.ficha_id = ? AND DATE(a.fecha) = ?
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$ficha_id, $fecha]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\PDOException $e2) {
                // Último recurso: sin joins
                $sql = "SELECT id, estudiante_id, ficha_id, DATE(fecha) as fecha, estado_asistencia AS estado FROM asistencias WHERE ficha_id = ? AND DATE(fecha) = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$ficha_id, $fecha]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        // Ya devolvemos estado_asistencia como 'estado' textual
        return $rows;
    }

    // Obtener asistencias por ficha en un rango [inicio, fin]
    public function obtenerAsistenciasPorFichaRango(int $ficha_id, string $fecha_inicio, string $fecha_fin) {
        // Intento con fecha_dia y join a aprendices->usuarios por usuario_id
        try {
            $sql = "
                SELECT 
                    a.id,
                    COALESCE(u.id, a.estudiante_id) AS estudiante_id,
                    a.ficha_id,
                    COALESCE(DATE(a.fecha), a.fecha_dia) AS fecha,
                    a.estado_asistencia AS estado
                FROM asistencias a
                LEFT JOIN aprendices ap ON ap.id = a.estudiante_id
                LEFT JOIN usuarios u ON u.id = ap.usuario_id
                WHERE a.ficha_id = ? AND (
                    (DATE(a.fecha) BETWEEN ? AND ?) OR (a.fecha_dia BETWEEN ? AND ?)
                )
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ficha_id, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e1) {
            if ($e1->getCode() !== '42S22' && stripos($e1->getMessage(), 'Unknown column') === false) { throw $e1; }
            // Fallback: aprendices.usuario (legacy) y sin fecha_dia
            try {
                $sql = "
                    SELECT 
                        a.id,
                        COALESCE(u.id, a.estudiante_id) AS estudiante_id,
                        a.ficha_id,
                        DATE(a.fecha) AS fecha,
                        a.estado_asistencia AS estado
                    FROM asistencias a
                    LEFT JOIN aprendices ap ON ap.id = a.estudiante_id
                    LEFT JOIN usuarios u ON u.id = ap.usuario
                    WHERE a.ficha_id = ? AND DATE(a.fecha) BETWEEN ? AND ?
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$ficha_id, $fecha_inicio, $fecha_fin]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\PDOException $e2) {
                $sql = "SELECT id, estudiante_id, ficha_id, DATE(fecha) as fecha, estado_asistencia AS estado FROM asistencias WHERE ficha_id = ? AND DATE(fecha) BETWEEN ? AND ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$ficha_id, $fecha_inicio, $fecha_fin]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        // Ya devolvemos estado_asistencia como 'estado' textual
        return $rows;
    }

    // Estadísticas por estudiante (opcionalmente filtradas por ficha)
    public function obtenerEstadisticasEstudiante(int $estudiante_id, ?int $ficha_id = null) {
        $params = [$estudiante_id];
        $whereFicha = '';
        if ($ficha_id) { $whereFicha = ' AND ficha_id = ?'; $params[] = $ficha_id; }

        $sql = "SELECT 
                    COUNT(*) as total_clases,
                    SUM(CASE WHEN estado_asistencia = 'presente' THEN 1 ELSE 0 END) as presentes,
                    SUM(CASE WHEN estado_asistencia = 'no_asistio' THEN 1 ELSE 0 END) as faltas,
                    SUM(CASE WHEN estado_asistencia = 'tarde' THEN 1 ELSE 0 END) as tardanzas,
                    SUM(CASE WHEN estado_asistencia = 'justificado' THEN 1 ELSE 0 END) as justificados
                FROM asistencias
                WHERE estudiante_id = ? $whereFicha";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_clases' => 0,
            'presentes' => 0,
            'faltas' => 0,
            'tardanzas' => 0,
            'justificados' => 0
        ];
    }

    /**
     * Obtener estudiantes de una ficha junto al estado de asistencia del día dado
     */
    public function obtenerEstudiantesFichaFecha(int $ficha_id, string $fecha): array {
        // Asegurar formato de fecha YYYY-MM-DD
        $fecha = date('Y-m-d', strtotime($fecha));

        $sql = "\n            SELECT \n                u.id AS estudiante_id,\n                u.nombres,\n                u.apellidos,\n                a.estado_asistencia AS estado,\n                a.id AS asistencia_id\n            FROM aprendices ap\n            JOIN usuarios u ON u.id = ap.usuario_id\n            LEFT JOIN asistencias a \n              ON a.estudiante_id = u.id \n             AND a.ficha_id = ap.ficha_id\n             AND DATE(a.fecha) = ?\n            WHERE ap.ficha_id = ?\n            ORDER BY u.apellidos, u.nombres\n        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$fecha, $ficha_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ya devolvemos estado textual (puede ser null si no hay registro)
        return $rows;
    }
}
