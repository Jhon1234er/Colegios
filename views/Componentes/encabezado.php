<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/db.php';

$usuario = $_SESSION['usuario'] ?? null;
$usuario_id = $usuario['id'] ?? null;
$rol_id = $usuario['rol_id'] ?? null;

// Mapear rol_id a tipo_usuario textual según tu base de datos
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
    } catch (PDOException $e) {
        error_log("Error al obtener notificaciones: " . $e->getMessage());
        // Puedes mostrar un error si lo deseas:
        // echo "Error al cargar notificaciones.";
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

    <?php if ($usuario): ?>
      <!-- NOTIFICACIONES -->
      <div class="relative group w-14 hover:w-64 transition-all duration-300">
        <div class="flex items-center gap-2 p-2 cursor-pointer bg-white border rounded-lg">
          <div class="rounded-lg border-2 border-purple-300 bg-purple-100 p-1">
            <img src="/icons/campana.png" alt="Notificaciones" class="w-6 h-6 object-contain">
          </div>
          <span class="hidden group-hover:inline font-semibold text-purple-800">Notificaciones</span>
        </div>

        <!-- Contenedor de notificaciones -->
        <div class="absolute left-0 top-full mt-1 w-64 bg-white border rounded-lg shadow-lg opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto transition-opacity duration-300 z-50">
          <ul class="divide-y divide-gray-200 max-h-60 overflow-y-auto p-2">
            <?php if (!empty($notificaciones)): ?>
              <?php foreach ($notificaciones as $noti): ?>
                <li class="py-2 text-sm">
                  <div class="flex justify-between items-center">
                    <div class="text-gray-800"><?= htmlspecialchars($noti['mensaje']) ?></div>
                    <div class="text-xs text-gray-500"><?= date('H:i', strtotime($noti['fecha'])) ?></div>
                  </div>
                </li>
              <?php endforeach; ?>
            <?php else: ?>
              <li class="py-2 text-gray-500 text-sm text-center">Sin notificaciones</li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <!-- MENÚ POR ROL -->
    <div class="header-buttons">
      <div class="menu">
        <?php if ($rol_id === 1): ?>
          <!-- Administrador -->
        <form class="buscador-header flex items-center bg-white border border-gray-300 rounded-full overflow-hidden shadow-sm">
          <select class="select-buscador-header bg-transparent px-2 py-1 text-sm text-gray-700 focus:outline-none">
            <option value="colegio">Colegios</option>
            <option value="profesor">Facilitadores</option>
            <option value="estudiante">Aprendices</option>
          </select>
          <input type="text" class="input-buscador-header px-2 py-1 text-sm focus:outline-none" placeholder="Buscar..." />
          <button type="submit" class="px-3 py-1 text-sm bg-green-600 text-white hover:bg-green-700 transition-all">Buscar</button>
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
          <!-- Profesor -->
          <a href="/?page=dashboard_profesor" class="navlink">Mis Fichas</a>

        <?php elseif ($rol_id === 3): ?>
          <!-- Estudiante -->
          <a href="/?page=mis_asistencias" class="navlink">Mis Asistencias</a>
        <?php endif; ?>

        <!-- Botón cerrar sesión -->
      <form action="/logout.php" method="post" style="display:inline;">
        <button type="submit" class="Btn" title="Cerrar sesión">
          <div class="sign">
            <svg viewBox="0 0 512 512">
              <path d="M377.9 105.9L500.7 228.7c7.2 7.2 11.3 17.1 11.3 27.3L377.9 406.1c-6.4 6.4-15 9.9-24 9.9c-18.7 0-33.9-15.2-33.9-33.9l0-62.1-128 0c-17.7 0-32-14.3-32-32l0-64c0-17.7 14.3-32 32-32l128 0 0-62.1c0-18.7 15.2-33.9 33.9-33.9c9 0 17.6 3.6 24 9.9zM160 96L96 96c-17.7 0-32 14.3-32 32l0 256c0 17.7 14.3 32 32 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-53 0-96-43-96-96L0 128C0 75 43 32 96 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32z"></path>
            </svg>
          </div>
          <div class="text">Cerrar sesión</div>
        </button>
      </form>
      </div>
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

</body>
</html>
