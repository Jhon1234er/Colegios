<?php
require_once __DIR__ . '/../Componentes/encabezado.php';
?>

<link rel="stylesheet" href="/css/Profesor/crear.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<div class="formulario-registro">
  <h2>Registro de Facilitador</h2>
  <form action="/?page=profesores&action=guardar" method="POST" id="registroProfesorForm">
      <?= csrf_input(); ?>
    <div class="form-grid">
      <!-- Columna izquierda -->
      <div class="columna">
        <label>Nombres</label>
        <input type="text" name="nombres" class="form-nombre" required>

        <label>Apellidos</label>
        <input type="text" name="apellidos" class="form-apellido" required>

        <label>Tipo de Documento</label>
        <select name="tipo_documento" class="form-tipo" required>
          <option value="">Seleccione...</option>
          <option value="CC">Cédula de Ciudadanía</option>
          <option value="CE">Cédula de Extranjería</option>
        </select>

        <label>Número de Documento</label>
        <input type="text" name="numero_documento" class="form-documento" required>

        <label>Correo Electrónico</label>
        <input type="email" name="correo_electronico" class="form-personal" required>

        <label>Correo Electrónico Institucional</label>
        <input type="email" name="correo_institucional" class="form-institucional" required>

        <label>Teléfono</label>
        <input type="text" name="telefono" class="form-numero" required>
      </div>

      <!-- Columna derecha -->
      <div class="columna">
        <label>Fecha de Nacimiento</label>
        <input type="text" name="fecha_nacimiento" id="fecha_nacimiento" class="form-nacimiento" required>

        <label>RH</label>
        <select name="rh" class="form-select-rh" required>
          <option value="">Seleccione...</option>
          <option value="A+">A+</option>
          <option value="A-">A-</option>
          <option value="B+">B+</option>
          <option value="B-">B-</option>
          <option value="O+">O+</option>
          <option value="O-">O-</option>
          <option value="AB+">AB+</option>
          <option value="AB-">AB-</option>
        </select>

        <label>Género</label>
        <select name="genero" class="form-selec-genero" required>
          <option value="">Seleccione...</option>
          <option value="M">Masculino</option>
          <option value="F">Femenino</option>
          <option value="Otro">Otro</option>
        </select>

        <label>Título Académico</label>
        <input type="text" name="titulo_academico" class="form-control-titulo" required>

        <label>Especialidad</label>
        <input type="text" name="especialidad" class="form-control-especialidad" required>

        <label>Tipo de Contrato</label>
        <select name="tip_contrato" class="form-select-contrato" required>
          <option value="">Seleccione...</option>
          <option value="contratista">Facilitador</option>
          <option value="instructor">Instructor</option>
        </select>

        <label>Contraseña</label>
        <input type="password" name="password" class="form-contraseña" required>
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
