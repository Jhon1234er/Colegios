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

    // Estados normalizados en BD
    public const ESTADO_PRESENTE     = 'presente';
    public const ESTADO_FALLA        = 'ausente';        // en BD el enum es 'ausente'
    public const ESTADO_JUSTIFICADA  = 'justificado';
    public const ESTADO_TARDANZA     = 'tarde';

    public function obtenerFallasPorFicha($colegio_id) {
        $sql = "SELECT 
                    f.id AS ficha_id, 
                    f.nombre AS numero_ficha, 
                    COUNT(CASE WHEN a.estado != 'presente' THEN 1 END) AS total_fallas
                FROM fichas f
                INNER JOIN profesor_ficha pf ON f.id = pf.ficha_id
                INNER JOIN profesores p ON pf.profesor_id = p.id
                LEFT JOIN asistencias a ON f.id = a.ficha_id
                WHERE p.colegio_id = ?
                GROUP BY f.id, f.nombre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$colegio_id]);
        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $resultado;
    }

    public function obtenerEstudiantesConFallas($colegio_id, $min_fallas = 3) {
        $sql = "
            SELECT 
                e.id AS estudiante_id,
                u.nombres,
                u.apellidos,
                f.nombre AS numero_ficha,
                COUNT(a.id) AS total_fallas
            FROM estudiantes e
            INNER JOIN usuarios u ON u.id = e.usuario_id
            INNER JOIN fichas f ON f.id = e.ficha_id
            INNER JOIN profesor_ficha pf ON f.id = pf.ficha_id
            INNER JOIN profesores p ON pf.profesor_id = p.id
            LEFT JOIN asistencias a 
                ON a.estudiante_id = e.id AND a.estado = 'ausente'
            WHERE p.colegio_id = :colegio_id
            GROUP BY e.id, u.nombres, u.apellidos, f.nombre
            HAVING total_fallas >= :min_fallas
            ORDER BY total_fallas DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'colegio_id' => $colegio_id,
            'min_fallas' => $min_fallas
        ]);
        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $resultado;
    }

    // Registrar o actualizar (upsert) asistencia individual
    public function registrarAsistencia(array $data) {
        // $data: ficha_id, estudiante_id, profesor_id, fecha (Y-m-d), estado, observaciones?, creado_por?
        // Mapear estado desde UI si llega como no_asistio
        $estado = $data['estado'] ?? '';
        // Normalizar estados recibidos desde UI
        if ($estado === 'no_asistio' || $estado === 'falla') { $estado = self::ESTADO_FALLA; }
        if ($estado === 'tardanza') { $estado = self::ESTADO_TARDANZA; }
        if (!in_array($estado, [self::ESTADO_PRESENTE, self::ESTADO_FALLA, self::ESTADO_JUSTIFICADA, self::ESTADO_TARDANZA])) {
            throw new Exception('Estado de asistencia no válido');
        }

        // Intentar encontrar asistencia existente por clave lógica (ficha+estudiante+fecha)
        $buscar = $this->pdo->prepare("SELECT id FROM asistencias WHERE ficha_id = ? AND estudiante_id = ? AND DATE(fecha) = ?");
        $buscar->execute([$data['ficha_id'], $data['estudiante_id'], $data['fecha']]);
        $existente = $buscar->fetch(PDO::FETCH_ASSOC);

        if ($existente) {
            $upd = $this->pdo->prepare("UPDATE asistencias SET estado = ?, observaciones = ?, profesor_id = ? WHERE id = ?");
            return $upd->execute([
                $estado,
                $data['observaciones'] ?? null,
                $data['profesor_id'] ?? null,
                $existente['id']
            ]);
        } else {
            $ins = $this->pdo->prepare("INSERT INTO asistencias (ficha_id, estudiante_id, profesor_id, fecha, estado, observaciones) VALUES (?, ?, ?, ?, ?, ?)");
            return $ins->execute([
                $data['ficha_id'],
                $data['estudiante_id'],
                $data['profesor_id'] ?? null,
                ($data['fecha'] ? ($data['fecha'] . ' 00:00:00') : date('Y-m-d 00:00:00')),
                $estado,
                $data['observaciones'] ?? null
            ]);
        }
    }

    // Actualizar una asistencia por id
    public function actualizarAsistencia(int $id, string $estado, ?string $observaciones = null) {
        if ($estado === 'no_asistio' || $estado === 'falla') { $estado = self::ESTADO_FALLA; }
        if ($estado === 'tardanza') { $estado = self::ESTADO_TARDANZA; }
        if (!in_array($estado, [self::ESTADO_PRESENTE, self::ESTADO_FALLA, self::ESTADO_JUSTIFICADA, self::ESTADO_TARDANZA])) {
            throw new Exception('Estado de asistencia no válido');
        }
        $sql = "UPDATE asistencias SET estado = ?, observaciones = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$estado, $observaciones, $id]);
    }

    // Obtener asistencias por ficha y fecha exacta
    public function obtenerAsistenciasPorFicha(int $ficha_id, string $fecha) {
        $sql = "SELECT id, estudiante_id, ficha_id, DATE(fecha) as fecha, estado FROM asistencias WHERE ficha_id = ? AND DATE(fecha) = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$ficha_id, $fecha]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener asistencias por ficha en un rango [inicio, fin]
    public function obtenerAsistenciasPorFichaRango(int $ficha_id, string $fecha_inicio, string $fecha_fin) {
        $sql = "SELECT id, estudiante_id, ficha_id, DATE(fecha) as fecha, estado FROM asistencias WHERE ficha_id = ? AND DATE(fecha) BETWEEN ? AND ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$ficha_id, $fecha_inicio, $fecha_fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Estadísticas por estudiante (opcionalmente filtradas por ficha)
    public function obtenerEstadisticasEstudiante(int $estudiante_id, ?int $ficha_id = null) {
        $params = [$estudiante_id];
        $whereFicha = '';
        if ($ficha_id) { $whereFicha = ' AND ficha_id = ?'; $params[] = $ficha_id; }

        $sql = "SELECT 
                    COUNT(*) as total_clases,
                    SUM(CASE WHEN estado = 'presente' THEN 1 ELSE 0 END) as presentes,
                    SUM(CASE WHEN estado = 'ausente' THEN 1 ELSE 0 END) as faltas,
                    SUM(CASE WHEN estado = 'tarde' THEN 1 ELSE 0 END) as tardanzas,
                    SUM(CASE WHEN estado = 'justificado' THEN 1 ELSE 0 END) as justificados
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

        $sql = "
            SELECT 
                e.id AS estudiante_id,
                u.nombres,
                u.apellidos,
                a.estado AS estado,
                a.id AS asistencia_id
            FROM estudiantes e
            JOIN usuarios u ON u.id = e.usuario_id
            LEFT JOIN asistencias a 
              ON a.estudiante_id = e.id 
             AND a.ficha_id = e.ficha_id
             AND DATE(a.fecha) = ?
            WHERE e.ficha_id = ?
            ORDER BY u.apellidos, u.nombres
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$fecha, $ficha_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mapear estados a valores esperados por el frontend
        foreach ($rows as &$row) {
            if (isset($row['estado'])) {
                if ($row['estado'] === self::ESTADO_TARDANZA) { $row['estado'] = 'tardanza'; }
                if ($row['estado'] === self::ESTADO_FALLA) { $row['estado'] = 'falla'; }
                if ($row['estado'] === self::ESTADO_JUSTIFICADA) { $row['estado'] = 'justificada'; }
            }
        }
        return $rows;
    }
}
