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
            
            // Change welcome text
            welcomeTitle.textContent = '¡Únete!';
            welcomeSubtitle.innerHTML = 'Regístrate en <strong>System School</strong>';
            welcomeDescription.textContent = 'Crea tu cuenta y comienza tu experiencia SENA';
            
            // Load register form content via AJAX
            fetch('/?page=registro')
                .then(response => response.text())
                .then(html => {
                    // Extract just the form content from the response
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const formContent = doc.querySelector('.registro-container');
                    
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
                                    welcomeTitle.textContent = '¡Hola!';
                                    welcomeSubtitle.innerHTML = 'Bienvenidos a <strong>System School</strong>';
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
                    }
                })
                .catch(error => {
                    console.error('Error loading register form:', error);
                    // Fallback to redirect if AJAX fails
                    window.location.href = '/?page=registro';
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
