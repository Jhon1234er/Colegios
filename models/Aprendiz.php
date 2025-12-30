<?php
require_once __DIR__ . '/../config/db.php';

class Aprendiz {
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

            $estado = $datos['estado'] ?? (empty($datos['ficha_id']) ? 'Pendiente' : 'Activo');

            // Insertar en usuarios (normalizar fecha y password)
            $fecha_nac = $datos['fecha_nacimiento'] ?? null;
            if (!empty($fecha_nac) && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fecha_nac)) {
                $p = explode('/', $fecha_nac);
                $fecha_nac = $p[2] . '-' . $p[1] . '-' . $p[0];
            }
            $passwordPlano = $datos['password'] ?? ($datos['numero_documento'] ?? '');
            $passwordHash = password_hash((string)$passwordPlano, PASSWORD_DEFAULT);

            $stmtUsuario = $this->pdo->prepare("
                INSERT INTO usuarios 
                (nombres, apellidos, tipo_documento, numero_documento, correo_electronico, correo_institucional, telefono,
                 municipio, direccion, barrio, eps, estrato, rh, fecha_nacimiento, genero, password_hash, rol_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtUsuario->execute([
                $datos['nombres'],
                $datos['apellidos'],
                $datos['tipo_documento'],
                $datos['numero_documento'],
                $datos['correo_electronico'],
                $datos['correo_institucional'] ?? null,
                $datos['telefono'],
                $datos['municipio'] ?? null,
                $datos['direccion'] ?? null,
                $datos['barrio'] ?? null,
                $datos['eps'] ?? null,
                $datos['estrato'] ?? null,
                $datos['rh'] ?? null,
                $fecha_nac,
                $datos['genero'],
                $passwordHash,
                3 // rol estudiante
            ]);

            $usuario_id = $this->pdo->lastInsertId();

            $colegio_id = null;
            if (!empty($datos['colegio_id'])) {
                $colegio_id = (int)$datos['colegio_id'];
            }
            if (!$colegio_id && !empty($datos['ficha_id'])) {
                // Compatibilidad: intentar derivar colegio desde ficha si aún existe la columna
                try {
                    $stmtFichaCol = $this->pdo->prepare("SELECT colegio_id FROM fichas WHERE id = ? LIMIT 1");
                    $stmtFichaCol->execute([$datos['ficha_id']]);
                    $colegio_id = (int)$stmtFichaCol->fetchColumn();
                } catch (\PDOException $eFichaCol) {
                    if ($eFichaCol->getCode() !== '42S22' && $eFichaCol->getCode() !== '42S02') { throw $eFichaCol; }
                }
            }
            if (!$colegio_id) {
                throw new \Exception('Colegio no válido para el aprendiz.');
            }

            // Normalización de celular del acudiente (se mantiene por compatibilidad, aunque ahora se guarda en familiares)
            $telAcud = $datos['celular_acudiente'] ?? ($datos['telefono_acudiente'] ?? null);
            $telAcud = preg_replace('/\D+/', '', (string)$telAcud);
            $telAcud = preg_replace('/^57/', '', $telAcud);
            if ($telAcud === '') {
                $telAcud = null;
            } elseif (!preg_match('/^\d{10}$/', $telAcud)) {
                throw new \RuntimeException('El celular del acudiente debe tener exactamente 10 dígitos.');
            }

            // Insertar en aprendices (nuevo esquema: solo datos académicos del aprendiz)
            $stmtEstudiante = $this->pdo->prepare("
                INSERT INTO aprendices (
                    usuario_id, colegio_id, ficha_id, grado, grupo, jornada, fecha_ingreso,
                    estado, creado_por, creado_en, actualizado_en
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmtEstudiante->execute([
                $usuario_id,
                $colegio_id,
                $datos['ficha_id'],
                $datos['grado'],
                $datos['grupo'],
                $datos['jornada'],
                $datos['fecha_ingreso'],
                $estado,
                $datos['creado_por'] ?? null
            ]);

            // ID del registro en aprendices (para relacionarlo en familiares.aprendiz_id)
            $aprendiz_id = $this->pdo->lastInsertId();

            if (!empty($datos['ficha_id'])) {
                $this->asegurarRelacionFichaColegio((int)$datos['ficha_id'], (int)$colegio_id);
            }

            // Información médica (opcional) → tabla informacion_medica y cursos_tecnoacademia
            if (!empty($datos['ficha_medica']) && is_array($datos['ficha_medica'])) {
                $fm = $datos['ficha_medica'];

                $padece_enfermedad      = !empty($fm['padece_enfermedad']) ? 1 : 0;
                $alergias               = !empty($fm['tiene_alergias']) ? 1 : 0;
                $medicamentosPermanentes = !empty($fm['medicamento_permanente']) ? 1 : 0;
                $discapacidad           = !empty($fm['discapacidad']) ? 1 : 0;

                $stmtFM = $this->pdo->prepare("
                    INSERT INTO informacion_medica (
                      usuario_id, padece_enfermedad, enfermedad_detalle, alergias, alergias_detalle,
                      medicamentos_permanentes, medicamentos_detalle, discapacidad, discapacidad_detalle,
                      creado_en, actualizado_en
                    ) VALUES (?,?,?,?,?,?,?,?,?, NOW(), NOW())
                ");
                $stmtFM->execute([
                    $usuario_id,
                    $padece_enfermedad,
                    $fm['enfermedad_detalle'] ?? null,
                    $alergias,
                    $fm['alergias_detalle'] ?? null,
                    $medicamentosPermanentes,
                    $fm['medicamento_detalle'] ?? null,
                    $discapacidad,
                    $fm['discapacidad_detalle'] ?? null,
                ]);

                // Cursos en Tecnoacademia → tabla cursos_tecnoacademia
                if (array_key_exists('cursos_tecnoacademia', $fm)) {
                    $hizoCursos = !empty($fm['cursos_tecnoacademia']) ? 1 : 0;
                    $detalleCursos = $fm['cursos_tecnoacademia_detalle'] ?? null;

                    $stmtCursos = $this->pdo->prepare("
                        INSERT INTO cursos_tecnoacademia (usuario_id, hizo_cursos, detalle, creado_en)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmtCursos->execute([
                        $usuario_id,
                        $hizoCursos,
                        $detalleCursos,
                    ]);
                }
            }

            // Familiares / acudientes (nuevo esquema) → tabla familiares
            if (!empty($datos['acudientes']) && is_array($datos['acudientes'])) {
                foreach ($datos['acudientes'] as $idx => $acu) {
                    $nombres      = trim((string)($acu['nombres'] ?? ''));
                    $apellidos    = trim((string)($acu['apellidos'] ?? ''));
                    $numDoc       = trim((string)($acu['numero_documento'] ?? ''));
                    if ($nombres === '' && $apellidos === '' && $numDoc === '') {
                        continue;
                    }

                    $nombreCompleto   = trim($nombres . ' ' . $apellidos);
                    $tipoDocumento    = $acu['tipo_documento'] ?? 'CC';
                    $telefonoRaw      = $acu['celular'] ?? null;
                    $telefono         = $telefonoRaw !== null ? preg_replace('/\D+/', '', (string)$telefonoRaw) : null;
                    if ($telefono === '') { $telefono = null; }

                    $parentesco       = $acu['parentesco'] ?? null;
                    $parentescoOtro   = $acu['parentesco_otro'] ?? null;
                    $ocupacion        = $acu['ocupacion'] ?? null;
                    $ocupacionOtro    = $acu['ocupacion_otro'] ?? null;
                    $esAcudiente      = isset($acu['es_acudiente']) && (string)$acu['es_acudiente'] === '1' ? 1 : 0;
                    // Simplificación: si es acudiente, también será contacto de emergencia
                    $contactoEmergencia = $esAcudiente;

                    $stmtFam = $this->pdo->prepare("
                        INSERT INTO familiares (
                            aprendiz_id, nombre_completo, tipo_documento, numero_documento, telefono,
                            parentesco, parentesco_otro, ocupacion, ocupacion_otro, es_acudiente, contacto_emergencia,
                            creado_en, actualizado_en
                        ) VALUES (?,?,?,?,?,?,?,?,?,?,?, NOW(), NOW())
                    ");
                    $stmtFam->execute([
                        $aprendiz_id,
                        $nombreCompleto,
                        $tipoDocumento,
                        $numDoc,
                        $telefono,
                        $parentesco,
                        $parentescoOtro,
                        $ocupacion,
                        $ocupacionOtro,
                        $esAcudiente,
                        $contactoEmergencia,
                    ]);
                }
            }

            // 🔹 Actualizar el cupo usado de la ficha
            if (!empty($datos['ficha_id'])) {
                $stmtCupo = $this->pdo->prepare("
                    UPDATE fichas
                    SET cupo_usado = cupo_usado + 1
                    WHERE id = ?
                ");
                $stmtCupo->execute([$datos['ficha_id']]);
            }

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            die("❌ Error al guardar estudiante: " . $e->getMessage());
        }
    }

    // -------------------------
    // OBTENER APRENDICES PENDIENTES (sin ficha)
    // -------------------------
    public function obtenerPendientes() {
        try {
            $sql = <<<SQL
                SELECT
                    u.id AS id,
                    u.nombres,
                    u.apellidos,
                    u.tipo_documento,
                    u.numero_documento,
                    COALESCE(c.nombre, '') AS colegio,
                    a.grado,
                    a.grupo,
                    COALESCE(a.jornada, '') AS jornada,
                    COALESCE(a.estado, 'Pendiente') AS estado
                FROM aprendices a
                LEFT JOIN usuarios u ON a.usuario_id = u.id
                LEFT JOIN colegios c ON a.colegio_id = c.id
                WHERE (a.ficha_id IS NULL OR a.ficha_id = 0)
                  AND (a.estado IS NULL OR a.estado = 'Pendiente')
                ORDER BY u.apellidos, u.nombres
            SQL;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            // Fallback mínimo sin joins a colegios
            $sql = <<<SQL
                SELECT
                    u.id AS id,
                    u.nombres,
                    u.apellidos,
                    u.numero_documento,
                    '' AS colegio,
                    a.grado,
                    a.grupo,
                    COALESCE(a.jornada, '') AS jornada,
                    COALESCE(a.estado, 'Pendiente') AS estado
                FROM aprendices a
                INNER JOIN usuarios u ON a.usuario_id = u.id
                WHERE (a.ficha_id IS NULL OR a.ficha_id = 0)
                  AND (a.estado IS NULL OR a.estado = 'Pendiente')
                ORDER BY u.apellidos, u.nombres
            SQL;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }

    // -------------------------
    // ASIGNAR APRENDIZ PENDIENTE A UNA FICHA
    // -------------------------
    public function asignarPendienteAFicha($usuario_id, $ficha_id) {
        $stmt = $this->pdo->prepare("
            UPDATE aprendices
            SET ficha_id = ?, estado = 'Activo'
            WHERE usuario_id = ? AND (estado IS NULL OR estado = 'Pendiente')
        ");
        return $stmt->execute([$ficha_id, $usuario_id]);
    }

    // -------------------------
    // GUARDAR ESTUDIANTE (uso público desde registro.php)
    // -------------------------
    public function guardarPublico($datos) {
        try {
            $this->pdo->beginTransaction();

            $estado = $datos['estado'] ?? (empty($datos['ficha_id']) ? 'Pendiente' : 'Activo');

            // ✅ Derivar colegio_id priorizando el valor del formulario
            $colegio_id = null;
            if (!empty($datos['colegio_id'])) {
                $colegio_id = (int)$datos['colegio_id'];
            }
            if (!$colegio_id && !empty($datos['ficha_id'])) {
                try {
                    $stmtFichaCol = $this->pdo->prepare("SELECT colegio_id FROM fichas WHERE id = ? LIMIT 1");
                    $stmtFichaCol->execute([$datos['ficha_id']]);
                    $colegio_id = (int)$stmtFichaCol->fetchColumn();
                } catch (\PDOException $eFichaCol) {
                    if ($eFichaCol->getCode() !== '42S22' && $eFichaCol->getCode() !== '42S02') { throw $eFichaCol; }
                }
            }
            if (!$colegio_id) {
                throw new Exception("Colegio no válido para el aprendiz.");
            }

            // Generar contraseña automática = número de documento
            $passwordPlano = $datos['numero_documento'];
            $passwordHash  = password_hash($passwordPlano, PASSWORD_DEFAULT);

            // Insertar en usuarios (normalizar fecha)
            $fecha_nac = $datos['fecha_nacimiento'] ?? null;
            if (!empty($fecha_nac) && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fecha_nac)) {
                $p = explode('/', $fecha_nac);
                $fecha_nac = $p[2] . '-' . $p[1] . '-' . $p[0];
            }

            $stmtUsuario = $this->pdo->prepare("\n                INSERT INTO usuarios \n                (nombres, apellidos, tipo_documento, numero_documento, correo_electronico, correo_institucional, telefono,\n                 municipio, direccion, barrio, eps, estrato, rh, fecha_nacimiento, genero, password_hash, rol_id) \n                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n            ");
            $stmtUsuario->execute([
                $datos['nombres'],
                $datos['apellidos'],
                $datos['tipo_documento'],
                $datos['numero_documento'],
                $datos['correo_electronico'],
                $datos['correo_institucional'] ?? null,
                $datos['telefono'],
                $datos['municipio'] ?? null,
                $datos['direccion'] ?? null,
                $datos['barrio'] ?? null,
                $datos['eps'] ?? null,
                $datos['estrato'] ?? null,
                $datos['rh'] ?? null,
                $fecha_nac,
                $datos['genero'],
                $passwordHash,
                3 // rol estudiante
            ]);
            $usuario_id = $this->pdo->lastInsertId();

            // Normalización de celular del acudiente (público, se mantiene pero ahora se usa en familiares)
            $telAcud = $datos['celular_acudiente'] ?? ($datos['telefono_acudiente'] ?? null);
            $telAcud = preg_replace('/\D+/', '', (string)$telAcud);
            $telAcud = preg_replace('/^57/', '', $telAcud);
            if ($telAcud === '') {
                $telAcud = null;
            } elseif (!preg_match('/^\d{10}$/', $telAcud)) {
                throw new \RuntimeException('El celular del acudiente debe tener exactamente 10 dígitos.');
            }

            // Insertar en aprendices (público, nuevo esquema)
            $stmtEstudiante = $this->pdo->prepare("\n                INSERT INTO aprendices (\n                    usuario_id, colegio_id, ficha_id, grado, grupo, jornada, fecha_ingreso,\n                    estado, creado_por, creado_en, actualizado_en\n                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())\n            ");
            $stmtEstudiante->execute([
                $usuario_id,
                $colegio_id,
                $datos['ficha_id'] ?? null,
                $datos['grado'],
                $datos['grupo'],
                $datos['jornada'] ?? null,
                date('Y-m-d'),
                $estado,
                $datos['creado_por'] ?? null
            ]);

            // ID del registro en aprendices (para relacionarlo en familiares.aprendiz_id)
            $aprendiz_id = $this->pdo->lastInsertId();

            if (!empty($datos['ficha_id'])) {
                $this->asegurarRelacionFichaColegio((int)$datos['ficha_id'], (int)$colegio_id);
            }

            // Ficha médica (opcional) → informacion_medica y cursos_tecnoacademia
            if (!empty($datos['ficha_medica']) && is_array($datos['ficha_medica'])) {
                $fm = $datos['ficha_medica'];

                $padece_enfermedad      = !empty($fm['padece_enfermedad']) ? 1 : 0;
                $alergias               = !empty($fm['tiene_alergias']) ? 1 : 0;
                $medicamentosPermanentes = !empty($fm['medicamento_permanente']) ? 1 : 0;
                $discapacidad           = !empty($fm['discapacidad']) ? 1 : 0;

                $stmtFM = $this->pdo->prepare("\n                    INSERT INTO informacion_medica (\n                      usuario_id, padece_enfermedad, enfermedad_detalle, alergias, alergias_detalle,\n                      medicamentos_permanentes, medicamentos_detalle, discapacidad, discapacidad_detalle,\n                      creado_en, actualizado_en\n                    ) VALUES (?,?,?,?,?,?,?,?,?, NOW(), NOW())\n                ");
                $stmtFM->execute([
                    $usuario_id,
                    $padece_enfermedad,
                    $fm['enfermedad_detalle'] ?? null,
                    $alergias,
                    $fm['alergias_detalle'] ?? null,
                    $medicamentosPermanentes,
                    $fm['medicamento_detalle'] ?? null,
                    $discapacidad,
                    $fm['discapacidad_detalle'] ?? null,
                ]);

                if (array_key_exists('cursos_tecnoacademia', $fm)) {
                    $hizoCursos = !empty($fm['cursos_tecnoacademia']) ? 1 : 0;
                    $detalleCursos = $fm['cursos_tecnoacademia_detalle'] ?? null;

                    $stmtCursos = $this->pdo->prepare("\n                        INSERT INTO cursos_tecnoacademia (usuario_id, hizo_cursos, detalle, creado_en)\n                        VALUES (?, ?, ?, NOW())\n                    ");
                    $stmtCursos->execute([
                        $usuario_id,
                        $hizoCursos,
                        $detalleCursos,
                    ]);
                }
            }

            // Familiares (acudientes) → tabla familiares
            if (!empty($datos['acudientes']) && is_array($datos['acudientes'])) {
                foreach ($datos['acudientes'] as $idx => $acu) {
                    $nombres      = trim((string)($acu['nombres'] ?? ''));
                    $apellidos    = trim((string)($acu['apellidos'] ?? ''));
                    $numDoc       = trim((string)($acu['numero_documento'] ?? ''));
                    if ($nombres === '' && $apellidos === '' && $numDoc === '') {
                        continue;
                    }

                    $nombreCompleto   = trim($nombres . ' ' . $apellidos);
                    $tipoDocumento    = $acu['tipo_documento'] ?? 'CC';
                    $telefonoRaw      = $acu['celular'] ?? null;
                    $telefono         = $telefonoRaw !== null ? preg_replace('/\D+/', '', (string)$telefonoRaw) : null;
                    if ($telefono === '') { $telefono = null; }

                    $parentesco       = $acu['parentesco'] ?? null;
                    $parentescoOtro   = $acu['parentesco_otro'] ?? null;
                    $ocupacion        = $acu['ocupacion'] ?? null;
                    $ocupacionOtro    = $acu['ocupacion_otro'] ?? null;
                    $esAcudiente      = isset($acu['es_acudiente']) && (string)$acu['es_acudiente'] === '1' ? 1 : 0;
                    $contactoEmergencia = $esAcudiente; // por defecto, el acudiente también es contacto de emergencia

                    $stmtFam = $this->pdo->prepare("\n                        INSERT INTO familiares (\n                            aprendiz_id, nombre_completo, tipo_documento, numero_documento, telefono,\n                            parentesco, parentesco_otro, ocupacion, ocupacion_otro, es_acudiente, contacto_emergencia,\n                            creado_en, actualizado_en\n                        ) VALUES (?,?,?,?,?,?,?,?,?,?,?, NOW(), NOW())\n                    ");
                    $stmtFam->execute([
                        $aprendiz_id,
                        $nombreCompleto,
                        $tipoDocumento,
                        $numDoc,
                        $telefono,
                        $parentesco,
                        $parentescoOtro,
                        $ocupacion,
                        $ocupacionOtro,
                        $esAcudiente,
                        $contactoEmergencia,
                    ]);
                }
            }

            // 🔹 Actualizar el cupo usado de la ficha
            if (!empty($datos['ficha_id'])) {
                $stmtCupo = $this->pdo->prepare("
                    UPDATE fichas
                    SET cupo_usado = cupo_usado + 1
                    WHERE id = ?
                ");
                $stmtCupo->execute([$datos['ficha_id']]);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            // No mostramos errores SQL crudos aquí; dejamos que el controlador
            // los capture y los muestre con un diseño amigable.
            throw $e;
        }
    }

    /**
     * Asegura que exista la relación ficha↔colegio en ficha_colegio.
     * Si ya existe, incrementa cantidad_estudiantes; si no, la crea.
     */
    private function asegurarRelacionFichaColegio(int $ficha_id, int $colegio_id): void
    {
        if ($ficha_id <= 0 || $colegio_id <= 0) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT id, cantidad_estudiantes FROM ficha_colegio WHERE ficha_id = ? AND colegio_id = ? FOR UPDATE");
            $stmt->execute([$ficha_id, $colegio_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($row) {
                $upd = $this->pdo->prepare("UPDATE ficha_colegio SET cantidad_estudiantes = cantidad_estudiantes + 1, actualizado_en = NOW() WHERE id = ?");
                $upd->execute([$row['id']]);
            } else {
                $ins = $this->pdo->prepare("INSERT INTO ficha_colegio (ficha_id, colegio_id, cantidad_estudiantes, creado_en, actualizado_en) VALUES (?, ?, 1, NOW(), NOW())");
                $ins->execute([$ficha_id, $colegio_id]);
            }
        } catch (\PDOException $e) {
            // Si la tabla no existe en algún entorno legacy, no romper el flujo principal
            if ($e->getCode() !== '42S02') {
                throw $e;
            }
        }
    }

    // -------------------------
    // OBTENER TODOS (opcional por ficha)
    // -------------------------
    public function obtenerTodos($ficha_id = null) {
        if ($ficha_id === null) {
            // Intentar con a.ficha_id (nuevo)
            try {
                $sql = <<<SQL
                    SELECT
                        u.id AS id,
                        u.nombres,
                        u.apellidos,
                        CONCAT(u.nombres,' ',u.apellidos) AS nombre_completo,
                        u.tipo_documento AS tipo_documento,
                        u.numero_documento,
                        u.correo_electronico,
                        u.telefono,
                        a.grado,
                        a.grupo,
                        COALESCE(a.jornada, '') AS jornada,
                        a.fecha_ingreso,
                        COALESCE(a.estado, 'Activo') AS estado,
                        c.nombre AS colegio,
                        f.nombre AS ficha,
                        '' AS nombre_completo_acudiente,
                        '' AS tipo_documento_acudiente,
                        '' AS numero_documento_acudiente,
                        '' AS telefono_acudiente,
                        '' AS parentesco,
                        '' AS ocupacion,
                        a.ficha_id AS ficha_id
                    FROM aprendices a
                    INNER JOIN usuarios u ON a.usuario_id = u.id
                    INNER JOIN colegios c ON a.colegio_id = c.id
                    LEFT JOIN fichas f ON f.id = a.ficha_id
                    LEFT JOIN fichas f_as ON f_as.id = (
                        SELECT a2.ficha_id 
                        FROM asistencias a2 
                        WHERE a2.estudiante_id = u.id 
                        ORDER BY a2.fecha DESC 
                        LIMIT 1
                    )
                    ORDER BY f.nombre, u.apellidos, u.nombres
                SQL;
                $stmt = $this->pdo->query($sql);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                if ($e->getCode() !== '42S22') { throw $e; }
                // Variante 1: legacy 'colegio' pero manteniendo ficha_id
                try {
                    $sql = <<<SQL
                        SELECT
                            u.id AS id,
                            u.nombres,
                            u.apellidos,
                            CONCAT(u.nombres,' ',u.apellidos) AS nombre_completo,
                            u.tipo_documento AS tipo_documento,
                            u.numero_documento,
                            u.correo_electronico,
                            u.telefono,
                            a.grado,
                            a.grupo,
                            COALESCE(a.jornada, '') AS jornada,
                            a.fecha_ingreso,
                            COALESCE(a.estado, 'Activo') AS estado,
                            c.nombre AS colegio,
                            f.nombre AS ficha,
                            '' AS nombre_completo_acudiente,
                            '' AS tipo_documento_acudiente,
                            '' AS numero_documento_acudiente,
                            '' AS telefono_acudiente,
                            '' AS parentesco,
                            '' AS ocupacion,
                            a.ficha_id AS ficha_id
                        FROM aprendices a
                        INNER JOIN usuarios u ON a.usuario_id = u.id
                        LEFT JOIN colegios c ON a.colegio = c.id
                        LEFT JOIN fichas f ON f.id = a.ficha_id
                        ORDER BY f.nombre, u.apellidos, u.nombres
                    SQL;
                    $stmt = $this->pdo->query($sql);
                    return $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (\PDOException $e1) {
                    if ($e1->getCode() !== '42S22') { throw $e1; }
                    // Variante 2: legacy total usando a.ficha
                    try {
                        $sql = <<<SQL
                            SELECT
                                u.id AS id,
                                u.nombres,
                                u.apellidos,
                                CONCAT(u.nombres,' ',u.apellidos) AS nombre_completo,
                                u.tipo_documento AS tipo_documento,
                                u.numero_documento,
                                u.correo_electronico,
                                u.telefono,
                                a.grado,
                                a.grupo,
                                COALESCE(a.jornada, '') AS jornada,
                                a.fecha_ingreso,
                                COALESCE(a.estado, 'Activo') AS estado,
                                c.nombre AS colegio,
                                f.nombre AS ficha,
                                '' AS nombre_completo_acudiente,
                                '' AS tipo_documento_acudiente,
                                '' AS numero_documento_acudiente,
                                '' AS telefono_acudiente,
                                '' AS parentesco,
                                '' AS ocupacion,
                                a.ficha AS ficha_id
                            FROM aprendices a
                            INNER JOIN usuarios u ON a.usuario_id = u.id
                            LEFT JOIN colegios c ON a.colegio = c.id
                            LEFT JOIN fichas f ON f.id = a.ficha
                            ORDER BY f.nombre, u.apellidos, u.nombres
                        SQL;
                        $stmt = $this->pdo->query($sql);
                        return $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (\PDOException $e2) {
                        if ($e2->getCode() !== '42S22') { throw $e2; }
                        // Último recurso: sin joins opcionales (no romper)
                        $sql = <<<SQL
                            SELECT
                                u.id AS id,
                                u.nombres,
                                u.apellidos,
                                CONCAT(u.nombres,' ',u.apellidos) AS nombre_completo,
                                u.tipo_documento AS tipo_documento,
                                u.numero_documento,
                                u.correo_electronico,
                                u.telefono,
                                a.grado,
                                a.grupo,
                                COALESCE(a.jornada, '') AS jornada,
                                a.fecha_ingreso,
                                'Activo' AS estado,
                                '' AS colegio,
                                '' AS ficha,
                                '' AS nombre_completo_acudiente,
                                '' AS tipo_documento_acudiente,
                                '' AS numero_documento_acudiente,
                                '' AS telefono_acudiente,
                                '' AS parentesco,
                                '' AS ocupacion,
                                NULL AS ficha_id
                            FROM aprendices a
                            INNER JOIN usuarios u ON a.usuario_id = u.id
                            ORDER BY u.apellidos, u.nombres
                        SQL;
                        $stmt = $this->pdo->query($sql);
                        return $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                }
            }
        } else {
            // Intentar con a.ficha_id (nuevo)
            try {
                $sql = <<<SQL
                    SELECT
                        u.id AS id,
                        u.nombres,
                        u.apellidos,
                        CONCAT(u.nombres,' ',u.apellidos) AS nombre_completo,
                        u.tipo_documento AS tipo_documento,
                        u.numero_documento,
                        u.correo_electronico,
                        u.telefono,
                        a.grado,
                        a.grupo,
                        COALESCE(a.jornada, '') AS jornada,
                        a.fecha_ingreso,
                        COALESCE(a.estado, 'Activo') AS estado,
                        c.nombre AS colegio,
                        f.nombre AS ficha,
                        '' AS nombre_completo_acudiente,
                        '' AS tipo_documento_acudiente,
                        '' AS numero_documento_acudiente,
                        '' AS telefono_acudiente,
                        '' AS parentesco,
                        '' AS ocupacion,
                        a.ficha_id AS ficha_id
                    FROM aprendices a
                    INNER JOIN usuarios u ON a.usuario_id = u.id
                    INNER JOIN colegios c ON a.colegio_id = c.id
                    LEFT JOIN fichas f ON f.id = a.ficha_id
                    WHERE a.ficha_id = ?
                    ORDER BY u.apellidos, u.nombres
                SQL;
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$ficha_id]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                if ($e->getCode() !== '42S22') { throw $e; }
                // Fallback legacy con a.ficha
                $sql = <<<SQL
                    SELECT
                        u.id AS id,
                        u.nombres,
                        u.apellidos,
                        CONCAT(u.nombres,' ',u.apellidos) AS nombre_completo,
                        u.tipo_documento AS tipo_documento,
                        u.numero_documento,
                        u.correo_electronico,
                        u.telefono,
                        a.grado,
                        a.grupo,
                        COALESCE(a.jornada, '') AS jornada,
                        a.fecha_ingreso,
                        'Activo' AS estado,
                        c.nombre AS colegio,
                        f.nombre AS ficha,
                        '' AS nombre_completo_acudiente,
                        '' AS tipo_documento_acudiente,
                        '' AS numero_documento_acudiente,
                        '' AS telefono_acudiente,
                        '' AS parentesco,
                        '' AS ocupacion,
                        a.ficha AS ficha_id
                    FROM aprendices a
                    INNER JOIN usuarios u ON a.usuario_id = u.id
                    INNER JOIN colegios c ON a.colegio_id = c.id
                    LEFT JOIN fichas f ON f.id = a.ficha
                    WHERE a.ficha = ?
                    ORDER BY u.apellidos, u.nombres
                SQL;
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$ficha_id]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }

    // -------------------------
    // BUSCAR POR NOMBRE
    // -------------------------
    public function buscarPorNombre($q) {
        $searchTerm = '%' . $q . '%';
        try {
            // Esquema nuevo: a.ficha_id
            $sqlNuevo = "\n                SELECT \n                    u.id AS id,\n                    __APR_ID__ AS aprendiz_id,\n                    u.nombres,\n                    u.apellidos,\n                    CONCAT(u.nombres,' ',u.apellidos) AS nombre_completo,\n                    u.numero_documento,\n                    u.correo_electronico AS email,\n                    u.telefono,\n                    a.grado,\n                    a.grupo,\n                    a.fecha_ingreso,\n                    'Activo' AS estado,\n                    c.nombre AS colegio_nombre,\n                    f.nombre AS ficha_nombre,\n                    a.ficha_id AS ficha_id\n                FROM aprendices a\n                INNER JOIN usuarios u ON a.usuario_id = u.id\n                INNER JOIN colegios c ON a.colegio_id = c.id\n                LEFT JOIN fichas f ON f.id = a.ficha_id\n                WHERE u.nombres LIKE ? \n                   OR u.apellidos LIKE ? \n                   OR u.numero_documento LIKE ?\n                   OR f.nombre LIKE ?\n                   OR c.nombre LIKE ?\n                ORDER BY u.apellidos ASC, u.nombres ASC\n            ";
            $aprendizCols = ['a.id', 'a.usuario_id', 'a.usuario'];
            foreach ($aprendizCols as $col) {
                try {
                    $stmt = $this->pdo->prepare(str_replace('__APR_ID__', $col, $sqlNuevo));
                    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($rows !== false && count($rows)) return $rows;
                } catch (\PDOException $e2) {
                    if ($e2->getCode() !== '42S22' && strpos($e2->getMessage(), 'Unknown column') === false) { throw $e2; }
                    // probar siguiente variante
                }
            }
            // si no hubo resultados, seguir al catch general para probar legacy
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S22' && strpos($e->getMessage(), 'Unknown column') === false) { throw $e; }
            // Esquema legacy: a.ficha
            $sqlLegacy = "\n                SELECT \n                    u.id AS id,\n                    __APR_ID__ AS aprendiz_id,\n                    u.nombres,\n                    u.apellidos,\n                    CONCAT(u.nombres,' ',u.apellidos) AS nombre_completo,\n                    u.numero_documento,\n                    u.correo_electronico AS email,\n                    u.telefono,\n                    a.grado,\n                    a.grupo,\n                    a.fecha_ingreso,\n                    'Activo' AS estado,\n                    c.nombre AS colegio_nombre,\n                    f.nombre AS ficha_nombre,\n                    a.ficha AS ficha_id\n                FROM aprendices a\n                INNER JOIN usuarios u ON a.usuario_id = u.id\n                INNER JOIN colegios c ON a.colegio_id = c.id\n                LEFT JOIN fichas f ON f.id = a.ficha\n                WHERE u.nombres LIKE ? \n                   OR u.apellidos LIKE ? \n                   OR u.numero_documento LIKE ?\n                   OR f.nombre LIKE ?\n                   OR c.nombre LIKE ?\n                ORDER BY u.apellidos ASC, u.nombres ASC\n            ";
            $aprendizColsLegacy = ['a.id', 'a.usuario_id', 'a.usuario'];
            foreach ($aprendizColsLegacy as $col) {
                try {
                    $stmt = $this->pdo->prepare(str_replace('__APR_ID__', $col, $sqlLegacy));
                    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($rows !== false && count($rows)) return $rows;
                } catch (\PDOException $e3) {
                    if ($e3->getCode() !== '42S22' && strpos($e3->getMessage(), 'Unknown column') === false) { throw $e3; }
                }
            }
            return [];
        }
    }

    // -------------------------
    // CONTAR APRENDICES (total)
    // -------------------------
    public function contarAprendices() {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM aprendices");
        return (int)$stmt->fetchColumn();
    }

    // -------------------------
    // CONTAR POR FICHA
    // -------------------------
    public function contarPorFicha($ficha_id) {
        try {
            // Esquema actual: ficha_id
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM aprendices WHERE ficha_id = ?");
            $stmt->execute([$ficha_id]);
            return (int)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S22' && strpos($e->getMessage(), 'Unknown column') === false) { throw $e; }
            // Legacy: columna ficha
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM aprendices WHERE ficha = ?");
            $stmt->execute([$ficha_id]);
            return (int)$stmt->fetchColumn();
        }
    }

    // -------------------------
    // EXISTE DOCUMENTO (para evitar duplicados en importación)
    // -------------------------
    public function existeDocumento($numero_documento) {
        if ($numero_documento === null || $numero_documento === '') { return false; }
        try {
            $stmt = $this->pdo->prepare("SELECT 1 FROM usuarios WHERE numero_documento = ? LIMIT 1");
            $stmt->execute([$numero_documento]);
            return (bool)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S22' && strpos($e->getMessage(), 'Unknown column') === false) { throw $e; }
            // Fallback legacy: algunas BD pueden usar 'documento'
            $stmt = $this->pdo->prepare("SELECT 1 FROM usuarios WHERE documento = ? LIMIT 1");
            $stmt->execute([$numero_documento]);
            return (bool)$stmt->fetchColumn();
        }
    }

    // -------------------------
    // OBTENER POR COLEGIO (para endpoints públicos/dashboard)
    // Versión protegida: aprendices + usuarios + acudiente (familiares) + info médica.
    // Nunca deja salir una PDOException; en error devuelve [].
    // -------------------------
    public function obtenerPorColegio($colegio_id) {
        try {
            // Consulta principal (basada en la que probaste en phpMyAdmin)
            $sql = <<<SQL
                SELECT
                    -- IDs
                    u.id AS id,
                    a.usuario_id AS aprendiz_id,
                    a.usuario_id AS usuario_id,
                    NULL AS fam_aprendiz_id,

                    -- Datos del usuario
                    COALESCE(u.nombres, '') AS nombres,
                    COALESCE(u.apellidos, '') AS apellidos,
                    TRIM(CONCAT(COALESCE(u.nombres, ''),' ',COALESCE(u.apellidos, ''))) AS nombre_completo,
                    COALESCE(u.numero_documento, '') AS numero_documento,
                    COALESCE(u.correo_electronico, '') AS email,
                    COALESCE(u.telefono, '') AS telefono,

                    -- Datos del aprendiz
                    COALESCE(a.grado, '') AS grado,
                    COALESCE(a.jornada, '') AS jornada,
                    COALESCE(a.grupo, '') AS grupo,
                    a.ficha_id AS ficha_id,

                    -- Compatibilidad con el dashboard (ficha)
                    '' AS ficha_nombre,
                    '' AS numero_ficha,
                    COALESCE(a.ficha_id, '') AS ficha,
                    '' AS codigo_ficha,

                    -- Datos del acudiente (familiares)
                    COALESCE(fam.nombre_completo_acudiente, '') AS nombre_completo_acudiente,
                    COALESCE(fam.telefono_acudiente, '') AS telefono_acudiente,
                    COALESCE(fam.parentesco_acudiente, '') AS parentesco,
                    COALESCE(fam.ocupacion_acudiente, '') AS ocupacion,

                    -- Información médica (toda)
                    im.padece_enfermedad,
                    im.enfermedad_detalle,
                    im.alergias,
                    im.alergias_detalle,
                    im.medicamentos_permanentes,
                    im.medicamentos_detalle,
                    im.discapacidad,
                    im.discapacidad_detalle

                FROM aprendices a

                -- Usuario
                LEFT JOIN usuarios u
                    ON a.usuario_id = u.id

                -- Acudiente
                LEFT JOIN (
                    SELECT
                        aprendiz_id,
                        MAX(nombre_completo) AS nombre_completo_acudiente,
                        MAX(telefono) AS telefono_acudiente,
                        MAX(COALESCE(parentesco, parentesco_otro)) AS parentesco_acudiente,
                        MAX(COALESCE(ocupacion, ocupacion_otro)) AS ocupacion_acudiente
                    FROM familiares
                    WHERE es_acudiente = 1
                    GROUP BY aprendiz_id
                ) fam
                    ON fam.aprendiz_id = a.usuario_id

                -- Información médica
                LEFT JOIN informacion_medica im
                    ON im.usuario_id = u.id

                WHERE a.colegio_id = :cid
                ORDER BY u.apellidos, u.nombres
            SQL;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':cid' => $colegio_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            // Si falla por columnas/tablas (por ejemplo en otra instalación), usar versión simple
            if (
                $e->getCode() === '42S22' || $e->getCode() === '42S02' ||
                strpos($e->getMessage(), 'Unknown column') !== false ||
                strpos($e->getMessage(), 'Base table or view not found') !== false
            ) {
                try {
                    $sql = <<<SQL
                        SELECT
                            u.id AS id,
                            a.usuario_id AS aprendiz_id,
                            a.usuario_id AS usuario_id,
                            NULL AS fam_aprendiz_id,
                            COALESCE(u.nombres, '') AS nombres,
                            COALESCE(u.apellidos, '') AS apellidos,
                            TRIM(CONCAT(COALESCE(u.nombres, ''),' ',COALESCE(u.apellidos, ''))) AS nombre_completo,
                            COALESCE(u.numero_documento, '') AS numero_documento,
                            COALESCE(u.correo_electronico, '') AS email,
                            COALESCE(u.telefono, '') AS telefono,
                            COALESCE(a.grado, '') AS grado,
                            COALESCE(a.grupo, '') AS grupo,
                            COALESCE(a.jornada, '') AS jornada,
                            a.ficha_id AS ficha_id,
                            '' AS ficha_nombre,
                            '' AS numero_ficha,
                            COALESCE(a.ficha_id, '') AS ficha,
                            '' AS codigo_ficha,
                            '' AS nombre_completo_acudiente,
                            '' AS telefono_acudiente,
                            '' AS parentesco,
                            '' AS ocupacion
                        FROM aprendices a
                        LEFT JOIN usuarios u ON a.usuario_id = u.id
                        WHERE a.colegio_id = :cid
                        ORDER BY u.apellidos, u.nombres
                    SQL;

                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([':cid' => $colegio_id]);
                    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (\PDOException $e2) {
                    error_log('Aprendiz::obtenerPorColegio FALLBACK ERROR: ' . $e2->getMessage());
                    return [];
                }
            }

            error_log('Aprendiz::obtenerPorColegio ERROR: ' . $e->getMessage());
            return [];
        }
    }

    // -------------------------
    // DATOS BÁSICOS POR USUARIO
    // -------------------------
    public function obtenerBasicoPorUsuarioId($usuario_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.id AS usuario_id,
                u.nombres,
                u.apellidos,
                u.tipo_documento,
                u.numero_documento,
                u.correo_electronico,
                u.telefono,
                a.grado,
                a.grupo,
                a.jornada,
                a.estado,
                a.ficha_id
            FROM aprendices a
            INNER JOIN usuarios u ON a.usuario_id = u.id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$usuario_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function actualizarBasicoPorUsuarioId($usuario_id, array $datos) {
        try {
            $this->pdo->beginTransaction();

            // Actualizar datos básicos en usuarios
            $stmtU = $this->pdo->prepare("
                UPDATE usuarios
                SET 
                    nombres = ?,
                    apellidos = ?,
                    tipo_documento = ?,
                    numero_documento = ?,
                    correo_electronico = ?,
                    telefono = ?
                WHERE id = ?
            ");
            $stmtU->execute([
                $datos['nombres'] ?? '',
                $datos['apellidos'] ?? '',
                $datos['tipo_documento'] ?? '',
                $datos['numero_documento'] ?? '',
                $datos['correo_electronico'] ?? '',
                $datos['telefono'] ?? '',
                $usuario_id,
            ]);

            // Actualizar datos académicos básicos en aprendices
            $stmtA = $this->pdo->prepare("
                UPDATE aprendices
                SET 
                    grado = ?,
                    grupo = ?,
                    jornada = ?,
                    actualizado_en = NOW()
                WHERE usuario_id = ?
            ");
            $stmtA->execute([
                $datos['grado'] ?? null,
                $datos['grupo'] ?? null,
                $datos['jornada'] ?? null,
                $usuario_id,
            ]);

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // -------------------------
    // SUSPENDER APRENDIZ POR USUARIO
    // -------------------------
    public function suspenderPorUsuarioId($usuario_id) {
        $stmt = $this->pdo->prepare("
            UPDATE aprendices
            SET estado = 'Suspendido', actualizado_en = NOW()
            WHERE usuario_id = ?
        ");
        return $stmt->execute([$usuario_id]);
    }

    // -------------------------
    // ACTIVAR APRENDIZ POR USUARIO
    // -------------------------
    public function activarPorUsuarioId($usuario_id) {
        $stmt = $this->pdo->prepare("
            UPDATE aprendices
            SET estado = 'Activo', actualizado_en = NOW()
            WHERE usuario_id = ?
        ");
        return $stmt->execute([$usuario_id]);
    }
}
