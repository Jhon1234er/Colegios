document.addEventListener('DOMContentLoaded', () => {
  const tarjetasFichas  = document.getElementById('tarjetasFichas');   // contenedor fichas
  const calWrapper      = document.getElementById('calendarioAsistencia'); // contenedor calendario

  if (!tarjetasFichas || !calWrapper) {
    console.warn('Contenedores no encontrados en el DOM.');
    return;
  }

  let fichasGlobal = [];
  let semanaOffset = 0; 
  let estudiantesCache = []; 
  let fichaSeleccionada = null; 
  let registrosAsistencia = {}; 

  // ==== Utilidades de fechas ====
  const hoy = () => new Date();
  function getMonday(d) {
    const tmp = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const day = tmp.getDay();
    const diff = (day === 0 ? -6 : 1 - day);
    tmp.setDate(tmp.getDate() + diff);
    tmp.setHours(0,0,0,0);
    return tmp;
  }
  function addDays(date, days) { const d = new Date(date); d.setDate(d.getDate() + days); return d; }
  function formatYMD(d) { return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; }
  function formatDM(d) { return String(d.getDate()).padStart(2,'0'); }

  // ==== Asistencias ====
  async function cargarRegistrosAsistencia(fichaId, fechaInicio, fechaFin) {
    try {
      const url = `index.php?page=obtener_asistencias&ficha_id=${encodeURIComponent(fichaId)}&fecha_inicio=${formatYMD(fechaInicio)}&fecha_fin=${formatYMD(fechaFin)}`;
      console.log("📌 URL de fetch:", url, fichaId, fechaInicio, fechaFin);

      const response = await fetch(url);
      if (!response.ok) throw new Error('Error al cargar registros');

      const text = await response.text();

      let registros;
      try {
        registros = JSON.parse(text);
      } catch (e) {
        registrosAsistencia = {};
        return; // simplemente salimos sin mostrar error
      }

      registrosAsistencia = {};
      if (Array.isArray(registros)) {
        registros.forEach(registro => {
          const fecha = registro.fecha;
          if (!registrosAsistencia[fecha]) registrosAsistencia[fecha] = {};
          registrosAsistencia[fecha][registro.estudiante_id] = registro.estado;
        });
      }
    } catch (error) {
      console.error('Error cargando registros:', error);
      registrosAsistencia = {};
    }
  }

  function obtenerEstadoAsistencia(estudianteId, fecha) {
    const fechaStr = formatYMD(fecha);
    return registrosAsistencia[fechaStr]?.[estudianteId] ?? null;
  }
  function huboClase(fecha) {
    const fechaStr = formatYMD(fecha);
    return registrosAsistencia[fechaStr] && Object.keys(registrosAsistencia[fechaStr]).length > 0;
  }
  function formatearEstado(estado) {
    const estados = { 'presente':'P', 'ausente':'A', 'tarde':'T', 'justificado':'J' };
    return estados[estado] || estado;
  }

  // ==== Render Calendario ====
  async function renderCalendario() {
    if (!fichaSeleccionada) return;
    const baseMonday = getMonday(hoy());
    const monday = addDays(baseMonday, semanaOffset * 7);
    const days = [0,1,2,3,4].map(i => addDays(monday, i));
    const friday = days[4];
    await cargarRegistrosAsistencia(fichaSeleccionada.id, monday, friday);

    const weekOfMonth = Math.ceil((monday.getDate() + 1) / 7);
    const monthName = monday.toLocaleString('default', { month: 'long' });
    const semanaLabel = `Semana ${weekOfMonth} de ${monthName}`;
    const esSemanaActual = (semanaOffset === 0);
    const fechaHoy = hoy();
    const indiceHoy = esSemanaActual ? Math.min(4, Math.max(0, fechaHoy.getDay()-1)) : -1;

    const header = `
      <div class="cal-topbar">
        <div class="cal-info">
          <strong>Ficha:</strong> ${fichaSeleccionada.nombre}
          &nbsp;|&nbsp; <strong>${semanaLabel}</strong>
        </div>
        <div class="cal-nav">
          <button type="button" class="cal-prev">←</button>
          <button type="button" class="cal-next">→</button>
        </div>
      </div>
    `;

    const thDias = days.map((d, idx) => {
      const nombres = ['LUNES','MARTES','MIÉRCOLES','JUEVES','VIERNES'];
      return `<th>${nombres[idx]}<div>${formatDM(d)}</div></th>`;
    }).join('');

    const filas = estudiantesCache.map(e => {
      const celdas = days.map((d, idx) => {
        const esHoy = (esSemanaActual && idx === indiceHoy);

        if (esHoy) {
          return `<td><select name="asistencias[${e.id}][estado]" class="sel-estado" required>
              <option value="" selected disabled>—</option>
              <option value="presente">Presente</option>
              <option value="ausente">Ausente</option>
              <option value="tarde">Tarde</option>
              <option value="justificado">Justificado</option>
            </select>
            <input type="hidden" name="asistencias[${e.id}][estudiante_id]" value="${e.id}">
          </td>`;
        } else if (d < fechaHoy) {
          const estado = obtenerEstadoAsistencia(e.id, d);
          if (estado) return `<td class="estado-${estado}">${formatearEstado(estado)}</td>`;
          else if (huboClase(d)) return `<td class="estado-falta">—</td>`;
          else return `<td class="no-clase">No hubo clase</td>`;
        } else {
          return `<td class="futuro">—</td>`;
        }
      }).join('');
      return `<tr><td>${e.nombres} ${e.apellidos}</td>${celdas}<td>—</td></tr>`;
    }).join('');

    const tabla = `
      <form id="formAsistencia" method="POST" action="/?page=guardar_asistencia">
        <input type="hidden" name="ficha_id" value="${fichaSeleccionada.id}">
        ${esSemanaActual ? `<input type="hidden" name="fecha" value="${formatYMD(hoy())}">` : ''}
        <table>
          <thead><tr><th>ESTUDIANTES</th>${thDias}<th>PRÓXIMAMENTE</th></tr></thead>
          <tbody>${filas}</tbody>
        </table>
        <div class="cal-actions">
          <button type="submit" id="btnSubir" ${esSemanaActual ? 'disabled' : 'disabled'}>Subir Registro</button>
        </div>
      </form>
    `;

    calWrapper.innerHTML = header + tabla;

    calWrapper.querySelector('.cal-prev').addEventListener('click', () => { semanaOffset -= 1; renderCalendario(); });
    calWrapper.querySelector('.cal-next').addEventListener('click', () => { semanaOffset += 1; renderCalendario(); });

    if (esSemanaActual) {
      const selects = Array.from(calWrapper.querySelectorAll('.sel-estado'));
      const btn     = calWrapper.querySelector('#btnSubir');
      function checkCompleto() { btn.disabled = !selects.every(s => s.value); }
      selects.forEach(s => s.addEventListener('change', checkCompleto));
      checkCompleto();
    }
  }

// ==== Cargar lista de fichas y activar calendario ====
async function cargarFichas() {
  try {
    // ✅ endpoint correcto + cookies
    const response = await fetch('index.php?page=profesorficha', {
      credentials: 'include'
    });
    if (!response.ok) throw new Error('Error en la respuesta');
    const texto = await response.text();

    console.log("📌 Respuesta cruda de obtenerFichasPorProfesor:", texto);

    let fichas;
    try {
      fichas = JSON.parse(texto);
    } catch (e) {
      tarjetasFichas.innerHTML = `<pre style="color:red;">Error: la respuesta no es JSON válido.\n\n${texto}</pre>`;
      return;
    }

    if (!Array.isArray(fichas) || fichas.length === 0) {
      tarjetasFichas.innerHTML = '<p>No hay fichas disponibles.</p>';
      return;
    }

    fichasGlobal = fichas;
    tarjetasFichas.innerHTML = ""; // 🔥 limpiar tarjetas anteriores

    fichas.forEach(ficha => {
      const card = document.createElement('div');
      card.classList.add('card-ficha');
      card.innerHTML = `
        <div class="banner"></div>
        <div class="contenido">
          <h4>${ficha.numero || ficha.numero_ficha}</h4>
          <p>${ficha.nombre || 'Formación Titulada Virtual y a Distancia'}</p>
        </div>
        <div class="menu-container">
          <div class="menu">⋮</div>
          <div class="menu-dropdown">
            <a href="index.php?page=fichas&action=ver&id=${ficha.id}">Ingresar a ficha</a>
          </div>
        </div>
      `;

      // click en la tarjeta (para calendario)
      card.querySelector('.contenido').addEventListener('click', () => {
        fichaSeleccionada = { id: ficha.id, nombre: ficha.numero || ficha.numero_ficha };
        semanaOffset = 0;
        calWrapper.innerHTML = '<div>Cargando aprendices...</div>';
        fetch(`index.php?page=estudiantesporficha&ficha_id=${encodeURIComponent(ficha.id)}`, {
          credentials: 'include'
        })
          .then(r => { if (!r.ok) throw new Error('Error al cargar estudiantes'); return r.json(); })
          .then(estudiantes => {
            console.log("📌 Estudiantes cargados:", estudiantes);
            estudiantesCache = Array.isArray(estudiantes) ? estudiantes.filter(e => e && e.id) : [];
            if (estudiantesCache.length === 0) {
              calWrapper.innerHTML = '<div>No hay aprendices en esta ficha.</div>';
              return;
            }
            renderCalendario();
          })
          .catch(err => {
            console.error('Error estudiantes:', err);
            calWrapper.innerHTML = '<div>Error al cargar estudiantes.</div>';
          });
      });

      // menú desplegable
      const menuBtn = card.querySelector('.menu');
      const menuDropdown = card.querySelector('.menu-dropdown');

      menuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        menuDropdown.classList.toggle('show');
      });

      document.addEventListener('click', () => {
        menuDropdown.classList.remove('show');
      });

      tarjetasFichas.appendChild(card);
    });

  } catch (err) {
    console.error('Error al cargar fichas:', err);
  }
}


  // Ejecuta al inicio
  cargarFichas();

  // 🔥 Disponible globalmente
  window.cargarFichas = cargarFichas;

});
