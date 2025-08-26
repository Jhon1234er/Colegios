// ============================================
// COMPORTAMIENTO MEJORADO DE LA NAVBAR Y MODAL
// ============================================

document.addEventListener('DOMContentLoaded', function () {
  
  // ============================================
  // CONFIGURACIÓN DEL MODAL DEL BUSCADOR
  // ============================================
  
  const buscador = document.getElementById('buscador-global');
  const dashboardOverlay = document.getElementById('dashboard-overlay');
  const dashboardResultados = document.getElementById('dashboard-resultados');
  const dashboardNormal = document.getElementById('dashboard-normal');
  
  if (buscador) {
    buscador.addEventListener('submit', function (e) {
      e.preventDefault();
      
      const filtro = document.getElementById('filtro-busqueda').value;
      const query = document.getElementById('input-busqueda').value.trim();
      
      if (!query) {
        // Mostrar mensaje de error suave
        mostrarNotificacionTemporal('Por favor ingresa un término de búsqueda', 'warning');
        return;
      }
      
      // Mostrar indicador de carga
      const submitBtn = buscador.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.classList.add('btn-loading');
      submitBtn.disabled = true;
      
      // Realizar búsqueda
      fetch(`/?page=dashboard&filtro=${encodeURIComponent(filtro)}&q=${encodeURIComponent(query)}&ajax=1`)
        .then(res => {
          if (!res.ok) throw new Error('Error en la búsqueda');
          return res.text();
        })
        .then(html => {
          // Configurar el contenido del modal
          dashboardResultados.innerHTML = `
            <div class="modal-header">
              <h3 class="modal-title">Resultados de búsqueda: "${query}"</h3>
              <button id="volver-dashboard" class="modal-close-btn" title="Cerrar">
                <span>×</span>
              </button>
            </div>
            <div class="modal-content">
              ${html}
            </div>
          `;
          
          // Mostrar el modal con animaciones
          mostrarModal();
          
          // Configurar el botón de cerrar
          configurarBotonesCerrarModal();
          
        })
        .catch(error => {
          console.error('Error en la búsqueda:', error);
          mostrarNotificacionTemporal('Error al realizar la búsqueda. Intenta nuevamente.', 'error');
        })
        .finally(() => {
          // Remover indicador de carga
          submitBtn.classList.remove('btn-loading');
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
        });
    });
  }
  
  // ============================================
  // FUNCIONES DEL MODAL
  // ============================================
  
  function mostrarModal() {
    // Prevenir scroll del body
    document.body.classList.add('no-scroll');
    
    // Mostrar overlay
    dashboardOverlay.classList.add('active');
    
    // Animar dashboard normal
    if (dashboardNormal) {
      dashboardNormal.classList.add('anim-out');
    }
    
    // Mostrar y animar resultados
    dashboardResultados.style.display = 'block';
    dashboardResultados.classList.remove('anim-out');
    
    // Forzar reflow para la animación
    void dashboardResultados.offsetWidth;
    
    dashboardResultados.classList.add('anim-in');
  }
  
  function cerrarModal() {
    // Animar cierre
    dashboardResultados.classList.remove('anim-in');
    dashboardResultados.classList.add('anim-out');
    dashboardOverlay.classList.remove('active');
    
    if (dashboardNormal) {
      dashboardNormal.classList.remove('anim-out');
    }
    
    // Restaurar scroll del body
    document.body.classList.remove('no-scroll');
    
    // Ocultar después de la animación
    setTimeout(() => {
      dashboardResultados.style.display = 'none';
      dashboardResultados.classList.remove('anim-out');
      if (dashboardNormal) {
        dashboardNormal.style.display = '';
      }
    }, 400);
  }
  
  function configurarBotonesCerrarModal() {
    // Botón cerrar específico
    const volverBtn = document.getElementById('volver-dashboard');
    if (volverBtn) {
      volverBtn.addEventListener('click', cerrarModal);
    }
    
    // Cerrar al hacer clic en el overlay (fuera del modal)
    dashboardOverlay.addEventListener('click', function(e) {
      if (e.target === dashboardOverlay) {
        cerrarModal();
      }
    });
  }
  
  // Cerrar modal con tecla ESC
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && dashboardOverlay.classList.contains('active')) {
      cerrarModal();
    }
  });
  
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
      }, 300); // Delay de 300ms para permitir movimiento del mouse
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
      }, 300);
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
          console.error("❌ Respuesta no es JSON válido:", text);
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
          console.error("⚠️ Error en la respuesta del servidor:", data);
          mostrarNotificacionTemporal('Error al marcar la notificación', 'error');
        }
        
      } catch (err) {
        console.error("❌ Error al marcar notificación:", err);
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
  // INICIALIZACIÓN DE CHOICES.JS
  // ============================================
  
  // Configurar Choices.js en selects de búsqueda
  document.querySelectorAll(".search-select").forEach(select => {
    // Verificar si ya tiene Choices inicializado
    if (select.classList.contains('choices__input')) return;
    
    try {
      new Choices(select, {
        removeItemButton: false,
        shouldSort: false,
        searchEnabled: false,
        itemSelectText: "",
        classNames: {
          containerOuter: 'choices',
          containerInner: 'choices__inner',
          input: 'choices__input',
          inputCloned: 'choices__input--cloned',
          list: 'choices__list',
          listItems: 'choices__list--multiple',
          listSingle: 'choices__list--single',
          listDropdown: 'choices__list--dropdown',
          item: 'choices__item',
          itemSelectable: 'choices__item--selectable',
          itemDisabled: 'choices__item--disabled',
          itemChoice: 'choices__item--choice',
          placeholder: 'choices__placeholder',
          group: 'choices__group',
          groupHeading: 'choices__heading',
          button: 'choices__button',
          activeState: 'is-active',
          focusState: 'is-focused',
          openState: 'is-open',
          disabledState: 'is-disabled',
          highlightedState: 'is-highlighted',
          selectedState: 'is-selected',
          flippedState: 'is-flipped',
          loadingState: 'is-loading',
        },
        callbackOnCreateTemplates: function(template) {
          return {
            option: function(classNames, data) {
              return template(`
                <div class="${classNames.item} ${classNames.itemChoice} ${data.highlighted ? classNames.highlightedState : classNames.itemSelectable}" data-select-text="${this.config.itemSelectText}" data-choice ${data.disabled ? 'data-choice-disabled aria-disabled="true"' : 'data-choice-selectable'} data-id="${data.id}" data-value="${data.value}" ${data.groupId > 0 ? 'role="treeitem"' : 'role="option"'}>
                  <span>${data.label}</span>
                </div>
              `);
            }
          };
        }
      });
    } catch (error) {
      console.warn('Error inicializando Choices.js:', error);
    }
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
    
    // Ajustar modal en mobile
    if (dashboardOverlay.classList.contains('active') && window.innerWidth <= 480) {
      const modalContent = dashboardResultados.querySelector('.modal-content');
      if (modalContent) {
        modalContent.style.maxHeight = 'calc(100vh - 80px)';
      }
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
  // LOGS DE DESARROLLO
  // ============================================
  
  if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    console.log('🎉 Navbar mejorada cargada correctamente');
    console.log('📱 Dropdowns configurados:', dropdowns.length);
    console.log('🔍 Buscador modal:', buscador ? 'Configurado' : 'No encontrado');
    console.log('🔔 Sistema de notificaciones:', badge ? 'Activo' : 'No encontrado');
  }
  
}); // Fin del DOMContentLoaded

// ============================================
// FUNCIONES GLOBALES (para compatibilidad)
// ============================================

// Función global para toggleDropdown (mantener compatibilidad)
function toggleDropdown() {
  const dropdown = document.getElementById("dropdown-notificaciones");
  if (dropdown) {
    dropdown.classList.toggle("hidden");
  }
}