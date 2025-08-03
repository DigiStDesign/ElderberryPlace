CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);
--Insert some defaults
INSERT INTO categories (name) VALUES
('Personal Care'),
('Medical Services'),
('Social Activities')