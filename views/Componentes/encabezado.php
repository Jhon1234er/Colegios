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
            <?php if ($rol_id === 1 && (!isset($_GET['page']) || $_GET['page'] === 'dashboard')): ?>
              <div class="buscador-header-container">
                <form class="buscador-header">
                  <select class="select-buscador-header">
                    <option value="colegio">Colegios</option>
                    <option value="profesor">Facilitadores</option>
                    <option value="estudiante">Aprendices</option>
                  </select>
                  <input type="text" class="input-buscador-header" placeholder="Buscar..." />
                  <button type="submit" class="button-buscador-header">Buscar</button>
                </form>
              </div>
            <?php endif; ?>
              </div>
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

        <!-- Aquí se incluye el nuevo menú lateral -->
        <?php include __DIR__ . '/sidebar_menu.php'; ?>
      <?php endif; ?>
    </div>
  </div>
</header>

<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="/js/encabezado.js"></script>

</body>
</html>
