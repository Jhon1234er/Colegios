<?php require __DIR__ . '/../Componentes/encabezado.php'; ?>

<link rel="stylesheet" href="/css/Profesor/dashboard_profesor.css">

<main>
  <?php require_once __DIR__ . '/../../helpers/auth.php'; ?>
  <input type="hidden" id="csrf_token" value="<?= csrf_token() ?>">
  <div class="parent">
    
    <!-- div1: Panel secciones -->
    <div class="div1">
      <div class="panel-secciones">Mis Fichas / Secciones / Calendario</div>
    </div>

    <!-- div2: Bienvenida -->
<div class="div2">
  <div class="panel-secciones">
<?php 
      $tip_contrato = strtolower($tip_contrato ?? '');
      $nombre_usuario = htmlspecialchars($_SESSION['usuario']['nombres']);

      if ($tip_contrato === 'contratista') {
          $saludo = "Bienvenid@, Facilitador $nombre_usuario";
      } elseif ($tip_contrato === 'instructor') {
          $saludo = "Bienvenid@, Instructor $nombre_usuario";
      } else {
          $saludo = "Bienvenido, $nombre_usuario";
      }
    ?>
    <h2><?= $saludo ?></h2>

  </div>
</div>


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
