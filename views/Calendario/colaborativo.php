<?php
start_secure_session();
require_login();
require_role([1, 4]); // Admin y Asistente
include __DIR__ . '/../Componentes/encabezado.php';
$usuario = $_SESSION['usuario'] ?? null;
?>
<!-- Meta y estilos de la página -->
<meta charset="UTF-8">
<meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<title>Calendario Colaborativo</title>
<!-- FullCalendar CSS: carga desde CDN sin CORS explícito, con fallbacks -->
<link id="fc-css-colab" rel="stylesheet" type="text/css" href="https://unpkg.com/fullcalendar@6.1.10/main.min.css" />
<link rel="stylesheet" href="/css/Calendario/colaborativo.css">
<link rel="stylesheet" href="/css/Calendario/calendario.css">
<script>
  (function(){
    function cssOk(){
      try{
        var ok=false, ss=document.styleSheets||[];
        for(var i=0;i<ss.length;i++){ var h=(ss[i].href||'').toLowerCase(); if(h && (h.indexOf('fullcalendar')>-1 || h.indexOf('main.min.css')>-1)){ ok=true; break; } }
        return ok;
      }catch(_){ return false; }
    }
    function add(href){ var l=document.createElement('link'); l.rel='stylesheet'; l.type='text/css'; l.href=href; document.head.appendChild(l); }
    // Intentar en cascada: unpkg -> cdnjs -> jsdelivr -> local
    setTimeout(function(){
      if(!cssOk()) add('https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.10/main.min.css');
      setTimeout(function(){ if(!cssOk()) add('https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/main.min.css'); }, 700);
      setTimeout(function(){ if(!cssOk()) add('/css/vendor/fullcalendar/main.min.css'); }, 1500);
    }, 300);
  })();
  </script>
  <main class="container" style="max-width:100%;padding:16px;margin:0 auto;">
    <section class="cc-page-title">
      <div class="cc-title-inner"></div>
    </section>
    <div id="calcolab-root" style="display:grid;grid-template-columns:280px 1fr 260px;gap:16px;min-height:70vh;">
      <style>
        #calcolab-root .fc-timegrid .fc-timegrid-axis,
        #calcolab-root .fc .fc-timegrid-axis{ 
          width:auto !important; 
          min-width:0 !important; 
          background:transparent !important; 
          position:static !important; 
          left:auto !important; 
          border-right:0 !important; 
          z-index:auto !important; 
        }
      </style>
      <aside id="calcolab-sidebar">
        <div class="cc-sidebar-header">
          <h3>Instructores - Facilitadores</h3>
        </div>
        <div id="calcolab-instructors" style="color:#6b7280;">No tenemos instructores/facilitadores</div>
      </aside>
      <section>
        <div class="cc-calbar">
          <div class="cc-calbar-left"></div>
          <div class="cc-calbar-center" id="calcolab-nav" aria-label="Navegación calendario">
            <button type="button" class="btn btn-sm" data-nav="prev">◀</button>
            <strong id="calcolab-title"></strong>
            <button type="button" class="btn btn-sm" data-nav="next">▶</button>
          </div>
          <div class="cc-views-below">
            <button type="button" class="btn btn-sm" id="calcolab-today">Hoy</button>
            <div id="calcolab-views" role="tablist" aria-label="Cambiar vista">
              <button type="button" class="btn btn-sm" data-view="timeGridDay" aria-selected="false">Día</button>
              <button type="button" class="btn btn-sm" data-view="timeGridWeek" aria-selected="false">Semana</button>
              <button type="button" class="btn btn-sm active" data-view="dayGridMonth" aria-selected="true">Mes</button>
            </div>
          </div>
        </div>
        <div id="calcolab-calendar" style="min-height:60vh;"></div>
      </section>
      <aside id="calcolab-controls">
        <div class="cc-controls-inner">
          <div class="cc-controls-main">
            <button id="calcolab-newclass" type="button" class="cc-btn cc-btn-green cc-btn-wide">
              Nueva Clase ＋
            </button>
            <select id="calcolab-filter-estado" class="cc-select">
              <option value="">Estado: Todos</option>
              <option value="en_curso">En curso</option>
              <option value="programado">Programado</option>
              <option value="suspendido">Canceladas</option>
              <option value="finalizado">Finalizado</option>
            </select>
            <input id="calcolab-filter-ficha" class="cc-input" list="calcolab-fichas-list" placeholder="Buscar ficha (código o nombre)">
            <datalist id="calcolab-fichas-list"></datalist>
          </div>
          <div id="calcolab-legend" class="cc-legend" aria-label="Leyenda">
            <span class="cc-legend-title">Leyenda</span>
            <div class="cc-legend-grid">
              <span class="cc-legend-item"><span class="cc-dot cc-dot-blue"></span><small>Programado</small></span>
              <span class="cc-legend-item"><span class="cc-dot cc-dot-green"></span><small>En curso</small></span>
              <span class="cc-legend-item"><span class="cc-dot cc-dot-red"></span><small>Canceladas</small></span>
              <span class="cc-legend-item"><span class="cc-dot cc-dot-black"></span><small>Finalizado</small></span>
            </div>
          </div>
          <div class="cc-controls-bottom">
            <button id="calcolab-export" type="button" class="cc-btn cc-btn-green cc-btn-icon cc-btn-wide">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
              </svg>
              Exportar
            </button>
            <div class="cc-controls-stats">
              <div class="cc-stat"><div class="cc-stat-num" id="cc-total">0</div><small>Total</small></div>
              <div class="cc-stat"><div class="cc-stat-num" id="cc-curso">0</div><small>En curso</small></div>
              <div class="cc-stat"><div class="cc-stat-num" id="cc-susp">0</div><small>Canceladas</small></div>
              <div class="cc-stat"><div class="cc-stat-num" id="cc-fin">0</div><small>Finalizado</small></div>
            </div>
          </div>
        </div>
      </aside>
    </div>
  </main>

  <!-- Modal Nueva Clase (solo Admin) -->
  <div class="modal fade" id="modalNewClass" tabindex="-1">
    <div class="modal-dialog modal-lg" style="max-width: 980px;">
      <div class="modal-content" style="border:none;border-radius:12px;">
        <div class="modal-header" style="background:#00304D;justify-content:center;border-radius:12px;margin:16px 16px 0 16px;">
          <h5 class="modal-title" style="color:#fff;text-align:center;width:100%;margin:6px 0;">Nueva Clase</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
        </div>
        <div class="modal-body" style="padding:16px 20px 8px 20px;">
          <div style="position:relative;background:#D7EECC;border-radius:12px;padding:16px 16px 14px 54px;margin:16px 0 18px 0;">
            <div style="position:absolute;left:12px;top:12px;width:24px;height:24px;border-radius:50%;background:#00304D;display:flex;align-items:center;justify-content:center;color:transparent;">
              <span style="position:relative;color:transparent;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <circle cx="12" cy="12" r="10" fill="#00304D"></circle>
                  <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 2.5-3 4" />
                  <line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
              </span>
            </div>
            <div style="color:#000;font-weight:700;margin-bottom:4px;display:flex;align-items:center;gap:8px;">
              <span>Horario Seleccionado</span>
              <small style="background:#e7f3dc;color:#00304D;border-radius:8px;padding:2px 8px;font-weight:600;">Fecha y Hora</small>
            </div>
            <div style="color:#000;font-weight:400;opacity:.9;">Primero, selecciona al instructor o facilitador para cargar las fichas correspondientes. Luego, elige el día y ajusta la hora según sea necesario. Finalmente, selecciona el aula donde se llevará a cabo la actividad.</div>
          </div>

          <!-- Fila 1: Instructor | Ficha -->
          <div class="row g-3" style="margin-bottom:8px;">
            <div class="col-md-6">
              <label class="form-label">Instructor</label>
              <select id="newclass-instructor" class="form-select" style="border-radius:12px;box-shadow:0 3px 0 rgba(0,0,0,0.06);">
                <option value="">-- Seleccionar --</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Ficha<span style="color:#dc2626;">*</span></label>
              <select id="newclass-ficha" class="form-select" style="border-radius:12px;box-shadow:0 3px 0 rgba(0,0,0,0.06);">
                <option value="">-- Seleccionar --</option>
              </select>
            </div>
          </div>

          <!-- Fila 2: Fecha | Inicio | Fin -->
          <div class="row g-3" style="margin-bottom:8px;">
            <div class="col-md-4">
              <label class="form-label">Fecha</label>
              <input type="date" id="newclass-fecha" class="form-control cc-date" style="border-radius:12px;box-shadow:0 3px 0 rgba(0,0,0,0.06);" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Hora Inicio</label>
              <input type="text" id="newclass-hora-inicio" class="form-control cc-time" style="border-radius:12px;box-shadow:0 3px 0 rgba(0,0,0,0.06);" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Hora Fin</label>
              <input type="text" id="newclass-hora-fin" class="form-control cc-time" style="border-radius:12px;box-shadow:0 3px 0 rgba(0,0,0,0.06);" />
            </div>
          </div>

          <!-- Fila 3: Colores | Aula -->
          <div style="margin-top:6px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <div style="flex:1;min-width:220px;">
              <label class="form-label">Color del Evento</label>
              <div id="newclass-color" style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;">
                <input type="color" id="newclass-color-picker" class="cc-color cc-color-input" title="Elegir color" value="#0b2f3f" />
                <button type="button" class="cc-color" data-color="#52a31a" style="background:#52a31a;"></button>
                <button type="button" class="cc-color" data-color="#ea1919" style="background:#ea1919;"></button>
                <button type="button" class="cc-color" data-color="#f0d01a" style="background:#f0d01a;"></button>
                <button type="button" class="cc-color" data-color="#a020f0" style="background:#a020f0;"></button>
                <button type="button" class="cc-color" data-color="#f97316" style="background:#f97316;"></button>
                <button type="button" class="cc-color" data-color="#06d7d9" style="background:#06d7d9;"></button>
              </div>
              <input type="hidden" id="newclass-color-value" value="#0b2f3f" />
            </div>
            <div style="flex:1;min-width:220px;">
              <label class="form-label">Aula<span style="color:#dc2626;">*</span></label>
              <input type="text" id="newclass-aula" class="form-control" placeholder="Ej: A-102" style="border-radius:12px;box-shadow:0 3px 0 rgba(0,0,0,0.06);" />
            </div>
          </div>
        </div>
        <div class="modal-footer" style="justify-content:flex-end;padding:14px 20px 20px 20px;">
          <button type="button" class="btn" data-bs-dismiss="modal" style="background:#0b2f3f;color:#fff;border-radius:20px;padding:8px 16px;box-shadow:0 6px 0 rgba(11,47,63,.18);">Cancelar</button>
          <button type="button" id="newclass-go" class="btn" style="background:#4CAF50;color:#fff;border-radius:20px;padding:8px 16px;box-shadow:0 6px 0 rgba(76,175,80,.18);">Generar</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
  <script src="/js/Calendario/colaborativo.js"></script>

<?php include __DIR__ . '/../Componentes/footer.php'; ?>
