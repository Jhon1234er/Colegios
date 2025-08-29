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
      } elseif ($tip_contrato === 'planta') {
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

<?php require __DIR__ . '/../Componentes/footer.php'; ?>
<script src="/js/dashboard_profesor.js"></script>
