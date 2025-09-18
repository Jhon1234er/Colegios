<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - SENA</title>
    <link rel="stylesheet" href="/css/login_sena.css">
    <!-- Estilos para el calendario -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Estilos para selects -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
</head>
<body>

<div class="login-container">
    <!-- Panel de bienvenida -->
    <div class="welcome-panel" id="welcomePanel">
        <div class="welcome-content">
            <img src="/icons/logo_sena.jpeg" alt="Logo SENA" class="sena-logo">
            <div class="welcome-text" id="welcomeText">
                <h1 id="welcomeTitle">¡Hola!</h1>
                <h2 id="welcomeSubtitle">Bienvenidos a <strong>System School</strong></h2>
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
                    
                    <div class="form-group">
                        <label for="correo">Usuario</label>
                        <input type="email" name="correo" id="correo" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" required>
                            <img src="/icons/Ver.svg" alt="Ver contraseña" id="togglePassword" class="toggle-password">
                        </div>
                    </div>

                    <div class="forgot-password">
                        <a href="#">¿Olvidaste tu contraseña?</a>
                    </div>

                    <button type="submit" name="login" class="form-button">Entrar</button>

                    <div class="form-links">
                        ¿No tienes cuenta? <a href="#" id="switchToRegister">Crea una</a>
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

    <!-- Switch modo oscuro/claro - Posición fija -->
    <div class="theme-switch" id="themeSwitch">
        <label for="switch" class="switch">
            <input id="switch" type="checkbox" />
            <span class="slider"></span>
            <span class="decoration"></span>
        </label>
    </div>
</div>

<!-- Scripts para calendario y selects -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="/js/login.js"></script>
</body>
</html>
