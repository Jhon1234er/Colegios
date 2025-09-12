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
    
    // Obtener días de la semana de la ficha seleccionada
    const diasSemana = fichaSeleccionada.dias_semana || ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'];
    const mapaDias = {
      'lunes': 0, 'martes': 1, 'miercoles': 2, 'jueves': 3, 'viernes': 4, 'sabado': 5
    };
    
    // Generar solo los días que tiene la ficha
    const days = diasSemana.map(dia => addDays(monday, mapaDias[dia])).filter(Boolean);
    const ultimoDia = days[days.length - 1];
    await cargarRegistrosAsistencia(fichaSeleccionada.id, monday, ultimoDia);

    const weekOfMonth = Math.ceil((monday.getDate() + 1) / 7);
    const monthName = monday.toLocaleString('default', { month: 'long' });
    const semanaLabel = `Semana ${weekOfMonth} de ${monthName}`;
    const esSemanaActual = (semanaOffset === 0);
    const fechaHoy = hoy();
    // Calcular índice del día actual basado en los días de la ficha
    let indiceHoy = -1;
    if (esSemanaActual) {
      const diaActual = fechaHoy.getDay(); // 0=domingo, 1=lunes, etc.
      const diasNumeros = diasSemana.map(dia => mapaDias[dia] + 1); // convertir a números de día de semana
      indiceHoy = diasNumeros.indexOf(diaActual);
    }

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
      const nombresDias = ['LUNES','MARTES','MIÉRCOLES','JUEVES','VIERNES','SÁBADO'];
      const diaSemana = d.getDay(); // 0=domingo, 1=lunes, 2=martes, etc.
      const nombreDia = diaSemana === 0 ? 'DOMINGO' : nombresDias[diaSemana - 1];
      return `<th>${nombreDia}<div>${formatDM(d)}</div></th>`;
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
    tarjetasFichas.innerHTML = '';

    fichas.forEach(ficha => {
      const card = document.createElement('div');
      card.className = 'card-ficha';
      
      // Badge para fichas compartidas
      const badgeCompartida = ficha.tipo === 'compartida' ? 
        '<div class="ficha-compartida-badge">Compartido</div>' : '';
      
      card.innerHTML = `
        <div class="banner"></div>
        <div class="contenido">
          <h4>${ficha.nombre}</h4>
          <p>Ficha: ${ficha.numero_ficha || ficha.numero}</p>
        </div>
        ${badgeCompartida}
        <div class="menu-container">
          <button class="menu" type="button"></button>
          <div class="menu-dropdown">
            <a href="/?page=fichas&action=ver&id=${ficha.id}" class="ver-ficha" data-ficha-id="${ficha.id}" data-ficha-nombre="${ficha.nombre}">Ver Ficha</a>
            ${ficha.tipo !== 'compartida' ? '<a href="#" class="compartir-ficha" data-ficha-id="' + ficha.id + '" data-ficha-nombre="' + ficha.nombre + '">Compartir</a>' : ''}
          </div>
        </div>
      `;

      // Click en la tarjeta para seleccionar ficha
      card.addEventListener('click', (e) => {
        if (!e.target.closest('.menu-container')) {
          let diasSemana;
          if (ficha.dias_semana) {
            try {
              diasSemana = JSON.parse(ficha.dias_semana);
            } catch (e) {
              diasSemana = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'];
            }
          } else {
            diasSemana = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'];
          }
        
          fichaSeleccionada = { 
            id: ficha.id, 
            nombre: ficha.numero || ficha.numero_ficha,
            dias_semana: diasSemana || ['lunes', 'martes', 'miercoles', 'jueves', 'viernes']
          };
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
        }
      });

      // menú desplegable
      const menuBtn = card.querySelector('.menu');
      const menuDropdown = card.querySelector('.menu-dropdown');

      menuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        menuDropdown.classList.toggle('show');
      });

      // Event listener para ver ficha
      const verBtn = card.querySelector('.ver-ficha');
      verBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        menuDropdown.classList.remove('show');
        // El enlace href ya maneja la redirección
      });

      // Event listener para compartir ficha (solo si existe el botón)
      const compartirBtn = card.querySelector('.compartir-ficha');
      if (compartirBtn) {
        compartirBtn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const fichaId = e.target.getAttribute('data-ficha-id');
          const fichaNombre = e.target.getAttribute('data-ficha-nombre');
          abrirModalCompartir(fichaId, fichaNombre);
          menuDropdown.classList.remove('show');
        });
      }

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

// ===== FUNCIONES PARA COMPARTIR FICHAS =====
let profesoresSeleccionados = [];
let fichaActualCompartir = null;

async function abrirModalCompartir(fichaId, fichaNombre) {
  fichaActualCompartir = fichaId;
  document.getElementById('fichaCompartirNombre').textContent = fichaNombre;
  
  // Cargar profesores
  await cargarProfesores();
  
  // Mostrar modal
  const modal = new bootstrap.Modal(document.getElementById('modalCompartirFicha'));
  modal.show();
}

async function cargarProfesores() {
  try {
    const response = await fetch('index.php?page=obtener_profesores', {
      credentials: 'include'
    });
    
    if (!response.ok) throw new Error('Error al cargar profesores');
    
    const profesores = await response.json();
    
    // Verificar estado de compartir para cada profesor
    const responseEstado = await fetch('index.php?page=verificar_estado_compartir', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ 
        ficha_id: fichaActualCompartir,
        profesores: profesores.map(p => p.id)
      })
    });
    
    const estadosCompartir = await responseEstado.json();
    
    const container = document.getElementById('profesoresContainer');
    
    container.innerHTML = '';
    profesoresSeleccionados = [];
    
    profesores.forEach(profesor => {
      const card = document.createElement('div');
      card.classList.add('profesor-card');
      card.dataset.profesorId = profesor.id;
      
      const tipoContrato = profesor.tip_contrato?.toLowerCase();
      const etiquetaProfesor = tipoContrato === 'instructor' ? 'Instructor' : 'Facilitador';
      
      const estadoProfesor = estadosCompartir[profesor.id];
      let etiquetaEstado = '';
      let claseEstado = '';
      let deshabilitado = false;
      
      if (estadoProfesor === 'aceptada') {
        etiquetaEstado = '<div class="estado-ficha ya-tiene">Ya tiene la ficha</div>';
        claseEstado = 'ya-tiene-ficha';
        deshabilitado = true;
      } else if (estadoProfesor === 'pendiente') {
        etiquetaEstado = '<div class="estado-ficha pendiente">Solicitud pendiente</div>';
        claseEstado = 'solicitud-pendiente';
        deshabilitado = true;
      }
      
      card.innerHTML = `
        <div class="checkmark">✓</div>
        <h6>${profesor.nombres} ${profesor.apellidos}</h6>
        <p>${etiquetaProfesor}</p>
        ${etiquetaEstado}
      `;
      
      if (deshabilitado) {
        card.classList.add('deshabilitado', claseEstado);
      } else {
        card.addEventListener('click', () => toggleProfesorSeleccion(profesor.id, card));
      }
      container.appendChild(card);
    });
    
  } catch (error) {
    console.error('Error cargando profesores:', error);
    document.getElementById('profesoresContainer').innerHTML = 
      '<p class="text-danger">Error al cargar profesores</p>';
  }
}

function toggleProfesorSeleccion(profesorId, cardElement) {
  const index = profesoresSeleccionados.indexOf(profesorId);
  
  if (index > -1) {
    // Deseleccionar
    profesoresSeleccionados.splice(index, 1);
    cardElement.classList.remove('selected');
  } else {
    // Seleccionar
    profesoresSeleccionados.push(profesorId);
    cardElement.classList.add('selected');
  }
}

// Event listener para el botón compartir
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('btnCompartirFicha').addEventListener('click', async () => {
    if (profesoresSeleccionados.length === 0) {
      alert('Selecciona al menos un profesor para compartir la ficha');
      return;
    }
    
    try {
      const response = await fetch('index.php?page=compartir_ficha', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
          ficha_id: fichaActualCompartir,
          profesores: profesoresSeleccionados
        })
      });
      
      if (!response.ok) throw new Error('Error al compartir ficha');
      
      const result = await response.json();
      
      if (result.success) {
        alert('Ficha compartida exitosamente');
        bootstrap.Modal.getInstance(document.getElementById('modalCompartirFicha')).hide();
      } else {
        alert('Error: ' + result.message);
      }
      
    } catch (error) {
      console.error('Error compartiendo ficha:', error);
      alert('Error al compartir la ficha');
    }
  });
});
