-- phpMyAdmin SQL Dump
-- version 3.3.9.2
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Sep 22, 2013 at 11:03 AM
-- Server version: 5.5.9
-- PHP Version: 5.3.6

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
--

-- --------------------------------------------------------

--
-- Table structure for table `attributes`
--

CREATE TABLE `attributes` (
  `id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `name` varchar(45) DEFAULT NULL,
  `description` varchar(45) DEFAULT NULL,
  `entity_type_id` int(11) NOT NULL,
  `data_type_id` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `attributes`
--


-- --------------------------------------------------------

--
-- Table structure for table `attributes_binary_values`
--

CREATE TABLE `attributes_binary_values` (
  `id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `entity_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `attribute_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `value` blob,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entity_id` (`entity_id`),
  KEY `attribute_id` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `attributes_binary_values`
--


-- --------------------------------------------------------

--
-- Table structure for table `attributes_boolean_values`
--

CREATE TABLE `attributes_boolean_values` (
  `id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `entity_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `attribute_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `value` tinyint(1) NOT NULL DEFAULT '0',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entity_id` (`entity_id`),
  KEY `attribute_id` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `attributes_boolean_values`
--

-- --------------------------------------------------------

--
-- Table structure for table `attributes_datetime_values`
--

CREATE TABLE `attributes_datetime_values` (
  `id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `entity_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `attribute_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `value` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entity_id` (`entity_id`),
  KEY `attribute_id` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `attributes_datetime_values`
--


-- --------------------------------------------------------

--
-- Table structure for table `attributes_date_values`
--

CREATE TABLE `attributes_date_values` (
  `id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `entity_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `attribute_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `value` date DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entity_id` (`entity_id`),
  KEY `attribute_id` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `attributes_date_values`
--


-- --------------------------------------------------------

--
-- Table structure for table `attributes_float_values`
--

CREATE TABLE `attributes_float_values` (
  `id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `entity_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `attribute_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `value` float DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entity_id` (`entity_id`),
  KEY `attribute_id` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `attributes_float_values`
--


-- --------------------------------------------------------

--
-- Table structure for table `attributes_integer_values`
--

CREATE TABLE `attributes_integer_values` (
  `id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `entity_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `attribute_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `value` int(11) DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entity_id` (`entity_id`),
  KEY `attribute_id` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `attributes_integer_values`
--


-- --------------------------------------------------------

--
-- Table structure for table `attributes_key_values`
--

CREATE TABLE `attributes_key_values` (
  `id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `entity_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `attribute_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `value` int(11) DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entity_id` (`entity_id`),
  KEY `attribute_id` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `attributes_key_values`
--


-- --------------------------------------------------------

--
-- Table structure for table `attributes_string_values`
--

CREATE TABLE `attributes_string_values` (
  `id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `entity_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `attribute_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entity_id` (`entity_id`),
  KEY `attribute_id` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `attributes_string_values`
--

-- --------------------------------------------------------

--
-- Table structure for table `attributes_text_values`
--

CREATE TABLE `attributes_text_values` (
  `id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `entity_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `attribute_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `value` text,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entity_id` (`entity_id`),
  KEY `attribute_id` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `attributes_text_values`
--

-- --------------------------------------------------------

--
-- Table structure for table `attributes_timestamp_values`
--

CREATE TABLE `attributes_timestamp_values` (
  `id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `entity_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `attribute_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `value` datetime NULL DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entity_id` (`entity_id`),
  KEY `attribute_id` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `attributes_timestamp_values`
--


-- --------------------------------------------------------

--
-- Table structure for table `attributes_time_values`
--

CREATE TABLE `attributes_time_values` (
  `id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `entity_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `attribute_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `value` time DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entity_id` (`entity_id`),
  KEY `attribute_id` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `attributes_time_values`
--


-- --------------------------------------------------------

--
-- Table structure for table `attributes_uuid_values`
--

CREATE TABLE `attributes_uuid_values` (
  `id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `entity_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `attribute_id` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `value` char(36) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entity_id` (`entity_id`),
  KEY `attribute_id` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `attributes_uuid_values`
--


-- --------------------------------------------------------

--
-- Table structure for table `data_types`
--

CREATE TABLE `data_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=13 ;

--
-- Dumping data for table `data_types`
--

INSERT INTO `data_types` VALUES(1, 'string', NOW(), NOW());
INSERT INTO `data_types` VALUES(2, 'text', NOW(), NOW());
INSERT INTO `data_types` VALUES(3, 'integer', NOW(), NOW());
INSERT INTO `data_types` VALUES(4, 'float', NOW(), NOW());
INSERT INTO `data_types` VALUES(5, 'datetime', NOW(), NOW());
INSERT INTO `data_types` VALUES(6, 'timestamp', NOW(), NOW());
INSERT INTO `data_types` VALUES(7, 'time', NOW(), NOW());
INSERT INTO `data_types` VALUES(8, 'date', NOW(), NOW());
INSERT INTO `data_types` VALUES(9, 'binary', NOW(), NOW());
INSERT INTO `data_types` VALUES(10, 'boolean', NOW(), NOW());
INSERT INTO `data_types` VALUES(11, 'key', NOW(), NOW());
INSERT INTO `data_types` VALUES(12, 'uuid', NOW(), NOW());

-- --------------------------------------------------------

--
-- Table structure for table `entity_types`
--

CREATE TABLE `entity_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `entity_types`
--

SET FOREIGN_KEY_CHECKS=1;
