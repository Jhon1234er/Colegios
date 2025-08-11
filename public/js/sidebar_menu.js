document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('sidebar-menu');
  const sidebarToggle = document.getElementById('sidebar-toggle');
  const sidebarItems = document.querySelectorAll('.sidebar-item'); // Todos los <li> que son ítems del menú

  let isSidebarExpandedByClick = localStorage.getItem('sidebarExpanded') === 'true' ? true : false;

  // Función para expandir/colapsar el sidebar principal
  function toggleSidebar() {
  isSidebarExpandedByClick = !isSidebarExpandedByClick;
  localStorage.setItem('sidebarExpanded', isSidebarExpandedByClick);
  
  sidebar.classList.toggle('w-16', isSidebarExpandedByClick);
  sidebar.classList.toggle('w-64', !isSidebarExpandedByClick);

  sidebar.querySelectorAll('.font-semibold.whitespace-nowrap').forEach(text => {
    text.classList.toggle('hidden', isSidebarExpandedByClick);
  });

  if (isSidebarExpandedByClick) {
    sidebarItems.forEach(item => {
      const expandableContent = item.querySelector('.expandable-content');
      if (expandableContent) {
        expandableContent.classList.remove('open');
        expandableContent.classList.add('max-h-0');
      }
    });
  }
}
// Después de definir isSidebarExpandedByClick, añade esta función para aplicar el estado guardado
function applySidebarState() {
  sidebar.classList.toggle('w-16', isSidebarExpandedByClick);
  sidebar.classList.toggle('w-64', !isSidebarExpandedByClick);

  sidebar.querySelectorAll('.font-semibold.whitespace-nowrap').forEach(text => {
    text.classList.toggle('hidden', isSidebarExpandedByClick);
  });

  if (isSidebarExpandedByClick) {
    sidebarItems.forEach(item => {
      const expandableContent = item.querySelector('.expandable-content');
      if (expandableContent) {
        expandableContent.classList.remove('open');
        expandableContent.classList.add('max-h-0');
      }
    });
  }
}

applySidebarState();


  // Evento para el botón de hamburguesa (toggle del sidebar principal)
  sidebarToggle.addEventListener('click', toggleSidebar);



  // Evento para colapsar el sidebar principal al salir el mouse (solo si no está expandido por clic)
  sidebar.addEventListener('mouseleave', () => {
    if (!isSidebarExpandedByClick) {
      sidebar.classList.remove('w-64');
      sidebar.classList.add('w-16');
      sidebar.querySelectorAll('.font-semibold.whitespace-nowrap').forEach(text => {
        text.classList.add('hidden');
      });
      // Colapsar todos los submenús al colapsar el sidebar principal por mouseleave
      sidebarItems.forEach(item => {
        const expandableContent = item.querySelector('.expandable-content');
        if (expandableContent) {
          expandableContent.classList.remove('open'); // Colapsa el submenú
          expandableContent.classList.add('max-h-0'); // Asegura que esté colapsado
        }
      });
    }
  });

  // Lógica de expansión/colapso de sub-menús al hacer clic en el botón del ítem
  sidebarItems.forEach(item => {
    const itemButton = item.querySelector('.sidebar-item-button'); // El botón dentro del li
    const expandableContent = item.querySelector('.expandable-content'); // El div del submenú

    if (itemButton && expandableContent) {
      itemButton.addEventListener('click', (event) => {
        event.stopPropagation(); // Evita que el clic en el botón propague al li padre si tuviera un listener

        // Colapsar todos los demás sub-menús
        sidebarItems.forEach(otherItem => {
          if (otherItem !== item) { // Si no es el ítem actual
            const otherExpandableContent = otherItem.querySelector('.expandable-content');
            if (otherExpandableContent && otherExpandableContent.classList.contains('open')) {
              otherExpandableContent.classList.remove('open'); // Colapsa el submenú
              otherExpandableContent.classList.add('max-h-0'); // Asegura que esté colapsado
            }
          }
        });

        // Alternar la visibilidad del submenú actual con animación
        if (expandableContent.classList.contains('max-h-0')) {
          expandableContent.classList.remove('max-h-0'); // Expandir
          expandableContent.classList.add('open'); // Añadir clase para indicar que está abierto
        } else {
          expandableContent.classList.remove('open'); // Colapsar
          expandableContent.classList.add('max-h-0'); // Asegura que esté colapsado
        }
      });
    }

    // Lógica para colapsar el submenú al salir el mouse del item (solo si el sidebar está expandido por hover)
    item.addEventListener('mouseleave', () => {
      // Solo colapsar si el sidebar principal no está expandido por clic
      if (!isSidebarExpandedByClick && expandableContent && expandableContent.classList.contains('open')) {
        expandableContent.classList.remove('open'); // Colapsa el submenú
        expandableContent.classList.add('max-h-0'); // Asegura que esté colapsado
      }
    });
  });

  // Lógica para marcar notificaciones como leídas
  const badge = document.querySelector('.notifications-section .absolute'); // Selector para el badge de notificaciones
  const listaNotificaciones = document.getElementById('lista-notificaciones-sidebar');
  const sinNotificaciones = document.getElementById('sin-notificaciones-sidebar');

  document.querySelectorAll('.marcar-leida-btn').forEach(btn => {
    btn.addEventListener('click', async (event) => {
      event.stopPropagation(); // Evita que el clic en el botón propague y colapse el submenú
      const id = btn.dataset.id;
      const formData = new FormData();
      formData.append('notificacion_id', id);

      try {
        const res = await fetch('/views/Componentes/prueba_api_notificaciones.php', { // Ruta corregida
          method: 'POST',
          body: formData
        });

        const text = await res.text();
        let data;
        try {
          data = JSON.parse(text);
        } catch (error) {
          console.error(" Respuesta no es JSON válido:", text);
          return;
        }

        if (data.success) {
          const item = btn.closest('li');
          if (item) {
            item.classList.add('opacity-60'); // Añade opacidad para indicar que se ha leído
            btn.remove(); // Elimina el botón "Marcar como leída"
          }

          if (listaNotificaciones && listaNotificaciones.querySelectorAll('li:not(.opacity-60)').length === 0) {
            sinNotificaciones.classList.remove('hidden'); // Muestra el mensaje de "No tienes notificaciones"
          }

          if (badge) {
            let count = parseInt(badge.textContent);
            if (!isNaN(count)) {
              count--;
              if (count <= 0) {
                badge.remove(); // Elimina el badge si no hay más notificaciones
              } else {
                badge.textContent = count; // Actualiza el contador de notificaciones
              }
            }
          }
        } else {
          console.error("Error en la respuesta del servidor:", data);
        }
      } catch (err) {
        console.error("Error al marcar notificación:", err);
      }
    });
  });
});
