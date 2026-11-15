-- =====================================================================
-- RINCÓN AZTECA - Base de datos 'ra'
-- Motor: MySQL / MariaDB 10.4 (InnoDB)
-- 8 tablas | 3 triggers AFTER INSERT | 5 procedimientos almacenados
-- Nota: en Railway la base ya existe; si hace falta, omite CREATE DATABASE.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS ra
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE ra;

-- ---------------------------------------------------------------------
-- LIMPIEZA (descomenta solo si quieres recrear todo desde cero)
-- ---------------------------------------------------------------------
-- DROP TRIGGER IF EXISTS trg_resumen_pago;
-- DROP TRIGGER IF EXISTS trg_detalle_pedido;
-- DROP TRIGGER IF EXISTS trg_menu_platos;
-- DROP TABLE IF EXISTS pedido_detalle, detalle_pedido, resumen_pago, pagos,
--                      tiporeserva, reserva, usuario, menu;

-- =====================================================================
-- TABLAS OPERATIVAS
-- =====================================================================

CREATE TABLE IF NOT EXISTS usuario (
  id_user   INT AUTO_INCREMENT PRIMARY KEY,
  nombre    VARCHAR(100) NOT NULL,
  clave     VARCHAR(255) NOT NULL,          -- 255 para poder guardar hash (password_hash)
  correo    VARCHAR(120) NOT NULL UNIQUE,
  telefono  VARCHAR(20)  NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reserva (
  id_reserva   INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(100) NOT NULL,
  correo       VARCHAR(120) NOT NULL,
  telefono     VARCHAR(20)  NOT NULL,
  fecha        DATE NOT NULL,
  hora         TIME NOT NULL,
  preferencias VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tiporeserva (
  id_reserva   INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(100) NOT NULL,
  correo       VARCHAR(120) NOT NULL,
  telefono     VARCHAR(20)  NOT NULL,
  fecha        DATE NOT NULL,
  hora         TIME NOT NULL,
  tiporeserva  VARCHAR(100) NOT NULL        -- tipo de evento (Taco Day, etc.)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pagos (
  id_pago         INT AUTO_INCREMENT PRIMARY KEY,
  primerNombre    VARCHAR(60) NOT NULL,
  primerApellido  VARCHAR(60) NOT NULL,
  numeroTarjeta   VARCHAR(19) NOT NULL,     -- ver recomendaciones de seguridad al final
  fechaE          VARCHAR(7)  NOT NULL,     -- fecha de expiración, ej. '12/25'
  total           DECIMAL(10,2) NOT NULL
) ENGINE=InnoDB;

-- =====================================================================
-- TABLAS PARA POWER BI
-- =====================================================================

CREATE TABLE IF NOT EXISTS menu (
  id_menu       INT AUTO_INCREMENT PRIMARY KEY,
  nombre_plato  VARCHAR(100) NOT NULL UNIQUE,
  categoria     VARCHAR(50)  NOT NULL,      -- Entradas, Platillos, Bebidas, Postres
  precio        DECIMAL(8,2) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedido_detalle (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  id_pago       INT NOT NULL,
  nombre_plato  VARCHAR(100) NOT NULL,
  cantidad      INT NOT NULL DEFAULT 1,
  precio        DECIMAL(8,2) NOT NULL,
  CONSTRAINT fk_pedido_pago
    FOREIGN KEY (id_pago) REFERENCES pagos(id_pago)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detalle_pedido (
  id_detalle    INT AUTO_INCREMENT PRIMARY KEY,
  nombre_plato  VARCHAR(100) NOT NULL,
  categoria     VARCHAR(50)  NOT NULL,
  precio        DECIMAL(8,2) NOT NULL,
  cantidad      INT NOT NULL DEFAULT 0,
  fecha_ped     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS resumen_pago (
  id_resumen      INT AUTO_INCREMENT PRIMARY KEY,
  nombre_usuario  VARCHAR(130) NOT NULL,
  total_pagado    DECIMAL(10,2) NOT NULL,
  fecha_pago      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================================
-- TRIGGERS (AFTER INSERT)
-- =====================================================================

DROP TRIGGER IF EXISTS trg_resumen_pago;
CREATE TRIGGER trg_resumen_pago AFTER INSERT ON pagos
FOR EACH ROW
  INSERT INTO resumen_pago (nombre_usuario, total_pagado)
  VALUES (CONCAT(NEW.primerNombre, ' ', NEW.primerApellido), NEW.total);

DROP TRIGGER IF EXISTS trg_detalle_pedido;
CREATE TRIGGER trg_detalle_pedido AFTER INSERT ON pedido_detalle
FOR EACH ROW
  INSERT INTO detalle_pedido (nombre_plato, categoria, precio, cantidad)
  SELECT NEW.nombre_plato, m.categoria, NEW.precio, NEW.cantidad
  FROM menu m WHERE m.nombre_plato = NEW.nombre_plato;

DROP TRIGGER IF EXISTS trg_menu_platos;
CREATE TRIGGER trg_menu_platos AFTER INSERT ON menu
FOR EACH ROW
  INSERT INTO detalle_pedido (nombre_plato, categoria, precio, cantidad)
  VALUES (NEW.nombre_plato, NEW.categoria, NEW.precio, 0);

-- =====================================================================
-- PROCEDIMIENTOS ALMACENADOS
-- (Si tu cliente no soporta DELIMITER, ejecuta cada procedimiento por separado)
-- =====================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_insertar_reserva$$
CREATE PROCEDURE sp_insertar_reserva(
  IN p_nombre VARCHAR(100), IN p_correo VARCHAR(120), IN p_telefono VARCHAR(20),
  IN p_fecha DATE, IN p_hora TIME, IN p_preferencias VARCHAR(255))
BEGIN
  INSERT INTO reserva (nombre, correo, telefono, fecha, hora, preferencias)
  VALUES (p_nombre, p_correo, p_telefono, p_fecha, p_hora, p_preferencias);
END$$

DROP PROCEDURE IF EXISTS sp_obtener_reservas$$
CREATE PROCEDURE sp_obtener_reservas()
BEGIN
  SELECT * FROM reserva ORDER BY fecha, hora;
END$$

DROP PROCEDURE IF EXISTS sp_registrar_usuario$$
CREATE PROCEDURE sp_registrar_usuario(
  IN p_nombre VARCHAR(100), IN p_clave VARCHAR(255),
  IN p_correo VARCHAR(120), IN p_telefono VARCHAR(20))
BEGIN
  INSERT INTO usuario (nombre, clave, correo, telefono)
  VALUES (p_nombre, p_clave, p_correo, p_telefono);
END$$

DROP PROCEDURE IF EXISTS sp_login_usuario$$
CREATE PROCEDURE sp_login_usuario(IN p_correo VARCHAR(120), IN p_clave VARCHAR(255))
BEGIN
  SELECT id_user, nombre, correo, telefono
  FROM usuario
  WHERE correo = p_correo AND clave = p_clave;
END$$

DROP PROCEDURE IF EXISTS sp_registrar_pago$$
CREATE PROCEDURE sp_registrar_pago(
  IN p_nombre VARCHAR(60), IN p_apellido VARCHAR(60), IN p_tarjeta VARCHAR(19),
  IN p_fechaE VARCHAR(7), IN p_cvv VARCHAR(4), IN p_total DECIMAL(10,2))
BEGIN
  -- El CVV se recibe para validación en la aplicación, pero NO se almacena.
  INSERT INTO pagos (primerNombre, primerApellido, numeroTarjeta, fechaE, total)
  VALUES (p_nombre, p_apellido, p_tarjeta, p_fechaE, p_total);
END$$

DELIMITER ;

-- =====================================================================
-- DATOS DE EJEMPLO DEL MENÚ (completa con tus 24 platos reales)
-- Cada INSERT en menu dispara trg_menu_platos.
-- =====================================================================
-- INSERT INTO menu (nombre_plato, categoria, precio) VALUES
--   ('Pan con Ajo',  'Entradas',  0.00),
--   ('Papas Locas',  'Entradas',  0.00);

-- =====================================================================
-- PRUEBAS RÁPIDAS
-- =====================================================================
-- CALL sp_registrar_usuario('Ana', 'pass123', 'ana@email.com', '7654-3210');
-- CALL sp_login_usuario('ana@email.com', 'pass123');
-- CALL sp_insertar_reserva('Juan', 'juan@email.com', '7777-1234', '2026-12-15', '19:00', 'Sin especias');
-- CALL sp_obtener_reservas();
-- CALL sp_registrar_pago('Ian', 'Reyes', '1234567890123456', '12/27', '123', 14.50);
-- SELECT * FROM resumen_pago;   -- debe mostrar el pago gracias a trg_resumen_pago

-- =====================================================================
-- RECOMENDACIONES DE SEGURIDAD
-- 1. No guardes numeroTarjeta completo; usa una pasarela de pago o guarda
--    solo los últimos 4 dígitos.
-- 2. Guarda usuario.clave con password_hash() en PHP y verifica con
--    password_verify() (en ese caso sp_login_usuario debería buscar solo
--    por correo y la comparación se hace en PHP).
-- =====================================================================
