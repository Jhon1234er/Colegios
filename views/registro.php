<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Registro de Profesor - Sistem School</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/login_sena.css" />
  <link rel="stylesheet" href="/css/registro.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
</head>
<body>

  <!-- Panel de bienvenida (igual al login, con mensaje Únete) -->
  <div class="welcome-panel">
    <div class="welcome-content">
      <img src="/icons/logo_sena.jpeg" alt="Logo SENA" class="sena-logo">
      <div class="welcome-text">
        <h1>¡Únete!</h1>
        <h2 id="welcomeSubtitle">Regístrate en <strong>System School</strong></h2>
        <p id="welcomeDescription">Crea tu cuenta y comienza tu experiencia SENA</p>
      </div>
    </div>
  </div>

  <!-- Panel Derecho - Formulario de Registro -->
  <div class="login-panel">
    <div class="form-container profesor-crear">
      <div class="login-form-container" style="display:block;">
        <h2 class="form-title">Crear Cuenta</h2>

        <?php
          $isPublic = (($_GET['page'] ?? '') === 'registro_profesor');
          $formAction = $isPublic ? '/?page=registro_profesor_guardar' : '/?page=profesores&action=guardar';
        ?>
        <form action="<?= htmlspecialchars($formAction) ?>" method="POST" id="registroProfesorForm">
          <?= csrf_input(); ?>

          <div class="form-grid">
            <!-- Columna izquierda -->
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

              <label>Fecha de Nacimiento</label>
              <input type="text" id="fecha_nacimiento" name="fecha_nacimiento" placeholder="dd/mm/aaaa" autocomplete="off">

              <label>Género</label>
              <select name="genero" id="genero_sel">
                <option value="">Seleccione...</option>
                <option value="Masculino">Masculino</option>
                <option value="Femenino">Femenino</option>
                <option value="Otro">Otro</option>
              </select>
              <input type="text" name="genero_otro" id="genero_otro" placeholder="Especifique" style="display:none; margin-top:6px;">

              <label>Municipio</label>
              <input type="text" name="municipio">

              <label>Dirección</label>
              <input type="text" name="direccion">

            </div>

            <!-- Columna derecha -->
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

              <label>RH</label>
              <select name="rh">
                <option value="">Seleccione...</option>
                <option>O+</option><option>O-</option>
                <option>A+</option><option>A-</option>
                <option>B+</option><option>B-</option>
                <option>AB+</option><option>AB-</option>
              </select>

              <label>EPS</label>
              <input type="text" name="eps" placeholder="Nombre de su EPS">

              <label>Estrato</label>
              <select name="estrato">
                <option value="">Seleccione...</option>
                <option>0</option><option>1</option><option>2</option><option>3</option>
                <option>4</option><option>5</option><option>6</option>
              </select>

              <label>Barrio</label>
              <input type="text" name="barrio">
            </div>
          </div>

          <button type="submit" class="form-button">Crear</button>
          <div class="form-links" style="margin-top:12px; text-align:center;">
            ¿Ya tienes cuenta? <a href="/">Inicia sesión</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
  <script src="/js/Facilitador/crearP.js"></script>
  <script src="/js/login.js"></script>
  <script>
    // Mostrar campo genero_otro cuando aplica
    (function(){
      const sel = document.getElementById('genero_sel');
      const otro = document.getElementById('genero_otro');
      if (!sel || !otro) return;
      function toggle(){ otro.style.display = (sel.value === 'Otro') ? 'block' : 'none'; if (sel.value !== 'Otro') otro.value = ''; }
      sel.addEventListener('change', toggle); toggle();
    })();
  </script>
</body>
</html>
