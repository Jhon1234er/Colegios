<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Usuario</title>

    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="/css/registro.css">
    <!-- Estilos para el calendario -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- Estilos para selects -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
</head>
<body>

<div class="registro-container">


    <h2>Crear cuenta</h2>

    <!-- Formulario redirigido correctamente -->
    <form method="POST" action="../../public/index.php?registro=true">
        <div class="form-group">
            <label for="nombres">Nombres*</label>
            <input type="text" name="nombres" id="nombres" required>
        </div>

        <div class="form-group">
            <label for="apellidos">Apellidos*</label>
            <input type="text" name="apellidos" id="apellidos" required>
        </div>

        <div class="form-group">
            <label for="tipo_documento">Tipo de documento</label>
            <select name="tipo_documento" id="tipo_documento" required>
                <option value="" disabled selected>Seleccionar</option>
                <option value="CC">Cédula de Ciudadanía</option>
                <option value="CE">Cédula de Extranjería</option>
            </select>
        </div>

        <div class="form-group">
            <label for="numero_documento">Número de documento*</label>
            <input type="text" name="numero_documento" id="numero_documento" required>
        </div>

        <div class="form-group">
            <label for="correo_electronico">Correo electrónico*</label>
            <input type="email" name="correo_electronico" id="correo_electronico" required>
        </div>

        <div class="form-group">
            <label for="telefono">Teléfono</label>
            <input type="text" name="telefono" id="telefono">
        </div>


        <div class="form-group">
            <label for="fecha_nacimiento">Fecha de nacimiento*</label>
            <input type="date   " name="fecha_nacimiento" id="fecha_nacimiento" required>
        </div>

        <div class="form-group">
            <label for="genero">Género</label>
            <select name="genero" id="genero" required>
                <option value="" disabled selected>Seleccionar</option>
                <option value="M">Masculino</option>
                <option value="F">Femenino</option>
                <option value="Otro">Otro</option>
            </select>
        </div>

        <div class="form-group">
            <label for="password">Contraseña*</label>
            <input type="password" name="password" id="password" required>
        </div>

        <input type="hidden" name="rol_id" value="1">

        <button type="submit" name="registro" class="btn">Registrar</button>

        <div class="text-links">
            <a href="../../public/index.php">¿Ya tienes cuenta?</a>
        </div>
    </form>
</div>

<!-- Scripts para calendario y selects -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="/js/registro.js"></script>
</body>
</html>
