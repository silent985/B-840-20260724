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
