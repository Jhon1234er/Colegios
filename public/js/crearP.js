document.addEventListener("DOMContentLoaded", function () {
  const colegioSelect = document.getElementById('colegio_id');
  const materiasSelect = document.getElementById('materias');
  const fichaSelect = document.getElementById('ficha_id');

  // Flatpickr para fecha de nacimiento
  if (typeof flatpickr !== "undefined") {
    flatpickr("#fecha_nacimiento", {
      dateFormat: "Y-m-d",
      maxDate: "today",
      locale: "es"
    });
  }

  // Inicializa Choices y guarda la instancia
  const materiasChoices = new Choices(materiasSelect, { removeItemButton: false, shouldSort: false });
  const fichasChoices = new Choices(fichaSelect, { removeItemButton: true, shouldSort: false });

  if (colegioSelect) {
    colegioSelect.addEventListener('change', async function () {
      const colegioId = this.value;
      console.log("Colegio seleccionado:", colegioId);

      // Limpiar selects usando Choices
      materiasChoices.clearStore();
      materiasChoices.setChoices([{ value: '', label: 'Cargando materias...', selected: true, disabled: true }], 'value', 'label', true);
      fichasChoices.clearStore();
      fichasChoices.setChoices([{ value: '', label: 'Cargando fichas...', selected: true, disabled: true }], 'value', 'label', true);

      if (!colegioId) {
        materiasChoices.setChoices([{ value: '', label: 'Seleccione un colegio primero', selected: true, disabled: true }], 'value', 'label', true);
        fichasChoices.setChoices([{ value: '', label: 'Seleccione un colegio primero', selected: true, disabled: true }], 'value', 'label', true);
        return;
      }

      // Cargar materias
      try {
        const res = await fetch(`/ajax/get_materias_por_colegio.php?colegio_id=${colegioId}`);
        const data = await res.json();
        console.log("Materias recibidas:", data);
        if (data.length > 0) {
          materiasChoices.setChoices(
            data.map(m => ({ value: m.id, label: m.nombre })),
            'value', 'label', true
          );
        } else {
          materiasChoices.setChoices([{ value: '', label: 'Este colegio no tiene materias registradas', selected: true, disabled: true }], 'value', 'label', true);
        }
      } catch (error) {
        materiasChoices.setChoices([{ value: '', label: 'Error al cargar materias', selected: true, disabled: true }], 'value', 'label', true);
        console.error("Error cargando materias:", error);
      }

      // Cargar fichas
      try {
        const res = await fetch(`/ajax/get_fichas_por_colegio.php?colegio_id=${colegioId}`);
        const data = await res.json();
        if (data.length > 0) {
          fichasChoices.setChoices(
            data.map(f => ({ value: f.id, label: f.nombre })),
            'value', 'label', false
          );
          // No seleccionar ninguna automáticamente
        } else {
          fichasChoices.setChoices([{ value: '', label: 'Este colegio no tiene fichas registradas', selected: true, disabled: true }], 'value', 'label', true);
        }
      } catch (error) {
        fichasChoices.setChoices([{ value: '', label: 'Error al cargar fichas', selected: true, disabled: true }], 'value', 'label', true);
        console.error("Error cargando fichas:", error);
      }
    });
  }
});
