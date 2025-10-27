<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/db.php';

$usuario = $_SESSION['usuario'] ?? null;
$usuario_id = $usuario['id'] ?? null;
$rol_id = $usuario['rol_id'] ?? null;
$tip_contrato = $usuario['tip_contrato'] ?? null;

$roles = [
    1 => 'administrador',
    2 => 'profesor',
    4 => 'rector'
];

$tipo_usuario = $roles[$rol_id] ?? null;

// Obtener notificaciones
$notificaciones = [];
$totalNoLeidas = 0;

if ($usuario_id) {
    try {
        $pdo = Database::conectar();
        // Listado: todas (leídas y no leídas) para visualización; ordenar por id DESC para evitar dependencia de 'fecha'
        $stmt = $pdo->prepare("SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY id DESC LIMIT 50");
        $stmt->execute([$usuario_id]);
        $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND (estado = 'no_leida' OR estado IS NULL)");
        $stmtTotal->execute([$usuario_id]);
        $totalNoLeidas = $stmtTotal->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error al obtener notificaciones: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Sistem Scholl</title>
  <!-- Favicon (con cache-busting) -->
  <link rel="icon" type="image/x-icon" href="/icons/logo.ico?v=3">
  <link rel="icon" href="/favicon.ico?v=3">
  <!-- PNG favicon and Apple Touch (recomendado por navegadores) -->
  <link rel="icon" type="image/png" href="/icons/logo.png?v=3">
  <link rel="apple-touch-icon" href="/icons/logo-180.png?v=3">
  <!-- Optional PNG fallbacks -->
  <link rel="icon" type="image/png" sizes="32x32" href="/icons/logo-32.png?v=3">
  <link rel="icon" type="image/png" sizes="16x16" href="/icons/logo-16.png?v=3">
  <link rel="apple-touch-icon" sizes="180x180" href="/icons/logo-180.png?v=3">
  <link rel="stylesheet" href="/css/Componentes/encabezado.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</head>
<body>
<header class="header-unified" role="banner">
  <div class="header-inner">
    <!-- Logo -->
    <div class="logo-section" style="flex:0 0 auto">
      <img src="/icons/logo_sistem.png" alt="Logo Sistem Scholl">
      <a href="/?page=dashboard" class="logo" aria-label="Ir al inicio">Sistem Scholl</a>
    </div>

    <!-- Buscador (solo admin en dashboard) -->
    <?php if ($usuario && $rol_id === 1): ?>
      <?php 
        $current_page = $_GET['page'] ?? '';
        $mostrar_buscador = ($current_page === 'dashboard' || ($current_page === '' && !isset($_GET['action'])));
      ?>
      <?php if ($mostrar_buscador): ?>
        <div class="search-section" role="search" aria-label="Buscador global">
          <form id="buscador-global" class="search-form" autocomplete="off">
            <div class="select-wrapper">
              <select id="filtro-busqueda" class="search-select" aria-label="Filtro de búsqueda">
                <option value="colegio">Colegios</option>
                <option value="profesor">Facilitadores/Instructores</option>
                <option value="estudiante">Aprendices</option>
              </select>
              <span class="select-arrow" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" aria-hidden="true">
                  <polyline points="6,9 12,15 18,9"></polyline>
                </svg>
              </span>
            </div>
            <input id="input-busqueda" type="text" placeholder="Buscar..." class="search-input" aria-label="Ingresar término de búsqueda" />
            <button type="submit" class="search-btn" aria-label="Buscar">
              <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
              </svg>
            </button>
          </form>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Navegación -->
    <?php if ($usuario): ?>
      <nav class="nav-main" role="navigation" aria-label="Navegación principal">
        <?php if ($rol_id === 1): ?>
          <!-- Registros -->
          <div class="dropdown" data-dropdown>
            <button type="button" class="dropbtn" aria-haspopup="true" aria-expanded="false" data-dropdown-button>
              <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
              </svg>
              Registros
            </button>
            <div class="dropdown-content" role="menu">
              <a role="menuitem" href="/?page=profesores&action=crear">Crear Facilitador</a>
              <a role="menuitem" href="/?page=estudiantes&action=crear">Crear Aprendiz</a>
              <a role="menuitem" href="/?page=colegios&action=crear">Registrar Colegios</a>
              <a role="menuitem" href="/?page=materias">Registrar Cursos</a>
            </div>
          </div>

          

          <a href="/?page=dashboard" class="dropbtn">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
            Inicio
          </a>

          <a href="/?page=calendario_colaborativo" class="dropbtn">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            Calendario
          </a>

        <?php elseif ($rol_id === 2): ?>
          <a href="/?page=dashboard_profesor" class="dropbtn">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
            </svg>
            Mis Fichas
          </a>
          <a href="/?page=calendario" class="dropbtn">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            Calendario
          </a>
          <a href="/?page=fichas&action=crear" class="dropbtn">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Nueva Ficha
          </a>
        <?php elseif ($rol_id === 4): ?>
          <!-- Menú para Asistente -->
          <a href="/?page=dashboard" class="dropbtn">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            Inicio
          </a>
          <a href="/?page=calendario_colaborativo" class="dropbtn">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            Calendario
          </a>
        <?php endif; ?>
      </nav>

      <!-- Panel de usuario -->
      <div class="user-panel" style="flex:0 0 auto;display:flex;align-items:center;gap:12px;position:relative">
        <?php if ($rol_id === 4): ?>
          <!-- Cambiar vista (Asistente) al lado izquierdo de notificaciones -->
          <button id="view-toggle-btn" class="view-toggle-btn" title="Cambiar vista">
            <span class="icon-grid"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span>
            <span class="icon-eye"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span>
          </button>
        <?php endif; ?>
        <!-- Notificaciones -->
        <button type="button" class="notifications-btn" id="btn-notificaciones" aria-expanded="false" aria-controls="notifications-panel" title="Notificaciones">
          <i class="fa-regular fa-bell" style="font-size:18px;"></i>
          <?php if ($totalNoLeidas > 0): ?>
            <span class="notifications-badge" aria-label="No leídas"><?= $totalNoLeidas ?></span>
          <?php endif; ?>
        </button>

        <div id="notifications-panel" class="notifications-panel" role="region" aria-label="Notificaciones">
          <div class="notifications-header">Notificaciones</div>
          <div class="notifications-list">
            <?php if (empty($notificaciones)): ?>
              <div class="empty-notifications">No tienes notificaciones nuevas</div>
            <?php else: ?>
              <?php foreach ($notificaciones as $n): ?>
                <?php $isUnread = !isset($n['estado']) || $n['estado'] === 'no_leida'; ?>
                <div class="notification-item <?= $isUnread ? 'unread' : '' ?>">
                  <div class="notification-content">
                    <?php 
                      $msg = trim((string)($n['mensaje'] ?? ''));
                      if ($msg === '' && isset($n['titulo'])) { $msg = (string)$n['titulo']; }
                      $msg = str_replace('profesor', 'facilitador', $msg);
                      // Permitir solo <a> y quitar el resto de etiquetas
                      $msgSafe = strip_tags($msg, '<a>');
                      // Forzar atributos seguros en los anchors
                      $msgSafe = preg_replace(
                        '#<a\s+([^>]*href\s*=\s*\"[^\"]*\"[^>]*)>#i',
                        '<a $1 target="_blank" rel="noopener noreferrer" class="link">',
                        $msgSafe
                      );
                      echo $msgSafe;
                    ?>
                  </div>

                  <?php if (!empty($n['botones_accion']) && $isUnread): ?>
                    <?php $botones = json_decode($n['botones_accion'], true); ?>
                    <?php $datos = json_decode($n['datos_accion'], true); ?>
                    <div class="notification-buttons">
                      <?php if (isset($botones['aceptar'])): ?>
                        <button type="button" class="btn-aceptar" onclick="responderSolicitud(<?= (int)$datos['solicitud_id'] ?>, 'aceptar', <?= (int)$n['id'] ?>)">
                          <?= $botones['aceptar'] ?>
                        </button>
                      <?php endif; ?>
                      <?php if (isset($botones['rechazar'])): ?>
                        <button type="button" class="btn-rechazar" onclick="responderSolicitud(<?= (int)$datos['solicitud_id'] ?>, 'rechazar', <?= (int)$n['id'] ?>)">
                          <?= $botones['rechazar'] ?>
                        </button>
                      <?php endif; ?>
                    </div>
                  <?php elseif (!empty($n['botones_accion']) && isset($n['estado']) && $n['estado'] === 'leida'): ?>
                    <div class="notification-buttons-disabled">
                      <span class="btn-disabled">Procesado</span>
                    </div>
                  <?php endif; ?>

                  <div class="notification-actions">
                    <?php 
                      $fechaTxt = '';
                      if (!empty($n['fecha']) && strtotime($n['fecha'])) {
                        $fechaTxt = date('d/m/Y H:i', strtotime($n['fecha']));
                      }
                    ?>
                    <span class="notification-date"><?= htmlspecialchars($fechaTxt ?: '') ?></span>
                    <?php if ($isUnread): ?>
                      <button type="button" class="mark-read-btn" onclick="markAsRead(<?= (int)$n['id'] ?>)">Marcar leída</button>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Menú Perfil -->
        <div class="profile-menu" data-dropdown>
          <button type="button" class="profile-btn" data-dropdown-button aria-haspopup="true" aria-expanded="false">
            <img src="/icons/usuario.png" alt="Perfil" class="profile-avatar" />
            <svg class="profile-caret-svg" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <polyline points="6,9 12,15 18,9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"></polyline>
            </svg>
          </button>
          <div class="profile-dropdown" role="menu">
            <a role="menuitem" href="/?page=ver_perfil">
              <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
              </svg>
              Ver Perfil
            </a>
            
            <?php if ($rol_id === 1): ?>
              <a role="menuitem" href="/?page=profesores">Facilitadores</a>
              <a role="menuitem" href="/?page=estudiantes">Aprendices</a>
              <a role="menuitem" href="/?page=colegios">Colegios</a>
              <a role="menuitem" href="/?page=materias">Cursos</a>
            <?php endif; ?>
            <a role="menuitem" href="#" onclick="document.getElementById('logout-form').submit(); return false;">
              <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
              </svg>
              Salir
            </a>
          </div>
        </div>

        <!-- Hidden logout POST form -->
        <form id="logout-form" action="/logout.php" method="post" style="display:none;"></form>
      </div>
    <?php endif; ?>
  </div>
</header>

<script>
// ---------- UTIL: fuera de control, centralizamos listeners una sola vez
(function () {
  const $ = (sel, ctx=document) => ctx.querySelector(sel);
  const $$ = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));

  // Notificaciones (un solo listener global)
  const btnNotif = $('#btn-notificaciones');
  const panelNotif = $('#notifications-panel');

  if (btnNotif && panelNotif) {
    btnNotif.addEventListener('click', (e) => {
      e.stopPropagation();
      panelNotif.classList.toggle('active');
      const expanded = panelNotif.classList.contains('active');
      btnNotif.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    });
  }

  // Dropdowns por clic (soporta touch). Cierra los demás si abres uno
  document.addEventListener('click', (e) => {
    const isToggle = e.target.closest('[data-dropdown-button]');
    const dropdown = e.target.closest('[data-dropdown]');

    // Cerrar todo si haces click fuera
    if (!dropdown) {
      $$('.dropdown-content, .profile-dropdown').forEach(el => {
        el.style.opacity = '0';
        el.style.visibility = 'hidden';
        el.style.pointerEvents = 'none';
      });
      // quitar estado activo para flechas/estilos
      $$('.profile-menu, [data-dropdown]').forEach(el => el.classList.remove('active'));
      // aria-expanded a false en todos los botones de dropdown
      $$('[data-dropdown-button]').forEach(btn => btn.setAttribute('aria-expanded','false'));
      if (panelNotif) panelNotif.classList.remove('active');
      if (btnNotif) btnNotif.setAttribute('aria-expanded','false');
      return;
    }

    // Si el botón del dropdown fue clickeado
    if (isToggle) {
      e.preventDefault();
      e.stopPropagation();

      // Cierra otros
      $$('.dropdown-content, .profile-dropdown').forEach(el => {
        if (!dropdown.contains(el)) {
          el.style.opacity = '0';
          el.style.visibility = 'hidden';
          el.style.pointerEvents = 'none';
        }
      });
      // Quitar 'active' a otros dropdowns
      $$('.profile-menu, [data-dropdown]').forEach(el => {
        if (el !== dropdown) el.classList.remove('active');
      });

      // Toggle del actual
      const menu = dropdown.querySelector('.dropdown-content, .profile-dropdown');
      const btn = dropdown.querySelector('[data-dropdown-button]');
      const visible = menu && menu.style.visibility === 'visible';
      if (menu) {
        menu.style.opacity   = visible ? '0' : '1';
        menu.style.visibility= visible ? 'hidden' : 'visible';
        menu.style.pointerEvents = visible ? 'none' : 'auto';
        // Marcar contenedor como activo para rotar flecha del perfil
        if (visible) dropdown.classList.remove('active'); else dropdown.classList.add('active');
        if (btn) btn.setAttribute('aria-expanded', visible ? 'false' : 'true');
      }
    }
  });
})();

// ---------- APIs (sin cambios sustanciales)
function markAsRead(notificationId) {
  const formData = new FormData();
  formData.append('notificacion_id', notificationId);
  fetch('/?page=marcar_notificacion', { method: 'POST', body: formData })
    .then(r => r.json()).then(d => { if (d.success) location.reload(); });
}

function responderSolicitud(solicitudId, respuesta, notificationId) {
  fetch('index.php?page=responder_solicitud', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ solicitud_id: solicitudId, respuesta })
  })
  .then(r => r.json())
  .then(d => d.success ? markAsRead(notificationId) : alert('Error: ' + d.message))
  .catch(() => alert('Error al procesar la respuesta'));
}
</script>
