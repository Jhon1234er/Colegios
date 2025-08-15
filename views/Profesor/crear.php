<?php
require_once __DIR__ . '/../../models/Colegio.php';
$colegioModel = new Colegio();
$colegios = $colegioModel->obtenerTodos();
require_once __DIR__ . '/../Componentes/encabezado.php';
?>

<link rel="stylesheet" href="/css/Profesor/crear.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<div class="formulario-registro">
  <div class="container">
  <h2>Registro de Facilitador</h2>
  <form action="/?page=profesores&action=guardar" method="POST" id="registroProfesorForm">
    <div class="row">
      <!-- Columna izquierda -->
      <div class="col-md-6 mb-3">
        <label>Nombres</label>
        <input type="text" name="nombres" class="form-control" required>

        <label>Apellidos</label>
        <input type="text" name="apellidos" class="form-control" required>

        <label>Tipo de Documento</label>
        <select name="tipo_documento" class="form-select" required>
          <option value="">Seleccione...</option>
          <option value="CC">Cédula de Ciudadanía</option>
          <option value="CE">Cédula de Extranjería</option>
        </select>

        <label>Número de Documento</label>
        <input type="text" name="numero_documento" class="form-control" required>

        <label>Correo Electrónico</label>
        <input type="email" name="correo_electronico" class="form-control" required>

        <label>Teléfono</label>
        <input type="text" name="telefono" class="form-control" required>

        <label>Colegio</label>
        <select id="colegio_id" name="colegio_id" class="form-select" required>
          <option value="">Seleccione colegio...</option>
          <?php foreach ($colegios as $colegio): ?>
            <option value="<?= $colegio['id'] ?>"><?= $colegio['nombre'] ?></option>
          <?php endforeach ?>
        </select>

        <label>Materia que imparte</label>
        <select id="materias" name="materias[]" class="form-select" required>
          <option value="">Seleccione un colegio primero</option>
        </select>

        <label for="ficha_id">Fichas</label>
        <select name="fichas" id="ficha_id" class="form-select" multiple required>
          <option value="">Seleccione un colegio primero</option>
        </select>
      </div>

      <!-- Columna derecha -->
      <div class="col-md-6">
        <label>Correo Electrónico Institucional</label>
        <input type="email" name="correo_institucional" class="form-control" required>

        <label>Fecha de Nacimiento</label>
        <input type="text" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control" required>

        <label>RH</label>
        <select name="rh" class="form-select" required>
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
        <select name="genero" class="form-select" required>
          <option value="">Seleccione...</option>
          <option value="M">Masculino</option>
          <option value="F">Femenino</option>
          <option value="Otro">Otro</option>
        </select>

        <label>Título Académico</label>
        <input type="text" name="titulo_academico" class="form-control" required>

        <label>Especialidad</label>
        <input type="text" name="especialidad" class="form-control" required>

        <label>Tipo de Contrato</label>
        <select name="tip_contrato" class="form-select" required>
          <option value="">Seleccione...</option>
          <option value="contratista">Contratista</option>
          <option value="planta">Planta</option>
        </select>

        <label>Contraseña</label>
        <input type="password" name="password" class="form-control" required>
      </div>
    </div>

    <button type="submit" class="btn btn-success mt-3">Registrar Profesor</button>
  </form>
  </div>
</div>
<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="/js/crearP.js"></script>
<script src="/js/fichas_por_colegio.js"></script>

<?php include __DIR__ . '/../Componentes/footer.php'; ?>
