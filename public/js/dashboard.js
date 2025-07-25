document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.btn-ver-colegio').forEach(btn => {
        btn.addEventListener('click', function () {
            const colegioId = this.dataset.id;

            // 🔹 Mostrar Profesores
            fetch(`../index.php?page=profesores_por_colegio&colegio_id=${colegioId}`)
                .then(res => res.json())
                .then(data => {
                    const div1 = document.querySelector('.div1');
                    if (!data.length) {
                        div1.innerHTML = '<p>No hay profesores registrados para este colegio.</p>';
                        return;
                    }

                    let html = '<h3>Profesores y Materias</h3><ul>';
                    data.forEach(p => {
                        html += `<li><strong>${p.nombre}</strong> - ${p.materia}</li>`;
                    });
                    html += '</ul>';
                    div1.innerHTML = html;
                })
                .catch(err => console.error('Error cargando profesores:', err));

            // 🔹 Mostrar Estudiantes
            fetch(`../index.php?page=estudiantes_por_colegio&colegio_id=${colegioId}`)
                .then(res => res.json())
                .then(data => {
                    const div2 = document.querySelector('.div2');
                    if (!data.length) {
                        div2.innerHTML = '<p>No hay estudiantes registrados para este colegio.</p>';
                        return;
                    }

                    let html = '<h3>Estudiantes</h3><ul>';
                    data.forEach(e => {
                        html += `
                            <li>
                                <strong>${e.nombre_completo}</strong> - Grado: ${e.grado}, Jornada: ${e.jornada}<br>
                                Acudiente: ${e.nombre_completo_acudiente}, Celular: ${e.telefono_acudiente}, Parentesco: ${e.parentesco}
                            </li>
                            <hr>`;
                    });
                    html += '</ul>';
                    div2.innerHTML = html;
                })
                .catch(err => console.error('Error cargando estudiantes:', err));

            // 🔹 Mostrar Gráfico Estadístico
            const canvas = document.getElementById('graficoColegios');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            if (window.chartInstance) window.chartInstance.destroy();

            window.chartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Matemáticas', 'Español', 'Inglés', 'Ciencias'],
                    datasets: [{
                        label: 'Cantidad de profesores por materia',
                        data: [10, 7, 4, 2], // 🔸 Sustituir por datos reales desde el servidor si aplica
                        backgroundColor: [
                            'rgba(34, 197, 94, 0.6)',   // Verde
                            'rgba(59, 130, 246, 0.6)',  // Azul
                            'rgba(249, 115, 22, 0.6)',  // Naranja
                            'rgba(234, 88, 12, 0.6)'    // Rojo oscuro
                        ],
                        borderColor: [
                            'rgba(34, 197, 94, 1)',
                            'rgba(59, 130, 246, 1)',
                            'rgba(249, 115, 22, 1)',
                            'rgba(234, 88, 12, 1)'
                        ],
                        borderWidth: 1.5
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Profesores por materia en el colegio seleccionado',
                            font: {
                                size: 18,
                                weight: 'bold'
                            },
                            color: '#1e293b'
                        },
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        });
    });
});
