<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/db.php';

// Asegurar token CSRF para formularios AJAX
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mustChangePassword = !empty($_SESSION['must_change_password']);

$usuario = $_SESSION['usuario'] ?? null;
$usuario_id = $usuario['id'] ?? null;
$rol_id = $usuario['rol_id'] ?? null;
$tip_contrato = $usuario['tip_contrato'] ?? null;

// Asegurar genero en sesión para saludos (fallback sin re-login)
if ($usuario_id && empty($_SESSION['usuario']['genero'])) {
    try {
        $pdoTmp = Database::conectar();
        $stG = $pdoTmp->prepare("SELECT genero FROM usuarios WHERE id = ? LIMIT 1");
        $stG->execute([$usuario_id]);
        $gen = $stG->fetchColumn();
        if ($gen !== false && $gen !== null && $gen !== '') {
            $_SESSION['usuario']['genero'] = $gen;
            $usuario['genero'] = $gen;
        }
    } catch (Exception $e) { /* noop */ }
}

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
        $miFacilitadorId = null;
        try {
            $stFac = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario_id = ?");
            $stFac->execute([$usuario_id]);
            $miFacilitadorId = ($stFac->fetchColumn() ?: null);
        } catch (PDOException $eFac1) {
            if ($eFac1->getCode() !== '42S22') { throw $eFac1; }
            $stFac = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario = ?");
            $stFac->execute([$usuario_id]);
            $miFacilitadorId = ($stFac->fetchColumn() ?: null);
        }
        // Preferir ordenar por creado_en; fallback a id. Soportar usuario_id o usuario
        try {
            $stmt = $pdo->prepare("SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY creado_en DESC LIMIT 50");
            $stmt->execute([$usuario_id]);
        } catch (PDOException $eOrder) {
            if ($eOrder->getCode() !== '42S22') { throw $eOrder; }
            try {
                $stmt = $pdo->prepare("SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY id DESC LIMIT 50");
                $stmt->execute([$usuario_id]);
            } catch (PDOException $eUserId) {
                if ($eUserId->getCode() !== '42S22') { throw $eUserId; }
                try {
                    $stmt = $pdo->prepare("SELECT * FROM notificaciones WHERE usuario = ? ORDER BY creado_en DESC LIMIT 50");
                    $stmt->execute([$usuario_id]);
                } catch (PDOException $eUsuario) {
                    if ($eUsuario->getCode() !== '42S22') { throw $eUsuario; }
                    $stmt = $pdo->prepare("SELECT * FROM notificaciones WHERE usuario = ? ORDER BY id DESC LIMIT 50");
                    $stmt->execute([$usuario_id]);
                }
            }
        }
        $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Contar no leídas soportando leido/estado y usuario_id/usuario
        try {
            $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND estado_id = 1");
            $stmtTotal->execute([$usuario_id]);
        } catch (PDOException $eEstadoId) {
            if ($eEstadoId->getCode() !== '42S22') { throw $eEstadoId; }
            $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND (leido = 0 OR leido IS NULL)");
            $stmtTotal->execute([$usuario_id]);
        }

        if (!isset($stmtTotal)) {
            $stmtTotal = null;
        }

        if (!$stmtTotal) {
            try {
                $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND (estado = 'no_leida' OR estado IS NULL)");
                $stmtTotal->execute([$usuario_id]);
            } catch (PDOException $eCount2) {
                if ($eCount2->getCode() !== '42S22') { throw $eCount2; }
                try {
                    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario = ? AND (leido = 0 OR leido IS NULL)");
                    $stmtTotal->execute([$usuario_id]);
                } catch (PDOException $eCount3) {
                    if ($eCount3->getCode() !== '42S22') { throw $eCount3; }
                    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario = ? AND (estado = 'no_leida' OR estado IS NULL)");
                    $stmtTotal->execute([$usuario_id]);
                }
            }
        }

        $totalNoLeidas = $stmtTotal ? (int)$stmtTotal->fetchColumn() : 0;
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
  <link rel="icon" type="image/x-icon" href="/favicon.ico?v=3">
  <link rel="icon" href="/favicon.ico?v=3">
  <!-- PNG favicon and Apple Touch (recomendado por navegadores) -->
  <link rel="icon" type="image/png" href="/icons/logo_sistem.png?v=3">
  <link rel="apple-touch-icon" href="/icons/logo_sistem.png?v=3">
  <!-- Optional PNG fallbacks -->
  <link rel="icon" type="image/png" sizes="32x32" href="/icons/logo_sistem.png?v=3">
  <link rel="icon" type="image/png" sizes="16x16" href="/icons/logo_sistem.png?v=3">
  <link rel="apple-touch-icon" sizes="180x180" href="/icons/logo_sistem.png?v=3">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/Componentes/encabezado.css?v=20251127-1">
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
                <option value="profesor" title="Facilitadores/Instructores">Facil./Instr.</option>
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
              <a role="menuitem" href="/?page=administradores&action=crear">Crear Administrador</a>
              <a role="menuitem" href="/?page=asistentes&action=crear">Crear Asistente</a>
              <a role="menuitem" href="/?page=facilitadores&action=crear">Crear Facilitador/Instructor</a>
              <a role="menuitem" href="/?page=aprendices&action=crear">Crear Aprendiz</a>
              <a role="menuitem" href="/?page=colegios&action=crear">Registrar Colegios</a>
              <a role="menuitem" href="/?page=cursos">Registrar Cursos</a>
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
          <?php $__pg = $_GET['page'] ?? ''; $__showToggle = ($__pg === 'dashboard'); ?>
          <?php if ($__showToggle): ?>
          <!-- Cambiar vista (Asistente) al lado izquierdo de notificaciones -->
          <button id="view-toggle-btn" class="view-toggle-btn" title="Cambiar vista">
            <span class="icon-grid"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="7 17 17 7" /><polyline points="17 11 17 7 13 7" /><polyline points="7 13 7 17 11 17" /></svg></span>
            <span class="icon-eye"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="17 7 7 17" /><polyline points="7 13 7 17 11 17" /><polyline points="17 11 17 7 13 7" /></svg></span>
          </button>
          <?php endif; ?>
        <?php endif; ?>
        <!-- Notificaciones -->
        <button type="button" class="notifications-btn" id="btn-notificaciones" aria-expanded="false" aria-controls="notifications-panel" title="Notificaciones">
          <i class="fa-regular fa-bell" style="font-size:18px;"></i>
          <?php if ((int)$totalNoLeidas > 0): ?>
            <span class="notifications-badge" aria-label="No leídas"><?= (int)$totalNoLeidas ?></span>
          <?php endif; ?>
        </button>

        <div id="notifications-panel" class="notifications-panel" role="region" aria-label="Notificaciones">
          <div class="notifications-header">Notificaciones</div>
          <div class="notifications-list">
            <?php if (empty($notificaciones)): ?>
              <div class="empty-notifications">No tienes notificaciones nuevas</div>
            <?php else: ?>
              <?php foreach ($notificaciones as $n): ?>
                <?php 
                  // Determinar si está no leída: preferir columna leido (0/1)
                  $isUnread = true;
                  if (array_key_exists('estado_id', $n)) {
                    $isUnread = (int)$n['estado_id'] === 1;
                  } elseif (array_key_exists('leido', $n)) {
                    $isUnread = (string)$n['leido'] === '0' || is_null($n['leido']);
                  } elseif (array_key_exists('estado', $n)) {
                    $isUnread = !isset($n['estado']) || $n['estado'] === 'no_leida';
                  }
                ?>
                <div class="notification-item <?= $isUnread ? 'unread' : '' ?>" data-id="<?= (int)($n['id'] ?? 0) ?>">
                  <div class="notification-content">
                    <?php 
                      $msg = trim((string)($n['mensaje'] ?? ''));
                      if ($msg === '' && isset($n['titulo'])) { $msg = (string)$n['titulo']; }
                      $msg = str_replace('profesor', 'facilitador', $msg);
                      $msg = str_replace(['Aprobar/Rechazar','Aprobar / Rechazar','Aprobar - Rechazar'], 'Revisar', $msg);
                      $re = '/\bde\s+(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2})(?::\d{2})?\s+a\s+(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2})(?::\d{2})?/i';
                      $msg = preg_replace_callback($re, function($m){
                        try {
                          $dtI = new DateTime($m[1] . ' ' . $m[2]);
                          $dtF = new DateTime($m[3] . ' ' . $m[4]);
                          if ($dtI && $dtF && $dtI->format('Y-m-d') === $dtF->format('Y-m-d')) {
                            return $dtI->format('d-m-Y') . ' de ' . $dtI->format('h:i') . ' a ' . $dtF->format('h:i');
                          }
                        } catch (Throwable $e) {}
                        return $m[0];
                      }, $msg);
                      // Permitir solo <a> y quitar el resto de etiquetas
                      $msgSafe = strip_tags($msg, '<a>');
                      // Atributos seguros en anchors: abrir en la misma pestaña si es interno; nueva pestaña solo externos
                      $msgSafe = preg_replace_callback(
                        '#<a\s+([^>]*href\s*=\s*\"([^\"]*)\"[^>]*)>#i',
                        function($m){
                          $attrs = $m[1];
                          $href  = $m[2];
                          // Quitar target/rel existentes para imponer comportamiento deseado
                          $attrs = preg_replace('/\s*target\s*=\s*\"[^\"]*\"/i', '', $attrs);
                          $attrs = preg_replace('/\s*rel\s*=\s*\"[^\"]*\"/i', '', $attrs);
                          $isExternal = preg_match('#^https?://#i', $href);
                          $extra = $isExternal ? ' target="_blank" rel="noopener noreferrer" class="link"' : ' class="link"';
                          return '<a ' . trim($attrs) . $extra . '>';
                        },
                        $msgSafe
                      );
                      // Estilizar fecha/hora compacta y aula como chips
                      $msgStyled = $msgSafe;
                      $msgStyled = preg_replace_callback(
                        '/\b(\d{2}-\d{2}-\d{4})\s+de\s+(\d{1,2}:\d{2})\s+a\s+(\d{1,2}:\d{2})\b/u',
                        function($m){
                          return '<span class="notif-chip notif-chip--date">'.htmlspecialchars($m[1]).'</span>'
                               . ' de '
                               . '<span class="notif-chip notif-chip--time">'.htmlspecialchars($m[2]).'</span>'
                               . ' a '
                               . '<span class="notif-chip notif-chip--time">'.htmlspecialchars($m[3]).'</span>';
                        },
                        $msgStyled
                      );
                      $msgStyled = preg_replace(
                        '/\ben\s+([^\.]+)(\.)/u',
                        'en <span class="notif-chip notif-chip--room">$1</span>$2',
                        $msgStyled
                      );
                      echo $msgStyled;
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
                  <?php elseif ($isUnread): ?>
                    <?php 
                      $msgText = trim((string)($n['mensaje'] ?? ''));
                      $solicitud = null;
                      if ($miFacilitadorId) {
                        $fichaNombreDetectada = null;
                        $needle = 'quiere compartir la ficha';
                        $pos = stripos($msgText, $needle);
                        if ($pos !== false) {
                          $rest = substr($msgText, $pos + strlen($needle));
                          $rest = trim($rest);
                          $hasta = stripos($rest, 'contigo');
                          $namePart = $hasta !== false ? substr($rest, 0, $hasta) : $rest;
                          $fichaNombreDetectada = trim($namePart);
                        }
                        if ($fichaNombreDetectada !== null && $fichaNombreDetectada !== '') {
                          $fichaIdTmp = null;
                          try {
                            $stF = $pdo->prepare("SELECT id FROM fichas WHERE nombre = ? LIMIT 1");
                            $stF->execute([$fichaNombreDetectada]);
                            $fichaIdTmp = ($stF->fetchColumn() ?: null);
                          } catch (Exception $eF) { $fichaIdTmp = null; }
                          if ($fichaIdTmp) {
                            $liderFacId = null;
                            try {
                              $q = "SELECT facilitador_lider FROM fichas_compartidas WHERE ficha = ? AND facilitador_compartido = ? AND (estado = 'Pendiente' OR estado IS NULL) LIMIT 1";
                              $stS = $pdo->prepare($q);
                              $stS->execute([$fichaIdTmp, $miFacilitadorId]);
                              $liderFacId = ($stS->fetchColumn() ?: null);
                            } catch (PDOException $eS1) {
                              if ($eS1->getCode() !== '42S22') { throw $eS1; }
                              try {
                                $q = "SELECT facilitador_lider FROM fichas_compartidas WHERE ficha_id = ? AND facilitador_compartido = ? AND (estado = 'Pendiente' OR estado_id = 1) LIMIT 1";
                                $stS = $pdo->prepare($q);
                                $stS->execute([$fichaIdTmp, $miFacilitadorId]);
                                $liderFacId = ($stS->fetchColumn() ?: null);
                              } catch (PDOException $eS2) {
                                if ($eS2->getCode() !== '42S22') { throw $eS2; }
                                try {
                                  $q = "SELECT profesor_lider_id FROM fichas_compartidas WHERE ficha_id = ? AND profesor_compartido_id = ? AND (estado = 'Pendiente' OR estado_id = 1) LIMIT 1";
                                  $stS = $pdo->prepare($q);
                                  $stS->execute([$fichaIdTmp, $usuario_id]);
                                  $liderFacId = ($stS->fetchColumn() ?: null);
                                } catch (PDOException $eS3) { $liderFacId = null; }
                              }
                            }
                            if ($liderFacId) {
                              $solicitud = [
                                'ficha_id' => (int)$fichaIdTmp,
                                'lider_facilitador_id' => (int)$liderFacId,
                                'receptor_facilitador_id' => (int)$miFacilitadorId,
                                'receptor_usuario_id' => (int)$usuario_id
                              ];
                            }
                          }
                          // Fallback: si no se detectó por nombre, tomar la última solicitud pendiente del receptor
                          if (!$solicitud) {
                            try {
                              $q = "SELECT ficha, facilitador_lider FROM fichas_compartidas WHERE facilitador_compartido = ? AND (estado = 'Pendiente' OR estado_id = 1 OR estado IS NULL) ORDER BY creado_en DESC LIMIT 1";
                              $stLast = $pdo->prepare($q);
                              $stLast->execute([$miFacilitadorId]);
                              $rowLast = $stLast->fetch(PDO::FETCH_ASSOC);
                              if ($rowLast && isset($rowLast['ficha']) && isset($rowLast['facilitador_lider'])) {
                                $solicitud = [
                                  'ficha_id' => (int)$rowLast['ficha'],
                                  'lider_facilitador_id' => (int)$rowLast['facilitador_lider'],
                                  'receptor_facilitador_id' => (int)$miFacilitadorId,
                                  'receptor_usuario_id' => (int)$usuario_id
                                ];
                              }
                            } catch (Exception $_) { /* noop */ }
                          }
                        }
                      }
                    ?>
                    <?php if ($solicitud): ?>
                      <div class="notification-buttons">
                        <button type="button" class="btn-aceptar" onclick='responderSolicitud(<?= json_encode($solicitud, JSON_UNESCAPED_UNICODE) ?>, "aceptar", <?= (int)$n["id"] ?>)'>Aceptar</button>
                        <button type="button" class="btn-rechazar" onclick='responderSolicitud(<?= json_encode($solicitud, JSON_UNESCAPED_UNICODE) ?>, "rechazar", <?= (int)$n["id"] ?>)'>Rechazar</button>
                      </div>
                    <?php endif; ?>
                  <?php elseif (!empty($n['botones_accion']) && isset($n['estado']) && $n['estado'] === 'leida'): ?>
                    <div class="notification-buttons-disabled">
                      <span class="btn-disabled">Procesado</span>
                    </div>
                  <?php endif; ?>

                  <div class="notification-actions">
                    <?php 
                      $fechaTxt = '';
                      // Preferir creado_en; fallback a fecha
                      $fechaBase = $n['creado_en'] ?? ($n['fecha'] ?? null);
                      if (!empty($fechaBase) && strtotime($fechaBase)) {
                        $fechaTxt = date('d/m/Y H:i', strtotime($fechaBase));
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
              <a role="menuitem" href="/?page=facilitadores">Facilitadores/Instructores</a>
              <a role="menuitem" href="/?page=aprendices">Aprendices</a>
              <a role="menuitem" href="/?page=colegios">Colegios</a>
              <a role="menuitem" href="/?page=cursos">Cursos</a>
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
  let payload;
  if (typeof solicitudId === 'object' && solicitudId) {
    payload = Object.assign({}, solicitudId, { respuesta });
  } else {
    payload = { solicitud_id: solicitudId, respuesta };
  }
  fetch('index.php?page=responder_solicitud', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(d => {
    const msg = (d && d.message) ? String(d.message) : '';
    const yaProc = /ya\s*procesad/i.test(msg);
    if (d && d.success) {
      markAsRead(notificationId);
    } else if (yaProc) {
      // Considerar como éxito benigno: ya se había atendido
      markAsRead(notificationId);
      showGlobalNotice('Solicitud ya procesada', 'Esta solicitud ya fue atendida previamente.');
    } else {
      const fallback = msg || 'No se pudo procesar la solicitud en este momento.';
      showGlobalNotice('No se pudo procesar', fallback);
    }
  })
  .catch(() => showGlobalNotice('No se pudo procesar', 'Error de red al procesar la solicitud.'));
}
</script>

<script>
// Marcar como leída al hacer clic en enlaces de notificación (en la misma pestaña)
(function(){
  document.addEventListener('click', function(ev){
    const a = ev.target.closest('.notification-item a');
    if (!a) return;
    const item = a.closest('.notification-item');
    const nid = item && item.getAttribute('data-id');
    const href = a.getAttribute('href') || '#';
    // Interceptar siempre para navegar en la misma pestaña
    ev.preventDefault();
    if (nid && item && item.classList.contains('unread')) {
      const fd = new FormData();
      fd.append('notificacion_id', nid);
      fetch('/?page=marcar_notificacion', { method: 'POST', body: fd })
        .catch(function(){ /* noop */ })
        .finally(function(){ window.location.href = href; });
    } else {
      window.location.href = href;
    }
  }, true);
})();
</script>

<script>
function showGlobalNotice(title, text){
  const overlay = document.getElementById('global-success-overlay');
  if (!overlay) { alert(title + ': ' + text); return; }
  const card = overlay.querySelector('.gsuccess-card');
  const bar  = overlay.querySelector('#gs_bar');
  const t    = overlay.querySelector('#gs_title');
  const p    = overlay.querySelector('#gs_text');
  t.textContent = title || 'Aviso';
  p.textContent = text || '';
  overlay.style.display = 'flex';
  requestAnimationFrame(function(){ card.classList.add('show'); });
  const DURATION = 3000;
  bar.style.transitionDuration = DURATION + 'ms';
  bar.style.width = '0%';
  requestAnimationFrame(function(){ bar.style.width = '100%'; });
  setTimeout(function(){ card.classList.remove('show'); setTimeout(()=>{ overlay.style.display = 'none'; }, 220); }, DURATION + 150);
}
</script>
<!-- Modal global de éxito (reutilizable) -->
<style>
  .gsuccess-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.35); display: none; align-items: center; justify-content: center; z-index: 2000; }
  .gsuccess-card { width: min(92vw, 520px); background: #fff; border-radius: 14px; box-shadow: 0 20px 45px rgba(2,6,23,.18); padding: 20px 22px; transform: translateY(8px) scale(.98); opacity: 0; transition: all .25s ease; text-align: center; }
  .gsuccess-card.show { transform: translateY(0) scale(1); opacity: 1; }
  .gsuccess-icon { width: 64px; height: 64px; border-radius: 50%; margin: 8px auto 12px; display: grid; place-items: center; background: #e8f8ef; color: #16a34a; }
  .gsuccess-icon svg { width: 32px; height: 32px; }
  .gsuccess-title { font-size: 20px; font-weight: 700; color: #0f172a; margin: 4px 0 6px; }
  .gsuccess-text { font-size: 14px; color: #334155; margin: 0 4px 6px; }
  .gsuccess-progress { height: 4px; width: 100%; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin-top: 10px; }
  .gsuccess-bar { height: 100%; width: 0%; background: linear-gradient(90deg, #22c55e, #16a34a); transition: width linear; }
  .gsuccess-hide { display: none !important; }
</style>
<div id="global-success-overlay" class="gsuccess-overlay" role="dialog" aria-live="polite" aria-modal="true">
  <div class="gsuccess-card" role="document">
    <div class="gsuccess-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 6L9 17l-5-5"/>
      </svg>
    </div>
    <div class="gsuccess-title" id="gs_title">Registro completado</div>
    <div class="gsuccess-text" id="gs_text">La operación se realizó correctamente.</div>
    <div class="gsuccess-progress" aria-hidden="true"><div class="gsuccess-bar" id="gs_bar"></div></div>
  </div>
  <button class="gsuccess-hide" aria-label="Cerrar"></button>
  <!-- botón oculto para accesibilidad/escape si se requiere -->
  
</div>
<script>
  (function(){
    const qs = new URLSearchParams(window.location.search);
    const hasSuccess = (qs.get('success') === '1') || (qs.get('registro_facilitador') === 'ok') || (qs.get('registro') === 'ok');
    if (!hasSuccess) return;

    const overlay = document.getElementById('global-success-overlay');
    if (!overlay) return;
    const card = overlay.querySelector('.gsuccess-card');
    const bar  = overlay.querySelector('#gs_bar');
    const title = overlay.querySelector('#gs_title');
    const text  = overlay.querySelector('#gs_text');

    // Mensajes contextualizados básicos
    if (qs.get('registro_facilitador') === 'ok') {
      title.textContent = '¡Registro enviado!';
      text.textContent  = 'Hemos recibido tu registro. Debes esperar a que un administrador lo revise y active o bloquee tu cuenta. Te notificaremos cuando esté listo.';
    } else if (qs.get('registro') === 'ok') {
      title.textContent = '¡Registro exitoso!';
      text.textContent  = 'Tu cuenta fue creada correctamente.';
    } else {
      title.textContent = '¡Registro completado!';
      text.textContent  = 'La información se guardó con éxito.';
    }

    const DURATION = 2600; // ms
    overlay.style.display = 'flex';
    requestAnimationFrame(function(){ card.classList.add('show'); });

    // Limpiar parámetros para evitar reaparición del modal al recargar
    try {
      const url = new URL(window.location.href);
      ['success','registro_facilitador','registro'].forEach(p=> url.searchParams.delete(p));
      const newUrl = url.pathname + (url.search ? url.search : '') + (url.hash || '');
      window.history.replaceState({}, '', newUrl);
    } catch(_) {}

    // barra de progreso
    bar.style.transitionDuration = DURATION + 'ms';
    requestAnimationFrame(function(){ bar.style.width = '100%'; });

    const hide = () => {
      card.classList.remove('show');
      setTimeout(()=>{ overlay.style.display = 'none'; }, 220);
    };

    // Autocierre
    setTimeout(hide, DURATION + 150);

    // Cerrar con ESC o clic en overlay
    overlay.addEventListener('click', function(e){ if (e.target === overlay) hide(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') hide(); });
  })();
</script>

<!-- Modal BLOQUEANTE: cambio de contraseña inicial -->
<style>
  .cp-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.55); display: none; align-items: center; justify-content: center; z-index: 3000; }
  .cp-card { width: min(92vw, 560px); background: #fff; border-radius: 16px; box-shadow: 0 24px 60px rgba(2,6,23,.28); padding: 20px 22px; }
  .cp-title { font-weight: 800; font-size: 22px; margin: 2px 0 6px; color:#0f172a }
  .cp-sub { color:#475569; font-size:14px; margin-bottom: 12px }
  .cp-policy { background:#f8fafc; border:1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; font-size: 13px; color:#334155; margin-bottom: 12px }
  .cp-group { margin: 10px 0 }
  .cp-group label { display:block; font-weight: 700; color:#000; margin: 0 0 6px }
  .cp-input { width:100%; padding: 10px 12px; border-radius: 10px; border: 2px solid #22c55e; font-size: 15px }
  .cp-btn { width:100%; padding: 12px 16px; border-radius: 12px; border: 0; background:#00304D; color: #fff; font-weight: 800; font-size: 16px; margin-top: 10px }
  .cp-error { margin:8px 0 0; color:#dc2626; font-size: 13px; display:none }
  body.cp_locked { overflow: hidden; }
  .cp-overlay * { pointer-events: auto; }
</style>
<div id="force-password-overlay" class="cp-overlay" role="dialog" aria-modal="true" aria-labelledby="cp_title" aria-describedby="cp_desc">
  <div class="cp-card">
    <div id="cp_title" class="cp-title">Cambia tu contraseña</div>
    <div id="cp_desc" class="cp-sub">Por seguridad, debes crear una nueva contraseña antes de continuar.</div>
    <div class="cp-policy">
      Debe cumplir todos los requisitos:
      <ul style="margin:8px 0 0 18px;">
        <li>Al menos 1 mayúscula</li>
        <li>Al menos 1 minúscula</li>
        <li>Al menos 1 número</li>
        <li>Al menos 1 carácter especial</li>
        <li>Mínimo 9 caracteres</li>
      </ul>
    </div>
    <form id="force-password-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
      <div class="cp-group">
        <label for="cp_new">Nueva contraseña</label>
        <input type="password" id="cp_new" class="cp-input" autocomplete="new-password" required>
      </div>
      <div class="cp-group">
        <label for="cp_confirm">Confirmar contraseña</label>
        <input type="password" id="cp_confirm" class="cp-input" autocomplete="new-password" required>
        <div id="cp_error" class="cp-error"></div>
      </div>
      <button type="submit" class="cp-btn">Guardar y continuar</button>
    </form>
  </div>
  <button class="gsuccess-hide" aria-label="Cerrar"></button>
</div>
<script>
  (function(){
    const MUST_CHANGE = <?= $mustChangePassword ? '1' : '0' ?>;
    if (String(MUST_CHANGE) !== '1') return;

    const overlay = document.getElementById('force-password-overlay');
    const form    = document.getElementById('force-password-form');
    const p1      = document.getElementById('cp_new');
    const p2      = document.getElementById('cp_confirm');
    const errBox  = document.getElementById('cp_error');
    const policy  = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{9,}$/;

    // Mostrar modal y bloquear scroll/interacción del fondo
    overlay.style.display = 'flex';
    document.body.classList.add('cp_locked');

    form.addEventListener('submit', function(e){
      e.preventDefault();
      errBox.style.display = 'none';
      errBox.textContent = '';
      const a = String(p1.value || '');
      const b = String(p2.value || '');
      if (a !== b) { errBox.textContent = 'Las contraseñas no coinciden.'; errBox.style.display = 'block'; return; }
      if (!policy.test(a)) { errBox.textContent = 'Debe tener mínimo 9 caracteres, incluir mayúscula, minúscula, número y carácter especial.'; errBox.style.display = 'block'; return; }

      const fd = new FormData();
      fd.append('csrf_token', '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>');
      fd.append('password_nueva', a);
      fd.append('password_confirmacion', b);
      fetch('/?page=cambiar_password_inicial_guardar', { method: 'POST', body: fd, credentials: 'include', redirect: 'follow' })
        .then(function(){ window.location.reload(); })
        .catch(function(){ errBox.textContent = 'Error de red. Intenta nuevamente.'; errBox.style.display = 'block'; });
    });

    // Evitar cerrar con ESC/clic fuera: modal es bloqueante
    overlay.addEventListener('click', function(e){ e.stopPropagation(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') e.preventDefault(); }, true);
  })();
</script>

<!-- Fix para scroll eliminado - estaba causando bucles infinitos -->
<!-- <link rel="stylesheet" href="/css/scroll_fix.css">
<script src="/js/scroll_fix.js"></script> -->
