document.addEventListener('DOMContentLoaded', () => {
  // Elementos del DOM
  const calEl = document.getElementById('calcolab-calendar');
  const listEl = document.getElementById('calcolab-instructors');
  const $estado = document.getElementById('calcolab-filter-estado');
  const $ficha = document.getElementById('calcolab-filter-ficha');
  const fichaListEl = document.getElementById('calcolab-fichas-list');
  const $export = document.getElementById('calcolab-export');
  const $ccTotal = document.getElementById('cc-total');
  const $ccCurso = document.getElementById('cc-curso');
  const $ccSusp = document.getElementById('cc-susp');
  const $ccFin = document.getElementById('cc-fin');
  const rootEl = document.getElementById('calcolab-root');
  const sidebarEl = document.getElementById('calcolab-sidebar');
  const sidebarToggle = document.getElementById('calcolab-sidebar-toggle');
  
  if (!calEl || !listEl || !window.FullCalendar) {
    console.error('Elementos del DOM o FullCalendar no disponibles');
    return;
  }
  // Parseo seguro de fechas (acepta Date u 'YYYY-MM-DD HH:MM:SS')
  const parseDateSafe = (v) => {
    if (!v) return null;
    if (v instanceof Date) return v;
    if (typeof v === 'string') return new Date(v.replace(' ', 'T'));
    return new Date(v);
  };

  // Estado UI
  const state = {
    instructors: [],
    selected: new Set(),
    pollHandle: null,
    eventsRaw: [], // últimos eventos cargados del backend (rango visible)
    showNoRel: true,
  };

  // Debug helper (silenciado por defecto para no llenar la consola)
  const DEBUG_CALCOLAB = false;
  const dlog = (...args) => { if (DEBUG_CALCOLAB) try { console.log(...args); } catch(_) {} };

  if (rootEl && sidebarEl && sidebarToggle) {
    const origCols = rootEl.style.gridTemplateColumns || '280px 1fr 260px';
    sidebarToggle.addEventListener('click', () => {
      try{
        const icon = sidebarToggle.querySelector('.cc-sidebar-toggle-icon');
        const collapsed = sidebarEl.classList.toggle('cc-sidebar-is-collapsed');
        if (rootEl) {
          rootEl.style.gridTemplateColumns = collapsed ? '56px 1fr 260px' : (origCols || '280px 1fr 260px');
        }
        const mainPanel = rootEl ? rootEl.querySelector('section') : null;
        if (mainPanel) {
          if (collapsed) {
            mainPanel.style.marginLeft = '0';
            mainPanel.style.paddingLeft = '24px';
          } else {
            mainPanel.style.marginLeft = '';
            mainPanel.style.paddingLeft = '';
          }
        }
        if (icon) {
          icon.textContent = collapsed ? '▶' : '◀';
        }
        sidebarToggle.setAttribute('aria-label', collapsed ? 'Mostrar panel de instructores' : 'Ocultar panel de instructores');
      } catch(_) {}
    });
  }

  // Disponibilidad seleccionada (hoisted para ser accesible desde el botón Guardar)
  let fichaAvail = null; // { dias:[], jornada_global:[], jornada_por_dia:{} }
  const segToRange = (seg) => {
    const map = { 'mañana':['06:00','12:00'], 'manana':['06:00','12:00'], 'tarde':['12:00','18:00'], 'noche':['18:00','22:00'] };
    return map[(seg||'').toLowerCase()] || null;
  };
  function rangesForDayFromAvail(av, dayName){
    if (!av) return [];
    const perDay = av.jornada_por_dia && av.jornada_por_dia[dayName];
    const global = av.jornada_global;
    const segs = Array.isArray(perDay) && perDay.length ? perDay : (Array.isArray(global) ? global : []);
    const norm = (segs||[]).map(s => s === 'mañana' ? 'manana' : s).filter(Boolean);
    return norm.map(segToRange).filter(Boolean);
  }

  // Helpers
  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  function debounce(fn, wait) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), wait); }; }

  // Render lista instructores
  function renderInstructors() {
    listEl.innerHTML = '';
    const wrap = document.createElement('div');
    // Switch Mostrar sin relación
    const togg = document.createElement('label');
    togg.className = 'cc-toggle';
    togg.setAttribute('data-dir','rtl');
    togg.innerHTML = `
      <input type="checkbox" id="cc-show-norel" ${state.showNoRel ? 'checked':''} />
      <span class="cc-switch" aria-hidden="true"></span>
      <span class="cc-toggle-label">Mostrar sin fichas</span>
    `;
    wrap.appendChild(togg);

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
      const disabled = Number(p.has_rel||0) === 0;
      const title = disabled ? 'Sin relación con fichas' : '';
      return `
        <label class="tarjeta tarjeta--mini" title="${title}" style="display:flex;align-items:center;gap:10px;padding:8px;${disabled?'opacity:.55;cursor:not-allowed;':'cursor:pointer;'}">
          <input type="checkbox" class="chk-inst" data-id="${p.id}" ${checked? 'checked':''} ${disabled?'disabled':''} />
          <span class="cc-avatar">${(p.iniciales||'IN')}</span>
          <span>${escapeHtml(p.nombre||'')}</span>
        </label>`;
    }


  // Navegación y título del rango
  const navWrap = document.getElementById('calcolab-nav');
  const titleEl = document.getElementById('calcolab-title');
  function formatRangeTitle(raw){
    if (!raw) return '';
    const s = String(raw);
    return s.charAt(0).toUpperCase() + s.slice(1);
  }
  function updateTitle(){
    try {
      const v = calendar.view;
      // Aprovechar title de FullCalendar según la vista, pero con primera letra mayúscula
      titleEl.textContent = formatRangeTitle(v?.title || '');
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
      let data = state.instructors.filter(p => !q || String(p.nombre||'').toLowerCase().includes(q));
      if (!state.showNoRel) {
        data = data.filter(p => Number(p.has_rel||0) === 1);
      }
      ul.innerHTML = data.map(itemHtml).join('');
    }
    search.addEventListener('input', debounce(applyFilter, 150));
    const toggInput = togg.querySelector('input');
    if (toggInput) {
      toggInput.addEventListener('change', (e)=>{ state.showNoRel = !!e.target.checked; applyFilter(); });
    }

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
  // Manejo de errores en el modal Nueva Clase
  function ensureTopErrorBox(){
    const modal = document.getElementById('modalNewClass');
    if (!modal) return null;
    let box = modal.querySelector('#newclass-error');
    if (!box) {
      box = document.createElement('div');
      box.id = 'newclass-error';
      box.className = 'alert alert-danger';
      box.style.cssText = 'display:none;margin-bottom:10px;border-radius:10px;';
      const body = modal.querySelector('.modal-body');
      if (body) body.insertBefore(box, body.firstChild);
    }
    return box;
  }
  function showTopError(msg){
    const box = ensureTopErrorBox();
    if (!box) return;
    box.textContent = String(msg||'Ocurrió un error');
    box.style.display = 'block';
  }
  function hideTopError(){
    const box = document.getElementById('newclass-error');
    if (box) box.style.display = 'none';
  }
  function showFieldError(inputEl, msg){
    if (!inputEl) return;
    try { inputEl.classList.add('is-invalid'); } catch(_) {}
    // invalid-feedback
    let fb = inputEl.nextElementSibling && inputEl.nextElementSibling.classList && inputEl.nextElementSibling.classList.contains('invalid-feedback') ? inputEl.nextElementSibling : null;
    if (!fb) {
      fb = document.createElement('div');
      fb.className = 'invalid-feedback';
      fb.style.display = 'block';
      inputEl.parentElement && inputEl.parentElement.appendChild(fb);
    }
    fb.textContent = String(msg||'Campo inválido');
  }
  function clearFieldError(inputEl){
    if (!inputEl) return;
    inputEl.classList.remove('is-invalid');
    const fb = inputEl.nextElementSibling && inputEl.nextElementSibling.classList && inputEl.nextElementSibling.classList.contains('invalid-feedback') ? inputEl.nextElementSibling : null;
    if (fb) fb.remove();
  }
  function clearAllNewClassErrors(){
    hideTopError();
    const modal = document.getElementById('modalNewClass');
    if (!modal) return;
    modal.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    modal.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
  }

  // ====== INICIALIZACIÓN DEL CALENDARIO ======
  
  const calendar = new FullCalendar.Calendar(calEl, {
    nowIndicator: true,
    initialView: 'dayGridMonth',
    height: 'auto',
    headerToolbar: false,
    locale: 'es',
    dayHeaderFormat: { weekday: 'long' },
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
        // Encabezados de días: nombre completo (lunes, martes, ...)
        dayHeaderFormat: { weekday: 'long' }
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
        // Orden requerido: ficha - aula - instructor
        const parts = [];
        if (ficha) parts.push(ficha);
        if (aula) parts.push(aula);
        if (instr) parts.push(instr);
        const text = parts.length ? parts.join(' - ') : (arg.event.title || '');
        return { html: '<div class="cc-evrow"><span class="cc-ev-dot '+colorCls+'"></span><span class="cc-ev" style="background:'+ (bg || '#00304D') +'"><span class="cc-ev-text">'+ escapeHtml(text) +'</span></span></div>' };
      } catch (_) {
        return { html: '<div class="cc-evrow"><span class="cc-ev-dot cc-dot-blue"></span><span class="cc-ev" style="background:#00304D"><span class="cc-ev-text">'+ escapeHtml(arg.event.title || '') +'</span></span></div>' };
      }
    },
    events: [],
    datesSet: debounce(() => {
      try { calendar.setOption('hiddenDays', [0]); } catch(_) {}
      reloadEvents();
      updateMonthNowBadge();
    }, 50),
    // Personalización del link "+N más"
    moreLinkContent: function(args){
      try {
        const n = args && (args.num || args.shortText || args.text);
        const num = typeof n === 'number' ? n : parseInt(String(n||'').replace(/\D+/g,''), 10) || '';
        return { html: `<span class="cc-more-pill">+${num} más</span>` };
      } catch(_) {
        return { html: '<span class="cc-more-pill">+ más</span>' };
      }
    },
    moreLinkClick: function(arg){
      try { calendar.changeView('timeGridDay', arg.date); } catch(_) {}
      return 'prevent';
    },
  });
  calendar.render();
  // Asegurar: semana inicia en lunes y domingo oculto en TODAS las vistas
  try {
    calendar.setOption('firstDay', 1);
    calendar.setOption('hiddenDays', [0]);
  } catch(_) {}
  // Exportar (XLSX) del calendario colaborativo
  try {
    const toYMD = (d) => {
      const yy=d.getFullYear(); const mm=String(d.getMonth()+1).padStart(2,'0'); const dd=String(d.getDate()).padStart(2,'0');
      return `${yy}-${mm}-${dd}`;
    };
    if ($export && !$export.dataset.bound) {
      $export.dataset.bound = '1';
      $export.addEventListener('click', () => {
        try {
          const v = calendar.view;
          const d1 = v?.activeStart || v?.currentStart || new Date();
          const d2 = v?.activeEnd   || v?.currentEnd   || new Date();
          const url = new URL('/', window.location.origin);
          url.searchParams.set('page','calcolab_export');
          url.searchParams.set('start', toYMD(d1));
          // fin inclusivo: restar un día al activeEnd
          const d2inc = new Date(d2.getTime() - 24*60*60*1000);
          url.searchParams.set('end', toYMD(d2inc));
          const estSel = document.getElementById('calcolab-filter-estado')?.value || '';
          const fichaSel = document.getElementById('calcolab-filter-ficha')?.value || '';
          if (estSel) url.searchParams.set('estado', estSel);
          if (fichaSel) url.searchParams.set('ficha', fichaSel);
          const inst = Array.from(state.selected || []).join(',');
          if (inst) url.searchParams.set('instructores', inst);
          window.open(url.toString(), '_blank');
        } catch(_) {}
      });
    }
  } catch(_) {}
  // Badge de "Ahora" para vista mensual
  function updateMonthNowBadge(){
    try{
      const viewType = calendar.view && calendar.view.type ? calendar.view.type : '';
      // limpiar anteriores
      document.querySelectorAll('.cc-now-badge').forEach(el=>el.remove());
      if (!/^dayGrid/.test(viewType)) return;
      const todayFrame = document.querySelector('#calcolab-root .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-frame');
      if (!todayFrame) return;
      const now = new Date();
      const H = now.getHours();
      const h = ((H % 12) || 12);
      const mm = String(now.getMinutes()).padStart(2,'0');
      const ampm = H >= 12 ? 'PM' : 'AM';
      const badge = document.createElement('span');
      badge.className = 'cc-now-badge';
      badge.textContent = `${h}:${mm} ${ampm}`;
      todayFrame.appendChild(badge);
    } catch(_){}
  }
  // actualizar cada minuto
  try { if (window.calcolabNowTimer) { clearInterval(window.calcolabNowTimer); } } catch(_){}
  window.calcolabNowTimer = setInterval(() => { updateMonthNowBadge(); }, 60000);
  // primera pintura
  updateMonthNowBadge();
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
      let colegio = pick(ex, 'colegio_nombre','colegio','colegio_legacy');
      if (!colegio) {
        const idCol = ex.ficha_colegio_id || ex.colegio_id_ficha;
        if (idCol) colegio = `ID ${idCol}`;
      }
      // Estado VISUAL según fechas (consistente con chips del calendario)
      const rawEstado = String(pick(ex, 'estado')||'').toLowerCase();
      const now = new Date();
      const st = inicio ? new Date(inicio) : null;
      const en = fin ? new Date(fin) : null;
      let estado = 'programado';
      if (rawEstado === 'suspendido') estado = 'suspendido';
      else if (st && en && now >= st && now <= en) estado = 'en_curso';
      else if (en && now > en) estado = 'finalizado';
      else estado = 'programado';
      const estadoBgMap = { 'finalizado':'#111827', 'en_curso':'#22c55e', 'suspendido':'#ef4444', 'programado':'#3b82f6' };
      const estadoBg = estadoBgMap[estado] || '#3b82f6';
      const estadoHuman = { 'programado':'Programado', 'en_curso':'En curso', 'finalizado':'Finalizado', 'suspendido':'Suspendido' };
      const estadoLabel = estadoHuman[estado] || 'Programado';
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
              <div><span style="display:inline-block;padding:4px 10px;border-radius:9999px;font-weight:800;color:#fff;background:${estadoBg};text-transform:lowercase;">${estadoLabel||'—'}</span></div>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:repeat(1,minmax(0,1fr));gap:12px;">
            <div style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;box-shadow:0 2px 8px rgba(0,0,0,.04);text-align:center;">
              <small style="color:#6b7280;">Colegio</small>
              <div style="font-weight:800;">${colegio||'—'}</div>
            </div>
          </div>
        </div>`;
      // cerrar cualquier modal previamente abierto para evitar aria-hidden warnings
      try {
        document.querySelectorAll('.modal.show').forEach(m=>{ m.classList.remove('show'); m.style.display='none'; m.setAttribute('aria-hidden','true'); });
      } catch(_){}
      if (window.bootstrap && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
      } else {
        modal.style.display = 'block'; modal.classList.add('show');
        try { modal.setAttribute('aria-hidden','false'); modal.removeAttribute('aria-modal'); } catch(_) {}
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
      dlog('[calcolab] instructores:', state.instructors.length);
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
      const fq = String($ficha?.value || '').trim();
      if (fq) qs.set('ficha', fq);
      const r = await fetch('/?page=calcolab_eventos&'+qs.toString(), { credentials: 'same-origin' });
      if (!r.ok) {
        const txt = await r.text();
        throw new Error('HTTP '+r.status+' '+txt);
      }
      const evs = await r.json();
      state.eventsRaw = Array.isArray(evs) ? evs : [];
      dlog('[calcolab] eventos(raw):', state.eventsRaw.length, {start, end, ids});
      refreshFichaOptionsFromEvents();
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

  function escapeHtml(s) { return String(s||'').replace(/[&<>"'/]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;','/':'&#x2F;'}[m])); }

  // Exponer para depuración
  window.calcolab = { calendar, state };

  // ====== HERO: filtros, contadores, exportar, nueva clase ======

  function norm(s){ return String(s||'').toLowerCase().trim(); }

  function buildFichaIndexFromEvents(){
    const items = [];
    const seen = new Set();
    (state.eventsRaw || []).forEach(ev => {
      const ex = ev.extendedProps || {};
      const codeRaw = ex.ficha_numero != null ? String(ex.ficha_numero) : (ex.ficha_id != null ? String(ex.ficha_id) : '');
      const nameRaw = ex.ficha_nombre != null ? String(ex.ficha_nombre) : '';
      const code = codeRaw.trim();
      const name = nameRaw.trim();
      if (!code && !name) return;
      const key = code || name;
      if (seen.has(key)) return;
      seen.add(key);
      const label = name || code;
      items.push({ value: code, label });
    });
    items.sort((a,b) => a.label.localeCompare(b.label, 'es', { sensitivity:'base', numeric:true }));
    return items;
  }

  function refreshFichaOptionsFromEvents(){
    if (!fichaListEl) return;
    const items = buildFichaIndexFromEvents();
    fichaListEl.innerHTML = '';
    items.forEach(it => {
      const o = document.createElement('option');
      o.value = it.value;
      o.label = it.label;
      fichaListEl.appendChild(o);
    });
  }

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
    // 4) filtro por ficha (texto del input con sugerencias)
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
      const st = parseDateSafe(ev.start);
      const en = parseDateSafe(ev.end);
      if (raw === 'suspendido' || raw === 'cancelado' || raw === 'cancelada') return 'suspendido';
      if (st && en && now >= st && now <= en) return 'en_curso';
      if (en && now > en) return 'finalizado';
      if (raw === 'finalizado') return 'finalizado';
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
  $ficha?.addEventListener('change', applyAllFiltersAndRender);

  // Exportar CSV simple con los eventos filtrados actualmente visibles
  $export?.addEventListener('click', () => {
    // reconstruir la lista filtrada con la misma función
    // reutilizamos applyAllFiltersAndRender pero sin tocar el calendario
    let data = state.eventsRaw.slice();
    if (state.selected && state.selected.size > 0){ data = data.filter(ev => state.selected.has(Number(ev?.extendedProps?.profesor_id))); }
    const est = norm($estado?.value);
    if (est){
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
    const fq = norm($ficha?.value);
    if (fq){
      data = data.filter(ev => {
        const n = norm(ev?.extendedProps?.ficha_numero) || '';
        const fn = norm(ev?.extendedProps?.ficha_nombre) || '';
        return n.includes(fq) || fn.includes(fq);
      });
    }
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
      clearAllNewClassErrors();
      // Poblar select con instructores SIEMPRE filtrando solo los que tienen relación con fichas
      const valid = (state.instructors||[]).filter(p => Number(p.has_rel||0) === 1);
      selNew.innerHTML = '';
      const opt0 = document.createElement('option');
      opt0.value = '';
      opt0.textContent = '-- Seleccionar --';
      selNew.appendChild(opt0);
      valid.forEach(p => {
        const opt = document.createElement('option');
        opt.value = String(p.id);
        opt.textContent = p.nombre;
        selNew.appendChild(opt);
      });
      if (valid.length === 0) { showTopError('No hay instructores con fichas disponibles.'); return; }
      // abrir modal (Bootstrap)
      if (window.bootstrap) {
        const m = new bootstrap.Modal(document.getElementById('modalNewClass'));
        m.show();
        selNew.focus();
      } else {
        document.getElementById('modalNewClass')?.classList.add('show');
      }

      // Initialize date/time pickers (Flatpickr) on modal fields
      try {
        if (window.flatpickr) {
          const $fecha = document.getElementById('newclass-fecha');
          const $hi = document.getElementById('newclass-hora-inicio');
          const $hf = document.getElementById('newclass-hora-fin');
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
            });
            $fecha.dataset.fpBound = '1';
          }
          // Time pickers (Hora Inicio/Fin)
          const initTime = (el) => {
            if (!el || el.dataset.fpBound) return;
            window.flatpickr(el, {
              enableTime: true,
              noCalendar: true,
              time_24hr: false,
              minuteIncrement: 5,
              dateFormat: 'H:i',      // valor técnico para backend
              altInput: true,
              altFormat: 'h:i K',     // visual 12h
              appendTo: document.getElementById('modalNewClass'),
            });
            // fallback si vacío
            if (!el.value) {
              const pad = (n)=>String(n).padStart(2,'0');
              const now = new Date();
              el.value = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
            }
            el.dataset.fpBound = '1';
          };
          initTime($hi); initTime($hf);
          initTime($hi);
          initTime($hf);
          // Sincronizar fin con inicio: min = inicio y por defecto +60 min si quedara antes/igual
          try {
            const addMinutesStr = (t, mins) => {
              const m = /^([0-2]?\d):([0-5]\d)$/.exec(String(t||''));
              if (!m) return t;
              let H = parseInt(m[1],10), M = parseInt(m[2],10);
              let total = H*60 + M + (mins||0);
              if (total < 0) total = 0; if (total > 1439) total = 1439;
              const hh = String(Math.floor(total/60)).padStart(2,'0');
              const mm = String(total%60).padStart(2,'0');
              return `${hh}:${mm}`;
            };
            const setTime = (el, t) => {
              if (!el) return;
              const fp = el._flatpickr;
              if (fp) {
                const [h,m] = String(t).split(':');
                const d = new Date(); d.setSeconds(0,0); d.setHours(parseInt(h||'0',10), parseInt(m||'0',10), 0, 0);
                fp.setDate(d, true);
              } else {
                el.value = t;
              }
            };
            const getTime = (el) => {
              if (!el) return '';
              const fp = el._flatpickr;
              if (fp && fp.input) return fp.input.value;
              return el.value||'';
            };
            const enforceEndMin = () => {
              const tStart = getTime($hi);
              const tEnd   = getTime($hf);
              if (!tStart) return;
              // Aplicar minTime al picker de fin si existe
              if ($hf && $hf._flatpickr) {
                try { $hf._flatpickr.set('minTime', tStart); } catch(_) {}
              }
              // Si fin <= inicio, mover fin a inicio + 60 min
              const toMin = (t)=>{ const m=/^([0-2]?\d):([0-5]\d)$/.exec(String(t||'')); if(!m) return NaN; return parseInt(m[1],10)*60+parseInt(m[2],10); };
              const ms = toMin(tStart), me = toMin(tEnd);
              if (Number.isFinite(ms) && Number.isFinite(me) && me <= ms) {
                // Caso común: usuario eligió 12:00 AM (00:00) queriendo decir medio día
                const alt = ($hf && $hf._flatpickr && $hf._flatpickr.altInput) ? String($hf._flatpickr.altInput.value||'') : '';
                const isZeroHour = /^00:/.test(String(tEnd||''));
                if (isZeroHour && /12:0\d\s*AM/i.test(alt)) {
                  // convertir a 12:MM (noon)
                  const mm = String(tEnd).slice(3,5);
                  setTime($hf, `12:${mm}`);
                } else {
                  const tNew = addMinutesStr(tStart, 60);
                  setTime($hf, tNew);
                }
              }
            };
            if ($hi && !$hi.dataset.syncBound) {
              $hi.dataset.syncBound = '1';
              $hi.addEventListener('change', enforceEndMin);
            }
            if ($hf && !$hf.dataset.syncBound) {
              $hf.dataset.syncBound = '1';
              $hf.addEventListener('change', enforceEndMin);
            }
          } catch(_) {}
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
      fichaAvail = null; // reset global para este flujo
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
        const cur = (fpDate && fpDate.selectedDates && fpDate.selectedDates[0]) ? fpDate.selectedDates[0] : new Date();
        if (!isAllowedDate(cur)) {
          let probe = new Date(cur);
          for (let k=0; k<14; k++) { probe.setDate(probe.getDate()+1); if (isAllowedDate(probe)) { fpDate.setDate(probe, true); break; } }
        }
          const dSel = (fpDate && fpDate.selectedDates && fpDate.selectedDates[0]) ? fpDate.selectedDates[0] : new Date();
          const name = dayNameFromIdx(dSel.getDay());
          const ranges = rangesForDay(name);
          if (ranges.length && fpHi && fpHf) {
            const mins = ranges.map(r=>r[0]); const maxs = ranges.map(r=>r[1]);
            const minOverall = mins.sort()[0]; const maxOverall = maxs.sort().slice(-1)[0];
            fpHi.set('minTime', minOverall); fpHi.set('maxTime', maxOverall);
            fpHf.set('minTime', minOverall); fpHf.set('maxTime', maxOverall);
          } else if (fpHi && fpHf) {
            // Sin jornada: liberar restricciones
            fpHi.set('minTime', null); fpHi.set('maxTime', null);
            fpHf.set('minTime', null); fpHf.set('maxTime', null);
          }
      };
      const renderAvailability = () => {
        const wrap = ensureAvailWrap();
        if (!fichaAvail) { wrap.textContent = ''; return; }
        const prettySeg = (s)=> s==='manana' ? 'mañana' : (s==='noche' ? 'nocturna' : s);
        const daysOrder = ['lunes','martes','miercoles','jueves','viernes'];
        let dias = Array.isArray(fichaAvail.dias) && fichaAvail.dias.length ? fichaAvail.dias.slice() : daysOrder.slice();
        // Día seleccionado (si existe flatpickr en el modal); si no, hoy
        const $fecha = document.getElementById('newclass-fecha');
        let selDay = null;
        try {
          const fp = $fecha? $fecha._flatpickr : null;
          const base = (fp && fp.selectedDates && fp.selectedDates[0]) ? fp.selectedDates[0] : new Date();
          const idx = base.getDay();
          selDay = ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'][idx] || null;
        } catch(_) { selDay = null; }
        // Construir mapa dia -> segs (prioriza jornada_por_dia; cae a global)
        const map = [];
        for (const d of daysOrder) {
          if (!dias.includes(d)) continue; // omitir días no permitidos
          const per = (fichaAvail.jornada_por_dia && fichaAvail.jornada_por_dia[d]) || null;
          const base = per && per.length ? per : (Array.isArray(fichaAvail.jornada_global) ? fichaAvail.jornada_global : []);
          const segs = (base||[]).map(x=> x==='mañana'?'manana':x);
          const val = segs.length ? segs.map(prettySeg).join(', ') : '—';
          let label = d.charAt(0).toUpperCase()+d.slice(1).replace('miercoles','Miércoles');
          if (selDay && d === selDay) label = `<strong>${label}</strong>`;
          map.push(`${label}: ${val}`);
        }
        const text = `Días permitidos: ${map.join(' , ')}`;
        wrap.innerHTML = `<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <span style="background:#eef6ff;color:#0369a1;border-radius:10px;padding:2px 8px;">${text}</span>
        </div>`;
        // Re-render on date change to mantener el resaltado del día
        try {
          const fp = $fecha? $fecha._flatpickr : null;
          if (fp && !fp.config._avail_onChange_bound) {
            fp.config._avail_onChange_bound = true;
            (fp.config.onChange || (fp.config.onChange = [])).push(()=>{ try { renderAvailability(); } catch(_){} });
          }
        } catch(_) {}
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
      const __urlParams = new URLSearchParams(window.location.search || '');
      const __colegioParam = __urlParams.get('colegio_id') || __urlParams.get('colegio') || '';
      let __updatingFichas = false;
      let __updatingInstr = false;
      const loadFichasPorInstructor = async (pid) => {
        if (!fichaSel) return;
        __updatingFichas = true;
        fichaSel.disabled = true;
        populateSelect(fichaSel, [], x=>x.id, x=>'', 'Cargando fichas...', true);
        try {
          const __extra = __colegioParam ? ('&colegio_id='+encodeURIComponent(__colegioParam)) : '';
          const data = await fetchJSON('/?page=calcolab_fichas_por_instructor&profesor_id='+encodeURIComponent(pid)+__extra);
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

      clearAllNewClassErrors();
      const $instr = document.getElementById('newclass-instructor');
      const $fichaSel = document.getElementById('newclass-ficha');
      let firstInvalid = null;
      if (!profesor_id) { showFieldError($instr, 'Seleccione un instructor'); if (!firstInvalid) firstInvalid = $instr; }
      if (!ficha_id) { showFieldError($fichaSel, 'Seleccione una ficha'); if (!firstInvalid) firstInvalid = $fichaSel; }
      if (!fecha) { showFieldError($fechaEl, 'Seleccione la fecha'); if (!firstInvalid) firstInvalid = $fechaEl; }
      if (!hi) { showFieldError($hiEl, 'Seleccione la hora de inicio'); if (!firstInvalid) firstInvalid = $hiEl; }
      if (!hf) { showFieldError($hfEl, 'Seleccione la hora de fin'); if (!firstInvalid) firstInvalid = $hfEl; }
      if (firstInvalid) { showTopError('Complete los campos obligatorios.'); firstInvalid.focus(); return; }
      // Aula y color obligatorios
      if (!aula || String(aula).trim() === '') { const $aula = document.getElementById('newclass-aula'); showFieldError($aula,'Digite el aula'); if (!firstInvalid) firstInvalid = $aula; }
      if (!color || String(color).trim() === '') { const $c = document.getElementById('newclass-color-picker'); showFieldError($c,'Seleccione un color'); if (!firstInvalid) firstInvalid = $c; }
      if (firstInvalid) { showTopError('Revise los campos marcados en rojo.'); firstInvalid.focus(); return; }
      // Hora fin debe ser mayor a inicio
      const toMinCmp = (t)=>{ const m = /^([0-2]?\d):([0-5]\d)$/.exec(t||''); if(!m) return NaN; return parseInt(m[1],10)*60 + parseInt(m[2],10); };
      const cmpHi = toMinCmp(hi), cmpHf = toMinCmp(hf);
      if (!Number.isFinite(cmpHi) || !Number.isFinite(cmpHf)) { showFieldError($hiEl,'Hora inválida'); showFieldError($hfEl,'Hora inválida'); showTopError('Formato de hora inválido'); ($hiEl||{}).focus?.(); return; }
      // Intento de autoajuste previo si el usuario escogió 12:00 AM y el inicio es en la mañana
      if (cmpHf <= cmpHi) {
        const isZeroHour = /^00:/.test(String(hf||''));
        const altHfTry = ($hfEl && $hfEl._flatpickr && $hfEl._flatpickr.altInput) ? String($hfEl._flatpickr.altInput.value||'') : '';
        if (isZeroHour && /12:0\d\s*AM/i.test(altHfTry)) {
          const mm = String(hf).slice(3,5) || '00';
          // set a 12:MM (noon) y recomputar
          if ($hfEl && $hfEl._flatpickr) { $hfEl._flatpickr.setDate(`12:${mm}`, true); }
          else { $hfEl.value = `12:${mm}`; }
          const toMinCmp2 = (t)=>{ const m=/^([0-2]?\d):([0-5]\d)$/.exec(t||''); if(!m) return NaN; return parseInt(m[1],10)*60 + parseInt(m[2],10); };
          const cmpHf2 = toMinCmp2($hfEl && $hfEl._flatpickr ? $hfEl._flatpickr.input.value : $hfEl.value);
          if (Number.isFinite(cmpHf2) && cmpHf2 > cmpHi) {
            // pasó la validación
          } else {
            // si aún no, caerá al mensaje de error abajo
          }
        }
      }
      if (cmpHf <= cmpHi) {
        // Mostrar lo que el usuario ve (altInput) para aclarar el caso 12:00 AM vs 12:00 PM
        const altHi = ($hiEl && $hiEl._flatpickr && $hiEl._flatpickr.altInput) ? $hiEl._flatpickr.altInput.value : hi;
        const altHf = ($hfEl && $hfEl._flatpickr && $hfEl._flatpickr.altInput) ? $hfEl._flatpickr.altInput.value : hf;
        showFieldError($hfEl, 'La hora de fin debe ser mayor a la de inicio');
        showTopError(`La hora de fin debe ser mayor a la de inicio. Inicio: ${altHi} | Fin: ${altHf}. Si querías mediodía, selecciona 12:00 PM (12:00 AM es medianoche).`);
        $hfEl.focus(); return;
      }
      // Validación con disponibilidad de ficha (si existe)
      if (fichaAvail) {
        const d = new Date(fecha+'T00:00:00');
        const dayName = ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'][d.getDay()];
        if (!(fichaAvail.dias||[]).includes(dayName)) {
          showFieldError($fechaEl, 'La ficha no permite crear clases el día seleccionado');
          showTopError('La ficha no permite crear clases el día seleccionado.');
          $fechaEl.focus(); return;
        }
        // Validar contra al menos uno de los segmentos del día
        const ranges = rangesForDayFromAvail(fichaAvail, dayName);
        if (ranges.length) {
          const toMin = (t)=>{ const [H,M]=t.split(':').map(x=>parseInt(x,10)); return H*60+(M||0); };
          const tI = toMin(hi), tF = toMin(hf);
          const ok = ranges.some(([lo,hiR]) => { const a=toMin(lo), b=toMin(hiR); return tI>=a && tF<=b; });
          if (!ok) { showFieldError($hiEl,'Fuera de jornada'); showFieldError($hfEl,'Fuera de jornada'); showTopError('La hora seleccionada está fuera de la jornada permitida para ese día.'); $hiEl.focus(); return; }
        }
      }

      const toDT = (d, t) => `${d} ${t}:00`;
      const fecha_inicio = toDT(fecha, hi);
      const fecha_fin = toDT(fecha, hf);

      const fd = new FormData();
      fd.append('profesor_id', profesor_id);
      fd.append('facilitador_id', profesor_id);
      fd.append('ficha_id', ficha_id);
      fd.append('titulo', '');
      fd.append('fecha_inicio', fecha_inicio);
      fd.append('fecha_fin', fecha_fin);
      fd.append('aula', aula);
      fd.append('color', color);
      fd.append('estado', 'programado');
      fd.append('estado_id', '1');

      fetch('/?page=calcolab_crear', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(async r => { const j = await r.json().catch(()=>({})); if (!r.ok) throw new Error(j && j.error ? j.error : ('HTTP '+r.status)); return j; })
        .then(() => {
          // Cerrar modal y refrescar eventos del calendario
          const mEl = document.getElementById('modalNewClass');
          if (window.bootstrap && mEl) { try { bootstrap.Modal.getInstance(mEl)?.hide(); } catch(_){} }
          // refrescar eventos con pipeline propio
          try { if (typeof reloadEvents === 'function') reloadEvents(); } catch(_) {}
          // fallback a refetchEvents si existiera una fuente remota declarada
          try { if (window.calcolab && window.calcolab.calendar && typeof window.calcolab.calendar.refetchEvents === 'function') window.calcolab.calendar.refetchEvents(); } catch(_) {}
          // último recurso: recargar página
          setTimeout(() => { try { if (!window.calcolab || !window.calcolab.calendar) window.location.reload(); } catch(_) {} }, 150);
        })
        .catch(err => {
          showTopError('No se pudo guardar: ' + (err.message || err));
        });
    });
  }
});
