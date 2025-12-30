// JS de Registro de Facilitador: calendario + selects
(function(){
  function init(){
    // ===== Flatpickr para Fecha de Nacimiento =====
    if (typeof flatpickr !== 'undefined') {
      // Localizar a español si el bundle de locales está cargado
      try { if (flatpickr.l10ns && flatpickr.l10ns.es) { flatpickr.localize(flatpickr.l10ns.es); } } catch(_){}

      const input = document.querySelector('#fecha_nacimiento');
      if (input) {
        const container = document.body; // evitar clipping por overflow en contenedores
        const __today = new Date();
        const __cutoff = new Date(__today.getFullYear() - 18, __today.getMonth(), __today.getDate());
        flatpickr(input, {
          dateFormat: 'd/m/Y',
          disableMobile: true,
          locale: 'es',
          monthSelectorType: 'dropdown',
          yearSelectorType: 'dropdown',
          maxDate: __cutoff,
          appendTo: container,
          static: false,
          position: 'auto',
          zIndex: 3000
        });

        // Abrir calendario al hacer clic en el icono (si existe)
        const icon = document.querySelector('.date-icon');
        if (icon) {
          icon.addEventListener('click', function(){
            if (input._flatpickr) input._flatpickr.open();
          });
        }
        // Asegurar apertura en click/focus del input
        input.addEventListener('focus', function(){ if (input._flatpickr) input._flatpickr.open(); });
        input.addEventListener('click', function(){ if (input._flatpickr) input._flatpickr.open(); });
      }
    }

    // ===== Choices en TODOS los selects =====
    if (typeof Choices !== 'undefined') {
      document.querySelectorAll('select').forEach(function(sel){
        // Evitar aplicar Choices a los selects internos del calendario Flatpickr
        if (sel.closest('.flatpickr-calendar')) return;
        if (sel.classList.contains('flatpickr-monthDropdown-months')) return;
        try {
          new Choices(sel, { shouldSort:false, searchEnabled:false, itemSelectText:'' });
        } catch (_) {}
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

