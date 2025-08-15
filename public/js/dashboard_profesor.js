document.addEventListener('DOMContentLoaded', () => {
  const fichasContainer = document.getElementById('fichasContainer');
  const estudiantesContainer = document.getElementById('estudiantesContainer');

  if (!fichasContainer || !estudiantesContainer) {
    console.warn('Contenedores no encontrados en el DOM.');
    return;
  }

  // ✅ CORREGIDO: página sin guion bajo
  fetch(new URL('?page=profesorficha', window.location.origin))
    .then(response => {
      if (!response.ok) throw new Error("Error en la respuesta");
      return response.json();
    })
    .then(fichas => {
      if (Array.isArray(fichas) && fichas.length > 0) {
        fichas.forEach(ficha => {
          const btn = document.createElement('button');
          btn.textContent = ficha.nombre;
          btn.classList.add('btn-ficha');
          btn.dataset.fichaId = ficha.id;
          fichasContainer.appendChild(btn);
        });
      } else {
        fichasContainer.innerHTML = '<p>No hay fichas asignadas.</p>';
      }
    })
    .catch(err => {
      console.error('Error al cargar fichas:', err);
    });

  // ✅ CORREGIDO: página sin guion bajo
  fichasContainer.addEventListener('click', (e) => {
    if (e.target.matches('.btn-ficha')) {
      const fichaId = e.target.dataset.fichaId;

      fetch(new URL(`?page=estudiantesporficha&ficha_id=${encodeURIComponent(fichaId)}`, window.location.origin))
        .then(response => {
          if (!response.ok) throw new Error("Error en la respuesta de estudiantes");
          return response.json();
        })
        .then(estudiantes => {
          estudiantesContainer.innerHTML = '';
          if (Array.isArray(estudiantes) && estudiantes.length > 0) {
            estudiantes.forEach(est => {
              const div = document.createElement('div');
              div.classList.add('estudiante');
              div.innerHTML = `
                <p><strong>${est.nombres} ${est.apellidos}</strong> - ${est.grado}° (${est.jornada})</p>
                <p>Acudiente: ${est.nombre_completo_acudiente} (${est.parentesco}) - 📞 ${est.telefono_acudiente}</p>
              `;
              estudiantesContainer.appendChild(div);
            });
          } else {
            estudiantesContainer.innerHTML = '<p>No hay estudiantes en esta ficha.</p>';
          }
        })
        .catch(err => {
          console.error('Error al cargar estudiantes:', err);
        });
    }
  });
});
// --- DASHBOARD PROFESOR: FICHAS + CALENDARIO SEMANAL (L-V) ---
document.addEventListener('DOMContentLoaded', () => {
  const fichasContainer = document.getElementById('fichasContainer'); // div3
  const tarjetasFichas  = document.getElementById('tarjetasFichas');   // div4
  const calWrapper      = document.getElementById('calendarioAsistencia'); // nuevo contenedor

  if (!fichasContainer || !tarjetasFichas || !calWrapper) {
    console.warn('Contenedores no encontrados en el DOM.');
    return;
  }

  let fichasGlobal = [];
  let semanaOffset = 0; // 0 = semana actual, -1 = pasada, +1 = próxima
  let estudiantesCache = []; // cache estudiantes de la ficha seleccionada
  let fichaSeleccionada = null; // {id, nombre}
  let registrosAsistencia = {}; // cache de registros de asistencia por fecha

  // ==== Utilidades de fechas (Lunes a Viernes) ====
  const toBogota = (d) => d; // El navegador ya usa local; si usas TZ distinta, ajusta aquí.
  const hoy = () => toBogota(new Date());

  function getMonday(d) {
    const tmp = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const day = tmp.getDay(); // 0=Dom..6=Sab
    const diff = (day === 0 ? -6 : 1 - day); // llevar a lunes
    tmp.setDate(tmp.getDate() + diff);
    tmp.setHours(0,0,0,0);
    return tmp;
  }

  function addDays(date, days) {
    const d = new Date(date);
    d.setDate(d.getDate() + days);
    return d;
  }

  function formatYMD(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth()+1).padStart(2,'0');
    const day = String(d.getDate()).padStart(2,'0');
    return `${y}-${m}-${day}`;
  }

  function formatDM(d) {
    return String(d.getDate()).padStart(2,'0'); // número de día
  }

  function sameYMD(a, b) { return formatYMD(a) === formatYMD(b); }

  // ==== Cargar registros de asistencia ====
  async function cargarRegistrosAsistencia(fichaId, fechaInicio, fechaFin) {
    try {
      const url = `index.php?page=obtener_asistencias&ficha_id=${encodeURIComponent(fichaId)}&fecha_inicio=${formatYMD(fechaInicio)}&fecha_fin=${formatYMD(fechaFin)}`;
      const response = await fetch(url);
      if (!response.ok) throw new Error('Error al cargar registros');
      const registros = await response.json();
      
      // Organizar registros por fecha y estudiante
      registrosAsistencia = {};
      if (Array.isArray(registros)) {
        registros.forEach(registro => {
          const fecha = registro.fecha;
          if (!registrosAsistencia[fecha]) {
            registrosAsistencia[fecha] = {};
          }
          registrosAsistencia[fecha][registro.estudiante_id] = registro.estado;
        });
      }
    } catch (error) {
      console.error('Error cargando registros:', error);
      registrosAsistencia = {};
    }
  }

  // ==== Obtener estado de asistencia ====
  function obtenerEstadoAsistencia(estudianteId, fecha) {
    const fechaStr = formatYMD(fecha);
    if (registrosAsistencia[fechaStr] && registrosAsistencia[fechaStr][estudianteId]) {
      return registrosAsistencia[fechaStr][estudianteId];
    }
    return null;
  }

  // ==== Verificar si hubo clase ese día ====
  function huboClase(fecha) {
    const fechaStr = formatYMD(fecha);
    return registrosAsistencia[fechaStr] && Object.keys(registrosAsistencia[fechaStr]).length > 0;
  }

  // ==== Formatear estado para mostrar ====
  function formatearEstado(estado) {
    const estados = {
      'presente': 'P',
      'ausente': 'A',
      'tarde': 'T',
      'justificado': 'J'
    };
    return estados[estado] || estado;
  }

  // ==== Render Calendario ====
  async function renderCalendario() {
    if (!fichaSeleccionada) return;
    const baseMonday = getMonday(hoy());
    const monday = addDays(baseMonday, semanaOffset * 7);
    const days = [0,1,2,3,4].map(i => addDays(monday, i)); // L-V
    const friday = days[4];

    // Cargar registros de asistencia para esta semana
    await cargarRegistrosAsistencia(fichaSeleccionada.id, monday, friday);

    // Calcular la semana del mes
    const weekOfMonth = Math.ceil((monday.getDate() + 1) / 7);
    const monthName = monday.toLocaleString('default', { month: 'long' });

    const semanaLabel = `Semana ${weekOfMonth} de ${monthName}`;
    const esSemanaActual = (semanaOffset === 0);
    const fechaHoy = hoy();
    const indiceHoy = esSemanaActual ? Math.min(4, Math.max(0, fechaHoy.getDay()-1)) : -1; // 0=Lunes..4=Viernes

    // Encabezado superior
    const header = `
      <div class="cal-topbar">
        <div class="cal-info">
          <strong>Ficha:</strong> ${fichaSeleccionada.nombre}
          &nbsp;|&nbsp; <strong>${semanaLabel}</strong>
        </div>
        <div class="cal-nav">
          <button type="button" class="cal-prev" aria-label="Semana anterior">←</button>
          <button type="button" class="cal-next" aria-label="Semana siguiente">→</button>
        </div>
      </div>
    `;

    // Cabecera de tabla
    const thDias = days.map((d, idx) => {
      const nombres = ['LUNES','MARTES','MIÉRCOLES','JUEVES','VIERNES'];
      return `<th>${nombres[idx]}<div class="cal-dia">${formatDM(d)}</div></th>`;
    }).join('');

    // Filas de estudiantes
    const filas = estudiantesCache.map(e => {
      const celdas = days.map((d, idx) => {
        const esHoy = (esSemanaActual && idx === indiceHoy);
        const fechaStr = formatYMD(d);
        
        if (esHoy) {
          // Editable solo hoy
          return `
            <td data-dia="${fechaStr}" class="celda-editable">
              <select name="asistencias[${e.id}][estado]" class="sel-estado" required>
                <option value="" selected disabled>—</option>
                <option value="presente">Presente</option>
                <option value="ausente">Ausente</option>
                <option value="tarde">Tarde</option>
                <option value="justificado">Justificado</option>
              </select>
              <input type="hidden" name="asistencias[${e.id}][estudiante_id]" value="${e.id}">
            </td>
          `;
        } else if (d < fechaHoy) {
          // Día pasado - mostrar registro real
          const estado = obtenerEstadoAsistencia(e.id, d);
          if (estado) {
            const estadoFormateado = formatearEstado(estado);
            const claseEstado = `estado-${estado}`;
            return `<td class="celda-bloqueada pasado ${claseEstado}" title="${estado}">${estadoFormateado}</td>`;
          } else if (huboClase(d)) {
            // Hubo clase pero no hay registro para este estudiante
            return `<td class="celda-bloqueada pasado estado-falta" title="Sin registro">—</td>`;
          } else {
            // No hubo clase ese día
            return `<td class="celda-bloqueada pasado no-clase" title="No hubo clase">No hubo clase</td>`;
          }
        } else {
          // Día futuro
          return `<td class="celda-bloqueada futuro" title="Día futuro">—</td>`;
        }
      }).join('');

      return `
        <tr>
          <td class="col-estudiante">${e.nombres} ${e.apellidos}</td>
          ${celdas}
          <td class="col-prox">—</td>
        </tr>
      `;
    }).join('');

    // Formulario (solo envía los selects del día actual si es semana actual)
    const puedeEditar = (semanaOffset === 0);
    const formOpen  = `<form id="formAsistencia" method="POST" action="/?page=guardar_asistencia">`;
    const formClose = `</form>`;
    const ocultos = `
      <input type="hidden" name="ficha_id" value="${fichaSeleccionada.id}">
      <!-- Fecha efectiva de registro = día actual -->
      ${puedeEditar ? `<input type="hidden" name="fecha" value="${formatYMD(hoy())}">` : ''}
    `;

    const tabla = `
      <table class="tabla-calendario">
        <thead>
          <tr>
            <th class="col-estudiante">ESTUDIANTES</th>
            ${thDias}
            <th class="col-prox">PRÓXIMAMENTE</th>
          </tr>
        </thead>
        <tbody>
          ${filas}
        </tbody>
      </table>
    `;

    const botones = `
      <div class="cal-actions">
        <button type="submit" id="btnSubir" class="btn-subir" ${puedeEditar ? 'disabled' : 'disabled'}>Subir Registro</button>
        ${!puedeEditar ? `<div class="aviso-vista">Vista de solo lectura (semana distinta a la actual).</div>` : ''}
      </div>
    `;

    calWrapper.innerHTML = formOpen + header + ocultos + tabla + botones + formClose;

    // Listeners navegación
    calWrapper.querySelector('.cal-prev').addEventListener('click', () => {
      semanaOffset -= 1; // siempre vista
      renderCalendario();
    });
    calWrapper.querySelector('.cal-next').addEventListener('click', () => {
      semanaOffset += 1; // siempre vista
      renderCalendario();
    });

    // Validación para activar "Subir Registro"
    if (puedeEditar) {
      const selects = Array.from(calWrapper.querySelectorAll('.sel-estado'));
      const btn     = calWrapper.querySelector('#btnSubir');
      function checkCompleto() {
        const completos = selects.every(s => s.value && s.value.length > 0);
        btn.disabled = !completos;
      }
      selects.forEach(s => s.addEventListener('change', checkCompleto));
      checkCompleto();

      // Confirmación antes de enviar
      const form = calWrapper.querySelector('#formAsistencia');
      form.addEventListener('submit', (ev) => {
        const ok = confirm('¿Confirmas enviar la asistencia de HOY?');
        if (!ok) ev.preventDefault();
      });
    }
  }

  // ==== Cargar lista de fichas ====
  fetch(new URL('?page=profesorficha', window.location.origin))
    .then(r => { if (!r.ok) throw new Error('Error en la respuesta'); return r.json(); })
    .then(fichas => {
      if (!Array.isArray(fichas) || fichas.length === 0) {
        fichasContainer.innerHTML = '<p>No hay fichas asignadas.</p>';
        tarjetasFichas.innerHTML = '<p>No hay fichas disponibles.</p>';
        return;
      }

      fichasGlobal = fichas;

      // Botones (div3)
      fichas.forEach(ficha => {
        const btn = document.createElement('button');
        btn.textContent = ficha.nombre;
        btn.classList.add('btn-ficha');
        btn.dataset.fichaId = ficha.id;
        btn.dataset.fichaNombre = ficha.nombre;
        fichasContainer.appendChild(btn);
      });

      // Tarjetas (div4) - sin filtro por ahora
      fichas.forEach(ficha => {
        const card = document.createElement('div');
        card.classList.add('card-ficha');
        card.innerHTML = `
          <div class="banner"></div>
          <div class="contenido">
            <h4>${ficha.nombre}</h4>
            <p>${ficha.descripcion || 'Formación Titulada Virtual y a Distancia'}</p>
          </div>
          <div class="menu">⋮</div>
        `;
        tarjetasFichas.appendChild(card);
      });
    })
    .catch(err => {
      console.error('Error al cargar fichas:', err);
    });

  // ==== Click en una ficha (div3) -> cargar estudiantes y renderizar calendario ====
  fichasContainer.addEventListener('click', (e) => {
    if (!e.target.matches('.btn-ficha')) return;

    const fichaId = e.target.dataset.fichaId;
    const fichaNombre = e.target.dataset.fichaNombre || (fichasGlobal.find(f=>String(f.id)===String(fichaId))?.nombre ?? 'Ficha');

    fichaSeleccionada = { id: fichaId, nombre: fichaNombre };
    semanaOffset = 0; // siempre volvemos a la semana actual al seleccionar ficha
    calWrapper.innerHTML = '<div class="cal-cargando">Cargando aprendices...</div>';

    fetch(`index.php?page=estudiantesporficha&ficha_id=${encodeURIComponent(fichaId)}`)
      .then(r => { if (!r.ok) throw new Error('Error al cargar estudiantes'); return r.json(); })
      .then(estudiantes => {
        // Filtrar válidos
        estudiantesCache = Array.isArray(estudiantes) ? estudiantes.filter(e => e && e.id) : [];
        if (estudiantesCache.length === 0) {
          calWrapper.innerHTML = '<div class="cal-vacio">No hay aprendices en esta ficha.</div>';
          return;
        }
        renderCalendario();
      })
      .catch(err => {
        console.error('Error estudiantes:', err);
        calWrapper.innerHTML = '<div class="cal-error">Error al cargar estudiantes.</div>';
      });
  });
});

