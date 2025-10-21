<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

class CalendarioColaborativoController {
    private $pdo;

    public function __construct() {
        if (function_exists('start_secure_session')) start_secure_session();
        $this->pdo = Database::conectar();
        header('Content-Type: application/json; charset=utf-8');
    }

    // POST /?page=calcolab_crear
    public function crear() {
        try {
            $rol = (int)($_SESSION['usuario']['rol_id'] ?? 0);
            if (!in_array($rol, [1,4], true)) { http_response_code(403); echo json_encode(['error'=>'No autorizado']); return; }

            // Datos esperados
            $profesor_id   = (int)($_POST['profesor_id'] ?? 0);
            $ficha_id      = (int)($_POST['ficha_id'] ?? 0);
            $titulo        = trim((string)($_POST['titulo'] ?? '')); // opcional
            $fecha_inicio  = trim((string)($_POST['fecha_inicio'] ?? ''));
            $fecha_fin     = trim((string)($_POST['fecha_fin'] ?? ''));
            $aula          = trim((string)($_POST['aula'] ?? ''));
            $color         = trim((string)($_POST['color'] ?? '#3b82f6'));
            $estado        = trim((string)($_POST['estado'] ?? 'programado'));

            if ($profesor_id <= 0 || $ficha_id <= 0 || $fecha_inicio === '' || $fecha_fin === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Datos incompletos']);
                return;
            }

            // Normalizar fechas a Y-m-d H:i:s
            $fi = $this->toMysqlDateTime($fecha_inicio);
            $ff = $this->toMysqlDateTime($fecha_fin);
            if (!$fi || !$ff) { http_response_code(400); echo json_encode(['error'=>'Fechas inválidas']); return; }

            // Si no nos envían título, usar ficha o un genérico
            if ($titulo === '') { $titulo = 'Clase'; }

            // Alinear con calendario de instructores: guardar tambien creado_por
            $sql = "INSERT INTO horarios_fichas (profesor_id, ficha_id, titulo, fecha_inicio, fecha_fin, aula, color, estado, creado_por)
                    VALUES (?,?,?,?,?,?,?,?,?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$profesor_id, $ficha_id, $titulo, $fi, $ff, $aula, $color, $estado, $profesor_id]);
            $id = (int)$this->pdo->lastInsertId();

            echo json_encode(['ok'=>true, 'id'=>$id]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // GET /?page=calcolab_disponibilidad_ficha&ficha_id=ID
    public function disponibilidadFicha() {
        try {
            $rol = (int)($_SESSION['usuario']['rol_id'] ?? 0);
            if (!in_array($rol, [1,4], true)) { http_response_code(403); echo json_encode(['error'=>'No autorizado']); return; }
            $fid = (int)($_GET['ficha_id'] ?? 0);
            if ($fid <= 0) { echo json_encode(['dias'=>[], 'jornada_global'=>null, 'jornada_por_dia'=>[]]); return; }

            $stmt = $this->pdo->prepare("SELECT jornada, dias_semana FROM fichas WHERE id = ?");
            $stmt->execute([$fid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $jornada = isset($row['jornada']) ? strtolower(trim((string)$row['jornada'])) : '';
            $dias_semana_raw = $row['dias_semana'] ?? '';
            $dias = [];
            $jornada_por_dia = [];
            // Helper: normalizar textos a segmentos reconocidos
            $normSegs = function($val){
                if ($val === null) return [];
                $s = strtolower(trim((string)$val));
                if ($s === '') return [];
                // reemplazos comunes
                $s = str_replace(['á','é','í','ó','ú'], ['a','e','i','o','u'], $s);
                // separar por coma, punto y coma, slash o espacios con 'y'
                $s = str_replace([' y '], [','], $s);
                $parts = preg_split('/[;,\/\s]*,[;,\/\s]*/', $s);
                $out = [];
                foreach ($parts as $p) {
                    $p = trim($p);
                    if ($p === '') continue;
                    if ($p === 'ambas' || $p === 'manana y tarde' || $p === 'manana tarde') { $out[] = 'manana'; $out[] = 'tarde'; continue; }
                    if (in_array($p, ['manana','tarde','noche'], true)) { $out[] = $p; }
                }
                // dedup
                return array_values(array_unique($out));
            };
            if ($dias_semana_raw) {
                try {
                    $parsed = json_decode($dias_semana_raw, true);
                    if (is_array($parsed)) {
                        if (array_keys($parsed) !== range(0, count($parsed)-1)) {
                            // asociativo: { dia: jornada o lista }
                            $dias = array_keys($parsed);
                            foreach ($parsed as $k=>$v) {
                                $dia = strtolower(trim((string)$k));
                                $jornada_por_dia[$dia] = $normSegs($v);
                            }
                        } else {
                            // indexado: ["lunes",...]
                            $dias = array_map(function($d){ return strtolower(trim((string)$d)); }, $parsed);
                        }
                    }
                } catch(Exception $e) { /* ignore */ }
            }
            if (empty($dias)) { $dias = ['lunes','martes','miercoles','jueves','viernes']; }
            // Jornada global a lista de segmentos
            $jornada_global_list = $normSegs($jornada);
            echo json_encode([
                'dias' => $dias,
                'jornada_global' => $jornada_global_list,
                'jornada_por_dia' => $jornada_por_dia,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // GET /?page=calcolab_fichas_por_instructor&profesor_id=ID
    public function fichasPorInstructor() {
        try {
            $rol = (int)($_SESSION['usuario']['rol_id'] ?? 0);
            if (!in_array($rol, [1,4], true)) { http_response_code(403); echo json_encode(['error'=>'No autorizado']); return; }

            $pid = (int)($_GET['profesor_id'] ?? 0);
            if ($pid <= 0) { echo json_encode([]); return; }

            // Intento 1: union de tablas de relación conocidas
            try {
                $sql = "
                    SELECT DISTINCT f.id, f.numero AS codigo, f.nombre
                    FROM fichas f
                    INNER JOIN (
                        SELECT ficha_id FROM profesor_ficha WHERE profesor_id = ?
                        UNION
                        SELECT ficha_id FROM horarios_fichas WHERE profesor_id = ?
                    ) x ON x.ficha_id = f.id
                    ORDER BY f.numero, f.nombre
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$pid, $pid]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Exception $inner) {
                // Intento 2: solo profesor_ficha (por si falta horarios_fichas)
                try {
                    $sql2 = "
                        SELECT DISTINCT f.id, f.numero AS codigo, f.nombre
                        FROM fichas f
                        INNER JOIN profesor_ficha pf ON pf.ficha_id = f.id AND pf.profesor_id = ?
                        ORDER BY f.numero, f.nombre
                    ";
                    $stmt2 = $this->pdo->prepare($sql2);
                    $stmt2->execute([$pid]);
                    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (Exception $inner2) {
                    $rows = [];
                }
            }
            echo json_encode($rows);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // GET /?page=calcolab_instructores_por_ficha&ficha_id=ID
    public function instructoresPorFicha() {
        try {
            $rol = (int)($_SESSION['usuario']['rol_id'] ?? 0);
            if (!in_array($rol, [1,4], true)) { http_response_code(403); echo json_encode(['error'=>'No autorizado']); return; }

            $fid = (int)($_GET['ficha_id'] ?? 0);
            if ($fid <= 0) { echo json_encode([]); return; }

            // Intento 1: union de tablas de relación conocidas
            try {
                $sql = "
                    SELECT DISTINCT p.id, TRIM(CONCAT(u.nombres,' ',u.apellidos)) AS nombre
                    FROM profesores p
                    INNER JOIN usuarios u ON u.id = p.usuario_id
                    INNER JOIN (
                        SELECT profesor_id FROM profesor_ficha WHERE ficha_id = ?
                        UNION
                        SELECT profesor_id FROM horarios_fichas WHERE ficha_id = ?
                    ) x ON x.profesor_id = p.id
                    ORDER BY u.apellidos, u.nombres
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$fid, $fid]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Exception $inner) {
                // Intento 2: solo profesor_ficha (por si falta horarios_fichas)
                try {
                    $sql2 = "
                        SELECT DISTINCT p.id, TRIM(CONCAT(u.nombres,' ',u.apellidos)) AS nombre
                        FROM profesores p
                        INNER JOIN usuarios u ON u.id = p.usuario_id
                        INNER JOIN profesor_ficha pf ON pf.profesor_id = p.id AND pf.ficha_id = ?
                        ORDER BY u.apellidos, u.nombres
                    ";
                    $stmt2 = $this->pdo->prepare($sql2);
                    $stmt2->execute([$fid]);
                    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (Exception $inner2) {
                    $rows = [];
                }
            }
            echo json_encode($rows);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    // GET /?page=calcolab_instructores
    public function instructores() {
        try {
            // Solo Admin (1) y Asistente (4)
            $rol = (int)($_SESSION['usuario']['rol_id'] ?? 0);
            if (!in_array($rol, [1,4], true)) { http_response_code(403); echo json_encode(['error'=>'No autorizado']); return; }

            $sql = "
                SELECT 
                    p.id AS id,
                    TRIM(CONCAT(u.nombres,' ',u.apellidos)) AS nombre,
                    COALESCE(p.tip_contrato, '') AS tip_contrato
                FROM profesores p
                INNER JOIN usuarios u ON u.id = p.usuario_id
                WHERE 1=1
                ORDER BY u.apellidos, u.nombres
            ";
            $stmt = $this->pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Añadir iniciales y color base (simple hash por id)
            $out = array_map(function($r){
                $nombre = trim($r['nombre'] ?? '');
                $parts = preg_split('/\s+/', $nombre);
                $ini = '';
                foreach ($parts as $i => $p) { if ($i > 1) break; $ini .= mb_strtoupper(mb_substr($p,0,1)); }
                $h = (int)$r['id'] % 360; // tono HSL simple
                $color = sprintf('#%02x%02x%02x', ...$this->hslToRgb($h/360, 0.55, 0.55));
                return [
                    'id' => (int)$r['id'],
                    'nombre' => $nombre,
                    'iniciales' => $ini ?: 'IN',
                    'color' => $color,
                    'tip_contrato' => $r['tip_contrato'] ?? ''
                ];
            }, $rows);

            echo json_encode($out);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // GET /?page=calcolab_eventos&start=...&end=...&instructores=1,2
    public function eventos() {
        try {
            $rol = (int)($_SESSION['usuario']['rol_id'] ?? 0);
            if (!in_array($rol, [1,4], true)) { http_response_code(403); echo json_encode(['error'=>'No autorizado']); return; }

            $start = $_GET['start'] ?? null; // ISO
            $end   = $_GET['end'] ?? null;   // ISO
            $idsCsv = $_GET['instructores'] ?? '';
            $ids = array_filter(array_map('intval', array_filter(array_map('trim', explode(',', (string)$idsCsv)))));
            $estado = strtolower(trim((string)($_GET['estado'] ?? '')));
            $fichaQ = trim((string)($_GET['ficha'] ?? ''));

            // Normalizar estados en DB según la hora actual (excluye 'suspendido')
            try {
                // finalizado: NOW() > fin
                $this->pdo->exec("UPDATE horarios_fichas SET estado='finalizado' WHERE COALESCE(LOWER(estado),'') <> 'suspendido' AND NOW() > fecha_fin AND estado <> 'finalizado'");
                // en_curso: NOW() entre inicio y fin
                $this->pdo->exec("UPDATE horarios_fichas SET estado='en_curso' WHERE COALESCE(LOWER(estado),'') <> 'suspendido' AND NOW() >= fecha_inicio AND NOW() <= fecha_fin AND estado <> 'en_curso'");
                // programado: NOW() < inicio
                $this->pdo->exec("UPDATE horarios_fichas SET estado='programado' WHERE COALESCE(LOWER(estado),'') <> 'suspendido' AND NOW() < fecha_inicio AND estado <> 'programado'");
            } catch (Exception $e) { /* sin bloquear */ }

            $params = [];
            $sql = "
                SELECT 
                    hf.id,
                    hf.titulo,
                    hf.fecha_inicio,
                    hf.fecha_fin,
                    hf.aula,
                    COALESCE(hf.color, '#3b82f6') AS color,
                    COALESCE(hf.estado, 'programado') AS estado,
                    hf.ficha_id,
                    f.numero AS ficha_numero,
                    f.nombre AS ficha_nombre,
                    p.id AS profesor_id,
                    TRIM(CONCAT(u.nombres,' ',u.apellidos)) AS profesor_nombre,
                    c.id AS colegio_id,
                    c.nombre AS colegio_nombre
                FROM horarios_fichas hf
                INNER JOIN fichas f ON f.id = hf.ficha_id
                INNER JOIN profesores p ON p.id = hf.profesor_id
                INNER JOIN usuarios u ON u.id = p.usuario_id
                LEFT JOIN colegios c ON c.id = p.colegio_id
                WHERE 1=1
            ";

            if ($start && $end) {
                // Normalizar a Y-m-d H:i:s
                $ds = $this->toMysqlDateTime($start);
                $de = $this->toMysqlDateTime($end);
                // Incluir eventos que SE SOLAPAN con el rango [start, end)
                // (inicio < end) AND (fin > start)
                if ($ds && $de) { $sql .= " AND hf.fecha_inicio < ? AND hf.fecha_fin > ?"; $params[] = $de; $params[] = $ds; }
            }
            if (!empty($ids)) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $sql .= " AND hf.profesor_id IN ($in)";
                $params = array_merge($params, $ids);
            }
            // Filtro por estado visual
            if ($estado !== '') {
                // Normalizar columna estado a minúsculas para comparar suspendido
                $sqlEstadoBase = "LOWER(COALESCE(hf.estado, ''))";
                if ($estado === 'suspendido') {
                    $sql .= " AND $sqlEstadoBase = 'suspendido'";
                } elseif ($estado === 'en_curso') {
                    $sql .= " AND $sqlEstadoBase <> 'suspendido' AND NOW() >= hf.fecha_inicio AND NOW() <= hf.fecha_fin";
                } elseif ($estado === 'finalizado') {
                    $sql .= " AND $sqlEstadoBase <> 'suspendido' AND NOW() > hf.fecha_fin";
                } elseif ($estado === 'programado') {
                    $sql .= " AND $sqlEstadoBase <> 'suspendido' AND NOW() < hf.fecha_inicio";
                }
            }
            // Filtro por ficha (por número o nombre)
            if ($fichaQ !== '') {
                $sql .= " AND (f.numero LIKE ? OR f.nombre LIKE ?)";
                $like = "%" . $fichaQ . "%";
                $params[] = $like;
                $params[] = $like;
            }
            $sql .= " ORDER BY hf.fecha_inicio";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $eventos = array_map(function($r){
                $start = $this->toIso($r['fecha_inicio']);
                $end   = $this->toIso($r['fecha_fin']);
                return [
                    'id' => (int)$r['id'],
                    'title' => ($r['titulo'] ?? '') . ' - ' . ($r['profesor_nombre'] ?? ''),
                    'start' => $start,
                    'end' => $end,
                    'backgroundColor' => $r['color'],
                    'borderColor' => $r['color'],
                    'className' => 'evento-' . ($r['estado'] ?? 'programado'),
                    'extendedProps' => [
                        'ficha_id' => (int)$r['ficha_id'],
                        'ficha_numero' => $r['ficha_numero'] ?? '',
                        'ficha_nombre' => $r['ficha_nombre'] ?? '',
                        'profesor_id' => (int)$r['profesor_id'],
                        'profesor_nombre' => $r['profesor_nombre'] ?? '',
                        'colegio_id' => $r['colegio_id'] !== null ? (int)$r['colegio_id'] : null,
                        'colegio_nombre' => $r['colegio_nombre'] ?? '',
                        'aula' => $r['aula'] ?? '',
                        'estado' => $r['estado'] ?? 'programado',
                        'tipo' => 'colaborativo'
                    ]
                ];
            }, $rows);

            echo json_encode($eventos);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function toMysqlDateTime($s) {
        try { $dt = new DateTime($s); return $dt->format('Y-m-d H:i:s'); } catch(Exception $e) { return null; }
    }
    private function toIso($s) {
        try { $dt = new DateTime($s); return $dt->format('Y-m-d\TH:i:s'); } catch(Exception $e) { return null; }
    }

    // Helper simple HSL->RGB (0..1)
    private function hslToRgb($h, $s, $l) {
        $r=$l; $g=$l; $b=$l;
        if ($s != 0) {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = $this->hue2rgb($p, $q, $h + 1/3);
            $g = $this->hue2rgb($p, $q, $h);
            $b = $this->hue2rgb($p, $q, $h - 1/3);
        }
        return [round($r*255), round($g*255), round($b*255)];
    }
    private function hue2rgb($p, $q, $t) {
        if ($t < 0) $t += 1; if ($t > 1) $t -= 1;
        if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1/2) return $q;
        if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
        return $p;
    }
}
