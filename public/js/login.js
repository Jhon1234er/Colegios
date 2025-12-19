// Mostrar/Ocultar contraseña (sin alternar formularios)
document.addEventListener('DOMContentLoaded', function () {
  // Login
  const togglePassword = document.getElementById('togglePassword');
  const passwordInput = document.getElementById('password');
  if (togglePassword && passwordInput) {
    togglePassword.addEventListener('click', function () {
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
      this.src = type === 'password' ? '/icons/Ver.svg' : '/icons/Esconder.svg';
    });
  }

  // Registro (si está presente en la página pública)
  const toggleRegPassword = document.getElementById('toggleRegPassword');
  const regPasswordInput = document.getElementById('reg_password');
  if (toggleRegPassword && regPasswordInput) {
    toggleRegPassword.addEventListener('click', function () {
      const type = regPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      regPasswordInput.setAttribute('type', type);
      this.src = type === 'password' ? '/icons/Ver.svg' : '/icons/Esconder.svg';
    });
  }
});