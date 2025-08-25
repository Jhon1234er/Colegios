<?php
class Database {
    public static function conectar(): PDO {
        $host = getenv('DB_HOST') ?: 'localhost';
        $db   = getenv('DB_NAME') ?: 'sistema_escolar';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '1234';

        $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        return new PDO($dsn, $user, $pass, $opt);
    }
}
