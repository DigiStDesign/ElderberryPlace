-- ===========================================
-- RESET: Drop tables in any order (FKs off)
-- ===========================================
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS
  visit_requests,
  visitor_resident_links,
  relationship_types,
  resident_schedule,
  staff_schedule,
  staff_assignments,
  service_schedule,
  services,
  categories,
  visitor_profiles,
  resident_profiles,
  staff_profiles,
  staff_jobs,
  users,
  roles;

SET FOREIGN_KEY_CHECKS = 1;

-- ===========================================
-- Base: roles & users (all logins live here)
-- ===========================================
CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE  -- 'ADMIN','STAFF','RESIDENT','VISITOR'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  full_name VARCHAR(190) NOT NULL,
  email VARCHAR(120) NULL,
  -- Keep 255 to allow future upgrade from MD5 to password_hash()/bcrypt/argon2
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('ADMIN','STAFF','RESIDENT','VISITOR') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- (Optional) seed roles (kept for clarity)
INSERT INTO roles (name) VALUES ('ADMIN'), ('STAFF'), ('RESIDENT'), ('VISITOR');

-- Demo users (MD5 only for demo)
INSERT INTO users (username, full_name, email, password_hash, role) VALUES
('admin',    'Adam Administrator', 'admin@example.com',    MD5('admin'),    'ADMIN'),
('staff',    'John Staff',         'staff@example.com',    MD5('staff'),    'STAFF'),
('resident', 'Jill Resident',      'resident@example.com', MD5('resident'), 'RESIDENT'),
('visitor',  'Victoria Visitor',   'visitor@example.com',  MD5('visitor'),  'VISITOR');

-- ===========================================
-- Role-specific profiles (optional 1:1 rows)
-- ===========================================
CREATE TABLE staff_jobs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE   -- 'Registered Nurse','Care Worker',...
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO staff_jobs (name) VALUES
('Physiotherapist'),          
('Registered Nurse'),         
('Occupational Therapist'),   
('Personal Carer'),           
('Activity Coordinator'),     
('Medical Officer'),          
('Dementia Care Specialist'), 
('Volunteer'),                
('Support Worker');           

CREATE TABLE staff_profiles (
  user_id INT UNSIGNED PRIMARY KEY,
  staff_job_id INT UNSIGNED NOT NULL,
  started_on DATE NULL,
  notes TEXT NULL,
  CONSTRAINT fk_sp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_sp_job  FOREIGN KEY (staff_job_id) REFERENCES staff_jobs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE resident_profiles (
  user_id INT UNSIGNED PRIMARY KEY,
  -- You can rely on users.full_name; add extra resident-only fields here:
  dob DATE NULL,
  room_number VARCHAR(20) NULL,
  care_notes TEXT NULL,
  CONSTRAINT fk_rp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE visitor_profiles (
  user_id INT UNSIGNED PRIMARY KEY,
  phone VARCHAR(40) NULL,
  verified TINYINT(1) NOT NULL DEFAULT 0,
  verified_at DATETIME NULL,
  verified_by INT UNSIGNED NULL,
  CONSTRAINT fk_vp_user        FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_vp_verified_by FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ===========================================
-- Relationship types & Visitor<->Resident links
-- ===========================================
CREATE TABLE relationship_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO relationship_types (name) VALUES
('Parent'),('Child'),
('Spouse'),('Sibling'),('Guardian'),('Friend'),('Other');

CREATE TABLE visitor_resident_links (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visitor_user_id  INT UNSIGNED NOT NULL,  -- users.id (role=VISITOR) - enforce in code
  resident_user_id INT UNSIGNED NOT NULL,  -- users.id (role=RESIDENT) - enforce in code
  relationship_type_id INT UNSIGNED NOT NULL,
  is_primary_contact TINYINT(1) NOT NULL DEFAULT 0,
  start_date DATE NULL,
  end_date DATE NULL,
  notes VARCHAR(255) NULL,
  CONSTRAINT fk_vrl_visitor   FOREIGN KEY (visitor_user_id)  REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_vrl_resident  FOREIGN KEY (resident_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_vrl_type      FOREIGN KEY (relationship_type_id) REFERENCES relationship_types(id),
  UNIQUE KEY uq_vrl (visitor_user_id, resident_user_id, relationship_type_id),
  INDEX idx_vrl_resident (resident_user_id),
  INDEX idx_vrl_visitor (visitor_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ===========================================
-- Services catalog and scheduling
-- ===========================================
CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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

CREATE TABLE services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  description TEXT,
  duration_minutes_min INT UNSIGNED NULL,
  duration_minutes_max INT UNSIGNED NULL,
  frequency VARCHAR(50),
  cost DECIMAL(8,2) NULL,
  status ENUM('Active','Scheduled','Inactive') DEFAULT 'Inactive',
  rating DECIMAL(2,1) NULL,
  completion_rate TINYINT UNSIGNED NULL,
  weekly_appointments INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_services_category FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_services_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO services
(name, category_id, description, duration_minutes_min, duration_minutes_max, frequency, cost, status, rating, completion_rate, weekly_appointments)
VALUES
('Physiotherapy Session', 1, 'Mobility and rehabilitation exercises', 30, 60, 'Weekly', 75.00, 'Inactive', 4.5, 90, 5),
('Nursing Check-up',      2, 'General nursing care and health monitoring', 20, 40, 'Bi-weekly', 50.00, 'Inactive', 4.7, 95, 8),
('Occupational Therapy',  3, 'Support for daily living activities and independence', 30, 60, 'Weekly', 70.00, 'Inactive', 4.6, 85, 4),
('Personal Care Support', 4, 'Assistance with hygiene and grooming', 15, 30, 'Daily', 35.00, 'Inactive', 4.3, 88, 12),
('Recreational Activities',5,'Group games and social events', 60, 90, 'Weekly', 20.00, 'Inactive', 4.9, 98, 2),
('Medical Consultation',  6, 'On-site doctor visits or specialist reviews', 15, 30, 'As needed', 120.00, 'Inactive', 4.2, 92, 3),
('Dementia Care Program', 7, 'Cognitive support and memory exercises', 30, 60, 'Weekly', 60.00, 'Inactive', 4.8, 96, 2),
('Volunteer Reading Hour',8, 'Reading and companionship sessions', 30, 60, 'Weekly', 0.00, 'Inactive', 5.0, 100, 1),
('Mobility Support',      9, 'Assistance with walking or wheelchair use', 15, 30, 'Daily', 40.00, 'Inactive', 4.4, 87, 7);

-- A scheduled instance of a service (time/place)
CREATE TABLE service_schedule (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  start_time DATETIME NOT NULL,
  end_time   DATETIME NOT NULL,
  location VARCHAR(100),
  notes TEXT,
  CONSTRAINT fk_ss_service FOREIGN KEY (service_id) REFERENCES services(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_ss_service (service_id),
  INDEX idx_ss_time (start_time),
  INDEX idx_ss_end  (end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Which STAFF user(s) are assigned to a scheduled service
CREATE TABLE staff_assignments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,      -- direct link to the service (optional but handy)
  schedule_id INT UNSIGNED NOT NULL,     -- FK to service_schedule
  staff_user_id INT UNSIGNED NOT NULL,   -- users.id (role=STAFF) -- enforce in PHP
  CONSTRAINT fk_sa_service   FOREIGN KEY (service_id)  REFERENCES services(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sa_schedule  FOREIGN KEY (schedule_id) REFERENCES service_schedule(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sa_staffuser FOREIGN KEY (staff_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uq_sa (schedule_id, staff_user_id),
  INDEX idx_sa_staff (staff_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Which STAFF user(s) are scheduled when (redundant to staff_assignments but useful for queries)
-- If you prefer, you can drop this table. Otherwise it mirrors the old design but uses users.
CREATE TABLE staff_schedule (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_user_id INT UNSIGNED NOT NULL,   -- users.id (role=STAFF)
  schedule_id INT UNSIGNED NOT NULL,     -- service_schedule.id
  CONSTRAINT fk_ss_user     FOREIGN KEY (staff_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ss_schedule FOREIGN KEY (schedule_id)   REFERENCES service_schedule(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uq_staff_slot (staff_user_id, schedule_id),
  INDEX idx_staff_user (staff_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Which RESIDENT user(s) are booked into a scheduled service
CREATE TABLE resident_schedule (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resident_user_id INT UNSIGNED NOT NULL,  -- users.id (role=RESIDENT)
  schedule_id INT UNSIGNED NOT NULL,       -- service_schedule.id
  status ENUM('BOOKED','ATTENDED','CANCELLED','NOSHOW') NOT NULL DEFAULT 'BOOKED',
  notes TEXT NULL,
  CONSTRAINT fk_rs_resident FOREIGN KEY (resident_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_rs_schedule FOREIGN KEY (schedule_id)     REFERENCES service_schedule(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uq_resident_slot (resident_user_id, schedule_id),
  INDEX idx_resident_user (resident_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ===========================================
-- Visit requests (Visitor asks to visit Resident)
-- ===========================================
CREATE TABLE visit_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visitor_user_id  INT UNSIGNED NOT NULL,  -- users.id role=VISITOR (enforce in PHP)
  resident_user_id INT UNSIGNED NOT NULL,  -- users.id role=RESIDENT (enforce in PHP)
  requested_start DATETIME NOT NULL,
  requested_end   DATETIME NULL,
  status ENUM('PENDING','APPROVED','DECLINED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_vr_visitor  FOREIGN KEY (visitor_user_id)  REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_vr_resident FOREIGN KEY (resident_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_vr_visitor (visitor_user_id),
  INDEX idx_vr_resident (resident_user_id),
  INDEX idx_vr_status (status),
  INDEX idx_vr_start (requested_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
