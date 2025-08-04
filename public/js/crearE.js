document.addEventListener("DOMContentLoaded", function () {
  const colegioSelect = document.getElementById('colegio_id');
  const gradoSelect = document.getElementById('grado');
  const jornadaSelect = document.getElementById('jornada');
  const tipoDocumentoSelect = document.querySelector('select[name="tipo_documento"]');
  const generoSelect = document.querySelector('select[name="genero"]');
  const filtroSelect = document.querySelector('select[name="filtro"]');
  const tipoDocumentoAcudienteSelect = document.querySelector('select[name="tipo_documento_acudiente"]');

  const choicesInstances = {};

  function initChoices(element, id) {
    if (element && !element.classList.contains("choices__input")) {
      if (choicesInstances[id]) choicesInstances[id].destroy();
      choicesInstances[id] = new Choices(element, {
        searchEnabled: true,
        shouldSort: false,
        placeholder: true,
        itemSelectText: ''
      });
    }
  }

  initChoices(colegioSelect, 'colegio');
  initChoices(gradoSelect, 'grado');
  initChoices(jornadaSelect, 'jornada');
  initChoices(tipoDocumentoSelect, 'tipo_documento');
  initChoices(generoSelect, 'genero');
  initChoices(tipoDocumentoAcudienteSelect, 'tipo_documento_acudiente');
  initChoices(filtroSelect, 'filtro');

  colegioSelect.addEventListener('change', function () {
    gradoSelect.innerHTML = '<option value="">Seleccione grado</option>';
    jornadaSelect.innerHTML = '<option value="">Seleccione jornada</option>';

    if (choicesInstances.grado) choicesInstances.grado.destroy();
    if (choicesInstances.jornada) choicesInstances.jornada.destroy();

    const selected = colegioSelect.options[colegioSelect.selectedIndex];
    const grados = (selected.getAttribute('data-grados') || '').split(',').map(g => g.trim()).filter(g => g);
    const jornadas = (selected.getAttribute('data-jornada') || '').split(',').map(j => j.trim()).filter(j => j);

    const gradosFiltrados = grados.filter(g => ['7', '8', '9', '10'].includes(g));
    gradosFiltrados.forEach(g => {
      const opt = document.createElement('option');
      opt.value = g;
      opt.textContent = g;
      gradoSelect.appendChild(opt);
    });

    const jornadasFiltradas = jornadas.filter(j => ['MAÑANA', 'TARDE'].includes(j.toUpperCase()));
    jornadasFiltradas.forEach(j => {
      const opt = document.createElement('option');
      opt.value = j;
      opt.textContent = j.charAt(0).toUpperCase() + j.slice(1).toLowerCase();
      jornadaSelect.appendChild(opt);
    });

    initChoices(gradoSelect, 'grado');
    initChoices(jornadaSelect, 'jornada');
  });

  // Flatpickr
  if (typeof flatpickr !== "undefined") {
    flatpickr("#fecha_nacimiento", {
      dateFormat: "Y-m-d",
      maxDate: "today",
      locale: "es"
    });
  }

  // Stepper
  const steps = document.querySelectorAll(".form-step");
  const stepIndicators = document.querySelectorAll(".stepper .step");
  let currentStep = 0;

  function showStep(index) {
    steps.forEach((step, i) => {
      step.classList.toggle("active", i === index);
      if (stepIndicators[i]) {
        stepIndicators[i].classList.toggle("active", i <= index);
      }
    });
  }

  document.querySelectorAll(".next-btn").forEach(btn =>
    btn.addEventListener("click", () => {
      if (currentStep < steps.length - 1) {
        currentStep++;
        showStep(currentStep);
      }
    })
  );

  document.querySelectorAll(".prev-btn").forEach(btn =>
    btn.addEventListener("click", () => {
      if (currentStep > 0) {
        currentStep--;
        showStep(currentStep);
      }
    })
  );

  showStep(currentStep);
});

