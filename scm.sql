-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 02, 2026 at 09:53 AM
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
-- Database: `scm`
--

-- --------------------------------------------------------

--
-- Table structure for table `employee_profiles`
--

CREATE TABLE `employee_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `experience` text DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `cv` text DEFAULT NULL,
  `previous_jobs` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_profiles`
--

INSERT INTO `employee_profiles` (`id`, `user_id`, `experience`, `bio`, `skills`, `notes`, `department`, `phone`, `address`, `cv`, `previous_jobs`, `created_at`, `updated_at`) VALUES
(2, 1, '5 years of management experience', 'Experienced administrator with strong leadership and communication skills', 'Management, Communication, Excel, Operations', 'Handles internal coordination and employee support', 'Administration', '050-1111111', 'Haifa, Israel', '/api/serve-file.php?file=employee_1_1777371500____________________-___________________.pdf', 'Office Manager at ABC Ltd, Team Lead at XYZ Group', '2026-04-28 09:55:31', '2026-04-28 10:18:20');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `title`, `message`, `link`, `created_at`) VALUES
(26, 'New task assigned', 'Noor was assigned: Migrate contacts', '/app/tasks', '2025-09-22 19:17:03'),
(27, 'New task assigned', 'Sara was assigned: Scheduler worker', '/app/tasks', '2025-09-22 19:17:03'),
(31, 'New chat message', 'You have a new message on a task', '/app/tasks/31', '2025-09-23 15:12:26'),
(32, 'New chat message', 'You have a new message on a task', '/app/tasks/31', '2025-09-23 15:12:29'),
(33, 'New chat message', 'You have a new message on a task', '/app/tasks/31', '2025-09-23 15:12:47'),
(34, 'New chat message', 'You have a new message on a task', '/app/tasks/31', '2025-09-23 15:17:17'),
(35, 'New chat message', 'You have a new message on a task', '/app/tasks/31', '2025-09-23 15:22:53'),
(36, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-17 09:32:19'),
(37, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-17 09:40:51'),
(38, 'Hurry up', 'i need from you to try to finish the last part of the task as much as possible try to help with this link', 'https://www.yes.co.il/', '2025-11-18 11:35:29'),
(39, 'be careful', 'try to finish early', NULL, '2025-11-18 11:50:23'),
(40, 'click the link', 'this link will help you to finish', 'https://www.yes.co.il/', '2025-11-18 11:51:44'),
(41, 'בדיקת לינק חדש', 'יש ללחוץ על הלינק הזה כדי להעוזר בו', 'https://www.yes.co.il/', '2025-11-18 11:57:16'),
(42, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-18 12:45:18'),
(43, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-18 12:51:30'),
(44, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-18 12:58:42'),
(45, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-18 12:59:35'),
(46, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-18 13:02:52'),
(47, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-18 13:06:11'),
(48, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-19 22:41:11'),
(49, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-21 10:41:47'),
(50, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-23 09:06:32'),
(51, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-23 09:12:27'),
(52, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-23 09:12:44'),
(53, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-23 09:22:40'),
(54, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-23 09:39:26'),
(55, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-23 09:42:22'),
(56, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-23 09:42:39'),
(57, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-23 09:42:48'),
(58, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-23 09:51:26'),
(59, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-23 09:51:47'),
(60, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-23 10:14:52'),
(61, 'Important', 'new update that change all the system please enter the link and check', 'https://www.yes.co.il/content/yesplus/', '2025-11-23 10:22:16'),
(62, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-23 10:24:18'),
(63, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-23 10:25:02'),
(64, 'New chat message', 'You have a new message on a task', '/app/tasks/35', '2025-11-23 10:43:41'),
(65, 'New chat message', 'You have a new message on a task', '/app/tasks/36', '2025-11-25 15:41:41'),
(66, 'New chat message', 'You have a new message on a task', '/app/tasks/36', '2025-11-25 15:42:11'),
(67, 'New chat message', 'You have a new message on a task', '/app/tasks/36', '2025-11-25 15:44:57'),
(68, 'New chat message', 'You have a new message on a task', '/app/tasks/36', '2025-11-25 15:46:12'),
(69, 'New chat message', 'You have a new message on a task', '/app/tasks/36', '2025-11-25 15:46:17'),
(70, 'New chat message', 'You have a new message on a task', '/app/tasks/37', '2025-11-25 16:18:46'),
(71, 'New chat message', 'You have a new message on a task', '/app/tasks/38', '2025-11-25 16:33:44'),
(72, 'New chat message', 'You have a new message on a task', '/app/tasks/38', '2025-11-25 16:33:50'),
(73, 'No title', 'click', 'https://he.wikipedia.org/wiki/%D7%A7%D7%95%D7%91%D7%A5:Yes_._logo.svg', '2025-11-25 17:37:45'),
(74, 'New chat message', 'You have a new message on a task', '/app/tasks/37', '2025-11-26 13:51:33'),
(75, 'New chat message', 'You have a new message on a task', '/app/tasks/39', '2025-11-26 13:57:19'),
(76, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-02-17 00:28:10'),
(77, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-02-17 00:29:42'),
(78, 'About Yes pro', 'The project covers frontend development (Angular), backend services (PHP + MySQL), and integration with existing company systems.', 'https://www.yes.co.il/', '2026-02-17 00:31:56'),
(79, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-02-20 15:43:28'),
(80, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-02-21 14:39:20'),
(81, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-02-21 15:33:20'),
(82, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-02-21 15:50:19'),
(83, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-02-21 15:54:26'),
(84, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-02-21 20:51:38'),
(85, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-02-21 20:52:36'),
(86, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-02-21 20:57:27'),
(87, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-02-21 20:57:33'),
(88, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-02-21 20:57:47'),
(89, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-02-21 21:02:17'),
(90, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-02-21 21:07:37'),
(91, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-02-23 19:14:55'),
(92, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-03-08 20:01:01'),
(93, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-03-08 20:20:03'),
(94, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-03-08 20:20:31'),
(95, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-03-08 20:22:15'),
(96, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-03-08 20:25:41'),
(97, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-03-08 20:30:45'),
(98, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-03-08 20:33:46'),
(99, 'New chat message', 'You have a new message on a task', '/app/tasks/41', '2026-04-01 16:30:36'),
(100, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-04-02 14:32:37'),
(101, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-04-02 14:55:17'),
(102, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-04-02 14:58:50'),
(103, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-04-02 15:01:24'),
(104, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-04-02 15:06:26'),
(105, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-04-02 15:13:10'),
(106, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-04-02 15:20:28'),
(107, 'New chat message', 'You have a new message on a task', '/app/tasks/41', '2026-04-02 15:30:37'),
(108, 'New chat message', 'You have a new message on a task', '/app/tasks/41', '2026-04-02 15:37:37'),
(109, 'New chat message', 'You have a new message on a task', '/app/tasks/41', '2026-04-02 16:06:02'),
(110, 'New chat message', 'You have a new message on a task', '/app/tasks/41', '2026-04-02 16:17:09'),
(111, 'New chat message', 'You have a new message on a task', '/app/tasks/41', '2026-04-02 16:17:29'),
(112, 'New chat message', 'You have a new message on a task', '/app/tasks/41', '2026-04-02 16:22:15'),
(113, 'New chat message', 'You have a new message on a task', '/app/tasks/41', '2026-04-02 16:34:23'),
(114, 'New chat message', 'You have a new message on a task', '/app/tasks/41', '2026-04-25 15:46:09'),
(115, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-04-25 16:00:24'),
(116, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-04-27 16:18:12'),
(117, 'New chat message', 'You have a new message on a task', '/app/tasks/40', '2026-04-27 16:21:48'),
(118, 'New chat message', 'You have a new message on a task', '/app/tasks/43', '2026-05-21 22:48:20'),
(119, 'New chat message', 'You have a new message on a task', '/app/tasks/43', '2026-05-21 22:53:16'),
(120, 'New chat message', 'You have a new message on a task', '/app/tasks/43', '2026-05-22 08:31:57'),
(121, 'New chat message', 'You have a new message on a task', '/app/tasks/44', '2026-05-22 09:00:02'),
(122, 'New chat message', 'You have a new message on a task', '/app/tasks/44', '2026-05-22 09:00:08'),
(123, 'New chat message', 'You have a new message on a task', '/app/tasks/44', '2026-05-22 09:00:36'),
(124, 'New chat message', 'You have a new message on a task', '/app/tasks/44', '2026-05-22 09:02:34'),
(125, 'New chat message', 'You have a new message on a task', '/app/tasks/44', '2026-05-22 09:07:07');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_items`
--

CREATE TABLE `payroll_items` (
  `id` bigint(20) NOT NULL,
  `payroll_run_id` bigint(20) NOT NULL,
  `item_key` varchar(64) NOT NULL,
  `label` varchar(160) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `type` enum('allowance','deduction','tax','overtime') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_items`
--

INSERT INTO `payroll_items` (`id`, `payroll_run_id`, `item_key`, `label`, `amount`, `type`) VALUES
(65, 19, 'allowance_transport', 'Transport', 0.00, 'allowance'),
(66, 19, 'allowance', 'Allowance', 0.00, 'allowance'),
(67, 19, 'deduction_advance', 'Advance', 0.00, 'deduction'),
(68, 19, 'deduction', 'Deduction', 0.00, 'deduction'),
(69, 19, 'overtime', 'Overtime', 30.00, 'overtime'),
(82, 20, 'allowance_transport', 'Transport', 5.00, 'allowance'),
(83, 20, 'deduction_advance', 'Advance', 8.00, 'deduction'),
(84, 20, 'overtime', 'Overtime', 125.00, 'overtime');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_runs`
--

CREATE TABLE `payroll_runs` (
  `id` bigint(20) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `period_year` int(11) NOT NULL,
  `period_month` int(11) NOT NULL,
  `gross` decimal(12,2) NOT NULL,
  `total_deductions` decimal(12,2) NOT NULL,
  `net_pay` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_runs`
--

INSERT INTO `payroll_runs` (`id`, `employee_id`, `period_year`, `period_month`, `gross`, `total_deductions`, `net_pay`, `created_at`) VALUES
(19, 1, 2026, 4, 261030.00, 0.00, 261030.00, '2026-04-27 20:20:14'),
(20, 1, 2026, 5, 11297.50, 8.00, 11289.50, '2026-05-19 11:26:17');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `module`, `action`, `description`) VALUES
(1, 'users', 'read', 'List and view users'),
(2, 'users', 'create', 'Create users'),
(3, 'users', 'update', 'Update users'),
(4, 'users', 'delete', 'Delete users'),
(5, 'projects', 'read', 'List and view projects'),
(6, 'projects', 'create', 'Create projects'),
(7, 'projects', 'update', 'Update projects'),
(8, 'projects', 'delete', 'Delete projects'),
(9, 'tasks', 'read', 'List and view tasks'),
(10, 'tasks', 'create', 'Create tasks'),
(11, 'tasks', 'update', 'Update tasks'),
(12, 'tasks', 'delete', 'Delete tasks'),
(13, 'notifications', 'create', 'Send notifications'),
(14, 'payroll', 'read', 'View payroll and tax tables'),
(15, 'payroll', 'create', 'Create payroll runs'),
(16, 'payroll', 'update', 'Update payroll runs'),
(17, 'payroll', 'delete', 'Delete payroll runs'),
(18, 'payroll', 'tax_edit', 'Edit tax brackets');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `owner_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `description`, `due_date`, `owner_id`, `created_at`) VALUES
(21, 'MARAM Glow Commerce', 'MARAM Glow Commerce is a full e‑commerce web application designed for beauty, skincare, and makeup products.\nIt provides a complete online shopping experience, including product browsing, filtering, cart management, secure checkout, user authentication, and order processing.\nThe system is built with a modern architecture that includes a responsive frontend, a secure Spring Boot backend, and a structured MySQL database.\nIt supports two main roles: Customer (shopping) and Admin (management).', '2027-01-01', 1, '2026-05-22 05:46:57');

-- --------------------------------------------------------

--
-- Table structure for table `project_members`
--

CREATE TABLE `project_members` (
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_members`
--

INSERT INTO `project_members` (`project_id`, `user_id`) VALUES
(21, 1);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `created_at`) VALUES
(1, 'Administrator', 'admin', 'Full access', '2025-08-10 01:35:39'),
(2, 'Manager', 'manager', 'Manage assigned projects/teams', '2025-08-10 01:35:39'),
(3, 'Employee', 'employee', 'Standard user', '2025-08-10 01:35:39');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(2, 5),
(2, 6),
(2, 7),
(2, 8),
(2, 9),
(2, 10),
(2, 11),
(2, 12),
(2, 13),
(2, 14),
(2, 15),
(2, 16),
(2, 17),
(2, 18),
(3, 5),
(3, 6),
(3, 7),
(3, 8),
(3, 9),
(3, 10),
(3, 11),
(3, 12),
(3, 13),
(3, 14),
(3, 15),
(3, 16),
(3, 17),
(3, 18);

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('todo','in_progress','done') NOT NULL DEFAULT 'todo',
  `assignee_id` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `priority` tinyint(4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `project_id`, `title`, `description`, `status`, `assignee_id`, `due_date`, `priority`, `created_at`) VALUES
(44, 21, '1️⃣ Task 1 — Frontend UI & User Experience', 'Goal:\nBuild the entire client-side interface where users browse products, view details, manage their cart, and complete checkout.\n\nWhat the programmer must do:\nCreate responsive pages using HTML, CSS, JavaScript\n\nBuild product listing, product details, and category filters\n\nImplement a dynamic shopping cart (add/remove/update items)\n\nCreate user forms: login, register, checkout\n\nConnect the frontend to backend APIs using Fetch / Axios\n\nTechnologies:\nHTML, CSS, JavaScript, Responsive Design, REST API calls', 'todo', 109, '2027-01-01', 3, '2026-05-22 05:48:53'),
(45, 21, '2️⃣ Task 2 — Backend API (Spring Boot)', 'Goal:\nDevelop all server-side logic and REST APIs that power the application.\n\nWhat the programmer must do:\nCreate controllers for:\n\nAuth (login/register)\n\nProducts\n\nCart\n\nOrders\n\nUsers\n\nImplement business logic in Service classes\n\nUse DTOs for clean data transfer\n\nImplement validation and error handling\n\nBuild repository layer using Spring Data JPA\n\nTechnologies:\nSpring Boot, Spring MVC, Spring Data JPA, DTOs, Validation', 'todo', 109, '2027-04-01', 3, '2026-05-22 05:50:28'),
(46, 21, '3️⃣ Task 3 — Security & Authentication', 'Goal:\nSecure the system using modern authentication and authorization.\n\nWhat the programmer must do:\nImplement JWT authentication (login returns token)\n\nProtect routes based on user roles:\n\nCustomer: shopping features\n\nAdmin: product/user/order management\n\nConfigure Spring Security filters\n\nHash passwords using BCrypt\n\nAdd email verification & password reset (SendGrid)\n\nTechnologies:\nSpring Security, JWT, BCrypt, SendGrid API', 'todo', 109, '2027-07-01', 3, '2026-05-22 05:51:58'),
(47, 21, '4️⃣ Task 4 — Database Design & Order Processing', 'Goal:\nBuild the database structure and implement the full order workflow.\n\nWhat the programmer must do:\nCreate MySQL tables:\n\nUsers, Products, Categories\n\nCart, Cart_Items\n\nOrders, Order_Items\n\nImplement relationships (One‑To‑Many, Many‑To‑One)\n\nBuild logic for:\n\nCreating orders from cart\n\nCalculating total price\n\nSaving order history\n\nEnsure data consistency with transactions\n\nTechnologies:\nMySQL, JPA Entities, Relationships, Transactions', 'todo', 1, '2027-10-01', 3, '2026-05-22 05:53:20');

-- --------------------------------------------------------

--
-- Table structure for table `task_messages`
--

CREATE TABLE `task_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `thread_id` bigint(20) UNSIGNED NOT NULL,
  `sender_id` int(11) NOT NULL,
  `type` enum('text','file') NOT NULL DEFAULT 'text',
  `text` text DEFAULT NULL,
  `file_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_messages`
--

INSERT INTO `task_messages` (`id`, `thread_id`, `sender_id`, `type`, `text`, `file_url`, `created_at`) VALUES
(96, 50, 1, 'text', 'Hello, we are about to create a perfect project to our future bussiness . please read all the instructions and just imagine the project from all its sides', NULL, '2026-05-22 06:00:02'),
(97, 50, 1, 'text', 'https://copilot.microsoft.com/shares/pages/KGxvp29bRbcfy6kBCgQwf', NULL, '2026-05-22 06:00:08'),
(98, 50, 1, 'file', NULL, '/api/serve-file.php?file=chat_1779429636_Screenshot_2026-04-25_131738.png', '2026-05-22 06:00:36'),
(99, 50, 1, 'file', NULL, '/api/serve-file.php?file=chat_1779429754_MARAM_Glow_Commerce.pdf', '2026-05-22 06:02:34'),
(100, 50, 109, 'text', 'sure sir', NULL, '2026-05-22 06:07:07');

-- --------------------------------------------------------

--
-- Table structure for table `task_message_reads`
--

CREATE TABLE `task_message_reads` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `message_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_message_reads`
--

INSERT INTO `task_message_reads` (`id`, `message_id`, `user_id`, `read_at`) VALUES
(2187, 96, 1, '2026-05-22 06:00:02'),
(2191, 97, 1, '2026-05-22 06:00:08'),
(2200, 98, 1, '2026-05-22 06:00:36'),
(2227, 99, 1, '2026-05-22 06:02:34'),
(2247, 96, 109, '2026-05-22 06:06:41'),
(2248, 97, 109, '2026-05-22 06:06:41'),
(2249, 98, 109, '2026-05-22 06:06:41'),
(2250, 99, 109, '2026-05-22 06:06:41'),
(2260, 100, 109, '2026-05-22 06:07:07');

-- --------------------------------------------------------

--
-- Table structure for table `task_threads`
--

CREATE TABLE `task_threads` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `task_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_threads`
--

INSERT INTO `task_threads` (`id`, `task_id`, `created_by`, `created_at`) VALUES
(50, 44, 1, '2026-05-22 05:57:59');

-- --------------------------------------------------------

--
-- Table structure for table `task_thread_participants`
--

CREATE TABLE `task_thread_participants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `thread_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_hint` enum('manager','employee','admin') DEFAULT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_thread_participants`
--

INSERT INTO `task_thread_participants` (`id`, `thread_id`, `user_id`, `role_hint`, `joined_at`) VALUES
(693, 50, 1, 'manager', '2026-05-22 05:57:59'),
(694, 50, 109, 'employee', '2026-05-22 05:57:59');

-- --------------------------------------------------------

--
-- Table structure for table `tax_brackets`
--

CREATE TABLE `tax_brackets` (
  `id` int(11) NOT NULL,
  `bracket_from` decimal(12,2) NOT NULL,
  `bracket_to` decimal(12,2) DEFAULT NULL,
  `rate_percent` decimal(5,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `time_entries`
--

CREATE TABLE `time_entries` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `task_id` int(11) DEFAULT NULL,
  `work_date` date NOT NULL,
  `hours` decimal(5,2) NOT NULL DEFAULT 0.00,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `role_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','banned') NOT NULL DEFAULT 'active',
  `hourly_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `max_active_tasks` int(11) NOT NULL DEFAULT 3,
  `role` varchar(50) NOT NULL DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `created_at`, `updated_at`, `role_id`, `status`, `hourly_rate`, `max_active_tasks`, `role`) VALUES
(1, 'Ahmad Badarne', 'admin@smartco.local', '$2y$10$p6./iVeDS.jvpA89qWMFp.M6zPj1XSzPn3vqUJ73ndEH53tmxRCQi', '2025-08-09 22:35:40', '2026-05-22 05:38:04', 1, 'active', 25.00, 5, 'user'),
(109, 'Maram Badarne', 'awadi.mar34@gmail.com', '$2y$10$NWIoM9xHeJ06uQMhBhWXvutb2wND9NYHtpXXgncSHu11zT3YO7BqG', '2026-05-22 05:36:39', '2026-05-22 05:36:39', 3, 'active', 0.00, 3, 'employee');

-- --------------------------------------------------------

--
-- Table structure for table `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_notifications`
--

INSERT INTO `user_notifications` (`id`, `user_id`, `notification_id`, `is_read`, `read_at`) VALUES
(121, 1, 78, 1, '2026-05-22 09:05:34'),
(131, 1, 81, 1, '2026-05-22 09:05:33'),
(135, 1, 85, 1, '2026-05-22 09:05:31'),
(136, 1, 86, 1, '2026-05-22 09:05:30'),
(137, 1, 87, 1, '2026-05-22 09:05:26'),
(138, 1, 88, 1, '2026-05-22 09:05:25'),
(139, 1, 89, 1, '2026-05-22 09:05:24'),
(140, 1, 90, 1, '2026-05-22 09:05:23'),
(141, 1, 91, 1, '2026-05-22 09:05:22'),
(148, 1, 98, 1, '2026-05-22 09:05:21'),
(158, 1, 107, 1, '2026-05-22 09:05:19'),
(167, 1, 116, 1, '2026-05-22 09:05:18'),
(173, 109, 121, 1, '2026-05-22 09:07:38'),
(174, 109, 122, 1, '2026-05-22 09:07:37'),
(175, 109, 123, 1, '2026-05-22 09:07:36'),
(176, 109, 124, 1, '2026-05-22 09:07:33'),
(189, 1, 125, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `work_sessions`
--

CREATE TABLE `work_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ended_at` timestamp NULL DEFAULT NULL,
  `last_seen` timestamp NULL DEFAULT NULL,
  `seconds_worked` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `source` varchar(32) DEFAULT 'web',
  `ip` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `closed_reason` enum('logout','idle_timeout','midnight_cut','force_close') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `work_sessions`
--

INSERT INTO `work_sessions` (`id`, `user_id`, `started_at`, `ended_at`, `last_seen`, `seconds_worked`, `source`, `ip`, `user_agent`, `closed_reason`, `created_at`) VALUES
(94, 1, '2026-02-16 22:21:12', '2026-02-20 07:43:37', '2026-02-20 07:43:37', 292945, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-02-16 22:21:12'),
(97, 1, '2026-02-20 07:43:37', '2026-02-20 08:17:48', '2026-02-20 08:17:48', 2051, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-02-20 07:43:37'),
(99, 1, '2026-02-20 08:17:48', '2026-02-20 10:12:18', '2026-02-20 10:12:18', 6870, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-02-20 08:17:48'),
(100, 1, '2026-02-20 10:12:18', '2026-02-20 10:24:02', '2026-02-20 10:24:02', 704, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-02-20 10:12:18'),
(101, 1, '2026-02-20 10:24:02', '2026-02-20 10:29:01', '2026-02-20 10:29:01', 299, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-02-20 10:24:02'),
(102, 1, '2026-02-20 10:29:01', '2026-02-20 10:43:59', '2026-02-20 10:43:59', 898, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-02-20 10:29:01'),
(103, 1, '2026-02-20 10:43:59', '2026-02-20 11:42:50', '2026-02-20 11:42:50', 3531, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-02-20 10:43:59'),
(104, 1, '2026-02-20 11:42:50', '2026-02-20 12:34:49', '2026-02-20 12:34:49', 3119, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-02-20 11:42:50'),
(105, 1, '2026-02-20 12:34:49', '2026-02-21 12:37:23', '2026-02-21 12:37:23', 86554, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-02-20 12:34:49'),
(107, 1, '2026-02-21 12:37:23', '2026-02-21 12:40:08', '2026-02-21 12:40:08', 165, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-02-21 12:37:23'),
(109, 1, '2026-02-21 12:40:08', '2026-02-21 13:33:30', '2026-02-21 13:33:30', 3202, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-02-21 12:40:08'),
(111, 1, '2026-02-21 13:33:30', '2026-02-21 13:54:08', '2026-02-21 13:54:08', 1238, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-02-21 13:33:30'),
(112, 1, '2026-02-21 13:54:08', '2026-02-21 18:51:22', '2026-02-21 18:51:22', 17834, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-02-21 13:54:08'),
(114, 1, '2026-02-21 18:51:22', '2026-02-21 19:11:24', '2026-02-21 19:11:24', 1202, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-02-21 18:51:22'),
(116, 1, '2026-02-21 19:11:24', '2026-02-21 19:28:57', '2026-02-21 19:28:57', 1053, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-02-21 19:11:24'),
(118, 1, '2026-02-21 19:28:57', '2026-02-24 14:38:08', '2026-02-24 14:38:08', 241751, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-02-21 19:28:57'),
(121, 1, '2026-02-24 14:38:08', '2026-03-08 18:00:23', '2026-03-08 18:00:23', 1048935, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-02-24 14:38:08'),
(124, 1, '2026-03-08 18:00:23', '2026-03-08 18:05:09', '2026-03-08 18:05:09', 286, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-03-08 18:00:23'),
(125, 1, '2026-03-08 18:05:09', '2026-03-23 22:10:02', '2026-03-23 22:10:02', 1310693, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-03-08 18:05:09'),
(129, 1, '2026-03-23 22:10:02', '2026-04-01 12:16:32', '2026-04-01 12:16:32', 745590, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 'force_close', '2026-03-23 22:10:02'),
(130, 1, '2026-04-01 12:16:32', '2026-04-01 13:14:15', '2026-04-01 13:14:15', 3463, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 'force_close', '2026-04-01 12:16:32'),
(132, 1, '2026-04-01 13:14:15', '2026-04-01 13:27:45', '2026-04-01 13:27:45', 810, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 'force_close', '2026-04-01 13:14:15'),
(133, 1, '2026-04-01 13:27:45', '2026-04-02 12:30:48', '2026-04-02 12:30:48', 82983, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 'force_close', '2026-04-01 13:27:45'),
(135, 1, '2026-04-02 12:30:48', '2026-04-02 12:32:41', '2026-04-02 12:32:41', 113, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 'force_close', '2026-04-02 12:30:48'),
(136, 1, '2026-04-02 12:32:41', '2026-04-02 13:05:49', '2026-04-02 13:05:49', 1988, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 'force_close', '2026-04-02 12:32:41'),
(137, 1, '2026-04-02 13:05:49', '2026-04-25 12:30:11', '2026-04-25 12:30:11', 1985062, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 'force_close', '2026-04-02 13:05:49'),
(138, 1, '2026-04-25 12:30:11', '2026-04-25 13:17:14', '2026-04-25 13:17:14', 2823, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'force_close', '2026-04-25 12:30:11'),
(142, 1, '2026-04-25 13:17:14', '2026-04-27 13:11:48', '2026-04-27 13:11:48', 172474, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'force_close', '2026-04-25 13:17:14'),
(145, 1, '2026-04-27 13:11:48', '2026-04-27 13:18:49', '2026-04-27 13:18:49', 421, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'force_close', '2026-04-27 13:11:48'),
(147, 1, '2026-04-27 13:18:49', '2026-04-27 13:40:41', '2026-04-27 13:40:41', 1312, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'force_close', '2026-04-27 13:18:49'),
(149, 1, '2026-04-27 13:40:41', '2026-04-27 20:03:52', '2026-04-27 20:03:52', 22991, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'force_close', '2026-04-27 13:40:41'),
(150, 1, '2026-04-27 20:03:52', '2026-04-27 21:00:02', '2026-04-27 21:00:02', 3370, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'force_close', '2026-04-27 20:03:52'),
(151, 1, '2026-04-27 21:00:02', '2026-04-28 08:33:28', '2026-04-28 08:33:28', 41606, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'force_close', '2026-04-27 21:00:02'),
(153, 1, '2026-04-28 08:33:28', '2026-04-28 09:39:15', '2026-04-28 09:39:15', 3947, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'force_close', '2026-04-28 08:33:28'),
(155, 1, '2026-04-28 09:39:15', '2026-04-28 10:57:39', '2026-04-28 10:57:39', 4704, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'force_close', '2026-04-28 09:39:15'),
(156, 1, '2026-04-28 10:57:39', '2026-04-28 10:58:50', '2026-04-28 10:58:50', 71, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'force_close', '2026-04-28 10:57:39'),
(157, 1, '2026-04-28 10:58:50', '2026-04-28 11:09:10', '2026-04-28 11:09:10', 620, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'force_close', '2026-04-28 10:58:50'),
(158, 1, '2026-04-28 11:09:10', '2026-04-28 11:12:25', '2026-04-28 11:12:25', 195, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'force_close', '2026-04-28 11:09:10'),
(161, 1, '2026-04-28 11:12:25', '2026-04-28 19:22:02', '2026-04-28 19:22:02', 29377, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'force_close', '2026-04-28 11:12:25'),
(162, 1, '2026-04-28 19:22:02', '2026-04-29 18:39:00', '2026-04-29 18:39:00', 83818, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'force_close', '2026-04-28 19:22:02'),
(163, 1, '2026-04-29 18:39:00', '2026-05-05 19:34:14', '2026-05-05 19:34:14', 521714, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'force_close', '2026-04-29 18:39:00'),
(164, 1, '2026-05-05 19:34:14', '2026-05-05 19:36:36', '2026-05-05 19:36:36', 142, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'force_close', '2026-05-05 19:34:14'),
(166, 1, '2026-05-05 19:36:36', '2026-05-19 06:33:56', '2026-05-19 06:33:56', 1162640, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'force_close', '2026-05-05 19:36:36'),
(167, 1, '2026-05-19 06:33:56', '2026-05-19 07:24:27', '2026-05-19 07:24:27', 3031, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'force_close', '2026-05-19 06:33:56'),
(169, 1, '2026-05-19 07:24:27', '2026-05-19 07:49:23', '2026-05-19 07:49:23', 1496, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'force_close', '2026-05-19 07:24:27'),
(171, 1, '2026-05-19 07:49:23', '2026-05-19 10:53:55', '2026-05-19 10:53:55', 11072, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'force_close', '2026-05-19 07:49:23'),
(172, 1, '2026-05-19 10:53:55', '2026-05-19 11:40:33', '2026-05-19 11:40:33', 2798, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'force_close', '2026-05-19 10:53:55'),
(173, 1, '2026-05-19 11:40:33', '2026-05-21 19:47:28', '2026-05-21 19:47:28', 202015, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'force_close', '2026-05-19 11:40:33'),
(174, 1, '2026-05-21 19:47:28', NULL, '2026-05-21 19:47:28', 0, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-21 19:47:28'),
(175, 109, '2026-05-22 06:06:14', NULL, '2026-05-22 06:06:14', 0, 'web', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL, '2026-05-22 06:06:14');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payroll_items`
--
ALTER TABLE `payroll_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pi_run` (`payroll_run_id`),
  ADD KEY `idx_pi_type` (`type`);

--
-- Indexes for table `payroll_runs`
--
ALTER TABLE `payroll_runs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_emp_period` (`employee_id`,`period_year`,`period_month`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_perm` (`module`,`action`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_projects_owner` (`owner_id`),
  ADD KEY `idx_projects_due` (`due_date`);

--
-- Indexes for table `project_members`
--
ALTER TABLE `project_members`
  ADD PRIMARY KEY (`project_id`,`user_id`),
  ADD KEY `fk_pm_user` (`user_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `fk_rp_perm` (`permission_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `assignee_id` (`assignee_id`),
  ADD KEY `idx_tasks_status_due` (`status`,`due_date`),
  ADD KEY `idx_tasks_assignee` (`assignee_id`);

--
-- Indexes for table `task_messages`
--
ALTER TABLE `task_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tm_thread_created` (`thread_id`,`created_at`),
  ADD KEY `fk_tm_sender` (`sender_id`);

--
-- Indexes for table `task_message_reads`
--
ALTER TABLE `task_message_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_msg_user` (`message_id`,`user_id`),
  ADD KEY `idx_tmr_user` (`user_id`);

--
-- Indexes for table `task_threads`
--
ALTER TABLE `task_threads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `task_id` (`task_id`),
  ADD KEY `fk_tt_creator` (`created_by`);

--
-- Indexes for table `task_thread_participants`
--
ALTER TABLE `task_thread_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_thread_user` (`thread_id`,`user_id`),
  ADD KEY `idx_ttp_user` (`user_id`);

--
-- Indexes for table `tax_brackets`
--
ALTER TABLE `tax_brackets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tax_from_to` (`bracket_from`,`bracket_to`);

--
-- Indexes for table `time_entries`
--
ALTER TABLE `time_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_te_user_date` (`user_id`,`work_date`),
  ADD KEY `idx_te_task` (`task_id`),
  ADD KEY `fk_te_project` (`project_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_notification` (`user_id`,`notification_id`),
  ADD KEY `notification_id` (`notification_id`);

--
-- Indexes for table `work_sessions`
--
ALTER TABLE `work_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ws_user_started` (`user_id`,`started_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `payroll_items`
--
ALTER TABLE `payroll_items`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `payroll_runs`
--
ALTER TABLE `payroll_runs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `task_messages`
--
ALTER TABLE `task_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `task_message_reads`
--
ALTER TABLE `task_message_reads`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2263;

--
-- AUTO_INCREMENT for table `task_threads`
--
ALTER TABLE `task_threads`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `task_thread_participants`
--
ALTER TABLE `task_thread_participants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=701;

--
-- AUTO_INCREMENT for table `tax_brackets`
--
ALTER TABLE `tax_brackets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `time_entries`
--
ALTER TABLE `time_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=196;

--
-- AUTO_INCREMENT for table `work_sessions`
--
ALTER TABLE `work_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=176;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  ADD CONSTRAINT `fk_employee_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_items`
--
ALTER TABLE `payroll_items`
  ADD CONSTRAINT `fk_pi_run` FOREIGN KEY (`payroll_run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_runs`
--
ALTER TABLE `payroll_runs`
  ADD CONSTRAINT `fk_payroll_user` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_members`
--
ALTER TABLE `project_members`
  ADD CONSTRAINT `fk_pm_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`assignee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `task_messages`
--
ALTER TABLE `task_messages`
  ADD CONSTRAINT `fk_tm_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tm_thread` FOREIGN KEY (`thread_id`) REFERENCES `task_threads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_message_reads`
--
ALTER TABLE `task_message_reads`
  ADD CONSTRAINT `fk_tmr_msg` FOREIGN KEY (`message_id`) REFERENCES `task_messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tmr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_threads`
--
ALTER TABLE `task_threads`
  ADD CONSTRAINT `fk_tt_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tt_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_thread_participants`
--
ALTER TABLE `task_thread_participants`
  ADD CONSTRAINT `fk_ttp_thread` FOREIGN KEY (`thread_id`) REFERENCES `task_threads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ttp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `time_entries`
--
ALTER TABLE `time_entries`
  ADD CONSTRAINT `fk_te_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_te_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_te_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `user_notifications_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_notifications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_sessions`
--
ALTER TABLE `work_sessions`
  ADD CONSTRAINT `fk_ws_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
