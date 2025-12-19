<?php
start_secure_session();
require_login();
require_role([1, 4]); // 1 para Admin, 4 para Asistente
include __DIR__ . '/../Componentes/encabezado.php';
$usuario = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Asistente</title>
    <link rel="stylesheet" href="/css/Asistente/dashboard.css?v=20251023-1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<body>
<div id="dashboard-normal" class="dashboard-panel">
    <div class="parent">

        <!-- Bienvenida -->
        <div class="div9">
            <?php 
              $n1 = trim((string)($usuario['nombres'] ?? ''));
              $a1 = trim((string)($usuario['apellidos'] ?? ''));
              $nombre_usuario = htmlspecialchars((explode(' ', $n1)[0] ?? '').' '.(explode(' ', $a1)[0] ?? ''));
              $genero = strtolower(trim((string)($usuario['genero'] ?? '')));
              if (in_array($genero, ['m','masculino','hombre'], true)) {
                $bienvenida = 'Bienvenido';
              } elseif (in_array($genero, ['f','femenino','mujer'], true)) {
                $bienvenida = 'Bienvenida';
              } else {
                $bienvenida = 'Bienvenid@';
              }
              $h = (int)date('H');
              $saludo = $h < 12 ? '¡Buenos días' : ($h < 18 ? '¡Buenas tardes' : '¡Buenas noches');
            ?>
            <p><strong><?= $bienvenida ?></strong> <?= $nombre_usuario ?></p>
            <div class="welcome-center">
                <span id="greeting-text"><?= $saludo ?>, <?= htmlspecialchars($usuario['nombres']) ?>!</span>
                <svg class="hand-outline" width="32" height="32" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
                    <path d="M14 22 V9 a2 2 0 1 1 4 0v11 M18 21 V7 a2 2 0 1 1 4 0v16 M22 21 V8 a2 2 0 1 1 4 0v15 M26 23 V12 a2 2 0 1 1 4 0v14 M30 26 V16 a2 2 0 1 1 4 0v13 C34 36 28 42 21 42 C15 42 12 37 12 32 V27" />
                    <path d="M36 10 l4 -4 M38 16 l6 -2" class="ho-accent" />
                </svg>
            </div>
            <p><strong>Rol:</strong> Asistente</p>
        </div>


        <!-- VISTA PRINCIPAL -->
        <div class="indicaciones-column view-main">
            <div id="facilitadores-container" class="div1"><p>Selecciona un colegio para ver sus Facilitadores.</p></div>
            <div id="aprendices-container" class="div2"><p>Selecciona un colegio para ver sus Aprendices.</p></div>
        </div>

        <div class="div3 view-main">
            <h3>Estadísticas</h3>
            <div id="chart-container"></div>
        </div>
        <div class="contadores-column view-main">
            <div class="div5"><h3>Clases Hoy</h3><p id="m_total">—</p></div>
            <div class="div6"><h3>En Curso</h3><p id="m_curso">—</p></div>
            <div class="div7"><h3>Ausencias</h3><p id="m_faltas">—</p></div>
            <div class="div8"><h3>Próxima Clase</h3><p id="m_proxima" style="font-size:1.4rem; padding-top:8px;">—</p></div>
            <div class="div10">
                <h3>Descargar Reporte Semana</h3>
                <button class="btn" data-bs-toggle="modal" data-bs-target="#modalReportesPDF" style="margin-top: 8px;">Descargar</button>
            </div>
        </div>
        <div class="div4 view-main">
            <h3 id="tabla-title">Colegios Gestionados</h3>
            <div id="tabla-controls" style="display:flex; gap:10px; align-items:center; justify-content:space-between; margin:10px 0 14px;">
                <div style="display:flex; align-items:center; gap:8px; flex:0 0 auto;">
                    <input id="tabla-search" class="input-buscar" type="text" placeholder="Buscar colegio..." style="width:260px; max-width: 320px;">
                    <button class="btn-icon" id="tabla-search-btn" aria-label="Buscar" type="button">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><line x1="20" y1="20" x2="16.5" y2="16.5" stroke="currentColor" stroke-width="2" /></svg>
                    </button>
                </div>
                <div id="tabla-pager" style="display:flex; flex-wrap:wrap; gap:6px; align-items:center; justify-content:flex-end; min-height:34px;"></div>
            </div>
            <table id="tabla-colegios">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Departamento</th>
                        <th>Municipio</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaColegiosListado">
                    <tr><td colspan="6" class="text-center py-4">Cargando…</td></tr>
                </tbody>
            </table>
        </div>

        <!-- VISTA SECUNDARIA -->
        <div class="indicaciones-column view-secondary">
            <!-- Lista base de ausentes -->
            <div class="div1">
                <h3 class="block-title">Lista de inasistencias del día</h3>
                <div class="list-scroll">
                    <table class="table table-sm table-hover align-middle mb-0" id="tablaAusentes">
                        <tbody>
                            <tr><td class="text-center py-4">Cargando…</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="ausentes-pager" style="display:flex; flex-wrap:wrap; gap:6px; align-items:center; justify-content:center; min-height:30px; margin-top:4px;"></div>
            </div>

            <!-- Historial: No asistieron (pendientes de proceso) -->
            <div class="div2">
                <h3 class="block-title">Pendientes por proceso</h3>
                <div class="list-scroll">
                    <table class="table table-sm table-hover align-middle mb-0" id="tablaPendientesProceso">
                        <tbody>
                            <tr><td class="text-center py-4">Sin datos</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="pend-pager" style="display:flex; flex-wrap:wrap; gap:6px; align-items:center; justify-content:center; min-height:30px; margin-top:4px;"></div>
            </div>

            <!-- Historial: No asistieron (proceso realizado) -->
            <div class="div3">
                <h3 class="block-title">Proceso realizado</h3>
                <div class="proc-filters">
                    <div class="proc-filter-search">
                        <input id="proc-buscar-nombre" class="input-buscar proc-filter-nombre" type="text" placeholder="Buscar estudiante...">
                        <button class="btn-icon" id="proc-buscar-btn" aria-label="Buscar" type="button">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><line x1="20" y1="20" x2="16.5" y2="16.5" stroke="currentColor" stroke-width="2" /></svg>
                        </button>
                    </div>
                    <input id="proc-fecha-falta" type="date" class="proc-filter-fecha">
                </div>
                <div class="list-scroll">
                    <table class="table table-sm table-hover align-middle mb-0" id="tablaProcesados">
                        <tbody>
                            <tr><td class="text-center py-4">Sin datos</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="proc-pager" style="display:flex; flex-wrap:wrap; gap:6px; align-items:center; justify-content:center; min-height:30px; margin-top:4px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Reporte PDF -->
<div class="modal fade" id="modalReportesPDF" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Generar Reporte de Asistencias </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="selectColegioPDF" class="form-label">Seleccione Colegio</label>
          <select id="selectColegioPDF" class="form-select"></select>
        </div>
        <div class="mb-3">
          <label class="form-label">Seleccione Ficha(s)</label>
          <div id="fichasContainerPDF"></div>
          <div class="form-check mt-2">
            <input type="checkbox" class="form-check-input" id="checkAllFichasPDF">
            <label for="checkAllFichasPDF" class="form-check-label">Seleccionar todas</label>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label for="weekStartPDF" class="form-label">Días (selección múltiple)</label>
            <input type="text" id="weekStartPDF" class="form-control" placeholder="Selecciona uno o varios días">
          </div>
          <div class="col-md-6" style="display:none">
            <label for="weekEndPDF" class="form-label">Semana (fin)</label>
            <input type="text" id="weekEndPDF" class="form-control" placeholder="">
          </div>
        </div>
        
      </div>
      <div class="modal-footer">
        <button type="button" id="btnDownloadPDF" class="btn btn-pdf">Descargar PDF</button>
        <button type="button" id="btnDownloadExcel" class="btn btn-excel">Descargar Excel</button>
      </div>
    </div>
  </div>
</div>


    <?php include __DIR__ . '/../Componentes/footer.php'; ?>

    <!-- Librerías JS -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
    <script src="/js/Componentes/encabezado.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

    <script src="/js/Asistente/dashboard.js"></script>
    <script src="/js/Asistente/view-toggle.js"></script>
    <script>
      (function(){
        const nativeAlert = window.alert;
        window.alert = function(msg){
          try {
            if (typeof mostrarNotificacionTemporal === 'function') {
              const m = String(msg||'');
              const type = /error|falló|fallo|no pudo|invalid/i.test(m) ? 'error' : ( /éxito|exito|ok|listo/i.test(m) ? 'success' : 'info');
              mostrarNotificacionTemporal(m, type);
            } else {
              nativeAlert(msg);
            }
          } catch(_){ nativeAlert(msg); }
        };
      })();
    </script>
    <script>
      // Saludo dinámico (alineado con Admin), usando zona horaria de Bogotá
      (function(){
        const el = document.getElementById('greeting-text');
        if (!el) return;
        const nombre = <?= json_encode(explode(' ', trim($usuario['nombres']))[0] ?? '') ?>;
        const RETURN_THRESHOLD = 120000; // 2 min
        const RETURN_GREETINGS = [
          '¡Hola de nuevo!',
          '¡Qué bueno verte, ' + nombre + '!'
          , '¡Listo para continuar!',
          '¡Seguimos!'
        ];
        function saludoPorHora(){
          try {
            const tz = 'America/Bogota';
            const parts = new Intl.DateTimeFormat('es-CO', { hour: 'numeric', hour12: false, timeZone: tz }).formatToParts(new Date());
            const hourPart = parts.find(p=>p.type==='hour');
            const h = hourPart ? parseInt(hourPart.value,10) : new Date().getHours();
            if (h < 12) return '¡Buenos días!';
            if (h < 19) return '¡Buenas tardes!';
            return '¡Buenas noches!';
          } catch(_) {
            const h = new Date().getHours();
            if (h < 12) return '¡Buenos días!';
            if (h < 19) return '¡Buenas tardes!';
            return '¡Buenas noches!';
          }
        }
        function siguienteSaludo(){
          let idx = Number(localStorage.getItem('asist_greetIdx')||0);
          const txt = RETURN_GREETINGS[idx % RETURN_GREETINGS.length];
          localStorage.setItem('asist_greetIdx', String((idx+1) % RETURN_GREETINGS.length));
          return txt;
        }
        function setSaludoInicial(){
          const last = Number(localStorage.getItem('asist_lastActiveTs')||0);
          const now = Date.now();
          if (last && (now - last) > RETURN_THRESHOLD) el.textContent = siguienteSaludo();
          else el.textContent = saludoPorHora();
          localStorage.setItem('asist_lastActiveTs', String(now));
        }
        function handleVisibility(){
          if (!document.hidden){
            const last = Number(localStorage.getItem('asist_lastActiveTs')||0);
            const now = Date.now();
            if (last && (now - last) > RETURN_THRESHOLD) el.textContent = siguienteSaludo();
            localStorage.setItem('asist_lastActiveTs', String(now));
          }
        }
        setSaludoInicial();
        document.addEventListener('visibilitychange', handleVisibility);
        window.addEventListener('beforeunload', () => localStorage.setItem('asist_lastActiveTs', String(Date.now())));
      })();
    </script>
    <!-- Lógica del Dashboard del Asistente -->
    <script>
    async function cargarResumen() {
        try {
            const r = await fetch('?page=asistente_resumen');
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const j = await r.json();
            if (!j || j.success === false) throw new Error(j && j.error ? j.error : 'Error');

            const total = Number(j.total_clases) || 0;
            const curso = Number(j.en_curso) || 0;
            const faltas = Number(j.faltas) || 0;
            document.getElementById('m_total').textContent = String(total);
            document.getElementById('m_curso').textContent = String(curso);
            document.getElementById('m_faltas').textContent = String(faltas);

            const prox = j.proxima_clase;
            if (prox && (prox.fecha_inicio || prox.fecha)) {
                const raw = prox.fecha_inicio || prox.fecha;
                const iso = typeof raw === 'string' ? raw.replace(' ', 'T') : raw;
                const dt = new Date(iso);
                if (!isNaN(dt.getTime())) {
                    const fecha = dt.toLocaleDateString();
                    const hora = String(dt.getHours()).padStart(2,'0') + ':' + String(dt.getMinutes()).padStart(2,'0');
                    document.getElementById('m_proxima').textContent = fecha + ' ' + hora;
                } else {
                    document.getElementById('m_proxima').textContent = '—';
                }
            } else {
                document.getElementById('m_proxima').textContent = '—';
            }
        } catch (e) {
            console.error('asistente_resumen', e);
            document.getElementById('m_total').textContent = '0';
            document.getElementById('m_curso').textContent = '0';
            document.getElementById('m_faltas').textContent = '0';
            document.getElementById('m_proxima').textContent = '—';
        }
    }

    // Mostrar detalle de proceso en modal Bootstrap
    let modalDetalleProceso = null;
    function abrirModalDetalleProceso(procesoId) {
        const modalEl = document.getElementById('modalDetalleProceso');
        const bodyEl  = document.getElementById('detalleProcesoBody');
        const titleEl = document.getElementById('detalleProcesoTitle');
        if (!modalEl || !bodyEl) return;

        if (!procesoId) {
            // Si no viene ID, mostrar mensaje genérico pero abrir modal
            if (titleEl) titleEl.textContent = 'Detalle del proceso';
            bodyEl.innerHTML = '<div style="padding:12px;">No hay información detallada disponible para este proceso.</div>';
        } else {
            bodyEl.innerHTML = '<div style="padding:12px;">Cargando...</div>';
            if (titleEl) titleEl.textContent = 'Detalle del proceso';

            fetch(`/?page=asistente&action=detalle_proceso&proceso_id=${encodeURIComponent(procesoId)}`)
              .then(r => r.json())
              .then(j => {
                  if (!j || j.success === false || !j.data) {
                      const msg = (j && j.error) ? j.error : 'No se pudo cargar el detalle.';
                      bodyEl.innerHTML = `<div style="padding:12px; color:#b91c1c;">${msg}</div>`;
                      return;
                  }
                  const d = j.data;
                  const nombre = ((d.nombres||'') + ' ' + (d.apellidos||'')).trim();
                  if (titleEl && nombre) {
                      titleEl.textContent = 'Proceso de ' + nombre;
                  }
                  const ficha = (d.ficha_numero ? d.ficha_numero : (d.ficha_nombre||''));
                  const fechaProc = (d.fecha_proceso || '').split('T')[0] || '';
                  const via = d.via || '—';
                  const contacto = d.contacto || '—';
                  const tel = d.telefono || '—';
                  const motivo = d.motivo || '—';
                  const obs = d.observaciones || '';

                  bodyEl.innerHTML = `
                <div style="font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                  <div style="margin-bottom:12px;">
                    <div style="font-size:1.1rem;font-weight:700;">${nombre || 'Aprendiz'}</div>
                    <div style="color:#64748b;">Ficha: ${ficha || '—'}</div>
                  </div>
                  <div class="row" style="row-gap:10px;">
                    <div class="col-md-6">
                      <strong>Fecha del proceso:</strong><br>
                      <span>${fechaProc || '—'}</span>
                    </div>
                    <div class="col-md-6">
                      <strong>Vía de contacto:</strong><br>
                      <span>${via}</span>
                    </div>
                    <div class="col-md-6" style="margin-top:10px;">
                      <strong>Contacto:</strong><br>
                      <span>${contacto}</span>
                    </div>
                    <div class="col-md-6" style="margin-top:10px;">
                      <strong>Teléfono:</strong><br>
                      <span>${tel}</span>
                    </div>
                  </div>
                  <div style="margin-top:16px;">
                    <strong>Motivo informado:</strong>
                    <div>${motivo}</div>
                  </div>
                  <div style="margin-top:12px;">
                    <strong>Observaciones:</strong>
                    <div style="white-space:pre-wrap;">${obs || 'Sin observaciones registradas.'}</div>
                  </div>
                </div>`;
              })
              .catch(() => {
                  bodyEl.innerHTML = '<div style="padding:12px; color:#b91c1c;">No se pudo cargar el detalle.</div>';
              });
        }

        try {
            const bs = window.bootstrap || window.Bootstrap || null;
            if (bs && bs.Modal) {
                if (!modalDetalleProceso) {
                    modalDetalleProceso = new bs.Modal(modalEl);
                }
                modalDetalleProceso.show();
            }
        } catch (_) { /* noop */ }
    }
    // Historial: Pendientes de proceso y Proceso realizado (scope global)
    let historialProcesadosRaw = [];
    let historialProcesadosFiltrado = [];
    let procCurrentPage = 1;
    const procItemsPerPage = 7;

// Ausentes: Lista del día (5 por página)
let ausentesRaw = [];
let ausentesCurrentPage = 1;
const ausentesItemsPerPage = 5;

// Pendientes por proceso (5 por página)
let pendientesRaw = [];
let pendCurrentPage = 1;
const pendItemsPerPage = 5;

    async function cargarHistorialPendientes() {
        const tb = document.querySelector('#tablaPendientesProceso tbody');
        if (!tb) return;
        try {
            tb.innerHTML = '<tr><td class="text-center py-4">Cargando…</td></tr>';
            const r = await fetch('?page=asistente&action=historial_pendientes');
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const j = await r.json();
            try { console.log('asistente.historial_pendientes JSON:', j); } catch(_){ }
            pendientesRaw = (j && (j.data || j.rows || [])) || [];
            renderPendientesPagina(1);
        } catch (e) {
            console.error('historial pendientes', e);
            tb.innerHTML = '<tr><td class="text-center py-4">Sin datos</td></tr>';
        }
    }

    async function cargarHistorialProcesados() {
        const tb = document.querySelector('#tablaProcesados tbody');
        if (!tb) return;
        try {
            tb.innerHTML = '<tr><td class="text-center py-4">Cargando…</td></tr>';
            const r = await fetch('?page=asistente&action=historial_procesados');
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const j = await r.json();
            try { console.log('asistente.historial_procesados JSON:', j); } catch(_){ }
            historialProcesadosRaw = (j && (j.data || j.rows || [])) || [];
            renderHistorialProcesadosFiltrado();
        } catch (e) {
            console.error('historial procesados', e);
            if (tb) {
                tb.innerHTML = '<tr><td class="text-center py-4">Sin datos</td></tr>';
            }
        }
    }

    function renderHistorialProcesadosFiltrado() {
        const tb = document.querySelector('#tablaProcesados tbody');
        const pager = document.getElementById('proc-pager');
        if (!tb) return;
        if (!Array.isArray(historialProcesadosRaw) || historialProcesadosRaw.length === 0) {
            tb.innerHTML = '<tr><td class="text-center py-4">Sin datos</td></tr>';
            if (pager) pager.innerHTML = '';
            return;
        }
        const inpNombre = document.getElementById('proc-buscar-nombre');
        const inpFecha  = document.getElementById('proc-fecha-falta');
        const q = (inpNombre && inpNombre.value ? inpNombre.value : '').toString().toLowerCase().trim();
        const fExact = (inpFecha && inpFecha.value ? inpFecha.value : '').toString().trim();

        let data = historialProcesadosRaw.slice();
        if (q) {
            data = data.filter(function(x){
                const nom = (((x.nombres||'') + ' ' + (x.apellidos||'')).toString().toLowerCase());
                return nom.indexOf(q) !== -1;
            });
        }
        if (fExact) {
            data = data.filter(function(x){
                const fInas = ((x.fecha_inasistencia || x.fecha || '') + '').split('T')[0] || '';
                return fInas === fExact;
            });
        }

        historialProcesadosFiltrado = data;
        procCurrentPage = 1;
        renderHistorialProcesadosPagina(1);
    }

    function renderHistorialProcesadosPagina(page) {
        const tb = document.querySelector('#tablaProcesados tbody');
        const pager = document.getElementById('proc-pager');
        if (!tb) return;

        const data = Array.isArray(historialProcesadosFiltrado) ? historialProcesadosFiltrado : [];
        if (data.length === 0) {
            tb.innerHTML = '<tr><td class="text-center py-4">Sin resultados</td></tr>';
            if (pager) pager.innerHTML = '';
            return;
        }

        const totalPages = Math.max(1, Math.ceil(data.length / procItemsPerPage));
        if (!page || page < 1) page = 1;
        if (page > totalPages) page = totalPages;
        procCurrentPage = page;

        const start = (page - 1) * procItemsPerPage;
        const end = start + procItemsPerPage;
        const pageData = data.slice(start, end);

        tb.innerHTML = pageData.map(function(x){
            const nombre = ((x.nombres||'') + ' ' + (x.apellidos||'')).trim();
            const fichaNum = x.ficha_numero || x.ficha || '';
            const fichaNombre = x.ficha_nombre || '';
            const fichaLabel = fichaNum ? (fichaNombre ? (fichaNum + ' · ' + fichaNombre) : fichaNum) : (fichaNombre || '');
            const fechaInas = (x.fecha_inasistencia || x.fecha || '').split('T')[0] || '';
            const fechaProc = (x.fecha_proceso || x.fecha || '').split('T')[0] || '';
            return '<tr>'
              + '<td><strong>' + nombre + '</strong><br><small>' + fichaLabel + '</small></td>'
              + '<td><small>No asistió: ' + (fechaInas || '—') + '</small></td>'
              + '<td><small>Proceso: ' + (fechaProc || '—') + '</small></td>'
              + '<td style="text-align:right"><button type="button" class="btn btn-sm btn-secondary btn-detalle-proceso" data-proceso-id="' + (x.proceso_id||'') + '">Detalles</button></td>'
              + '</tr>';
        }).join('');

        if (pager) {
            pager.innerHTML = '';
            const createButton = function(label, targetPage, isDisabled, isActive) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.innerHTML = label;
                btn.disabled = !!isDisabled;
                btn.style.cssText = 'min-width:28px;height:28px;border:1px solid #d1d5db;border-radius:10px;background:#fff;cursor:pointer;padding:0 8px;margin:0 2px;font-size:.8rem;';
                if (isActive) {
                    btn.style.background = '#e5f5e8';
                    btn.style.borderColor = '#39A900';
                    btn.style.fontWeight = '700';
                }
                if (!isDisabled) {
                    btn.addEventListener('click', function(){
                        renderHistorialProcesadosPagina(targetPage);
                    });
                }
                return btn;
            };

            pager.appendChild(createButton('&laquo;', procCurrentPage - 1, procCurrentPage === 1, false));
            for (let i = 1; i <= totalPages; i++) {
                pager.appendChild(createButton(String(i), i, false, i === procCurrentPage));
            }
            pager.appendChild(createButton('&raquo;', procCurrentPage + 1, procCurrentPage === totalPages, false));
        }
    }

    async function cargarAusentes() {
        const tb = document.querySelector('#tablaAusentes tbody');
        if (!tb) return;
        try {
            tb.innerHTML = '<tr><td class="text-center py-4">Cargando…</td></tr>';
            const r = await fetch('?page=asistente_ausentes');
            const j = await r.json();
            try { console.log('asistente.ausentes_hoy JSON:', j); } catch(_){ }
            if (!j.success) throw new Error(j.error || 'Error');
            ausentesRaw = j.data || [];
            renderAusentesPagina(1);
        } catch (e) {
            console.error(e);
            tb.innerHTML = '<tr><td class="text-center py-4 text-danger">Error</td></tr>';
        }
    }

    function renderAusentesPagina(page) {
        const tb = document.querySelector('#tablaAusentes tbody');
        const pager = document.getElementById('ausentes-pager');
        if (!tb) return;

        if (!Array.isArray(ausentesRaw) || ausentesRaw.length === 0) {
            tb.innerHTML = '<tr><td class="text-center py-4">No hay ausentes hoy.</td></tr>';
            if (pager) pager.innerHTML = '';
            return;
        }

        const totalPages = Math.max(1, Math.ceil(ausentesRaw.length / ausentesItemsPerPage));
        if (!page || page < 1) page = 1;
        if (page > totalPages) page = totalPages;
        ausentesCurrentPage = page;

        const start = (page - 1) * ausentesItemsPerPage;
        const end = start + ausentesItemsPerPage;
        const pageData = ausentesRaw.slice(start, end);

        const today = new Date().toISOString().split('T')[0];

        tb.innerHTML = pageData.map(function(x, idx){
            const rowNum = start + idx + 1;
            const url = '/?page=seguimiento_ausencia_iniciar&ficha_id=' + encodeURIComponent(x.ficha_id||'') + '&estudiante_id=' + encodeURIComponent(x.estudiante_id||'') + '&fecha=' + today;
            return '<tr>'
               + '<td><small>#' + rowNum + ' · No asistió: ' + today + '</small></td>'
               + '<td style="text-align:right"><a class="btn btn-sm btn-primary" href="' + url + '">Iniciar</a></td>'
               + '</tr>';
        }).join('');

        if (pager) {
            pager.innerHTML = '';
            const createButton = function(label, targetPage, isDisabled, isActive){
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.innerHTML = label;
                btn.disabled = !!isDisabled;
                btn.style.cssText = 'min-width:28px;height:28px;border:1px solid #d1d5db;border-radius:10px;background:#fff;cursor:pointer;padding:0 8px;margin:0 2px;font-size:.8rem;';
                if (isActive){
                    btn.style.background = '#e5f5e8';
                    btn.style.borderColor = '#39A900';
                    btn.style.fontWeight = '700';
                }
                if (!isDisabled){
                    btn.addEventListener('click', function(){ renderAusentesPagina(targetPage); });
                }
                return btn;
            };
            pager.appendChild(createButton('&laquo;', ausentesCurrentPage-1, ausentesCurrentPage===1));
            for (let i=1;i<=totalPages;i++){
                pager.appendChild(createButton(String(i), i, false, i===ausentesCurrentPage));
            }
            pager.appendChild(createButton('&raquo;', ausentesCurrentPage+1, ausentesCurrentPage===totalPages));
        }
    }

    function renderPendientesPagina(page) {
        const tb = document.querySelector('#tablaPendientesProceso tbody');
        const pager = document.getElementById('pend-pager');
        if (!tb) return;

        if (!Array.isArray(pendientesRaw) || pendientesRaw.length === 0) {
            tb.innerHTML = '<tr><td class="text-center py-4">Sin datos</td></tr>';
            if (pager) pager.innerHTML = '';
            return;
        }

        const totalPages = Math.max(1, Math.ceil(pendientesRaw.length / pendItemsPerPage));
        if (!page || page < 1) page = 1;
        if (page > totalPages) page = totalPages;
        pendCurrentPage = page;

        const start = (page - 1) * pendItemsPerPage;
        const end = start + pendItemsPerPage;
        const pageData = pendientesRaw.slice(start, end);

        tb.innerHTML = pageData.map(function(x){
            const nombre = ((x.nombres||'') + ' ' + (x.apellidos||'')).trim();
            const ficha = x.ficha_nombre || x.ficha || '';
            const fecha = (x.fecha || '').split('T')[0] || '';
            const url = '/?page=seguimiento_ausencia_iniciar&ficha_id=' + encodeURIComponent(x.ficha_id||'') + '&estudiante_id=' + encodeURIComponent(x.estudiante_id||'') + '&fecha=' + encodeURIComponent(fecha);
            return '<tr>'
               + '<td><strong>' + nombre + '</strong><br><small>' + ficha + '</small></td>'
               + '<td><small>No asistió: ' + (fecha || '') + '</small></td>'
               + '<td style="text-align:right"><a class="btn btn-sm btn-primary" href="' + url + '">Iniciar</a></td>'
               + '</tr>';
        }).join('');

        if (pager) {
            pager.innerHTML = '';
            const createButton = function(label, targetPage, isDisabled, isActive){
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.innerHTML = label;
                btn.disabled = !!isDisabled;
                btn.style.cssText = 'min-width:28px;height:28px;border:1px solid #d1d5db;border-radius:10px;background:#fff;cursor:pointer;padding:0 8px;margin:0 2px;font-size:.8rem;';
                if (isActive){
                    btn.style.background = '#e5f5e8';
                    btn.style.borderColor = '#39A900';
                    btn.style.fontWeight = '700';
                }
                if (!isDisabled){
                    btn.addEventListener('click', function(){ renderPendientesPagina(targetPage); });
                }
                return btn;
            };
            pager.appendChild(createButton('&laquo;', pendCurrentPage-1, pendCurrentPage===1));
            for (let i=1;i<=totalPages;i++){
                pager.appendChild(createButton(String(i), i, false, i===pendCurrentPage));
            }
            pager.appendChild(createButton('&raquo;', pendCurrentPage+1, pendCurrentPage===totalPages));
        }
    }

let allColegios = [];
    let currentPage = 1;
    const itemsPerPage = 5;

    function renderTable(page = 1, searchTerm = '') {
        const tb = document.querySelector('#tablaColegiosListado');
        const pager = document.querySelector('#tabla-pager');
        if (!tb || !pager) return;

        currentPage = page;
        const filteredData = allColegios.filter(c => 
            c.nombre.toLowerCase().includes(searchTerm.toLowerCase()) ||
            (c.municipio && c.municipio.toLowerCase().includes(searchTerm.toLowerCase()))
        );

        const totalPages = Math.max(1, Math.ceil(filteredData.length / itemsPerPage));
        if (page > totalPages) page = totalPages;
        const start = (page - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        const paginatedData = filteredData.slice(start, end);

        // Renderizar filas de la tabla
        if (paginatedData.length > 0) {
            tb.innerHTML = paginatedData.map(function(c){
              return '<tr>'
                + '<td>' + (c.id || '') + '</td>'
                + '<td>' + (c.nombre || '') + '</td>'
                + '<td>' + (c.tipo_institucion || '') + '</td>'
                + '<td>' + (c.departamento || '') + '</td>'
                + '<td>' + (c.municipio || '') + '</td>'
                + '<td><button class="btn-ver-colegio" data-id="' + (c.id||'') + '">Ver</button></td>'
                + '</tr>';
            }).join('');
        } else {
            tb.innerHTML = '<tr><td colspan="6" class="text-center py-4">No se encontraron colegios.</td></tr>';
        }

        // Renderizar paginador
        pager.innerHTML = '';
        const createButton = (label, targetPage, isDisabled = false, isActive = false) => {
            const btn = document.createElement('button');
            btn.innerHTML = label;
            btn.type = 'button';
            btn.disabled = isDisabled;
            btn.style.cssText = 'min-width:34px; height:34px; border:1px solid #d1d5db; border-radius:10px; background:#fff; cursor:pointer; padding:0 10px; margin: 0 2px;';
            if (isActive) {
                btn.style.background = '#e5f5e8';
                btn.style.borderColor = '#39A900';
                btn.style.fontWeight = '700';
            }
            if (!isDisabled) {
                btn.onclick = () => renderTable(targetPage, searchTerm);
            }
            return btn;
        };

        // Botón Anterior
        pager.appendChild(createButton('&laquo;', currentPage - 1, currentPage === 1));

        // Botones de página
        for (let i = 1; i <= totalPages; i++) {
            pager.appendChild(createButton(i, i, false, i === currentPage));
        }

        // Botón Siguiente
        pager.appendChild(createButton('&raquo;', currentPage + 1, currentPage === totalPages));

        // Mensaje si no hay resultados
        if (filteredData.length === 0) {
            const info = document.createElement('div');
            info.textContent = 'Sin resultados';
            info.style.cssText = 'color:#6b7280; margin-left:8px;';
            pager.appendChild(info);
        }
    }

    async function renderColegiosListado() {
        const tb = document.querySelector('#tablaColegiosListado');
        try {
            tb.innerHTML = '<tr><td colspan="6" class="text-center py-4">Cargando…</td></tr>';
            const r = await fetch('?page=asistente_colegios');
            const j = await r.json();
            if (!j.success) throw new Error(j.error || 'Error');
            allColegios = j.data || [];
            renderTable(1, ''); // Renderizar la primera página sin filtro
            return allColegios;
        } catch (e) {
            console.error(e);
            tb.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Error al cargar los colegios.</td></tr>';
            return [];
        }
    }

    document.addEventListener('DOMContentLoaded', async function() {
        cargarResumen();
        cargarAusentes();
        cargarHistorialPendientes();
        cargarHistorialProcesados();
        await renderColegiosListado();
        setInterval(cargarResumen, 60000);

        // Lógica de búsqueda para la tabla de colegios
        const searchInput = document.getElementById('tabla-search');
        const searchButton = document.getElementById('tabla-search-btn');

        function performSearch() {
            const searchTerm = searchInput.value;
            renderTable(1, searchTerm);
        }

        if (searchButton) {
            searchButton.addEventListener('click', performSearch);
        }
        if (searchInput) {
            searchInput.addEventListener('keyup', performSearch);
        }

        // Click en botón Detalles de "Proceso realizado"
        const tablaProcesados = document.getElementById('tablaProcesados');
        if (tablaProcesados) {
            tablaProcesados.addEventListener('click', function(ev){
                const btn = ev.target.closest('.btn-detalle-proceso');
                if (!btn) return;
                const pid = btn.getAttribute('data-proceso-id') || '';
                abrirModalDetalleProceso(pid);
            });
        }

        // Filtros para "Proceso realizado"
        const inpProcNombre = document.getElementById('proc-buscar-nombre');
        const inpProcFecha  = document.getElementById('proc-fecha-falta');
        const btnProcBuscar = document.getElementById('proc-buscar-btn');
        if (inpProcNombre) {
            inpProcNombre.addEventListener('input', renderHistorialProcesadosFiltrado);
        }
        if (inpProcFecha) {
            inpProcFecha.addEventListener('change', renderHistorialProcesadosFiltrado);
        }
        if (btnProcBuscar) {
            btnProcBuscar.addEventListener('click', renderHistorialProcesadosFiltrado);
        }

        // Configuración del modal se maneja en /js/Asistente/dashboard.js
    });
    </script>
</body>
</html>
