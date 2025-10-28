document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("form");
    const themeSwitch = document.getElementById("switch");

    // 🌙 Aplicar tema oscuro si está en localStorage
    const storedTheme = localStorage.getItem("theme");
    if (storedTheme === "dark") {
        document.body.classList.add("dark-mode", "dark-select");
        if (themeSwitch) themeSwitch.checked = true;
    }

    // 🎚️ Toggle claro/oscuro
    if (themeSwitch) {
        themeSwitch.addEventListener("change", () => {
            if (themeSwitch.checked) {
                document.body.classList.add("dark-mode", "dark-select");
                localStorage.setItem("theme", "dark");
            } else {
                document.body.classList.remove("dark-mode", "dark-select");
                localStorage.setItem("theme", "light");
            }
        });
    }

    // ✅ Validación del formulario antes de enviar
    form.addEventListener("submit", function (e) {
        const requiredFields = [
            "nombres",
            "apellidos",
            "tipo_documento",
            "numero_documento",
            "correo_electronico",
            "genero",
            "password",
            "fecha_nacimiento"
        ];

        let valid = true;

        requiredFields.forEach((id) => {
            const input = document.getElementById(id);
            if (!input || !input.value.trim()) {
                input.classList.add("input-error");
                valid = false;
            } else {
                input.classList.remove("input-error");
            }
        });

        const email = document.getElementById("correo_electronico").value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (email && !emailRegex.test(email)) {
            alert("Correo electrónico no es válido.");
            valid = false;
        }

        if (!valid) {
            e.preventDefault();
            alert("Por favor completa todos los campos obligatorios correctamente.");
        }
    });

    // 📅 Flatpickr: solo fechas pasadas, mayor o igual a 18 años
    const fechaInput = document.getElementById("fecha_nacimiento");
    if (fechaInput) {
        const today = new Date();
        const maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());

        flatpickr(fechaInput, {
            dateFormat: "Y-m-d",
            maxDate: maxDate.toISOString().split("T")[0],
            altInput: true,
            altFormat: "d-m-Y",
            locale: "es",
            allowInput: true,
        });

        if (!fechaInput.value) {
            fechaInput.setAttribute("placeholder", "Seleccionar fecha");
        }
    }

    // 🎨 Choices.js para estilizar selects
    const selects = document.querySelectorAll("select");
    selects.forEach((select) => {
        new Choices(select, {
            searchEnabled: false,
            itemSelectText: '',
            classNames: {
                containerOuter: 'choices',
                containerOuterDark: 'dark-compatible'
            }
        });
    });
});
