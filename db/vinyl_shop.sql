-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 01, 2026 at 10:41 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `vinyl_shop`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_place_order` (IN `p_user_id` INT, IN `p_address_id` INT, IN `p_vinyl_id` INT, IN `p_quantity` INT, OUT `p_success` INT, OUT `p_message` VARCHAR(255))   sp_label: BEGIN
    DECLARE v_available_stock INT;
    DECLARE v_item_price DECIMAL(10,2);
    DECLARE v_new_order_id INT;
    
    -- Rollback on any standard SQLEXCEPTION
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = 0;
        SET p_message = 'Transaction aborted: Internal database processing error occurred.';
    END;

    -- Initialize tracking state variables
    SET p_success = 1;
    SET p_message = 'Order processed and recorded successfully.';

    -- Start acid-compliant transaction block
    START TRANSACTION;

    -- 1. Get current item constraints
    SELECT stock, price INTO v_available_stock, v_item_price
    FROM Vinyls
    WHERE vinyl_id = p_vinyl_id;

    -- 2. Validate availability criteria before committing structural inserts
    IF v_available_stock IS NULL THEN
        SET p_success = 0;
        SET p_message = 'Order failure: The requested vinyl target item ID does not exist.';
        ROLLBACK;
        LEAVE sp_label;
    END IF;

    IF v_available_stock < p_quantity THEN
        SET p_success = 0;
        SET p_message = CONCAT('Order failure: Insufficient inventory. Requested: ', p_quantity, ' Available: ', v_available_stock);
        ROLLBACK;
        LEAVE sp_label;
    END IF;

    -- 3. Insert parent record tracking entry
    INSERT INTO Orders (user_id, address_id, total_price, order_date)
    VALUES (p_user_id, p_address_id, (v_item_price * p_quantity), NOW());

    SET v_new_order_id = LAST_INSERT_ID();

    -- 4. Map dependent multi-table child components
    INSERT INTO Order_Items (order_id, vinyl_id, quantity, unit_price)
    VALUES (v_new_order_id, p_vinyl_id, p_quantity, v_item_price);

    -- 5. Cascade structural values to base tables
    UPDATE Vinyls
    SET stock = stock - p_quantity
    WHERE vinyl_id = p_vinyl_id;

    COMMIT;
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_determine_user_tier` (`p_user_id` INT) RETURNS VARCHAR(50) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_lifetime_spending DECIMAL(10,2);
    DECLARE v_tier VARCHAR(50);

    -- Calculate complete financial footprint of past records
    SELECT COALESCE(SUM(total_price), 0.00) INTO v_lifetime_spending
    FROM Orders
    WHERE user_id = p_user_id;

    -- Conditional logic pathways for performance classification
    IF v_lifetime_spending >= 500.00 THEN
        SET v_tier = 'Diamond Collector';
    ELSEIF v_lifetime_spending >= 250.00 THEN
        SET v_tier = 'Platinum Enthusiast';
    ELSEIF v_lifetime_spending >= 100.00 THEN
        SET v_tier = 'Gold Listener';
    ELSE
        SET v_tier = 'Standard Member';
    END IF;

    RETURN v_tier;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `address_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `street` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `country` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`address_id`, `user_id`, `street`, `city`, `country`, `postal_code`, `is_default`) VALUES
(20, 8, 'Example Street 1', 'Sarajevo', 'BA', '71000', 0),
(21, 8, 'Example Street 1', 'Sarajevo', 'BA', '71000', 0),
(22, 7, 'Example Street 1', 'Sarajevo', 'BA', '71000', 0),
(23, 7, 'Example Street 1', 'Sarajevo', 'BA', '71000', 0),
(26, 7, 'Example Street 1', 'Sarajevo', 'BA', '71000', 0);

--
-- Triggers `addresses`
--
DELIMITER $$
CREATE TRIGGER `tr_manage_default_address` BEFORE UPDATE ON `addresses` FOR EACH ROW BEGIN
    IF NEW.is_default = 1 AND OLD.is_default = 0 THEN
        UPDATE Addresses 
        SET is_default = 0 
        WHERE user_id = NEW.user_id 
          AND address_id != NEW.address_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `artists`
--

CREATE TABLE `artists` (
  `artist_id` int(10) UNSIGNED NOT NULL,
  `artist_name` varchar(200) NOT NULL,
  `bio` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `artists`
--

INSERT INTO `artists` (`artist_id`, `artist_name`, `bio`) VALUES
(59, 'Pink Floyd', 'English rock band formed in London in 1965, known for philosophical lyrics and sonic experimentation.'),
(60, 'David Bowie', 'English singer-songwriter and actor, widely regarded as one of the most influential musicians of the 20th century.'),
(61, 'The Beatles', 'English rock band formed in Liverpool in 1960, widely considered the most influential band in history.'),
(62, 'Led Zeppelin', 'English rock band formed in London in 1968, known for blending blues with hard rock and folk.'),
(63, 'Fleetwood Mac', 'Anglo-American rock band formed in London in 1967, best known for their melodic pop-rock sound.'),
(64, 'Radiohead', 'English alternative rock band from Abingdon, Oxfordshire, known for pushing the boundaries of rock music.'),
(65, 'Nirvana', 'American grunge band from Aberdeen, Washington, that popularized alternative rock in the early 1990s.'),
(66, 'The Cure', 'English post-punk band formed in Crawley in 1976, pioneers of the gothic rock genre.'),
(67, 'Depeche Mode', 'English electronic band from Basildon, Essex, known for their dark and synth-driven sound.'),
(68, 'Joy Division', 'English post-punk band from Salford, whose sparse, haunting sound proved enormously influential.'),
(69, 'Miles Davis', 'American jazz trumpeter, widely considered one of the most influential musicians in the history of jazz.'),
(70, 'Coltrane John', 'American jazz saxophonist and composer who played a major role in the development of modal jazz. Kralj'),
(71, 'Massive Attack', 'English musical group from Bristol, widely credited as pioneers of the trip hop genre.'),
(72, 'Portishead', 'English musical group from Bristol, England, formed in 1991, pioneers of trip hop.'),
(73, 'Björk', 'Icelandic singer, songwriter, and actress known for her avant-garde approach to music production.'),
(74, 'Bijelo Dugme', 'Yugoslav and Bosnian rock band from Sarajevo, one of the biggest rock bands from ex-Yugoslavia.'),
(75, 'Riblja Čorba', 'Serbian rock band formed in Belgrade in 1978, one of the most prominent Yugoslav rock acts.'),
(76, 'Azra', 'Yugoslav rock band from Zagreb, considered one of the most important acts of the New Wave of Yugoslav rock.'),
(77, 'Haustor', 'Yugoslav new wave band from Zagreb, active primarily in the early 1980s.'),
(78, 'Bajaga i Instruktori', 'Serbian rock band formed in Belgrade in 1984, known for combining rock with folk elements.'),
(79, 'Ekatarina Velika', 'Yugoslav post-punk and new wave band from Belgrade, considered one of the greatest ex-YU rock bands.'),
(80, 'Leb i Sol', 'Macedonian jazz-rock fusion band, pioneers of progressive rock in ex-Yugoslavia.'),
(81, 'Zdravko Čolić', 'Bosnian pop-rock singer, one of the most beloved and recognizable voices from ex-Yugoslavia.'),
(82, 'Oliver Dragojević', 'Croatian singer considered one of the greatest popular music artists from ex-Yugoslavia.'),
(83, 'Crvena Jabuka', 'Bosnian pop-rock band from Sarajevo, formed in 1984, known for romantic and melodic songs.'),
(84, 'Dino Merlin', 'Pjevac iz Sarajeva.'),
(85, 'Indexi', 'Sarajevska grupa');

-- --------------------------------------------------------

--
-- Table structure for table `format_type`
--

CREATE TABLE `format_type` (
  `type_id` int(10) UNSIGNED NOT NULL,
  `type_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `format_type`
--

INSERT INTO `format_type` (`type_id`, `type_name`) VALUES
(20, '45 RPM'),
(21, '78 RPM'),
(24, 'Box Set'),
(23, 'Colored Vinyl'),
(18, 'EP (10\")'),
(17, 'LP (12\")'),
(22, 'Picture Disc'),
(19, 'Single (7\")');

-- --------------------------------------------------------

--
-- Table structure for table `genres`
--

CREATE TABLE `genres` (
  `genre_id` int(10) UNSIGNED NOT NULL,
  `genre_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `genres`
--

INSERT INTO `genres` (`genre_id`, `genre_name`) VALUES
(32, 'Alternative Rock'),
(49, 'Ambient'),
(47, 'Art Pop'),
(75, 'Art Rock'),
(70, 'Balkan Jazz'),
(41, 'Black Metal'),
(60, 'Blues'),
(34, 'Classic Rock'),
(63, 'Classical'),
(61, 'Country'),
(58, 'Disco'),
(42, 'Doom Metal'),
(46, 'Dream Pop'),
(45, 'Electro Pop'),
(48, 'Electronic'),
(71, 'Ethno'),
(62, 'Folk'),
(57, 'Funk'),
(79, 'Fusion'),
(74, 'Glam Rock'),
(78, 'Gothic Rock'),
(77, 'Grunge'),
(35, 'Hard Rock'),
(39, 'Heavy Metal'),
(54, 'Hip-Hop'),
(50, 'House'),
(33, 'Indie Rock'),
(52, 'Industrial'),
(59, 'Jazz'),
(38, 'Metal'),
(53, 'New Wave'),
(68, 'Newly Composed Folk Music'),
(43, 'Pop'),
(37, 'Post-Punk'),
(73, 'Psychedelic Rock'),
(36, 'Punk Rock'),
(55, 'R&B'),
(64, 'Reggae'),
(31, 'Rock'),
(72, 'Sevdah'),
(65, 'Ska'),
(76, 'Soft Rock'),
(56, 'Soul'),
(44, 'Synth-Pop'),
(51, 'Techno'),
(40, 'Thrash Metal'),
(80, 'Trip Hop'),
(67, 'Turbo-Folk'),
(66, 'World Music'),
(69, 'YU Rock');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `address_id` int(10) UNSIGNED NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `order_date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `address_id`, `total_price`, `order_date`) VALUES
(10, 8, 20, 80.97, '2026-06-01 21:47:43'),
(11, 8, 21, 22.99, '2026-06-01 21:48:09'),
(12, 7, 22, 50.00, '2026-06-01 22:17:26'),
(13, 7, 23, 50.00, '2026-06-01 22:17:43'),
(16, 7, 26, 39.00, '2026-06-01 22:19:43');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `vinyl_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `unit_price` decimal(8,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `vinyl_id`, `quantity`, `unit_price`) VALUES
(1, 10, 173, 1, 24.99),
(2, 10, 167, 1, 27.99),
(3, 10, 163, 1, 27.99),
(4, 11, 185, 1, 22.99),
(5, 12, 191, 1, 50.00),
(6, 13, 193, 1, 50.00),
(7, 16, 195, 1, 39.00);

--
-- Triggers `order_items`
--
DELIMITER $$
CREATE TRIGGER `tr_verify_vinyl_stock` BEFORE INSERT ON `order_items` FOR EACH ROW BEGIN
    DECLARE current_stock INT;

    SELECT stock INTO current_stock 
    FROM Vinyls 
    WHERE vinyl_id = NEW.vinyl_id;

    IF current_stock < NEW.quantity THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Transaction aborted: Insufficient vinyl inventory available.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `vinyl_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`review_id`, `user_id`, `vinyl_id`, `rating`, `comment`, `created_at`) VALUES
(1, 7, 194, 1, 'f', '2026-06-01 22:07:30'),
(6, 7, 180, 4, 'cvrka', '2026-06-01 22:07:48'),
(7, 7, 170, 3, 'trica', '2026-06-01 22:08:02'),
(8, 7, 147, 2, 'Mrak', '2026-06-01 22:08:12'),
(9, 7, 195, 5, 'Top bend', '2026-06-01 22:11:51');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `f_name` varchar(100) NOT NULL,
  `l_name` varchar(100) NOT NULL,
  `dob` date DEFAULT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `email` varchar(255) NOT NULL,
  `password` char(60) NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `f_name`, `l_name`, `dob`, `balance`, `email`, `password`, `is_admin`) VALUES
(7, 'Demo', 'Admin', '2000-01-01', 4861.00, 'admin@example.com', '$2y$10$m/y75wpBN.YOWTT61ob0Geqbc0dfXvoYUgEEFnWQkJkuurlVPiEOe', 1),
(8, 'Demo', 'Customer', '2000-01-01', 2896.04, 'customer@example.com', '$2y$10$m/y75wpBN.YOWTT61ob0Geqbc0dfXvoYUgEEFnWQkJkuurlVPiEOe', 0);

-- --------------------------------------------------------

--
-- Table structure for table `vinyls`
--

CREATE TABLE `vinyls` (
  `vinyl_id` int(10) UNSIGNED NOT NULL,
  `artist_id` int(10) UNSIGNED NOT NULL,
  `type_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(300) NOT NULL,
  `release_date` date DEFAULT NULL,
  `price` decimal(8,2) NOT NULL,
  `stock` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `itunes_id` varchar(255) DEFAULT NULL,
  `cover_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vinyls`
--

INSERT INTO `vinyls` (`vinyl_id`, `artist_id`, `type_id`, `title`, `release_date`, `price`, `stock`, `itunes_id`, `cover_path`) VALUES
(146, 59, 17, 'The Dark Side of the Moon', '1973-03-01', 29.99, 12, '1499069229', 'images/default-cover.png'),
(147, 59, 17, 'Wish You Were Here', '1975-09-12', 27.99, 8, '1065973975', 'images/default-cover.png'),
(148, 59, 17, 'Animals', '1977-01-23', 26.99, 6, '1065975633', 'images/default-cover.png'),
(149, 60, 17, 'Ziggy Stardust', '1972-06-16', 28.99, 10, '1039796877', 'images/default-cover.png'),
(150, 60, 17, 'Heroes', '1977-10-14', 27.99, 7, '1347894082', 'images/default-cover.png'),
(151, 61, 17, 'Abbey Road', '1969-09-26', 32.99, 15, '1474815798', 'images/default-cover.png'),
(152, 61, 17, 'Revolver', '1966-08-05', 31.99, 11, '1642995371', 'images/default-cover.png'),
(153, 62, 17, 'Led Zeppelin IV', '1971-11-08', 29.99, 9, '580708175', 'images/default-cover.png'),
(154, 62, 17, 'Physical Graffiti', '1975-02-24', 34.99, 5, '580707980', 'images/default-cover.png'),
(155, 63, 17, 'Rumours', '1977-02-04', 27.99, 13, '594061854', 'images/default-cover.png'),
(156, 64, 17, 'OK Computer', '1997-05-21', 30.99, 10, '1097861387', 'images/default-cover.png'),
(157, 64, 17, 'Kid A', '2000-10-02', 30.99, 8, '1097862870', 'images/default-cover.png'),
(158, 65, 17, 'Nevermind', '1991-09-24', 28.99, 14, '1242284010', 'images/default-cover.png'),
(160, 66, 17, 'Disintegration', '1989-05-02', 29.99, 7, '372100016', 'images/default-cover.png'),
(161, 67, 17, 'Violator', '1990-03-19', 28.99, 10, '1174246686', 'images/default-cover.png'),
(162, 67, 17, 'Music for the Masses', '1987-09-28', 26.99, 6, '665403347', 'images/default-cover.png'),
(163, 68, 17, 'Unknown Pleasures', '1979-06-15', 27.99, 7, '1476702180', 'images/default-cover.png'),
(164, 68, 17, 'Closer', '1980-07-18', 27.99, 7, '266610001', 'images/default-cover.png'),
(165, 69, 17, 'Kind of Blue', '1959-08-17', 26.99, 11, '268443092', 'images/default-cover.png'),
(166, 69, 17, 'Bitches Brew', '1970-03-30', 31.99, 5, '168376392', 'images/default-cover.png'),
(167, 70, 17, 'A Love Supreme', '1965-01-17', 27.99, 8, '1440713018', 'images/default-cover.png'),
(168, 71, 17, 'Mezzanine', '1998-04-20', 29.99, 8, '1428684976', 'images/default-cover.png'),
(169, 71, 17, 'Blue Lines', '1991-04-08', 28.99, 7, '715864097', 'images/default-cover.png'),
(170, 72, 17, 'Dummy', '1994-08-22', 28.99, 10, '1440653096', 'images/default-cover.png'),
(171, 73, 17, 'Homogenic', '1997-09-22', 30.99, 6, '371572866', 'images/default-cover.png'),
(173, 74, 17, 'Eto! Baš hoću!', '1976-01-01', 24.99, 7, '', 'images/default-cover.png'),
(180, 77, 17, 'Treći svijet', '1983-01-01', 22.99, 4, '', 'images/default-cover.png'),
(185, 80, 17, 'Leb i Sol', '1976-01-01', 22.99, 4, '1728280651', 'images/default-cover.png'),
(191, 84, 17, 'Nešto lijepo treba da se desi', '2025-04-07', 50.00, 14, '948253453', 'images/default-cover.png'),
(193, 84, 20, 'Merlin', '1987-01-01', 50.00, 4, '948249514', 'images/default-cover.png'),
(194, 77, 24, 'Bolero', '1985-10-28', 50.00, 50, '505420143', 'images/default-cover.png'),
(195, 65, 21, 'In Utero', '1993-09-21', 39.00, 4, '1440858699', 'images/default-cover.png');

-- --------------------------------------------------------

--
-- Table structure for table `vinyl_genres`
--

CREATE TABLE `vinyl_genres` (
  `vinyl_id` int(10) UNSIGNED NOT NULL,
  `genre_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vinyl_genres`
--

INSERT INTO `vinyl_genres` (`vinyl_id`, `genre_id`) VALUES
(146, 31),
(146, 34),
(146, 73),
(147, 31),
(147, 34),
(148, 31),
(148, 34),
(149, 31),
(149, 74),
(149, 75),
(150, 31),
(150, 53),
(150, 75),
(151, 31),
(151, 34),
(151, 43),
(152, 31),
(152, 34),
(152, 73),
(153, 31),
(153, 34),
(153, 35),
(154, 31),
(154, 35),
(155, 31),
(155, 43),
(155, 76),
(156, 32),
(156, 75),
(157, 32),
(157, 48),
(157, 49),
(158, 32),
(158, 33),
(158, 77),
(160, 32),
(160, 37),
(160, 78),
(161, 44),
(161, 48),
(161, 53),
(162, 44),
(162, 48),
(162, 53),
(163, 32),
(163, 37),
(164, 32),
(164, 37),
(165, 59),
(166, 59),
(166, 79),
(167, 59),
(168, 48),
(168, 49),
(168, 80),
(169, 48),
(169, 54),
(169, 80),
(170, 48),
(170, 49),
(170, 80),
(171, 47),
(171, 48),
(171, 49),
(173, 31),
(173, 69),
(180, 37),
(180, 53),
(180, 69),
(185, 31),
(185, 69),
(185, 70),
(191, 66),
(191, 67),
(191, 80),
(193, 43),
(194, 31),
(194, 32),
(194, 33),
(194, 34),
(194, 35),
(194, 36),
(194, 69),
(194, 73),
(194, 74),
(194, 75),
(194, 76),
(194, 78),
(195, 32);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_vinyl_sales_analytics`
-- (See below for the actual view)
--
CREATE TABLE `v_vinyl_sales_analytics` (
`vinyl_id` int(10) unsigned
,`vinyl_title` varchar(300)
,`artist_name` varchar(200)
,`format_type` varchar(50)
,`current_unit_price` decimal(8,2)
,`remaining_inventory` int(10) unsigned
,`total_units_sold` decimal(32,0)
,`total_revenue_generated` decimal(40,2)
,`total_reviews_received` bigint(21)
,`average_user_rating` decimal(7,4)
);

-- --------------------------------------------------------

--
-- Structure for view `v_vinyl_sales_analytics`
--
DROP TABLE IF EXISTS `v_vinyl_sales_analytics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_vinyl_sales_analytics`  AS SELECT `v`.`vinyl_id` AS `vinyl_id`, `v`.`title` AS `vinyl_title`, `a`.`artist_name` AS `artist_name`, `ft`.`type_name` AS `format_type`, `v`.`price` AS `current_unit_price`, `v`.`stock` AS `remaining_inventory`, coalesce(sum(`oi`.`quantity`),0) AS `total_units_sold`, coalesce(sum(`oi`.`quantity` * `oi`.`unit_price`),0.00) AS `total_revenue_generated`, count(distinct `r`.`review_id`) AS `total_reviews_received`, coalesce(avg(`r`.`rating`),0.0) AS `average_user_rating` FROM ((((`vinyls` `v` join `artists` `a` on(`v`.`artist_id` = `a`.`artist_id`)) join `format_type` `ft` on(`v`.`type_id` = `ft`.`type_id`)) left join `order_items` `oi` on(`v`.`vinyl_id` = `oi`.`vinyl_id`)) left join `reviews` `r` on(`v`.`vinyl_id` = `r`.`vinyl_id`)) GROUP BY `v`.`vinyl_id`, `v`.`title`, `a`.`artist_name`, `ft`.`type_name`, `v`.`price`, `v`.`stock` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `fk_addr_user` (`user_id`);

--
-- Indexes for table `artists`
--
ALTER TABLE `artists`
  ADD PRIMARY KEY (`artist_id`),
  ADD KEY `idx_artists_name` (`artist_name`);

--
-- Indexes for table `format_type`
--
ALTER TABLE `format_type`
  ADD PRIMARY KEY (`type_id`),
  ADD UNIQUE KEY `type_name` (`type_name`);

--
-- Indexes for table `genres`
--
ALTER TABLE `genres`
  ADD PRIMARY KEY (`genre_id`),
  ADD UNIQUE KEY `genre_name` (`genre_name`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `fk_order_user` (`user_id`),
  ADD KEY `fk_order_addr` (`address_id`),
  ADD KEY `idx_orders_date` (`order_date`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `fk_oi_order` (`order_id`),
  ADD KEY `fk_oi_vinyl` (`vinyl_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `uq_review` (`user_id`,`vinyl_id`),
  ADD KEY `fk_rev_vinyl` (`vinyl_id`),
  ADD KEY `idx_reviews_timestamp` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vinyls`
--
ALTER TABLE `vinyls`
  ADD PRIMARY KEY (`vinyl_id`),
  ADD KEY `fk_vinyl_artist` (`artist_id`),
  ADD KEY `fk_vinyl_type` (`type_id`),
  ADD KEY `idx_vinyls_title` (`title`);

--
-- Indexes for table `vinyl_genres`
--
ALTER TABLE `vinyl_genres`
  ADD PRIMARY KEY (`vinyl_id`,`genre_id`),
  ADD KEY `fk_vg_genre` (`genre_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `address_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `artists`
--
ALTER TABLE `artists`
  MODIFY `artist_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `format_type`
--
ALTER TABLE `format_type`
  MODIFY `type_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `genres`
--
ALTER TABLE `genres`
  MODIFY `genre_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `vinyls`
--
ALTER TABLE `vinyls`
  MODIFY `vinyl_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=197;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `fk_addr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_order_addr` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`address_id`),
  ADD CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_oi_vinyl` FOREIGN KEY (`vinyl_id`) REFERENCES `vinyls` (`vinyl_id`);

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_rev_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rev_vinyl` FOREIGN KEY (`vinyl_id`) REFERENCES `vinyls` (`vinyl_id`) ON DELETE CASCADE;

--
-- Constraints for table `vinyls`
--
ALTER TABLE `vinyls`
  ADD CONSTRAINT `fk_vinyl_artist` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`artist_id`),
  ADD CONSTRAINT `fk_vinyl_type` FOREIGN KEY (`type_id`) REFERENCES `format_type` (`type_id`);

--
-- Constraints for table `vinyl_genres`
--
ALTER TABLE `vinyl_genres`
  ADD CONSTRAINT `fk_vg_genre` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`genre_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vg_vinyl` FOREIGN KEY (`vinyl_id`) REFERENCES `vinyls` (`vinyl_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
