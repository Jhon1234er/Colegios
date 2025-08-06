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

<header>
  <div class="header-content">
    <span class="logo">Sistem Scholl</span>
    <div class="flex items-center gap-6">
      <?php if ($usuario): ?>
        <div class="header-buttons">
          <div class="menu">
            <?php if ($rol_id === 1): ?>
              <form class="buscador-header">
                <select class="select-buscador-header">
                  <option value="colegio">Colegios</option>
                  <option value="profesor">Facilitadores</option>
                  <option value="estudiante">Aprendices</option>
                </select>
                <input type="text" class="input-buscador-header" placeholder="Buscar..." />
                <button type="submit" class="button-buscador-header">Buscar</button>
              </form>
              <div class="dropdown">
                <button class="dropbtn">Registros</button>
                <div class="dropdown-content">
                  <a href="/?page=profesores&action=crear">Crear Facilitador</a>
                  <a href="/?page=estudiantes&action=crear">Crear Aprendiz</a>
                  <a href="/?page=colegios&action=crear">Registrar Colegio</a>
                  <a href="/?page=crear_materia">Registrar Materia / Ficha</a>
                </div>
              </div>
              <div class="dropdown">
                <button class="dropbtn">Listas</button>
                <div class="dropdown-content">
                  <a href="/?page=profesores">Facilitadores</a>
                  <a href="/?page=estudiantes">Aprendices</a>
                  <a href="/?page=colegios">Colegios</a>
                </div>
              </div>
              <a href="/?page=dashboard" class="navlink">Inicio</a>
            <?php elseif ($rol_id === 2): ?>
              <a href="/?page=dashboard_profesor" class="navlink">Mis Fichas</a>
            <?php elseif ($rol_id === 3): ?>
              <a href="/?page=mis_asistencias" class="navlink">Mis Asistencias</a>
            <?php endif; ?>

            <form action="/logout.php" method="post" style="display:inline;">
              <button type="submit" class="Btn" title="Cerrar sesión">
                <div class="sign">
                  <svg viewBox="0 0 512 512">
                    <path d="M377.9 105.9L500.7 228.7c7.2 7.2 11.3 17.1 11.3 27.3L377.9 406.1c-6.4 6.4-15 9.9-24 9.9
                    c-18.7 0-33.9-15.2-33.9-33.9l0-62.1-128 0c-17.7 0-32-14.3-32-32l0-64c0-17.7 14.3-32 32-32l128 0
                    0-62.1c0-18.7 15.2-33.9 33.9-33.9c9 0 17.6 3.6 24 9.9zM160 96L96 96c-17.7 0-32 14.3-32 32l0 256
                    c0 17.7 14.3 32 32 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-53 0-96-43-96-96L0 128C0
                    75 43 32 96 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32z"></path>
                  </svg>
                </div>
                <div class="text">Cerrar sesión</div>
              </button>
            </form>
          </div>
        </div>

        <div class="relative group w-14 hover:w-64 transition-all duration-300">
          <div onclick="toggleDropdown()" class="flex items-center gap-2 p-2 cursor-pointer bg-white border rounded-lg relative">
            <div class="rounded-lg border-2 border-purple-300 bg-purple-100 p-1 relative">
              <img src="/icons/campana.png" alt="Notificaciones" class="w-6 h-6 object-contain">
              <?php if ($totalNoLeidas > 0): ?>
                <span class="absolute -top-2 -right-2 bg-red-600 text-white text-xs font-bold px-2 py-0.5 rounded-full shadow">
                  <?= $totalNoLeidas ?>
                </span>
              <?php endif; ?>
            </div>
            <span class="hidden group-hover:inline font-semibold text-purple-800">Notificaciones</span>
            </div>

            <div id="dropdown-notificaciones" class="hidden absolute right-0 mt-2 w-72 bg-white shadow-lg rounded-lg border z-50 max-h-80 overflow-y-auto">
              <div id="sin-notificaciones" class="<?= empty($notificaciones) ? '' : 'hidden' ?> p-4 text-gray-500 text-sm text-center">
                No tienes notificaciones.
              </div>

              <?php if (!empty($notificaciones)): ?>
                <ul id="lista-notificaciones" class="divide-y divide-gray-200">
                  <?php foreach ($notificaciones as $n): ?>
                    <li class="p-4 hover:bg-gray-50 <?= $n['estado'] === 'no_leida' ? 'bg-purple-50' : 'opacity-60' ?>">
                      <p class="text-sm text-gray-700 font-medium"><?= htmlspecialchars($n['mensaje']) ?></p>
                      <p class="text-xs text-gray-400"><?= $n['fecha'] ?></p>
                      <?php if ($n['estado'] === 'no_leida'): ?>
                        <button 
                          type="button" 
                          class="marcar-leida-btn text-xs text-green-600 hover:underline" 
                          data-id="<?= $n['id'] ?>">
                          Marcar como leída
                        </button>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</header>

<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="/js/encabezado.js"></script>
<script>
document.querySelector('.notificaciones-icono')?.addEventListener('click', () => {
  fetch('/marcar_notificaciones.php', { method: 'POST' });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const badge = document.querySelector('.notificaciones-badge');
  const lista = document.getElementById('lista-notificaciones');
  const mensajeVacio = document.getElementById('sin-notificaciones');

  document.querySelectorAll('.marcar-leida-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;

      const formData = new FormData();
      formData.append('notificacion_id', id);

      try {
        const res = await fetch('/marcar_notificaciones.php', {
          method: 'POST',
          body: formData
        });

        const text = await res.text();

        try {
          const data = JSON.parse(text);

          if (data.success) {
            // Oculta la notificación
            const item = btn.closest('li');
            item.classList.add('opacity-60');
            btn.remove();

            // Actualiza el contador del badge
            if (badge) {
              let count = parseInt(badge.textContent);
              if (!isNaN(count)) {
                count--;
                if (count <= 0) {
                  badge.remove();
                } else {
                  badge.textContent = count;
                }
              }
            }

            // Verifica si quedan notificaciones activas
            if (lista && lista.querySelectorAll('li:not(.opacity-60)').length === 0) {
              mensajeVacio.classList.remove('hidden');
            }
          } else {
            console.error("Error en respuesta:", data);
          }
        } catch (parseError) {
          console.error("Respuesta no es JSON válido:", text);
        }
      } catch (err) {
        console.error("Error al marcar notificación:", err);
      }
    });
  });
});

// Dropdown toggle
function toggleDropdown() {
  const dropdown = document.getElementById("dropdown-notificaciones");
  dropdown.classList.toggle("hidden");
}

// Ocultar si se hace clic fuera del menú
document.addEventListener('click', function(event) {
  const campana = document.querySelector('.group');
  const dropdown = document.getElementById("dropdown-notificaciones");

  if (!campana.contains(event.target)) {
    dropdown.classList.add("hidden");
  }
});
</script>
</body>
</html>
