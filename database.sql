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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. MESSAGES TABLE
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- INSERTING USERS
INSERT INTO users (name, email, password) VALUES 
('Iqbolshoh Ilhomjonov', 'iilhomjonov777@gmail.com', '$2y$10$FK1CG7WYwBbjC/rNTscuGOuH05Jqs.fxLxYB0rZ..Y1keEoDiEQMu'),
('Simple User', 'user@iqbolshoh.uz',  '$2y$10$FK1CG7WYwBbjC/rNTscuGOuH05Jqs.fxLxYB0rZ..Y1keEoDiEQMu');

INSERT INTO messages (sender_id, receiver_id, message) VALUES 
(1, 2, 'Hello! How are you?'),
(2, 1, 'I am good, thank you! How about you?'),
(1, 2, 'I am doing well too. What are you up to today?'),
(2, 1, 'Just working on some projects. You?'),
(1, 2, 'Same here. Just trying to finish up some tasks.'),
(2, 1, 'Good luck with that! Let me know if you need any help.'),
(1, 2, 'Thanks! I appreciate it.'),
(2, 1, 'No problem! Always happy to help.'),
(1, 2, 'By the way, have you seen the latest updates on our project?'),
(2, 1, 'Yes! I think they look great. We should discuss them in our next meeting.');

