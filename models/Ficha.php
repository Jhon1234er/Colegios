<?php
require_once __DIR__ . '/../config/db.php';

class Ficha {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::conectar();
    }

    // Crear ficha y relacionarla con el profesor que la creó
    public function guardar($nombre, $numero, $cupo_total, $profesor_id, $dias_semana = [], $jornada = null) {
        try {
            $this->pdo->beginTransaction();

            // 🔑 generar token único
            $token = bin2hex(random_bytes(16)); 

            // Convertir días de semana a JSON (intento 1: estructura completa dia=>jornada)
            $dias_json = !empty($dias_semana) ? json_encode($dias_semana) : json_encode(['lunes', 'martes', 'miercoles', 'jueves', 'viernes']);
            // Variante compacta (solo lista de días) para fallback cuando el campo es corto
            $dias_json_compacto = !empty($dias_semana) ? json_encode(array_keys($dias_semana)) : json_encode(['lunes', 'martes', 'miercoles', 'jueves', 'viernes']);

            // Si no viene jornada, intentar deducir una global si todas las jornadas por día son iguales
            $jornada_calc = $jornada;
            if ($jornada_calc === null && is_array($dias_semana) && !empty($dias_semana)) {
                $vals = array_values(array_filter($dias_semana, function($v){ return $v !== null && $v !== ''; }));
                // normalizar valores: mañana/manana, tarde, noche/nocturna => nocturna
                $vals = array_map(function($v){
                    $s = is_string($v) ? strtolower(trim($v)) : $v;
                    $s = str_replace(['á','é','í','ó','ú'], ['a','e','i','o','u'], (string)$s);
                    if ($s === 'noche' || $s === 'nocturno') return 'nocturna';
                    if ($s === 'manana' || $s === 'mañana') return 'manana';
                    if ($s === 'tarde') return 'tarde';
                    if ($s === 'nocturna') return 'nocturna';
                    return $s;
                }, $vals);
                $uniq = array_values(array_unique($vals));
                if (count($uniq) === 1) { $jornada_calc = $uniq[0]; }
                elseif (count($uniq) > 1) { $jornada_calc = 'mixta'; }
            }

            // 1️⃣ Insertar ficha (ya sin depender de colegio_id en la tabla fichas)
            try {
                // Esquema nuevo con *_id (pero sin columna colegio_id)
                try {
                    $stmt = $this->pdo->prepare("\n                        INSERT INTO fichas (numero, nombre, jornada_id, cupo_total, cupo_usado, token, estado_id, dias_semana) \n                        VALUES (?, ?, NULL, ?, 0, ?, 1, ?)\n                    ");
                    $stmt->execute([$numero, $nombre, $cupo_total, $token, $dias_json]);
                } catch (\PDOException $eNew) {
                    if ($eNew->getCode() === '22001') {
                        // Data too long -> reintentar con versión compacta
                        $stmt = $this->pdo->prepare("\n                            INSERT INTO fichas (numero, nombre, jornada_id, cupo_total, cupo_usado, token, estado_id, dias_semana) \n                            VALUES (?, ?, NULL, ?, 0, ?, 1, ?)\n                        ");
                        $stmt->execute([$numero, $nombre, $cupo_total, $token, $dias_json_compacto]);
                    } else {
                        throw $eNew;
                    }
                }
            } catch (\PDOException $e) {
                if ($e->getCode() !== '42S22') { throw $e; }
                // Esquema legacy con columnas sin *_id (también sin colegio_id)
                try {
                    $stmt = $this->pdo->prepare("\n                        INSERT INTO fichas (numero, nombre, jornada, cupo_total, cupo_usado, token, estado, dias_semana) \n                        VALUES (?, ?, ?, ?, 0, ?, 'Activo', ?)\n                    ");
                    $stmt->execute([$numero, $nombre, $jornada_calc, $cupo_total, $token, $dias_json]);
                } catch (\PDOException $eLegacy) {
                    if ($eLegacy->getCode() === '22001') {
                        // Data too long -> reintentar con versión compacta
                        $stmt = $this->pdo->prepare("\n                            INSERT INTO fichas (numero, nombre, jornada, cupo_total, cupo_usado, token, estado, dias_semana) \n                            VALUES (?, ?, ?, ?, 0, ?, 'Activo', ?)\n                        ");
                        $stmt->execute([$numero, $nombre, $jornada_calc, $cupo_total, $token, $dias_json_compacto]);
                    } else {
                        throw $eLegacy;
                    }
                }
            }

            // Obtener el ID de la ficha creada
            $ficha_id = $this->pdo->lastInsertId();

            // 2️⃣ Verificar que el profesor (facilitador) exista
            $stmtCheck = $this->pdo->prepare("SELECT id FROM facilitadores WHERE id = ?");
            $stmtCheck->execute([$profesor_id]);
            $profesor = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$profesor) {
                throw new Exception("❌ Error: El profesor con ID {$profesor_id} no existe en la tabla profesores.");
            }

            // 3️⃣ Insertar relación en tabla puente (preferir facilitador_ficha; fallback a profesor_ficha)
            try {
                $stmt2 = $this->pdo->prepare("\n                    INSERT INTO facilitador_ficha (facilitador_id, ficha_id) \n                    VALUES (?, ?)\n                ");
                $stmt2->execute([$profesor_id, $ficha_id]);
            } catch (\PDOException $e) {
                if ($e->getCode() !== '42S02') { throw $e; }
                $stmt2 = $this->pdo->prepare("\n                    INSERT INTO profesor_ficha (profesor_id, ficha_id) \n                    VALUES (?, ?)\n                ");
                $stmt2->execute([$profesor_id, $ficha_id]);
            }

            // 4️⃣ Intentar dejar grabado en fichas el facilitador_id y la jornada si existen estas columnas
            try {
                $u = $this->pdo->prepare("UPDATE fichas SET facilitador_id = ? WHERE id = ?");
                $u->execute([$profesor_id, $ficha_id]);
            } catch (\PDOException $e) {
                if ($e->getCode() !== '42S22') { throw $e; }
            }
            if ($jornada_calc !== null) {
                try {
                    $u2 = $this->pdo->prepare("UPDATE fichas SET jornada = ? WHERE id = ?");
                    $u2->execute([$jornada_calc, $ficha_id]);
                } catch (\PDOException $e) {
                    if ($e->getCode() !== '42S22') { throw $e; }
                }
            }

            $this->pdo->commit();
            return $ficha_id;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            die("❌ Error al guardar ficha: " . $e->getMessage());
        }
    }

    // 🔹 Obtener todas las fichas (para admin, no filtra)
    public function obtenerTodas() {
        try {
            $stmt = $this->pdo->prepare("\n                SELECT id, numero, nombre, cupo_total, cupo_usado, estado_id AS estado, token\n                FROM fichas\n                ORDER BY id DESC\n            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S22') { throw $e; }
            $stmt = $this->pdo->prepare("\n                SELECT id, numero, nombre, cupo_total, cupo_usado, estado, token\n                FROM fichas\n                ORDER BY id DESC\n            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // 🔹 Obtener fichas solo de un profesor (facilitador)
    public function obtenerTodasPorProfesor($profesor_id) {
        try {
            $stmt = $this->pdo->prepare("\n                SELECT f.id, f.numero, f.nombre, f.cupo_total, f.cupo_usado, f.estado_id AS estado, f.token, f.dias_semana\n                FROM fichas f\n                INNER JOIN facilitador_ficha pf ON f.id = pf.ficha_id\n                WHERE pf.facilitador_id = ?\n                ORDER BY f.id DESC\n            ");
            $stmt->execute([$profesor_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S22') { throw $e; }
            // Fallback solo para columna de estado legacy
            $stmt = $this->pdo->prepare("\n                SELECT f.id, f.numero, f.nombre, f.cupo_total, f.cupo_usado, f.estado AS estado, f.token, f.dias_semana\n                FROM fichas f\n                INNER JOIN facilitador_ficha pf ON f.id = pf.ficha_id\n                WHERE pf.facilitador_id = ?\n                ORDER BY f.id DESC\n            ");
            $stmt->execute([$profesor_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Contar fichas
    public function contarFichas() {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM fichas");
        return (int) $stmt->fetchColumn();
    }

    // Obtener ficha por ID
    public function obtenerPorId($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM fichas WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 🔎 Buscar ficha por token (para el link compartido sin login)
    public function buscarPorToken($token) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM fichas WHERE token = ? AND estado_id = 1");
            $stmt->execute([$token]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S22') { throw $e; }
            $stmt = $this->pdo->prepare("SELECT * FROM fichas WHERE token = ? AND (estado = 'Activo' OR estado IS NULL)");
            $stmt->execute([$token]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // Actualizar cupo usado (cuando se registra un aprendiz)
    public function incrementarCupo($id) {
        $stmt = $this->pdo->prepare("
            UPDATE fichas 
            SET cupo_usado = cupo_usado + 1 
            WHERE id = ? AND cupo_usado < cupo_total
        ");
        return $stmt->execute([$id]);
    }

    // Cambiar estado de ficha
    public function actualizarEstado($id, $estado) {
        // acepta id numérico o mapea texto simple
        try {
            $estado_id = is_numeric($estado) ? (int)$estado : (strtolower($estado) === 'cerrada' ? 2 : 1);
            $stmt = $this->pdo->prepare("UPDATE fichas SET estado_id = ? WHERE id = ?");
            return $stmt->execute([$estado_id, $id]);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S22') { throw $e; }
            $estado_txt = is_numeric($estado) ? ((int)$estado === 1 ? 'Activo' : 'Cerrada') : (string)$estado;
            $stmt = $this->pdo->prepare("UPDATE fichas SET estado = ? WHERE id = ?");
            return $stmt->execute([$estado_txt, $id]);
        }
    }

    // Obtener fichas por colegio usando la tabla puente ficha_colegio
    public function obtenerPorColegio($colegioId) {
        try {
            $stmt = $this->pdo->prepare("\n                SELECT f.id, f.numero, f.nombre\n                FROM fichas f\n                INNER JOIN ficha_colegio fc ON fc.ficha_id = f.id\n                WHERE fc.colegio_id = ?\n                ORDER BY f.nombre ASC\n            ");
            $stmt->execute([$colegioId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            if (!in_array($e->getCode(), ['42S02','42S22'], true)) { throw $e; }
            // Fallback legacy: directamente desde fichas por columnas antiguas
            try {
                $stmt = $this->pdo->prepare("\n                    SELECT id, numero, nombre\n                    FROM fichas\n                    WHERE colegio_id = ?\n                    ORDER BY nombre ASC\n                ");
                $stmt->execute([$colegioId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\PDOException $e2) {
                if ($e2->getCode() !== '42S22') { throw $e2; }
                $stmt = $this->pdo->prepare("\n                    SELECT id, numero, nombre\n                    FROM fichas\n                    WHERE colegio = ?\n                    ORDER BY nombre ASC\n                ");
                $stmt->execute([$colegioId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }


    // Obtener fichas compartidas aceptadas para un profesor
    public function obtenerCompartidasAceptadas($usuario_id) {
        try {
            $stmt = $this->pdo->prepare("\n                SELECT f.id, f.nombre, f.cupo_total, f.cupo_usado, f.estado_id AS estado, f.dias_semana,\n                       u_lider.nombres as lider_nombres, u_lider.apellidos as lider_apellidos,\n                       'compartida' as tipo_ficha\n                FROM fichas f\n                INNER JOIN fichas_compartidas fc ON f.id = fc.ficha_id\n                INNER JOIN usuarios u_lider ON fc.profesor_lider_id = u_lider.id\n                WHERE fc.profesor_compartido_id = ? AND fc.estado_id = 2\n                ORDER BY f.nombre ASC\n            ");
            $stmt->execute([$usuario_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S22') { throw $e; }
            // Fallback 1: join por columna 'ficha' con estado_id
            try {
                $stmt = $this->pdo->prepare("\n                    SELECT f.id, f.nombre, f.cupo_total, f.cupo_usado, f.estado_id AS estado, f.dias_semana,\n                           u_lider.nombres as lider_nombres, u_lider.apellidos as lider_apellidos,\n                           'compartida' as tipo_ficha\n                    FROM fichas f\n                    INNER JOIN fichas_compartidas fc ON f.id = fc.ficha\n                    INNER JOIN usuarios u_lider ON fc.profesor_lider_id = u_lider.id\n                    WHERE fc.profesor_compartido_id = ? AND fc.estado_id = 2\n                    ORDER BY f.nombre ASC\n                ");
                $stmt->execute([$usuario_id]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\PDOException $e2) {
                if ($e2->getCode() !== '42S22') { throw $e2; }
                // Fallback 2 (legacy): join por 'ficha' con estado textual
                $stmt = $this->pdo->prepare("\n                    SELECT f.id, f.nombre, f.cupo_total, f.cupo_usado, f.estado AS estado, f.dias_semana,\n                           u_lider.nombres as lider_nombres, u_lider.apellidos as lider_apellidos,\n                           'compartida' as tipo_ficha\n                    FROM fichas f\n                    INNER JOIN fichas_compartidas fc ON f.id = fc.ficha\n                    INNER JOIN usuarios u_lider ON fc.profesor_lider_id = u_lider.id\n                    WHERE fc.profesor_compartido_id = ? AND fc.estado = 'Aceptada'\n                    ORDER BY f.nombre ASC\n                ");
                $stmt->execute([$usuario_id]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
}
