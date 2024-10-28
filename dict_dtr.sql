-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 28, 2024 at 04:36 AM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.1.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dict_dtr`
--

-- --------------------------------------------------------

--
-- Table structure for table `august2024`
--

CREATE TABLE `august2024` (
  `username` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `am_time_in` time DEFAULT NULL,
  `am_time_out` time DEFAULT NULL,
  `pm_time_in` time DEFAULT NULL,
  `pm_time_out` time DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `august2024`
--

INSERT INTO `august2024` (`username`, `fullname`, `date`, `am_time_in`, `am_time_out`, `pm_time_in`, `pm_time_out`, `remarks`) VALUES
('beans', 'Vhince Cedrick A. Afroilan', '2024-08-22', '09:11:15', NULL, NULL, NULL, NULL),
('angel', 'Mighty Angel Pacio Angelo', '2024-08-22', '09:22:43', '10:26:20', NULL, NULL, NULL),
('angel', 'Mighty Angel Pacio Angelo', '2024-08-23', '11:32:54', '12:08:44', '12:08:52', '12:04:08', NULL),
('beans', 'Vhince Cedrick A. Afroilan', '2024-08-23', '11:57:59', '13:02:22', '13:02:34', '12:01:25', NULL),
('m', 'mouse', '2024-08-23', NULL, NULL, '12:03:19', '12:03:34', NULL),
('rose', 'Roselyn Valles', '2024-08-23', NULL, '12:09:31', '12:09:27', '12:13:13', NULL),
('kris', 'Kristin Lopez', '2024-08-23', NULL, NULL, '12:13:31', '12:13:35', NULL),
('beans', 'Vhince Cedrick A. Afroilan', '2024-08-30', NULL, NULL, NULL, NULL, 'TO R1-24-1234'),
('beans', 'Vhince Cedrick A. Afroilan', '2024-08-31', NULL, NULL, NULL, NULL, 'TO R1-24-1234'),
('sample', 'sample', '2024-10-18', NULL, NULL, NULL, NULL, NULL),
('rose', 'Roselyn Valles', '2024-10-21', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ob`
--

CREATE TABLE `ob` (
  `username` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `startdate` date NOT NULL,
  `enddate` date NOT NULL,
  `location` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `october2024`
--

CREATE TABLE `october2024` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `am_time_in` time DEFAULT NULL,
  `am_time_out` time DEFAULT NULL,
  `pm_time_in` time DEFAULT NULL,
  `pm_time_out` time DEFAULT NULL,
  `remarks` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `october2024`
--

INSERT INTO `october2024` (`id`, `username`, `fullname`, `date`, `am_time_in`, `am_time_out`, `pm_time_in`, `pm_time_out`, `remarks`) VALUES
(9, 'sample', 'sample', '2024-10-24', NULL, NULL, NULL, NULL, 'TO R1-1234-13'),
(10, 'sample', 'sample', '2024-10-25', NULL, NULL, NULL, NULL, 'TO R1-1234-13'),
(13, 'rose', 'Roselyn Valles', '2024-10-21', NULL, NULL, NULL, NULL, 'TO R1-345-756'),
(14, 'rose', 'Roselyn Valles', '2024-10-22', NULL, NULL, NULL, NULL, 'TO R1-345-756'),
(15, 'm', 'mouse', '2024-10-22', NULL, NULL, NULL, NULL, 'WFH'),
(16, 'm', 'mouse', '2024-10-23', NULL, NULL, NULL, NULL, 'WFH');

-- --------------------------------------------------------

--
-- Table structure for table `offsets`
--

CREATE TABLE `offsets` (
  `username` varchar(255) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `startdate` date NOT NULL,
  `enddate` date NOT NULL,
  `reason` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pending_users`
--

CREATE TABLE `pending_users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `contract` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `to`
--

CREATE TABLE `to` (
  `number` varchar(255) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `startdate` date NOT NULL,
  `enddate` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `to`
--

INSERT INTO `to` (`number`, `username`, `fullname`, `startdate`, `enddate`, `created_at`, `status`) VALUES
('R1-231-123', 'angel', 'Mighty Angel Pacio Angelo', '2024-08-30', '2024-08-31', '2024-08-22 02:21:56', 'Pending'),
('R1-24-1234', 'beans', 'Vhince Cedrick A. Afroilan', '2024-09-01', '2024-09-04', '2024-08-31 07:41:37', 'Pending'),
('R1-24-1234', 'beans', 'Vhince Cedrick A. Afroilan', '2024-08-14', '2024-08-15', '2024-08-31 07:42:18', 'Pending'),
('R1-1234-12', 'sample', 'sample', '2024-10-21', '2024-10-23', '2024-10-18 07:16:59', ''),
('R1-1234-13', 'sample', 'sample', '2024-10-24', '2024-10-25', '2024-10-18 07:20:52', ''),
('R1-345-756', 'rose', 'Roselyn Valles', '2024-10-21', '2024-10-22', '2024-10-21 09:31:44', '');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `username` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `contract` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`username`, `fullname`, `contract`, `position`, `password`) VALUES
('angel', 'Mighty Angel Pacio Angelo', 'Job Order', 'Information Technology Officer III', '123'),
('beans', 'Vhince Cedrick A. Afroilan', 'Plantilla', 'Project Development Officer II', '123'),
('JDoe', 'John D. Doe', 'Plantilla', 'Personal Driver', '123'),
('kris', 'Kristin Lopez', 'Job Order', 'Chief', '123'),
('lay', 'Eugene Laysa', 'Job Order', 'Assistant', '123'),
('m', 'mouse', 'Plantilla', 'Electrical Engineer II', '1'),
('PS', 'Platino Style', 'Job Order', 'Technician', '123'),
('rose', 'Roselyn Valles', 'Job Order', 'Electrical Engineer', '123'),
('sample', 'sample', 'Job Order', 'sample', '123');

-- --------------------------------------------------------

--
-- Table structure for table `wfh`
--

CREATE TABLE `wfh` (
  `username` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `startdate` date NOT NULL,
  `enddate` date NOT NULL,
  `reason` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wfh`
--

INSERT INTO `wfh` (`username`, `fullname`, `startdate`, `enddate`, `reason`, `created_at`, `status`) VALUES
('m', 'mouse', '2024-10-22', '2024-10-23', '', '2024-10-21 09:34:00', '');

-- --------------------------------------------------------

--
-- Table structure for table `ws`
--

CREATE TABLE `ws` (
  `username` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `startdate` date NOT NULL,
  `enddate` date NOT NULL,
  `reason` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ws`
--

INSERT INTO `ws` (`username`, `fullname`, `startdate`, `enddate`, `reason`, `created_at`, `status`) VALUES
('sample', 'sample', '2024-10-28', '2024-10-28', '', '2024-10-18 07:38:04', '');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `october2024`
--
ALTER TABLE `october2024`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pending_users`
--
ALTER TABLE `pending_users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `october2024`
--
ALTER TABLE `october2024`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `pending_users`
--
ALTER TABLE `pending_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
