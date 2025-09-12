<?php
require_once __DIR__ . '/../../helpers/auth.php';
start_secure_session();
require_role(2);

$profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario Colaborativo - Sistema Escolar SENA</title>
    
    <!-- Bootstrap 5.3.2 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FullCalendar CSS -->
    <style>
        /* FullCalendar base styles */
        .fc { direction: ltr; text-align: left; }
        .fc table { border-collapse: collapse; border-spacing: 0; }
        .fc th, .fc td { padding: 0; vertical-align: top; }
        .fc .fc-button { background: #007bff; border: 1px solid #007bff; color: white; padding: 0.375rem 0.75rem; border-radius: 0.25rem; }
        .fc .fc-button:hover { background: #0056b3; border-color: #0056b3; }
        .fc-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .fc-toolbar-title { font-size: 1.5rem; font-weight: bold; }
        .fc-daygrid-day { min-height: 100px; }
        .fc-event { padding: 2px 4px; margin: 1px; border-radius: 3px; font-size: 0.85rem; }
        .fc-timegrid-slot { height: 2em; }
        .fc-col-header-cell { padding: 0.5rem; font-weight: bold; }
    </style>
    
    <!-- CSS personalizado -->
    <link rel="stylesheet" href="/css/Calendario/calendario.css">
    
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
                                    Nuevo Horario
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
                            <div class="col-md-2">
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnSincronizar" title="Sincronizar">
                                        <i class="fas fa-share-alt"></i>
                                    </button>
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
                    <div class="card-body">
                        <div id="calendario"></div>
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
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalles del Horario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detallesContent">
                    <!-- Contenido dinámico -->
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Script personalizado -->
    <script src="/js/calendario.js"></script>
</body>
</html>
