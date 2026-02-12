<?php
require_once __DIR__ . '/../config/db.php';

class Facilitador {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::conectar();
    }

    public function obtenerTodos() {
        $stmt = $this->pdo->query("\n            SELECT u.nombres, u.apellidos, u.tipo_documento, u.numero_documento,\n                   u.correo_electronico, u.telefono,\n                   f.id AS profesor_id,\n                   f.titulo_academico, f.especialidad, f.fecha_ingreso,\n                   f.tipo_contrato\n            FROM facilitadores f\n            JOIN usuarios u ON f.usuario = u.id\n            ORDER BY f.fecha_ingreso DESC\n        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardar($datos) {
        try {
            $this->pdo->beginTransaction();

            // 1. Insertar usuario (orden/columnas según solicitud)
            $stmtUsuario = $this->pdo->prepare("\n  INSERT INTO usuarios\n    (rol_id, nombres, apellidos, tipo_documento, numero_documento,\n     genero, genero_otro, fecha_nacimiento, rh,\n     correo_electronico, correo_institucional, telefono, municipio, direccion, barrio,\n     eps, eps_otro, estrato,\n     password_hash, estado_id, creado_en, actualizado_en)\n  VALUES\n    (?, ?, ?, ?, ?,\n     ?, ?, ?, ?,\n     ?, ?, ?, ?, ?, ?,\n     ?, ?, ?,\n     ?, ?, NOW(), NOW())\n");
            $passwordHash = password_hash($datos['password'], PASSWORD_DEFAULT);
            $rh = isset($datos['rh']) ? strtoupper(trim((string)$datos['rh'])) : null;
            $validRH = ['O+','O-','A+','A-','B+','B-','AB+','AB-'];
            if (!in_array($rh, $validRH, true)) { $rh = null; }

            $stmtUsuario->execute([
              $datos['rol_id'],
              $datos['nombres'],
              $datos['apellidos'],
              $datos['tipo_documento'],
              $datos['numero_documento'],

              $datos['genero'] ?? null,
              $datos['genero_otro'] ?? null,
              $datos['fecha_nacimiento'],
              $rh,

              $datos['correo_electronico'],
              $datos['correo_institucional'] ?? null,
              $datos['telefono'] ?? null,
              $datos['municipio'] ?? null,
              $datos['direccion'] ?? null,
              $datos['barrio'] ?? null,

              $datos['eps'] ?? null,
              $datos['eps_otro'] ?? null,
              $datos['estrato'] ?? null,

              $passwordHash,
              (int)($datos['estado'] ?? 1)
            ]);

            $usuario_id = $this->pdo->lastInsertId();

            // 2. Insertar facilitador (llenando ambas columnas: usuario y usuario_id)
            $stmtFac = $this->pdo->prepare("\n  INSERT INTO facilitadores\n    (usuario, usuario_id, titulo_academico, especialidad, fecha_ingreso, tipo_contrato, creado_en, actualizado_en)\n  VALUES\n    (?, ?, ?, ?, ?, ?, NOW(), NOW())\n");

            $stmtFac->execute([
              $usuario_id,
              $usuario_id,
              trim((string)($datos['titulo_academico'] ?? '')),
              trim((string)($datos['especialidad']    ?? '')),
              $datos['fecha_ingreso'] ?? date('Y-m-d'),
              ($datos['tipo_contrato'] ?? ($datos['tip_contrato'] ?? 'contratista'))
            ]);

            $facilitador_id = $this->pdo->lastInsertId();
            $this->pdo->commit();
            return [(int)$usuario_id, (int)$facilitador_id];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function contarFacilitadores() {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS total FROM facilitadores");
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    public function buscarPorNombre($q) {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.nombres, u.apellidos, u.numero_documento,
                   u.correo_electronico, u.telefono,
                   f.id AS id,
                   f.titulo_academico, f.especialidad, f.fecha_ingreso,
                   f.tipo_contrato
            FROM facilitadores f
            JOIN usuarios u ON f.usuario = u.id
            WHERE u.nombres LIKE ? 
               OR u.apellidos LIKE ? 
               OR f.especialidad LIKE ?
               OR u.numero_documento LIKE ?
            ORDER BY u.apellidos ASC, u.nombres ASC
        ");
        $searchTerm = '%' . $q . '%';
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las fichas asignadas a un profesor con sus respectivos horarios
     */
    public function obtenerFichasPorFacilitador($facilitador_id) {
        try {
            // 1) Esquema actual: tabla puente facilitador_ficha
            try {
                $stmt = $this->pdo->prepare("\n                    SELECT DISTINCT f.id, f.nombre, f.dias_semana, COALESCE(f.numero, f.id) AS numero\n                    FROM fichas f\n                    INNER JOIN facilitador_ficha ff ON f.id = ff.ficha_id\n                    WHERE ff.facilitador_id = ?\n                    ORDER BY f.nombre\n                ");
                $stmt->execute([$facilitador_id]);
                $fichas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if (empty($fichas)) {
                    // Fallback: relación directa por columna fichas.facilitador_id
                    $stmt2 = $this->pdo->prepare("\n                        SELECT f.id, f.nombre, f.dias_semana, COALESCE(f.numero, f.id) AS numero\n                        FROM fichas f\n                        WHERE f.facilitador_id = ?\n                        ORDER BY f.nombre\n                    ");
                    $stmt2->execute([$facilitador_id]);
                    $fichas = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }
            } catch (PDOException $e) {
                // Si la tabla puente no existe, ir directo al fallback de columna
                if ($e->getCode() !== '42S02') throw $e;
                $stmt = $this->pdo->prepare("\n                    SELECT f.id, f.nombre, f.dias_semana, COALESCE(f.numero, f.id) AS numero\n                    FROM fichas f\n                    WHERE f.facilitador_id = ?\n                    ORDER BY f.nombre\n                ");
                $stmt->execute([$facilitador_id]);
                $fichas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            // Procesar cada ficha para agregar próximos horarios (usando horarios_fichas.facilitador_id)
            return array_map(function($ficha) use ($facilitador_id) {
                try {
                    $stmt = $this->pdo->prepare("\n                        SELECT \n                            id, \n                            DATE(fecha_inicio) as fecha,\n                            TIME(fecha_inicio) as hora_inicio,\n                            TIME(fecha_fin) as hora_fin,\n                            fecha_inicio,\n                            fecha_fin,\n                            DAYOFWEEK(fecha_inicio) as dia_numero,\n                            CASE \n                                WHEN DAYOFWEEK(fecha_inicio) = 1 THEN 'domingo'\n                                WHEN DAYOFWEEK(fecha_inicio) = 2 THEN 'lunes'\n                                WHEN DAYOFWEEK(fecha_inicio) = 3 THEN 'martes'\n                                WHEN DAYOFWEEK(fecha_inicio) = 4 THEN 'miércoles'\n                                WHEN DAYOFWEEK(fecha_inicio) = 5 THEN 'jueves'\n                                WHEN DAYOFWEEK(fecha_inicio) = 6 THEN 'viernes'\n                                WHEN DAYOFWEEK(fecha_inicio) = 7 THEN 'sábado'\n                            END as dia_semana,\n                            titulo,\n                            aula,\n                            color,\n                            estado\n                        FROM horarios_fichas \n                        WHERE ficha_id = ? \n                        AND facilitador_id = ?\n                        AND fecha_inicio >= CURDATE()\n                        ORDER BY fecha_inicio\n                        LIMIT 5\n                    ");
                    $stmt->execute([$ficha['id'], $facilitador_id]);
                    $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (\PDOException $e) { throw $e; }

                if (!empty($horarios)) {
                    $dias_semana = array_values(array_unique(array_column($horarios, 'dia_semana')));
                    $primerHorario = $horarios[0];
                    $ficha['hora_inicio'] = $primerHorario['hora_inicio'];
                    $ficha['hora_fin'] = $primerHorario['hora_fin'];
                    $ficha['dia_semana'] = $primerHorario['dia_semana'];
                    $ficha['dias_semana'] = $dias_semana;
                    $ficha['horario_actual'] = $primerHorario;
                    $ficha['proximos_horarios'] = $horarios;
                } else {
                    // Valores por defecto si no hay horarios programados
                    $ficha['hora_inicio'] = '07:00:00';
                    $ficha['hora_fin'] = '17:00:00';
                    $ficha['dia_semana'] = 'lunes';
                    // Intentar parsear dias_semana como JSON primero; si falla, tratar como CSV
                    if (!empty($ficha['dias_semana'])) {
                        $parsed = null;
                        try { $parsed = json_decode($ficha['dias_semana'], true); } catch (\Throwable $t) { $parsed = null; }
                        if (is_array($parsed)) {
                            // si es asociativo, tomar llaves; si es lista, usarla
                            $ficha['dias_semana'] = (array_keys($parsed) !== range(0, count($parsed)-1)) ? array_keys($parsed) : $parsed;
                        } else {
                            $csv = array_filter(array_map('trim', explode(',', $ficha['dias_semana'])));
                            $ficha['dias_semana'] = !empty($csv) ? $csv : ['lunes','miércoles','viernes'];
                        }
                    } else {
                        $ficha['dias_semana'] = ['lunes','miércoles','viernes'];
                    }
                    $ficha['horario_actual'] = null;
                    $ficha['proximos_horarios'] = [];
                }
                // Asegurar numero para UI
                if (empty($ficha['numero']) && !empty($ficha['nombre'])) $ficha['numero'] = $ficha['nombre'];
                return $ficha;
            }, $fichas);

        } catch (Exception $e) {
            error_log("Error en obtenerFichasPorFacilitador: " . $e->getMessage());
            error_log("Trace: " . $e->getTraceAsString());
            return [];
        }
    }


    public function obtenerPorColegio($colegioId) {
        try {
            // -1) Esquema real según tu BD (ver capturas):
            //     a) fichas.facilitador_id  -> facilitador principal de la ficha.
            //     b) fichas_compartidas (ficha_id, facilitador_id, estado) -> facilitadores a quienes se comparte la ficha.
            //     c) ficha_colegio (ficha_id, colegio_id) -> vincula fichas con colegios.
            try {
                // a) Facilitadores principales de las fichas del colegio
                $sqlPropios = "
                    SELECT DISTINCT
                        f.id AS profesor_id,
                        u.nombres,
                        u.apellidos,
                        u.correo_electronico,
                        u.correo_institucional,
                        u.telefono,
                        f.tipo_contrato AS tip_contrato
                    FROM ficha_colegio fc
                    INNER JOIN fichas fi       ON fi.id = fc.ficha_id
                    INNER JOIN facilitadores f ON f.id = fi.facilitador_id
                    INNER JOIN usuarios u      ON f.usuario = u.id
                    WHERE fc.colegio_id = :cid
                ";

                // b) Facilitadores con fichas compartidas del colegio (tabla fichas_compartidas)
                $sqlCompartidos = "
                    SELECT DISTINCT
                        f2.id AS profesor_id,
                        u2.nombres,
                        u2.apellidos,
                        u2.correo_electronico,
                        u2.correo_institucional,
                        u2.telefono,
                        f2.tipo_contrato AS tip_contrato
                    FROM ficha_colegio fc
                    INNER JOIN fichas fi              ON fi.id = fc.ficha_id
                    INNER JOIN fichas_compartidas fc2 ON fc2.ficha_id = fi.id
                    INNER JOIN facilitadores f2       ON f2.id = fc2.facilitador_id
                    INNER JOIN usuarios u2            ON f2.usuario = u2.id
                    WHERE fc.colegio_id = :cid2
                      AND fc2.estado = 'Aceptada'
                ";

                $sqlN = "{$sqlPropios} UNION {$sqlCompartidos} ORDER BY apellidos, nombres";
                $stmtN = $this->pdo->prepare($sqlN);
                $stmtN->execute([':cid' => $colegioId, ':cid2' => $colegioId]);
                $rowsN = $stmtN->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if (!empty($rowsN)) {
                    return $rowsN;
                }
            } catch (\PDOException $eN) {
                if ($eN->getCode() !== '42S22' && $eN->getCode() !== '42S02') {
                    throw $eN; // error real distinto a columnas/tablas faltantes
                }
                // Fallback mínimo: mismo esquema pero usando facilitadores.usuario_id en lugar de facilitadores.usuario
                try {
                    $sqlPropios2 = str_replace('f.usuario = u.id', 'f.usuario_id = u.id', $sqlPropios);
                    $sqlCompartidos2 = str_replace('f2.usuario = u2.id', 'f2.usuario_id = u2.id', $sqlCompartidos);
                    $sqlN2 = "{$sqlPropios2} UNION {$sqlCompartidos2} ORDER BY apellidos, nombres";
                    $stmtN2 = $this->pdo->prepare($sqlN2);
                    $stmtN2->execute([':cid' => $colegioId, ':cid2' => $colegioId]);
                    $rowsN2 = $stmtN2->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    if (!empty($rowsN2)) {
                        return $rowsN2;
                    }
                } catch (\PDOException $eN2) {
                    if ($eN2->getCode() !== '42S22' && $eN2->getCode() !== '42S02') { throw $eN2; }
                    // Si tampoco aplica, continuamos con los fallbacks legacy de abajo.
                }
            }

            // 0) Preferir relación directa por facilitadores.colegio_id (unión con usuarios)
            try {
                $stmt0 = $this->pdo->prepare("\n                    SELECT DISTINCT
                        f.id AS profesor_id,
                        u.nombres, u.apellidos,
                        u.correo_electronico, u.correo_institucional, u.telefono,
                        f.tipo_contrato AS tip_contrato
                    FROM facilitadores f
                    INNER JOIN usuarios u ON f.usuario_id = u.id
                    WHERE f.colegio_id = ?
                    ORDER BY u.apellidos, u.nombres
                ");
                $stmt0->execute([$colegioId]);
                $rows0 = $stmt0->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if (!empty($rows0)) return $rows0;
            } catch (\PDOException $e0) {
                if ($e0->getCode() !== '42S22') { throw $e0; }
                // Variante legacy: f.usuario
                try {
                    $stmt0b = $this->pdo->prepare("\n                        SELECT DISTINCT
                            f.id AS profesor_id,
                            u.nombres, u.apellidos,
                            u.correo_electronico, u.correo_institucional, u.telefono,
                            f.tipo_contrato AS tip_contrato
                        FROM facilitadores f
                        INNER JOIN usuarios u ON f.usuario = u.id
                        WHERE f.colegio_id = ?
                        ORDER BY u.apellidos, u.nombres
                    ");
                    $stmt0b->execute([$colegioId]);
                    $rows0b = $stmt0b->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    if (!empty($rows0b)) return $rows0b;
                } catch (\PDOException $e0b) { if ($e0b->getCode() !== '42S22') { throw $e0b; } }
            }

            // 0b) Esquema nuevo: facilitador_ficha + ficha_colegio
            try {
                // Variante con columna usuario_id
                try {
                    $stmt0c = $this->pdo->prepare("\n                        SELECT DISTINCT
                            f.id AS profesor_id,
                            u.nombres, u.apellidos,
                            u.correo_electronico, u.correo_institucional, u.telefono,
                            f.tipo_contrato AS tip_contrato
                        FROM facilitadores f
                        INNER JOIN usuarios u ON f.usuario_id = u.id
                        INNER JOIN facilitador_ficha ff ON ff.facilitador_id = f.id
                        INNER JOIN ficha_colegio fc ON fc.ficha_id = ff.ficha_id
                        WHERE fc.colegio_id = ?
                        ORDER BY u.apellidos, u.nombres
                    ");
                    $stmt0c->execute([$colegioId]);
                    $rows0c = $stmt0c->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    if (!empty($rows0c)) return $rows0c;
                } catch (\PDOException $e0c1) {
                    if ($e0c1->getCode() !== '42S22' && $e0c1->getCode() !== '42S02') { throw $e0c1; }
                    // Variante legacy: columna usuario
                    $stmt0c = $this->pdo->prepare("\n                        SELECT DISTINCT
                            f.id AS profesor_id,
                            u.nombres, u.apellidos,
                            u.correo_electronico, u.correo_institucional, u.telefono,
                            f.tipo_contrato AS tip_contrato
                        FROM facilitadores f
                        INNER JOIN usuarios u ON f.usuario = u.id
                        INNER JOIN facilitador_ficha ff ON ff.facilitador_id = f.id
                        INNER JOIN ficha_colegio fc ON fc.ficha_id = ff.ficha_id
                        WHERE fc.colegio_id = ?
                        ORDER BY u.apellidos, u.nombres
                    ");
                    $stmt0c->execute([$colegioId]);
                    $rows0c = $stmt0c->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    if (!empty($rows0c)) return $rows0c;
                }
            } catch (\PDOException $e0c) {
                if (!in_array($e0c->getCode(), ['42S22','42S02'], true)) { throw $e0c; }
            }

            // 1) Esquema con tabla puente: facilitador_ficha -> fichas.colegio_id
            try {
                $stmt = $this->pdo->prepare("\n                    SELECT DISTINCT
                        f.id AS profesor_id,
                        u.nombres, u.apellidos,
                        u.correo_electronico, u.correo_institucional, u.telefono,
                        f.tipo_contrato AS tip_contrato
                    FROM facilitadores f
                    INNER JOIN usuarios u ON f.usuario = u.id
                    INNER JOIN facilitador_ficha ff ON ff.facilitador_id = f.id
                    INNER JOIN fichas fi ON fi.id = ff.ficha_id
                    WHERE fi.colegio_id = ?
                    ORDER BY u.apellidos, u.nombres
                ");
                $stmt->execute([$colegioId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if (!empty($rows)) return $rows;
            } catch (\PDOException $e1a) {
                if (!in_array($e1a->getCode(), ['42S22','42S02'], true)) { throw $e1a; }
            }
            // 1a) Variante con f.usuario_id
            try {
                $stmt = $this->pdo->prepare("\n                    SELECT DISTINCT
                        f.id AS profesor_id,
                        u.nombres, u.apellidos,
                        u.correo_electronico, u.correo_institucional, u.telefono,
                        f.tipo_contrato AS tip_contrato
                    FROM facilitadores f
                    INNER JOIN usuarios u ON f.usuario_id = u.id
                    INNER JOIN facilitador_ficha ff ON ff.facilitador_id = f.id
                    INNER JOIN fichas fi ON fi.id = ff.ficha_id
                    WHERE fi.colegio_id = ?
                    ORDER BY u.apellidos, u.nombres
                ");
                $stmt->execute([$colegioId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if (!empty($rows)) return $rows;
            } catch (\PDOException $e1a2) {
                if (!in_array($e1a2->getCode(), ['42S22','42S02'], true)) { throw $e1a2; }
            }
        } catch (\PDOException $e) {
            // si falla por tabla/columna inexistente, intentamos esquema legacy
            if (!in_array($e->getCode(), ['42S02','42S22'], true)) { throw $e; }
        }

        // 1b) Fallback: relación directa en fichas.facilitador_id (dos variantes de join a usuarios)
        try {
            $stmt1b = $this->pdo->prepare("\n                SELECT DISTINCT
                    f.id AS profesor_id,
                    u.nombres, u.apellidos,
                    u.correo_electronico, u.correo_institucional, u.telefono,
                    f.tipo_contrato AS tip_contrato
                FROM facilitadores f
                INNER JOIN usuarios u ON f.usuario_id = u.id
                INNER JOIN fichas fi ON fi.facilitador_id = f.id
                WHERE fi.colegio_id = ?
                ORDER BY u.apellidos, u.nombres
            ");
            $stmt1b->execute([$colegioId]);
            $rows1b = $stmt1b->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows1b)) return $rows1b;    
        } catch (\PDOException $e1b) {
            if (!in_array($e1b->getCode(), ['42S22','42S02'], true)) { throw $e1b; }
        }
        try {
            $stmt1b2 = $this->pdo->prepare("\n                SELECT DISTINCT
                    f.id AS profesor_id,
                    u.nombres, u.apellidos,
                    u.correo_electronico, u.correo_institucional, u.telefono,
                    f.tipo_contrato AS tip_contrato
                FROM facilitadores f
                INNER JOIN usuarios u ON f.usuario = u.id
                INNER JOIN fichas fi ON fi.facilitador_id = f.id
                WHERE fi.colegio_id = ?
                ORDER BY u.apellidos, u.nombres
            ");
            $stmt1b2->execute([$colegioId]);
            $rows1b2 = $stmt1b2->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rows1b2)) return $rows1b2;    
        } catch (\PDOException $e1b2) {
            if (!in_array($e1b2->getCode(), ['42S22','42S02'], true)) { throw $e1b2; }
        }

        try {
            // Legacy: columna f.colegio
            $stmt = $this->pdo->prepare("\n                SELECT 
                    f.id AS profesor_id,
                    u.nombres,
                    u.apellidos,
                    u.correo_electronico,
                    u.correo_institucional,
                    u.telefono,
                    f.tipo_contrato AS tip_contrato
                FROM facilitadores f
                INNER JOIN usuarios u ON f.usuario = u.id
                WHERE f.colegio = ?
                ORDER BY u.apellidos, u.nombres
            ");
            $stmt->execute([$colegioId]);
            $legacy = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($legacy)) return $legacy;
        } catch (\PDOException $e2) {
            if (!in_array($e2->getCode(), ['42S22'], true)) { throw $e2; }
        }

        // 1c) Fallback ampliado: fichas.facilitador_id referencia a usuarios.id (dos variantes)
        try {
            $stmtX = $this->pdo->prepare("\n                SELECT DISTINCT
                    f.id AS profesor_id,
                    u.nombres, u.apellidos,
                    u.correo_electronico, u.correo_institucional, u.telefono,
                    f.tipo_contrato AS tip_contrato
                FROM fichas fi
                INNER JOIN usuarios u ON fi.facilitador_id = u.id
                INNER JOIN facilitadores f ON f.usuario_id = u.id
                WHERE fi.colegio_id = ?
                ORDER BY u.apellidos, u.nombres
            ");
            $stmtX->execute([$colegioId]);
            $rowsX = $stmtX->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rowsX)) return $rowsX;
        } catch (\PDOException $ex1) {
            if (!in_array($ex1->getCode(), ['42S22','42S02'], true)) { throw $ex1; }
        }
        try {
            $stmtX2 = $this->pdo->prepare("\n                SELECT DISTINCT
                    f.id AS profesor_id,
                    u.nombres, u.apellidos,
                    u.correo_electronico, u.correo_institucional, u.telefono,
                    f.tipo_contrato AS tip_contrato
                FROM fichas fi
                INNER JOIN usuarios u ON fi.facilitador_id = u.id
                INNER JOIN facilitadores f ON f.usuario = u.id
                WHERE fi.colegio_id = ?
                ORDER BY u.apellidos, u.nombres
            ");
            $stmtX2->execute([$colegioId]);
            $rowsX2 = $stmtX2->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rowsX2)) return $rowsX2;
        } catch (\PDOException $ex2) {
            if (!in_array($ex2->getCode(), ['42S22','42S02'], true)) { throw $ex2; }
        }

        // Último recurso: por fichas.colegio_id vía profesor_ficha (dos variantes de join a usuarios)
        try {
            $stmt = $this->pdo->prepare("\n                SELECT DISTINCT\n                    f.id AS profesor_id,\n                    u.nombres, u.apellidos,\n                    u.correo_electronico, u.correo_institucional, u.telefono,\n                    COALESCE(f.tipo_contrato, f.tip_contrato) AS tip_contrato\n                FROM facilitadores f\n                INNER JOIN usuarios u ON f.usuario_id = u.id\n                INNER JOIN profesor_ficha pf ON pf.profesor_id = f.id\n                INNER JOIN fichas fi ON fi.id = pf.ficha_id\n                WHERE fi.colegio_id = ?\n                ORDER BY u.apellidos, u.nombres\n            ");
            $stmt->execute([$colegioId]);
            $rowsUF = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rowsUF)) return $rowsUF;
        } catch (\PDOException $e2) {
            if (!in_array($e2->getCode(), ['42S22','42S02'], true)) { throw $e2; }
        }
        try {
            $stmt = $this->pdo->prepare("\n                SELECT DISTINCT\n                    f.id AS profesor_id,\n                    u.nombres, u.apellidos,\n                    u.correo_electronico, u.correo_institucional, u.telefono,\n                    COALESCE(f.tipo_contrato, f.tip_contrato) AS tip_contrato\n                FROM facilitadores f\n                INNER JOIN usuarios u ON f.usuario = u.id\n                INNER JOIN profesor_ficha pf ON pf.profesor_id = f.id\n                INNER JOIN fichas fi ON fi.id = pf.ficha_id\n                WHERE fi.colegio_id = ?\n                ORDER BY u.apellidos, u.nombres\n            ");
            $stmt->execute([$colegioId]);
            $rowsLast = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rowsLast)) return $rowsLast;
        } catch (\PDOException $e2b) {
            if (!in_array($e2b->getCode(), ['42S22','42S02'], true)) { throw $e2b; }
        }
        // Fallback sin tabla facilitadores o sin registros: fichas.facilitador_id -> usuarios.id
        try {
            $stmtZ = $this->pdo->prepare("\n                SELECT DISTINCT\n                    u.id AS profesor_id,\n                    u.nombres, u.apellidos,\n                    u.correo_electronico, u.correo_institucional, u.telefono,\n                    NULL AS tip_contrato\n                FROM fichas fi\n                INNER JOIN usuarios u ON fi.facilitador_id = u.id\n                WHERE fi.colegio_id = ?\n                ORDER BY u.apellidos, u.nombres\n            ");
            $stmtZ->execute([$colegioId]);
            $rowsZ = $stmtZ->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($rowsZ)) return $rowsZ;
        } catch (\PDOException $eZ) {
            if (!in_array($eZ->getCode(), ['42S22','42S02'], true)) { throw $eZ; }
        }
        return [];
        }

    public function obtenerTodosExcepto($usuarioId) {
        // Intento 1: join por f.usuario y columna f.tipo_contrato
        try {
            $stmt = $this->pdo->prepare("SELECT f.id, u.nombres, u.apellidos, f.tipo_contrato AS tipo_contrato 
                                          FROM facilitadores f 
                                          INNER JOIN usuarios u ON f.usuario = u.id 
                                          WHERE u.id != ?");
            $stmt->execute([$usuarioId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return $rows;
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S22') { throw $e; }
        }

        // Intento 1b: join por f.usuario y usar tip_contrato
        try {
            $stmt = $this->pdo->prepare("SELECT f.id, u.nombres, u.apellidos, f.tip_contrato AS tipo_contrato 
                                          FROM facilitadores f 
                                          INNER JOIN usuarios u ON f.usuario = u.id 
                                          WHERE u.id != ?");
            $stmt->execute([$usuarioId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return $rows;
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S22') { throw $e; }
        }

        // Intento 2: join por f.usuario_id con tipo_contrato
        try {
            $stmt = $this->pdo->prepare("SELECT f.id, u.nombres, u.apellidos, f.tipo_contrato AS tipo_contrato 
                                          FROM facilitadores f 
                                          INNER JOIN usuarios u ON f.usuario_id = u.id 
                                          WHERE u.id != ?");
            $stmt->execute([$usuarioId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return $rows;
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S22') { throw $e; }
        }

        // Intento 2b: join por f.usuario_id con tip_contrato
        try {
            $stmt = $this->pdo->prepare("SELECT f.id, u.nombres, u.apellidos, f.tip_contrato AS tipo_contrato 
                                          FROM facilitadores f 
                                          INNER JOIN usuarios u ON f.usuario_id = u.id 
                                          WHERE u.id != ?");
            $stmt->execute([$usuarioId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return $rows;
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S22') { throw $e; }
        }

        // Fallback mínimo: sin columna de contrato
        try {
            $stmt = $this->pdo->prepare("SELECT f.id, u.nombres, u.apellidos 
                                          FROM facilitadores f 
                                          INNER JOIN usuarios u ON f.usuario_id = u.id 
                                          WHERE u.id != ?");
            $stmt->execute([$usuarioId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_map(function($r){ $r['tipo_contrato'] = null; return $r; }, $rows);
        } catch (\PDOException $e) {
            // Último intento: join por f.usuario
            $stmt = $this->pdo->prepare("SELECT f.id, u.nombres, u.apellidos 
                                          FROM facilitadores f 
                                          INNER JOIN usuarios u ON f.usuario = u.id 
                                          WHERE u.id != ?");
            $stmt->execute([$usuarioId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_map(function($r){ $r['tipo_contrato'] = null; return $r; }, $rows);
        }
    }

}
