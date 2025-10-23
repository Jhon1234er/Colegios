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
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <title>Calendario Colaborativo - Sistema Escolar SENA</title>
    
    <!-- FullCalendar base CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css">
    
    <!-- CSS vacío del calendario (reset) -->
    <link rel="stylesheet" href="/css/Calendario/calendario.css?v=<?php echo time(); ?>">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
</head>
<body>
    <?php include __DIR__ . '/../Componentes/encabezado.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <!-- Filtros superiores (estilo colaborativo) -->             
                <div id="cal-filters" class="mb-2">
                    <div class="cf-left">
                        <button type="button" class="btn btn-success btn-sm" id="btnNuevaClase"><i class="fas fa-plus me-1"></i> Nueva Ficha</button>
                        <select id="filtroFicha" class="form-select form-select-sm">
                            <option value="">Todas las fichas</option>
                        </select>
                        <select id="filtroEstado" class="form-select form-select-sm">
                            <option value="">Todos los estados</option>
                            <option value="programado">Programado</option>
                            <option value="en_curso">En curso</option>
                            <option value="finalizado">Finalizado</option>
                            <option value="suspendido">Suspendido</option>
                        </select>
                    </div>
                    <div class="cf-right">
                        <div class="cf-legend">
                            <span class="legend-title">Leyenda:</span>
                            <span class="dot dot-blue"></span> Programado
                            <span class="dot dot-green"></span> En curso
                            <span class="dot dot-black"></span> Finalizado
                            <span class="dot dot-red"></span> Suspendido
                        </div>
                        <div class="cf-actions">
                            <button type="button" class="btn btn-primary btn-sm" id="btnExportar"><i class="fas fa-download me-1"></i> Exportar</button>
                            <button type="button" class="btn btn-success btn-sm" id="btnDuplicarSemana"><i class="fas fa-clone me-1"></i> Duplicar</button>
                            <div class="cf-counts">
                                <span class="count" id="countTotal">1</span>
                                <span class="count-label">Total</span>
                                <span class="count" id="countHoy">1</span>
                                <span class="count-label">Hoy</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="cal-container">
                    <div id="cal-subtitle" class="cal-subtitle"><i class="fas fa-calendar-alt me-2"></i> <b>Calendario Laboral</b></div>
                    <div id="cal-nav" aria-label="Navegación calendario">
                        <button type="button" id="cal-prev" aria-label="Anterior">◀</button>
                        <strong id="cal-title"></strong>
                        <button type="button" id="cal-next" aria-label="Siguiente">▶</button>
                    </div>
                    <div id="calendario"></div>
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

    <!-- Modal para nueva clase (simplificado y funcional) -->
    <div class="modal fade" id="modalNuevaClase" tabindex="-1">
        <div class="modal-dialog modal-lg" style="max-width: 980px;">
            <div class="modal-content" style="border:none;border-radius:12px;">
                <div class="modal-header" style="background:#00304D;justify-content:center;border-radius:12px;margin:16px 16px 0 16px;padding:12px 18px;position:relative;">
                    <h5 class="modal-title" style="color:#fff;text-align:center;width:100%;margin:6px 0;font-weight:900;font-size:24px;letter-spacing:0.4px;">Nueva Clase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                        style="
                          position:absolute;right:18px;top:50%;transform:translateY(-50%);
                          width:40px;height:40px;opacity:1;outline:none;border:0;background-color:transparent;
                          background-repeat:no-repeat;background-position:center;background-size:16px 16px;
                          background-image:url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\' fill=\'none\' stroke=\'%23ffffff\' stroke-width=\'3\' stroke-linecap=\'round\'><path d=\'M4 4 L12 12 M12 4 L4 12\'/></svg>');
                        ">
                    </button>
                </div>
                <div class="modal-body">
                    <div style="position:relative;background:#D7EECC;border-radius:12px;padding:16px 16px 14px 54px;margin:16px 0 18px 0;">
                        <div style="position:absolute;left:12px;top:12px;width:24px;height:24px;border-radius:50%;background:#00304D;display:flex;align-items:center;justify-content:center;color:transparent;">
                          <span style="position:relative;color:transparent;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                              <circle cx="12" cy="12" r="10" fill="#00304D"></circle>
                              <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 2.5-3 4" />
                              <line x1="12" y1="17" x2="12.01" y2="17" />
                            </svg>
                          </span>
                        </div>
                        <div style="color:#000;font-weight:700;margin-bottom:4px;display:flex;align-items:center;gap:8px;">
                          <span>Horario Seleccionado</span>
                          <small style="background:#e7f3dc;color:#00304D;border-radius:8px;padding:2px 8px;font-weight:600;">Fecha y Hora</small>
                        </div>
                        <div style="color:#000;font-weight:400;opacity:.9;">Selecciona la ficha. Esta te mostrará la información sobre los días y horarios disponibles. Luego, elige la fecha y, a continuación, selecciona la hora. Finalmente, indica el aula y, si lo deseas, el color.</div>
                    </div>
                    <form id="formNuevaClase">
                        <div class="mb-3">
                            <label for="fichaNuevaClase" class="form-label">Ficha</label>
                            <select class="form-select" id="fichaNuevaClase" name="ficha_id" required>
                                <option value="">Seleccionar ficha...</option>
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="fechaNuevaClase" class="form-label">Fecha</label>
                                <input type="text" class="form-control" id="fechaNuevaClase" required>
                            </div>
                            <div class="col-md-3">
                                <label for="horaInicioNuevaClase" class="form-label">Inicio</label>
                                <input type="text" class="form-control" id="horaInicioNuevaClase" required>
                            </div>
                            <div class="col-md-3">
                                <label for="horaFinNuevaClase" class="form-label">Fin</label>
                                <input type="text" class="form-control" id="horaFinNuevaClase" required>
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label for="aulaNuevaClase" class="form-label">Aula</label>
                                <input type="text" class="form-control" id="aulaNuevaClase" placeholder="Ej: A-102" required>
                            </div>
                            <div class="col-md-6">
                                <label for="colorNuevaClase" class="form-label">Color</label>
                                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                  <input type="color" class="form-control form-control-color" id="colorNuevaClase" value="#3b82f6" title="Elige color">
                                  <div id="newclass-color-chips">
                                    <button type="button" class="cc-color" data-color="#52a31a" style="background:#52a31a;"></button>
                                    <button type="button" class="cc-color" data-color="#ea1919" style="background:#ea1919;"></button>
                                    <button type="button" class="cc-color" data-color="#f0d01a" style="background:#f0d01a;"></button>
                                    <button type="button" class="cc-color" data-color="#a020f0" style="background:#a020f0;"></button>
                                  </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnCrearClase">
                        <i class="fas fa-plus me-2"></i>
                        Crear Clase
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Flatpickr (igual que colaborativo) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

    <!-- Scripts mínimos -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="/js/Calendario/calendario_base.js"></script>

</body>
</html>
