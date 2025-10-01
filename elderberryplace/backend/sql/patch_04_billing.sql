CREATE TABLE IF NOT EXISTS medication_purchases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resident_user_id INT UNSIGNED NOT NULL,
  medication_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  total_price DECIMAL(10,2) NOT NULL,
  purchase_date DATE NOT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_medpurch_resident FOREIGN KEY (resident_user_id) 
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_medpurch_medication FOREIGN KEY (medication_id) 
    REFERENCES medications(id) ON DELETE RESTRICT,
  INDEX idx_medpurch_resident (resident_user_id),
  INDEX idx_medpurch_date (purchase_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Service charges (links to service_schedule for billed sessions)
CREATE TABLE IF NOT EXISTS service_charges (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resident_user_id INT UNSIGNED NOT NULL,
  schedule_id INT UNSIGNED NULL, -- NULL if ad-hoc charge
  service_id INT UNSIGNED NOT NULL,
  service_name VARCHAR(120) NOT NULL, -- denormalized for history
  description TEXT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  quantity DECIMAL(8,2) NOT NULL DEFAULT 1.00, -- allow fractional (e.g., 0.5 hours)
  total_price DECIMAL(10,2) NOT NULL,
  service_date DATE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_svccharge_resident FOREIGN KEY (resident_user_id) 
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_svccharge_schedule FOREIGN KEY (schedule_id) 
    REFERENCES service_schedule(id) ON DELETE SET NULL,
  CONSTRAINT fk_svccharge_service FOREIGN KEY (service_id) 
    REFERENCES services(id) ON DELETE RESTRICT,
  INDEX idx_svccharge_resident (resident_user_id),
  INDEX idx_svccharge_date (service_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bills (invoice header)
CREATE TABLE IF NOT EXISTS bills (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bill_number VARCHAR(50) NOT NULL UNIQUE, -- e.g., "INV-2025-00001"
  resident_user_id INT UNSIGNED NOT NULL,
  bill_date DATE NOT NULL,
  due_date DATE NULL,
  period_start DATE NULL, -- billing period
  period_end DATE NULL,
  subtotal_medications DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  subtotal_services DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  grand_total DECIMAL(10,2) NOT NULL,
  status ENUM('draft','issued','paid','cancelled') NOT NULL DEFAULT 'draft',
  notes TEXT NULL,
  created_by INT UNSIGNED NULL, -- staff who created the bill
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bill_resident FOREIGN KEY (resident_user_id) 
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_bill_creator FOREIGN KEY (created_by) 
    REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_bill_resident (resident_user_id),
  INDEX idx_bill_date (bill_date),
  INDEX idx_bill_status (status),
  INDEX idx_bill_number (bill_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bill line items (detail of what's on the bill)
CREATE TABLE IF NOT EXISTS bill_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bill_id INT UNSIGNED NOT NULL,
  item_type ENUM('medication','service','other') NOT NULL,
  reference_id INT UNSIGNED NULL, -- medication_purchases.id or service_charges.id
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(8,2) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  total_price DECIMAL(10,2) NOT NULL,
  line_order INT UNSIGNED NOT NULL DEFAULT 0, -- for display ordering
  CONSTRAINT fk_billitem_bill FOREIGN KEY (bill_id) 
    REFERENCES bills(id) ON DELETE CASCADE,
  INDEX idx_billitem_bill (bill_id),
  INDEX idx_billitem_ref (item_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Receipts (payment records)
CREATE TABLE IF NOT EXISTS receipts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receipt_number VARCHAR(50) NOT NULL UNIQUE, -- e.g., "RCT-2025-00001"
  bill_id INT UNSIGNED NOT NULL,
  resident_user_id INT UNSIGNED NOT NULL,
  payment_date DATE NOT NULL,
  payment_method ENUM('cash','card','bank_transfer','cheque','other') NOT NULL,
  amount_paid DECIMAL(10,2) NOT NULL,
  reference_number VARCHAR(100) NULL, -- cheque/transaction number
  notes TEXT NULL,
  created_by INT UNSIGNED NULL, -- staff who recorded payment
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_receipt_bill FOREIGN KEY (bill_id) 
    REFERENCES bills(id) ON DELETE CASCADE,
  CONSTRAINT fk_receipt_resident FOREIGN KEY (resident_user_id) 
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_receipt_creator FOREIGN KEY (created_by) 
    REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_receipt_bill (bill_id),
  INDEX idx_receipt_resident (resident_user_id),
  INDEX idx_receipt_date (payment_date),
  INDEX idx_receipt_number (receipt_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;