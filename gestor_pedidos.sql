-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 06-09-2026 a las 00:59:08
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

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

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_agregar_detalle_pedido` (IN `p_id_pedido` INT, IN `p_id_producto` INT, IN `p_cantidad` INT, IN `p_precio_unitario` DECIMAL(10,2))   BEGIN
    DECLARE v_subtotal DECIMAL(10,2);
    
    -- 1. Calcular el subtotal del producto
    SET v_subtotal = p_cantidad * p_precio_unitario;
    
    -- 2. Insertar la línea de detalle
    INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario, subtotal)
    VALUES (p_id_pedido, p_id_producto, p_cantidad, p_precio_unitario, v_subtotal);
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_crear_usuario_y_rol` (IN `p_nombre_rol` VARCHAR(50), IN `p_descripcion_rol` VARCHAR(255), IN `p_nombre_usuario` VARCHAR(100), IN `p_email` VARCHAR(100), IN `p_password_plana` VARCHAR(255), IN `p_estado` ENUM('ACTIVO','INACTIVO'))   BEGIN
    DECLARE v_id_rol INT;
    DECLARE v_password_encriptada VARCHAR(255);

    -- 1. Intentamos buscar si el rol ya existe
    SELECT id_rol INTO v_id_rol 
    FROM roles 
    WHERE nombre = p_nombre_rol;

    -- 2. Si el rol NO existe, lo creamos automáticamente en la tabla roles
    IF v_id_rol IS NULL THEN
        INSERT INTO roles (nombre, descripcion) 
        VALUES (p_nombre_rol, p_descripcion_rol);
        
        -- Obtenemos el ID del rol que acabamos de crear
        SET v_id_rol = LAST_INSERT_ID();
    END IF;

    -- 3. Encriptamos la contraseña con SHA-256
    SET v_password_encriptada = SHA2(p_password_plana, 256);

    -- 4. Insertamos el usuario en la tabla usuarios vinculado a ese id_rol (y 'creado_en' se llena solo)
    INSERT INTO usuarios (id_rol, nombre, email, password, estado)
    VALUES (v_id_rol, p_nombre_usuario, p_email, v_password_encriptada, p_estado);

END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_obtener_mesas` ()   BEGIN
    SELECT id_mesa, numero_mesa, capacidad, estado 
    FROM mesas;
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
    WHERE pb.disponible = 1; 
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
(42, 30, 3, 1, 2.00, 2.00, NULL);

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
(1, '1', 2, 'DISPONIBLE'),
(2, '2', 2, 'DISPONIBLE'),
(3, '3', 2, 'DISPONIBLE'),
(4, '4', 2, 'DISPONIBLE'),
(5, '5', 2, 'DISPONIBLE'),
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
(30, 6, 'ENTREGADO', 6.00, '2026-09-05 16:44:17');

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
(1, 1, 'Hamburguesa de Carne con queso y tocineta', 'Pan\r\nCarne de 180g\r\nQueso Cheddar\r\nTocineta\r\nLechuga\r\nSalsa de Tomate\r\nMayonesa', 1.50, 'https://www.saborusa.com/wp-content/uploads/2019/10/Rompe-la-rutina-con-una-suculenta-hamburguesa-con-queso-Foto-destacada.png', 1),
(2, 1, 'Hamburguesa Doble Carne', 'Pan\r\n2 trozos de Carne de 180g\r\nQueso Cheddar\r\nTocineta\r\nLechuga\r\nSalsa de Tomate\r\nMayonesa', 4.00, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQB9bIhAH4o15Z7hEMFfWzzTrD2LG-DdajDrOGj-FrUqSRpe4uY35CcrIM&s=10', 1),
(3, 1, 'Hamburguesa de Carne con queso y tocineta + Papas Fritas', 'Pan\r\nCarne de 180g\r\nQueso Cheddar\r\nTocineta\r\nLechuga\r\nSalsa de Tomate\r\nMayonesa\r\n100g de Papas Fritas', 2.00, 'https://preview.redd.it/homemade-bacon-double-cheeseburger-with-fries-v0-1fjhjvn03o3c1.jpg?width=640&crop=smart&auto=webp&s=c1ad8b7a6c3706e304bb8a83023df366c70451af', 1),
(4, 1, 'Hamburguesa Doble Carne + Papas Fritas', 'Pan\r\n2 trozos de Carne de 180g\r\nQueso Cheddar\r\nTocineta\r\nLechuga\r\nSalsa de Tomate\r\nMayonesa\r\n100g de Papas Fritas', 5.00, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRwE23wtck6iFOo3tnApr01EW3xZGrNMdKevgs3D7SzytTU5vIJ9zk-ZaM&s=10', 1),
(5, 1, 'Hamburguesa de Pollo con queso y tocineta', 'Pan\r\n180g de Pollo\r\nQueso Cheddar\r\nTocineta\r\nLechuga\r\nSalsa de Tomate\r\nMayonesa', 1.50, 'https://img.magnific.com/fotos-premium/hamburguesa-pollo-tocino-queso-sobre-tabla-madera_105609-10889.jpg', 1),
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
(3, 'mesero', 'Rol asignado desde el registro web');

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
(1, 1, 'victor', 'dsjfijs@gmail.com', '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92', 'ACTIVO', '2026-08-29 16:09:48'),
(2, 2, 'jose', 'suifedk@gmail.com', '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92', 'ACTIVO', '2026-08-29 16:12:51'),
(3, 3, 'pedro', 'dfdfd@gmail.com', '5994471abb01112afcc18159f6cc74b4f511b99806da59b3caf5a9c173cacfc5', 'ACTIVO', '2026-08-29 16:14:00'),
(4, 2, 'ramon', 'jsdfsfd@gmail.com', '5994471abb01112afcc18159f6cc74b4f511b99806da59b3caf5a9c173cacfc5', 'ACTIVO', '2026-08-29 16:15:06'),
(5, 1, 'raul', 'dfdfdc@gmail.com', '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92', 'ACTIVO', '2026-09-05 14:57:35'),
(6, 3, 'rafael', 'sdhfd@gmail.com', '5994471abb01112afcc18159f6cc74b4f511b99806da59b3caf5a9c173cacfc5', 'ACTIVO', '2026-09-05 15:14:31');

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
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT de la tabla `mesas`
--
ALTER TABLE `mesas`
  MODIFY `id_mesa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id_pedido` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de la tabla `platos_bebidas`
--
ALTER TABLE `platos_bebidas`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

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
