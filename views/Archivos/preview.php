<?php
require_once __DIR__ . '/../../models/Estudiante.php';
require_once __DIR__ . '/../../models/Ficha.php';
require_once __DIR__ . '/../../config/db.php';

$colegioId = $_POST['colegio_id'] ?? null;
$fichas    = $_POST['fichas'] ?? [];

// Exigir al menos una ficha para la vista previa
if (!$fichas || !is_array($fichas) || count($fichas) === 0) {
    echo "<tr><td colspan='10' class='text-center text-warning'>Seleccione al menos una ficha para ver la vista previa.</td></tr>";
    return; // detener render
}

$estudianteModel = new Estudiante();
$fichaModel = new Ficha();
$pdo = Database::conectar();
$rows = [];

// Calcular semana actual (Lunes a Viernes)
$weekStart = $_POST['week_start'] ?? null; // YYYY-MM-DD
$weekEnd   = $_POST['week_end']   ?? null; // YYYY-MM-DD
$daysParam = $_POST['days'] ?? [];          // array o CSV

// Construir arreglo de fechas a mostrar
$diasSemana = [];
// Si viene una lista de días explícitos, usarla
if (!empty($daysParam)) {
    if (is_string($daysParam)) { $daysParam = array_filter(array_map('trim', explode(',', $daysParam))); }
    if (is_array($daysParam)) {
        foreach ($daysParam as $d) {
            if (!$d) continue;
            $dt = DateTime::createFromFormat('Y-m-d', $d);
            if ($dt) {
                $dow = (int)$dt->format('N');
                if ($dow >= 1 && $dow <= 5) { $diasSemana[] = $dt->format('Y-m-d'); }
            }
        }
        // ordenar y únicos
        $diasSemana = array_values(array_unique($diasSemana));
        sort($diasSemana);
    }
} elseif ($weekStart) {
    $s = new DateTime($weekStart);
    if ($weekEnd) {
        $e = new DateTime($weekEnd);
        if ($e < $s) { $e = clone $s; }
        $days = 0;
        while ($s <= $e && $days < 10) {
            $dow = (int)$s->format('N');
            if ($dow >= 1 && $dow <= 5) { $diasSemana[] = $s->format('Y-m-d'); }
            $s->modify('+1 day');
            $days++;
        }
    } else {
        // Sin fin: usar L-V de la semana del inicio
        $startOfWeek = (clone $s)->modify('monday this week');
        for ($i=0; $i<5; $i++) { $d = clone $startOfWeek; $d->modify("+{$i} day"); $diasSemana[] = $d->format('Y-m-d'); }
    }
} else {
    // Sin inicio: semana actual L-V
    $base = new DateTime('now');
    $startOfWeek = (clone $base)->modify('monday this week');
    for ($i=0; $i<5; $i++) { $d = clone $startOfWeek; $d->modify("+{$i} day"); $diasSemana[] = $d->format('Y-m-d'); }
}

if ($fichas && is_array($fichas)) {
    foreach($fichas as $fichaId) {
        $estudiantes = $estudianteModel->obtenerTodos($fichaId);
        foreach ($estudiantes as &$estudiante) {
            $ficha = $fichaModel->obtenerPorId($fichaId);
            $estudiante['numero_ficha'] = $ficha['numero'] ?? $fichaId;
        }
        $rows = array_merge($rows, $estudiantes);
    }
} else {
    $rows = $estudianteModel->obtenerPorColegio($colegioId);
    foreach ($rows as &$row) {
        if (isset($row['ficha_id'])) {
            $ficha = $fichaModel->obtenerPorId($row['ficha_id']);
            $row['numero_ficha'] = $ficha['numero'] ?? $row['ficha_id'];
        }
    }
}

// Emitir fila meta oculta con los días seleccionados (solo L-V dentro del rango)
$metaDias = implode(',', $diasSemana);
echo "<tr class='__meta' data-dias='" . htmlspecialchars($metaDias, ENT_QUOTES, 'UTF-8') . "' style='display:none'></tr>";
if (!$rows || count($rows) === 0): ?>
    <tr>
        <td colspan="10" class="text-center text-muted">⚠️ No hay estudiantes</td>
    </tr>
<?php else: ?>
    <?php foreach ($rows as $e):
        $estado = $e['estado'] ?? 'Activo';
        $estadoClass = $estado === 'Deserto' ? 'estado-deserto' : 'estado-activo';

        // Nombre/documento
        $apellidos = isset($e['apellidos']) && trim($e['apellidos']) !== '' ? trim($e['apellidos']) : '';
        $nombres   = isset($e['nombres']) && trim($e['nombres']) !== '' ? trim($e['nombres']) : '';
        $nombreCompleto = trim($nombres . ' ' . $apellidos);
        if ($nombreCompleto === '') { $nombreCompleto = ($e['nombre_completo'] ?? ''); }
        if (trim($nombreCompleto) === '') { $nombreCompleto = 'Sin nombre'; }
        $tipoDoc = $e['tipo_documento'] ?? 'CC';
        $numDoc  = $e['numero_documento'] ?? '';
        $documento = $numDoc !== '' ? ($tipoDoc . ' ' . $numDoc) : 'Sin documento';
        $numeroFicha = $e['numero_ficha'] ?? ($e['ficha'] ?? 'N/A');
        // Jornada: normalizar valores y evitar vacíos feos
        $jornada = isset($e['jornada']) ? trim((string)$e['jornada']) : '';
        // Quitar comillas o caracteres extraños
        $jornada = trim($jornada, " \"'“”");
        $jn = strtolower(iconv('UTF-8','ASCII//TRANSLIT',$jornada));
        if ($jn === '' || $jn === '""' || $jn === 'null' || $jn === 'n/a') { $jornada = ''; }
        elseif (strpos($jn,'tarde') !== false) { $jornada = 'Tarde'; }
        elseif (strpos($jn,'manana') !== false || strpos($jn,'mañana') !== false) { $jornada = 'Mañana'; }
        elseif (strpos($jn,'noche') !== false || strpos($jn,'noct') !== false) { $jornada = 'Noche'; }
        elseif (strpos($jn,'mixta') !== false) { $jornada = 'Mixta'; }
        // Si sigue vacía, mostrar vacío sin comillas
        if ($jornada === '') { $jornada = ''; }

        // Traer asistencias de la semana para el estudiante
        $asistMap = [];
        try {
            $estId = $e['id'] ?? ($e['estudiante_id'] ?? null);
            if ($estId) {
                $stmt = $pdo->prepare("SELECT DATE(fecha) as f, estado FROM asistencias WHERE estudiante_id = ? AND DATE(fecha) BETWEEN ? AND ?");
                $stmt->execute([ $estId, $diasSemana[0], end($diasSemana) ]);
            } else {
                $stmt = null;
            }
            if ($stmt) {
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) { $asistMap[$a['f']] = $a['estado']; }
            }
        } catch (Exception $ex) { /* silencioso en preview */ }

        // Helper de estado legible
        $nice = function($estadoRaw){
            if (!$estadoRaw) return 'No hubo clase';
            $m = [ 'presente' => 'Presente', 'ausente' => 'Ausente', 'justificado' => 'Justificado', 'tarde' => 'Tarde' ];
            $e = strtolower($estadoRaw);
            return $m[$e] ?? ucfirst($e);
        };
    ?>
    <tr>
        <td><?= htmlspecialchars($documento) ?></td>
        <td><?= htmlspecialchars($nombreCompleto) ?></td>
        <td><?= htmlspecialchars($numeroFicha) ?></td>
        <?php foreach ($diasSemana as $d): ?>
          <td><?= htmlspecialchars($nice($asistMap[$d] ?? null)) ?></td>
        <?php endforeach; ?>
        <td><?= htmlspecialchars($jornada) ?></td>
        <td class="<?= $estadoClass ?>"><?= htmlspecialchars($estado) ?></td>
    </tr>
    <?php endforeach; ?>
<?php endif; ?>
