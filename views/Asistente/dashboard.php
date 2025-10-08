<?php
start_secure_session();
require_login();
require_role([1,4]);
include __DIR__ . '/../Componentes/encabezado.php';
?>
<!-- Select2 CSS (solo en este dashboard) -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<!-- jQuery UI CSS para datepicker con tema ligero -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<style>
  /* Variables SENA */
  :root {
    --sena-green: #39A900;
    --sena-green-dark: #2d8400;
    --sena-green-light: #e8f5e0;
    --sena-green-pale: #f4faf0;
  }
  
  /* Layout full height */
  body { min-height: 100vh; }
  .container { max-width: 100% !important; padding-left:20px; padding-right:20px; }
  
  /* Métricas con acento verde SENA */
  .metric { 
    border:2px solid var(--sena-green-light); 
    border-radius:12px; 
    background:#fff; 
    padding:18px; 
    height:100%; 
    box-shadow:0 2px 8px rgba(57,169,0,0.08);
    transition: all .3s ease;
  }
  .metric:hover { 
    border-color: var(--sena-green); 
    box-shadow:0 4px 16px rgba(57,169,0,0.15);
    transform: translateY(-2px);
  }
  .metric h6 { 
    color:#6b7280; 
    font-size:.75rem; 
    text-transform:uppercase; 
    letter-spacing:.05rem; 
    margin:0 0 6px;
    font-weight:600;
  }
  .metric .val { 
    font-size:1.8rem; 
    font-weight:700; 
    color: var(--sena-green);
  }
  
  /* Tablas con header verde SENA - sin degradado */
  .table thead th { 
    background: var(--sena-green);
    color: #fff;
    font-weight:600;
    border:0;
    padding:12px;
    font-size:.9rem;
  }
  .table tbody tr:hover { background: var(--sena-green-pale); }
  .table tbody td { padding:10px 12px; vertical-align:middle; }
  
  /* Cards con borde verde */
  .card {
    border:2px solid var(--sena-green-light);
    border-radius:12px;
    box-shadow:0 2px 8px rgba(57,169,0,0.06);
    transition: all .3s ease;
    margin-bottom:16px;
  }
  .card:hover {
    box-shadow:0 4px 16px rgba(57,169,0,0.12);
  }
  .card-header {
    background: var(--sena-green-light) !important;
    border-bottom:2px solid var(--sena-green-light);
    font-weight:600;
    color: var(--sena-green-dark);
    padding:12px 16px;
  }
  .card-body { padding:16px; }
  
  /* Filtros con acento verde */
  .filters .form-select, .filters .form-control[type="date"], .filters .select2-container .select2-selection--single {
    border:2px solid var(--sena-green-light); 
    border-radius:10px; 
    background:#fff; 
    color:#111827;
    transition: all .3s ease; 
    min-height:42px;
  }
  .filters .form-control[type="date"] { padding: .55rem .8rem; }
  .filters .form-select:focus, .filters .form-control[type="date"]:focus, .filters .select2-container--default.select2-container--focus .select2-selection--single {
    border-color: var(--sena-green); 
    box-shadow:0 0 0 3px rgba(57,169,0,0.15);
  }
  .filters .input-group-text { 
    border-radius:10px; 
    background: var(--sena-green-pale); 
    color: var(--sena-green-dark); 
    border:2px solid var(--sena-green-light);
    font-weight:600;
  }
  .filters .btn { 
    border-radius:10px; 
    font-weight:600;
    transition: all .3s ease;
    padding:.55rem 1rem;
  }
  .filters .btn-primary {
    background: var(--sena-green);
    border-color: var(--sena-green);
  }
  .filters .btn-primary:hover {
    background: var(--sena-green-dark);
    border-color: var(--sena-green-dark);
    transform: translateY(-2px);
    box-shadow:0 4px 12px rgba(57,169,0,0.3);
  }
  .filters .btn-success {
    background: var(--sena-green-dark);
    border-color: var(--sena-green-dark);
  }
  .filters .btn-success:hover {
    background: #1f6600;
    border-color: #1f6600;
    transform: translateY(-2px);
    box-shadow:0 4px 12px rgba(45,132,0,0.3);
  }
  
  /* Select2 con tema verde */
  .filters .select2-container--default .select2-selection--single { height:42px; display:flex; align-items:center; }
  .filters .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:42px; padding-left:12px; }
  .filters .select2-container--default .select2-selection--single .select2-selection__arrow { height:42px; right:10px; }
  .filters .select2-dropdown { 
    border-radius:10px; 
    box-shadow: 0 8px 20px rgba(57,169,0,0.15); 
    border:2px solid var(--sena-green-light); 
  }
  .filters .select2-results__option--highlighted { 
    background: var(--sena-green-light) !important; 
    color: var(--sena-green-dark) !important; 
  }
  .filters .select2-selection__placeholder { color:#6b7280; }
  
  /* jQuery UI Datepicker verde */
  .ui-datepicker { 
    font-family: inherit; 
    border-radius:10px; 
    border:2px solid var(--sena-green-light); 
    box-shadow:0 8px 20px rgba(57,169,0,0.12); 
    padding:.5rem; 
    z-index: 9999 !important;
  }
  .ui-datepicker .ui-datepicker-header { 
    background: var(--sena-green); 
    color:#fff;
    border:0; 
    border-radius:8px;
    font-weight:600;
  }
  .ui-datepicker .ui-state-default { 
    border:0; 
    background:#fff; 
    text-align:center; 
    padding:.35rem .45rem; 
    border-radius:6px; 
  }
  .ui-datepicker .ui-state-active { 
    background: var(--sena-green); 
    color:#fff;
    font-weight:600;
  }
  .ui-datepicker .ui-state-hover { 
    background: var(--sena-green-light); 
    color: var(--sena-green-dark);
  }
  
  /* Badges verde SENA */
  .badge.bg-success {
    background: var(--sena-green) !important;
  }
  
  /* Títulos con acento verde */
  h3, h5 {
    color: var(--sena-green-dark);
    font-weight:700;
  }
  
  /* Ajustes de espaciado para aprovechar altura */
  .row.g-3 { row-gap:12px !important; }
  .mb-2 { margin-bottom:8px !important; }
  .mb-3 { margin-bottom:12px !important; }
  .mb-4 { margin-bottom:16px !important; }
  .mt-4 { margin-top:16px !important; }
  
  /* Panel lateral más compacto */
  #panelInstructores, #panelAprendices {
    max-height:280px;
    overflow-y:auto;
  }
  #panelInstructores::-webkit-scrollbar, #panelAprendices::-webkit-scrollbar {
    width:6px;
  }
  #panelInstructores::-webkit-scrollbar-thumb, #panelAprendices::-webkit-scrollbar-thumb {
    background: var(--sena-green-light);
    border-radius:3px;
  }
  
  @media (max-width: 768px) {
    .filters .input-group { gap:8px; flex-wrap:wrap; }
  }
  
  /* Estilos para tarjetas de profesores y estudiantes */
  .profesor-container, .students-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
    max-height: 400px;
    overflow-y: auto;
  }
  .profesor-card, .student-card {
    background: #fff;
    border: 2px solid var(--sena-green-light);
    border-radius: 10px;
    padding: 12px;
    transition: all .3s ease;
  }
  .profesor-card:hover, .student-card:hover {
    border-color: var(--sena-green);
    box-shadow: 0 4px 12px rgba(57,169,0,0.15);
    transform: translateY(-2px);
  }
  .profesor-name, .student-name {
    font-weight: 700;
    color: var(--sena-green-dark);
    font-size: 1rem;
    margin-bottom: 8px;
  }
  .profesor-card p, .student-details {
    font-size: 0.85rem;
    color: #6b7280;
    margin: 4px 0;
  }
  .alertas {
    background: #fef2f2;
    border: 2px solid #fecaca;
    border-radius: 10px;
    padding: 12px;
    margin-top: 12px;
  }
  .alertas h4 {
    color: #dc2626;
    font-size: 1rem;
    margin-bottom: 8px;
  }
  .alertas ul {
    list-style: none;
    padding: 0;
    margin: 0;
  }
  .alertas li {
    padding: 6px 0;
    border-bottom: 1px solid #fecaca;
    font-size: 0.9rem;
  }
  .alertas li:last-child {
    border-bottom: none;
  }
  #chart-container.loading::after {
    content: 'Cargando gráfico...';
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #6b7280;
  }
</style>

<div class="container py-4">
  <!-- Toast éxito -->
  <div id="toast-ok" style="position:fixed; right:16px; bottom:16px; background:#16a34a; color:#fff; padding:12px 16px; border-radius:10px; box-shadow:0 8px 20px rgba(0,0,0,0.15); display:none; z-index:9999;">
    <i class="fa-solid fa-check me-2"></i> Seguimiento guardado correctamente
  </div>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fa-solid fa-user-shield text-success me-2"></i>Asistente</h3>
  </div>

  <!-- Accesos rápidos -->
  <div class="row g-2 mb-3 filters">
    <div class="col-12 col-md-8">
      <div class="input-group">
        <span class="input-group-text"><i class="fa-solid fa-school"></i></span>
        <select id="filtroColegio" class="form-select">
          <option value="">Todos los colegios</option>
        </select>
        <span class="input-group-text"><i class="fa-regular fa-calendar"></i></span>
        <input type="date" id="desde" class="form-control" />
        <input type="date" id="hasta" class="form-control" />
        <button id="btnReporteColegio" class="btn btn-primary"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
        <button id="btnReporteExcel" class="btn btn-success"><i class="fa-solid fa-file-excel me-1"></i>Excel</button>
      </div>
    </div>
    <div class="col-12 col-md-4 d-flex gap-2">
      <a class="btn btn-outline-primary w-50" href="?page=calendario"><i class="fa-regular fa-calendar-days me-1"></i>Calendario</a>
      <a class="btn btn-outline-secondary w-50" href="?page=reportes"><i class="fa-solid fa-chart-column me-1"></i>Reportes</a>
    </div>
  </div>

  <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="metric text-center">
          <h6>Clases Hoy</h6>
          <div id="m_total" class="val">—</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="metric text-center">
          <h6>En Curso</h6>
          <div id="m_curso" class="val">—</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="metric text-center">
          <h6>Ausencias</h6>
          <div id="m_faltas" class="val">—</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="metric text-center">
          <h6>Próxima clase</h6>
          <div id="m_proxima" class="val" style="font-size:1rem">—</div>
        </div>
      </div>
  </div>

  <div class="d-flex align-items-center mb-2">
      <h5 class="mb-0 me-2"><i class="fa-solid fa-user-minus text-danger me-2"></i>Ausentes de Hoy</h5>
      <button id="btnExport" class="btn btn-sm btn-outline-primary ms-auto"><i class="fa-solid fa-file-arrow-down me-1"></i>Exportar CSV</button>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" id="tablaAusentes">
          <thead>
            <tr>
              <th>Estudiante</th>
              <th>Ficha</th>
              <th>Fecha</th>
              <th style="width:120px">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr><td colspan="4" class="text-center py-4">Cargando…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Bloque: Datos del colegio seleccionado -->
<div class="container py-0">
  <div class="d-flex align-items-center mb-2 mt-4">
      <h5 class="mb-0 me-2"><i class="fa-solid fa-chart-column text-primary me-2"></i>Información del colegio</h5>
      <small class="text-muted">Haz clic en "Ver" en la tabla de colegios para cargar los datos</small>
  </div>

  <!-- Layout estilo paneles: izquierda info, centro gráfico -->
  <div class="row g-3 mt-2">
    <div class="col-12 col-lg-4">
      <div class="card mb-3">
        <div class="card-header bg-light"><strong>Facilitadores / Instructores</strong></div>
        <div class="card-body div1" id="panelInstructores">
          <div class="text-muted">Seleccione un colegio para ver facilitadores.</div>
        </div>
      </div>
      <div class="card">
        <div class="card-header bg-light"><strong>Aprendices</strong></div>
        <div class="card-body div2" id="panelAprendices">
          <div class="text-muted">Seleccione un colegio para ver aprendices.</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-8">
      <div class="card h-100">
        <div class="card-header bg-light"><strong>Estadísticas de asistencia</strong></div>
        <div class="card-body">
          <div id="chart-container" style="width:100%; height:400px;">
            <div class="text-center text-muted mb-2">Seleccione un colegio para ver estadísticas</div>
          </div>
          <div class="div3 mt-3"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Colegios gestionados -->
  <div class="d-flex align-items-center mb-2 mt-4">
    <h5 class="mb-0 me-2"><i class="fa-solid fa-building-columns text-primary me-2"></i>Colegios gestionados</h5>
  </div>
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" id="tablaColegiosListado">
          <thead>
            <tr>
              <th style="width:90px">ID</th>
              <th>Nombre</th>
              <th>Tipo</th>
              <th>Departamento</th>
              <th>Municipio</th>
              <th style="width:120px">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr><td colspan="6" class="text-center py-4">Cargando…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Select2 JS (dep. jQuery) -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- jQuery UI para datepicker -->
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<!-- ECharts para gráficos -->
<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
<!-- Dashboard JS -->
<script src="js/dashboard.js"></script>

<script>
async function cargarColegios() {
  try {
    const r = await fetch('?page=asistente_colegios', { credentials:'same-origin' });
    const j = await r.json();
    if (!j.success) throw new Error(j.error||'Error');
    const sel = document.getElementById('filtroColegio');
    sel.innerHTML = '<option value="">Todos los colegios</option>' +
      (j.data||[]).map(c=>`<option value="${c.id}">${c.nombre}</option>`).join('');
    // Actualizar Select2 si está inicializado
    if (window.jQuery && $.fn.select2) {
      $('#filtroColegio').trigger('change.select2');
    }
    // Auto-seleccionar primer colegio para mostrar estadísticas inline de inmediato
    if ((j.data||[]).length > 0) {
      sel.value = String(j.data[0].id);
      if (window.jQuery && $.fn.select2) { $('#filtroColegio').trigger('change.select2'); }
      // Disparar carga de KPIs y ausentes
      cargarResumen();
      cargarAusentes();
    }
  } catch(e){ console.error(e); }
}

function setFechasPorDefecto() {
  const d = new Date();
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth()+1).padStart(2,'0');
  const dd = String(d.getDate()).padStart(2,'0');
  const ultimoDia = new Date(yyyy, d.getMonth()+1, 0).getDate();
  document.getElementById('desde').value = `${yyyy}-${mm}-01`;
  document.getElementById('hasta').value = `${yyyy}-${mm}-${String(ultimoDia).padStart(2,'0')}`;
}

document.getElementById('btnReporteColegio').addEventListener('click', ()=>{
  const colegio = document.getElementById('filtroColegio').value || '';
  const desde = document.getElementById('desde').value || '';
  const hasta = document.getElementById('hasta').value || '';
  const url = new URL(window.location.href.split('?')[0], window.location.origin);
  url.searchParams.set('page','asistente_reporte_csv');
  if (colegio) url.searchParams.set('colegio_id', colegio);
  if (desde) url.searchParams.set('desde', desde);
  if (hasta) url.searchParams.set('hasta', hasta);
  window.open(url.toString(),'_blank');
});
async function cargarResumen() {
  try {
    const colegio = document.getElementById('filtroColegio').value || '';
    const url = new URL(window.location.href.split('?')[0], window.location.origin);
    url.searchParams.set('page','asistente_resumen');
    if (colegio) url.searchParams.set('colegio_id', colegio);
    const r = await fetch(url.toString(), { credentials:'same-origin' });
    const j = await r.json();
    if (!j.success) throw new Error(j.error||'Error');
    document.getElementById('m_total').textContent = j.total_clases;
    document.getElementById('m_curso').textContent = j.en_curso;
    document.getElementById('m_faltas').textContent = j.faltas;
    if (j.proxima_clase) {
      const dt = new Date(j.proxima_clase.fecha_inicio.replace(' ','T'));
      const hh = String(dt.getHours()).padStart(2,'0');
      const mm = String(dt.getMinutes()).padStart(2,'0');
      document.getElementById('m_proxima').textContent = `${dt.toLocaleDateString()} ${hh}:${mm}`;
    } else {
      document.getElementById('m_proxima').textContent = '—';
    }
  } catch (e) {
    console.error(e);
  }
}

async function cargarAusentes() {
  const tb = document.querySelector('#tablaAusentes tbody');
  try {
    tb.innerHTML = '<tr><td colspan="4" class="text-center py-4">Cargando…</td></tr>';
    const colegio = document.getElementById('filtroColegio').value || '';
    const url = new URL(window.location.href.split('?')[0], window.location.origin);
    url.searchParams.set('page','asistente_ausentes');
    if (colegio) url.searchParams.set('colegio_id', colegio);
    const r = await fetch(url.toString(), { credentials:'same-origin' });
    const j = await r.json();
    if (!j.success) throw new Error(j.error||'Error');
    const rows = j.data || [];
    if (rows.length === 0) {
      tb.innerHTML = '<tr><td colspan="4" class="text-center py-4">No hay ausentes hoy.</td></tr>';
      return;
    }
    tb.innerHTML = rows.map(x => {
      const hoy = new Date().toISOString().split('T')[0];
      const url = `/?page=seguimiento_ausencia_iniciar&ficha_id=${encodeURIComponent(x.ficha_id)}&estudiante_id=${encodeURIComponent(x.estudiante_id)}&fecha=${encodeURIComponent(hoy)}`;
      return `
      <tr data-estudiante="${x.estudiante_id}" data-ficha="${x.ficha_id}">
        <td>${(x.nombres||'')+' '+(x.apellidos||'')}</td>
        <td>${x.ficha_nombre||''}</td>
        <td>${new Date().toLocaleDateString()}</td>
        <td>
          <a class="btn btn-sm btn-primary" href="${url}"><i class="fa-solid fa-play me-1"></i>Iniciar proceso</a>
        </td>
      </tr>`;
    }).join('');
  } catch (e) {
    console.error(e);
    tb.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">Error al cargar</td></tr>';
  }
}

async function notificar(estudiante_id, ficha_id) {
  try {
    const fecha = new Date().toISOString().split('T')[0];
    const r = await fetch('?page=asistente_notificar', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ estudiante_id, ficha_id, fecha }),
      credentials:'same-origin'
    });
    const j = await r.json();
    if (!j.success) throw new Error(j.error||'Error');
    alert('Notificación enviada');
  } catch (e) {
    alert('No se pudo notificar: '+(e.message||''));
  }
}

function exportCSV() {
  const rows = Array.from(document.querySelectorAll('#tablaAusentes tr'))
    .map(tr => Array.from(tr.children).map(td => '"'+td.innerText.replaceAll('"','""')+'"').join(','))
    .join('\n');
  const blob = new Blob(["\ufeff"+rows], { type:'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = 'ausentes_hoy.csv'; a.click();
  URL.revokeObjectURL(url);
}

document.getElementById('btnExport').addEventListener('click', exportCSV);
document.getElementById('filtroColegio').addEventListener('change', ()=>{ cargarResumen(); cargarAusentes(); });

cargarResumen();
cargarAusentes();
setFechasPorDefecto();
cargarColegios();
setInterval(cargarResumen, 60000);
// cargar listado de colegios inferior
renderColegiosListado();


// Listado inferior de colegios
async function renderColegiosListado() {
  const tb = document.querySelector('#tablaColegiosListado tbody');
  if (!tb) return;
  tb.innerHTML = '<tr><td colspan="6" class="text-center py-4">Cargando…</td></tr>';
  try {
    const r = await fetch('?page=asistente_colegios', { credentials:'same-origin' });
    const j = await r.json();
    if (!j.success) throw new Error(j.error||'Error');
    const rows = j.data || [];
    tb.innerHTML = rows.length ? rows.map(c=>`
      <tr>
        <td>${c.id||''}</td>
        <td>${c.nombre||''}</td>
        <td>${c.tipo_institucion||''}</td>
        <td>${c.departamento||''}</td>
        <td>${c.municipio||''}</td>
        <td><button class="btn btn-sm btn-primary btn-ver-colegio" data-id="${c.id}"><i class="fa-solid fa-eye me-1"></i>Ver</button></td>
      </tr>
    `).join('') : '<tr><td colspan="6" class="text-center py-4">Sin colegios</td></tr>';
  } catch (e) {
    console.error(e);
    tb.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Error</td></tr>';
  }
}

// Inicializar Select2 para el selector de colegio
document.addEventListener('DOMContentLoaded', function(){
  if (window.jQuery && $.fn.select2) {
    $('#filtroColegio').select2({
      placeholder: 'Todos los colegios',
      allowClear: true,
      width: 'resolve',
      language: {
        noResults: () => 'Sin resultados',
        searching: () => 'Buscando…'
      }
    });
  }
  // Inicializar Datepicker con locale ES y estilo
  if (window.jQuery && $.fn.datepicker) {
    $.datepicker.regional['es'] = {
      closeText: 'Cerrar', prevText: 'Anterior', nextText: 'Siguiente', currentText: 'Hoy',
      monthNames: ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'],
      monthNamesShort: ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'],
      dayNames: ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'],
      dayNamesShort: ['dom','lun','mar','mié','jue','vie','sáb'],
      dayNamesMin: ['D','L','M','X','J','V','S'], weekHeader: 'Sm', dateFormat: 'yy-mm-dd', firstDay: 1,
      isRTL: false, showMonthAfterYear: false, yearSuffix: ''
    };
    $.datepicker.setDefaults($.datepicker.regional['es']);
    $('#desde').datepicker({
      changeMonth: true,
      changeYear: true,
      showButtonPanel: true,
      onSelect: function(){ cargarResumen(); cargarAusentes(); }
    });
    $('#hasta').datepicker({
      changeMonth: true,
      changeYear: true,
      showButtonPanel: true,
      onSelect: function(){ cargarResumen(); cargarAusentes(); }
    });
  }
  // Toast por querystring
  try {
    const qp = new URLSearchParams(window.location.search);
    if (qp.get('msg') === 'seguimiento_ok') {
      const t = document.getElementById('toast-ok');
      if (t) {
        t.style.display = 'block';
        t.style.opacity = '0';
        t.style.transition = 'opacity .2s ease, transform .2s ease';
        requestAnimationFrame(()=>{ t.style.opacity='1'; t.style.transform='translateY(-4px)';});
        setTimeout(()=>{
          t.style.opacity='0';
          t.style.transform='translateY(0)';
          setTimeout(()=>{ t.style.display='none'; }, 250);
        }, 2500);
      }
      // limpiar param de la URL sin recargar
      try { const url = new URL(window.location.href); url.searchParams.delete('msg'); window.history.replaceState({}, '', url.toString()); } catch(_) {}
    }
  } catch(_) {}
});
</script>
<?php include __DIR__ . '/../Componentes/footer.php'; ?>
