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
        $stmt = $pdo->prepare("
            SELECT u.*, 
                   e.colegio_id, e.ficha_id, e.jornada, e.estado as estado_estudiante,
                   e.nombre_completo_acudiente as acudiente, 
                   e.numero_documento_acudiente as doc_acudiente, 
                   e.telefono_acudiente, e.parentesco, e.ocupacion,
                   c.nombre as nombre_colegio,
                   f.nombre as nombre_ficha, f.numero as numero_ficha,
                   prof.colegio_id as profesor_colegio_id, 
                   COALESCE(prof.correo_institucional, admin.correo_institucional) as correo_institucional
            FROM usuarios u
            LEFT JOIN estudiantes e ON u.id = e.usuario_id
            LEFT JOIN colegios c ON e.colegio_id = c.id
            LEFT JOIN fichas f ON e.ficha_id = f.id
            LEFT JOIN profesores prof ON u.id = prof.usuario_id
            LEFT JOIN administradores admin ON u.id = admin.usuario_id
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function actualizar($id, $data) {
        $pdo = Database::conectar();
        
        // Actualizar tabla usuarios
        $stmt = $pdo->prepare("
            UPDATE usuarios SET 
                nombres = ?, apellidos = ?, tipo_documento = ?, numero_documento = ?,
                correo_electronico = ?, telefono = ?, fecha_nacimiento = ?, genero = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['nombres'] ?? '',
            $data['apellidos'] ?? '',
            $data['tipo_documento'] ?? 'CC',
            $data['numero_documento'] ?? '',
            $data['correo_electronico'] ?? '',
            $data['telefono'] ?? '',
            $data['fecha_nacimiento'] ?? null,
            $data['genero'] ?? 'M',
            $id
        ]);

        // Si es administrador y hay correo institucional, actualizar tabla administradores
        if (isset($data['correo_institucional'])) {
            $stmt = $pdo->prepare("
                UPDATE administradores SET correo_institucional = ? WHERE usuario_id = ?
            ");
            $stmt->execute([$data['correo_institucional'], $id]);
        }

        // Si es estudiante, actualizar datos adicionales
        if (isset($data['acudiente'])) {
            $stmt = $pdo->prepare("
                UPDATE estudiantes SET 
                    nombre_completo_acudiente = ?, numero_documento_acudiente = ?, telefono_acudiente = ?,
                    parentesco = ?, ocupacion = ?
                WHERE usuario_id = ?
            ");
            
            $stmt->execute([
                $data['acudiente'] ?? '',
                $data['doc_acudiente'] ?? '',
                $data['telefono_acudiente'] ?? '',
                $data['parentesco'] ?? '',
                $data['ocupacion'] ?? '',
                $id
            ]);
        }
        
        return true;
    }
}
