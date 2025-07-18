-- --------------------------------------------------------
-- Hôte:                         127.0.0.1
-- Version du serveur:           8.4.3 - MySQL Community Server - GPL
-- SE du serveur:                Win64
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Listage de la structure de la base pour linkclick_db
CREATE DATABASE IF NOT EXISTS `linkclick_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `linkclick_db`;

-- Listage de la structure de table linkclick_db. comments
CREATE TABLE IF NOT EXISTS `comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `post_id` int NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listage des données de la table linkclick_db.comments : ~5 rows (environ)
INSERT INTO `comments` (`id`, `user_id`, `post_id`, `content`, `is_active`, `created_at`, `updated_at`) VALUES
	(1, 4, 1, 'Bienvenue ! J\'espère que tu vas aimer LinkClick.', 1, '2025-07-12 14:25:31', '2025-07-12 14:25:31'),
	(2, 5, 1, 'Super ! Une nouvelle personne dans la communauté.', 1, '2025-07-12 14:25:31', '2025-07-12 14:25:31'),
	(3, 3, 2, 'Merci ! L\'équipe a fait du bon travail sur l\'UX.', 1, '2025-07-12 14:25:31', '2025-07-12 14:25:31'),
	(4, 5, 3, 'Moi aussi ! Surtout le système de notifications.', 1, '2025-07-12 14:25:31', '2025-07-12 14:25:31'),
	(5, 3, 5, 'Merci ! C\'était important d\'avoir un chat fluide.', 1, '2025-07-12 14:25:31', '2025-07-12 14:25:31');

-- Listage de la structure de table linkclick_db. conversations
CREATE TABLE IF NOT EXISTS `conversations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user1_id` int NOT NULL,
  `user2_id` int NOT NULL,
  `last_message_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_conversation` (`user1_id`,`user2_id`),
  KEY `user2_id` (`user2_id`),
  KEY `idx_users` (`user1_id`,`user2_id`),
  KEY `last_message_id` (`last_message_id`),
  CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversations_ibfk_3` FOREIGN KEY (`last_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listage des données de la table linkclick_db.conversations : ~1 rows (environ)
INSERT INTO `conversations` (`id`, `user1_id`, `user2_id`, `last_message_id`, `created_at`, `updated_at`) VALUES
	(1, 7, 6, 2, '2025-07-12 15:44:40', '2025-07-12 16:05:08');

-- Listage de la structure de table linkclick_db. friendships
CREATE TABLE IF NOT EXISTS `friendships` (
  `id` int NOT NULL AUTO_INCREMENT,
  `requester_id` int NOT NULL,
  `addressee_id` int NOT NULL,
  `status` enum('pending','accepted','rejected','blocked') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_friendship` (`requester_id`,`addressee_id`),
  KEY `idx_requester` (`requester_id`),
  KEY `idx_addressee` (`addressee_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `friendships_ibfk_1` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `friendships_ibfk_2` FOREIGN KEY (`addressee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listage des données de la table linkclick_db.friendships : ~3 rows (environ)
INSERT INTO `friendships` (`id`, `requester_id`, `addressee_id`, `status`, `created_at`, `updated_at`) VALUES
	(1, 3, 4, 'accepted', '2025-07-12 14:25:31', '2025-07-12 14:25:31'),
	(2, 3, 5, 'accepted', '2025-07-12 14:25:31', '2025-07-12 14:25:31'),
	(3, 4, 5, 'pending', '2025-07-12 14:25:31', '2025-07-12 14:25:31'),
	(4, 7, 4, 'pending', '2025-07-12 16:01:37', '2025-07-12 16:01:37');

-- Listage de la structure de table linkclick_db. messages
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `conversation_id` int NOT NULL,
  `sender_id` int NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conversation` (`conversation_id`),
  KEY `idx_sender` (`sender_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listage des données de la table linkclick_db.messages : ~0 rows (environ)
INSERT INTO `messages` (`id`, `conversation_id`, `sender_id`, `content`, `image`, `is_read`, `created_at`) VALUES
	(1, 1, 7, 'salut', NULL, 0, '2025-07-12 16:04:55'),
	(2, 1, 7, 'cheyyyyy', NULL, 0, '2025-07-12 16:05:08');

-- Listage de la structure de table linkclick_db. notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` enum('friend_request','friend_accepted','post_like','post_comment','message') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `related_id` int DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listage des données de la table linkclick_db.notifications : ~0 rows (environ)
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `content`, `related_id`, `is_read`, `created_at`) VALUES
	(1, 4, 'friend_request', 'Nouvelle demande d\'ami', 'Armando Bradshaw souhaite devenir votre ami', 7, 0, '2025-07-12 16:01:37'),
	(2, 6, 'message', 'Nouveau message', 'Vous avez reçu un nouveau message', 7, 0, '2025-07-12 16:04:55'),
	(3, 6, 'message', 'Nouveau message', 'Vous avez reçu un nouveau message', 7, 0, '2025-07-12 16:05:08');

-- Listage de la structure de table linkclick_db. posts
CREATE TABLE IF NOT EXISTS `posts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `likes_count` int DEFAULT '0',
  `dislikes_count` int DEFAULT '0',
  `comments_count` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listage des données de la table linkclick_db.posts : ~5 rows (environ)
INSERT INTO `posts` (`id`, `user_id`, `content`, `image`, `likes_count`, `dislikes_count`, `comments_count`, `is_active`, `created_at`, `updated_at`) VALUES
	(1, 3, 'Bienvenue sur LinkClick ! Hâte de découvrir cette nouvelle plateforme sociale.', NULL, 2, 0, 2, 1, '2025-07-12 14:25:31', '2025-07-12 14:25:31'),
	(2, 4, 'Première publication sur LinkClick. L\'interface est vraiment intuitive !', NULL, 2, 0, 1, 1, '2025-07-12 14:25:31', '2025-07-12 14:25:31'),
	(3, 5, 'Qui d\'autre est excité par les nouvelles fonctionnalités de LinkClick ?', NULL, 2, 0, 1, 1, '2025-07-12 14:25:31', '2025-07-12 14:25:31'),
	(4, 3, 'Les notifications en temps réel fonctionnent parfaitement. Bravo à l\'équipe !', NULL, 2, 0, 0, 1, '2025-07-12 14:25:31', '2025-07-12 14:25:31'),
	(5, 4, 'J\'adore le système de chat intégré. Très pratique pour rester en contact.', NULL, 2, 0, 1, 1, '2025-07-12 14:25:31', '2025-07-12 14:25:31'),
	(6, 6, 'Florent', NULL, 1, 0, 0, 1, '2025-07-12 14:49:58', '2025-07-12 14:58:01'),
	(7, 7, 'salut', 'assets/uploads/posts/post_7_6872881ef2d4a_1752336414.png', 0, 0, 0, 1, '2025-07-12 16:06:54', '2025-07-12 16:06:55');

-- Listage de la structure de table linkclick_db. post_reactions
CREATE TABLE IF NOT EXISTS `post_reactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `post_id` int NOT NULL,
  `type` enum('like','dislike') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_post` (`user_id`,`post_id`),
  KEY `idx_post_type` (`post_id`,`type`),
  CONSTRAINT `post_reactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_reactions_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listage des données de la table linkclick_db.post_reactions : ~10 rows (environ)
INSERT INTO `post_reactions` (`id`, `user_id`, `post_id`, `type`, `created_at`) VALUES
	(1, 4, 1, 'like', '2025-07-12 14:25:31'),
	(2, 5, 1, 'like', '2025-07-12 14:25:31'),
	(3, 3, 2, 'like', '2025-07-12 14:25:31'),
	(4, 5, 2, 'like', '2025-07-12 14:25:31'),
	(5, 3, 3, 'like', '2025-07-12 14:25:31'),
	(6, 4, 3, 'like', '2025-07-12 14:25:31'),
	(7, 4, 4, 'like', '2025-07-12 14:25:31'),
	(8, 5, 4, 'like', '2025-07-12 14:25:31'),
	(9, 3, 5, 'like', '2025-07-12 14:25:31'),
	(10, 5, 5, 'like', '2025-07-12 14:25:31'),
	(12, 6, 6, 'like', '2025-07-12 14:58:01');

-- Listage de la structure de table linkclick_db. users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `firstname` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lastname` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `role` enum('user','moderator','admin') COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT '1',
  `email_verified` tinyint(1) DEFAULT '0',
  `verification_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listage des données de la table linkclick_db.users : ~7 rows (environ)
INSERT INTO `users` (`id`, `firstname`, `lastname`, `email`, `password`, `avatar`, `bio`, `role`, `is_active`, `email_verified`, `verification_token`, `reset_token`, `reset_token_expires`, `created_at`, `updated_at`) VALUES
	(1, 'Admin', 'LinkClick', 'admin@linkclick.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'admin', 1, 1, NULL, NULL, NULL, '2025-07-12 14:25:31', '2025-07-12 14:25:31'),
	(2, 'Modérateur', 'Test', 'moderator@linkclick.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'moderator', 1, 1, NULL, NULL, NULL, '2025-07-12 14:25:31', '2025-07-12 14:25:31'),
	(3, 'Jean', 'Dupont', 'jean.dupont@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'user', 1, 1, NULL, NULL, NULL, '2025-07-12 14:25:31', '2025-07-12 14:25:31'),
	(4, 'Marie', 'Martin', 'marie.martin@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'user', 1, 1, NULL, NULL, NULL, '2025-07-12 14:25:31', '2025-07-12 14:25:31'),
	(5, 'Pierre', 'Durand', 'pierre.durand@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'user', 1, 1, NULL, NULL, NULL, '2025-07-12 14:25:31', '2025-07-12 14:25:31'),
	(6, 'Demetria', 'Gould', 'syzyqa@mailinator.com', '$2y$12$4ob6HVBzFhIx1kU3Y5to/eZsw/YPWNI7Ran8f310WUYNma8bhPRXO', NULL, NULL, 'user', 1, 1, '4f2ed9eb03c8eb3fc7f8422643a643cbdaf1e43577e0d5a4cc39f17c9846429a', NULL, NULL, '2025-07-12 14:25:39', '2025-07-12 14:26:42'),
	(7, 'Armando', 'Bradshaw', 'bawok@mailinator.com', '$2y$12$ozS1TdOXs5aTAZRzJMmJluXPtU2ZRGMyp5tvHGcit4aIn0I5/9fJC', 'assets/uploads/profiles/profile_7_687287e89dfa5_1752336360.png', 'qwertyu', 'user', 1, 1, '3977238e08886f220af7335feacdbba58cc2a680aefb2ceb6cee515b751a582f', NULL, NULL, '2025-07-12 15:42:27', '2025-07-12 16:06:00');

-- Listage de la structure de table linkclick_db. user_sessions
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listage des données de la table linkclick_db.user_sessions : ~0 rows (environ)
INSERT INTO `user_sessions` (`id`, `user_id`, `token`, `ip_address`, `user_agent`, `expires_at`, `created_at`) VALUES
	(1, 6, 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjo2LCJlbWFpbCI6InN5enlxYUBtYWlsaW5hdG9yLmNvbSIsInJvbGUiOiJ1c2VyIiwiaWF0IjoxNzUyMzMwNDEwLCJleHAiOjE3NTI0MTY4MTB9.MMzaN4uO8nn42XrE1tYmb8fcmfl8xrrM2xDh-j2SrWg', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-13 14:26:50', '2025-07-12 14:26:50'),
	(2, 6, 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjo2LCJlbWFpbCI6InN5enlxYUBtYWlsaW5hdG9yLmNvbSIsInJvbGUiOiJ1c2VyIiwiaWF0IjoxNzUyMzMwNDMzLCJleHAiOjE3NTI0MTY4MzN9.6DQomaNCuSIo3b2GP-K9BZLSx6FBmUMIODkQRpMJ7hY', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-13 14:27:13', '2025-07-12 14:27:13'),
	(3, 6, 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjo2LCJlbWFpbCI6InN5enlxYUBtYWlsaW5hdG9yLmNvbSIsInJvbGUiOiJ1c2VyIiwiaWF0IjoxNzUyMzMwODc4LCJleHAiOjE3NTI0MTcyNzh9.NCynd1PrMVqryVzbOZHQiXYur0n94G4Vgt0a2unauYI', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-13 14:34:38', '2025-07-12 14:34:38'),
	(4, 6, 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjo2LCJlbWFpbCI6InN5enlxYUBtYWlsaW5hdG9yLmNvbSIsInJvbGUiOiJ1c2VyIiwiaWF0IjoxNzUyMzMxMTAzLCJleHAiOjE3NTI0MTc1MDN9.4KJRnTdFRGe6AMvoiSSZ_uKFaGJkp0YN3G_hmw2w0tw', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-13 14:38:23', '2025-07-12 14:38:23'),
	(5, 6, 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjo2LCJlbWFpbCI6InN5enlxYUBtYWlsaW5hdG9yLmNvbSIsInJvbGUiOiJ1c2VyIiwiaWF0IjoxNzUyMzMxMTQ3LCJleHAiOjE3NTI0MTc1NDd9.udfcNX78yGmR4N6Kv24IA1oH8uWP8SLZF_P9bP63hOM', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-13 14:39:07', '2025-07-12 14:39:07'),
	(6, 6, 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjo2LCJlbWFpbCI6InN5enlxYUBtYWlsaW5hdG9yLmNvbSIsInJvbGUiOiJ1c2VyIiwiaWF0IjoxNzUyMzMxMzg2LCJleHAiOjE3NTI0MTc3ODZ9.bFyWYR87FF6MfNkai_vn4TMxh9b244RgoJZjUuIhqTA', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-13 14:43:06', '2025-07-12 14:43:06'),
	(7, 6, 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjo2LCJlbWFpbCI6InN5enlxYUBtYWlsaW5hdG9yLmNvbSIsInJvbGUiOiJ1c2VyIiwiaWF0IjoxNzUyMzM1MDE1LCJleHAiOjE3NTI0MjE0MTV9.4Ne6DylHMN3KYElVPmvww7AB0nCyXdNrVMO3hERi1lY', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-13 15:43:35', '2025-07-12 15:43:35'),
	(8, 6, 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjo2LCJlbWFpbCI6InN5enlxYUBtYWlsaW5hdG9yLmNvbSIsInJvbGUiOiJ1c2VyIiwiaWF0IjoxNzUyMzM1MDIzLCJleHAiOjE3NTI0MjE0MjN9.D_sJ3IidywEDXqIh-FhrrK5HnphF-cbDJAJroE_7Jxo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-13 15:43:43', '2025-07-12 15:43:43'),
	(9, 7, 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjo3LCJlbWFpbCI6ImJhd29rQG1haWxpbmF0b3IuY29tIiwicm9sZSI6InVzZXIiLCJpYXQiOjE3NTIzMzUwNDksImV4cCI6MTc1MjQyMTQ0OX0.AJrvII-chwu21Uxq1UdkiZNOOkb92HBBDeL9xPsdhXQ', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-13 15:44:09', '2025-07-12 15:44:09');

-- Listage de la structure de déclencheur linkclick_db. update_comments_count
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
DELIMITER //
CREATE TRIGGER `update_comments_count` AFTER INSERT ON `comments` FOR EACH ROW BEGIN
    UPDATE posts 
    SET comments_count = (SELECT COUNT(*) FROM comments WHERE post_id = NEW.post_id AND is_active = TRUE)
    WHERE id = NEW.post_id;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Listage de la structure de déclencheur linkclick_db. update_comments_count_delete
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
DELIMITER //
CREATE TRIGGER `update_comments_count_delete` AFTER DELETE ON `comments` FOR EACH ROW BEGIN
    UPDATE posts 
    SET comments_count = (SELECT COUNT(*) FROM comments WHERE post_id = OLD.post_id AND is_active = TRUE)
    WHERE id = OLD.post_id;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Listage de la structure de déclencheur linkclick_db. update_comments_count_update
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
DELIMITER //
CREATE TRIGGER `update_comments_count_update` AFTER UPDATE ON `comments` FOR EACH ROW BEGIN
    UPDATE posts 
    SET comments_count = (SELECT COUNT(*) FROM comments WHERE post_id = NEW.post_id AND is_active = TRUE)
    WHERE id = NEW.post_id;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Listage de la structure de déclencheur linkclick_db. update_conversation_last_message
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
DELIMITER //
CREATE TRIGGER `update_conversation_last_message` AFTER INSERT ON `messages` FOR EACH ROW BEGIN
    UPDATE conversations 
    SET 
        last_message_id = NEW.id,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = NEW.conversation_id;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Listage de la structure de déclencheur linkclick_db. update_post_reactions_count
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
DELIMITER //
CREATE TRIGGER `update_post_reactions_count` AFTER INSERT ON `post_reactions` FOR EACH ROW BEGIN
    UPDATE posts 
    SET 
        likes_count = (SELECT COUNT(*) FROM post_reactions WHERE post_id = NEW.post_id AND type = 'like'),
        dislikes_count = (SELECT COUNT(*) FROM post_reactions WHERE post_id = NEW.post_id AND type = 'dislike')
    WHERE id = NEW.post_id;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Listage de la structure de déclencheur linkclick_db. update_post_reactions_count_delete
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
DELIMITER //
CREATE TRIGGER `update_post_reactions_count_delete` AFTER DELETE ON `post_reactions` FOR EACH ROW BEGIN
    UPDATE posts 
    SET 
        likes_count = (SELECT COUNT(*) FROM post_reactions WHERE post_id = OLD.post_id AND type = 'like'),
        dislikes_count = (SELECT COUNT(*) FROM post_reactions WHERE post_id = OLD.post_id AND type = 'dislike')
    WHERE id = OLD.post_id;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Listage de la structure de déclencheur linkclick_db. update_post_reactions_count_update
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
DELIMITER //
CREATE TRIGGER `update_post_reactions_count_update` AFTER UPDATE ON `post_reactions` FOR EACH ROW BEGIN
    UPDATE posts 
    SET 
        likes_count = (SELECT COUNT(*) FROM post_reactions WHERE post_id = NEW.post_id AND type = 'like'),
        dislikes_count = (SELECT COUNT(*) FROM post_reactions WHERE post_id = NEW.post_id AND type = 'dislike')
    WHERE id = NEW.post_id;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
