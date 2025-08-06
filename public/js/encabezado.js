document.addEventListener('DOMContentLoaded', function () {
  const buscador = document.getElementById('buscador-global');
  if (buscador) {
    buscador.addEventListener('submit', function (e) {
      e.preventDefault();
      const filtro = document.getElementById('filtro-busqueda').value;
      const query = document.getElementById('input-busqueda').value.trim();
      if (!query) return;

      fetch(`/?page=dashboard&filtro=${encodeURIComponent(filtro)}&q=${encodeURIComponent(query)}&ajax=1`)
        .then(res => res.text())
        .then(html => {
          const dashboardResultados = document.getElementById('dashboard-resultados');
          dashboardResultados.innerHTML = html;
          dashboardResultados.classList.remove('anim-in');
          void dashboardResultados.offsetWidth;
          dashboardResultados.classList.add('anim-in');
          dashboardResultados.style.display = '';
          document.getElementById('dashboard-normal').classList.add('anim-out');
          document.getElementById('dashboard-overlay').classList.add('active');

          const volverBtn = document.getElementById('volver-dashboard');
          if (volverBtn) {
            volverBtn.addEventListener('click', function (e) {
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

  // Manejo de notificaciones
  const badge = document.querySelector('.notificaciones-badge');
  const lista = document.getElementById('lista-notificaciones');
  const mensajeVacio = document.getElementById('sin-notificaciones');

  document.querySelectorAll('.marcar-leida-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      const formData = new FormData();
      formData.append('notificacion_id', id);

      try {
          const res = await fetch('/prueba_api_notificaciones.php', {
          method: 'POST',
          body: formData
        });

        const text = await res.text();

        // 👇 Validamos que sea JSON real antes de intentar usarlo
        let data;
        try {
          data = JSON.parse(text);
        } catch (error) {
          console.error("❌ Respuesta no es JSON válido:", text);
          return;
        }

        if (data.success) {
          const item = btn.closest('li');
          item.classList.add('opacity-50');
          btn.remove();

          if (lista && lista.querySelectorAll('li:not(.opacity-50)').length === 0) {
            mensajeVacio.classList.remove('hidden');
          }

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
        } else {
          console.error("⚠️ Error en la respuesta del servidor:", data);
        }
      } catch (err) {
        console.error("❌ Error al marcar notificación:", err);
      }
    });
  });
});

// Dropdown de notificaciones
function toggleDropdown() {
  const dropdown = document.getElementById("dropdown-notificaciones");
  dropdown.classList.toggle("hidden");
}

// Ocultar dropdown si se hace clic afuera
document.addEventListener('click', function (event) {
  const campana = document.querySelector('.group');
  const dropdown = document.getElementById("dropdown-notificaciones");
  if (!campana.contains(event.target)) {
    dropdown.classList.add("hidden");
  }
});
