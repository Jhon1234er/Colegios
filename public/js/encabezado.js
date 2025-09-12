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
            
            // Agregar botón para volver
            const backBtn = document.createElement('button');
            backBtn.className = 'back-to-dashboard-btn';
            backBtn.innerHTML = '← Volver al Dashboard';
            backBtn.onclick = function() {
              dashboardNormal.style.display = 'block';
              dashboardResultados.style.display = 'none';
              overlay.style.display = 'none';
              // Limpiar formulario
              document.getElementById('input-busqueda').value = '';
            };
            
            dashboardResultados.insertBefore(backBtn, dashboardResultados.firstChild);
            
            // INICIALIZAR FUNCIONALIDAD DE BOTONES "VER DETALLES" DESPUÉS DE CARGAR AJAX
            try { initializeStudentDetailsButtons(); } catch(e) { console.warn(e); }
            try { initializeProfessorDetailsButtons(); } catch(e) { console.warn(e); }
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
      
      // PRIMERO: Resetear TODOS los botones (incluyendo este)
      buttons.forEach(btn => {
        // Verificar que el botón aún existe en el DOM
        if (!btn || !btn.parentNode) {
          return; // Saltar botones que ya no existen
        }
        
        btn.textContent = 'Ver Detalles';
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-primary');
        btn.disabled = false;
        
        // Deshabilitar todos los botones de editar
        const resultCard = btn.closest('.result-card');
        if (resultCard) {
          const editBtn = resultCard.querySelector('.btn-editar');
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
      
      // Mostrar el sidebar
      sidebar.style.display = 'block';
      
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
      <!-- Nombres del Aprendiz -->
      <div class="data-container">
        <div class="section-title">Nombres del Aprendiz</div>
        <div class="names-container" style="text-align: center; padding: 20px;">
          <div class="student-name" style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">
            Cargando información del aprendiz...
          </div>
        </div>
      </div>
      
      <!-- Detalles del Aprendiz -->
      <div class="data-container">
        <div class="section-title">Detalles del Aprendiz</div>
        <div class="data-grid" id="student-data-grid">
          <div class="data-item">
            <div class="data-label">Cargando...</div>
            <div class="data-value">Información del aprendiz</div>
          </div>
        </div>
      </div>
      
      <!-- Nombres del Acudiente -->
      <div class="data-container acudiente-container">
        <div class="section-title">Nombres del Acudiente</div>
        <div class="names-container" style="text-align: center; padding: 20px;">
          <div class="acudiente-name" style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">
            Cargando información del acudiente...
          </div>
        </div>
      </div>
      
      <!-- Detalles del Acudiente -->
      <div class="data-container acudiente-container">
        <div class="section-title">Detalles del Acudiente</div>
        <div class="data-grid" id="guardian-data-grid">
          <div class="data-item">
            <div class="data-label">Cargando...</div>
            <div class="data-value">Información del acudiente</div>
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
  console.log('Haciendo petición AJAX para ID:', studentId);
  fetch(`./ajax/get_student_details.php?id=${studentId}`)
    .then(response => {
      console.log('Respuesta recibida:', response);
      return response.json();
    })
    .then(data => {
      console.log('Datos recibidos:', data);
      if (data.success) {
        const student = data.student;
        
        // Actualizar nombre del aprendiz
        const studentNameElement = container.querySelector('.student-name');
        if (studentNameElement) {
          studentNameElement.textContent = student.nombre_completo || 'No disponible';
        }
        
        // Actualizar detalles del aprendiz usando data-grid
        const studentDataGrid = container.querySelector('#student-data-grid');
        if (studentDataGrid) {
          let studentItems = '';
          
          if (student.tipo_documento && student.numero_documento) {
            studentItems += `
              <div class="data-item">
                <div class="data-label">Documento</div>
                <div class="data-value">${student.tipo_documento} ${student.numero_documento}</div>
              </div>`;
          }
          
          if (student.email) {
            studentItems += `
              <div class="data-item">
                <div class="data-label">Email</div>
                <div class="data-value">${student.email}</div>
              </div>`;
          }
          
          if (student.telefono) {
            studentItems += `
              <div class="data-item">
                <div class="data-label">Teléfono</div>
                <div class="data-value">${student.telefono}</div>
              </div>`;
          }
          
          if (student.ficha_nombre) {
            studentItems += `
              <div class="data-item">
                <div class="data-label">Ficha</div>
                <div class="data-value">${student.ficha_nombre}</div>
              </div>`;
          }
          
          if (student.colegio_nombre) {
            studentItems += `
              <div class="data-item">
                <div class="data-label">Colegio</div>
                <div class="data-value">${student.colegio_nombre}</div>
              </div>`;
          }
          
          if (student.jornada) {
            studentItems += `
              <div class="data-item">
                <div class="data-label">Jornada</div>
                <div class="data-value">${student.jornada}</div>
              </div>`;
          }
          
          if (student.estado) {
            studentItems += `
              <div class="data-item">
                <div class="data-label">Estado</div>
                <div class="data-value">
                  <span class="status-badge">${student.estado}</span>
                </div>
              </div>`;
          }
          
          studentDataGrid.innerHTML = studentItems || `
            <div class="data-item">
              <div class="data-label">Sin información</div>
              <div class="data-value">No hay información adicional del aprendiz</div>
            </div>`;
        }
        
        // Actualizar nombre del acudiente
        const guardianNameElement = container.querySelector('.acudiente-name');
        if (guardianNameElement) {
          guardianNameElement.textContent = student.nombre_completo_acudiente || 'No disponible';
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
      dataGrids.forEach(grid => {
        grid.innerHTML = `
          <div class="data-item">
            <div class="data-label">Error</div>
            <div class="data-value">Error al cargar información</div>
          </div>`;
      });
    });
}

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

      // Esqueleto inicial del panel
      detailsContainer.innerHTML = `
        <div class="student-details-content">
          <div class="data-container">
            <div class="section-title">Nombre del Instructor/Facilitador</div>
            <div class="names-container" style="text-align: center; padding: 20px;">
              <div class="prof-name" style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Cargando...</div>
            </div>
            <div class="actions-bar" style="display:flex; gap:8px; justify-content:flex-end; padding: 0 10px 10px 10px;">
              <button class="btn btn-sm btn-primary" id="btnVerCalendarioProf">Ver Calendario</button>
              <button class="btn btn-sm btn-secondary" id="btnExportarCSVProf">Exportar CSV</button>
            </div>
          </div>
          <div class="data-container">
            <div class="section-title">Datos del Instructor/Facilitador</div>
            <div class="data-grid" id="professor-data-grid">
              <div class="data-item"><div class="data-label">Cargando...</div><div class="data-value">—</div></div>
            </div>
          </div>
          <div class="data-container">
            <div class="section-title">Fichas y Próximas Clases</div>
            <div class="data-grid" id="professor-fichas-grid">
              <div class="data-item"><div class="data-label">Cargando...</div><div class="data-value">—</div></div>
            </div>
          </div>
          <div class="data-container">
            <div class="section-title">Materias u Otros Datos</div>
            <div class="data-grid" id="professor-otros-grid">
              <div class="data-item"><div class="data-label">Cargando...</div><div class="data-value">—</div></div>
            </div>
          </div>
        </div>`;

      // Mostrar panel
      sidebar.style.display = 'block';

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
      const fichas = data.fichas || [];
      const materias = data.materias || [];
      const clases = data.clases || [];

      // Wire acciones
      const btnCal = container.querySelector('#btnVerCalendarioProf');
      if (btnCal) {
        btnCal.addEventListener('click', () => {
          // Navegar al calendario filtrado por profesor (el backend soporta filtro por profesor)
          window.location.href = `/?page=calendario&profesor_id=${encodeURIComponent(profId)}`;
        });
      }
      const btnCSV = container.querySelector('#btnExportarCSVProf');
      if (btnCSV) {
        btnCSV.addEventListener('click', () => exportarCSVClasesProfesor(prof, clases));
      }

      // Nombre
      const nameEl = container.querySelector('.prof-name');
      if (nameEl) nameEl.textContent = (prof.nombres && prof.apellidos) ? `${prof.nombres} ${prof.apellidos}` : 'Sin nombre';

      // Datos principales
      const grid = container.querySelector('#professor-data-grid');
      if (grid) {
        grid.innerHTML = '';
        const rows = [];
        const push = (l,v)=>{ if (v && String(v).trim() !== '') rows.push(`<div class=\"data-item\"><div class=\"data-label\">${l}</div><div class=\"data-value\">${v}</div></div>`); };
        push('Documento', `${prof.tipo_documento || ''} ${prof.numero_documento || ''}`.trim());
        push('Email', prof.correo_electronico);
        push('Email Institucional', prof.correo_institucional);
        push('Teléfono', prof.telefono);
        push('Especialidad', prof.especialidad);
        push('Tipo de Contrato', prof.tip_contrato);
        push('Fecha Ingreso', prof.fecha_ingreso);
        push('Colegio', prof.colegio_nombre);
        grid.innerHTML = rows.join('') || '<div class="data-item"><div class="data-label">—</div><div class="data-value">Sin datos</div></div>';
      }

      // Fichas y próximas clases
      const fichasGrid = container.querySelector('#professor-fichas-grid');
      if (fichasGrid) {
        let html = '';
        if (fichas.length) {
          html += fichas.map(f=>`<div class=\"data-item\"><div class=\"data-label\">Ficha</div><div class=\"data-value\">${(f.numero_ficha||f.numero||f.id)} - ${f.nombre||''}</div></div>`).join('');
        }
        if (clases.length) {
          html += clases.map(c=>`<div class=\"data-item\"><div class=\"data-label\">Clase</div><div class=\"data-value\">${c.fecha_inicio} → ${c.fecha_fin} | ${c.aula||''} | ${c.estado||''}</div></div>`).join('');
        }
        fichasGrid.innerHTML = html || '<div class="data-item"><div class="data-label">—</div><div class="data-value">Sin fichas/clases próximas</div></div>';
      }

      // Materias / otros
      const otros = container.querySelector('#professor-otros-grid');
      if (otros) {
        let html = '';
        // Contadores
        html += `<div class="data-item"><div class="data-label">Resumen</div><div class="data-value">Fichas: ${fichas.length} • Materias: ${materias.length} • Clases semana: ${clases.length}</div></div>`;
        if (materias.length) {
          html += `<div class=\"data-item\"><div class=\"data-label\">Materias</div><div class=\"data-value\">${materias.join(', ')}</div></div>`;
        }
        otros.innerHTML = html || '<div class="data-item"><div class="data-label">—</div><div class="data-value">Sin datos adicionales</div></div>';
      }
    })
    .catch(err => {
      console.error('Error cargando facilitador:', err);
      const grids = container.querySelectorAll('.data-grid');
      grids.forEach(g=> g.innerHTML = '<div class="data-item"><div class="data-label">Error</div><div class="data-value">No se pudieron cargar los datos</div></div>');
    });
}
