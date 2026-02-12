document.addEventListener('DOMContentLoaded', function () {

  

  // ============================================

  // MANEJO MEJORADO DE DROPDOWNS

  // ============================================

  

  // Configurar todos los dropdowns

  const dropdowns = document.querySelectorAll('.dropdown');

  let dropdownActivo = null;

  let timeoutDropdown = null;

  

  dropdowns.forEach(dropdown => {

    const dropbtn = dropdown.querySelector('.dropbtn');

    const content = dropdown.querySelector('.dropdown-content');

    

    if (!dropbtn || !content) return;

    

    // Mouse enter en el botón

    dropbtn.addEventListener('mouseenter', function() {

      clearTimeout(timeoutDropdown);

      

      // Cerrar otros dropdowns

      if (dropdownActivo && dropdownActivo !== dropdown) {

        cerrarDropdown(dropdownActivo);

      }

      

      abrirDropdown(dropdown);

      dropdownActivo = dropdown;

    });

    

    // Mouse leave del dropdown completo

    dropdown.addEventListener('mouseleave', function() {

      timeoutDropdown = setTimeout(() => {

        if (dropdownActivo === dropdown) {

          cerrarDropdown(dropdown);

          dropdownActivo = null;

        }

      }, 600); //

    });

    

    // Mouse enter de vuelta al dropdown (cancelar cierre)

    dropdown.addEventListener('mouseenter', function() {

      clearTimeout(timeoutDropdown);

    });

    

    // Manejar clicks en los enlaces

    const enlaces = content.querySelectorAll('a');

    enlaces.forEach(enlace => {

      enlace.addEventListener('click', function(e) {

        // Permitir navegación normal pero cerrar dropdown

        setTimeout(() => {

          cerrarDropdown(dropdown);

          dropdownActivo = null;

        }, 100);

      });

    });

  });

  

  function abrirDropdown(dropdown) {

    const content = dropdown.querySelector('.dropdown-content');

    if (content) {

      dropdown.classList.add('dropdown-stay-open');

      content.style.display = 'block';

    }

  }

  

  function cerrarDropdown(dropdown) {

    const content = dropdown.querySelector('.dropdown-content');

    if (content) {

      dropdown.classList.remove('dropdown-stay-open');

      // No ocultar inmediatamente para permitir animación CSS

      setTimeout(() => {

        if (!dropdown.classList.contains('dropdown-stay-open')) {

          content.style.display = '';

        }

      }, 600);

    }

  }

  

  // Cerrar dropdowns al hacer clic fuera

  document.addEventListener('click', function(e) {

    const dropdown = e.target.closest('.dropdown');

    if (!dropdown && dropdownActivo) {

      cerrarDropdown(dropdownActivo);

      dropdownActivo = null;

    }

  });

  

  // ============================================

  // DROPDOWN DE NOTIFICACIONES ESPECÍFICO

  // ============================================

  

  const notificacionesBtn = document.querySelector('[onclick="toggleDropdown()"]');

  const dropdownNotificaciones = document.getElementById('dropdown-notificaciones');

  

  if (notificacionesBtn && dropdownNotificaciones) {

    // Remover onclick inline y manejar con event listener

    notificacionesBtn.removeAttribute('onclick');

    

    notificacionesBtn.addEventListener('click', function(e) {

      e.preventDefault();

      e.stopPropagation();

      

      const isHidden = dropdownNotificaciones.classList.contains('hidden');

      

      // Cerrar otros dropdowns primero

      if (dropdownActivo) {

        cerrarDropdown(dropdownActivo);

        dropdownActivo = null;

      }

      

      if (isHidden) {

        dropdownNotificaciones.classList.remove('hidden');

        dropdownNotificaciones.style.display = 'block';

      } else {

        dropdownNotificaciones.classList.add('hidden');

      }

    });

    

    // Cerrar al hacer clic fuera

    document.addEventListener('click', function(e) {

      if (!notificacionesBtn.contains(e.target) && 

          !dropdownNotificaciones.contains(e.target)) {

        dropdownNotificaciones.classList.add('hidden');

      }

    });

  }

  

  // ============================================

  // MANEJO DE NOTIFICACIONES

  // ============================================

  

  const badge = document.querySelector('.notificaciones-badge');

  const lista = document.getElementById('lista-notificaciones');

  const mensajeVacio = document.getElementById('sin-notificaciones');

  

  // Configurar botones de marcar como leída

  document.querySelectorAll('.marcar-leida-btn').forEach(btn => {

    btn.addEventListener('click', async function(e) {

      e.preventDefault();

      e.stopPropagation();

      

      const id = btn.dataset.id;

      const item = btn.closest('li');

      

      // Mostrar indicador de carga en el botón

      const originalText = btn.innerHTML;

      btn.innerHTML = '<span style="font-size: 10px;">⏳</span>';

      btn.disabled = true;

      

      const formData = new FormData();

      formData.append('notificacion_id', id);

      

      try {

        const res = await fetch('/?page=marcar_notificacion', {

          method: 'POST',

          body: formData

        });

        

        const text = await res.text();

        let data;

        

        try {

          data = JSON.parse(text);

        } catch (error) {

          mostrarNotificacionTemporal('Error al procesar la respuesta', 'error');

          return;

        }

        

        if (data.success) {

          // Animar la transición del elemento

          if (item) {

            item.style.transition = 'all 0.3s ease';

            item.classList.add('opacity-60');

            item.style.transform = 'translateX(10px)';

            

            // Remover el botón con animación

            btn.style.opacity = '0';

            btn.style.transform = 'scale(0.8)';

            

            setTimeout(() => {

              btn.remove();

            }, 300);

          }

          

          // Actualizar contador de notificaciones

          if (badge) {

            let count = parseInt(badge.textContent);

            if (!isNaN(count)) {

              count--;

              if (count <= 0) {

                badge.style.animation = 'pulse 0.3s ease';

                setTimeout(() => badge.remove(), 300);

              } else {

                badge.textContent = count;

                badge.style.animation = 'pulse 0.3s ease';

              }

            }

          }

          

          // Mostrar mensaje vacío si no hay más notificaciones no leídas

          if (lista && lista.querySelectorAll('li:not(.opacity-60)').length === 0) {

            setTimeout(() => {

              if (mensajeVacio) {

                mensajeVacio.classList.remove('hidden');

                mensajeVacio.style.animation = 'fadeIn 0.3s ease';

              }

            }, 400);

          }

          

          mostrarNotificacionTemporal('Notificación marcada como leída', 'success');

          

        } else {

          mostrarNotificacionTemporal('Error al marcar la notificación', 'error');

        }

        

      } catch (err) {

        mostrarNotificacionTemporal('Error de conexión', 'error');

      } finally {

        // Restaurar botón si hubo error

        if (!btn.disabled) return;

        btn.innerHTML = originalText;

        btn.disabled = false;

      }

    });

  });

  

  

  // ============================================

  // EFECTOS DE SCROLL DE LA NAVBAR

  // ============================================

  

  let lastScrollTop = 0;

  const navbar = document.querySelector('.navbar, .header-content, .header-unified');

  

  if (navbar) {

    window.addEventListener('scroll', function() {

      const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

      

      // Añadir clase scrolled cuando se hace scroll

      if (scrollTop > 50) {

        navbar.classList.add('scrolled');

      } else {

        navbar.classList.remove('scrolled');

      }

      

      // Ocultar/mostrar navbar en mobile al hacer scroll

      if (window.innerWidth <= 768) {

        if (scrollTop > lastScrollTop && scrollTop > 100) {

          // Scrolling down

          navbar.style.transform = 'translateY(-100%)';

        } else {

          // Scrolling up

          navbar.style.transform = 'translateY(0)';

        }

      }

      

      lastScrollTop = scrollTop;

    });

  }

  

  // ============================================

  // FUNCIONES DE UTILIDAD

  // ============================================

  

  function mostrarNotificacionTemporal(mensaje, tipo = 'info') {

    // Crear elemento de notificación

    const notificacion = document.createElement('div');

    notificacion.className = `notificacion-temporal notificacion-${tipo}`;

    notificacion.innerHTML = `

      <div class="notificacion-content">

        <span class="notificacion-icon">${getIconoTipo(tipo)}</span>

        <span class="notificacion-mensaje">${mensaje}</span>

        <button class="notificacion-cerrar">×</button>

      </div>

    `;

    

    // Estilos inline para la notificación temporal

    Object.assign(notificacion.style, {

      position: 'fixed',

      top: '20px',

      right: '20px',

      background: getTemaColor(tipo),

      color: 'white',

      padding: '12px 16px',

      borderRadius: '8px',

      boxShadow: '0 4px 12px rgba(0, 0, 0, 0.15)',

      zIndex: '10000',

      transform: 'translateX(100%)',

      transition: 'transform 0.3s ease',

      maxWidth: '300px',

      fontSize: '14px'

    });

    

    notificacion.querySelector('.notificacion-content').style.cssText = `

      display: flex;

      align-items: center;

      gap: 8px;

    `;

    

    const cerrarBtn = notificacion.querySelector('.notificacion-cerrar');

    cerrarBtn.style.cssText = `

      background: none;

      border: none;

      color: white;

      cursor: pointer;

      font-size: 18px;

      padding: 0;

      margin-left: auto;

    `;

    

    // Añadir al DOM

    document.body.appendChild(notificacion);

    

    // Mostrar con animación

    setTimeout(() => {

      notificacion.style.transform = 'translateX(0)';

    }, 100);

    

    // Configurar cierre

    const cerrar = () => {

      notificacion.style.transform = 'translateX(100%)';

      setTimeout(() => {

        if (notificacion.parentNode) {

          notificacion.parentNode.removeChild(notificacion);

        }

      }, 300);

    };

    

    cerrarBtn.addEventListener('click', cerrar);

    

    // Auto-cerrar después de 4 segundos

    setTimeout(cerrar, 4000);

  }

  

  function getIconoTipo(tipo) {

    const iconos = {

      success: '✓',

      error: '⚠',

      warning: '⚠',

      info: 'ℹ'

    };

    return iconos[tipo] || iconos.info;

  }

  

  function getTemaColor(tipo) {

    const colores = {

      success: '#27ae60',

      error: '#e74c3c',

      warning: '#f39c12',

      info: '#3498db'

    };

    return colores[tipo] || colores.info;

  }

  

  // ============================================

  // MANEJO DE RESIZE

  // ============================================

  

  window.addEventListener('resize', function() {

    // Cerrar dropdowns en resize

    if (dropdownActivo) {

      cerrarDropdown(dropdownActivo);

      dropdownActivo = null;

    }

    

    // Cerrar dropdown de notificaciones

    if (dropdownNotificaciones) {

      dropdownNotificaciones.classList.add('hidden');

    }

  });

  

  // ============================================

  // MEJORAS DE ACCESIBILIDAD

  // ============================================

  

  // Manejo de navegación por teclado en dropdowns

  dropdowns.forEach(dropdown => {

    const dropbtn = dropdown.querySelector('.dropbtn');

    const enlaces = dropdown.querySelectorAll('.dropdown-content a');

    

    if (dropbtn) {

      dropbtn.addEventListener('keydown', function(e) {

        if (e.key === 'Enter' || e.key === ' ') {

          e.preventDefault();

          if (dropdownActivo === dropdown) {

            cerrarDropdown(dropdown);

            dropdownActivo = null;

          } else {

            if (dropdownActivo) {

              cerrarDropdown(dropdownActivo);

            }

            abrirDropdown(dropdown);

            dropdownActivo = dropdown;

          }

        } else if (e.key === 'ArrowDown' && dropdownActivo === dropdown) {

          e.preventDefault();

          if (enlaces.length > 0) {

            enlaces[0].focus();

          }

        }

      });

    }

    

    enlaces.forEach((enlace, index) => {

      enlace.addEventListener('keydown', function(e) {

        if (e.key === 'ArrowDown') {

          e.preventDefault();

          const nextIndex = (index + 1) % enlaces.length;

          enlaces[nextIndex].focus();

        } else if (e.key === 'ArrowUp') {

          e.preventDefault();

          const prevIndex = index === 0 ? enlaces.length - 1 : index - 1;

          enlaces[prevIndex].focus();

        } else if (e.key === 'Escape') {

          cerrarDropdown(dropdown);

          dropdownActivo = null;

          dropbtn.focus();

        }

      });

    });

  });

  

  // ============================================

  // OPTIMIZACIÓN DE RENDIMIENTO

  // ============================================

  

  // Debounce para eventos de scroll

  let ticking = false;

  function requestTick() {

    if (!ticking) {

      requestAnimationFrame(updateScrollEffects);

    }

    ticking = true;

  }

  

  function updateScrollEffects() {

    // Aquí van los efectos de scroll optimizados

    ticking = false;

  }

  

  // Lazy loading para componentes pesados

  const observer = new IntersectionObserver((entries) => {

    entries.forEach(entry => {

      if (entry.isIntersecting) {

        entry.target.classList.add('loaded');

      }

    });

  });

  

  // Observar elementos que necesitan lazy loading

  document.querySelectorAll('.lazy-load').forEach(el => {

    observer.observe(el);

  });



  // ============================================

  // FUNCIONALIDAD DE BÚSQUEDA PARA ADMINISTRADORES

  // ============================================



  // Helper: cerrar paneles de detalles y resetear botones/estados

  function resetAllDetailsPanels(){

    try{

      const stu = document.getElementById('student-details-sidebar');

      if (stu) stu.style.display = 'none';

      const prof = document.getElementById('professor-details-sidebar');

      if (prof) prof.style.display = 'none';

      const school = document.getElementById('school-details-sidebar');

      if (school) school.style.display = 'none';



      // Reset botones estudiantes

      document.querySelectorAll('.btn-ver-detalles').forEach(b=>{

        b.textContent = 'Ver Detalles';

        b.classList.add('btn-primary');

        b.classList.remove('btn-secondary');

        b.disabled = false;

      });

      // Reset botones facilitadores

      document.querySelectorAll('.btn-ver-detalles-profesor').forEach(b=>{

        b.textContent = 'Ver Detalles';

        b.classList.add('btn-primary');

        b.classList.remove('btn-secondary');

        b.disabled = false;

      });

      // Reset botones colegios

      document.querySelectorAll('.btn-ver-detalles-colegio').forEach(b=>{

        b.textContent = 'Ver Detalles';

        b.classList.add('btn-primary');

        b.classList.remove('btn-secondary');

        b.disabled = false;

      });

      // Deshabilitar botones Editar en tarjetas

      document.querySelectorAll('.btn-editar').forEach(btn=>{

        btn.style.opacity = '0.5';

        btn.style.pointerEvents = 'none';

        btn.classList.remove('enabled');

      });

    }catch(_e){}

  }



  // Cerrar paneles al cambiar el filtro del buscador

  const filtroSelectEl = document.getElementById('filtro-busqueda');

  if (filtroSelectEl){

    filtroSelectEl.addEventListener('change', resetAllDetailsPanels);

  }

  

  const buscador = document.getElementById('buscador-global');

  if (buscador) {

    buscador.addEventListener('submit', function(e) {

      e.preventDefault();

      

      const filtro = document.getElementById('filtro-busqueda').value;

      const query = document.getElementById('input-busqueda').value.trim();

      

      if (!query) {

        alert('Por favor ingresa un término de búsqueda');

        return;

      }

      // Antes de buscar, cerrar cualquier panel abierto y limpiar estados

      resetAllDetailsPanels();

      

      // Mostrar indicador de carga

      const searchBtn = buscador.querySelector('.search-btn');

      const originalHTML = searchBtn.innerHTML;

      searchBtn.innerHTML = '<div class="spinner"></div>';

      searchBtn.disabled = true;

      

      // Realizar búsqueda AJAX

      fetch(`/?page=dashboard&ajax=1&filtro=${encodeURIComponent(filtro)}&q=${encodeURIComponent(query)}`)

        .then(response => response.text())

        .then(html => {

          // Mostrar resultados en el panel dinámico

          const dashboardNormal = document.getElementById('dashboard-normal');

          const dashboardResultados = document.getElementById('dashboard-resultados');

          const overlay = document.getElementById('dashboard-overlay');

          

          if (dashboardNormal && dashboardResultados && overlay) {

            dashboardNormal.style.display = 'none';

            dashboardResultados.innerHTML = html;

            dashboardResultados.style.display = 'block';

            overlay.style.display = 'block';



            // Si el HTML de resultados incluyó una backbar inline, muévela fuera del contenedor

            // y elimina cualquier barra previa para evitar duplicados cuando se busca varias veces.

            const inlineBackBar = document.getElementById('backbar-inline');

            if (inlineBackBar) {

              const parent = dashboardResultados.parentNode;

              if (parent) {

                // 1) Eliminar cualquier barra existente ya montada

                const existingBar = document.getElementById('dashboard-backbar');

                if (existingBar && existingBar.parentNode) {

                  existingBar.parentNode.removeChild(existingBar);

                }



                // 2) Insertar la barra INMEDIATAMENTE ANTES del contenedor de resultados

                parent.insertBefore(inlineBackBar, dashboardResultados);

                inlineBackBar.id = 'dashboard-backbar';

                inlineBackBar.style.display = 'flex';



                const backBtn = inlineBackBar.querySelector('#back-to-dashboard');

                const syncBackBar = () => {

                  const rect = dashboardResultados.getBoundingClientRect();

                  inlineBackBar.style.maxWidth = `${rect.width}px`;

                  inlineBackBar.style.marginLeft = `${rect.left}px`;

                  // Separación solicitada respecto al encabezado

                  inlineBackBar.style.marginBottom = '0px';

                  inlineBackBar.style.marginTop = '35px';

                };

                syncBackBar();

                window.addEventListener('resize', syncBackBar);



                // 3) Acción de volver al dashboard y limpieza

                if (backBtn) {

                  backBtn.onclick = () => {

                    dashboardNormal.style.display = 'block';

                    dashboardResultados.style.display = 'none';

                    overlay.style.display = 'none';

                    // Cerrar paneles de detalles y resetear botones/estados

                    try { if (typeof resetAllDetailsPanels === 'function') resetAllDetailsPanels(); } catch(_e) {}

                    // Quitar la barra del DOM para no dejar residuos

                    const currentBar = document.getElementById('dashboard-backbar');

                    if (currentBar && currentBar.parentNode) {

                      currentBar.parentNode.removeChild(currentBar);

                    }

                    // Eliminar la hoja de estilos de resultados para que no altere el dashboard

                    const resStyles = Array.from(document.querySelectorAll('link[rel="stylesheet"][href*="/css/Componentes/resultados.css"]'));

                    resStyles.forEach(l => l.parentNode && l.parentNode.removeChild(l));

                    const input = document.getElementById('input-busqueda');

                    if (input) input.value = '';

                  };

                }

              }

            }

            

            // INICIALIZAR FUNCIONALIDAD DE BOTONES "VER DETALLES" DESPUÉS DE CARGAR AJAX

            if (typeof initializeStudentDetailsButtons === 'function') {

              try { initializeStudentDetailsButtons(); } catch(e) { console.warn(e); }

            }

            if (typeof initializeProfessorDetailsButtons === 'function') {

              try { initializeProfessorDetailsButtons(); } catch(e) { console.warn(e); }

            }

            if (typeof initializeSchoolDetailsButtons === 'function') {

              try { initializeSchoolDetailsButtons(); } catch(e) { console.warn(e); }

            }

          }

        })

        .catch(error => {

          console.error('Error en la búsqueda:', error);

          alert('Error al realizar la búsqueda. Inténtalo de nuevo.');

        })

        .finally(() => {

          // Restaurar botón

          searchBtn.innerHTML = originalHTML;

          searchBtn.disabled = false;

        });

    });

  }



}); // Closing brace for DOMContentLoaded



// ============================================

// FUNCIONALIDAD DE BOTONES "VER DETALLES" PARA ESTUDIANTES

// ============================================



function initializeStudentDetailsButtons() {

  // Manejar clicks en botones "Ver Detalles"

  const buttons = document.querySelectorAll('.btn-ver-detalles');

  

  buttons.forEach((button, index) => {

    // Remover listeners previos para evitar duplicados

    button.replaceWith(button.cloneNode(true));

    const newButton = document.querySelectorAll('.btn-ver-detalles')[index];

    

    newButton.addEventListener('click', function(e) {

      e.preventDefault();

      

      const resultCard = this.closest('.result-card');

      

      // PRIMERO: Resetear TODOS los botones (incluyendo este) usando lista ACTUAL

      document.querySelectorAll('.btn-ver-detalles').forEach(btn => {

        if (!btn || !btn.parentNode) return; // solo los que siguen en el DOM

        btn.textContent = 'Ver Detalles';

        btn.classList.add('btn-primary');

        btn.classList.remove('btn-secondary');

        btn.disabled = false;



        // Deshabilitar botón Editar de su tarjeta

        const card = btn.closest('.result-card');

        if (card) {

          const editBtn = card.querySelector('.btn-editar');

          if (editBtn) {

            editBtn.style.opacity = '0.5';

            editBtn.style.pointerEvents = 'none';

            editBtn.classList.remove('enabled');

          }

        }

      });

      

      // Mostrar el sidebar de detalles

      const sidebar = document.getElementById('student-details-sidebar');

      const detailsContainer = document.getElementById('student-details-content');

      

      if (!sidebar || !detailsContainer) {

        console.error('No se encontró el sidebar o contenedor de detalles!');

        return;

      }

      

      // Obtener datos del estudiante desde la tarjeta

      const studentData = extractStudentData(resultCard);

      

      // Mostrar el sidebar de detalles (profesor)

      sidebar.style.display = 'block';



      // Posicionar pegado al límite derecho del panel de resultados

      const placeProfSidebar = () => {

        const panel = document.getElementById('dashboard-resultados');

        if (!panel) return;

        const rect = panel.getBoundingClientRect();

        const gutter = 24;   // separación visual al borde

        // Top pegado al encabezado

        const header = document.querySelector('.header-unified');

        const headerBottom = header ? header.getBoundingClientRect().bottom : 68;

        const top = Math.max(0, Math.round(headerBottom) + 16);

        const left = rect.right + gutter;  // justo al borde derecho del panel + gutter

        const rightPadding = gutter;       // mismo gutter al borde derecho de la ventana

        const width = Math.max(320, window.innerWidth - left - rightPadding);

        const bottomPadding = gutter;

        // No sobrepasar el footer

        const footer = document.querySelector('.footer');

        const footerTop = footer ? footer.getBoundingClientRect().top : window.innerHeight;

        const maxHeightByViewport = window.innerHeight - top - bottomPadding;

        const maxHeightByFooter = footerTop - top - bottomPadding;

        const height = Math.max(200, Math.min(maxHeightByViewport, maxHeightByFooter));

        Object.assign(sidebar.style, {

          position: 'fixed',

          left: left + 'px',

          top: top + 'px',

          width: width + 'px',

          height: height + 'px',

          borderRadius: '29px',

          zIndex: 900

        });

      };

      placeProfSidebar();

      window.addEventListener('resize', placeProfSidebar);

      window.addEventListener('scroll', placeProfSidebar, { passive: true });



      // Misma lógica de pegado para estudiantes (misma UI)

      const placeStudentSidebar = () => {

        const panel = document.getElementById('dashboard-resultados');

        if (!panel) return;

        const rect = panel.getBoundingClientRect();

        const gutter = 24;

        // Top pegado al encabezado

        const header = document.querySelector('.header-unified');

        const headerBottom = header ? header.getBoundingClientRect().bottom : 68;

        const top = Math.max(0, Math.round(headerBottom) + 8);

        const left = rect.right + gutter;

        const rightPadding = gutter;

        const width = Math.max(320, window.innerWidth - left - rightPadding);

        const bottomPadding = gutter;

        const footer = document.querySelector('.footer');

        const footerTop = footer ? footer.getBoundingClientRect().top : window.innerHeight;

        const maxHeightByViewport = window.innerHeight - top - bottomPadding;

        const maxHeightByFooter = footerTop - top - bottomPadding;

        const height = Math.max(200, Math.min(maxHeightByViewport, maxHeightByFooter));

        Object.assign(sidebar.style, {

          position: 'fixed',

          left: left + 'px',

          top: top + 'px',

          width: width + 'px',

          height: height + 'px',

          borderRadius: '29px',

          zIndex: 900

        });

      };

      placeStudentSidebar();

      window.addEventListener('resize', placeStudentSidebar);

      window.addEventListener('scroll', placeStudentSidebar, { passive: true });

      

      // Mostrar detalles en el sidebar

      showStudentDetails(detailsContainer, studentData);

      

      // DESPUÉS: Cambiar estado SOLO de este botón

      this.textContent = 'Detalles Mostrados';

      this.classList.remove('btn-primary');

      this.classList.add('btn-secondary');

      this.disabled = true;

      

      // Habilitar SOLO el botón de editar de este estudiante

      const editButton = resultCard.querySelector('.btn-editar');

      if (editButton) {

        editButton.style.opacity = '1';

        editButton.style.pointerEvents = 'auto';

        editButton.classList.add('enabled');

      }

    });

  });

}



function extractStudentData(resultCard) {

  const data = {};

  

  // Extraer datos básicos del estudiante

  const nameElement = resultCard.querySelector('.result-header h4');

  data.fullName = nameElement ? nameElement.textContent.trim() : '';

  

  // Extraer detalles de los párrafos

  const detailsElements = resultCard.querySelectorAll('.result-details p');

  detailsElements.forEach(p => {

    const text = p.textContent;

    if (text.includes('Documento:')) {

      data.documento = text.replace('Documento:', '').trim();

    } else if (text.includes('Email:')) {

      data.email = text.replace('Email:', '').trim();

    } else if (text.includes('Teléfono:')) {

      data.telefono = text.replace('Teléfono:', '').trim();

    } else if (text.includes('Ficha:')) {

      data.ficha = text.replace('Ficha:', '').trim();

    } else if (text.includes('Colegio:')) {

      data.colegio = text.replace('Colegio:', '').trim();

    }

  });

  

  // Obtener ID del estudiante del botón

  data.id = resultCard.querySelector('.btn-ver-detalles').getAttribute('data-id');

  

  return data;

}

function showStudentDetails(container, studentData) {

  const detailsHTML = `

    <div class="student-details-content">

      <!-- Perfil del Aprendiz: Iniciales + Nombre -->

      <div class="data-container profile-card student-profile-block">

        <div class="profile" style="display:flex; align-items:center; gap:12px;">

          <div class="profile-avatar student-initials" aria-hidden="true">AP</div>

          <div class="profile-name student-name" style="font-size: 1.5rem; font-weight:700; color:#1e293b;">Cargando...</div>

        </div>

      </div>

      

      <!-- Datos del Aprendiz -->

      <div class="data-container">

        <div class="section-title">Datos del Aprendiz</div>

        <div class="data-grid" id="student-data-grid">

          <div class="data-item">

            <div class="data-label">Cargando...</div>

            <div class="data-value">Información del aprendiz</div>

          </div>

        </div>

      </div>

      

      <!-- Título de Detalles del Acudiente en contenedor propio (sin .data-container para igualar ancho) -->

      <div class="acudiente-title-container">

        <div class="details-header">

          <h2>Detalles del Acudiente</h2>

        </div>

      </div>



      <!-- Perfil del Acudiente: Iniciales + Nombre (contenedor aparte) -->

      <div class="data-container profile-card guardian-profile-block">

        <div class="profile" style="display:flex; align-items:center; gap:12px;">

          <div class="profile-avatar guardian-initials" aria-hidden="true">AC</div>

          <div class="profile-name acudiente-name" style="font-size: 1.2rem; font-weight:700; color:#1e293b;">Cargando...</div>

        </div>

      </div>



      <!-- Contenedor de grid del Acudiente -->

      <div class="data-container acudiente-container">

        <div class="section-title">Datos del Acudiente</div>

        <div class="data-grid" id="guardian-data-grid">

          <div class="data-item">

            <div class="data-label">Cargando...</div>

            <div class="data-value">Información del acudiente</div>

          </div>

        </div>

      </div>



      <!-- Información Médica -->

      <div class="data-container medical-container">

        <div class="section-title">Información Médica</div>

        <div class="data-grid" id="medical-data-grid">

          <div class="data-item">

            <div class="data-label">Cargando...</div>

            <div class="data-value">Información médica</div>

          </div>

        </div>

      </div>



      <!-- Fichas y Clases -->

      <div class="data-container classes-container">

        <div class="section-title">Fichas y Clases</div>

        <div class="data-grid" id="classes-data-grid">

          <div class="data-item">

            <div class="data-label">Cargando...</div>

            <div class="data-value">Fichas y clases</div>

          </div>

        </div>

      </div>

    </div>

  `;

  

  container.innerHTML = detailsHTML;

  

  // Cargar datos completos del estudiante y acudiente via AJAX

  if (studentData.id) {

    loadStudentCompleteData(studentData.id, container);

  } else {

    console.error('No se encontró ID del estudiante');

  }

}



function loadStudentCompleteData(studentId, container) {

  // Hacer llamada AJAX para obtener datos completos del estudiante y acudiente

  fetch(`./ajax/get_student_details.php?id=${studentId}`)

    .then(response => {

      return response.json();

    })

    .then(data => {

      if (data.success) {

        const student = data.student;

        

        // Actualizar nombre e iniciales del aprendiz

        const studentNameElement = container.querySelector('.student-name');

        const initialsEl = container.querySelector('.student-initials');

        const nombre = student.nombre_completo || `${student.nombres||''} ${student.apellidos||''}`.trim();

        if (studentNameElement) studentNameElement.textContent = nombre || 'No disponible';

        if (initialsEl) {

          const parts = (nombre || '').trim().split(/\s+/).filter(Boolean);

          let ini = '';

          if (parts.length) ini += (parts[0].charAt(0) || '').toUpperCase();

          if (parts.length > 1) ini += (parts[1].charAt(0) || '').toUpperCase();

          initialsEl.textContent = ini || 'AP';

        }

        

        // Actualizar detalles del aprendiz usando data-grid

        const studentDataGrid = container.querySelector('#student-data-grid');

        if (studentDataGrid) {

          // Preparar cada bloque y concatenar en el orden solicitado

          try { console.debug('[Detalles Aprendiz] Payload estudiante:', student); } catch(e) {}



          const docLabel = (student.tipo_documento && student.numero_documento)

            ? `${student.tipo_documento} ${student.numero_documento}`

            : (student.numero_documento || 'Sin registrar');

          const telLabel = student.telefono || 'Sin registrar';

          const emailLabel = student.email || 'Sin registrar';



          const blocks = {

            documento: `

              <div class="data-item">

                <div class="data-label">Documento</div>

                <div class="data-value">${docLabel}</div>

              </div>`,

            ficha: (function(){

              // Buscar el CÓDIGO de ficha en múltiples variantes comunes

              const candidates = [

                student.numero_ficha,

                student.num_ficha,

                student.numeroFicha,

                student.ficha_codigo,

                student.codigo_ficha,

                student.codigoFicha,

                student.codigo,

                student.ficha,

                student.ficha_id,

                student.id_ficha

              ];

              const codigo = (candidates.find(v => v !== undefined && v !== null && String(v).trim() !== '') || '').toString().trim();

              try { console.debug('[Detalles Aprendiz] Ficha candidatos:', candidates, '=> elegido:', codigo || '—'); } catch(e) {}

              const value = codigo || '—';

              return `

                <div class="data-item">

                  <div class="data-label">Ficha</div>

                  <div class="data-value">${value}</div>

                </div>`;

            })(),

            colegio: student.colegio_nombre ? `

              <div class="data-item">

                <div class="data-label">Colegio</div>

                <div class="data-value">${student.colegio_nombre}</div>

              </div>` : '',

            estado: student.estado ? `

              <div class="data-item">

                <div class="data-label">Estado</div>

                <div class="data-value"><span class="status-badge">${student.estado}</span></div>

              </div>` : '',

            email: `

              <div class="data-item">

                <div class="data-label">Email</div>

                <div class="data-value">${emailLabel}</div>

              </div>`,

            telefono: `

              <div class="data-item">

                <div class="data-label">Teléfono</div>

                <div class="data-value">${telLabel}</div>

              </div>`,

            jornada: (function(){

              const raw = (student.jornada || '').toString();

              const clean = raw.replace(/^\s*["']|["']\s*$/g, ''); // quitar comillas al inicio/fin

              return clean ? `

                <div class="data-item">

                  <div class="data-label">Jornada</div>

                  <div class="data-value">${clean}</div>

                </div>` : '';

            })()

          };



          const studentItems = [

            // Fila 1 (subir Teléfono)

            blocks.documento,

            blocks.ficha,

            blocks.colegio,

            blocks.telefono,

            // Fila 2 (Estado donde estaba Jornada)

            blocks.email,

            blocks.estado,

            blocks.jornada

          ].filter(Boolean).join('\n');

          try { console.debug('[Detalles Aprendiz] HTML items generado:', studentItems); } catch(e) {}



          studentDataGrid.innerHTML = studentItems || `

            <div class="data-item">

              <div class="data-label">Sin información</div>

              <div class="data-value">No hay información adicional del aprendiz</div>

            </div>`;

        }

        

        // Actualizar nombre e iniciales del acudiente

        const guardianNameElement = container.querySelector('.acudiente-name');

        if (guardianNameElement) {

          guardianNameElement.textContent = student.nombre_completo_acudiente || 'No disponible';

        }

        const guardianInitials = container.querySelector('.guardian-initials');

        if (guardianInitials) {

          const gname = (student.nombre_completo_acudiente || '').trim();

          const gparts = gname.split(/\s+/).filter(Boolean);

          let gini = '';

          if (gparts.length) gini += (gparts[0].charAt(0) || '').toUpperCase();

          if (gparts.length > 1) gini += (gparts[1].charAt(0) || '').toUpperCase();

          guardianInitials.textContent = gini || 'AC';

        }

        

        // Actualizar detalles del acudiente usando data-grid

        const guardianDataGrid = container.querySelector('#guardian-data-grid');

        if (guardianDataGrid) {

          let guardianItems = '';

          

          if (student.tipo_documento_acudiente && student.numero_documento_acudiente) {

            guardianItems += `

              <div class="data-item">

                <div class="data-label">Documento</div>

                <div class="data-value">${student.tipo_documento_acudiente} ${student.numero_documento_acudiente}</div>

              </div>`;

          }

          

          if (student.telefono_acudiente) {

            guardianItems += `

              <div class="data-item">

                <div class="data-label">Teléfono</div>

                <div class="data-value">${student.telefono_acudiente}</div>

              </div>`;

          }

          

          if (student.parentesco) {

            guardianItems += `

              <div class="data-item">

                <div class="data-label">Parentesco</div>

                <div class="data-value">${student.parentesco}</div>

              </div>`;

          }

          

          if (student.ocupacion) {

            guardianItems += `

              <div class="data-item">

                <div class="data-label">Ocupación</div>

                <div class="data-value">${student.ocupacion}</div>

              </div>`;

          }

          

          guardianDataGrid.innerHTML = guardianItems || `

            <div class="data-item">

              <div class="data-label">Sin información</div>

              <div class="data-value">No hay información del acudiente registrada</div>

            </div>`;

        }

        

        // Actualizar información médica

        const medicalDataGrid = container.querySelector('#medical-data-grid');

        if (medicalDataGrid) {

          let medicalItems = '';

          

          if (student.padece_enfermedad == 1) {

            medicalItems += `

              <div class="data-item">

                <div class="data-label">Enfermedad</div>

                <div class="data-value">${student.enfermedad_detalle || 'Sí'}</div>

              </div>`;

          }

          

          if (student.alergias == 1) {

            medicalItems += `

              <div class="data-item">

                <div class="data-label">Alergias</div>

                <div class="data-value">${student.alergias_detalle || student.alergias || 'No'}</div>

              </div>`;

          }

          

          if (student.medicamentos_permanentes == 1) {

            medicalItems += `

              <div class="data-item">

                <div class="data-label">Medicamentos</div>

                <div class="data-value">${student.medicamentos_detalle || 'Sí'}</div>

              </div>`;

          }

          

          if (student.discapacidad == 1) {

            medicalItems += `

              <div class="data-item">

                <div class="data-label">Discapacidad</div>

                <div class="data-value">${student.discapacidad_detalle || 'Sí'}</div>

              </div>`;

          }

          

          medicalDataGrid.innerHTML = medicalItems || `

            <div class="data-item">

              <div class="data-label">Sin información</div>

              <div class="data-value">No hay información médica registrada</div>

            </div>`;

        }

        

        // Actualizar fichas y clases

        const classesDataGrid = container.querySelector('#classes-data-grid');

        if (classesDataGrid) {

          let classesItems = '';

          

          // Total de clases

          classesItems += `

            <div class="data-item">

              <div class="data-label">Total Clases</div>

              <div class="data-value">${student.total_clases || 0}</div>

            </div>`;

          

          // Ficha actual

          if (student.numero_ficha) {

            classesItems += `

              <div class="data-item">

                <div class="data-label">Ficha Actual</div>

                <div class="data-value">${student.numero_ficha}</div>

              </div>`;

          }

          

          // Historial de fichas

          if (student.historial_fichas && student.historial_fichas.length > 0) {

            student.historial_fichas.forEach((ficha, index) => {

              const estado = ficha.fecha_retiro ? '(Retirado)' : '(Activa)';

              classesItems += `

                <div class="data-item">

                  <div class="data-label">Ficha ${ficha.numero}</div>

                  <div class="data-value">${ficha.nombre} ${estado}</div>

                </div>`;

            });

          }

          

          classesDataGrid.innerHTML = classesItems || `

            <div class="data-item">

              <div class="data-label">Sin información</div>

              <div class="data-value">No hay fichas registradas</div>

            </div>`;

        }

        

      } else {

        // Si no hay datos, mostrar mensaje de error

        const dataGrids = container.querySelectorAll('.data-grid');

        dataGrids.forEach(grid => {

          grid.innerHTML = `

            <div class="data-item">

              <div class="data-label">Error</div>

              <div class="data-value">No se pudieron cargar los datos</div>

            </div>`;

        });

      }

    })

    .catch(error => {

      console.error('Error cargando datos completos:', error);

      console.error('URL intentada:', `./ajax/get_student_details.php?id=${studentId}`);

      const dataGrids = container.querySelectorAll('.data-grid');

      dataGrids.forEach(g => {

        g.innerHTML = `

          <div class="data-item">

            <div class="data-label">Error</div>

            <div class="data-value">Error al cargar información</div>

          </div>`;

      });

    });

}



// ============================================

// FUNCIONALIDAD DE BOTONES PARA FACILITADORES/PROFESORES

// ============================================



// Manejar clic en botones de exportar de la búsqueda

document.addEventListener('click', function(e) {

  const btn = e.target.closest('.btn-exportar-profesor');

  if (!btn) return;

  

  e.preventDefault();

  const profesorId = btn.getAttribute('data-id');

  if (!profesorId) return;

  

  // Obtener el rango de fechas del mes actual

  const now = new Date();

  const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);

  const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

  

  // Construir URL de exportación

  const url = new URL(window.location.href);

  url.searchParams.set('page', 'calendario_exportar');

  url.searchParams.set('action', 'exportarReporte');

  url.searchParams.set('profesor_id', profesorId);

  url.searchParams.set('fecha_inicio', firstDay.toISOString().split('T')[0]);

  url.searchParams.set('fecha_fin', lastDay.toISOString().split('T')[0]);

  

  // Abrir en nueva pestaña para iniciar la descarga

  window.open(url.toString(), '_blank');

  

  // Mostrar notificación

  mostrarNotificacionTemporal('Generando reporte de clases...', 'info');

});



// ==========================

// CERRAR SIDEbars DE DETALLES (delegado)

// ==========================

document.addEventListener('click', function(e) {

  // Cerrar detalles de Facilitador/Instructor

  if (e.target && e.target.id === 'close-prof-details') {

    const profSidebar = document.getElementById('professor-details-sidebar');

    if (profSidebar) {

      profSidebar.style.display = 'none';

      profSidebar.classList.remove('active');

      // Resetear botones de "Ver Detalles" (profesores)

      document.querySelectorAll('.btn-ver-detalles-profesor').forEach(b => {

        b.textContent = 'Ver Detalles';

        b.classList.remove('btn-secondary');

        b.classList.add('btn-primary');

        b.disabled = false;

      });

      // Actualizar layout del contenedor de resultados

      if (typeof updateResultsContainerForSidebars === 'function') {

        updateResultsContainerForSidebars();

      }

    }

  }



  // Cerrar detalles de Aprendiz

  if (e.target && e.target.id === 'close-details') {

    const studentSidebar = document.getElementById('student-details-sidebar');

    if (studentSidebar) studentSidebar.style.display = 'none';

    // Resetear botones de "Ver Detalles" (estudiantes)

    document.querySelectorAll('.btn-ver-detalles').forEach(b => {

      b.textContent = 'Ver Detalles';

      b.classList.remove('btn-secondary');

      b.classList.add('btn-primary');

      b.disabled = false;

    });

    // Deshabilitar botones de Editar en tarjetas

    document.querySelectorAll('.btn-editar').forEach(btn => {

      btn.style.opacity = '0.5';

      btn.style.pointerEvents = 'none';

      btn.classList.remove('enabled');

    });

  }



  // Cerrar detalles de Colegio

  if (e.target && e.target.id === 'close-school-details') {

    const schoolSidebar = document.getElementById('school-details-sidebar');

    if (schoolSidebar) schoolSidebar.style.display = 'none';

    // Resetear botones de "Ver Detalles" (colegios)

    document.querySelectorAll('.btn-ver-detalles-colegio').forEach(b => {

      b.textContent = 'Ver Detalles';

      b.classList.remove('btn-secondary');

      b.classList.add('btn-primary');

      b.disabled = false;

    });

  }

});



// ============================================

// FUNCIONALIDAD DE BOTONES "VER DETALLES" PARA FACILITADORES/PROFESORES

// ============================================



function initializeProfessorDetailsButtons() {

  const buttons = document.querySelectorAll('.btn-ver-detalles-profesor');

  if (!buttons || buttons.length === 0) return;



  buttons.forEach((button, index) => {

    // Limpiar listeners previos

    button.replaceWith(button.cloneNode(true));

    const newButton = document.querySelectorAll('.btn-ver-detalles-profesor')[index];



    newButton.addEventListener('click', function(e) {

      e.preventDefault();



      // Reset de todos los botones de profesor

      document.querySelectorAll('.btn-ver-detalles-profesor').forEach(b => {

        if (!b || !b.parentNode) return;

        b.textContent = 'Ver Detalles';

        b.classList.remove('btn-secondary');

        b.classList.add('btn-primary');

        b.disabled = false;

      });



      const sidebar = document.getElementById('professor-details-sidebar');

      const detailsContainer = document.getElementById('professor-details-content');

      if (!sidebar || !detailsContainer) {

        console.error('No se encontró el sidebar de Facilitador');

        return;

      }



      // Esqueleto inicial del panel (igual estilo que Aprendiz)

      detailsContainer.innerHTML = `

        <div class="student-details-content">

          <!-- Perfil del Facilitador: Iniciales + Nombre -->

          <div class="data-container profile-card professor-profile-block">

            <div class="profile" style="display:flex; align-items:center; gap:12px; justify-content:space-between; width:100%;">

              <!-- Bloque centrado visualmente -->

              <div style="flex:1; display:flex; justify-content:center; align-items:center; gap:12px;">

                <div class="profile-avatar prof-initials" aria-hidden="true">PF</div>

                <div class="profile-name prof-name" style="font-size: 1.5rem; font-weight:700; color:#1e293b;">Cargando...</div>

              </div>

              <!-- Acciones a la derecha, una sobre otra -->

              <div class="actions-bar" style="display:flex; flex-direction:column; gap:8px; align-items:flex-end;">

                <button class="btn" id="btnVerCalendarioProf" style="background:#39A900; color:#fff; min-width:180px; width:180px;">Ver Calendario</button>

                <button class="btn" id="btnExportarExcelProf" style="background:#00304D; color:#fff; min-width:180px; width:180px;">Exportar Excel</button>

              </div>

            </div>

          </div>

          <div class="data-container">

            <div class="section-title" id="prof-datos-title">Datos del Instructor/Facilitador</div>

            <div class="data-grid" id="professor-data-grid">

              <div class="data-item"><div class="data-label">Cargando...</div><div class="data-value">—</div></div>

            </div>

          </div>

          <div class="data-container">

            <div class="section-title">Fichas y Próximas Clases</div>

            <!-- Filtros internos para alternar -->

            <div class="fp-filters" style="display:flex; gap:8px; justify-content:flex-end; margin-bottom:8px;">

              <button type="button" id="tabFichas" class="btn fp-tab active" data-tab="fichas" style="background:#39A900; color:#fff;">Fichas</button>

              <button type="button" id="tabClases" class="btn fp-tab" data-tab="clases" style="background:#00304D; color:#fff;">Clases</button>

            </div>

            <div class="fp-wrapper" style="position:relative; min-height: 80px;">

              <div class="data-grid" id="professor-fichas-grid">

                <div class="data-item"><div class="data-label">Cargando...</div><div class="data-value">—</div></div>

              </div>

              <div class="data-grid" id="professor-clases-grid" style="display:none;">

                <div class="data-item"><div class="data-label">Cargando...</div><div class="data-value">—</div></div>

              </div>

            </div>

          </div>

          <div class="data-container">

            <div class="section-title">Resumen de actividad</div>

            <div class="data-grid" id="professor-otros-grid">

              <div class="data-item"><div class="data-label">Cargando...</div><div class="data-value">—</div></div>

            </div>

          </div>

        </div>`;



      // Mostrar panel
      sidebar.style.display = 'block';
      setTimeout(() => {
        sidebar.classList.add('active');
      }, 10);

      // Posicionar el sidebar del facilitador pegado al panel de resultados (igual al de Aprendiz)

      const placeProfessorSidebar = () => {

        const panel = document.getElementById('dashboard-resultados');

        if (!panel) return;

        const rect = panel.getBoundingClientRect();

        const gutter = 24;

        const header = document.querySelector('.header-unified');

        const headerBottom = header ? header.getBoundingClientRect().bottom : 68;

        const top = Math.max(0, Math.round(headerBottom) + 8);

        const left = rect.right + gutter;

        const rightPadding = gutter;

        const width = Math.max(320, window.innerWidth - left - rightPadding);

        const bottomPadding = gutter;

        const footer = document.querySelector('.footer');

        const footerTop = footer ? footer.getBoundingClientRect().top : window.innerHeight;

        const maxHeightByViewport = window.innerHeight - top - bottomPadding;

        const maxHeightByFooter = footerTop - top - bottomPadding;

        const height = Math.max(200, Math.min(maxHeightByViewport, maxHeightByFooter));

        Object.assign(sidebar.style, {

          position: 'fixed',

          left: left + 'px',

          top: top + 'px',

          width: width + 'px',

          height: height + 'px',

          borderRadius: '29px',

          zIndex: 900

        });

      };

      placeProfessorSidebar();

      window.addEventListener('resize', placeProfessorSidebar);

      window.addEventListener('scroll', placeProfessorSidebar, { passive: true });

      // Actualizar layout del contenedor de resultados
      if (typeof updateResultsContainerForSidebars === 'function') {
        updateResultsContainerForSidebars();
      }

      const id = this.getAttribute('data-id');

      if (!id) return;

      loadProfessorCompleteData(id, detailsContainer);



      // Marcar botón activo

      this.textContent = 'Detalles Mostrados';

      this.classList.remove('btn-primary');

      this.classList.add('btn-secondary');

      this.disabled = true;

    });

  });

}



function loadProfessorCompleteData(profId, container) {

  fetch(`./ajax/get_profesor_details.php?id=${encodeURIComponent(profId)}`)

    .then(r => r.json())

    .then(data => {

      if (!data.success) throw new Error(data.message || 'Error');

      const prof = data.profesor || {};

      const fichas = Array.isArray(data.fichas) ? data.fichas : [];

      const fichasCompartidas = Array.isArray(data.fichas_compartidas)

        ? data.fichas_compartidas

        : fichas.filter(f => String(f.tipo || '').toLowerCase() === 'compartida');

      const materias = data.materias || [];

      const clases = Array.isArray(data.clases) ? data.clases : [];



      // Wire acciones

      const btnCal = container.querySelector('#btnVerCalendarioProf');

      const btnExport = container.querySelector('#btnExportarExcelProf');

      

      // Configurar botón de exportación

      if (btnExport) {

        btnExport.addEventListener('click', () => {

          // Obtener el rango de fechas del mes actual

          const now = new Date();

          const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);

          const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

          

          // Construir URL de exportación

          const url = new URL(window.location.href);

          url.searchParams.set('page', 'calendario_exportar');

          url.searchParams.set('action', 'exportarReporte');

          url.searchParams.set('profesor_id', profId);

          url.searchParams.set('fecha_inicio', firstDay.toISOString().split('T')[0]);

          url.searchParams.set('fecha_fin', lastDay.toISOString().split('T')[0]);

          // Modo depuración temporal: pedir al backend JSON en vez de Excel

          url.searchParams.set('debug', '1');

          

          // Abrir en nueva pestaña para iniciar la descarga

          window.open(url.toString(), '_blank');

          

          // Mostrar notificación

          mostrarNotificacionTemporal('Generando reporte de clases...', 'info');

        });

      }

      

      // Configurar botón de calendario

      if (btnCal) {

        btnCal.addEventListener('click', () => {

          // Navegar al calendario filtrado por facilitador (param esperado por backend)

          window.location.href = `/?page=calendario&facilitador_id=${encodeURIComponent(profId)}`;

        });

      }

      const btnExcel = container.querySelector('#btnExportarExcelProf');

      if (btnExcel) {

        // Mantener compatibilidad si existe una función específica; de lo contrario, usar la descarga por URL

        if (typeof exportarExcelClasesProfesor === 'function') {

          btnExcel.addEventListener('click', () => exportarExcelClasesProfesor(prof, clases));

        }

      }



      // Nombre

      const nameEl = container.querySelector('.prof-name');

      if (nameEl) nameEl.textContent = (prof.nombres && prof.apellidos) ? `${prof.nombres} ${prof.apellidos}` : 'Sin nombre';



      // Iniciales

      const initialsEl = container.querySelector('.prof-initials');

      if (initialsEl) {

        const n = (prof.nombres || '').trim();

        const a = (prof.apellidos || '').trim();

        let ini = '';

        if (n) ini += (n.charAt(0) || '').toUpperCase();

        if (a) ini += (a.charAt(0) || '').toUpperCase();

        initialsEl.textContent = ini || 'PF';

      }



      // Ajustar título 'Datos del ...' según tipo de contrato

      const contrato = ((prof.tip_contrato || prof.tipo_contrato || '') + '').toLowerCase().trim();

      const rolLabel = (contrato === 'planta' || contrato === 'instructor') ? 'Instructor' : 'Facilitador';

      const datosTitle = container.querySelector('#prof-datos-title');

      if (datosTitle) datosTitle.textContent = `Datos del ${rolLabel}`;



      // Datos principales (filas horizontales: 4 por fila)

      const grid = container.querySelector('#professor-data-grid');

      if (grid) {

        const titleCase = (str) => {

          if (!str) return '';

          const s = String(str).toLowerCase();

          return s.replace(/\b([a-záéíóúñü])([a-záéíóúñü]*)/gi, (m, p1, p2) => p1.toUpperCase() + p2);

        };

        const item = (label, value) => {

          const v = (value ?? '').toString().trim();

          if (!v) return '';

          return `<div class=\"data-item\"><div class=\"data-label\">${label}</div><div class=\"data-value\">${v}</div></div>`;

        };

        const contratoTxt = prof.tip_contrato || prof.tipo_contrato || '';

        const itemsHtml = [

          // Orden solicitado

          item('Documento', `${prof.tipo_documento || ''} ${prof.numero_documento || ''}`.trim()),

          item('Email', prof.correo_electronico),

          item('Email Institucional', prof.correo_institucional),

          item('Teléfono', prof.telefono),

          item('Especialidad', prof.especialidad),

          item('Tipo de Contrato', contratoTxt),

          item('Fecha Ingreso', prof.fecha_ingreso),

          item('Colegio', titleCase(prof.colegio_nombre))

        ].filter(Boolean).join('');

        grid.innerHTML = itemsHtml;

        if (!itemsHtml) {

          grid.innerHTML = '<div class="data-item"><div class="data-label">—</div><div class="data-value">Sin datos</div></div>';

        }

      }



      // Fichas y próximas clases (con filtro)

      const fichasGrid = container.querySelector('#professor-fichas-grid');

      const clasesGrid = container.querySelector('#professor-clases-grid');

      const fpWrapper  = container.querySelector('.fp-wrapper');

      if (fichasGrid) {

        const fh = fichas.length

          ? fichas.map(f=>`<div class=\"data-item\"><div class=\"data-label\">Ficha</div><div class=\"data-value\">${(f.numero_ficha||f.numero||f.id)} - ${f.nombre||''}</div></div>`).join('')

          : '<div class="data-item"><div class="data-label">—</div><div class="data-value">Sin fichas</div></div>';

        fichasGrid.innerHTML = fh;

      }

      if (clasesGrid) {

        const ch = clases.length

          ? clases.map(c=>`<div class=\"data-item\"><div class=\"data-label\">Clase</div><div class=\"data-value\">${c.fecha_inicio} → ${c.fecha_fin} ${c.aula?('| '+c.aula):''} ${c.estado?('| '+c.estado):''}</div></div>`).join('')

          : '<div class="data-item"><div class="data-label">—</div><div class="data-value">Sin clases próximas</div></div>';

        clasesGrid.innerHTML = ch;

      }



      // Estado inicial: mostrar solo fichas (usar 'grid' explícito)

      if (fichasGrid) fichasGrid.style.display = 'grid';

      if (clasesGrid) clasesGrid.style.display = 'none';

      const tabF = container.querySelector('#tabFichas');

      const tabC = container.querySelector('#tabClases');

      const setActive = (isFichas) => {

        if (isFichas) {

          if (fichasGrid) fichasGrid.style.display = 'grid';

          if (clasesGrid) clasesGrid.style.display = 'none';

          if (tabF) tabF.classList.add('active');

          if (tabC) tabC.classList.remove('active');

        } else {

          if (fichasGrid) fichasGrid.style.display = 'none';

          if (clasesGrid) clasesGrid.style.display = 'grid';

          if (tabC) tabC.classList.add('active');

          if (tabF) tabF.classList.remove('active');

        }

      };

      if (tabF) tabF.addEventListener('click', (e)=>{ e.preventDefault(); setActive(true); });

      if (tabC) tabC.addEventListener('click', (e)=>{ e.preventDefault(); setActive(false); });



      // Fallback por delegación dentro del sidebar del profesor

      const profSidebar = document.getElementById('professor-details-sidebar');

      if (profSidebar) {

        profSidebar.addEventListener('click', (e) => {

          const tab = e.target.closest('.fp-tab');

          if (!tab) return;

          const target = tab.getAttribute('data-tab');

          if (target === 'fichas') setActive(true); else if (target === 'clases') setActive(false);

        });

      }



      // Resumen de actividad (fichas totales, fichas compartidas y clases de la semana)

      const otros = container.querySelector('#professor-otros-grid');

      if (otros) {

        let html = '';

        const totalFichas = fichas.length;

        const compartidasCount = fichasCompartidas.length;

        html += `<div class="data-item"><div class="data-label">Fichas</div><div class="data-value">${totalFichas}</div></div>`;

        html += `<div class="data-item"><div class="data-label">Fichas compartidas</div><div class="data-value">${compartidasCount}</div></div>`;

        html += `<div class="data-item"><div class="data-label">Clases semana</div><div class="data-value">${clases.length}</div></div>`;

        otros.innerHTML = html;

      }

    })

    .catch(err => {

      console.error('Error cargando colegio:', err);

      const grids = container.querySelectorAll('.data-grid');

      grids.forEach(g => {

        g.innerHTML = '<div class="data-item"><div class="data-label">Error</div><div class="data-value">No se pudieron cargar los datos del colegio</div></div>';

      });

    });

}

// Agregar evento para cerrar el sidebar de colegios (solo si existe en la página)

const closeSchoolBtn = document.getElementById('close-school-details');

if (closeSchoolBtn) {

  closeSchoolBtn.addEventListener('click', () => {

    const sidebar = document.getElementById('school-details-sidebar');

    if (sidebar) {

      sidebar.style.display = 'none';

      sidebar.classList.remove('active');

      // Resetear botones de colegio

      document.querySelectorAll('.btn-ver-detalles-colegio').forEach(b => {

        if (!b || !b.parentNode) return;

        b.textContent = 'Ver Detalles';

        b.classList.remove('btn-secondary');

        b.classList.add('btn-primary');

        b.disabled = false;

      });

      // Actualizar layout del contenedor de resultados

      if (typeof updateResultsContainerForSidebars === 'function') {

        updateResultsContainerForSidebars();

      }

    }

  });

}



// ============================================

// FUNCIONALIDAD DE BOTONES "VER DETALLES" PARA COLEGIOS

// ============================================



function initializeSchoolDetailsButtons() {

  const buttons = document.querySelectorAll('.btn-ver-detalles-colegio');

  if (!buttons || buttons.length === 0) return;



  buttons.forEach((button, index) => {

    // Limpiar listeners previos

    button.replaceWith(button.cloneNode(true));

    const newButton = document.querySelectorAll('.btn-ver-detalles-colegio')[index];



    newButton.addEventListener('click', function(e) {

      e.preventDefault();



      // Reset de todos los botones de colegio

      document.querySelectorAll('.btn-ver-detalles-colegio').forEach(b => {

        if (!b || !b.parentNode) return;

        b.textContent = 'Ver Detalles';

        b.classList.remove('btn-secondary');

        b.classList.add('btn-primary');

        b.disabled = false;

      });



      const sidebar = document.getElementById('school-details-sidebar');

      const detailsContainer = document.getElementById('school-details-content');

      if (!sidebar || !detailsContainer) {

        console.error('No se encontró el sidebar de Colegio');

        return;

      }



      // Esqueleto inicial del panel (mismo estilo que Aprendiz/Facilitador)

      detailsContainer.innerHTML = `

        <div class="student-details-content">

          <div class="data-container profile-card school-profile-block">

            <div class="profile" style="display:flex; align-items:center; gap:12px; justify-content:center;">

              <div class="profile-avatar school-initials" aria-hidden="true">SC</div>

              <div class="profile-name school-name" style="font-size: 1.5rem; font-weight:700; color:#1e293b; text-align:center;">Cargando...</div>

            </div>

          </div>



          <div class="data-container">

            <div class="section-title">Datos del Colegio</div>

            <div class="data-grid" id="school-data-grid">

              <div class="data-item"><div class="data-label">Cargando...</div><div class="data-value">Información del colegio</div></div>

            </div>

          </div>



          <div class="data-container">

            <div class="section-title">Grados y Jornadas</div>

            <div class="data-grid" id="school-grados-grid">

              <div class="data-item"><div class="data-label">Cargando...</div><div class="data-value">Información académica</div></div>

            </div>

          </div>



          <div class="data-container">

            <div class="section-title">Cursos / Programas</div>

            <div class="data-grid" id="school-materias-grid">

              <div class="data-item"><div class="data-label">Cargando...</div><div class="data-value">Listado de cursos</div></div>

            </div>

          </div>

        </div>`;

      // Mostrar panel
      sidebar.style.display = 'block';
      setTimeout(() => {
        sidebar.classList.add('active');
      }, 10);

      // Posicionar el sidebar del colegio pegado al panel de resultados

      const placeSchoolSidebar = () => {

        const panel = document.getElementById('dashboard-resultados');

        if (!panel) return;

        const rect = panel.getBoundingClientRect();

        const gutter = 24;

        const header = document.querySelector('.header-unified');

        const headerBottom = header ? header.getBoundingClientRect().bottom : 68;

        const top = Math.max(0, Math.round(headerBottom) + 8);

        const left = rect.right + gutter;

        const rightPadding = gutter;

        const width = Math.max(320, window.innerWidth - left - rightPadding);

        const bottomPadding = gutter;

        const footer = document.querySelector('.footer');

        const footerTop = footer ? footer.getBoundingClientRect().top : window.innerHeight;

        const maxHeightByViewport = window.innerHeight - top - bottomPadding;

        const maxHeightByFooter = footerTop - top - bottomPadding;

        const height = Math.max(200, Math.min(maxHeightByViewport, maxHeightByFooter));

        Object.assign(sidebar.style, {

          position: 'fixed',

          left: left + 'px',

          top: top + 'px',

          width: width + 'px',

          height: height + 'px',

          borderRadius: '29px',

          zIndex: 900

        });

      };

      placeSchoolSidebar();

      window.addEventListener('resize', placeSchoolSidebar);

      window.addEventListener('scroll', placeSchoolSidebar, { passive: true });

      // Actualizar layout del contenedor de resultados
      if (typeof updateResultsContainerForSidebars === 'function') {
        updateResultsContainerForSidebars();
      }

      const id = this.getAttribute('data-id');

      if (!id) return;

      loadSchoolCompleteData(id, detailsContainer);



      this.textContent = 'Detalles Mostrados';

      this.classList.remove('btn-primary');

      this.classList.add('btn-secondary');

      this.disabled = true;

    });

  });

}



function loadSchoolCompleteData(schoolId, container) {

  fetch(`./ajax/get_colegio_details.php?id=${encodeURIComponent(schoolId)}`)

    .then(r => r.json())

    .then(data => {

      if (!data.success) throw new Error(data.message || 'Error');

      const school = data.colegio || {};



      const titleCase = (str) => {

        if (!str) return '';

        const s = String(str).toLowerCase();

        return s.replace(/\b([a-záéíóúñü])([a-záéíóúñü]*)/gi, (m, p1, p2) => p1.toUpperCase() + p2);

      };



      // Nombre e iniciales del colegio

      const nameEl = container.querySelector('.school-name');

      const initialsEl = container.querySelector('.school-initials');

      const nombreRaw = (school.nombre || '').toString().trim();

      const nombre = nombreRaw || 'Sin nombre';

      if (nameEl) nameEl.textContent = titleCase(nombre);

      if (initialsEl) {

        const parts = nombreRaw.split(/\s+/).filter(Boolean);

        let ini = '';

        if (parts.length) ini += (parts[0].charAt(0) || '').toUpperCase();

        if (parts.length > 1) ini += (parts[1].charAt(0) || '').toUpperCase();

        initialsEl.textContent = ini || 'SC';

      }



      // Datos principales

      const dataGrid = container.querySelector('#school-data-grid');

      if (dataGrid) {

        const item = (label, value, allowEmpty = false) => {

          const v = (value ?? '').toString().trim();

          if (!v && !allowEmpty) return '';

          return `<div class=\"data-item\"><div class=\"data-label\">${label}</div><div class=\"data-value\">${v || 'Sin registrar'}</div></div>`;

        };



        const tel = school.telefono || '';

        const mail = school.correo || school.email || '';

        const itemsHtml = [

          item('Código DANE', school.codigo_dane),

          item('NIT', school.nit),

          item('Tipo de Institución', school.tipo_institucion),

          item('Dirección', school.direccion),

          item('Teléfono', tel || 'Sin registrar', true),

          item('Email', mail || 'Sin registrar', true),

          item('Municipio', titleCase(school.municipio || '')),

          item('Departamento', titleCase(school.departamento || '')),

          item('Estado', school.estado)

        ].filter(Boolean).join('');



        dataGrid.innerHTML = itemsHtml || '<div class="data-item"><div class="data-label">—</div><div class="data-value">Sin datos del colegio</div></div>';

      }



      // Grados y jornadas

      const gradosGrid = container.querySelector('#school-grados-grid');

      if (gradosGrid) {

        const asArray = (v) => {

          if (!v) return [];

          if (Array.isArray(v)) return v;

          return String(v).split(',').map(s => s.trim()).filter(Boolean);

        };

        const buildChips = (items, emptyLabel, transformFn) => {

          const arr = (items || []).map(v => {

            const s = String(v).trim();

            if (!s) return '';

            return transformFn ? transformFn(s) : s;

          }).filter(Boolean);

          if (!arr.length) {

            return `<span class="school-chip school-chip-empty">${emptyLabel}</span>`;

          }

          return arr.map(v => `<span class="school-chip">${v}</span>`).join(' ');

        };



        const grados = asArray(school.grados);

        const jornadas = asArray(school.jornada);



        let html = '';

        html += `

          <div class="data-item">

            <div class="data-label">Grados</div>

            <div class="data-value school-chip-row">${buildChips(grados, 'Sin información', null)}</div>

          </div>`;

        html += `

          <div class="data-item">

            <div class="data-label">Jornadas</div>

            <div class="data-value school-chip-row">${buildChips(jornadas, 'Sin información', titleCase)}</div>

          </div>`;



        gradosGrid.innerHTML = html;

      }



      // Cursos / programas (priorizar fichas asociadas al colegio)

      const materiasGrid = container.querySelector('#school-materias-grid');

      if (materiasGrid) {

        const fichas = Array.isArray(school.fichas) ? school.fichas : [];

        const materias = Array.isArray(school.materias) ? school.materias : [];

        let html = '';



        if (fichas.length) {

          html = fichas.map(f => {

            const numero = (f && f.numero != null) ? String(f.numero).trim() : '';

            const nombreFicha = (f && f.nombre) ? String(f.nombre).trim() : '';

            const label = numero && nombreFicha ? `${numero} – ${nombreFicha}` : (numero || nombreFicha || 'Sin nombre');

            return `<div class=\"data-item\"><div class=\"data-label\">Ficha</div><div class=\"data-value\">${label}</div></div>`;

          }).join('');

        } else if (materias.length) {

          html = materias.map(m => {

            const nombre = m && (m.nombre || m.nombre_curso || m.titulo) || '';

            const v = nombre.toString().trim();

            return `<div class=\"data-item\"><div class=\"data-label\">Curso</div><div class=\"data-value\">${v || 'Sin nombre'}</div></div>`;

          }).join('');

        } else {

          html = '<div class="data-item"><div class="data-label">Cursos</div><div class="data-value">Sin cursos asociados</div></div>';

        }



        materiasGrid.innerHTML = html;

      }

    })

    .catch(err => {

      console.error('Error cargando colegio:', err);

      const grids = container.querySelectorAll('.data-grid');

      grids.forEach(g => {

        g.innerHTML = '<div class="data-item"><div class="data-label">Error</div><div class="data-value">No se pudieron cargar los datos del colegio</div></div>';

      });

    });

}

