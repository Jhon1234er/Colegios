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
                correo_electronico, correo_institucional, telefono, 
                municipio, direccion, barrio, eps, eps_otro, estrato, rh,
                fecha_nacimiento, genero, genero_otro, password_hash, rol_id, estado
            ) VALUES (
                :nombres, :apellidos, :tipo_documento, :numero_documento,
                :correo_electronico, :correo_institucional, :telefono,
                :municipio, :direccion, :barrio, :eps, :eps_otro, :estrato, :rh,
                :fecha_nacimiento, :genero, :genero_otro, :password_hash, :rol_id, :estado
            )
        ");

        $stmt->execute([
            ':nombres'              => $data['nombres'],
            ':apellidos'            => $data['apellidos'],
            ':tipo_documento'       => $data['tipo_documento'],
            ':numero_documento'     => $data['numero_documento'],
            ':correo_electronico'   => $data['correo_electronico'] ?? null,
            ':correo_institucional' => $data['correo_institucional'] ?? null,
            ':telefono'             => $data['telefono'] ?? null,
            ':municipio'            => $data['municipio'] ?? null,
            ':direccion'            => $data['direccion'] ?? null,
            ':barrio'               => $data['barrio'] ?? null,
            ':eps'                  => $data['eps'] ?? null,
            ':eps_otro'             => $data['eps_otro'] ?? null,
            ':estrato'              => $data['estrato'] ?? null,
            ':rh'                   => $data['rh'] ?? null,
            ':fecha_nacimiento'     => $data['fecha_nacimiento'] ?? null,
            ':genero'               => $data['genero'] ?? null,
            ':genero_otro'          => $data['genero_otro'] ?? null,
            ':password_hash'        => $data['password_hash'],
            ':rol_id'               => $data['rol_id'],
            ':estado'               => $data['estado'] ?? 1
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
    public function buscarPorDocumento($tipoDocumento, $numeroDocumento) {
        $pdo = Database::conectar();
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE tipo_documento = ? AND numero_documento = ? LIMIT 1");
            $stmt->execute([$tipoDocumento, $numeroDocumento]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) { return $row; }
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S22') { throw $e; }
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE tipo_id = ? AND numero_id = ? LIMIT 1");
            $stmt->execute([$tipoDocumento, $numeroDocumento]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) { return $row; }
        } catch (\PDOException $e2) {
            if ($e2->getCode() !== '42S22') { throw $e2; }
        }
        return null;
    }
    public function obtenerPorId($id) {
        $pdo = Database::conectar();

        // Intentar obtener información enriquecida (colegio, ficha, etc.)
        try {
            $sql = "
                SELECT
                    u.*,
                    ap.colegio_id,
                    ap.ficha_id,
                    NULL AS acudiente,
                    NULL AS doc_acudiente,
                    NULL AS telefono_acudiente,
                    NULL AS parentesco,
                    NULL AS ocupacion,
                    c.nombre AS nombre_colegio,
                    f.nombre AS numero_ficha,
                    NULL AS jornada,
                    NULL AS estado_estudiante,
                    (
                        SELECT fi.colegio_id
                        FROM facilitador_ficha ff
                        INNER JOIN fichas fi ON fi.id = ff.ficha_id
                        WHERE ff.facilitador_id = fac.id
                        LIMIT 1
                    ) AS profesor_colegio_id,
                    u.rol_id
                FROM usuarios u
                LEFT JOIN aprendices ap ON u.id = ap.usuario_id
                LEFT JOIN colegios c ON ap.colegio_id = c.id
                LEFT JOIN fichas f ON ap.ficha_id = f.id
                LEFT JOIN facilitadores fac ON u.id = fac.usuario
                WHERE u.id = ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        } catch (\PDOException $e) {
            // Si faltan columnas/tablas (42S22), caer a consulta mínima compatible
            if ($e->getCode() !== '42S22' && $e->getCode() !== '42S02') {
                throw $e;
            }
        }

        // Fallback mínimo: solo datos de usuarios (esquemas legados)
        $stmtMin = $pdo->prepare('SELECT u.*, u.rol_id FROM usuarios u WHERE u.id = ?');
        $stmtMin->execute([$id]);
        return $stmtMin->fetch(PDO::FETCH_ASSOC);
    }

    public function actualizar($id, $data) {
        $pdo = Database::conectar();
        
        // Actualizar tabla usuarios
        $stmt = $pdo->prepare("
            UPDATE usuarios SET 
                nombres = ?, apellidos = ?, tipo_documento = ?, numero_documento = ?,
                correo_electronico = ?, correo_institucional = ?, telefono = ?,
                municipio = ?, direccion = ?, barrio = ?, eps = ?, eps_otro = ?, estrato = ?, rh = ?,
                fecha_nacimiento = ?, genero = ?, genero_otro = ?, estado = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['nombres'] ?? '',
            $data['apellidos'] ?? '',
            $data['tipo_documento'] ?? '',
            $data['numero_documento'] ?? '',
            $data['correo_electronico'] ?? '',
            $data['correo_institucional'] ?? null,
            $data['telefono'] ?? '',
            $data['municipio'] ?? null,
            $data['direccion'] ?? null,
            $data['barrio'] ?? null,
            $data['eps'] ?? null,
            $data['eps_otro'] ?? null,
            $data['estrato'] ?? null,
            $data['rh'] ?? null,
            $data['fecha_nacimiento'] ?? null,
            $data['genero'] ?? null,
            $data['genero_otro'] ?? null,
            $data['estado'] ?? 1,
            $id
        ]);

        // Nota: El esquema actual de administradores no contiene correo_institucional; no se actualiza aquí.

        // Si es aprendiz, actualizar datos adicionales
        if (isset($data['acudiente'])) {
            $stmt = $pdo->prepare("
                UPDATE aprendices SET 
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

    public function actualizarPassword($id, $passwordHash) {
        $pdo = Database::conectar();
        $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
        return $stmt->execute([$passwordHash, $id]);
    }
}
