// JS de Registro de Facilitador: calendario + selects

document.addEventListener('DOMContentLoaded', function(){
  // ===== Flatpickr para Fecha de Nacimiento =====
  if (typeof flatpickr !== 'undefined') {

    const input = document.querySelector('#fecha_nacimiento');
    if (input) {
      flatpickr(input, {
        dateFormat: 'd/m/Y',
        disableMobile: true,
        locale: 'es',
        monthSelectorType: 'dropdown',
        yearSelectorType: 'dropdown',
        maxDate: 'today',
        appendTo: document.querySelector('.profesor-crear')
      });

      // Abrir calendario al hacer clic en el icono
      const icon = document.querySelector('.date-icon');
      if (icon && input) {
        icon.addEventListener('click', function(){
          if (input._flatpickr) input._flatpickr.open();
        });
      }
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
});

