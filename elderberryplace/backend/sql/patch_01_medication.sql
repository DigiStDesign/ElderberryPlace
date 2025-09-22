CREATE TABLE IF NOT EXISTS medications(
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  generic_name VARCHAR(120) NOT NULL,
  brand_name VARCHAR(120), form VARCHAR(40), strength VARCHAR(40),
  UNIQUE KEY uq_meds (generic_name,strength,form)
);
CREATE TABLE IF NOT EXISTS prescriptions(
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resident_user_id INT UNSIGNED NOT NULL,
  medication_id INT UNSIGNED NOT NULL,
  dose VARCHAR(60) NOT NULL, route VARCHAR(20), prn TINYINT(1) NOT NULL DEFAULT 0,
  frequency VARCHAR(80) NOT NULL, start_date DATE NOT NULL, end_date DATE,
  times_json TEXT, max_daily_dose VARCHAR(40), instructions TEXT, prescriber VARCHAR(120),
  status ENUM('active','paused','stopped') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (resident_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (medication_id) REFERENCES medications(id) ON DELETE RESTRICT,
  INDEX (resident_user_id), INDEX(status)
);
CREATE TABLE IF NOT EXISTS med_schedule(
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prescription_id INT UNSIGNED NOT NULL,
  due_at DATETIME NOT NULL, window_minutes INT NOT NULL DEFAULT 60,
  FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
  INDEX(prescription_id), INDEX(due_at)
);
CREATE TABLE IF NOT EXISTS administrations(
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  schedule_id INT UNSIGNED NOT NULL, administered_at DATETIME,
  outcome ENUM('given','refused','withheld','missed') NOT NULL,
  dose_given VARCHAR(60), notes TEXT, staff_user_id INT UNSIGNED NOT NULL,
  witness_user_id INT UNSIGNED,
  FOREIGN KEY (schedule_id) REFERENCES med_schedule(id) ON DELETE CASCADE,
  FOREIGN KEY (staff_user_id) REFERENCES users(id),
  FOREIGN KEY (witness_user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS med_alerts(
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resident_user_id INT UNSIGNED NOT NULL,
  type ENUM('overdue','allergy','prn_limit') NOT NULL,
  message VARCHAR(255) NOT NULL,
  state ENUM('open','resolved') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL, resolved_by INT UNSIGNED,
  FOREIGN KEY (resident_user_id) REFERENCES users(id)
);
-- demo med (optional)
INSERT IGNORE INTO medications(generic_name,brand_name,form,strength)
VALUES('Paracetamol','Panadol','tablet','500 mg');
