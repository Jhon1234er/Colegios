document.addEventListener("DOMContentLoaded", function () {

    // Inicialización de Choices.js
    const departamentoChoices = new Choices('#departamento', {
        searchEnabled: true,
        itemSelectText: '',
        placeholderValue: 'Selecciona un departamento'
    });
    const municipioChoices = new Choices('#municipio', {
        searchEnabled: true,
        itemSelectText: '',
        placeholderValue: 'Selecciona un municipio'
    });
    const colegioChoices = new Choices('#nombre', {
        searchEnabled: true,
        itemSelectText: '',
        placeholderValue: 'Selecciona un colegio'
    });
    const tipoInstitucionChoices = new Choices('#tipo_institucion', {
        searchEnabled: false,
        itemSelectText: '',
        placeholderValue: 'Seleccione tipo de institución'
    });

    const API_URL = "https://www.datos.gov.co/resource/upkm-vdjb.json";
    const TOKEN = "DzkbPRExtJtt2F38YHxxZHU2k";

    const departamentoInput = document.getElementById("departamento");
    const municipioInput = document.getElementById("municipio");
    const colegioInput = document.getElementById("nombre");
    const direccionInput = document.getElementById("direccion");

    let departamentos = [];
    let municipios = [];
    let colegios = [];

    // Cargar departamentos
    fetch(`${API_URL}?$select=nombredepartamento&$limit=50000`, { headers: { "X-App-Token": TOKEN } })
        .then(res => res.json())
        .then(data => {
            departamentos = Array.from(new Set(data.map(item => item.nombredepartamento))).filter(Boolean).sort();
            departamentoChoices.setChoices(
                departamentos.map(dep => ({ value: dep, label: dep })),
                'value', 'label', false
            );
        });

    // Al seleccionar departamento, cargar municipios
    departamentoInput.addEventListener('change', function() {
        const dep = this.value;
        municipioChoices.clearChoices();
        colegioChoices.clearChoices();
        fetch(`${API_URL}?nombredepartamento=${encodeURIComponent(dep)}&$select=nombremunicipio&$limit=50000`, { headers: { "X-App-Token": TOKEN } })
            .then(res => res.json())
            .then(data => {
                municipios = Array.from(new Set(data.map(item => item.nombremunicipio))).filter(Boolean).sort();
                municipioChoices.setChoices(
                    municipios.map(mun => ({ value: mun, label: mun })),
                    'value', 'label', false
                );
            });
    });

    // Al seleccionar municipio, cargar colegios
    municipioInput.addEventListener('change', function() {
        const dep = departamentoInput.value;
        const mun = this.value;
        colegioChoices.clearChoices();
        fetch(`${API_URL}?nombredepartamento=${encodeURIComponent(dep)}&nombremunicipio=${encodeURIComponent(mun)}&$select=nombreestablecimiento,direccion,telefono,correo_electronico,codigoestablecimiento,jornada,grados,calendario,prestador_de_servicio&$limit=50000`, { headers: { "X-App-Token": TOKEN } })
            .then(res => res.json())
            .then(data => {
                colegios = data.filter(item => item.nombreestablecimiento);
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
        if (!texto) return;
        const lista = departamentos.filter(dep => dep.toLowerCase().includes(texto)).slice(0, 10);
        departamentoChoices.clearChoices();
        departamentoChoices.setChoices(lista.map(dep => ({ value: dep, label: dep })), 'value', 'label', false);
    });

    // Autocompletar municipio
    municipioInput.addEventListener("input", function () {
        const texto = this.value.toLowerCase();
        if (!texto) return;
        const lista = municipios.filter(mun => mun && mun.toLowerCase().includes(texto)).slice(0, 10);
        municipioChoices.clearChoices();
        municipioChoices.setChoices(lista.map(mun => ({ value: mun, label: mun })), 'value', 'label', false);
    });

    // Autocompletar colegio
    colegioInput.addEventListener("input", function () {
        const texto = this.value.toLowerCase();
        if (!texto) return;
        const lista = colegios.filter(col => col.nombreestablecimiento && col.nombreestablecimiento.toLowerCase().includes(texto)).slice(0, 10);
        colegioChoices.clearChoices();
        colegioChoices.setChoices(
            lista.map(col => ({
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

    // Rellenar campos al seleccionar colegio
    colegioInput.addEventListener('change', function(e) {
        const selectedOption = this.options[this.selectedIndex];
        let props = {};
        if (selectedOption && selectedOption.dataset.customProperties) {
            props = JSON.parse(selectedOption.dataset.customProperties);
        }

        direccionInput.value = props.direccion || '';
        document.getElementById('telefono').value = props.telefono || '';
        document.getElementById('correo').value = props.correo || '';
        document.getElementById('codigo_dane').value = props.codigo || '';

        // Jornada MAÑANA/TARDE
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

        // Grados 7-10
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

        // Tipo de institución
        const publico = ["OFICIAL", "MUNICIPIO", "DEPARTAMENTO", "ESTABLECIMIENTO OFICIAL", "ENTIDAD TERRITORIAL"];
        const privado = ["PERSONA NATURAL", "PERSONA JURÍDICA", "COMUNIDAD RELIGIOSA", "COOPERATIVA",
                         "FUNDACIÓN", "ONG", "CORPORACIÓN", "ENTIDAD PRIVADA", "UNIVERSIDAD"];
        let tipo = '';
        if (props.prestador) {
            const prestadorUpper = props.prestador.trim().toUpperCase();
            if (publico.includes(prestadorUpper)) tipo = 'Pública';
            else if (privado.includes(prestadorUpper)) tipo = 'Privada';
        }
        tipoInstitucionChoices.setChoiceByValue(tipo);
    });

});
