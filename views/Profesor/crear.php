<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
?>

<link rel="stylesheet" href="/css/Profesor/crear.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<div class="formulario-registro">
  <h2>Registro de Facilitador</h2>
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

        <label>Correo Electrónico Institucional</label>
        <input type="email" name="correo_institucional" class="form-institucional" required>

        <label>Especialidad</label>
        <input type="text" name="especialidad" class="form-control-especialidad" required>
        

        <label>Tipo de Contrato</label>
        <select name="tip_contrato" class="form-select-contrato" required>
          <option value="">Seleccione...</option>
          <option value="contratista">Facilitador</option>
          <option value="instructor">Instructor</option>
        </select>



        <!-- Contraseña se generará automáticamente por el sistema -->
      </div>
    </div>

    <button type="submit" class="btn-registrar">Registrar Profesor</button>
  </form>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="/js/crearP.js"></script>

<?php include __DIR__ . '/../Componentes/footer.php'; ?>
