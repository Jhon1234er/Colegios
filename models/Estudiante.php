<?php
require_once __DIR__ . '/../config/db.php';

class Estudiante {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::conectar();
    }

    // -------------------------
    // GUARDAR ESTUDIANTE (uso interno - profesores/admins)
    // -------------------------
    public function guardar($datos) {
        try {
            $this->pdo->beginTransaction();

            // Insertar en usuarios
            $stmtUsuario = $this->pdo->prepare("
                INSERT INTO usuarios 
                (nombres, apellidos, tipo_documento, numero_documento, correo_electronico, telefono,
                 fecha_nacimiento, genero, password_hash, rol_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtUsuario->execute([
                $datos['nombres'],
                $datos['apellidos'],
                $datos['tipo_documento'],
                $datos['numero_documento'],
                $datos['correo_electronico'],
                $datos['telefono'],
                $datos['fecha_nacimiento'],
                $datos['genero'],
                password_hash($datos['password'], PASSWORD_DEFAULT),
                3 // rol estudiante
            ]);

            $usuario_id = $this->pdo->lastInsertId();

            // Insertar en estudiantes
            $stmtEstudiante = $this->pdo->prepare("
                INSERT INTO estudiantes (
                    usuario_id, colegio_id, ficha_id, grado, grupo, jornada, fecha_ingreso,
                    nombre_completo_acudiente, tipo_documento_acudiente, numero_documento_acudiente,
                    telefono_acudiente, parentesco, ocupacion, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtEstudiante->execute([
                $usuario_id,
                $datos['colegio_id'],
                $datos['ficha_id'],
                $datos['grado'],
                $datos['grupo'],
                $datos['jornada'],
                $datos['fecha_ingreso'],
                $datos['nombre_completo_acudiente'],
                $datos['tipo_documento_acudiente'],
                $datos['numero_documento_acudiente'],
                $datos['telefono_acudiente'],
                $datos['parentesco'],
                $datos['ocupacion'],
                $datos['estado'] ?? 'Activo'
            ]);

            $estudiante_id = $this->pdo->lastInsertId();

            // Ficha médica (opcional)
            if (!empty($datos['ficha_medica']) && is_array($datos['ficha_medica'])) {
                $fm = $datos['ficha_medica'];
                $stmtFM = $this->pdo->prepare("\n                    INSERT INTO ficha_medica_estudiantes (\n                      estudiante_id, padece_enfermedad, enfermedad_detalle, tiene_alergias, alergias_detalle,\n                      medicamento_permanente, medicamento_detalle, discapacidad, discapacidad_detalle, cursos_tecnoacademia\n                    ) VALUES (?,?,?,?,?,?,?,?,?,?)\n                    ON DUPLICATE KEY UPDATE\n                      padece_enfermedad=VALUES(padece_enfermedad), enfermedad_detalle=VALUES(enfermedad_detalle),\n                      tiene_alergias=VALUES(tiene_alergias), alergias_detalle=VALUES(alergias_detalle),\n                      medicamento_permanente=VALUES(medicamento_permanente), medicamento_detalle=VALUES(medicamento_detalle),\n                      discapacidad=VALUES(discapacidad), discapacidad_detalle=VALUES(discapacidad_detalle),\n                      cursos_tecnoacademia=VALUES(cursos_tecnoacademia)
                ");
                $stmtFM->execute([
                    $estudiante_id,
                    !empty($fm['padece_enfermedad']) ? 1 : 0,
                    $fm['enfermedad_detalle'] ?? null,
                    !empty($fm['tiene_alergias']) ? 1 : 0,
                    $fm['alergias_detalle'] ?? null,
                    !empty($fm['medicamento_permanente']) ? 1 : 0,
                    $fm['medicamento_detalle'] ?? null,
                    !empty($fm['discapacidad']) ? 1 : 0,
                    $fm['discapacidad_detalle'] ?? null,
                    !empty($fm['cursos_tecnoacademia']) ? 1 : 0
                ]);
            }

            // Acudientes múltiples (opcional)
            if (!empty($datos['acudientes']) && is_array($datos['acudientes'])) {
                foreach ($datos['acudientes'] as $idx => $acu) {
                    if (empty($acu['nombres']) && empty($acu['apellidos'])) continue;
                    $stmtAcu = $this->pdo->prepare("\n                        INSERT INTO acudientes (nombres, apellidos, tipo_documento, numero_documento, genero, genero_otro, celular, correo, ocupacion)\n                        VALUES (?,?,?,?,?,?,?,?,?)\n                        ON DUPLICATE KEY UPDATE nombres=VALUES(nombres), apellidos=VALUES(apellidos), genero=VALUES(genero), genero_otro=VALUES(genero_otro), celular=VALUES(celular), correo=VALUES(correo), ocupacion=VALUES(ocupacion)
                    ");
                    $stmtAcu->execute([
                        $acu['nombres'] ?? '',
                        $acu['apellidos'] ?? '',
                        $acu['tipo_documento'] ?? 'CC',
                        $acu['numero_documento'] ?? '',
                        $acu['genero'] ?? null,
                        $acu['genero_otro'] ?? null,
                        $acu['celular'] ?? null,
                        $acu['correo'] ?? null,
                        $acu['ocupacion'] ?? null,
                    ]);
                    $acudiente_id = $this->pdo->lastInsertId();
                    if (!$acudiente_id) {
                        $stmtFind = $this->pdo->prepare("SELECT id FROM acudientes WHERE tipo_documento=? AND numero_documento=? LIMIT 1");
                        $stmtFind->execute([$acu['tipo_documento'] ?? 'CC', $acu['numero_documento'] ?? '']);
                        $acudiente_id = $stmtFind->fetchColumn();
                    }
                    if ($acudiente_id) {
                        $stmtLink = $this->pdo->prepare("\n                            INSERT IGNORE INTO estudiante_acudiente (estudiante_id, acudiente_id, parentesco, es_contacto_emergencia, prioridad_llamada)\n                            VALUES (?,?,?,?,?)\n                        ");
                        $stmtLink->execute([
                            $estudiante_id,
                            $acudiente_id,
                            $acu['parentesco'] ?? 'Acudiente',
                            !empty($acu['es_contacto_emergencia']) ? 1 : 0,
                            isset($acu['prioridad_llamada']) ? (int)$acu['prioridad_llamada'] : ($idx === 0 ? 1 : 2),
                        ]);
                    }
                }
            }

            // 🔹 Actualizar el cupo usado de la ficha
            $stmtCupo = $this->pdo->prepare("
                UPDATE fichas
                SET cupo_usado = cupo_usado + 1
                WHERE id = ?
            ");
            $stmtCupo->execute([$datos['ficha_id']]);

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            die("❌ Error al guardar estudiante: " . $e->getMessage());
        }
    }

    // -------------------------
    // GUARDAR ESTUDIANTE (uso público desde registro.php)
    // -------------------------
    public function guardarPublico($datos) {
        try {
            $this->pdo->beginTransaction();

            // ✅ Usar colegio seleccionado por el aprendiz y validar que exista
            $colegio_id = $datos['colegio_id'] ?? null;
            if (!$colegio_id) {
                throw new Exception("Debe seleccionar un colegio.");
            }
            $stmtCol = $this->pdo->prepare("SELECT id FROM colegios WHERE id = ?");
            $stmtCol->execute([$colegio_id]);
            if (!$stmtCol->fetchColumn()) {
                throw new Exception("Colegio no válido.");
            }

            // Generar contraseña automática = número de documento
            $passwordPlano = $datos['numero_documento'];
            $passwordHash  = password_hash($passwordPlano, PASSWORD_DEFAULT);

            // Insertar en usuarios
            $stmtUsuario = $this->pdo->prepare("
                INSERT INTO usuarios 
                (nombres, apellidos, tipo_documento, numero_documento, correo_electronico, telefono,
                 fecha_nacimiento, genero, password_hash, rol_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtUsuario->execute([
                $datos['nombres'],
                $datos['apellidos'],
                $datos['tipo_documento'],
                $datos['numero_documento'],
                $datos['correo_electronico'],
                $datos['telefono'],
                $datos['fecha_nacimiento'],
                $datos['genero'],
                $passwordHash,
                3 // rol estudiante
            ]);

            $usuario_id = $this->pdo->lastInsertId();

            // Insertar en estudiantes
            $stmtEstudiante = $this->pdo->prepare("
                INSERT INTO estudiantes (
                    usuario_id, colegio_id, ficha_id, grado, grupo, jornada, fecha_ingreso,
                    nombre_completo_acudiente, tipo_documento_acudiente, numero_documento_acudiente,
                    telefono_acudiente, parentesco, ocupacion, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtEstudiante->execute([
                $usuario_id,
                $colegio_id, // 🔹 proviene del formulario público
                $datos['ficha_id'],
                $datos['grado'],
                $datos['grupo'],
                $datos['jornada'],
                date('Y-m-d'), // ingreso automático hoy
                $datos['nombre_completo_acudiente'],
                $datos['tipo_documento_acudiente'],
                $datos['numero_documento_acudiente'],
                $datos['telefono_acudiente'],
                $datos['parentesco'],
                $datos['ocupacion'],
                'Activo'
            ]);

            $estudiante_id = $this->pdo->lastInsertId();

            // Ficha médica (opcional)
            if (!empty($datos['ficha_medica']) && is_array($datos['ficha_medica'])) {
                $fm = $datos['ficha_medica'];
                $stmtFM = $this->pdo->prepare("\n                    INSERT INTO ficha_medica_estudiantes (\n                      estudiante_id, padece_enfermedad, enfermedad_detalle, tiene_alergias, alergias_detalle,\n                      medicamento_permanente, medicamento_detalle, discapacidad, discapacidad_detalle, cursos_tecnoacademia\n                    ) VALUES (?,?,?,?,?,?,?,?,?,?)\n                    ON DUPLICATE KEY UPDATE\n                      padece_enfermedad=VALUES(padece_enfermedad), enfermedad_detalle=VALUES(enfermedad_detalle),\n                      tiene_alergias=VALUES(tiene_alergias), alergias_detalle=VALUES(alergias_detalle),\n                      medicamento_permanente=VALUES(medicamento_permanente), medicamento_detalle=VALUES(medicamento_detalle),\n                      discapacidad=VALUES(discapacidad), discapacidad_detalle=VALUES(discapacidad_detalle),\n                      cursos_tecnoacademia=VALUES(cursos_tecnoacademia)
                ");
                $stmtFM->execute([
                    $estudiante_id,
                    !empty($fm['padece_enfermedad']) ? 1 : 0,
                    $fm['enfermedad_detalle'] ?? null,
                    !empty($fm['tiene_alergias']) ? 1 : 0,
                    $fm['alergias_detalle'] ?? null,
                    !empty($fm['medicamento_permanente']) ? 1 : 0,
                    $fm['medicamento_detalle'] ?? null,
                    !empty($fm['discapacidad']) ? 1 : 0,
                    $fm['discapacidad_detalle'] ?? null,
                    !empty($fm['cursos_tecnoacademia']) ? 1 : 0
                ]);
            }

            // Acudientes múltiples (opcional)
            if (!empty($datos['acudientes']) && is_array($datos['acudientes'])) {
                foreach ($datos['acudientes'] as $idx => $acu) {
                    if (empty($acu['nombres']) && empty($acu['apellidos'])) continue;
                    $stmtAcu = $this->pdo->prepare("\n                        INSERT INTO acudientes (nombres, apellidos, tipo_documento, numero_documento, genero, genero_otro, celular, correo, ocupacion)\n                        VALUES (?,?,?,?,?,?,?,?,?)\n                        ON DUPLICATE KEY UPDATE nombres=VALUES(nombres), apellidos=VALUES(apellidos), genero=VALUES(genero), genero_otro=VALUES(genero_otro), celular=VALUES(celular), correo=VALUES(correo), ocupacion=VALUES(ocupacion)
                    ");
                    $stmtAcu->execute([
                        $acu['nombres'] ?? '',
                        $acu['apellidos'] ?? '',
                        $acu['tipo_documento'] ?? 'CC',
                        $acu['numero_documento'] ?? '',
                        $acu['genero'] ?? null,
                        $acu['genero_otro'] ?? null,
                        $acu['celular'] ?? null,
                        $acu['correo'] ?? null,
                        $acu['ocupacion'] ?? null,
                    ]);
                    $acudiente_id = $this->pdo->lastInsertId();
                    if (!$acudiente_id) {
                        $stmtFind = $this->pdo->prepare("SELECT id FROM acudientes WHERE tipo_documento=? AND numero_documento=? LIMIT 1");
                        $stmtFind->execute([$acu['tipo_documento'] ?? 'CC', $acu['numero_documento'] ?? '']);
                        $acudiente_id = $stmtFind->fetchColumn();
                    }
                    if ($acudiente_id) {
                        $stmtLink = $this->pdo->prepare("\n                            INSERT IGNORE INTO estudiante_acudiente (estudiante_id, acudiente_id, parentesco, es_contacto_emergencia, prioridad_llamada)\n                            VALUES (?,?,?,?,?)\n                        ");
                        $stmtLink->execute([
                            $estudiante_id,
                            $acudiente_id,
                            $acu['parentesco'] ?? 'Acudiente',
                            !empty($acu['es_contacto_emergencia']) ? 1 : 0,
                            isset($acu['prioridad_llamada']) ? (int)$acu['prioridad_llamada'] : ($idx === 0 ? 1 : 2),
                        ]);
                    }
                }
            }

            // 🔹 Actualizar el cupo usado de la ficha
            $stmtCupo = $this->pdo->prepare("
                UPDATE fichas
                SET cupo_usado = cupo_usado + 1
                WHERE id = ?
            ");
            $stmtCupo->execute([$datos['ficha_id']]);

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            die("❌ Error al guardar estudiante público: " . $e->getMessage());
        }
    }

    // -------------------------
    // OBTENER TODOS (opcional por ficha)
    // -------------------------
    public function obtenerTodos($ficha_id = null) {
        if ($ficha_id === null) {
            $stmt = $this->pdo->query("
                SELECT 
                    e.id,
                    u.nombres,
                    u.apellidos,
                    CONCAT(u.nombres,' ',u.apellidos) AS nombre_completo,
                    u.tipo_documento,
                    u.numero_documento,
                    u.correo_electronico,
                    u.telefono,
                    e.grado,
                    e.grupo,
                    e.jornada,
                    e.fecha_ingreso,
                    e.estado,
                    c.nombre AS colegio,
                    f.nombre AS ficha,
                    e.nombre_completo_acudiente,
                    e.tipo_documento_acudiente,
                    e.numero_documento_acudiente,
                    e.telefono_acudiente,
                    e.parentesco,
                    e.ocupacion,
                    e.ficha_id
                FROM estudiantes e
                INNER JOIN usuarios u ON e.usuario_id = u.id
                INNER JOIN colegios c ON e.colegio_id = c.id
                INNER JOIN fichas f ON f.id = e.ficha_id
                ORDER BY f.nombre, u.apellidos, u.nombres
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT 
                    e.id,
                    u.nombres,
                    u.apellidos,
                    CONCAT(u.nombres,' ',u.apellidos) AS nombre_completo,
                    u.tipo_documento,
                    u.numero_documento,
                    u.correo_electronico,
                    u.telefono,
                    e.grado,
                    e.grupo,
                    e.jornada,
                    e.fecha_ingreso,
                    e.estado,
                    c.nombre AS colegio,
                    f.nombre AS ficha,
                    e.nombre_completo_acudiente,
                    e.tipo_documento_acudiente,
                    e.numero_documento_acudiente,
                    e.telefono_acudiente,
                    e.parentesco,
                    e.ocupacion,
                    e.ficha_id
                FROM estudiantes e
                INNER JOIN usuarios u ON e.usuario_id = u.id
                INNER JOIN colegios c ON e.colegio_id = c.id
                INNER JOIN fichas f ON f.id = e.ficha_id
                WHERE e.ficha_id = ?
                ORDER BY u.apellidos, u.nombres
            ");
            $stmt->execute([$ficha_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // -------------------------
    // CONTAR ESTUDIANTES
    // -------------------------
    public function contarEstudiantes() {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS total FROM estudiantes");
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    // -------------------------
    // VALIDAR DUPLICADOS POR DOCUMENTO
    // -------------------------
    public function existeDocumento($numero_documento) {
        $stmt = $this->pdo->prepare("SELECT 1 FROM usuarios WHERE numero_documento = ? LIMIT 1");
        $stmt->execute([$numero_documento]);
        return (bool)$stmt->fetchColumn();
    }

    // -------------------------
    // POR COLEGIO
    // -------------------------
    public function obtenerPorColegio($colegioId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                CONCAT(u.nombres,' ',u.apellidos) AS nombre_completo,
                u.tipo_documento,
                u.numero_documento,
                e.grado,
                e.jornada,
                e.estado,
                f.nombre AS ficha,
                e.nombre_completo_acudiente,
                e.telefono_acudiente,
                e.parentesco
            FROM estudiantes e
            INNER JOIN usuarios u ON e.usuario_id = u.id
            INNER JOIN fichas f ON f.id = e.ficha_id
            WHERE e.colegio_id = ?
            ORDER BY f.nombre, u.apellidos, u.nombres
        ");
        $stmt->execute([$colegioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -------------------------
    // BUSCAR POR NOMBRE
    // -------------------------
    public function buscarPorNombre($q) {
        $stmt = $this->pdo->prepare("
            SELECT 
                e.id,
                u.nombres,
                u.apellidos,
                CONCAT(u.nombres,' ',u.apellidos) AS nombre_completo,
                u.tipo_documento,
                u.numero_documento,
                u.correo_electronico AS email,
                u.telefono,
                e.grado,
                e.grupo,
                e.jornada,
                e.fecha_ingreso,
                e.estado,
                c.nombre AS colegio_nombre,
                f.nombre AS ficha_nombre,
                e.nombre_completo_acudiente,
                e.tipo_documento_acudiente,
                e.numero_documento_acudiente,
                e.telefono_acudiente,
                e.parentesco,
                e.ocupacion,
                e.ficha_id
            FROM estudiantes e
            INNER JOIN usuarios u ON e.usuario_id = u.id
            INNER JOIN colegios c ON e.colegio_id = c.id
            INNER JOIN fichas f ON f.id = e.ficha_id
            WHERE u.nombres LIKE ? 
               OR u.apellidos LIKE ? 
               OR u.numero_documento LIKE ?
               OR f.nombre LIKE ?
               OR c.nombre LIKE ?
            ORDER BY u.apellidos ASC, u.nombres ASC
        ");
        $searchTerm = '%' . $q . '%';
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarPorFicha($ficha_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total FROM estudiantes WHERE ficha_id = ?");
        $stmt->execute([$ficha_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
}
