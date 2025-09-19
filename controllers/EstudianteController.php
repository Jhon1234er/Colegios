<?php
require_once __DIR__ . '/../models/Estudiante.php';
require_once __DIR__ . '/../models/Colegio.php';
require_once __DIR__ . '/../models/Ficha.php';
// Librería para leer Excel
use PhpOffice\PhpSpreadsheet\IOFactory;

class EstudianteController {

    /* 📌 Mostrar formulario de creación (panel interno) */
    public function crear() {
        $colegioModel = new Colegio();
        $colegios = $colegioModel->obtenerTodos();
        require __DIR__ . '/../views/Estudiante/crear.php';
    }

    /* 📌 Guardar estudiante (panel interno) */
    public function guardar() {
        start_secure_session();
        require_login();
        require_role([1, 2]);
        csrf_validate();

        $datos = [
            'nombres'                     => trim($_POST['nombres'] ?? ''),
            'apellidos'                   => trim($_POST['apellidos'] ?? ''),
            'tipo_documento'              => $_POST['tipo_documento'] ?? '',
            'numero_documento'            => trim($_POST['numero_documento'] ?? ''),
            'correo_electronico'          => trim($_POST['correo_electronico'] ?? ''),
            'telefono'                    => trim($_POST['telefono'] ?? ''),
            'fecha_nacimiento'            => $_POST['fecha_nacimiento'] ?? '',
            'genero'                      => $_POST['genero'] ?? '',
            'colegio_id'                  => $_POST['colegio_id'] ?? null,
            'grado'                       => $_POST['grado'] ?? '',
            'grupo'                       => trim($_POST['grupo'] ?? ''),
            'jornada'                     => $_POST['jornada'] ?? '',
            'fecha_ingreso'               => date('Y-m-d'),
            'nombre_completo_acudiente'   => trim($_POST['nombre_completo_acudiente'] ?? ''),
            'tipo_documento_acudiente'    => $_POST['tipo_documento_acudiente'] ?? '',
            'numero_documento_acudiente'  => trim($_POST['numero_documento_acudiente'] ?? ''),
            'telefono_acudiente'          => trim($_POST['telefono_acudiente'] ?? ''),
            'parentesco'                  => trim($_POST['parentesco'] ?? ''),
            'ocupacion'                   => trim($_POST['ocupacion'] ?? ''),
            'ficha_id'                    => $_POST['ficha_id'] ?? null,
        ];

        $estudianteModel = new Estudiante();

        if ($estudianteModel->guardar($datos)) {
            $fichaId = $datos['ficha_id'];
            header("Location: /?page=fichas&action=ver&id=" . urlencode($fichaId) . "&success=1");
            exit;
        }
        echo "❌ Error al registrar estudiante.";
    }

    /* 📌 Guardar estudiante desde formulario público (usando token de la ficha) */
    public function guardarPublico() {
        // Iniciar sesión para CSRF sin requerir login
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        csrf_validate();

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

        // ✅ Siempre se fuerza ficha_id con la ficha encontrada
        //    El colegio será el que seleccione el aprendiz en el formulario público
        $datos = [
            'nombres'                     => trim($_POST['nombres'] ?? ''),
            'apellidos'                   => trim($_POST['apellidos'] ?? ''),
            'tipo_documento'              => $_POST['tipo_documento'] ?? '',
            'numero_documento'            => trim($_POST['numero_documento'] ?? ''),
            'correo_electronico'          => trim($_POST['correo_electronico'] ?? ''),
            'telefono'                    => trim($_POST['telefono'] ?? ''),
            'fecha_nacimiento'            => $_POST['fecha_nacimiento'] ?? '',
            'genero'                      => $_POST['genero'] ?? '',
            'colegio_id'                  => $_POST['colegio_id'] ?? null,
            'grado'                       => $_POST['grado'] ?? '',
            'grupo'                       => trim($_POST['grupo'] ?? ''),
            'jornada'                     => $_POST['jornada'] ?? '',
            'fecha_ingreso'               => date('Y-m-d'),
            'nombre_completo_acudiente'   => trim($_POST['nombre_completo_acudiente'] ?? ''),
            'tipo_documento_acudiente'    => $_POST['tipo_documento_acudiente'] ?? '',
            'numero_documento_acudiente'  => trim($_POST['numero_documento_acudiente'] ?? ''),
            'telefono_acudiente'          => trim($_POST['telefono_acudiente'] ?? ''),
            'parentesco'                  => trim($_POST['parentesco'] ?? ''),
            'ocupacion'                   => trim($_POST['ocupacion'] ?? ''),
            'ficha_id'                    => $ficha['id'], // ✅ forzado desde el token
        ];

        $estudianteModel = new Estudiante();

        if ($estudianteModel->guardarPublico($datos)) {
            // Redirigir de vuelta al formulario con mensaje de éxito
            header("Location: /?page=registro_estudiante&token=" . urlencode($token) . "&success=1");
            exit;
        }

        echo "❌ Error al registrar estudiante desde formulario público.";
    }

    /* 📌 Listado de estudiantes */
    public function index() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $estudianteModel = new Estudiante();
        $rol_id = $_SESSION['usuario']['rol_id'] ?? null;

        if ($rol_id == 1) { 
            $estudiantes = $estudianteModel->obtenerTodos(); 
        } else {
            $ficha_id = $_GET['ficha_id'] ?? null;
            if (!$ficha_id) {
                die('❌ Ficha no especificada.');
            }
            $estudiantes = $estudianteModel->obtenerTodos($ficha_id);
        }

        require_once __DIR__ . '/../views/Estudiante/lista.php';
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

        // Preseleccionar si viene por GET
        $ficha_id_pre  = $_GET['ficha_id'] ?? '';
        $colegio_pre   = $_GET['colegio_id'] ?? '';

        require __DIR__ . '/../views/Estudiante/importar.php';
    }

    /* 📥 Procesar Excel y crear estudiantes en lote */
    public function importarExcel() {
        start_secure_session();
        require_login();
        require_role([1,2]);
        csrf_validate();

        if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
            die('❌ Archivo no recibido.');
        }

        $ficha_id   = $_POST['ficha_id'] ?? null;
        $colegio_id = $_POST['colegio_id'] ?? null;

        if (!$ficha_id || !$colegio_id) {
            die('⚠️ Debe seleccionar ficha y colegio para la importación.');
        }

        $tmpPath = $_FILES['archivo_excel']['tmp_name'];
        try {
            $spreadsheet = IOFactory::load($tmpPath);
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
            ];

            // Invertir el mapeo para buscar por nombre de columna (todo en minúsculas)
            $columnMap = [];
            foreach ($headers as $col => $name) {
                $columnMap[strtolower(trim($name))] = $col;
            }
            
            // Mostrar los encabezados encontrados para depuración
            echo "<div style='background:#f8f9fa;padding:10px;margin:10px 0;border:1px solid #ddd;'>";
            echo "<strong>Encabezados encontrados en el Excel:</strong><br>";
            foreach ($columnMap as $nombre => $col) {
                echo "- $nombre (columna $col)<br>";
            }
            echo "</div>";
            
            // Verificar que todas las columnas requeridas existen
            $requeridos = [
                'nombres',
                'apellidos',
                'tipo_documento',
                'numero_documento',
                'genero',
                'grado',
                'jornada'
            ];
            
            $errores = [];
            foreach ($requeridos as $columna) {
                $columnaLower = strtolower(trim($columna));
                if (!isset($columnMap[$columnaLower])) {
                    $errores[] = $columna;
                }
            }
            
            if (!empty($errores)) {
                die('⚠️ Faltan columnas requeridas en el Excel: ' . implode(', ', $errores));
            }

            // Validar mínimos
            $requeridos = ['nombres','apellidos','tipo_documento','numero_documento','genero','grado','jornada'];
            foreach ($requeridos as $rk) {
                if (empty($map[$rk])) {
                    die('⚠️ Falta columna requerida en el Excel: ' . $rk);
                }
            }

            $estudianteModel = new Estudiante();

            $creados = 0; $saltados = 0; $duplicados = 0; $errores = [];

            $totalRows = count($rows);
            for ($i = 2; $i <= $totalRows; $i++) { // desde fila 2
                $row = $rows[$i];
                if (!is_array($row)) { continue; }

                $datos = [
                    'nombres' => trim((string)($row[$columnMap['nombres']] ?? '')),
                    'apellidos' => trim((string)($row[$columnMap['apellidos']] ?? '')),
                    'tipo_documento' => trim((string)($row[$columnMap['tipo_documento']] ?? '')),
                    'numero_documento' => trim((string)($row[$columnMap['numero_documento']] ?? '')),
                    'correo_electronico' => '', // No veo columna de correo en el Excel
                    'telefono' => '', // No veo columna de teléfono en el Excel
                    'fecha_nacimiento' => trim((string)($row[$columnMap['fecha_nacimiento']] ?? '')),
                    'genero' => trim((string)($row[$columnMap['genero']] ?? '')),
                    'colegio_id' => $colegio_id,
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
                    'ficha_id' => $ficha_id,
                ];

                // Validación simple por fila
                if ($datos['nombres'] === '' || $datos['apellidos'] === '' || $datos['numero_documento'] === '') {
                    $saltados++; $errores[] = "Fila $i: Faltan campos obligatorios"; continue;
                }

                // Duplicados por número de documento
                if ($estudianteModel->existeDocumento($datos['numero_documento'])) {
                    $duplicados++; $errores[] = "Fila $i: Documento ya registrado (" . $datos['numero_documento'] . ")"; continue;
                }

                try {
                    // Reutilizamos la lógica pública para generar password y actualizar cupos
                    $ok = $estudianteModel->guardarPublico($datos);
                    if ($ok) { $creados++; } else { $saltados++; }
                } catch (\Throwable $e) {
                    $errores[] = "Fila $i: " . $e->getMessage();
                    $saltados++;
                }
            }

            // Redirigir a la ficha con resumen
            $_SESSION['import_errores'] = $errores; // almacenar errores para mostrar una vez
            $msg = http_build_query([
                'import_ok' => 1,
                'creados' => $creados,
                'saltados' => $saltados,
                'duplicados' => $duplicados,
            ]);
            header('Location: /?page=fichas&action=ver&id=' . urlencode($ficha_id) . '&' . $msg);
            exit;

        } catch (\Throwable $e) {
            die('❌ Error al procesar Excel: ' . $e->getMessage());
        }
    }

    /* 📌 Contar estudiantes */
    public function contar() {
        $estudianteModel = new Estudiante();
        $totalEstudiante = $estudianteModel->contarEstudiantes();
        require 'views/dashboard.php'; 
    }

    /* 📌 API → Estudiantes por colegio */
    public function obtenerPorColegio($colegioId) {
        $estudianteModel = new Estudiante();
        $estudiantes = $estudianteModel->obtenerPorColegio($colegioId);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($estudiantes, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* 📌 API → Estudiantes por ficha */
    public function obtenerPorFicha($ficha_id) {
        $estudianteModel = new Estudiante();
        $estudiantes = $estudianteModel->obtenerTodos($ficha_id);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($estudiantes, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
