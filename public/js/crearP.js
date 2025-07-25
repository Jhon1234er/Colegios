document.addEventListener("DOMContentLoaded", function () {
    const colegioSelect = document.querySelector("select[name='colegio_id']");
    const materiasSelect = document.getElementById("materias");

    colegioSelect.addEventListener("change", function () {
        const colegioId = this.value;

        if (colegioId) {
            // ✅ URL ajustada para evitar errores en Safari y navegadores estrictos
            fetch(`/index.php?page=materias_por_colegio&colegio_id=${colegioId}`)
                .then(response => response.json())
                .then(materias => {
                    materiasSelect.innerHTML = "";
                    materias.forEach(materia => {
                        const option = document.createElement("option");
                        option.value = materia.id;
                        option.textContent = materia.nombre;
                        materiasSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error("Error cargando materias:", error);
                });
        } else {
            materiasSelect.innerHTML = "";
        }
    });

    // ✅ Limitar fecha de nacimiento a mayores de 18 años
    const fechaNacimientoInput = document.querySelector("input[name='fecha_nacimiento']");
    const hoy = new Date();
    const hace18Anios = new Date(hoy.getFullYear() - 18, hoy.getMonth(), hoy.getDate());
    fechaNacimientoInput.max = hace18Anios.toISOString().split('T')[0];
});
