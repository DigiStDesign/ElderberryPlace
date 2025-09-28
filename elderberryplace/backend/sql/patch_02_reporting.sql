-- ============================================
-- ICT-210  Enhanced Reporting: DB Patch
-- Tables: incidents, staff_roster
-- Adds helpful indexes for reporting endpoints
-- ============================================

-- Compliance incidents (for Enhanced Reporting)
CREATE TABLE IF NOT EXISTS incidents (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resident_user_id   INT UNSIGNED NULL,
  type               ENUM('medication','fall','behaviour','infection','other') NOT NULL,
  severity           ENUM('low','moderate','high','critical') NOT NULL,
  occurred_at        DATETIME NOT NULL,
  reported_by        INT UNSIGNED NOT NULL,
  description        TEXT,
  action_taken       TEXT,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inc_res
    FOREIGN KEY (resident_user_id) REFERENCES users(id),
  CONSTRAINT fk_inc_staff
    FOREIGN KEY (reported_by)      REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Helpful indexes for reporting (filters/grouping by time/type/severity/resident)
CREATE INDEX idx_inc_occurred_at           ON incidents (occurred_at);
CREATE INDEX idx_inc_resident              ON incidents (resident_user_id);
CREATE INDEX idx_inc_type_severity         ON incidents (type, severity);

-- Optional: staff roster to attribute workload by shift/wing
CREATE TABLE IF NOT EXISTS staff_roster (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_user_id  INT UNSIGNED NOT NULL,
  shift_date     DATE NOT NULL,
  shift          ENUM('AM','PM','Night') NOT NULL,
  wing           VARCHAR(50),
  UNIQUE KEY uq_roster (staff_user_id, shift_date, shift),
  CONSTRAINT fk_roster_staff
    FOREIGN KEY (staff_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes to speed lookups by staff/date
CREATE INDEX idx_roster_staff_date ON staff_roster (staff_user_id, shift_date);
