document.addEventListener("DOMContentLoaded", function () {
  const colegioSelect = document.getElementById("colegio_id");
  const fichaSelect = document.getElementById("ficha_id");

  if (colegioSelect && fichaSelect) {
    colegioSelect.addEventListener("change", async function () {
      const colegioId = this.value;

      fichaSelect.innerHTML = '<option value="">Cargando fichas...</option>';

      if (!colegioId) {
        fichaSelect.innerHTML = '<option value="">Seleccione un colegio primero</option>';
        return;
      }

      try {
        const res = await fetch(`/ajax/get_fichas_por_colegio.php?colegio_id=${colegioId}`);
        const contentType = res.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
          throw new Error("La respuesta no es JSON");
        }

        const data = await res.json();

        fichaSelect.innerHTML = '';
        if (data.length > 0) {
          data.forEach(f => {
            fichaSelect.innerHTML += `<option value="${f.id}">${f.nombre}</option>`;
          });
        } else {
          fichaSelect.innerHTML = '<option value="">Este colegio no tiene fichas registradas</option>';
        }
      } catch (error) {
        fichaSelect.innerHTML = '<option value="">Error al cargar fichas</option>';
        console.error("Error cargando fichas:", error);
      }
    });
  }
});
