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
  // Resaltar estudiantes con 3+ fallas (después de cargar stats)
  function highlightAlertas(alertas){
    try{
      const ids = new Set((Array.isArray(alertas)?alertas:[])
        .map(a => String(a?.aprendiz_id ?? '').trim())
        .filter(Boolean));
      if (!ids.size) return;
      document.querySelectorAll('.div2 section.tarjeta').forEach(sec=>{
        const sid = String(sec.getAttribute('data-estudiante-id') || '').trim();
        if (sid && ids.has(sid)){
          sec.style.boxShadow = '0 0 0 2px #b91c1c inset';
          const header = sec.querySelector('.mockup__header');
          if (header && !header.querySelector('.badge-3plus')){
            const span = document.createElement('span');
            span.className = 'badge-3plus';
            span.textContent = ' 3+';
            span.style.cssText = 'color:#b91c1c;font-weight:700;margin-left:8px;';
            header.appendChild(span);
          }
        }
      });
    }catch(_){/* noop */}
  }

  // Estado inicial bonito para las tarjetas de Facilitadores y Aprendices
  // cuando aún no se ha seleccionado ningún colegio.
  (function renderEmptySidePanels(){
    const div1 = document.querySelector('.div1');
    const div2 = document.querySelector('.div2');
    if (div1 && !div1.querySelector('.tarjeta__filters') && !div1.querySelector('.tarjeta')) {
      div1.innerHTML = `
        <h3 class="block-title">Facilitadores/Instructores</h3>
        <div class="mockup-box" style="height:100%;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;color:#4b5563;font-size:.9rem;">
          <div style="font-weight:700;margin-bottom:.2rem;">Facilitadores e instructores por colegio</div>
          <div style="max-width:260px;">
            Selecciona un colegio en la parte superior para ver aquí sus facilitadores e instructores.
          </div>
        </div>
      `;
    }

    if (div2 && !div2.querySelector('.tarjeta__filters') && !div2.querySelector('.tarjeta')) {
      div2.innerHTML = `
        <h3 class="block-title">Aprendices</h3>
        <div class="mockup-box" style="height:100%;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;color:#4b5563;font-size:.9rem;">
          <div style="font-weight:700;margin-bottom:.2rem;">Aprendices por colegio</div>
          <div style="max-width:260px;">
            Elige un colegio para cargar aquí las tarjetas con sus aprendices y datos de contacto.
          </div>
        </div>
      `;
    }
  })();

  // Al cargar el dashboard, mostrar estadísticas GLOBALes por defecto (todos los colegios)
  // para que el recuadro de estadísticas no se vea vacío.
  (async ()=>{
    try{
      const statsGlobal = await fetchJson('/ajax/asistencias_por_colegio.php');
      renderAsistencias(statsGlobal?.fichas || [], statsGlobal?.alertas || [], 'Todos los colegios');
    }catch(e){
      // Si falla, dejamos el mensaje por defecto dentro del contenedor
      const container = document.getElementById('chart-container');
      if (container && !container.innerHTML.trim()){
        container.innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#6b7280;font-size:1rem;text-align:center;padding:16px;"><div style="font-weight:600;margin-bottom:0.25rem;">Estadísticas de asistencia por ficha</div><div style="max-width:360px;">Selecciona un colegio en la parte superior para ver sus reportes de asistencia.</div></div>';
      }
    }
  })();

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
      try { console.log('[Admin] profesores count:', Array.isArray(profs)?profs.length:profs); if (Array.isArray(profs) && profs[0]) console.log('[Admin] profesores sample:', profs[0]); } catch(_){ }
      renderProfesores(profs || [], colegioId);

      // Fetch estudiantes
      const studs = await fetchJson(`/index.php?page=estudiantes_por_colegio&colegio_id=${encodeURIComponent(colegioId)}`);
      await renderEstudiantes(studs || [], colegioId);

      // Fetch asistencias/estadísticas
      const stats = await fetchJson(`/ajax/asistencias_por_colegio.php?colegio_id=${encodeURIComponent(colegioId)}`);
      try { console.log('[Admin] stats:', { fichas: (Array.isArray(stats?.fichas)?stats.fichas.length:stats?.fichas), alertas: (Array.isArray(stats?.alertas)?stats.alertas.length:stats?.alertas) }); } catch(_){ }
      renderAsistencias(stats?.fichas || [], stats?.alertas || [], nombreColegio);
      try { highlightAlertas(stats?.alertas || []); } catch(_){}



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
    const clone = res.clone();
    const textBody = await clone.text(); // seguro: no consume el stream principal
    if (!res.ok) {
      console.error('[fetchJson] NO OK', { url, status: res.status, body: textBody?.slice(0,500) });
      throw new Error(`HTTP ${res.status}`);
    }
    const ct = res.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      console.error('[fetchJson] No JSON', { url, contentType: ct, body: textBody?.slice(0,500) });
      throw new Error('Respuesta del servidor no es JSON');
    }
    try {
      return await res.json();
    } catch (e) {
      console.error('[fetchJson] JSON parse error', { url, error: e?.message, body: textBody?.slice(0,500) });
      throw e;
    }
  }

  // Renderizadores
  async function renderProfesores(data, colegioId) {
    const root = document.querySelector('.div1');
    if (!root) {
      return;
    }
    
    const list = Array.isArray(data) ? data.slice() : [];
    
    // Fichas para el select (mismo endpoint del modal)
    let fichas = [];
    try{
      if (colegioId){
        const resp = await fetch(`/ajax/get_fichas_por_colegio.php?colegio_id=${encodeURIComponent(colegioId)}`);
        const arr = await resp.json();
        fichas = Array.isArray(arr) ? arr.map(f=>String(f.numero ?? f.nombre ?? f.id)).filter(Boolean) : [];
      }
    }catch(e){ 
    }
    // Título integrado dentro del contenedor (opción 2)
    root.innerHTML = `
      <h3 class="block-title">Facilitadores/Instructores</h3>
      <div class="tarjeta__filters" id="prof-filter">
        <select class="chip-select" id="prof-tipo">
          <option value="">Todos</option>
          <option value="facilitador">Facilitador</option>
          <option value="instructor">Instructor</option>
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
      
      const html = items.map(p=>{
        const nombre = (p.nombre ?? ((p.nombres||'') + ' ' + (p.apellidos||''))).trim();
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
        } else if (contratoNorm === '') {
          rolVisible = 'Facilitador'; // Por defecto para contratos vacíos
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
      
      $list.innerHTML = html;
    }
    function norm(s){ return String(s||'').trim().toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu,''); }
    function apply(){
      const tipoSel = norm($tipo?.value);
      const q = norm($q?.value);
      
      const filtered = list.filter(p=>{
        const contrato = norm(p.tip_contrato ?? p.tipo_contrato ?? '');
        const nombre = norm(p.nombre ?? ((p.nombres||'') + ' ' + (p.apellidos||'')));
        // Determinar si es un contrato docente válido
        const isInstructor = contrato.includes('planta') || contrato.includes('instructor');
        const isFacilitador = contrato.includes('contratista') || contrato.includes('facilitador');
        const isDocente = isInstructor || isFacilitador || contrato === ''; // Aceptar también contratos vacíos
        if (!isDocente) {
          return false; // descartar usuarios que No son facilitador/instructor
        }
        // Mapear a rol visible: planta/instructor => instructor; contratista/facilitador => facilitador; vacío => facilitador por defecto
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
  async function renderEstudiantes(data, colegioId) {
    const root = document.querySelector('.div2');
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
          /* situada sobre la línea, hacia la derecha del encabezado */
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
          /* -100% para que la cápsula quede apoyada sobre la línea (no atravesada) */
          transform: translateY(-100%) scale(0.9);
          transform-origin: center;
          transition: box-shadow 0.2s ease, transform 0.2s ease;
          z-index: 10;
          pointer-events: auto;
        }
        .est-card-health-strip-label {
          display: inline-block;
        }
        .est-card-health-strip:hover {
          box-shadow: 0 4px 12px rgba(0,0,0,0.16);
          transform: translateY(-100%) scale(1.08);
        }
        .est-card-footer {
          display:flex;
          justify-content:flex-end;
          margin-top:6px;
        }
        .est-card-details-btn {
          display:inline-flex;
          align-items:center;
          justify-content:center;
          gap:6px;
          padding:6px 14px;
          border-radius:999px;
          border:1px solid #39A900;              /* verde institucional */
          background:#ffffff;
          color:#1f2937;
          font-size:.8rem;
          font-weight:600;
          cursor:pointer;
          box-shadow:0 3px 8px rgba(0,0,0,0.08);
          transition:background .15s ease, color .15s ease,
                     box-shadow .15s ease, transform .15s ease,
                     border-color .15s ease;
        }
        .est-card-details-btn:hover {
          background:#39A900;
          color:#ffffff;
          border-color:#39A900;
          box-shadow:0 4px 12px rgba(0,0,0,0.14);
          transform:translateY(-1px);
        }
        .est-card-details-btn:active {
          transform:translateY(0);
          box-shadow:0 2px 6px rgba(0,0,0,0.12);
        }
      `;
      document.head.appendChild(style);
    }
    const list = Array.isArray(data) ? data.slice() : [];
    if (list.length) {
      try { console.log('[Admin] estudiantes sample:', list[0]); } catch(_){}
    }
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
    const fichaIdToNumero = new Map();
    try{
      if (colegioId){
        const resp = await fetch(`/ajax/get_fichas_por_colegio.php?colegio_id=${encodeURIComponent(colegioId)}`);
        const arr = await resp.json();
        // Mapear ficha_id -> numero real de ficha, y lista de numeros para los filtros
        if (Array.isArray(arr)) {
          arr.forEach(f => {
            const idStr = f && f.id != null ? String(f.id).trim() : '';
            const numStr = f && f.numero != null ? String(f.numero).trim() : '';
            if (idStr && numStr) {
              fichaIdToNumero.set(idStr, numStr);
            }
          });
          // Usar SIEMPRE el numero de ficha en el filtro (nunca el nombre)
          fichas = arr.map(f=>String((f.numero ?? f.id) ?? '')).filter(Boolean);
        } else {
          fichas = [];
        }
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

    // Título integrado dentro del contenedor (opción 2)
    root.innerHTML = `
      <h3 class="block-title">Aprendices</h3>
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
        // Determinar el número mostrado de ficha: primero por mapa ficha_id->numero, luego por heurística
        let fichaCode = '';
        if (e.ficha_id != null && fichaIdToNumero.size) {
          const k = String(e.ficha_id).trim();
          fichaCode = fichaIdToNumero.get(k) || '';
        }
        if (!fichaCode) {
          fichaCode = getCode(e.ficha ?? e.numero_ficha ?? e.codigo_ficha ?? e.codigo ?? e.ficha_id ?? e.ficha_nombre);
        }
        const grado = stripQuotes(e.grado ?? '');
        const jornada = stripQuotes(e.jornada ?? '');
        const grupo = stripQuotes(e.grupo ?? '');
        const acudienteNombre = e.nombre_completo_acudiente ?? e.acudiente ?? e.acudiente_nombre ?? '';
        const acudienteCel = e.telefono_acudiente ?? e.celular_acudiente ?? e.telefono ?? '';
        const parentesco = e.parentesco ?? e.parentesco_acudiente ?? '';
        const emailEst = e.email ?? e.correo_electronico ?? '';
        const telEst = e.telefono ?? '';
        const numDoc = stripQuotes(e.numero_documento ?? '');

        // Valores seguros para mostrar (si no hay datos, mostrar texto claro)
        const telEstLabel = telEst || 'Sin registrar';
        const emailEstLabel = emailEst || 'Sin registrar';
        const acudienteNombreLabel = acudienteNombre || 'Sin registrar';
        const acudienteCelLabel = acudienteCel || 'Sin registrar';
        const parentescoLabel = parentesco || 'Sin registrar';

        // Resumen médico simple usando los campos disponibles
        const alergias = stripQuotes(e.alergias_detalle ?? e.alergias ?? '');
        const enfermedad = stripQuotes(e.enfermedad_detalle ?? '');
        const discapacitado = stripQuotes(e.discapacidad_detalle ?? '');
        const meds = stripQuotes(e.medicamentos_detalle ?? '');
        let saludLabel = 'Sin observaciones médicas registradas';
        let saludTipo = 'ok';
        if (alergias) {
          saludLabel = 'Alergias registradas';
          saludTipo = 'warn';
        } else if (enfermedad) {
          saludLabel = 'Enfermedad registrada';
          saludTipo = 'warn';
        } else if (discapacitado) {
          saludLabel = 'Discapacidad registrada';
          saludTipo = 'warn';
        } else if (meds) {
          saludLabel = 'Medicamentos permanentes';
          saludTipo = 'warn';
        }
        const saludBg = saludTipo === 'ok'
          ? 'rgba(46, 204, 113, 0.08)'
          : 'rgba(57, 169, 0, 0.16)'; // verde SENA más intenso cuando hay datos
        const saludColor = saludTipo === 'ok'
          ? '#1e8449'
          : '#39A900';

        // Chips de contexto: grado, grupo, jornada, ficha (se muestran arriba del contenido)
        const chips = [];
        if (fichaCode) chips.push(`<span style="padding:2px 8px;border-radius:999px;border:1px solid rgba(0,0,0,.08);background:rgba(46,204,113,.04);font-size:.72rem;">${escapeHtml(String(fichaCode))}</span>`);
        if (grado) chips.push(`<span style="padding:2px 8px;border-radius:999px;border:1px solid rgba(0,0,0,.08);background:rgba(46,204,113,.04);font-size:.72rem;">${escapeHtml('Grado ' + grado)}</span>`);
        if (grupo) chips.push(`<span style="padding:2px 8px;border-radius:999px;border:1px solid rgba(0,0,0,.08);background:rgba(46,204,113,.04);font-size:.72rem;">${escapeHtml('Grupo ' + grupo)}</span>`);
        if (jornada) chips.push(`<span style="padding:2px 8px;border-radius:999px;border:1px solid rgba(0,0,0,.08);background:rgba(46,204,113,.04);font-size:.72rem;">${escapeHtml(jornada)}</span>`);

        // ¿Hay algún dato médico real además del resumen?
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

        const sid = String(e.usuario_id ?? e.id ?? e.aprendiz_id ?? '').trim();

        return `
          <section class="tarjeta tarjeta--mini est-card-wrap" data-estudiante-id="${escapeHtml(String(e.id ?? e.aprendiz_id ?? ''))}">
            <div class="mockup est-card">
              <button type="button" class="est-card-health-strip" title="Ver información médica" style="background:${saludBg};color:${saludColor};">
                <span class="est-card-health-strip-label">Información médica</span>
              </button>
              <div class="est-card-inner">
                <div class="est-card-face est-card-face--front">
                  <div class="mockup__header">${escapeHtml(nombre)}</div>
                  <div class="item" style="display:flex;flex-direction:column;gap:8px;font-size:.85rem;padding-right:10px;word-break:break-word;overflow-wrap:anywhere;">
                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:2px;">
                      ${chips.join(' ')}
                    </div>
                    <div style="border-top:1px solid rgba(0,0,0,.06);padding-top:4px;display:flex;flex-direction:column;gap:2px;">
                      <div style="font-weight:600;margin-bottom:2px;">Contacto estudiante</div>
                      <div><span style="font-weight:600;">Teléfono:</span> ${escapeHtml(telEstLabel)}</div>
                      <div><span style="font-weight:600;">Correo:</span> ${escapeHtml(emailEstLabel)}</div>
                    </div>
                    <div style="border-top:1px solid rgba(0,0,0,.06);padding-top:4px;display:flex;flex-direction:column;gap:2px;">
                      <div style="font-weight:600;margin-bottom:2px;">Acudiente</div>
                      <div>${escapeHtml(acudienteNombreLabel)}</div>
                      <div><span style="font-weight:600;">Celular:</span> ${escapeHtml(acudienteCelLabel)}</div>
                      <div><span style="font-weight:600;">Parentesco:</span> ${escapeHtml(parentescoLabel)}</div>
                    </div>
                    <div class="est-card-footer">
                      <button
                        type="button"
                        class="est-card-details-btn"
                        data-estudiante-id="${escapeHtml(sid)}"
                        data-full-name="${escapeHtml(nombre)}"
                        data-doc="${escapeHtml(numDoc)}"
                      >Ver toda la información</button>
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

        // Asegurar que siempre sea clickeable, pero dejarla por debajo de la máscara (.mockup::after)
        try {
          btn.style.zIndex = '10'; // la máscara usa 12, así tapa la parte inferior de la cápsula
          btn.style.pointerEvents = 'auto';
        } catch(_){ }

        const label = btn.querySelector('.est-card-health-strip-label');
        if (label) {
          // La animación principal (zoom) se maneja por CSS en .est-card-health-strip
          // Aquí solo controlamos el texto según la cara que se esté mostrando.
        }

        btn.addEventListener('click', ()=>{
          const flipped = card.classList.toggle('est-card--flipped');
          // Si está volteada mostramos texto referente a la cara opuesta
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

      // Botón para ir a la vista completa del aprendiz (reutiliza la vista de búsqueda)
      $list.querySelectorAll('.est-card-details-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const sid = String(btn.getAttribute('data-estudiante-id') || '').trim();
          if (!sid) return;

          const form = document.getElementById('buscador-global');
          const filtroSel = document.getElementById('filtro-busqueda');
          const input = document.getElementById('input-busqueda');

          if (form && filtroSel && input) {
            // Usar el mismo flujo que el buscador: filtro = estudiante, query = doc o nombre
            const doc = String(btn.getAttribute('data-doc') || '').trim();
            const fullName = String(btn.getAttribute('data-full-name') || '').trim();
            const q = doc || fullName;

            filtroSel.value = 'estudiante';
            input.value = q;

            // Disparar el submit para que se cargue la vista de Resultados de búsqueda
            form.dispatchEvent(new Event('submit', { bubbles:true, cancelable:true }));
            return;
          }

          // Fallback: abrir vista clásica de edición si el buscador no está disponible
          const url = `/?page=aprendices&action=editar&id=${encodeURIComponent(sid)}`;
          window.open(url, '_blank');
        });
      });
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
      
      // Inicializar ECharts con configuración básica para evitar problemas
      // con temas o locales no registrados en la build actual.
      const chart = echarts.init(container, null, {
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
      // Mapa: numero_ficha -> cantidad de estudiantes con 3+ fallas
      const alertCountByFicha = new Map();
      try {
        (alertasData || []).forEach(a => {
          const key = String(a?.numero_ficha ?? a?.ficha ?? a?.ficha_id ?? '').trim();
          if (!key) return;
          alertCountByFicha.set(key, (alertCountByFicha.get(key) || 0) + 1);
        });
      } catch(_){}

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
            const idx = p.dataIndex ?? 0;
            const code = labels[idx] ?? '';
            const fichaInfo = Array.isArray(fichasData) ? (fichasData[idx] || {}) : {};
            const totalFallas = Number(p.data ?? fichaInfo.total_fallas ?? 0);
            const totalAsist = Number(fichaInfo.total_asistencias ?? 0);
            const alertCount = alertCountByFicha.get(String(code)) || 0;
            const baseLine = totalAsist > 0
              ? `Fallas: ${totalFallas}`
              : 'No tiene asistencias registradas';
            const extra = alertCount > 0
              ? `<br/><span style="color:#b91c1c">Estudiantes 3+: ${alertCount}</span>`
              : '';
            return `<div><strong>Ficha ${code}</strong><br/>${baseLine}${extra}</div>`;
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
            color: (params) => {
              try {
                const code = labels[params.dataIndex];
                const hasAlerts = (alertCountByFicha.get(String(code)) || 0) > 0;
                return hasAlerts
                  ? new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                      { offset: 0, color: '#ef4444' },
                      { offset: 1, color: '#b91c1c' }
                    ])
                  : new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                      { offset: 0, color: '#3b82f6' },
                      { offset: 1, color: '#1d4ed8' }
                    ]);
              } catch(_) {
                return '#3b82f6';
              }
            },
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
