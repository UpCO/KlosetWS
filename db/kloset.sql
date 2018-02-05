-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: 03-Fev-2018 às 14:59
-- Versão do servidor: 5.7.19
-- PHP Version: 5.6.31

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kloset`
--

CREATE DATABASE kloset;
USE kloset;

-- --------------------------------------------------------

--
-- Estrutura da tabela `comments`
--

DROP TABLE IF EXISTS `comments`;
CREATE TABLE IF NOT EXISTS `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` varchar(256) NOT NULL,
  `type` int(11) NOT NULL,
  `content` text NOT NULL,
  `num_likes` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `items`
--

DROP TABLE IF EXISTS `items`;
CREATE TABLE IF NOT EXISTS `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` varchar(256) NOT NULL,
  `title` varchar(256) NOT NULL,
  `images` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `looks`
--

DROP TABLE IF EXISTS `looks`;
CREATE TABLE IF NOT EXISTS `looks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` varchar(256) NOT NULL,
  `title` varchar(256) NOT NULL,
  `privacy` int(11) NOT NULL,
  `num_items` int(11) NOT NULL,
  `num_likes` int(11) NOT NULL,
  `num_comments` int(11) NOT NULL,
  `num_shares` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `look_comments`
--

DROP TABLE IF EXISTS `look_comments`;
CREATE TABLE IF NOT EXISTS `look_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `look_uid` varchar(256) NOT NULL,
  `comment_uid` varchar(256) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `look_uid` (`look_uid`),
  KEY `comment_uid` (`comment_uid`),
  FOREIGN KEY (`look_uid`) REFERENCES `looks`(`uid`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`comment_uid`) REFERENCES `comments`(`uid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `look_items`
--

DROP TABLE IF EXISTS `look_items`;
CREATE TABLE IF NOT EXISTS `look_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `look_uid` varchar(256) NOT NULL,
  `item_uid` varchar(256) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `look_uid` (`look_uid`),
  KEY `item_uid` (`item_uid`),
  FOREIGN KEY (`look_uid`) REFERENCES `looks`(`uid`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`item_uid`) REFERENCES `items`(`uid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `posts`
--

DROP TABLE IF EXISTS `posts`;
CREATE TABLE IF NOT EXISTS `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` varchar(256) NOT NULL,
  `content` text NOT NULL,
  `privacy` int(11) NOT NULL,
  `num_likes` int(11) NOT NULL,
  `num_comments` int(11) NOT NULL,
  `num_shares` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `post_comments`
--

DROP TABLE IF EXISTS `post_comments`;
CREATE TABLE IF NOT EXISTS `post_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_uid` varchar(256) NOT NULL,
  `comment_uid` varchar(256) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `post_uid` (`post_uid`),
  KEY `comment_uid` (`comment_uid`),
  FOREIGN KEY (`post_uid`) REFERENCES `posts`(`uid`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`comment_uid`) REFERENCES `comments`(`uid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` varchar(256) NOT NULL,
  `name` varchar(256) NOT NULL,
  `email` varchar(256) NOT NULL,
  `password_hash` text NOT NULL,
  `api_key` varchar(256) NOT NULL,
  `birthday` date DEFAULT NULL,
  `location` varchar(256) DEFAULT NULL,
  `about` varchar(256) DEFAULT NULL,
  `status` int(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `user_looks`
--

DROP TABLE IF EXISTS `user_looks`;
CREATE TABLE IF NOT EXISTS `user_looks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_uid` varchar(256) NOT NULL,
  `look_uid` varchar(256) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_uid` (`user_uid`),
  KEY `look_uid` (`look_uid`),
  FOREIGN KEY (`user_uid`) REFERENCES `users`(`uid`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`look_uid`) REFERENCES `looks`(`uid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `user_posts`
--

DROP TABLE IF EXISTS `user_posts`;
CREATE TABLE IF NOT EXISTS `user_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_uid` varchar(256) NOT NULL,
  `post_uid` varchar(256) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_uid` (`user_uid`),
  KEY `post_uid` (`post_uid`),
  FOREIGN KEY (`user_uid`) REFERENCES `users`(`uid`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`post_uid`) REFERENCES `posts`(`uid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
