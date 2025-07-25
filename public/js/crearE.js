document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("formularioEstudiante");

    // Limitar fechas válidas
    const fechaNacimientoInput = document.getElementById("fecha_nacimiento");
    const fechaIngresoInput = document.getElementById("fecha_ingreso");

    const hoy = new Date();
    const hace18Anios = new Date(
        hoy.getFullYear() - 18,
        hoy.getMonth(),
        hoy.getDate()
    );

    fechaNacimientoInput.max = hace18Anios.toISOString().split("T")[0];
    fechaIngresoInput.max = hoy.toISOString().split("T")[0];

    // Inicializar Choices.js en selects
    const selects = document.querySelectorAll("select");
    selects.forEach(select => {
        new Choices(select, {
            searchEnabled: true,
            shouldSort: false,
            placeholder: true,
            itemSelectText: '',
        });
    });

    // Validación del formulario
    form.addEventListener("submit", function (e) {
        const camposObligatorios = [
            "nombre", "apellido", "correo_electronico", "contrasena",
            "fecha_nacimiento", "colegio_id", "grado", "grupo", "jornada", "fecha_ingreso",
            "nombre_completo_acudiente", "tipo_documento_acudiente",
            "numero_documento_acudiente", "telefono_acudiente", "parentesco", "ocupacion"
        ];

        let valido = true;
        camposObligatorios.forEach(id => {
            const campo = document.getElementById(id);
            if (campo && campo.value.trim() === "") {
                campo.classList.add("is-invalid");
                valido = false;
            } else if (campo) {
                campo.classList.remove("is-invalid");
            }
        });

        // Validar edad mínima
        const fechaNac = new Date(fechaNacimientoInput.value);
        if (fechaNac > hace18Anios) {
            fechaNacimientoInput.classList.add("is-invalid");
            valido = false;
            alert("El estudiante debe tener al menos 18 años.");
        } else {
            fechaNacimientoInput.classList.remove("is-invalid");
        }

        // Fecha de ingreso no en el futuro
        const fechaIng = new Date(fechaIngresoInput.value);
        if (fechaIng > hoy) {
            fechaIngresoInput.classList.add("is-invalid");
            valido = false;
            alert("La fecha de ingreso no puede ser futura.");
        } else {
            fechaIngresoInput.classList.remove("is-invalid");
        }

        if (!valido) {
            e.preventDefault();
        }
    });
});
