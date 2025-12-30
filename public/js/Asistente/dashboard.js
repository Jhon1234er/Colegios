document.addEventListener('DOMContentLoaded', () => {
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-ver-colegio');
    if (btn) {
      const colegioId = btn.dataset.id;
      if (!colegioId) return console.warn('Falta data-id en boton .btn-ver-colegio');
      handleVerColegio(colegioId);
    }
  });

  async function handleVerColegio(colegioId) {
    try {
      setInnerHTMLSafe('#facilitadores-container', '<p>Cargando facilitadores...</p>');
      setInnerHTMLSafe('#aprendices-container', '<p>Cargando aprendices...</p>');

      const profs = await fetchJson(`/index.php?page=profesores_por_colegio&colegio_id=${colegioId}`);
      renderProfesores(profs || [], colegioId);

      const studs = await fetchJson(`/index.php?page=estudiantes_por_colegio&colegio_id=${colegioId}`);
      renderEstudiantes(studs || [], colegioId);

      const stats = await fetchJson(`/ajax/asistencias_por_colegio.php?colegio_id=${colegioId}`);
      const colegioRow = document.querySelector(`[data-id="${colegioId}"]`)?.closest('tr');
      const nombreColegio = colegioRow?.cells[1]?.textContent?.trim() || 'Colegio';
      renderAsistencias(stats?.fichas || [], stats?.alertas || [], nombreColegio);
    } catch (err) {
      console.error('Error en dashboard asistente:', err);
      setInnerHTMLSafe('#facilitadores-container', '<p style="color:#c00">No se pudo cargar facilitadores.</p>');
      setInnerHTMLSafe('#aprendices-container', '<p style="color:#c00">No se pudo cargar aprendices.</p>');
    }
  }

  async function fetchJson(url) {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  function renderProfesores(data, colegioId) {
    const root = document.querySelector('#facilitadores-container');
    if (!root) return;
    const list = Array.isArray(data) ? data.slice() : [];

    root.innerHTML = `
      <h3 class="section-title">Facilitadores/Instructores</h3>
      <div class="tarjeta__filters" id="prof-filter">
        <select class="chip-select" id="prof-tipo">
          <option value="">Todos</option>
          <option value="facilitador">Facilitador</option>
          <option value="instructor">Instructor</option>
        </select>
        <input class="input-buscar" id="prof-q" type="text" placeholder="Buscar por nombre" />
        <button class="btn-icon" id="prof-btn" aria-label="Buscar" type="button">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><line x1="20" y1="20" x2="16.5" y2="16.5" stroke="currentColor" stroke-width="2" /></svg>
        </button>
      </div>
      <div class="cards-scroll" id="prof-list"></div>`;

    const $tipo = root.querySelector('#prof-tipo');
    const $q = root.querySelector('#prof-q');
    const $list = root.querySelector('#prof-list');

    function render(items) {
      if (!items || !items.length) {
        $list.innerHTML = '<div class="text-muted">Sin resultados</div>';
        return;
      }
      $list.innerHTML = items.map(p => {
        const nombre = (p.nombre || ((p.nombres||'') + ' ' + (p.apellidos||''))).trim();
        const contratoRaw = p.tip_contrato ?? p.tipo_contrato ?? '';
        const tel = p.telefono ?? '';
        const mailInst = p.correo_institucional ?? '';
        const mailPers = p.correo_electronico ?? '';
        const contratoNorm = String(contratoRaw || '').toLowerCase();
        let rolVisible = 'Docente';
        if (contratoNorm.includes('planta') || contratoNorm.includes('instructor')) {
          rolVisible = 'Instructor';
        } else if (contratoNorm.includes('contratista') || contratoNorm.includes('facilitador')) {
          rolVisible = 'Facilitador';
        }
        return `
          <section class="tarjeta tarjeta--mini">
            <div class="mockup">
              <div class="mockup__header">${escapeHtml(nombre)}</div>
              <div class="item" style="display:flex;flex-direction:column;gap:4px;font-size:.85rem;padding-right:10px;word-break:break-word;overflow-wrap:anywhere;">
                <div><span style="font-weight:600;">Teléfono:</span> ${escapeHtml(tel)}</div>
                <div><span style="font-weight:600;">Institucional:</span> ${escapeHtml(mailInst)}</div>
                <div><span style="font-weight:600;">Personal:</span> ${escapeHtml(mailPers)}</div>
                <div><span style="font-weight:600;">Contrato:</span> ${escapeHtml(contratoRaw)}</div>
                <div><span style="font-weight:600;">Rol:</span> ${escapeHtml(rolVisible)}</div>
              </div>
            </div>
          </section>`;
      }).join('');
    }

    function norm(s) { return String(s||'').trim().toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu,''); }
    
    function apply() {
      const tipoSel = norm($tipo?.value);
      const q = norm($q?.value);
      const filtered = list.filter(p => {
        const contrato = norm(p.tip_contrato ?? p.tipo_contrato ?? '');
        const nombre = norm(p.nombre || ((p.nombres||'') + ' ' + (p.apellidos||'')));
        // Determinar si es un contrato docente válido
        const isInstructor = contrato.includes('planta') || contrato.includes('instructor');
        const isFacilitador = contrato.includes('contratista') || contrato.includes('facilitador');
        const isDocente = isInstructor || isFacilitador;
        if (!isDocente) return false; // descartar usuarios que no son facilitador/instructor
        // Mapear a rol visible: planta/instructor => instructor; contratista/facilitador => facilitador
        const role = isInstructor ? 'instructor' : 'facilitador';
        const byTipo = !tipoSel || role === tipoSel;
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

  function renderEstudiantes(data, colegioId) {
    const root = document.querySelector('#aprendices-container');
    if (!root) return;
    const list = Array.isArray(data) ? data.slice() : [];
    const norm = v => String(v ?? '').trim();
    const stripQuotes = (val) => String(val ?? '').trim().replace(/^['"\u201C\u201D]+|['"\u201C\u201D]+$/g, '');
    const getFichaCode = (e) => {
      const raw = norm(e.numero_ficha || e.ficha || e.ficha_nombre || e.ficha_id || e.codigo_ficha || e.codigo);
      return stripQuotes(raw);
    };
    let fichasOpts = [];

    const buildUI = () => {
      const gradoJornadaPairs = [...new Set(
        list
          .map(e => {
            const g = norm(e.grado);
            const j = norm(e.jornada);
            if (!g && !j) return '';
            return `${g}|${j}`;
          })
          .filter(Boolean)
      )];

      root.innerHTML = `
        <h3 class="section-title">Aprendices</h3>
        <div class="tarjeta__filters" id="stud-filter">
          <select class="chip-select" id="stud-ficha"><option value="">Ficha</option>${fichasOpts.map(f=>`<option value="${escapeHtml(f)}">${escapeHtml(f)}</option>`).join('')}</select>
          <select class="chip-select" id="stud-grado"><option value="">Grado</option>${gradoJornadaPairs.map(p => `<option value="${escapeHtml(p.toLowerCase())}">${escapeHtml(p.replace('|', ' '))}</option>`).join('')}</select>
          <input class="input-buscar" id="stud-q" type="text" placeholder="Buscar" />
          <button class="btn-icon" id="stud-btn" aria-label="Buscar" type="button"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><line x1="20" y1="20" x2="16.5" y2="16.5" stroke="currentColor" stroke-width="2" /></svg></button>
        </div>
        <div class="cards-scroll" id="stud-list"></div>`;

      const $q = root.querySelector('#stud-q');
      const $f = root.querySelector('#stud-ficha');
      const $g = root.querySelector('#stud-grado');
      const $list = root.querySelector('#stud-list');

      function render(items) {
        if (!items || !items.length) {
          $list.innerHTML = '<div class="text-muted">Sin resultados</div>';
          return;
        }
        $list.innerHTML = items.map(e => {
          const nombre = (e.nombre_completo || ((e.nombres||'') + ' ' + (e.apellidos||''))).trim();
          const grado = norm(e.grado);
          const jornada = norm(e.jornada);
          const acudienteNombre = norm(e.nombre_completo_acudiente);
          const acudienteCel = norm(e.telefono_acudiente);
          const parentesco = norm(e.parentesco);
          const fichaCode = getFichaCode(e);
          return `
            <section class="tarjeta tarjeta--mini">
              <div class="mockup">
                <div class="mockup__header">${escapeHtml(nombre)}</div>
                <div class="item" style="font-size:.85rem;padding-right:10px;word-break:break-word;overflow-wrap:anywhere;display:flex;flex-direction:column;gap:4px;">
                  <div>
                    <span style="font-weight:600;">Grado:</span> ${escapeHtml(grado)}
                    &nbsp;&nbsp;•&nbsp;&nbsp;
                    <span style="font-weight:600;">Jornada:</span> ${escapeHtml(jornada)}
                    &nbsp;&nbsp;•&nbsp;&nbsp;
                    <span style="font-weight:600;">Ficha:</span> ${escapeHtml(fichaCode)}
                  </div>
                  <div><span style="font-weight:600;">Acudiente:</span> ${escapeHtml(acudienteNombre)}</div>
                  <div>
                    <span style="font-weight:600;">Celular:</span> ${escapeHtml(acudienteCel)}
                    &nbsp;&nbsp;•&nbsp;&nbsp;
                    <span style="font-weight:600;">Parentesco:</span> ${escapeHtml(parentesco)}
                  </div>
                </div>
              </div>
            </section>`;
        }).join('');
      }

      function apply() {
        const q = norm($q?.value).toLowerCase();
        const fichaSel = stripQuotes($f?.value || '').toLowerCase();
        const gradoSel = norm($g?.value).toLowerCase();
        const filtered = list.filter(e => {
          const nombre = (e.nombre_completo || ((e.nombres||'') + ' ' + (e.apellidos||''))).toLowerCase();
          const byQ = !q || nombre.includes(q);

          const fichaVal = getFichaCode(e).toLowerCase();
          const byF = !fichaSel || fichaVal === fichaSel;

          const pair = `${norm(e.grado).toLowerCase()}|${norm(e.jornada).toLowerCase()}`;
          const byG = !gradoSel || pair === gradoSel;

          return byQ && byF && byG;
        });
        render(filtered);
      }

      $f?.addEventListener('change', () => { if ($g) $g.value = ''; apply(); });
      $g?.addEventListener('change', () => { if ($f) $f.value = ''; apply(); });
      $q?.addEventListener('input', apply);
      root.querySelector('#stud-btn')?.addEventListener('click', apply);
      apply();
    };

    // Cargar opciones de fichas por colegio priorizando 'numero'
    (async () => {
      try {
        if (colegioId) {
          const resp = await fetch(`/ajax/get_fichas_por_colegio.php?colegio_id=${encodeURIComponent(colegioId)}`);
          const arr = await resp.json();
          if (Array.isArray(arr)) {
            const map = new Map();
            arr.forEach(f => {
              const idStr = norm(f.id);
              const numStr = stripQuotes(f.numero ?? f.id);
              if (idStr && numStr) {
                map.set(idStr, numStr);
              }
            });
            if (map.size) {
              list.forEach(e => {
                const idStr = norm(e.ficha_id || e.ficha);
                const numStr = idStr ? map.get(idStr) : '';
                if (numStr) {
                  e.numero_ficha = numStr;
                }
              });
            }
          }
        }
      } catch (_) { /* noop */ }
      fichasOpts = [...new Set(list
        .map(e => getFichaCode(e))
        .filter(Boolean)
      )];
      buildUI();
    })();
  }

  function setInnerHTMLSafe(selector, html) {
    const el = document.querySelector(selector);
    if (el) el.innerHTML = html;
  }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>'"/]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;'})[m]);
  }

  function renderAsistencias(fichasData, alertasData, nombreColegio = '') {
    const container = document.getElementById('chart-container');
    if (!container) return;

    try {
      if (window.myChart) {
        window.myChart.dispose();
        window.myChart = null;
      }

      if (fichasData && fichasData.length > 0) {
        container.innerHTML = '';
        const chart = echarts.init(container, 'white', { locale: 'ES' });
        window.myChart = chart;

        const labels = fichasData.map((it, idx) => (it.numero_ficha || `F-${idx+1}`));

        const option = {
          title: {
            text: `Asistencias - ${nombreColegio}`,
            left: 'center',
            textStyle: { fontSize: 16, fontWeight: 'bold' }
          },
          tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            formatter: (params) => {
              const p = params && params[0];
              if (!p) return '';
              const idx = p.dataIndex ?? 0;
              const code = labels[idx] ?? '';
              const fichaInfo = Array.isArray(fichasData) ? (fichasData[idx] || {}) : {};
              const totalFallas = Number(p.data ?? fichaInfo.total_fallas ?? 0);
              const totalAsist = Number(fichaInfo.total_asistencias ?? 0);
              const baseLine = totalAsist > 0
                ? `Fallas: ${totalFallas}`
                : 'No tiene asistencias registradas';
              return `<strong>Ficha ${code}</strong><br/>${baseLine}`;
            }
          },
          grid: { left: 30, right: 20, top: 50, bottom: 24, containLabel: true },
          xAxis: { type: 'category', data: labels, axisLabel: { interval: 0, rotate: 0 } },
          yAxis: { type: 'value' },
          series: [{
            name: 'Total de fallas',
            type: 'bar',
            data: fichasData.map(item => item.total_fallas),
            itemStyle: { color: '#3b82f6' },
            barMaxWidth: 28
          }]
        };
        chart.setOption(option);
        window.addEventListener('resize', () => chart.resize());
      } else {
        container.innerHTML = '<div style="text-align:center; padding-top: 50px;">No hay datos de asistencias.</div>';
      }
    } catch (err) {
      console.error('Error inicializando ECharts', err);
      container.innerHTML = '<div style="text-align:center; color:red; padding-top: 50px;">Error al cargar el gráfico.</div>';
    }
  }
  // ====== Reporte (sin vista previa) ======
  (function bindReportModal(){
    const $colegio = document.getElementById('selectColegioPDF');
    const $fichasWrap = document.getElementById('fichasContainerPDF');
    const $checkAll = document.getElementById('checkAllFichasPDF');
    const $days = document.getElementById('weekStartPDF');
    const $btnXlsx = document.getElementById('btnDownloadExcel');
    if (!$colegio || !$fichasWrap || !$days || !$btnXlsx) return;
    // Cargar colegios
    (async()=>{
      try{
        const res = await fetch('/index.php?page=asistente_colegios', { credentials:'same-origin' });
        const j = await res.json();
        const arr = (j && j.success && Array.isArray(j.data)) ? j.data : [];
        $colegio.innerHTML = '<option value="">Seleccione</option>' + arr.map(c=>`<option value="${c.id}">${(c.nombre||'Colegio')}</option>`).join('');
      }catch{}
    })();
    // Flatpickr multi
    try{ if (window.flatpickr){ window.flatpickr.localize(window.flatpickr.l10ns.es||{}); window.flatpickr($days,{mode:'multiple', dateFormat:'Y-m-d', altInput:true, altFormat:'d/m/Y'}); } }catch{}
    // Cambiar colegio => cargar fichas
    $colegio.addEventListener('change', async()=>{
      const id = $colegio.value || '';
      $fichasWrap.innerHTML = '<div class="text-muted">Cargando fichas…</div>';
      try{
        const r = await fetch(`/ajax/get_fichas_por_colegio.php?colegio_id=${encodeURIComponent(id)}`, { credentials:'same-origin' });
        const arr = await r.json();
        const list = Array.isArray(arr) ? arr : [];
        $fichasWrap.innerHTML = list.map(f=>{
          const code = (f.numero ?? f.id ?? '').toString();
          return `<label class="form-check me-3 mb-2"><input class="form-check-input" type="checkbox" name="ficha_ids" value="${code}"> <span class="form-check-label">${code}</span></label>`;
        }).join('') || '<div class="text-muted">Sin fichas</div>';
      }catch{ $fichasWrap.innerHTML = '<div class="text-danger">Error cargando fichas</div>'; }
    });
    // Seleccionar todas
    $checkAll?.addEventListener('change',()=>{
      $fichasWrap.querySelectorAll('input[type="checkbox"][name="ficha_ids"]').forEach(ch=>{ ch.checked = $checkAll.checked; });
    });
    // Descargar Excel
    $btnXlsx.addEventListener('click', ()=>{
      try{
        const colegioId = $colegio.value || '';
        const fp = $days._flatpickr; const dates = fp ? fp.selectedDates : [];
        let desde = '', hasta = '';
        if (dates && dates.length){
          const ys = d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
          dates.sort((a,b)=>a-b); desde = ys(dates[0]); hasta = ys(dates[dates.length-1]);
        }
        const url = new URL('/index.php', window.location.origin);
        url.searchParams.set('page','asistente_reporte_excel');
        if (colegioId) url.searchParams.set('colegio_id',colegioId);
        if (desde) url.searchParams.set('desde',desde);
        if (hasta) url.searchParams.set('hasta',hasta);
        // opcional: estado futuro
        window.open(url.toString(), '_blank');
      }catch(_){ /* noop */ }
    });
  })();
});
