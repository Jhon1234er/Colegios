<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Recuperar contraseña</title>
  <link rel="stylesheet" href="/css/login_sena.css" />

  <style>
    .form-button[disabled]{ opacity:.6; cursor:not-allowed; }
  </style>
</head>

<body>
  <div class="login-panel" style="min-height:100vh;display:flex;align-items:center;justify-content:center;">
    <div class="form-container" style="width:min(92vw,760px); display:grid; place-items:center;">
      <div class="login-form-container forgot-card" style="display:block; position:relative; border-radius:28px; width:clamp(520px, 48vw, 720px);">

        <img src="/icons/logo_green.png" alt="Sistem School" style="position:absolute; top:14px; left:18px; width:54px; height:auto;" /> 
        <h2 class="form-title">Restablecimiento de contraseña</h2>

        <?php if (isset($_GET['sent'])): ?>
          <div style="margin:10px 0 16px; padding:10px 12px; background: rgba(46,204,113,.13); color:#16a34a; border-radius:8px; font-size:.95rem; text-align:center;">
            Si el correo existe, hemos enviado un código de verificación.
          </div>
        <?php endif; ?>
        <?php if (isset($_GET['exp'])): ?>
          <div style="margin:10px 0 16px; padding:10px 12px; background: rgba(239,68,68,.12); color:#b91c1c; border-radius:8px; font-size:.95rem; text-align:center;">
            El código ha caducado. Solicita uno nuevo para continuar.
          </div>
        <?php endif; ?>

        <form id="forgotForm" method="POST" action="/?page=emailjs" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>" />

          <div class="form-group" style="margin-top:1rem;">
            <label for="tipo_id" style="display:block; font-weight:600; color:#111; margin-bottom:.5rem;">Tipo de identificación</label>
            <select name="tipo_id" id="tipo_id" required aria-required="true">
              <option value="" disabled selected hidden>Selecciona una opción</option>
              <option value="CC">Cédula de ciudadanía</option>
              <option value="CE">Cédula de extranjería</option>
            </select>
          </div>

          <div class="form-group" style="margin-top:1rem;">
            <label for="numero_id" style="display:block; font-weight:600; color:#111; margin-bottom:.5rem;">Número de identificación</label>
            <input
              type="text"
              name="numero_id"
              id="numero_id"
              inputmode="numeric"
              pattern="[0-9]+"
              required
              aria-required="true"
              minlength="6"
              maxlength="30"
              autocomplete="off"
            >
          </div>

          <div style="margin-top:6px;">
            <button type="submit" id="forgotSubmitBtn" class="form-button">Enviar código</button>
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

  <script>
    (function(){
      const form = document.getElementById('forgotForm');
      const btn = document.getElementById('forgotSubmitBtn');
      const tipo = document.getElementById('tipo_id');
      const numero = document.getElementById('numero_id');

      function showInlineError(msg) {
        const prev = document.querySelector('.inline-error');
        if (prev) prev.remove();
        const d = document.createElement('div');
        d.className = 'inline-error';
        d.style.cssText = 'color:#b91c1c;background:rgba(239,68,68,.08);padding:8px 12px;border-radius:8px;margin:8px 0;text-align:center;';
        d.textContent = msg;
        form.insertBefore(d, form.firstChild);
      }

      form.addEventListener('submit', function(e){
        if (!tipo.value) {
          e.preventDefault();
          showInlineError('Selecciona el tipo de identificación.');
          tipo.focus();
          return;
        }
        const onlyNums = numero.value.replace(/\D/g,'');
        if (!onlyNums || onlyNums.length < 6) {
          e.preventDefault();
          showInlineError('Ingresa un número de identificación válido (mín. 6 dígitos).');
          numero.focus();
          return;
        }
        numero.value = onlyNums;
        btn.disabled = true;
        btn.textContent = 'Enviando...';
      });
    })();
  </script>
</body>
</html>
