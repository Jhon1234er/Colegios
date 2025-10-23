
document.addEventListener('DOMContentLoaded', function () {
  const el = document.getElementById('calendario');
  if (!el || typeof FullCalendar === 'undefined') return;

  const cal = new FullCalendar.Calendar(el, {
    initialView: 'dayGridMonth',
    locale: 'es',
    firstDay: 1,
    contentHeight: 'auto',
    expandRows: true,
    handleWindowResize: true,
    headerToolbar: {
      left: 'today,timeGridDay,timeGridWeek,dayGridMonth',
      center: '', // sin flechas/título nativos
      right: ''
    },
    buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día' },
    views: {
      timeGridWeek: {
        allDaySlot: false,
        slotMinTime: '08:00:00',
        slotMaxTime: '22:00:00',
        slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: true }
      },
      timeGridDay: {
        allDaySlot: false,
        slotMinTime: '08:00:00',
        slotMaxTime: '22:00:00',
        slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: true }
      },
      dayGridMonth: {
        eventDisplay: 'block',
        dayMaxEvents: 2,
        displayEventTime: false
      }
    },
    // Render personalizado: vista Mes con punto de estado fuera y "pill" interior recortado
    eventContent: function(arg) {
      try {
        // Solo personalizar en vista Mes
        if (arg.view?.type !== 'dayGridMonth') return undefined;
        const estado = (arg.event.extendedProps?.estado || '').toString().toLowerCase();
        const map = { programado: '#3b82f6', 'en_curso': '#22c55e', finalizado: '#111827', suspendido: '#ef4444' };
        const dotColor = map[estado] || arg.event.backgroundColor || '#3b82f6';
        const pillColor = arg.event.backgroundColor || '#3b82f6';
        // Contenedor externo (sin fondo)
        const wrap = document.createElement('span');
        wrap.style.display = 'inline-flex';
        wrap.style.alignItems = 'center';
        wrap.style.position = 'relative';
        wrap.style.width = '100%'; // limitar al ancho del evento
        // Punto de estado, fuera a la izquierda
        const dot = document.createElement('span');
        dot.style.width = '8px'; dot.style.height = '8px'; dot.style.borderRadius = '50%';
        dot.style.background = dotColor; dot.style.position = 'absolute';
        // Ubicar el punto pegado al borde izquierdo del evento, pero sin salirse de la celda
        dot.style.left = '0px'; dot.style.top = '50%'; dot.style.transform = 'translateY(-50%)';
        dot.style.outline = '2px solid #fff';
        // Pill interno con color (recortado a la derecha del punto)
        const pill = document.createElement('span');
        pill.style.display = 'block';
        pill.style.background = pillColor; pill.style.color = '#fff';
        pill.style.borderRadius = '6px'; pill.style.padding = '2px 6px';
        // Deja espacio a la izquierda del texto para que el punto no pise el fondo
        pill.style.marginLeft = '12px';
        pill.style.width = 'calc(100% - 12px)'; // ancho restante dentro del evento
        pill.style.boxSizing = 'border-box';
        pill.style.whiteSpace = 'nowrap'; pill.style.overflow = 'hidden'; pill.style.textOverflow = 'ellipsis';
        // Construir título separando base (instructor+ficha) y cola (aula). Leer de event y de extendedProps.
        const ex = arg.event.extendedProps || {};
        const root = arg.event || {};
        const pick = (a, b, ...keys) => {
          for (const src of [a,b]) { for (const k of keys){ const v = src && src[k]; if (v!=null && String(v).trim()!=='') return String(v).trim(); } }
          return '';
        };
        const instructor = pick(root, ex, 'profesor_nombre','docente','profesor','creado_por','creador','title_profesor');
        const fichaCode  = pick(root, ex, 'ficha_codigo','codigo_ficha','ficha','codigo');
        const aula       = pick(root, ex, 'aula','salon','sala');
        const baseTitle = [instructor, fichaCode].filter(Boolean).join(' ');
        const aulaPart  = aula ? (' ' + aula) : '';
        const fullTitle = baseTitle + aulaPart;
        pill.textContent = fullTitle;
        pill.setAttribute('data-full-title', fullTitle);
        pill.setAttribute('data-base-title', baseTitle);
        pill.setAttribute('data-aula-title', aula);
        pill.setAttribute('data-is-month-pill', '1');
        wrap.appendChild(dot); wrap.appendChild(pill);
        return { domNodes: [wrap] };
      } catch (_) { return undefined; }
    },
    // Asegurar que el punto pueda salirse del pill (evitar clipping) y que el texto use ellipsis
    eventDidMount: function(info){
      try {
        if (info.view?.type !== 'dayGridMonth') return;
        // Hacer transparente el contenedor del evento (el color lo pinta el pill)
        const el = info.el;
        el.style.backgroundColor = 'transparent';
        el.style.borderColor = 'transparent';
        el.style.overflow = 'visible';
        el.style.width = '100%';
        const parent = el.parentElement; if (parent) parent.style.overflow = 'visible';
        // Truncado explícito con '…' si el texto no cabe
        const pill = el.querySelector('[data-is-month-pill="1"]');
        if (pill) {
          const fits = pill.scrollWidth <= pill.clientWidth + 1; // tolerancia
          if (!fits) {
            const base = pill.getAttribute('data-base-title') || '';
            const aula = pill.getAttribute('data-aula-title') || '';
            // Si no hay aula, truncar general
            if (!aula) {
              const full = pill.getAttribute('data-full-title') || pill.textContent || '';
              let lo = 0, hi = full.length, best = '';
              while (lo <= hi) { const mid = Math.floor((lo+hi)/2); pill.textContent = full.slice(0, mid) + '…'; if (pill.scrollWidth <= pill.clientWidth + 1) { best = pill.textContent; lo = mid + 1; } else { hi = mid - 1; } }
              pill.textContent = best || '…';
            } else {
              // Mantener base intacta y truncar solo la parte de Aula
              let lo = 0, hi = aula.length, best = '';
              while (lo <= hi) {
                const mid = Math.floor((lo+hi)/2);
                const cand = base + ' ' + aula.slice(0, mid) + '…';
                pill.textContent = cand;
                if (pill.scrollWidth <= pill.clientWidth + 1) { best = cand; lo = mid + 1; }
                else { hi = mid - 1; }
              }
              pill.textContent = best || (base + ' …');
            }
          }
        }
      } catch(_) {}
    },
    moreLinkClick: function(arg) {
      if (window.calendario) {
        window.calendario.changeView('timeGridDay', arg.date);
      }
      return 'prevent';
    },
    dayMaxEvents: true,
    // Cargar eventos desde el backend PHP
    events: function(fetchInfo, successCallback, failureCallback) {
      try {
        const url = new URL('/', window.location.origin);
        url.searchParams.set('page', 'calendario_obtener');
        url.searchParams.set('start', fetchInfo.startStr);
        url.searchParams.set('end', fetchInfo.endStr);
        if (window.profesorFiltro) url.searchParams.set('profesor_id', window.profesorFiltro);
        if (window.estadoFiltro) url.searchParams.set('estado', window.estadoFiltro);

        fetch(url.toString(), { credentials: 'same-origin' })
          .then(r => { if (!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
          .then(data => {
            let mapped = (Array.isArray(data) ? data : []).map(e => {
              const estado = (e.estado || (e.extendedProps && e.extendedProps.estado) || '').toString().trim();
              const cls = ['ev-pill'];
              if (estado) cls.push('ev-estado-' + estado);
              // Título SOLO: nombre del instructor + código de ficha + aula
              const x = (obj, ...keys) => {
                for (const k of keys) { if (obj && obj[k] != null && String(obj[k]).trim() !== '') return String(obj[k]).trim(); }
                return '';
              };
              const instructor = x(e, 'profesor_nombre','docente','profesor','creado_por','creador') || x(e.extendedProps||{}, 'profesor_nombre','docente','profesor','creado_por','creador');
              const fichaCode  = x(e, 'ficha_codigo','codigo_ficha','ficha','codigo') || x(e.extendedProps||{}, 'ficha_codigo','codigo_ficha','ficha','codigo');
              const aula       = x(e, 'aula','salon','sala') || x(e.extendedProps||{}, 'aula','salon','sala');
              const titleText  = [instructor, fichaCode, aula].filter(Boolean).join(' ');
              return {
                id: e.id,
                title: (titleText || 'Evento'),
                start: e.start || e.fecha_inicio || e.inicio,
                end: e.end || e.fecha_fin || e.fin || null,
                backgroundColor: e.backgroundColor || e.color || '#3b82f6',
                borderColor: e.borderColor || e.color || '#3b82f6',
                textColor: '#ffffff',
                display: 'block',
                classNames: cls,
                extendedProps: Object.assign({}, e.extendedProps || {}, { estado })
              };
            });
            // Filtro cliente por estado
            if (window.estadoFiltro) {
              const est = String(window.estadoFiltro).trim();
              mapped = mapped.filter(ev => {
                const es = (ev.extendedProps?.estado || '').toString().trim();
                return es === est;
              });
            }
            // Filtro cliente por ficha (acepta id o código)
            if (window.fichaFiltro) {
              const fval = String(window.fichaFiltro).trim();
              mapped = mapped.filter(ev => {
                const ex = ev.extendedProps || {};
                const fid = ex.ficha_id != null ? String(ex.ficha_id) : '';
                const fcod = ex.ficha_codigo != null ? String(ex.ficha_codigo) : '';
                return fid === fval || fcod === fval;
              });
            }
            // Guardar última lista para utilidades
            try { window.lastEventos = mapped.slice(); } catch(_) {}

            successCallback(mapped);
          })
          .catch(err => { console.error('Error cargando eventos:', err); failureCallback(err); });
      } catch (e) {
        console.error('Fallo general cargando eventos:', e); failureCallback(e);
      }
    }
  });

  cal.render();
  window.calendario = cal; // accesible para futuras extensiones

  // Conectar barra de navegación personalizada (como colaborativo)
  const t = document.getElementById('cal-title');
  const btnPrev = document.getElementById('cal-prev');
  const btnNext = document.getElementById('cal-next');
  const refreshTitle = () => { if (t) t.textContent = (cal.view?.title || '').toLowerCase(); };
  btnPrev?.addEventListener('click', () => { cal.prev(); refreshTitle(); });
  btnNext?.addEventListener('click', () => { cal.next(); refreshTitle(); });
  cal.on('datesSet', refreshTitle);
  refreshTitle();

  // Filtro por estado
  try {
    const filtroEstado = document.getElementById('filtroEstado');
    if (filtroEstado) {
      window.estadoFiltro = filtroEstado.value || '';
      filtroEstado.addEventListener('change', () => {
        window.estadoFiltro = filtroEstado.value || '';
        try { cal.refetchEvents(); } catch (_) {}
      });
    }
  } catch (_) {}

  // Filtro por ficha + carga de fichas
  (async () => {
    try {
      const sel = document.getElementById('filtroFicha');
      if (!sel) return;
      // Inicializar valor actual
      window.fichaFiltro = sel.value || '';
      sel.addEventListener('change', () => {
        window.fichaFiltro = sel.value || '';
        try { cal.refetchEvents(); } catch(_) {}
      });

      // Intentar 1: ?page=calendario_obtener_fichas (ruta definida en public/index.php)
      const tryFetch = async () => {
        const u1 = new URL('/', window.location.origin);
        u1.searchParams.set('page','calendario_obtener_fichas');
        try {
          const r1 = await fetch(u1.toString(), { credentials:'same-origin' });
          if (r1.ok) return await r1.json();
          throw new Error('fallback');
        } catch {
          const u2 = new URL('/', window.location.origin);
          u2.searchParams.set('page','calendario');
          u2.searchParams.set('action','obtenerFichas');
          try {
            const r2 = await fetch(u2.toString(), { credentials:'same-origin' });
            if (r2.ok) return await r2.json();
            throw new Error('fallback2');
          } catch {
            const u3 = new URL('/', window.location.origin);
            u3.searchParams.set('page','profesorficha');
            const r3 = await fetch(u3.toString(), { credentials:'same-origin' });
            if (!r3.ok) throw new Error('no fichas');
            return await r3.json();
          }
        }
      };

      const fichas = await tryFetch();
      if (Array.isArray(fichas) && fichas.length > 0) {
        try { window.fichasDisponibles = fichas.slice(); } catch(_) {}
        const current = sel.value;
        sel.innerHTML = '';
        const optAll = document.createElement('option');
        optAll.value = '';
        optAll.textContent = 'Todas las fichas';
        sel.appendChild(optAll);
        fichas.forEach(f => {
            const id = (f.id != null ? String(f.id) : (f.value != null ? String(f.value) : ''));
            const codigo = (f.codigo != null ? String(f.codigo) : (f.numero != null ? String(f.numero) : (f.numero_ficha != null ? String(f.numero_ficha) : '')));
            const nombre = (f.nombre != null ? String(f.nombre) : '');
            const o = document.createElement('option');
            o.value = id || codigo || '';
            o.textContent = codigo ? (nombre ? `${codigo} — ${nombre}` : codigo) : (nombre || 'Ficha');
            sel.appendChild(o);
        });
        sel.value = current;
      } else {
        // Fallback: construir fichas desde eventos cargados
        const buildFromEvents = () => {
          const evs = Array.isArray(window.lastEventos) ? window.lastEventos : [];
          const set = new Map();
          evs.forEach(ev => {
            const ex = ev.extendedProps || {};
            const fid = ex.ficha_id != null ? String(ex.ficha_id) : '';
            const fcod = ex.ficha_codigo != null ? String(ex.ficha_codigo) : (ex.codigo != null ? String(ex.codigo) : '');
            const fname = ex.ficha_nombre != null ? String(ex.ficha_nombre) : '';
            const key = fid || fcod;
            if (key && !set.has(key)) set.set(key, { id: fid, codigo: fcod, nombre: fname });
          });
          if (set.size > 0) {
            const current = sel.value;
            sel.innerHTML = '';
            const optAll = document.createElement('option');
            optAll.value = '';
            optAll.textContent = 'Todas las fichas';
            sel.appendChild(optAll);
            for (const [, f] of set) {
              const o = document.createElement('option');
              o.value = f.id || f.codigo || '';
              o.textContent = f.codigo ? (f.nombre ? `${f.codigo} — ${f.nombre}` : f.codigo) : (f.nombre || 'Ficha');
              sel.appendChild(o);
            }
            sel.value = current;
          }
        };
        // Si ya existen eventos cargados, construir inmediatamente; si no, esperar primera carga
        if (Array.isArray(window.lastEventos) && window.lastEventos.length > 0) buildFromEvents();
        else {
          const iv = setInterval(() => {
            if (Array.isArray(window.lastEventos) && window.lastEventos.length > 0) {
              clearInterval(iv);
              buildFromEvents();
            }
          }, 300);
          setTimeout(() => clearInterval(iv), 4000);
        }
      }
      try { cal.refetchEvents(); } catch(_) {}
    } catch (e) {
      // Silencioso
    }
  })();

  // Aplicar ajustes visuales a timeGrid (Semana/Día) tras cada render/cambio de vista
  const applyTimeGridTweaks = () => {
    try {
      const root = document.getElementById('calendario');
      if (!root) return;
      // Bordes/separación entre columnas
      const tables = root.querySelectorAll('.fc .fc-timegrid-body > table, .fc .fc-timegrid-cols > table');
      tables.forEach(t => { t.style.borderSpacing = '2px 0'; t.style.borderCollapse = 'separate'; t.style.width = '100%'; });
      // Padding mínimo en columnas y sin margen en frames
      const cols = root.querySelectorAll('.fc-timegrid .fc-timegrid-col');
      cols.forEach(c => { c.style.padding = '2px'; });
      const frames = root.querySelectorAll('.fc-timegrid .fc-timegrid-col-frame');
      frames.forEach(f => { f.style.margin = '0'; });
      // Eje de horas más compacto
      const axis = root.querySelectorAll('.fc-timegrid-axis');
      axis.forEach(a => { a.style.width = '60px'; a.style.minWidth = '60px'; });
    } catch (_) {}
  };
  applyTimeGridTweaks();
  cal.on('datesSet', applyTimeGridTweaks);

  // =====================
  // Modal de Detalle de Evento (todas las vistas)
  // =====================
  const ensureDetailModal = () => {
    let el = document.getElementById('modalDetalleEvento');
    if (el) return el;
    el = document.createElement('div');
    el.className = 'modal fade';
    el.id = 'modalDetalleEvento';
    el.tabIndex = -1;
    el.innerHTML = `
      <div class="modal-dialog modal-lg" style="max-width:980px;">
        <div class="modal-content" style="border:none;border-radius:12px;">
          <div class="modal-header" style="background:#00304D;justify-content:center;border-radius:12px;margin:16px 16px 0 16px;padding:12px 18px;position:relative;">
            <h5 class="modal-title" style="color:#fff;text-align:center;width:100%;margin:6px 0;font-weight:900;font-size:24px;letter-spacing:0.4px;">Detalle del Evento</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"
              style="position:absolute;right:18px;top:50%;transform:translateY(-50%);
                     width:40px;height:40px;opacity:1;outline:none;border:0;background-color:transparent;
                     background-repeat:no-repeat;background-position:center;background-size:16px 16px;">
            </button>
          </div>
          <div class="modal-body">
            <div id="detalleEventoBody"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>`;
    document.body.appendChild(el);
    return el;
  };
  const fmt = (dIso) => {
    try { const d = new Date(dIso); return d.toLocaleString('es-CO', { dateStyle:'medium', timeStyle:'short' }); } catch{ return String(dIso||''); }
  };
  const textOr = (obj, ...keys) => { for (const k of keys){ const v = obj && obj[k]; if (v!=null && String(v).trim()!=='') return String(v).trim(); } return ''; };
  cal.on('eventClick', function(info){
    try {
      info.jsEvent?.preventDefault?.();
      const modal = ensureDetailModal();
      const body = modal.querySelector('#detalleEventoBody');
      const ev = info.event;
      const ex = ev.extendedProps || {};
      const titulo   = ev.title || ex.titulo || 'Evento';
      const inicio   = ev.start || ex.fecha_inicio || ex.inicio;
      const fin      = ev.end   || ex.fecha_fin   || ex.fin;
      const aula     = textOr(ex, 'aula','salon','sala');
      const fichaC   = textOr(ex, 'ficha_codigo','codigo_ficha','ficha');
      const fichaN   = textOr(ex, 'ficha_nombre');
      const profesor = textOr(ex, 'profesor_nombre','docente','profesor','creado_por','creador');
      const estado   = (textOr(ex, 'estado') || '').toLowerCase();
      // Colores por estado
      const estadoBgMap = { 'finalizado':'#111827', 'en_curso':'#22c55e', 'suspendido':'#ef4444', 'programado':'#3b82f6' };
      const estadoBg = estadoBgMap[estado] || '#111827';

      body.innerHTML = `
        <div class="event-detail-tabs">
          <div class="event-detail-tab is-active" data-tab="detalles">Detalles</div>
          <div class="event-detail-tab" data-tab="asistencias">Asistencias</div>
        </div>
        <div class="event-detail-pane is-active" id="pane-detalles">
          <div class="event-detail-card">
            <div class="row">
              <!-- Fila 1: Título + Ficha -->
              <div class="col-md-6 event-detail-item edi-center">
                <small class="text-muted">Título</small>
                <div><strong>${titulo}</strong></div>
              </div>
              <div class="col-md-6 event-detail-item edi-center">
                <small class="text-muted">Ficha</small>
                <div>${[fichaC, fichaN].filter(Boolean).join(' — ')||'—'}</div>
              </div>
              <!-- Fila 2: Inicio + Fin -->
              <div class="col-md-6 event-detail-item edi-center">
                <small class="text-muted">Inicio</small>
                <div>${fmt(inicio)}</div>
              </div>
              <div class="col-md-6 event-detail-item edi-center">
                <small class="text-muted">Fin</small>
                <div>${fmt(fin)}</div>
              </div>
              <!-- Fila 3: Instructor + Aula + Estado -->
              <div class="col-md-4 event-detail-item edi-center">
                <small class="text-muted">Instructor</small>
                <div>${profesor||'—'}</div>
              </div>
              <div class="col-md-4 event-detail-item edi-center">
                <small class="text-muted">Aula</small>
                <div>${aula||'—'}</div>
              </div>
              <div class="col-md-4 event-detail-item edi-center edi-state" style="--chip-bg:${estadoBg};">
                <small class="text-muted">Estado</small>
                <div><span class="edi-chip">${estado || '—'}</span></div>
              </div>
            </div>
          </div>
        </div>
        <div class="event-detail-pane" id="pane-asistencias">
          <div class="event-detail-card" id="asistenciasContent">Cargando asistencias…</div>
        </div>`;

      // Tabs behavior
      const tabs = Array.from(body.querySelectorAll('.event-detail-tab'));
      const panes = { detalles: body.querySelector('#pane-detalles'), asistencias: body.querySelector('#pane-asistencias') };
      tabs.forEach(t => t.addEventListener('click', () => {
        tabs.forEach(x => x.classList.remove('is-active'));
        t.classList.add('is-active');
        const k = t.getAttribute('data-tab');
        panes.detalles.classList.toggle('is-active', k==='detalles');
        panes.asistencias.classList.toggle('is-active', k==='asistencias');
      }));

      // Cargar asistencias (usa ficha_id si está disponible y fecha local YYYY-MM-DD)
      try {
        const toLocalYMD = (d) => {
          const yy = d.getFullYear();
          const mm = String(d.getMonth()+1).padStart(2,'0');
          const dd = String(d.getDate()).padStart(2,'0');
          return `${yy}-${mm}-${dd}`;
        };
        const d0 = inicio ? new Date(inicio) : new Date();
        const dayISO = toLocalYMD(d0);
        const url = new URL('/', window.location.origin);
        url.searchParams.set('page','asistencias_por_fecha');
        url.searchParams.set('fecha', dayISO);
        const fichaIdFromEvent = ex.ficha_id || ex.fichaId || ex.id_ficha || ex.idFicha;
        if (fichaIdFromEvent && /^\d+$/.test(String(fichaIdFromEvent))) {
          url.searchParams.set('ficha_id', String(fichaIdFromEvent));
        } else if (fichaC) {
          url.searchParams.set('ficha', fichaC);
        }
        fetch(url.toString(), { credentials:'same-origin' })
          .then(r => r.ok ? r.json() : [])
          .then(list => {
            const cont = body.querySelector('#asistenciasContent');
            if (!Array.isArray(list) || list.length===0) { cont.textContent = 'Sin asistencias registradas en esta fecha.'; return; }
            const ul = document.createElement('ul'); ul.style.margin='0'; ul.style.padding='0 0 0 16px';
            list.forEach(it => { const li = document.createElement('li'); li.textContent = `${it.estudiante || it.nombre || 'Estudiante'} — ${it.estado || 'presente'}`; ul.appendChild(li); });
            cont.innerHTML = ''; cont.appendChild(ul);
          })
          .catch(()=>{ const cont = body.querySelector('#asistenciasContent'); cont.textContent = 'Sin asistencias registradas en esta fecha.'; });
      } catch(_) {}
      if (window.bootstrap && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modal).show();
      } else {
        modal.style.display = 'block'; modal.classList.add('show');
      }
    } catch (e) { /* noop */ }
  });

  // Ajustar alto del harness: fijo a 750px (pedido)
  const adjustHarnessHeight = () => {
    const harness = document.querySelector('#calendario .fc-view-harness');
    if (harness) harness.style.minHeight = '750px';
  };
  adjustHarnessHeight();
  window.addEventListener('resize', () => { adjustHarnessHeight(); cal.updateSize(); });

  // API mínima para inyectar datos más adelante
  window.setCalendarEvents = function(arr) {
    try {
      cal.removeAllEvents();
      if (Array.isArray(arr)) cal.addEventSource(arr);
    } catch (e) { /* noop */ }
  };

  // =====================
  // Modal "Nueva Clase"
  // =====================
  // Esta sección replica (y centraliza) la lógica que estaba embebida en views/Calendario/calendario.php
  // para abrir el modal, poblar fichas, aplicar disponibilidad y validar fecha/horario.
  // De esta manera, el HTML queda limpio y todo el comportamiento vive aquí.

  // Utilidades comunes ---------------------------------------------
  const dayNameFromIdx = (i) => ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'][i] || '';
  const segToRange = (seg) => { const map = { 'mañana':['08:00','12:00'], 'manana':['08:00','12:00'], 'tarde':['12:00','17:00'], 'noche':['18:00','22:00'] }; return map[(seg||'').toLowerCase()] || null; };
  const toMinutes = (hhmm) => { const [h,m]=String(hhmm).split(':').map(x=>parseInt(x,10)||0); return h*60+(m||0); };

  // Estado temporal dentro del modal
  let _fichaAvailModal = null; // { dias:[], jornada_global:[], jornada_por_dia:{} }

  // Construye/actualiza el select de fichas dentro del modal
  const buildFichaOptions = (sel) => {
    const current = sel.value;
    const arr = Array.isArray(window.fichasDisponibles) ? window.fichasDisponibles : [];
    sel.innerHTML = '<option value="">Seleccionar ficha...</option>';
    (arr||[]).forEach(f => {
      const id = String(f.id ?? f.value ?? '');
      const cod = String(f.codigo ?? f.numero ?? f.numero_ficha ?? '');
      const nom = String(f.nombre ?? '');
      const o = document.createElement('option');
      o.value = id || cod; o.textContent = cod ? (nom ? `${cod} — ${nom}` : cod) : (nom || 'Ficha');
      sel.appendChild(o);
    });
    if (current) sel.value = current;
    // fallback desde el filtro superior si no hay datos
    if (sel.options.length <= 1) {
      const filtro = document.getElementById('filtroFicha');
      if (filtro) {
        Array.from(filtro.options).forEach(opt => {
          if (!opt.value) return; const o2 = document.createElement('option');
          o2.value = opt.value; o2.textContent = opt.textContent; sel.appendChild(o2);
        });
      }
    }
    // Sincronizar con filtro actual si existe valor
    const filtro = document.getElementById('filtroFicha');
    if (filtro && filtro.value) {
      const val = String(filtro.value);
      const opt = Array.from(sel.options).find(o => String(o.value) === val);
      if (opt) sel.value = val;
    }
  };

  // Resuelve id real a partir de id o código
  const resolveFichaId = (raw) => {
    const v = String(raw||'').trim();
    if (/^\d+$/.test(v)) return v;
    try {
      const list = Array.isArray(window.fichasDisponibles)? window.fichasDisponibles : [];
      const hit = list.find(f => String(f.codigo||f.numero||f.numero_ficha||'') === v);
      if (hit && hit.id!=null) return String(hit.id);
    } catch(_) {}
    return v;
  };

  // Obtiene rango permitido para una fecha según disponibilidad
  const allowedRangeForDate = (dSel) => {
    if (!_fichaAvailModal || !dSel) return null;
    const name = dayNameFromIdx(dSel.getDay());
    const perDay = _fichaAvailModal.jornada_por_dia && _fichaAvailModal.jornada_por_dia[name];
    const global = _fichaAvailModal.jornada_global;
    const segs0 = Array.isArray(perDay) && perDay.length ? perDay : (Array.isArray(global) ? global : []);
    const segs = (segs0||[]).map(s => s === 'mañana' ? 'manana' : s).filter(Boolean);
    let ranges = segs.map(segToRange).filter(Boolean);
    // todo el día: mañana + tarde
    if (segs.includes('manana') && segs.includes('tarde')) ranges = [['08:00','17:00']];
    if (!ranges.length) return null;
    const mins = ranges.map(r=>r[0]); const maxs = ranges.map(r=>r[1]);
    const minOverall = mins.sort()[0]; const maxOverall = maxs.sort().slice(-1)[0];
    return [minOverall, maxOverall];
  };

  // Pinta el aviso de disponibilidad encima del formulario
  const renderAvailability = (wrap, fechaInput) => {
    if (!_fichaAvailModal) { wrap.textContent = ''; return; }
    const dias = (_fichaAvailModal.dias||[]).map(d=>d.charAt(0).toUpperCase()+d.slice(1)).join(', ');
    const prettySeg = (s)=> s==='manana' ? 'mañana' : (s==='noche' ? 'nocturna' : s);
    let jgList = Array.isArray(_fichaAvailModal.jornada_global) ? _fichaAvailModal.jornada_global.slice() : [];
    if (!jgList.length && _fichaAvailModal.jornada_por_dia && typeof _fichaAvailModal.jornada_por_dia === 'object'){
      const set = new Set();
      Object.values(_fichaAvailModal.jornada_por_dia).forEach(arr => { (Array.isArray(arr)?arr:[]).forEach(x=>{ if (x) set.add(String(x)); }); });
      jgList = Array.from(set);
    }
    let jgText = '—';
    if (jgList.length){
      const hasM = jgList.includes('manana') || jgList.includes('mañana');
      const hasT = jgList.includes('tarde');
      const hasN = jgList.includes('noche');
      if (hasM && hasT) { jgText = 'todo el día' + (hasN ? ' (incluye nocturna)' : ''); }
      else { jgText = jgList.map(prettySeg).join(', '); }
    }
    // Día seleccionado
    let daySegsText = '';
    const fpDate = fechaInput? fechaInput._flatpickr : null;
    if (fpDate && fpDate.selectedDates && fpDate.selectedDates[0]){
      const name = dayNameFromIdx(fpDate.selectedDates[0].getDay());
      const segs = (_fichaAvailModal.jornada_por_dia && _fichaAvailModal.jornada_por_dia[name]) || _fichaAvailModal.jornada_global || [];
      const segsNorm = (segs||[]).map(s=> s==='mañana' ? 'manana' : s);
      if (segsNorm.length) {
        const pretty = segsNorm.map(prettySeg);
        daySegsText = ` | Para ${name.charAt(0).toUpperCase()+name.slice(1)}: ${pretty.join(', ')}`;
      }
    }
    wrap.innerHTML = `<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <span style="background:#eef6ff;color:#0369a1;border-radius:10px;padding:2px 8px;">Días permitidos: ${dias||'—'}</span>
      <span style="background:#f1f5f9;color:#111827;border-radius:10px;padding:2px 8px;">Jornada: ${jgText}${daySegsText}</span>
    </div>`;
  };

  // Abre el modal y conecta todos los comportamientos
  const openNuevaClaseModal = () => {
    const selFicha = document.getElementById('fichaNuevaClase');
    const fecha = document.getElementById('fechaNuevaClase');
    const hi = document.getElementById('horaInicioNuevaClase');
    const hf = document.getElementById('horaFinNuevaClase');
    const modalEl = document.getElementById('modalNuevaClase');

    // Poblar fichas y sincronizar con filtro superior
    if (selFicha) buildFichaOptions(selFicha);

    // Flatpickr (usa el contenedor del modal para estilos)
    if (window.flatpickr) {
      window.flatpickr.localize(window.flatpickr.l10ns.es || {});
      const container = modalEl;
      if (fecha && !fecha._flatpickr) window.flatpickr(fecha, { dateFormat:'Y-m-d', altInput:true, altFormat:'d/m/Y', allowInput:true, disableMobile:true, appendTo: container });
      const initTime = (el) => { if (el && !el._flatpickr) window.flatpickr(el, { enableTime:true, noCalendar:true, time_24hr:false, minuteIncrement:5, dateFormat:'H:i', altInput:true, altFormat:'h:i K', appendTo: container }); };
      initTime(hi); initTime(hf);
    }

    // Crear contenedor del aviso si no existe
    const ensureAvailWrap = () => {
      let el = document.getElementById('newclass-ficha-availability');
      if (!el) {
        el = document.createElement('div'); el.id = 'newclass-ficha-availability';
        el.style.margin = '6px 0 0'; el.style.fontSize = '12px';
        const formEl = document.getElementById('formNuevaClase');
        formEl?.parentElement?.insertBefore(el, formEl);
      }
      return el;
    };

    // Aplicar disponibilidad a pickers y aviso
    const applyAvailabilityToPickers = () => {
      const fpDate = fecha? fecha._flatpickr : null;
      const fpHi = hi? hi._flatpickr : null;
      const fpHf = hf? hf._flatpickr : null;
      if (!_fichaAvailModal || !fpDate) return;
      const idxFromDayName = (n) => ({'domingo':0,'lunes':1,'martes':2,'miercoles':3,'jueves':4,'viernes':5,'sabado':6})[n] ?? -1;
      const allowedIdx = (_fichaAvailModal.dias||[]).map(idxFromDayName).filter(x=>x>=0);
      const isAllowedDate = (d)=> !allowedIdx.length || allowedIdx.includes(d.getDay());
      fpDate.set('enable', [isAllowedDate]);
      const cur = fpDate.selectedDates?.[0] || new Date();
      if (!isAllowedDate(cur)) {
        let probe = new Date(cur);
        for (let k=0; k<21; k++) { probe.setDate(probe.getDate()+1); if (isAllowedDate(probe)) { fpDate.setDate(probe, true); break; } }
      }
      const dSel = fpDate.selectedDates?.[0];
      if (dSel && (fpHi||fpHf)) {
        const rng = allowedRangeForDate(dSel);
        if (rng && fpHi && fpHf) {
          const [minOverall,maxOverall] = rng;
          fpHi.set('minTime', minOverall); fpHi.set('maxTime', maxOverall);
          fpHf.set('minTime', minOverall); fpHf.set('maxTime', maxOverall);
          // Autocorrección si el usuario escribió fuera de rango
          const clamp = (fp, val, minT, maxT) => {
            const cur = (fp.input.value||'').trim();
            const cM = cur? toMinutes(cur) : null; const minM = toMinutes(minT); const maxM = toMinutes(maxT);
            if (cM===null || cM<minM || cM>maxM) fp.setDate(val, true);
          };
          clamp(fpHi, minOverall, minOverall, maxOverall);
          clamp(fpHf, maxOverall, minOverall, maxOverall);
        } else if (fpHi && fpHf) {
          fpHi.set('minTime', null); fpHi.set('maxTime', null);
          fpHf.set('minTime', null); fpHf.set('maxTime', null);
        }
      }
      renderAvailability(ensureAvailWrap(), fecha);
    };

    // Eventos de cambio
    try {
      if (fecha && fecha._flatpickr) {
        const fn = () => { try { applyAvailabilityToPickers(); } catch(_){} };
        (fecha._flatpickr.config.onChange || (fecha._flatpickr.config.onChange = [])).push(fn);
      }
      const clampTime = () => {
        const dSel = fecha? fecha._flatpickr?.selectedDates?.[0] : null;
        const rng = dSel ? allowedRangeForDate(dSel) : null;
        if (!rng) return;
        const [minT,maxT] = rng;
        const fpHi = hi? hi._flatpickr : null; const fpHf = hf? hf._flatpickr : null;
        if (fpHi) { const cur = (fpHi.input.value||'').trim(); if (!cur || toMinutes(cur) < toMinutes(minT)) fpHi.setDate(minT, true); }
        if (fpHf) { const cur2 = (fpHf.input.value||'').trim(); if (!cur2 || toMinutes(cur2) > toMinutes(maxT)) fpHf.setDate(maxT, true); }
      };
      hi?.addEventListener('change', clampTime);
      hf?.addEventListener('change', clampTime);
    } catch(_) {}

    // Al cambiar ficha => traer disponibilidad
    const fetchAvailability = async (fidRaw) => {
      _fichaAvailModal = null; renderAvailability(ensureAvailWrap(), fecha);
      try {
        const fid = resolveFichaId(fidRaw);
        const r = await fetch('/?page=calcolab_disponibilidad_ficha&ficha_id='+encodeURIComponent(fid), { credentials:'same-origin' });
        if (!r.ok) return; _fichaAvailModal = await r.json();
        renderAvailability(ensureAvailWrap(), fecha);
        applyAvailabilityToPickers();
      } catch(_) {}
    };
    if (selFicha && !selFicha.dataset.boundAvail) {
      selFicha.dataset.boundAvail = '1';
      selFicha.addEventListener('change', () => { const v = selFicha.value || ''; if (v) fetchAvailability(v); });
      if (selFicha.value) fetchAvailability(selFicha.value);
    }

    // Abrir modal correctamente (Bootstrap gestiona aria-hidden)
    if (modalEl) {
      if (window.bootstrap && bootstrap.Modal) {
        const inst = bootstrap.Modal.getOrCreateInstance(modalEl); inst.show();
        if (!modalEl.dataset.boundReset) {
          modalEl.dataset.boundReset = '1';
          modalEl.addEventListener('hidden.bs.modal', () => {
            // Reset del modal al cerrar
            document.getElementById('formNuevaClase')?.reset?.();
            ['fichaNuevaClase','aulaNuevaClase','colorNuevaClase'].forEach(id => { const el = document.getElementById(id); if (id==='fichaNuevaClase' && el) el.selectedIndex = 0; if (id==='aulaNuevaClase' && el) el.value=''; if (id==='colorNuevaClase' && el) el.value='#3b82f6'; });
            ['fechaNuevaClase','horaInicioNuevaClase','horaFinNuevaClase'].forEach(id => { const el = document.getElementById(id); try { if (el?._flatpickr) el._flatpickr.clear(); } catch(_){} });
            const chips = document.getElementById('newclass-color-chips'); chips?.querySelectorAll('.cc-color').forEach(b=>b.classList.remove('is-selected'));
            const av = document.getElementById('newclass-ficha-availability'); if (av) av.textContent = '';
            _fichaAvailModal = null;
          });
        }
      } else {
        modalEl.setAttribute('aria-hidden', 'false'); modalEl.style.display = 'block'; modalEl.classList.add('show');
      }
    }
  };

  // Botón para abrir el modal
  const btnNueva = document.getElementById('btnNuevaClase');
  btnNueva?.addEventListener('click', openNuevaClaseModal);
});
