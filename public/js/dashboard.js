// /js/dashboard.js
document.addEventListener('DOMContentLoaded', () => {
  // Delegación: responde a cualquier botón .btn-ver-colegio
  document.querySelectorAll('.btn-ver-colegio').forEach(btn => {
    btn.addEventListener('click', () => {
      const colegioId = btn.dataset.id;
      if (!colegioId) return console.warn('Falta data-id en boton .btn-ver-colegio');
      handleVerColegio(colegioId);
    });
  });

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
      renderProfesores(profs || []);

      // Fetch estudiantes
      const studs = await fetchJson(`/index.php?page=estudiantes_por_colegio&colegio_id=${encodeURIComponent(colegioId)}`);
      renderEstudiantes(studs || []);

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
  function renderProfesores(data) {
    const div1 = document.querySelector('.div1');
    if (!div1) return;
    if (!Array.isArray(data) || data.length === 0) {
      div1.innerHTML = '<p>No hay facilitadores registrados para este colegio.</p>';
      return;
    }
    let html = '<h3>Facilitadores</h3><div class="profesor-container">';
    data.forEach(p => {
      const nombre = (p.nombre ?? ((p.nombres||'') + ' ' + (p.apellidos||''))).trim();
      html += `
        <div class="profesor-card">
          <div class="profesor-name">${escapeHtml(nombre)}</div>
          <p>Teléfono: ${escapeHtml(p.telefono ?? '')}</p>
          <p>Institucional: ${escapeHtml(p.correo_institucional ?? '')}</p>
          <p>Personal: ${escapeHtml(p.correo_electronico ?? '')}</p>
          <p>Contrato: ${escapeHtml(p.tip_contrato ?? '')}</p>
          <p>Materia: ${escapeHtml(p.materia ?? '')}</p>
        </div>`;
    });
    html += '</div>';
    div1.innerHTML = html;
  }

  function renderEstudiantes(data) {
    const div2 = document.querySelector('.div2');
    if (!div2) return;
    document.querySelector('.esta')?.remove();
    if (!Array.isArray(data) || data.length === 0) {
      div2.innerHTML = '<p>No hay aprendices registrados para este colegio.</p>';
      return;
    }
    let html = '<h3>Aprendices</h3><div class="students-container">';
    data.forEach(e => {
      const nombre = e.nombre_completo ?? ((e.nombres||'') + ' ' + (e.apellidos||''));
      html += `
        <div class="student-card">
          <div class="student-name">${escapeHtml(nombre)}</div>
          <div class="student-details">
            Grado: ${escapeHtml(e.grado ?? '')}, Jornada: ${escapeHtml(e.jornada ?? '')}<br>
            <strong>Acudiente:</strong> ${escapeHtml(e.nombre_completo_acudiente ?? '')}<br>
            <strong>Celular:</strong> ${escapeHtml(e.telefono_acudiente ?? '')}<br>
            <strong>Parentesco:</strong> ${escapeHtml(e.parentesco ?? '')}<br>
            <strong>Ficha:</strong> ${escapeHtml(e.ficha ?? '')}
          </div>
        </div>`;
    });
    html += '</div>';
    div2.innerHTML = html;
  }

function renderAsistencias(fichasData, alertasData, nombreColegio = '') {
  const container = document.getElementById('chart-container');
  if (!container) return;
  
  // Agregar clase loading
  container.classList.add('loading');
  
  try {
    // Limpiar cualquier instancia previa
    if (window.myChart) {
      window.myChart.dispose();
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
          textStyle: { color: '#374151' }
        },
        grid: {
          left: '3%',
          right: '4%',
          bottom: '15%',
          containLabel: true
        },
        xAxis: {
          type: 'category',
          data: fichasData.map(item => item.numero_ficha || `Ficha ${item.ficha_id}`),
          axisLabel: { 
            rotate: 45, 
            fontSize: 11,
            color: '#6b7280',
            margin: 10
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
