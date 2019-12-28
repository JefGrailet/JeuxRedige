-- phpMyAdmin SQL Dump
-- version 4.8.4
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le :  jeu. 07 fév. 2019 à 21:55
-- Version du serveur :  5.7.24
-- Version de PHP :  7.2.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données :  `pag_db`
--

-- --------------------------------------------------------

--
-- Structure de la table `articles`
--

DROP TABLE IF EXISTS `articles`;
CREATE TABLE IF NOT EXISTS `articles` (
  `id_article` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pseudo` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL COMMENT 'Main author of the article.',
  `title` varchar(100) COLLATE utf8_unicode_520_ci NOT NULL,
  `subtitle` varchar(100) COLLATE utf8_unicode_520_ci NOT NULL,
  `type` set('review','preview','opinion') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'opinion',
  `related_topic` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Topic not deleted if article is deleted.',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_publication` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `date_last_modifications` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `featured` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'no',
  `views` int(10) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_article`),
  KEY `author` (`pseudo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `articles_segments`
--

DROP TABLE IF EXISTS `articles_segments`;
CREATE TABLE IF NOT EXISTS `articles_segments` (
  `id_segment` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_article` int(10) UNSIGNED NOT NULL,
  `pseudo` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL COMMENT 'Author of the segment.',
  `title` varchar(100) COLLATE utf8_unicode_520_ci DEFAULT NULL COMMENT 'Can be NULL if position = 1 (single segment article).',
  `position` tinyint(3) UNSIGNED NOT NULL,
  `content` longtext COLLATE utf8_unicode_520_ci NOT NULL,
  `attachment` text COLLATE utf8_unicode_520_ci,
  `date_last_modification` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  PRIMARY KEY (`id_segment`),
  KEY `id_article` (`id_article`),
  KEY `author` (`pseudo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commentables`
--

DROP TABLE IF EXISTS `commentables`;
CREATE TABLE IF NOT EXISTS `commentables` (
  `id_commentable` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pseudo` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL COMMENT 'Author of the content.',
  `title` varchar(60) COLLATE utf8_unicode_520_ci NOT NULL,
  `date_publication` datetime NOT NULL,
  `date_last_edition` datetime NOT NULL,
  `votes_relevant` smallint(6) UNSIGNED NOT NULL,
  `votes_irrelevant` smallint(6) UNSIGNED NOT NULL,
  `id_topic` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id_commentable`),
  KEY `commentables_ibkf_2` (`pseudo`),
  KEY `commentables_ibkf_1` (`id_topic`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commentables_ratings`
--

DROP TABLE IF EXISTS `commentables_ratings`;
CREATE TABLE IF NOT EXISTS `commentables_ratings` (
  `id_commentable` int(11) UNSIGNED NOT NULL,
  `user` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `rating` set('relevant','irrelevant') COLLATE utf8_unicode_520_ci NOT NULL COMMENT 'Might be extended with other choices later.',
  `date` datetime NOT NULL,
  UNIQUE KEY `user_commentable_tuple` (`id_commentable`,`user`),
  KEY `ratings_ibkf_2` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `emoticons`
--

DROP TABLE IF EXISTS `emoticons`;
CREATE TABLE IF NOT EXISTS `emoticons` (
  `id_emoticon` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `file` varchar(50) COLLATE utf8_unicode_520_ci NOT NULL,
  `name` varchar(50) COLLATE utf8_unicode_520_ci NOT NULL,
  `uploader` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `upload_date` datetime NOT NULL,
  `suggested_shortcut` varchar(30) COLLATE utf8_unicode_520_ci NOT NULL,
  PRIMARY KEY (`id_emoticon`),
  UNIQUE KEY `suggested_shortcut` (`suggested_shortcut`),
  KEY `uploader` (`uploader`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `functions`
--

DROP TABLE IF EXISTS `functions`;
CREATE TABLE IF NOT EXISTS `functions` (
  `function_name` varchar(30) COLLATE utf8_unicode_520_ci NOT NULL,
  `can_create_topics` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'no',
  `can_upload` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'no',
  `can_invite` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL,
  `can_edit_all_posts` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'no',
  `can_edit_games` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'no',
  `can_edit_users` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'no',
  `can_mark` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'no',
  `can_lock` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'no',
  `can_delete` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'no',
  `can_ban` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'no',
  PRIMARY KEY (`function_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

--
-- Déchargement des données de la table `functions`
--

INSERT INTO `functions` (`function_name`, `can_create_topics`, `can_upload`, `can_invite`, `can_edit_all_posts`, `can_edit_games`, `can_edit_users`, `can_mark`, `can_lock`, `can_delete`, `can_ban`) VALUES
('administrator', 'yes', 'yes', 'yes', 'yes', 'yes', 'yes', 'yes', 'yes', 'yes', 'yes'),
('alumnus', 'yes', 'yes', 'yes', 'no', 'no', 'no', 'no', 'no', 'no', 'no');

-- --------------------------------------------------------

--
-- Structure de la table `games`
--

DROP TABLE IF EXISTS `games`;
CREATE TABLE IF NOT EXISTS `games` (
  `tag` varchar(100) COLLATE utf8_unicode_520_ci NOT NULL,
  `genre` varchar(50) COLLATE utf8_unicode_520_ci NOT NULL,
  `publisher` varchar(50) COLLATE utf8_unicode_520_ci NOT NULL,
  `developer` varchar(50) COLLATE utf8_unicode_520_ci NOT NULL,
  `publication_date` datetime NOT NULL,
  `hardware` varchar(100) COLLATE utf8_unicode_520_ci NOT NULL,
  PRIMARY KEY (`tag`),
  KEY `games_ibfk_2` (`genre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

--
-- Déchargement des données de la table `games`
--

INSERT INTO `games` (`tag`, `genre`, `publisher`, `developer`, `publication_date`, `hardware`) VALUES
('Another Metroid 2 Remake', 'Action', 'DoctorM64', 'DoctorM64', '2016-08-06 00:00:00', 'PC'),
('Bloodborne', 'RPG', 'Sony', 'From Software, Japan Studio', '2015-03-24 00:00:00', 'PS4'),
('Bloodborne: The Old Hunters', 'RPG', 'Sony', 'From Software, Japan Studio', '2015-11-24 00:00:00', 'PS4'),
('Dark Souls', 'RPG', 'Namco Bandai', 'From Software', '2011-10-07 00:00:00', 'PC|PS3|X360'),
('Dark Souls II', 'RPG', 'Namco Bandai', 'From Software', '2014-03-14 00:00:00', 'PC|PS3|X360'),
('Dark Souls II: Crown of the Ivory King', 'RPG', 'Namco Bandai', 'From Software', '2014-09-30 00:00:00', 'PC|PS3|X360'),
('Dark Souls II: Crown of the Old Iron King', 'RPG', 'Namco Bandai', 'From Software', '2014-08-26 00:00:00', 'PC|PS3|X360'),
('Dark Souls II: Crown of the Sunken King', 'RPG', 'Namco Bandai', 'From Software', '2014-07-22 00:00:00', 'PC|PS3|X360'),
('Dark Souls II: Scholar of the First Sin', 'RPG', 'Namco Bandai', 'From Software', '2015-04-01 00:00:00', 'PC|PS3|PS4|X360|XONE'),
('Dark Souls III', 'RPG', 'Namco Bandai', 'From Software', '2016-04-12 00:00:00', 'PC|PS4|XONE'),
('Dark Souls III: Ashes of Ariandel', 'RPG', 'Namco Bandai', 'From Software', '2016-10-26 00:00:00', 'PC|PS4|XONE'),
('Dark Souls III: The Ringed City', 'RPG', 'Namco Bandai', 'From Software', '2017-03-28 00:00:00', 'PC|PS4|XONE'),
('Demon\'s Souls', 'RPG', 'Namco Bandai', 'From Software, Japan Studio', '2010-06-25 00:00:00', 'PS3'),
('Gravity Rush 2', 'Aventure', 'Sony Interactive Entertainment', 'Japan Studio', '2017-01-18 00:00:00', 'PS4'),
('Hollow Knight', 'Aventure', 'Team Cherry', 'Team Cherry', '2017-02-27 00:00:00', 'PC|PS4|Switch|XONE'),
('Metal Gear Rising: Revengeance', 'Beat\'em All', 'Konami', 'Platinum Games', '2013-02-21 00:00:00', 'PC|PS3|X360'),
('Metroid: Samus Returns', 'Action', 'Nintendo', 'MercurySteam', '2017-09-15 00:00:00', '3DS'),
('Monster Hunter 3 Ultimate', 'Action', 'Capcom', 'Capcom', '2013-03-22 00:00:00', '3DS|WiiU'),
('Monster Hunter 4 Ultimate', 'Action', 'Capcom', 'Capcom', '2015-02-13 00:00:00', '3DS'),
('Monster Hunter World', 'Action', 'Capcom', 'Capcom', '2018-01-26 00:00:00', 'PC|PS4|XONE'),
('Shadow of the Colossus (Remake)', 'Aventure', 'Sony Interactive Entertainment', 'Bluepoint Games', '2018-02-07 00:00:00', 'PS4'),
('The Last of Us', 'Action', 'Sony', 'Naughty Dog', '2013-06-14 00:00:00', 'PS3'),
('The Legend of Zelda: Breath of the Wild', 'Aventure', 'Nintendo', 'Nintendo', '2017-03-03 00:00:00', 'Switch|WiiU'),
('The Legend of Zelda: Breath of the Wild - L\'Ode aux Prodiges', 'Aventure', 'Nintendo', 'Nintendo', '2017-12-07 00:00:00', 'Switch|WiiU'),
('The Legend of Zelda: Majora\'s Mask 3D', 'Aventure', 'Nintendo', 'Grezzo', '2015-02-13 00:00:00', '3DS'),
('The Legend of Zelda: Ocarina of Time 3D', 'Aventure', 'Nintendo', 'Grezzo', '2011-06-17 00:00:00', '3DS'),
('The Legend of Zelda: Skyward Sword', 'Aventure', 'Nintendo', 'Nintendo', '2011-11-18 00:00:00', 'Wii'),
('The Legend of Zelda: The Wind Waker HD', 'Aventure', 'Nintendo', 'Hexa Drive', '2013-10-04 00:00:00', 'WiiU'),
('The Legend of Zelda: Twilight Princess HD', 'Aventure', 'Nintendo', 'Nintendo', '2016-03-04 00:00:00', 'WiiU');

-- --------------------------------------------------------

--
-- Structure de la table `genres`
--

DROP TABLE IF EXISTS `genres`;
CREATE TABLE IF NOT EXISTS `genres` (
  `genre` varchar(30) COLLATE utf8_unicode_520_ci NOT NULL,
  PRIMARY KEY (`genre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

--
-- Déchargement des données de la table `genres`
--

INSERT INTO `genres` (`genre`) VALUES
('Action'),
('Aventure'),
('Beat\'em All'),
('Combat'),
('Courses'),
('MMORPG'),
('Réflexion'),
('RPG'),
('Simulation'),
('Stratégie');

-- --------------------------------------------------------

--
-- Structure de la table `hardware`
--

DROP TABLE IF EXISTS `hardware`;
CREATE TABLE IF NOT EXISTS `hardware` (
  `code` varchar(10) COLLATE utf8_unicode_520_ci NOT NULL,
  `full_name` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

--
-- Déchargement des données de la table `hardware`
--

INSERT INTO `hardware` (`code`, `full_name`) VALUES
('3DS', 'Nintendo 3DS'),
('DS', 'Nintendo DS'),
('PC', 'PC'),
('PS3', 'PlayStation 3'),
('PS4', 'PlayStation 4'),
('PSP', 'PlayStation Portable'),
('PSV', 'PlayStation Vita'),
('Switch', 'Nintendo Switch'),
('Wii', 'Nintendo Wii'),
('WiiU', 'Nintendo Wii U'),
('X360', 'Xbox 360'),
('XONE', 'Xbox One');

-- --------------------------------------------------------

--
-- Structure de la table `invitations`
--

DROP TABLE IF EXISTS `invitations`;
CREATE TABLE IF NOT EXISTS `invitations` (
  `invitation_key` varchar(15) COLLATE utf8_unicode_520_ci NOT NULL,
  `sponsor` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `guest_email` varchar(125) COLLATE utf8_unicode_520_ci NOT NULL,
  `emission_date` datetime NOT NULL,
  `last_email` datetime NOT NULL,
  PRIMARY KEY (`invitation_key`),
  UNIQUE KEY `key` (`invitation_key`),
  UNIQUE KEY `guest` (`guest_email`),
  KEY `invitations_ibfk_1` (`sponsor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `lists`
--

DROP TABLE IF EXISTS `lists`;
CREATE TABLE IF NOT EXISTS `lists` (
  `id_commentable` int(10) UNSIGNED NOT NULL,
  `description` text NOT NULL,
  `ordering` set('default','top') NOT NULL DEFAULT 'default',
  PRIMARY KEY (`id_commentable`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Structure de la table `map_aliases`
--

DROP TABLE IF EXISTS `map_aliases`;
CREATE TABLE IF NOT EXISTS `map_aliases` (
  `tag` varchar(100) COLLATE utf8_unicode_520_ci NOT NULL,
  `alias` varchar(100) COLLATE utf8_unicode_520_ci NOT NULL,
  UNIQUE KEY `alias` (`tag`,`alias`),
  KEY `map_aliases_ibfk_2` (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

--
-- Déchargement des données de la table `map_aliases`
--

INSERT INTO `map_aliases` (`tag`, `alias`) VALUES
('AM2R', 'Another Metroid 2 Remake'),
('Metroid 2', 'Another Metroid 2 Remake'),
('Metroid II', 'Another Metroid 2 Remake'),
('BB', 'Bloodborne'),
('BB', 'Bloodborne: The Old Hunters'),
('TOH', 'Bloodborne: The Old Hunters'),
('DkS', 'Dark Souls'),
('DS', 'Dark Souls'),
('Dark Souls 2', 'Dark Souls II'),
('DkS', 'Dark Souls II'),
('DkS 2', 'Dark Souls II'),
('DkS II', 'Dark Souls II'),
('DkS2', 'Dark Souls II'),
('DkSII', 'Dark Souls II'),
('DS2', 'Dark Souls II'),
('DSII', 'Dark Souls II'),
('Dark Souls 2', 'Dark Souls II: Crown of the Ivory King'),
('DaS II', 'Dark Souls II: Crown of the Ivory King'),
('DaS2', 'Dark Souls II: Crown of the Ivory King'),
('DS II', 'Dark Souls II: Crown of the Ivory King'),
('DS2', 'Dark Souls II: Crown of the Ivory King'),
('Lost Crowns', 'Dark Souls II: Crown of the Ivory King'),
('Dark Souls 2', 'Dark Souls II: Crown of the Old Iron King'),
('DaS II', 'Dark Souls II: Crown of the Old Iron King'),
('DaS2', 'Dark Souls II: Crown of the Old Iron King'),
('DS II', 'Dark Souls II: Crown of the Old Iron King'),
('DS2', 'Dark Souls II: Crown of the Old Iron King'),
('Lost Crowns', 'Dark Souls II: Crown of the Old Iron King'),
('Dark Souls 2', 'Dark Souls II: Crown of the Sunken King'),
('DaS II', 'Dark Souls II: Crown of the Sunken King'),
('DaS2', 'Dark Souls II: Crown of the Sunken King'),
('DS II', 'Dark Souls II: Crown of the Sunken King'),
('DS2', 'Dark Souls II: Crown of the Sunken King'),
('Lost Crowns', 'Dark Souls II: Crown of the Sunken King'),
('Dark Souls 2', 'Dark Souls II: Scholar of the First Sin'),
('DaS II', 'Dark Souls II: Scholar of the First Sin'),
('DaS2', 'Dark Souls II: Scholar of the First Sin'),
('DS II', 'Dark Souls II: Scholar of the First Sin'),
('DS2', 'Dark Souls II: Scholar of the First Sin'),
('SOTFS', 'Dark Souls II: Scholar of the First Sin'),
('DaS', 'Dark Souls III'),
('DaS III', 'Dark Souls III'),
('DaS3', 'Dark Souls III'),
('DS', 'Dark Souls III'),
('DS III', 'Dark Souls III'),
('DS3', 'Dark Souls III'),
('AoA', 'Dark Souls III: Ashes of Ariandel'),
('DaS III', 'Dark Souls III: Ashes of Ariandel'),
('DaS3', 'Dark Souls III: Ashes of Ariandel'),
('DS III', 'Dark Souls III: Ashes of Ariandel'),
('DS3', 'Dark Souls III: Ashes of Ariandel'),
('DaS III', 'Dark Souls III: The Ringed City'),
('DaS3', 'Dark Souls III: The Ringed City'),
('DS III', 'Dark Souls III: The Ringed City'),
('DS3', 'Dark Souls III: The Ringed City'),
('TRC', 'Dark Souls III: The Ringed City'),
('DeS', 'Demon\'s Souls'),
('DS', 'Demon\'s Souls'),
('GR2', 'Gravity Rush 2'),
('Gravity Daze', 'Gravity Rush 2'),
('HK', 'Hollow Knight'),
('MG Rising', 'Metal Gear Rising: Revengeance'),
('MGR', 'Metal Gear Rising: Revengeance'),
('Metroid 2', 'Metroid: Samus Returns'),
('Metroid II', 'Metroid: Samus Returns'),
('Metroid SR', 'Metroid: Samus Returns'),
('MSR', 'Metroid: Samus Returns'),
('MH3 Ultimate', 'Monster Hunter 3 Ultimate'),
('MH3U', 'Monster Hunter 3 Ultimate'),
('MonHun', 'Monster Hunter 3 Ultimate'),
('Monster Hunter Tri Ultimate', 'Monster Hunter 3 Ultimate'),
('MH', 'Monster Hunter 4 Ultimate'),
('MH4U', 'Monster Hunter 4 Ultimate'),
('MonHun', 'Monster Hunter 4 Ultimate'),
('MHW', 'Monster Hunter World'),
('MonHun', 'Monster Hunter World'),
('MonHun World', 'Monster Hunter World'),
('SOTC', 'Shadow of the Colossus (Remake)'),
('TLOU', 'The Last of Us'),
('Zelda BoTW', 'The Legend of Zelda: Breath of the Wild'),
('OaP', 'The Legend of Zelda: Breath of the Wild - L\'Ode aux Prodiges'),
('TCB', 'The Legend of Zelda: Breath of the Wild - L\'Ode aux Prodiges'),
('Zelda BOTW', 'The Legend of Zelda: Breath of the Wild - L\'Ode aux Prodiges'),
('MM3D', 'The Legend of Zelda: Majora\'s Mask 3D'),
('TLoZ MM 3D', 'The Legend of Zelda: Majora\'s Mask 3D'),
('TLoZ OoT 3D', 'The Legend of Zelda: Ocarina of Time 3D'),
('zelda ss', 'The Legend of Zelda: Skyward Sword'),
('zelda tww', 'The Legend of Zelda: The Wind Waker'),
('TLoZ TWW HD', 'The Legend of Zelda: The Wind Waker HD'),
('zelda tp', 'The Legend of Zelda: Twilight Princess'),
('TLoZ TP', 'The Legend of Zelda: Twilight Princess HD');

-- --------------------------------------------------------

--
-- Structure de la table `map_emoticons`
--

DROP TABLE IF EXISTS `map_emoticons`;
CREATE TABLE IF NOT EXISTS `map_emoticons` (
  `id_emoticon` int(10) UNSIGNED NOT NULL,
  `pseudo` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `shortcut` varchar(30) COLLATE utf8_unicode_520_ci NOT NULL,
  PRIMARY KEY (`id_emoticon`,`pseudo`),
  UNIQUE KEY `user_mapping` (`pseudo`,`shortcut`) USING BTREE,
  KEY `mapping` (`id_emoticon`,`pseudo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `map_functions`
--

DROP TABLE IF EXISTS `map_functions`;
CREATE TABLE IF NOT EXISTS `map_functions` (
  `function_pseudo` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `function_name` varchar(30) COLLATE utf8_unicode_520_ci NOT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY (`function_pseudo`),
  KEY `function_name` (`function_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

--
-- Déchargement des données de la table `map_functions`
--

INSERT INTO `map_functions` (`function_pseudo`, `function_name`, `date`) VALUES
('Admin', 'administrator', '2013-12-30 00:00:00');

-- --------------------------------------------------------

--
-- Structure de la table `map_lists_games`
--

DROP TABLE IF EXISTS `map_lists_games`;
CREATE TABLE IF NOT EXISTS `map_lists_games` (
  `id_item` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_commentable` int(10) UNSIGNED NOT NULL,
  `game` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_520_ci NOT NULL,
  `subtitle` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_520_ci DEFAULT NULL COMMENT 'Optional title to replace the name of the game.',
  `comment` text CHARACTER SET utf8 COLLATE utf8_unicode_520_ci NOT NULL COMMENT 'Comment about why this game is in the list.',
  `rank` tinyint(3) UNSIGNED NOT NULL COMMENT 'To list items following a specific order chosen by the user.',
  PRIMARY KEY (`id_item`),
  UNIQUE KEY `list_game_tuple` (`id_commentable`,`game`),
  KEY `map_lists_games_ibfk_2` (`game`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Structure de la table `map_pings`
--

DROP TABLE IF EXISTS `map_pings`;
CREATE TABLE IF NOT EXISTS `map_pings` (
  `pseudo` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `id_ping` int(10) UNSIGNED NOT NULL,
  `viewed` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL,
  `last_update` datetime NOT NULL,
  PRIMARY KEY (`pseudo`,`id_ping`),
  UNIQUE KEY `pseudo` (`pseudo`,`id_ping`),
  KEY `id_ping` (`id_ping`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `map_tags`
--

DROP TABLE IF EXISTS `map_tags`;
CREATE TABLE IF NOT EXISTS `map_tags` (
  `tag` varchar(100) COLLATE utf8_unicode_520_ci NOT NULL,
  `id_topic` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`tag`,`id_topic`),
  UNIQUE KEY `tag` (`tag`,`id_topic`),
  KEY `id_topic` (`id_topic`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

--
-- Déchargement des données de la table `map_tags`
--

INSERT INTO `map_tags` (`tag`, `id_topic`) VALUES
('Ipsum', 1),
('Lorem', 1);

-- --------------------------------------------------------

--
-- Structure de la table `map_tags_articles`
--

DROP TABLE IF EXISTS `map_tags_articles`;
CREATE TABLE IF NOT EXISTS `map_tags_articles` (
  `tag` varchar(100) COLLATE utf8_unicode_520_ci NOT NULL,
  `id_article` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`tag`,`id_article`),
  UNIQUE KEY `tag` (`tag`,`id_article`),
  KEY `id_article` (`id_article`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `map_topics_users`
--

DROP TABLE IF EXISTS `map_topics_users`;
CREATE TABLE IF NOT EXISTS `map_topics_users` (
  `id_topic` int(10) UNSIGNED NOT NULL,
  `pseudo` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `favorite` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'no',
  `last_seen` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`id_topic`,`pseudo`),
  UNIQUE KEY `pseudo` (`id_topic`,`pseudo`),
  KEY `id_topic` (`id_topic`),
  KEY `map_topics_users_ibfk_1` (`pseudo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

--
-- Déchargement des données de la table `map_topics_users`
--

INSERT INTO `map_topics_users` (`id_topic`, `pseudo`, `favorite`, `last_seen`) VALUES
(1, 'AlainTouring', 'no', 1);

-- --------------------------------------------------------

--
-- Structure de la table `map_tropes_reviews`
--

DROP TABLE IF EXISTS `map_tropes_reviews`;
CREATE TABLE IF NOT EXISTS `map_tropes_reviews` (
  `tag` varchar(100) COLLATE utf8_unicode_520_ci NOT NULL,
  `id_commentable` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`tag`,`id_commentable`),
  UNIQUE KEY `tag` (`tag`,`id_commentable`),
  KEY `id_commentable` (`id_commentable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `pings`
--

DROP TABLE IF EXISTS `pings`;
CREATE TABLE IF NOT EXISTS `pings` (
  `id_ping` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `emitter` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `receiver` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `ping_type` set('ping pong','friendship request','notification') COLLATE utf8_unicode_520_ci NOT NULL,
  `state` set('pending','cancelled','archived') COLLATE utf8_unicode_520_ci NOT NULL,
  `title` varchar(50) COLLATE utf8_unicode_520_ci NOT NULL,
  `message` text COLLATE utf8_unicode_520_ci NOT NULL,
  `emission_date` datetime NOT NULL,
  PRIMARY KEY (`id_ping`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `pongs`
--

DROP TABLE IF EXISTS `pongs`;
CREATE TABLE IF NOT EXISTS `pongs` (
  `id_pong` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_ping` int(11) UNSIGNED NOT NULL,
  `author` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `ip_author` varchar(50) COLLATE utf8_unicode_520_ci NOT NULL,
  `date` datetime NOT NULL,
  `message` text COLLATE utf8_unicode_520_ci NOT NULL,
  PRIMARY KEY (`id_pong`),
  KEY `pongs_ibfk_1` (`id_ping`),
  KEY `pongs_ibfk_2` (`author`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `posts`
--

DROP TABLE IF EXISTS `posts`;
CREATE TABLE IF NOT EXISTS `posts` (
  `id_post` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_topic` int(10) UNSIGNED NOT NULL,
  `author` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `ip_author` varchar(50) COLLATE utf8_unicode_520_ci DEFAULT NULL,
  `date` datetime NOT NULL,
  `content` longtext COLLATE utf8_unicode_520_ci NOT NULL,
  `last_edit` datetime DEFAULT NULL,
  `last_editor` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `nb_edits` tinyint(3) UNSIGNED NOT NULL,
  `nb_likes` smallint(5) UNSIGNED NOT NULL,
  `nb_dislikes` smallint(5) UNSIGNED NOT NULL,
  `bad_score` smallint(4) NOT NULL,
  `posted_as` set('anonymous','regular user','administrator','author') COLLATE utf8_unicode_520_ci NOT NULL,
  `attachment` text COLLATE utf8_unicode_520_ci,
  PRIMARY KEY (`id_post`),
  KEY `id_topic` (`id_topic`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

--
-- Déchargement des données de la table `posts`
--

INSERT INTO `posts` (`id_post`, `id_topic`, `author`, `ip_author`, `date`, `content`, `last_edit`, `last_editor`, `nb_edits`, `nb_likes`, `nb_dislikes`, `bad_score`, `posted_as`, `attachment`) VALUES
(1, 1, 'AlainTouring', '::1', '2019-02-01 12:05:00', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque scelerisque nisl sit amet neque varius, in finibus erat venenatis. Aenean accumsan in lacus vel fermentum. Cras gravida molestie nisi non pretium. Cras massa nibh, vestibulum a justo quis, elementum commodo dolor. Aliquam ut justo purus. Donec tincidunt, est quis ornare ultricies, felis turpis pharetra sem, sit amet consequat lorem lectus id elit. Proin ornare interdum ante sed sagittis. Curabitur eget risus vel nulla pharetra molestie. Donec tempor odio id auctor pulvinar. Nulla pulvinar, lorem eget varius fermentum, nisl leo ultricies neque, vel vestibulum lectus nunc non sapien.<br />\r\n<br />\r\nPhasellus eros augue, auctor at tincidunt sit amet, scelerisque at justo. Vivamus at mi sodales, sodales libero ultrices, elementum leo. Etiam vitae condimentum neque. Sed quis eleifend tortor. Sed pharetra lacus nisi, sit amet tincidunt arcu egestas in. Maecenas eget nunc ullamcorper justo consequat congue vitae non mi. Etiam id maximus ex, a viverra leo. Nam sagittis malesuada purus, congue cursus ex consectetur fringilla. Praesent metus velit, malesuada eu nunc eu, tristique lacinia odio. Nunc dolor odio, efficitur laoreet gravida in, congue ut elit. Sed vehicula tortor mi, vitae fermentum felis luctus vel. Vestibulum tristique risus ut ligula tristique rhoncus. Suspendisse nec nunc sed tellus elementum venenatis a at lorem. Nulla sit amet ipsum nibh. Etiam suscipit aliquam feugiat.<br />\r\n<br />\r\nCurabitur erat tellus, egestas at convallis lobortis, pulvinar vel purus. Etiam nulla lectus, placerat sed gravida a, sollicitudin vitae metus. Suspendisse odio nisl, interdum non libero in, blandit cursus sapien. Duis mauris est, fringilla quis vulputate vel, malesuada sed lectus. Proin vitae ultricies sapien, ac ultricies metus. Proin tincidunt viverra faucibus. Sed sed orci vitae ligula iaculis consectetur quis suscipit nunc. In quis ornare sem, vel venenatis lorem.<br />\r\n<br />\r\nCurabitur consectetur posuere neque eget pellentesque. Integer finibus, lorem ac scelerisque imperdiet, dolor nibh commodo tellus, nec hendrerit mauris lacus et arcu. Fusce vitae interdum lorem, eu luctus erat. Quisque vitae molestie turpis. Ut vel venenatis velit, quis semper nunc. Etiam in mi leo. Quisque eleifend velit nec lacinia suscipit. Etiam ut eros nisi. Nullam gravida mauris eget quam porttitor pretium. Ut at condimentum ligula. Curabitur tristique lorem tristique, iaculis diam at, pellentesque magna. Nulla facilisi. Maecenas magna libero, tincidunt eu aliquam et, dignissim eu elit.<br />\r\n<br />\r\nSed id ultricies mauris. Morbi eu velit ipsum. Vivamus feugiat velit lectus. Mauris efficitur varius tortor. Donec sit amet facilisis lacus, non feugiat enim. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; In hac habitasse platea dictumst. Donec tempus tortor ac velit facilisis volutpat. Phasellus eu sem nec lorem luctus vulputate quis nec nunc. Vestibulum vitae interdum elit. Maecenas efficitur dapibus vulputate. Etiam ultrices eros in orci sagittis, at facilisis quam molestie. ', '1970-01-01 00:00:00', '', 0, 0, 0, 0, 'regular user', '');

-- --------------------------------------------------------

--
-- Structure de la table `posts_history`
--

DROP TABLE IF EXISTS `posts_history`;
CREATE TABLE IF NOT EXISTS `posts_history` (
  `id_post` int(10) UNSIGNED NOT NULL,
  `version` tinyint(3) UNSIGNED NOT NULL,
  `id_topic` int(10) UNSIGNED NOT NULL,
  `author` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `ip_author` varchar(50) COLLATE utf8_unicode_520_ci DEFAULT NULL,
  `editor` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `posted_as` set('anonymous','regular user','administrator') COLLATE utf8_unicode_520_ci NOT NULL,
  `date` datetime NOT NULL,
  `content` longtext COLLATE utf8_unicode_520_ci NOT NULL,
  `attachment` text COLLATE utf8_unicode_520_ci,
  `censorship` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL,
  UNIQUE KEY `id_history` (`id_post`,`version`),
  KEY `posts_history_ibfk_2` (`id_topic`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `posts_interactions`
--

DROP TABLE IF EXISTS `posts_interactions`;
CREATE TABLE IF NOT EXISTS `posts_interactions` (
  `id_interaction` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_post` int(10) UNSIGNED NOT NULL,
  `user` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY (`id_interaction`),
  UNIQUE KEY `couple` (`id_interaction`,`id_post`),
  KEY `posts_interactions_ibfk_2` (`user`),
  KEY `posts_interactions_ibfk_1` (`id_post`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `posts_interactions_alerts`
--

DROP TABLE IF EXISTS `posts_interactions_alerts`;
CREATE TABLE IF NOT EXISTS `posts_interactions_alerts` (
  `id_interaction` int(11) UNSIGNED NOT NULL,
  `motivation` varchar(100) CHARACTER SET latin1 NOT NULL,
  `function_pseudo` varchar(20) CHARACTER SET latin1 DEFAULT NULL,
  PRIMARY KEY (`id_interaction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `posts_interactions_pins`
--

DROP TABLE IF EXISTS `posts_interactions_pins`;
CREATE TABLE IF NOT EXISTS `posts_interactions_pins` (
  `id_interaction` int(10) UNSIGNED NOT NULL,
  `comment` varchar(100) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id_interaction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `posts_interactions_votes`
--

DROP TABLE IF EXISTS `posts_interactions_votes`;
CREATE TABLE IF NOT EXISTS `posts_interactions_votes` (
  `id_interaction` int(11) UNSIGNED NOT NULL,
  `vote` tinyint(4) NOT NULL,
  PRIMARY KEY (`id_interaction`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `records_sentences`
--

DROP TABLE IF EXISTS `records_sentences`;
CREATE TABLE IF NOT EXISTS `records_sentences` (
  `pseudo` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `judge` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `date` datetime NOT NULL,
  `duration` int(11) NOT NULL,
  `expiration` datetime NOT NULL,
  `relaxed` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'no',
  `details` text COLLATE utf8_unicode_520_ci NOT NULL,
  UNIQUE KEY `pseudo` (`pseudo`,`judge`,`date`,`duration`),
  KEY `records_banishment_ibfk_2` (`judge`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
CREATE TABLE IF NOT EXISTS `reviews` (
  `id_commentable` int(10) UNSIGNED NOT NULL,
  `game` varchar(100) COLLATE utf8_unicode_520_ci NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `comment` text COLLATE utf8_unicode_520_ci NOT NULL,
  `associated_tropes` varchar(510) COLLATE utf8_unicode_520_ci NOT NULL COMMENT 'Spares the effort of a SQL request for each review when listing a bunch of reviews.',
  `id_article` int(10) UNSIGNED DEFAULT NULL,
  `external_link` varchar(300) COLLATE utf8_unicode_520_ci DEFAULT NULL,
  PRIMARY KEY (`id_commentable`),
  KEY `reviews_ibfk_2` (`game`),
  KEY `reviews_ibfk_3` (`id_article`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tags`
--

DROP TABLE IF EXISTS `tags`;
CREATE TABLE IF NOT EXISTS `tags` (
  `tag` varchar(100) COLLATE utf8_unicode_520_ci NOT NULL,
  PRIMARY KEY (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

--
-- Déchargement des données de la table `tags`
--

INSERT INTO `tags` (`tag`) VALUES
('AM2R'),
('Another Metroid 2 Remake'),
('AoA'),
('BB'),
('Bloodborne'),
('Bloodborne: The Old Hunters'),
('Collectathon'),
('Dark Souls'),
('Dark Souls 2'),
('Dark Souls II'),
('Dark Souls II: Crown of the Ivory King'),
('Dark Souls II: Crown of the Old Iron King'),
('Dark Souls II: Crown of the Sunken King'),
('Dark Souls II: Scholar of the First Sin'),
('Dark Souls III'),
('Dark Souls III: Ashes of Ariandel'),
('Dark Souls III: The Ringed City'),
('DaS'),
('DaS II'),
('DaS III'),
('DaS2'),
('DaS3'),
('Demon\'s Souls'),
('DeS'),
('Die and retry'),
('DkS'),
('DkS 2'),
('DkS II'),
('DkS2'),
('DkSII'),
('DS'),
('DS II'),
('DS III'),
('DS2'),
('DS3'),
('DSII'),
('En solitaire'),
('Exploration'),
('GR2'),
('Gravity Daze'),
('Gravity Rush 2'),
('Histoire avec un grand H'),
('HK'),
('Hollow Knight'),
('Ipsum'),
('Jeu cinématique'),
('Lorem'),
('Lost Crowns'),
('Metal Gear Rising: Revengeance'),
('Metroid 2'),
('Metroid II'),
('Metroid SR'),
('Metroid: Samus Returns'),
('MG Rising'),
('MGR'),
('MH'),
('MH3 Ultimate'),
('MH3U'),
('MH4U'),
('MHW'),
('MM3D'),
('MonHun'),
('MonHun World'),
('Monster Hunter 3 Ultimate'),
('Monster Hunter 4 Ultimate'),
('Monster Hunter Tri Ultimate'),
('Monster Hunter World'),
('MSR'),
('Mythes et folklore'),
('Nature et découverte'),
('OaP'),
('Shadow of the Colossus (Remake)'),
('SOTC'),
('SOTFS'),
('TCB'),
('The Last of Us'),
('The Legend of Zelda: Breath of the Wild'),
('The Legend of Zelda: Breath of the Wild - L\'Ode aux Prodiges'),
('The Legend of Zelda: Majora\'s Mask 3D'),
('The Legend of Zelda: Ocarina of Time 3D'),
('The Legend of Zelda: Skyward Sword'),
('The Legend of Zelda: The Wind Waker'),
('The Legend of Zelda: The Wind Waker HD'),
('The Legend of Zelda: Twilight Princess'),
('The Legend of Zelda: Twilight Princess HD'),
('TLOU'),
('TLoZ MM 3D'),
('TLoZ OoT 3D'),
('TLoZ TP'),
('TLoZ TWW HD'),
('TOH'),
('TRC'),
('Zelda BoTW'),
('zelda ss'),
('zelda tp'),
('zelda tww');

-- --------------------------------------------------------

--
-- Structure de la table `topics`
--

DROP TABLE IF EXISTS `topics`;
CREATE TABLE IF NOT EXISTS `topics` (
  `id_topic` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(50) COLLATE utf8_unicode_520_ci DEFAULT NULL,
  `thumbnail` varchar(50) COLLATE utf8_unicode_520_ci DEFAULT NULL,
  `author` varchar(20) COLLATE utf8_unicode_520_ci DEFAULT NULL,
  `created_as` set('regular user','administrator','author') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'regular user',
  `date` datetime DEFAULT NULL,
  `type` tinyint(3) UNSIGNED DEFAULT NULL,
  `is_anon_posting_enabled` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'no',
  `uploads_enabled` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'no',
  `is_locked` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'no',
  `is_marked` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'no',
  `last_post` datetime DEFAULT NULL,
  `last_author` varchar(20) COLLATE utf8_unicode_520_ci DEFAULT NULL,
  `posted_as` set('anonymous','regular user','administrator','author') COLLATE utf8_unicode_520_ci DEFAULT NULL,
  PRIMARY KEY (`id_topic`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

--
-- Déchargement des données de la table `topics`
--

INSERT INTO `topics` (`id_topic`, `title`, `thumbnail`, `author`, `created_as`, `date`, `type`, `is_anon_posting_enabled`, `uploads_enabled`, `is_locked`, `is_marked`, `last_post`, `last_author`, `posted_as`) VALUES
(1, 'Lorem ipsum dolor sit amet...', 'CUSTOM', 'AlainTouring', 'regular user', '2019-02-01 12:05:00', 1, 'no', 'yes', 'no', 'no', '2019-02-01 12:05:00', 'AlainTouring', 'regular user');

-- --------------------------------------------------------

--
-- Structure de la table `trivia`
--

DROP TABLE IF EXISTS `trivia`;
CREATE TABLE IF NOT EXISTS `trivia` (
  `id_commentable` int(10) UNSIGNED NOT NULL,
  `game` varchar(100) COLLATE utf8_unicode_520_ci NOT NULL,
  `content` text COLLATE utf8_unicode_520_ci NOT NULL,
  PRIMARY KEY (`id_commentable`),
  KEY `trivia_ibfk_2` (`game`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tropes`
--

DROP TABLE IF EXISTS `tropes`;
CREATE TABLE IF NOT EXISTS `tropes` (
  `tag` varchar(100) COLLATE utf8_unicode_520_ci NOT NULL,
  `color` varchar(10) COLLATE utf8_unicode_520_ci NOT NULL DEFAULT '#BFBFBF',
  `description` varchar(250) COLLATE utf8_unicode_520_ci NOT NULL,
  PRIMARY KEY (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

--
-- Déchargement des données de la table `tropes`
--

INSERT INTO `tropes` (`tag`, `color`, `description`) VALUES
('Collectathon', '#63c166', 'Chaque recoin du monde regorge d\'objets uniques à dénicher. En explorant, vous augmenterez vos réserves d\'objets et pourrez débloquer de nouveaux pouvoirs, restaurer votre énergie et ouvrir l\'accès à de nouveaux mondes !'),
('Die and retry', '#6c6b6b', 'Dans ce jeu, les erreurs du joueur sont très vite sanctionnées et l\'échec fait partie intégrante de l\'apprentissage du joueur, d\'où le terme &quot;die and retry&quot; (&quot;meurs et réessaye&quot;). Souvent associé à des jeux old school.'),
('En solitaire', '#ac2f3e', 'Ce jeu met l\'accent sur la solitude du joueur et évite autant que possible toute cinématique, dialogue ou indice. Le joueur y est souvent livré à lui-même, tant sur le plan ludique que scénaristique.'),
('Exploration', '#6f90c6', 'Ce jeu comporte des zones ouvertes que le joueur pourra visiter pour glaner diverses récompenses, progresser dans sa quête ou simplement flâner. Propice à une progression non-linéaire.'),
('Histoire avec un grand H', '#8767ff', 'L\'univers du jeu et/ou son scénario s\'inspirent d\'évènements historiques. Selon les intentions des développeurs, la réalité historique peut être altérée pour y insérer un scénario inédit ou expliquer des éléments du gameplay.'),
('Jeu cinématique', '#ba1bff', 'Ce jeu est porté sur le développement de son scénario, via un grand nombre de cinématiques et dialogues prenant parfois le pas sur les phases de gameplay. Ce type de jeu emprunte souvent des codes au 7e art.'),
('Mythes et folklore', '#a578f5', 'L\'univers de ce jeu se base en partie ou en totalité sur une mythologie existante ou sur le folklore d\'un pays. Parfois, des quêtes entières se baseront sur des contes et légendes populaires tirés de ceux-ci.'),
('Nature et découverte', '#00af00', 'L\'environnement graphique de ce jeu met l\'accent sur la richesse et/ou la variété de sa faune et de sa flore. Il est souvent possible d\'interagir avec celles-ci pour progresser dans le jeu ou obtenir des avantages.');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `pseudo` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `email` varchar(125) COLLATE utf8_unicode_520_ci NOT NULL,
  `secret` varchar(15) COLLATE utf8_unicode_520_ci NOT NULL,
  `password` varchar(255) COLLATE utf8_unicode_520_ci NOT NULL COMMENT 'Normally 60 characters.',
  `confirmation` varchar(15) COLLATE utf8_unicode_520_ci NOT NULL,
  `registration_date` datetime NOT NULL,
  `last_connection` datetime NOT NULL,
  `advanced_features` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'no',
  `function_pseudo` varchar(20) COLLATE utf8_unicode_520_ci DEFAULT NULL,
  `pwd_reset_attempts` smallint(6) NOT NULL,
  `pwd_reset_last_attempt` datetime NOT NULL,
  `last_ban_expiration` datetime NOT NULL,
  `using_preferences` set('yes','no') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'no',
  `pref_message_size` set('default','medium') COLLATE utf8_unicode_520_ci NOT NULL,
  `pref_posts_per_page` tinyint(3) UNSIGNED NOT NULL DEFAULT '20',
  `pref_video_default_display` set('embedded','thumbnail') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'thumbnail',
  `pref_video_thumbnail_style` set('hq','small') COLLATE utf8_unicode_520_ci NOT NULL DEFAULT 'hq',
  `pref_default_nav_mode` set('classic','dynamic','flow') COLLATE utf8_unicode_520_ci DEFAULT 'classic',
  `pref_auto_preview` set('yes','no') COLLATE utf8_unicode_520_ci DEFAULT 'no',
  `pref_auto_refresh` set('yes','no') COLLATE utf8_unicode_520_ci DEFAULT 'no',
  PRIMARY KEY (`pseudo`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`pseudo`, `email`, `secret`, `password`, `confirmation`, `registration_date`, `last_connection`, `advanced_features`, `function_pseudo`, `pwd_reset_attempts`, `pwd_reset_last_attempt`, `last_ban_expiration`, `using_preferences`, `pref_message_size`, `pref_posts_per_page`, `pref_video_default_display`, `pref_video_thumbnail_style`, `pref_default_nav_mode`, `pref_auto_preview`, `pref_auto_refresh`) VALUES
('AlainTouring', 'admin@projectag.org', '0123456789abcde', '$2y$12$NSdC6oHXeHvPi1ZztEi7HOLUreZ6P2.TRfkXdduy6wD1999OCtPcm', 'DONE', '2019-02-01 12:00:00', '2019-02-01 12:05:30', 'yes', 'Admin', 0, '1970-01-01 00:00:00', '1970-01-01 00:00:00', 'no', 'default', 20, 'thumbnail', 'hq', 'classic', 'no', 'no');

-- --------------------------------------------------------

--
-- Structure de la table `users_presentations`
--

DROP TABLE IF EXISTS `users_presentations`;
CREATE TABLE IF NOT EXISTS `users_presentations` (
  `pseudo` varchar(20) COLLATE utf8_unicode_520_ci NOT NULL,
  `presentation` text COLLATE utf8_unicode_520_ci NOT NULL,
  PRIMARY KEY (`pseudo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_520_ci COMMENT='To motivate quick access to advanced features (optional)';

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `articles`
--
ALTER TABLE `articles`
  ADD CONSTRAINT `articles_ibfk_1` FOREIGN KEY (`pseudo`) REFERENCES `users` (`pseudo`);

--
-- Contraintes pour la table `articles_segments`
--
ALTER TABLE `articles_segments`
  ADD CONSTRAINT `articles_segments_ibfk_1` FOREIGN KEY (`id_article`) REFERENCES `articles` (`id_article`) ON DELETE CASCADE,
  ADD CONSTRAINT `articles_segments_ibfk_2` FOREIGN KEY (`pseudo`) REFERENCES `users` (`pseudo`);

--
-- Contraintes pour la table `commentables`
--
ALTER TABLE `commentables`
  ADD CONSTRAINT `commentables_ibkf_1` FOREIGN KEY (`id_topic`) REFERENCES `topics` (`id_topic`) ON DELETE SET NULL,
  ADD CONSTRAINT `commentables_ibkf_2` FOREIGN KEY (`pseudo`) REFERENCES `users` (`pseudo`);

--
-- Contraintes pour la table `commentables_ratings`
--
ALTER TABLE `commentables_ratings`
  ADD CONSTRAINT `ratings_ibkf_1` FOREIGN KEY (`id_commentable`) REFERENCES `commentables` (`id_commentable`) ON DELETE CASCADE,
  ADD CONSTRAINT `ratings_ibkf_2` FOREIGN KEY (`user`) REFERENCES `users` (`pseudo`);

--
-- Contraintes pour la table `emoticons`
--
ALTER TABLE `emoticons`
  ADD CONSTRAINT `emoticons_ibfk_1` FOREIGN KEY (`uploader`) REFERENCES `users` (`pseudo`);

--
-- Contraintes pour la table `games`
--
ALTER TABLE `games`
  ADD CONSTRAINT `games_ibfk_1` FOREIGN KEY (`tag`) REFERENCES `tags` (`tag`) ON DELETE CASCADE,
  ADD CONSTRAINT `games_ibfk_2` FOREIGN KEY (`genre`) REFERENCES `genres` (`genre`);

--
-- Contraintes pour la table `invitations`
--
ALTER TABLE `invitations`
  ADD CONSTRAINT `invitations_ibfk_1` FOREIGN KEY (`sponsor`) REFERENCES `users` (`pseudo`);

--
-- Contraintes pour la table `lists`
--
ALTER TABLE `lists`
  ADD CONSTRAINT `lists_ibfk_1` FOREIGN KEY (`id_commentable`) REFERENCES `commentables` (`id_commentable`) ON DELETE CASCADE;

--
-- Contraintes pour la table `map_aliases`
--
ALTER TABLE `map_aliases`
  ADD CONSTRAINT `map_aliases_ibfk_1` FOREIGN KEY (`tag`) REFERENCES `tags` (`tag`),
  ADD CONSTRAINT `map_aliases_ibfk_2` FOREIGN KEY (`alias`) REFERENCES `tags` (`tag`);

--
-- Contraintes pour la table `map_emoticons`
--
ALTER TABLE `map_emoticons`
  ADD CONSTRAINT `map_emoticons_ibfk_1` FOREIGN KEY (`id_emoticon`) REFERENCES `emoticons` (`id_emoticon`) ON DELETE CASCADE,
  ADD CONSTRAINT `map_emoticons_ibfk_2` FOREIGN KEY (`pseudo`) REFERENCES `users` (`pseudo`);

--
-- Contraintes pour la table `map_functions`
--
ALTER TABLE `map_functions`
  ADD CONSTRAINT `map_functions_ibfk_1` FOREIGN KEY (`function_name`) REFERENCES `functions` (`function_name`);

--
-- Contraintes pour la table `map_lists_games`
--
ALTER TABLE `map_lists_games`
  ADD CONSTRAINT `map_lists_games_ibfk_1` FOREIGN KEY (`id_commentable`) REFERENCES `lists` (`id_commentable`) ON DELETE CASCADE,
  ADD CONSTRAINT `map_lists_games_ibfk_2` FOREIGN KEY (`game`) REFERENCES `games` (`tag`);

--
-- Contraintes pour la table `map_pings`
--
ALTER TABLE `map_pings`
  ADD CONSTRAINT `map_pings_ibfk_1` FOREIGN KEY (`pseudo`) REFERENCES `users` (`pseudo`),
  ADD CONSTRAINT `map_pings_ibfk_2` FOREIGN KEY (`id_ping`) REFERENCES `pings` (`id_ping`);

--
-- Contraintes pour la table `map_tags`
--
ALTER TABLE `map_tags`
  ADD CONSTRAINT `map_tags_ibfk_1` FOREIGN KEY (`tag`) REFERENCES `tags` (`tag`),
  ADD CONSTRAINT `map_tags_ibfk_2` FOREIGN KEY (`id_topic`) REFERENCES `topics` (`id_topic`) ON DELETE CASCADE;

--
-- Contraintes pour la table `map_tags_articles`
--
ALTER TABLE `map_tags_articles`
  ADD CONSTRAINT `map_tags_articles_ibfk_1` FOREIGN KEY (`tag`) REFERENCES `tags` (`tag`),
  ADD CONSTRAINT `map_tags_articles_ibfk_2` FOREIGN KEY (`id_article`) REFERENCES `articles` (`id_article`) ON DELETE CASCADE;

--
-- Contraintes pour la table `map_topics_users`
--
ALTER TABLE `map_topics_users`
  ADD CONSTRAINT `map_topics_users_ibfk_1` FOREIGN KEY (`pseudo`) REFERENCES `users` (`pseudo`),
  ADD CONSTRAINT `map_topics_users_ibfk_2` FOREIGN KEY (`id_topic`) REFERENCES `topics` (`id_topic`) ON DELETE CASCADE;

--
-- Contraintes pour la table `map_tropes_reviews`
--
ALTER TABLE `map_tropes_reviews`
  ADD CONSTRAINT `map_tropes_reviews_ibfk_1` FOREIGN KEY (`tag`) REFERENCES `tropes` (`tag`) ON DELETE CASCADE,
  ADD CONSTRAINT `map_tropes_reviews_ibfk_2` FOREIGN KEY (`id_commentable`) REFERENCES `reviews` (`id_commentable`) ON DELETE CASCADE;

--
-- Contraintes pour la table `pongs`
--
ALTER TABLE `pongs`
  ADD CONSTRAINT `pongs_ibfk_1` FOREIGN KEY (`id_ping`) REFERENCES `pings` (`id_ping`) ON DELETE CASCADE,
  ADD CONSTRAINT `pongs_ibfk_2` FOREIGN KEY (`author`) REFERENCES `users` (`pseudo`);

--
-- Contraintes pour la table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`id_topic`) REFERENCES `topics` (`id_topic`) ON DELETE CASCADE;

--
-- Contraintes pour la table `posts_history`
--
ALTER TABLE `posts_history`
  ADD CONSTRAINT `posts_history_ibfk_1` FOREIGN KEY (`id_post`) REFERENCES `posts` (`id_post`) ON DELETE CASCADE,
  ADD CONSTRAINT `posts_history_ibfk_2` FOREIGN KEY (`id_topic`) REFERENCES `topics` (`id_topic`) ON DELETE CASCADE;

--
-- Contraintes pour la table `posts_interactions`
--
ALTER TABLE `posts_interactions`
  ADD CONSTRAINT `posts_interactions_ibfk_1` FOREIGN KEY (`id_post`) REFERENCES `posts` (`id_post`) ON DELETE CASCADE,
  ADD CONSTRAINT `posts_interactions_ibfk_2` FOREIGN KEY (`user`) REFERENCES `users` (`pseudo`);

--
-- Contraintes pour la table `posts_interactions_alerts`
--
ALTER TABLE `posts_interactions_alerts`
  ADD CONSTRAINT `posts_interactions_alerts_ibfk_1` FOREIGN KEY (`id_interaction`) REFERENCES `posts_interactions` (`id_interaction`) ON DELETE CASCADE;

--
-- Contraintes pour la table `posts_interactions_pins`
--
ALTER TABLE `posts_interactions_pins`
  ADD CONSTRAINT `posts_interactions_pins_ibfk_1` FOREIGN KEY (`id_interaction`) REFERENCES `posts_interactions` (`id_interaction`) ON DELETE CASCADE;

--
-- Contraintes pour la table `posts_interactions_votes`
--
ALTER TABLE `posts_interactions_votes`
  ADD CONSTRAINT `posts_interactions_votes_ibfk_1` FOREIGN KEY (`id_interaction`) REFERENCES `posts_interactions` (`id_interaction`) ON DELETE CASCADE;

--
-- Contraintes pour la table `records_sentences`
--
ALTER TABLE `records_sentences`
  ADD CONSTRAINT `records_sentences_ibfk_1` FOREIGN KEY (`pseudo`) REFERENCES `users` (`pseudo`);

--
-- Contraintes pour la table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`id_commentable`) REFERENCES `commentables` (`id_commentable`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`game`) REFERENCES `games` (`tag`),
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`id_article`) REFERENCES `articles` (`id_article`);

--
-- Contraintes pour la table `trivia`
--
ALTER TABLE `trivia`
  ADD CONSTRAINT `trivia_ibfk_1` FOREIGN KEY (`id_commentable`) REFERENCES `commentables` (`id_commentable`) ON DELETE CASCADE,
  ADD CONSTRAINT `trivia_ibfk_2` FOREIGN KEY (`game`) REFERENCES `games` (`tag`);

--
-- Contraintes pour la table `tropes`
--
ALTER TABLE `tropes`
  ADD CONSTRAINT `tropes_ibfk_1` FOREIGN KEY (`tag`) REFERENCES `tags` (`tag`) ON DELETE CASCADE;

--
-- Contraintes pour la table `users_presentations`
--
ALTER TABLE `users_presentations`
  ADD CONSTRAINT `users_presentations_ibfk_1` FOREIGN KEY (`pseudo`) REFERENCES `users` (`pseudo`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
