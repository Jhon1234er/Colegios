// /js/dashboard.js
document.addEventListener('DOMContentLoaded', () => {
  // Delegación de eventos en el documento para botones dinámicos
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-ver-colegio');
    if (btn) {
      const colegioId = btn.dataset.id;
      if (!colegioId) return console.warn('Falta data-id en boton .btn-ver-colegio');
      handleVerColegio(colegioId);
    }
  });

  // Títulos siempre visibles y centrados sobre los contenedores
  setSectionTitle(document.querySelector('.div1'), 'title-profes', 'Facilitadores/Instructores');
  setSectionTitle(document.querySelector('.div2'), 'title-students', 'Aprendices');

  async function handleVerColegio(colegioId) {
    try {
      // Loading state
      setInnerHTMLSafe('.div1', '<p>Cargando facilitadores...</p>');
      setInnerHTMLSafe('.div2', '<p>Cargando aprendices...</p>');
      setInnerHTMLSafe('#chart-container', '<p>Cargando estadísticas...</p>');

      // Get college name from the table
      const colegioRow = document.querySelector(`[data-id="${colegioId}"]`)?.closest('tr');
      const nombreColegio = colegioRow?.cells[1]?.textContent?.trim() || 'Colegio';

      // Fetch profesores
      const profs = await fetchJson(`/index.php?page=profesores_por_colegio&colegio_id=${encodeURIComponent(colegioId)}`);
      renderProfesores(profs || [], colegioId);

      // Fetch estudiantes
      const studs = await fetchJson(`/index.php?page=estudiantes_por_colegio&colegio_id=${encodeURIComponent(colegioId)}`);
      renderEstudiantes(studs || [], colegioId);

      // Fetch asistencias/estadísticas
      const stats = await fetchJson(`/ajax/asistencias_por_colegio.php?colegio_id=${encodeURIComponent(colegioId)}`);
      renderAsistencias(stats?.fichas || [], stats?.alertas || [], nombreColegio);



    } catch (err) {
      console.error('Error en dashboard:', err);
      // Mensajes de error visibles
      setInnerHTMLSafe('.div1', '<p style="color:#c00">No se pudo cargar facilitadores.</p>');
      setInnerHTMLSafe('.div2', '<p style="color:#c00">No se pudo cargar aprendices.</p>');
      setInnerHTMLSafe('#chart-container', '<p style="color:#c00">No se pudieron cargar estadísticas.</p>');
    }
  }

  // Helper: fetch y asegurar que la respuesta sea JSON válido
  async function fetchJson(url) {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) {
      const text = await res.text();
      console.error('fetchJson: respuesta no OK', res.status, text);
      throw new Error(`HTTP ${res.status}`);
    }
    const ct = res.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      const text = await res.text();
      console.error('fetchJson: respuesta no es JSON:', text);
      throw new Error('Respuesta del servidor no es JSON');
    }
    return res.json();
  }

  // Renderizadores
  async function renderProfesores(data, colegioId) {
    const root = document.querySelector('.div1');
    if (!root) return;
    const list = Array.isArray(data) ? data.slice() : [];
    // Fichas para el select (mismo endpoint del modal)
    let fichas = [];
    try{
      if (colegioId){
        const resp = await fetch(`/ajax/get_fichas_por_colegio.php?colegio_id=${encodeURIComponent(colegioId)}`);
        const arr = await resp.json();
        fichas = Array.isArray(arr) ? arr.map(f=>String(f.numero ?? f.nombre ?? f.id)).filter(Boolean) : [];
      }
    }catch(e){ /* noop */ }
    // Título fuera del contenedor (siempre visible)
    setSectionTitle(root, 'title-profes', 'Facilitadores/Instructores');
    // Filtros y tarjetas dentro del contenedor
    root.innerHTML = `
      <div class="tarjeta__filters" id="prof-filter">
        <select class="chip-select" id="prof-tipo">
          <option value="">Todos</option>
          <option value="facilitador">Facilitador</option>
          <option value="contratista">Contratista</option>
        </select>
        <input class="input-buscar" id="prof-q" type="text" placeholder="Buscar por nombre" />
        <button class="btn-icon" id="prof-btn" aria-label="Buscar" type="button">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
            <line x1="20" y1="20" x2="16.5" y2="16.5" stroke="currentColor" stroke-width="2" />
          </svg>
        </button>
      </div>
      <div class="cards-scroll" id="prof-list"></div>`;

    const $tipo = root.querySelector('#prof-tipo');
    const $q = root.querySelector('#prof-q');
    const $list = root.querySelector('#prof-list');

    function render(items){
      if (!items || !items.length){
        $list.innerHTML = '<div class="text-muted">Sin resultados</div>';
        return;
      }
      $list.innerHTML = items.map(p=>{
        const nombre = (p.nombre ?? ((p.nombres||'') + ' ' + (p.apellidos||''))).trim();
        return `
          <section class="tarjeta tarjeta--mini">
            <div class="mockup">
              <div class="mockup__header">${escapeHtml(nombre)}</div>
              <div class="item">
                <div>Teléfono: ${escapeHtml(p.telefono ?? '')}</div>
                <div>Institucional: ${escapeHtml(p.correo_institucional ?? '')}</div>
                <div>Personal: ${escapeHtml(p.correo_electronico ?? '')}</div>
                <div>Contrato: ${escapeHtml(p.tip_contrato ?? '')}</div>
              </div>
            </div>
          </section>`;
      }).join('');
    }
    function norm(s){ return String(s||'').trim().toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu,''); }
    function apply(){
      const tipoSel = norm($tipo?.value);
      const q = norm($q?.value);
      const filtered = list.filter(p=>{
        const contrato = norm(p.tip_contrato);
        const nombre = norm(p.nombre ?? ((p.nombres||'') + ' ' + (p.apellidos||'')));
        // Aceptar variantes: facilitador(a), contratista, etc.
        let byTipo = true;
        if (tipoSel){
          if (tipoSel === 'facilitador') byTipo = /facilitador/.test(contrato);
          else if (tipoSel === 'contratista') byTipo = /contratista/.test(contrato);
          else byTipo = contrato.includes(tipoSel);
        }
        const byQ = !q || nombre.includes(q);
        return byTipo && byQ;
      });
      render(filtered);
    }
    $tipo?.addEventListener('change', apply);
    $q?.addEventListener('input', apply);
    root.querySelector('#prof-btn')?.addEventListener('click', apply);
    apply();
  }
  async function renderEstudiantes(data, colegioId) {
    const root = document.querySelector('.div2');
    if (!root) return;
    const list = Array.isArray(data) ? data.slice() : [];
    // Helper para extraer CÓDIGO numérico de ficha
    const getCode = (v)=>{
      if (v == null) return '';
      const s = String(v).trim();
      const m = s.match(/\d{3,}/g);
      if (m && m.length) return m.sort((a,b)=>b.length-a.length)[0].replace(/^['"\u201C\u201D]+|['"\u201C\u201D]+$/g, '');
      return s.replace(/^["'\u201C\u201D]+|["'\u201C\u201D]+$/g, '');
    };
    // Quitar comillas al inicio/fin (p. ej., 10" o "Mañana")
    const stripQuotes = (val) => String(val ?? '').trim().replace(/^["'\u201C\u201D]+|["'\u201C\u201D]+$/g, '');
    // Quitar comillas alrededor de valores (p. ej. 10" o "Mañana")
    let fichas = [];
    try{
      if (colegioId){
        const resp = await fetch(`/ajax/get_fichas_por_colegio.php?colegio_id=${encodeURIComponent(colegioId)}`);
        const arr = await resp.json();
        fichas = Array.isArray(arr) ? arr.map(f=>String(f.numero ?? f.nombre ?? f.id)).filter(Boolean) : [];
      }
    }catch(e){
      fichas = [];
    }
    if (!fichas.length){
      fichas = Array.from(new Set(list.map(e=>getCode(e.ficha||e.numero_ficha||e.codigo_ficha||e.codigo)).filter(Boolean)));
    }
    const grados = Array.from(new Set(list.map(e=>String(e.grado||'').trim()).filter(Boolean)));

    // Normalizar opciones (sin comillas)
    const fichasOpts = fichas.map(f => stripQuotes(f));
    // Pares Grado|Jornada únicos, ordenados ascendentemente por grado y luego jornada (Mañana, Tarde)
    const gradoJornadaPairs = Array.from(new Set(list.map(e=>{
      const g = stripQuotes(e.grado||'').trim();
      if (!g) return '';
      const j = stripQuotes(e.jornada||'').trim();
      return `${g}|${j}`;
    }).filter(Boolean)));
    const jornadaOrder = s => {
      const x = (s||'').toLowerCase();
      if (x === 'mañana') return 0; if (x === 'tarde') return 1; return 2;
    };
    gradoJornadaPairs.sort((a,b)=>{
      const [ga,ja] = a.split('|');
      const [gb,jb] = b.split('|');
      const na = parseInt(ga,10); const nb = parseInt(gb,10);
      const ca = isNaN(na)? Number.POSITIVE_INFINITY : na;
      const cb = isNaN(nb)? Number.POSITIVE_INFINITY : nb;
      if (ca !== cb) return ca - cb;
      return jornadaOrder(ja) - jornadaOrder(jb);
    });

    // Título fuera del contenedor (siempre visible)
    setSectionTitle(root, 'title-students', 'Aprendices');
    // Filtros y tarjetas dentro del contenedor
    root.innerHTML = `
      <div class="tarjeta__filters" id="stud-filter">
        <select class="chip-select" id="stud-ficha">
          <option value="">Ficha</option>
          ${fichasOpts.map(f=>`<option value="${escapeHtml(f)}">${escapeHtml(f)}</option>`).join('')}
        </select>
        <select class="chip-select" id="stud-grado">
          <option value="">Grado</option>
          ${gradoJornadaPairs.map(pair=>{
            const [g,j] = pair.split('|');
            const label = `${g}${j? ' ' + j : ''}`;
            const val = `${g.toLowerCase()}|${(j||'').toLowerCase()}`;
            return `<option value="${escapeHtml(val)}">${escapeHtml(label)}</option>`;
          }).join('')}
        </select>
        <input class="input-buscar" id="stud-q" type="text" placeholder="Buscar" />
        <button class="btn-icon" id="stud-btn" aria-label="Buscar" type="button">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
            <line x1="20" y1="20" x2="16.5" y2="16.5" stroke="currentColor" stroke-width="2" />
          </svg>
        </button>
      </div>
      <div class="cards-scroll" id="stud-list"></div>`;

    const $q = root.querySelector('#stud-q');
    const $f = root.querySelector('#stud-ficha');
    const $g = root.querySelector('#stud-grado');
    const $list = root.querySelector('#stud-list');

    function render(items){
      if (!items || !items.length){
        $list.innerHTML = '<div class="text-muted">Sin resultados</div>';
        return;
      }
      $list.innerHTML = items.map(e=>{
        const nombre = e.nombre_completo ?? ((e.nombres||'') + ' ' + (e.apellidos||''));
        const fichaCode = getCode(e.ficha ?? e.numero_ficha ?? e.codigo_ficha ?? e.codigo);
        const grado = stripQuotes(e.grado ?? '');
        const jornada = stripQuotes(e.jornada ?? '');
        return `
          <section class="tarjeta tarjeta--mini">
            <div class="mockup">
              <div class="mockup__header">${escapeHtml(nombre)}</div>
              <div class="item">
                <div>Grado: ${escapeHtml(grado)} &nbsp;&nbsp; Jornada: ${escapeHtml(jornada)}</div>
                <div>Acudiente: ${escapeHtml(e.nombre_completo_acudiente ?? '')}</div>
                <div>Celular: ${escapeHtml(e.telefono_acudiente ?? '')}</div>
                <div>Parentesco: ${escapeHtml(e.parentesco ?? '')}</div>
                <div>Ficha: ${escapeHtml(fichaCode)}</div>
              </div>
            </div>
          </section>`;
      }).join('');
    }
    function apply(){
      const q = ($q?.value||'').toLowerCase();
      const fichaSel = ($f?.value||'').toLowerCase();
      const gradoSel = ($g?.value||'').toLowerCase();
      const filtered = list.filter(e=>{
        const f = getCode(e.ficha||e.numero_ficha||e.codigo_ficha||e.codigo).toLowerCase();
        const g = stripQuotes(String(e.grado||'')).toLowerCase();
        const j = stripQuotes(String(e.jornada||'')).toLowerCase();
        const nombre = (e.nombre_completo ?? ((e.nombres||'') + ' ' + (e.apellidos||''))).toLowerCase();
        const byQ = !q || nombre.includes(q);
        const byF = !fichaSel || f === fichaSel;
        let byG = true;
        if (gradoSel){
          const [gs, js] = gradoSel.split('|');
          byG = (!gs || g === gs) && (!js || j === js);
        }
        return byQ && byF && byG;
      });
      render(filtered);
    }
    // Dependencias: si elijo Ficha, limpio Grado; si elijo Grado, limpio Ficha
    $f?.addEventListener('change', ()=>{ if ($g) $g.value = ''; apply(); });
    $g?.addEventListener('change', ()=>{ if ($f) $f.value = ''; apply(); });
    $q?.addEventListener('input', apply);
    root.querySelector('#stud-btn')?.addEventListener('click', apply);
    apply();
  }

function renderAsistencias(fichasData, alertasData, nombreColegio = '') {
  const container = document.getElementById('chart-container');
  if (!container) {
    console.warn('Contenedor chart-container no encontrado');
    return;
  }
  
  // Agregar clase loading
  container.classList.add('loading');
  
  try {
    // Limpiar cualquier instancia previa de forma segura
    if (window.myChart) {
      try {
        window.myChart.dispose();
      } catch(e) {
        console.warn('Error al limpiar gráfico previo:', e);
      }
      window.myChart = null;
    }
    
    // Mostrar gráfico
    if (fichasData && fichasData.length > 0) {
      container.innerHTML = '';
      container.classList.remove('loading');
      
      const chart = echarts.init(container, 'white', { 
        locale: 'ES',
        devicePixelRatio: window.devicePixelRatio || 1
      });
      
      // Guardar referencia global
      window.myChart = chart;
      
      // Preparar etiquetas usando CÓDIGO/NUMERO de ficha, no nombres
      function getFichaCode(item, idx){
        // Usar SOLO identificadores de ficha (nunca nombres)
        const code = item?.numero_ficha ?? item?.numero ?? item?.codigo ?? item?.codigo_ficha ?? item?.ficha_id ?? item?.id;
        if (code != null && String(code).trim() !== '') return String(code).trim();
        return `F-${idx+1}`;
      }
      const labels = fichasData.map((it, idx) => getFichaCode(it, idx));

      const option = {
        title: {
          text: `Asistencias - ${nombreColegio}`,
          left: 'center',
          textStyle: { 
            fontSize: 18, 
            fontWeight: 'bold',
            color: '#1f2937'
          }
        },
        tooltip: {
          trigger: 'axis',
          axisPointer: { type: 'shadow' },
          backgroundColor: 'rgba(255, 255, 255, 0.95)',
          borderColor: '#e5e7eb',
          borderWidth: 1,
          textStyle: { color: '#374151' },
          formatter: (params)=>{
            const p = params && params[0];
            if (!p) return '';
            const code = labels[p.dataIndex] ?? '';
            return `<div><strong>Ficha ${code}</strong><br/>Fallas: ${p.data ?? 0}</div>`;
          }
        },
        grid: {
          left: 20,
          right: 20,
          top: 50,
          bottom: 24,
          containLabel: false
        },
        xAxis: {
          type: 'category',
          data: labels,
          axisLabel: { 
            rotate: 0,
            fontSize: 11,
            color: '#6b7280',
            margin: 6,
            interval: 0,
            overflow: 'none', // mostrar el código completo cuando es corto
            hideOverlap: true,
            formatter: function(value, idx){
              const code = labels[idx] || value;
              return `Ficha ${code}`;
            }
          },
          axisLine: { lineStyle: { color: '#e5e7eb' } }
        },
        yAxis: { 
          type: 'value',
          axisLabel: { 
            fontSize: 11,
            color: '#6b7280'
          },
          axisLine: { lineStyle: { color: '#e5e7eb' } },
          splitLine: { lineStyle: { color: '#f3f4f6' } }
        },
        series: [{
          name: 'Total de fallas',
          type: 'bar',
          data: fichasData.map(item => item.total_fallas),
          itemStyle: { 
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: '#3b82f6' },
              { offset: 1, color: '#1d4ed8' }
            ]),
            borderRadius: [4, 4, 0, 0]
          },
          barMaxWidth: 28,
          barCategoryGap: '40%',
          emphasis: {
            itemStyle: {
              color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                { offset: 0, color: '#60a5fa' },
                { offset: 1, color: '#3b82f6' }
              ])
            }
          }
        }]
      };
      
      chart.setOption(option);
      window.addEventListener('resize', () => chart.resize());
    } else {
      container.classList.remove('loading');
      container.innerHTML = '<div style="text-align:center; color:#6b7280; margin-top:50px; font-size:1.1rem;"><div style="font-size:2rem; margin-bottom:1rem;">📊</div>No hay datos de asistencias para este colegio</div>';
    }
  } catch (err) {
    console.error('Error inicializando ECharts', err);
    container.classList.remove('loading');
    container.innerHTML = '<div style="text-align:center; color:#ef4444; margin-top:50px;">❌ Error al cargar el gráfico</div>';
  }

  // alertas
  const div3 = document.querySelector('.div3');
  if (!div3) return;
  div3.querySelector('.alertas')?.remove();
  if (alertasData && alertasData.length > 0) {
    let alertHtml = '<div class="alertas"><h4>⚠ Estudiantes con 3+ fallas</h4><ul>';
    alertasData.forEach(a => {
      alertHtml += `<li><strong>${escapeHtml((a.nombres ?? '') + ' ' + (a.apellidos ?? ''))}</strong> - Ficha ${escapeHtml(a.numero_ficha ?? '')} (${escapeHtml(a.total_fallas ?? '')} fallas)</li>`;
    });
    alertHtml += '</ul></div>';
    div3.insertAdjacentHTML('beforeend', alertHtml);
  }
}

  // pequeñas utilidades
  function setInnerHTMLSafe(selector, html) {
    const el = document.querySelector(selector);
    if (el) el.innerHTML = html;
  }
  // Inserta/actualiza un título fuera del contenedor (antes del bloque raíz)
  function setSectionTitle(rootEl, id, text){
    try {
      if (!rootEl || !rootEl.parentElement) return;
      let t = document.getElementById(id);
      if (!t) {
        t = document.createElement('h3');
        t.id = id;
        t.className = 'section-title';
        rootEl.parentElement.insertBefore(t, rootEl);
      }
      t.textContent = String(text ?? '').trim();
    } catch(err) {
      console.warn('setSectionTitle error', err);
    }
  }
  function escapeHtml(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
});

// =============================
// Tabla "Colegios Gestionados": búsqueda + paginación
// =============================
document.addEventListener('DOMContentLoaded', ()=>{
  const $table = document.getElementById('tabla-colegios');
  const $tbody = $table?.querySelector('tbody');
  const $search = document.getElementById('tabla-search');
  const $pager = document.getElementById('tabla-pager');
  if (!$table || !$tbody || !$pager) return;

  const PAGE_SIZE = 5;
  const rows = Array.from($tbody.querySelectorAll('tr'));
  let filteredIdx = rows.map((_,i)=>i);
  let page = 1;

  function norm(s){return String(s||'').toLowerCase();}

  function applyFilter(){
    const q = norm($search?.value);
    if (!q){
      filteredIdx = rows.map((_,i)=>i);
    } else {
      filteredIdx = rows
        .map((tr,i)=>({i, txt: norm(tr.textContent)}))
        .filter(o=> o.txt.includes(q))
        .map(o=>o.i);
    }
    page = 1;
    render();
  }

  function render(){
    // ocultar todos
    rows.forEach(tr=>{ tr.style.display = 'none'; });
    // mostrar página
    const totalPages = Math.max(1, Math.ceil(filteredIdx.length / PAGE_SIZE));
    if (page > totalPages) page = totalPages;
    const start = (page-1)*PAGE_SIZE;
    const end = start + PAGE_SIZE;
    const showIdx = filteredIdx.slice(start, end);
    showIdx.forEach(i=>{ rows[i].style.display = ''; });
    renderPager(totalPages);
  }

  function renderPager(total){
    if (!$pager) return;
    $pager.innerHTML = '';
    const btn = (label, targetPage, disabled=false, active=false)=>{
      const b = document.createElement('button');
      b.textContent = label;
      b.type = 'button';
      b.disabled = !!disabled;
      b.className = 'btn-pager' + (active? ' active' : '');
      b.style.cssText = 'min-width:34px;height:34px;border:1px solid #d1d5db;border-radius:10px;background:#fff;cursor:pointer;padding:0 10px;';
      if (active){ b.style.background = '#e5f5e8'; b.style.borderColor = '#39A900'; b.style.fontWeight = '700'; }
      b.addEventListener('click', ()=>{ page = targetPage; render(); });
      return b;
    };
    // Prev
    $pager.appendChild(btn('«', Math.max(1, page-1), page===1));
    // Números (máx 7 visibles centrados)
    const windowSize = 7;
    let start = Math.max(1, page - Math.floor(windowSize/2));
    let end = Math.min(total, start + windowSize - 1);
    start = Math.max(1, end - windowSize + 1);
    for (let p = start; p <= end; p++) $pager.appendChild(btn(String(p), p, false, p===page));
    // Next
    $pager.appendChild(btn('»', Math.min(total, page+1), page===total));
    // Info si no hay resultados
    if (filteredIdx.length === 0){
      const info = document.createElement('div');
      info.textContent = 'Sin resultados';
      info.style.cssText = 'color:#6b7280;margin-left:8px;';
      $pager.appendChild(info);
    }
  }

  // listeners
  $search?.addEventListener('input', ()=>{
    // pequeño debounce inline
    clearTimeout($search.__t);
    $search.__t = setTimeout(applyFilter, 150);
  });
  document.getElementById('tabla-search-btn')?.addEventListener('click', applyFilter);

  // inicial
  applyFilter();
});
