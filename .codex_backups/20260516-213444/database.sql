CREATE DATABASE IF NOT EXISTS cine_soe CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cine_soe;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS validacion_entradas;
DROP TABLE IF EXISTS entradas;
DROP TABLE IF EXISTS compras;
DROP TABLE IF EXISTS peliculas;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS configuracion;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre_completo VARCHAR(150) NOT NULL,
  correo VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('admin','vendedor','validador') NOT NULL DEFAULT 'vendedor',
  codigo_referencia CHAR(12) NOT NULL UNIQUE,
  whatsapp VARCHAR(20) NULL,
  estado ENUM('activo','inactivo','eliminado') NOT NULL DEFAULT 'activo',
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por INT UNSIGNED NULL,
  INDEX idx_rol (rol),
  INDEX idx_estado (estado),
  INDEX idx_codigo (codigo_referencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clientes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre_completo VARCHAR(150) NOT NULL,
  correo VARCHAR(150) NOT NULL,
  telefono VARCHAR(20) NULL,
  estado ENUM('activo','eliminado') NOT NULL DEFAULT 'activo',
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_correo (correo),
  INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE peliculas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(200) NOT NULL,
  descripcion TEXT NULL,
  imagen VARCHAR(255) NULL,
  genero VARCHAR(100) NULL,
  duracion_min SMALLINT UNSIGNED NULL,
  fecha_funcion DATE NOT NULL,
  hora_funcion TIME NOT NULL,
  precio_entrada DECIMAL(8,2) NOT NULL,
  capacidad SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  entradas_vendidas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  estado ENUM('activo','inactivo','eliminado') NOT NULL DEFAULT 'activo',
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_fecha_estado (fecha_funcion, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE compras (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_cliente INT UNSIGNED NOT NULL,
  id_pelicula INT UNSIGNED NOT NULL,
  id_usuario_vendedor INT UNSIGNED NULL,
  cantidad_entradas TINYINT UNSIGNED NOT NULL DEFAULT 1,
  precio_unitario DECIMAL(8,2) NOT NULL,
  monto_total DECIMAL(10,2) NOT NULL,
  comprobante_nombre VARCHAR(255) NULL,
  estado_pago ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  id_usuario_validador INT UNSIGNED NULL,
  fecha_validacion DATETIME NULL,
  motivo_rechazo VARCHAR(500) NULL,
  codigo_compra CHAR(10) NOT NULL UNIQUE,
  estado ENUM('activo','anulado','eliminado') NOT NULL DEFAULT 'activo',
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (id_cliente) REFERENCES clientes(id),
  FOREIGN KEY (id_pelicula) REFERENCES peliculas(id),
  FOREIGN KEY (id_usuario_vendedor) REFERENCES usuarios(id),
  FOREIGN KEY (id_usuario_validador) REFERENCES usuarios(id),
  INDEX idx_estado_pago (estado_pago),
  INDEX idx_codigo (codigo_compra),
  INDEX idx_estado_fecha (estado_pago, fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE entradas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_compra INT UNSIGNED NOT NULL,
  id_pelicula INT UNSIGNED NOT NULL,
  codigo_qr VARCHAR(255) NOT NULL UNIQUE,
  token_validacion CHAR(64) NOT NULL UNIQUE,
  numero_entrada TINYINT UNSIGNED NOT NULL DEFAULT 1,
  estado ENUM('pendiente','activa','usada','anulada') NOT NULL DEFAULT 'pendiente',
  validada_por INT UNSIGNED NULL,
  tipo_validacion ENUM('manual','camara') NULL,
  fecha_uso DATETIME NULL,
  eliminado TINYINT(1) NOT NULL DEFAULT 0,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (id_compra) REFERENCES compras(id),
  FOREIGN KEY (id_pelicula) REFERENCES peliculas(id),
  FOREIGN KEY (validada_por) REFERENCES usuarios(id),
  INDEX idx_token_estado (token_validacion, estado),
  INDEX idx_eliminado (eliminado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE validacion_entradas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_entrada INT UNSIGNED NULL,
  id_usuario INT UNSIGNED NOT NULL,
  token_escaneado VARCHAR(255) NOT NULL,
  metodo ENUM('manual','camara') NOT NULL,
  resultado ENUM('exitoso','fallido','ya_usada','anulada','pago_pendiente') NOT NULL,
  detalle VARCHAR(500) NULL,
  ip_origen VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  fecha_escaneo DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_entrada) REFERENCES entradas(id),
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
  INDEX idx_usuario (id_usuario),
  INDEX idx_fecha (fecha_escaneo),
  INDEX idx_resultado (resultado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE configuracion (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(100) NOT NULL UNIQUE,
  valor TEXT NULL,
  descripcion VARCHAR(500) NULL,
  tipo ENUM('texto','numero','booleano','json') NOT NULL DEFAULT 'texto',
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO usuarios (nombre_completo, correo, password_hash, rol, codigo_referencia, whatsapp) VALUES
('Administrador SOE', 'admin@soe.edu.bo', '$2y$10$sy.V6AZkDQWhCnhE1PYWGu1XciJIuxt3Y3HGFw03CdtO8YOaTIVcW', 'admin', 'ADMIN2026', '59170000000'),
('Vendedor SOE 1', 'vendedor1@soe.edu.bo', '$2y$10$sy.V6AZkDQWhCnhE1PYWGu1XciJIuxt3Y3HGFw03CdtO8YOaTIVcW', 'vendedor', 'VENDSOE001', '59170000001'),
('Vendedor SOE 2', 'vendedor2@soe.edu.bo', '$2y$10$sy.V6AZkDQWhCnhE1PYWGu1XciJIuxt3Y3HGFw03CdtO8YOaTIVcW', 'vendedor', 'VENDSOE002', '59170000002'),
('Validador Puerta', 'validador@soe.edu.bo', '$2y$10$sy.V6AZkDQWhCnhE1PYWGu1XciJIuxt3Y3HGFw03CdtO8YOaTIVcW', 'validador', 'VALIDA2026', '59170000003'),
('Daniela Emilse Cordova Villca', 'cvd2019198@est.univalle.edu', '$2y$10$Chjk0fM7B8iURYaxa8HlAedoIuujbXFc8K6iIX1A3.HLnppN.thHq', 'admin', 'DANIELA2026', '59177478776');

INSERT INTO peliculas (titulo, descripcion, imagen, genero, duracion_min, fecha_funcion, hora_funcion, precio_entrada, capacidad, estado) VALUES
('Interestelar', 'Viaje espacial, drama humano y ciencia ficcion para abrir la cartelera SOE.', 'interestelar.jpg', 'Ciencia ficcion', 169, '2026-05-13', '18:00:00', 20.00, 100, 'activo'),
('Hombres de Negro 1', 'Comedia y ciencia ficcion con agentes secretos defendiendo la Tierra.', 'hombres-de-negro-1.jpg', 'Ciencia ficcion / Comedia', 98, '2026-05-13', '20:45:00', 18.00, 100, 'activo'),
('Scary Movie 1', 'Parodia de terror para la segunda noche de cine universitario.', 'scary-movie-1.jpg', 'Comedia / Terror', 88, '2026-05-14', '18:00:00', 15.00, 100, 'activo'),
('Asi en la Tierra como en el Infierno', 'Terror subterraneo con atmosfera intensa para cerrar la cartelera.', 'asi-en-la-tierra-como-en-el-infierno.jpg', 'Terror', 93, '2026-05-14', '20:30:00', 18.00, 100, 'activo');

INSERT INTO configuracion (clave, valor, descripcion, tipo) VALUES
('nombre_sistema','Cine SOE','Nombre del sistema','texto'),
('sitio_url','http://localhost/Soe%20Cine','URL base local','texto'),
('cuenta_bancaria','Banco Union - 1234567890','Cuenta para transferencia','texto'),
('titular_cuenta','SOE Universidad','Titular del pago','texto'),
('qr_bancario_imagen','qr-bancario.png','Imagen QR bancaria','texto'),
('max_entradas_por_compra','10','Maximo por compra','numero'),
('smtp_host','','Servidor SMTP real para enviar correos','texto'),
('smtp_puerto','587','Puerto SMTP','numero'),
('smtp_usuario','','Correo SMTP real','texto'),
('smtp_password','','Password o app password SMTP','texto');

CREATE OR REPLACE VIEW v_ventas_por_pelicula AS
SELECT p.id, p.titulo, p.fecha_funcion, p.hora_funcion, p.precio_entrada, p.capacidad, p.entradas_vendidas,
       (p.capacidad - p.entradas_vendidas) AS entradas_disponibles,
       ROUND((p.entradas_vendidas / p.capacidad) * 100, 2) AS porcentaje_ocupacion,
       COALESCE(SUM(CASE WHEN c.estado_pago='aprobado' AND c.estado='activo' THEN c.monto_total ELSE 0 END), 0) AS ingresos_aprobados,
       COUNT(DISTINCT CASE WHEN c.estado_pago='pendiente' THEN c.id END) AS compras_pendientes
FROM peliculas p
LEFT JOIN compras c ON c.id_pelicula = p.id AND c.estado != 'eliminado'
WHERE p.estado != 'eliminado'
GROUP BY p.id;

CREATE OR REPLACE VIEW v_compras_detalle AS
SELECT c.id, c.codigo_compra, c.id_pelicula, c.id_usuario_vendedor, cl.nombre_completo AS cliente, cl.correo AS correo_cliente, cl.telefono,
       p.titulo AS pelicula, p.fecha_funcion, p.hora_funcion, c.cantidad_entradas, c.precio_unitario, c.monto_total,
       c.estado_pago, c.comprobante_nombre, u_vend.nombre_completo AS vendedor, u_vend.whatsapp AS vendedor_whatsapp,
       u_vali.nombre_completo AS validador_pago,
       c.fecha_validacion, c.motivo_rechazo, c.estado, c.fecha_creacion
FROM compras c
JOIN clientes cl ON c.id_cliente = cl.id
JOIN peliculas p ON c.id_pelicula = p.id
LEFT JOIN usuarios u_vend ON c.id_usuario_vendedor = u_vend.id
LEFT JOIN usuarios u_vali ON c.id_usuario_validador = u_vali.id
WHERE c.estado != 'eliminado';

CREATE OR REPLACE VIEW v_entradas_activas AS
SELECT e.id, e.token_validacion, e.codigo_qr, e.numero_entrada, e.estado, cl.nombre_completo AS cliente,
       p.titulo AS pelicula, p.fecha_funcion, p.hora_funcion, c.codigo_compra, e.fecha_uso,
       u.nombre_completo AS validado_por_nombre, e.tipo_validacion
FROM entradas e
JOIN compras c ON e.id_compra = c.id
JOIN clientes cl ON c.id_cliente = cl.id
JOIN peliculas p ON e.id_pelicula = p.id
LEFT JOIN usuarios u ON e.validada_por = u.id
WHERE e.eliminado = 0;
