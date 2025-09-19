<?php
require_once __DIR__ . '/../../helpers/auth.php';
start_secure_session();
require_role([1,2]);

$profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
// Si viene ?profesor_id y el usuario es admin, usar ese para filtrar
if (isset($_GET['profesor_id']) && !empty($_GET['profesor_id'])) {
    $isAdmin = (int)($_SESSION['usuario']['rol_id'] ?? 0) === 1;
    if ($isAdmin) {
        $profesor_id = $_GET['profesor_id'];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario Colaborativo - Sistema Escolar SENA</title>
    
    <!-- Estilos personalizados para el calendario -->
    <style>
        /* Reset básico */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        #calendario {
            margin: 20px auto;
            max-width: 1100px;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .fc { 
            direction: ltr; 
            text-align: left;
            width: 100%;
            margin: 0 auto;
            font-size: 14px;
        }
        
        /* Estilos para los botones */
        .fc .fc-button {
            background:rgb(3, 16, 47);
            border: 1px solid rgb(26, 32, 57);
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .fc .fc-button:hover {
            background: #3a5bd9;
        }
        
        .fc .fc-button-active {
            background: #2c4ec9;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Estilos para la cabecera */
        .fc-header-toolbar {
            margin-bottom: 1.5em;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        
        .fc-toolbar-title {
            font-size: 1.5em;
            margin: 0 10px;
            font-weight: 600;
        }
        
        /* Estilos para los días */
        .fc-daygrid-day {
            border: 1px solid #e0e0e0;
        }
        
        .fc-day-today {
            background-color: #f0f7ff !important;
        }
        
        .fc-daygrid-day-number {
            padding: 4px;
            color: #333;
        }
        
        /* Estilos para los eventos */
        .fc-event {
            padding: 2px 4px;
            margin: 1px 2px;
            border-radius: 3px;
            font-size: 0.85em;
            cursor: pointer;
            border: none;
        }
        .fc table { 
            border-collapse: collapse; 
            border-spacing: 0; 
            width: 100%;
        }
        .fc th, .fc td { 
            padding: 8px; 
            vertical-align: top; 
            border: 1px solid #ddd;
        }
        .fc .fc-button { 
            background: rgb(3, 16, 47) !important; 
            border: 1px solid rgb(26, 32, 57) !important; 
            color: white !important; 
            padding: 0.375rem 0.75rem; 
            border-radius: 0.25rem; 
            margin: 0 2px;
        }
        .fc .fc-button:hover { 
            background: #0056b3 !important; 
            border-color: #0056b3 !important; 
        }
        .fc-button-primary:not(:disabled):active, .fc-button-primary:not(:disabled).fc-button-active {
            background-color: #010101 !important;
            border-color: #0b0d0b !important;
        }
        .fc .fc-toolbar.fc-header-toolbar { 
            background: repeating-radial-gradient(#17a11d, #078800) !important;
            color: white !important;
            padding: 1rem !important;
            border-radius: 10px !important;
            margin-bottom: 1rem !important;
            display: flex !important; 
            flex-wrap: wrap !important;
            justify-content: space-between !important; 
            align-items: center !important; 
            gap: 10px !important;
        }
        
        .fc .fc-toolbar-title {
            color: white !important;
        }
        
        .fc .fc-button-group .fc-button {
            background: rgb(3, 16, 47) !important;
            border: 1px solid rgb(26, 32, 57) !important;
            color: white !important;
        }
        .fc-toolbar-title { 
            font-size: 1.5rem; 
            font-weight: bold; 
            margin: 0 10px;
        }
        .fc-daygrid-day { 
            min-height: 100px; 
        }
        .fc-event { 
            padding: 2px 4px; 
            margin: 1px; 
            border-radius: 3px; 
            font-size: 0.85rem;
            cursor: pointer;
        }
        .fc-timegrid-slot { 
            height: 2em; 
        }
        .fc-col-header-cell { 
            padding: 0.5rem; 
            font-weight: bold; 
            text-align: center;
        }
        #calendario {
            width: 100%;
            margin: 0 auto;
            padding: 1rem;
        }
        .fc-view-harness {
            min-height: 600px;
        }
        
        /* Estilos para la pestaña de asistencias */
        #tablaAsistencias .form-select {
            min-width: 120px;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        #tablaAsistencias .btn-sm {
            padding: 0.15rem 0.4rem;
            font-size: 0.75rem;
        }
        
        .estado-badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 500;
        }
        
        .estado-presente {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        
        .estado-falla {
            background-color: #f8d7da;
            color: #842029;
        }
        
        .estado-justificada {
            background-color: #fff3cd;
            color: #664d03;
        }
        
        .estado-tardanza {
            background-color: #cfe2ff;
            color: #084298;
        }
        
        .estado-pendiente {
            background-color: #e2e3e5;
            color: #41464b;
        }
        
        .badge-container {
            min-width: 100px;
            display: inline-block;
            text-align: center;
        }
    
        
        /* Estilos adicionales para forzar cambios */
        #calendario .fc-toolbar.fc-header-toolbar {
            background: repeating-radial-gradient(#17a11d, #078800) !important;
            color: white !important;
            padding: 1rem !important;
            border-radius: 10px !important;
            margin-bottom: 1rem !important;
        }
        
        #calendario .fc-toolbar-title {
            color: white !important;
            font-weight: bold !important;
        }
        
        #calendario .fc-button {
            background: rgb(3, 16, 47) !important;
            border: 1px solid rgb(26, 32, 57) !important;
            color: white !important;
        }
        
        #calendario .fc-button:hover {
            background: #0056b3 !important;
            border-color: #0056b3 !important;
        }
        
        #calendario .fc-button-primary:not(:disabled):active,
        #calendario .fc-button-primary:not(:disabled).fc-button-active {
            background-color: #010101 !important;
            border-color: #0b0d0b !important;
        }
    
    </style>
    
    <!-- CSS personalizado -->
    <link rel="stylesheet" href="/css/Calendario/calendario.css?v=<?php echo time(); ?>">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../Componentes/encabezado.php'; ?>

    <div class="container-fluid mt-4">
        <!-- Panel de controles compacto -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body py-2">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <button type="button" class="btn btn-success btn-sm w-100" id="btnCrearHorario">
                                    <i class="fas fa-plus me-1"></i>
                                    Nuevo Bloque
                                </button>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select form-select-sm" id="filtroFicha">
                                    <option value="">Todas las fichas</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select form-select-sm" id="filtroEstado">
                                    <option value="">Todos los estados</option>
                                    <option value="programado">Programado</option>
                                    <option value="en_curso">En Curso</option>
                                    <option value="finalizado">Finalizado</option>
                                    <option value="cancelado">Cancelado</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <!-- Leyenda compacta -->
                                <div class="d-flex align-items-center gap-2">
                                    <small class="text-muted me-2">Leyenda:</small>
                                    <div class="d-flex align-items-center">
                                        <div class="color-box me-1" style="background-color: #28a745; width: 12px; height: 12px;"></div>
                                        <small class="me-2">En Curso</small>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="color-box me-1" style="background-color: #6c757d; width: 12px; height: 12px;"></div>
                                        <small class="me-2">Finalizado</small>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="color-box me-1" style="background-color: #dc3545; width: 12px; height: 12px;"></div>
                                        <small class="me-2">Cancelado</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex gap-2 align-items-center">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnSincronizar" title="Sincronizar">
                                        <i class="fas fa-share-alt"></i>
                                    </button>
                                    <button type="button" class="btn btn-info btn-sm text-white" id="btnExportarReporte" title="Exportar reporte">
                                        <i class="fas fa-file-export me-1"></i> Exportar
                                    </button>
                                    <div class="vr mx-1"></div>
                                    <div class="text-center">
                                        <div class="stat-number small" id="totalHorarios">0</div>
                                        <small class="text-muted">Total</small>
                                    </div>
                                    <div class="text-center">
                                        <div class="stat-number small" id="horariosHoy">0</div>
                                        <small class="text-muted">Hoy</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div id="listaSincronizados" class="d-none">
                                    <!-- Lista dinámica oculta por defecto -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Calendario principal expandido -->
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>
                            Calendario Colaborativo
                        </h4>
                    </div>
                    <div class="card-body p-0">
                        <div id="calendario" style="min-height: 70vh; padding: 1rem;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para crear/editar horario -->
    <div class="modal fade" id="modalHorario" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalHorarioTitulo">Nuevo Horario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formHorario">
                        <input type="hidden" id="horarioId" name="horarioId">
                        <input type="hidden" id="fechaInicio" name="fechaInicio">
                        <input type="hidden" id="horaInicio" name="horaInicio">
                        <input type="hidden" id="horaFin" name="horaFin">
                        <input type="hidden" id="diaSemana" name="diaSemana">
                        <input type="hidden" id="titulo" name="titulo">
                        
                        <!-- Información del horario seleccionado -->
                        <div class="alert alert-info mb-4">
                            <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>Horario Seleccionado</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <small class="text-muted">Fecha y Hora:</small><br>
                                    <span id="infoFechaHora" class="fw-bold">-</span>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted">Día:</small><br>
                                    <span id="infoDia" class="fw-bold">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Editor manual de fecha y hora (solo para 'Nuevo Bloque') -->
                        <div id="editorManualTiempo" class="mb-4" style="display:none;">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="inputFecha" class="form-label">Fecha</label>
                                    <input type="date" class="form-control" id="inputFecha" />
                                </div>
                                <div class="col-md-4">
                                    <label for="inputHoraInicio" class="form-label">Hora inicio</label>
                                    <input type="time" class="form-control" id="inputHoraInicio" step="3600" />
                                </div>
                                <div class="col-md-4">
                                    <label for="inputHoraFin" class="form-label">Hora fin</label>
                                    <input type="time" class="form-control" id="inputHoraFin" step="3600" />
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="fichaId" class="form-label">Ficha <span class="text-danger">*</span></label>
                                    <select class="form-select" id="fichaId" name="ficha_id" required>
                                        <option value="">Seleccionar ficha...</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="aula" class="form-label">Aula <span class="text-danger">*</span></label>
                                    <select class="form-select" id="aula" name="aula" required>
                                        <option value="">Seleccionar aula...</option>
                                        <option value="Aula 101">Aula 101</option>
                                        <option value="Aula 102">Aula 102</option>
                                        <option value="Aula 103">Aula 103</option>
                                        <option value="Aula 201">Aula 201</option>
                                        <option value="Aula 202">Aula 202</option>
                                        <option value="Laboratorio 1">Laboratorio 1</option>
                                        <option value="Laboratorio 2">Laboratorio 2</option>
                                        <option value="Sala de Sistemas">Sala de Sistemas</option>
                                        <option value="Auditorio">Auditorio</option>
                                        <option value="Biblioteca">Biblioteca</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="color" class="form-label">Color del Evento</label>
                            <div class="d-flex align-items-center">
                                <input type="color" class="form-control form-control-color me-3" id="color" name="color" value="#007bff">
                                <div class="color-presets">
                                    <button type="button" class="color-preset" data-color="#007bff" style="background-color: #007bff;"></button>
                                    <button type="button" class="color-preset" data-color="#28a745" style="background-color: #28a745;"></button>
                                    <button type="button" class="color-preset" data-color="#dc3545" style="background-color: #dc3545;"></button>
                                    <button type="button" class="color-preset" data-color="#ffc107" style="background-color: #ffc107;"></button>
                                    <button type="button" class="color-preset" data-color="#6f42c1" style="background-color: #6f42c1;"></button>
                                    <button type="button" class="color-preset" data-color="#fd7e14" style="background-color: #fd7e14;"></button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarHorario">
                        <i class="fas fa-save me-2"></i>
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de detalles del evento -->
    <div class="modal fade" id="modalDetalles" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalles del Horario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Pestañas -->
                    <ul class="nav nav-tabs mb-3" id="detallesTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="detalles-tab" data-bs-toggle="tab" data-bs-target="#detalles" type="button" role="tab">
                                <i class="fas fa-info-circle me-1"></i> Detalles
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="asistencias-tab" data-bs-toggle="tab" data-bs-target="#asistencias" type="button" role="tab">
                                <i class="fas fa-clipboard-check me-1"></i> Asistencias
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Contenido de las pestañas -->
                    <div class="tab-content" id="detallesTabsContent">
                        <!-- Pestaña de detalles -->
                        <div class="tab-pane fade show active" id="detalles" role="tabpanel">
                            <div id="detallesContent">
                                <!-- Contenido dinámico -->
                            </div>
                        </div>
                        
                        <!-- Pestaña de asistencias -->
                        <div class="tab-pane fade" id="asistencias" role="tabpanel">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Registro de Asistencias</h6>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-outline-primary me-2" id="btnActualizarAsistencias">
                                            <i class="fas fa-sync-alt me-1"></i> Actualizar
                                        </button>
                                        <button type="button" class="btn btn-sm btn-success" id="btnGuardarAsistencias">
                                            <i class="fas fa-save me-1"></i> Guardar Cambios
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover" id="tablaAsistencias">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Estudiante</th>
                                                <th class="text-center" style="width: 120px;">Asistencia</th>
                                                <th class="text-center" style="width: 100px;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="listaAsistencias">
                                            <tr>
                                                <td colspan="3" class="text-center py-4">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="visually-hidden">Cargando...</span>
                                                    </div>
                                                    <p class="mt-2 mb-0">Cargando lista de estudiantes...</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="alert alert-info mt-3 mb-0" id="infoAsistencia">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Seleccione el estado de asistencia para cada estudiante.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-warning" id="btnEditarEvento">
                        <i class="fas fa-edit me-2"></i>
                        Editar
                    </button>
                    <button type="button" class="btn btn-success" id="btnIniciarClase" style="display: none;">
                        <i class="fas fa-play me-2"></i>
                        Iniciar Clase
                    </button>
                    <button type="button" class="btn btn-secondary" id="btnFinalizarClase" style="display: none;">
                        <i class="fas fa-stop me-2"></i>
                        Finalizar
                    </button>
                    <button type="button" class="btn btn-danger" id="btnEliminarEvento">
                        <i class="fas fa-ban me-2"></i>
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para sincronizar calendario -->
    <div class="modal fade" id="modalSincronizar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sincronizar Calendario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formSincronizar">
                        <div class="mb-3">
                            <label for="profesorSincronizar" class="form-label">Seleccionar Instructor/Facilitador</label>
                            <select class="form-select" id="profesorSincronizar" name="profesor_id" required>
                                <option value="">Seleccionar Instructor/Facilitador...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="fichaCompartir" class="form-label">Seleccionar Ficha a Compartir</label>
                            <select class="form-select" id="fichaCompartir" name="ficha_id" required>
                                <option value="">Seleccionar ficha...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="permisosSincronizar" class="form-label">Permisos</label>
                            <select class="form-select" id="permisosSincronizar" name="permisos" required>
                                <option value="solo_lectura">Solo Lectura</option>
                                <option value="lectura_escritura">Lectura y Escritura</option>
                            </select>
                            <div class="form-text">
                                <strong>Solo Lectura:</strong> El Instructor/Facilitador podrá ver tus horarios.<br>
                                <strong>Lectura y Escritura:</strong> El Instructor/Facilitador podrá ver y modificar tus horarios.
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnEnviarSolicitud">
                        <i class="fas fa-paper-plane me-2"></i>
                        Enviar Solicitud
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- FullCalendar 6.1.8 -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    
    <!-- SweetAlert2 para notificaciones -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Script del calendario -->
    <script>
        // Pasar datos del profesor desde PHP a JavaScript
        window.nombreProfesor = '<?php echo htmlspecialchars($_SESSION['usuario']['nombres'] . ' ' . $_SESSION['usuario']['apellidos'], ENT_QUOTES, 'UTF-8'); ?>';
        window.profesorFiltro = '<?php echo htmlspecialchars((string)($profesor_id ?? ''), ENT_QUOTES, 'UTF-8'); ?>';
    </script>
    <script src="/js/calendario_nuevo.js"></script>
</body>
</html>
