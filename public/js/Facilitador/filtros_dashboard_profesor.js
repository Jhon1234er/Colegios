// filtros_dashboard_profesor.js
// Lógica para los filtros "Todos" y "Buscar" en el dashboard del profesor

document.addEventListener('DOMContentLoaded', () => {
  const filtros = document.querySelectorAll('.filtros-cursos .filtro');
  const tarjetasFichas = document.getElementById('tarjetasFichas');

  // Paginación
  let paginarFichas;
  function aplicarPaginacion() {
    if (window.inicializarPaginacionFichas) {
      paginarFichas = window.inicializarPaginacionFichas('tarjetasFichas', 4);
    }
  }


  // Crear input de búsqueda y botón de icono
  let inputBuscar = document.createElement('input');
  inputBuscar.type = 'text';
  inputBuscar.placeholder = 'Buscar ficha...';
  inputBuscar.className = 'input-buscar-ficha';
  inputBuscar.style.display = 'none';

  // Crear wrapper para input y botón
  let buscarWrapper = document.createElement('div');
  buscarWrapper.className = 'buscar-ficha-wrapper';
  buscarWrapper.style.display = 'none';

  // Crear botón de icono
  let btnBuscarIcon = document.createElement('button');
  btnBuscarIcon.className = 'btn-icon';
  btnBuscarIcon.id = 'tabla-search-btn';
  btnBuscarIcon.type = 'button';
  btnBuscarIcon.setAttribute('aria-label', 'Buscar');
  btnBuscarIcon.style.width = '38px';
  btnBuscarIcon.style.height = '38px';
  btnBuscarIcon.style.borderRadius = '50%';
  btnBuscarIcon.style.border = '2px solid var(--green)';
  btnBuscarIcon.style.background = 'var(--green)';
  btnBuscarIcon.style.color = '#fff';
  btnBuscarIcon.style.display = 'grid';
  btnBuscarIcon.style.placeItems = 'center';
  btnBuscarIcon.innerHTML = `<svg viewBox="0 0 24 24" fill="none" width="20" height="20" xmlns="http://www.w3.org/2000/svg"><circle cx="11" cy="11" r="7" stroke="white" stroke-width="2.2" fill="none"/><line x1="16.5" y1="16.5" x2="21" y2="21" stroke="white" stroke-width="2.2"/></svg>`;

  buscarWrapper.appendChild(inputBuscar);
  buscarWrapper.appendChild(btnBuscarIcon);

  let fichasBackup = [];

  // Esperar a que cargarFichas() haya llenado las cards
  function obtenerCards() {
    return Array.from(document.querySelectorAll('#tarjetasFichas .card-ficha'));
  }

  // Guardar backup de fichas al primer uso
  function backupFichas() {
    if (fichasBackup.length === 0) {
      fichasBackup = obtenerCards().map(card => card.cloneNode(true));
    }
  }

  // Estado para saber si estamos en modo 'Todos' o paginado
  let modoTodosActivo = false;


  // Delegación para el filtro 'Todos' para evitar problemas de referencia
  const filtrosCursos = document.querySelector('.filtros-cursos');
  function activarFiltroTodos() {
    modoTodosActivo = true;
    if (window.fichasGlobal && typeof window.fichasGlobal.forEach === 'function' && typeof window.renderCard === 'function') {
      tarjetasFichas.innerHTML = '';
      window.fichasGlobal.forEach(ficha => {
        tarjetasFichas.appendChild(window.renderCard(ficha, true));
      });
      // Eliminar paginación si existe
      const paginacion = document.getElementById('paginacionFichas');
      if (paginacion) paginacion.remove();
      inputBuscar.value = '';
      inputBuscar.style.display = 'none';
      // Restaurar el botón de buscar si el input está visible
      if (filtrosCursos && filtros[1] && buscarWrapper && buscarWrapper.parentNode) {
        buscarWrapper.parentNode.replaceChild(filtros[1], buscarWrapper);
      }
      // Reemplazar el botón 'Todos' por la flecha
      let backBtn = document.getElementById('filtro-todos-back');
      if (!backBtn) {
        backBtn = document.createElement('button');
        backBtn.id = 'filtro-todos-back';
        backBtn.innerHTML = "<span class='icon-flecha' aria-hidden='true'><svg viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'><path d='M15 4L7 12L15 20' fill='none' stroke='currentColor' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'/><line x1='7' y1='12' x2='21' y2='12' stroke='currentColor' stroke-width='2.2' stroke-linecap='round'/></svg></span>";
        backBtn.title = 'Volver a fichas paginadas';
        backBtn.style.marginRight = '8px';
        backBtn.style.background = 'none';
        backBtn.style.border = 'none';
        backBtn.style.cursor = 'pointer';
        // Reemplazar el botón 'Todos' (primer .filtro) por la flecha
        const todosBtn = filtrosCursos.querySelector('.filtro');
        if (todosBtn && todosBtn.parentNode) {
          todosBtn.parentNode.replaceChild(backBtn, todosBtn);
        }
        backBtn.addEventListener('click', () => {
          // Restaurar paginación
          tarjetasFichas.innerHTML = '';
          if (window.inicializarPaginacionFichas && window.fichasGlobal && typeof window.renderCard === 'function') {
            window.inicializarPaginacionFichas('tarjetasFichas', 4, 'paginacionFichas', window.fichasGlobal, window.renderCard);
          }
          // Restaurar el botón 'Todos' en su lugar
          if (backBtn && backBtn.parentNode) {
            const nuevoTodosBtn = document.createElement('div');
            nuevoTodosBtn.className = 'filtro';
            nuevoTodosBtn.textContent = 'Todos';
            backBtn.parentNode.replaceChild(nuevoTodosBtn, backBtn);
          }
          modoTodosActivo = false;
        });
      }
      // Limpiar selección de ficha y calendario de asistencia
      if (window.fichaSeleccionada !== undefined) {
        window.fichaSeleccionada = null;
      }
      const calWrapper = document.getElementById('calendarioAsistencia');
      if (calWrapper) {
        calWrapper.innerHTML = '';
      }
    }
  }

  // Delegación de eventos para el filtro 'Todos'
  filtrosCursos.addEventListener('click', (e) => {
    const target = e.target;
    if (target.classList.contains('filtro') && target.textContent.trim() === 'Todos') {
      activarFiltroTodos();
    }
  });

  // Click en el botón de búsqueda aplica el filtro actual
  btnBuscarIcon.addEventListener('click', () => {
    try { inputBuscar.dispatchEvent(new Event('input')); } catch(_) {}
  });

  // Enter en el input aplica el filtro actual
  inputBuscar.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter') {
      ev.preventDefault();
      try { inputBuscar.dispatchEvent(new Event('input')); } catch(_) {}
    }
  });

  // Filtro "Buscar"
  filtros[1]?.addEventListener('click', () => {
    backupFichas();
    // Reemplazar el botón de buscar por el wrapper con botón + input
    filtros[1].parentNode.replaceChild(buscarWrapper, filtros[1]);
    buscarWrapper.style.display = 'flex';
    inputBuscar.style.display = 'inline-block';
    inputBuscar.focus();
  });

  // Lógica de búsqueda
  inputBuscar.addEventListener('input', (e) => {
    const norm = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    const valorN = norm(e.target.value.trim());
    tarjetasFichas.innerHTML = '';
    // Usar window.fichasGlobal y renderCard para evitar mostrar el calendario
    if (window.fichasGlobal && typeof window.renderCard === 'function') {
      if (valorN === '') {
        // Si el input está vacío:
        if (modoTodosActivo) {
          // Si venimos de 'Todos', mostrar todas las fichas deshabilitadas
          window.fichasGlobal.forEach(ficha => tarjetasFichas.appendChild(window.renderCard(ficha, true)));
          // Eliminar paginación si existe
          const paginacion = document.getElementById('paginacionFichas');
          if (paginacion) paginacion.remove();
        } else {
          // Si venimos de paginado, restaurar paginación
          if (window.inicializarPaginacionFichas) {
            window.inicializarPaginacionFichas('tarjetasFichas', 4, 'paginacionFichas', window.fichasGlobal, window.renderCard);
          } else {
            window.fichasGlobal.forEach(ficha => tarjetasFichas.appendChild(window.renderCard(ficha)));
          }
        }
        // Limpiar calendario si está abierto
        const calWrapper = document.getElementById('calendarioAsistencia');
        if (calWrapper) calWrapper.innerHTML = '';
        return;
      }
      const fichasFiltradas = window.fichasGlobal.filter(ficha => {
        // Construir texto con todos los posibles campos (nombre + variantes de número/código)
        const nombre = ficha && ficha.nombre != null ? String(ficha.nombre) : '';
        const parts = [
          ficha && ficha.numero != null ? String(ficha.numero) : '',
          ficha && ficha.numero_ficha != null ? String(ficha.numero_ficha) : '',
          ficha && ficha.codigo != null ? String(ficha.codigo) : '',
          ficha && ficha.codigo_ficha != null ? String(ficha.codigo_ficha) : '',
          ficha && ficha.id != null ? String(ficha.id) : ''
        ];
        const texto = [nombre].concat(parts).join(' ');
        return norm(texto).includes(valorN);
      });
      // Si estamos en modoTodosActivo pero hay búsqueda, permitir seleccionar (disabled = false)
      const disabled = false;
      fichasFiltradas.forEach(ficha => tarjetasFichas.appendChild(window.renderCard(ficha, modoTodosActivo && valorN === '' ? true : disabled)));
      // Eliminar paginación si existe
      const paginacion = document.getElementById('paginacionFichas');
      if (paginacion) paginacion.remove();
      // Limpiar calendario si está abierto
      const calWrapper = document.getElementById('calendarioAsistencia');
      if (calWrapper) calWrapper.innerHTML = '';
    }
  });

  // Si el input pierde el foco y está vacío, restaurar el botón de buscar
  inputBuscar.addEventListener('blur', () => {
    setTimeout(() => {
      if (inputBuscar.value.trim() === '') {
        // Restaurar el botón de buscar
        const filtrosCursos = document.querySelector('.filtros-cursos');
        if (buscarWrapper && buscarWrapper.parentNode) {
          buscarWrapper.parentNode.replaceChild(filtros[1], buscarWrapper);
        }
        buscarWrapper.style.display = 'none';
        inputBuscar.style.display = 'none';
      }
    }, 120);
  });

  // Inicializar backup y paginación si ya hay fichas
  if (obtenerCards().length > 0) {
    backupFichas();
    aplicarPaginacion();
  }

  // Si cargarFichas es global, hook para actualizar backup y paginación
  if (window.cargarFichas) {
    const originalCargarFichas = window.cargarFichas;
    window.cargarFichas = async function() {
      await originalCargarFichas();
      fichasBackup = [];
      backupFichas();
      aplicarPaginacion();
    };
  }
});
