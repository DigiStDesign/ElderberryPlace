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
  bill_items,
  receipts,
  bills,
  service_charges,
  medication_purchases,
  med_alerts,
  administrations,
  med_schedule,
  prescriptions,
  medications,
  staff_roster,
  incidents,
  users,
  roles;

SET FOREIGN_KEY_CHECKS = 1;

-- ===========================================
-- Base: roles & users (all logins live here)
-- ===========================================
CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  full_name VARCHAR(190) NOT NULL,
  email VARCHAR(120) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('ADMIN','STAFF','RESIDENT','VISITOR') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO roles (name) VALUES ('ADMIN'), ('STAFF'), ('RESIDENT'), ('VISITOR');

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
  name VARCHAR(80) NOT NULL UNIQUE
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
  visitor_user_id  INT UNSIGNED NOT NULL,
  resident_user_id INT UNSIGNED NOT NULL,
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

CREATE TABLE staff_assignments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  schedule_id INT UNSIGNED NOT NULL,
  staff_user_id INT UNSIGNED NOT NULL,
  CONSTRAINT fk_sa_service   FOREIGN KEY (service_id)  REFERENCES services(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sa_schedule  FOREIGN KEY (schedule_id) REFERENCES service_schedule(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sa_staffuser FOREIGN KEY (staff_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uq_sa (schedule_id, staff_user_id),
  INDEX idx_sa_staff (staff_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE staff_schedule (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_user_id INT UNSIGNED NOT NULL,
  schedule_id INT UNSIGNED NOT NULL,
  CONSTRAINT fk_ss_user     FOREIGN KEY (staff_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ss_schedule FOREIGN KEY (schedule_id)   REFERENCES service_schedule(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uq_staff_slot (staff_user_id, schedule_id),
  INDEX idx_staff_user (staff_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE resident_schedule (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resident_user_id INT UNSIGNED NOT NULL,
  schedule_id INT UNSIGNED NOT NULL,
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
-- Visits
-- ===========================================
CREATE TABLE visit_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visitor_user_id  INT UNSIGNED NOT NULL,
  resident_user_id INT UNSIGNED NOT NULL,
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

-- ===========================================
-- Meds
-- ===========================================
CREATE TABLE medications(
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  generic_name VARCHAR(120) NOT NULL,
  brand_name VARCHAR(120),
  form VARCHAR(40),
  strength VARCHAR(40),
  UNIQUE KEY uq_meds (generic_name,strength,form)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE prescriptions(
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resident_user_id INT UNSIGNED NOT NULL,
  medication_id INT UNSIGNED NOT NULL,
  dose VARCHAR(60) NOT NULL,
  route VARCHAR(20),
  prn TINYINT(1) NOT NULL DEFAULT 0,
  frequency VARCHAR(80) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE,
  times_json TEXT,
  max_daily_dose VARCHAR(40),
  instructions TEXT,
  prescriber VARCHAR(120),
  status ENUM('active','paused','stopped') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (resident_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (medication_id) REFERENCES medications(id) ON DELETE RESTRICT,
  INDEX (resident_user_id),
  INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE med_schedule(
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prescription_id INT UNSIGNED NOT NULL,
  due_at DATETIME NOT NULL,
  window_minutes INT NOT NULL DEFAULT 60,
  FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
  INDEX(prescription_id),
  INDEX(due_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE administrations(
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  schedule_id INT UNSIGNED NOT NULL,
  administered_at DATETIME,
  outcome ENUM('given','refused','withheld','missed') NOT NULL,
  dose_given VARCHAR(60),
  notes TEXT,
  staff_user_id INT UNSIGNED NOT NULL,
  witness_user_id INT UNSIGNED,
  FOREIGN KEY (schedule_id) REFERENCES med_schedule(id) ON DELETE CASCADE,
  FOREIGN KEY (staff_user_id) REFERENCES users(id),
  FOREIGN KEY (witness_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE med_alerts(
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resident_user_id INT UNSIGNED NOT NULL,
  type ENUM('overdue','allergy','prn_limit') NOT NULL,
  message VARCHAR(255) NOT NULL,
  state ENUM('open','resolved') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL,
  resolved_by INT UNSIGNED,
  FOREIGN KEY (resident_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO medications(generic_name,brand_name,form,strength)
VALUES('Paracetamol','Panadol','tablet','500 mg');

-- ============================================
-- Enhanced Reporting (ICT-210)
-- ============================================
CREATE TABLE incidents (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resident_user_id   INT UNSIGNED NULL,
  type               ENUM('medication','fall','behaviour','infection','other') NOT NULL,
  severity           ENUM('low','moderate','high','critical') NOT NULL,
  occurred_at        DATETIME NOT NULL,
  reported_by        INT UNSIGNED NOT NULL,
  description        TEXT,
  action_taken       TEXT,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inc_res   FOREIGN KEY (resident_user_id) REFERENCES users(id),
  CONSTRAINT fk_inc_staff FOREIGN KEY (reported_by)      REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE INDEX idx_inc_occurred_at   ON incidents (occurred_at);
CREATE INDEX idx_inc_resident      ON incidents (resident_user_id);
CREATE INDEX idx_inc_type_severity ON incidents (type, severity);

CREATE TABLE staff_roster (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_user_id  INT UNSIGNED NOT NULL,
  shift_date     DATE NOT NULL,
  shift          ENUM('AM','PM','Night') NOT NULL,
  wing           VARCHAR(50),
  UNIQUE KEY uq_roster (staff_user_id, shift_date, shift),
  CONSTRAINT fk_roster_staff FOREIGN KEY (staff_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE INDEX idx_roster_staff_date ON staff_roster (staff_user_id, shift_date);

-- Link alerts to schedule + prevent dupes
ALTER TABLE med_alerts
  ADD COLUMN schedule_id INT UNSIGNED NULL;

ALTER TABLE med_alerts
  ADD CONSTRAINT fk_ma_schedule
  FOREIGN KEY (schedule_id) REFERENCES med_schedule(id) ON DELETE CASCADE;

CREATE UNIQUE INDEX uq_alert_unique ON med_alerts (schedule_id, type);

-- ============================================
-- Billing Core (Mercury-safe)
-- ============================================
CREATE TABLE medication_purchases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resident_user_id INT UNSIGNED NOT NULL,
  medication_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  total_price DECIMAL(10,2) NOT NULL,
  purchase_date DATE NOT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_medpurch_resident  FOREIGN KEY (resident_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_medpurch_medication FOREIGN KEY (medication_id) REFERENCES medications(id) ON DELETE RESTRICT,
  INDEX idx_medpurch_resident (resident_user_id),
  INDEX idx_medpurch_date (purchase_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE service_charges (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resident_user_id INT UNSIGNED NOT NULL,
  schedule_id INT UNSIGNED NULL,
  service_id INT UNSIGNED NOT NULL,
  service_name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  quantity DECIMAL(8,2) NOT NULL DEFAULT 1.00,
  total_price DECIMAL(10,2) NOT NULL,
  service_date DATE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_svccharge_resident FOREIGN KEY (resident_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_svccharge_schedule FOREIGN KEY (schedule_id) REFERENCES service_schedule(id) ON DELETE SET NULL,
  CONSTRAINT fk_svccharge_service  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
  INDEX idx_svccharge_resident (resident_user_id),
  INDEX idx_svccharge_date (service_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE bills (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bill_number VARCHAR(50) NOT NULL UNIQUE,
  resident_user_id INT UNSIGNED NOT NULL,
  bill_date DATE NOT NULL,
  due_date DATE NULL,
  period_start DATE NULL,
  period_end DATE NULL,
  subtotal_medications DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  subtotal_services   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  tax_amount          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  discount_amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  grand_total         DECIMAL(10,2) NOT NULL,
  status ENUM('draft','issued','paid','cancelled') NOT NULL DEFAULT 'draft',
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_bill_resident FOREIGN KEY (resident_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_bill_creator  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_bill_resident (resident_user_id),
  INDEX idx_bill_date (bill_date),
  INDEX idx_bill_status (status),
  INDEX idx_bill_number (bill_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE bill_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bill_id INT UNSIGNED NOT NULL,
  item_type ENUM('medication','service','other') NOT NULL,
  reference_id INT UNSIGNED NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(8,2) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  total_price DECIMAL(10,2) NOT NULL,
  line_order INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_billitem_bill FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE,
  INDEX idx_billitem_bill (bill_id),
  INDEX idx_billitem_ref (item_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE receipts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receipt_number VARCHAR(50) NOT NULL UNIQUE,
  bill_id INT UNSIGNED NOT NULL,
  resident_user_id INT UNSIGNED NOT NULL,
  payment_date DATE NOT NULL,
  payment_method ENUM('cash','card','bank_transfer','cheque','other') NOT NULL,
  amount_paid DECIMAL(10,2) NOT NULL,
  reference_number VARCHAR(100) NULL,
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_receipt_bill     FOREIGN KEY (bill_id)         REFERENCES bills(id) ON DELETE CASCADE,
  CONSTRAINT fk_receipt_resident FOREIGN KEY (resident_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_receipt_creator  FOREIGN KEY (created_by)       REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_receipt_bill (bill_id),
  INDEX idx_receipt_resident (resident_user_id),
  INDEX idx_receipt_date (payment_date),
  INDEX idx_receipt_number (receipt_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
