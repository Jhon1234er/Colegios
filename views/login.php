<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - SENA</title>
    <!-- Favicon (con cache-busting) -->
    <link rel="icon" type="image/x-icon" href="/icons/logo.ico?v=3">
    <link rel="icon" href="/favicon.ico?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/logo-32.png?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="/icons/logo-16.png?v=3">
    <link rel="apple-touch-icon" href="/icons/logo-180.png?v=3">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/login_sena.css">
    <!-- Estilos para el calendario -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Estilos para selects -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
</head>
<body>

    <!-- Panel de bienvenida -->
    <div class="welcome-panel" id="welcomePanel">
        <div class="welcome-content">
            <img src="/icons/logo_sena.jpeg" alt="Logo SENA" class="sena-logo">
            <div class="welcome-text" id="welcomeText">
                <h1 id="welcomeTitle">Bienvenidos a <strong>Sistem&nbsp;School</strong></h1>
                <h2 id="welcomeSubtitle"></h2>
                <p id="welcomeDescription">Tu plataforma educativa del SENA</p>
            </div>
        </div>
    </div>
    <!-- Panel Derecho - Formulario Login -->
    <div class="login-panel" id="loginPanel">
        <div class="form-container">
            <!-- Formulario de Login -->
            <div class="login-form-container" id="loginForm">
                <h2 class="form-title">Inicia Sesión</h2>

                <form method="POST" action="index.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    
                    <!-- Usuario -->
                    <div class="form-group floating">
                    <input type="email" name="correo" id="correo" placeholder=" " required>
                    <label for="correo">Usuario</label>
                    </div>

                    <!-- Contraseña -->
                    <div class="form-group floating">
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" placeholder=" " required>
                        <label for="password">Contraseña</label>
                        <img src="/icons/Ver.svg" alt="Ver contraseña" id="togglePassword" class="toggle-password">
                    </div>
                    </div>

                    <div class="forgot-password">
                        <a href="#">¿Olvidaste tu contraseña?</a>
                    </div>

                    <button type="submit" name="login" class="form-button">Entrar</button>

                    <div class="form-links">
                        ¿No tienes cuenta? <a href="/?page=registro_profesor">Crea una</a>
                    </div>
                </form>
            </div>

            <!-- Contenedor dinámico para el formulario de registro -->
            <div class="register-form-container inactive" id="registerForm">
                <!-- El contenido se cargará dinámicamente aquí -->
            </div>
        </div>
    </div>

        <?php if (!empty($error)): ?>
            <div style="color: #e74c3c; text-align: center; margin-top: 1rem; padding: 0.5rem; background: rgba(231, 76, 60, 0.1); border-radius: 8px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Scripts para calendario y selects -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="/js/login.js"></script>
</body>
</html>
