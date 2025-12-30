<?php
require_once __DIR__ . '/../models/Aprendiz.php';
require_once __DIR__ . '/../models/Colegio.php';
require_once __DIR__ . '/../models/Ficha.php';
require_once __DIR__ . '/../config/db.php';
// Librería para leer Excel
use PhpOffice\PhpSpreadsheet\IOFactory;

class AprendizController {

    /* 📌 Mostrar formulario de creación (panel interno) */
    public function crear() {
        $colegioModel = new Colegio();
        $colegios = $colegioModel->obtenerTodos();
        // Lista de fichas para que el administrador pueda escoger
        $fichaModel = new Ficha();
        $fichas = $fichaModel->obtenerTodas();
        $esPendienteInterno = isset($_GET['pendiente']) && (string)$_GET['pendiente'] === '1';

        if ($esPendienteInterno) {
            require __DIR__ . '/../views/Aprendiz/crear_pendientes.php';
        } else {
            require __DIR__ . '/../views/Aprendiz/crear.php';
        }
    }

    /* 📄 Formulario público para registro de aprendices PENDIENTES (sin ficha) */
    public function formularioPendientePublico() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $es_publico = true;
        $modo_pendiente = true;
        $ficha_id = null;
        $ficha_llena = false;
        $form_action = '/?page=registro_aprendiz_pendiente';

        require __DIR__ . '/../views/Aprendiz/crear.php';
    }

    /* 💾 Guardar aprendiz público pendiente (sin ficha asociada) */
    public function guardarPendientePublico() {
        // Sesión para poder usar CSRF y flash, pero sin requerir login
        if (session_status() === PHP_SESSION_NONE) {
            start_secure_session();
        }
        // Para formularios públicos sin usuario autenticado no aplicamos CSRF estricto,
        // solo se valida si hay sesión de usuario (login) activa.
        if (!empty($_SESSION['usuario'])) {
            csrf_validate();
        }

        // Reutilizamos la lógica de acudientes y mapeo del registro público normal,
        // pero sin token ni ficha forzada.
        $acudientesPost = $_POST['acudientes'] ?? [];
        $familiares = [];
        if (is_array($acudientesPost)) {
            foreach ($acudientesPost as $acu) {
                $n = trim((string)($acu['nombres'] ?? ''));
                $a = trim((string)($acu['apellidos'] ?? ''));
                $doc = trim((string)($acu['numero_documento'] ?? ''));
                if ($n === '' && $a === '' && $doc === '') {
                    continue;
                }
                $familiares[] = $acu;
            }
        }
        if (count($familiares) === 0) {
            die('Debes agregar al menos un acudiente.');
        }
        if (count($familiares) > 2) {
            die('Solo puedes registrar máximo 2 familiares.');
        }
        $acudienteCount = 0;
        foreach ($familiares as $acu) {
            $esAcu = isset($acu['es_acudiente']) && (string)$acu['es_acudiente'] === '1';
            if ($esAcu) { $acudienteCount++; }
        }
        if ($acudienteCount === 0) {
            die('Debes marcar al menos un familiar como acudiente.');
        }
        $_POST['acudientes'] = $familiares;

        $ac0 = $_POST['acudientes'][0] ?? [];
        $nombreAcu = trim((($ac0['nombres'] ?? '') . ' ' . ($ac0['apellidos'] ?? '')));

        // Normalizar género: si eligen "Otro" y escriben algo, se guarda el texto libre
        $generoSel  = trim($_POST['genero'] ?? '');
        $generoOtro = trim($_POST['genero_otro'] ?? '');
        if ($generoSel === 'Otro' && $generoOtro !== '') {
            $generoFinal = $generoOtro;
        } else {
            $generoFinal = $generoSel;
        }

        $datos = [
            'nombres'            => trim($_POST['nombres'] ?? ''),
            'apellidos'          => trim($_POST['apellidos'] ?? ''),
            'tipo_documento'     => $_POST['tipo_documento'] ?? '',
            'numero_documento'   => trim($_POST['numero_documento'] ?? ''),
            'correo_electronico' => trim($_POST['correo_electronico'] ?? ''),
            'correo_institucional'=> trim($_POST['correo_institucional'] ?? ''),
            'telefono'           => trim($_POST['celular'] ?? ($_POST['telefono'] ?? '')),
            'municipio'          => trim($_POST['municipio'] ?? ''),
            'direccion'          => trim($_POST['direccion'] ?? ''),
            'barrio'             => trim($_POST['barrio'] ?? ''),
            'eps'                => trim($_POST['eps'] ?? ''),
            'estrato'            => trim($_POST['estrato'] ?? ''),
            'rh'                 => trim($_POST['rh'] ?? ''),
            'fecha_nacimiento'   => $_POST['fecha_nacimiento'] ?? '',
            'genero'             => $generoFinal,
            'colegio_id'         => $_POST['colegio_id'] ?? null,
            'grado'              => $_POST['grado'] ?? '',
            'grupo'              => trim($_POST['grupo'] ?? ''),
            'jornada'            => $_POST['jornada'] ?? '',
            'fecha_ingreso'      => date('Y-m-d'),
            'nombre_completo_acudiente'  => $nombreAcu,
            'tipo_documento_acudiente'   => $ac0['tipo_documento'] ?? ($_POST['tipo_documento_acudiente'] ?? ''),
            'numero_documento_acudiente' => trim($ac0['numero_documento'] ?? ($_POST['numero_documento_acudiente'] ?? '')),
            'telefono_acudiente'         => trim($ac0['celular'] ?? ($_POST['telefono_acudiente'] ?? '')),
            'parentesco'                 => trim(($ac0['parentesco'] ?? '') ?: ($_POST['parentesco'] ?? '')),
            'parentesco_otro'            => trim(($ac0['parentesco_otro'] ?? '') ?: ($_POST['parentesco_otro'] ?? '')),
            'ocupacion'                  => trim(($ac0['ocupacion'] ?? '') ?: ($_POST['ocupacion'] ?? '')),
            'ocupacion_otro'             => trim(($ac0['ocupacion_otro'] ?? '') ?: ($_POST['ocupacion_otro'] ?? '')),
            'ficha_id'                   => null,
            'acudientes'                 => $_POST['acudientes'] ?? [],
            'ficha_medica'               => $_POST['ficha_medica'] ?? [],
            'estado'                     => 'Pendiente',
        ];

        $aprendizModel = new Aprendiz();
        if ($aprendizModel->guardarPublico($datos)) {
            header('Location: /?page=registro_aprendiz_pendiente&success=1');
            exit;
        }

        echo "❌ Error al registrar estudiante pendiente.";
    }

    /* 📌 Guardar estudiante (panel interno) */
    public function guardar() {
        start_secure_session();
        require_login();
        require_role([1, 2]);
        csrf_validate();

        $acudientesPost = $_POST['acudientes'] ?? [];
        $familiares = [];
        if (is_array($acudientesPost)) {
            foreach ($acudientesPost as $idx => $acu) {
                $n = trim((string)($acu['nombres'] ?? ''));
                $a = trim((string)($acu['apellidos'] ?? ''));
                $doc = trim((string)($acu['numero_documento'] ?? ''));
                if ($n === '' && $a === '' && $doc === '') {
                    continue;
                }
                $familiares[] = $acu;
            }
        }
        if (count($familiares) === 0) {
            die('Debes agregar al menos un acudiente.');
        }
        if (count($familiares) > 2) {
            die('Solo puedes registrar máximo 2 familiares.');
        }
        $acudienteCount = 0;
        foreach ($familiares as $acu) {
            $esAcu = isset($acu['es_acudiente']) && (string)$acu['es_acudiente'] === '1';
            if ($esAcu) { $acudienteCount++; }
        }
        if ($acudienteCount === 0) {
            die('Debes marcar al menos un familiar como acudiente.');
        }
        $_POST['acudientes'] = $familiares;

        // Mapear campos del formulario a estructura esperada por el modelo
        // Nota: en la vista admin, los acudientes vienen como acudientes[0][...]
        $ac0 = $_POST['acudientes'][0] ?? [];
        $nombreAcu = trim((($ac0['nombres'] ?? '') . ' ' . ($ac0['apellidos'] ?? '')));

        $epsSeleccionada = trim($_POST['eps'] ?? '');
        $epsOtro = trim($_POST['eps_otro'] ?? '');
        if ($epsSeleccionada === 'Otro' && $epsOtro !== '') {
            $epsFinal = $epsOtro;
        } else {
            $epsFinal = $epsSeleccionada;
        }

        $sinFicha = isset($_POST['sin_ficha']) && (string)$_POST['sin_ficha'] === '1';

        // Normalizar género: permitir texto libre cuando seleccionan "Otro"
        $generoSel  = trim($_POST['genero'] ?? '');
        $generoOtro = trim($_POST['genero_otro'] ?? '');
        if ($generoSel === 'Otro' && $generoOtro !== '') {
            $generoFinal = $generoOtro;
        } else {
            $generoFinal = $generoSel;
        }

        $datos = [
            'nombres'                     => trim($_POST['nombres'] ?? ''),
            'apellidos'                   => trim($_POST['apellidos'] ?? ''),
            'tipo_documento'              => $_POST['tipo_documento'] ?? '',
            'numero_documento'            => trim($_POST['numero_documento'] ?? ''),
            'correo_electronico'          => trim($_POST['correo_electronico'] ?? ''),
            // La vista usa 'celular' para el estudiante
            'telefono'                    => trim($_POST['celular'] ?? ($_POST['telefono'] ?? '')),
            'correo_institucional'        => trim($_POST['correo_institucional'] ?? ''),
            'municipio'                   => trim($_POST['municipio'] ?? ''),
            'direccion'                   => trim($_POST['direccion'] ?? ''),
            'barrio'                      => trim($_POST['barrio'] ?? ''),
            'eps'                         => $epsFinal,
            'estrato'                     => trim($_POST['estrato'] ?? ''),
            'rh'                          => trim($_POST['rh'] ?? ''),
            'fecha_nacimiento'            => $_POST['fecha_nacimiento'] ?? '',
            'genero'                      => $generoFinal,
            'colegio_id'                  => $_POST['colegio_id'] ?? null,
            'grado'                       => $_POST['grado'] ?? '',
            'grupo'                       => trim($_POST['grupo'] ?? ''),
            'jornada'                     => $_POST['jornada'] ?? '',
            'fecha_ingreso'               => date('Y-m-d'),
            // Tomar el primer acudiente como acudiente principal para aprendices
            'nombre_completo_acudiente'   => $nombreAcu,
            'tipo_documento_acudiente'    => $ac0['tipo_documento'] ?? ($_POST['tipo_documento_acudiente'] ?? ''),
            'numero_documento_acudiente'  => trim($ac0['numero_documento'] ?? ($_POST['numero_documento_acudiente'] ?? '')),
            'telefono_acudiente'          => trim($ac0['celular'] ?? ($_POST['telefono_acudiente'] ?? '')),
            'parentesco'                  => trim(($ac0['parentesco'] ?? '') ?: ($_POST['parentesco'] ?? '')),
            'parentesco_otro'             => trim(($ac0['parentesco_otro'] ?? '') ?: ($_POST['parentesco_otro'] ?? '')),
            'ocupacion'                   => trim(($ac0['ocupacion'] ?? '') ?: ($_POST['ocupacion'] ?? '')),
            'ocupacion_otro'              => trim(($ac0['ocupacion_otro'] ?? '') ?: ($_POST['ocupacion_otro'] ?? '')),
            'ficha_id'                    => $sinFicha ? null : ($_POST['ficha_id'] ?? null),
            // Pasar el arreglo completo de acudientes para guardado en su tabla
            'acudientes'                  => $_POST['acudientes'] ?? [],
            // Información médica completa (Paso 3)
            'ficha_medica'                => $_POST['ficha_medica'] ?? [],
            // Meta de auditoría
            'creado_por'                  => $_SESSION['usuario']['id'] ?? null,
            'estado'                      => $sinFicha ? 'Pendiente' : 'Activo',
        ];

        $camposOpcionalesPaso1 = ['correo_institucional','municipio','direccion','barrio'];
        foreach ($camposOpcionalesPaso1 as $campo) {
            if (array_key_exists($campo, $datos) && $datos[$campo] === '') {
                $datos[$campo] = null;
            }
        }

        $camposObligatoriosPaso1 = [
            'nombres',
            'apellidos',
            'tipo_documento',
            'numero_documento',
            'fecha_nacimiento',
            'correo_electronico',
            'telefono',
            'rh',
            'genero',
            'eps',
            'estrato',
        ];
        foreach ($camposObligatoriosPaso1 as $campo) {
            if (empty($datos[$campo])) {
                die('Por favor completa todos los campos obligatorios del aprendiz.');
            }
        }

        // Validación de campos obligatorios del Paso 4 (datos escolares)
        $camposObligatoriosPaso4 = [
            'colegio_id',
            'grado',
            'jornada',
        ];
        foreach ($camposObligatoriosPaso4 as $campo) {
            if (empty($datos[$campo])) {
                die('Por favor completa todos los campos obligatorios de datos escolares.');
            }
        }

        if (!$sinFicha && empty($datos['ficha_id'])) {
            die('Debes seleccionar una ficha o marcar que el aprendiz quedará sin ficha (pendiente).');
        }

        // Validación básica de información médica (Paso 3)
        $fichaMedicaPost = $_POST['ficha_medica'] ?? [];
        $camposObligatoriosMedica = [
            'padece_enfermedad',
            'tiene_alergias',
            'medicamento_permanente',
            'discapacidad',
            'cursos_tecnoacademia',
        ];
        foreach ($camposObligatoriosMedica as $campoMed) {
            if (!isset($fichaMedicaPost[$campoMed]) || ($fichaMedicaPost[$campoMed] !== '0' && $fichaMedicaPost[$campoMed] !== '1')) {
                die('Por favor responde todas las preguntas obligatorias de información médica.');
            }
        }
        $datos['ficha_medica'] = $fichaMedicaPost;

        // Bloqueo por cupo (solo no administradores). Admin (rol 1) puede exceder.
        $rolActual = (int)($_SESSION['usuario']['rol_id'] ?? 0);
        if ($rolActual !== 1 && !empty($datos['ficha_id'])) {
            $fichaModelChk = new Ficha();
            $f = $fichaModelChk->obtenerPorId($datos['ficha_id']);
            if ($f && isset($f['cupo_total'], $f['cupo_usado']) && (int)$f['cupo_total'] > 0 && (int)$f['cupo_usado'] >= (int)$f['cupo_total']) {
                die('⚠️ Cupo de la ficha alcanzado. Contacte al administrador.');
            }
        }

        $aprendizModel = new Aprendiz();
        // Debug de payload enviado (revisar en error_log)
        error_log('ADMIN guardar aprendiz POST: ' . json_encode([
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'tipo_documento' => $datos['tipo_documento'],
            'numero_documento' => $datos['numero_documento'],
            'correo_electronico' => $datos['correo_electronico'],
            'telefono' => $datos['telefono'],
            'correo_institucional' => $datos['correo_institucional'],
            'municipio' => $datos['municipio'],
            'direccion' => $datos['direccion'],
            'barrio' => $datos['barrio'],
            'eps' => $datos['eps'],
            'estrato' => $datos['estrato'],
            'rh' => $datos['rh'],
            'fecha_nacimiento' => $datos['fecha_nacimiento'],
            'genero' => $datos['genero'],
            'colegio_id_POST' => $datos['colegio_id'],
            'grado' => $datos['grado'],
            'grupo' => $datos['grupo'],
            'jornada' => $datos['jornada'],
            'ficha_id' => $datos['ficha_id'],
        ]));

        if ($aprendizModel->guardar($datos)) {
            if (!empty($datos['ficha_id'])) {
                $fichaId = $datos['ficha_id'];
                header("Location: /?page=fichas&action=ver&id=" . urlencode($fichaId) . "&success=1");
            } else {
                header("Location: /?page=aprendices&success=1");
            }
            exit;
        }
        echo "❌ Error al registrar aprendiz.";
    }

    /* 📌 Guardar estudiante desde formulario público (usando token de la ficha) */
    public function guardarPublico() {
        // Sesión para poder usar CSRF y token de ficha, sin requerir login
        if (session_status() === PHP_SESSION_NONE) {
            start_secure_session();
        }
        // En modo público solo aplicamos CSRF si el usuario ya está autenticado en la plataforma.
        if (!empty($_SESSION['usuario'])) {
            csrf_validate();
        }

        // ✅ Tomar token desde la URL
        $token = $_GET['token'] ?? null;
        if (!$token) {
            die("⚠️ Token no válido.");
        }

        // ✅ Buscar ficha asociada (validar token y obtener ID de ficha)
        $fichaModel = new Ficha();
        $ficha = $fichaModel->buscarPorToken($token);

        if (!$ficha) {
            die("⚠️ Token inválido o vencido.");
        }

        // Bloqueo por cupo en formulario público (sin bypass de admin aquí)
        if (isset($ficha['cupo_total'], $ficha['cupo_usado']) && (int)$ficha['cupo_total'] > 0 && (int)$ficha['cupo_usado'] >= (int)$ficha['cupo_total']) {
            die('⚠️ Cupo de la ficha alcanzado. El registro público está cerrado.');
        }

        // ✅ Siempre se fuerza ficha_id con la ficha encontrada
        //    El colegio será el que seleccione el aprendiz en el formulario público (o se deriva de la ficha en el modelo)
        $acudientesPost = $_POST['acudientes'] ?? [];
        $familiares = [];
        if (is_array($acudientesPost)) {
            foreach ($acudientesPost as $idx => $acu) {
                $n = trim((string)($acu['nombres'] ?? ''));
                $a = trim((string)($acu['apellidos'] ?? ''));
                $doc = trim((string)($acu['numero_documento'] ?? ''));
                if ($n === '' && $a === '' && $doc === '') {
                    continue;
                }
                $familiares[] = $acu;
            }
        }
        if (count($familiares) === 0) {
            die('Debes agregar al menos un acudiente.');
        }
        if (count($familiares) > 2) {
            die('Solo puedes registrar máximo 2 familiares.');
        }
        $acudienteCount = 0;
        foreach ($familiares as $acu) {
            $esAcu = isset($acu['es_acudiente']) && (string)$acu['es_acudiente'] === '1';
            if ($esAcu) { $acudienteCount++; }
        }
        if ($acudienteCount === 0) {
            die('Debes marcar al menos un familiar como acudiente.');
        }
        $_POST['acudientes'] = $familiares;

        // Tomar primer acudiente si viene arreglo como en el panel interno
        $ac0 = $_POST['acudientes'][0] ?? [];
        $nombreAcu = trim((($ac0['nombres'] ?? '') . ' ' . ($ac0['apellidos'] ?? '')));

        // Normalizar género para formulario público: permitir texto libre cuando seleccionan "Otro"
        $generoSelPub  = trim($_POST['genero'] ?? '');
        $generoOtroPub = trim($_POST['genero_otro'] ?? '');
        if ($generoSelPub === 'Otro' && $generoOtroPub !== '') {
            $generoFinalPub = $generoOtroPub;
        } else {
            $generoFinalPub = $generoSelPub;
        }

        $datos = [
            'nombres'                     => trim($_POST['nombres'] ?? ''),
            'apellidos'                   => trim($_POST['apellidos'] ?? ''),
            'tipo_documento'              => $_POST['tipo_documento'] ?? '',
            'numero_documento'            => trim($_POST['numero_documento'] ?? ''),
            'correo_electronico'          => trim($_POST['correo_electronico'] ?? ''),
            'correo_institucional'        => trim($_POST['correo_institucional'] ?? ''),
            // Normalizar: usar 'celular' si existe, sino 'telefono'
            'telefono'                    => trim($_POST['celular'] ?? ($_POST['telefono'] ?? '')),
            'municipio'                   => trim($_POST['municipio'] ?? ''),
            'direccion'                   => trim($_POST['direccion'] ?? ''),
            'barrio'                      => trim($_POST['barrio'] ?? ''),
            'eps'                         => trim($_POST['eps'] ?? ''),
            'estrato'                     => trim($_POST['estrato'] ?? ''),
            'rh'                          => trim($_POST['rh'] ?? ''),
            'fecha_nacimiento'            => $_POST['fecha_nacimiento'] ?? '',
            'genero'                      => $generoFinalPub,
            'colegio_id'                  => $_POST['colegio_id'] ?? null,
            'grado'                       => $_POST['grado'] ?? '',
            'grupo'                       => trim($_POST['grupo'] ?? ''),
            'jornada'                     => $_POST['jornada'] ?? '',
            'fecha_ingreso'               => date('Y-m-d'),
            // Top-level acudiente (compatibilidad con tabla aprendices)
            'nombre_completo_acudiente'   => $nombreAcu ?: trim($_POST['nombre_completo_acudiente'] ?? ''),
            'tipo_documento_acudiente'    => $ac0['tipo_documento'] ?? ($_POST['tipo_documento_acudiente'] ?? ''),
            'numero_documento_acudiente'  => trim($ac0['numero_documento'] ?? ($_POST['numero_documento_acudiente'] ?? '')),
            'telefono_acudiente'          => trim($ac0['celular'] ?? ($_POST['telefono_acudiente'] ?? '')),
            'parentesco'                  => trim(($ac0['parentesco'] ?? '') ?: ($_POST['parentesco'] ?? '')),
            'parentesco_otro'             => trim(($ac0['parentesco_otro'] ?? '') ?: ($_POST['parentesco_otro'] ?? '')),
            'ocupacion'                   => trim(($ac0['ocupacion'] ?? '') ?: ($_POST['ocupacion'] ?? '')),
            'ocupacion_otro'              => trim(($ac0['ocupacion_otro'] ?? '') ?: ($_POST['ocupacion_otro'] ?? '')),
            'ficha_id'                    => $ficha['id'], // ✅ forzado desde el token
            // Arreglos completos (nuevos)
            'acudientes'                  => $_POST['acudientes'] ?? [],
            'ficha_medica'                => $_POST['ficha_medica'] ?? [],
        ];

        $aprendizModel = new Aprendiz();

        if ($aprendizModel->guardarPublico($datos)) {
            // Redirigir de vuelta al formulario con mensaje de éxito
            header("Location: /?page=registro_estudiante&token=" . urlencode($token) . "&success=1");
            exit;
        }

        echo "❌ Error al registrar estudiante desde formulario público.";
    }

    /* 📄 Mostrar formulario público de registro (solo formulario, sin encabezado/footer) */
    public function formularioPublico() {
        // Formulario accesible por link con token, sin requerir login
        if (session_status() === PHP_SESSION_NONE) {
            start_secure_session();
        }

        $token = $_GET['token'] ?? null;
        if (!$token) {
            die("⚠️ Token no válido.");
        }

        $fichaModel = new Ficha();
        $ficha = $fichaModel->buscarPorToken($token);
        if (!$ficha) {
            die("⚠️ Token inválido o vencido.");
        }

        $cupo_total = (int)($ficha['cupo_total'] ?? 0);
        $cupo_usado = (int)($ficha['cupo_usado'] ?? 0);
        $ficha_llena = ($cupo_total > 0 && $cupo_usado >= $cupo_total);

        // Variables para la vista crear.php en modo público
        $ficha_id = $ficha['id'] ?? null;
        $es_publico = true;
        $modo_pendiente = false; // desde link siempre va asociado a ficha
        $form_action = '/?page=registro_estudiante&token=' . urlencode($token);

        require __DIR__ . '/../views/Aprendiz/crear.php';
    }

    /* 📌 Listado de estudiantes */
    public function index() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $aprendizModel = new Aprendiz();
        $rol_id = $_SESSION['usuario']['rol_id'] ?? null;

        if ($rol_id == 1) { 
            $estudiantes = $aprendizModel->obtenerTodos(); 
        } else {
            $ficha_id = $_GET['ficha_id'] ?? null;
            if (!$ficha_id) {
                die('❌ Ficha no especificada.');
            }
            $estudiantes = $aprendizModel->obtenerTodos($ficha_id);
        }

        require_once __DIR__ . '/../views/Aprendiz/lista.php';
    }

    /* 📥 Formulario de importación desde Excel */
    public function importar() {
        start_secure_session();
        require_login();
        require_role([1,2]);

        $colegioModel = new Colegio();
        $fichaModel   = new Ficha();

        $colegios = $colegioModel->obtenerTodos();
        $fichas   = $fichaModel->obtenerTodas();

        // Preseleccionar si viene por GET (solo si NO es flujo de pendientes)
        $esPendiente = isset($_GET['pendiente']) && (string)$_GET['pendiente'] === '1';
        $ficha_id_pre  = $esPendiente ? '' : ($_GET['ficha_id'] ?? '');
        $colegio_pre   = $_GET['colegio_id'] ?? '';

        require __DIR__ . '/../views/Aprendiz/importar.php';
    }

    /* 📋 Listar aprendices pendientes para asignar a una ficha */
    public function pendientesFicha() {
        require_login();
        require_role([1,2]);

        $ficha_id = isset($_GET['ficha_id']) ? (int)$_GET['ficha_id'] : 0;
        if ($ficha_id <= 0) {
            die('Ficha no válida.');
        }

        $fichaModel = new Ficha();
        $ficha = $fichaModel->obtenerPorId($ficha_id);
        if (!$ficha) {
            die('Ficha no encontrada.');
        }

        $aprendizModel = new Aprendiz();
        $pendientes = $aprendizModel->obtenerPendientes();

        // Paginación simple en memoria (15 por página)
        $perPage = 15;
        $page    = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
        $total   = is_array($pendientes) ? count($pendientes) : 0;
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) { $page = $totalPages; }
        $offset = ($page - 1) * $perPage;
        $pendientesPagina = array_slice($pendientes, $offset, $perPage);

        require __DIR__ . '/../views/Aprendiz/pendientes_ficha.php';
    }

    /* 🔗 Asignar un aprendiz pendiente a una ficha específica */
    public function asignarPendiente() {
        start_secure_session();
        require_login();
        require_role([1,2]);

        $ficha_id   = isset($_GET['ficha_id']) ? (int)$_GET['ficha_id'] : 0;
        $usuario_id = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;

        if ($ficha_id <= 0 || $usuario_id <= 0) {
            die('Parámetros inválidos para asignar aprendiz.');
        }

        $aprendizModel = new Aprendiz();
        $ok = false;
        try {
            $ok = $aprendizModel->asignarPendienteAFicha($usuario_id, $ficha_id);
        } catch (\Throwable $e) {
            $ok = false;
        }

        if ($ok) {
            // Intentar incrementar el cupo usado de la ficha
            try {
                $fichaModel = new Ficha();
                $fichaModel->incrementarCupo($ficha_id);
            } catch (\Throwable $e) { /* noop */ }

            header('Location: /?page=fichas&action=ver&id=' . urlencode($ficha_id) . '&asignado=1');
        } else {
            header('Location: /?page=aprendices&action=pendientes_ficha&ficha_id=' . urlencode($ficha_id) . '&error=No+se+pudo+asignar+el+aprendiz');
        }
        exit;
    }

    /* 📥 Formulario de importación desde Excel → lista de PENDIENTES (sin ficha fija) */
    public function importarPendientes() {
        start_secure_session();
        require_login();
        require_role([1,2]);

        $colegioModel = new Colegio();
        $fichaModel   = new Ficha();

        $colegios = $colegioModel->obtenerTodos();
        $fichas   = $fichaModel->obtenerTodas();

        // En modo pendientes no se preselecciona ficha
        $ficha_id_pre = '';
        $colegio_pre  = $_GET['colegio_id'] ?? '';

        require __DIR__ . '/../views/Aprendiz/importar.php';
    }

    /* 📥 Procesar Excel y crear estudiantes en lote
       - Si viene ficha_id en POST: importa directamente a ESA ficha (Activo, con validación de cupo)
       - Si NO viene ficha_id: crea aprendices Pendientes (sin ficha asociada)
    */
    public function importarExcel() {
        start_secure_session();
        require_login();
        require_role([1,2]);
        csrf_validate();

        if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
            // Volver al formulario de importación con mensaje amigable
            $msg = 'Archivo no recibido. Verifica que hayas seleccionado un archivo válido.';
            $qs  = http_build_query(['error' => $msg]);
            if (!empty($_POST['ficha_id'])) {
                header('Location: /?page=aprendices&action=importar&ficha_id=' . urlencode((string)$_POST['ficha_id']) . '&' . $qs);
            } else {
                header('Location: /?page=aprendices&action=importar&' . $qs);
            }
            exit;
        }

        $ficha_id = $_POST['ficha_id'] ?? null;
        $tieneFichaFija = !empty($ficha_id);

        // Solo cuando se importa directo a una ficha se valida cupo
        $rolActual = (int)($_SESSION['usuario']['rol_id'] ?? 0);
        $isAdmin   = ($rolActual === 1);
        $disponibles = null;
        if ($tieneFichaFija) {
            $fichaModel = new Ficha();
            $ficha = $fichaModel->obtenerPorId($ficha_id);
            if (!$ficha) {
                $msg = 'Ficha no encontrada. Verifica que la ficha siga existiendo.';
                header('Location: /?page=aprendices&action=importar&ficha_id=' . urlencode((string)$ficha_id) . '&' . http_build_query(['error' => $msg]));
                exit;
            }
            $disponibles = max(0, (int)($ficha['cupo_total'] ?? 0) - (int)($ficha['cupo_usado'] ?? 0));
            if (!$isAdmin && $disponibles <= 0) {
                $msg = 'El cupo de la ficha está completo. No es posible importar más estudiantes.';
                header('Location: /?page=aprendices&action=importar&ficha_id=' . urlencode((string)$ficha_id) . '&' . http_build_query(['error' => $msg]));
                exit;
            }
        }

        $tmpPath = $_FILES['archivo_excel']['tmp_name'];

        // Helper para redirigir errores al formulario de importación con estilo
        $errorRedirect = function(string $mensaje) use ($tieneFichaFija, $ficha_id) {
            $mensaje = trim($mensaje);
            if ($mensaje === '') {
                $mensaje = 'Ocurrió un error al procesar el archivo de Excel.';
            }
            $qs = http_build_query(['error' => $mensaje]);
            if ($tieneFichaFija && $ficha_id) {
                header('Location: /?page=aprendices&action=importar&ficha_id=' . urlencode((string)$ficha_id) . '&' . $qs);
            } else {
                header('Location: /?page=aprendices&action=importar&' . $qs);
            }
            exit;
        };

        try {
            // Validaciones básicas
            $origName = $_FILES['archivo_excel']['name'] ?? '';
            $size     = (int)($_FILES['archivo_excel']['size'] ?? 0);
            if ($size <= 0) {
                throw new \RuntimeException('El archivo subido está vacío o no es legible.');
            }

            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            // Helper para detectar delimitador probable de CSV
            $sniffDelimiter = function(string $path): string {
                $sample = @file_get_contents($path, false, null, 0, 2048) ?: '';
                $counts = [',' => substr_count($sample, ','), ';' => substr_count($sample, ';'), "\t" => substr_count($sample, "\t")];
                arsort($counts);
                $top = key($counts);
                return $top ?: ',';
            };

            $createReaderByExt = function(string $ext) use ($sniffDelimiter) {
                switch ($ext) {
                    case 'xlsx': return IOFactory::createReader('Xlsx');
                    case 'xls':  return IOFactory::createReader('Xls');
                    case 'csv':
                        $r = IOFactory::createReader('Csv');
                        if (method_exists($r, 'setInputEncoding')) { $r->setInputEncoding('UTF-8'); }
                        // Delimitador se establecerá afuera con sniff
                        return $r;
                    default:
                        return null;
                }
            };

            $reader = $createReaderByExt($ext);
            $usedCsv = false;
            if ($reader === null) {
                // Intentar identificar por contenido
                try {
                    $inputType = IOFactory::identify($tmpPath);
                } catch (\Throwable $eId) {
                    $inputType = 'Csv';
                }
                $reader = IOFactory::createReader($inputType);
                $usedCsv = ($inputType === 'Csv');
            } else {
                $usedCsv = ($ext === 'csv');
            }

            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            if ($usedCsv) {
                $delim = $sniffDelimiter($tmpPath);
                if (method_exists($reader, 'setDelimiter')) { $reader->setDelimiter($delim); }
                if (method_exists($reader, 'setEnclosure')) { $reader->setEnclosure('"'); }
            }

            // Primer intento de carga
            try {
                $spreadsheet = $reader->load($tmpPath);
            } catch (\Throwable $eLoad) {
                // Fallback: si no es CSV, intentar como CSV
                if (!$usedCsv) {
                    $reader = IOFactory::createReader('Csv');
                    if (method_exists($reader, 'setInputEncoding')) { $reader->setInputEncoding('UTF-8'); }
                    $delim = $sniffDelimiter($tmpPath);
                    if (method_exists($reader, 'setDelimiter')) { $reader->setDelimiter($delim); }
                    if (method_exists($reader, 'setEnclosure')) { $reader->setEnclosure('"'); }
                    $spreadsheet = $reader->load($tmpPath);
                } else {
                    throw $eLoad;
                }
            }

            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
            
            // Obtener la primera fila como encabezados
            $headers = $rows[1]; // La primera fila (índice 1) contiene los encabezados
            
            // Mapeo de nombres de columna exactos como están en el Excel (con guiones bajos)
            $map = [
                'nombres' => 'nombres',
                'apellidos' => 'apellidos',
                'tipo_documento' => 'tipo_documento',
                'numero_documento' => 'numero_documento',
                'correo_electronico' => 'correo_electronico',
                'telefono' => 'telefono',
                'fecha_nacimiento' => 'fecha_nacimiento',
                'genero' => 'genero',
                'grado' => 'grado',
                'grupo' => 'grupo',
                'jornada' => 'jornada',
                'nombre_completo_acudiente' => 'nombre_completo_acudiente',
                'tipo_documento_acudiente' => 'tipo_documento_acudiente',
                'numero_documento_acudiente' => 'numero_documento_acudiente',
                'telefono_acudiente' => 'telefono_acudiente',
                'parentesco' => 'parentesco',
                'ocupacion' => 'ocupacion',
                'colegio' => 'colegio',
            ];

            // Invertir el mapeo para buscar por nombre de columna NORMALIZADO
            // Normalizamos: minúsculas, trim, removemos asterisco final y convertimos espacios a guiones bajos
            $columnMap = [];
            foreach ($headers as $col => $name) {
                $norm = strtolower(trim((string)$name));
                $norm = preg_replace('/\s*\*\s*$/', '', $norm); // quitar "*" al final
                $norm = str_replace(["\t", ' '], '_', $norm);
                $columnMap[$norm] = $col;
            }
            
            // Mostrar los encabezados encontrados para depuración
            echo "<div style='background:#f8f9fa;padding:10px;margin:10px 0;border:1px solid #ddd;'>";
            echo "<strong>Encabezados encontrados en el Excel:</strong><br>";
            foreach ($columnMap as $nombre => $col) {
                echo "- $nombre (columna $col)<br>";
            }
            echo "</div>";
            
            // Verificar que todas las columnas requeridas existen (contra nombres normalizados)
            $requeridos = [
                'nombres',
                'apellidos',
                'tipo_documento',
                'numero_documento',
                'genero',
                'grado',
                'jornada',
                'colegio',
            ];
            
            $errores = [];
            foreach ($requeridos as $columna) {
                $columnaLower = strtolower(trim($columna));
                if (!isset($columnMap[$columnaLower])) {
                    $errores[] = $columna;
                }
            }
            
            if (!empty($errores)) {
                $errorRedirect('Faltan columnas requeridas en el Excel: ' . implode(', ', $errores));
            }

            $aprendizModel = new Aprendiz();

            // Normalizador flexible de nombres de colegio (quita tildes, palabras genéricas, signos, etc.)
            $normalizeSchoolKey = function(string $texto): string {
                $texto = trim($texto);
                if ($texto === '') { return ''; }
                if (function_exists('mb_strtolower')) {
                    $texto = mb_strtolower($texto, 'UTF-8');
                } else {
                    $texto = strtolower($texto);
                }
                // Quitar tildes comunes
                $texto = strtr($texto, [
                    'á' => 'a','à' => 'a','ä' => 'a','â' => 'a','ã' => 'a',
                    'é' => 'e','è' => 'e','ë' => 'e','ê' => 'e',
                    'í' => 'i','ì' => 'i','ï' => 'i','î' => 'i',
                    'ó' => 'o','ò' => 'o','ö' => 'o','ô' => 'o','õ' => 'o',
                    'ú' => 'u','ù' => 'u','ü' => 'u','û' => 'u',
                    'ñ' => 'n',
                ]);
                // Dejar solo letras, números y espacios
                $texto = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $texto) ?? '';
                $texto = preg_replace('/\s+/', ' ', $texto) ?? '';
                // Eliminar palabras muy genéricas
                $eliminar = [
                    'institucion educativa','institucion','educativa','colegio',
                    'i e','ie','i.e','i.e.','instituto','tecnoacademia',
                    'sede','campus'
                ];
                foreach ($eliminar as $pal) {
                    $pat = '/\b' . preg_quote($pal, '/') . '\b/u';
                    $texto = preg_replace($pat, ' ', $texto) ?? '';
                }
                $texto = preg_replace('/\s+/', ' ', $texto) ?? '';
                return trim($texto);
            };

            // Índice de colegios: cada ID con una lista de claves normalizadas (nombre, código DANE)
            $colegioModel = new Colegio();
            $colegiosTodos = $colegioModel->obtenerTodos();
            $colegiosIndex = [];
            foreach ($colegiosTodos as $col) {
                $idCol = (int)($col['id'] ?? 0);
                if ($idCol <= 0) { continue; }
                $keys = [];
                $keys[] = $normalizeSchoolKey((string)($col['nombre'] ?? ''));
                $keys[] = $normalizeSchoolKey((string)($col['codigo_dane'] ?? ''));
                $keys = array_values(array_filter(array_unique($keys), function($v){ return $v !== ''; }));
                if (!empty($keys)) {
                    $colegiosIndex[$idCol] = $keys;
                }
            }

            // Control de cupos restantes solo cuando se importa a ficha fija
            $restantes = ($tieneFichaFija && !$isAdmin) ? (int)$disponibles : PHP_INT_MAX;

            $creados = 0; $saltados = 0; $duplicados = 0; $errores = [];

            $totalRows = count($rows);
            for ($i = 2; $i <= $totalRows; $i++) { // desde fila 2
                $row = $rows[$i];
                if (!is_array($row)) { continue; }

                // Omitir filas completamente vacías (todas las celdas sin texto)
                $tieneContenido = false;
                foreach ($row as $cell) {
                    if ($cell !== null && trim((string)$cell) !== '') { $tieneContenido = true; break; }
                }
                if (!$tieneContenido) { continue; }

                // Resolver colegio_id por fila a partir de la columna 'colegio'
                $colegioTexto = '';
                if (isset($columnMap['colegio'])) {
                    $colegioTexto = trim((string)($row[$columnMap['colegio']] ?? ''));
                }
                $colegioNorm = $normalizeSchoolKey($colegioTexto);
                $colegioFilaId = null;
                if ($colegioNorm !== '') {
                    // Búsqueda flexible: coincidencia exacta o parcial con alguna de las claves del colegio
                    foreach ($colegiosIndex as $idCol => $keys) {
                        foreach ($keys as $k) {
                            if ($k === '') { continue; }
                            if ($colegioNorm === $k || strpos($colegioNorm, $k) !== false || strpos($k, $colegioNorm) !== false) {
                                $colegioFilaId = $idCol;
                                break 2;
                            }
                        }
                    }
                }

                $datos = [
                    'nombres' => trim((string)($row[$columnMap['nombres']] ?? '')),
                    'apellidos' => trim((string)($row[$columnMap['apellidos']] ?? '')),
                    'tipo_documento' => trim((string)($row[$columnMap['tipo_documento']] ?? '')),
                    'numero_documento' => trim((string)($row[$columnMap['numero_documento']] ?? '')),
                    'correo_electronico' => isset($columnMap['correo_electronico']) ? trim((string)($row[$columnMap['correo_electronico']] ?? '')) : '',
                    'correo_institucional' => isset($columnMap['correo_institucional']) ? trim((string)($row[$columnMap['correo_institucional']] ?? '')) : '',
                    'telefono' => isset($columnMap['telefono']) ? trim((string)($row[$columnMap['telefono']] ?? '')) : '',
                    'fecha_nacimiento' => trim((string)($row[$columnMap['fecha_nacimiento']] ?? '')),
                    'genero' => trim((string)($row[$columnMap['genero']] ?? '')),
                    'rh' => isset($columnMap['rh']) ? trim((string)($row[$columnMap['rh']] ?? '')) : null,
                    'municipio' => isset($columnMap['municipio']) ? trim((string)($row[$columnMap['municipio']] ?? '')) : null,
                    'direccion' => isset($columnMap['direccion']) ? trim((string)($row[$columnMap['direccion']] ?? '')) : null,
                    'barrio' => isset($columnMap['barrio']) ? trim((string)($row[$columnMap['barrio']] ?? '')) : null,
                    'eps' => isset($columnMap['eps']) ? trim((string)($row[$columnMap['eps']] ?? '')) : null,
                    'estrato' => isset($columnMap['estrato']) ? trim((string)($row[$columnMap['estrato']] ?? '')) : null,
                    'colegio_id' => $colegioFilaId,
                    'grado' => trim((string)($row[$columnMap['grado']] ?? '')),
                    'grupo' => trim((string)($row[$columnMap['grupo']] ?? '')),
                    'jornada' => trim((string)($row[$columnMap['jornada']] ?? '')),
                    'fecha_ingreso' => date('Y-m-d'),
                    'nombre_completo_acudiente' => trim((string)($row[$columnMap['nombre_completo_acudiente']] ?? '')),
                    'tipo_documento_acudiente' => trim((string)($row[$columnMap['tipo_documento_acudiente']] ?? '')),
                    'numero_documento_acudiente' => trim((string)($row[$columnMap['numero_documento_acudiente']] ?? '')),
                    'telefono_acudiente' => trim((string)($row[$columnMap['telefono_acudiente']] ?? '')),
                    'parentesco' => trim((string)($row[$columnMap['parentesco']] ?? '')),
                    'ocupacion' => trim((string)($row[$columnMap['ocupacion']] ?? '')),
                    'ficha_id' => $tieneFichaFija ? $ficha_id : null,
                    // Meta de auditoría y estado inicial
                    'creado_por' => $_SESSION['usuario']['id'] ?? null,
                    'estado'     => $tieneFichaFija ? 'Activo' : 'Pendiente',
                ];

                // Soportar columnas *_otro desde Excel (género_otro, eps_otro, parentesco_otro, ocupacion_otro)
                $generoOtroExcel = '';
                if (isset($columnMap['genero_otro'])) {
                    $generoOtroExcel = trim((string)($row[$columnMap['genero_otro']] ?? ''));
                }

                $epsOtroExcel = '';
                if (isset($columnMap['eps_otro'])) {
                    $epsOtroExcel = trim((string)($row[$columnMap['eps_otro']] ?? ''));
                }

                $parentescoOtroExcel = '';
                if (isset($columnMap['parentesco_otro'])) {
                    $parentescoOtroExcel = trim((string)($row[$columnMap['parentesco_otro']] ?? ''));
                }

                $ocupacionOtroExcel = '';
                if (isset($columnMap['ocupacion_otro'])) {
                    $ocupacionOtroExcel = trim((string)($row[$columnMap['ocupacion_otro']] ?? ''));
                }

                // Aplicar lógica "Otro" igual que en el formulario normal
                if ($epsOtroExcel !== '') {
                    // Si en el Excel diligencian eps_otro, se usa como valor final de EPS
                    $datos['eps'] = $epsOtroExcel;
                }

                if ($generoOtroExcel !== '') {
                    // Si viene un género libre en genero_otro, se respeta como género final
                    $datos['genero'] = $generoOtroExcel;
                }

                $acudientes = [];
                $nombreAcu      = trim((string)($datos['nombre_completo_acudiente'] ?? ''));
                $docAcu         = trim((string)($datos['numero_documento_acudiente'] ?? ''));
                $telAcu         = trim((string)($datos['telefono_acudiente'] ?? ''));
                $parentescoAcu  = trim((string)($datos['parentesco'] ?? ''));
                $ocupacionAcu   = trim((string)($datos['ocupacion'] ?? ''));
                if ($nombreAcu !== '' || $docAcu !== '' || $telAcu !== '') {
                    $acudientes[] = [
                        'nombres'          => $nombreAcu,
                        'apellidos'        => '',
                        'tipo_documento'   => ($datos['tipo_documento_acudiente'] ?? '') !== ''
                            ? $datos['tipo_documento_acudiente']
                            : 'CC',
                        'numero_documento' => $docAcu,
                        'celular'          => $telAcu,
                        'parentesco'       => $parentescoAcu,
                        'parentesco_otro'  => $parentescoOtroExcel !== '' ? $parentescoOtroExcel : null,
                        'ocupacion'        => $ocupacionAcu,
                        'ocupacion_otro'   => $ocupacionOtroExcel !== '' ? $ocupacionOtroExcel : null,
                        'es_acudiente'     => '1',
                    ];
                }
                if (!empty($acudientes)) {
                    $datos['acudientes'] = $acudientes;
                    if (empty($datos['celular_acudiente'] ?? '')) {
                        $datos['celular_acudiente'] = $telAcu;
                    }
                }

                $fichaMedica = [];
                $camposFM = [
                    'padece_enfermedad',
                    'enfermedad_detalle',
                    'tiene_alergias',
                    'alergias_detalle',
                    'medicamento_permanente',
                    'medicamento_detalle',
                    'discapacidad',
                    'discapacidad_detalle',
                    'cursos_tecnoacademia',
                    'cursos_tecnoacademia_detalle',
                ];
                foreach ($camposFM as $campoFM) {
                    if (isset($columnMap[$campoFM])) {
                        $fichaMedica[$campoFM] = trim((string)($row[$columnMap[$campoFM]] ?? ''));
                    }
                }
                if (empty($fichaMedica)) {
                    $fichaMedica = [
                        'padece_enfermedad'            => '0',
                        'enfermedad_detalle'           => '',
                        'tiene_alergias'               => '0',
                        'alergias_detalle'             => '',
                        'medicamento_permanente'       => '0',
                        'medicamento_detalle'          => '',
                        'discapacidad'                 => '0',
                        'discapacidad_detalle'         => '',
                        'cursos_tecnoacademia'         => '0',
                        'cursos_tecnoacademia_detalle' => '',
                    ];
                } else {
                    $baseFM = [
                        'padece_enfermedad'            => '0',
                        'enfermedad_detalle'           => '',
                        'tiene_alergias'               => '0',
                        'alergias_detalle'             => '',
                        'medicamento_permanente'       => '0',
                        'medicamento_detalle'          => '',
                        'discapacidad'                 => '0',
                        'discapacidad_detalle'         => '',
                        'cursos_tecnoacademia'         => '0',
                        'cursos_tecnoacademia_detalle' => '',
                    ];
                    $fichaMedica = array_merge($baseFM, $fichaMedica);
                }
                $datos['ficha_medica'] = $fichaMedica;

                // Normalizar género para cumplir el constraint chk_genero_otro_fk.
                // Solo permitimos valores compatibles con los formularios: 'M', 'F' o 'Prefiero no decirlo'.
                $gRaw = strtoupper(trim((string)$datos['genero']));
                if (in_array($gRaw, ['M', 'MASCULINO', 'HOMBRE', 'H'], true)) {
                    $datos['genero'] = 'M';
                } elseif (in_array($gRaw, ['F', 'FEMENINO', 'MUJER'], true)) {
                    $datos['genero'] = 'F';
                } elseif ($gRaw === '' || in_array($gRaw, ['P', 'PREFIERO NO DECIRLO', 'PREFIERO_NO_DECIRLO', 'NO ESPECIFICA', 'NO-ESPECIFICA', 'NO_ESPECIFICA'], true)) {
                    $datos['genero'] = 'Prefiero no decirlo';
                } else {
                    // Cualquier otro valor (incluyendo 'OTRO', 'NB', 'NO BINARIO', etc.)
                    $datos['genero'] = 'Prefiero no decirlo';
                }

                // Validación simple por fila
                if ($datos['nombres'] === '' || $datos['apellidos'] === '' || $datos['numero_documento'] === '') {
                    $saltados++; $errores[] = "Fila $i: Faltan campos obligatorios"; continue;
                }

                if (!$colegioFilaId) {
                    $saltados++; $errores[] = "Fila $i: Colegio no encontrado o vacío (columna 'colegio')."; continue;
                }

                // Duplicados por número de documento
                if ($aprendizModel->existeDocumento($datos['numero_documento'])) {
                    $duplicados++; $errores[] = "Fila $i: Documento ya registrado (" . $datos['numero_documento'] . ")"; continue;
                }

                try {
                    // Si importamos a ficha fija y no es admin, respetar cupos disponibles
                    if ($tieneFichaFija && !$isAdmin && $restantes <= 0) {
                        $saltados++; $errores[] = "Fila $i: Sin cupo disponible en la ficha."; continue;
                    }

                    // Reutilizamos la lógica pública para generar password.
                    $ok = $aprendizModel->guardarPublico($datos);
                    if ($ok) {
                        $creados++;
                        if ($tieneFichaFija && !$isAdmin) { $restantes--; }
                    } else {
                        $saltados++;
                    }
                } catch (\Throwable $e) {
                    $raw = (string)$e->getMessage();
                    $msgAmigable = $raw;
                    $lower = strtolower($raw);

                    // Duplicados por restricciones únicas (correo, documento, etc.)
                    $esDuplicado = (strpos($lower, 'duplicate entry') !== false || strpos($lower, 'sqlstate[23000]') !== false);
                    if ($esDuplicado) {
                        // Intentar detectar el campo implicado para un mensaje más claro
                        if (strpos($lower, "correo_electronico") !== false || strpos($lower, "email") !== false) {
                            $msgAmigable = 'Correo electrónico ya registrado. Verifica que no tengas correos repetidos en el archivo.';
                        } elseif (strpos($lower, "numero_documento") !== false || strpos($lower, "documento") !== false) {
                            $msgAmigable = 'Documento ya registrado. Revisa que el número de documento no esté repetido.';
                        } else {
                            $msgAmigable = 'Datos duplicados: ya existe un registro con esta información (por ejemplo correo o documento).';
                        }
                        $duplicados++;
                        $errores[] = "Fila $i: " . $msgAmigable;
                        continue;
                    }

                    // Errores típicos de fecha (por ejemplo, serial de Excel como 39791)
                    if (strpos($lower, 'invalid datetime format') !== false || strpos($lower, 'incorrect date value') !== false) {
                        $msgAmigable = 'La fecha de nacimiento tiene un formato no válido. Usa el formato dd/mm/aaaa o asegúrate de que la celda sea una fecha real de Excel.';
                    } elseif (strpos($raw, 'Colegio no válido para el aprendiz') !== false) {
                        $msgAmigable = 'El colegio no es válido o no coincide con ninguno registrado. Revisa la columna "colegio" en el archivo.';
                    } elseif (strpos($raw, 'Ficha no válida') !== false || strpos($raw, 'sin colegio asociado') !== false) {
                        $msgAmigable = 'La ficha asociada no es válida o no tiene colegio configurado. Si estás importando pendientes, no asocies ficha.';
                    }

                    $errores[] = "Fila $i: " . $msgAmigable;
                    $saltados++;
                }
            }

            // Redirigir con resumen de importación
            $_SESSION['import_errores'] = $errores; // almacenar errores para mostrar una vez
            $msg = http_build_query([
                'import_ok' => 1,
                'creados' => $creados,
                'saltados' => $saltados,
                'duplicados' => $duplicados,
            ]);

            if ($tieneFichaFija) {
                // Si se importó a una ficha concreta, volver a la ficha
                header('Location: /?page=fichas&action=ver&id=' . urlencode($ficha_id) . '&' . $msg);
            } else {
                // Sin ficha fija: para admin, ir a la lista general; para facilitador, volver a la pantalla importar_pendientes
                if ($rolActual === 1) {
                    header('Location: /?page=aprendices&' . $msg);
                } else {
                    header('Location: /?page=aprendices&action=importar_pendientes&' . $msg);
                }
            }
            exit;

        } catch (\Throwable $e) {
            error_log('[AprendizController::importarExcel] ' . $e->getMessage());
            $errorRedirect('Error al procesar el archivo de Excel. Verifica que tenga el formato correcto.');
        }
    }

    /* ✏️ Editar datos básicos de un estudiante */
    public function editar() {
        $usuario_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $ficha_id   = isset($_GET['ficha_id']) ? (int)$_GET['ficha_id'] : null;

        if ($usuario_id <= 0) {
            die('ID de estudiante no válido.');
        }

        $aprendizModel = new Aprendiz();
        $estudiante = $aprendizModel->obtenerBasicoPorUsuarioId($usuario_id);

        if (!$estudiante) {
            die('No se encontró el estudiante.');
        }

        // Pasar ficha_id actual para poder volver a la ficha luego de actualizar
        $ficha_actual_id = $ficha_id ?: ($estudiante['ficha_id'] ?? null);

        require __DIR__ . '/../views/Aprendiz/editar.php';
    }

    /* 💾 Actualizar datos básicos de un estudiante */
    public function actualizar() {
        start_secure_session();
        require_login();
        require_role([1, 2]);
        csrf_validate();

        $usuario_id = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : 0;
        $ficha_id   = isset($_POST['ficha_id']) ? (int)$_POST['ficha_id'] : null;

        if ($usuario_id <= 0) {
            die('ID de estudiante no válido.');
        }

        $datos = [
            'nombres'           => trim($_POST['nombres'] ?? ''),
            'apellidos'         => trim($_POST['apellidos'] ?? ''),
            'tipo_documento'    => $_POST['tipo_documento'] ?? '',
            'numero_documento'  => trim($_POST['numero_documento'] ?? ''),
            'correo_electronico'=> trim($_POST['correo_electronico'] ?? ''),
            'telefono'          => trim($_POST['telefono'] ?? ''),
            'grado'             => trim($_POST['grado'] ?? ''),
            'grupo'             => trim($_POST['grupo'] ?? ''),
            'jornada'           => trim($_POST['jornada'] ?? ''),
        ];

        foreach (['nombres','apellidos','tipo_documento','numero_documento'] as $campo) {
            if ($datos[$campo] === '') {
                die('Por favor completa todos los campos obligatorios.');
            }
        }

        $aprendizModel = new Aprendiz();
        try {
            $aprendizModel->actualizarBasicoPorUsuarioId($usuario_id, $datos);
        } catch (\Throwable $e) {
            die('Error al actualizar estudiante: ' . $e->getMessage());
        }

        if ($ficha_id) {
            header('Location: /?page=fichas&action=ver&id=' . urlencode($ficha_id));
        } else {
            header('Location: /?page=aprendices');
        }
        exit;
    }

    /* 📴 Eliminar lógico = suspender aprendiz */
    public function eliminar() {
        require_login();
        require_role([1, 2]);

        $usuario_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $ficha_id   = isset($_GET['ficha_id']) ? (int)$_GET['ficha_id'] : null;

        if ($usuario_id <= 0) {
            die('ID de estudiante no válido.');
        }

        $aprendizModel = new Aprendiz();
        $aprendizModel->suspenderPorUsuarioId($usuario_id);

        // Notificar a administradores sobre la suspensión
        try {
            $pdo = Database::conectar();

            // Datos básicos del aprendiz y su ficha
            $info = null;
            try {
                $stmtInfo = $pdo->prepare("SELECT u.nombres, u.apellidos, u.numero_documento, a.ficha_id, COALESCE(f.numero, f.id) AS ficha_numero, f.nombre AS ficha_nombre
                                             FROM aprendices a
                                             JOIN usuarios u ON a.usuario_id = u.id
                                             LEFT JOIN fichas f ON f.id = a.ficha_id
                                             WHERE a.usuario_id = ?
                                             LIMIT 1");
                $stmtInfo->execute([$usuario_id]);
                $info = $stmtInfo->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (\PDOException $eInfo) { /* noop */ }

            $nombreApr = $info ? trim(($info['nombres'] ?? '') . ' ' . ($info['apellidos'] ?? '')) : ('ID ' . $usuario_id);
            $docApr    = $info['numero_documento'] ?? '';
            $fichaTxt  = '';
            if ($info && !empty($info['ficha_numero'])) {
                $fichaTxt = $info['ficha_numero'] . (empty($info['ficha_nombre']) ? '' : ' - ' . $info['ficha_nombre']);
            }

            $tituloNoti = 'Aprendiz suspendido';
            $msgNoti = 'Se suspendió el aprendiz ' . htmlspecialchars($nombreApr) . ($docApr ? ' (' . htmlspecialchars($docApr) . ')' : '');
            if ($fichaTxt !== '') {
                $msgNoti .= ' en la ficha ' . htmlspecialchars($fichaTxt) . '.';
            } else {
                $msgNoti .= '.';
            }

            // Buscar administradores (rol_id = 1)
            $destinatarios = [];
            try {
                $stmtDest = $pdo->query("SELECT id FROM usuarios WHERE rol_id = 1");
                $destinatarios = $stmtDest->fetchAll(PDO::FETCH_COLUMN) ?: [];
            } catch (\PDOException $eDest) { /* noop */ }

            foreach ($destinatarios as $uid) {
                $uid = (int)$uid;
                try {
                    $stN = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, leido, creado_en) VALUES (?, ?, ?, 0, NOW())");
                    $stN->execute([$uid, $tituloNoti, $msgNoti]);
                } catch (\PDOException $eN1) {
                    if ($eN1->getCode() !== '42S22') { throw $eN1; }
                    try {
                        $stN = $pdo->prepare("INSERT INTO notificaciones (usuario, titulo, mensaje, leido, creado_en) VALUES (?, ?, ?, 0, NOW())");
                        $stN->execute([$uid, $tituloNoti, $msgNoti]);
                    } catch (\PDOException $eN2) {
                        if ($eN2->getCode() !== '42S22') { throw $eN2; }
                        try {
                            $stN = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, estado, creado_en) VALUES (?, ?, ?, 'no_leida', NOW())");
                            $stN->execute([$uid, $tituloNoti, $msgNoti]);
                        } catch (\PDOException $eN3) {
                            if ($eN3->getCode() !== '42S22') { throw $eN3; }
                            $stN = $pdo->prepare("INSERT INTO notificaciones (usuario, titulo, mensaje, estado, creado_en) VALUES (?, ?, ?, 'no_leida', NOW())");
                            $stN->execute([$uid, $tituloNoti, $msgNoti]);
                        }
                    }
                }
            }
        } catch (\Throwable $eNoti) {
            // No romper flujo si falla la notificación
        }

        if ($ficha_id) {
            header('Location: /?page=fichas&action=ver&id=' . urlencode($ficha_id));
        } else {
            header('Location: /?page=aprendices');
        }
        exit;
    }

    public function activar() {
        require_login();
        require_role([1, 2]);

        $usuario_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $ficha_id   = isset($_GET['ficha_id']) ? (int)$_GET['ficha_id'] : null;

        if ($usuario_id <= 0) {
            die('ID de estudiante no válido.');
        }

        $aprendizModel = new Aprendiz();
        $aprendizModel->activarPorUsuarioId($usuario_id);

        if ($ficha_id) {
            header('Location: /?page=fichas&action=ver&id=' . urlencode($ficha_id));
        } else {
            header('Location: /?page=aprendices');
        }
        exit;
    }

    /* 📌 Contar estudiantes */
    public function contar() {
        $aprendizModel = new Aprendiz();
        $totalEstudiante = $aprendizModel->contarAprendices();
        require 'views/dashboard.php'; 
    }

    /* 📌 API → Estudiantes por colegio */
    public function obtenerPorColegio($colegioId) {
        $aprendizModel = new Aprendiz();
        $estudiantes = $aprendizModel->obtenerPorColegio($colegioId);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($estudiantes, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* 📌 API → Estudiantes por ficha */
    public function obtenerPorFicha($ficha_id) {
        $aprendizModel = new Aprendiz();
        $estudiantes = $aprendizModel->obtenerTodos($ficha_id);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($estudiantes, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
