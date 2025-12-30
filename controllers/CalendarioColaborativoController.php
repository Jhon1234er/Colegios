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

    // GET /?page=calcolab_export&start=YYYY-MM-DD&end=YYYY-MM-DD
    public function exportarExcel() {
        try {
            if (!isset($_SESSION['usuario'])) { http_response_code(401); echo 'No autorizado'; return; }
            $rol = (int)($_SESSION['usuario']['rol_id'] ?? 0);
            if (!in_array($rol, [1,4], true)) { http_response_code(403); echo 'No autorizado'; return; }
            if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
                http_response_code(500);
                echo 'PhpSpreadsheet no está instalado. Instala con: composer require phpoffice/phpspreadsheet';
                return;
            }

            $start = $_GET['start'] ?? null; // ISO
            $end   = $_GET['end'] ?? null;   // ISO
            $estado = strtolower(trim((string)($_GET['estado'] ?? '')));
            $idsCsv = $_GET['instructores'] ?? '';
            $ids = array_filter(array_map('intval', array_filter(array_map('trim', explode(',', (string)$idsCsv)))));
            $fichaQ = trim((string)($_GET['ficha'] ?? ''));

            // Normalizar fechas
            $toMysql = function($s){ if (!$s) return null; try { $d=new DateTime($s); return $d->format('Y-m-d H:i:s'); } catch(Exception $e){ return null; } };
            $startMy = $toMysql($start);
            $endMy   = $toMysql($end);
            // Forzar exportar solo el mes visible: usar $start + 15 días como referencia (cae en el mes mostrado por FullCalendar)
            if ($startMy) {
                $dref = new DateTime($startMy);
                try { $dref->modify('+15 days'); } catch (Exception $e) { /* ignore */ }
                $first = (clone $dref)->modify('first day of this month')->setTime(0,0,0);
                $last  = (clone $dref)->modify('last day of this month')->setTime(23,59,59);
                $startMy = $first->format('Y-m-d H:i:s');
                $endMy   = $last->format('Y-m-d H:i:s');
                // También reescribir $start y $end para mostrarlos en el hero si se usan
                $start = $first->format('Y-m-d H:i:s');
                $end   = $last->format('Y-m-d H:i:s');
            }

            $params = [];
            $sql = "
                SELECT 
                  hf.id,
                  COALESCE(hf.titulo,'Clase') AS titulo,
                  hf.fecha_inicio,
                  hf.fecha_fin,
                  hf.aula,
                  COALESCE(hf.estado,'programado') AS estado,
                  hf.ficha_id,
                  COALESCE(f.numero, f.id) AS ficha_numero,
                  f.nombre AS ficha_nombre,
                  COALESCE(
                    c.nombre,
                    (SELECT c2.nombre FROM colegios c2 INNER JOIN aprendices a2 ON a2.colegio_id=c2.id WHERE a2.ficha_id=f.id LIMIT 1)
                  ) AS colegio_nombre,
                  TRIM(CONCAT(u.nombres,' ',u.apellidos)) AS profesor_nombre
                FROM horarios_fichas hf
                INNER JOIN fichas f ON f.id = hf.ficha_id
                INNER JOIN facilitadores p ON p.id = hf.facilitador_id
                INNER JOIN usuarios u ON u.id = p.usuario
                LEFT JOIN colegios c ON c.id = f.colegio_id
                WHERE 1=1
            ";
            if ($startMy && $endMy) { $sql .= " AND hf.fecha_inicio < ? AND hf.fecha_fin > ?"; $params[] = $endMy; $params[] = $startMy; }
            if (!empty($ids)) { $sql .= " AND hf.facilitador_id IN (".implode(',', array_fill(0, count($ids), '?')).")"; $params = array_merge($params, $ids); }
            if ($estado !== '') { $sql .= " AND LOWER(hf.estado) = ?"; $params[] = $estado; }
            if ($fichaQ !== '') { $sql .= " AND (f.id = ? OR COALESCE(f.numero,f.id) = ?)"; $params[] = $fichaQ; $params[] = $fichaQ; }
            $sql .= " ORDER BY hf.fecha_inicio";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Construir XLSX
            $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            // Fondo blanco por defecto para todas las celdas no usadas
            $ss->getDefaultStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFFFFFFF');
            $sheet = $ss->getActiveSheet();
            $sheet->setTitle('Clases');

            // ===== Layout: A,B,C vacías. Hero borde superior en D5 y ancho hasta N5 =====
            $startColIndex = 4; // D
            $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColIndex); // 'D'
            $heroRightIndex = 14; // N
            $heroEndCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($heroRightIndex); // 'N'
            // Tres filas del hero
            $sheet->mergeCells($startCol.'5:'.$heroEndCol.'5');
            $sheet->mergeCells($startCol.'6:'.$heroEndCol.'6');
            $sheet->mergeCells($startCol.'7:'.$heroEndCol.'7');
            $sheet->setCellValue($startCol.'5', 'SENA');
            $sheet->setCellValue($startCol.'6', 'SERVICIO NACIONAL DE APRENDIZAJE - SENA');
            $sheet->setCellValue($startCol.'7', 'CONTROL DE ASISTENCIA - SISTEM SCHOLL');
            $sheet->getRowDimension(5)->setRowHeight(40);
            $sheet->getRowDimension(6)->setRowHeight(30);
            $sheet->getRowDimension(7)->setRowHeight(30);
            $sheet->getStyle($startCol.'5:'.$heroEndCol.'7')->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
            $sheet->getStyle($startCol.'5:'.$heroEndCol.'7')->getAlignment()
                  ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                  ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                  ->setWrapText(true);
            // Fuentes hero en Calibri con tamaños específicos
            $sheet->getStyle($startCol.'5')->getFont()->setBold(true)->setName('Calibri')->setSize(36);
            $sheet->getStyle($startCol.'6')->getFont()->setName('Calibri')->setSize(12);
            $sheet->getStyle($startCol.'7')->getFont()->setName('Calibri')->setSize(12);

            // FECHA DE REPORTE en I9:J9 y fechas en K9:M9
            $labelLeftCol = 'I'; $labelRightCol = 'J';
            $dateLeftCol  = 'K'; $dateRightCol  = 'M';
            $sheet->mergeCells($labelLeftCol.'9:'.$labelRightCol.'9');
            $sheet->mergeCells($dateLeftCol.'9:'.$dateRightCol.'9');
            $sheet->setCellValue($labelLeftCol.'9', 'FECHA DE REPORTE:');
            $sheet->setCellValue($dateLeftCol.'9', ($start ? (new DateTime($start))->format('d/m/Y') : '') . ' al ' . ($end ? (new DateTime($end))->format('d/m/Y') : ''));
            $sheet->getStyle($labelLeftCol.'9:'.$labelRightCol.'9')->getFont()->setBold(true);
            $sheet->getStyle($labelLeftCol.'9:'.$dateRightCol.'9')->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
            $sheet->getStyle($labelLeftCol.'9:'.$dateRightCol.'9')->getAlignment()
                  ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                  ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                  ->setWrapText(true);
            $sheet->getStyle($labelLeftCol.'9:'.$dateRightCol.'9')->getFont()->setName('Calibri')->setSize(12);

            // Tabla desde D11
            $endColIndex   = $startColIndex + 8 - 1; // 8 columnas (N°, COLEGIO, TITULO, INSTRUCTOR, FICHA, AULA, HORARIO, ESTADO) -> D..K
            $endCol   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($endColIndex);   // 'K'
            $tableHeaderRow = 11; // D11
            $headers = ['N°','COLEGIO','TITULO','INSTRUCTOR','FICHA','AULA','HORARIO','ESTADO'];
            foreach ($headers as $i=>$h) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColIndex + $i);
                $sheet->setCellValue($col.$tableHeaderRow, mb_strtoupper($h));
            }
            // Estilo encabezado: color 0B3A53, letra blanca, negrita, centrado
            $headerRange = $startCol.$tableHeaderRow.':'.$endCol.$tableHeaderRow;
            $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                  ->getStartColor()->setARGB('FF0B3A53');
            // Configurar fuente y color por separado (evita encadenar setName después de getColor)
            $sheet->getStyle($headerRange)->getFont()->setBold(true)->setName('Calibri')->setSize(12);
            $sheet->getStyle($headerRange)->getFont()->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($headerRange)->getAlignment()
                  ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                  ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

            $r = $tableHeaderRow + 1; $n=1; foreach ($rows as $row) {
                $inicio = (new DateTime($row['fecha_inicio']))->format('Y-m-d H:i');
                $fin    = (new DateTime($row['fecha_fin']))->format('Y-m-d H:i');
                $hor    = $inicio.' - '.$fin;
                $sheet->setCellValue('D'.$r, $n);
                $sheet->setCellValue('E'.$r, (string)($row['colegio_nombre'] ?? ''));
                $sheet->setCellValue('F'.$r, 'Clase');
                $sheet->setCellValue('G'.$r, (string)($row['profesor_nombre'] ?? ''));
                $sheet->setCellValue('H'.$r, (string)($row['ficha_numero'] ?? ''));
                $sheet->setCellValue('I'.$r, (string)($row['aula'] ?? ''));
                $sheet->setCellValue('J'.$r, $hor);
                $sheet->setCellValue('K'.$r, (string)($row['estado'] ?? 'programado'));

                // Centrar fila completa (horizontal y vertical) y aplicar fuente
                $sheet->getStyle('D'.$r.':K'.$r)->getAlignment()
                      ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                      ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                $sheet->getStyle('D'.$r.':K'.$r)->getFont()->setName('Calibri')->setSize(12)->getColor()->setARGB('FF000000');
                // Fondo blanco en filas con información
                $sheet->getStyle('D'.$r.':K'.$r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                      ->getStartColor()->setARGB('FFFFFFFF');
                // Color en columna Estado según valor
                $est = strtolower((string)($row['estado'] ?? 'programado'));
                $stColor = 'FF0B3A53'; // default dark header color
                if ($est === 'en_curso') $stColor = 'FF22C55E';
                elseif ($est === 'suspendido' || $est === 'cancelado' || $est === 'cancelada') $stColor = 'FFEF4444';
                elseif ($est === 'finalizado') $stColor = 'FF111827';
                elseif ($est === 'programado') $stColor = 'FF3B82F6';
                $sheet->getStyle('K'.$r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                      ->getStartColor()->setARGB($stColor);
                $sheet->getStyle('K'.$r)->getFont()->getColor()->setARGB('FFFFFFFF');

                $r++; $n++;
            }
            // Autosize columnas D..K
            for ($ci=$startColIndex; $ci<=$endColIndex; $ci++) {
                $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci))->setAutoSize(true);
            }
            // Centrar y aplicar fuente a toda la tabla
            $sheet->getStyle($startCol.$tableHeaderRow.':'.$endCol.($r-1))->getAlignment()
                  ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                  ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $sheet->getStyle($startCol.$tableHeaderRow.':'.$endCol.($r-1))->getFont()->setName('Calibri')->setSize(12);
            // Bordes finos a la tabla
            $tableRange  = $startCol.$tableHeaderRow.':'.$endCol.($r-1);
            $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                  ->getColor()->setARGB('FF000000');
            // Congelar encabezado (fila bajo encabezado)
            $sheet->freezePane($startCol.($tableHeaderRow+1)); // D12
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="calendario_colaborativo.xlsx"');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
            $writer->save('php://output');
        } catch (Throwable $e) {
            http_response_code(500);
            echo 'Error exportando: '.$e->getMessage();
        }
    }

    // POST /?page=calcolab_crear
    public function crear() {
        try {
            $rol = (int)($_SESSION['usuario']['rol_id'] ?? 0);
            if (!in_array($rol, [1,2,4], true)) { http_response_code(403); echo json_encode(['error'=>'No autorizado']); return; }

            // Datos esperados
            // Aceptar facilitador_id (preferido) o profesor_id por compatibilidad
            $profesor_id   = (int)($_POST['facilitador_id'] ?? ($_POST['profesor_id'] ?? 0));
            $ficha_id      = (int)($_POST['ficha_id'] ?? 0);
            $titulo        = trim((string)($_POST['titulo'] ?? '')); // opcional
            $fecha_inicio  = trim((string)($_POST['fecha_inicio'] ?? ''));
            $fecha_fin     = trim((string)($_POST['fecha_fin'] ?? ''));
            $aula          = trim((string)($_POST['aula'] ?? ''));
            $color         = trim((string)($_POST['color'] ?? '#3b82f6'));
            $estado        = strtolower(trim((string)($_POST['estado'] ?? 'programado')));
            $estado_id_in  = isset($_POST['estado_id']) ? (int)$_POST['estado_id'] : 0;

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

            // Derivar estado_id si no viene
            $mapEstado = ['programado'=>1, 'en_curso'=>2, 'suspendido'=>3, 'finalizado'=>4];
            $estado_id = $estado_id_in > 0 ? $estado_id_in : ($mapEstado[$estado] ?? 1);

            // Inserción específica de tu esquema: SOLO facilitador_id (sin profesor_id)
            try {
                $sql = "INSERT INTO horarios_fichas (facilitador_id, ficha_id, titulo, fecha_inicio, fecha_fin, aula, color, estado, estado_id, creado_por)
                        VALUES (?,?,?,?,?,?,?,?,?,?)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$profesor_id, $ficha_id, $titulo, $fi, $ff, $aula, $color, $estado, $estado_id, $profesor_id]);
            } catch (\PDOException $e) {
                // Si la columna creado_por no existe en tu tabla, usar inserción sin ella
                if ($e->getCode() === '42S22' || stripos($e->getMessage(), 'Unknown column') !== false) {
                    // Intento sin creado_por
                    try {
                        $sql = "INSERT INTO horarios_fichas (facilitador_id, ficha_id, titulo, fecha_inicio, fecha_fin, aula, color, estado, estado_id)
                                VALUES (?,?,?,?,?,?,?,?,?)";
                        $stmt = $this->pdo->prepare($sql);
                        $stmt->execute([$profesor_id, $ficha_id, $titulo, $fi, $ff, $aula, $color, $estado, $estado_id]);
                    } catch (\PDOException $e2) {
                        // Intento final sin estado_id (si no existiera la columna)
                        if ($e2->getCode() === '42S22' || stripos($e2->getMessage(), 'Unknown column') !== false) {
                            $sql = "INSERT INTO horarios_fichas (facilitador_id, ficha_id, titulo, fecha_inicio, fecha_fin, aula, color, estado)
                                    VALUES (?,?,?,?,?,?,?,?)";
                            $stmt = $this->pdo->prepare($sql);
                            $stmt->execute([$profesor_id, $ficha_id, $titulo, $fi, $ff, $aula, $color, $estado]);
                        } else { throw $e2; }
                    }
                } else {
                    throw $e;
                }
            }
            $id = (int)$this->pdo->lastInsertId();
            $usuarioDestinoId = 0;
            try {
                $stU = $this->pdo->prepare("SELECT usuario_id FROM facilitadores WHERE id = ?");
                $stU->execute([$profesor_id]);
                $usuarioDestinoId = (int)($stU->fetchColumn() ?: 0);
            } catch (\PDOException $eU1) {
                if ($eU1->getCode() !== '42S22') { throw $eU1; }
                $stU = $this->pdo->prepare("SELECT usuario FROM facilitadores WHERE id = ?");
                $stU->execute([$profesor_id]);
                $usuarioDestinoId = (int)($stU->fetchColumn() ?: 0);
            }
            if ($usuarioDestinoId > 0) {
                $nombreFicha = '';
                try {
                    $stF = $this->pdo->prepare("SELECT nombre FROM fichas WHERE id = ?");
                    $stF->execute([$ficha_id]);
                    $nombreFicha = (string)($stF->fetchColumn() ?: '');
                } catch (\Throwable $_) {}
                $tituloNoti = 'Nuevo bloque programado';
                // Formato compacto: si es el mismo día => "d-m-Y de hh:mm a hh:mm"
                // en caso contrario => "del d-m-Y HH:mm al d-m-Y HH:mm"
                try {
                    $dtI = new \DateTime($fi);
                    $dtF = new \DateTime($ff);
                    if ($dtI && $dtF && $dtI->format('Y-m-d') === $dtF->format('Y-m-d')) {
                        $rango = $dtI->format('d-m-Y') . ' de ' . $dtI->format('h:i') . ' a ' . $dtF->format('h:i');
                    } else {
                        $rango = 'del ' . $dtI->format('d-m-Y H:i') . ' al ' . $dtF->format('d-m-Y H:i');
                    }
                } catch (\Throwable $t) {
                    $rango = 'de ' . $fi . ' a ' . $ff;
                }
                $msg = 'Se programó un nuevo bloque para la ficha ' . $nombreFicha . ' ' . $rango . ' en ' . $aula . '.';
                try {
                    try {
                        $stN = $this->pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, leido, creado_en) VALUES (?, ?, ?, 0, NOW())");
                        $stN->execute([$usuarioDestinoId, $tituloNoti, $msg]);
                    } catch (\PDOException $eN1) {
                        if ($eN1->getCode() !== '42S22') { throw $eN1; }
                        try {
                            $stN = $this->pdo->prepare("INSERT INTO notificaciones (usuario, titulo, mensaje, leido, creado_en) VALUES (?, ?, ?, 0, NOW())");
                            $stN->execute([$usuarioDestinoId, $tituloNoti, $msg]);
                        } catch (\PDOException $eN2) {
                            if ($eN2->getCode() !== '42S22') { throw $eN2; }
                            try {
                                $stN = $this->pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, estado, creado_en) VALUES (?, ?, ?, 'no_leida', NOW())");
                                $stN->execute([$usuarioDestinoId, $tituloNoti, $msg]);
                            } catch (\PDOException $eN3) {
                                if ($eN3->getCode() !== '42S22') { throw $eN3; }
                                $stN = $this->pdo->prepare("INSERT INTO notificaciones (usuario, titulo, mensaje, estado, creado_en) VALUES (?, ?, ?, 'no_leida', NOW())");
                                $stN->execute([$usuarioDestinoId, $tituloNoti, $msg]);
                            }
                        }
                    }
                } catch (\Throwable $ign) {}
            }

            echo json_encode(['ok'=>true, 'id'=>$id]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // GET /?page=calcolab_disponibilidad_ficha&ficha_id=ID
    public function disponibilidadFicha() {
        try {
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
                // Soportar arreglo de segmentos
                if (is_array($val)) {
                    $out = [];
                    foreach ($val as $vv) {
                        $s = strtolower(trim((string)$vv));
                        if ($s === '') continue;
                        $s = str_replace(['á','é','í','ó','ú'], ['a','e','i','o','u'], $s);
                        if ($s === 'ambas' || $s === 'manana y tarde' || $s === 'manana tarde' || $s === 'mixta' || $s === 'diurna') { $out[]='manana'; $out[]='tarde'; continue; }
                        if ($s === 'nocturna' || $s === 'noche') { $out[]='noche'; continue; }
                        if (in_array($s, ['manana','tarde','noche'], true)) { $out[] = $s; }
                    }
                    return array_values(array_unique($out));
                }
                $s = strtolower(trim((string)$val));
                if ($s === '') return [];
                // reemplazos comunes
                $s = str_replace(['á','é','í','ó','ú'], ['a','e','i','o','u'], $s);
                // sinónimos a normalizar a segmentos soportados
                // 'nocturna' -> 'noche'; 'diurna'/'mixta'/'todo el dia'/'jornada completa' -> manana+tarde
                $synAllDay = ['mixta','diurna','todo el dia','jornada completa','completa','todo'];
                foreach ($synAllDay as $kw) { if (strpos($s, $kw) !== false) { return ['manana','tarde']; } }
                if (strpos($s, 'nocturna') !== false) { return ['noche']; }
                // separar por coma, punto y coma, slash o espacios con 'y'
                $s = str_replace([' y '], [','], $s);
                $parts = preg_split('/[;,\/\s]*,[;,\/\s]*/', $s);
                $out = [];
                foreach ($parts as $p) {
                    $p = trim($p);
                    if ($p === '') continue;
                    if ($p === 'ambas' || $p === 'manana y tarde' || $p === 'manana tarde') { $out[] = 'manana'; $out[] = 'tarde'; continue; }
                    if ($p === 'nocturna') { $out[] = 'noche'; continue; }
                    if (in_array($p, ['manana','tarde','noche'], true)) { $out[] = $p; }
                }
                // dedup
                return array_values(array_unique($out));
            };
            if ($dias_semana_raw) {
                try {
                    $parsed = json_decode($dias_semana_raw, true);
                    // Fallback: algunas instalaciones guardaron con paréntesis en lugar de llaves
                    if (!is_array($parsed) || empty($parsed)) {
                        $fix = trim((string)$dias_semana_raw);
                        if ($fix !== '' && $fix[0] === '(') {
                            $fix = '{' . trim($fix, "() ") . '}';
                            $parsed = json_decode($fix, true);
                        }
                    }
                    if (is_array($parsed)) {
                        if (array_keys($parsed) !== range(0, count($parsed)-1)) {
                            // asociativo: { dia: jornada o lista }
                            $dias = [];
                            foreach ($parsed as $k=>$v) {
                                $dia = strtolower(trim((string)$k));
                                // Normalizar tildes en nombre del día para coincidir con claves JS
                                $dia = str_replace(['á','é','í','ó','ú'], ['a','e','i','o','u'], $dia);
                                $dias[] = $dia;
                                $jornada_por_dia[$dia] = $normSegs($v);
                            }
                            $dias = array_values(array_unique($dias));
                        } else {
                            // indexado: ["lunes",...]
                            $dias = array_map(function($d){
                                $x = strtolower(trim((string)$d));
                                return str_replace(['á','é','í','ó','ú'], ['a','e','i','o','u'], $x);
                            }, $parsed);
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
            $colegioId = (int)($_GET['colegio_id'] ?? ($_GET['colegio'] ?? 0));

            // Intento 1: unión de tablas de relación conocidas (facilitador_ficha + horarios_fichas)
            try {
                $base = "
                    SELECT DISTINCT f.id, COALESCE(f.numero, f.id) AS codigo, f.nombre
                    FROM fichas f
                    INNER JOIN (
                        SELECT ficha_id FROM facilitador_ficha WHERE facilitador_id = ?
                        UNION
                        SELECT ficha_id FROM horarios_fichas WHERE facilitador_id = ?
                    ) x ON x.ficha_id = f.id
                ";
                $params = [$pid, $pid];
                if ($colegioId > 0) {
                    try {
                        $sql = $base . " WHERE f.colegio_id = ? ORDER BY COALESCE(f.numero, f.id), f.nombre";
                        $stmt = $this->pdo->prepare($sql);
                        $stmt->execute(array_merge($params, [$colegioId]));
                    } catch (\PDOException $eCol) {
                        if ($eCol->getCode() !== '42S22') { throw $eCol; }
                        $sql = $base . " WHERE f.colegio = ? ORDER BY COALESCE(f.numero, f.id), f.nombre";
                        $stmt = $this->pdo->prepare($sql);
                        $stmt->execute(array_merge($params, [$colegioId]));
                    }
                } else {
                    $sql = $base . " ORDER BY COALESCE(f.numero, f.id), f.nombre";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute($params);
                }
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Exception $inner) {
                $rows = [];
            }

            // Intento 2: solo facilitador_ficha (por si falla la unión con horarios_fichas)
            if (empty($rows)) {
                try {
                    $base2 = "
                        SELECT DISTINCT f.id, COALESCE(f.numero, f.id) AS codigo, f.nombre
                        FROM fichas f
                        INNER JOIN facilitador_ficha pf ON pf.ficha_id = f.id AND pf.facilitador_id = ?
                    ";
                    $params2 = [$pid];
                    if ($colegioId > 0) {
                        try {
                            $sql2 = $base2 . " WHERE f.colegio_id = ? ORDER BY COALESCE(f.numero, f.id), f.nombre";
                            $stmt2 = $this->pdo->prepare($sql2);
                            $stmt2->execute(array_merge($params2, [$colegioId]));
                        } catch (\PDOException $eCol2) {
                            if ($eCol2->getCode() !== '42S22') { throw $eCol2; }
                            $sql2 = $base2 . " WHERE f.colegio = ? ORDER BY COALESCE(f.numero, f.id), f.nombre";
                            $stmt2 = $this->pdo->prepare($sql2);
                            $stmt2->execute(array_merge($params2, [$colegioId]));
                        }
                    } else {
                        $sql2 = $base2 . " ORDER BY COALESCE(f.numero, f.id), f.nombre";
                        $stmt2 = $this->pdo->prepare($sql2);
                        $stmt2->execute($params2);
                    }
                    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (Exception $inner2) {
                    $rows = [];
                }
            }

            // Intento 3: relación directa por columna fichas.facilitador_id
            if (empty($rows)) {
                try {
                    $base3 = "
                        SELECT DISTINCT f.id, COALESCE(f.numero, f.id) AS codigo, f.nombre
                        FROM fichas f
                        WHERE f.facilitador_id = ?
                    ";
                    $params3 = [$pid];
                    if ($colegioId > 0) {
                        try {
                            $sql3 = $base3 . " AND f.colegio_id = ? ORDER BY COALESCE(f.numero, f.id), f.nombre";
                            $stmt3 = $this->pdo->prepare($sql3);
                            $stmt3->execute(array_merge($params3, [$colegioId]));
                        } catch (\PDOException $eCol3) {
                            if ($eCol3->getCode() !== '42S22') { throw $eCol3; }
                            $sql3 = $base3 . " AND f.colegio = ? ORDER BY COALESCE(f.numero, f.id), f.nombre";
                            $stmt3 = $this->pdo->prepare($sql3);
                            $stmt3->execute(array_merge($params3, [$colegioId]));
                        }
                    } else {
                        $sql3 = $base3 . " ORDER BY COALESCE(f.numero, f.id), f.nombre";
                        $stmt3 = $this->pdo->prepare($sql3);
                        $stmt3->execute($params3);
                    }
                    $rows = $stmt3->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (Exception $inner3) {
                    // Si tampoco hay fichas por facilitador_id, dejamos rows vacío
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
                    FROM facilitadores p
                    INNER JOIN usuarios u ON u.id = p.usuario
                    INNER JOIN (
                        SELECT facilitador_id AS profesor_id FROM facilitador_ficha WHERE ficha_id = ?
                        UNION
                        SELECT facilitador_id AS profesor_id FROM horarios_fichas WHERE ficha_id = ?
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
                        FROM facilitadores p
                        INNER JOIN usuarios u ON u.id = p.usuario
                        INNER JOIN facilitador_ficha pf ON pf.facilitador_id = p.id AND pf.ficha_id = ?
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
            @ini_set('display_errors','0');
            if (function_exists('ob_start')) { @ob_start(); }
            // Solo Admin (1) y Asistente (4)
            $rol = (int)($_SESSION['usuario']['rol_id'] ?? 0);
            if (!in_array($rol, [1,4], true)) { http_response_code(403); echo json_encode(['error'=>'No autorizado']); return; }

            // Variante 1: usar EXISTS sobre las tres tablas (si alguna falta, capturamos y probamos variantes)
            $rows = [];
            try {
                $sql = "
                    SELECT 
                        p.id AS id,
                        TRIM(CONCAT(u.nombres,' ',u.apellidos)) AS nombre,
                        COALESCE(p.tipo_contrato, '') AS tip_contrato,
                        CASE WHEN (
                            EXISTS (SELECT 1 FROM facilitador_ficha pf WHERE pf.facilitador_id = p.id)
                            OR EXISTS (SELECT 1 FROM profesor_ficha pf2 WHERE pf2.profesor_id = p.id)
                            OR EXISTS (SELECT 1 FROM horarios_fichas hf WHERE hf.facilitador_id = p.id)
                        ) THEN 1 ELSE 0 END AS has_rel
                    FROM facilitadores p
                    INNER JOIN usuarios u ON u.id = p.usuario
                    ORDER BY u.apellidos, u.nombres
                ";
                $stmt = $this->pdo->query($sql);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\PDOException $e1) {
                if ($e1->getCode() !== '42S02') { throw $e1; }
                // Variante 2: sin profesor_ficha
                try {
                    $sql = "
                        SELECT 
                            p.id AS id,
                            TRIM(CONCAT(u.nombres,' ',u.apellidos)) AS nombre,
                            COALESCE(p.tipo_contrato, '') AS tip_contrato,
                            CASE WHEN (
                                EXISTS (SELECT 1 FROM facilitador_ficha pf WHERE pf.facilitador_id = p.id)
                                OR EXISTS (SELECT 1 FROM horarios_fichas hf WHERE hf.facilitador_id = p.id)
                            ) THEN 1 ELSE 0 END AS has_rel
                        FROM facilitadores p
                        INNER JOIN usuarios u ON u.id = p.usuario
                        ORDER BY u.apellidos, u.nombres
                    ";
                    $stmt = $this->pdo->query($sql);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (\PDOException $e2) {
                    if ($e2->getCode() !== '42S02') { throw $e2; }
                    // Variante 3: solo horarios_fichas
                    try {
                        $sql = "
                            SELECT 
                                p.id AS id,
                                TRIM(CONCAT(u.nombres,' ',u.apellidos)) AS nombre,
                                COALESCE(p.tipo_contrato, '') AS tip_contrato,
                                CASE WHEN EXISTS (SELECT 1 FROM horarios_fichas hf WHERE hf.facilitador_id = p.id)
                                THEN 1 ELSE 0 END AS has_rel
                            FROM facilitadores p
                            INNER JOIN usuarios u ON u.id = p.usuario
                            ORDER BY u.apellidos, u.nombres
                        ";
                        $stmt = $this->pdo->query($sql);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (\PDOException $e3) {
                        if ($e3->getCode() !== '42S02') { throw $e3; }
                        // Último recurso: ningún origen disponible -> has_rel = 0
                        $sql = "
                            SELECT 
                                p.id AS id,
                                TRIM(CONCAT(u.nombres,' ',u.apellidos)) AS nombre,
                                COALESCE(p.tip_contrato, '') AS tip_contrato,
                                0 AS has_rel
                            FROM facilitadores p
                            INNER JOIN usuarios u ON u.id = p.usuario_id
                            ORDER BY u.apellidos, u.nombres
                        ";
                        $stmt = $this->pdo->query($sql);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    }
                }
            }

            // Marcar relación por fichas compartidas aceptadas (estado='Aceptada' o estado_id=2)
            try {
                $fcIds = [];
                // Variante A: profesor_compartido_id (usuarios.id)
                try {
                    $qA = $this->pdo->query("SELECT DISTINCT p.id FROM fichas_compartidas fc JOIN facilitadores p ON p.usuario = fc.profesor_compartido_id WHERE (fc.estado = 'Aceptada' OR fc.estado_id = 2)");
                    $fcIds = $qA->fetchAll(PDO::FETCH_COLUMN) ?: [];
                } catch (\PDOException $eA) {
                    if ($eA->getCode() !== '42S22' && $eA->getCode() !== '42S02') { throw $eA; }
                }
                // Variante B: profesor_compartido (usuarios.id)
                if (empty($fcIds)) {
                    try {
                        $qB = $this->pdo->query("SELECT DISTINCT p.id FROM fichas_compartidas fc JOIN facilitadores p ON p.usuario = fc.profesor_compartido WHERE (fc.estado = 'Aceptada' OR fc.estado_id = 2)");
                        $fcIds = $qB->fetchAll(PDO::FETCH_COLUMN) ?: [];
                    } catch (\PDOException $eB) {
                        if ($eB->getCode() !== '42S22' && $eB->getCode() !== '42S02') { throw $eB; }
                        // Variante B2: join por p.usuario_id
                        try {
                            $qB2 = $this->pdo->query("SELECT DISTINCT p.id FROM fichas_compartidas fc JOIN facilitadores p ON p.usuario_id = fc.profesor_compartido WHERE (fc.estado = 'Aceptada' OR fc.estado_id = 2)");
                            $fcIds = $qB2->fetchAll(PDO::FETCH_COLUMN) ?: [];
                        } catch (\PDOException $eB2) {
                            if ($eB2->getCode() !== '42S22' && $eB2->getCode() !== '42S02') { throw $eB2; }
                        }
                    }
                }
                // Variante C: facilitador_compartido (facilitadores.id)
                if (empty($fcIds)) {
                    try {
                        $qC = $this->pdo->query("SELECT DISTINCT fc.facilitador_compartido AS id FROM fichas_compartidas fc WHERE (fc.estado = 'Aceptada' OR fc.estado_id = 2)");
                        $fcIds = $qC->fetchAll(PDO::FETCH_COLUMN) ?: [];
                    } catch (\PDOException $eC) {
                        if ($eC->getCode() !== '42S22' && $eC->getCode() !== '42S02') { throw $eC; }
                    }
                }
                if (!empty($fcIds)) {
                    $fcSet = array_flip(array_map('intval', $fcIds));
                    foreach ($rows as &$r) {
                        $rid = (int)($r['id'] ?? 0);
                        if ($rid && isset($fcSet[$rid])) { $r['has_rel'] = 1; }
                    }
                    unset($r);
                }
            } catch (\Throwable $_) { /* noop: si falla, solo no se marca */ }

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
                    'tip_contrato' => $r['tip_contrato'] ?? '',
                    'has_rel' => !empty($r['has_rel']) ? 1 : 0
                ];
            }, $rows);

            if (function_exists('ob_get_length') && ob_get_length() !== false) { @ob_clean(); }
            echo json_encode($out);
        } catch (Exception $e) {
            http_response_code(500);
            if (function_exists('ob_get_length') && ob_get_length() !== false) { @ob_clean(); }
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // GET /?page=calcolab_eventos&start=...&end=...&instructores=1,2
    public function eventos() {
        try {
            @ini_set('display_errors','0');
            if (function_exists('ob_start')) { @ob_start(); }
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
            } catch (Exception $e) { /* sin bloquear */ }
            $params = [];
            $buildBase = function($joinPf, $idCol) use ($start, $end, $estado, $fichaQ, &$params) {
                $sql = "
                    SELECT 
                        hf.id,
                        hf.titulo,
                        hf.fecha_inicio,
                        hf.fecha_fin,
                        hf.aula,
                        COALESCE(hf.color, '#3b82f6') AS color,
                        CASE 
                          WHEN NOW() BETWEEN hf.fecha_inicio AND hf.fecha_fin THEN 'en_curso'
                          WHEN NOW() < hf.fecha_inicio THEN 'programado'
                          ELSE 'finalizado'
                        END AS estado,
                        hf.ficha_id,
                        COALESCE(f.numero, f.id) AS ficha_numero,
                        f.nombre AS ficha_nombre,
                        fc.colegio_id AS ficha_colegio_id,
                        NULL AS ficha_colegio_legacy,
                        MIN($idCol) AS profesor_id,
                        COALESCE(GROUP_CONCAT(DISTINCT TRIM(CONCAT(u.nombres,' ',u.apellidos)) ORDER BY u.apellidos SEPARATOR ', '), '') AS profesor_nombre,
                        c.id AS colegio_id,
                        COALESCE(
                          c.nombre,
                          (SELECT c2.nombre FROM colegios c2 INNER JOIN aprendices a2 ON a2.colegio_id = c2.id WHERE a2.ficha_id = f.id LIMIT 1)
                        ) AS colegio_nombre
                    FROM horarios_fichas hf
                    INNER JOIN fichas f ON f.id = hf.ficha_id
                    $joinPf
                    LEFT JOIN ficha_colegio fc ON fc.ficha_id = f.id
                    LEFT JOIN facilitadores p ON p.id = $idCol
                    LEFT JOIN usuarios u ON u.id = p.usuario
                    LEFT JOIN colegios c ON c.id = fc.colegio_id
                    WHERE 1=1
                ";
                if ($start && $end) {
                    $ds = $this->toMysqlDateTime($start);
                    $de = $this->toMysqlDateTime($end);
                    if ($ds && $de) { $sql .= " AND hf.fecha_inicio < ? AND hf.fecha_fin > ?"; $params[] = $de; $params[] = $ds; }
                }
                if ($estado !== '') {
                    if ($estado === 'suspendido') { $sql .= " AND 1=0"; } /* estado suspendido no se soporta si no hay columna */
                    elseif ($estado === 'en_curso') { $sql .= " AND NOW() >= hf.fecha_inicio AND NOW() <= hf.fecha_fin"; }
                    elseif ($estado === 'finalizado') { $sql .= " AND NOW() > hf.fecha_fin"; }
                    elseif ($estado === 'programado') { $sql .= " AND NOW() < hf.fecha_inicio"; }
                }
                if ($fichaQ !== '') {
                    $sql .= " AND (CAST(COALESCE(f.numero, f.id) AS CHAR) LIKE ? OR f.nombre LIKE ?)";
                    $like = "%" . $fichaQ . "%";
                    $params[] = $like; $params[] = $like;
                }
                $sql .= " GROUP BY hf.id, hf.titulo, hf.fecha_inicio, hf.fecha_fin, hf.aula, hf.color, hf.estado, hf.ficha_id, f.numero, f.id, f.nombre, c.id, c.nombre ORDER BY hf.fecha_inicio";
                return $sql;
            };

            // Intento 0 (prioritario): usar directamente hf.facilitador_id
            $rows = [];
            try {
                $params0 = [];
                $sql0 = "
                    SELECT 
                        hf.id,
                        hf.titulo,
                        hf.fecha_inicio,
                        hf.fecha_fin,
                        hf.aula,
                        COALESCE(hf.color, '#3b82f6') AS color,
                        CASE 
                          WHEN NOW() BETWEEN hf.fecha_inicio AND hf.fecha_fin THEN 'en_curso'
                          WHEN NOW() < hf.fecha_inicio THEN 'programado'
                          ELSE 'finalizado'
                        END AS estado,
                        hf.ficha_id,
                        COALESCE(f.numero, f.id) AS ficha_numero,
                        f.nombre AS ficha_nombre,
                        fc.colegio_id AS ficha_colegio_id,
                        NULL AS ficha_colegio_legacy,
                        hf.facilitador_id AS profesor_id,
                        TRIM(CONCAT(u.nombres,' ',u.apellidos)) AS profesor_nombre,
                        c.id AS colegio_id,
                        COALESCE(
                          c.nombre,
                          (SELECT c2.nombre FROM colegios c2 INNER JOIN aprendices a2 ON a2.colegio_id = c2.id WHERE a2.ficha_id = f.id LIMIT 1)
                        ) AS colegio_nombre
                    FROM horarios_fichas hf
                    INNER JOIN fichas f ON f.id = hf.ficha_id
                    LEFT JOIN ficha_colegio fc ON fc.ficha_id = f.id
                    LEFT JOIN facilitadores p ON p.id = hf.facilitador_id
                    LEFT JOIN usuarios u ON u.id = p.usuario
                    LEFT JOIN colegios c ON c.id = fc.colegio_id
                    WHERE 1=1
                ";
                if ($start && $end) {
                    $ds = $this->toMysqlDateTime($start);
                    $de = $this->toMysqlDateTime($end);
                    if ($ds && $de) { $sql0 .= " AND hf.fecha_inicio < ? AND hf.fecha_fin > ?"; $params0[] = $de; $params0[] = $ds; }
                }
                if (!empty($ids)) {
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    $sql0 .= " AND hf.facilitador_id IN ($in)";
                    $params0 = array_merge($params0, $ids);
                }
                if ($estado !== '') {
                    if ($estado === 'suspendido') { $sql0 .= " AND 1=0"; }
                    elseif ($estado === 'en_curso') { $sql0 .= " AND NOW() >= hf.fecha_inicio AND NOW() <= hf.fecha_fin"; }
                    elseif ($estado === 'finalizado') { $sql0 .= " AND NOW() > hf.fecha_fin"; }
                    elseif ($estado === 'programado') { $sql0 .= " AND NOW() < hf.fecha_inicio"; }
                }
                if ($fichaQ !== '') {
                    $sql0 .= " AND (CAST(COALESCE(f.numero, f.id) AS CHAR) LIKE ? OR f.nombre LIKE ?)";
                    $like = "%".$fichaQ."%"; $params0[] = $like; $params0[] = $like;
                }
                $sql0 .= " ORDER BY hf.fecha_inicio";
                list($sqlExec0, $paramsExec0) = $this->alignParams($sql0, $params0);
                $stmt0 = $this->pdo->prepare($sqlExec0);
                $stmt0->execute($paramsExec0);
                $rows = $stmt0->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\PDOException $e0) {
                // Si falla por columna desconocida (colegio_id), intentar con columna legacy 'colegio'
                if ($e0->getCode() === '42S22' || stripos($e0->getMessage(), 'Unknown column') !== false) {
                    try {
                        $params0b = [];
                        $sql0b = str_replace('LEFT JOIN colegios c ON c.id = f.colegio_id', 'LEFT JOIN colegios c ON c.id = f.colegio', $sql0);
                        // reconstruir filtros con los mismos parámetros
                        if ($start && $end) {
                            $ds = $this->toMysqlDateTime($start);
                            $de = $this->toMysqlDateTime($end);
                            if ($ds && $de) { $params0b[] = $de; $params0b[] = $ds; }
                        }
                        if (!empty($ids)) { $params0b = array_merge($params0b, $ids); }
                        if ($estado !== '') { /* no añade params extra */ }
                        if ($fichaQ !== '') { $like = "%".$fichaQ."%"; $params0b[] = $like; $params0b[] = $like; }
                        list($sqlExec0b, $paramsExec0b) = $this->alignParams($sql0b, $params0b);
                        $stmt0b = $this->pdo->prepare($sqlExec0b);
                        $stmt0b->execute($paramsExec0b);
                        $rows = $stmt0b->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (\PDOException $e0b) {
                        if (!in_array($e0b->getCode(), ['42S02','42S01','42000'], true)) { throw $e0b; }
                    }
                } else if (!in_array($e0->getCode(), ['42S02','42S01','42000'], true)) { throw $e0; }
            }

            // Intento 1: facilitador_ficha (facilitador_id) si el intento 0 no devolvió filas
            if ($rows === []) {
                $params1 = $params;
                $sql1 = $buildBase("LEFT JOIN facilitador_ficha pf ON pf.ficha_id = f.id", "pf.facilitador_id");
                if (!empty($ids)) {
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    $sql1 = str_replace('WHERE 1=1', 'WHERE 1=1 AND pf.facilitador_id IN ('.$in.')', $sql1);
                    $params1 = array_merge($params1, $ids);
                }
                try {
                    list($sqlExec, $paramsExec) = $this->alignParams($sql1, $params1);
                    $stmt = $this->pdo->prepare($sqlExec);
                    $stmt->execute($paramsExec);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (\PDOException $e1) {
                    if (!in_array($e1->getCode(), ['42S02','42S01','42000'], true)) { throw $e1; }
                }
            }

            // Intento 2: profesor_ficha (profesor_id)
            if ($rows === []) {
                $params2 = $params; // reset filtros base
                $sql2 = $buildBase("LEFT JOIN profesor_ficha pf ON pf.ficha_id = f.id", "pf.profesor_id");
                if (!empty($ids)) {
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    $sql2 = str_replace('WHERE 1=1', 'WHERE 1=1 AND pf.profesor_id IN ('.$in.')', $sql2);
                    $params2 = array_merge($params2, $ids);
                }
                try {
                    list($sqlExec2, $paramsExec2) = $this->alignParams($sql2, $params2);
                    $stmt = $this->pdo->prepare($sqlExec2);
                    $stmt->execute($paramsExec2);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (\PDOException $e2) {
                    if (!in_array($e2->getCode(), ['42S02','42S01','42000'], true)) { throw $e2; }
                }
            }

            // Fallback 3: sin columna profesor en horarios_fichas; tomar instructor por relación de ficha si existe
            if ($rows === []) {
                $params3 = [];
                $sql3 = "
                    SELECT 
                        hf.id,
                        hf.titulo,
                        hf.fecha_inicio,
                        hf.fecha_fin,
                        hf.aula,
                        COALESCE(hf.color, '#3b82f6') AS color,
                        COALESCE(hf.estado, 'programado') AS estado,
                        hf.ficha_id,
                        COALESCE(f.numero, f.id) AS ficha_numero,
                        f.nombre AS ficha_nombre,
                        MIN(pf.facilitador_id) AS profesor_id,
                        COALESCE(GROUP_CONCAT(DISTINCT TRIM(CONCAT(u.nombres,' ',u.apellidos)) ORDER BY u.apellidos SEPARATOR ', '), '') AS profesor_nombre,
                        c.id AS colegio_id,
                        c.nombre AS colegio_nombre
                    FROM horarios_fichas hf
                    INNER JOIN fichas f ON f.id = hf.ficha_id
                    LEFT JOIN facilitador_ficha pf ON pf.ficha_id = f.id
                    LEFT JOIN facilitadores p ON p.id = pf.facilitador_id
                    LEFT JOIN usuarios u ON u.id = p.usuario
                    LEFT JOIN colegios c ON c.id = f.colegio_id
                    WHERE 1=1
                ";
                if ($start && $end) {
                    $ds = $this->toMysqlDateTime($start); $de = $this->toMysqlDateTime($end);
                    if ($ds && $de) { $sql3 .= " AND hf.fecha_inicio < ? AND hf.fecha_fin > ?"; $params3[] = $de; $params3[] = $ds; }
                }
                if (!empty($ids)) {
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    $sql3 .= " AND pf.facilitador_id IN ($in)"; $params3 = array_merge($params3, $ids);
                }
                if ($estado !== '') {
                    $sqlEstadoBase = "LOWER(COALESCE(hf.estado, ''))";
                    if ($estado === 'suspendido') { $sql3 .= " AND $sqlEstadoBase = 'suspendido'"; }
                    elseif ($estado === 'en_curso') { $sql3 .= " AND $sqlEstadoBase <> 'suspendido' AND NOW() >= hf.fecha_inicio AND NOW() <= hf.fecha_fin"; }
                    elseif ($estado === 'finalizado') { $sql3 .= " AND $sqlEstadoBase <> 'suspendido' AND NOW() > hf.fecha_fin"; }
                    elseif ($estado === 'programado') { $sql3 .= " AND $sqlEstadoBase <> 'suspendido' AND NOW() < hf.fecha_inicio"; }
                }
                if ($fichaQ !== '') {
                    $sql3 .= " AND (CAST(COALESCE(f.numero, f.id) AS CHAR) LIKE ? OR f.nombre LIKE ?)";
                    $like = "%".$fichaQ."%"; $params3[] = $like; $params3[] = $like;
                }
                $sql3 .= " GROUP BY hf.id, hf.titulo, hf.fecha_inicio, hf.fecha_fin, hf.aula, hf.color, hf.estado, hf.ficha_id, f.numero, f.id, f.nombre, c.id, c.nombre ORDER BY hf.fecha_inicio";
                list($sqlExec3, $paramsExec3) = $this->alignParams($sql3, $params3);
                $stmt = $this->pdo->prepare($sqlExec3);
                $stmt->execute($paramsExec3);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

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
                        'colegio_nombre' => $r['colegio_nombre'] ?? '',
                        'colegio_id_ficha' => isset($r['colegio_id']) ? (int)$r['colegio_id'] : null,
                        'colegio_legacy' => $r['colegio'] ?? null,
                        'aula' => $r['aula'] ?? '',
                        'estado' => $r['estado'] ?? 'programado',
                        'tipo' => 'colaborativo'
                    ]
                ];
            }, $rows);

            if (function_exists('ob_get_length') && ob_get_length() !== false) { @ob_clean(); }
            echo json_encode($eventos);
        } catch (Exception $e) {
            http_response_code(500);
            if (function_exists('ob_get_length') && ob_get_length() !== false) { @ob_clean(); }
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function toMysqlDateTime($s) {
        try { $dt = new DateTime($s); return $dt->format('Y-m-d H:i:s'); } catch(Exception $e) { return null; }
    }
    private function toIso($s) {
        try { $dt = new DateTime($s); return $dt->format('Y-m-d\TH:i:s'); } catch(Exception $e) { return null; }
    }

    // Alinea cantidad de placeholders ? con la cantidad de parámetros para evitar HY093
    private function alignParams(string $sql, array $params): array {
        $placeholders = substr_count($sql, '?');
        $count = count($params);
        if ($count === $placeholders) return [$sql, $params];
        if ($count > $placeholders) {
            // sobran parámetros: recortar
            return [$sql, array_slice($params, 0, $placeholders)];
        }
        // faltan parámetros: no deberíamos, pero completamos con null para no romper
        while ($count < $placeholders) { $params[] = null; $count++; }
        return [$sql, $params];
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
