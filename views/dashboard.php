<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: /');
    exit;
}
$usuario = $_SESSION['usuario'] ?? null;
$isAdmin = isset($usuario['rol_id']) && $usuario['rol_id'] == 1;
//PARA LLAMAR EL TOTAL DE USUARIOS
require_once __DIR__ . '/../models/Ficha.php';
$fichas = new Ficha();
$totalFichas = $fichas->contarFichas();

//PARA LLAMAR EL TOTAL DE ESTUDIANTES
require_once __DIR__ . '/../models/Estudiante.php';
$estudianteModel = new Estudiante();
$totalEstudiante = $estudianteModel->contarEstudiantes();

//PARA LLAMAR EL TOTAL DE PROFESORES
require_once __DIR__ . '/../models/Profesor.php';
$profesorModel = new Profesor();
$totalProfesores = $profesorModel->contarProfesores();

//PARA LLAMAR EL TOTAL DE MATERIAS
require_once __DIR__ . '/../models/Materia.php';
$materiaModel = new Materia();
$totalMaterias = $materiaModel->contarMaterias();

//PARA LLAMAR LA LISTA DE COLEGIOS
require_once __DIR__ . '/../models/Colegio.php';
$colegioModel = new Colegio();
$colegios = $colegioModel->obtenerTodos(); 

$filtro = $_GET['filtro'] ?? null;
$q = $_GET['q'] ?? null;
$resultados = [];

if ($filtro && $q) {
    // Realiza la búsqueda según el filtro y llena $resultados
    if ($filtro === 'colegio') {
        $resultados = $colegioModel->buscarPorNombre($q);
    } elseif ($filtro === 'profesor') {
        $resultados = $profesorModel->buscarPorNombre($q);
    } elseif ($filtro === 'estudiante') {
        $resultados = $estudianteModel->buscarPorNombre($q);
    }
}
$mostrarResultados = ($filtro && $q);

// Si es AJAX, solo devuelve el bloque de resultados y termina
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    ob_start();
    ?>
    <div class="resultados-dashboard-flex">
      <div class="div10">
        <div class="resultados-lista">
          <h2>Resultados Similares</h2>
          <?php if (empty($resultados)): ?>
            <p style="color:#888;">No se encontraron resultados.</p>
          <?php else: ?>
            <table class="tabla-resultados">
              <thead>
                <tr>
                  <?php if ($filtro === 'colegio'): ?>
                    <th>Nombre</th>
                    <th>Tipo Institución</th>
                  <?php elseif ($filtro === 'profesor'): ?>
                    <th>Nombre Completo</th>
                    <th>Materia</th>
                    <th>Colegio</th>
                    <th>Tipo de Contrato</th>
                  <?php elseif ($filtro === 'estudiante'): ?>
                    <th>Nombre Completo</th>
                    <th>Grado</th>
                    <th>Grupo</th>
                    <th>Jornada</th>
                    <th>Informacion</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach($resultados as $i => $fila): ?>
                  <tr class="fila-resultado" data-index="<?= $i ?>">
                    <?php if ($filtro === 'colegio'): ?>
                      <td><?= formatearNombreColegio(htmlspecialchars($fila['nombre'] ?? '')) ?></td>
                      <td><?= htmlspecialchars($fila['tipo_institucion'] ?? '') ?></td>
                    <?php elseif ($filtro === 'profesor'): ?>
                      <td><?= htmlspecialchars(($fila['nombres'] ?? '') . ' ' . ($fila['apellidos'] ?? '')) ?></td>
                      <td><?= htmlspecialchars($fila['materia'] ?? '') ?></td>
                      <td><?= formatearNombreColegio(htmlspecialchars($fila['colegio'] ?? '')) ?></td>
                      <td><?= htmlspecialchars($fila['tip_contrato'] ?? '') ?></td>
                    <?php elseif ($filtro === 'estudiante'): ?>
                      <td><?= htmlspecialchars(($fila['nombres'] ?? '') . ' ' . ($fila['apellidos'] ?? '')) ?></td>
                      <td><?= htmlspecialchars($fila['grado'] ?? ''). 'º' ?></td>
                      <td><?= !empty($fila['grupo']) ? htmlspecialchars($fila['grupo']) : 'No aplica' ?></td>
                      <td><?= htmlspecialchars($fila['jornada'] ?? '') ?></td>
                      <td>
                        <!-- <button class="btn-ver-estudiante" data-id="<?= $fila['id'] ?>">Ver Informacion</button> -->
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
      <div class="div11">
        <div class="resultados-detalle" id="detalle-resultado">
          <div class="detalle-placeholder">
            <p>Seleciona una fila para saber mas informacion.</p>
          </div>
        </div>
      </div>
    </div>
    <button id="volver-dashboard" class="btn-volver">Finalizar busqueda</button>
    <?php
    echo ob_get_clean();
    exit;
}

function formatearNombreColegio($nombre) {
    // Formato título con soporte para tildes
    $nombre = mb_convert_case($nombre, MB_CASE_TITLE, "UTF-8");
    
    // Siglas con puntos (ej: D.C. o , D.C.)
    $nombre = preg_replace_callback('/(\s|,)\s*([A-Za-z])\.([A-Za-z])\.?/u', function ($m) {
            return $m[1] . strtoupper($m[2]) . '.' . strtoupper($m[3]);
        },
        $nombre
    );
    
    // Siglas en paréntesis (ej: (ied) → (IED))
    $nombre = preg_replace_callback('/\(([a-zA-Z]{2,})\)/u', function($m) {
        return '(' . strtoupper($m[1]) . ')';
    }, $nombre);
    
    return $nombre;
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administrador</title>
    <link rel="stylesheet" href="/css/dashboard.css">
      <script src="https://echarts.apache.org/en/js/vendors/echarts/dist/echarts.min.js"></script>
</head>
<body>
    <?php include 'Componentes/encabezado.php'; ?>

    <div id="dashboard-normal" class="dashboard-panel" style="<?= $mostrarResultados ? 'display:none;' : '' ?>">

    <div class="parent">
        <div class="div1" style="overflow-y: auto; padding: 1rem;"> <p style="color: #666;">Selecciona un colegio en la tabla para ver sus Facilitadores.</p> </div>

        <div class="div2" style="overflow-y: auto; padding: 1rem;"> <p style="color: #666;">Selecciona un colegio en la tabla para ver sus Aprendices</p> </div>
        
        <div class="div3" style="overflow-y: auto; padding: 1rem;">
            <h3>Estadísticas de asistencias por ficha</h3>
            <p class="esta"style="color: #666;">Selecciona un colegio en la tabla para ver las asistencias de sus Aprendices  </p> 
            <div id="chart-container" style="width:100%;height:400px;"></div>
        </div>

        
        <div class="div4">
            <h3 class="Tabla">Colegios Gestionados</h3>
            <table class="tabla-colegios">
                <thead>
                    <tr>
                        <th>Dane</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Departamento</th>
                        <th>Municipio</th>
                        <th>Informacion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($colegios as $colegio): ?>
                        <tr>
                            <td><?= htmlspecialchars($colegio['codigo_dane']) ?></td>
                            <td><?= formatearNombreColegio(htmlspecialchars($colegio['nombre'])) ?></td>
                            <td><?= htmlspecialchars($colegio['tipo_institucion']) ?></td>
                            <td><?=formatearNombreColegio(htmlspecialchars($colegio['departamento'])) ?></td>
                            <td><?=formatearNombreColegio(htmlspecialchars($colegio['municipio'])) ?></td>
                            <td>
                                <button class="btn-ver-colegio" data-id="<?= $colegio['id'] ?>">Ver Informacion</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="div5">
            <h3>Total de Programas en Curso</h3>
            <p style="font-size: 2rem; font-weight: bold;">
            <?= $totalMaterias ?>
            </p>
        </div>
        
        <div class="div6">
            <h3>Total de Facilitadores Activos</h3>
            <p style="font-size: 2rem; font-weight: bold;">
            <?= $totalProfesores ?>
            </p>
        </div>
        
        <div class="div7">
            <h3>Total de Fichas Activas</h3>
            <p style="font-size: 2rem; font-weight: bold;">
            <?= $totalFichas ?>
            </p>
        </div>

        <div class="div8">
            <h3>Total de Aprendices Matriculados</h3>
            <p style="font-size: 2rem; font-weight: bold;">
            <?= $totalEstudiante ?>
            </p>
        </div>

        <div class="div9">
            <p> <strong>Bienvenido</strong> <?= htmlspecialchars($usuario['nombres']) ?> <?= htmlspecialchars($usuario['apellidos']) ?> a Sistem scholl Tecno Academia</p>
            <p class="texto-rol"><strong>Rol:</strong> <?= $isAdmin ? 'Administrador' : 'Otro' ?></p>
        </div>
    <?php include 'Componentes/footer.php'; ?> 
        
    </div>
    </div>

    <!-- El dashboard de resultados solo se llena por AJAX -->
    <div id="dashboard-resultados" class="dashboard-panel" style="display:none;"></div>
    <div id="dashboard-overlay"></div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- Tu JS personalizado -->
    <script src="/js/dashboard.js"></script>
    <script src="/js/encabezado.js"></script>
</body>
</html>
