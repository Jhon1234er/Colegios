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
          <option value="contratista">Contratista</option>
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

    function norm(s) { return String(s||'').trim().toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu,''); }
    
    function apply() {
      const tipoSel = norm($tipo?.value);
      const q = norm($q?.value);
      const filtered = list.filter(p => {
        const contrato = norm(p.tip_contrato);
        const nombre = norm(p.nombre || ((p.nombres||'') + ' ' + (p.apellidos||'')));
        let byTipo = !tipoSel || (tipoSel === 'facilitador' ? /facilitador/.test(contrato) : (tipoSel === 'contratista' ? /contratista/.test(contrato) : contrato.includes(tipoSel)));
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
    const getCode = v => (String(v || '').match(/\d{3,}/g) || []).sort((a,b) => b.length - a.length)[0] || String(v || '');
    const stripQuotes = val => String(val ?? '').trim().replace(/^["']|["']$/g, '');
    const fichasOpts = [...new Set(list.map(e => getCode(e.ficha || e.numero_ficha)).filter(Boolean))].map(stripQuotes);
    const gradoJornadaPairs = [...new Set(list.map(e => `${stripQuotes(e.grado||'').trim()}|${stripQuotes(e.jornada||'').trim()}`).filter(p => p !== '|'))];

    root.innerHTML = `
      <h3 class="section-title">Aprendices</h3>
      <div class="tarjeta__filters" id="stud-filter">
        <select class="chip-select" id="stud-ficha"><option value="">Ficha</option>${fichasOpts.map(f=>`<option value="${escapeHtml(f)}">${escapeHtml(f)}</option>`).join('')}</select>
        <select class="chip-select" id="stud-grado"><option value="">Grado</option>${gradoJornadaPairs.map(p => `<option value="${escapeHtml(p.toLowerCase())}">${escapeHtml(p.replace('|', ' '))}</option>`).join('')}</select>
        <input class="input-buscar" id="stud-q" type="text" placeholder="Buscar" />
        <button class="btn-icon" id="stud-btn" aria-label="Buscar" type="button"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><line x1="20" y1="20" x2="16.5" y2="16.5" stroke="currentColor" stroke-width="2" /></svg></button>
      </div>
      <div class="cards-scroll" id="stud-list"></div>`;

    const $q = root.querySelector('#stud-q'), $f = root.querySelector('#stud-ficha'), $g = root.querySelector('#stud-grado'), $list = root.querySelector('#stud-list');

    function render(items) {
      if (!items || !items.length) {
        $list.innerHTML = '<div class="text-muted">Sin resultados</div>';
        return;
      }
      $list.innerHTML = items.map(e => {
        const nombre = e.nombre_completo || ((e.nombres||'') + ' ' + (e.apellidos||''));
        return `
          <section class="tarjeta tarjeta--mini">
            <div class="mockup"><div class="mockup__header">${escapeHtml(nombre)}</div>
              <div class="item">
                <div>Grado: ${escapeHtml(stripQuotes(e.grado ?? ''))} &nbsp;&nbsp; Jornada: ${escapeHtml(stripQuotes(e.jornada ?? ''))}</div>
                <div>Acudiente: ${escapeHtml(e.nombre_completo_acudiente ?? '')}</div>
                <div>Celular: ${escapeHtml(e.telefono_acudiente ?? '')}</div>
                <div>Ficha: ${escapeHtml(getCode(e.ficha || e.numero_ficha))}</div>
              </div>
            </div>
          </section>`;
      }).join('');
    }

    function apply() {
      const q = ($q?.value||'').toLowerCase(), fichaSel = ($f?.value||'').toLowerCase(), gradoSel = ($g?.value||'').toLowerCase();
      const filtered = list.filter(e => {
        const byQ = !q || (e.nombre_completo || '').toLowerCase().includes(q);
        const byF = !fichaSel || getCode(e.ficha || e.numero_ficha).toLowerCase() === fichaSel;
        let byG = !gradoSel || `${stripQuotes(String(e.grado||'')).toLowerCase()}|${stripQuotes(String(e.jornada||'')).toLowerCase()}` === gradoSel;
        return byQ && byF && byG;
      });
      render(filtered);
    }

    $f?.addEventListener('change', () => { if ($g) $g.value = ''; apply(); });
    $g?.addEventListener('change', () => { if ($f) $f.value = ''; apply(); });
    $q?.addEventListener('input', apply);
    root.querySelector('#stud-btn')?.addEventListener('click', apply);
    apply();
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
            formatter: (params) => `<strong>Ficha ${labels[params[0].dataIndex]}</strong><br/>Fallas: ${params[0].data}`
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
});
