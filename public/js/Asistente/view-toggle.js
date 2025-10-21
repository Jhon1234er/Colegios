// public/js/Asistente/view-toggle.js
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('view-toggle-btn');
    const parentContainer = document.querySelector('.parent');

    if (toggleBtn && parentContainer) {
        toggleBtn.addEventListener('click', () => {
            parentContainer.classList.toggle('show-secondary');
        });
    }
});
