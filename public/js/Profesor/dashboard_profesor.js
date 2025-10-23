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
  let registrosAsistenciaIds = {}; // fecha(YYYY-MM-DD) -> { estudiante_id: asistencia_id }

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
        const parsed = JSON.parse(text);
        // Soportar respuesta como array o como objeto {success, data}
        registros = Array.isArray(parsed) ? parsed : (parsed?.data || []);
      } catch (e) {
        registrosAsistencia = {};
        return; // simplemente salimos sin mostrar error
      }

      registrosAsistencia = {};
      registrosAsistenciaIds = {};
      if (Array.isArray(registros)) {
        registros.forEach(registro => {
          const fecha = registro.fecha;
          if (!registrosAsistencia[fecha]) registrosAsistencia[fecha] = {};
          if (!registrosAsistenciaIds[fecha]) registrosAsistenciaIds[fecha] = {};
          // Conservar estado original tal cual viene de BD
          const estado = (registro.estado || '').toString();
          registrosAsistencia[fecha][registro.estudiante_id] = estado;
          if (registro.id) {
            registrosAsistenciaIds[fecha][registro.estudiante_id] = registro.id;
          }
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
  function obtenerAsistenciaId(estudianteId, fecha) {
    const fechaStr = formatYMD(fecha);
    return registrosAsistenciaIds[fechaStr]?.[estudianteId] ?? null;
  }
  function huboClase(fecha) {
    const fechaStr = formatYMD(fecha);
    return registrosAsistencia[fechaStr] && Object.keys(registrosAsistencia[fechaStr]).length > 0;
  }
  function formatearEstado(estado) {
    const map = {
      'presente': 'Presente',
      'no_asistio': 'No asistió',
      'tarde': 'Tarde',
      'justificado': 'Justificado',
      'falla': 'Falla',
      'ausente': 'Ausente'
    };
    return map[estado] || (estado ? estado : '—');
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

    // Consultar si hay clase en curso para habilitar el registro del día actual
    let hayClaseEnCurso = false;
    let proximaClaseHoy = null;
    try {
      const respClase = await fetch(`index.php?page=clase_en_curso&ficha_id=${encodeURIComponent(fichaSeleccionada.id)}`, {
        credentials: 'include'
      });
      if (respClase.ok) {
        const info = await respClase.json();
        hayClaseEnCurso = !!info?.en_curso;
      }
    } catch (e) { /* ignorar, se asume false */ }

    // Si no hay clase en curso, consultamos si existe una próxima clase hoy para mostrar contador
    if (!hayClaseEnCurso) {
      try {
        const rprox = await fetch(`index.php?page=clase_proxima_hoy&ficha_id=${encodeURIComponent(fichaSeleccionada.id)}`, { credentials: 'include' });
        if (rprox.ok) {
          const jprox = await rprox.json();
          if (jprox?.success && jprox?.horario) {
            proximaClaseHoy = jprox.horario; // { id, titulo, fecha_inicio, fecha_fin }
          }
        }
      } catch(_) {}
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
        const hoyStr = formatYMD(fechaHoy);
        const huboRegHoy = !!(registrosAsistencia[hoyStr] && Object.keys(registrosAsistencia[hoyStr]).length > 0);

        if (esHoy) {
          // Si YA hay registros hoy, mostrar en texto y no selects ni mensajes
          if (huboRegHoy) {
            const estado = obtenerEstadoAsistencia(e.id, fechaHoy);
            const aid = obtenerAsistenciaId(e.id, fechaHoy);
            if (estado) return `<td class="estado-${estado}"><span class="estado-text">${formatearEstado(estado)}</span> ${aid ? `<button type="button" class="edit-asistencia" data-asistencia-id="${aid}" data-estado="${estado}" title="Editar asistencia">✎</button>` : ''}</td>`;
            // Si no hay estado individual pero hubo registros en general, dejar guion
            return `<td class="estado-sin-registro">—</td>`;
          }

          if (!hayClaseEnCurso) {
            if (proximaClaseHoy) {
              const inicio = new Date(proximaClaseHoy.fecha_inicio.replace(' ', 'T'));
              const ms = Math.max(0, inicio - new Date());
              const idCt = `ct_${fichaSeleccionada.id}`;
              setTimeout(() => iniciarCountdown(inicio, idCt, proximaClaseHoy.id), 100); // iniciar cuando se pinte
              // Link con creación rápida pre-rellena (inicio próximo y duración 60m)
              const now = new Date();
              const roundUp = (d)=>{ const n=new Date(d); const m=n.getMinutes(); n.setMinutes(m%30===0?m: m + (30 - (m%30)),0,0); return n; };
              const ini = roundUp(now);
              const fin = new Date(ini.getTime()+60*60*1000);
              const pad = (n)=>String(n).padStart(2,'0');
              const date = `${ini.getFullYear()}-${pad(ini.getMonth()+1)}-${pad(ini.getDate())}`;
              const start = `${pad(ini.getHours())}:${pad(ini.getMinutes())}`;
              const end = `${pad(fin.getHours())}:${pad(fin.getMinutes())}`;
              const urlCal = `/?page=calendario&quick=1&ficha_id=${encodeURIComponent(fichaSeleccionada.id)}&date=${date}&start=${start}&end=${end}`;
              return `<td class="clase-proxima">
                <div><strong>Próxima clase hoy</strong></div>
                <div id="${idCt}" class="countdown" style="font-weight:700;color:#0d6efd;">—</div>
                <div class="no-clase-cta" style="margin-top:6px;">
                  <a href="${urlCal}" class="btn-ir-calendario" style="display:inline-block;padding:6px 12px;background:#0d6efd;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">Ir al calendario</a>
                </div>
              </td>`;
            } else {
              const urlCal = `/?page=calendario&ficha_id=${encodeURIComponent(fichaSeleccionada.id)}`;
              return `<td class="no-clase-hoy">
                <div><strong>NO TIENEN CLASE</strong></div>
                <div class="no-clase-msg">Para registrar asistencias y mantener historial, crea las clases de esta ficha en el calendario.</div>
                <div class="no-clase-cta">
                  <a href="${urlCal}" class="btn-ir-calendario" style="display:inline-block;padding:6px 12px;background: #39A900;color:#fff;border-radius:20px;text-decoration:none;font-weight:600;">Ir al calendario</a>
                </div>
              </td>`;
            }
          }
          // Si hay clase en curso, pero ya existe un registro hoy para este estudiante, mostrarlo en texto y no el select
          const estadoHoy = obtenerEstadoAsistencia(e.id, hoy());
          if (estadoHoy) {
            const txt = formatearEstado(estadoHoy);
            const aid = obtenerAsistenciaId(e.id, fechaHoy);
            return `<td class="estado-${estadoHoy}"><span class="estado-text">${txt}</span> ${aid ? `<button type="button" class="edit-asistencia" data-asistencia-id="${aid}" data-estado="${estadoHoy}" title="Editar asistencia">✎</button>` : ''}</td>`;
          }
          return `<td><select name="asistencias[${e.id}][estado]" class="sel-estado" required>
              <option value="" selected disabled>—</option>
              <option value="presente">Presente</option>
              <option value="no_asistio">No asistió</option>
              <option value="tarde">Tarde</option>
            </select>
            <input type="hidden" name="asistencias[${e.id}][estudiante_id]" value="${e.id}">
          </td>`;
        } else if (d < fechaHoy) {
          const estado = obtenerEstadoAsistencia(e.id, d);
          if (estado) {
            const aid = obtenerAsistenciaId(e.id, d);
            return `<td class="estado-${estado}"><span class="estado-text">${formatearEstado(estado)}</span> ${aid ? `<button type="button" class="edit-asistencia" data-asistencia-id="${aid}" data-estado="${estado}" title="Editar asistencia">✎</button>` : ''}</td>`;
          }
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
          <thead><tr class="th-container"><th>ESTUDIANTES</th>${thDias}<th>PRÓXIMAMENTE</th></tr></thead>
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
      function checkCompleto() {
        // Debe haber clase en curso y al menos un select visible
        if (!hayClaseEnCurso) { btn.disabled = true; return; }
        if (selects.length === 0) { btn.disabled = true; return; }
        // Habilitar solo si todos los selects tienen valor
        btn.disabled = !selects.every(s => !!s.value);
      }
      selects.forEach(s => s.addEventListener('change', checkCompleto));
      checkCompleto();

      // Envío via fetch en JSON al endpoint registrar_lote
      const form = calWrapper.querySelector('#formAsistencia');
      form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        if (!hayClaseEnCurso || btn.disabled) return;
        btn.disabled = true;
        btn.textContent = 'Guardando...';
        try {
          const fichaId = fichaSeleccionada.id;
          const fecha = formatYMD(hoy());
          const asistencias = {};
          selects.forEach(sel => {
            const estId = sel.name.match(/asistencias\[(\d+)\]/)?.[1];
            if (estId) {
              asistencias[estId] = { estudiante_id: parseInt(estId), estado: sel.value };
            }
          });
          const resp = await fetch('index.php?page=guardar_asistencia', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ ficha_id: fichaId, fecha, asistencias })
          });
          const raw = await resp.text();
          let data;
          try {
            data = JSON.parse(raw);
          } catch(parseErr) {
            console.error('Respuesta no-JSON del servidor:', raw);
            throw new Error('Respuesta inesperada del servidor. Detalle: ' + (raw?.slice(0, 200) || ''));
          }
          if (!resp.ok || data.success === false) {
            throw new Error(data.message || 'Error al guardar asistencias');
          }
          // Recargar registros y re-renderizar
          await cargarRegistrosAsistencia(fichaSeleccionada.id, monday, ultimoDia);
          renderCalendario();
        } catch (e) {
          console.error('Error guardando asistencias:', e);
          alert('Error al guardar asistencias: ' + (e.message || ''));
        } finally {
          btn.disabled = false;
          btn.textContent = 'Subir Registro';
        }
      });
    }

    // Delegación: editar asistencia inline
    calWrapper.addEventListener('click', async (ev) => {
      const btnEdit = ev.target.closest('.edit-asistencia');
      if (!btnEdit) return;
      const td = btnEdit.closest('td');
      const asistenciaId = btnEdit.dataset.asistenciaId;
      const estadoActual = btnEdit.dataset.estado;
      if (!td || !asistenciaId) return;

      // Construir editor inline
      const opciones = [
        {v:'presente', l:'Presente'},
        {v:'no_asistio', l:'No asistió'},
        {v:'tarde', l:'Tarde'},
        {v:'justificado', l:'Justificado'}
      ];
      const selectHtml = `<select class="edit-select-estado">${opciones.map(o=>`<option value="${o.v}" ${o.v===estadoActual?'selected':''}>${o.l}</option>`).join('')}</select>`;
      const accionesHtml = `<button type="button" class="edit-guardar">Guardar</button> <button type="button" class="edit-cancelar">Cancelar</button>`;
      const original = td.innerHTML;
      td.innerHTML = `<div class="editor-asistencia">${selectHtml} ${accionesHtml}</div>`;

      const cancelar = () => { td.innerHTML = original; };
      td.querySelector('.edit-cancelar').addEventListener('click', cancelar);
      td.querySelector('.edit-guardar').addEventListener('click', async () => {
        const nuevo = td.querySelector('.edit-select-estado').value;
        try {
          const fd = new FormData();
          fd.append('id', asistenciaId);
          fd.append('estado', nuevo);
          const resp = await fetch('index.php?page=asistencia_actualizar', {
            method: 'POST',
            credentials: 'include',
            body: fd
          });
          const txt = await resp.text();
          let data; try { data = JSON.parse(txt); } catch(_) { throw new Error('Respuesta inesperada'); }
          if (!resp.ok || data.success === false) throw new Error(data.message || 'Error al actualizar');
          // Refrescar datos y re-renderizar
          await cargarRegistrosAsistencia(fichaSeleccionada.id, monday, ultimoDia);
          renderCalendario();
        } catch (e) {
          alert('No se pudo actualizar: ' + (e.message || ''));
          cancelar();
        }
      });
    });
  }

  // ==== Countdown para próxima clase de hoy ====
  function iniciarCountdown(fechaInicio, elementId, horarioId) {
    try {
      const el = document.getElementById(elementId);
      if (!el) return;
      // Normalizar a Date si viene como string
      const target = (fechaInicio instanceof Date) ? fechaInicio : new Date(fechaInicio);
      // Guardar timer para poder limpiarlo si se re-renderiza
      window.__ctTimers = window.__ctTimers || {};
      if (window.__ctTimers[elementId]) {
        clearInterval(window.__ctTimers[elementId]);
      }
      const fmt = (n)=> String(n).padStart(2,'0');
      const tick = async () => {
        const now = new Date();
        let diff = target - now;
        if (diff <= 0) {
          el.textContent = '¡Es hora!';
          clearInterval(window.__ctTimers[elementId]);
          // Intentar iniciar clase automáticamente
          if (horarioId) {
            try {
              const resp = await fetch('index.php?page=iniciar_clase', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ horario_id: horarioId })
              });
              // Ignorar errores, igual re-renderizamos
            } catch (_) {}
          }
          // Re-render para que el tablero revalide si ya hay clase en curso
          try { renderCalendario(); } catch(_) {}
          return;
        }
        const sec = Math.floor(diff/1000) % 60;
        const min = Math.floor(diff/(1000*60)) % 60;
        const hr  = Math.floor(diff/(1000*60*60));
        el.textContent = `${fmt(hr)}:${fmt(min)}:${fmt(sec)}`;
      };
      tick();
      window.__ctTimers[elementId] = setInterval(tick, 1000);
    } catch(_) {}
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
    // Función para renderizar una card y agregar listeners
    function renderCard(ficha, disabled = false) {
      const card = document.createElement('div');
      card.className = 'card-ficha';
      if (disabled) card.classList.add('card-ficha-disabled');
      const badgeCompartida = ficha.tipo === 'compartida' ? '<div class="ficha-compartida-badge">Compartido</div>' : '';
      card.innerHTML = `
        <div class="banner"></div>
        <div class="contenido">
          <h4>${ficha.nombre}</h4>
          <p>Ficha: ${ficha.numero_ficha || ficha.numero}</p>
        </div>
        ${badgeCompartida}
        <div class="menu-container">
          <button class="menu" type="button">
            <i class="fas fa-ellipsis-v"></i>
          </button>
          <div class="menu-dropdown">
            <a href="/?page=fichas&action=ver&id=${ficha.id}" class="ver-ficha" data-ficha-id="${ficha.id}" data-ficha-nombre="${ficha.nombre}">Ver Ficha</a>
            ${ficha.tipo !== 'compartida' ? '<a href="#" class="compartir-ficha" data-ficha-id="' + ficha.id + '" data-ficha-nombre="' + ficha.nombre + '">Compartir</a>' : ''}
          </div>
        </div>
      `;
      if (!disabled) {
        // Listener para seleccionar ficha
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
      } else {
        // Si está deshabilitado, evitar cualquier interacción
        card.style.pointerEvents = 'none';
        card.style.opacity = '0.6';
      }
      // Menú desplegable y otros listeners
      const menuBtn = card.querySelector('.menu');
      const menuDropdown = card.querySelector('.menu-dropdown');
      if (menuBtn && menuDropdown) {
        menuBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          menuDropdown.classList.toggle('show');
        });
        const verBtn = card.querySelector('.ver-ficha');
        if (verBtn) {
          verBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            menuDropdown.classList.remove('show');
          });
        }
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
        const irReport = card.querySelector('.ir-reportes');
        if (irReport) {
          irReport.addEventListener('click', (e) => {
            e.stopPropagation();
            menuDropdown.classList.remove('show');
          });
        }
        document.addEventListener('click', () => {
          menuDropdown.classList.remove('show');
        });
      }
      return card;
    }
    // Exponer renderCard y fichasGlobal globalmente
    window.renderCard = renderCard;
    window.fichasGlobal = fichasGlobal;

    // Paginación: solo después de crear todas las cards
    if (window.inicializarPaginacionFichas) {
      tarjetasFichas.innerHTML = '';
      const paginar = window.inicializarPaginacionFichas;
      if (typeof paginar === 'function') {
        paginar('tarjetasFichas', 4, 'paginacionFichas', fichas, renderCard);
      }
    } else {
      fichas.forEach(ficha => tarjetasFichas.appendChild(renderCard(ficha)));
    }

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
