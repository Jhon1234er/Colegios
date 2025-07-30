document.addEventListener("DOMContentLoaded", function () {
    // Inicializa Choices en todos los selects
    document.querySelectorAll('select').forEach(select => {
        select.choices = new Choices(select, {
            searchEnabled: true,
            shouldSort: false,
            placeholder: true,
            itemSelectText: '',
        });
    });

    const fechaInput = document.querySelector("#fecha_nacimiento");
    if (fechaInput) {
        flatpickr(fechaInput, {
            dateFormat: "d-m-Y",
            maxDate: new Date().fp_incr(-6570), // máx. 18 años atrás
            locale: "es"
        });
    }

    // Materias dinámicas según colegio
    const colegioSelect = document.querySelector("select[name='colegio_id']");
    const materiasSelect = document.getElementById("materias");

    colegioSelect.addEventListener("change", function () {
        const colegioId = this.value;
        materiasSelect.innerHTML = ""; // Limpia antes de cargar

        if (materiasSelect.choices) materiasSelect.choices.destroy();

        if (colegioId) {
            fetch(`/index.php?page=materias_por_colegio&colegio_id=${colegioId}`)
                .then(response => response.json())
                .then(materias => {
                    materias.forEach(materia => {
                        const option = document.createElement("option");
                        option.value = materia.id;
                        option.textContent = materia.nombre;
                        materiasSelect.appendChild(option);
                    });
                    materiasSelect.choices = new Choices(materiasSelect, {
                        searchEnabled: true,
                        shouldSort: false,
                        placeholder: true,
                        itemSelectText: '',
                    });
                })
                .catch(error => {
                    console.error("Error cargando materias:", error);
                });
        }
    });

    // Opcional: Limitar fecha de nacimiento si tienes ese campo
    const fechaNacimientoInput = document.querySelector("input[name='fecha_nacimiento']");
    if (fechaNacimientoInput) {
        const hoy = new Date();
        const hace18Anios = new Date(hoy.getFullYear() - 18, hoy.getMonth(), hoy.getDate());
        fechaNacimientoInput.max = hace18Anios.toISOString().split('T')[0];
    }
});
