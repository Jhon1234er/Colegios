document.addEventListener('DOMContentLoaded', function() {
  var buscador = document.getElementById('buscador-global');
  if (buscador) {
    buscador.addEventListener('submit', function(e) {
      e.preventDefault();
      const filtro = document.getElementById('filtro-busqueda').value;
      const query = document.getElementById('input-busqueda').value.trim();
      if (!query) return;

      // AJAX: pide solo el bloque de resultados
      fetch(`/?page=dashboard&filtro=${encodeURIComponent(filtro)}&q=${encodeURIComponent(query)}&ajax=1`)
        .then(res => res.text())
        .then(html => {
          // Inserta el HTML en el dashboard de resultados
          const dashboardResultados = document.getElementById('dashboard-resultados');
          dashboardResultados.innerHTML = html;

          // 👇 Asegúrate de quitar y volver a agregar la clase para reiniciar la animación
          dashboardResultados.classList.remove('anim-in');
          void dashboardResultados.offsetWidth; // Forzar reflow para reiniciar animación
          dashboardResultados.classList.add('anim-in');
          dashboardResultados.style.display = '';

          // Muestra el dashboard de resultados con animación
          document.getElementById('dashboard-normal').classList.add('anim-out');
          document.getElementById('dashboard-overlay').classList.add('active');

          // Vuelve a activar el botón volver (porque el HTML fue reemplazado)
          const volverBtn = document.getElementById('volver-dashboard');
          if (volverBtn) {
            volverBtn.addEventListener('click', function(e) {
              e.preventDefault();
              document.getElementById('dashboard-normal').classList.remove('anim-out');
              dashboardResultados.classList.remove('anim-in');
              document.getElementById('dashboard-overlay').classList.remove('active');
              setTimeout(() => {
                dashboardResultados.style.display = 'none';
                document.getElementById('dashboard-normal').style.display = '';
              }, 500);
            });
          }
        });
    });
  }
});