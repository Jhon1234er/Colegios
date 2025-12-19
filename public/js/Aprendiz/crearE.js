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
  // Modal de error reutilizable para este formulario
  // ────────────────────────────────────────────────────────────
  function createFormErrorModal() {
    let backdrop = document.getElementById('form-error-backdrop');
    if (backdrop) return backdrop;

    backdrop = document.createElement('div');
    backdrop.id = 'form-error-backdrop';
    backdrop.className = 'form-error-backdrop';
    backdrop.innerHTML = `
      <div class="form-error-modal" role="alertdialog" aria-modal="true">
        <div class="form-error-header">
          <span class="form-error-title">Ocurrió un problema</span>
          <button type="button" class="form-error-close" aria-label="Cerrar">&times;</button>
        </div>
        <div class="form-error-body">
          <p class="form-error-message"></p>
        </div>
        <div class="form-error-actions">
          <button type="button" class="btn btn-primario form-error-ok">Entendido</button>
        </div>
      </div>
    `;

    document.body.appendChild(backdrop);

    const close = () => {
      backdrop.classList.remove('is-visible');
    };

    backdrop.addEventListener('click', (e) => {
      if (
        e.target === backdrop ||
        e.target.classList.contains('form-error-close') ||
        e.target.classList.contains('form-error-ok')
      ) {
        close();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && backdrop.classList.contains('is-visible')) {
        close();
      }
    });

    return backdrop;
  }

  function showErrorModal(message) {
    const backdrop = createFormErrorModal();
    const msgEl = backdrop.querySelector('.form-error-message');
    if (msgEl) {
      msgEl.textContent = message;
    }
    backdrop.classList.add('is-visible');
  }

  // Exponer para que otros scripts en la vista puedan reutilizarlo
  window.showFormError = showErrorModal;

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
  const fixedColegio = (document.getElementById('fixed_colegio_id') && document.getElementById('fixed_colegio_id').value) ? String(document.getElementById('fixed_colegio_id').value) : '';

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

    let grados = safeParseList(gradosRaw).map(cleanTextItem);
    let jornadas = safeParseList(jornadasRaw).map(cleanTextItem).map(toTitle);

    console.log("✅ Grados parseados:", grados);
    console.log("✅ Jornadas parseadas:", jornadas);

    const needFetch = (!grados || grados.length === 0) && (!jornadas || jornadas.length === 0);
    if (needFetch) {
      // Fallback: obtener desde endpoint ligero
      fetch(`/?page=info_colegio&colegio_id=${encodeURIComponent(value)}`)
        .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP '+r.status)))
        .then(data => {
          const g = Array.isArray(data.grados) ? data.grados : [];
          const j = Array.isArray(data.jornadas) ? data.jornadas : [];
          clearAndSetChoices(gradoId, g, gradoPlaceholder);
          clearAndSetChoices(jornadaId, j.map(toTitle), jornadaPlaceholder);
        })
        .catch(err => {
          console.warn('No se pudieron cargar grados/jornadas vía API', err);
          clearAndSetChoices(gradoId, [], gradoPlaceholder);
          clearAndSetChoices(jornadaId, [], jornadaPlaceholder);
        });
    } else {
      clearAndSetChoices(gradoId, grados, gradoPlaceholder);
      clearAndSetChoices(jornadaId, jornadas, jornadaPlaceholder);
    }
  }

  if (colegioSelect) {
    if (fixedColegio) {
      try {
        colegioSelect.value = fixedColegio;
        if (hasChoices && choicesInstances['colegio']) {
          try { choicesInstances['colegio'].setChoiceByValue(fixedColegio); } catch(_) {}
        }
        updateGradosJornadas();
        colegioSelect.setAttribute('disabled','disabled');
      } catch(_) {}
    }
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

  function toggleRequiredForStep(stepEl, enable) {
    if (!stepEl) return;
    const controls = stepEl.querySelectorAll('input, select, textarea');
    controls.forEach(el => {
      if (enable) {
        if (el.dataset.wasRequired === '1') {
          el.required = true;
        }
      } else {
        if (el.required) {
          el.dataset.wasRequired = '1';
        }
        el.required = false;
      }
    });
  }

  function showStep(index) {
    if (!steps.length) return;
    steps.forEach((step, i) => {
      const active = i === index;
      step.classList.toggle('active', active);
      toggleRequiredForStep(step, active);
    });
    stepIndicators.forEach((ind, i) => {
      ind.classList.toggle("active", i <= index);
      ind.classList.toggle("current", i === index);
    });
  }

  function validateCurrentStep() {
    const active = steps[currentStep];
    if (!active) return true;
    const required = Array.from(active.querySelectorAll("[required]"));
    let firstInvalid = null;
    for (const el of required) {
      if (!el.value || el.value.trim() === "") {
        el.classList.add("is-invalid");
        if (!firstInvalid) {
          firstInvalid = el;
        }
      } else {
        el.classList.remove("is-invalid");
      }
    }
    if (firstInvalid) {
      if (typeof showErrorModal === 'function') {
        showErrorModal('Por favor completa los campos obligatorios de este paso.');
      }
      firstInvalid.focus();
      return false;
    }
    return true;
  }

  function validateAcudientesRule() {
    const acContainer = document.getElementById('acudientes-container');
    if (!acContainer) return true;

    const items = Array.from(acContainer.querySelectorAll('.acudiente-item'));
    if (items.length === 0) {
      showErrorModal('Debes agregar al menos un acudiente.');
      return false;
    }
    if (items.length > 2) {
      showErrorModal('Solo puedes registrar máximo 2 acudientes.');
      return false;
    }

    let acudientes = 0;
    items.forEach(item => {
      const selAcu = item.querySelector('select[name$="[es_acudiente]"]');
      if (selAcu && selAcu.value === '1') {
        acudientes++;
      }
    });

    if (acudientes === 0) {
      showErrorModal('Debes marcar al menos un acudiente.');
      return false;
    }

    return true;
  }

  document.addEventListener("click", function (e) {
    const nextBtn = e.target.closest(".next-btn");
    if (nextBtn) {
      e.preventDefault();
      if (currentStep < steps.length - 1) {
        if (!validateCurrentStep()) return;
        if (currentStep === 1 && !validateAcudientesRule()) return;
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
    // Navegar con Enter al siguiente paso (sin enviar formulario)
    form.addEventListener("keydown", function (e) {
      if (e.key === "Enter") {
        const isLastStep = currentStep === steps.length - 1;
        if (!isLastStep) {
          e.preventDefault();
          if (validateCurrentStep()) {
            if (currentStep === 1 && !validateAcudientesRule()) return;
            currentStep = Math.min(currentStep + 1, steps.length - 1);
            showStep(currentStep);
          }
        }
      }
    });

    // Validación completa antes de enviar
    form.addEventListener('submit', function(e){
      const acContainer = document.getElementById('acudientes-container');
      if (acContainer) {
        if (!validateAcudientesRule()) {
          e.preventDefault();
          currentStep = Math.min(1, steps.length - 1);
          showStep(currentStep);
          return;
        }
      }

      // Restaurar required en todos los pasos para validación completa
      steps.forEach(step => toggleRequiredForStep(step, true));
      // Revalidar manualmente: si algo falta, saltar a ese paso y bloquear envío
      for (let i = 0; i < steps.length; i++) {
        const step = steps[i];
        const required = Array.from(step.querySelectorAll('[required]'));
        for (const el of required) {
          if (!el.value || el.value.trim() === '') {
            e.preventDefault();
            currentStep = i;
            showStep(currentStep);
            el.classList.add('is-invalid');
            el.focus();
            return;
          }
        }
      }
    });
  }

  showStep(currentStep);
});
