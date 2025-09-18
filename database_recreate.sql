-- =============================================================
-- System School (SENA) - Script de recreación de Base de Datos
-- Compatible con XAMPP/MySQL (MariaDB) y el código actual del proyecto
-- Autor: Cascade
-- Fecha: 2025-09-17 10:08:00 -05
-- =============================================================

-- 1) RECREAR BASE DE DATOS
DROP DATABASE IF EXISTS `sistema_escolar`;
CREATE DATABASE `sistema_escolar` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sistema_escolar`;

-- Forzar SQL_MODE compatible
SET sql_mode='';

-- 2) TABLAS BÁSICAS
-- -------------------------------------------------------------
-- roles
CREATE TABLE roles (
  id TINYINT UNSIGNED PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO roles (id, nombre) VALUES
  (1, 'administrador'),
  (2, 'profesor'),
  (3, 'estudiante');

-- usuarios (usado por login, perfiles, profesores y estudiantes)
CREATE TABLE usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rol_id TINYINT UNSIGNED NOT NULL,
  nombres VARCHAR(120) NOT NULL,
  apellidos VARCHAR(120) NOT NULL,
  tipo_documento VARCHAR(30) DEFAULT 'CC',
  numero_documento VARCHAR(40) NULL UNIQUE,
  genero VARCHAR(20) DEFAULT 'M',
  fecha_nacimiento DATE NULL,
  correo_electronico VARCHAR(190) NOT NULL,
  telefono VARCHAR(40) NULL,
  correo_institucional VARCHAR(190) NULL,
  password_hash VARCHAR(255) NOT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY u_usuarios_correo (correo_electronico),
  CONSTRAINT fk_usuarios_roles FOREIGN KEY (rol_id) REFERENCES roles(id)
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- administradores (vinculado 1:1 con usuarios)
CREATE TABLE administradores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  correo_institucional VARCHAR(190) NOT NULL,
  fecha_designacion DATE NOT NULL,
  UNIQUE KEY u_administrador_usuario (usuario_id),
  CONSTRAINT fk_admin_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 3) COLEGIOS / MATERIAS
-- -------------------------------------------------------------
CREATE TABLE colegios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(180) NOT NULL,
  codigo_dane VARCHAR(50) NULL,
  nit VARCHAR(50) NULL,
  tipo_institucion VARCHAR(100) NULL,
  direccion VARCHAR(255) NULL,
  telefono VARCHAR(40) NULL,
  correo VARCHAR(190) NULL,
  municipio VARCHAR(120) NULL,
  departamento VARCHAR(120) NULL,
  grados VARCHAR(255) NULL,   -- listado separado por comas o JSON
  jornada VARCHAR(255) NULL,  -- listado separado por comas o JSON
  calendario TEXT NULL,       -- JSON opcional
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE materias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(32) NULL,
  denominacion VARCHAR(255) NULL,
  duracion VARCHAR(50) NULL,
  version INT NULL,
  linea_tecnoacademia VARCHAR(120) NULL,
  nombre VARCHAR(120) NOT NULL,
  descripcion TEXT NULL,
  UNIQUE KEY u_materia_nombre (nombre),
  KEY k_materia_codigo (codigo)
) ENGINE=InnoDB;

-- Relación opcional colegio ↔ materias (según models/Colegio.php usa 'colegio_materia')
CREATE TABLE colegio_materia (
  colegio_id INT UNSIGNED NOT NULL,
  materia_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (colegio_id, materia_id),
  CONSTRAINT fk_cm_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cm_materia FOREIGN KEY (materia_id) REFERENCES materias(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 4) PROFESORES
-- -------------------------------------------------------------
CREATE TABLE profesores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  colegio_id INT UNSIGNED NULL,
  titulo_academico VARCHAR(120) NULL,
  especialidad VARCHAR(120) NULL,
  fecha_ingreso DATE NULL,
  rh VARCHAR(10) NULL,
  correo_institucional VARCHAR(190) NULL,
  tip_contrato VARCHAR(60) NULL,
  UNIQUE KEY u_profesor_usuario (usuario_id),
  KEY k_profesor_colegio (colegio_id),
  CONSTRAINT fk_profesor_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_profesor_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5) FICHAS (grupos) y relaciones
-- -------------------------------------------------------------
CREATE TABLE fichas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  numero VARCHAR(50) NOT NULL,
  jornada VARCHAR(60) NULL,
  cupo_total INT UNSIGNED NOT NULL DEFAULT 0,
  cupo_usado INT UNSIGNED NOT NULL DEFAULT 0,
  token CHAR(32) NOT NULL,
  estado ENUM('activa','inactiva') NOT NULL DEFAULT 'activa',
  dias_semana JSON NULL,
  UNIQUE KEY u_fichas_numero (numero)
) ENGINE=InnoDB;

-- Relación muchos-a-muchos profesor ↔ ficha
CREATE TABLE profesor_ficha (
  profesor_id INT UNSIGNED NOT NULL,
  ficha_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (profesor_id, ficha_id),
  KEY k_pf_ficha (ficha_id),
  CONSTRAINT fk_pf_profesor FOREIGN KEY (profesor_id) REFERENCES profesores(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pf_ficha FOREIGN KEY (ficha_id) REFERENCES fichas(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Materias dictadas por profesor (opcional, usado en consultas)
CREATE TABLE materia_profesor (
  profesor_id INT UNSIGNED NOT NULL,
  materia_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (profesor_id, materia_id),
  CONSTRAINT fk_mp_profesor FOREIGN KEY (profesor_id) REFERENCES profesores(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_mp_materia FOREIGN KEY (materia_id) REFERENCES materias(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Fichas compartidas entre profesores (compat. con models/Ficha.php)
CREATE TABLE fichas_compartidas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ficha_id INT UNSIGNED NOT NULL,
  profesor_lider_id INT UNSIGNED NOT NULL,
  profesor_compartido_id INT UNSIGNED NOT NULL,
  estado ENUM('pendiente','aceptada','rechazada') NOT NULL DEFAULT 'pendiente',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY u_ficha_compartida (ficha_id, profesor_compartido_id),
  CONSTRAINT fk_fc_ficha FOREIGN KEY (ficha_id) REFERENCES fichas(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_fc_prof_lider FOREIGN KEY (profesor_lider_id) REFERENCES profesores(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_fc_prof_comp FOREIGN KEY (profesor_compartido_id) REFERENCES profesores(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 6) SINCRONIZACIÓN DE CALENDARIOS
-- -------------------------------------------------------------
CREATE TABLE calendario_sincronizacion (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  profesor_propietario_id INT UNSIGNED NOT NULL,
  profesor_sincronizado_id INT UNSIGNED NOT NULL,
  estado ENUM('pendiente','aceptado','rechazado') NOT NULL DEFAULT 'pendiente',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY u_sinc_par (profesor_propietario_id, profesor_sincronizado_id),
  CONSTRAINT fk_cs_propietario FOREIGN KEY (profesor_propietario_id) REFERENCES profesores(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cs_sincronizado FOREIGN KEY (profesor_sincronizado_id) REFERENCES profesores(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 7) ESTUDIANTES / ASISTENCIAS
-- -------------------------------------------------------------
CREATE TABLE estudiantes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  colegio_id INT UNSIGNED NULL,
  ficha_id INT UNSIGNED NULL,
  grado VARCHAR(60) NULL,
  grupo VARCHAR(60) NULL,
  jornada VARCHAR(60) NULL,
  fecha_ingreso DATE NULL,
  nombre_completo_acudiente VARCHAR(180) NULL,
  tipo_documento_acudiente VARCHAR(30) NULL,
  numero_documento_acudiente VARCHAR(40) NULL,
  telefono_acudiente VARCHAR(40) NULL,
  parentesco VARCHAR(60) NULL,
  ocupacion VARCHAR(120) NULL,
  estado VARCHAR(30) NOT NULL DEFAULT 'Activo',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY k_estudiante_usuario (usuario_id),
  KEY k_estudiante_colegio (colegio_id),
  KEY k_estudiante_ficha (ficha_id),
  CONSTRAINT fk_e_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_e_colegio FOREIGN KEY (colegio_id) REFERENCES colegios(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_e_ficha FOREIGN KEY (ficha_id) REFERENCES fichas(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE asistencias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ficha_id INT UNSIGNED NOT NULL,
  estudiante_id INT UNSIGNED NOT NULL,
  profesor_id INT UNSIGNED NULL,
  fecha DATETIME NOT NULL,
  estado ENUM('presente','ausente','tarde','justificado') NOT NULL DEFAULT 'presente',
  observaciones TEXT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY u_asistencia_unica (ficha_id, estudiante_id, fecha),
  KEY k_asist_profesor (profesor_id),
  CONSTRAINT fk_asist_ficha FOREIGN KEY (ficha_id) REFERENCES fichas(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_asist_est FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_asist_prof FOREIGN KEY (profesor_id) REFERENCES profesores(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 8) CALENDARIO (EVENTOS POR FICHA)
-- -------------------------------------------------------------
CREATE TABLE horarios_fichas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ficha_id INT UNSIGNED NOT NULL,
  profesor_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  aula VARCHAR(120) NULL,
  fecha_inicio DATETIME NOT NULL,
  fecha_fin DATETIME NOT NULL,
  color CHAR(7) NULL,
  estado ENUM('programado','en_curso','finalizado','cancelado') NOT NULL DEFAULT 'programado',
  asistencia_habilitada TINYINT(1) NOT NULL DEFAULT 0,
  creado_por INT UNSIGNED NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY k_hf_ficha (ficha_id),
  KEY k_hf_profesor (profesor_id),
  KEY k_hf_fechas (fecha_inicio, fecha_fin),
  CONSTRAINT fk_hf_ficha FOREIGN KEY (ficha_id) REFERENCES fichas(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_hf_profesor FOREIGN KEY (profesor_id) REFERENCES profesores(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_hf_creado_por FOREIGN KEY (creado_por) REFERENCES profesores(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 9) NOTIFICACIONES (simple)
-- -------------------------------------------------------------
CREATE TABLE notificaciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  mensaje TEXT NOT NULL,
  leido TINYINT(1) NOT NULL DEFAULT 0,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 10) SEMILLAS MÍNIMAS
-- -------------------------------------------------------------
-- Materias base
-- Catálogo de cursos (nombre = denominacion para compatibilidad)
INSERT INTO materias (codigo, denominacion, duracion, version, linea_tecnoacademia, nombre) VALUES
 ('83930184', 'APLICACION DE LA ELECTRONICA EN PROYECTOS DE CIENCIA, TECNOLOGIA E INNOVACION CON ENFOQUE RURAL', '144 horas', 1, 'ELECTRÓNICA Y ROBÓTICA', 'APLICACION DE LA ELECTRONICA EN PROYECTOS DE CIENCIA, TECNOLOGIA E INNOVACION CON ENFOQUE RURAL'),
 ('93820056', 'APLICACION DE LA ELECTRONICA Y ROBOTICA EN PROYECTOS DE CIENCIA, TECNOLOGIA E INNOVACION', '144 horas', 2, 'ELECTRÓNICA Y ROBÓTICA', 'APLICACION DE LA ELECTRONICA Y ROBOTICA EN PROYECTOS DE CIENCIA, TECNOLOGIA E INNOVACION'),
 ('93820057', 'APLICACION DE LA ELECTRONICA EN PROYECTOS DE CIENCIA Y TECNOLOGIA', '144 horas', 1, 'ELECTRÓNICA Y ROBÓTICA', 'APLICACION DE LA ELECTRONICA EN PROYECTOS DE CIENCIA Y TECNOLOGIA'),
 ('12210005', 'FORMULACION DE PROYECTOS DE INVESTIGACION FORMATIVA', '144 horas', 1, 'INVESTIGACIÓN 1', 'FORMULACION DE PROYECTOS DE INVESTIGACION FORMATIVA'),
 ('12210006', 'DESARROLLO METODOLÓGICO DE UN PROYECTO DE INVESTIGACION FORMATIVO', '144 horas', 1, 'INVESTIGACIÓN 2', 'DESARROLLO METODOLÓGICO DE UN PROYECTO DE INVESTIGACION FORMATIVO'),
 ('12210016', 'APROPIACION DE LOS RESULTADOS Y PRODUCTOS DEL PROYECTO DE INVESTIGACION', '144 horas', 1, 'INVESTIGACIÓN 3', 'APROPIACION DE LOS RESULTADOS Y PRODUCTOS DEL PROYECTO DE INVESTIGACION'),
 ('22210046', 'APLICACION DE PROCESOS DE BIOTECNOLOGÍA Y AUTOMATIZACION EN EL ÁREA AGROPECUARIA', '144 horas', 1, 'ELECTRÓNICA Y ROBÓTICA', 'APLICACION DE PROCESOS DE BIOTECNOLOGÍA Y AUTOMATIZACION EN EL ÁREA AGROPECUARIA'),
 ('21730190', 'APLICACION DE LAS ETAPAS DEL DESARROLLO DE SOFTWARE EN LA IMPLEMENTACION DE SOLUCIONES DE TECNOLOGIAS DE LA INFORMACION Y LAS COMUNICACIONES', '144 horas', 1, 'DESARROLLO DE SOFTWARE', 'APLICACION DE LAS ETAPAS DEL DESARROLLO DE SOFTWARE EN LA IMPLEMENTACION DE SOLUCIONES DE TECNOLOGIAS DE LA INFORMACION Y LAS COMUNICACIONES'),
 ('23310003', 'DISEÑO Y DESARROLLO DE SISTEMAS DE INFORMACION', '144 horas', 2, 'TIC', 'DISEÑO Y DESARROLLO DE SISTEMAS DE INFORMACION'),
 ('23120012', 'RECONOCIMIENTO DE LA CIENCIA, LA TECNOLOGIA Y LA INNOVACION A PARTIR DE METODOLOGIAS EXPERIENCIALES PARA ALCANZAR APRENDIZAJES', '144 horas', 2, 'EXPLORACIÓN 1', 'RECONOCIMIENTO DE LA CIENCIA, LA TECNOLOGIA Y LA INNOVACION A PARTIR DE METODOLOGIAS EXPERIENCIALES PARA ALCANZAR APRENDIZAJES'),
 ('23120020', 'DESARROLLO DE HABILIDADES EN CIENCIA E INNOVACION A PARTIR DE CONCEPTOS DE TECNOLOGIAS EMERGENTES', '144 horas', 1, 'EXPLORACIÓN 2', 'DESARROLLO DE HABILIDADES EN CIENCIA E INNOVACION A PARTIR DE CONCEPTOS DE TECNOLOGIAS EMERGENTES'),
 ('23120019', 'APLICACION DE LA CIENCIA, LA TECNOLOGIA Y LA INNOVACION A PARTIR DE METODOLOGIAS EXPERIENCIALES PARA LA FORMULACION DE PROYECTOS', '144 horas', 1, 'EXPLORACIÓN 3', 'APLICACION DE LA CIENCIA, LA TECNOLOGIA Y LA INNOVACION A PARTIR DE METODOLOGIAS EXPERIENCIALES PARA LA FORMULACION DE PROYECTOS'),
 ('63220002', 'MANEJO DE HERRAMIENTAS DE MARKETING DIGITAL', '144 horas', 1, 'MARKETING DIGITAL', 'MANEJO DE HERRAMIENTAS DE MARKETING DIGITAL'),
 ('22210044', 'GESTION DE BIOTECNOLOGÍA', '144 horas', 2, 'BIOTECNOLOGÍA', 'GESTION DE BIOTECNOLOGÍA'),
 ('22210045', 'APLICACIONES Y DESARROLLO DE PROCESOS BIOTECNOLÓGICOS', '144 horas', 2, 'BIOTECNOLOGÍA', 'APLICACIONES Y DESARROLLO DE PROCESOS BIOTECNOLÓGICOS'),
 ('23120013', 'CIENCIAS BÁSICAS ENFASIS EN QUÍMICA', '144 horas', 2, 'CIENCIAS BÁSICAS', 'CIENCIAS BÁSICAS ENFASIS EN QUÍMICA'),
 ('23120018', 'CIENCIAS BÁSICAS CON ENFASIS EN MATEMÁTICAS Y FÍSICA', '144 horas', 1, 'CIENCIAS BÁSICAS', 'CIENCIAS BÁSICAS CON ENFASIS EN MATEMÁTICAS Y FÍSICA'),
 ('22110029', 'APLICACIONES DE LA NANOCIENCIA Y NANOTECNOLOGÍA EN PROYECTOS DE CIENCIA, TECNOLOGIA E INNOVACION', '144 horas', 1, 'NANOTECNOLOGÍA', 'APLICACIONES DE LA NANOCIENCIA Y NANOTECNOLOGÍA EN PROYECTOS DE CIENCIA, TECNOLOGIA E INNOVACION'),
 ('22520106', 'ELABORACION Y CONSTRUCCION DE PROTOTIPOS MECANICOS', '144 horas', 3, 'DISEÑO Y PROTOTIPADO', 'ELABORACION Y CONSTRUCCION DE PROTOTIPOS MECANICOS');

-- Colegio demo
INSERT INTO colegios (nombre, direccion, telefono, grados, jornada)
VALUES ('Colegio Demo SENA', 'Calle 123 #45-67', '3000000000', 'Primero,Segundo,Tercero,Cuarto,Quinto', 'Mañana,Tarde');

-- Usuario administrador (login)
-- Usuario: admin@example.com
-- Contraseña: password
INSERT INTO usuarios (
  rol_id, nombres, apellidos, tipo_documento, numero_documento,
  genero, fecha_nacimiento, correo_electronico, telefono, correo_institucional, password_hash, estado
) VALUES (
  1, 'Admin', 'Principal', 'CC', '12345678',
  'M', '1990-01-01', 'admin@example.com', '0000000000', 'admin@colegio.edu',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'activo'
);

INSERT INTO administradores (usuario_id, correo_institucional, fecha_designacion)
SELECT u.id, COALESCE(u.correo_institucional,'admin@colegio.edu'), CURDATE()
FROM usuarios u WHERE u.correo_electronico = 'admin@example.com';

-- Profesor demo (opcional para probar fichas y calendario)
-- Usuario: prof@example.com / Contraseña: password
INSERT INTO usuarios (
  rol_id, nombres, apellidos, tipo_documento, numero_documento,
  genero, fecha_nacimiento, correo_electronico, telefono, password_hash, estado
) VALUES (
  2, 'Juan', 'Pérez', 'CC', '87654321',
  'M', '1985-05-10', 'prof@example.com', '3111111111',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'activo'
);

INSERT INTO profesores (usuario_id, colegio_id, titulo_academico, especialidad, fecha_ingreso, rh, correo_institucional, tip_contrato)
SELECT u.id, c.id, 'Licenciado', 'Matemáticas', CURDATE(), 'O+', 'jperez@colegio.edu', 'Tiempo Completo'
FROM usuarios u CROSS JOIN colegios c
WHERE u.correo_electronico = 'prof@example.com' AND c.nombre = 'Colegio Demo SENA' LIMIT 1;

-- Ficha demo y relación con el profesor
INSERT INTO fichas (nombre, numero, jornada, cupo_total, cupo_usado, token, estado, dias_semana)
VALUES ('Ficha 101', '101', 'mañana', 35, 0, LOWER(HEX(RANDOM_BYTES(16))), 'activa', JSON_ARRAY('lunes','martes','miercoles','jueves','viernes'));

INSERT INTO profesor_ficha (profesor_id, ficha_id)
SELECT p.id, f.id FROM profesores p, fichas f
WHERE p.correo_institucional = 'jperez@colegio.edu' AND f.numero = '101' LIMIT 1;

-- FIN DEL SCRIPT
