<?php 
include '../views/Componentes/encabezado.php'; 

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
                
                <div class="profile-avatar">
                    <div class="avatar-icon"><?= htmlspecialchars($iniciales) ?></div>
                </div>
                
                <div class="profile-id">
                    <?= htmlspecialchars($usuario['nombres'] . ' ' . $usuario['apellidos']) ?><br>
                    <small><?= htmlspecialchars($usuario['correo_electronico'] ?? '') ?></small>
                </div>

                <div class="action-buttons">
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
                    <button class="action-btn btn-orange">
                        <div class="btn-content">
                            CAMBIAR CONTRASEÑA
                        </div>
                    </button>
                </div>
            </div>

            <div class="content-right">
                <div class="info-section">
                    <h3>👤 Datos Personales</h3>
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
                    <h3>📞 Datos de Contacto</h3>
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
                        <div class="info-row">
                            <span class="info-label">Rol:</span>
                            <span class="info-value"><?= htmlspecialchars($tituloRol) ?></span>
                        </div>
                        <?php if ($rolId == 2 && !empty($usuario['profesor_colegio_id'])): ?>
                        <div class="info-row">
                            <span class="info-label">Ficha Asignada:</span>
                            <span class="info-value"><?= htmlspecialchars($usuario['nombre_colegio'] ?? 'No asignado') ?></span>
                        </div>
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
                    <h3><?= $rolId == 1 ? ' Estadísticas del Sistema' : ($rolId == 2 ? ' Información Académica' : ' Datos Académicos') ?></h3>
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
</body>
</html>
<!-- Scripts para funcionalidad del perfil -->
<script>
  let editMode = false;
  let originalValues = {};

  function activarCambioFoto() {
    document.getElementById('botonesPerfil').style.display = 'none';
    document.getElementById('formFoto').style.display = 'block';
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
</script>

<?php include '../views/Componentes/footer.php'; ?>
