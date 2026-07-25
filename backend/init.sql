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

-- 每次答题的学习记录：单词、答题结果和学习时间
-- word_id 允许为空且删除单词时置空（ON DELETE SET NULL），历史统计不因删词而丢失；
-- word_snapshot 保存答题时的单词文本快照，删词后仍可追溯；
-- client_token 必填且唯一，用于幂等，避免请求重试造成重复记录。
CREATE TABLE IF NOT EXISTS study_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    word_id INT NULL,
    word_snapshot VARCHAR(255) NOT NULL,
    is_correct TINYINT(1) NOT NULL,
    client_token VARCHAR(64) NOT NULL UNIQUE,
    studied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE SET NULL,
    INDEX idx_studied_at (studied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 错词集：答错的单词自动进入，可复习并移除；每个单词仅保留一条记录。
-- 复习依赖单词的释义，故随单词删除而级联清除（历史统计另由 study_records 保留）。
CREATE TABLE IF NOT EXISTS wrong_words (
    id INT AUTO_INCREMENT PRIMARY KEY,
    word_id INT NOT NULL UNIQUE,
    wrong_count INT NOT NULL DEFAULT 1,
    last_wrong_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (word_id) REFERENCES words(id) ON DELETE CASCADE
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
