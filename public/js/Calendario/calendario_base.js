
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
              const root = document.getElementById('calendario');
              const targets = [];
              root?.querySelectorAll('.fc-daygrid .fc-day-today .fc-daygrid-day-frame')?.forEach(n=>targets.push(n));
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
        if (window.profesorFiltro) url.searchParams.set('profesor_id', window.profesorFiltro);
        if (window.estadoFiltro) url.searchParams.set('estado', window.estadoFiltro);
        if (window.fichaFiltro) url.searchParams.set('ficha', window.fichaFiltro);

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
                // no forzar display para respetar render según vista
                classNames: cls,
                extendedProps: Object.assign({}, e.extendedProps || {}, { estado })
              };
            });
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

  // Conectar barra de navegación personalizada (como colaborativo)
  const t = document.getElementById('cal-title');
  const btnPrev = document.getElementById('cal-prev');
  const btnNext = document.getElementById('cal-next');
  const refreshTitle = () => { if (t) t.textContent = (cal.view?.title || '').toLowerCase(); };
  btnPrev?.addEventListener('click', () => { cal.prev(); refreshTitle(); });
  btnNext?.addEventListener('click', () => { cal.next(); refreshTitle(); });
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
          const d1 = view?.activeStart || view?.currentStart || new Date();
          const d2 = view?.activeEnd   || view?.currentEnd   || new Date();
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
          url.searchParams.set('fecha_fin', toYMD(new Date(d2.getTime() - 24*60*60*1000))); // fin inclusivo
          if (window.estadoFiltro)   url.searchParams.set('estado', String(window.estadoFiltro));
          if (window.profesorFiltro) url.searchParams.set('profesor_id', String(window.profesorFiltro));
          // Abrir en nueva pestaña para descargar .xls
          window.open(url.toString(), '_blank');
        } catch (_) { /* noop */ }
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
  cal.on('datesSet', (arg) => {
    applyTimeGridTweaks();
    try {
      const vt = arg.view?.type || cal.view?.type || '';
      const isMonth = vt === 'dayGridMonth';
      // En Mes: permitir mover entre días, NO redimensionar
      cal.setOption('eventDurationEditable', !isMonth);
      cal.setOption('eventResizableFromStart', !isMonth);
      // Drag sigue activo en todas, pero validaremos por estado en handlers
    } catch(_) {}
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
  const toast = (msg) => {
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
      clearTimeout(toast._h);
      toast._h = setTimeout(()=>{ t.style.opacity = '0'; }, 1800);
    } catch(_){ /* noop */ }
  };
  cal.on('eventResize', async (info)=>{
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
    if (res.ok) {
      toast('Duración actualizada'); try { cal.refetchEvents(); } catch(_){}
    } else { info.revert(); toast(res.error || 'No se pudo guardar el cambio'); }
  });
  cal.on('eventDrop',   async (info)=>{
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
    if (res.ok) {
      const d = info.event.start; const pad=(n)=>String(n).padStart(2,'0');
      toast(`Clase movida a ${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`);
      try { cal.refetchEvents(); } catch(_){}
    } else { info.revert(); toast(res.error || 'No se pudo guardar el cambio'); }
  });

  // Bloquear selección fuera de disponibilidad si ya se eligió ficha en el modal (sólo en semana/día)
  cal.setOption('selectAllow', (selInfo) => {
    const vt = cal.view?.type || '';
    if (vt !== 'timeGridWeek' && vt !== 'timeGridDay') return true;
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
    const matches = [];
    for (const f of all) {
      const fid = String(f.id ?? f.value ?? ''); if (!fid) continue;
      const avail = await fetchAvail(fid);
      if (!avail) continue;
      const dias = Array.isArray(avail.dias) ? avail.dias : [];
      if (!dias.length || dias.includes(dayName)) { matches.push({ id: fid, codigo: f.codigo ?? f.numero ?? f.numero_ficha ?? '', nombre: f.nombre ?? '' }); }
    }
    // Rellenar select solo con coincidentes
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
              if (st === 'presente') counts.presente++;
              else if (st === 'falla' || st === 'ausente') counts.ausente++;
              else if (st === 'tardanza' || st === 'tarde') counts.tardanza++;
              else if (st === 'justificada' || st === 'justificado') counts.justificada++;
            });

            const frag = document.createDocumentFragment();
            const summary = document.createElement('div');
            summary.className = 'asist-summary';
            summary.innerHTML = `
              <span class="asist-pill presente">Presentes: ${counts.presente}</span>
              <span class="asist-pill ausente">Ausentes: ${counts.ausente}</span>
              <span class="asist-pill tardanza">Tarde: ${counts.tardanza}</span>
              <span class="asist-pill justificada">Justificados: ${counts.justificada}</span>
            `;
            frag.appendChild(summary);

            const listWrap = document.createElement('div');
            listWrap.className = 'asist-list';
            withRecords.forEach(it => {
              const nombre = [it.nombres, it.apellidos].filter(Boolean).join(' ') || it.estudiante || it.nombre || 'Estudiante';
              const estadoRaw = (it.estado || 'sin registro').toLowerCase();
              const estadoClass = estadoRaw === 'falla' ? 'ausente' : (estadoRaw === 'tarde' ? 'tardanza' : estadoRaw);
              const initials = nombre.split(' ').map(w=>w[0]).filter(Boolean).slice(0,2).join('').toUpperCase();
              const item = document.createElement('div');
              item.className = 'asist-item';
              item.innerHTML = `
                <div class="asist-avatar">${initials}</div>
                <div class="asist-name">${nombre}</div>
                <div class="asist-chip ${estadoClass}">${estadoRaw}</div>
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
            const start = new Date(toDT(fecha, hi).replace(' ', 'T'));
            const end   = new Date(toDT(fecha, hf).replace(' ', 'T'));
            if (end <= start) { toast('La hora fin debe ser mayor a la de inicio'); return; }
            // Validar disponibilidad (inline para evitar dependencias de scope)
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
            // Armar FormData según backend
            const fd = new FormData();
            fd.append('ficha_id', fid);
            fd.append('titulo', titulo);
            fd.append('fecha_inicio', `${fecha} ${hi.length===5?hi+':00':hi}`);
            fd.append('fecha_fin', `${fecha} ${hf.length===5?hf+':00':hf}`);
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
              try { cal.refetchEvents(); } catch(_){ }
              inst.hide();
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
