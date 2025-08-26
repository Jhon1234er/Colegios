<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header('Location: /'); exit; }

$usuario = $_SESSION['usuario'];
$isAdmin = ($usuario['rol_id'] == 1);

// ===== Modelos =====
require_once __DIR__ . '/../models/Ficha.php';
require_once __DIR__ . '/../models/Estudiante.php';
require_once __DIR__ . '/../models/Profesor.php';
require_once __DIR__ . '/../models/Materia.php';
require_once __DIR__ . '/../models/Colegio.php';

// Totales
$totalFichas     = (new Ficha())->contarFichas();
$totalEstudiante = (new Estudiante())->contarEstudiantes();
$totalProfesores = (new Profesor())->contarProfesores();
$totalMaterias   = (new Materia())->contarMaterias();
$colegios        = (new Colegio())->obtenerTodos();

// Helper
function formatearNombreColegio($nombre) {
    $nombre = mb_convert_case($nombre, MB_CASE_TITLE, "UTF-8");
    $nombre = preg_replace_callback('/(\s|,)\s*([A-Za-z])\.([A-Za-z])\.?/u', fn($m) => $m[1] . strtoupper($m[2]) . '.' . strtoupper($m[3]), $nombre);
    $nombre = preg_replace_callback('/\(([a-zA-Z]{2,})\)/u', fn($m) => '(' . strtoupper($m[1]) . ')', $nombre);
    return $nombre;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administrador</title>
    <link rel="stylesheet" href="/css/dashboard.css">
</head>
<body>
    <?php include 'Componentes/encabezado.php'; ?>
    
    <div id="dashboard-normal" class="dashboard-panel">
        <div class="parent">
    <!-- Bienvenida -->
        <div class="div9">
            <p><strong>Bienvenido</strong> <?= htmlspecialchars($usuario['nombres'].' '.$usuario['apellidos']) ?></p>
            <p><strong>Rol:</strong> <?= $isAdmin ? 'Administrador' : 'Otro' ?></p>
        </div>

    <!-- Columna izquierda -->
            <div class="indicaciones-column">
                <div class="div1"><p>Selecciona un colegio para ver sus Facilitadores/Instrucrores.</p></div>
                <div class="div2"><p>Selecciona un colegio para ver sus Aprendices.</p></div>
            </div>

            <!-- Gráfico -->
            <div class="div3">
                <h3>Estadísticas de asistencias por ficha</h3>
                <div id="chart-container">Selecciona un colegio</div>
            </div>

            <!-- Totales -->
            <div class="contadores-column">
                <div class="div5"><h3>Programas en Curso</h3><p><?= $totalMaterias ?></p></div>
                <div class="div6"><h3>Facilitadores Activos</h3><p><?= $totalProfesores ?></p></div>
                <div class="div7"><h3>Fichas Activas</h3><p><?= $totalFichas ?></p></div>
                <div class="div8"><h3>Aprendices Matriculados</h3><p><?= $totalEstudiante ?></p></div>
                <div class="div10">
                    <h3>Descargar reporte de semana (PDF)</h3>
                    <a href="/views/Archivos/generar_pdf.php" class="btn">Descargar PDF</a>
                </div>
                <div class="div11">
                    <h3>Descargar datos de la semana (Excel)</h3>
                    <a href="/views/Archivos/generar_excel.php" class="btn">Descargar Excel</a>
                </div>
            </div>

            

            <!-- Tabla colegios -->
            <div class="div4">
                <h3>Colegios Gestionados</h3>
                <table id="tabla-colegios">
                    <thead>
                        <tr>
                            <th>Dane</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Departamento</th>
                            <th>Municipio</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($colegios as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['codigo_dane']) ?></td>
                            <td><?= formatearNombreColegio(htmlspecialchars($c['nombre'])) ?></td>
                            <td><?= htmlspecialchars($c['tipo_institucion']) ?></td>
                            <td><?= formatearNombreColegio(htmlspecialchars($c['departamento'])) ?></td>
                            <td><?= formatearNombreColegio(htmlspecialchars($c['municipio'])) ?></td>
                            <td><button class="btn-ver-colegio" data-id="<?= $c['id'] ?>">Ver</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>


        </div>
    </div>

    <!-- Panel dinámico -->
    <div id="dashboard-resultados" class="dashboard-panel" style="display:none;"></div>
    <div id="dashboard-overlay"></div>

    <?php include 'Componentes/footer.php'; ?> 

    <!-- Librerías JS -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
    <script src="/js/dashboard.js"></script>
    <script src="/js/encabezado.js"></script>
</body>
</html>
