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

    // Estilos para tarjetas flippables de estudiantes (solo se inyectan una vez)
    if (!document.getElementById('est-card-style')) {
      const style = document.createElement('style');
      style.id = 'est-card-style';
      style.textContent = `
        .est-card-wrap { perspective: 1200px; }
        .est-card { position: relative; overflow: visible; }
        .est-card-inner {
          position: relative;
          transform-style: preserve-3d;
          transition: transform 0.6s ease;
        }
        .est-card-face {
          position: relative;
          backface-visibility: hidden;
        }
        .est-card-face--back {
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          transform: rotateY(180deg);
        }
        .est-card.est-card--flipped .est-card-inner {
          transform: rotateY(180deg);
        }
        .est-card-health-strip {
          position: absolute;
          top: 34px;
          right: 18px;
          padding: 4px 10px;
          border-radius: 999px;
          border: none;
          cursor: pointer;
          font-size: 0.7rem;
          font-weight: 600;
          box-shadow: 0 0 0 1px rgba(0,0,0,0.04);
          letter-spacing: 0.06em;
          height: 24px;
          overflow: visible;
          background-clip: padding-box;
          transform: translateY(-100%) scale(0.9);
          transform-origin: center;
          transition: box-shadow 0.2s ease, transform 0.2s ease;
          z-index: 10;
          pointer-events: auto;
        }
        .est-card-health-strip-label { display: inline-block; }
        .est-card-health-strip:hover {
          box-shadow: 0 4px 12px rgba(0,0,0,0.16);
          transform: translateY(-100%) scale(1.08);
        }
      `;
      document.head.appendChild(style);
    }

    const list = Array.isArray(data) ? data.slice() : [];
    const norm = v => String(v ?? '').trim();
    const stripQuotes = (val) => String(val ?? '').trim().replace(/^['"\u201C\u201D]+|['"\u201C\u201D]+$/g, '');
    const getFichaCode = (e) => {
      const raw = norm(e.numero_ficha || e.ficha || e.ficha_nombre || e.ficha_id || e.codigo_ficha || e.codigo);
      return stripQuotes(raw);
    };
    const getCode = (v)=>{
      if (v == null) return '';
      const s = String(v).trim();
      const m = s.match(/\d{3,}/g);
      if (m && m.length) return m.sort((a,b)=>b.length-a.length)[0].replace(/^['"\u201C\u201D]+|['"\u201C\u201D]+$/g, '');
      return s.replace(/^["'\u201C\u201D]+|["'\u201C\u201D]+$/g, '');
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
          const fichaCode = getCode(e.numero_ficha || e.ficha || e.ficha_nombre || e.ficha_id || e.codigo_ficha || e.codigo);
          const grado = stripQuotes(e.grado ?? '');
          const jornada = stripQuotes(e.jornada ?? '');
          const grupo = stripQuotes(e.grupo ?? '');
          const acudienteNombre = norm(e.nombre_completo_acudiente || e.acudiente || e.acudiente_nombre);
          const acudienteCel = norm(e.telefono_acudiente || e.celular_acudiente);
          const parentesco = norm(e.parentesco || e.parentesco_acudiente);
          const emailEst = norm(e.email || e.correo_electronico);
          const telEst = norm(e.telefono);

          const telEstLabel = telEst || 'Sin registrar';
          const emailEstLabel = emailEst || 'Sin registrar';
          const acudienteNombreLabel = acudienteNombre || 'Sin registrar';
          const acudienteCelLabel = acudienteCel || 'Sin registrar';
          const parentescoLabel = parentesco || 'Sin registrar';

          const alergias = stripQuotes(e.alergias_detalle ?? e.alergias ?? '');
          const enfermedad = stripQuotes(e.enfermedad_detalle ?? '');
          const discapacitado = stripQuotes(e.discapacidad_detalle ?? '');
          const meds = stripQuotes(e.medicamentos_detalle ?? '');
          let saludLabel = 'Sin observaciones médicas registradas';
          let saludTipo = 'ok';
          if (alergias) { saludLabel = 'Alergias registradas'; saludTipo = 'warn'; }
          else if (enfermedad) { saludLabel = 'Enfermedad registrada'; saludTipo = 'warn'; }
          else if (discapacitado) { saludLabel = 'Discapacidad registrada'; saludTipo = 'warn'; }
          else if (meds) { saludLabel = 'Medicamentos permanentes'; saludTipo = 'warn'; }

          const saludBg = saludTipo === 'ok'
            ? 'rgba(46, 204, 113, 0.08)'
            : 'rgba(57, 169, 0, 0.16)';
          const saludColor = saludTipo === 'ok' ? '#1e8449' : '#39A900';

          const chips = [];
          if (fichaCode) chips.push(`<span style="padding:2px 8px;border-radius:999px;border:1px solid rgba(0,0,0,.08);background:rgba(46,204,113,.04);font-size:.72rem;">${escapeHtml(String(fichaCode))}</span>`);
          if (grado) chips.push(`<span style="padding:2px 8px;border-radius:999px;border:1px solid rgba(0,0,0,.08);background:rgba(46,204,113,.04);font-size:.72rem;">${escapeHtml('Grado ' + grado)}</span>`);
          if (grupo) chips.push(`<span style="padding:2px 8px;border-radius:999px;border:1px solid rgba(0,0,0,.08);background:rgba(46,204,113,.04);font-size:.72rem;">${escapeHtml('Grupo ' + grupo)}</span>`);
          if (jornada) chips.push(`<span style="padding:2px 8px;border-radius:999px;border:1px solid rgba(0,0,0,.08);background:rgba(46,204,113,.04);font-size:.72rem;">${escapeHtml(jornada)}</span>`);

          const hasMedData = Boolean(
            alergias || enfermedad || discapacitado || meds ||
            e.padece_enfermedad || e.medicamentos_permanentes || e.discapacidad
          );
          const backContent = hasMedData
            ? `
                <div class="item" style="display:flex;flex-direction:column;gap:4px;font-size:.82rem;padding-right:10px;word-break:break-word;overflow-wrap:anywhere;">
                  <div><span style="font-weight:600;">Resumen:</span> ${escapeHtml(saludLabel)}</div>
                  <div><span style="font-weight:600;">Enfermedad:</span> ${escapeHtml(enfermedad || (e.padece_enfermedad ? 'Sí' : 'No'))}</div>
                  <div><span style="font-weight:600;">Alergias:</span> ${escapeHtml(alergias || 'No registradas')}</div>
                  <div><span style="font-weight:600;">Medicamentos permanentes:</span> ${escapeHtml(meds || (e.medicamentos_permanentes ? 'Sí' : 'No'))}</div>
                  <div><span style="font-weight:600;">Discapacidad:</span> ${escapeHtml(discapacitado || (e.discapacidad ? 'Sí' : 'No'))}</div>
                </div>
              `
            : `
                <div class="item" style="display:flex;align-items:center;justify-content:center;height:100%;font-size:.85rem;padding:10px;word-break:break-word;overflow-wrap:anywhere;">
                  <span style="font-weight:600;">No tiene información médica suministrada</span>
                </div>
              `;
          return `
            <section class="tarjeta tarjeta--mini est-card-wrap">
              <div class="mockup est-card">
                <button type="button" class="est-card-health-strip" title="Ver información médica" style="background:${saludBg};color:${saludColor};">
                  <span class="est-card-health-strip-label">Información médica</span>
                </button>
                <div class="est-card-inner">
                  <div class="est-card-face est-card-face--front">
                    <div class="mockup__header">${escapeHtml(nombre)}</div>
                    <div class="item" style="display:flex;flex-direction:column;gap:8px;font-size:.85rem;padding-right:10px;word-break:break-word;overflow-wrap:anywhere;">
                      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:2px;">${chips.join(' ')}</div>
                      <div style="border-top:1px solid rgba(0,0,0,.06);padding-top:4px;display:flex;flex-direction:column;gap:2px;">
                        <div style="font-weight:600;margin-bottom:2px;">Contacto aprendiz</div>
                        <div><span style="font-weight:600;">Teléfono:</span> ${escapeHtml(telEstLabel)}</div>
                        <div><span style="font-weight:600;">Correo:</span> ${escapeHtml(emailEstLabel)}</div>
                      </div>
                      <div style="border-top:1px solid rgba(0,0,0,.06);padding-top:4px;display:flex;flex-direction:column;gap:2px;">
                        <div style="font-weight:600;margin-bottom:2px;">Acudiente</div>
                        <div>${escapeHtml(acudienteNombreLabel)}</div>
                        <div><span style="font-weight:600;">Celular:</span> ${escapeHtml(acudienteCelLabel)}</div>
                        <div><span style="font-weight:600;">Parentesco:</span> ${escapeHtml(parentescoLabel)}</div>
                      </div>
                    </div>
                  </div>
                  <div class="est-card-face est-card-face--back">
                    <div class="mockup__header">${escapeHtml(nombre)}</div>
                    ${backContent}
                  </div>
                </div>
              </div>
            </section>`;
        }).join('');

        // Activar giro de tarjetas al pulsar la tira de salud
        $list.querySelectorAll('.est-card-health-strip').forEach(btn => {
          const card = btn.closest('.est-card');
          if (!card) return;
          const label = btn.querySelector('.est-card-health-strip-label');
          btn.addEventListener('click', ()=>{
            const flipped = card.classList.toggle('est-card--flipped');
            if (label) {
              if (flipped) {
                label.textContent = 'Datos de contacto';
                btn.title = 'Ver datos de contacto';
              } else {
                label.textContent = 'Información médica';
                btn.title = 'Ver información médica';
              }
            }
          });
        });
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
