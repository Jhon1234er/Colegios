document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar-menu');
    const toggleButton = document.getElementById('sidebar-toggle');
    const body = document.body;

    if (toggleButton && sidebar) {
        toggleButton.addEventListener('click', () => {
            sidebar.classList.toggle('w-16');
            body.classList.toggle('sidebar-collapsed');
        });
    }

    const itemButtons = document.querySelectorAll('.sidebar-item-button');

    itemButtons.forEach(button => {
        button.addEventListener('click', () => {
            const content = button.nextElementSibling;
            if (content && content.classList.contains('expandable-content')) {
                // Cerrar otros menús abiertos
                document.querySelectorAll('.expandable-content.open').forEach(openContent => {
                    if (openContent !== content) {
                        openContent.classList.remove('open');
                        openContent.style.maxHeight = '0';
                    }
                });

                // Abrir o cerrar el menú actual
                content.classList.toggle('open');
                if (content.classList.contains('open')) {
                    content.style.maxHeight = content.scrollHeight + "px";
                } else {
                    content.style.maxHeight = '0';
                }
            }
        });
    });
});