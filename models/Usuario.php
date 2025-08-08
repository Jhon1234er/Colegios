<?php
require_once __DIR__ . '/../config/db.php';

class Usuario {
    public function obtenerPorCorreo($correo) {
        $pdo = Database::conectar();
        $stmt = $pdo->prepare("
            SELECT u.*, r.nombre AS nombre_rol 
            FROM usuarios u 
            JOIN roles r ON u.rol_id = r.id 
            WHERE u.correo_electronico = ?
        ");
        $stmt->execute([$correo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function registrar($data, $pdo) {
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (
                nombres, apellidos, tipo_documento, numero_documento,
                correo_electronico, telefono, 
                fecha_nacimiento, genero, password_hash, rol_id
            ) VALUES (
                :nombres, :apellidos, :tipo_documento, :numero_documento,
                :correo_electronico, :telefono,
                :fecha_nacimiento, :genero, :password_hash, :rol_id
            )
        ");

        $stmt->execute([
            ':nombres'           => $data['nombres'],
            ':apellidos'         => $data['apellidos'],
            ':tipo_documento'    => $data['tipo_documento'],
            ':numero_documento'  => $data['numero_documento'],
            ':correo_electronico'=> $data['correo_electronico'],
            ':telefono'          => $data['telefono'],
            ':fecha_nacimiento'  => $data['fecha_nacimiento'],
            ':genero'            => $data['genero'],
            ':password_hash'     => $data['password_hash'],
            ':rol_id'            => $data['rol_id']
        ]);

        return $pdo->lastInsertId();
    }
    public function contarUsuarios() {
        $pdo = Database::conectar();
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM usuarios");
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    public function buscarPorCorreo($correo) {
        $pdo = Database::conectar();
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE correo_electronico = ?");
        $stmt->execute([$correo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function obtenerPorId($id) {
        $pdo = Database::conectar();
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
