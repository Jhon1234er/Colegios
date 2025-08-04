<?php require __DIR__ . '/../Componentes/encabezado.php'; ?>
<h2>Bienvenido, Profesor <?= htmlspecialchars($_SESSION['usuario']['nombres']) ?></h2>

<h3>Mis Fichas</h3>
<ul id="lista-fichas"></ul>

<div id="estudiantes-ficha" style="margin-top:2em;"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
        fetch('index.php?page=profesor_ficha')  
        .then(res => res.json())
        .then(fichas => {
            const ul = document.getElementById('lista-fichas');
            if (!fichas.length) {
                ul.innerHTML = '<li>No tienes fichas asignadas.</li>';
                return;
            }
            fichas.forEach(f => {
                const li = document.createElement('li');
                li.innerHTML = `${f.nombre} <button data-id="${f.id}" class="btn-ver-estudiantes">Ver estudiantes</button>`;
                ul.appendChild(li);
            });

            ul.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-ver-estudiantes')) {
                    const fichaId = e.target.dataset.id;
                        fetch(`index.php?page=estudiantes_por_ficha&ficha_id=${fichaId}`)   
                        .then(res => res.json())
                        .then(estudiantes => {
                            const div = document.getElementById('estudiantes-ficha');
                            if (!estudiantes.length) {
                                div.innerHTML = '<p>No hay estudiantes en esta ficha.</p>';
                                return;
                            }
                            let html = `<h4>Estudiantes de la ficha</h4>
                                <table border="1" cellpadding="5">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Grado</th>
                                    <th>Jornada</th>
                                    <th>Acudiente</th>
                                    <th>Teléfono</th>
                                    <th>Parentesco</th>
                                </tr>`;
                            estudiantes.forEach(e => {
                                html += `<tr>
                                    <td>${e.nombres} ${e.apellidos}</td>
                                    <td>${e.grado}</td>
                                    <td>${e.jornada}</td>
                                    <td>${e.nombre_completo_acudiente}</td>
                                    <td>${e.telefono_acudiente}</td>
                                    <td>${e.parentesco}</td>
                                </tr>`;
                            });
                            html += '</table>';
                            div.innerHTML = html;
                        });
                }
            });
        });
});
</script>
<script src="/js/dashboard_profesor.js"></script>

<?php require __DIR__ . '/../Componentes/footer.php'; ?>