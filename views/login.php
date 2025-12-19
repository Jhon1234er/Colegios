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
                <?php if (!empty($error)): ?>
                    <div style="margin:10px 0 16px; padding:10px 12px; background: rgba(231,76,60,.1); color:#e74c3c; border-radius:8px; font-size:.95rem; text-align:center;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['reset'])): ?>
                    <div style="margin:10px 0 16px; padding:10px 12px; background: rgba(34,197,94,.12); color:#16a34a; border-radius:8px; font-size:.95rem; text-align:center;">
                        Tu contraseña fue cambiada con éxito. Ahora puedes iniciar sesión.
                    </div>
                <?php endif; ?>

                <form method="POST" action="index.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    
                    <!-- Usuario -->
                    <div class="form-group floating">
                    <input type="email" name="correo" id="correo" placeholder=" " required autocomplete="username">
                    <label for="correo">Usuario</label>
                    </div>

                    <!-- Contraseña -->
                    <div class="form-group floating">
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" placeholder=" " required autocomplete="current-password">
                        <label for="password">Contraseña</label>
                        <img src="/icons/Ver.svg" alt="Ver contraseña" id="togglePassword" class="toggle-password">
                    </div>
                    </div>

                    <div class="forgot-password">
                        <a href="/?page=forgot_password">¿Olvidaste tu contraseña?</a>
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

    </div>

</div>

<!-- Scripts para calendario y selects -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="/js/login.js"></script>
<style>
    .gsuccess-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);display:none;align-items:center;justify-content:center;z-index:2000}
    .gsuccess-card{width:min(92vw,520px);background:#fff;border-radius:14px;box-shadow:0 20px 45px rgba(2,6,23,.18);padding:20px 22px;transform:translateY(8px) scale(.98);opacity:0;transition:all .25s ease;text-align:center}
    .gsuccess-card.show{transform:translateY(0) scale(1);opacity:1}
    .gsuccess-icon{width:64px;height:64px;border-radius:50%;margin:8px auto 12px;display:grid;place-items:center;background:#e8f8ef;color:#16a34a}
    .gsuccess-icon svg{width:32px;height:32px}
    .gsuccess-title{font-size:20px;font-weight:700;color:#0f172a;margin:4px 0 6px}
    .gsuccess-text{font-size:14px;color:#334155;margin:0 4px 6px}
    .gsuccess-progress{height:4px;width:100%;background:#e2e8f0;border-radius:999px;overflow:hidden;margin-top:10px}
    .gsuccess-bar{height:100%;width:0;background:linear-gradient(90deg,#22c55e,#16a34a);transition:width linear}
</style>
<div id="global-success-overlay" class="gsuccess-overlay" role="dialog" aria-live="polite" aria-modal="true">
    <div class="gsuccess-card" role="document">
        <div class="gsuccess-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
        </div>
        <div class="gsuccess-title" id="gs_title">¡Registro exitoso!</div>
        <div class="gsuccess-text" id="gs_text">Tu cuenta fue creada correctamente.</div>
        <div class="gsuccess-progress" aria-hidden="true"><div class="gsuccess-bar" id="gs_bar"></div></div>
    </div>
</div>
<script>
    (function(){
        const qs=new URLSearchParams(location.search);
        if(qs.get('registro_facilitador')!=='ok' && qs.get('registro')!=='ok') return;
        const overlay=document.getElementById('global-success-overlay');
        if(!overlay) return; const card=overlay.querySelector('.gsuccess-card'); const bar=overlay.querySelector('#gs_bar');
        const title=document.getElementById('gs_title'); const text=document.getElementById('gs_text');
        if(qs.get('registro_facilitador')==='ok'){
            title.textContent='¡Registro enviado!';
            text.textContent='Hemos recibido tu registro. Debes esperar a que un administrador lo revise y active o bloquee tu cuenta. Te notificaremos cuando esté listo.';
        } else if (qs.get('registro')==='ok') {
            title.textContent='¡Registro exitoso!';
            text.textContent='Tu cuenta fue creada correctamente.';
        }
        const DURATION=2600; overlay.style.display='flex'; requestAnimationFrame(()=>card.classList.add('show'));
        // Limpiar parámetros para evitar reaparición del modal al recargar
        try {
            const url = new URL(window.location.href);
            ['registro','registro_facilitador'].forEach(p=> url.searchParams.delete(p));
            const newUrl = url.pathname + (url.search ? url.search : '') + (url.hash || '');
            window.history.replaceState({}, '', newUrl);
        } catch(_) {}
        bar.style.transitionDuration=DURATION+'ms'; requestAnimationFrame(()=>bar.style.width='100%');
        const hide=()=>{card.classList.remove('show'); setTimeout(()=>{overlay.style.display='none';},220)};
        setTimeout(hide,DURATION+150);
        overlay.addEventListener('click',e=>{ if(e.target===overlay) hide();});
        document.addEventListener('keydown',e=>{ if(e.key==='Escape') hide();});
    })();
</script>
</body>
</html>
