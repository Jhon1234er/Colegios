document.addEventListener("DOMContentLoaded", function () {
  // Flatpickr para fecha de nacimiento
  if (typeof flatpickr !== "undefined") {
    flatpickr("#fecha_nacimiento", {
      dateFormat: "Y-m-d",
      maxDate: "today",
      locale: "es"
    });
  }

  // Inicializa Choices en TODOS los selects
  document.querySelectorAll("select").forEach(select => {
    new Choices(select, { removeItemButton: false, shouldSort: false });
  });
});
