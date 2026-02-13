-- Link medication alerts to a concrete schedule item (dose)
ALTER TABLE med_alerts
  ADD COLUMN schedule_id INT UNSIGNED NULL;

ALTER TABLE med_alerts
  ADD CONSTRAINT fk_ma_schedule
  FOREIGN KEY (schedule_id) REFERENCES med_schedule(id) ON DELETE CASCADE;

-- Prevent duplicate 'overdue' alerts for the same schedule
CREATE UNIQUE INDEX uq_alert_unique ON med_alerts (schedule_id, type);
