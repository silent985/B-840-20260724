CREATE DATABASE IF NOT EXISTS labelease;
USE labelease;

CREATE TABLE IF NOT EXISTS words (
    id INT AUTO_INCREMENT PRIMARY KEY,
    word VARCHAR(255) NOT NULL,
    definition TEXT NOT NULL,
    example TEXT,
    mastered TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO words (word, definition, example) VALUES
('abandon', 'to leave without intending to return', 'The hikers had to abandon their plans due to bad weather.'),
('benevolent', 'well meaning and kindly', 'The benevolent donor contributed millions to charity.'),
('catalyst', 'a person or thing that precipitates change', 'She was the catalyst for the companys transformation.'),
('diligent', 'having or showing care in ones work', 'The diligent researcher verified every fact.'),
('ephemeral', 'lasting for a very short time', 'Fashions are ephemeral, changing with every season.'),
('fortitude', 'courage in pain or adversity', 'She bore her illness with remarkable fortitude.'),
('gregarious', 'fond of company; sociable', 'His gregarious nature made him popular at parties.'),
('hierarchy', 'a system in which people are ranked', 'The corporate hierarchy was clearly defined.'),
('integral', 'necessary to make a whole complete', 'Teamwork is integral to our success.'),
('juxtapose', 'to place close together for contrasting effect', 'The artist juxtaposed light and dark colors.');

CREATE TABLE IF NOT EXISTS study_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(64) NOT NULL,
    word_id INT NOT NULL,
    word_snapshot VARCHAR(255) NOT NULL,
    is_correct TINYINT(1) NOT NULL,
    studied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_request (request_id),
    INDEX idx_studied_at (studied_at),
    INDEX idx_word_id (word_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wrong_words (
    id INT AUTO_INCREMENT PRIMARY KEY,
    word_id INT NOT NULL,
    review_count INT DEFAULT 0,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_reviewed_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_word (word_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
