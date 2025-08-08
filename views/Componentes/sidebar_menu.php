<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/db.php';

$usuario = $_SESSION['usuario'] ?? null;
$usuario_id = $usuario['id'] ?? null;
$rol_id = $usuario['rol_id'] ?? null;

$roles = [
    1 => 'administrador',
    2 => 'profesor',
    3 => 'estudiante',
    4 => 'rector'
];

$tipo_usuario = $roles[$rol_id] ?? null;
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

<style>
/* CSS para el sidebar */
#sidebar-menu {
  position: fixed; /* Fijo para que se mantenga en su lugar */
  top: 64px; /* Ajusta según la altura de tu encabezado */
  right: 0; /* Alineado a la derecha */
  height: calc(100vh - 64px); /* Altura total menos la altura del encabezado */
  width: 16rem; /* Ancho del menú lateral */
  background-color: #f5f5f5; /* Color de fondo */
  box-shadow: -2px 0 5px rgba(0, 0, 0, 0.1); /* Sombra para el menú */
  transition: width 0.3s ease; /* Transición suave para el ancho del sidebar */
}

/* Ajustes para el menú colapsado */
#sidebar-menu.w-16 {
  width: 4rem; /* Ancho colapsado */
}

/* Ajustes para el contenido del menú */
.expandable-content {
  max-height: 0; /* Colapsado por defecto */
  overflow: hidden; /* Oculta el contenido que excede el max-height */
  transition: max-height 0.3s ease-out; /* Transición suave */
}

.expandable-content.open {
  max-height: 500px; /* Ajusta este valor según el contenido */
}

</style>
<div id="sidebar-menu" class="fixed top-16 left-0 h-[calc(100vh-70px)] w-20 bg-gray-100 shadow-lg transition-all duration-300 z-50 overflow-y-auto">
  <ul class="flex flex-col gap-1 p-2">

    <!-- Hamburger Icon / Toggle Button -->
    <li class="w-full mb-2">
      <button id="sidebar-toggle" class="flex items-center justify-center w-full h-12 text-gray-700 hover:bg-gray-200 rounded-lg transition-colors duration-200">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
      </button>
    </li>

    <!-- Notifications Section -->
    <li class="sidebar-item group w-full overflow-hidden rounded-lg border-l border-transparent bg-white transition-all duration-300">
      <button class="flex items-center gap-2.5 px-3 py-1 text-left text-purple-800 transition-all active:scale-95 w-full sidebar-item-button">
        <div class="rounded-lg border-2 border-purple-300 bg-purple-100 p-0.1 relative">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"></path>
          </svg>
          <?php if ($totalNoLeidas > 0): ?>
            <span class="absolute -top-2 -right-2 bg-red-600 text-white text-xs font-bold px-2 py-0.5 rounded-full shadow">
              <?= $totalNoLeidas ?>
            </span>
          <?php endif; ?>
        </div>
        <span class="font-semibold whitespace-nowrap">Notificaciones</span>
      </button>
      <div class="expandable-content max-h-0 overflow-hidden transition-all duration-300 ease-out">
        <div class="overflow-hidden">
          <ul id="lista-notificaciones-sidebar" class="divide-y divide-gray-200 p-4 pt-0">
            <div id="sin-notificaciones-sidebar" class="p-4 text-gray-500 text-sm text-center <?= empty($notificaciones) ? '' : 'hidden' ?>">
              No tienes notificaciones.
            </div>
            <?php if (!empty($notificaciones)): ?>
              <?php foreach ($notificaciones as $n): ?>
                <li class="py-3 px-2 <?= $n['estado'] === 'no_leida' ? 'bg-purple-50' : 'opacity-60' ?> rounded-lg mb-2 shadow-sm">
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-gray-800 leading-snug">
                    <?= htmlspecialchars(str_replace('profesor', 'facilitador', $n['mensaje'])) ?>
                    </span>                    
                    <div class="flex justify-between items-center text-xs text-gray-500">
                    <span><?= $n['fecha'] ?></span>
                    <?php if ($n['estado'] === 'no_leida'): ?>
                        <button
                        type="button"
                        class="marcar-leida-btn text-green-600 hover:underline"
                        data-id="<?= $n['id'] ?>">
                        Marcar como leída
                        </button>
                    <?php endif; ?>
                    </div>
                </div>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </li>

    <!-- Settings Section -->
    <li class="sidebar-item group w-full overflow-hidden rounded-lg border-l border-transparent bg-white transition-all duration-300">
      <button class="flex items-center gap-2.5 px-3 py-2 text-left text-blue-800 transition-all active:scale-95 w-full sidebar-item-button">
        <div class="rounded-lg border-2 border-blue-300 bg-blue-100 p-0.1">
          <svg class="w-6 h-6" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" stroke-linejoin="round" stroke-linecap="round"></path>
            <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" stroke-linejoin="round" stroke-linecap="round"></path>
          </svg>
        </div>
        <span class="font-semibold whitespace-nowrap">Configuración</span>
      </button>
      <div class="expandable-content max-h-0 overflow-hidden transition-all duration-300 ease-out">
        <div class="overflow-hidden">
          <ul class="divide-y divide-gray-200 p-4 pt-0">
            <li class="py-2">
              <div class="flex items-center justify-between">
                <button class="cursor-pointer font-semibold text-gray-800 hover:text-blue-600">
                  Preferencias del Sistema
                </button>
                <div class="text-sm text-gray-500 transition-all peer-hover:translate-x-1">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"></path>
                  </svg>
                </div>
              </div>
              <div class="text-xs text-gray-500">Ajustes por defecto / Perfil</div>
            </li>
            <li class="py-1">
              <div class="flex items-center justify-between">
                <button class="cursor-pointer font-semibold text-gray-800 hover:text-blue-600">
                  Tema
                </button>
                <div class="text-sm text-gray-500 transition-all peer-hover:translate-x-1">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"></path>
                  </svg>
                </div>
              </div>
              <div class="text-xs text-gray-500">Modo Claro / Oscuro</div>
            </li>
          </ul>
        </div>
      </div>
    </li>

    <!-- Profile Section -->
    <li class="sidebar-item group w-full overflow-hidden rounded-lg border-l border-transparent bg-white transition-all duration-300">
      <button class="flex items-center gap-2.5 px-3 py-2 text-left text-yellow-800 transition-all active:scale-95 w-full sidebar-item-button">
        <div class="rounded-lg border-0 border-yellow-300 bg-yellow-100 p-0.1">
                <div class="w-7 h-7 flex items-center justify-center">
                <img src="/icons/usuario.png" alt="Perfil"
                    class="w-8 h-8 rounded-full object-cover" />
                </div>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
          </svg>
        </div>
        <span class="font-semibold whitespace-nowrap">Perfil</span>
      </button>
      <div class="expandable-content max-h-0 overflow-hidden transition-all duration-300 ease-out">
        <div class="overflow-hidden">
          <ul class="divide-y divide-gray-200 p-4 pt-0">
            <li class="py-2">
              <div class="flex items-center justify-between">
                <a href="/?page=ver_perfil" class="cursor-pointer font-semibold text-gray-800 hover:text-blue-600">
                  Ver mi Perfil
                </a>
                <div class="text-sm text-gray-500 transition-all peer-hover:translate-x-1">
                  <svg xmlns="http://www.w3.org/2000/svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"></path>
                  </svg>
                </div>
              </div>
              <div class="text-xs text-gray-500">Información personal y de cuenta</div>
            </li>
            <li class="py-1">
              <div class="flex items-center justify-between">
                <a href="/?page=editar_perfil" class="cursor-pointer font-semibold text-gray-800 hover:text-blue-600">
                  Editar Perfil
                </a>
                <div class="text-sm text-gray-500 transition-all peer-hover:translate-x-1">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"></path>
                  </svg>
                </div>
              </div>
              <div class="text-xs text-gray-500">Actualizar datos</div>
            </li>
          </ul>
        </div>
      </div>
    </li>

  </ul>
</div>

<!-- Enlaza tu archivo JavaScript -->
<script src="/js/sidebar_menu.js"></script>
