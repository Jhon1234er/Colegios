// paginacion_fichas_profesor.js
// Lógica de paginación para las fichas del dashboard del profesor

/**
 * Inicializa la paginación de fichas.
 * @param {string} contenedorId - ID del contenedor de tarjetas de fichas.
 * @param {number} fichasPorPagina - Cantidad de fichas por página.
 * @param {string} paginacionId - ID del contenedor donde se mostrará la paginación.
 */




// paginacion_fichas_profesor.js
// Lógica de paginación basada en datos y función de renderizado
function inicializarPaginacionFichas(contenedorId, fichasPorPagina = 4, paginacionId = 'paginacionFichas', dataArray = [], renderCardFn) {
  const contenedor = document.getElementById(contenedorId);
  if (!contenedor || !Array.isArray(dataArray) || typeof renderCardFn !== 'function') return;

  let paginaActual = 1;

  // Crear contenedor de paginación si no existe
  let paginacion = document.getElementById(paginacionId);
  if (!paginacion) {
    paginacion = document.createElement('div');
    paginacion.id = paginacionId;
    paginacion.className = 'paginacion-fichas';
    // Insertar dentro del wrapper si existe
    const wrapper = document.querySelector('.paginacion-fichas-wrapper');
    if (wrapper) {
      wrapper.appendChild(paginacion);
    } else {
      // Fallback: antes de las tarjetas
      contenedor.parentNode.insertBefore(paginacion, contenedor);
    }
  }

  function render() {
    const totalFichas = dataArray.length;
    const totalPaginas = Math.ceil(totalFichas / fichasPorPagina);
    contenedor.innerHTML = '';
    const inicioIdx = (paginaActual - 1) * fichasPorPagina;
    const finIdx = Math.min(paginaActual * fichasPorPagina, totalFichas);
    for (let i = inicioIdx; i < finIdx; i++) {
      contenedor.appendChild(renderCardFn(dataArray[i]));
    }
    const inicio = totalFichas === 0 ? 0 : inicioIdx + 1;
    const fin = finIdx;
    paginacion.innerHTML = `
      <span class="paginacion-texto">${inicio}–${fin} de ${totalFichas}</span>
      <button class="btn-paginacion" ${paginaActual === 1 ? 'disabled' : ''} data-dir="prev">&lt;</button>
      <button class="btn-paginacion" ${paginaActual === totalPaginas || totalFichas === 0 ? 'disabled' : ''} data-dir="next">&gt;</button>
    `;
  }

  paginacion.addEventListener('click', (e) => {
    if (e.target.classList.contains('btn-paginacion')) {
      const totalPaginas = Math.ceil(dataArray.length / fichasPorPagina);
      if (e.target.dataset.dir === 'prev' && paginaActual > 1) {
        paginaActual--;
        render();
      } else if (e.target.dataset.dir === 'next' && paginaActual < totalPaginas) {
        paginaActual++;
        render();
      }
    }
  });

  render();

  // Devuelve función para actualizar fichas (útil si cambian por filtro/búsqueda)
  return function actualizarFichas(nuevosDatos) {
    dataArray = nuevosDatos;
    paginaActual = 1;
    render();
  };
}

// Exportar para uso global
if (typeof window !== 'undefined') window.inicializarPaginacionFichas = inicializarPaginacionFichas;

// Exportar para uso en otros scripts
typeof window !== 'undefined' && (window.inicializarPaginacionFichas = inicializarPaginacionFichas);