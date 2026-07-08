-- DROP DATABASE IF EXISTS
DROP DATABASE IF EXISTS live_chat_db;

-- CREATE DATABASE
CREATE DATABASE live_chat_db;
USE live_chat_db;

-- 1. USERS TABLE
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. MESSAGES TABLE
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    type ENUM('text', 'image', 'audio') NOT NULL DEFAULT 'text',
    file_path VARCHAR(255) DEFAULT NULL,
    status ENUM('sent', 'read') DEFAULT 'sent',
    edited_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. MESSAGE REACTIONS TABLE
CREATE TABLE message_reactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    emoji VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reaction (message_id, user_id),
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- INSERTING USERS
INSERT INTO users (name, email, password) VALUES 
('Iqbolshoh Ilhomjonov', 'iilhomjonov777@gmail.com', '$2y$10$FK1CG7WYwBbjC/rNTscuGOuH05Jqs.fxLxYB0rZ..Y1keEoDiEQMu'),
('Simple User', 'user@iqbolshoh.uz',  '$2y$10$FK1CG7WYwBbjC/rNTscuGOuH05Jqs.fxLxYB0rZ..Y1keEoDiEQMu'),
('John Doe', 'john@iqbolshoh.uz', '$2y$10$FK1CG7WYwBbjC/rNTscuGOuH05Jqs.fxLxYB0rZ..Y1keEoDiEQMu');

INSERT INTO messages (sender_id, receiver_id, message, status) VALUES
(1, 2, 'Hello, how are you?', 'read'),
(2, 1, 'I am good, thank you! How about you?', 'read'),
(1, 2, 'I am doing well too. What are you up to today?', 'read'),
(2, 1, 'Just working on some projects. You?', 'sent'),
(1, 3, 'Hey John, long time no see!', 'read'),
(3, 1, 'Hey! Yeah, it has been a while. How have you been?', 'read'),
(1, 3, 'I have been good. Just busy with work.', 'sent'),
(3, 1, 'I understand. We should catch up sometime soon.', 'sent'),
(2, 3, 'Hi John! How are you doing?', 'read'),
(3, 2, 'Hi! I am doing well. How about you?', 'sent');

-- SEED REACTIONS
INSERT INTO message_reactions (message_id, user_id, emoji) VALUES
(1, 2, '👍'),
(2, 1, '❤️');

