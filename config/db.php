<?php
class Database {
    public static function conectar(): PDO {
        // Variables para entorno local
        if (getenv('RAILWAY_ENVIRONMENT') === 'production') {
            // Configuración para Railway
            $host = getenv('MYSQLHOST');
            $db   = getenv('MYSQLDATABASE');
            $user = getenv('MYSQLUSER');
            $pass = getenv('MYSQLPASSWORD');
            $port = getenv('MYSQLPORT');
            
            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        } else {
            // Configuración local
            $host = getenv('DB_HOST') ?: 'localhost';
            $db   = getenv('DB_NAME') ?: 'sistema_escolar';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '1234';
            
            $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
        }

        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $opt);
            return $pdo;
        } catch (PDOException $e) {
            // Log del error (en producción, usa un sistema de logs)
            error_log("Error de conexión: " . $e->getMessage());
            
            // Mensaje genérico para el usuario
            die("Error al conectar con la base de datos. Por favor, intente más tarde.");
        }
    }
}
