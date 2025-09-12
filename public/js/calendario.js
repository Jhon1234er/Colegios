// ===== CALENDARIO COLABORATIVO JS =====

let calendario;
let fichasDisponibles = [];
let eventoSeleccionado = null;

// Inicializar cuando el DOM esté listo
$(document).ready(function() {
    inicializarCalendario();
    cargarFichasDisponibles();
    cargarCalendariosSincronizados();
    configurarEventos();
    actualizarEstadisticas();
    
    // Actualizar estados cada 10 segundos para pruebas
    setInterval(function() {
        calendario.refetchEvents();
        forzarOrientacionHorizontal();
        console.log('Estados actualizados:', new Date().toLocaleTimeString());
    }, 10000);
});

// Inicializar FullCalendar
function inicializarCalendario() {
    const calendarEl = document.getElementById('calendario');
    
    calendario = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        buttonText: {
            today: 'Hoy',
            month: 'Mes',
            week: 'Semana',
            day: 'Día'
        },
        slotMinTime: '06:00:00',
        slotMaxTime: '17:00:00',
        slotDuration: '01:00:00',
        slotLabelInterval: '01:00:00',
        slotMinHeight: 60,
        slotLabelFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        },
        allDaySlot: false,
        height: 'auto',
        expandRows: true,
        nowIndicator: true,
        selectable: true,
        selectMirror: false,
        editable: true,
        dayMaxEvents: false,
        weekends: false,
        displayEventTime: true,
        eventDisplay: 'block',
        forceEventDuration: true,
        eventMinHeight: 25,
        
        // Configuración de horarios
        businessHours: {
            daysOfWeek: [1, 2, 3, 4, 5, 6], // Lunes a Sábado
            startTime: '06:00',
            endTime: '17:00'
        },
        
        // Validar fechas - no permitir selección en el pasado
        selectConstraint: {
            start: new Date().toISOString().split('T')[0] + 'T00:00:00'
        },
        
        // Deshabilitar arrastre a fechas pasadas
        eventConstraint: {
            start: new Date().toISOString()
        },
        
        // Cargar eventos
        events: function(info, successCallback, failureCallback) {
            console.log('Cargando eventos para vista:', info.view.type);
            console.log('Rango de fechas:', info.startStr, 'a', info.endStr);
            cargarHorarios(info, successCallback, failureCallback);
        },
        
        // Seleccionar rango para crear evento
        select: function(info) {
            // Prevenir creación automática de eventos
            calendario.unselect();
            
            // Verificar si la fecha seleccionada es en el pasado
            const ahora = new Date();
            const fechaSeleccionada = new Date(info.start);
            
            if (fechaSeleccionada < ahora) {
                mostrarError('No se pueden crear eventos en fechas pasadas');
                return;
            }
            
            abrirModalHorario(null, info);
        },
        
        // Click en evento existente
        eventClick: function(info) {
            mostrarDetallesEvento(info.event);
        },
        
        // Arrastrar y soltar evento
        eventDrop: function(info) {
            // Verificar si se está moviendo a una fecha/hora pasada
            const ahora = new Date();
            const nuevaFecha = new Date(info.event.start);
            
            if (nuevaFecha < ahora) {
                // Revertir el movimiento
                info.revert();
                mostrarError('No se pueden mover eventos a fechas u horas que ya pasaron');
                return;
            }
            
            actualizarHorarioArrastrado(info);
        },
        
        // Redimensionar evento
        eventResize: function(info) {
            // Verificar si se está redimensionando hacia una fecha/hora pasada
            const ahora = new Date();
            const nuevaFechaInicio = new Date(info.event.start);
            const nuevaFechaFin = new Date(info.event.end);
            
            if (nuevaFechaInicio < ahora || nuevaFechaFin < ahora) {
                // Revertir el redimensionamiento
                info.revert();
                mostrarError('No se pueden extender eventos hacia fechas u horas que ya pasaron');
                return;
            }
            
            actualizarHorarioRedimensionado(info);
        },
        
        // Personalizar renderizado de eventos
        eventDidMount: function(info) {
            aplicarEstilosEvento(info);
            
            // Deshabilitar arrastre para eventos pasados
            const ahora = new Date();
            const fechaEvento = new Date(info.event.end);
            
            if (fechaEvento < ahora) {
                // Hacer el evento no arrastrable si ya pasó
                info.event.setProp('editable', false);
                info.el.style.cursor = 'default';
                info.el.title = 'Este evento ya pasó y no se puede modificar';
            }
        },
        
        // Personalizar etiquetas de tiempo para mostrar rangos
        slotLabelDidMount: function(info) {
            const hora = info.date.getHours();
            const horaActual = String(hora).padStart(2, '0') + ':00';
            const horaSiguiente = String(hora + 1).padStart(2, '0') + ':00';
            info.el.innerHTML = `${horaActual} a ${horaSiguiente}`;
            
            // Forzar orientación horizontal con JavaScript
            info.el.style.writingMode = 'horizontal-tb';
            info.el.style.textOrientation = 'mixed';
            info.el.style.transform = 'rotate(0deg)';
            info.el.style.webkitTransform = 'rotate(0deg)';
            info.el.style.mozTransform = 'rotate(0deg)';
            info.el.style.msTransform = 'rotate(0deg)';
            info.el.style.oTransform = 'rotate(0deg)';
            info.el.style.width = '90px';
            info.el.style.minWidth = '90px';
            info.el.style.maxWidth = '90px';
            info.el.style.textAlign = 'center';
            info.el.style.fontSize = '0.7rem';
            info.el.style.padding = '0.2rem';
            info.el.style.display = 'flex';
            info.el.style.alignItems = 'center';
            info.el.style.justifyContent = 'center';
            
            // Aplicar también a elementos hijos
            const children = info.el.querySelectorAll('*');
            children.forEach(child => {
                child.style.writingMode = 'horizontal-tb';
                child.style.textOrientation = 'mixed';
                child.style.transform = 'rotate(0deg)';
                child.style.webkitTransform = 'rotate(0deg)';
            });
        },
        
        // Callback después de que se renderice el calendario
        viewDidMount: function(info) {
            console.log('Vista montada:', info.view.type);
            
            // Forzar orientación horizontal en todos los elementos de tiempo
            setTimeout(() => {
                forzarOrientacionHorizontal();
            }, 100);
            
            // Forzar recarga de eventos cuando cambie de vista
            setTimeout(() => {
                calendario.refetchEvents();
            }, 200);
        },
        
        // Callback cuando cambia la vista
        datesSet: function(info) {
            console.log('Fechas establecidas para vista:', info.view.type);
            console.log('Rango:', info.startStr, 'a', info.endStr);
            
            // Asegurar que los eventos se recargan
            setTimeout(() => {
                forzarOrientacionHorizontal();
            }, 150);
        }
    });
    
    calendario.render();
}

// Función para forzar orientación horizontal
function forzarOrientacionHorizontal() {
    const timeLabels = document.querySelectorAll('.fc-timegrid-axis, .fc-timegrid-slot-label, .fc-timegrid-axis-cushion, .fc-timegrid-slot-label-cushion');
    timeLabels.forEach(el => {
        el.style.writingMode = 'horizontal-tb';
        el.style.textOrientation = 'mixed';
        el.style.transform = 'rotate(0deg)';
        el.style.webkitTransform = 'rotate(0deg)';
        el.style.mozTransform = 'rotate(0deg)';
        el.style.msTransform = 'rotate(0deg)';
        el.style.oTransform = 'rotate(0deg)';
        el.style.width = '90px';
        el.style.minWidth = 'auto';
        el.style.textAlign = 'center';
        el.style.fontSize = '0.7rem';
        el.style.display = 'flex';
        el.style.alignItems = 'center';
        el.style.justifyContent = 'center';
    });
}

// Cargar horarios desde el servidor
function cargarHorarios(info, successCallback, failureCallback) {
    const params = {
        start: info.startStr,
        end: info.endStr,
        view: info.view.type
    };
    
    $.ajax({
        url: '/?page=calendario_horarios',
        method: 'GET',
        data: params,
        dataType: 'json',
        success: function(eventos) {
            console.log('Eventos recibidos del servidor:', eventos);
            console.log('Para vista:', info.view.type, 'desde', info.startStr, 'hasta', info.endStr);
            
            if (!Array.isArray(eventos)) {
                console.error('Los eventos no son un array:', eventos);
                eventos = [];
            }
            
            // Procesar y validar fechas
            const eventosValidados = eventos.map(evento => {
                // Asegurar que las fechas estén en formato ISO
                if (evento.start && !evento.start.includes('T')) {
                    evento.start = evento.start.replace(' ', 'T');
                }
                if (evento.end && !evento.end.includes('T')) {
                    evento.end = evento.end.replace(' ', 'T');
                }
                
                // Asegurar propiedades extendidas
                if (!evento.extendedProps) {
                    evento.extendedProps = {};
                }
                
                console.log('Evento procesado:', {
                    id: evento.id,
                    title: evento.title,
                    start: evento.start,
                    end: evento.end,
                    backgroundColor: evento.backgroundColor,
                    borderColor: evento.borderColor
                });
                
                return evento;
            });
            
            // Aplicar filtros
            const filtroFicha = $('#filtroFicha').val();
            const filtroEstado = $('#filtroEstado').val();
            
            let eventosFiltrados = eventosValidados;
            
            if (filtroFicha) {
                eventosFiltrados = eventosFiltrados.filter(e => 
                    e.extendedProps && e.extendedProps.ficha_id == filtroFicha
                );
            }
            
            if (filtroEstado) {
                eventosFiltrados = eventosFiltrados.filter(e => 
                    e.extendedProps && e.extendedProps.estado === filtroEstado
                );
            }
            
            console.log('Eventos filtrados enviados a FullCalendar:', eventosFiltrados);
            successCallback(eventosFiltrados);
            actualizarEstadisticas(eventos);
        },
        error: function(xhr) {
            console.error('Error al cargar horarios:', xhr);
            console.error('Respuesta del servidor:', xhr.responseText);
            failureCallback(xhr);
            mostrarError('Error al cargar los horarios: ' + (xhr.responseText || xhr.statusText));
        }
    });
}

// Cargar fichas disponibles
function cargarFichasDisponibles() {
    $.ajax({
        url: '/?page=calendario_fichas',
        method: 'GET',
        dataType: 'json',
        success: function(fichas) {
            fichasDisponibles = fichas;
            
            // Llenar selectores
            const selectores = ['#fichaId', '#filtroFicha'];
            selectores.forEach(selector => {
                const $select = $(selector);
                const valorActual = $select.val();
                
                if (selector === '#filtroFicha') {
                    $select.html('<option value="">Todas las fichas</option>');
                } else {
                    $select.html('<option value="">Seleccionar ficha...</option>');
                }
                
                fichas.forEach(ficha => {
                    $select.append(`
                        <option value="${ficha.id}">
                            ${ficha.codigo} - ${ficha.nombre}
                        </option>
                    `);
                });
                
                if (valorActual) $select.val(valorActual);
            });
        },
        error: function(xhr) {
            console.error('Error al cargar fichas:', xhr);
            mostrarError('Error al cargar las fichas disponibles');
        }
    });
}

// Configurar eventos de la interfaz
function configurarEventos() {
    // Botón crear horario
    $('#btnCrearHorario').click(function() {
        abrirModalHorario();
    });
    
    // Botón guardar horario
    $('#btnGuardarHorario').click(function() {
        console.log('=== CLICK EN BOTÓN GUARDAR ===');
        guardarHorario();
    });
    
    // Filtros
    $('#filtroFicha, #filtroEstado').change(function() {
        calendario.refetchEvents();
    });
    
    // Botones de vista removidos - usar solo los nativos de FullCalendar
    
    // Presets de colores
    $('.color-preset').click(function() {
        const color = $(this).data('color');
        $('#color').val(color);
        $('.color-preset').removeClass('active');
        $(this).addClass('active');
    });
    
    // Validación de horas
    $('#horaInicio, #horaFin').change(function() {
        validarHorarios();
    });
    
    // Auto-completar día de la semana basado en fecha
    $('#fechaInicio').change(function() {
        const fecha = new Date($(this).val());
        const dias = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
        $('#diaSemana').val(dias[fecha.getDay()]);
    });
    
    // Botones del modal de detalles
    $('#btnEditarEvento').click(function() {
        if (eventoSeleccionado) {
            $('#modalDetalles').modal('hide');
            abrirModalHorario(eventoSeleccionado);
        }
    });
    
    $('#btnEliminarEvento').click(function() {
        if (eventoSeleccionado) {
            cancelarHorario(eventoSeleccionado.id);
        }
    });
    
    $('#btnIniciarClase').click(function() {
        if (eventoSeleccionado) {
            cambiarEstadoHorario(eventoSeleccionado.id, 'en_curso');
        }
    });
    
    $('#btnFinalizarClase').click(function() {
        if (eventoSeleccionado) {
            cambiarEstadoHorario(eventoSeleccionado.id, 'finalizado');
        }
    });
    
    // Botón sincronizar calendario
    $('#btnSincronizar').click(function() {
        abrirModalSincronizar();
    });
    
    // Enviar solicitud de sincronización
    $('#btnEnviarSolicitud').click(function() {
        enviarSolicitudSincronizacion();
    });
}

// Abrir modal para crear/editar horario
function abrirModalHorario(evento = null, seleccion = null) {
    const esEdicion = evento !== null;
    
    // Configurar título del modal
    $('#modalHorarioTitulo').text(esEdicion ? 'Editar Horario' : 'Nuevo Horario');
    
    // Limpiar formulario
    $('#formHorario')[0].reset();
    $('#horarioId').val('');
    $('.color-preset').removeClass('active');
    
    if (esEdicion) {
        // Llenar datos del evento existente
        const props = evento.extendedProps;
        $('#horarioId').val(evento.id);
        $('#fichaId').val(props.ficha_id);
        
        const fechaInicio = new Date(evento.start);
        const fechaFin = new Date(evento.end);
        
        // Llenar campos ocultos
        $('#fechaInicio').val(fechaInicio.toISOString().split('T')[0]);
        $('#horaInicio').val(fechaInicio.toTimeString().slice(0, 5));
        $('#horaFin').val(fechaFin.toTimeString().slice(0, 5));
        
        const dias = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
        $('#diaSemana').val(dias[fechaInicio.getDay()]);
        
        // Mostrar información
        actualizarInfoHorario(fechaInicio, fechaFin);
        
        $('#aula').val(props.aula || '');
        $('#color').val(evento.backgroundColor);
        
        // Activar preset de color si coincide
        $(`.color-preset[data-color="${evento.backgroundColor}"]`).addClass('active');
        
    } else if (seleccion) {
        // Usar datos de la selección del calendario
        const fechaInicio = new Date(seleccion.start);
        const fechaFin = new Date(seleccion.end);
        
        // Llenar campos ocultos
        $('#fechaInicio').val(fechaInicio.toISOString().split('T')[0]);
        $('#horaInicio').val(fechaInicio.toTimeString().slice(0, 5));
        $('#horaFin').val(fechaFin.toTimeString().slice(0, 5));
        
        // Auto-completar día de la semana
        const dias = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
        $('#diaSemana').val(dias[fechaInicio.getDay()]);
        
        // Mostrar información del horario seleccionado
        actualizarInfoHorario(fechaInicio, fechaFin);
    }
    
    $('#modalHorario').modal('show');
}

// Actualizar información del horario en el modal
function actualizarInfoHorario(fechaInicio, fechaFin) {
    const opciones = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    };
    
    const fechaTexto = fechaInicio.toLocaleDateString('es-ES', opciones);
    const horaInicio = fechaInicio.toTimeString().slice(0, 5);
    const horaFin = fechaFin.toTimeString().slice(0, 5);
    
    $('#infoFechaHora').text(`${fechaTexto} de ${horaInicio} a ${horaFin}`);
    $('#infoDia').text(fechaInicio.toLocaleDateString('es-ES', { weekday: 'long' }));
}

// Guardar horario (crear o actualizar)
function guardarHorario() {
    console.log('=== FUNCIÓN GUARDAR HORARIO INICIADA ===');
    if (!validarFormulario()) {
        console.log('Validación falló');
        return;
    }
    
    const formData = new FormData($('#formHorario')[0]);
    const datos = Object.fromEntries(formData.entries());
    
    // Auto-generar título basado en ficha seleccionada
    const fichaSelect = $('#fichaId option:selected');
    const fichaTexto = fichaSelect.text().replace(/\s+/g, ' ').trim();
    const tituloGenerado = `Clase con Instructor/Facilitador - ${fichaTexto}`;
    
    // Debug: verificar datos
    console.log('Datos del formulario:', datos);
    
    
    // Simplificar datos - usar solo lo necesario
    const fechaInicio = datos.fechaInicio || $('#fechaInicio').val();
    const horaInicio = datos.horaInicio || $('#horaInicio').val();
    const horaFin = datos.horaFin || $('#horaFin').val();
    
    const datosSimples = {
        ficha_id: datos.ficha_id || $('#fichaId').val(),
        titulo: tituloGenerado,
        fecha_inicio: `${fechaInicio} ${horaInicio}:00`,
        fecha_fin: `${fechaInicio} ${horaFin}:00`,
        dia_semana: datos.diaSemana || $('#diaSemana').val(),
        hora_inicio: horaInicio,
        hora_fin: horaFin,
        aula: datos.aula || $('#aula').val(),
        color: datos.color || $('#color').val() || '#007bff'
    };
    
    console.log('Datos a enviar:', datosSimples);
    
    if (datos.horarioId) {
        datosSimples.id = datos.horarioId;
    }
    
    const esEdicion = datos.horarioId !== '';
    const url = esEdicion ? '/?page=calendario_actualizar' : '/?page=calendario_crear';
    
    // Mostrar loading
    $('#btnGuardarHorario').addClass('loading').prop('disabled', true);
    
    $.ajax({
        url: url,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(datosSimples),
        success: function(response) {
            console.log('Respuesta del servidor:', response);
            $('#modalHorario').modal('hide');
            // Forzar recarga completa del calendario
            setTimeout(() => {
                calendario.refetchEvents();
            }, 100);
            mostrarExito(esEdicion ? 'Horario actualizado correctamente' : 'Horario creado correctamente');
        },
        error: function(xhr) {
            console.log('Error en AJAX:', xhr);
            console.log('Status:', xhr.status);
            console.log('Response:', xhr.responseText);
            console.log('URL llamada:', url);
            
            let errorMsg = 'Error al guardar el horario';
            try {
                const response = JSON.parse(xhr.responseText);
                errorMsg = response.error || errorMsg;
            } catch(e) {
                errorMsg = xhr.responseText || errorMsg;
            }
            
            console.log('Error final:', errorMsg);
            
            if (xhr.status === 409) {
                // Conflicto de horarios
                mostrarConflictos(xhr.responseJSON.conflictos);
            } else {
                mostrarError(errorMsg);
            }
        },
        complete: function() {
            $('#btnGuardarHorario').removeClass('loading').prop('disabled', false);
        }
    });
}

// Mostrar detalles del evento
function mostrarDetallesEvento(evento) {
    eventoSeleccionado = evento;
    const props = evento.extendedProps;
    
    const fechaInicio = new Date(evento.start);
    const fechaFin = new Date(evento.end);
    
    const contenido = `
        <div class="detalle-item">
            <span class="detalle-label">Ficha:</span>
            <span class="detalle-valor">${props.ficha_codigo} - ${props.ficha_nombre}</span>
        </div>
        <div class="detalle-item">
            <span class="detalle-label">Instructor/Facilitador:</span>
            <span class="detalle-valor">${props.profesor_nombre}</span>
        </div>
        <div class="detalle-item">
            <span class="detalle-label">Fecha:</span>
            <span class="detalle-valor">${fechaInicio.toLocaleDateString('es-ES')}</span>
        </div>
        <div class="detalle-item">
            <span class="detalle-label">Horario:</span>
            <span class="detalle-valor">${fechaInicio.toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'})} - ${fechaFin.toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'})}</span>
        </div>
        <div class="detalle-item">
            <span class="detalle-label">Aula:</span>
            <span class="detalle-valor">${props.aula || 'No especificada'}</span>
        </div>
        <div class="detalle-item">
            <span class="detalle-label">Estado:</span>
            <span class="badge badge-${props.estado}">${props.estado.charAt(0).toUpperCase() + props.estado.slice(1)}</span>
        </div>
    `;
    
    $('#detallesContent').html(contenido);
    
    // Configurar botones según estado
    configurarBotonesEstado(props.estado, props.asistencia_habilitada);
    
    $('#modalDetalles').modal('show');
}

// Configurar botones según el estado del horario
function configurarBotonesEstado(estado, asistenciaHabilitada) {
    $('#btnIniciarClase, #btnFinalizarClase').hide();
    
    const ahora = new Date();
    const inicioEvento = new Date(eventoSeleccionado.start);
    const finEvento = new Date(eventoSeleccionado.end);
    
    // Ocultar botones de editar y eliminar para eventos finalizados o pasados
    const esPasado = finEvento < ahora;
    const esFinalizado = estado === 'finalizado';
    
    if (esPasado || esFinalizado) {
        $('#btnEditarEvento, #btnEliminarEvento').hide();
    } else {
        $('#btnEditarEvento, #btnEliminarEvento').show();
    }
    
    if (estado === 'programado' && ahora >= inicioEvento && ahora <= finEvento) {
        $('#btnIniciarClase').show();
    } else if (estado === 'en_curso') {
        $('#btnFinalizarClase').show();
    }
}

// Cambiar estado del horario
function cambiarEstadoHorario(horarioId, nuevoEstado) {
    $.ajax({
        url: '/?page=calendario_estado',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            horario_id: horarioId,
            estado: nuevoEstado
        }),
        success: function() {
            $('#modalDetalles').modal('hide');
            calendario.refetchEvents();
            
            let mensaje = '';
            switch(nuevoEstado) {
                case 'en_curso':
                    mensaje = 'Clase iniciada. Asistencia habilitada.';
                    break;
                case 'finalizado':
                    mensaje = 'Clase finalizada correctamente.';
                    break;
                case 'cancelado':
                    mensaje = 'Clase cancelada. Se mantiene en el historial.';
                    break;
                default:
                    mensaje = 'Estado actualizado correctamente.';
            }
            
            mostrarExito(mensaje);
        },
        error: function(xhr) {
            mostrarError('Error al cambiar el estado del horario');
        }
    });
}

// Cancelar horario (cambiar estado a cancelado)
function cancelarHorario(horarioId) {
    Swal.fire({
        title: '¿Cancelar clase?',
        text: 'La clase se marcará como cancelada pero se mantendrá en el historial',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, cancelar clase',
        cancelButtonText: 'No cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            cambiarEstadoHorario(horarioId, 'cancelado');
        }
    });
}

// Actualizar horario cuando se arrastra
function actualizarHorarioArrastrado(info) {
    const evento = info.event;
    const datos = {
        id: evento.id,
        titulo: evento.title,
        fecha_inicio: evento.start.toISOString().slice(0, 19).replace('T', ' '),
        fecha_fin: evento.end.toISOString().slice(0, 19).replace('T', ' '),
        dia_semana: ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'][evento.start.getDay()],
        hora_inicio: evento.start.toTimeString().slice(0, 5),
        hora_fin: evento.end.toTimeString().slice(0, 5),
        ficha_id: evento.extendedProps.ficha_id,
        aula: evento.extendedProps.aula,
        color: evento.backgroundColor
    };
    
    actualizarHorarioAjax(datos, info);
}

// Actualizar horario cuando se redimensiona
function actualizarHorarioRedimensionado(info) {
    const evento = info.event;
    const datos = {
        id: evento.id,
        titulo: evento.title,
        fecha_inicio: evento.start.toISOString().slice(0, 19).replace('T', ' '),
        fecha_fin: evento.end.toISOString().slice(0, 19).replace('T', ' '),
        dia_semana: ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'][evento.start.getDay()],
        hora_inicio: evento.start.toTimeString().slice(0, 5),
        hora_fin: evento.end.toTimeString().slice(0, 5),
        ficha_id: evento.extendedProps.ficha_id,
        aula: evento.extendedProps.aula,
        color: evento.backgroundColor
    };
    
    actualizarHorarioAjax(datos, info);
}

// Función común para actualizar horario via AJAX
function actualizarHorarioAjax(datos, info) {
    $.ajax({
        url: '/?page=calendario_actualizar',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(datos),
        success: function() {
            mostrarExito('Horario actualizado correctamente');
        },
        error: function(xhr) {
            // Revertir cambio si hay error
            info.revert();
            
            if (xhr.status === 409) {
                mostrarConflictos(xhr.responseJSON.conflictos);
            } else {
                mostrarError('Error al actualizar el horario');
            }
        }
    });
}

// Aplicar estilos personalizados a eventos
function aplicarEstilosEvento(info) {
    const evento = info.event;
    const estado = evento.extendedProps.estado;
    
    // Agregar clases CSS según estado
    info.el.classList.add(`evento-${estado}`);
    
    // Agregar tooltip
    $(info.el).attr('title', `${evento.title} - ${estado.charAt(0).toUpperCase() + estado.slice(1)}`);
}

// Validar formulario de horario
function validarFormulario() {
    console.log('=== VALIDANDO FORMULARIO ===');
    const campos = ['fichaId', 'aula']; // Solo validar campos visibles
    let valido = true;
    
    campos.forEach(campo => {
        const $campo = $(`#${campo}`);
        const valor = $campo.val();
        console.log(`Campo ${campo}: "${valor}"`);
        if (!valor || !valor.trim()) {
            $campo.addClass('is-invalid');
            valido = false;
            console.log(`Campo ${campo} es inválido`);
        } else {
            $campo.removeClass('is-invalid');
        }
    });
    
    console.log(`Validación resultado: ${valido}`);
    return valido;
    
    // Validar que hora fin sea mayor que hora inicio
    if (!validarHorarios()) {
        valido = false;
    }
    
    return valido;
}

// Validar horarios
function validarHorarios() {
    const horaInicio = $('#horaInicio').val();
    const horaFin = $('#horaFin').val();
    
    if (horaInicio && horaFin) {
        if (horaInicio >= horaFin) {
            $('#horaFin').addClass('is-invalid');
            mostrarError('La hora de fin debe ser mayor que la hora de inicio');
            return false;
        } else {
            $('#horaInicio, #horaFin').removeClass('is-invalid');
            return true;
        }
    }
    
    return true;
}

// Mostrar conflictos de horarios
function mostrarConflictos(conflictos) {
    let mensaje = 'Se detectaron conflictos de horarios:\n\n';
    conflictos.forEach(conflicto => {
        mensaje += `• ${conflicto.ficha_codigo}: ${conflicto.fecha_inicio} - ${conflicto.fecha_fin}\n`;
    });
    
    Swal.fire({
        title: 'Conflicto de Horarios',
        text: mensaje,
        icon: 'warning',
        confirmButtonText: 'Entendido'
    });
}

// Actualizar estadísticas
function actualizarEstadisticas(eventos = null) {
    if (!eventos) {
        // Cargar eventos para estadísticas
        $.ajax({
            url: '/?page=calendario_horarios',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                actualizarEstadisticas(data);
            }
        });
        return;
    }
    
    const hoy = new Date().toDateString();
    const horariosHoy = eventos.filter(evento => {
        const fechaEvento = new Date(evento.start).toDateString();
        return fechaEvento === hoy;
    });
    
    $('#totalHorarios').text(eventos.length);
    $('#horariosHoy').text(horariosHoy.length);
}

// Funciones de utilidad para mensajes
function mostrarExito(mensaje) {
    Swal.fire({
        icon: 'success',
        title: '¡Éxito!',
        text: mensaje,
        timer: 3000,
        showConfirmButton: false
    });
}

function mostrarError(mensaje) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: mensaje
    });
}

function mostrarInfo(mensaje) {
    Swal.fire({
        icon: 'info',
        title: 'Información',
        text: mensaje
    });
}

// ===== FUNCIONES DE SINCRONIZACIÓN =====

// Cargar calendarios sincronizados
function cargarCalendariosSincronizados() {
    $.ajax({
        url: '/?page=calendarios_sincronizados',
        method: 'GET',
        dataType: 'json',
        success: function(sincronizados) {
            mostrarCalendariosSincronizados(sincronizados);
        },
        error: function(xhr) {
            console.error('Error al cargar calendarios sincronizados:', xhr);
        }
    });
}

// Mostrar lista de calendarios sincronizados
function mostrarCalendariosSincronizados(sincronizados) {
    const $lista = $('#listaSincronizados');
    
    if (sincronizados.length === 0) {
        $lista.html('<small class="text-muted">No hay calendarios sincronizados</small>');
        return;
    }
    
    let html = '';
    sincronizados.forEach(sync => {
        const icono = sync.tipo === 'propietario' ? 'fa-share-alt' : 'fa-eye';
        const permisos = sync.permisos === 'solo_lectura' ? 'Solo lectura' : 'Lectura/Escritura';
        const tipo = sync.tipo === 'propietario' ? 'Compartido con' : 'Recibido de';
        
        html += `
            <div class="sync-item d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                <div class="flex-grow-1">
                    <div class="fw-bold">${sync.profesor_nombre}</div>
                    <small class="text-muted">${tipo} • ${permisos}</small>
                </div>
                <div class="d-flex gap-1">
                    <i class="fas ${icono} text-primary"></i>
                    <button class="btn btn-sm btn-outline-danger" onclick="eliminarSincronizacion(${sync.id})" title="Eliminar sincronización">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    $lista.html(html);
}

// Abrir modal para sincronizar
function abrirModalSincronizar() {
    // Cargar profesores disponibles
    $.ajax({
        url: '/?page=profesores_sincronizar',
        method: 'GET',
        dataType: 'json',
        success: function(profesores) {
            const $select = $('#profesorSincronizar');
            $select.html('<option value="">Seleccionar Instructor/Facilitador...</option>');
            
            profesores.forEach(profesor => {
                $select.append(`
                    <option value="${profesor.id}">
                        ${profesor.nombres} (${profesor.correo})
                    </option>
                `);
            });
            
            $('#modalSincronizar').modal('show');
        },
        error: function(xhr) {
            mostrarError('Error al cargar Instructor/Facilitador disponibles');
        }
    });
}

// Enviar solicitud de sincronización
function enviarSolicitudSincronizacion() {
    const formData = new FormData($('#formSincronizar')[0]);
    const datos = Object.fromEntries(formData.entries());
    
    if (!datos.profesor_id || !datos.permisos) {
        mostrarError('Por favor completa todos los campos');
        return;
    }
    
    $('#btnEnviarSolicitud').addClass('loading').prop('disabled', true);
    
    $.ajax({
        url: '/?page=sincronizar_calendario',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(datos),
        success: function(response) {
            $('#modalSincronizar').modal('hide');
            mostrarExito(response.message);
            cargarCalendariosSincronizados();
        },
        error: function(xhr) {
            const error = xhr.responseJSON?.error || 'Error al enviar solicitud';
            mostrarError(error);
        },
        complete: function() {
            $('#btnEnviarSolicitud').removeClass('loading').prop('disabled', false);
        }
    });
}

// Eliminar sincronización
function eliminarSincronizacion(sincronizacionId) {
    Swal.fire({
        title: '¿Eliminar sincronización?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '/?page=eliminar_sincronizacion',
                method: 'POST',
                data: { sincronizacion_id: sincronizacionId },
                success: function(response) {
                    mostrarExito(response.message);
                    cargarCalendariosSincronizados();
                    calendario.refetchEvents(); // Actualizar calendario
                },
                error: function(xhr) {
                    const error = xhr.responseJSON?.error || 'Error al eliminar sincronización';
                    mostrarError(error);
                }
            });
        }
    });
}
