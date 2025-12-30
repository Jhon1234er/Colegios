<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$remainingSeconds = 0;
$resendCount = (int)($_SESSION['pw_reset_resend_count'] ?? 0);
$lastChance  = !empty($_SESSION['pw_reset_last_chance']);
$blockUntil  = isset($_SESSION['pw_reset_block_until']) ? (int)$_SESSION['pw_reset_block_until'] : 0;
if (!empty($_SESSION['pw_reset']['expires'])) {
    $remainingSeconds = max(0, (int)$_SESSION['pw_reset']['expires'] - time());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Verificar código</title>
  <link rel="stylesheet" href="/css/login_sena.css" />
</head>
<body>
  <div class="login-panel" style="min-height:100vh;display:flex;align-items:center;justify-content:center;">
    <div class="form-container" style="width:min(92vw,760px); display:grid; place-items:center;">
      <div class="login-form-container forgot-card" style="display:block; position:relative; border-radius:28px; width:clamp(520px, 48vw, 720px);">
        <img src="/icons/logo_green.png" alt="Sistem School" style="position:absolute; top:14px; left:18px; width:54px; height:auto;" />
        <h2 class="form-title" style="font-family:'Myriad Pro', Inter, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;">
          Verificación de código
        </h2>

        <?php if (isset($_GET['error'])): ?>
          <div style="margin:10px 0 16px; padding:10px 12px; background: rgba(239,68,68,.12); color:#b91c1c; border-radius:8px; font-size:.95rem; text-align:center;">
            Código inválido o expirado. Intenta nuevamente.
          </div>
        <?php endif; ?>
        <?php if (isset($_GET['blocked']) && $blockUntil > time()): ?>
          <div style="margin:0 0 12px; padding:10px 12px; background: rgba(239,68,68,.12); color:#b91c1c; border-radius:8px; font-size:.9rem; text-align:center;">
            Has alcanzado el número máximo de reenvíos. Espera unos minutos antes de volver a solicitar un código.
          </div>
        <?php endif; ?>

        <p style="margin:0 0 .35rem; font-size:.95rem; color:#374151;">
          Ingresa el código de <strong>6 dígitos</strong> que enviamos a tu correo electrónico.
        </p>
        <?php if (!empty($_SESSION['reset_email_mask'])): ?>
          <p style="margin:0 0 .5rem; font-size:.9rem; color:#6b7280;">
            Enviamos el código a: <strong><?= htmlspecialchars($_SESSION['reset_email_mask'], ENT_QUOTES, 'UTF-8') ?></strong>
          </p>
        <?php endif; ?>

        <?php $canResendNow = ($remainingSeconds <= 0) && !($blockUntil > time()); ?>
        <div id="code-timer" style="margin:0 0 .5rem; font-size:.85rem; color:#6b7280; text-align:center;" data-remaining="<?= (int)$remainingSeconds ?>">
          <?php if ($canResendNow): ?>
            Puedes solicitar un nuevo código ahora.
          <?php else: ?>
            Podrás solicitar un nuevo código en <span id="code-timer-text"></span>.
          <?php endif; ?>
        </div>

        <form id="verifyCodeForm" method="POST" action="/?page=verify_reset_code" style="margin-top:1rem;">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>" />

          <div style="display:flex; gap:0.5rem; justify-content:center; margin:1rem 0 1.5rem;">
            <?php for ($i = 1; $i <= 6; $i++): ?>
              <input
                type="text"
                name="code<?= $i ?>"
                maxlength="1"
                inputmode="numeric"
                pattern="[0-9]"
                style="width:42px; height:48px; text-align:center; font-size:1.4rem; border-radius:10px; border:1px solid #d1d5db;"
              />
            <?php endfor; ?>
          </div>

          <button type="submit" class="form-button">
            Verificar código
          </button>

          <div style="text-align:center; margin-top:0.85rem;">
            <button type="button" id="resendCodeBtn" class="form-button" style="background:#e5e7eb;color:#111;padding:0.55rem 1.3rem;font-size:0.9rem;" <?= $canResendNow ? '' : 'disabled' ?>>
              Reenviar código
            </button>
          </div>

          <?php if ($lastChance): ?>
            <p style="margin:0.75rem 0 0; font-size:.85rem; color:#f97316; text-align:center;">
              Este es tu <strong>último reenvío</strong>. Si aún no recibes el código, espera el tiempo de bloqueo o contacta al administrador.
            </p>
          <?php elseif ($resendCount >= 3): ?>
            <p style="margin:0.75rem 0 0; font-size:.85rem; color:#9ca3af; text-align:center;">
              Si aún no recibes el código, revisa también tu <strong>correo institucional</strong> registrado.
            </p>
          <?php endif; ?>

          <div style="text-align:center; margin-top:1.5rem;">
            <a href="/?page=forgot_password" style="color:#00304D; text-decoration:none; font-weight:500; font-size:0.95rem;">
              ← Volver a recuperar contraseña
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    (function(){
      var inputs = document.querySelectorAll('#verifyCodeForm input[type="text"]');
      if (!inputs.length) return;
      inputs[0].focus();
      inputs.forEach(function(inp, idx){
        inp.addEventListener('input', function(){
          this.value = this.value.replace(/\D/g,'').slice(0,1);
          if (this.value && idx < inputs.length - 1) {
            inputs[idx+1].focus();
          }
        });
        inp.addEventListener('keydown', function(e){
          if (e.key === 'Backspace' && !this.value && idx > 0) {
            inputs[idx-1].focus();
          }
        });
      });
    })();

    (function(){
      var timerEl = document.getElementById('code-timer');
      var btn = document.getElementById('resendCodeBtn');
      if (!timerEl || !btn) return;

      var remaining = parseInt(timerEl.getAttribute('data-remaining') || '0', 10);
      var textEl = document.getElementById('code-timer-text');

      function fmt(s){
        s = Math.max(0, s|0);
        var m = Math.floor(s/60), r = s%60;
        return String(m).padStart(2,'0') + ':' + String(r).padStart(2,'0');
      }

      var intervalId = null;
      if (remaining > 0 && textEl) {
        textEl.textContent = fmt(remaining);
        intervalId = setInterval(function(){
          remaining--;
          if (remaining <= 0) {
            clearInterval(intervalId);
            btn.disabled = false;
            timerEl.textContent = 'Puedes solicitar un nuevo código ahora.';
            return;
          }
          textEl.textContent = fmt(remaining);
        }, 1000);
      } else {
        btn.disabled = false;
      }

      btn.addEventListener('click', function(){
        if (btn.disabled) return;
        btn.disabled = true;
        btn.textContent = 'Reenviando...';
        window.location.href = '/?page=resend_code';
      });
    })();
  </script>
</body>
</html>
