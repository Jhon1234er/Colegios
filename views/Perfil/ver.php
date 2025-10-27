<?php 
include '../views/Componentes/encabezado.php'; 
// CAMBIO AGREGADO: Incluir helper de autenticación para generar tokens CSRF
// Necesario para las peticiones AJAX seguras (actualizar perfil y cambiar contraseña)
require_once '../helpers/auth.php';

// Obtener datos del usuario actual
$usuarioId = $_SESSION['usuario']['id'];
$rolId = $_SESSION['usuario']['rol_id'];

// Obtener datos completos del usuario
require_once '../models/Usuario.php';
$usuarioModel = new Usuario();
$usuario = $usuarioModel->obtenerPorId($usuarioId);

// Obtener estadísticas según el rol
$estadisticas = [];
if ($rolId == 1) { // Administrador
    require_once '../models/Colegio.php';
    require_once '../models/Estudiante.php';
    require_once '../models/Profesor.php';
    
    $colegioModel = new Colegio();
    $estudianteModel = new Estudiante();
    $profesorModel = new Profesor();
    
    $estadisticas = [
        'colegios' => $colegioModel->contarColegios(),
        'estudiantes' => $estudianteModel->contarEstudiantes(),
        'profesores' => $profesorModel->contarProfesores(),
        'fichas' => 0 // Se puede agregar después
    ];
} elseif ($rolId == 2) { // Profesor
    require_once '../models/Ficha.php';
    require_once '../models/Estudiante.php';
    
    $fichaModel = new Ficha();
    $estudianteModel = new Estudiante();
    
    // Obtener profesor_id del usuario actual
    require_once '../config/db.php';
    $pdo = Database::conectar();
    $stmt = $pdo->prepare("SELECT id FROM profesores WHERE usuario_id = ?");
    $stmt->execute([$usuarioId]);
    $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $fichasProfesor = [];
    if ($profesor) {
        $fichasProfesor = $fichaModel->obtenerTodasPorProfesor($profesor['id']);
    }
    $totalEstudiantes = 0;
    foreach ($fichasProfesor as $ficha) {
        $totalEstudiantes += $estudianteModel->contarPorFicha($ficha['id']);
    }
    
    $estadisticas = [
        'fichas' => count($fichasProfesor),
        'estudiantes' => $totalEstudiantes,
        'materias' => 0, // Se puede agregar después
        'experiencia' => date('Y') - 2020 // Ejemplo
    ];
}

// Determinar el título y subtítulo según el rol
$tituloRol = '';
$subtituloRol = '';
switch ($rolId) {
    case 1:
        $tituloRol = 'Administrador del Sistema';
        $subtituloRol = 'Panel de Administración Académica';
        break;
    case 2:
        $tituloRol = 'Instructor';
        $subtituloRol = 'Panel del Docente';
        break;
    case 3:
        $tituloRol = 'Estudiante';
        $subtituloRol = 'Panel del Estudiante';
        break;
}

// Generar iniciales para el avatar
$nombres = $usuario['nombres'] ?? '';
$apellidos = $usuario['apellidos'] ?? '';
$iniciales = strtoupper(substr($nombres, 0, 1) . substr($apellidos, 0, 1));
if (empty($iniciales)) $iniciales = 'US';
?>
<link rel="stylesheet" href="/css/perfil.css">

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Sistema Académico</title>

</head>
<body>
<div class="perfil-container">
    <div class="container">
        <div class="main-content">
            <div class="profile-section">
                <h2 class="profile-title"><?= htmlspecialchars($tituloRol) ?></h2>
                <p class="profile-subtitle"><?= htmlspecialchars($subtituloRol) ?></p>
                
                <!-- Avatar simple -->
                <div class="avatar-icon">
                    <?= htmlspecialchars($iniciales) ?>
                </div>
                
                <div class="profile-id">
                    <?= htmlspecialchars($usuario['nombres'] . ' ' . $usuario['apellidos']) ?><br>
                    <small><?= htmlspecialchars($usuario['correo_electronico'] ?? '') ?></small>
                </div>

                <div class="action-buttons" id="botonesPerfil">
                    <button class="action-btn btn-green" onclick="activarCambioFoto()">
                        <div class="btn-content">
                            CAMBIAR FOTO
                        </div>
                    </button>
                    <button class="action-btn btn-blue" onclick="toggleEditMode()" id="edit-btn">
                        <div class="btn-content">
                            EDITAR PERFIL
                        </div>
                    </button>
                    <button class="action-btn btn-green" onclick="saveChanges()" id="save-btn" style="display: none;">
                        <div class="btn-content">
                            GUARDAR CAMBIOS
                        </div>
                    </button>
                    <button class="action-btn btn-orange" onclick="cancelEdit()" id="cancel-btn" style="display: none;">
                        <div class="btn-content">
                            CANCELAR
                        </div>
                    </button>
                    <!-- 
                    CAMBIO REALIZADO: Agregado onclick para funcionalidad de cambiar contraseña
                    - Se conecta con la función abrirModalCambiarPassword()
                    - Abre un modal con formulario para cambiar contraseña de forma segura
                    -->
                    <button class="action-btn btn-orange" onclick="abrirModalCambiarPassword()">
                        <div class="btn-content">
                            CAMBIAR CONTRASEÑA
                        </div>
                    </button>
                </div>

                <!-- Formulario para cambiar foto (inicialmente oculto) -->
                <div id="formFoto" style="display: none; margin-top: 20px;">
                    <h4>Cambiar Foto de Perfil</h4>
                    <input type="file" id="inputFoto" accept="image/*">
                    <div style="margin-top: 10px;">
                        <button class="action-btn btn-green" onclick="subirFoto()">
                            <div class="btn-content">SUBIR FOTO</div>
                        </button>
                        <button class="action-btn btn-gray" onclick="cancelarCambioFoto()">
                            <div class="btn-content">CANCELAR</div>
                        </button>
                    </div>
                </div>
            </div>

            <div class="content-right">
                <div class="info-section">
                    <h3>
                        <svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18 18C16.35 18 14.9375 17.4125 13.7625 16.2375C12.5875 15.0625 12 13.65 12 12C12 10.35 12.5875 8.9375 13.7625 7.7625C14.9375 6.5875 16.35 6 18 6C19.65 6 21.0625 6.5875 22.2375 7.7625C23.4125 8.9375 24 10.35 24 12C24 13.65 23.4125 15.0625 22.2375 16.2375C21.0625 17.4125 19.65 18 18 18ZM6 30V25.8C6 24.95 6.21875 24.1687 6.65625 23.4562C7.09375 22.7437 7.675 22.2 8.4 21.825C9.95 21.05 11.525 20.4688 13.125 20.0812C14.725 19.6937 16.35 19.5 18 19.5C19.65 19.5 21.275 19.6937 22.875 20.0812C24.475 20.4688 26.05 21.05 27.6 21.825C28.325 22.2 28.9062 22.7437 29.3438 23.4562C29.7812 24.1687 30 24.95 30 25.8V30H6ZM9 27H27V25.8C27 25.525 26.9312 25.275 26.7938 25.05C26.6562 24.825 26.475 24.65 26.25 24.525C24.9 23.85 23.5375 23.3438 22.1625 23.0063C20.7875 22.6688 19.4 22.5 18 22.5C16.6 22.5 15.2125 22.6688 13.8375 23.0063C12.4625 23.3438 11.1 23.85 9.75 24.525C9.525 24.65 9.34375 24.825 9.20625 25.05C9.06875 25.275 9 25.525 9 25.8V27ZM18 15C18.825 15 19.5313 14.7063 20.1188 14.1187C20.7063 13.5312 21 12.825 21 12C21 11.175 20.7063 10.4688 20.1188 9.88125C19.5313 9.29375 18.825 9 18 9C17.175 9 16.4688 9.29375 15.8813 9.88125C15.2937 10.4688 15 11.175 15 12C15 12.825 15.2937 13.5312 15.8813 14.1187C16.4688 14.7063 17.175 15 18 15Z" fill="#1D1B20"/>
                        </svg>

                        Datos Personales
                    </h3>
                    <div class="info-list">
                        <div class="info-row">
                            <span class="info-label">Nombre:</span>
                            <span class="info-value"><?= htmlspecialchars(($usuario['nombres'] ?? '') . ' ' . ($usuario['apellidos'] ?? '')) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Documento:</span>
                            <span class="info-value"><?= htmlspecialchars(($usuario['tipo_documento'] ?? 'CC') . ' ' . ($usuario['numero_documento'] ?? '')) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Fecha Nacimiento:</span>
                            <span class="info-value"><?= htmlspecialchars($usuario['fecha_nacimiento'] ?? 'No registrada') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Género:</span>
                            <span class="info-value"><?php 
                                $generos = ['M' => 'Masculino', 'F' => 'Femenino', 'O' => 'Otro'];
                                echo htmlspecialchars($generos[$usuario['genero'] ?? 'M'] ?? 'No especificado');
                            ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Estado:</span>
                            <span class="status-badge">ACTIVO</span>
                        </div>
                    </div>
                </div>

                <div class="info-section">
                    <h3>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <g clip-path="url(#clip0_101_1420)">
                                <path d="M21.9994 16.92V19.92C22.0006 20.1985 21.9435 20.4741 21.832 20.7293C21.7204 20.9845 21.5567 21.2136 21.3515 21.4018C21.1463 21.5901 20.904 21.7335 20.6402 21.8227C20.3764 21.9119 20.0968 21.945 19.8194 21.92C16.7423 21.5856 13.7864 20.5341 11.1894 18.85C8.77327 17.3146 6.72478 15.2661 5.18945 12.85C3.49942 10.2412 2.44769 7.27097 2.11944 4.17997C2.09446 3.90344 2.12732 3.62474 2.21595 3.3616C2.30457 3.09846 2.44702 2.85666 2.63421 2.6516C2.82141 2.44653 3.04925 2.28268 3.30324 2.1705C3.55722 2.05831 3.83179 2.00024 4.10945 1.99997H7.10945C7.59475 1.9952 8.06524 2.16705 8.43321 2.48351C8.80118 2.79996 9.04152 3.23942 9.10944 3.71997C9.23607 4.68004 9.47089 5.6227 9.80945 6.52997C9.94399 6.8879 9.97311 7.27689 9.89335 7.65086C9.8136 8.02482 9.62831 8.36809 9.35944 8.63998L8.08945 9.90997C9.513 12.4135 11.5859 14.4864 14.0894 15.91L15.3594 14.64C15.6313 14.3711 15.9746 14.1858 16.3486 14.1061C16.7225 14.0263 17.1115 14.0554 17.4694 14.19C18.3767 14.5285 19.3194 14.7634 20.2794 14.89C20.7652 14.9585 21.2088 15.2032 21.526 15.5775C21.8431 15.9518 22.0116 16.4296 21.9994 16.92Z" stroke="#1E1E1E" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </g>
                            <defs>
                        <clipPath id="clip0_101_1420">
                            <rect width="24" height="24" fill="white"/>
                        </clipPath>
                            </defs>
                        </svg>

                        Datos de Contacto
                    </h3>
                    <div class="info-list">
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value editable-field" data-field="correo_electronico">
                                <span class="display-value"><?= htmlspecialchars($usuario['correo_electronico'] ?? 'No registrado') ?></span>
                                <input type="email" class="edit-input" value="<?= htmlspecialchars($usuario['correo_electronico'] ?? '') ?>" style="display: none;">
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Teléfono:</span>
                            <span class="info-value editable-field" data-field="telefono">
                                <span class="display-value"><?= htmlspecialchars($usuario['telefono'] ?? 'No registrado') ?></span>
                                <input type="tel" class="edit-input" value="<?= htmlspecialchars($usuario['telefono'] ?? '') ?>" style="display: none;">
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Correo Institucional:</span>
                            <span class="info-value editable-field" data-field="correo_institucional">
                                <span class="display-value"><?= htmlspecialchars($usuario['correo_institucional'] ?? 'No asignado') ?></span>
                                <input type="email" class="edit-input" value="<?= htmlspecialchars($usuario['correo_institucional'] ?? '') ?>" style="display: none;">
                            </span>
                        </div>
                        <div class="info-row password-row">
                            <span class="info-label">Contraseña:</span>
                            <span class="info-value">
                                *****
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">&nbsp;</span>
                            <span class="info-value">&nbsp;</span>
                        </div>
                        
                        <?php if ($rolId == 2): ?>
                            <?php endif; ?>
                        <?php if ($rolId == 3): ?>
                        <div class="info-row">
                            <span class="info-label">Acudiente:</span>
                            <span class="info-value"><?= htmlspecialchars($usuario['acudiente'] ?? 'No registrado') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Tel. Acudiente:</span>
                            <span class="info-value"><?= htmlspecialchars($usuario['telefono_acudiente'] ?? 'No registrado') ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="academic-section">
                    <h3>
                        <svg width="22" height="21" viewBox="0 0 22 21" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 8px; vertical-align: middle;">
                            <rect width="22" height="5.5" rx="1" transform="matrix(1 0 0 -1 0 5.5)" fill="black"/>
                            <rect width="22" height="5.5" rx="1" transform="matrix(1 0 0 -1 0 12.8333)" fill="black"/>
                            <rect width="22" height="5.5" rx="1" transform="matrix(1 0 0 -1 0 20.1667)" fill="black"/>
                        </svg>
                        <?= $rolId == 1 ? 'Estadísticas del Sistema' : ($rolId == 2 ? 'Información Académica' : 'Datos Académicos') ?>
                    </h3>
                    <div class="academic-stats">
                        <?php if ($rolId == 1): // Administrador ?>
                        <div class="stat-item">
                            <div class="stat-number"><?= $estadisticas['colegios'] ?? 0 ?></div>
                            <div class="stat-label">Colegios Registrados</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= $estadisticas['estudiantes'] ?? 0 ?></div>
                            <div class="stat-label">Estudiantes Activos</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= $estadisticas['profesores'] ?? 0 ?></div>
                            <div class="stat-label">Profesores Registrados</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= date('Y') - 2020 ?></div>
                            <div class="stat-label">Años del Sistema</div>
                        </div>
                        <?php elseif ($rolId == 2): // Profesor ?>
                        <div class="stat-item">
                            <div class="stat-number"><?= $estadisticas['fichas'] ?? 0 ?></div>
                            <div class="stat-label">Fichas Asignadas</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= $estadisticas['estudiantes'] ?? 0 ?></div>
                            <div class="stat-label">Estudiantes a Cargo</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= $estadisticas['experiencia'] ?? 0 ?></div>
                            <div class="stat-label">Años Experiencia</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">4.8</div>
                            <div class="stat-label">Calificación Promedio</div>
                        </div>
                        <?php else: // Estudiante ?>
                        <div class="stat-item">
                            <div class="stat-number"><?= htmlspecialchars($usuario['nombre_colegio'] ?? 'N/A') ?></div>
                            <div class="stat-label">Colegio</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= htmlspecialchars($usuario['numero_ficha'] ?? 'N/A') ?></div>
                            <div class="stat-label">Número de Ficha</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= htmlspecialchars($usuario['jornada'] ?? 'N/A') ?></div>
                            <div class="stat-label">Jornada</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= htmlspecialchars($usuario['estado_estudiante'] ?? 'Activo') ?></div>
                            <div class="stat-label">Estado</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="recent-section">
                    <h3>Información Reciente</h3>
                    <div class="recent-grid">
                        <div class="recent-item">
                            <h4>Último Acceso</h4>
                            <p class="recent-value"><?= date('d/m/Y H:i') ?></p>
                        </div>
                        <?php if ($rolId == 1): ?>
                        <div class="recent-item">
                            <h4>Rol del Sistema</h4>
                            <p class="recent-value">Administrador Principal</p>
                        </div>
                        <div class="recent-item">
                            <h4>Permisos</h4>
                            <p class="recent-value">Acceso Completo</p>
                        </div>
                        <?php elseif ($rolId == 2): ?>
                        <div class="recent-item">
                            <h4>Tipo de Usuario</h4>
                            <p class="recent-value">Instructor</p>
                        </div>
                        <div class="recent-item">
                            <h4>Área de Trabajo</h4>
                            <p class="recent-value"><?= htmlspecialchars($usuario['nombre_colegio'] ?? 'No asignado') ?></p>
                        </div>
                        <?php else: ?>
                        <div class="recent-item">
                            <h4>Ficha Académica</h4>
                            <p class="recent-value"><?= htmlspecialchars($usuario['nombre_ficha'] ?? 'No asignada') ?></p>
                        </div>
                        <div class="recent-item">
                            <h4>Estado Académico</h4>
                            <p class="recent-value"><?= htmlspecialchars($usuario['estado_estudiante'] ?? 'Activo') ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 
MODAL AGREGADO: Formulario para cambiar contraseña
-->
<div id="modalCambiarPassword" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 50px; padding: 32px; box-shadow: 0 8px 32px rgba(0,0,0,0.25); border: 1px solid #e5e7eb; width: 90%; max-width: 800px;">
        <h3 style="font-size: 18px; color: #000000; margin-bottom: 24px; font-weight: 700; text-align: left; line-height: 1.2;">
            Cambiar<br>Contraseña
        </h3>
        <div style="border-bottom: 1px solid #94a3b8; margin: 0 -32px 24px -32px;"></div>
        <form id="formCambiarPassword">
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <!-- Campo: Contraseña Actual -->
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 32px; margin: 0 -32px; border-bottom: 1px solid #94a3b8;">
                    <span style="font-size: 18px; color: #000000; font-weight: 600; flex: 0 0 auto; min-width: 180px;">Contraseña Actual:</span>
                    <input type="password" id="password_actual" name="password_actual" required 
                           style="font-size: 15px; color: #454545; font-weight: 500; border: none; outline: none; background: transparent; width: 100%; text-align: right;">
                </div>
                <!-- Campo: Contraseña Nueva -->
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 32px; margin: 0 -32px; border-bottom: 1px solid #94a3b8;">
                    <span style="font-size: 18px; color: #000000; font-weight: 600; flex: 0 0 auto; min-width: 180px;">Contraseña Nueva:</span>
                    <input type="password" id="password_nueva" name="password_nueva" required 
                           style="font-size: 15px; color: #454545; font-weight: 500; border: none; outline: none; background: transparent; width: 100%; text-align: right;">
                </div>
                <!-- Campo: Confirmar Contraseña -->
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 32px; margin: 0 -32px; border-bottom: 1px solid #94a3b8;">
                    <span style="font-size: 18px; color: #000000; font-weight: 600; flex: 0 0 auto; min-width: 180px;">Confirmar Contraseña:</span>
                    <input type="password" id="password_confirmar" name="password_confirmar" required 
                           style="font-size: 15px; color: #454545; font-weight: 500; border: none; outline: none; background: transparent; width: 100%; text-align: right;">
                </div>
            </div>
            <!-- Botones de acción del modal -->
            <div style="text-align: center; margin-top: 30px; display: flex; gap: 15px; justify-content: center;">
                <button type="submit" style="background: #39A900; color: white; padding: 15px 40px; border: none; border-radius: 25px; font-weight: 700; cursor: pointer; font-size: 16px; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);">
                    Cambiar
                </button>
                <button type="button" onclick="cerrarModalCambiarPassword()" style="background: #6c757d; color: white; padding: 15px 40px; border: none; border-radius: 25px; font-weight: 700; cursor: pointer; font-size: 16px; box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
<!-- Scripts para funcionalidad del perfil -->
<script>
  // VARIABLE AGREGADA: Token CSRF para peticiones AJAX seguras
  // Soluciona el error "Token CSRF inválido" que ocurría en las peticiones
  // Se genera desde PHP usando la función csrf_token() del helper auth.php
  const csrfToken = '<?= csrf_token() ?>';
  let editMode = false;
  let originalValues = {};

  function activarCambioFoto() {
    document.getElementById('botonesPerfil').style.display = 'none';
    document.getElementById('formFoto').style.display = 'block';
  }

  function cancelarCambioFoto() {
    document.getElementById('botonesPerfil').style.display = 'block';
    document.getElementById('formFoto').style.display = 'none';
    document.getElementById('inputFoto').value = '';
  }

  function subirFoto() {
    const input = document.getElementById('inputFoto');
    const file = input.files[0];
    
    if (!file) {
      alert('Por favor selecciona una imagen');
      return;
    }
    
    // Aquí iría la lógica para subir la foto
    // Por ahora solo mostramos un mensaje
    alert('Funcionalidad de subir foto no implementada');
  }

  function toggleEditMode() {
    editMode = !editMode;
    const editableFields = document.querySelectorAll('.editable-field');
    const editBtn = document.getElementById('edit-btn');
    const saveBtn = document.getElementById('save-btn');
    const cancelBtn = document.getElementById('cancel-btn');

    if (editMode) {
      // Guardar valores originales
      editableFields.forEach(field => {
        const fieldName = field.dataset.field;
        const displayValue = field.querySelector('.display-value');
        originalValues[fieldName] = displayValue.textContent;
        
        // Mostrar input, ocultar display
        displayValue.style.display = 'none';
        field.querySelector('.edit-input').style.display = 'inline-block';
      });

      // Cambiar botones
      editBtn.style.display = 'none';
      saveBtn.style.display = 'block';
      cancelBtn.style.display = 'block';
    } else {
      cancelEdit();
    }
  }

  function cancelEdit() {
    editMode = false;
    const editableFields = document.querySelectorAll('.editable-field');
    const editBtn = document.getElementById('edit-btn');
    const saveBtn = document.getElementById('save-btn');
    const cancelBtn = document.getElementById('cancel-btn');

    editableFields.forEach(field => {
      const displayValue = field.querySelector('.display-value');
      const editInput = field.querySelector('.edit-input');
      
      // Restaurar valores originales
      editInput.value = originalValues[field.dataset.field] || '';
      
      // Mostrar display, ocultar input
      displayValue.style.display = 'inline';
      editInput.style.display = 'none';
    });

    // Cambiar botones
    editBtn.style.display = 'block';
    saveBtn.style.display = 'none';
    cancelBtn.style.display = 'none';
  }

  function saveChanges() {
    const editableFields = document.querySelectorAll('.editable-field');
    const formData = new FormData();
    formData.append('action', 'update_contact');
    // CAMBIO AGREGADO: Incluir token CSRF en la petición AJAX
    // Soluciona el error 400 (Bad Request) que ocurría por falta de validación CSRF
    formData.append('csrf_token', csrfToken);

    editableFields.forEach(field => {
      const fieldName = field.dataset.field;
      const newValue = field.querySelector('.edit-input').value;
      formData.append(fieldName, newValue);
    });

    fetch('/?page=actualizar_perfil', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Actualizar valores mostrados
        editableFields.forEach(field => {
          const fieldName = field.dataset.field;
          const newValue = field.querySelector('.edit-input').value;
          const displayValue = field.querySelector('.display-value');
          
          displayValue.textContent = newValue || 'No registrado';
          displayValue.style.display = 'inline';
          field.querySelector('.edit-input').style.display = 'none';
        });

        // Resetear modo edición
        editMode = false;
        document.getElementById('edit-btn').style.display = 'block';
        document.getElementById('save-btn').style.display = 'none';
        document.getElementById('cancel-btn').style.display = 'none';

        alert('Datos actualizados correctamente');
      } else {
        alert('Error al actualizar los datos, Si no agenerado ningun cambio seleccione cancelar');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error al actualizar los datos, Si no agenerado ningun cambio seleccione cancelar');
    });
  }

  function cancelarCambioFoto() {
    document.getElementById('botonesPerfil').style.display = 'flex';
    document.getElementById('formFoto').style.display = 'none';
  }

  /**
   * FUNCIONES AGREGADAS: Manejo del modal de cambiar contraseña
   */
  function abrirModalCambiarPassword() {
    document.getElementById('modalCambiarPassword').style.display = 'flex';
    document.getElementById('password_actual').focus();
  }

  // Función para cerrar el modal y limpiar el formulario
  function cerrarModalCambiarPassword() {
    document.getElementById('modalCambiarPassword').style.display = 'none';
    document.getElementById('formCambiarPassword').reset();
  }

  /**
   * EVENT LISTENER AGREGADO: Manejo del envío del formulario de cambiar contraseña.
   */
  document.getElementById('formCambiarPassword').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const passwordActual = document.getElementById('password_actual').value;
    const passwordNueva = document.getElementById('password_nueva').value;
    const passwordConfirmar = document.getElementById('password_confirmar').value;
    
    // VALIDACIÓN 1: Verificar que las contraseñas nuevas coincidan
    if (passwordNueva !== passwordConfirmar) {
      alert('Las contraseñas nuevas no coinciden');
      return;
    }
    
    // VALIDACIÓN 2: Verificar longitud mínima de seguridad
    if (passwordNueva.length < 6) {
      alert('La contraseña nueva debe tener al menos 6 caracteres');
      return;
    }
    
    // PREPARAR DATOS: Incluir token CSRF para validación del servidor
    const formData = new FormData();
    formData.append('action', 'cambiar_password');
    // CAMBIO AGREGADO: Token CSRF para evitar ataques CSRF
    formData.append('csrf_token', csrfToken);
    formData.append('password_actual', passwordActual);
    formData.append('password_nueva', passwordNueva);
    
    // PETICIÓN AJAX: Envío seguro al backend
    fetch('/?page=actualizar_perfil', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert('Contraseña cambiada exitosamente');
        cerrarModalCambiarPassword();
      } else {
        alert(data.message || 'Error al cambiar la contraseña');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error al cambiar la contraseña');
    });
  });
</script>

<?php include '../views/Componentes/footer.php'; ?>
