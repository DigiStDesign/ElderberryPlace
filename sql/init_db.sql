-- ===========================================
-- RESET SCRIPT: Drop all relevant tables
-- ===========================================
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS 
    resident_schedule,
    staff_schedule,
    service_schedule,
    staff_assignments,
    services,
    residents,
    staff,
    categories;

SET FOREIGN_KEY_CHECKS = 1;

-- ===========================================
-- Create categories table
-- ===========================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insert default service/activity categories
INSERT INTO categories (name) VALUES
('Physiotherapy'),
('Nursing'),
('Occupational Therapy'),
('Personal Care'),
('Activity Coordination'),
('Medical Services'),
('Dementia Support'),
('Volunteering'),
('Support Work');

-- ===========================================
-- Create staff table
-- ===========================================
CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(50) NOT NULL,
    email VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insert default staff members
INSERT INTO staff (name, role, email) VALUES
('John Johnson', 'Physiotherapist', 'john.johnson@example.com'),
('Maria Gonzales', 'Nurse', 'maria.gonzales@example.com'),
('Sam Patel', 'Occupational Therapist', 'sam.patel@example.com'),
('Emily Nguyen', 'Personal Carer', 'emily.nguyen@example.com'),
('Alan Smith', 'Activity Coordinator', 'alan.smith@example.com'),
('Grace Lee', 'Medical Services', 'grace.lee@example.com'),
('Robert Chen', 'Dementia Specialist', 'robert.chen@example.com'),
('Lina Davies', 'Volunteer', 'lina.davies@example.com'),
('Tony Brooks', 'Support Worker', 'tony.brooks@example.com');


-- ===========================================
-- Create residents table
-- ===========================================
CREATE TABLE IF NOT EXISTS residents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    room_number VARCHAR(20)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insert default residents
INSERT INTO residents (name, room_number) VALUES
('Jill Valentine', 'A101'),
('Leon Kennedy', 'A102'),
('Claire Redfield', 'B201'),
('Chris Redfield', 'B202'),
('Ada Wong', 'C301'),
('Barry Burton', 'C302'),
('Rebecca Chambers', 'D401'),
('Hunk Unknown', 'D402'),
('Sherry Birkin', 'E501');

-- ===========================================
-- Create services table
-- ===========================================
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category_id INT NOT NULL,
    description TEXT,
    duration_minutes_min INT,
    duration_minutes_max INT,
    frequency VARCHAR(50),
    cost DECIMAL(6,2),
    status ENUM('Active', 'Scheduled', 'Inactive') DEFAULT 'Inactive',
    rating DECIMAL(2,1),
    completion_rate TINYINT,
    weekly_appointments INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_services_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ===========================================
-- Insert default services
-- ===========================================
INSERT INTO services (
    name, category_id, description, duration_minutes_min, duration_minutes_max,
    frequency, cost, status, rating, completion_rate, weekly_appointments
) VALUES
('Physiotherapy Session', 1, 'Mobility and rehabilitation exercises', 30, 60, 'Weekly', 75.00, 'Inactive', 4.5, 90, 5),
('Nursing Check-up', 2, 'General nursing care and health monitoring', 20, 40, 'Bi-weekly', 50.00, 'Inactive', 4.7, 95, 8),
('Occupational Therapy', 3, 'Support for daily living activities and independence', 30, 60, 'Weekly', 70.00, 'Inactive', 4.6, 85, 4),
('Personal Care Support', 4, 'Assistance with hygiene and grooming', 15, 30, 'Daily', 35.00, 'Inactive', 4.3, 88, 12),
('Recreational Activities', 5, 'Group games and social events', 60, 90, 'Weekly', 20.00, 'Inactive', 4.9, 98, 2),
('Medical Consultation', 6, 'On-site doctor visits or specialist reviews', 15, 30, 'As needed', 120.00, 'Inactive', 4.2, 92, 3),
('Dementia Care Program', 7, 'Cognitive support and memory exercises', 30, 60, 'Weekly', 60.00, 'Inactive', 4.8, 96, 2),
('Volunteer Reading Hour', 8, 'Reading and companionship sessions', 30, 60, 'Weekly', 0.00, 'Inactive', 5.0, 100, 1),
('Mobility Support', 9, 'Assistance with walking or wheelchair use', 15, 30, 'Daily', 40.00, 'Inactive', 4.4, 87, 7);


-- ===========================================
-- Create staff_assignments table
-- ===========================================
CREATE TABLE IF NOT EXISTS staff_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    staff_id INT NOT NULL,

    FOREIGN KEY (service_id) REFERENCES services(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ===========================================
-- Create service_schedule table
-- ===========================================
CREATE TABLE IF NOT EXISTS service_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    location VARCHAR(100),
    notes TEXT,
    FOREIGN KEY (service_id) REFERENCES services(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ===========================================
-- Create staff_schedule table
-- ===========================================
CREATE TABLE IF NOT EXISTS staff_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    schedule_id INT NOT NULL,
    FOREIGN KEY (staff_id) REFERENCES staff(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES service_schedule(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ===========================================
-- Create resident_schedule table
-- ===========================================
CREATE TABLE IF NOT EXISTS resident_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resident_id INT NOT NULL,
    schedule_id INT NOT NULL,
    FOREIGN KEY (resident_id) REFERENCES residents(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES service_schedule(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
