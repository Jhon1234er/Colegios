<?php require __DIR__ . '/../Componentes/encabezado.php'; ?>

<link rel="stylesheet" href="/css/Profesor/dashboard_profesor.css">

<main>
  <div class="parent">
    
    <!-- div1: Panel secciones -->
    <div class="div1">
      <div class="panel-secciones">Mis Fichas / Secciones / Calendario</div>
    </div>

    <!-- div2: Bienvenida -->
    <div class="div2">
      <div class="panel-secciones">
        <h2>Bienvenido, Facilitador <?= htmlspecialchars($_SESSION['usuario']['nombres']) ?></h2>
      </div>
    </div>
    
    <!-- div3: Lista de fichas -->
    <div class="div3">
      <div class="panel-navegacion">PANEL DE FICHAS</div>
      <div id="fichasContainer" class="lista-fichas"></div>
    </div>

    <div class="div4">
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
        <!-- Registro de asistencia  -->
        <div id="calendarioAsistencia" class="calendario-wrapper"></div>

    </div>

  </div>
</main>

<?php require __DIR__ . '/../Componentes/footer.php'; ?>
<script src="/js/dashboard_profesor.js"></script>
