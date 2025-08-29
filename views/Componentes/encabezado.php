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

if ($usuario_id && $tipo_usuario) {
    try {
        $pdo = Database::conectar();
        $stmt = $pdo->prepare("SELECT * FROM notificaciones WHERE usuario_id = ? AND tipo_usuario = ? ORDER BY fecha DESC LIMIT 5");
        $stmt->execute([$usuario_id, $tipo_usuario]);
        $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND tipo_usuario = ? AND estado = 'no_leida'");
        $stmtTotal->execute([$usuario_id, $tipo_usuario]);
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
  <link rel="stylesheet" href="/css/Componentes/encabezado.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

</head>
<body>

<header class="header-unified">
  <!-- Logo -->
  <div class="logo-section">
    <a href="/?page=dashboard" class="logo">Sistem Scholl</a>
  </div>

  <!-- Navegación Principal -->
  <?php if ($usuario): ?>
    <nav class="nav-main">
      <?php if ($rol_id === 1): ?>
        <!-- Buscador integrado -->
        <?php if (!isset($_GET['page']) || $_GET['page'] === 'dashboard'): ?>
          <div class="search-section">
            <form id="buscador-global" style="display: flex; align-items: center; gap: 8px;">
              <select id="filtro-busqueda" class="search-select">
                <option value="colegio">Colegios</option>
                <option value="profesor">Facilitadores</option>
                <option value="estudiante">Aprendices</option>
              </select>
              <input id="input-busqueda" type="text" class="search-input" placeholder="Buscar..." />
              <button type="submit" class="search-btn">
                <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
              </button>
            </form>
          </div>
        <?php endif; ?>

        <!-- Menús de navegación -->
        <div class="dropdown">
          <button class="dropbtn">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Registros
          </button>
          <div class="dropdown-content">
            <a href="/?page=profesores&action=crear">Crear Facilitador</a>
            <a href="/?page=estudiantes&action=crear">Crear Aprendiz</a>
            <a href="/?page=colegios&action=crear">Registrar Colegios</a>
            <a href="/?page=crear_materia">Registrar Cursos</a>
          </div>
        </div>

        <div class="dropdown">
          <button class="dropbtn">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
            </svg>
            Listas
          </button>
          <div class="dropdown-content">
            <a href="/?page=profesores">Facilitadores</a>
            <a href="/?page=estudiantes">Aprendices</a>
            <a href="/?page=colegios">Colegios</a>
          </div>
        </div>

        <a href="/?page=dashboard" class="nav-link">
          <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
          </svg>
          Inicio
        </a>

      <?php elseif ($rol_id === 2): ?>
        <a href="/?page=dashboard_profesor" class="nav-link">
          <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
          </svg>
          Mis Fichas
        </a>
        <a href="/?page=fichas&action=crear" class="nav-link">
          <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
          </svg>
          Nueva Ficha
        </a>
      <?php endif; ?>
    </nav>

    <!-- Panel de Usuario -->
    <div class="user-panel">
      <!-- Notificaciones -->
      <div class="notifications-btn" onclick="toggleNotifications()">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM10.02 2.87A7 7 0 118 15l-4 4v-4H3a1 1 0 01-1-1v-3a7 7 0 017-7z"></path>
        </svg>
        <?php if ($totalNoLeidas > 0): ?>
          <span class="notifications-badge"><?= $totalNoLeidas ?></span>
        <?php endif; ?>
      </div>

      <!-- Panel de notificaciones -->
      <div id="notifications-panel" class="notifications-panel">
        <div class="notifications-header">
          Notificaciones
        </div>
        <div class="notifications-list">
          <?php if (empty($notificaciones)): ?>
            <div class="empty-notifications">
              No tienes notificaciones nuevas
            </div>
          <?php else: ?>
            <?php foreach ($notificaciones as $n): ?>
              <div class="notification-item <?= $n['estado'] === 'no_leida' ? 'unread' : '' ?>">
                <div class="notification-content">
                  <?= htmlspecialchars(str_replace('profesor', 'facilitador', $n['mensaje'])) ?>
                </div>
                <div class="notification-actions">
                  <span class="notification-date"><?= date('d/m/Y H:i', strtotime($n['fecha'])) ?></span>
                  <?php if ($n['estado'] === 'no_leida'): ?>
                    <button class="mark-read-btn" onclick="markAsRead(<?= $n['id'] ?>)">
                      Marcar leída
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Menú de Perfil -->
      <div class="profile-menu">
        <button class="profile-btn">
          <img src="/icons/usuario.png" alt="Perfil" class="profile-avatar" />
          <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
          </svg>
        </button>
        <div class="profile-dropdown">
          <a href="/?page=ver_perfil">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            Ver Perfil
          </a>
          <a href="/?page=editar_perfil">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
            Editar Perfil
          </a>
          <a href="#">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            Configuración
          </a>
        </div>
      </div>

      <!-- Botón de Logout -->
      <form action="/logout.php" method="post" style="display:inline;">
        <button type="submit" class="logout-btn" title="Cerrar sesión">
          <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
          </svg>
          Salir
        </button>
      </form>
    </div>
  <?php endif; ?>
</header>

<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script>
// Toggle panel de notificaciones
function toggleNotifications() {
  const panel = document.getElementById('notifications-panel');
  panel.classList.toggle('active');
  
  // Cerrar al hacer clic fuera
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.notifications-btn') && !e.target.closest('#notifications-panel')) {
      panel.classList.remove('active');
    }
  });
}

// Marcar notificación como leída
function markAsRead(notificationId) {
  fetch('/api/marcar_notificacion_leida.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ id: notificationId })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Actualizar la interfaz
      location.reload();
    }
  })
  .catch(error => {
    console.error('Error:', error);
  });
}

// Funcionalidad del buscador global
document.getElementById('buscador-global')?.addEventListener('submit', function(e) {
  e.preventDefault();
  const filtro = document.getElementById('filtro-busqueda').value;
  const query = document.getElementById('input-busqueda').value;
  
  if (query.trim()) {
    // Implementar lógica de búsqueda
    console.log('Buscando:', filtro, query);
  }
});

// Cerrar dropdowns al hacer clic fuera
document.addEventListener('click', function(e) {
  if (!e.target.closest('.dropdown') && !e.target.closest('.profile-menu')) {
    // Cerrar todos los dropdowns activos
    document.querySelectorAll('.dropdown-content, .profile-dropdown').forEach(dropdown => {
      dropdown.style.opacity = '0';
      dropdown.style.visibility = 'hidden';
    });
  }
});
</script>

</body>
</html>