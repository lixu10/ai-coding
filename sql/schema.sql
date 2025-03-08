CREATE DATABASE IF NOT EXISTS lingxiao_code CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE lingxiao_code;

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    verification_code VARCHAR(6) DEFAULT NULL,
    verification_expires TIMESTAMP NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    points INT NOT NULL DEFAULT 100,
    status ENUM('active', 'banned') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;

-- 可选：记录生成请求
CREATE TABLE IF NOT EXISTS `code_requests` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `title` TEXT NOT NULL,
    `input_content` MEDIUMTEXT NOT NULL,
    `output_content` MEDIUMTEXT NOT NULL,
    `samples` MEDIUMTEXT NOT NULL,
    `time_limit` int(11) NOT NULL DEFAULT 1000,
    `memory_limit` int(11) NOT NULL DEFAULT 65536,
    `user_code` MEDIUMTEXT,
    `language` varchar(50) NOT NULL,
    `generated_code` MEDIUMTEXT NOT NULL,
    `thinking_process` MEDIUMTEXT,
    `solution_approach` MEDIUMTEXT,
    `related_knowledge` MEDIUMTEXT,
    `code_bugs` MEDIUMTEXT,
    `pseudo_code` tinyint(1) NOT NULL DEFAULT 0,
    `add_comments` tinyint(1) NOT NULL DEFAULT 0,
    `normal_naming` tinyint(1) NOT NULL DEFAULT 0,
    `additional_requirements` MEDIUMTEXT,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `code_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 管理员表
CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 初始管理员账号
INSERT INTO admins (username, password_hash) VALUES ('lxzsnb', '$2y$10$' || SUBSTRING(SHA2('lxzsnb', 256), 1, 22));

-- 模型表
CREATE TABLE IF NOT EXISTS `models` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL UNIQUE,
    `sort_order` int(11) NOT NULL DEFAULT 0,
    `points_consumption` int(11) NOT NULL DEFAULT 10,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 添加默认模型
INSERT INTO `models` (`name`, `sort_order`, `points_consumption`) VALUES 
('gpt-4', 100, 20),
('gpt-3.5-turbo', 90, 10);

-- 充值码表
CREATE TABLE IF NOT EXISTS recharge_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,  -- 充值码
    points INT NOT NULL,               -- 充值积分数量
    remaining_uses INT NOT NULL,       -- 剩余可用次数
    total_uses INT NOT NULL,           -- 总可用次数
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED,           -- 创建管理员ID
    FOREIGN KEY (created_by) REFERENCES admins(id)
) ENGINE=InnoDB;

-- 充值记录表
CREATE TABLE IF NOT EXISTS recharge_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    code_id INT UNSIGNED NOT NULL,
    points INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (code_id) REFERENCES recharge_codes(id)
) ENGINE=InnoDB; 