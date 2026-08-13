-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 26-06-2026 a las 17:04:58
-- Versión del servidor: 8.4.7
-- Versión de PHP: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `trt_historico_evento`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `boletos`
--

DROP TABLE IF EXISTS `boletos`;
CREATE TABLE IF NOT EXISTS `boletos` (
  `id_boleto` int NOT NULL AUTO_INCREMENT,
  `id_evento` int NOT NULL,
  `id_funcion` int DEFAULT NULL,
  `id_asiento` int NOT NULL,
  `id_categoria` int DEFAULT NULL,
  `id_promocion` int DEFAULT NULL COMMENT 'La promoción que se aplicó (si hubo)',
  `codigo_unico` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `precio_base` decimal(10,2) NOT NULL,
  `descuento_aplicado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `precio_final` decimal(10,2) NOT NULL,
  `tipo_boleto` varchar(20) COLLATE utf8mb4_general_ci DEFAULT 'adulto',
  `id_usuario` int DEFAULT NULL,
  `fecha_compra` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estatus` int NOT NULL DEFAULT '1' COMMENT '1=Activo, 0=Usado/Escaneado',
  PRIMARY KEY (`id_boleto`),
  UNIQUE KEY `idx_codigo_unico` (`codigo_unico`),
  UNIQUE KEY `idx_evento_funcion_asiento` (`id_evento`,`id_funcion`,`id_asiento`),
  KEY `id_asiento` (`id_asiento`),
  KEY `id_categoria` (`id_categoria`),
  KEY `id_promocion` (`id_promocion`),
  KEY `idx_boletos_funcion` (`id_funcion`)
) ENGINE=InnoDB AUTO_INCREMENT=1967 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

DROP TABLE IF EXISTS `categorias`;
CREATE TABLE IF NOT EXISTS `categorias` (
  `id_categoria` int NOT NULL AUTO_INCREMENT,
  `id_evento` int DEFAULT NULL,
  `nombre_categoria` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Ej: General, VIP, Balcón',
  `precio` decimal(10,2) NOT NULL COMMENT 'Precio base para esta categoría',
  `color` varchar(10) COLLATE utf8mb4_general_ci DEFAULT '#E0E0E0' COMMENT 'Color hexadecimal para la UI (ej: #FF0000)',
  PRIMARY KEY (`id_categoria`),
  UNIQUE KEY `idx_evento_categoria` (`id_evento`,`nombre_categoria`),
  KEY `idx_id_evento` (`id_evento`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evento`
--

DROP TABLE IF EXISTS `evento`;
CREATE TABLE IF NOT EXISTS `evento` (
  `id_evento` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci,
  `imagen` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tipo` int NOT NULL COMMENT '1 = 420 asientos, 2 = 540 asientos',
  `inicio_venta` datetime NOT NULL,
  `cierre_venta` datetime NOT NULL,
  `finalizado` int NOT NULL DEFAULT '0' COMMENT '0=activo, 1=finalizado',
  `mapa_json` text COLLATE utf8mb4_general_ci COMMENT 'Almacena las asignaciones del mapa en formato JSON',
  PRIMARY KEY (`id_evento`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `funciones`
--

DROP TABLE IF EXISTS `funciones`;
CREATE TABLE IF NOT EXISTS `funciones` (
  `id_funcion` int NOT NULL AUTO_INCREMENT,
  `id_evento` int NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `estado` tinyint(1) NOT NULL,
  PRIMARY KEY (`id_funcion`),
  KEY `id_evento_idx` (`id_evento`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `promociones`
--

DROP TABLE IF EXISTS `promociones`;
CREATE TABLE IF NOT EXISTS `promociones` (
  `id_promocion` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Nombre (ej: Early Bird, Cupón Verano)',
  `precio` int NOT NULL,
  `id_evento` int DEFAULT NULL COMMENT 'NULO = aplica a todos los eventos',
  `id_categoria` int DEFAULT NULL COMMENT 'NULO = aplica a todas las categorías',
  `fecha_desde` datetime DEFAULT NULL COMMENT 'NULO = sin fecha de inicio',
  `fecha_hasta` datetime DEFAULT NULL COMMENT 'NULO = sin fecha de fin',
  `min_cantidad` int NOT NULL DEFAULT '1' COMMENT 'Mínimo de boletos para aplicar',
  `tipo_regla` enum('automatica','codigo') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'automatica',
  `codigo` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'El código a escribir (ej: VERANO20)',
  `modo_calculo` enum('porcentaje','fijo') COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Si descuenta % o un monto fijo $',
  `valor` decimal(10,2) NOT NULL COMMENT 'El valor (ej: 20.00 para 20%)',
  `condiciones` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_promocion`),
  UNIQUE KEY `idx_codigo` (`codigo`),
  KEY `id_evento` (`id_evento`),
  KEY `id_categoria` (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD CONSTRAINT `fk_categoria_evento` FOREIGN KEY (`id_evento`) REFERENCES `evento` (`id_evento`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `funciones`
--
ALTER TABLE `funciones`
  ADD CONSTRAINT `fk_funciones_evento` FOREIGN KEY (`id_evento`) REFERENCES `evento` (`id_evento`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `promociones`
--
ALTER TABLE `promociones`
  ADD CONSTRAINT `promociones_ibfk_1` FOREIGN KEY (`id_evento`) REFERENCES `evento` (`id_evento`) ON DELETE CASCADE,
  ADD CONSTRAINT `promociones_ibfk_2` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
