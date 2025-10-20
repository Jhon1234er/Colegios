<?php require __DIR__ . '/../Componentes/encabezado.php'; ?>

<link rel="stylesheet" href="/css/Profesor/dashboard_profesor.css">

<main>
  <?php require_once __DIR__ . '/../../helpers/auth.php'; ?>
  <input type="hidden" id="csrf_token" value="<?= csrf_token() ?>">
  <div class="parent">
    
    <!-- Bienvenida (barra azul superior) -->
    <div class="div9">
      <?php 
        $tip_contrato = strtolower($tip_contrato ?? '');
        $nombre_usuario = htmlspecialchars($_SESSION['usuario']['nombres'] . ' ' . ($_SESSION['usuario']['apellidos'] ?? ''));

        if ($tip_contrato === 'contratista') {
            $rol = 'Facilitador';
        } elseif ($tip_contrato === 'instructor') {
            $rol = 'Instructor';
        } else {
            $rol = 'Profesor';
        }
      ?>
      <p><strong>Bienvenido</strong> <?= $nombre_usuario ?></p>
      <div class="welcome-center">
        <span id="greeting-text">¡Qué bueno verte, <?= htmlspecialchars($_SESSION['usuario']['nombres']) ?>!</span>
        <!-- Mano de contorno en verde (saludando) -->
        <svg class="hand-outline" width="32" height="32" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
          <!-- contorno mano -->
          <path d="M14 22 V9 a2 2 0 1 1 4 0v11
                   M18 21 V7 a2 2 0 1 1 4 0v16
                   M22 21 V8 a2 2 0 1 1 4 0v15
                   M26 23 V12 a2 2 0 1 1 4 0v14
                   M30 26 V16 a2 2 0 1 1 4 0v13
                   C34 36 28 42 21 42
                   C15 42 12 37 12 32
                   V27" />
          <!-- líneas de énfasis (movimiento) -->
          <path d="M36 10 l4 -4 M38 16 l6 -2" class="ho-accent" />
        </svg>
      </div>
      <p><strong>Rol:</strong> <?= $rol ?></p>
    </div>

    <!-- Contenedor principal para tarjetas y filtros -->
    <div class="contenedor-principal-tarjetas">
        <!-- Filtros para las trajetas de fichas -->
      <div class="filtros-cursos"> 
        <div class="filtro">Todos</div> 
        <div class="filtro">Buscar</div> 
        <div class="filtro">Ordenar por nombre</div> 
        <div class="filtro">Tarjeta</div> 
      </div>
        <h3>Mis Fichas</h3>
          <!-- Tarjetas de fichas -->
        <div id="tarjetasFichas" class="contenedor-tarjetas"></div>
        <div id="estudiantesContainer"></div>
        <!-- Registro de asistencia  -->
        <div id="calendarioAsistencia" class="calendario-wrapper"></div>
    </div>
  </div>
</main>

<!-- Modal Compartir Ficha -->
<div class="modal fade" id="modalCompartirFicha" tabindex="-1" aria-labelledby="modalCompartirFichaLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCompartirFichaLabel">Compartir Ficha</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <h6>Ficha: <span id="fichaCompartirNombre"></span></h6>
          <p class="text-muted">Selecciona los profesores con los que deseas compartir esta ficha:</p>
        </div>
        
        <div id="profesoresContainer" class="profesores-grid">
          <!-- Los profesores se cargarán dinámicamente aquí -->
        </div>
        
        <div class="mt-3">
          <small class="text-muted">Los profesores seleccionados aparecerán con opacidad reducida y un checkmark.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="btnCompartirFicha" class="btn btn-primary">Compartir Ficha</button>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../Componentes/footer.php'; ?>
<script src="/js/dashboard_profesor.js"></script>
