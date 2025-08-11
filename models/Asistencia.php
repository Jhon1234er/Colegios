<?php
class Asistencia {
    private $pdo;

    public function __construct() {
        require_once __DIR__ . '/../config/db.php'; // Ruta al archivo que crea la conexión
        $this->pdo = $pdo; // O el nombre de la variable que uses en db.php
    }

    public function obtenerFallasPorFicha($colegio_id) {
        $sql = "
            SELECT 
                f.id AS ficha_id,
                f.numero_ficha,
                COUNT(a.id) AS total_fallas
            FROM fichas f
            LEFT JOIN estudiantes e ON e.ficha_id = f.id
            LEFT JOIN asistencias a 
                ON a.estudiante_id = e.id AND a.estado = 'falla'
            WHERE f.colegio_id = :colegio_id
            GROUP BY f.id
            ORDER BY total_fallas DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['colegio_id' => $colegio_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerEstudiantesConFallas($colegio_id, $min_fallas = 3) {
        $sql = "
            SELECT 
                e.id AS estudiante_id,
                e.nombres,
                e.apellidos,
                f.numero_ficha,
                COUNT(a.id) AS total_fallas
            FROM estudiantes e
            INNER JOIN fichas f ON f.id = e.ficha_id
            LEFT JOIN asistencias a 
                ON a.estudiante_id = e.id AND a.estado = 'falla'
            WHERE f.colegio_id = :colegio_id
            GROUP BY e.id
            HAVING total_fallas >= :min_fallas
            ORDER BY total_fallas DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'colegio_id' => $colegio_id,
            'min_fallas' => $min_fallas
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
