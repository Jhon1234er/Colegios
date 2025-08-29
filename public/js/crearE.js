document.addEventListener("DOMContentLoaded", function () {
  // ────────────────────────────────────────────────────────────
  // Helpers Choices.js
  // ────────────────────────────────────────────────────────────
  const hasChoices = typeof Choices !== "undefined";
  const choicesInstances = {};

  function initChoices(element, id) {
    if (!element || !hasChoices) return;
    if (choicesInstances[id]) {
      try { choicesInstances[id].destroy(); } catch (_) {}
      delete choicesInstances[id];
    }
    choicesInstances[id] = new Choices(element, {
      searchEnabled: true,
      shouldSort: false,
      placeholder: true,
      itemSelectText: ''
    });
  }

  function destroyChoices(id) {
    if (choicesInstances[id]) {
      try { choicesInstances[id].destroy(); } catch (_) {}
      delete choicesInstances[id];
    }
  }

  // ────────────────────────────────────────────────────────────
  // Inicializar selects con Choices
  // ────────────────────────────────────────────────────────────
  const selectsCfg = [
    { element: document.getElementById('colegio_id'), id: 'colegio' },
    { element: document.getElementById('grado'), id: 'grado' },
    { element: document.getElementById('jornada'), id: 'jornada' },
    { element: document.querySelector('select[name="tipo_documento"]'), id: 'tipo_documento' },
    { element: document.querySelector('select[name="genero"]'), id: 'genero' },
    { element: document.querySelector('select[name="tipo_documento_acudiente"]'), id: 'tipo_documento_acudiente' },
    { element: document.querySelector('select[name="filtro"]'), id: 'filtro' }
  ];
  selectsCfg.forEach(s => initChoices(s.element, s.id));

  // ────────────────────────────────────────────────────────────
  // Actualizar grados/jornadas según colegio
  // ────────────────────────────────────────────────────────────
  const colegioSelect = document.getElementById('colegio_id');
  const gradoSelect   = document.getElementById('grado');
  const jornadaSelect = document.getElementById('jornada');

  if (colegioSelect && gradoSelect && jornadaSelect) {
    colegioSelect.addEventListener('change', function () {
      destroyChoices('grado');
      destroyChoices('jornada');
      gradoSelect.innerHTML   = '<option value="">Seleccione grado</option>';
      jornadaSelect.innerHTML = '<option value="">Seleccione jornada</option>';

      const selected  = colegioSelect.options[colegioSelect.selectedIndex];
      const grados    = (selected?.getAttribute('data-grados') || '').split(',').map(g => g.trim()).filter(Boolean);
      const jornadas  = (selected?.getAttribute('data-jornada') || '').split(',').map(j => j.trim()).filter(Boolean);

      grados.forEach(g => {
        const opt = document.createElement('option');
        opt.value = g; opt.textContent = g;
        gradoSelect.appendChild(opt);
      });
      jornadas.forEach(j => {
        const opt = document.createElement('option');
        opt.value = j;
        opt.textContent = j.charAt(0).toUpperCase() + j.slice(1).toLowerCase();
        jornadaSelect.appendChild(opt);
      });

      initChoices(gradoSelect, 'grado');
      initChoices(jornadaSelect, 'jornada');
    });
  }

  // ────────────────────────────────────────────────────────────
  // Flatpickr
  // ────────────────────────────────────────────────────────────
  if (typeof flatpickr !== "undefined") {
    flatpickr("#fecha_nacimiento", {
      dateFormat: "Y-m-d",
      maxDate: "today",
      locale: "es"
    });
  }

  // ────────────────────────────────────────────────────────────
  // STEPPER
  // ────────────────────────────────────────────────────────────
  const steps = Array.from(document.querySelectorAll(".form-step"));
  const stepIndicators = Array.from(document.querySelectorAll(".stepper .step"));
  let currentStep = 0;

  // Asegurar que los botones no envíen el formulario
  document.querySelectorAll(".next-btn, .prev-btn").forEach(btn => {
    if (!btn.getAttribute("type")) btn.setAttribute("type", "button");
  });

  function showStep(index) {
    steps.forEach((step, i) => {
      step.classList.toggle("active", i === index);
    });
    stepIndicators.forEach((ind, i) => {
      ind.classList.toggle("active", i <= index);
      ind.classList.toggle("current", i === index);
    });
    steps[index]?.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  // Validar campos requeridos del paso actual
  function validateCurrentStep() {
    const active = steps[currentStep];
    if (!active) return true;
    const required = Array.from(active.querySelectorAll("[required]"));
    for (const el of required) {
      if (!el.value || el.value.trim() === "") {
        el.classList.add("is-invalid");
        el.focus();
        return false;
      } else {
        el.classList.remove("is-invalid");
      }
    }
    return true;
  }

  // Botón siguiente
  document.addEventListener("click", function (e) {
    const nextBtn = e.target.closest(".next-btn");
    if (nextBtn) {
      e.preventDefault();
      if (currentStep < steps.length - 1) {
        if (!validateCurrentStep()) return;
        currentStep++;
        showStep(currentStep);
      }
    }
  });

  // Botón anterior
  document.addEventListener("click", function (e) {
    const prevBtn = e.target.closest(".prev-btn");
    if (prevBtn) {
      e.preventDefault();
      if (currentStep > 0) {
        currentStep--;
        showStep(currentStep);
      }
    }
  });

  // Evitar que Enter envíe el form antes de tiempo
  const form = document.querySelector("form");
  if (form) {
    form.addEventListener("keydown", function (e) {
      if (e.key === "Enter") {
        const isLastStep = currentStep === steps.length - 1;
        if (!isLastStep) {
          e.preventDefault();
          if (validateCurrentStep()) {
            currentStep = Math.min(currentStep + 1, steps.length - 1);
            showStep(currentStep);
          }
        }
      }
    });
  }

  // Mostrar primer paso
  showStep(currentStep);
});
