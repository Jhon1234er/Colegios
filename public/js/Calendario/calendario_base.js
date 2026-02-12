
document.addEventListener('DOMContentLoaded', function () {
  // Debug flags via query: ?fcdebug
  try {
    const qp = new URLSearchParams(window.location.search);
    window.fcdebug = qp.has('fcdebug');
    if (window.fcdebug) {
      const st = document.createElement('style');
      st.textContent = [
        '.fc .fc-daygrid-event{display:block!important;opacity:1!important}',
        '.fc .fc-daygrid-event-harness{overflow:visible!important}',
        '.fc .fc-daygrid-dot-event .fc-event-title{white-space:nowrap!important}',
      ].join('\n');
      document.head.appendChild(st);
    }
  } catch(_) {}
  // Sembrar filtros desde la URL (robusto al entrar desde buscador)
  try {
    const qp2 = new URLSearchParams(window.location.search);
    const fid = qp2.get('facilitador_id') || qp2.get('profesor_id');
    if (fid && !window.facilitadorFiltro) { window.facilitadorFiltro = String(fid).trim(); }
    const fch = qp2.get('ficha');
    if (fch && !window.fichaFiltro) { window.fichaFiltro = String(fch).trim(); }
    const est = qp2.get('estado');
    if (est && !window.estadoFiltro) { window.estadoFiltro = String(est).trim(); }
  } catch(_){}

  const el = document.getElementById('calendario');
  if (!el || typeof FullCalendar === 'undefined') return;

  const cal = new FullCalendar.Calendar(el, {
    initialView: 'dayGridMonth',
    nowIndicator: true,
    locale: 'es',
    firstDay: 1,
    contentHeight: 'auto',
    expandRows: true,
    handleWindowResize: true,
    // Interacciones: arrastrar, redimensionar y seleccionar
    editable: true,
    selectable: true,
    selectMirror: true,
    eventResizableFromStart: true,
    eventOverlap: true,
    customButtons: {
      myToday: {
        text: 'Hoy',
        click: () => {
          try {
            cal.today();
            setTimeout(() => {
              const root = document.getElementById('calendar');
              // ANIMACIÓN DESACTIVADA - estaba causando bugs constantes
              /*
              const targets = [];
              root?.querySelectorAll('.fc-daygrid-day.fc-day-today .fc-daygrid-day-frame')?.forEach(n=>targets.push(n));
              root?.querySelectorAll('.fc-timegrid .fc-day-today .fc-timegrid-col-frame')?.forEach(n=>targets.push(n));
              if (!targets.length) return;
              let up = true; const start = Date.now(); const D = 1000; const STEP = 140;
              const prev = targets.map(n=>({t:n.style.transform, s:n.style.boxShadow}));
              const timer = setInterval(() => {
                const elapsed = Date.now()-start; if (elapsed > D) { clearInterval(timer); targets.forEach((n,i)=>{ n.style.transform = prev[i].t || ''; n.style.boxShadow = prev[i].s || ''; }); return; }
                targets.forEach(n=>{
                  n.style.transition = 'transform .12s ease, box-shadow .12s ease';
                  n.style.transform = up ? 'translateY(-8px)' : 'translateY(0)';
                  n.style.boxShadow = up ? '0 10px 18px rgba(0,0,0,.12)' : '0 4px 10px rgba(0,0,0,.06)';
                });
                up = !up;
              }, STEP);
              */
            }, 60);
          } catch(_) {}
        }
      }
    },
    headerToolbar: {
      left: 'myToday,timeGridDay,timeGridWeek,dayGridMonth',
      center: '', // sin flechas/título nativos
      right: ''
    },
    buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Día' },
    views: {
      timeGridWeek: {
        allDaySlot: false,
        dayHeaderFormat: { weekday: 'long', day: '2-digit', month: '2-digit' },
        slotMinTime: '08:00:00',
        slotMaxTime: '22:00:00',
        slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: true }
      },
      timeGridDay: {
        allDaySlot: false,
        dayHeaderFormat: { weekday: 'long', day: '2-digit', month: '2-digit' },
        slotMinTime: '08:00:00',
        slotMaxTime: '22:00:00',
        slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: true }
      },
      dayGridMonth: {
        dayHeaderFormat: { weekday: 'long' },
        eventDisplay: 'block',
        dayMaxEvents: 2,
        dayMaxEventRows: 2,
        displayEventTime: false
      }
    },
    // Personalizar el texto del link "+N más" en vista Mes
    moreLinkContent: function(args) {
      try {
        const n = args && (args.num || args.shortText || args.text);
        const num = typeof n === 'number' ? n : parseInt(String(n||'').replace(/\D+/g,''), 10) || '';
        return { html: `+${num} más` };
      } catch (_) {
        return { html: '+ más' };
      }
    },
    // Render personalizado: vista Mes con punto de estado fuera y "pill" interior recortado
    eventContent: function(arg) {
      if (window.fcdebug) { return undefined; }
      try {
        const vtype = arg.view?.type || '';
        // Vista Semana/Día (timeGrid): mostrar texto informativo dentro del bloque
        if (vtype === 'timeGridWeek' || vtype === 'timeGridDay') {
          const ex = arg.event.extendedProps || {};
          const root = arg.event || {};
          const pick = (a, b, ...keys) => {
            for (const src of [a,b]) { for (const k of keys){ const v = src && src[k]; if (v!=null && String(v).trim()!=='') return String(v).trim(); } }
            return '';
          };
          const instructor = pick(root, ex, 'profesor_nombre','docente','profesor','creado_por','creador','title_profesor');
          const fichaCode  = pick(root, ex, 'ficha_codigo','codigo_ficha','ficha','codigo');
          const aula       = pick(root, ex, 'aula','salon','sala');
          const head = [instructor, fichaCode].filter(Boolean).join(' ');
          const sub  = aula ? ('Aula: ' + aula) : '';
          const wrap = document.createElement('div');
          wrap.style.display = 'flex';
          wrap.style.flexDirection = 'column';
          wrap.style.gap = '2px';
          const h = document.createElement('div'); h.textContent = head || (root.title || 'Clase'); h.style.fontWeight = '800';
          const s = document.createElement('div'); s.textContent = sub; s.className = 'ev-sub';
          wrap.appendChild(h); if (sub) wrap.appendChild(s);
          return { domNodes: [wrap] };
        }
        // Solo personalizar en vista Mes
        if (vtype !== 'dayGridMonth') return undefined;
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
        // No forzar overflow/width para evitar cambios de alto en filas
        el.style.overflow = '';
        el.style.width = '';
        const parent = el.parentElement; if (parent) parent.style.overflow = '';
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
        // Asegurar filtro de facilitador desde window o URL
        let fac = window.facilitadorFiltro;
        if (!fac) {
          try {
            const qp = new URLSearchParams(window.location.search);
            fac = qp.get('facilitador_id') || qp.get('profesor_id') || '';
          } catch(_) { fac = ''; }
        }
        if (fac) url.searchParams.set('facilitador_id', String(fac));
        if (window.estadoFiltro) url.searchParams.set('estado', window.estadoFiltro);
        if (window.fichaFiltro) url.searchParams.set('ficha', window.fichaFiltro);

        const fUrl = url.toString();
        try { console.debug('[cal] fetching', fUrl); } catch(_){ }
        fetch(fUrl, { credentials: 'same-origin' })
          .then(async r => {
            if (!r.ok) throw new Error('HTTP '+r.status);
            // Tolerar respuestas vacías o no-JSON devolviendo []
            try { return await r.json(); } catch { return []; }
          })
          .then(data => {
            try {
              console.debug('[cal] raw type/len', typeof data, Array.isArray(data) ? data.length : 'na');
            } catch(_){ }
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
                // no forzar display para respetar render según vista
                classNames: cls,
                extendedProps: Object.assign({}, e.extendedProps || {}, { estado })
              };
            });
            try {
              console.debug('[cal] mapped count', mapped.length, mapped.slice(0,3));
              window._calLastFetch = { url: fUrl, data, mapped };
            } catch(_){ }
            // Filtro cliente por estado (normalizado en minúsculas)
            if (window.estadoFiltro) {
              const est = String(window.estadoFiltro).trim().toLowerCase();
              mapped = mapped.filter(ev => {
                const es = (ev.extendedProps?.estado || '').toString().trim().toLowerCase();
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

  // ===== Mostrar la hora actual en vista Mes (badge) =====
  function updateMonthNowBadge(){
    try {
      const root = document.getElementById('calendario');
      if (!root) return;
      // Si no estamos en Mes, eliminar y salir
      if ((cal.view?.type || '') !== 'dayGridMonth') {
        const oldAny = root.querySelector('#cal-now-badge');
        if (oldAny) oldAny.remove();
        return;
      }
      const dayTop = root.querySelector('.fc-daygrid .fc-day-today .fc-daygrid-day-top');
      if (!dayTop) return;
      let badge = dayTop.querySelector('#cal-now-badge');
      if (!badge) {
        badge = document.createElement('span');
        badge.id = 'cal-now-badge';
        badge.className = 'cc-now-badge';
        dayTop.appendChild(badge);
      }
      const pad = (n)=>String(n).padStart(2,'0');
      const now = new Date();
      const hh = now.getHours();
      const mm = pad(now.getMinutes());
      const ap = hh >= 12 ? 'PM' : 'AM';
      const h12 = (hh % 12) || 12;
      badge.textContent = `${h12}:${mm} ${ap}`;
    } catch(_){ }
  }

  function scheduleMonthBadgeTimer(){
    try {
      if (window._calNowBadgeTimer) { clearInterval(window._calNowBadgeTimer); window._calNowBadgeTimer = null; }
      if ((cal.view?.type || '') === 'dayGridMonth') {
        // Desactivado - estaba causando animaciones constantes
// window._calNowBadgeTimer = setInterval(() => { try { updateMonthNowBadge(); } catch(_){} }, 30000);
      }
    } catch(_){}
  }

  try { updateMonthNowBadge(); scheduleMonthBadgeTimer(); } catch(_) {}

  // Conectar barra de navegación personalizada (título centrado y flechas externas)
  const t = document.getElementById('cal-title');
  const btnPrev = document.getElementById('cal-prev');
  const btnNext = document.getElementById('cal-next');
  function refreshTitle(){
    try {
      if (!t) return;
      const raw = (cal.view?.title || '').toString().trim().toLowerCase();
      t.textContent = raw ? (raw.charAt(0).toUpperCase() + raw.slice(1)) : '';
    } catch(_){}
  }
  btnPrev?.addEventListener('click', () => { try { cal.prev(); refreshTitle(); } catch(_){} });
  btnNext?.addEventListener('click', () => { try { cal.next(); refreshTitle(); } catch(_){} });
  cal.on('datesSet', refreshTitle);
  refreshTitle();

  // Exportar reporte de clases (rango de la vista actual)
  try {
    const btnExp = document.getElementById('btnExportar');
    if (btnExp && !btnExp.dataset.bound) {
      btnExp.dataset.bound = '1';
      btnExp.addEventListener('click', () => {
        try {
          const view = cal.view;
          const base = view?.currentStart || view?.activeStart || new Date();
          const ref = new Date(base.getTime());
          ref.setDate(ref.getDate() + 15);
          const d1 = new Date(ref.getFullYear(), ref.getMonth(), 1);
          const d2 = new Date(ref.getFullYear(), ref.getMonth() + 1, 0);
          const toYMD = (d) => {
            const yy = d.getFullYear();
            const mm = String(d.getMonth()+1).padStart(2,'0');
            const dd = String(d.getDate()).padStart(2,'0');
            return `${yy}-${mm}-${dd}`;
          };
          const url = new URL('/', window.location.origin);
          url.searchParams.set('page', 'calendario_exportar');
          url.searchParams.set('action', 'exportarReporte');
          url.searchParams.set('fecha_inicio', toYMD(d1));
          url.searchParams.set('fecha_fin', toYMD(d2));
          if (window.estadoFiltro)     url.searchParams.set('estado', String(window.estadoFiltro));
          if (window.facilitadorFiltro) url.searchParams.set('facilitador_id', String(window.facilitadorFiltro));
          // Abrir en nueva pestaña para descargar .xls
          window.open(url.toString(), '_blank');
        } catch (_) { /* noop */ }
      });
    }
  } catch(_) {}

  // Duplicar semana (modal con lista de clases de la semana actual)
  try {
    const btnDup = document.getElementById('btnDuplicarSemana');
    if (btnDup && !btnDup.dataset.bound) {
      btnDup.dataset.bound = '1';

      const buildDupModal = () => {
        let modal = document.getElementById('modalDuplicarSemana');
        if (modal) return modal;
        modal = document.createElement('div');
        modal.id = 'modalDuplicarSemana';
        modal.className = 'modal fade';
        modal.innerHTML = `
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden;">
      <div class="modal-header" style="background:#00304D;justify-content:center;padding:14px 20px;border-bottom:none;position:relative;">
        <h5 class="modal-title" style="color:#fff;text-align:center;width:100%;margin:0;font-weight:800;font-size:20px;letter-spacing:0.3px;">Duplicar clases a la siguiente semana</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"
          style="position:absolute;right:18px;top:50%;transform:translateY(-50%);
                 width:36px;height:36px;opacity:1;outline:none;border:0;background-color:transparent;
                 background-repeat:no-repeat;background-position:center;background-size:14px 14px;
                 filter:invert(1);">
        </button>
      </div>
      <div class="modal-body" style="padding:18px 20px 16px 20px;">
        <div style="position:relative;background:#F1F5F9;border-radius:10px;padding:10px 12px 10px 40px;margin-bottom:14px;font-size:13px;line-height:1.4;color:#0f172a;">
          <div style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:22px;height:22px;border-radius:50%;background:#00304D;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:700;">
            i
          </div>
          <div>
            Se mostrarán las clases correspondientes a la semana actual (según la fecha de hoy). Al hacer clic sobre una clase, esta se marcará como opaca; las clases opacas no se duplicarán en la semana siguiente. Las clases que permanezcan visibles se duplicarán automáticamente.
          </div>
        </div>
        <div id="dupSemanaLista" style="max-height:320px;overflow:auto;border:1px solid #e2e8f0;border-radius:10px;padding:8px 0;background:#ffffff;"></div>
      </div>
      <div class="modal-footer" style="border-top:none;padding:12px 20px 18px 20px;display:flex;justify-content:flex-end;gap:10px;">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
          style="border-radius:999px;padding:6px 18px;font-weight:600;font-size:14px;background:#E5E7EB;color:#111827;border:1px solid #CBD5E1;">
          Cancelar
        </button>
        <button type="button" class="btn btn-success" id="btnConfirmarDuplicarSemana"
          style="border-radius:999px;padding:6px 20px;font-weight:700;font-size:14px;background:#16A34A;border-color:#16A34A;box-shadow:0 8px 18px rgba(22,163,74,.35);">
          Duplicar seleccionadas
        </button>
      </div>
    </div>
  </div>`;
        document.body.appendChild(modal);
        return modal;
      };

      const getThisWeekRange = () => {
        const today = new Date();
        const d = new Date(today.getTime());
        const day = d.getDay(); // 0=domingo..6=sabado
        const diff = day === 0 ? -6 : (1 - day); // mover a lunes
        d.setDate(d.getDate() + diff);
        d.setHours(0,0,0,0);
        const start = d;
        const end = new Date(start.getTime());
        end.setDate(end.getDate() + 7);
        return {start, end};
      };

      const isInThisWeek = (startDate) => {
        if (!(startDate instanceof Date) || isNaN(startDate)) return false;
        const {start, end} = getThisWeekRange();
        return startDate >= start && startDate < end;
      };

      const openDupModal = () => {
        const modalEl = buildDupModal();
        const listWrap = modalEl.querySelector('#dupSemanaLista');
        listWrap.innerHTML = '';

        const eventos = Array.isArray(window.lastEventos) ? window.lastEventos.slice() : [];
        const thisWeek = getThisWeekRange();

        const parseDate = (val) => {
          if (!val) return null;
          try { return new Date(val); } catch(_) { return null; }
        };

        const items = [];
        eventos.forEach(ev => {
          const st = parseDate(ev.start || ev.extendedProps?.fecha_inicio || ev.extendedProps?.inicio);
          if (!isInThisWeek(st)) return;
          const ex = ev.extendedProps || {};
          const idNum = parseInt(String(ex.horario_original_id || ex.horario_id || ev.id || 0), 10);
          if (!idNum || isNaN(idNum)) return;

          const fechaStr = st ? st.toLocaleDateString('es-CO', { weekday:'short', year:'numeric', month:'short', day:'numeric' }) : '';
          const horaStr  = st ? st.toLocaleTimeString('es-CO', { hour:'2-digit', minute:'2-digit' }) : '';
          const ficha    = (ex.ficha_codigo || ex.ficha_nombre || '').toString();
          const prof     = (ex.profesor_nombre || '').toString();
          const aula     = (ex.aula || '').toString();
          const label    = [fechaStr + (horaStr?` ${horaStr}`:''), ficha, prof, aula].filter(Boolean).join(' — ');

          items.push({ id: idNum, label });
        });

        if (!items.length) {
          listWrap.innerHTML = '<div style="padding:12px 16px;font-size:14px;opacity:.8;">No hay clases en la semana actual para duplicar.</div>';
        } else {
          const ul = document.createElement('div');
          ul.style.display = 'flex';
          ul.style.flexDirection = 'column';
          ul.style.gap = '6px';
          ul.style.padding = '4px 10px 6px 10px';
          items.forEach(it => {
            const row = document.createElement('div');
            row.className = 'dup-semana-item';
            row.dataset.id = String(it.id);
            row.dataset.selected = '1';
            row.style.cursor = 'pointer';
            row.style.padding = '8px 10px';
            row.style.display = 'flex';
            row.style.alignItems = 'center';
            row.style.justifyContent = 'space-between';
            row.style.fontSize = '13px';
            row.style.borderRadius = '8px';
            row.style.border = '1px solid #e2e8f0';
            row.style.background = '#F9FAFB';
            row.innerHTML = `
              <span>${it.label}</span>
              <span class="dup-pill" style="font-size:11px;padding:2px 9px;border-radius:999px;background:#DCFCE7;color:#166534;font-weight:600;">Se duplicará</span>
            `;
            const updateState = () => {
              const sel = row.dataset.selected === '1';
              if (sel) {
                row.style.opacity = '1';
                row.style.background = '#F9FAFB';
                row.style.borderColor = '#cbd5f5';
                row.querySelector('.dup-pill').textContent = 'Se duplicará';
                row.querySelector('.dup-pill').style.background = '#DCFCE7';
                row.querySelector('.dup-pill').style.color = '#166534';
              } else {
                row.style.opacity = '0.6';
                row.style.background = '#F8FAFC';
                row.style.borderColor = '#fecaca';
                row.querySelector('.dup-pill').textContent = 'No se duplicará';
                row.querySelector('.dup-pill').style.background = '#FEE2E2';
                row.querySelector('.dup-pill').style.color = '#B91C1C';
              }
            };
            row.addEventListener('click', () => {
              row.dataset.selected = row.dataset.selected === '1' ? '0' : '1';
              updateState();
            });
            updateState();
            ul.appendChild(row);
          });
          listWrap.appendChild(ul);
        }

        const doOpen = () => {
          if (window.bootstrap && bootstrap.Modal) {
            const inst = bootstrap.Modal.getOrCreateInstance(modalEl);
            inst.show();
          } else {
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
            modalEl.setAttribute('aria-hidden','false');
          }
        };

        const btnConfirm = modalEl.querySelector('#btnConfirmarDuplicarSemana');
        if (btnConfirm && !btnConfirm.dataset.bound) {
          btnConfirm.dataset.bound = '1';
          btnConfirm.addEventListener('click', async () => {
            const rows = Array.from(modalEl.querySelectorAll('.dup-semana-item'));
            const ids = rows
              .filter(r => r.dataset.selected === '1')
              .map(r => parseInt(String(r.dataset.id||'0'),10))
              .filter(v => v>0);
            if (!ids.length) {
              if (typeof toast === 'function') toast('Debes dejar al menos una clase seleccionada para duplicar');
              return;
            }
            let locked = false;
            try {
              btnConfirm.disabled = true;
              btnConfirm.dataset.prevText = btnConfirm.textContent;
              btnConfirm.textContent = 'Duplicando...';
              locked = true;
              const url = new URL('/', window.location.origin);
              url.searchParams.set('page','calendario_duplicar');
              const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
              const payload = { event_ids: ids };
              const res = await fetch(url.toString(), {
                method:'POST',
                credentials:'same-origin',
                headers:{
                  'Content-Type':'application/json',
                  'X-CSRF-Token': csrf
                },
                body: JSON.stringify(payload)
              });
              const txt = await res.text();
              let json = null; try { json = JSON.parse(txt); } catch(_){}
              if (!res.ok || !json || json.success !== true) {
                const msg = (json && json.error) || txt || 'No se pudo duplicar la semana';
                if (typeof toast === 'function') toast(msg);
                return;
              }
              const n = json.insertados || 0;
              if (typeof toast === 'function') {
                if (n > 0) toast('Clases duplicadas: ' + n);
                else toast('No se crearon clases nuevas al duplicar');
              }
              try { cal.refetchEvents(); } catch (_) {} // Necesario para filtros
              if (window.bootstrap && bootstrap.Modal) {
                try { bootstrap.Modal.getOrCreateInstance(modalEl).hide(); } catch(_){}
              } else {
                modalEl.classList.remove('show');
                modalEl.style.display='none';
                modalEl.setAttribute('aria-hidden','true');
              }
            } catch(_) {
              if (typeof toast === 'function') toast('Error al duplicar la semana');
            } finally {
              if (locked) {
                btnConfirm.disabled = false;
                if (btnConfirm.dataset.prevText) btnConfirm.textContent = btnConfirm.dataset.prevText;
              }
            }
          });
        }

        doOpen();
      };

      btnDup.addEventListener('click', () => {
        openDupModal();
      });
    }
  } catch(_) {}

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
          if (r1.ok) { try { return await r1.json(); } catch { return []; } }
          throw new Error('fallback');
        } catch {
          // Fallback 2: fichas del facilitador autenticado
          try {
            const u2 = new URL('/', window.location.origin);
            u2.searchParams.set('page','facilitadorficha');
            const r2 = await fetch(u2.toString(), { credentials:'same-origin' });
            if (r2.ok) { try { return await r2.json(); } catch { return []; } }
            throw new Error('fallback2');
          } catch {
            // Evitar 403/500: regresar [] y usar buildFromEvents
            return [];
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
  // Línea de "ahora" personalizada (segmentada) para Semana/Día
  const updateDashedNowLine = () => {
    try {
      const root = document.getElementById('calendario'); if (!root) return;
      const vtype = cal.view?.type || '';
      const isTG = (vtype === 'timeGridWeek' || vtype === 'timeGridDay');
      const nativeLine = root.querySelector('.fc-timegrid-now-indicator-line');
      if (!isTG || !nativeLine) { const ex = root.querySelector('#cal-now-dashed'); if (ex) ex.remove(); return; }
      // ocultar la línea nativa continua
      nativeLine.style.opacity = '0'; nativeLine.style.borderTop = '0';
      const parent = nativeLine.parentElement || root;
      let mine = root.querySelector('#cal-now-dashed');
      if (!mine) {
        mine = document.createElement('div'); mine.id = 'cal-now-dashed';
        Object.assign(mine.style, {
          position:'absolute', left:'0', right:'0', height:'4px', zIndex:'12', pointerEvents:'none'
        });
        parent.appendChild(mine);
      }
      const top = (window.getComputedStyle(nativeLine).top || nativeLine.style.top || '0px');
      mine.style.top = top;
      mine.style.backgroundImage = 'repeating-linear-gradient(to right, #ef4444 0 14px, rgba(239,68,68,0) 14px 24px)';
    } catch(_) {}
  };
  try {
    updateDashedNowLine();
    if (window._calDashedTimer) clearInterval(window._calDashedTimer);
    // Desactivado - estaba causando animaciones constantes
// window._calDashedTimer = setInterval(updateDashedNowLine, 30000);
    window.addEventListener('resize', updateDashedNowLine);
  } catch(_) {}

  // Estilizar '+X más' como píldora (fallback JS por si CSS es sobreescrito)
  const styleMoreLinks = () => {
    try {
      const root = document.getElementById('calendario'); if (!root) return;
      const all = root.querySelectorAll('a.fc-daygrid-more-link, a.fc-more-link, .fc-daygrid-day-bottom a');
      all.forEach(a => {
        const txt = (a.textContent||'').trim();
        if (!/\+\d+\s*más/i.test(txt) && !/\+\s*\d+/i.test(txt)) return; // evitar tocar enlaces no relacionados
        a.style.background = '#2563eb';
        a.style.color = '#ffffff';
        a.style.borderRadius = '9999px';
        a.style.padding = '2px 8px';
        a.style.fontWeight = '800';
        a.style.fontSize = '12px';
        a.style.lineHeight = '20px';
        a.style.textDecoration = 'none';
        a.style.display = 'inline-block';
      });
    } catch(_) {}
  };
  try {
    styleMoreLinks(); setTimeout(styleMoreLinks, 50);
    const mo2 = new MutationObserver(()=> styleMoreLinks());
    mo2.observe(document.getElementById('calendario'), { childList:true, subtree:true });
  } catch(_) {}
  applyTimeGridTweaks();
  cal.on('datesSet', (arg) => {
    try { if (typeof applyTimeGridTweaks === 'function') applyTimeGridTweaks(); } catch(_) {}
    try { updateDashedNowLine(); setTimeout(updateDashedNowLine, 50); } catch(_) {}
    try {
      const vt = arg.view?.type || cal.view?.type || '';
      const isMonth = vt === 'dayGridMonth';
      // En Mes: permitir mover entre días, NO redimensionar
      cal.setOption('eventDurationEditable', !isMonth);
      cal.setOption('eventResizableFromStart', !isMonth);
      // Drag sigue activo en todas, pero validaremos por estado en handlers
    } catch(_) {}
    // Actualizar badge de hora en vista Mes y reprogramar temporizador
    try { updateMonthNowBadge(); scheduleMonthBadgeTimer(); setTimeout(updateMonthNowBadge, 50); } catch(_) {}
    try { styleMoreLinks(); setTimeout(styleMoreLinks, 50); } catch(_) {}
  });

  // === Botón "Hoy" confiable ===
  const inlineJump = () => {
    try {
      const root = document.getElementById('calendario');
      const targets = [];
      root?.querySelectorAll('.fc-daygrid .fc-day-today .fc-daygrid-day-frame')?.forEach(n=>targets.push(n));
      root?.querySelectorAll('.fc-timegrid .fc-day-today .fc-timegrid-col-frame')?.forEach(n=>targets.push(n));
      targets.forEach(n=>{
        const prev = n.style.transform;
        const prevShadow = n.style.boxShadow;
        n.style.transition = 'transform .25s ease, box-shadow .25s ease';
        n.style.transform = 'translateY(-6px)';
        n.style.boxShadow = '0 8px 16px rgba(0,0,0,.12)';
        setTimeout(()=>{ n.style.transform = prev || ''; n.style.boxShadow = prevShadow || ''; }, 350);
      });
    } catch(_) {}
  };
  const bindTodayReliable = () => {
    try {
      const btn = document.querySelector('.fc .fc-today-button');
      if (!btn || btn.dataset.boundToday==='1') return;
      btn.dataset.boundToday = '1';
      btn.addEventListener('click', () => { try { cal.today(); setTimeout(inlineJump, 60); } catch(_) {} });
    } catch(_) {}
  };
  // Bind inicial y observar cambios en el header del calendario
  bindTodayReliable();
  try {
    const mo = new MutationObserver(() => bindTodayReliable());
    mo.observe(document.getElementById('calendario'), { childList:true, subtree:true });
  } catch(_) {}

  // Persistencia al mover/redimensionar eventos (según reglas)
  const toMySQL = (d) => {
    if (!d) return null;
    const pad = (n)=> String(n).padStart(2,'0');
    const yy=d.getFullYear(); const mm=pad(d.getMonth()+1); const dd=pad(d.getDate());
    const hh=pad(d.getHours()); const mi=pad(d.getMinutes()); const ss=pad(d.getSeconds());
    return `${yy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
  };
  const persistEventUpdate = async (ev) => {
    try {
      const url = new URL('/', window.location.origin);
      url.searchParams.set('page','calendario_actualizar');
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      const payload = {
        id: ev.id,
        fecha_inicio: ev.start ? toMySQL(ev.start) : null,
        fecha_fin: ev.end ? toMySQL(ev.end) : null,
        // Enviar también campos que el backend espera para no sobreescribir con NULL
        titulo: ev.title || (ev.extendedProps?.titulo) || 'Clase',
        aula: ev.extendedProps?.aula || null,
        color: ev.backgroundColor || ev.extendedProps?.color || '#007bff'
      };
      const res = await fetch(url.toString(), {
        method:'POST', credentials:'same-origin',
        headers: { 'Content-Type':'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(payload)
      });
      let data = null; try { data = await res.json(); } catch(_){}
      return res.ok ? { ok:true } : { ok:false, error: (data && (data.error||data.message)) || 'Error' };
    } catch(_) { return { ok:false, error:'Error de red' }; }
  };
  const estadoOf = (ev) => (ev.extendedProps?.estado || '').toString().toLowerCase();
  const isMovable = (ev) => {
    const st = estadoOf(ev);
    return st === 'programado' || st === 'suspendido';
  };
  // Helpers de fechas
  const startOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());
  const isPastDay = (d) => startOfDay(d) < startOfDay(new Date());
  // Hacer toast global para que esté disponible en todos los contextos
  window.toast = (msg) => {
    try {
      let t = document.getElementById('cal-toast');
      if (!t) {
        t = document.createElement('div'); t.id = 'cal-toast';
        t.setAttribute('role','alert'); t.setAttribute('aria-live','polite');
        Object.assign(t.style, {
          position:'fixed', bottom:'16px', left:'50%', transform:'translateX(-50%)',
          background:'#111827', color:'#fff', padding:'10px 14px', borderRadius:'10px',
          boxShadow:'0 6px 20px rgba(0,0,0,.2)', fontWeight:'800', zIndex:'100000',
          transition:'opacity .2s ease', opacity:'0', pointerEvents:'none'
        });
        document.body.appendChild(t);
      }
      t.textContent = String(msg || '');
      // Force reflow then show
      void t.offsetHeight; t.style.opacity = '1';
      clearTimeout(window.toast._h);
      window.toast._h = setTimeout(()=>{ t.style.opacity = '0'; }, 1800);
    } catch(_){ /* noop */ }
  };
  
  // También mantener la referencia local para compatibilidad
  const toast = window.toast;
  cal.on('eventResize', async (info)=>{
    console.log('eventResize disparado', info.event);
    const vt = cal.view?.type || '';
    if (vt === 'dayGridMonth') { info.revert(); toast('En Mes no se puede cambiar la duración'); return; } // no redimensionar en Mes
    if (!isMovable(info.event)) { info.revert(); toast('No se puede editar una clase en curso o finalizada'); return; }
    // No permitir que empiece o termine en fechas pasadas
    const s = info.event.start, e = info.event.end || info.event.start;
    if (isPastDay(s) || isPastDay(e)) { info.revert(); toast('No se puede ajustar a fechas pasadas'); return; }
    // Validar disponibilidad por ficha
    const fid = info.event.extendedProps?.ficha_id;
    if (fid) {
      const chek = await rangeAllowedForFicha(fid, info.event.start, info.event.end || info.event.start);
      if (!chek.ok) { info.revert(); toast(chek.reason || 'Horario no permitido'); return; }
    }
    const res = await persistEventUpdate(info.event);
    console.log('Resultado de persistEventUpdate:', res);
    if (res.ok) {
      toast('Duración actualizada'); 
      // No recargar todo - el evento ya se actualizó localmente
    } else { info.revert(); toast(res.error || 'No se pudo guardar el cambio'); }
  });
  cal.on('eventDrop',   async (info)=>{
    console.log('eventDrop disparado', info.event);
    const vt = cal.view?.type || '';
    const st = estadoOf(info.event);
    // No mover en_curso o finalizado en ninguna vista
    if (st === 'en_curso' || st === 'finalizado') { info.revert(); toast('No se puede mover una clase en curso o finalizada'); return; }
    // En Mes: permitido mover entre días; en Semana/Día: permitido si es movable
    if (vt !== 'dayGridMonth' && !isMovable(info.event)) { info.revert(); toast('Solo Programadas o Suspendidas se pueden mover'); return; }
    // No permitir mover a fechas pasadas
    const s2 = info.event.start; const e2 = info.event.end || info.event.start;
    if (isPastDay(s2) || isPastDay(e2)) { info.revert(); toast('No se puede mover a fechas pasadas'); return; }
    // Validar disponibilidad por ficha
    const fid = info.event.extendedProps?.ficha_id;
    if (fid) {
      const chek = await rangeAllowedForFicha(fid, info.event.start, info.event.end || info.event.start);
      if (!chek.ok) { info.revert(); toast(chek.reason || 'Día/hora no permitido'); return; }
    }
    const res = await persistEventUpdate(info.event);
    console.log('Resultado de persistEventUpdate en eventDrop:', res);
    if (res.ok) {
      const d = info.event.start; const pad=(n)=>String(n).padStart(2,'0');
      toast(`Clase movida a ${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`);
      // No recargar todo - el evento ya se actualizó localmente
    } else { info.revert(); toast(res.error || 'No se pudo guardar el cambio'); }
  });

  // Bloquear selección fuera de disponibilidad si ya se eligió ficha en el modal (sólo en semana/día)
  cal.setOption('selectAllow', (selInfo) => {
    const vt = cal.view?.type || '';
    if (vt !== 'timeGridWeek' && vt !== 'timeGridDay') return true;
    // Impedir selección en días pasados
    if (isPastDay(selInfo.start)) return false;
    const sel = document.getElementById('fichaNuevaClase');
    const fid = sel && sel.value ? sel.value : null;
    // Si no hay ficha seleccionada aún, permitir; luego el modal filtrará
    if (!fid) return true;
    // Validación síncrona no puede esperar; devolvemos true y validamos al abrir modal también
    return true;
  });

  // Selección de slot en Semana/Día => abrir modal 'Nueva Clase' con fecha/hora precargadas y fichas filtradas
  const dayNameFromIdx2 = (i) => ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'][i] || '';
  const toYMD = (d) => {
    const yy=d.getFullYear(); const mm=String(d.getMonth()+1).padStart(2,'0'); const dd=String(d.getDate()).padStart(2,'0');
    return `${yy}-${mm}-${dd}`;
  };
  const toHM = (d) => `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;

  // Cache simple de disponibilidad por ficha
  window._fichaAvailCache = window._fichaAvailCache || new Map();
  const fetchAvail = async (fid) => {
    const key = String(fid);
    if (window._fichaAvailCache.has(key)) return window._fichaAvailCache.get(key);
    try {
      const r = await fetch(`/?page=calcolab_disponibilidad_ficha&ficha_id=${encodeURIComponent(key)}`, { credentials:'same-origin' });
      if (!r.ok) return null; const data = await r.json();
      window._fichaAvailCache.set(key, data); return data;
    } catch { return null; }
  };

  // Validar si un rango [start,end] es permitido para una ficha por día/jornada
  const rangeAllowedForFicha = async (fid, start, end) => {
    const avail = await fetchAvail(fid);
    if (!avail) return { ok: true };
    const dayIdx = start.getDay();
    const dayName = dayNameFromIdx2(dayIdx);
    const dias = Array.isArray(avail.dias) ? avail.dias : [];
    if (dias.length && !dias.includes(dayName)) return { ok:false, reason:`La ficha no opera el ${dayName}` };
    // jornada: puede venir global o por día; permitimos mañana/tarde/noche o ambos (todo el día)
    const segsDay = avail.jornada_por_dia && avail.jornada_por_dia[dayName];
    let segs = Array.isArray(segsDay) ? segsDay.slice() : (Array.isArray(avail.jornada_global) ? avail.jornada_global.slice() : []);
    segs = (segs||[]).map(s => s==='mañana' ? 'manana' : s);
    // convertir a rangos
    const segTo = (s)=> s==='manana'?['06:00','12:00']: s==='tarde'?['12:00','18:00']: s==='noche'?['18:00','22:00']: null;
    let ranges = segs.map(segTo).filter(Boolean);
    if (segs.includes('manana') && segs.includes('tarde')) ranges = [['08:00','17:00']];
    if (!ranges.length) return { ok: true };
    const fmt = (d)=> `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
    const sHM = fmt(start), eHM = fmt(end || start);
    const inRange = ranges.some(([a,b]) => sHM>=a && eHM<=b);
    return inRange ? { ok:true } : { ok:false, reason:'Fuera de la jornada permitida para ese día' };
  };

  const filterFichasForWeekday = async (weekdayIdx) => {
    const sel = document.getElementById('fichaNuevaClase'); if (!sel) return;
    const all = Array.isArray(window.fichasDisponibles) ? window.fichasDisponibles.slice() : [];
    const dayName = dayNameFromIdx2(weekdayIdx);
    let matches = [];
    let anyNullAvail = false;
    for (const f of all) {
      const fid = String(f.id ?? f.value ?? ''); if (!fid) continue;
      const avail = await fetchAvail(fid);
      if (!avail) { anyNullAvail = true; matches.push({ id: fid, codigo: f.codigo ?? f.numero ?? f.numero_ficha ?? '', nombre: f.nombre ?? '' }); continue; }
      const dias = Array.isArray(avail.dias) ? avail.dias : [];
      if (!dias.length || dias.includes(dayName)) { matches.push({ id: fid, codigo: f.codigo ?? f.numero ?? f.numero_ficha ?? '', nombre: f.nombre ?? '' }); }
    }
    // Si el endpoint de disponibilidad no está accesible (403/null), no filtrar por día: usar todas
    if (anyNullAvail && matches.length === all.length) {
      matches = all.map(f => ({ id: String(f.id ?? f.value ?? ''), codigo: f.codigo ?? f.numero ?? f.numero_ficha ?? '', nombre: f.nombre ?? '' }));
    }
    // Si el filtrado por día quedó vacío, mantener todas para no bloquear el flujo
    if (!matches.length && all.length) {
      matches = all.map(f => ({ id: String(f.id ?? f.value ?? ''), codigo: f.codigo ?? f.numero ?? f.numero_ficha ?? '', nombre: f.nombre ?? '' }));
    }
    // Rellenar select con resultados (o todas si aplica)
    const current = sel.value;
    sel.innerHTML = '<option value="">Seleccionar ficha...</option>';
    matches.forEach(f => {
      const o = document.createElement('option');
      o.value = f.id || f.codigo || '';
      o.textContent = f.codigo ? (f.nombre ? `${f.codigo} — ${f.nombre}` : f.codigo) : (f.nombre || 'Ficha');
      sel.appendChild(o);
    });
    if (current && Array.from(sel.options).some(o=>o.value===current)) sel.value = current;
  };

  cal.on('select', async (selInfo) => {
    try {
      const vtype = cal.view?.type || '';
      if (vtype !== 'timeGridWeek' && vtype !== 'timeGridDay') return;
      // Abrir modal
      openNuevaClaseModal();
      // Precargar fecha y horas
      const d = selInfo.start; const d2 = selInfo.end;
      const fechaEl = document.getElementById('fechaNuevaClase');
      const hiEl = document.getElementById('horaInicioNuevaClase');
      const hfEl = document.getElementById('horaFinNuevaClase');
      const ymd = toYMD(d);
      if (fechaEl && fechaEl._flatpickr) fechaEl._flatpickr.setDate(ymd, true); else if (fechaEl) fechaEl.value = ymd;
      if (hiEl && hiEl._flatpickr) hiEl._flatpickr.setDate(toHM(d), true); else if (hiEl) hiEl.value = toHM(d);
      if (hfEl && hfEl._flatpickr) hfEl._flatpickr.setDate(toHM(d2), true); else if (hfEl) hfEl.value = toHM(d2);
      // Filtrar fichas para el día seleccionado
      await filterFichasForWeekday(d.getDay());
    } catch(_) {}
  });

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
            <button type="button" class="btn btn-success" id="btnReactivarClaseFooter">Reactivar</button>
            <button type="button" class="btn btn-outline-danger" id="btnSuspenderClaseFooter">Suspender</button>
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
    console.log('eventClick disparado', info.event);
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
      console.log('Datos del evento:', { titulo, inicio, fin, aula, fichaC, fichaN, profesor, estado });
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
            <div class="mt-3" id="detalle-acciones" style="display:flex;gap:8px;justify-content:flex-end;align-items:center;"></div>
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

      // Acciones: Suspender / Reactivar en footer (no se ocultan; solo se (des)habilitan)
      try {
        let btnReactFooter = modal.querySelector('#btnReactivarClaseFooter');
        let btnSuspFooter  = modal.querySelector('#btnSuspenderClaseFooter');
        const chip = body.querySelector('.edi-state .edi-chip');
        const stateWrap = body.querySelector('.edi-state');
        const estadoBgMap2 = { 'finalizado':'#111827', 'en_curso':'#22c55e', 'suspendido':'#ef4444', 'programado':'#3b82f6' };
        const canReact = () => (estado === 'suspendido') && (new Date(inicio) > new Date());
        const syncButtons = () => {
          // Suspender deshabilitado si ya está suspendido
          if (btnSuspFooter) btnSuspFooter.disabled = (estado === 'suspendido');
          // Reactivar deshabilitado salvo que cumpla reglas
          if (btnReactFooter) {
            btnReactFooter.disabled = !canReact();
            btnReactFooter.title = canReact() ? '' : 'Solo se puede reactivar antes de la hora de inicio';
          }
          if (chip) chip.textContent = (estado || '—');
          if (stateWrap) stateWrap.style.setProperty('--chip-bg', estadoBgMap2[estado] || '#111827');
        };

        // Deshabilitar temporalmente las acciones de estado según solicitud
        const estadoActionsEnabled = false;
        if (!estadoActionsEnabled) {
          if (btnReactFooter) { btnReactFooter.style.display = 'none'; btnReactFooter.disabled = true; }
          if (btnSuspFooter)  { btnSuspFooter.style.display  = 'none'; btnSuspFooter.disabled  = true; }
          syncButtons();
        } else {

        let changingEstado = false;
        const doChangeEstado = async (nuevo) => {
          if (changingEstado) return; // evitar re-entrada por doble clic
          changingEstado = true;
          // Deshabilitar botones mientras guarda
          btnReactFooter && (btnReactFooter.disabled = true);
          btnSuspFooter  && (btnSuspFooter.disabled  = true);
          let saved = false;
          try {
            const url = new URL('/', window.location.origin); url.searchParams.set('page','calendario_estado');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            // Para eventos sincronizados, usar el id original numérico
            const rawId = (ex && (ex.horario_original_id || ex.horario_id)) || ev.id;
            const idNum = String(rawId).replace(/^sync_/,'');
            if (!/^[0-9]+$/.test(idNum)) { toast('No se puede cambiar el estado de este evento'); return; }
            const tryOnce = async (estadoVal) => {
              const pay = { horario_id: parseInt(idNum, 10), estado: estadoVal };
              const res = await fetch(url.toString(), { method:'POST', credentials:'same-origin', headers:{ 'Content-Type':'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(pay) });
              const txt = await res.text(); let jj=null; try{ jj = JSON.parse(txt); }catch(_){ }
              return { ok: res.ok && !(jj && jj.error), text: txt, json: jj };
            };
            const toTitleEnum = (v)=> v==='en_curso' ? 'En curso' : (v==='programado' ? 'Programado' : (v==='suspendido' ? 'Suspendido' : v));
            let resp = await tryOnce(toTitleEnum(nuevo));
            if (!resp.ok) { resp = await tryOnce(nuevo); }
            if (!resp.ok && /Data truncated|SQLSTATE\[01000\]/i.test(resp.text||'')) {
              resp = await tryOnce(toTitleEnum(nuevo));
            }
            const isSoftSuccess = (!resp.ok) && /SQLSTATE\[01000\].*Data truncated.*estado/i.test(resp.text||'');
            if (!resp.ok && !isSoftSuccess) {
              toast((resp.json && resp.json.error) || resp.text || 'No se pudo cambiar el estado');
            } else {
              // Actualizar estado local y del evento sin recargar
              estado = (nuevo === 'suspendido') ? 'suspendido' : 'programado';
              try { if (ev.setExtendedProp) ev.setExtendedProp('estado', estado); } catch(_) { /* noop */ }
              syncButtons();
              toast(nuevo === 'suspendido' ? 'Clase suspendida' : 'Clase reactivada');
              // Refrescar y cerrar modal para que el cambio sea evidente
              try { cal.refetchEvents(); } catch(_){ }
              try {
                const md = document.getElementById('modalDetalleEvento');
                if (md) {
                  if (window.bootstrap && window.bootstrap.Modal) {
                    const inst = window.bootstrap.Modal.getInstance(md) || new window.bootstrap.Modal(md);
                    inst.hide();
                  } else {
                    md.classList.remove('show');
                    md.style.display = 'none';
                  }
                }
              } catch(_) { /* noop */ }
              saved = true;
            }
          } catch(_) {
            if (!saved) toast('No se pudo contactar al servidor');
          } finally {
            // Rehabilitar según estado actual
            syncButtons();
            changingEstado = false;
          }
        };
        // Asegurar un solo listener por apertura de modal: clonar botones para limpiar listeners previos
        if (btnReactFooter && btnReactFooter.parentNode) {
          const clone = btnReactFooter.cloneNode(true);
          btnReactFooter.parentNode.replaceChild(clone, btnReactFooter);
          btnReactFooter = clone;
        }
        if (btnSuspFooter && btnSuspFooter.parentNode) {
          const clone = btnSuspFooter.cloneNode(true);
          btnSuspFooter.parentNode.replaceChild(clone, btnSuspFooter);
          btnSuspFooter = clone;
        }
        // Wire footer buttons (ya sin listeners anteriores)
        btnReactFooter?.addEventListener('click', ()=> { if (!btnReactFooter.disabled) doChangeEstado('programado'); });
        btnSuspFooter?.addEventListener('click',  ()=> { if (!btnSuspFooter.disabled)  doChangeEstado('suspendido'); });
        // Inicializar estado de botones
        syncButtons();
        }
      } catch(_) {}
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
            const rows = Array.isArray(list) ? list : [];
            // Mostrar solo asistencias con registro real
            const withRecords = rows.filter(it => it && (it.asistencia_id != null));
            if (withRecords.length === 0) { cont.textContent = 'Sin asistencias registradas en esta fecha.'; return; }

            // Resumen por estado
            const counts = { presente:0, ausente:0, tardanza:0, justificada:0 };
            withRecords.forEach(it => {
              const st = (it.estado||'').toLowerCase();
              if (st === 'presente') {
                counts.presente++;
              } else if (st === 'falla' || st === 'ausente' || st === 'no_asistio') {
                counts.ausente++;
              } else if (st === 'tardanza' || st === 'tarde') {
                counts.tardanza++;
              } else if (st === 'justificada' || st === 'justificado') {
                counts.justificada++;
              }
            });

            const frag = document.createDocumentFragment();
            const summary = document.createElement('div');
            summary.className = 'asist-summary';
            summary.innerHTML = `
              <span class="asist-pill presente">Asistió: ${counts.presente}</span>
              <span class="asist-pill ausente">Ausentes: ${counts.ausente}</span>
              <span class="asist-pill tardanza">Tarde: ${counts.tardanza}</span>
              <span class="asist-pill justificada">Inacistencia Justificada: ${counts.justificada}</span>
            `;
            frag.appendChild(summary);

            const listWrap = document.createElement('div');
            listWrap.className = 'asist-list';
            withRecords.forEach(it => {
              const nombre = [it.nombres, it.apellidos].filter(Boolean).join(' ') || it.estudiante || it.nombre || 'Estudiante';
              const estadoRaw = (it.estado || 'sin registro').toLowerCase();
              const estadoClass =
                (estadoRaw === 'falla' || estadoRaw === 'ausente' || estadoRaw === 'no_asistio') ? 'ausente' :
                (estadoRaw === 'tardanza' || estadoRaw === 'tarde') ? 'tardanza' :
                (estadoRaw === 'justificado' || estadoRaw === 'justificada') ? 'justificada' :
                (estadoRaw === 'presente') ? 'presente' : estadoRaw;
              const estadoLabel =
                (estadoRaw === 'falla' || estadoRaw === 'ausente' || estadoRaw === 'no_asistio') ? 'No asistió' :
                (estadoRaw === 'tardanza' || estadoRaw === 'tarde') ? 'Tarde' :
                (estadoRaw === 'justificado' || estadoRaw === 'justificada') ? 'Inacistencia Justificada' :
                (estadoRaw === 'presente') ? 'Asistió' : (estadoRaw || '—');
              const initials = nombre.split(' ').map(w=>w[0]).filter(Boolean).slice(0,2).join('').toUpperCase();
              const item = document.createElement('div');
              item.className = 'asist-item';
              item.innerHTML = `
                <div class="asist-avatar">${initials}</div>
                <div class="asist-name">${nombre}</div>
                <div class="asist-chip ${estadoClass}">${estadoLabel}</div>
              `;
              listWrap.appendChild(item);
            });
            frag.appendChild(listWrap);

            cont.innerHTML = '';
            cont.appendChild(frag);
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

  // Pinta el aviso de disponibilidad encima del formulario (días y sus jornadas; día seleccionado en negrilla)
  const renderAvailability = (wrap, fechaInput) => {
    if (!_fichaAvailModal) { wrap.textContent = ''; return; }
    const prettySeg = (s)=> s==='manana' ? 'mañana' : (s==='noche' ? 'nocturna' : s);
    const daysOrder = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'];
    const allowedDias = Array.isArray(_fichaAvailModal.dias) && _fichaAvailModal.dias.length
      ? _fichaAvailModal.dias.map(d=>String(d).toLowerCase())
      : Object.keys(_fichaAvailModal.jornada_por_dia || {});
    const fpDate = fechaInput? fechaInput._flatpickr : null;
    const selectedDay = (fpDate && fpDate.selectedDates && fpDate.selectedDates[0]) ? dayNameFromIdx(fpDate.selectedDates[0].getDay()) : null;
    const getSegs = (day) => {
      const per = _fichaAvailModal.jornada_por_dia && _fichaAvailModal.jornada_por_dia[day];
      let segs = Array.isArray(per) && per.length ? per.slice() : (Array.isArray(_fichaAvailModal.jornada_global) ? _fichaAvailModal.jornada_global.slice() : []);
      return (segs||[]).map(s=> s==='mañana' ? 'manana' : s);
    };
    const items = [];
    const dayLabels = { lunes:'Lunes', martes:'Martes', miercoles:'Miércoles', jueves:'Jueves', viernes:'Viernes', sabado:'Sábado', domingo:'Domingo' };
    daysOrder.forEach(day => {
      if (allowedDias.length && !allowedDias.includes(day)) return;
      const segs = getSegs(day);
      let segsText = '—';
      const hasM = segs.includes('manana');
      const hasT = segs.includes('tarde');
      const hasN = segs.includes('noche');
      if (segs.length) {
        const parts = [];
        if (hasM) parts.push(prettySeg('manana'));
        if (hasT) parts.push(prettySeg('tarde'));
        if (hasN) parts.push(prettySeg('noche'));
        segsText = parts.join(', ');
      }
      const label = dayLabels[day] || (day.charAt(0).toUpperCase()+day.slice(1));
      const isSel = selectedDay === day;
      items.push(`<span class="cc-av-item" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:2px 8px;${isSel?'font-weight:800;':'font-weight:600;'}">${label}: ${segsText}</span>`);
    });
    wrap.innerHTML = `<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">${items.join(' ')||'<span style="opacity:.7">Sin disponibilidad</span>'}</div>`;
  };

  // Abre el modal y conecta todos los comportamientos
  const openNuevaClaseModal = async () => {
    const selFicha = document.getElementById('fichaNuevaClase');
    const fecha = document.getElementById('fechaNuevaClase');
    const hi = document.getElementById('horaInicioNuevaClase');
    const hf = document.getElementById('horaFinNuevaClase');
    const modalEl = document.getElementById('modalNuevaClase');

    // Poblar fichas y sincronizar con filtro superior
    if (selFicha) buildFichaOptions(selFicha);

    // Si sigue vacío, intentar carga diferida desde endpoint y, si falla, desde eventos
    if (selFicha && selFicha.options.length <= 1) {
      try {
        const u = new URL('/', window.location.origin);
        u.searchParams.set('page','calendario_obtener_fichas');
        const r = await fetch(u.toString(), { credentials:'same-origin' });
        if (r.ok) {
          try {
            const data = await r.json();
            if (Array.isArray(data) && data.length > 0) {
              try { window.fichasDisponibles = data.slice(); } catch(_) {}
              buildFichaOptions(selFicha);
            }
          } catch(_) {}
        }
        // Fallback adicional: si aún vacío, traer fichas del facilitador autenticado
        if (selFicha.options.length <= 1) {
          try {
            const uF = new URL('/', window.location.origin);
            uF.searchParams.set('page','facilitadorficha');
            const rF = await fetch(uF.toString(), { credentials:'same-origin' });
            if (rF.ok) {
              try {
                const dataF = await rF.json();
                if (Array.isArray(dataF) && dataF.length > 0) {
                  try { window.fichasDisponibles = dataF.slice(); } catch(_) {}
                  buildFichaOptions(selFicha);
                }
              } catch(_) {}
            }
          } catch(_) {}
        }
        if (selFicha.options.length <= 1 && Array.isArray(window.lastEventos) && window.lastEventos.length) {
          const set = new Map();
          window.lastEventos.forEach(ev => {
            const ex = ev.extendedProps || {};
            const id = ex.ficha_id != null ? String(ex.ficha_id) : '';
            const codigo = ex.ficha_codigo != null ? String(ex.ficha_codigo) : (ex.codigo != null ? String(ex.codigo) : '');
            const nombre = ex.ficha_nombre != null ? String(ex.ficha_nombre) : '';
            const key = id || codigo;
            if (key && !set.has(key)) set.set(key, { id, codigo, nombre });
          });
          if (set.size) {
            try { window.fichasDisponibles = Array.from(set.values()); } catch(_) {}
            buildFichaOptions(selFicha);
          }
        }
      } catch(_) {}
    }

    // Flatpickr (usa el contenedor del modal para estilos)
    if (window.flatpickr) {
      window.flatpickr.localize(window.flatpickr.l10ns.es || {});
      const container = modalEl;
      if (fecha && !fecha._flatpickr) window.flatpickr(fecha, { dateFormat:'Y-m-d', altInput:true, altFormat:'d/m/Y', allowInput:true, disableMobile:true, appendTo: container });
      const initTime = (el) => { if (el && !el._flatpickr) window.flatpickr(el, { enableTime:true, noCalendar:true, time_24hr:false, minuteIncrement:5, dateFormat:'H:i', altInput:true, altFormat:'h:i K', appendTo: container }); };
      initTime(hi); initTime(hf);
    }

    // Color chips: seleccionar/deseleccionar y sincronizar con input color
    const colorInput = document.getElementById('colorNuevaClase');
    const chipsWrap = document.getElementById('newclass-color-chips');
    const updateStatus = (hex, selectedChip) => {
      // eliminar cualquier texto de estado si existe
      const st = document.getElementById('newclass-color-status'); if (st) st.remove();
      // Marcar visualmente el chip seleccionado
      if (chipsWrap) {
        chipsWrap.querySelectorAll('.cc-color').forEach(btn => btn.classList.remove('is-selected'));
        if (selectedChip) selectedChip.classList.add('is-selected');
      }
      // Brillo suave en el input color con el tono seleccionado
      if (colorInput) {
        const v = (hex && hex.startsWith('#')) ? hex : (hex ? ('#'+hex) : '#3b82f6');
        colorInput.style.boxShadow = `0 0 0 3px ${v}33`;
        colorInput.style.borderColor = v;
      }
    };
    const defaultHex = '#3b82f6';
    if (chipsWrap && !chipsWrap.dataset.bound) {
      chipsWrap.dataset.bound = '1';
      chipsWrap.addEventListener('click', (e) => {
        const btn = e.target.closest('.cc-color');
        if (!btn) return;
        const hex = String(btn.getAttribute('data-color') || '').trim();
        // Toggle: si ya está seleccionado, deseleccionar y volver a default
        const isSelected = btn.classList.contains('is-selected');
        if (isSelected) {
          btn.classList.remove('is-selected');
          if (colorInput) colorInput.value = defaultHex;
          updateStatus(defaultHex, null);
        } else {
          if (colorInput && /^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(hex.replace('#',''))) {
            colorInput.value = hex.startsWith('#') ? hex : ('#'+hex);
            updateStatus(colorInput.value, btn);
          }
        }
      });
    }
    // Cambio manual del input color limpia selección de chips y actualiza estado
    if (colorInput && !colorInput.dataset.bound) {
      colorInput.dataset.bound = '1';
      colorInput.addEventListener('input', () => {
        const v = colorInput.value || defaultHex;
        if (chipsWrap) chipsWrap.querySelectorAll('.cc-color').forEach(btn => btn.classList.remove('is-selected'));
        updateStatus(v, null);
      });
      // Inicializar estado al abrir
      updateStatus(colorInput.value || defaultHex, null);
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
      // Impedir seleccionar días pasados
      try {
        if (fpDate) {
          const today = new Date(); today.setHours(0,0,0,0);
          fpDate.set('minDate', today);
        }
      } catch(_) {}
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
            // limpiar estilos visuales del input color
            const ci = document.getElementById('colorNuevaClase');
            if (ci) { ci.style.boxShadow = ''; ci.style.borderColor = ''; }
          });
        }
        // Bind de envío del formulario (crear clase)
        const form = document.getElementById('formNuevaClase');
        const doCreate = async () => {
            const fichaEl = document.getElementById('fichaNuevaClase');
            const aulaEl  = document.getElementById('aulaNuevaClase');
            const colorEl = document.getElementById('colorNuevaClase');
            const readDate = (inp) => {
              if (!inp) return '';
              if (inp._flatpickr && inp._flatpickr.selectedDates && inp._flatpickr.selectedDates[0]) {
                const d = inp._flatpickr.selectedDates[0];
                const yy=d.getFullYear(); const mm=String(d.getMonth()+1).padStart(2,'0'); const dd=String(d.getDate()).padStart(2,'0');
                return `${yy}-${mm}-${dd}`;
              }
              return (inp.value||'').trim();
            };
            const readTime = (inp) => {
              if (!inp) return '';
              if (inp._flatpickr && inp._flatpickr.input) return (inp._flatpickr.input.value||'').trim();
              return (inp.value||'').trim();
            };
            const fechaStr = readDate(fecha);
            const hiStr = readTime(hi);
            const hfStr = readTime(hf);
            const fidRaw = fichaEl ? fichaEl.value : '';
            if (!fidRaw) { toast('Seleccione una ficha'); return; }
            const fid = resolveFichaId(fidRaw);
            if (!fechaStr || !hiStr || !hfStr) { toast('Complete fecha y horas'); return; }
            const toIso = (d,t) => `${d}T${t.length===5?t+':00':t}`;
            const start = new Date(toIso(fechaStr, hiStr));
            const end   = new Date(toIso(fechaStr, hfStr));
            if (!(start instanceof Date) || isNaN(start)) { toast('Fecha de inicio inválida'); return; }
            if (!(end instanceof Date)   || isNaN(end))   { toast('Fecha de fin inválida'); return; }
            if (end <= start) { toast('La hora fin debe ser mayor a la de inicio'); return; }
            const fetchAvailLocal = async (fidL) => {
              try {
                const key = String(fidL);
                window._fichaAvailCache = window._fichaAvailCache || new Map();
                if (window._fichaAvailCache.has(key)) return window._fichaAvailCache.get(key);
                const r = await fetch(`/?page=calcolab_disponibilidad_ficha&ficha_id=${encodeURIComponent(key)}`, { credentials:'same-origin' });
                if (!r.ok) return null; const data = await r.json();
                window._fichaAvailCache.set(key, data); return data;
              } catch { return null; }
            };
            const avail = await fetchAvailLocal(fid);
            if (avail) {
              const dayName = ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'][start.getDay()] || '';
              const dias = Array.isArray(avail.dias) ? avail.dias : [];
              if (dias.length && !dias.includes(dayName)) { toast(`La ficha no opera el ${dayName}`); return; }
              const segsDay = avail.jornada_por_dia && avail.jornada_por_dia[dayName];
              let segs = Array.isArray(segsDay) ? segsDay.slice() : (Array.isArray(avail.jornada_global) ? avail.jornada_global.slice() : []);
              segs = (segs||[]).map(s => s==='mañana' ? 'manana' : s);
              const segTo = (s)=> s==='manana'?['06:00','12:00']: s==='tarde'?['12:00','18:00']: s==='noche'?['18:00','22:00']: null;
              let ranges = segs.map(segTo).filter(Boolean);
              if (segs.includes('manana') && segs.includes('tarde')) ranges = [['08:00','17:00']];
              if (ranges.length) {
                const fmt = (d)=> `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
                const sHM = fmt(start), eHM = fmt(end);
                const inRange = ranges.some(([a,b]) => sHM>=a && eHM<=b);
                if (!inRange) { toast('Fuera de la jornada permitida para ese día'); return; }
              }
            }
            const aula  = (aulaEl?.value || '').trim();
            const color = (colorEl?.value || '#3b82f6').trim();
            const estado = 'programado';
            const titulo = 'Clase';
            const fd = new FormData();
            fd.append('ficha_id', fid);
            fd.append('titulo', titulo);
            fd.append('fecha_inicio', `${fechaStr} ${hiStr.length===5?hiStr+':00':hiStr}`);
            fd.append('fecha_fin', `${fechaStr} ${hfStr.length===5?hfStr+':00':hfStr}`);
            fd.append('aula', aula);
            fd.append('color', color);
            fd.append('estado', estado);
            const url = new URL('/', window.location.origin); url.searchParams.set('page','calendario_crear');
            const btn = document.getElementById('btnCrearClase') || form.querySelector('[type="submit"]'); if (btn) { btn.disabled = true; btn.dataset.prevText = btn.textContent; btn.textContent = 'Guardando…'; }
            try {
              const r = await fetch(url.toString(), { method:'POST', credentials:'same-origin', body: fd });
              const ok = r.ok; let j=null; try { j = await r.json(); } catch(_){ }
              if (!ok || (j && j.error)) { toast(j?.error || 'No se pudo crear la clase'); return; }
              toast('Clase creada');
              // No recargar todo - el evento ya se agregó localmente
              // try { cal.refetchEvents(); } catch(_){ }
              if (window.bootstrap && bootstrap.Modal) { try { bootstrap.Modal.getOrCreateInstance(modalEl).hide(); } catch(_){} }
              else { modalEl.setAttribute('aria-hidden','true'); modalEl.classList.remove('show'); modalEl.style.display='none'; }
            } catch(_) { toast('Error de red al crear clase'); }
            finally { if (btn) { btn.disabled = false; btn.textContent = btn.dataset.prevText || 'Guardar'; } }
        };
        if (form && !form.dataset.boundSubmit) {
          form.dataset.boundSubmit = '1';
          form.addEventListener('submit', async (e) => { e.preventDefault(); await doCreate(); });
        }
        const btnCrear = document.getElementById('btnCrearClase');
        if (btnCrear && !btnCrear.dataset.boundClick) {
          btnCrear.dataset.boundClick = '1';
          btnCrear.addEventListener('click', async () => { await doCreate(); });
        }
      } else {
        modalEl.setAttribute('aria-hidden', 'false'); modalEl.style.display = 'block'; modalEl.classList.add('show');
      }
    }
  };

  // Botón para abrir el modal
  const btnNueva = document.getElementById('btnNuevaClase');
  btnNueva?.addEventListener('click', openNuevaClaseModal);

  // === Deep link desde Dashboard: abrir modal y preseleccionar ficha/fecha ===
  try {
    const qs = new URLSearchParams(window.location.search);
    const want = qs.get('newclass') || qs.get('openModal');
    // Fallback por sessionStorage (Dashboard puede setear cal_newclass)
    let payload = null;
    try { const raw = sessionStorage.getItem('cal_newclass'); if (raw) { payload = JSON.parse(raw); /* NO remover aún */ } } catch(_) {}
    const fichaParam = (qs.get('ficha_id') || qs.get('ficha') || qs.get('ficha_codigo') || (payload && (payload.ficha_id||payload.ficha||payload.ficha_codigo)) || '').toString();
    // Abrir si: flag explicita o simplemente viene una ficha por URL/storage
    if ((want && (want === '1' || /nueva|true|yes/i.test(String(want)))) || fichaParam || payload) {
      const fechaParam = (qs.get('fecha') || (payload && payload.fecha) || '').toString();
      const hiParam = (qs.get('hi') || qs.get('hora_inicio') || (payload && (payload.hi||payload.hora_inicio)) || '').toString();
      const hfParam = (qs.get('hf') || qs.get('hora_fin') || (payload && (payload.hf||payload.hora_fin)) || '').toString();
      // Abrir modal
      openNuevaClaseModal();
      // Retry loop hasta que las opciones estén listas
      let tries = 0; const MAX = 16; // ~16*150ms ~ 2.4s
      let applied = false;
      const attempt = () => {
        try {
          const sel = document.getElementById('fichaNuevaClase');
          if (sel && fichaParam) {
            // Asegurar opciones: si casi vacío y ya tenemos window.fichasDisponibles, repoblar
            if (sel.options.length <= 1 && Array.isArray(window.fichasDisponibles) && window.fichasDisponibles.length) {
              try { buildFichaOptions(sel); } catch(_) {}
            }
            let set = false;
            if (Array.from(sel.options).some(o => o.value == fichaParam)) { sel.value = fichaParam; set = true; }
            if (!set) {
              const opt = Array.from(sel.options).find(o => (o.textContent||'').toLowerCase().includes(fichaParam.toLowerCase()));
              if (opt) { sel.value = opt.value; set = true; }
            }
            // Si aún no existe, inyectar opción temporal para no dejar vacío
            if (!set) {
              const tmp = document.createElement('option'); tmp.value = fichaParam; tmp.textContent = `Ficha ${fichaParam}`; sel.appendChild(tmp); sel.value = fichaParam; set = true;
            }
            if (set) sel.dispatchEvent(new Event('change'));
          }
          const fechaEl = document.getElementById('fechaNuevaClase');
          const hiEl = document.getElementById('horaInicioNuevaClase');
          const hfEl = document.getElementById('horaFinNuevaClase');
          if (fechaParam && fechaEl) { if (fechaEl._flatpickr) fechaEl._flatpickr.setDate(fechaParam, true); else fechaEl.value = fechaParam; }
          if (hiParam && hiEl) { if (hiEl._flatpickr) hiEl._flatpickr.setDate(hiParam, true); else hiEl.value = hiParam; }
          if (hfParam && hfEl) { if (hfEl._flatpickr) hfEl._flatpickr.setDate(hfParam, true); else hfEl.value = hfParam; }
          applied = true;
        } catch(_) {}
        finally {
          if (++tries < MAX && !applied) setTimeout(attempt, 150);
          else {
            // Remover payload solo cuando aplicamos o agotamos reintentos
            try { if (payload) sessionStorage.removeItem('cal_newclass'); } catch(_) {}
          }
        }
      };
      setTimeout(attempt, 150);
    }
  } catch(_) {}
});
