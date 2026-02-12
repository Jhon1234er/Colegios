document.addEventListener("DOMContentLoaded", function () {
  // ────────────────────────────────────────────────────────────
  // Helpers / Utils
  // ────────────────────────────────────────────────────────────
  const hasChoices = typeof Choices !== "undefined";
  const choicesInstances = {};

  const cssEscape = (window.CSS && CSS.escape) ? CSS.escape : (v) =>
    String(v).replace(/[^a-zA-Z0-9_\-]/g, (c) => "\\" + c);

  const toTitle = (s) => (s || "").charAt(0).toUpperCase() + (s || "").slice(1).toLowerCase();

  function initChoices(element, id) {
    if (!element || !hasChoices) return;
    if (choicesInstances[id]) {
      try { choicesInstances[id].destroy(); } catch (_) {}
      delete choicesInstances[id];
    }
    const openUp = element && (element.name === 'eps' || element.name === 'estrato');
    choicesInstances[id] = new Choices(element, {
      searchEnabled: true,
      shouldSort: false,
      placeholder: true,
      itemSelectText: '',
      position: openUp ? 'top' : 'auto'
    });
  }

  function clearAndSetChoices(id, values, placeholder) {
    const inst = choicesInstances[id];
    const el = getSelectById(id);

    if (inst) {
      try { inst.clearChoices(); } catch (_) {}
      const items = (values || []).map(v => ({ value: v, label: v }));
      inst.setChoices(
        [{ value: "", label: placeholder, disabled: true, selected: true }].concat(items),
        "value",
        "label",
        true
      );
    } else if (el) {
      el.innerHTML = "";
      const optPh = document.createElement("option");
      optPh.value = "";
      optPh.textContent = placeholder;
      el.appendChild(optPh);
      (values || []).forEach(v => {
        const opt = document.createElement("option");
        opt.value = v;
        opt.textContent = v;
        el.appendChild(opt);
      });
    }
  }

  function getSelectById(id) {
    switch (id) {
      case "colegio": return document.getElementById("colegio_id");
      case "grado": return document.getElementById("grado");
      case "jornada": return document.getElementById("jornada");
      case "parentesco": return document.getElementById("parentesco");
      case "ocupacion": return document.getElementById("ocupacion");
      default: return document.getElementById(id);
    }
  }

  function safeParseList(str) {
    if (!str) return [];
    let txt = String(str).trim();
    try { const arr = JSON.parse(txt); if (Array.isArray(arr)) return arr; } catch (_) {}
    if (txt.includes("&quot;")) {
      try { const arr2 = JSON.parse(txt.replace(/&quot;/g, '"')); if (Array.isArray(arr2)) return arr2; } catch (_) {}
    }
    if (txt.includes(",")) return txt.split(",").map(s => s.trim()).filter(Boolean);
    return txt ? [txt] : [];
  }

  // Limpia texto: remueve comillas envolventes y decodifica \uXXXX
  function cleanTextItem(s) {
    try {
      let t = String(s).trim();
      // quitar comillas de borde simples o dobles
      if ((t.startsWith('"') && t.endsWith('"')) || (t.startsWith("'") && t.endsWith("'"))) {
        t = t.slice(1, -1);
      }
      // decodificar secuencias unicode usando JSON.parse sobre string
      try { t = JSON.parse('"' + t.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"'); } catch (_) {}
      return t;
    } catch (_) { return s; }
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
    { element: document.querySelector('select[name="filtro"]'), id: 'filtro' },
    { element: document.getElementById('parentesco'), id: 'parentesco' },
    { element: document.getElementById('ocupacion'), id: 'ocupacion' }
  ];
  selectsCfg.forEach(s => initChoices(s.element, s.id));

  // ────────────────────────────────────────────────────────────
  // Actualizar grados/jornadas según colegio
  // ────────────────────────────────────────────────────────────
  const colegioSelect = document.getElementById('colegio_id');

  function updateGradosJornadas() {
    const gradoId = 'grado';
    const jornadaId = 'jornada';
    const gradoPlaceholder = 'Seleccione grado';
    const jornadaPlaceholder = 'Seleccione jornada';

    const value = colegioSelect ? colegioSelect.value : "";
    console.log("📌 Colegio seleccionado:", value);

    if (!value) {
      clearAndSetChoices(gradoId, [], gradoPlaceholder);
      clearAndSetChoices(jornadaId, [], jornadaPlaceholder);
      return;
    }

    let selectedOption = null;
    try {
      selectedOption = colegioSelect.querySelector(`option[value="${cssEscape(value)}"]`);
    } catch (_) {
      const opts = Array.from(colegioSelect.options || []);
      selectedOption = opts.find(o => String(o.value) === String(value)) || null;
    }

    console.log("📌 Opción seleccionada:", selectedOption);

    if (!selectedOption) {
      clearAndSetChoices(gradoId, [], gradoPlaceholder);
      clearAndSetChoices(jornadaId, [], jornadaPlaceholder);
      return;
    }

    let gradosRaw = selectedOption.getAttribute('data-grados') || '[]';
    let jornadasRaw = selectedOption.getAttribute('data-jornada') || '[]';

    console.log("📥 Grados raw:", gradosRaw);
    console.log("📥 Jornadas raw:", jornadasRaw);

    const grados = safeParseList(gradosRaw).map(cleanTextItem);
    const jornadas = safeParseList(jornadasRaw).map(cleanTextItem).map(toTitle);

    console.log("✅ Grados parseados:", grados);
    console.log("✅ Jornadas parseadas:", jornadas);

    clearAndSetChoices(gradoId, grados, gradoPlaceholder);
    clearAndSetChoices(jornadaId, jornadas, jornadaPlaceholder);
  }

  if (colegioSelect) {
    colegioSelect.addEventListener('change', updateGradosJornadas);
    if (hasChoices && choicesInstances['colegio']) {
      const inst = choicesInstances['colegio'];
      inst.passedElement.element.addEventListener('change', updateGradosJornadas);
      if (typeof inst.on === 'function') {
        try { inst.on('addItem', updateGradosJornadas); } catch (_) {}
      }
    }
  }
  if (colegioSelect && colegioSelect.value) updateGradosJornadas();

  // ────────────────────────────────────────────────────────────
  // Manejo de "Otro" en parentesco y ocupación
  // ────────────────────────────────────────────────────────────
  function toggleOtro(selectId, inputId) {
    const selectEl = document.getElementById(selectId);
    const inputEl = document.getElementById(inputId);
    if (!selectEl || !inputEl) return;

    const handler = () => {
      if (selectEl.value === "Otro") {
        inputEl.style.display = "block";
        inputEl.required = true;
      } else {
        inputEl.style.display = "none";
        inputEl.required = false;
        inputEl.value = "";
      }
    };

    selectEl.addEventListener("change", handler);
    if (hasChoices && choicesInstances[selectId]) {
      const inst = choicesInstances[selectId];
      inst.passedElement.element.addEventListener("change", handler);
      if (typeof inst.on === 'function') {
        try { inst.on('addItem', handler); } catch (_) {}
      }
    }
    handler();
  }

  toggleOtro("parentesco", "parentesco_otro");
  toggleOtro("ocupacion", "ocupacion_otro");

  // ────────────────────────────────────────────────────────────
  // Flatpickr
  // ────────────────────────────────────────────────────────────
  if (typeof flatpickr !== "undefined") {
    const el = document.querySelector('#fecha_nacimiento');
    if (el) {
      flatpickr(el, {
        dateFormat: 'd/m/Y',
        maxDate: 'today',
        locale: 'es',
        disableMobile: true,
        monthSelectorType: 'dropdown',
        yearSelectorType: 'dropdown',
        appendTo: document.querySelector('.estudiante-crear') || undefined,
      });
      // Abrir al pulsar el icono
      const icon = document.querySelector('.date-icon');
      if (icon) icon.addEventListener('click', ()=> el._flatpickr && el._flatpickr.open());
    }
  }

  // ────────────────────────────────────────────────────────────
  // STEPPER
  // ────────────────────────────────────────────────────────────
  const steps = Array.from(document.querySelectorAll(".form-step"));
  const stepIndicators = Array.from(document.querySelectorAll(".stepper .step"));
  let currentStep = 0;

  document.querySelectorAll(".next-btn, .prev-btn").forEach(btn => {
    if (!btn.getAttribute("type")) btn.setAttribute("type", "button");
  });

  function showStep(index) {
    if (!steps.length) return;
    steps.forEach((step, i) => step.classList.toggle("active", i === index));
    stepIndicators.forEach((ind, i) => {
      ind.classList.toggle("active", i <= index);
      ind.classList.toggle("current", i === index);
    });
    // Evitar animaciones: posicionar sin desplazamiento suave
    // Si se requiere garantizar visibilidad en pantallas pequeñas, usar 'auto'
    // steps[index]?.scrollIntoView({ behavior: "auto", block: "nearest" });
  }

  function validateCurrentStep() {
    const active = steps[currentStep];
    if (!active) return true;
    
    // Si el checkbox "sin_ficha" está marcado, permitir continuar sin validar ficha
    const sinFichaCheckbox = document.getElementById('sin_ficha');
    const isSinFicha = sinFichaCheckbox && sinFichaCheckbox.checked;
    
    if (isSinFicha) {
      // Si está marcado "sin_ficha", solo validar que no haya campos required vacíos excepto ficha
      const required = Array.from(active.querySelectorAll("[required]"));
      for (const el of required) {
        // Ignorar completamente cualquier campo relacionado con ficha
        if (el.name === 'ficha_id' || el.id === 'ficha_id' || el.id === 'ficha_id_hidden') {
          el.removeAttribute('required'); // Eliminar required temporalmente
          continue;
        }
        
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
    
    // Validación normal si no está marcado "sin_ficha"
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

  const form = document.getElementById("formEstudiante");
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

  showStep(currentStep);
});
