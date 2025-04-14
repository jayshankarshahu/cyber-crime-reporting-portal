CREATE DATABASE cyber_crime_db;
use cyber_crime_db;

CREATE TABLE `complaints` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `complain_type` enum('woman-child-related-crime','financial-fraud','other') NOT NULL,
  `description` longtext NOT NULL,
  `status` enum('pending','in-process','resolved') NOT NULL DEFAULT 'pending',
  `createdBy` int(11) NOT NULL,
  `createdAt` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
);

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mobile` varchar(10) NOT NULL,
  `password` varchar(72) NOT NULL,
  `type` enum('user' , 'admin') not null default 'user',
  PRIMARY KEY (`id`),
  UNIQUE KEY `mobile` (`mobile`)
);
  
INSERT IGNORE INTO users  (password, mobile , type ) VALUES ('$2a$12$jXGlkGS8eN7S0v2uGjEXE.QTaTGAquuOHs6cpRAT576OoSzTOge4O', 0000000000 , 'admin')--admin password is password 

CREATE TABLE news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    created_at INT NOT NULL DEFAULT(unix_timestamp()),
    created_by INT NOT NULL
);    