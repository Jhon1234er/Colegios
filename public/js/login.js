
// VER O OCULTAR CONTRASEÑA
document.addEventListener("DOMContentLoaded", function () {
    const passwordInput = document.getElementById("password");
    const toggleIcon = document.getElementById("togglePassword");
    let visible = false;

    toggleIcon.addEventListener("click", () => {
        visible = !visible;
        passwordInput.type = visible ? "text" : "password";
        toggleIcon.src = visible ? "../icons/Esconder.svg" : "../icons/Ver.svg";
    });
});

//CAMBIAR TEMA
document.addEventListener("DOMContentLoaded", () => {
  const themeSwitch = document.getElementById("switch");

  // Cargar tema guardado
  if (localStorage.getItem("theme") === "dark") {
    document.body.classList.add("dark-mode");
    themeSwitch.checked = true;
  }

  // Cambiar tema al hacer toggle
  themeSwitch.addEventListener("change", () => {
    if (themeSwitch.checked) {
      document.body.classList.add("dark-mode");
      localStorage.setItem("theme", "dark");
    } else {
      document.body.classList.remove("dark-mode");
      localStorage.setItem("theme", "light");
    }
  });
});
