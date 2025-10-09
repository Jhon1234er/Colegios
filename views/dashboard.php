<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) { header('Location: ?page=login'); exit; }

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

    <!-- Flatpickr (mismo estilo que en formulario de profesor) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

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
            <div class="welcome-center"><span id="greeting-text">¡HOLA!</span>
              <!-- Mano de contorno en verde (saludando) -->
              <svg class="hand-outline" width="32" height="32" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
                <!-- contorno mano -->
                <path d="M14 22 V9 a2 2 0 1 1 4 0v11
                         M18 21 V7 a2 2 0 1 1 4 0v16
                         M22 21 V8 a2 2 0 1 1 4 0v15
                         M26 23 V12 a2 2 0 1 1 4 0v14
                         M30 26 V16 a2 2 0 1 1 4 0v13
                         C34 36 28 42 21 42
                         C15 42 12 37 12 32
                         V27" />
                <!-- líneas de énfasis (movimiento) -->
                <path d="M36 10 l4 -4 M38 16 l6 -2" class="ho-accent" />
              </svg>
            </div>
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
                <div id="chart-container"></div>
            </div>

            <!-- Totales -->
            <div class="contadores-column">
                <div class="div5"><h3>Programas en Curso</h3><p><?= $totalMaterias ?></p></div>
                <div class="div6"><h3>Facilitadores Activos</h3><p><?= $totalProfesores ?></p></div>
                <div class="div7"><h3>Fichas Activas</h3><p><?= $totalFichas ?></p></div>
                <div class="div8"><h3>Aprendices Matriculados</h3><p><?= $totalEstudiante ?></p></div>
                <div class="div10">
                    <h3>Descargar reporte de semana (PDF)</h3>
                    <button class="btn" data-bs-toggle="modal" data-bs-target="#modalReportesPDF">Descargar PDF</button>
                </div>
                <div class="div11">
                    <h3>Descargar datos de la semana (Excel)</h3>
                    <button class="btn" data-bs-toggle="modal" data-bs-target="#modalReportesExcel">Descargar Excel</button>
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
                            <td><?= htmlspecialchars($c['codigo_dane'] ?? '') ?></td>
                            <td><?= formatearNombreColegio(htmlspecialchars($c['nombre'] ?? '')) ?></td>
                            <td><?= htmlspecialchars($c['tipo_institucion'] ?? '') ?></td>
                            <td><?= formatearNombreColegio(htmlspecialchars($c['departamento'] ?? '')) ?></td>
                            <td><?= formatearNombreColegio(htmlspecialchars($c['municipio'] ?? '')) ?></td>
                            <td><button class="btn-ver-colegio" data-id="<?= $c['id'] ?? '' ?>">Ver</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>


        </div>
    </div>
    
    <!-- Panel dinámico para resultados de búsqueda -->
    <div id="dashboard-resultados" style="display: none;"></div>
    
    <!-- Contenedor para detalles del estudiante en el espacio blanco -->
    <div id="student-details-sidebar" class="student-details-sidebar" style="display: none;">
        <div class="details-header">
            <h2>Detalles del Aprendiz</h2>
            <button id="close-details" class="close-details-btn">&times;</button>
        </div>
        <div id="student-details-content" class="student-details-empty">
            <div class="empty-state">
                <p>Selecciona un aprendiz para ver sus detalles</p>
            </div>
        </div>
    </div>
    
    <!-- Contenedor para detalles del facilitador/instructor -->
    <div id="professor-details-sidebar" class="student-details-sidebar" style="display: none;">
        <div class="details-header">
            <h2>Detalles del Facilitador</h2>
            <button id="close-prof-details" class="close-details-btn">&times;</button>
        </div>
        <div id="professor-details-content" class="student-details-empty">
            <div class="empty-state">
                <p>Selecciona un facilitador para ver sus detalles</p>
            </div>
        </div>
    </div>
    
    <!-- Overlay para oscurecer el dashboard normal -->
    <div id="dashboard-overlay" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.1); z-index: 1;"></div>

    <?php include 'Componentes/footer.php'; ?> 

<!-- Modal Reporte PDF -->
<div class="modal fade" id="modalReportesPDF" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Generar Reporte de Asistencias (PDF)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Select colegio -->
        <div class="mb-3">
          <label for="selectColegioPDF" class="form-label">Seleccione Colegio</label>
          <select id="selectColegioPDF" class="form-select">
            <option value="">-- Seleccionar --</option>
            <?php foreach ($colegios as $c): ?>
              <option value="<?= $c['id'] ?>"><?= formatearNombreColegio(htmlspecialchars($c['nombre'])) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- Fichas -->
        <div class="mb-3">
          <label class="form-label">Seleccione Ficha(s)</label>
          <div id="fichasContainerPDF"></div>
          <div class="form-check mt-2">
            <input type="checkbox" class="form-check-input" id="checkAllFichasPDF">
            <label for="checkAllFichasPDF" class="form-check-label">Seleccionar todas</label>
          </div>
        </div>
        <!-- Selector de semana (misma fila) -->
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Semana (inicio)</label>
            <input type="date" id="weekStartPDF" class="form-control" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Semana (fin)</label>
            <input type="date" id="weekEndPDF" class="form-control" />
          </div>
        </div>
        <!-- Vista previa -->
        <div id="previewContainerPDF" class="mt-4" style="display:none;">
          <h6>Vista previa:</h6>
          <div class="table-responsive">
            <table class="table table-bordered" id="previewTablePDF">
              <thead>
                <tr>
                  <th>Ficha</th>
                  <th>Documento</th>
                  <th>Lunes</th>
                  <th>Martes</th>
                  <th>Miércoles</th>
                  <th>Jueves</th>
                  <th>Viernes</th>
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
        <button type="button" id="btnPreviewPDF" class="btn btn-preview">Vista previa</button>
        <button type="button" id="btnDownloadPDF" class="btn btn-pdf">Descargar PDF</button>
      </div>
    </div>
  </div>
 </div>

<!-- Modal Reporte Excel -->
<div class="modal fade" id="modalReportesExcel" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Generar Reporte de Asistencias (Excel)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="selectColegioExcel" class="form-label">Seleccione Colegio</label>
          <select id="selectColegioExcel" class="form-select">
            <option value="">-- Seleccionar --</option>
            <?php foreach ($colegios as $c): ?>
              <option value="<?= $c['id'] ?>"><?= formatearNombreColegio(htmlspecialchars($c['nombre'])) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Seleccione Ficha(s)</label>
          <div id="fichasContainerExcel"></div>
          <div class="form-check mt-2">
            <input type="checkbox" class="form-check-input" id="checkAllFichasExcel">
            <label for="checkAllFichasExcel" class="form-check-label">Seleccionar todas</label>
          </div>
        </div>
        <!-- Selector de semana (misma fila) -->
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Semana (inicio)</label>
            <input type="date" id="weekStartExcel" class="form-control" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Semana (fin)</label>
            <input type="date" id="weekEndExcel" class="form-control" />
          </div>
        </div>
        <div id="previewContainerExcel" class="mt-4" style="display:none;">
          <h6>Vista previa:</h6>
          <div class="table-responsive">
            <table class="table table-bordered" id="previewTableExcel">
              <thead>
                <tr>
                  <th>Ficha</th>
                  <th>Documento</th>
                  <th>Lunes</th>
                  <th>Martes</th>
                  <th>Miércoles</th>
                  <th>Jueves</th>
                  <th>Viernes</th>
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
        <button type="button" id="btnPreviewExcel" class="btn btn-preview">Vista previa</button>
        <button type="button" id="btnDownloadExcel" class="btn btn-excel">Descargar Excel</button>
      </div>
    </div>
  </div>
</div>





    <!-- Librerías JS -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
    <script src="/js/dashboard.js"></script>
    <script src="/js/encabezado.js"></script>
    <script>
      // Saludo dinámico: por hora al entrar, y rotación de saludos al volver tras 2 min
      (function(){
        const el = document.getElementById('greeting-text');
        if (!el) return;

        const nombre = <?= json_encode(explode(' ', trim($usuario['nombres']))[0] ?? '') ?>;
        const RETURN_THRESHOLD = 120_000; // 2 minutos

        const RETURN_GREETINGS = [
          '¡Hola de nuevo!',
          `¡Qué bueno verte, ${nombre}!`,
          '¡Listo para continuar!',
          '¡Seguimos!' 
        ];

        function saludoPorHora(){
          const h = new Date().getHours();
          if (h < 12) return '¡Buenos días!';
          if (h < 19) return '¡Buenas tardes!';
          return '¡Buenas noches!';
        }

        function siguienteSaludo(){
          let idx = Number(localStorage.getItem('greetIdx')||0);
          const txt = RETURN_GREETINGS[idx % RETURN_GREETINGS.length];
          localStorage.setItem('greetIdx', String((idx+1) % RETURN_GREETINGS.length));
          return txt;
        }

        function setSaludoInicial(){
          const last = Number(localStorage.getItem('lastActiveTs')||0);
          const now = Date.now();
          const awayMs = now - last;
          if (last && awayMs > RETURN_THRESHOLD) {
            el.textContent = siguienteSaludo();
          } else {
            el.textContent = saludoPorHora();
          }
          localStorage.setItem('lastActiveTs', String(now));
        }

        function handleVisibility(){
          if (!document.hidden) {
            const last = Number(localStorage.getItem('lastActiveTs')||0);
            const now = Date.now();
            if (last && now - last > RETURN_THRESHOLD) {
              el.textContent = siguienteSaludo();
            }
            localStorage.setItem('lastActiveTs', String(now));
          }
        }

        setSaludoInicial();
        document.addEventListener('visibilitychange', handleVisibility);
        window.addEventListener('beforeunload', () => localStorage.setItem('lastActiveTs', String(Date.now())));
      })();
    </script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function(){
  function setupReportModal(suf, downloadPage){
    const $sel = $('#selectColegio'+suf);
    const $cont = $('#fichasContainer'+suf);
    const $checkAll = $('#checkAllFichas'+suf);
    const $prevWrap = $('#previewContainer'+suf);
    const $prevTable = $('#previewTable'+suf+' tbody');
    const $btnPrev = $('#btnPreview'+suf);
    const $btnDown = $('#btnDownload'+suf);
    // iniciar botón deshabilitado hasta que haya fichas
    $btnPrev.prop('disabled', true);

    // helper para debounce
    function debounce(fn, wait){
      let t; return function(){ clearTimeout(t); const args=arguments, ctx=this; t=setTimeout(()=>fn.apply(ctx,args), wait); };
    }

    function doPreview(){
      const colegioId = $sel.val();
      const fichas = getSel();
      const weekStart = $('#weekStart'+suf).val();
      const weekEnd   = $('#weekEnd'+suf).val();
      if(!colegioId || !fichas.length){ return; }
      $.post('/?page=preview_v2', {colegio_id: colegioId, fichas: fichas, week_start: weekStart, week_end: weekEnd}, function(html){
        $prevTable.html(html); $prevWrap.show();
      });
    }
    const doPreviewDebounced = debounce(doPreview, 250);

    $sel.off('change').on('change', function(){
      const colegioId = $(this).val();
      // al cambiar de colegio, limpiar selección y ocultar vista previa
      $cont.empty();
      $checkAll.prop('checked', false);
      $prevWrap.hide();
      $prevTable.empty();
      $btnPrev.prop('disabled', true);
      if(!colegioId){ return; }
      $.get('/ajax/get_fichas_por_colegio.php', {colegio_id:colegioId}, function(fichas){
        $cont.empty();
        if(!fichas || !fichas.length){
          $cont.html('<div class="text-muted">⚠️ No hay fichas</div>');
          $btnPrev.prop('disabled', true);
          return;
        }
        fichas.forEach(f=>{
          const codigo = (f.numero && String(f.numero).trim()!=='') ? String(f.numero).trim() : String(f.id);
          const label = `Ficha ${codigo}`;
          $cont.append(`<div class="form-check">
            <input class="form-check-input ficha-check-${suf}" type="checkbox" value="${f.id}" id="ficha${suf}${f.id}">
            <label class="form-check-label" for="ficha${suf}${f.id}">${label}</label>
          </div>`);
        });
        // gestionar habilitado del botón según selección y auto-preview
        $cont.find('.ficha-check-'+suf).on('change', function(){
          const any = $('.ficha-check-'+suf+':checked').length > 0;
          $btnPrev.prop('disabled', !any);
          if(any){ doPreviewDebounced(); } else { $prevWrap.hide(); $prevTable.empty(); }
        });
      }, 'json');
    });

    $checkAll.off('change').on('change', function(){
      $('.ficha-check-'+suf).prop('checked', this.checked);
      const any = $('.ficha-check-'+suf+':checked').length > 0;
      $btnPrev.prop('disabled', !any);
      if(!any){ $prevWrap.hide(); $prevTable.empty(); } else { doPreviewDebounced(); }
    });

    function getSel(){
      const ids = [];
      $('.ficha-check-'+suf+':checked').each(function(){ ids.push($(this).val()); });
      return ids;
    }

    // cambios de fechas -> auto-preview si hay selección
    $('#weekStart'+suf+',#weekEnd'+suf).off('change').on('change', doPreviewDebounced);

    $btnPrev.off('click').on('click', function(){
      const colegioId = $sel.val();
      const fichas = getSel();
      const weekStart = $('#weekStart'+suf).val();
      const weekEnd   = $('#weekEnd'+suf).val();
      if(!colegioId){ alert('Seleccione un colegio'); return; }
      if(!fichas.length){ alert('Seleccione al menos una ficha'); return; }
      $.post('/?page=preview_v2', {colegio_id: colegioId, fichas: fichas, week_start: weekStart, week_end: weekEnd}, function(html){
        $prevTable.html(html); $prevWrap.show();
      }).fail(function(xhr){ alert('Error vista previa: '+xhr.responseText); });
    });

    $btnDown.off('click').on('click', function(){
      const colegioId = $sel.val();
      const fichas = getSel();
      if(!colegioId){ alert('Seleccione un colegio'); return; }
      if(!fichas.length){ alert('Seleccione al menos una ficha'); return; }
      const url = `/?page=${downloadPage}&colegio_id=${colegioId}&fichas=${fichas.join(',')}`;
      window.open(url, '_blank');
    });

    // Reset al cerrar
    $('#modalReportes'+suf).on('hidden.bs.modal', function(){
      $sel.val(''); $cont.empty(); $checkAll.prop('checked', false);
      $prevWrap.hide(); $prevTable.empty();
    });
  }

  setupReportModal('PDF', 'generar_pdf');
  setupReportModal('Excel', 'generar_excel');

  // Calendarios con Flatpickr (estilo igual al formulario de profesor)
  if (typeof flatpickr !== 'undefined') {
    const fpOpts = {
      dateFormat: 'Y-m-d',
      locale: 'es',
      static: true,
      monthSelectorType: 'static',
      prevArrow: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>',
      nextArrow: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>',
      allowInput: true,
      clickOpens: true,
      disableMobile: false,
      onOpen: (_,__,inst)=>{ inst.calendarContainer.style.zIndex = '9999'; }
    };

    const fpStartPDF  = flatpickr('#weekStartPDF', fpOpts);
    const fpEndPDF    = flatpickr('#weekEndPDF',   fpOpts);
    const fpStartXLS  = flatpickr('#weekStartExcel', fpOpts);
    const fpEndXLS    = flatpickr('#weekEndExcel',   fpOpts);

    function linkRange($startSel, $endSel){
      const $s = $($startSel), $e = $($endSel);
      $s.on('change', function(){ if ($s.val()) { $e.attr('min', $s.val()); } });
      $e.on('change', function(){ if ($e.val()) { $s.attr('max', $e.val()); } });
    }
    linkRange('#weekStartPDF','#weekEndPDF');
    linkRange('#weekStartExcel','#weekEndExcel');
  }
});
</script>



</body>
</html>
