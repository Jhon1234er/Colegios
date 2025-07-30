document.addEventListener("DOMContentLoaded", function () {
    const API_URL = "https://www.datos.gov.co/resource/upkm-vdjb.json";
    const TOKEN = "DzkbPRExtJtt2F38YHxxZHU2k";

    // Inputs y listas
    const departamentoInput = document.getElementById("departamento");
    const departamentoLista = document.getElementById("departamento-lista");
    const municipioInput = document.getElementById("municipio");
    const municipioLista = document.getElementById("municipio-lista");
    const colegioInput = document.getElementById("nombre");
    const colegioLista = document.getElementById("colegio-lista");
    const direccionInput = document.getElementById("direccion");

    let departamentos = [];
    let municipios = [];
    let colegios = [];

    // Cargar departamentos
    fetch(`${API_URL}?$select=nombredepartamento&$limit=50000`, { headers: { "X-App-Token": TOKEN } })
        .then(res => res.json())
        .then(data => {
            const departamentos = Array.from(new Set(data.map(item => item.nombredepartamento))).filter(Boolean).sort();
            departamentoChoices.clearChoices();
            departamentoChoices.setChoices(
                departamentos.map(dep => ({ value: dep, label: dep })),
                'value', 'label', false
            );
        });

    // Cuando seleccionan un departamento, carga municipios
    document.getElementById('departamento').addEventListener('change', function() {
        const dep = this.value;
        municipioChoices.clearChoices();
        colegioChoices.clearChoices();
        fetch(`${API_URL}?nombredepartamento=${encodeURIComponent(dep)}&$select=nombremunicipio&$limit=50000`, { headers: { "X-App-Token": TOKEN } })
            .then(res => res.json())
            .then(data => {
                const municipios = Array.from(new Set(data.map(item => item.nombremunicipio))).filter(Boolean).sort();
                municipioChoices.setChoices(
                    municipios.map(mun => ({ value: mun, label: mun })),
                    'value', 'label', false
                );
            });
    });

    // Cuando seleccionan un municipio, carga colegios
    document.getElementById('municipio').addEventListener('change', function() {
        const dep = document.getElementById('departamento').value;
        const mun = this.value;
        colegioChoices.clearChoices();
        fetch(`${API_URL}?nombredepartamento=${encodeURIComponent(dep)}&nombremunicipio=${encodeURIComponent(mun)}&$select=nombreestablecimiento,direccion,telefono,correo_electronico,codigoestablecimiento,jornada,grados,calendario,prestador_de_servicio&$limit=50000`, { headers: { "X-App-Token": TOKEN } })
            .then(res => res.json())
            .then(data => {
                const colegios = data.filter(item => item.nombreestablecimiento);
                colegioChoices.setChoices(
                    colegios.map(col => ({
                        value: col.nombreestablecimiento,
                        label: `${col.nombreestablecimiento}${col.direccion ? ' - ' + col.direccion : ''}`,
                        customProperties: {
                            direccion: col.direccion || '',
                            telefono: col.telefono || '',
                            correo: col.correo_electronico || '',
                            codigo: col.codigoestablecimiento || '',
                            jornada: col.jornada || '',
                            grados: col.grados || '',
                            calendario: col.calendario || '',
                            prestador: col.prestador_de_servicio || ''
                        }
                    })),
                    'value', 'label', false
                );
            });
    });

    // Autocompletar departamento
    departamentoInput.addEventListener("input", function () {
        const texto = this.value.toLowerCase();
        departamentoLista.innerHTML = "";
        if (!texto) return;
        departamentos.filter(dep => dep.toLowerCase().includes(texto)).slice(0, 10).forEach(dep => {
            const item = document.createElement("button");
            item.type = "button";
            item.className = "list-group-item list-group-item-action";
            item.textContent = dep;
            item.onclick = () => {
                departamentoInput.value = dep;
                departamentoLista.innerHTML = "";
                cargarMunicipios(dep);
            };
            departamentoLista.appendChild(item);
        });
    });
    departamentoInput.addEventListener("blur", () => setTimeout(() => departamentoLista.innerHTML = "", 200));

    // Cargar municipios según departamento
    function cargarMunicipios(dep) {
        municipioInput.value = "";
        municipioLista.innerHTML = "";
        colegioInput.value = "";
        colegioLista.innerHTML = "";
        direccionInput.value = "";
        municipios = [];
        colegios = [];
        fetch(`${API_URL}?nombredepartamento=${encodeURIComponent(dep)}&$select=nombremunicipio&$limit=50000`, { headers: { "X-App-Token": TOKEN } })
            .then(res => res.json())
            .then(data => {
                if (Array.isArray(data)) {
                    municipios = Array.from(new Set(data.map(item => item.nombremunicipio))).filter(Boolean).sort();
                } else {
                    municipios = [];
                    console.error("Respuesta inesperada de la API de municipios:", data);
                }
            })
            .catch(err => {
                municipios = [];
                console.error("Error al cargar municipios:", err);
            });
    }

    // Autocompletar municipio
    municipioInput.addEventListener("input", function () {
        const texto = this.value.toLowerCase();
        municipioLista.innerHTML = "";
        if (!texto) return;
        municipios.filter(mun => mun && mun.toLowerCase().includes(texto)).slice(0, 10).forEach(mun => {
            const item = document.createElement("button");
            item.type = "button";
            item.className = "list-group-item list-group-item-action";
            item.textContent = mun;
            item.onclick = () => {
                municipioInput.value = mun;
                municipioLista.innerHTML = "";
                cargarColegios(departamentoInput.value, mun);
            };
            municipioLista.appendChild(item);
        });
    });
    municipioInput.addEventListener("blur", () => setTimeout(() => municipioLista.innerHTML = "", 200));

    // Cargar colegios según municipio
    function cargarColegios(dep, mun) {
        colegioInput.value = "";
        colegioLista.innerHTML = "";
        direccionInput.value = "";
        colegios = [];
        fetch(`${API_URL}?nombredepartamento=${encodeURIComponent(dep)}&nombremunicipio=${encodeURIComponent(mun)}&$select=nombreestablecimiento,direccion&$limit=50000`, { headers: { "X-App-Token": TOKEN } })
            .then(res => res.json())
            .then(data => {
                if (Array.isArray(data)) {
                    colegios = data.filter(item => item.nombreestablecimiento);
                } else {
                    colegios = [];
                    console.error("Respuesta inesperada de la API de colegios:", data);
                }
            })
            .catch(err => {
                colegios = [];
                console.error("Error al cargar colegios:", err);
            });
    }

    // Autocompletar colegio
    colegioInput.addEventListener("input", function () {
        const texto = this.value.toLowerCase();
        colegioLista.innerHTML = "";
        if (!texto) return;
        colegios.filter(col => col.nombreestablecimiento && col.nombreestablecimiento.toLowerCase().includes(texto)).slice(0, 10).forEach(col => {
            const item = document.createElement("button");
            item.type = "button";
            item.className = "list-group-item list-group-item-action";
            item.innerHTML = `<strong>${col.nombreestablecimiento}</strong><br><small style="color:#769a69;">${col.direccion || ''}</small>`;
            item.onclick = () => {
                colegioInput.value = col.nombreestablecimiento;
                direccionInput.value = col.direccion || "";
                colegioLista.innerHTML = "";
            };
            colegioLista.appendChild(item);
        });
    });
    colegioInput.addEventListener("blur", () => setTimeout(() => colegioLista.innerHTML = "", 200));

    // Cuando seleccionan un colegio, llena los campos
    document.getElementById('nombre').addEventListener('change', function(e) {
        const selectedOption = this.options[this.selectedIndex];
        let props = {};
        if (selectedOption.getAttribute('data-custom-properties')) {
            props = JSON.parse(selectedOption.getAttribute('data-custom-properties'));
        }
        document.getElementById('direccion').value = props.direccion || '';
        document.getElementById('telefono').value = props.telefono || '';
        document.getElementById('correo').value = props.correo || '';
        document.getElementById('codigo_dane').value = props.codigo || '';
        
        // Solo jornada MAÑANA y TARDE
        if (props.jornada) {
            const jornadasFiltradas = props.jornada
                .split(',')
                .map(j => j.trim().toUpperCase())
                .filter(j => ['MAÑANA', 'TARDE'].includes(j))
                .join(', ');
            document.getElementById('jornada').value = jornadasFiltradas;
        } else {
            document.getElementById('jornada').value = '';
        }
        
        // Solo grados 7 a 10
        if (props.grados) {
            const gradosFiltrados = props.grados
                .split(',')
                .map(g => g.trim())
                .filter(g => ['7','8','9','10'].includes(g))
                .join(', ');
            document.getElementById('grados').value = gradosFiltrados;
        } else {
            document.getElementById('grados').value = '';
        }
        
        document.getElementById('calendario').value = props.calendario || '';

        // Lógica para tipo de institución
        const publico = [
            "OFICIAL", "MUNICIPIO", "DEPARTAMENTO", "ESTABLECIMIENTO OFICIAL", "ENTIDAD TERRITORIAL"
        ];
        const privado = [
            "PERSONA NATURAL", "PERSONA JURÍDICA", "COMUNIDAD RELIGIOSA", "COOPERATIVA",
            "FUNDACIÓN", "ONG", "CORPORACIÓN", "ENTIDAD PRIVADA", "UNIVERSIDAD"
        ];
        let tipo = '';
        if (props.prestador) {
            if (publico.includes(props.prestador.trim().toUpperCase())) {
                tipo = 'Pública';
            } else if (privado.includes(props.prestador.trim().toUpperCase())) {
                tipo = 'Privada';
            }
        }
        document.getElementById('tipo_institucion').value = tipo;
    });
});
