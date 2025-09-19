document.addEventListener("DOMContentLoaded", function () {
  // Flatpickr para fecha de nacimiento con mejor configuración
  if (typeof flatpickr !== "undefined") {
    flatpickr("#fecha_nacimiento", {
      dateFormat: "Y-m-d",
      maxDate: "today",
      locale: "es",
      defaultDate: "1990-01-01",
      // Mejoras en la apariencia
      static: true,
      monthSelectorType: 'static',
      prevArrow: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>',
      nextArrow: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>',
      // Mejoras en la accesibilidad
      altInput: true,
      altFormat: "j F, Y",
      ariaDateFormat: "l, j F Y",
      // Estilo del calendario
      theme: "light",
      // Mejoras en la experiencia móvil
      disableMobile: false,
      // Configuración específica para el campo de fecha
      allowInput: true,
      clickOpens: true,
      onOpen: function(selectedDates, dateStr, instance) {
        // Asegurar que el calendario se muestre correctamente
        instance.calendarContainer.style.zIndex = "9999";
      }
    });
  }

  // Inicializa Choices en TODOS los selects
  document.querySelectorAll("select").forEach(select => {
    new Choices(select, { 
      removeItemButton: false, 
      shouldSort: false,
      searchEnabled: false,
      itemSelectText: 'Presiona para seleccionar',
      classNames: {
        containerOuter: 'choices',
        containerInner: 'choices__inner',
        input: 'choices__input',
        item: 'choices__item',
        list: 'choices__list',
        listSingle: 'choices__list--single',
        listDropdown: 'choices__list--dropdown',
        group: 'choices__group',
        placeholder: 'choices__placeholder',
        button: 'choices__button'
      }
    });
  });
});
