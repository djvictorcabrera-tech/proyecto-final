-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 08-09-2026 a las 19:52:26
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `gestor_pedidos`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `actualizar_estado` (IN `p_id_usuario` INT, IN `p_estado` ENUM('ACTIVO','INACTIVO'))   BEGIN
    UPDATE usuarios
    SET estado = p_estado
    WHERE id_usuario = p_id_usuario;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_actualizar_estado_mesa` (IN `p_id_mesa` INT, IN `p_estado` ENUM('DISPONIBLE','OCUPADA','RESERVADA'))   BEGIN
    UPDATE mesas 
    SET estado = p_estado 
    WHERE id_mesa = p_id_mesa;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_actualizar_precio_disponible` (IN `p_id_producto` INT, IN `p_precio` DECIMAL(10,2), IN `p_disponible` BOOLEAN)   BEGIN
    UPDATE platos_bebidas 
    SET precio = p_precio, 
        disponible = p_disponible 
    WHERE id_producto = p_id_producto;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_agregar_detalle_pedido` (IN `p_id_pedido` INT, IN `p_id_producto` INT, IN `p_cantidad` INT, IN `p_precio_unitario` DECIMAL(10,2))   BEGIN
    DECLARE v_subtotal DECIMAL(10,2);
    
    -- 1. Calcular el subtotal del producto
    SET v_subtotal = p_cantidad * p_precio_unitario;
    
    -- 2. Insertar la línea de detalle
    INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario, subtotal)
    VALUES (p_id_pedido, p_id_producto, p_cantidad, p_precio_unitario, v_subtotal);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_autenticar_usuario` (IN `p_identificador` VARCHAR(100))   BEGIN
    SELECT 
        u.id_usuario, 
        u.nombre, 
        u.password, 
        u.estado, 
        r.nombre AS nombre_rol 
    FROM usuarios u
    INNER JOIN roles r ON u.id_rol = r.id_rol
    WHERE u.nombre = p_identificador OR u.email = p_identificador
    LIMIT 1;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_completar_y_despachar_pedido` (IN `p_id_pedido` INT)   BEGIN
    DECLARE v_id_mesa INT;

    -- Manejo de errores internos en MySQL
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
    END;

    START TRANSACTION;

    -- 1. Obtener la mesa asignada
    SELECT id_mesa INTO v_id_mesa 
    FROM pedidos 
    WHERE id_pedido = p_id_pedido;

    -- 2. Cambiar el estado del pedido
    UPDATE pedidos 
    SET estado = 'ENTREGADO' 
    WHERE id_pedido = p_id_pedido;

    -- 3. Liberar la mesa asociada si existe
    IF v_id_mesa IS NOT NULL THEN
        UPDATE mesas 
        SET estado = 'DISPONIBLE' 
        WHERE id_mesa = v_id_mesa;
    END IF;

    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_crear_pedido` (IN `p_id_mesa` INT, IN `p_total` DECIMAL(10,2), OUT `p_id_pedido` INT)   BEGIN
    -- 1. Insertar el nuevo pedido (el estado por defecto será PENDIENTE)
    INSERT INTO pedidos (id_mesa, estado, total, creado_en)
    VALUES (p_id_mesa, 'PENDIENTE', p_total, CURRENT_TIMESTAMP());
    
    -- 2. Capturar el ID del pedido recién insertado para retornarlo
    SET p_id_pedido = LAST_INSERT_ID();
    
    -- 3. Actualizar el estado de la mesa correspondiente
    UPDATE mesas SET estado = 'OCUPADA' WHERE id_mesa = p_id_mesa;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_crear_usuario_y_rol` (IN `p_nombre_rol` VARCHAR(50), IN `p_descripcion_rol` VARCHAR(255), IN `p_nombre_usuario` VARCHAR(100), IN `p_email` VARCHAR(100), IN `p_password_hash` VARCHAR(255), IN `p_estado` VARCHAR(20))   BEGIN
    DECLARE v_id_rol INT;

    -- 1. Buscar si el rol ya existe
    SELECT id_rol INTO v_id_rol 
    FROM roles 
    WHERE nombre = p_nombre_rol
    LIMIT 1;

    -- 2. Si el rol NO existe, lo creamos automáticamente en la tabla roles
    IF v_id_rol IS NULL THEN
        INSERT INTO roles (nombre, descripcion) 
        VALUES (p_nombre_rol, p_descripcion_rol);
        
        SET v_id_rol = LAST_INSERT_ID();
    END IF;

    -- 3. Insertar el usuario con los campos exactos de tu tabla (nombre, email, password, estado)
    INSERT INTO usuarios (id_rol, nombre, email, password, estado)
    VALUES (v_id_rol, p_nombre_usuario, p_email, p_password_hash, p_estado);

END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_eliminar_usuario` (IN `p_id_usuario` INT)   BEGIN
    DELETE FROM usuarios 
    WHERE id_usuario = p_id_usuario;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_liberar_mesa` (IN `p_id_mesa` INT)   BEGIN
    UPDATE mesas
    SET estado = 'DISPONIBLE'
    WHERE id_mesa = p_id_mesa;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_marcar_pedido_entregado` (IN `p_id_pedido` INT)   BEGIN
    UPDATE pedidos
    SET estado = 'ENTREGADO'
    WHERE id_pedido = p_id_pedido;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_obtener_factura_pedido` (IN `p_id_pedido` INT)   BEGIN
    -- 1. Encabezado del pedido
    SELECT p.id_pedido, p.estado, p.total, p.creado_en, m.numero_mesa
    FROM pedidos p
    INNER JOIN mesas m ON p.id_mesa = m.id_mesa
    WHERE p.id_pedido = p_id_pedido;

    -- 2. Desglose de detalle del pedido
    SELECT dp.cantidad, dp.precio_unitario, dp.subtotal, dp.notas, pb.nombre AS producto
    FROM detalle_pedido dp
    INNER JOIN platos_bebidas pb ON dp.id_producto = pb.id_producto
    WHERE dp.id_pedido = p_id_pedido;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_obtener_mesas` ()   BEGIN
    SELECT id_mesa, numero_mesa, capacidad, estado 
    FROM mesas;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_obtener_mesa_por_pedido` (IN `p_id_pedido` INT, OUT `p_id_mesa` INT)   BEGIN
    SELECT id_mesa INTO p_id_mesa
    FROM pedidos
    WHERE id_pedido = p_id_pedido
    LIMIT 1;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_obtener_pedidos_activos_cocina` ()   BEGIN
    SELECT 
        p.id_pedido,
        m.numero_mesa,
        p.estado,
        p.creado_en,
        TIMESTAMPDIFF(MINUTE, p.creado_en, NOW()) AS minutos_transcurridos,
        dp.cantidad,
        dp.notas,
        pb.nombre AS producto
    FROM pedidos p
    INNER JOIN mesas m ON p.id_mesa = m.id_mesa
    INNER JOIN detalle_pedido dp ON p.id_pedido = dp.id_pedido
    INNER JOIN platos_bebidas pb ON dp.id_producto = pb.id_producto
    WHERE p.estado IN ('PENDIENTE', 'EN_PREPARACION')
    ORDER BY p.creado_en ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_obtener_platos_bebidas` ()   BEGIN
    SELECT 
        pb.id_producto AS id, 
        pb.nombre AS name, 
        pb.descripcion AS `desc`, 
        pb.precio AS price, 
        c.nombre AS category,
        pb.imagen_url
    FROM platos_bebidas pb
    INNER JOIN categorias c ON pb.id_categoria = c.id_categoria
    WHERE pb.disponible >= 1;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_obtener_platos_bebidas2` ()   BEGIN
    SELECT id_producto, nombre, descripcion, precio, disponible 
    FROM platos_bebidas 
    ORDER BY id_producto ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_obtener_ultimo_id_pedido` (OUT `p_id_pedido` INT)   BEGIN
    SELECT COALESCE(MAX(id_pedido), 0) INTO p_id_pedido
    FROM pedidos;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_obtener_usuarios_con_rol` ()   BEGIN
    SELECT 
        u.id_usuario, 
        u.nombre AS nombre_usuario, 
        r.nombre AS nombre_rol, 
        u.email, 
        u.estado, 
        u.creado_en
    FROM usuarios u
    INNER JOIN roles r ON u.id_rol = r.id_rol;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_obtener_usuario_login` (IN `p_input_usuario` VARCHAR(100))   BEGIN
    SELECT 
        u.id_usuario AS id,
        u.nombre AS usuario,
        u.email,
        u.password,
        u.estado,
        r.nombre AS nombre_rol
    FROM usuarios u
    INNER JOIN roles r ON u.id_rol = r.id_rol
    WHERE (u.nombre = p_input_usuario OR u.email = p_input_usuario)
      AND u.estado = 'ACTIVO';
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id_categoria` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `orden` int(11) DEFAULT 0,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id_categoria`, `nombre`, `orden`, `estado`) VALUES
(1, 'comida', 0, 'INACTIVO'),
(2, 'bebidas', 0, 'INACTIVO'),
(3, 'postres', 0, 'INACTIVO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_pedido`
--

CREATE TABLE `detalle_pedido` (
  `id_detalle` int(11) NOT NULL,
  `id_pedido` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `notas` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `detalle_pedido`
--

INSERT INTO `detalle_pedido` (`id_detalle`, `id_pedido`, `id_producto`, `cantidad`, `precio_unitario`, `subtotal`, `notas`) VALUES
(1, 1, 3, 1, 2.00, 2.00, NULL),
(2, 1, 6, 1, 4.00, 4.00, NULL),
(3, 2, 3, 1, 2.00, 2.00, NULL),
(4, 2, 6, 1, 4.00, 4.00, NULL),
(5, 3, 13, 1, 1.00, 1.00, NULL),
(6, 3, 11, 1, 1.50, 1.50, NULL),
(7, 4, 3, 1, 2.00, 2.00, NULL),
(8, 5, 3, 1, 2.00, 2.00, NULL),
(9, 5, 6, 1, 4.00, 4.00, NULL),
(10, 6, 3, 1, 2.00, 2.00, NULL),
(11, 7, 3, 1, 2.00, 2.00, NULL),
(12, 8, 3, 1, 2.00, 2.00, NULL),
(13, 9, 3, 1, 2.00, 2.00, NULL),
(14, 10, 6, 1, 4.00, 4.00, NULL),
(15, 11, 6, 1, 4.00, 4.00, NULL),
(16, 12, 1, 3, 1.50, 4.50, NULL),
(17, 13, 6, 1, 4.00, 4.00, NULL),
(18, 14, 3, 1, 2.00, 2.00, NULL),
(19, 15, 6, 1, 4.00, 4.00, NULL),
(20, 16, 2, 1, 4.00, 4.00, NULL),
(21, 17, 1, 3, 1.50, 4.50, NULL),
(22, 17, 2, 3, 4.00, 12.00, NULL),
(23, 18, 3, 1, 2.00, 2.00, NULL),
(24, 18, 6, 1, 4.00, 4.00, NULL),
(25, 19, 3, 3, 2.00, 6.00, NULL),
(26, 20, 1, 1, 1.50, 1.50, NULL),
(27, 21, 2, 1, 4.00, 4.00, NULL),
(28, 21, 5, 1, 1.50, 1.50, NULL),
(29, 22, 3, 1, 2.00, 2.00, NULL),
(30, 23, 6, 1, 4.00, 4.00, NULL),
(31, 24, 8, 1, 5.00, 5.00, NULL),
(32, 25, 1, 1, 1.50, 1.50, NULL),
(33, 26, 3, 1, 2.00, 2.00, NULL),
(34, 27, 2, 1, 4.00, 4.00, NULL),
(35, 27, 20, 1, 1.50, 1.50, NULL),
(36, 27, 13, 1, 1.00, 1.00, NULL),
(37, 28, 3, 1, 2.00, 2.00, NULL),
(38, 28, 2, 1, 4.00, 4.00, NULL),
(39, 28, 5, 1, 1.50, 1.50, NULL),
(40, 29, 3, 3, 2.00, 6.00, NULL),
(41, 30, 6, 1, 4.00, 4.00, NULL),
(42, 30, 3, 1, 2.00, 2.00, NULL),
(43, 32, 1, 1, 1.60, 1.60, NULL),
(44, 32, 2, 1, 4.00, 4.00, NULL),
(45, 32, 11, 1, 1.50, 1.50, NULL),
(46, 32, 21, 1, 1.50, 1.50, NULL),
(47, 34, 4, 1, 5.00, 5.00, NULL),
(48, 35, 2, 1, 4.00, 4.00, NULL),
(49, 35, 4, 1, 5.00, 5.00, NULL),
(50, 36, 3, 1, 2.10, 2.10, NULL),
(51, 36, 21, 1, 1.50, 1.50, NULL),
(52, 37, 4, 1, 5.00, 5.00, NULL),
(53, 38, 2, 1, 4.00, 4.00, NULL),
(54, 39, 3, 2, 2.10, 4.20, NULL),
(55, 39, 4, 1, 5.00, 5.00, NULL),
(56, 40, 4, 1, 5.00, 5.00, NULL),
(57, 41, 3, 1, 2.10, 2.10, NULL),
(58, 42, 3, 1, 2.10, 2.10, NULL),
(59, 43, 2, 1, 4.00, 4.00, NULL),
(60, 44, 2, 1, 4.00, 4.00, NULL),
(61, 45, 1, 1, 1.60, 1.60, NULL),
(62, 46, 2, 1, 4.00, 4.00, NULL),
(63, 47, 3, 1, 2.10, 2.10, NULL),
(64, 47, 20, 1, 1.50, 1.50, NULL),
(65, 48, 1, 1, 1.60, 1.60, NULL),
(66, 49, 1, 1, 1.60, 1.60, NULL),
(67, 50, 1, 1, 1.60, 1.60, NULL),
(68, 51, 3, 1, 2.10, 2.10, NULL),
(69, 51, 16, 1, 2.50, 2.50, NULL),
(70, 52, 2, 2, 4.00, 8.00, NULL),
(71, 52, 4, 1, 5.00, 5.00, NULL),
(72, 53, 2, 1, 4.00, 4.00, NULL),
(73, 54, 1, 1, 1.60, 1.60, NULL),
(74, 55, 2, 1, 4.00, 4.00, NULL),
(75, 56, 2, 1, 4.00, 4.00, NULL),
(76, 57, 2, 1, 4.00, 4.00, NULL),
(77, 58, 3, 1, 2.10, 2.10, NULL),
(78, 58, 16, 1, 2.50, 2.50, NULL),
(79, 58, 21, 1, 1.50, 1.50, NULL),
(80, 59, 2, 1, 4.00, 4.00, NULL),
(81, 60, 1, 1, 1.60, 1.60, NULL),
(82, 61, 3, 1, 2.10, 2.10, NULL),
(83, 62, 3, 2, 2.10, 4.20, NULL),
(84, 63, 2, 2, 4.00, 8.00, NULL),
(85, 63, 21, 1, 1.50, 1.50, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mesas`
--

CREATE TABLE `mesas` (
  `id_mesa` int(11) NOT NULL,
  `numero_mesa` varchar(20) NOT NULL,
  `capacidad` int(11) DEFAULT 2,
  `estado` enum('DISPONIBLE','OCUPADA','RESERVADA') DEFAULT 'DISPONIBLE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `mesas`
--

INSERT INTO `mesas` (`id_mesa`, `numero_mesa`, `capacidad`, `estado`) VALUES
(1, '1', 2, 'OCUPADA'),
(2, '2', 2, 'DISPONIBLE'),
(3, '3', 2, 'DISPONIBLE'),
(4, '4', 2, 'DISPONIBLE'),
(5, '5', 2, 'OCUPADA'),
(6, '6', 2, 'DISPONIBLE');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id_pedido` int(11) NOT NULL,
  `id_mesa` int(11) NOT NULL,
  `estado` enum('PENDIENTE','EN_PREPARACION','ENTREGADO','CANCELADO') DEFAULT 'PENDIENTE',
  `total` decimal(10,2) DEFAULT 0.00,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pedidos`
--

INSERT INTO `pedidos` (`id_pedido`, `id_mesa`, `estado`, `total`, `creado_en`) VALUES
(1, 1, 'ENTREGADO', 6.00, '2026-09-05 15:38:10'),
(2, 1, 'ENTREGADO', 6.00, '2026-09-05 15:40:46'),
(3, 5, 'ENTREGADO', 2.50, '2026-09-05 15:41:11'),
(4, 4, 'ENTREGADO', 2.00, '2026-09-05 15:43:30'),
(5, 4, 'ENTREGADO', 6.00, '2026-09-05 15:50:18'),
(6, 4, 'ENTREGADO', 2.00, '2026-09-05 16:00:41'),
(7, 1, 'ENTREGADO', 2.00, '2026-09-05 16:00:52'),
(8, 1, 'ENTREGADO', 2.00, '2026-09-05 16:07:11'),
(9, 1, 'ENTREGADO', 2.00, '2026-09-05 16:08:26'),
(10, 1, 'ENTREGADO', 4.00, '2026-09-05 16:09:18'),
(11, 1, 'ENTREGADO', 4.00, '2026-09-05 16:13:11'),
(12, 1, 'ENTREGADO', 4.50, '2026-09-05 16:13:55'),
(13, 1, 'ENTREGADO', 4.00, '2026-09-05 16:14:20'),
(14, 1, 'ENTREGADO', 2.00, '2026-09-05 16:15:17'),
(15, 1, 'ENTREGADO', 4.00, '2026-09-05 16:15:42'),
(16, 1, 'ENTREGADO', 4.00, '2026-09-05 16:16:08'),
(17, 1, 'ENTREGADO', 16.50, '2026-09-05 16:27:35'),
(18, 1, 'ENTREGADO', 6.00, '2026-09-05 16:27:38'),
(19, 1, 'ENTREGADO', 6.00, '2026-09-05 16:30:52'),
(20, 1, 'ENTREGADO', 1.50, '2026-09-05 16:31:11'),
(21, 1, 'ENTREGADO', 5.50, '2026-09-05 16:31:47'),
(22, 1, 'ENTREGADO', 2.00, '2026-09-05 16:31:49'),
(23, 1, 'ENTREGADO', 4.00, '2026-09-05 16:31:51'),
(24, 1, 'ENTREGADO', 5.00, '2026-09-05 16:31:54'),
(25, 1, 'ENTREGADO', 1.50, '2026-09-05 16:40:15'),
(26, 1, 'ENTREGADO', 2.00, '2026-09-05 16:40:26'),
(27, 5, 'ENTREGADO', 6.50, '2026-09-05 16:41:48'),
(28, 4, 'ENTREGADO', 7.50, '2026-09-05 16:42:38'),
(29, 6, 'ENTREGADO', 6.00, '2026-09-05 16:44:04'),
(30, 6, 'ENTREGADO', 6.00, '2026-09-05 16:44:17'),
(31, 1, 'PENDIENTE', 8.60, '2026-09-07 18:30:53'),
(32, 5, 'ENTREGADO', 150.00, '2026-09-07 18:30:53'),
(33, 1, 'PENDIENTE', 5.00, '2026-09-07 18:35:34'),
(34, 5, 'ENTREGADO', 150.00, '2026-09-07 18:35:34'),
(35, 1, 'ENTREGADO', 9.00, '2026-09-07 18:41:30'),
(36, 4, 'ENTREGADO', 3.60, '2026-09-07 18:41:41'),
(37, 1, 'ENTREGADO', 5.00, '2026-09-07 18:56:29'),
(38, 3, 'ENTREGADO', 4.00, '2026-09-07 19:07:12'),
(39, 1, 'ENTREGADO', 9.20, '2026-09-07 19:10:33'),
(40, 1, 'ENTREGADO', 5.00, '2026-09-07 19:12:52'),
(41, 5, 'ENTREGADO', 2.10, '2026-09-07 19:12:59'),
(42, 1, 'ENTREGADO', 2.10, '2026-09-07 19:13:17'),
(43, 2, 'ENTREGADO', 4.00, '2026-09-08 15:51:07'),
(44, 3, 'ENTREGADO', 4.00, '2026-09-08 16:04:07'),
(45, 1, 'ENTREGADO', 1.60, '2026-09-08 16:04:20'),
(46, 2, 'ENTREGADO', 4.00, '2026-09-08 16:09:44'),
(47, 6, 'ENTREGADO', 3.60, '2026-09-08 16:09:52'),
(48, 1, 'ENTREGADO', 1.60, '2026-09-08 16:26:28'),
(49, 4, 'ENTREGADO', 1.60, '2026-09-08 16:26:34'),
(50, 1, 'ENTREGADO', 1.60, '2026-09-08 16:27:54'),
(51, 4, 'ENTREGADO', 4.60, '2026-09-08 16:28:01'),
(52, 1, 'ENTREGADO', 13.00, '2026-09-08 16:29:10'),
(53, 4, 'ENTREGADO', 4.00, '2026-09-08 17:01:37'),
(54, 1, 'ENTREGADO', 1.60, '2026-09-08 17:01:44'),
(55, 1, 'ENTREGADO', 4.00, '2026-09-08 17:11:03'),
(56, 1, 'ENTREGADO', 4.00, '2026-09-08 17:11:29'),
(57, 1, 'ENTREGADO', 4.00, '2026-09-08 17:12:11'),
(58, 4, 'ENTREGADO', 6.10, '2026-09-08 17:12:21'),
(59, 1, 'ENTREGADO', 4.00, '2026-09-08 17:28:32'),
(60, 4, 'ENTREGADO', 1.60, '2026-09-08 17:28:39'),
(61, 1, 'PENDIENTE', 2.10, '2026-09-08 17:48:42'),
(62, 5, 'PENDIENTE', 4.20, '2026-09-08 17:48:48'),
(63, 1, 'PENDIENTE', 9.50, '2026-09-08 17:48:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `platos_bebidas`
--

CREATE TABLE `platos_bebidas` (
  `id_producto` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL,
  `imagen_url` varchar(255) DEFAULT NULL,
  `disponible` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `platos_bebidas`
--

INSERT INTO `platos_bebidas` (`id_producto`, `id_categoria`, `nombre`, `descripcion`, `precio`, `imagen_url`, `disponible`) VALUES
(1, 1, 'Hamburguesa de Carne con queso y tocineta', 'Pan\r\nCarne de 180g\r\nQueso Cheddar\r\nTocineta\r\nLechuga\r\nSalsa de Tomate\r\nMayonesa', 1.60, 'https://www.saborusa.com/wp-content/uploads/2019/10/Rompe-la-rutina-con-una-suculenta-hamburguesa-con-queso-Foto-destacada.png', 5),
(2, 1, 'Hamburguesa Doble Carne', 'Pan\r\n2 trozos de Carne de 180g\r\nQueso Cheddar\r\nTocineta\r\nLechuga\r\nSalsa de Tomate\r\nMayonesa', 4.00, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQB9bIhAH4o15Z7hEMFfWzzTrD2LG-DdajDrOGj-FrUqSRpe4uY35CcrIM&s=10', 2),
(3, 1, 'Hamburguesa de Carne con queso y tocineta + Papas Fritas', 'Pan\r\nCarne de 180g\r\nQueso Cheddar\r\nTocineta\r\nLechuga\r\nSalsa de Tomate\r\nMayonesa\r\n100g de Papas Fritas', 2.10, 'https://preview.redd.it/homemade-bacon-double-cheeseburger-with-fries-v0-1fjhjvn03o3c1.jpg?width=640&crop=smart&auto=webp&s=c1ad8b7a6c3706e304bb8a83023df366c70451af', 4),
(4, 1, 'Hamburguesa Doble Carne + Papas Fritas', 'Pan\r\n2 trozos de Carne de 180g\r\nQueso Cheddar\r\nTocineta\r\nLechuga\r\nSalsa de Tomate\r\nMayonesa\r\n100g de Papas Fritas', 5.00, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRwE23wtck6iFOo3tnApr01EW3xZGrNMdKevgs3D7SzytTU5vIJ9zk-ZaM&s=10', 3),
(5, 1, 'Hamburguesa de Pollo con queso y tocineta', 'Pan\r\n180g de Pollo\r\nQueso Cheddar\r\nTocineta\r\nLechuga\r\nSalsa de Tomate\r\nMayonesa', 1.50, 'https://img.magnific.com/fotos-premium/hamburguesa-pollo-tocino-queso-sobre-tabla-madera_105609-10889.jpg', 2),
(6, 1, 'Hamburguesa Doble Pollo', 'Pan\r\n2 trozos de Pollo de 180g\r\nQueso Cheddar\r\nTocineta\r\nLechuga\r\nSalsa de Tomate\r\nMayonesa', 4.00, 'https://i.pinimg.com/originals/be/21/2c/be212c7d44d362bea0fca7bbb7e76329.jpg', 1),
(7, 1, 'Hamburguesa de Pollo con queso y tocineta + Papas Fritas', 'Pan\r\n180g de Pollo\r\nQueso Cheddar\r\nTocineta\r\nLechuga\r\nSalsa de Tomate\r\nMayonesa\r\n100g de Papas Fritas', 2.00, 'https://locosxlaparrilla.com/wp-content/uploads/2015/02/Receta-recetas-locos-x-la-parrilla-locosxlaparrilla-receta-hamburguesa-pollo-papas-fritas-hamburguesa-pollo-casera-hamburguesa.jpg', 1),
(8, 1, 'Hamburguesa Doble Pollo + Papas Fritas', 'Pan\r\n2 trozos de Pollo de 180g\r\nQueso Cheddar\r\nTocineta\r\nLechuga\r\nSalsa de Tomate\r\nMayonesa\r\n100g de Papas Fritas', 5.00, 'https://img.magnific.com/foto-gratis/naturaleza-muerta-deliciosa-hamburguesa-americana_23-2149637310.jpg?semt=ais_hybrid&w=740&q=80', 1),
(9, 2, 'Cocacola 500ml', NULL, 0.90, 'https://fsa.bo/productos/08676-01.jpg', 1),
(11, 2, 'Cocacola 1.5L', NULL, 1.50, 'https://www.sigo.com.ve/images/thumbs/0024372_coca-cola-sabor-original-15-l_450.jpeg', 1),
(12, 2, 'Cocacola 2L', NULL, 2.00, 'https://tantovital.com/images/productos/7591127123626.webp', 1),
(13, 2, 'Pepsi 500ml', NULL, 1.00, 'https://http2.mlstatic.com/D_NQ_NP_651109-MLA47722692936_102021-O.webp', 1),
(14, 2, 'Pepsi 1.5L', NULL, 2.00, 'https://www.diadeburgers.com/cdn/shop/products/pepsi_1_1024x1024.jpg?v=1606501800', 1),
(16, 2, 'Pepsi 2L', NULL, 2.50, 'https://sigo.com.ve/images/thumbs/0021207_refresco-pepsi-2-l_450.jpeg', 1),
(17, 2, 'Cocacola sin Azucar 1.5L', NULL, 1.50, 'https://dashboard.dondelanegra.cl/images/products/1778_0_cocacola-zero.jpg', 1),
(19, 2, 'Pepsi Light 1.5L', NULL, 2.00, 'https://prod-resize.tiendainglesa.com.uy/images/medium/P001703-1.jpg?20211127110024,Refresco-PEPSI-Cola-Light-1.5-L-en-Tienda-Inglesa', 1),
(20, 3, 'Torta de Chocolate', NULL, 1.50, 'https://www.recetasnestle.com.ve/sites/default/files/srh_recipes/e2928ff551a360cdadb4e5a2528841b7.jpg', 1),
(21, 3, 'Torta Marmoleada', NULL, 1.50, 'https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEgtO_5qHCofnpF3n1D_BzSm8MH77GWnqoTd32kUv3ZXlZnPFAxtbUEkEMV26MyCJIJFLvXMlVsn0GtU2etq3ZqDbP73qFeVnBXwHR0vDP9C5_GGgm-7p0HTMsDpuD7cdqKU7ZO3Zsd9dD7DjVBjbWonR8zFVbISu2WDGQFenGqBKMhjicutTiqqxXi7wQ/s1600/t', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre`, `descripcion`) VALUES
(1, 'administrador', 'crea usuario'),
(2, 'usuario', 'Rol asignado desde el registro web'),
(3, 'mesero', 'Rol asignado desde el registro web'),
(4, 'mesonero', 'Rol asignado desde el registro web'),
(5, 'Empleado', 'Rol asignado desde el registro web');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `id_rol`, `nombre`, `email`, `password`, `estado`, `creado_en`) VALUES
(16, 4, 'jose', 'adffdaf@gmail.com', '$2y$10$sejzlxE9VbAaH6S/uO0oG.0trztXLKUyagflVntSxqP8oEPcX820m', 'ACTIVO', '2026-09-08 17:27:54'),
(17, 4, 'ramon', 'dsagfsdgs@gmail.com', '$2y$10$tK4u6.MfkvGnENHNYEAf4.fEdyt49AqN9pHZ4BRWuqjlERsQxiQNq', 'ACTIVO', '2026-09-08 17:28:06'),
(18, 1, 'paul', 'asdfsadfd@gmail.com', '$2y$10$1xyY7T3oTr58.IrrVv/GZuBvEcf3gV3n4WuRoxDMZrqKxvkpYLH32', 'ACTIVO', '2026-09-08 17:35:10'),
(22, 1, 'juan', 'jsadasd@gmail.com', '$2y$10$7XlqMthtVJ57VkkYMFzoyuT/3uiaf/bWgplpy75ks8Mm.k2NGf0cu', 'ACTIVO', '2026-09-08 17:40:21'),
(23, 4, 'raul', 'fggfxg@gmail.com', '$2y$10$CzvLDkuWNgdizMJkezt0p.z0r6uvZqTgty4g.xzEr2q1oe8Vd5OTK', 'ACTIVO', '2026-09-08 17:40:50'),
(25, 5, 'victor perez', 'dfasdfsa@gmail.com', '$2y$10$c571Z1EJdVohOjlBJ2.DcePX7rkxvuvh5eZKiY190E.Z4Ft6Xb4fa', 'ACTIVO', '2026-09-08 17:43:26'),
(26, 1, 'victor cabrera', 'asdfadfdas@gmail.com', '$2y$10$lTD.hUerzZ2QX9zZ8hUXMOvnChyO5Tr6WpxCBcCGZ9WtsYANN8K9O', 'ACTIVO', '2026-09-08 17:43:53');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_usuarios`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_usuarios` (
`id_usuario` int(11)
,`nombre_usuario` varchar(100)
,`email` varchar(100)
,`password` varchar(255)
,`estado` enum('ACTIVO','INACTIVO')
,`creado_en` timestamp
,`id_rol` int(11)
,`nombre_rol` varchar(50)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_usuarios`
--
DROP TABLE IF EXISTS `vista_usuarios`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_usuarios`  AS SELECT `u`.`id_usuario` AS `id_usuario`, `u`.`nombre` AS `nombre_usuario`, `u`.`email` AS `email`, `u`.`password` AS `password`, `u`.`estado` AS `estado`, `u`.`creado_en` AS `creado_en`, `r`.`id_rol` AS `id_rol`, `r`.`nombre` AS `nombre_rol` FROM (`usuarios` `u` join `roles` `r` on(`u`.`id_rol` = `r`.`id_rol`)) ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id_categoria`);

--
-- Indices de la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_pedido` (`id_pedido`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indices de la tabla `mesas`
--
ALTER TABLE `mesas`
  ADD PRIMARY KEY (`id_mesa`),
  ADD UNIQUE KEY `numero_mesa` (`numero_mesa`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id_pedido`),
  ADD KEY `id_mesa` (`id_mesa`);

--
-- Indices de la tabla `platos_bebidas`
--
ALTER TABLE `platos_bebidas`
  ADD PRIMARY KEY (`id_producto`),
  ADD KEY `id_categoria` (`id_categoria`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `id_rol` (`id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT de la tabla `mesas`
--
ALTER TABLE `mesas`
  MODIFY `id_mesa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id_pedido` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT de la tabla `platos_bebidas`
--
ALTER TABLE `platos_bebidas`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  ADD CONSTRAINT `detalle_pedido_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `detalle_pedido_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `platos_bebidas` (`id_producto`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`id_mesa`) REFERENCES `mesas` (`id_mesa`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `platos_bebidas`
--
ALTER TABLE `platos_bebidas`
  ADD CONSTRAINT `platos_bebidas_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
