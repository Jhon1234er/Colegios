<?php if (session_status() === PHP_SESSION_NONE) session_start();
$error = $_SESSION['pwd_init_error'] ?? null;
if (isset($_SESSION['pwd_init_error'])) unset($_SESSION['pwd_init_error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Crear nueva contraseña</title>
  <link rel="stylesheet" href="/css/login_sena.css" />
</head>
<body>
  <div class="login-panel" style="min-height:100vh;display:flex;align-items:center;justify-content:center;">
    <div class="form-container" style="width:min(92vw,760px); display:grid; place-items:center;">
      <div class="login-form-container forgot-card" style="display:block; position:relative; border-radius:28px; width:clamp(520px, 48vw, 720px);">
        <img src="/icons/logo_green.png" alt="Sistem School" style="position:absolute; top:14px; left:18px; width:54px; height:auto;" />
        <h2 class="form-title">Nueva contraseña</h2>

        <?php if ($error): ?>
          <div style="margin:10px 0 16px; padding:10px 12px; background: rgba(239,68,68,.12); color:#b91c1c; border-radius:8px; font-size:.95rem; text-align:center;">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <p style="margin:0 0 1rem; font-size:.95rem; color:#374151;">
          Ingresa tu nueva contraseña. Debe tener mínimo 9 caracteres, incluir mayúscula, minúscula, número y un caracter especial.
        </p>

        <form method="POST" action="/?page=reset_password_code_submit" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>" />

          <div class="form-group" style="margin-top:1rem;">
            <label for="password_nueva" style="display:block; font-weight:600; color:#111; margin-bottom:.5rem;">Nueva contraseña</label>
            <input
              type="password"
              name="password_nueva"
              id="password_nueva"
              required
              minlength="9"
              autocomplete="new-password"
            >
          </div>

          <div class="form-group" style="margin-top:1rem;">
            <label for="password_confirmacion" style="display:block; font-weight:600; color:#111; margin-bottom:.5rem;">Confirmar contraseña</label>
            <input
              type="password"
              name="password_confirmacion"
              id="password_confirmacion"
              required
              minlength="9"
              autocomplete="new-password"
            >
          </div>

          <div style="margin-top:14px;">
            <button type="submit" class="form-button">Finalizar</button>
          </div>

          <div style="text-align:center; margin-top:1.5rem;">
            <a href="/?page=login" style="color:#00304D; text-decoration:none; font-weight:500; font-size:0.95rem;">
              ← Volver a iniciar sesión
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
