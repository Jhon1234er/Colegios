<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
<link rel="stylesheet" href="/css/Profesor/crear.css?v=4">

<div class="profesor-crear">
  <h2 class="form-title">Registro de Facilitador</h2>
  <?php
    $isPublic = (($_GET['page'] ?? '') === 'registro_profesor');
    $formAction = $isPublic ? '/?page=registro_profesor_guardar' : '/?page=profesores&action=guardar';
  ?>
  <form action="<?= htmlspecialchars($formAction) ?>" method="POST" id="registroProfesorForm">
      <?= csrf_input(); ?>
    <div class="form-grid">
      <!-- Columna izquierda (orden solicitado) -->
      <div class="columna">
        <label>Nombres</label>
        <input type="text" name="nombres" class="form-nombre" required>

        <label>Tipo de Documento</label>
        <select name="tipo_documento" class="form-tipo" required>
          <option value="">Seleccione...</option>
          <option value="CC">Cédula de Ciudadanía</option>
          <option value="CE">Cédula de Extranjería</option>
        </select>

        <div class="columna">
        <label for="fecha_nacimiento">Fecha de nacimiento</label>
        <div class="date-wrap">
          <input id="fecha_nacimiento" name="fecha_nacimiento" type="text" placeholder="dd/mm/aaaa" />
          <!-- Icono calendario (usa currentColor) -->
          <svg class="date-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
            <rect x="3" y="5" width="18" height="16" rx="4" ry="4" fill="none" stroke="currentColor" stroke-width="2"/>
            <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
            <line x1="8" y1="3" x2="8" y2="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <line x1="16" y1="3" x2="16" y2="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
      </div>

        <label>Correo Electrónico</label>
        <input type="email" name="correo_electronico" class="form-personal" required>

        <label>Título Académico</label>
        <input type="text" name="titulo_academico" class="form-control-titulo" required>

        <label>Teléfono</label>
        <input type="text" name="telefono" class="form-numero" required>
      </div>

      <!-- Columna derecha (orden solicitado) -->
      <div class="columna">
        <label>Apellidos</label>
        <input type="text" name="apellidos" class="form-apellido" required>

        <label>Número de Documento</label>
        <input type="text" name="numero_documento" class="form-documento" required>

        <label>RH</label>
        <select name="rh" class="form-rh" required>
          <option value="">Seleccione...</option>
          <option>A+</option><option>A-</option><option>B+</option><option>B-</option>
          <option>O+</option><option>O-</option><option>AB+</option><option>AB-</option>
        </select>

        <label>Correo Electrónico Institucional</label>
        <input type="email" name="correo_institucional" class="form-institucional" required>

        <label>Especialidad</label>
        <input type="text" name="especialidad" class="form-control-especialidad" required>

        <label>Cargo</label>
        <select name="cargo" class="form-select-contrato" required>
          <option value="">Seleccione...</option>
          <option value="facilitador">Facilitador</option>
          <option value="instructor">Instructor</option>
        </select>
      </div>
      
    </div>
<button type="submit" class="btn-registrar">Registrar Profesor</button>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="/js/crearP.js?v=2"></script>

<?php include __DIR__ . '/../Componentes/footer.php'; ?>
