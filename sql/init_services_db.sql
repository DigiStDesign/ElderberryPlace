-- ===========================================
-- Create categories table
-- ===========================================
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

-- Optional: insert default categories
INSERT INTO categories (name) VALUES
('Personal Care'),
('Medical Services'),
('Social Activities');

-- ===========================================
-- Create services table (with category FK)
-- ===========================================
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category_id INT NOT NULL,
    description TEXT,
    duration_minutes_min INT,
    duration_minutes_max INT,
    frequency VARCHAR(50),
    cost DECIMAL(6,2),
    status ENUM('Active', 'Scheduled', 'Inactive') DEFAULT 'Scheduled',
    rating DECIMAL(2,1),
    completion_rate TINYINT,
    weekly_appointments INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_services_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

-- ===========================================
-- Create staff_assignments table
-- ===========================================
CREATE TABLE staff_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    staff_id INT NOT NULL,

    FOREIGN KEY (service_id) REFERENCES services(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);