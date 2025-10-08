// VER O OCULTAR CONTRASEÑA
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility for login
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Change icon
            this.src = type === 'password' ? '/icons/Ver.svg' : '/icons/Esconder.svg';
        });
    }

    // Toggle password visibility for register
    const toggleRegPassword = document.getElementById('toggleRegPassword');
    const regPasswordInput = document.getElementById('reg_password');

    if (toggleRegPassword && regPasswordInput) {
        toggleRegPassword.addEventListener('click', function() {
            const type = regPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            regPasswordInput.setAttribute('type', type);
            
            // Change icon
            this.src = type === 'password' ? '/icons/Ver.svg' : '/icons/Esconder.svg';
        });
    }

    // Theme toggle functionality
    const themeSwitchInput = document.getElementById('switch');
    if (themeSwitchInput) {
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
        }
        themeSwitchInput.checked = savedTheme === 'dark';

        themeSwitchInput.addEventListener('change', function() {
            const theme = this.checked ? 'dark' : 'light';
            if (theme === 'dark') {
                document.body.classList.add('dark-mode');
            } else {
                document.body.classList.remove('dark-mode');
            }
            localStorage.setItem('theme', theme);
        });
    }

    // Form switching functionality (simple content change)
    const welcomeTitle = document.getElementById('welcomeTitle');
    const welcomeSubtitle = document.getElementById('welcomeSubtitle');
    const welcomeDescription = document.getElementById('welcomeDescription');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const switchToRegister = document.getElementById('switchToRegister');
    const switchToLogin = document.getElementById('switchToLogin');

    // Switch to register mode with animation
    if (switchToRegister) {
        switchToRegister.addEventListener('click', function(e) {
            e.preventDefault();
            // Mantener comportamiento AJAX (sin redirección inmediata)
            
            // Change welcome text
            welcomeTitle.textContent = '¡Únete!';
            welcomeSubtitle.innerHTML = 'Regístrate en <strong>System School</strong>';
            welcomeDescription.textContent = 'Crea tu cuenta y comienza tu experiencia SENA';
            
            // Cargar formulario de Facilitador/Instructor (ruta pública)
            fetch('/?page=registro_profesor')
                .then(response => response.text())
                .then(html => {
                    // Extract just the form content from the response
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    // Preferir contenedor de profesor, si no, intentar el general
                    const formContent = doc.querySelector('.formulario-registro') || doc.querySelector('.registro-container');
                    
                    if (formContent) {
                        // Replace the h2 title and adjust styling
                        const title = formContent.querySelector('h2');
                        if (title) {
                            title.textContent = 'Crea tu cuenta';
                            title.className = 'form-title';
                        }
                        
                        // Add the form-button class to the submit button
                        const submitBtn = formContent.querySelector('button[type="submit"]');
                        if (submitBtn) {
                            submitBtn.className = 'form-button';
                        }
                        
                        // Add "¿Ya tienes cuenta?" link
                        const textLinks = document.createElement('div');
                        textLinks.className = 'form-links';
                        textLinks.innerHTML = '<a href="#" id="switchToLogin">¿Ya tienes cuenta?</a>';
                        formContent.appendChild(textLinks);
                        
                        // Insert the content into the register form container
                        registerForm.innerHTML = formContent.innerHTML;

                        // Diseño embebido: estilos rápidos para que el formulario de profesor se vea bien en el layout verde
                        const style = document.createElement('style');
                        style.textContent = `
                          #registerForm { max-width: 560px; margin: 0 auto; }
                          #registerForm .form-title { font-size: 22px; color: #2a7e2e; text-align: center; margin-bottom: 14px; }
                          #registerForm .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
                          #registerForm .form-grid .columna { display: contents; }
                          #registerForm label { font-size: 12px; color: #5b6b7b; margin-bottom: 4px; display: block; }
                          #registerForm input, #registerForm select { height: 40px; border-radius: 8px; border: 1px solid #dfe5eb; padding: 8px 12px; width: 100%; }
                          #registerForm .form-select-contrato, #registerForm .form-tipo { width: 100%; }
                          #registerForm .btn-registrar, #registerForm .form-button { margin-top: 8px; background: #2dbf44; border: none; color: #fff; border-radius: 24px; padding: 10px 16px; width: 100%; font-weight: 600; }
                          #registerForm .form-links { margin-top: 10px; text-align: center; }
                          @media (max-width: 640px) { #registerForm .form-grid { grid-template-columns: 1fr; } }
                        `;
                        registerForm.prepend(style);

                        // Si el formulario cargado es el general y trae rol_id, forzar 2
                        const rolInput = registerForm.querySelector('input[name="rol_id"]');
                        if (rolInput) {
                            rolInput.value = '2';
                        }
                        
                        // Initialize Choices.js for select elements
                        const selectElements = registerForm.querySelectorAll('select');
                        selectElements.forEach(select => {
                            new Choices(select, {
                                searchEnabled: false,
                                itemSelectText: '',
                                shouldSort: false
                            });
                        });
                        
                        // Initialize Flatpickr for date input
                        const dateInput = registerForm.querySelector('input[type="date"]');
                        if (dateInput) {
                            flatpickr(dateInput, {
                                locale: 'es',
                                dateFormat: 'Y-m-d',
                                allowInput: true
                            });
                        }
                        
                        // Add slide-out animation to login form
                        loginForm.style.transform = 'translateX(-100%)';
                        loginForm.style.opacity = '0';
                        
                        // Show register form with animation
                        setTimeout(() => {
                            registerForm.classList.remove('inactive');
                            registerForm.classList.add('active');
                            
                            // Re-attach event listener for "¿Ya tienes cuenta?"
                            const newSwitchToLogin = document.getElementById('switchToLogin');
                            if (newSwitchToLogin) {
                                newSwitchToLogin.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    
                                    // Change welcome text back
                                    welcomeTitle.textContent = 'Bienvenidos a System School';
                                    welcomeSubtitle.innerHTML = '';
                                    welcomeDescription.textContent = 'Tu plataforma educativa del SENA';
                                    
                                    // Switch forms back
                                    registerForm.classList.remove('active');
                                    registerForm.classList.add('inactive');
                                    setTimeout(() => {
                                        loginForm.style.transform = 'translateX(0)';
                                        loginForm.style.opacity = '1';
                                    }, 300);
                                });
                            }
                        }, 300);
                    } else {
                        // Si no encontró el contenedor esperado, redirigir a la ruta pública de profesor
                        window.location.href = '/?page=registro_profesor';
                    }
                })
                .catch(error => {
                    console.error('Error loading register form:', error);
                    // Fallback to redirect if AJAX fails
                    window.location.href = '/?page=registro_profesor';
                });
        });
    }

    // Switch to login mode
    if (switchToLogin) {
        switchToLogin.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Change welcome text back
            welcomeTitle.textContent = '¡Hola!';
            welcomeSubtitle.innerHTML = 'Bienvenidos a <strong>System School</strong>';
            welcomeDescription.textContent = 'Tu plataforma educativa del SENA';
            
            // Switch forms
            registerForm.classList.remove('active');
            setTimeout(() => {
                loginForm.classList.remove('inactive');
            }, 300);
        });
    }
});
