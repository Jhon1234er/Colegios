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
    <!-- CSS Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- JS Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

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
                    <button class="btn" data-bs-toggle="modal" data-bs-target="#modalReportes">Descargar PDF</button>
                </div>
                <div class="div11">
                    <h3>Descargar datos de la semana (Excel)</h3>
                    <button class="btn" data-bs-toggle="modal" data-bs-target="#modalReportes">Descargar Excel</button>
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

<!-- Modal Generar Reportes EXCEL/PDF-->
<div class="modal fade" id="modalReportes" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Generar Reportes de Asistencias</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        
        <!-- Select colegio -->
        <div class="mb-3">
          <label for="selectColegio" class="form-label">Seleccione Colegio</label>
          <select id="selectColegio" class="form-select">
            <option value="">-- Seleccionar --</option>
            <?php foreach ($colegios as $c): ?>
              <option value="<?= $c['id'] ?>">
                <?= formatearNombreColegio(htmlspecialchars($c['nombre'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Fichas dinámicas -->
        <div class="mb-3">
          <label class="form-label">Seleccione Ficha(s)</label>
          <div id="fichasContainer"></div>
          <div class="form-check mt-2">
            <input type="checkbox" class="form-check-input" id="checkAllFichas">
            <label for="checkAllFichas" class="form-check-label">Seleccionar todas</label>
          </div>
        </div>

        <!-- Vista previa -->
        <div id="previewContainer" class="mt-4" style="display:none;">
          <h6>Vista previa:</h6>
          <div class="table-responsive">
            <table class="table table-bordered" id="previewTable">
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th>Documento</th>
                  <th>Ficha</th>
                  <th>Jornada</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" id="btnPreview" class="btn btn-info">Vista previa</button>
        <button type="button" id="btnDownloadExcel" class="btn btn-success">Descargar Excel</button>
        <button type="button" id="btnDownloadPDF" class="btn btn-danger">Descargar PDF</button>
      </div>
    </div>
  </div>
</div>





    <!-- Librerías JS -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
    <script src="/js/dashboard.js"></script>
    <script src="/js/encabezado.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){

  // Cargar fichas al seleccionar colegio
  $('#selectColegio').change(function(){
    let colegioId = $(this).val();
    if(!colegioId) return;

    $.ajax({
      url: '/ajax/get_fichas_por_colegio.php',
      method: 'GET',
      data: { colegio_id: colegioId },
      dataType: 'json',
      success: function(fichas){
        console.log("✅ Respuesta fichas:", fichas);
        $('#fichasContainer').empty();

        if (!fichas || fichas.length === 0) {
          $('#fichasContainer').html('<div class="text-muted">⚠️ No hay fichas</div>');
        } else {
          fichas.forEach(f => {
            let label = f.nombre && f.nombre.trim() !== "" ? f.nombre : "Ficha " + f.id;
            $('#fichasContainer').append(`
              <div class="form-check">
                <input class="form-check-input ficha-check" type="checkbox" value="${f.id}" id="ficha${f.id}">
                <label class="form-check-label" for="ficha${f.id}">${label}</label>
              </div>
            `);
          });
        }
      },
      error: function(xhr, status, err){
        console.error("❌ Error cargando fichas:", err, xhr.responseText);
        alert("Error cargando fichas: " + xhr.responseText);
      }
    });
  });

  // Seleccionar todas las fichas
  $('#checkAllFichas').on('change', function(){
    $('.ficha-check').prop('checked', this.checked);
  });

  // 🔹 Helper: obtener fichas seleccionadas
  function getFichasSeleccionadas(){
    let fichas = [];
    $('.ficha-check:checked').each(function(){
      fichas.push($(this).val());
    });
    return fichas;
  }

  // Vista previa
  $('#btnPreview').click(function(){
    let colegioId = $('#selectColegio').val();
    let fichas = getFichasSeleccionadas();
    if(!colegioId){
      alert('Seleccione un colegio');
      return;
    }

    $.ajax({
      url: '/Archivos/preview.php',
      method: 'POST',
      data: { colegio_id: colegioId, fichas: fichas },
      success: function(html){
        $('#previewTable tbody').html(html);   // insertar filas
        $('#previewContainer').show();         // mostrar tabla
      },
      error: function(xhr, status, err){
        console.error("❌ Error vista previa:", err, xhr.responseText);
        alert("Error cargando vista previa: " + xhr.responseText);
      }
    });
  });

  // Descargar Excel
  $('#btnDownloadExcel').click(function(){
    let colegioId = $('#selectColegio').val();
    let fichas = getFichasSeleccionadas();
    if(!colegioId) { 
      alert('Seleccione un colegio'); 
      return; 
    }
    let url = `/?page=generar_excel&colegio_id=${colegioId}&fichas=${fichas.join(',')}`;
    console.log("📥 Descargando Excel:", url);
    window.open(url, "_blank");
  });

  // Descargar PDF
  $('#btnDownloadPDF').click(function(){
    let colegioId = $('#selectColegio').val();
    let fichas = getFichasSeleccionadas();
    if(!colegioId) { 
      alert('Seleccione un colegio'); 
      return; 
    }
    let url = `/?page=generar_pdf&colegio_id=${colegioId}&fichas=${fichas.join(',')}`;
    console.log("📥 Descargando PDF:", url);
    window.open(url, "_blank");
  });

});
</script>



</body>
</html>
