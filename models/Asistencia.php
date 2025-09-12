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
                ON a.estudiante_id = e.id AND a.estado = 'falla'
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



}
