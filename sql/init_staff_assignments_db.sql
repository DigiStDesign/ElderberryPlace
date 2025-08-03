CREATE TABLE IF NOT EXISTS staff_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    staff_id INT NOT NULL,
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (staff_id) REFERENCES staff(id)
);