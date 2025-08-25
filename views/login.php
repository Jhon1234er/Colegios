<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="/css/login.css">
</head>
<body>

<div class="login-box">

    <!-- Modo claro/oscuro -->
    <div class="text-end" style="margin-bottom: 1rem;">
        <label for="switch" class="switch">
            <input id="switch" type="checkbox" />
            <span class="slider"></span>
            <span class="decoration"></span>
        </label>
    </div>

    <h2>Iniciar Sesión</h2>

    <form method="POST" action="/index.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        <div class="form-group">
            <label for="correo">Correo electrónico</label>
            <input type="email" name="correo" required>
        </div>

        <div class="form-group password-group">
            <label for="password">Contraseña</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="password" required>
                <img src="../icons/Ver.svg" alt="Ver" id="togglePassword" class="toggle-icon">
            </div>
        </div>

        <button type="submit" name="login" class="btn">Ingresar</button>

        <div class="text-center">
            <!-- ✅ CORREGIDO -->
            <a href="index.php?registro=true">Crear cuenta</a>
        </div>
        <div class="text-center">
            <a href="#">Recuperar contraseña</a>
        </div>
    </form>

    <?php if (!empty($error)): ?>
        <p style="color: red; text-align: center;"><?= $error ?></p>
    <?php endif; ?>
</div>

<script src="/js/login.js"></script>
</body>
</html>
