// ===== CALENDARIO COLABORATIVO COMPLETO =====

let calendario;
let fichasDisponibles = [];
let eventoSeleccionado = null;

// Utilidad: obtener nombre del día en español (necesaria para setInfoVisual)
function obtenerNombreDia(fechaISO) {
    try {
        const d = new Date(`${fechaISO}T00:00:00`);
        const dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        return dias[d.getDay()] || '';
    } catch (e) {
        console.warn('obtenerNombreDia error:', e);
        return '';
    }
}

// Configurar presets de color en el modal (creación/edición)
function configurarColorPresets() {
    const inputColor = document.getElementById('color');
    const presets = document.querySelectorAll('.color-preset');
    if (!presets || !inputColor) return;

    const activar = (el)=>{
        presets.forEach(p=>p.classList.remove('active'));
        if (el) el.classList.add('active');
    };

    presets.forEach(btn => {
        btn.onclick = () => {
            const c = btn.getAttribute('data-color');
            if (c) {
                inputColor.value = c;
                activar(btn);
            }
        };
    });

    // Si cambia el input de color manualmente, desactivar presets y no romper
    inputColor.oninput = () => activar(null);
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('Iniciando calendario...');
    try {
        if (!window.__cal_init_done__) {
            inicializarCalendario();
            window.__cal_init_done__ = true;
        }
    } catch (e) { console.error('Error inicializando calendario:', e); }
    configurarEventosModal();
    configurarBotonesUI();
    cargarFichasDisponibles();
    console.log('Botones configurados');
});

// Inicializar FullCalendar
function inicializarCalendario() {
    const calendarEl = document.getElementById('calendario');
    
    if (!calendarEl) {
        console.error('❌ No se encontró el elemento del calendario');
        return;
    }

    // Helper: toggle loading state on calendar container
    const setLoading = (v) => {
        try {
            const el = document.getElementById('calendario');
            if (!el) return;
            if (v) el.classList.add('loading'); else el.classList.remove('loading');
        } catch(_) {}
    };

    calendario = new FullCalendar.Calendar(calendarEl, {
        // Configuración básica
        initialView: 'timeGridWeek',
        locale: 'es',
        firstDay: 1,
        
        // Toolbar de navegación
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'timeGridWeek,timeGridDay'
        },
        
        // Textos en español
        buttonText: {
            today: 'Hoy',
            week: 'Semana',
            day: 'Día'
        },
        
        // Configuración de horarios (06:00 a 17:00 visibles). slotMaxTime es exclusivo, por eso 18:00
        slotMinTime: '06:00:00',
        slotMaxTime: '18:00:00',
        slotDuration: '01:00:00',
        slotLabelInterval: '01:00:00',
        slotLabelFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        },
        
        // Configuración de eventos
        allDaySlot: false,
        selectable: true,
        selectMirror: true,
        editable: true, // permitir edición global; restringimos por evento
        // No permitir seleccionar rangos en el pasado
        selectAllow: function(selectionInfo) {
            const now = new Date();
            return selectionInfo.start >= now; // solo permitir desde ahora hacia adelante
        },
        eventDurationEditable: true,
        eventStartEditable: true,
        eventResizableFromStart: true,
        snapDuration: '01:00:00',    // ajustar al grid de 1 hora
        dayMaxEvents: true,
        weekends: false,
        displayEventTime: true,
        eventDisplay: 'block',
        eventMinHeight: 25,
        height: 'auto',
        nowIndicator: true,
        
        // Horarios laborales: 06:00-17:00 de lunes a viernes
        businessHours: {
            daysOfWeek: [1, 2, 3, 4, 5],
            startTime: '06:00',
            endTime: '17:00'
        },
        
        // Cargar eventos desde el servidor
        events: function(fetchInfo, successCallback, failureCallback) {
            console.log('Cargando eventos del calendario...');
            setLoading(true);
            
            const url = new URL('/', window.location.origin);
            url.searchParams.append('page', 'calendario_obtener');
            if (window.profesorFiltro) {
                url.searchParams.append('profesor_id', window.profesorFiltro);
            }
            url.searchParams.append('start', fetchInfo.startStr);
            url.searchParams.append('end', fetchInfo.endStr);
            
            fetch(url.toString())
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Eventos cargados:', data);
                    
                    // Procesar eventos para asegurar formato correcto
                    const eventosFormateados = data.map(evento => ({
                        id: evento.id,
                        title: evento.title || evento.titulo,
                        start: evento.start || evento.fecha_inicio,
                        end: evento.end || evento.fecha_fin,
                        backgroundColor: evento.backgroundColor || '#3788d8',
                        borderColor: evento.borderColor || '#3788d8',
                        textColor: '#ffffff',
                        extendedProps: evento.extendedProps || {},
                        classNames: (evento.className ? [evento.className] : [])
                    }));

                    // Marcar eventos pasados como finalizados (visual y UX)
                    const ahora = new Date();
                    const normalizados = eventosFormateados.map(e => {
                        try {
                            const fin = new Date(e.end);
                            if (fin < ahora) {
                                e.extendedProps = Object.assign({}, e.extendedProps, { estado: 'finalizado', esPasado: true });
                                e.classNames = [...(e.classNames || []), 'evento-pasado'];
                                e.editable = false; // no permitir mover/estirar
                                // Mantener el color original; la opacidad se maneja por CSS (.evento-pasado)
                            }
                        } catch(_) {}
                        return e;
                    });

                    // Marcar automáticamente como "en_curso" si corresponde
                    try {
                        window.__marcadosEnCurso = window.__marcadosEnCurso || new Set();
                        normalizados.forEach(async (e) => {
                            try {
                                const inicio = new Date(e.start);
                                const fin = new Date(e.end);
                                const estado = (e.extendedProps?.estado || '').toString();
                                const cancelado = estado === 'cancelado';
                                const yaMarcado = window.__marcadosEnCurso.has(String(e.id));
                                if (!cancelado && estado !== 'en_curso' && ahora >= inicio && ahora < fin && !yaMarcado) {
                                    // Actualizar backend y estado local
                                    window.__marcadosEnCurso.add(String(e.id));
                                    try { await cambiarEstadoEvento(e.id, 'en_curso'); } catch(_) {}
                                    e.extendedProps = Object.assign({}, e.extendedProps, { estado: 'en_curso' });
                                }
                            } catch(_) {}
                        });
                    } catch(_) {}

                    // Marcar automáticamente como "finalizado" si corresponde (y no está cancelado)
                    try {
                        window.__marcadosFinalizados = window.__marcadosFinalizados || new Set();
                        normalizados.forEach(async (e) => {
                            try {
                                const fin = new Date(e.end);
                                const estado = (e.extendedProps?.estado || '').toString();
                                const cancelado = estado === 'cancelado';
                                const yaMarcado = window.__marcadosFinalizados.has(String(e.id));
                                if (!cancelado && estado !== 'finalizado' && ahora >= fin && !yaMarcado) {
                                    window.__marcadosFinalizados.add(String(e.id));
                                    try { await cambiarEstadoEvento(e.id, 'finalizado'); } catch(_) {}
                                    e.extendedProps = Object.assign({}, e.extendedProps, { estado: 'finalizado', esPasado: true });
                                }
                            } catch(_) {}
                        });
                    } catch(_) {}
                    
                    // Aplicar filtro por ficha y estado si están seleccionados
                    const filtroFichaEl = document.getElementById('filtroFicha');
                    const filtroFicha = (filtroFichaEl?.value || '').toString().trim();
                    const filtroFichaTexto = (filtroFichaEl?.selectedOptions?.[0]?.textContent || '').toString().trim().toLowerCase();
                    const filtroEstado = (document.getElementById('filtroEstado')?.value || '').toString().trim();

                    const filtrados = normalizados.filter(e => {
                        const codigo = (e.extendedProps?.ficha_codigo ?? '').toString();
                        const fid = (e.extendedProps?.ficha_id ?? '').toString();
                        const nombre = (e.extendedProps?.ficha_nombre ?? '').toString().trim().toLowerCase();
                        const estado = (e.extendedProps?.estado ?? '').toString();
                        // Si el valor está vacío y el texto dice 'todas', no filtrar
                        const isAllFichas = (!filtroFicha) && (filtroFichaTexto.includes('todas'));
                        const matchByValue = filtroFicha ? (codigo === filtroFicha || fid === filtroFicha) : false;
                        const matchByText = filtroFicha && filtroFichaTexto && nombre ? nombre.includes(filtroFichaTexto) : false;
                        const okFicha = isAllFichas ? true : (filtroFicha ? (matchByValue || matchByText) : true);
                        const okEstado = filtroEstado ? (estado === filtroEstado) : true;
                        return okFicha && okEstado;
                    });

                    // Actualizar contadores
                    try { actualizarContadores(filtrados); } catch (e) { console.warn('No se pudieron actualizar contadores:', e); }

                    successCallback(filtrados);
                    setLoading(false);
                })
                .catch(error => {
                    console.error('Error al cargar eventos:', error);
                    failureCallback(error);
                    setLoading(false);
                });
        },
        
        // Seleccionar rango para crear evento
        select: function(info) {
            console.log('Selección de rango:', info);
            const now = new Date();
            if (info.start < now) {
                mostrarError('No puedes crear horarios en bloques finalizados (pasados).');
                if (calendario) calendario.unselect();
                return;
            }
            abrirModalHorario(null, info);
        },
        
        // Click en evento existente
        eventClick: function(info) {
            console.log('Click en evento:', info.event);
            mostrarDetallesEvento(info.event);
        },
        
        // Arrastrar y soltar evento
        eventDrop: function(info) {
            const estado = info.event.extendedProps?.estado || '';
            const esPasado = !!info.event.extendedProps?.esPasado || estado === 'finalizado';
            if (estado === 'cancelado' || esPasado || estado === 'en_curso') {
                console.warn('Movimiento bloqueado: evento cancelado');
                info.revert();
                return;
            }
            console.log('Evento movido:', info.event);
            actualizarEventoEnServidor(info.event);
        },
        
        // Redimensionar evento
        eventResize: function(info) {
            const estado = info.event.extendedProps?.estado || '';
            const esPasado = !!info.event.extendedProps?.esPasado || estado === 'finalizado';
            if (estado === 'cancelado' || esPasado || estado === 'en_curso') {
                console.warn('Redimensionamiento bloqueado: evento cancelado');
                info.revert();
                return;
            }
            console.log('Evento redimensionado:', info.event);
            actualizarEventoEnServidor(info.event);
        },
        // Tooltip nativo con detalles del evento
        eventDidMount: function(info) {
            try {
                const e = info.event;
                const pad = (n)=>String(n).padStart(2,'0');
                const fmt = (d)=>`${pad(d.getHours())}:${pad(d.getMinutes())}`;
                const inicio = e.start ? fmt(e.start) : '';
                const fin = e.end ? fmt(e.end) : '';
                const fichaCod = e.extendedProps?.ficha_codigo || '';
                const fichaNom = e.extendedProps?.ficha_nombre || '';
                const aula = e.extendedProps?.aula || '';
                const estado = e.extendedProps?.estado || '';
                const titulo = e.title || '';
                const texto = `${titulo}\n${fichaCod} ${fichaNom}\n${inicio} - ${fin}${aula?`\nAula: ${aula}`:''}${estado?`\nEstado: ${estado}`:''}`.trim();
                info.el.setAttribute('title', texto);
            } catch(_) {}
        }
    });
    
    // Renderizar el calendario
    calendario.render();
    // Exponer instancia actualizada para otros listeners
    try { window.calendario = calendario; } catch (_) {}
    console.log('✅ Calendario inicializado correctamente');
}

// Configurar eventos del modal
function configurarEventosModal() {
    // Botón cerrar modal
    const btnCerrar = document.querySelector('#modalHorario .btn-secondary');
    if (btnCerrar) {
        btnCerrar.addEventListener('click', cerrarModal);
    }
    
    // Botón guardar
    const btnGuardar = document.getElementById('btnGuardarHorario');
    if (btnGuardar) {
        btnGuardar.addEventListener('click', guardarHorario);
    }

    // Presets de color: seleccionar y previsualizar
    configurarColorPresets();
    
    // Cambio en select de ficha (compatibilidad con #fichaId y #fichaSelect)
    const fichaSelect = document.getElementById('fichaSelect');
    if (fichaSelect) {
        fichaSelect.addEventListener('change', generarTituloAutomatico);
    }
    const fichaIdSel = document.getElementById('fichaId');
    if (fichaIdSel) {
        fichaIdSel.addEventListener('change', generarTituloAutomatico);
    }
    
    // Cerrar modal al hacer click fuera
    const modal = document.getElementById('modalHorario');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                cerrarModal();
            }
        });
    }
}

// Abrir modal para crear/editar horario
function abrirModalHorario(evento = null, seleccion = null) {
    const modal = document.getElementById('modalHorario');
    if (!modal) {
        console.error('❌ No se encontró el modal');
        return;
    }
    console.log('Abriendo modal horario...', { evento, seleccion });
    
    // Limpiar formulario
    limpiarFormularioModal();
    
    // Cargar fichas disponibles
    cargarFichasDisponibles().then(() => {
        // Generar título por defecto con ficha si está seleccionada
        generarTituloAutomatico();
    });
    
    // Helper para setear info visible
    const setInfoVisual = (fechaISO, hIni, hFin) => {
        const spanFH = document.getElementById('infoFechaHora');
        const spanDia = document.getElementById('infoDia');
        if (spanFH) spanFH.textContent = `${fechaISO} ${hIni} - ${hFin}`;
        if (spanDia) spanDia.textContent = obtenerNombreDia(fechaISO);
    };

    const editorManual = document.getElementById('editorManualTiempo');

    // Si es edición de evento existente
    if (evento) {
        if (editorManual) editorManual.style.display = 'block';
        document.getElementById('titulo').value = evento.title || '';
        // Guardar ID de evento para edición
        const hid = document.getElementById('horarioId');
        if (hid) hid.value = evento.id || '';
        const fechaISO = evento.start.toISOString().split('T')[0];
        const hIni = evento.start.toTimeString().substr(0, 5);
        const hFin = evento.end.toTimeString().substr(0, 5);
        document.getElementById('fechaInicio').value = fechaISO;
        document.getElementById('horaInicio').value = hIni;
        document.getElementById('horaFin').value = hFin;
        setInfoVisual(fechaISO, hIni, hFin);
        // Sincronizar con inputs visibles (fecha bloqueada)
        const inFecha = document.getElementById('inputFecha');
        const inHi = document.getElementById('inputHoraInicio');
        const inHf = document.getElementById('inputHoraFin');
        if (inFecha) { inFecha.value = fechaISO; inFecha.disabled = true; }
        if (inHi) inHi.value = hIni;
        if (inHf) inHf.value = hFin;
        const syncSel = () => {
            const nhi = inHi?.value || hIni;
            const nhf = inHf?.value || hFin;
            document.getElementById('horaInicio').value = nhi;
            document.getElementById('horaFin').value = nhf;
            setInfoVisual(fechaISO, nhi, nhf);
        };
        if (inHi) inHi.onchange = syncSel;
        if (inHf) inHf.onchange = syncSel;
        eventoSeleccionado = evento;
    }
    // Si es nueva selección
    else if (seleccion) {
        if (editorManual) editorManual.style.display = 'block';
        const hid = document.getElementById('horarioId');
        if (hid) hid.value = '';
        const fechaISO = seleccion.start.toISOString().split('T')[0];
        const hIni = seleccion.start.toTimeString().substr(0, 5);
        const hFin = seleccion.end.toTimeString().substr(0, 5);
        document.getElementById('fechaInicio').value = fechaISO;
        document.getElementById('horaInicio').value = hIni;
        document.getElementById('horaFin').value = hFin;
        // Título por defecto
        generarTituloAutomatico();
        setInfoVisual(fechaISO, hIni, hFin);
        // Sincronizar con inputs visibles (fecha bloqueada)
        const inFecha = document.getElementById('inputFecha');
        const inHi = document.getElementById('inputHoraInicio');
        const inHf = document.getElementById('inputHoraFin');
        if (inFecha) { inFecha.value = fechaISO; inFecha.disabled = true; }
        if (inHi) inHi.value = hIni;
        if (inHf) inHf.value = hFin;
        const syncSel2 = () => {
            const nhi = inHi?.value || hIni;
            const nhf = inHf?.value || hFin;
            document.getElementById('horaInicio').value = nhi;
            document.getElementById('horaFin').value = nhf;
            setInfoVisual(fechaISO, nhi, nhf);
        };
        if (inHi) inHi.onchange = syncSel2;
        if (inHf) inHf.onchange = syncSel2;
        eventoSeleccionado = null;
    }
    // Si se abre desde botón (sin selección ni evento), mostrar editor manual
    else {
        if (editorManual) {
            const hid = document.getElementById('horarioId');
            if (hid) hid.value = '';
            editorManual.style.display = 'block';
            // Inicializar inputs manuales con los valores cargados en ocultos
            const f = document.getElementById('fechaInicio').value;
            const hi = document.getElementById('horaInicio').value;
            const hf = document.getElementById('horaFin').value;
            const inFecha = document.getElementById('inputFecha');
            const inHi = document.getElementById('inputHoraInicio');
            const inHf = document.getElementById('inputHoraFin');
            if (inFecha) { inFecha.value = f; inFecha.disabled = false; }
            if (inHi) inHi.value = hi;
            if (inHf) inHf.value = hf;
            // Listeners para sincronizar
            const sync = () => {
                const nf = inFecha?.value || f;
                const nhi = inHi?.value || hi;
                const nhf = inHf?.value || hf;
                if (nf) document.getElementById('fechaInicio').value = nf;
                if (nhi) document.getElementById('horaInicio').value = nhi;
                if (nhf) document.getElementById('horaFin').value = nhf;
                setInfoVisual(nf, nhi, nhf);
            };
            if (inFecha) inFecha.oninput = sync;
            if (inHi) inHi.oninput = sync;
            if (inHf) inHf.oninput = sync;
            sync();
        }
    }
    
    // Mostrar modal (preferir Bootstrap 5)
    try {
        const bsModal = bootstrap.Modal.getOrCreateInstance(modal);
        bsModal.show();
    } catch (_) {
        // Fallback manual
        modal.style.display = 'block';
        modal.classList.add('show');
        modal.removeAttribute('aria-hidden');
        modal.setAttribute('aria-modal', 'true');
        modal.style.zIndex = 1055;
        // Crear backdrop simple si no existe
        if (!document.querySelector('.modal-backdrop')) {
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
        }
    }

    // Focus en select de ficha y regenerar título
    setTimeout(() => {
        document.getElementById('fichaId')?.focus();
        try { generarTituloAutomatico(); } catch {}
    }, 100);
}

// Cerrar modal
function cerrarModal() {
    const modal = document.getElementById('modalHorario');
    if (modal) {
        try {
            const bsModal = bootstrap.Modal.getOrCreateInstance(modal);
            bsModal.hide();
        } catch (_) {
            modal.style.display = 'none';
            modal.classList.remove('show');
        }
        // Eliminar backdrop manual si existe (fallback)
        const bd = document.querySelector('.modal-backdrop');
        if (bd) bd.remove();
        modal.setAttribute('aria-hidden', 'true');
        modal.removeAttribute('aria-modal');
        modal.style.zIndex = '';
        // Reset botón Guardar por si quedó deshabilitado
        const btnGuardar = document.querySelector('#modalHorario .btn-primary');
        if (btnGuardar) {
            btnGuardar.disabled = false;
            btnGuardar.textContent = 'Guardar';
        }
        // Ocultar editor manual para el próximo uso
        const editorManual = document.getElementById('editorManualTiempo');
        if (editorManual) editorManual.style.display = 'none';
    }
    eventoSeleccionado = null;
}

// Limpiar formulario del modal
function limpiarFormularioModal() {
    document.getElementById('titulo').value = '';
    document.getElementById('fechaInicio').value = '';
    document.getElementById('horaInicio').value = '';
    document.getElementById('horaFin').value = '';
    
    const fichaSelect = document.getElementById('fichaSelect');
    if (fichaSelect) {
        fichaSelect.innerHTML = '<option value="">Cargando fichas...</option>';
    }
}

// Cargar fichas disponibles
function cargarFichasDisponibles() {
    const url = new URL('/', window.location.origin);
    url.searchParams.append('page', 'calendario_obtener_fichas');
    if (window.profesorFiltro) {
        url.searchParams.append('profesor_id', window.profesorFiltro);
    }
    
    console.log('Cargando fichas disponibles...');
    
    return fetch(url.toString())
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(fichas => {
            console.log('Fichas cargadas:', fichas);
            
            const select = document.getElementById('fichaSelect');
            if (select) {
                select.innerHTML = '<option value="">Selecciona una ficha</option>';
                
                fichas.forEach(ficha => {
                    const option = document.createElement('option');
                    option.value = ficha.id;
                    option.textContent = `${ficha.codigo || ficha.id} - ${ficha.nombre}`;
                    select.appendChild(option);
                });
                
                fichasDisponibles = fichas;
            }

            // Poblar select del modal principal (formulario) si existe
            const selectFichaForm = document.getElementById('fichaId');
            if (selectFichaForm) {
                selectFichaForm.innerHTML = '<option value="">Seleccionar ficha...</option>';
                fichas.forEach(ficha => {
                    const option = document.createElement('option');
                    option.value = ficha.id;
                    option.textContent = `${ficha.codigo || ficha.id} - ${ficha.nombre}`;
                    selectFichaForm.appendChild(option);
                });
            }

            // Poblar select del modal Compartir si existe
            const selectFichaCompartir = document.getElementById('fichaCompartir');
            if (selectFichaCompartir) {
                selectFichaCompartir.innerHTML = '<option value="">Seleccionar ficha...</option>';
                fichas.forEach(ficha => {
                    const option = document.createElement('option');
                    option.value = ficha.id;
                    option.textContent = `${ficha.codigo || ficha.id} - ${ficha.nombre}`;
                    selectFichaCompartir.appendChild(option);
                });
            }

            // Poblar filtro
            const selectFiltro = document.getElementById('filtroFicha');
            if (selectFiltro) {
                const valorActual = selectFiltro.value;
                selectFiltro.innerHTML = '<option value="">Todas las fichas</option>';
                fichas.forEach(ficha => {
                    const option = document.createElement('option');
                    option.value = ficha.codigo || ficha.id;
                    option.textContent = `${ficha.codigo || ficha.id} - ${ficha.nombre}`;
                    selectFiltro.appendChild(option);
                });
                if (valorActual) selectFiltro.value = valorActual;
            }

            return fichas;
        })
        .catch(error => {
            console.error('Error al cargar fichas:', error);
            
            const select = document.getElementById('fichaSelect');
            if (select) {
                select.innerHTML = '<option value="">Error al cargar fichas</option>';
            }
            return [];
        });
}

// Generar título automáticamente
function generarTituloAutomatico() {
    const tituloInput = document.getElementById('titulo');
    if (!tituloInput) return;

    const nombreProfesor = (window.nombreProfesor || '').trim();
    const fichaIdSel = document.getElementById('fichaId');
    const fichaSelect = document.getElementById('fichaSelect');
    const fichaId = (fichaIdSel?.value || fichaSelect?.value || '').toString();

    if (fichaId) {
        const ficha = fichasDisponibles.find(f => String(f.id) === fichaId);
        if (ficha) {
            const cod = ficha.codigo || ficha.id;
            const nom = ficha.nombre || '';
            const prof = nombreProfesor || 'Profesor';
            tituloInput.value = `Clase con ${prof} - ${cod} ${nom}`.trim();
            return;
        }
    }
    // Fallback sin ficha
    tituloInput.value = `Clase con ${nombreProfesor || 'Profesor'}`;
}

// Guardar horario
function guardarHorario() {
    // Obtener datos del formulario
    const fichaId = document.getElementById('fichaId')?.value || document.getElementById('fichaSelect')?.value;
    let titulo = (document.getElementById('titulo').value || '').trim();
    const fecha = document.getElementById('fechaInicio').value;
    const horaInicio = document.getElementById('horaInicio').value;
    const horaFin = document.getElementById('horaFin').value;
    
    // Validaciones
    if (!fichaId) {
        mostrarError('Por favor selecciona una ficha');
        return;
    }
    
    // Forzar título compuesto con profesor y ficha seleccionada
    const fichaSel = fichasDisponibles.find(f => String(f.id) === String(fichaId));
    if (fichaSel) {
        const prof = (window.nombreProfesor || 'Profesor').trim();
        const cod = fichaSel.codigo || fichaSel.id;
        const nom = fichaSel.nombre || '';
        titulo = `Clase con ${prof} - ${cod} ${nom}`.trim();
        const tituloInput = document.getElementById('titulo');
        if (tituloInput) tituloInput.value = titulo;
    } else if (!titulo) {
        // Último fallback
        generarTituloAutomatico();
        titulo = (document.getElementById('titulo').value || '').trim();
    }
    
    if (!fecha || !horaInicio || !horaFin) {
        mostrarError('Por favor completa todos los campos de fecha y hora');
        return;
    }
    
    // Validar que la hora de fin sea posterior a la de inicio
    if (horaFin <= horaInicio) {
        mostrarError('La hora de fin debe ser posterior a la hora de inicio');
        return;
    }
    
    // Preparar FormData como espera el backend
    const formData = new FormData();
    formData.append('ficha_id', parseInt(fichaId));
    formData.append('titulo', titulo || (window.nombreProfesor ? `Clase con ${window.nombreProfesor}` : 'Clase'));
    formData.append('fecha_inicio', `${fecha} ${horaInicio}:00`);
    formData.append('fecha_fin', `${fecha} ${horaFin}:00`);
    formData.append('aula', document.getElementById('aula')?.value || 'Aula 101');
    formData.append('color', document.getElementById('color')?.value || '#007bff');
    formData.append('estado', 'programado');
    
    try {
        console.log('Guardando horario:', Object.fromEntries(formData.entries()));
    } catch (e) { /* noop */ }
    
    // Deshabilitar botón de guardar
    const btnGuardar = document.querySelector('#modalHorario .btn-primary');
    if (btnGuardar) {
        btnGuardar.disabled = true;
        btnGuardar.textContent = 'Guardando...';
    }
    
    // Decidir crear o actualizar
    const idEditar = (document.getElementById('horarioId')?.value || '').trim();
    const fecha_inicio_full = `${fecha} ${horaInicio}:00`;
    const fecha_fin_full = `${fecha} ${horaFin}:00`;
    if (idEditar) {
        // Actualizar existente
        const payload = {
            id: idEditar,
            titulo,
            fecha_inicio: fecha_inicio_full,
            fecha_fin: fecha_fin_full,
            dia_semana: (new Date(`${fecha}T00:00:00`)).getDay() || 7,
            hora_inicio: horaInicio,
            hora_fin: horaFin,
            aula: document.getElementById('aula')?.value || 'Aula 101',
            color: document.getElementById('color')?.value || '#007bff'
        };
        const url = new URL('/', window.location.origin);
        url.searchParams.append('page', 'calendario_actualizar');
        fetch(url.toString(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(resp => {
            if (!resp.success) throw new Error(resp.error || 'Error al actualizar');
            mostrarExito('Evento actualizado');
            cerrarModal();
            if (calendario) calendario.refetchEvents();
        })
        .catch(err => {
            console.error(err);
            mostrarError(err.message || 'Error al actualizar evento');
        })
        .finally(() => {
            if (btnGuardar) { btnGuardar.disabled = false; btnGuardar.textContent = 'Guardar'; }
        });
    } else {
        // Crear nuevo
        const url = new URL('/', window.location.origin);
        url.searchParams.append('page', 'calendario_crear');
        fetch(url.toString(), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(async response => {
            const raw = await response.text();
            let json = null;
            try { json = JSON.parse(raw); } catch {}
            if (!response.ok) {
                const msg = json?.error || raw || `HTTP error ${response.status}`;
                throw new Error(msg);
            }
            if (!json) {
                throw new Error('Respuesta no válida del servidor');
            }
            return json;
        })
        .then(data => {
            console.log('Respuesta del servidor:', data);
            
            if (data.success) {
                mostrarExito('Evento creado exitosamente');
                cerrarModal();
                
                // Recargar eventos del calendario
                if (calendario) {
                    calendario.refetchEvents();
                }
            } else {
                mostrarError(data.error || 'Error al crear el evento');
            }
        })
        .catch(error => {
            console.error('Error al guardar horario:', error);
            mostrarError(`Error de conexión o servidor: ${error.message}`);
        })
        .finally(() => {
            // Rehabilitar botón
            if (btnGuardar) {
                btnGuardar.disabled = false;
                btnGuardar.textContent = 'Guardar';
            }
        });
    }
}

// Actualizar evento en servidor (para drag & drop)
function actualizarEventoEnServidor(evento) {
    // Construir payload completo como espera el backend
    const start = evento.start;
    const end = evento.end || new Date(evento.start.getTime() + 60*60*1000); // fallback 1h
    const pad = n => String(n).padStart(2, '0');
    const fISO = (d) => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`;
    const hhmm = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;

    const fecha_inicio = fISO(start);
    const fecha_fin = fISO(end);
    const hora_inicio = hhmm(start);
    const hora_fin = hhmm(end);
    const dia_semana = (() => { const dow = start.getDay(); return dow === 0 ? 7 : dow; })();

    const datos = {
        id: evento.id,
        titulo: evento.title || '',
        fecha_inicio,
        fecha_fin,
        dia_semana,
        hora_inicio,
        hora_fin,
        aula: evento.extendedProps?.aula || null,
        color: evento.backgroundColor || evento.borderColor || '#007bff'
    };
    
    const url = new URL('/', window.location.origin);
    url.searchParams.append('page', 'calendario_actualizar');
    
    fetch(url.toString(), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(datos)
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Error al actualizar evento:', data.error);
            // Revertir cambio
            calendario.refetchEvents();
        }
    })
    .catch(error => {
        console.error('Error al actualizar evento:', error);
        calendario.refetchEvents();
    });
}

// Mostrar detalles de evento
function mostrarDetallesEvento(evento) {
    const modal = document.getElementById('modalDetalles');
    const body = document.getElementById('detallesContent');
    if (!modal || !body) { alert(`${evento.title}\n${evento.start} - ${evento.end}`); return; }

    const pad = (n) => String(n).padStart(2, '0');
    const fmtFecha = (d) => `${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()}`;
    const fmtHora = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;

    const fecha = fmtFecha(evento.start);
    const hora = `${fmtHora(evento.start)} – ${fmtHora(evento.end)}`;
    const ficha = evento.extendedProps?.ficha_codigo ? `${evento.extendedProps.ficha_codigo} ${evento.extendedProps.ficha_nombre||''}` : '—';
    const aula = evento.extendedProps?.aula || '—';
    const estado = evento.extendedProps?.estado || 'programado';
    const esPasado = !!evento.extendedProps?.esPasado;
    const profesor = evento.extendedProps?.profesor_nombre || '';
    const color = evento.backgroundColor || '#007bff';

    const ahora = new Date();
    const esCancelado = estado === 'cancelado';
    const pasoLaHora = evento.end ? (new Date(evento.end) < ahora) : false;
    const estadoTexto = (esCancelado && pasoLaHora) ? 'finalizado - cancelada' : estado;

    body.innerHTML = `
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-12 d-flex align-items-center justify-content-between">
            <h5 class="mb-0">${evento.title || 'Evento'}</h5>
            <span class="badge" style="background:${color};">&nbsp;</span>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="small text-muted">Fecha</div>
            <div class="fw-semibold">${fecha}</div>
          </div>
          <div class="col-md-6">
            <div class="small text-muted">Hora</div>
            <div class="fw-semibold">${hora}</div>
          </div>
          <div class="col-md-6">
            <div class="small text-muted">Ficha</div>
            <div class="fw-semibold">${ficha}</div>
          </div>
          <div class="col-md-6">
            <div class="small text-muted">Aula</div>
            <div class="fw-semibold">${aula}</div>
          </div>
          <div class="col-md-6">
            <div class="small text-muted">Estado</div>
            <span class="badge bg-${estadoTexto.includes('finalizado') ? 'secondary' : (estado === 'en_curso' ? 'success' : estado === 'cancelado' ? 'danger' : 'primary')} text-uppercase">${estadoTexto}</span>
          </div>
          ${profesor ? `<div class="col-md-6"><div class="small text-muted">Instructor</div><div class="fw-semibold">${profesor}</div></div>` : ''}
        </div>
      </div>
    `;

    // Wire actions
    const btnEditar = document.getElementById('btnEditarEvento');
    const btnIniciar = document.getElementById('btnIniciarClase');
    const btnFinalizar = document.getElementById('btnFinalizarClase');
    const btnCancelar = document.getElementById('btnEliminarEvento');

    // Resetear estado y handlers para evitar "arrastre" entre eventos
    [btnEditar, btnIniciar, btnFinalizar, btnCancelar].forEach(b => {
      if (!b) return;
      b.style.display = 'none';
      try { b.replaceWith(b.cloneNode(true)); } catch (_) {}
    });
    // Re-obtener referencias tras cloneNode (limpia handlers previos)
    const btnEditarRef = document.getElementById('btnEditarEvento');
    const btnIniciarRef = document.getElementById('btnIniciarClase');
    const btnFinalizarRef = document.getElementById('btnFinalizarClase');
    const btnCancelarRef = document.getElementById('btnEliminarEvento');

    // Si es pasado/finalizado: solo ver detalle, sin acciones
    const soloDetalle = esPasado || estado === 'finalizado' || (esCancelado && pasoLaHora);
    if (soloDetalle) {
      // ya reseteado arriba
    } else {
      // Estado actual permite algunas acciones
      if (btnEditarRef) {
        btnEditarRef.style.display = 'inline-block';
        btnEditarRef.onclick = () => {
          try { bootstrap.Modal.getOrCreateInstance(modal).hide(); } catch {}
          abrirModalHorario(evento);
        };
      }
      // Si está cancelado pero aún no pasó la hora, mostrar 'Habilitar'
      if (esCancelado && !pasoLaHora) {
        if (btnIniciarRef) {
          btnIniciarRef.style.display = 'inline-block';
          btnIniciarRef.innerText = 'Habilitar';
          btnIniciarRef.onclick = async () => {
            try {
              await cambiarEstadoEvento(evento.id, 'programado');
              try { bootstrap.Modal.getOrCreateInstance(modal).hide(); } catch {}
              if (window.calendario) window.calendario.refetchEvents();
            } catch (e) {
              mostrarError(e.message || 'No se pudo habilitar la clase');
            }
          };
        }
        // Ocultar botón Cancelar (ya está cancelada)
        if (btnCancelarRef) btnCancelarRef.style.display = 'none';
        // Ocultar Finalizar
        if (btnFinalizarRef) btnFinalizarRef.style.display = 'none';
      } else {
        if (btnIniciarRef) {
          if (estado === 'programado') {
            btnIniciarRef.style.display = 'inline-block';
            // Asegurar rotular correctamente cuando NO está cancelado
            btnIniciarRef.innerText = 'Iniciar Clase';
            // Limpiar posibles handlers previos
            btnIniciarRef.onclick = null;
          } else {
            btnIniciarRef.style.display = 'none';
          }
        }
      }
      if (btnFinalizarRef) btnFinalizarRef.style.display = estado === 'en_curso' ? 'inline-block' : 'none';
      if (!esCancelado && estado !== 'en_curso' && btnCancelarRef) {
        btnCancelarRef.style.display = 'inline-block';
        btnCancelarRef.onclick = async () => {
          const ok = confirm('¿Cancelar esta clase? Esta acción marcará el bloque como cancelado.');
          if (!ok) return;
          try {
            await cambiarEstadoEvento(evento.id, 'cancelado');
            try { bootstrap.Modal.getOrCreateInstance(modal).hide(); } catch {}
            if (window.calendario) window.calendario.refetchEvents();
          } catch (e) {
            mostrarError(e.message || 'No se pudo cancelar el evento');
          }
        };
      }
    }

    try { bootstrap.Modal.getOrCreateInstance(modal).show(); }
    catch (_) { modal.style.display = 'block'; modal.classList.add('show'); }
}

// Mostrar mensaje de error
function mostrarError(mensaje) {
    alert('❌ Error: ' + mensaje);
}

// Mostrar mensaje de éxito
function mostrarExito(mensaje) {
    alert('✅ ' + mensaje);
}

// Cambiar estado de un evento en el servidor
async function cambiarEstadoEvento(horarioId, nuevoEstado) {
    const url = new URL('/', window.location.origin);
    // Endpoint correcto según router PHP
    url.searchParams.append('page', 'calendario_estado');
    const resp = await fetch(url.toString(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ horario_id: horarioId, estado: nuevoEstado }),
        credentials: 'same-origin'
    });
    const raw = await resp.text();
    let json = null; try { json = JSON.parse(raw); } catch {}
    // Si el servidor devolvió HTML (por ejemplo, redirección a login), mostrar mensaje claro
    const looksLikeHTML = raw && raw.trim().startsWith('<');
    if (!resp.ok || !json?.success || looksLikeHTML) {
        throw new Error(json?.error || raw || 'Error al cambiar estado');
    }
    return json;
}

// Función para recargar eventos (útil para debugging)
function recargarEventos() {
    if (calendario) {
        calendario.refetchEvents();
        console.log('Eventos recargados');
    }
}

// Exponer funciones globalmente para debugging
window.calendario = calendario;
window.recargarEventos = recargarEventos;
window.abrirModalHorario = abrirModalHorario;
window.cerrarModal = cerrarModal;
window.guardarHorario = guardarHorario;

// ===== Helpers UI globales =====
function configurarBotonesUI() {
    // Botón Nuevo Bloque
    const btnNuevo = document.getElementById('btnCrearHorario');
    if (btnNuevo) {
        btnNuevo.addEventListener('click', () => {
            const now = new Date();
            const fechaStr = now.toISOString().split('T')[0];
            // Redondear a la hora actual (sin minutos) y asegurar jornada 06-17
            let h = now.getHours();
            if (h < 6) h = 6;
            if (h >= 17) h = 16; // último bloque 16-17
            const hh = String(h).padStart(2, '0');
            const hhNext = String(Math.min(h + 1, 17)).padStart(2, '0');
            // Pre-setear campos ocultos para que el editor manual los muestre por defecto
            const fH = document.getElementById('fechaInicio');
            const hI = document.getElementById('horaInicio');
            const hF = document.getElementById('horaFin');
            if (fH) fH.value = fechaStr;
            if (hI) hI.value = `${hh}:00`;
            if (hF) hF.value = `${hhNext}:00`;
            // Abrir como "desde botón" (sin selección) para habilitar fecha editable
            abrirModalHorario(null, null);
        });
    }

    // Compartir
    const btnShare = document.getElementById('btnSincronizar');
    if (btnShare) {
        btnShare.addEventListener('click', () => abrirModalCompartir());
        // Filtro por ficha
        const filtroFicha = document.getElementById('filtroFicha');
        if (filtroFicha) {
            filtroFicha.addEventListener('change', () => {
                if (window.calendario) window.calendario.refetchEvents();
            });
        }
        // Filtro por estado
        const filtroEstado = document.getElementById('filtroEstado');
        if (filtroEstado) {
            filtroEstado.addEventListener('change', () => {
                if (window.calendario) window.calendario.refetchEvents();
            });
        }
    }

    // Filtro por ficha
    const filtroFicha = document.getElementById('filtroFicha');
    if (filtroFicha) {
        filtroFicha.addEventListener('change', () => {
            if (window.calendario) window.calendario.refetchEvents();
        });
    }
}

function abrirModalCompartir() {
    const modal = document.getElementById('modalSincronizar');
    if (!modal) return;

    // Cargar profesores
    const urlProfes = new URL('/', window.location.origin);
    urlProfes.searchParams.append('page', 'obtener_profesores');
    fetch(urlProfes.toString())
        .then(r => r.json())
        .then(profesores => {
            const sel = document.getElementById('profesorSincronizar');
            if (sel) {
                sel.innerHTML = '<option value="">Seleccionar Instructor/Facilitador...</option>';
                profesores.forEach(p => {
                    const id = p.id || p.profesor_id || p["p.id"];
                    if (!id) return;
                    const opt = document.createElement('option');
                    opt.value = id;
                    opt.textContent = `${p.nombres} ${p.apellidos}`;
                    sel.appendChild(opt);
                });
            }
        })
        .catch(e => console.error('Error cargando profesores:', e));

    // Cargar fichas en el select del modal compartir
    cargarFichasDisponibles();

    // Enviar solicitud
    const btnEnviar = document.getElementById('btnEnviarSolicitud');
    if (btnEnviar) {
        btnEnviar.onclick = () => {
            const profesorId = parseInt(document.getElementById('profesorSincronizar').value || '');
            const fichaId = parseInt(document.getElementById('fichaCompartir').value || '');
            if (!profesorId || !fichaId) {
                mostrarError('Selecciona un facilitador y una ficha');
                return;
            }
            const payload = { ficha_id: fichaId, profesores: [profesorId] };
            fetch('/?page=compartir_ficha', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    mostrarExito('Solicitud enviada');
                    try {
                        bootstrap.Modal.getOrCreateInstance(modal).hide();
                    } catch (_) {
                        modal.style.display = 'none';
                        modal.classList.remove('show');
                    }
                } else {
                    mostrarError(resp.message || 'No fue posible enviar la solicitud');
                }
            })
            .catch(err => { console.error(err); mostrarError('Error enviando solicitud'); });
        };
    }

    try { bootstrap.Modal.getOrCreateInstance(modal).show(); }
    catch (_) { modal.style.display = 'block'; modal.classList.add('show'); }
}

function actualizarContadores(eventos) {
const totalEl = document.getElementById('totalHorarios');
const hoyEl = document.getElementById('horariosHoy');
if (!totalEl || !hoyEl) return;

// Total de la semana (lo que el loader entregó ya corresponde al rango actual)
const total = Array.isArray(eventos) ? eventos.length : 0;

// Hoy: clases que aún faltan (no finalizadas/canceladas y con fin en el futuro)
const ahora = new Date();
const hoyY = ahora.getFullYear();
const hoyM = ahora.getMonth();
const hoyD = ahora.getDate();

const restantesHoy = (eventos || []).filter(e => {
    try {
        const estado = (e.extendedProps?.estado || '').toString();
        if (estado === 'cancelado') return false;
        const start = new Date(e.start);
        // Comparar en zona local
        const isHoy = start.getFullYear() === hoyY && start.getMonth() === hoyM && start.getDate() === hoyD;
        if (!isHoy) return false;
        const finStr = e.end || e.extendedProps?.fecha_fin || null;
        const end = finStr ? new Date(finStr) : new Date(start.getTime() + 60*60*1000);
        // Debe terminar en el futuro
        return end > ahora;
    } catch(_) { return false; }
}).length;

    totalEl.textContent = String(total);
    hoyEl.textContent = String(restantesHoy);
}

// ... (rest of the code remains the same)
