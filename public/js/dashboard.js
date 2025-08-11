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
                        div1.innerHTML = '<p>No hay facilitadores registrados para este colegio.</p>';
                        return;
                    }

                    let html = '<h3>Facilitadores</h3>';
                    data.forEach(p => {
                        html += `
                            <div class="profesor-card">
                                <div class="profesor-name">${p.nombre} 
                                    <p class="profesor-materia">Materia: ${p.materia}</p>
                                </div>
                            </div>`;
                    });
                    div1.innerHTML = html;
                })
                .catch(err => console.error('Error cargando profesores:', err));

            // 🔹 Mostrar Estudiantes
            fetch(`../index.php?page=estudiantes_por_colegio&colegio_id=${colegioId}`)
                .then(res => res.json())
                .then(data => {
                    const div2 = document.querySelector('.div2');
                    document.querySelector('.esta')?.remove();
                    if (!data.length) {
                        div2.innerHTML = '<p>No hay aprendices registrados para este colegio.</p>';
                        return;
                    }

                    let html = '<h3>Aprendices</h3>';
                    data.forEach(e => {
                        html += `
                            <div class="student-card">
                                <div class="student-name">${e.nombre_completo}</div>
                                <div class="student-details">
                                    Grado: ${e.grado}, Jornada: ${e.jornada}<br>
                                    <strong>Acudiente:</strong> ${e.nombre_completo_acudiente}<br>
                                    <strong>Celular:</strong> ${e.telefono_acudiente}<br>
                                    <strong>Parentesco:</strong> ${e.parentesco}<br>
                                    <strong>Ficha:</strong> ${e.numero_ficha}
                                </div>
                            </div>`;
                    });
                    div2.innerHTML = html;
                })
                .catch(err => console.error('Error cargando estudiantes:', err));

            // 🔹 Mostrar Estadísticas ECharts
            fetch(`../ajax/asistencias_por_colegio.php?colegio_id=${colegioId}`)
                .then(res => res.json())
                .then(data => {
                    const dom = document.getElementById('chart-container');
                    const myChart = echarts.init(dom, 'dark');

                    const fichasData = data.fichas.map(f => ({
                        value: f.total_fallas,
                        name: `Ficha ${f.numero_ficha}`
                    }));

                    const option = {
                        legend: { top: 'bottom' },
                        toolbox: {
                            show: true,
                            feature: {
                                mark: { show: true },
                                dataView: { show: true, readOnly: false },
                                restore: { show: true },
                                saveAsImage: { show: true }
                            }
                        },
                        series: [
                            {
                                name: 'Fallas por ficha',
                                type: 'pie',
                                radius: [50, 250],
                                center: ['50%', '50%'],
                                roseType: 'area',
                                itemStyle: { borderRadius: 8 },
                                data: fichasData
                            }
                        ]
                    };

                    myChart.setOption(option);

                    // 🔹 Mostrar alertas de estudiantes con >= 3 fallas
                    if (data.alertas.length > 0) {
                        let alertHtml = '<h4>⚠ Estudiantes con 3+ fallas</h4><ul>';
                        data.alertas.forEach(e => {
                            alertHtml += `<li><strong>${e.nombres} ${e.apellidos}</strong> - Ficha ${e.numero_ficha} (${e.total_fallas} fallas)</li>`;
                        });
                        alertHtml += '</ul>';
                        document.querySelector('.div3').insertAdjacentHTML('beforeend', alertHtml);
                    }
                })
                .catch(err => console.error('Error cargando estadísticas:', err));
        });
    });
});
