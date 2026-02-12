-- =====================================================
-- BASE DE DATOS: colegios (MySQL/MariaDB)
-- SISTEMA ESCOLAR SENA - VERSIÓN COMPLETA
-- =====================================================

-- Crear base de datos si no existe
CREATE DATABASE IF NOT EXISTS sistema_escolar 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE sistema_escolar;

-- =====================================================
-- TABLAS PRINCIPALES (orden de creación)
-- =====================================================

-- 1. Tabla de roles del sistema
CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL UNIQUE
);

-- Insertar roles básicos
INSERT INTO roles (id, nombre) VALUES 
(1, 'admin'),
(2, 'profesor'),
(3, 'estudiante'),
(4, 'rector')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);

-- 2. Tabla de usuarios (tabla central del sistema)
CREATE TABLE IF NOT EXISTS usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rol_id INT NOT NULL,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    tipo_documento VARCHAR(20) NOT NULL,
    numero_documento VARCHAR(20) NOT NULL UNIQUE,
    correo_electronico VARCHAR(150) UNIQUE,
    correo_institucional VARCHAR(150),
    telefono VARCHAR(20),
    municipio VARCHAR(100),
    direccion VARCHAR(200),
    barrio VARCHAR(100),
    eps VARCHAR(100),
    eps_otro VARCHAR(100),
    estrato VARCHAR(10),
    rh VARCHAR(5),
    fecha_nacimiento DATE,
    genero VARCHAR(20),
    genero_otro VARCHAR(50),
    password_hash VARCHAR(255) NOT NULL,
    estado_id TINYINT DEFAULT 1 COMMENT '1=Activo, 0=Inactivo',
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_numero_documento (numero_documento),
    INDEX idx_correo_electronico (correo_electronico),
    INDEX idx_rol_id (rol_id),
    
    FOREIGN KEY (rol_id) REFERENCES roles(id)
);

-- 3. Tabla de colegios (instituciones educativas)
CREATE TABLE IF NOT EXISTS colegios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(200) NOT NULL,
    codigo_dane VARCHAR(20) UNIQUE,
    nit VARCHAR(20) UNIQUE,
    tipo_institucion VARCHAR(100),
    direccion VARCHAR(200),
    telefono VARCHAR(20),
    correo VARCHAR(150),
    municipio VARCHAR(100),
    departamento VARCHAR(100),
    jornada TEXT COMMENT 'JSON o CSV con jornadas',
    grados TEXT COMMENT 'JSON o CSV con grados',
    calendario TEXT COMMENT 'JSON con calendario académico',
    estado_id TINYINT DEFAULT 1 COMMENT '1=Activo, 0=Inactivo',
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_nombre (nombre),
    INDEX idx_municipio (municipio),
    INDEX idx_codigo_dane (codigo_dane)
);

-- 4. Tabla de cursos (áreas de conocimiento)
CREATE TABLE IF NOT EXISTS cursos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(50) UNIQUE,
    denominacion VARCHAR(200),
    nombre VARCHAR(200) NOT NULL,
    duracion INT COMMENT 'duración en horas',
    version INT,
    linea_tecnoacademia VARCHAR(100),
    estado_id TINYINT DEFAULT 1 COMMENT '1=Activo, 0=Inactivo',
    descripcion TEXT,
    
    INDEX idx_nombre (nombre),
    INDEX idx_codigo (codigo),
    INDEX idx_linea_tecnoacademia (linea_tecnoacademia)
);


-- 5. Tabla de facilitadores (profesores)
CREATE TABLE IF NOT EXISTS facilitadores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario INT NOT NULL COMMENT 'usuarios.id (legacy)',
    usuario_id INT NOT NULL COMMENT 'usuarios.id (actual)',
    titulo_academico VARCHAR(200),
    especialidad VARCHAR(200),
    fecha_ingreso DATE,
    tipo_contrato VARCHAR(50) COMMENT 'contratista, planta, etc',
    colegio_id INT COMMENT 'colegio principal (opcional)',
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_usuario (usuario),
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_colegio_id (colegio_id),
    
    FOREIGN KEY (usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- 6. Tabla de administradores
CREATE TABLE IF NOT EXISTS administradores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- 6.1. Tabla de asistentes
CREATE TABLE IF NOT EXISTS asistentes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    area VARCHAR(200),
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- 7. Tabla de fichas (grupos/cursos)
CREATE TABLE IF NOT EXISTS fichas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero VARCHAR(50) UNIQUE,
    nombre VARCHAR(200) NOT NULL,
    jornada_id INT COMMENT 'ID de jornada (opcional)',
    jornada VARCHAR(50) COMMENT 'jornada textual',
    cupo_total INT NOT NULL DEFAULT 0,
    cupo_usado INT NOT NULL DEFAULT 0,
    token VARCHAR(64) UNIQUE COMMENT 'token para registro público',
    estado_id TINYINT DEFAULT 1 COMMENT '1=Activa, 2=Suspendida, 3=Finalizada, 4=Archivada',
    dias_semana TEXT COMMENT 'JSON con días y horarios',
    facilitador_id INT COMMENT 'facilitador líder',
    colegio_id INT COMMENT 'colegio asignado',
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_numero (numero),
    INDEX idx_nombre (nombre),
    INDEX idx_token (token),
    INDEX idx_estado_id (estado_id),
    INDEX idx_facilitador_id (facilitador_id),
    INDEX idx_colegio_id (colegio_id),
    
    FOREIGN KEY (facilitador_id) REFERENCES facilitadores(id) ON DELETE SET NULL
    FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE SET NULL

);

-- 8. Tabla de aprendices (estudiantes)
CREATE TABLE IF NOT EXISTS aprendices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL COMMENT 'usuarios.id',
    colegio_id INT NOT NULL,
    ficha_id INT COMMENT 'ficha asignada',
    ficha INT COMMENT 'ficha asignada (legacy)',
    grado VARCHAR(20),
    grupo VARCHAR(20),
    jornada VARCHAR(50),
    fecha_ingreso DATE,
    estado_id TINYINT DEFAULT 1 COMMENT '1=Activo, 2=Retirado, 3=Egresado',
    creado_por INT COMMENT 'usuario que creó el registro',
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_colegio_id (colegio_id),
    INDEX idx_ficha_id (ficha_id),
    INDEX idx_ficha (ficha),
    INDEX idx_estado_id (estado_id),
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE RESTRICT,
    FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE SET NULL
);

-- =====================================================
-- TABLAS DE RELACIÓN (muchos a muchos)
-- =====================================================

-- 9. Relación colegio-curso
CREATE TABLE IF NOT EXISTS colegio_curso (
    colegio_id INT NOT NULL,
    curso_id INT NOT NULL,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (colegio_id, curso_id),
    FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
);


-- 10. Relación facilitador-ficha (nuevo esquema)
CREATE TABLE IF NOT EXISTS facilitador_ficha (
    facilitador_id INT NOT NULL,
    ficha_id INT NOT NULL,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (facilitador_id, ficha_id),
    FOREIGN KEY (facilitador_id) REFERENCES facilitadores(id) ON DELETE CASCADE,
    FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE CASCADE
);

-- 11. Relación ficha-colegio (nuevo esquema)
CREATE TABLE IF NOT EXISTS ficha_colegio (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ficha_id INT NOT NULL,
    colegio_id INT NOT NULL,
    cantidad_estudiantes INT DEFAULT 0,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_ficha_id (ficha_id),
    INDEX idx_colegio_id (colegio_id),
    
    FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE CASCADE,
    FOREIGN KEY (colegio_id) REFERENCES colegios(id) ON DELETE CASCADE
);

-- 12. Fichas compartidas entre profesores
CREATE TABLE IF NOT EXISTS fichas_compartidas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ficha_id INT NOT NULL,
    profesor_lider_id INT NOT NULL COMMENT 'usuario que comparte',
    profesor_compartido_id INT NOT NULL COMMENT 'usuario que recibe',
    estado_id TINYINT DEFAULT 1 COMMENT '1=Pendiente, 2=Aceptada, 3=Rechazada',
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_ficha_id (ficha_id),
    INDEX idx_profesor_lider (profesor_lider_id),
    INDEX idx_profesor_compartido (profesor_compartido_id),
    INDEX idx_estado_id (estado_id),
    
    FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE CASCADE,
    FOREIGN KEY (profesor_lider_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (profesor_compartido_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- =====================================================
-- TABLAS DE FUNCIONALIDADES ESPECÍFICAS
-- =====================================================

-- 13. Horarios de fichas (calendario)
CREATE TABLE IF NOT EXISTS horarios_fichas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ficha_id INT NOT NULL,
    facilitador_id INT NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,
    titulo VARCHAR(200),
    aula VARCHAR(50),
    color VARCHAR(20) DEFAULT '#007bff',
    estado_id TINYINT DEFAULT 1 COMMENT '1=Activo, 0=Inactivo',
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_ficha_id (ficha_id),
    INDEX idx_facilitador_id (facilitador_id),
    INDEX idx_fecha_inicio (fecha_inicio),
    INDEX idx_estado_id (estado_id),
    
    FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE CASCADE,
    FOREIGN KEY (facilitador_id) REFERENCES facilitadores(id) ON DELETE CASCADE
);

-- 14. Asistencias
CREATE TABLE IF NOT EXISTS asistencias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ficha_id INT NOT NULL,
    estudiante_id INT NOT NULL COMMENT 'usuarios.id',
    facilitador_id INT,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_dia DATE GENERATED ALWAYS AS (CAST(fecha AS DATE)) STORED,
    estado_asistencia_id TINYINT DEFAULT 1 COMMENT '1=Presente, 2=No asistió, 3=Justificado',
    observaciones TEXT,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_asistencia (ficha_id, estudiante_id, fecha_dia),
    INDEX idx_ficha_id (ficha_id),
    INDEX idx_estudiante_id (estudiante_id),
    INDEX idx_fecha_dia (fecha_dia),
    INDEX idx_estado_asistencia_id (estado_asistencia_id),
    
    FOREIGN KEY (ficha_id) REFERENCES fichas(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (facilitador_id) REFERENCES facilitadores(id) ON DELETE SET NULL
);

-- 15. Familiares/acudientes (nuevo esquema)
CREATE TABLE IF NOT EXISTS familiares (
    id INT PRIMARY KEY AUTO_INCREMENT,
    aprendiz_id INT NOT NULL COMMENT 'aprendices.id',
    nombre_completo VARCHAR(200) NOT NULL,
    tipo_documento VARCHAR(20) DEFAULT 'CC',
    numero_documento VARCHAR(20),
    telefono VARCHAR(20),
    parentesco VARCHAR(50),
    parentesco_otro VARCHAR(50),
    ocupacion VARCHAR(100),
    ocupacion_otro VARCHAR(100),
    es_acudiente TINYINT DEFAULT 0 COMMENT '1=es acudiente principal',
    contacto_emergencia TINYINT DEFAULT 0 COMMENT '1=contacto de emergencia',
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_aprendiz_id (aprendiz_id),
    INDEX idx_numero_documento (numero_documento),
    INDEX idx_es_acudiente (es_acudiente),
    
    FOREIGN KEY (aprendiz_id) REFERENCES aprendices(id) ON DELETE CASCADE
);

-- 16. Información médica
CREATE TABLE IF NOT EXISTS informacion_medica (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL COMMENT 'usuarios.id',
    padece_enfermedad TINYINT DEFAULT 0,
    enfermedad_detalle TEXT,
    alergias TINYINT DEFAULT 0,
    alergias_detalle TEXT,
    medicamentos_permanentes TINYINT DEFAULT 0,
    medicamentos_detalle TEXT,
    discapacidad TINYINT DEFAULT 0,
    discapacidad_detalle TEXT,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_usuario_id (usuario_id),
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- 17. Cursos de tecnoacademia
CREATE TABLE IF NOT EXISTS cursos_tecnoacademia (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL COMMENT 'usuarios.id',
    hizo_cursos TINYINT DEFAULT 0,
    detalle TEXT,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_usuario_id (usuario_id),
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- 18. Notificaciones
CREATE TABLE IF NOT EXISTS notificaciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL COMMENT 'usuarios.id',
    titulo VARCHAR(200) NOT NULL,
    mensaje TEXT NOT NULL,
    tipo VARCHAR(50) DEFAULT 'info',
    datos JSON COMMENT 'datos adicionales en formato JSON',
    estado_id TINYINT DEFAULT 1 COMMENT '1=No leída, 2=Leída',
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_usuario_id (usuario_id),
    INDEX idx_estado_id (estado_id),
    INDEX idx_tipo (tipo),
    INDEX idx_creado_en (creado_en),

    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- =====================================================
-- VISTAS ÚTILES (para reportes)
-- =====================================================

-- Vista de detalles completos de estudiantes
CREATE OR REPLACE VIEW vw_estudiantes_completo AS
SELECT 
    u.id AS usuario_id,
    u.nombres,
    u.apellidos,
    CONCAT(u.nombres, ' ', u.apellidos) AS nombre_completo,
    u.tipo_documento,
    u.numero_documento,
    u.correo_electronico,
    u.telefono,
    u.fecha_nacimiento,
    u.genero,

    a.id AS aprendiz_id,
    a.grado,
    a.grupo,
    a.jornada,
    a.fecha_ingreso,
    a.estado_id AS estado_estudiante_id,

    c.id AS colegio_id,
    c.nombre AS colegio_nombre,

    f.id AS ficha_id,
    f.nombre AS ficha_nombre,
    f.numero AS ficha_numero

FROM usuarios u
INNER JOIN aprendices a ON u.id = a.usuario_id
LEFT JOIN colegios c ON a.colegio_id = c.id
LEFT JOIN fichas f ON a.ficha_id = f.id;
CREATE OR REPLACE VIEW vw_asistencia_ficha AS
SELECT 
    f.id AS ficha_id,
    f.nombre AS ficha_nombre,
    f.numero AS ficha_numero,

    COUNT(a.id) AS total_registros,

    SUM(CASE WHEN a.estado_asistencia_id = 1 THEN 1 ELSE 0 END) AS presentes,
    SUM(CASE WHEN a.estado_asistencia_id = 2 THEN 1 ELSE 0 END) AS ausentes,
    SUM(CASE WHEN a.estado_asistencia_id = 3 THEN 1 ELSE 0 END) AS justificados,

    ROUND(
        (SUM(CASE WHEN a.estado_asistencia_id = 1 THEN 1 ELSE 0 END) * 100.0) 
        / NULLIF(COUNT(a.id), 0),
        2
    ) AS porcentaje_asistencia

FROM fichas f
LEFT JOIN asistencias a ON f.id = a.ficha_id
GROUP BY f.id, f.nombre, f.numero;

-- =====================================================
-- TRIGGERS (mantenimiento automático)
-- =====================================================
DELIMITER //

CREATE TRIGGER tr_aprendiz_insert_sumar_cupo
AFTER INSERT ON aprendices
FOR EACH ROW
BEGIN
    IF NEW.ficha_id IS NOT NULL THEN
        UPDATE fichas
        SET cupo_usado = cupo_usado + 1
        WHERE id = NEW.ficha_id;
    END IF;
END//

DELIMITER ;

DELIMITER //

CREATE TRIGGER tr_aprendiz_delete_restar_cupo
AFTER DELETE ON aprendices
FOR EACH ROW
BEGIN
    IF OLD.ficha_id IS NOT NULL THEN
        UPDATE fichas
        SET cupo_usado = GREATEST(0, cupo_usado - 1)
        WHERE id = OLD.ficha_id;
    END IF;
END//

DELIMITER ;


DELIMITER //

CREATE TRIGGER tr_aprendiz_update_cambio_ficha
AFTER UPDATE ON aprendices
FOR EACH ROW
BEGIN
    IF NOT (OLD.ficha_id <=> NEW.ficha_id) THEN

        -- Restar cupo a la ficha anterior
        IF OLD.ficha_id IS NOT NULL THEN
            UPDATE fichas
            SET cupo_usado = GREATEST(0, cupo_usado - 1)
            WHERE id = OLD.ficha_id;
        END IF;

        -- Sumar cupo a la nueva ficha
        IF NEW.ficha_id IS NOT NULL THEN
            UPDATE fichas
            SET cupo_usado = cupo_usado + 1
            WHERE id = NEW.ficha_id;
        END IF;

    END IF;
END//

DELIMITER ;


DELIMITER //

CREATE TRIGGER tr_aprendiz_before_insert_validar_cupo
BEFORE INSERT ON aprendices
FOR EACH ROW
BEGIN
    DECLARE cupo_actual INT;
    DECLARE cupo_maximo INT;

    IF NEW.ficha_id IS NOT NULL THEN
        SELECT cupo_usado, cupo_total
        INTO cupo_actual, cupo_maximo
        FROM fichas
        WHERE id = NEW.ficha_id;

        IF cupo_actual >= cupo_maximo THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La ficha no tiene cupos disponibles';
        END IF;
    END IF;
END//

DELIMITER ;

DELIMITER //

CREATE TRIGGER tr_aprendiz_before_update_validar_cupo
BEFORE UPDATE ON aprendices
FOR EACH ROW
BEGIN
    DECLARE cupo_actual INT;
    DECLARE cupo_maximo INT;

    IF NOT (OLD.ficha_id <=> NEW.ficha_id)
       AND NEW.ficha_id IS NOT NULL THEN

        SELECT cupo_usado, cupo_total
        INTO cupo_actual, cupo_maximo
        FROM fichas
        WHERE id = NEW.ficha_id;

        IF cupo_actual >= cupo_maximo THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No se puede asignar: la ficha está llena';
        END IF;
    END IF;
END//

DELIMITER ;

-- =====================================================
-- DATOS INICIALES (SEED COMPLETO Y CONSISTENTE)
-- =====================================================

-- 1. Colegio (SIEMPRE primero por FK)
INSERT IGNORE INTO colegios
(id, nombre, codigo_dane, nit, municipio, departamento, estado_id)
VALUES
(1, 'SENA - Centro de Gestión Industrial', '123001001', '800123456', 'Bogotá', 'Cundinamarca', 1);

-- -----------------------------------------------------

-- 2. Usuarios base del sistema - Contraseñas = "password"
INSERT IGNORE INTO usuarios (
    id, rol_id, nombres, apellidos, tipo_documento, numero_documento,
    correo_electronico, password_hash, estado_id
) VALUES
(
    1, 1, 'Administrador', 'Sistema', 'CC', '12345678',
    'admin@sena.edu.co',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    1
),
(
    2, 2, 'Juan', 'Pérez', 'CC', '87654321',
    'jperez@sena.edu.co',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    1
),
(
    3, 3, 'María', 'González', 'CC', '1098765432',
    'mgonzalez@estudiante.sena.edu.co',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    1
);

-- -----------------------------------------------------

-- 3. Facilitador (profesor)
INSERT IGNORE INTO facilitadores
(id, usuario_id, especialidad, fecha_ingreso, colegio_id, tipo_contrato)
VALUES
(1, 2, 'Desarrollo de Software', '2024-01-15', 1, 'Facilitador');

-- 3.1. Relación facilitador-ficha (para que aparezca en el colegio)
INSERT IGNORE INTO facilitador_ficha
(facilitador_id, ficha_id)
VALUES
(1, 1);

-- 3.2. Ficha de prueba (necesaria para la relación)
INSERT IGNORE INTO fichas
(id, nombre, numero, facilitador_id, colegio_id, estado_id)
VALUES
(1, 'Ficha de Prueba ADSI', '2666789', 1, 1, 1);

-- 3.3. Ficha-colegio (relacionar la ficha 1 con el colegio 1)
INSERT IGNORE INTO ficha_colegio
(ficha_id, colegio_id, cantidad_estudiantes)
VALUES
(1, 1, 0);

-- -----------------------------------------------------

-- 4. Cursos / materias
INSERT IGNORE INTO cursos
(codigo, nombre, linea_tecnoacademia, estado_id)
VALUES
('ADSI', 'Análisis y Desarrollo de Software', 'Tecnología en Análisis y Desarrollo de Software', 1),
('PDT', 'Producción y Transformación', 'Manufactura', 1),
('MVC', 'Mantenimiento de Vehículos', 'Mecánica Automotriz', 1);

-- -----------------------------------------------------

-- 5. Aprendiz (SIN ficha inicialmente → correcto con triggers)
INSERT IGNORE INTO aprendices
(id, usuario_id, colegio_id, grado, grupo, estado_id)
VALUES
(1, 3, 1, '10', 'A', 1);

-- =====================================================
-- ÍNDICES ADICIONALES ÚTILES (NO DUPLICADOS)
-- =====================================================

CREATE INDEX idx_aprendices_colegio_ficha
ON aprendices (colegio_id, ficha_id);

CREATE INDEX idx_ficha_colegio_lookup
ON ficha_colegio (ficha_id, colegio_id);

CREATE INDEX idx_horarios_ficha_fecha
ON horarios_fichas (ficha_id, fecha_inicio);

-- -----------------------------------------------------

-- 6. Familiares/Acudientes (datos de prueba)
INSERT IGNORE INTO familiares
(id, aprendiz_id, nombre_completo, tipo_documento, numero_documento, telefono, parentesco, ocupacion, es_acudiente, contacto_emergencia)
VALUES
(1, 1, 'Carlos Rodríguez Martínez', 'CC', '801234567', '3124567890', 'Padre', 'Ingeniero', 1, 1),
(2, 1, 'Ana María López', 'CC', '807654321', '3159876543', 'Madre', 'Contadora', 1, 0);

-- -----------------------------------------------------

-- 6. Datos de información médica para estudiantes de prueba
INSERT IGNORE INTO informacion_medica
(usuario_id, padece_enfermedad, enfermedad_detalle, alergias, alergias_detalle, medicamentos_permanentes, medicamentos_detalle, discapacidad, discapacidad_detalle)
VALUES
-- Información médica para el estudiante de prueba (usuario_id = 3, ya que aprendiz_id = 1 corresponde a usuario_id = 3)
(3, 1, 'Asma leve controlado', 1, 'Polen y ácaros', 'Rinitis alérgica estacional', 0, '', 0, ''),
-- Información médica para otros estudiantes si existen
(4, 0, '', 0, '', '', 1, 'Vitamina D', 0, '');

-- -----------------------------------------------------
