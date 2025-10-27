document.addEventListener('DOMContentLoaded', () => {
  // Elementos del DOM
  const calEl = document.getElementById('calcolab-calendar');
  const listEl = document.getElementById('calcolab-instructors');
  const $estado = document.getElementById('calcolab-filter-estado');
  const $ficha = document.getElementById('calcolab-filter-ficha');
  const $export = document.getElementById('calcolab-export');
  const $ccTotal = document.getElementById('cc-total');
  const $ccCurso = document.getElementById('cc-curso');
  const $ccSusp = document.getElementById('cc-susp');
  const $ccFin = document.getElementById('cc-fin');
  
  if (!calEl || !listEl || !window.FullCalendar) {
    console.error('Elementos del DOM o FullCalendar no disponibles');
    return;
  }

  // Estado UI
  const state = {
    instructors: [],
    selected: new Set(),
    pollHandle: null,
    eventsRaw: [], // últimos eventos cargados del backend (rango visible)
  };

  // Helpers
  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  function debounce(fn, wait) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), wait); }; }

  // Render lista instructores
  function renderInstructors() {
    listEl.innerHTML = '';
    const wrap = document.createElement('div');
    // Search
    const search = document.createElement('input');
    search.type = 'text';
    search.placeholder = 'Buscar instructor';
    search.className = 'input-buscar';
    search.style.cssText = 'width:100%;margin:0 0 10px;';
    wrap.appendChild(search);

    const ul = document.createElement('div');
    ul.setAttribute('role','list');
    ul.style.display = 'grid';
    ul.style.gap = '8px';
    wrap.appendChild(ul);

    function itemHtml(p) {
      const checked = state.selected.has(p.id);
      return `
        <label class="tarjeta tarjeta--mini" style="display:flex;align-items:center;gap:10px;padding:8px;cursor:pointer;">
          <input type="checkbox" class="chk-inst" data-id="${p.id}" ${checked? 'checked':''} />
          <span class="cc-avatar">${(p.iniciales||'IN')}</span>
          <span>${escapeHtml(p.nombre||'')}</span>
        </label>`;
    }


  // Navegación y título del rango
  const navWrap = document.getElementById('calcolab-nav');
  const titleEl = document.getElementById('calcolab-title');
  function updateTitle(){
    try {
      const v = calendar.view;
      // Aprovechar title de FullCalendar según la vista
      titleEl.textContent = v?.title || '';
    } catch(_) { titleEl.textContent = ''; }
  }
  updateTitle();
  if (navWrap) {
    navWrap.addEventListener('click', (e)=>{
      const btn = e.target.closest('button[data-nav]');
      if (!btn) return;
      const a = btn.getAttribute('data-nav');
      if (a === 'today') calendar.today();
      else if (a === 'prev') calendar.prev();
      else if (a === 'next') calendar.next();
      updateTitle();
    });
  }
  // Actualizar título cuando cambian fechas (también recarga eventos)
  calendar.on('datesSet', updateTitle);

    function applyFilter() {
      const q = (search.value||'').trim().toLowerCase();
      const data = state.instructors.filter(p => !q || String(p.nombre||'').toLowerCase().includes(q));
      ul.innerHTML = data.map(itemHtml).join('');
    }
    search.addEventListener('input', debounce(applyFilter, 150));

    listEl.appendChild(wrap);
    applyFilter();

    // cambios de selección: recalcular Set a partir de checkboxes
    ul.addEventListener('change', () => {
      const checked = Array.from(ul.querySelectorAll('.chk-inst:checked')).map(el => parseInt(el.dataset.id, 10));
      state.selected = new Set(checked);
      reloadEvents();
    });
  }

  // ====== FUNCIONES AUXILIARES ======

  // ====== INICIALIZACIÓN DEL CALENDARIO ======
  
  const calendar = new FullCalendar.Calendar(calEl, {
    initialView: 'dayGridMonth',
    height: 'auto',
    headerToolbar: false,
    locale: 'es',
    firstDay: 1, // Lunes como primer día de la semana
    editable: true,
    selectable: false,
    selectMirror: true,
    eventResizableFromStart: true,
    hiddenDays: [0], // Ocultar domingos
    allDaySlot: false,
    slotMinTime: '06:00:00',
    slotMaxTime: '22:00:00',
    slotDuration: '01:00:00',
    dayMaxEvents: 2, // Mostrar máximo 2 eventos por día
    dayMaxEventRows: 2, // Máximo de filas de eventos por día
    dayPopoverFormat: { 
      month: 'long', 
      day: 'numeric', 
      year: 'numeric' 
    },
    moreLinkContent: function(args) {
      // Personalizar el texto del enlace "+X más"
      return { html: `+${args.num} más` };
    },
    moreLinkClick: function(info) {
      // Al hacer clic en "+X más", ir a la vista diaria de ese día
      calendar.changeView('timeGridDay', info.date);
    },
    slotLabelFormat: {
      hour: '2-digit',
      minute: '2-digit',
      hour12: true
    },
    views: {
      timeGridWeek: {
        dayHeaderFormat: { weekday: 'short', day: 'numeric' },
        allDaySlot: false,
        slotMinTime: '06:00:00',
        slotMaxTime: '22:00:00',
        slotDuration: '01:00:00',
        slotLabelInterval: '01:00',
        slotLabelFormat: {
          hour: '2-digit',
          minute: '2-digit',
          hour12: true
        }
      },
      dayGridMonth: {
        // Configuración específica para la vista mensual
      }
    },
    eventContent: function(arg){
      try {
        const p = arg.event.extendedProps || {};
        const instr = p.profesor_nombre ? String(p.profesor_nombre) : '';
        const ficha = (p.ficha_numero || p.ficha_nombre) ? String(p.ficha_numero || p.ficha_nombre) : '';
        const aula  = p.aula ? String(p.aula) : '';
        const raw   = (p.estado||'').toString().toLowerCase().replace(/\s+/g,'_');
        const now = new Date();
        const st  = arg.event.start ? new Date(arg.event.start) : null;
        const en  = arg.event.end ? new Date(arg.event.end) : null;
        // Derivar estado visual
        let visState = raw || 'programado';
        if (raw === 'suspendido') {
          visState = 'suspendido';
        } else if (st && en && now >= st && now <= en) {
          visState = 'en_curso';
        } else if (en && now > en) {
          visState = 'finalizado';
        }
        let colorCls = 'cc-dot-blue';
        if (visState === 'en_curso') colorCls = 'cc-dot-green';
        else if (visState === 'suspendido') colorCls = 'cc-dot-red';
        else if (visState === 'finalizado') colorCls = 'cc-dot-black';
        // Color del chip: por defecto #00304D, si el evento trae color usarlo
        const bg = (arg.event.backgroundColor || p.color || '#00304D');
        const parts = [];
        if (instr) parts.push(instr);
        if (ficha) parts.push(ficha);
        if (aula) parts.push(aula);
        const text = parts.length ? parts.join(' ') : (arg.event.title || '');
        return { html: '<div class="cc-evrow"><span class="cc-ev-dot '+colorCls+'"></span><span class="cc-ev" style="background:'+ (bg || '#00304D') +'"><span class="cc-ev-text">'+ escapeHtml(text) +'</span></span></div>' };
      } catch (_) {
        return { html: '<div class="cc-evrow"><span class="cc-ev-dot cc-dot-blue"></span><span class="cc-ev" style="background:#00304D"><span class="cc-ev-text">'+ escapeHtml(arg.event.title || '') +'</span></span></div>' };
      }
    },
    events: [],
    datesSet: debounce(() => reloadEvents(), 50),
  });
  calendar.render();
  function ensureDetailModal(){
    let el = document.getElementById('calcolab-modal-detalle');
    if (el) return el;
    el = document.createElement('div');
    el.className = 'modal fade';
    el.id = 'calcolab-modal-detalle';
    el.tabIndex = -1;
    el.innerHTML = `
      <div class="modal-dialog modal-lg" style="max-width:980px;">
        <div class="modal-content" style="border:none;border-radius:12px;">
          <div class="modal-header" style="background:#00304D;justify-content:center;border-radius:12px;margin:16px 16px 0 16px;padding:12px 18px;position:relative;">
            <h5 class="modal-title" style="color:#fff;text-align:center;width:100%;margin:6px 0;font-weight:900;font-size:24px;letter-spacing:0.4px;">Detalle del Evento</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar" style="position:absolute;right:18px;top:50%;transform:translateY(-50%);"></button>
          </div>
          <div class="modal-body">
            <div id="calcolab-detalle-body"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal" style="background:#39A900;color:#fff;border-radius:10px;">Cerrar</button>
          </div>
        </div>
      </div>`;
    document.body.appendChild(el);
    return el;
  }
  function fmtDateTime(v){
    try{ const d = new Date(v); return d.toLocaleString('es-CO', { dateStyle:'medium', timeStyle:'short' }); }catch{ return String(v||''); }
  }
  function pick(obj){
    for (let i=1;i<arguments.length;i++){ const k = arguments[i]; const val = obj && obj[k]; if (val!=null && String(val).trim()!=='') return String(val).trim(); }
    return '';
  }
  calendar.on('eventClick', function(info){
    try{
      info.jsEvent && info.jsEvent.preventDefault && info.jsEvent.preventDefault();
      const modal = ensureDetailModal();
      const body = modal.querySelector('#calcolab-detalle-body');
      const ev = info.event || {};
      const ex = ev.extendedProps || {};
      const titulo = ev.title || ex.titulo || 'Evento';
      const inicio = ev.start || ex.fecha_inicio || ex.inicio;
      const fin = ev.end || ex.fecha_fin || ex.fin;
      const aula = pick(ex, 'aula','salon','sala');
      const profesor = pick(ex, 'profesor_nombre','docente','profesor','creado_por','creador');
      const ficha = pick(ex, 'ficha_numero','ficha_codigo','codigo_ficha','ficha_nombre','ficha');
      const colegio = pick(ex, 'colegio_nombre','colegio');
      const estado = String(pick(ex, 'estado')).toLowerCase();
      const estadoBgMap = { 'finalizado':'#111827', 'en_curso':'#22c55e', 'suspendido':'#ef4444', 'programado':'#3b82f6' };
      const estadoBg = estadoBgMap[estado] || '#3b82f6';
      body.innerHTML = `
        <div style="display:grid;gap:12px;">
          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
            <div style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;box-shadow:0 2px 8px rgba(0,0,0,.04);text-align:center;">
              <small style="color:#6b7280;">Título</small>
              <div style="font-weight:800;">${titulo}</div>
            </div>
            <div style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;box-shadow:0 2px 8px rgba(0,0,0,.04);text-align:center;">
              <small style="color:#6b7280;">Ficha</small>
              <div style="font-weight:800;">${ficha||'—'}</div>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
            <div style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;box-shadow:0 2px 8px rgba(0,0,0,.04);text-align:center;">
              <small style="color:#6b7280;">Inicio</small>
              <div style="font-weight:800;">${fmtDateTime(inicio)}</div>
            </div>
            <div style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;box-shadow:0 2px 8px rgba(0,0,0,.04);text-align:center;">
              <small style="color:#6b7280;">Fin</small>
              <div style="font-weight:800;">${fmtDateTime(fin)}</div>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;">
            <div style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;box-shadow:0 2px 8px rgba(0,0,0,.04);text-align:center;">
              <small style="color:#6b7280;">Instructor</small>
              <div style="font-weight:800;">${profesor||'—'}</div>
            </div>
            <div style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;box-shadow:0 2px 8px rgba(0,0,0,.04);text-align:center;">
              <small style="color:#6b7280;">Aula</small>
              <div style="font-weight:800;">${aula||'—'}</div>
            </div>
            <div style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;box-shadow:0 2px 8px rgba(0,0,0,.04);text-align:center;">
              <small style="color:#6b7280;">Estado</small>
              <div><span style="display:inline-block;padding:4px 10px;border-radius:9999px;font-weight:800;color:#fff;background:${estadoBg};text-transform:lowercase;">${estado||'—'}</span></div>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:repeat(1,minmax(0,1fr));gap:12px;">
            <div style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;box-shadow:0 2px 8px rgba(0,0,0,.04);text-align:center;">
              <small style="color:#6b7280;">Colegio</small>
              <div style="font-weight:800;">${colegio||'—'}</div>
            </div>
          </div>
        </div>`;
      if (window.bootstrap && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
      } else {
        modal.style.display = 'block'; modal.classList.add('show');
      }
    } catch(_){}
  });

  calendar.on('eventMouseEnter', function(info){
    try{
      const el = info.el; if (!el) return;
      el._prevTrf = el.style.transform || '';
      el._prevSh  = el.style.boxShadow || '';
      el.style.transition = 'transform .15s ease, box-shadow .15s ease';
      el.style.transform = 'scale(1.03) translateY(-2px)';
      el.style.boxShadow = '0 8px 20px rgba(0,0,0,.18)';
    }catch(_){}
  });
  calendar.on('eventMouseLeave', function(info){
    try{
      const el = info.el; if (!el) return;
      el.style.transform = el._prevTrf || '';
      el.style.boxShadow = el._prevSh || '';
    }catch(_){}
  });

  // ====== Edición: mover/redimensionar con reglas similares al base ======
  // Toast minimalista
  function toast(msg){
    try{
      let t = document.getElementById('calcolab-toast');
      if (!t){
        t = document.createElement('div'); t.id='calcolab-toast';
        Object.assign(t.style, { position:'fixed', bottom:'16px', left:'50%', transform:'translateX(-50%)', background:'#111827', color:'#fff', padding:'10px 14px', borderRadius:'10px', boxShadow:'0 6px 20px rgba(0,0,0,.2)', fontWeight:'800', zIndex:'100000', transition:'opacity .2s ease', opacity:'0', pointerEvents:'none' });
        document.body.appendChild(t);
      }
      t.textContent = String(msg||''); void t.offsetHeight; t.style.opacity='1'; clearTimeout(toast._h); toast._h=setTimeout(()=>{ t.style.opacity='0'; }, 1600);
    }catch(_){}
  }
  const toMySQL = (d) => {
    if (!d) return null; const pad=n=>String(n).padStart(2,'0');
    const yy=d.getFullYear(), mm=pad(d.getMonth()+1), dd=pad(d.getDate());
    const hh=pad(d.getHours()), mi=pad(d.getMinutes()), ss=pad(d.getSeconds());
    return `${yy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
  };
  async function persistEventUpdate(ev){
    try{
      const url = new URL('/', window.location.origin); url.searchParams.set('page','calendario_actualizar');
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      const payload = {
        id: ev.id,
        fecha_inicio: ev.start ? toMySQL(ev.start) : null,
        fecha_fin: ev.end ? toMySQL(ev.end) : null,
        titulo: ev.title || ev.extendedProps?.titulo || 'Clase',
        aula: ev.extendedProps?.aula || null,
        color: ev.backgroundColor || ev.extendedProps?.color || '#00304D'
      };
      const res = await fetch(url.toString(), { method:'POST', credentials:'same-origin', headers:{ 'Content-Type':'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(payload) });
      let data=null; try{ data=await res.json(); }catch{}
      return res.ok ? { ok:true } : { ok:false, error: (data && (data.error||data.message)) || 'Error' };
    }catch{ return { ok:false, error:'Error de red' }; }
  }
  const estadoOf = (ev)=> (ev.extendedProps?.estado || '').toString().toLowerCase();
  const isMovable = (ev)=>{ const st = estadoOf(ev); return st === 'programado' || st === 'suspendido'; };
  const startOfDay = (d)=> new Date(d.getFullYear(), d.getMonth(), d.getDate());
  const isPastDay = (d)=> startOfDay(d) < startOfDay(new Date());
  // Disponibilidad por ficha (cache)
  window._fichaAvailCache = window._fichaAvailCache || new Map();
  async function fetchAvail(fid){
    const key = String(fid||''); if (!key) return null;
    if (window._fichaAvailCache.has(key)) return window._fichaAvailCache.get(key);
    try{ const r = await fetch(`/?page=calcolab_disponibilidad_ficha&ficha_id=${encodeURIComponent(key)}`, { credentials:'same-origin' }); if (!r.ok) return null; const data = await r.json(); window._fichaAvailCache.set(key, data); return data; }catch{ return null; }
  }
  async function rangeAllowedForFicha(fid, start, end){
    const avail = await fetchAvail(fid); if (!avail) return { ok:true };
    const dayIdx = start.getDay(); const names=['domingo','lunes','martes','miercoles','jueves','viernes','sabado'];
    const dayName = names[dayIdx] || '';
    const dias = Array.isArray(avail.dias) ? avail.dias : [];
    if (dias.length && !dias.includes(dayName)) return { ok:false, reason:`La ficha no opera el ${dayName}` };
    const segsDay = avail.jornada_por_dia && avail.jornada_por_dia[dayName];
    let segs = Array.isArray(segsDay) ? segsDay.slice() : (Array.isArray(avail.jornada_global) ? avail.jornada_global.slice() : []);
    segs = (segs||[]).map(s => s==='mañana' ? 'manana' : s);
    const segTo = (s)=> s==='manana'?['06:00','12:00']: s==='tarde'?['12:00','18:00']: s==='noche'?['18:00','22:00']: null;
    let ranges = segs.map(segTo).filter(Boolean);
    if (segs.includes('manana') && segs.includes('tarde')) ranges = [['08:00','17:00']];
    if (!ranges.length) return { ok:true };
    const fmt=(d)=>`${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
    const sHM = fmt(start), eHM = fmt(end || start);
    const inRange = ranges.some(([a,b]) => sHM>=a && eHM<=b);
    return inRange ? { ok:true } : { ok:false, reason:'Fuera de la jornada permitida para ese día' };
  }
  calendar.on('eventResize', async (info)=>{
    const vt = calendar.view?.type || '';
    if (vt === 'dayGridMonth') { info.revert(); toast('En Mes no se puede cambiar la duración'); return; }
    if (!isMovable(info.event)) { info.revert(); toast('No se puede editar una clase en curso o finalizada'); return; }
    const s = info.event.start, e = info.event.end || info.event.start;
    if (isPastDay(s) || isPastDay(e)) { info.revert(); toast('No se puede ajustar a fechas pasadas'); return; }
    const fid = info.event.extendedProps?.ficha_id;
    if (fid) {
      const chek = await rangeAllowedForFicha(fid, s, e);
      if (!chek.ok) { info.revert(); toast(chek.reason || 'Horario no permitido'); return; }
    }
    const res = await persistEventUpdate(info.event);
    if (res.ok) { toast('Duración actualizada'); try{ reloadEvents(); }catch(_){} }
    else { info.revert(); toast(res.error || 'No se pudo guardar el cambio'); }
  });
  calendar.on('eventDrop', async (info)=>{
    const vt = calendar.view?.type || '';
    const st = estadoOf(info.event);
    if (st === 'en_curso' || st === 'finalizado') { info.revert(); toast('No se puede mover una clase en curso o finalizada'); return; }
    if (vt !== 'dayGridMonth' && !isMovable(info.event)) { info.revert(); toast('Solo Programadas o Suspendidas se pueden mover'); return; }
    const s2 = info.event.start; const e2 = info.event.end || info.event.start;
    if (isPastDay(s2) || isPastDay(e2)) { info.revert(); toast('No se puede mover a fechas pasadas'); return; }
    const fid = info.event.extendedProps?.ficha_id;
    if (fid) {
      const chek = await rangeAllowedForFicha(fid, s2, e2);
      if (!chek.ok) { info.revert(); toast(chek.reason || 'Día/hora no permitido'); return; }
    }
    const res = await persistEventUpdate(info.event);
    if (res.ok) {
      const d = info.event.start; const pad=(n)=>String(n).padStart(2,'0');
      toast(`Clase movida a ${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`);
      try{ reloadEvents(); }catch(_){ }
    } else { info.revert(); toast(res.error || 'No se pudo guardar el cambio'); }
  });

  const btnGotoToday = document.getElementById('calcolab-today');
  if (btnGotoToday) {
    btnGotoToday.addEventListener('click', () => {
      try {
        calendar.today();
        setTimeout(() => {
          const el = document.querySelector('.fc-daygrid-day.fc-day-today, .fc-timegrid-col.fc-day-today, .fc-day-today');
          if (el) {
            try { el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' }); } catch(_) {}
            try {
              el.animate(
                [
                  { transform: 'scale(1) translateY(0)', boxShadow: '0 0 0 0 rgba(34,197,94,0)' },
                  { transform: 'scale(1.02) translateY(-4px)', boxShadow: '0 0 0 8px rgba(34,197,94,0.18)' },
                  { transform: 'scale(1) translateY(0)', boxShadow: '0 0 0 0 rgba(34,197,94,0)' }
                ],
                { duration: 900, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' }
              );
            } catch(_) {}
          }
        }, 100);
      } catch (_) {}
    });
  }

  // Ajuste dinámico de alto/horario en vista semanal
  function isTodayInRange(start, end){
    try{
      const t = new Date(); t.setHours(0,0,0,0);
      const s = new Date(start); s.setHours(0,0,0,0);
      const e = new Date(end);   e.setHours(0,0,0,0);
      return t >= s && t < e;
    } catch(_) { return false; }
  }

  function adjustWeekSizing() {
    const view = calendar.view;
    if (!view) return;
    
    const container = document.getElementById('calcolab-calendar');
    if (!container) return;
    
    const isWeekView = view.type.includes('Week');
    const isDayView = view.type.includes('Day');
    
    // Calcular altura disponible
    const headerHeight = document.querySelector('.cc-calbar')?.offsetHeight || 0;
    const availableHeight = window.innerHeight - container.getBoundingClientRect().top - headerHeight - 40;
    
    if (isWeekView || isDayView) {
      // Para vistas semanales o diarias
      calendar.setOption('contentHeight', Math.max(600, availableHeight));
      
      // Ajustar horas visibles
      calendar.setOption('slotMinTime', '06:00:00');
      calendar.setOption('slotMaxTime', '22:00:00');
      
      // Asegurar que las columnas tengan el mismo ancho
      const headerCells = document.querySelectorAll('.fc-col-header-cell');
      if (headerCells.length > 0) {
        const dayWidth = `${(100 / headerCells.length).toFixed(2)}%`;
        headerCells.forEach(cell => {
          cell.style.width = dayWidth;
        });
      }
      
      // Ajustar el alto de las filas de tiempo
      const timeSlots = document.querySelectorAll('.fc-timegrid-slot');
      if (timeSlots.length > 0) {
        const slotHeight = `${(availableHeight - 100) / 16}px`; // Aprox. 16 horas visibles
        timeSlots.forEach(slot => {
          slot.style.height = slotHeight;
        });
      }
    } else {
      // Configuración para vista mensual
      calendar.setOption('contentHeight', 'auto');
    }
    
    // Forzar actualización de la vista
    setTimeout(() => {
      calendar.updateSize();
    }, 100);
  }

  // Configurar manejadores de eventos
  function setupEventHandlers() {
    // Reaccionar al cambio de fechas/vista
    calendar.on('datesSet', () => {
      adjustWeekSizing();
      
      // Asegurar que los encabezados de los días se mantengan visibles
      const headerCells = document.querySelectorAll('.fc-col-header-cell');
      headerCells.forEach(cell => {
        cell.style.position = 'sticky';
        cell.style.top = '0';
        cell.style.background = '#f8f9fa';
      });
      setTimeout(() => {
        const headerCells = document.querySelectorAll('.fc-col-header-cell');
        headerCells.forEach(cell => {
          cell.style.position = 'sticky';
          cell.style.top = '0';
          cell.style.background = '#f8f9fa';
          cell.style.zIndex = '10';
        });
      }, 500);
    });
  }
  
  // Inicialización
  setupEventHandlers();
  
  // (el arranque asíncrono se realiza más abajo con init())

  // Hook de tabs de vista
  const viewsWrap = document.getElementById('calcolab-views');
  if (viewsWrap) {
    viewsWrap.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-view]');
      if (!btn) return;
      const v = btn.getAttribute('data-view');
      try { calendar.changeView(v); } catch(_){}
      // Estado activo visual
      viewsWrap.querySelectorAll('button[data-view]').forEach(b=>{
        b.classList.toggle('active', b === btn);
        b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
      });
    });
  }

  // Cargar instructores y eventos
  init();

  async function init() {
    await loadInstructors();
    // Selección inicial vacía: sin filtro => se muestran TODAS las clases
    state.selected = new Set();
    renderInstructors();
    reloadEvents();
    setupPolling();
  }

  async function loadInstructors() {
    listEl.textContent = 'Cargando instructores...';
    try {
      const r = await fetch('/?page=calcolab_instructores', { credentials: 'same-origin' });
      if (!r.ok) {
        const txt = await r.text();
        throw new Error('HTTP '+r.status+' '+txt);
      }
      const data = await r.json();
      state.instructors = Array.isArray(data)? data : [];
      console.log('[calcolab] instructores:', state.instructors.length);
    } catch (e) {
      console.error('instructores', e);
      listEl.innerHTML = '<div class="text-danger">Error al cargar instructores. '+(e?.message||'')+'</div>';
    }
  }

  function getVisibleRange() {
    const v = calendar.view; // has currentStart/currentEnd (Date)
    const fmt = (d) => {
      const pad = (n)=>String(n).padStart(2,'0');
      const y=d.getFullYear(), m=pad(d.getMonth()+1), da=pad(d.getDate());
      const h=pad(d.getHours()), mi=pad(d.getMinutes()), s=pad(d.getSeconds());
      return `${y}-${m}-${da} ${h}:${mi}:${s}`;
    };
    return { start: fmt(v.currentStart), end: fmt(v.currentEnd) };
  }

  async function reloadEvents() {
    try {
      const { start, end } = getVisibleRange();
      const ids = Array.from(state.selected);
      const qs = new URLSearchParams({ start, end });
      if (ids.length) qs.set('instructores', ids.join(','));
      const est = norm($estado?.value);
      if (est) qs.set('estado', est);
      const fq = norm($ficha?.value);
      if (fq) qs.set('ficha', fq);
      const r = await fetch('/?page=calcolab_eventos&'+qs.toString(), { credentials: 'same-origin' });
      if (!r.ok) {
        const txt = await r.text();
        throw new Error('HTTP '+r.status+' '+txt);
      }
      const evs = await r.json();
      state.eventsRaw = Array.isArray(evs) ? evs : [];
      console.log('[calcolab] eventos(raw):', state.eventsRaw.length, {start, end, ids});
      applyAllFiltersAndRender();
      if (!evs || evs.length === 0) {
        // opcional: mostrar mensaje vacío superpuesto
        // se deja en consola para no ensuciar UI
      }
    } catch (e) {
      console.error('eventos', e);
      // Mensaje visual simple arriba del calendario
      const msgId = 'calcolab-msg';
      let msg = document.getElementById(msgId);
      if (!msg) { msg = document.createElement('div'); msg.id = msgId; calEl.parentElement.prepend(msg); }
      msg.textContent = 'Error cargando eventos: ' + (e?.message||'');
      msg.style.cssText = 'color:#dc2626;margin:4px 0;';
    }
  }

  function setupPolling() {
    if (state.pollHandle) clearInterval(state.pollHandle);
    state.pollHandle = setInterval(reloadEvents, 60000); // 60s
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) reloadEvents();
    });
  }

  function escapeHtml(s) { return String(s||'').replace(/[&<>"'/]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;'}[m])); }

  // Exponer para depuración
  window.calcolab = { calendar, state };

  // ====== HERO: filtros, contadores, exportar, nueva clase ======

  function norm(s){ return String(s||'').toLowerCase().trim(); }

  function applyAllFiltersAndRender(){
    // 1) partir de eventos del backend para el rango visible
    let data = state.eventsRaw.slice();
    // 2) filtro por instructores (si hay selección)
    if (state.selected && state.selected.size > 0){
      data = data.filter(ev => state.selected.has(Number(ev?.extendedProps?.profesor_id)));
    }
    // 3) filtro por estado
    const est = norm($estado?.value);
    if (est){
      const now = new Date();
      const visStateOf = (ev) => {
        const raw = norm(ev?.extendedProps?.estado).replace(/\s+/g,'_');
        const st = ev.start ? new Date(ev.start) : null;
        const en = ev.end ? new Date(ev.end) : null;
        if (raw === 'suspendido') return 'suspendido';
        if (st && en && now >= st && now <= en) return 'en_curso';
        if (en && now > en) return 'finalizado';
        return 'programado';
      };
      data = data.filter(ev => visStateOf(ev) === est);
    }
    // 4) filtro por ficha (buscador)
    const fq = norm($ficha?.value);
    if (fq){
      data = data.filter(ev => {
        const n = norm(ev?.extendedProps?.ficha_numero) || '';
        const fn = norm(ev?.extendedProps?.ficha_nombre) || '';
        return n.includes(fq) || fn.includes(fq);
      });
    }
    // 5) Render al calendario
    calendar.removeAllEvents();
    calendar.addEventSource(data);
    // 6) Contadores (derivar estados visuales coherentes con eventContent)
    const now = new Date();
    function visStateOf(ev){
      const raw = norm(ev?.extendedProps?.estado).replace(/\s+/g,'_');
      const st = ev.start ? new Date(ev.start) : null;
      const en = ev.end ? new Date(ev.end) : null;
      if (raw === 'suspendido') return 'suspendido';
      if (st && en && now >= st && now <= en) return 'en_curso';
      if (en && now > en) return 'finalizado';
      return 'programado';
    }
    const total = data.length;
    const cCurso = data.filter(ev => visStateOf(ev) === 'en_curso').length;
    const cSusp  = data.filter(ev => visStateOf(ev) === 'suspendido').length;
    const cFin   = data.filter(ev => visStateOf(ev) === 'finalizado').length;
    if ($ccTotal) $ccTotal.textContent = String(total);
    if ($ccCurso) $ccCurso.textContent = String(cCurso);
    if ($ccSusp)  $ccSusp.textContent  = String(cSusp);
    if ($ccFin)   $ccFin.textContent   = String(cFin);
  }

  $estado?.addEventListener('change', applyAllFiltersAndRender);
  $ficha?.addEventListener('input', debounce(applyAllFiltersAndRender, 200));

  // Exportar CSV simple con los eventos filtrados actualmente visibles
  $export?.addEventListener('click', () => {
    // reconstruir la lista filtrada con la misma función
    // reutilizamos applyAllFiltersAndRender pero sin tocar el calendario
    let data = state.eventsRaw.slice();
    if (state.selected && state.selected.size > 0){ data = data.filter(ev => state.selected.has(Number(ev?.extendedProps?.profesor_id))); }
    const est = norm($estado?.value); if (est){
      const nowF = new Date();
      const visF = (ev) => {
        const raw = norm(ev?.extendedProps?.estado).replace(/\s+/g,'_');
        const st = ev.start ? new Date(ev.start) : null;
        const en = ev.end ? new Date(ev.end) : null;
        if (raw === 'suspendido') return 'suspendido';
        if (st && en && nowF >= st && nowF <= en) return 'en_curso';
        if (en && nowF > en) return 'finalizado';
        return 'programado';
      };
      data = data.filter(ev => visF(ev) === est);
    }
    const fq = norm($ficha?.value); if (fq){ data = data.filter(ev => (norm(ev?.extendedProps?.ficha_numero)||'').includes(fq) || (norm(ev?.extendedProps?.ficha_nombre)||'').includes(fq)); }
    const now = new Date();
    const visStateOf = (ev) => {
      const raw = norm(ev?.extendedProps?.estado).replace(/\s+/g,'_');
      const st = ev.start ? new Date(ev.start) : null;
      const en = ev.end ? new Date(ev.end) : null;
      if (raw === 'suspendido') return 'suspendido';
      if (st && en && now >= st && now <= en) return 'en_curso';
      if (en && now > en) return 'finalizado';
      return 'programado';
    };
    const rows = [
      ['Titulo','Inicio','Fin','Instructor','Ficha','Colegio','Estado','Aula']
    ].concat(data.map(ev => [
      ev.title || '',
      ev.start || '',
      ev.end || '',
      ev?.extendedProps?.profesor_nombre || '',
      ev?.extendedProps?.ficha_numero || ev?.extendedProps?.ficha_nombre || '',
      ev?.extendedProps?.colegio_nombre || '',
      visStateOf(ev),
      ev?.extendedProps?.aula || ''
    ]));
    const csv = rows.map(r => r.map(x => '"'+String(x).replace(/"/g,'""')+'"').join(',')).join('\n');
    const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'calendario_colaborativo.csv'; a.click();
    URL.revokeObjectURL(url);
  });

  // Modal "Nueva Clase…" (solo Admin)
  const btnNew = document.getElementById('calcolab-newclass');
  const selNew = document.getElementById('newclass-instructor');
  const goNew  = document.getElementById('newclass-go');
  if (btnNew && selNew && goNew) {
    btnNew.addEventListener('click', () => {
      // Poblar select con instructores si está vacío
      if (selNew.options.length <= 1) {
        (state.instructors||[]).forEach(p => {
          const opt = document.createElement('option');
          opt.value = String(p.id);
          opt.textContent = p.nombre;
          selNew.appendChild(opt);
        });
      }
      // abrir modal (Bootstrap)
      if (window.bootstrap) {
        const m = new bootstrap.Modal(document.getElementById('modalNewClass'));
        m.show();
        selNew.focus();
      } else {
        document.getElementById('modalNewClass')?.classList.add('show');
      }

      // Initialize date picker styling (Flatpickr) on Fecha
      try {
        if (window.flatpickr) {
          const $fecha = document.getElementById('newclass-fecha');
          if ($fecha && !$fecha.dataset.fpBound) {
            window.flatpickr.localize(window.flatpickr.l10ns.es || {});
            window.flatpickr($fecha, {
              dateFormat: 'Y-m-d',
              altInput: true,
              altFormat: 'd/m/Y',
              allowInput: true,
              monthSelectorType: 'static',
              disableMobile: true,
              appendTo: document.getElementById('modalNewClass'),
              defaultDate: new Date(),
            });
            // fallback: si el input está vacío, poner hoy en formato técnico
            if (!$fecha.value) {
              const d = new Date();
              const pad = (n)=>String(n).padStart(2,'0');
              $fecha.value = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
            }
            $fecha.dataset.fpBound = '1';
          }
          // Time pickers (Hora Inicio/Fin)
          const $hi = document.getElementById('newclass-hora-inicio');
          const $hf = document.getElementById('newclass-hora-fin');
          const initTime = (el) => {
            if (!el || el.dataset.fpBound) return;
            const now = new Date();
            now.setSeconds(0,0);
            // redondear a 30 min
            const minutes = now.getMinutes();
            const rounded = minutes < 30 ? 30 : 60;
            now.setMinutes(rounded === 60 ? 0 : 30);
            if (rounded === 60) now.setHours(now.getHours()+1);
            window.flatpickr(el, {
              enableTime: true,
              noCalendar: true,
              time_24hr: false,
              minuteIncrement: 5,
              dateFormat: 'H:i',      // valor técnico para backend
              altInput: true,
              altFormat: 'h:i K',     // visual 12h
              appendTo: document.getElementById('modalNewClass'),
              defaultDate: now,
            });
            // fallback si vacío
            if (!el.value) {
              const pad = (n)=>String(n).padStart(2,'0');
              el.value = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
            }
            el.dataset.fpBound = '1';
          };
          initTime($hi);
          initTime($hf);
        }
      } catch(_) {}
      const palette = document.getElementById('newclass-color');
      const colorVal = document.getElementById('newclass-color-value');
      const colorPicker = document.getElementById('newclass-color-picker');
      if (palette && !palette.dataset.bound) {
        palette.dataset.bound = '1';
        const sysDefault = colorVal ? colorVal.value : '#0b2f3f';
        const selected = () => palette.querySelector('.cc-color.is-selected');
        const ringOn = (btn) => { btn.classList.add('is-selected'); btn.setAttribute('aria-pressed','true'); btn.style.boxShadow = '0 6px 0 rgba(0,0,0,.08), 0 0 0 3px rgba(0,0,0,.15)'; };
        const ringOff = (btn) => { btn.classList.remove('is-selected'); btn.setAttribute('aria-pressed','false'); btn.style.boxShadow = '0 6px 0 rgba(0,0,0,.08)'; };
        palette.addEventListener('click', (e) => {
          const customBtn = e.target.closest('.cc-color[data-custom]');
          const fixedBtn  = e.target.closest('.cc-color[data-color]');
          const cur = selected();
          if (customBtn) {
            if (cur === customBtn) { ringOff(customBtn); if (colorVal) colorVal.value = sysDefault; return; }
            if (!colorPicker) return;
            colorPicker.onchange = () => {
              const clr = colorPicker.value || '';
              if (!clr) return;
              if (cur) ringOff(cur);
              customBtn.style.background = clr;
              ringOn(customBtn);
              if (colorVal) colorVal.value = clr;
            };
            try {
              if (typeof colorPicker.showPicker === 'function') {
                colorPicker.showPicker();
              } else {
                colorPicker.click();
              }
            } catch(_) {
              try { colorPicker.click(); } catch(_) {}
            }
            return;
          }
          if (fixedBtn) {
            if (cur === fixedBtn) { ringOff(fixedBtn); if (colorVal) colorVal.value = sysDefault; return; }
            if (cur) ringOff(cur);
            ringOn(fixedBtn);
            const clr = fixedBtn.getAttribute('data-color') || sysDefault;
            if (colorVal) colorVal.value = clr;
          }
        });
      }

      // Dependent selects dentro del modal: instructor <-> ficha
      const fichaSel = document.getElementById('newclass-ficha');
      const instrSel = document.getElementById('newclass-instructor');
      // Disponibilidad por ficha (días/jornada)
      let fichaAvail = null; // { dias:[], jornada_global, jornada_por_dia:{} }
      const availWrapId = 'newclass-ficha-availability';
      const ensureAvailWrap = () => {
        let el = document.getElementById(availWrapId);
        if (!el) {
          el = document.createElement('div');
          el.id = availWrapId;
          el.style.margin = '6px 0 0';
          el.style.fontSize = '12px';
          const row = fichaSel?.closest('.row');
          if (row) row.parentElement.insertBefore(el, row.nextSibling);
        }
        return el;
      };
      const dayNameFromIdx = (i) => ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'][i] || '';
      const idxFromDayName = (n) => ({'domingo':0,'lunes':1,'martes':2,'miercoles':3,'jueves':4,'viernes':5,'sabado':6})[n] ?? -1;
      const segToRange = (seg) => {
        const map = { 'mañana':['06:00','12:00'], 'manana':['06:00','12:00'], 'tarde':['12:00','18:00'], 'noche':['18:00','22:00'] };
        return map[(seg||'').toLowerCase()] || null;
      };
      const segmentsForDay = (dayName) => {
        if (!fichaAvail) return [];
        const perDay = fichaAvail.jornada_por_dia && fichaAvail.jornada_por_dia[dayName];
        const global = fichaAvail.jornada_global;
        // jornada_global es array; perDay es array
        const segs = Array.isArray(perDay) && perDay.length ? perDay : (Array.isArray(global) ? global : []);
        // normalizar a ['manana','tarde','noche']
        return (segs||[]).map(s => s === 'mañana' ? 'manana' : s).filter(Boolean);
      };
      const rangesForDay = (dayName) => {
        const segs = segmentsForDay(dayName);
        return segs.map(segToRange).filter(Boolean);
      };
      const applyAvailabilityToPickers = () => {
        const $fecha = document.getElementById('newclass-fecha');
        const $hi = document.getElementById('newclass-hora-inicio');
        const $hf = document.getElementById('newclass-hora-fin');
        const fpDate = $fecha? $fecha._flatpickr : null;
        const fpHi = $hi? $hi._flatpickr : null;
        const fpHf = $hf? $hf._flatpickr : null;
        if (!fichaAvail || !fpDate) return;
        const allowedIdx = (fichaAvail.dias||[]).map(idxFromDayName).filter(x=>x>=0);
        const isAllowedDate = (d)=> allowedIdx.includes(d.getDay());
        fpDate.set('enable', [isAllowedDate]);
        // Si la fecha actual no es válida, saltar a la próxima válida
        const cur = fpDate.selectedDates?.[0] || new Date();
        if (!isAllowedDate(cur)) {
          let probe = new Date(cur);
          for (let k=0; k<14; k++) { probe.setDate(probe.getDate()+1); if (isAllowedDate(probe)) { fpDate.setDate(probe, true); break; } }
        }
        // Ajustar min/max de tiempo según jornada del día seleccionado (usar envolvente amplia si múltiples segmentos)
        const dSel = fpDate.selectedDates?.[0];
        if (dSel && (fpHi||fpHf)) {
          const name = dayNameFromIdx(dSel.getDay());
          const ranges = rangesForDay(name);
          if (ranges.length && fpHi && fpHf) {
            // calcular envolvente [min(start), max(end)] para facilitar input
            const mins = ranges.map(r=>r[0]); const maxs = ranges.map(r=>r[1]);
            const minOverall = mins.sort()[0]; const maxOverall = maxs.sort().slice(-1)[0];
            fpHi.set('minTime', minOverall); fpHi.set('maxTime', maxOverall);
            fpHf.set('minTime', minOverall); fpHf.set('maxTime', maxOverall);
            // Si horas fuera de rango, corregir hacia el mínimo dentro del rango
            const fixTime = (fp) => {
              try {
                const val = fp.input.value || '';
                const [h,m] = val.split(':').map(x=>parseInt(x,10));
                const t = (h*60 + (m||0));
                const [mh,mm] = minOverall.split(':').map(x=>parseInt(x,10));
                const [xH,xM] = maxOverall.split(':').map(x=>parseInt(x,10));
                const minT = mh*60 + mm; const maxT = xH*60 + xM;
                if (!(t>=minT && t<=maxT)) fp.setDate(bounds[0], true);
              } catch(_){}
            };
            fixTime(fpHi); fixTime(fpHf);
          } else if (fpHi && fpHf) {
            // Sin jornada: liberar restricciones
            fpHi.set('minTime', null); fpHi.set('maxTime', null);
            fpHf.set('minTime', null); fpHf.set('maxTime', null);
          }
        }
      };
      const renderAvailability = () => {
        const wrap = ensureAvailWrap();
        if (!fichaAvail) { wrap.textContent = ''; return; }
        const dias = (fichaAvail.dias||[]).map(d=>d.charAt(0).toUpperCase()+d.slice(1)).join(', ');
        const jg = Array.isArray(fichaAvail.jornada_global) && fichaAvail.jornada_global.length ? fichaAvail.jornada_global.join(', ') : '—';
        const hasPerDay = fichaAvail.jornada_por_dia && Object.keys(fichaAvail.jornada_por_dia).length>0;
        // Info del día seleccionado
        const $fecha = document.getElementById('newclass-fecha');
        const fpDate = $fecha? $fecha._flatpickr : null;
        let daySegsText = '';
        if (fpDate && fpDate.selectedDates && fpDate.selectedDates[0]){
          const name = dayNameFromIdx(fpDate.selectedDates[0].getDay());
          const segs = segmentsForDay(name);
          if (segs.length) {
            const pretty = segs.map(s=> s==='manana' ? 'mañana' : (s==='noche'?'nocturna':s));
            daySegsText = ` | Para ${name.charAt(0).toUpperCase()+name.slice(1)}: ${pretty.join(', ')}`;
          }
        }
        wrap.innerHTML = `
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <span style="background:#eef6ff;color:#0369a1;border-radius:10px;padding:2px 8px;">Días permitidos: ${dias||'—'}</span>
            <span style="background:#f1f5f9;color:#111827;border-radius:10px;padding:2px 8px;">Jornada: ${hasPerDay? 'todo el día' : (jg||'—')}${daySegsText}</span>
          </div>`;
      };
      const fetchAvailability = async (fid) => {
        fichaAvail = null;
        try {
          const data = await fetchJSON('/?page=calcolab_disponibilidad_ficha&ficha_id='+encodeURIComponent(fid));
          fichaAvail = data || null;
          renderAvailability();
          applyAvailabilityToPickers();
        } catch(e) {
          console.error('disponibilidad ficha:', e);
        }
      };
      // Helper
      const fetchJSON = async (url) => {
        const r = await fetch(url, { credentials: 'same-origin' });
        let body = null;
        try { body = await r.json(); } catch(_) {}
        if (!r.ok) {
          const msg = body && body.error ? body.error : ('HTTP ' + r.status);
          throw new Error(msg);
        }
        return body;
      };
      const populateSelect = (sel, items, getVal, getText, placeholder, preserveValue) => {
        if (!sel) return;
        const prev = preserveValue ? sel.value : '';
        sel.innerHTML = '';
        const opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = placeholder || '-- Seleccionar --';
        sel.appendChild(opt0);
        let foundPrev = false;
        (items||[]).forEach(it => {
          const val = String(getVal(it));
          const o = document.createElement('option');
          o.value = val;
          o.textContent = String(getText(it));
          if (prev && prev === val) { o.selected = true; foundPrev = true; }
          sel.appendChild(o);
        });
        if (preserveValue && prev && !foundPrev) {
          // Si el valor anterior ya no es válido, dejar placeholder seleccionado
          sel.value = '';
        }
      };
      let __updatingFichas = false;
      let __updatingInstr = false;
      const loadFichasPorInstructor = async (pid) => {
        if (!fichaSel) return;
        __updatingFichas = true;
        fichaSel.disabled = true;
        populateSelect(fichaSel, [], x=>x.id, x=>'', 'Cargando fichas...', true);
        try {
          const data = await fetchJSON('/?page=calcolab_fichas_por_instructor&profesor_id='+encodeURIComponent(pid));
          populateSelect(fichaSel, data, x=>x.id, x=>`${x.codigo||x.id} - ${x.nombre||''}`, '-- Seleccionar --', true);
        } catch(e) {
          populateSelect(fichaSel, [], x=>x.id, x=>'', 'Error al cargar', false);
        } finally {
          fichaSel.disabled = false;
          __updatingFichas = false;
        }
      };
      const loadInstructoresPorFicha = async (_fid) => { return; };
      // Bind once
      if (instrSel && !instrSel.dataset.bound) {
        instrSel.dataset.bound = '1';
        instrSel.addEventListener('change', () => {
          if (__updatingInstr) return; // ignore programmatic updates
          const pid = instrSel.value;
          if (pid) loadFichasPorInstructor(pid);
        });
      }
      // Se deshabilita la dependencia ficha -> instructor; solo se usa para disponibilidad
      if (fichaSel && !fichaSel.dataset.availBound) {
        fichaSel.dataset.availBound = '1';
        fichaSel.addEventListener('change', () => {
          const fid = fichaSel.value;
          if (fid) fetchAvailability(fid);
        });
      }
      // También re-aplicar restricciones al cambiar la fecha
      const $fecha = document.getElementById('newclass-fecha');
      if ($fecha && !$fecha.dataset.availBound) {
        $fecha.dataset.availBound = '1';
        $fecha.addEventListener('change', applyAvailabilityToPickers);
      }
      const modalEl = document.getElementById('modalNewClass');
      const resetModal = () => {
        const $fechaEl = document.getElementById('newclass-fecha');
        const $hiEl = document.getElementById('newclass-hora-inicio');
        const $hfEl = document.getElementById('newclass-hora-fin');
        const $aula = document.getElementById('newclass-aula');
        if ($fechaEl && $fechaEl._flatpickr) { try { $fechaEl._flatpickr.clear(); } catch(_){} }
        if ($hiEl && $hiEl._flatpickr) { try { $hiEl._flatpickr.clear(); } catch(_){} }
        if ($hfEl && $hfEl._flatpickr) { try { $hfEl._flatpickr.clear(); } catch(_){} }
        if ($aula) $aula.value = '';
        if (fichaSel) fichaSel.value = '';
        if (instrSel) instrSel.value = '';
        const colorVal = document.getElementById('newclass-color-value');
        const colorPicker = document.getElementById('newclass-color-picker');
        if (colorVal) colorVal.value = '#0b2f3f';
        if (colorPicker) colorPicker.value = '#0b2f3f';
        const palette = document.getElementById('newclass-color');
        if (palette) {
          const cur = palette.querySelector('.cc-color.is-selected');
          if (cur) { cur.classList.remove('is-selected'); cur.setAttribute('aria-pressed','false'); cur.style.boxShadow = '0 6px 0 rgba(0,0,0,.08)'; }
        }
        const avail = document.getElementById('newclass-ficha-availability');
        if (avail) avail.textContent = '';
        fichaAvail = null;
      };
      if (modalEl && !modalEl.dataset.resetBound) {
        modalEl.dataset.resetBound = '1';
        modalEl.addEventListener('hidden.bs.modal', resetModal);
      }
    });
    // Guardar nueva clase (POST calcolab_crear)
    goNew.addEventListener('click', () => {
      // Recolectar datos del modal
      const profesor_id = document.getElementById('newclass-instructor')?.value || '';
      const ficha_id = document.getElementById('newclass-ficha')?.value || '';
      const aula = document.getElementById('newclass-aula')?.value || '';
      const color = document.getElementById('newclass-color-value')?.value || '#3b82f6';
      const $fechaEl = document.getElementById('newclass-fecha');
      const $hiEl = document.getElementById('newclass-hora-inicio');
      const $hfEl = document.getElementById('newclass-hora-fin');
      // Leer fecha/hora desde flatpickr para evitar desync con altInput
      const selDate = ($fechaEl && $fechaEl._flatpickr && $fechaEl._flatpickr.selectedDates && $fechaEl._flatpickr.selectedDates[0]) ? $fechaEl._flatpickr.selectedDates[0] : null;
      const fecha = selDate ? `${selDate.getFullYear()}-${String(selDate.getMonth()+1).padStart(2,'0')}-${String(selDate.getDate()).padStart(2,'0')}` : ($fechaEl?.value || '');
      const hi = ($hiEl && $hiEl._flatpickr) ? $hiEl._flatpickr.input.value : ($hiEl?.value || '');
      const hf = ($hfEl && $hfEl._flatpickr) ? $hfEl._flatpickr.input.value : ($hfEl?.value || '');

      if (!profesor_id) { alert('Seleccione un instructor'); return; }
      if (!ficha_id) { alert('Seleccione una ficha'); return; }
      if (!fecha) { alert('Seleccione la fecha'); return; }
      if (!hi || !hf) { alert('Seleccione horas de inicio y fin'); return; }
      // Validación con disponibilidad de ficha (si existe)
      if (fichaAvail) {
        const d = new Date(fecha+'T00:00:00');
        const dayName = ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'][d.getDay()];
        if (!(fichaAvail.dias||[]).includes(dayName)) {
          alert('La ficha no permite crear clases el día seleccionado.');
          return;
        }
        // Validar contra al menos uno de los segmentos del día
        const ranges = rangesForDay(dayName);
        if (ranges.length) {
          const toMin = (t)=>{ const [H,M]=t.split(':').map(x=>parseInt(x,10)); return H*60+(M||0); };
          const tI = toMin(hi), tF = toMin(hf);
          const ok = ranges.some(([lo,hiR]) => { const a=toMin(lo), b=toMin(hiR); return tI>=a && tF<=b; });
          if (!ok) { alert('La hora seleccionada está fuera de la jornada permitida para ese día.'); return; }
        }
      }

      const toDT = (d, t) => `${d} ${t}:00`;
      const fecha_inicio = toDT(fecha, hi);
      const fecha_fin = toDT(fecha, hf);

      const fd = new FormData();
      fd.append('profesor_id', profesor_id);
      fd.append('ficha_id', ficha_id);
      fd.append('titulo', '');
      fd.append('fecha_inicio', fecha_inicio);
      fd.append('fecha_fin', fecha_fin);
      fd.append('aula', aula);
      fd.append('color', color);
      fd.append('estado', 'programado');

      fetch('/?page=calcolab_crear', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(async r => { const j = await r.json().catch(()=>({})); if (!r.ok) throw new Error(j && j.error ? j.error : ('HTTP '+r.status)); return j; })
        .then(() => {
          // Cerrar modal y refrescar eventos del calendario
          const mEl = document.getElementById('modalNewClass');
          if (window.bootstrap && mEl) { try { bootstrap.Modal.getInstance(mEl)?.hide(); } catch(_){} }
          // refrescar FullCalendar si existe
          if (window.calcolab && window.calcolab.calendar) {
            window.calcolab.calendar.refetchEvents();
          } else {
            // fallback: recargar página
            window.location.reload();
          }
        })
        .catch(err => {
          alert('No se pudo guardar: ' + (err.message || err));
        });
    });
  }
});
