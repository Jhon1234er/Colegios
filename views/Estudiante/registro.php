<?php
// 📌 Formulario público de inscripción de estudiante
// No pedimos login aquí

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../models/Colegio.php';
require_once __DIR__ . '/../../models/Ficha.php';

// Iniciar sesión solo para CSRF (sin requerir login)
start_secure_session();

$colegioModel = new Colegio();
$colegios = $colegioModel->obtenerTodos();

// ------------------------------
// 📌 Captura de ficha_id por token
// ------------------------------
$ficha_id = null;

if (isset($_GET['token'])) {
    $fichaModel = new Ficha();
    $ficha = $fichaModel->buscarPorToken($_GET['token']);
    if ($ficha) {
        $ficha_id = $ficha['id'];
    } else {
        die("⚠️ Token inválido o vencido");
    }
} else {
    die("⚠️ No se recibió token de acceso");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro de Aprendiz</title>
  <link rel="stylesheet" href="/css/Estudiante/crear.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
</head>
<body>
  <div class="container">
    <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
        <!-- Vista de confirmación final -->
        <div class="registro-completado">
            <div class="success-icon"></div>
            <h2>¡Registro Completado!</h2>
            <p class="mensaje-exito">Tu inscripción ha sido procesada exitosamente.</p>
            <div class="detalles-registro">
                <h3>¿Qué sigue ahora?</h3>
                <ul>
                    <li>"¡Gracias por tu tiempo! Tu registro se ha completado con éxito."</li>
                    <li>Ahora puedes cerrar esta ventana.</li>
                </ul>
            </div>
            <div class="acciones-finales">
                <button onclick="cerrarVentana()" class="btn btn-primary">Cerrar</button>
            </div>
        </div>
    <?php else: ?>
        <h2>Registro de Aprendiz</h2>

    <!-- Stepper visual -->
    <ol class="stepper flex items-center w-full mb-4 sm:mb-5">
      <li class="active step"><div class="step-circle">1</div><span class="step-label">Aprendiz</span></li>
      <li class="step"><div class="step-circle">2</div><span class="step-label">Acudiente</span></li>
      <li class="step"><div class="step-circle">3</div><span class="step-label">Colegio</span></li>
    </ol>

    <!-- 📌 IMPORTANTE: acción al routing público -->
    <form id="formEstudiante" 
        action="/?page=registro_estudiante&token=<?= urlencode($_GET['token']) ?>" 
        method="POST">
    <?= csrf_input(); ?>

    <!-- 📌 Campo oculto con el ID de la ficha -->
    <input type="hidden" name="ficha_id" value="<?= htmlspecialchars($ficha_id) ?>">

      <!-- Paso 1: Estudiante -->
      <div class="form-step active">
        <h5>Información del Estudiante</h5>
        <div class="row">
          <div class="col-md-6"><label>Nombres</label><input type="text" name="nombres" required></div>
          <div class="col-md-6"><label>Apellidos</label><input type="text" name="apellidos" required></div>
          <div class="col-md-6">
            <label>Tipo de Documento</label>
            <select name="tipo_documento" required>
              <option value="">Seleccione</option>
              <option value="TI">TI</option>
              <option value="CC">CC</option>
            </select>
          </div>
          <div class="col-md-6"><label>Número de Documento</label><input type="text" name="numero_documento" required></div>
          <div class="col-md-6"><label>Fecha de Nacimiento</label><input type="text" name="fecha_nacimiento" id="fecha_nacimiento" required></div>
          <div class="col-md-6"><label>Correo Electrónico</label><input type="email" name="correo_electronico" required></div>
          <div class="col-md-6"><label>Teléfono</label><input type="text" name="telefono"></div>
          <div class="col-md-6"><label>Dirección</label><input type="text" name="direccion"></div>
          <div class="col-md-6">
            <label>Género</label>
            <select name="genero" required>
              <option value="">Seleccione</option>
              <option value="M">Masculino</option>
              <option value="F">Femenino</option>
            </select>
          </div>
          <!-- 🔑 La contraseña aquí se ignora, se usa el documento como clave -->
          <div class="col-md-6"><label>Contraseña</label><input type="password" name="password" required></div>
        </div>
        <div class="form-navigation">
          <button type="button" class="next-btn">Siguiente</button>
        </div>
      </div>

      <!-- Paso 2: Acudiente -->
      <div class="form-step">
        <h5>Información del Acudiente</h5>
        <div class="row">
          <div class="col-md-6"><label>Nombre completo del Acudiente</label><input type="text" name="nombre_completo_acudiente" required></div>
          <div class="col-md-6">
            <label>Tipo de Documento</label>
            <select name="tipo_documento_acudiente" required>
              <option value="">Seleccione</option>
              <option value="CC">CC</option>
              <option value="CE">CE</option>
            </select>
          </div>
          <div class="col-md-6"><label>Número de Documento</label><input type="text" name="numero_documento_acudiente" required></div>
          <div class="col-md-6"><label>Teléfono</label><input type="text" name="telefono_acudiente" required></div>
          <div class="col-md-6">
            <label>Parentesco</label>
            <select name="parentesco" id="parentesco" required>
              <option value="">Seleccione</option>
              <option value="Padre">Padre</option>
              <option value="Madre">Madre</option>
              <option value="Hermano/a">Hermano/a</option>
              <option value="Abuelo/a">Abuelo/a</option>
              <option value="Tío/a">Tío/a</option>
              <option value="Primo/a">Primo/a</option>
              <option value="Otro">Otro</option>
            </select>
            <input type="text" name="parentesco_otro" id="parentesco_otro" placeholder="Especifique parentesco" style="display:none;">
          </div>
          <div class="col-md-6">
            <label>Ocupación</label>
            <select name="ocupacion" id="ocupacion" required>
              <option value="">Seleccione</option>
              <option value="Empleado">Empleado(a)</option>
              <option value="Independiente">Independiente</option>
              <option value="Comerciante">Comerciante</option>
              <option value="Ama de casa">Ama de casa</option>
              <option value="Estudiante">Estudiante</option>
              <option value="Docente">Docente</option>
              <option value="Profesional">Profesional</option>
              <option value="Obrero">Obrero(a)</option>
              <option value="Conductor">Conductor(a)</option>
              <option value="Agricultor">Agricultor(a)</option>
              <option value="Pensionado">Pensionado(a)</option>
              <option value="Desempleado">Desempleado(a)</option>
              <option value="Otro">Otro</option>
            </select>
            <input type="text" name="ocupacion_otro" id="ocupacion_otro" placeholder="Especifique ocupación" style="display:none;">
          </div>
        </div>
        <div class="form-navigation">
          <button type="button" class="prev-btn">Anterior</button>
          <button type="button" class="next-btn">Siguiente</button>
        </div>
      </div>

      <!-- Paso 3: Colegio -->
      <div class="form-step">
        <h5>Información del Colegio</h5>
        <div class="row">
          <div class="col-md-6">
            <label>Colegio</label>
            <select name="colegio_id" id="colegio_id" required>
              <option value="">Seleccione un colegio</option>
              <?php foreach ($colegios as $colegio): ?>
                <option value="<?= $colegio['id'] ?>"
                        data-grados='<?= json_encode($colegio['grados']) ?>'
                        data-jornada='<?= json_encode($colegio['jornada']) ?>'>
                  <?= htmlspecialchars($colegio['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6"><label>Grado</label><select name="grado" id="grado" required><option value="">Seleccione grado</option></select></div>
          <div class="col-md-6"><label>Grupo</label><input type="text" name="grupo"></div>
          <div class="col-md-6"><label>Jornada</label><select name="jornada" id="jornada" required><option value="">Seleccione jornada</option></select></div>
        </div>
        <div class="form-navigation">
          <button type="button" class="prev-btn">Anterior</button>
          <button type="submit" class="submit-btn">Registrar Estudiante</button>
        </div>
      </div>
    </form>
    <?php endif; ?>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="/js/crearE.js"></script>

  <!-- Confirmación antes de enviar -->
  <script>
    // Función para cerrar ventana
    function cerrarVentana() {
      window.close();
      
      // Si no se puede cerrar (restricciones del navegador), 
      // redirigir a una página en blanco o cerrar pestaña
      setTimeout(() => {
        // Intentar abrir about:blank para "cerrar" efectivamente
        window.location.href = 'about:blank';
      }, 500);
    }

    // Solo agregar el listener si existe el formulario
    const form = document.getElementById("formEstudiante");
    if (form) {
      form.addEventListener("submit", function(e) {
        e.preventDefault();
        Swal.fire({
          title: "¿Confirmar registro?",
          text: "Por favor, verifica que la información sea correcta antes de continuar.",
          icon: "question",
          showCancelButton: true,
          confirmButtonText: "Sí, registrar",
          cancelButtonText: "Cancelar"
        }).then((result) => {
          if (result.isConfirmed) {
            e.target.submit();
          }
        });
      });
    }
  </script>
</body>
</html>
