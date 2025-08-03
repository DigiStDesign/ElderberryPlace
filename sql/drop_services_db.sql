-- drop_services_db.sql
SET FOREIGN_KEY_CHECKS = 0; -- call the cops

DROP TABLE IF EXISTS staff_assignments;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS categories;

SET FOREIGN_KEY_CHECKS = 1;