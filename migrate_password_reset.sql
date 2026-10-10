-- migrate_password_reset.sql
-- Run ONCE in pgAdmin on iot_streetlights database
-- Adds password reset token columns to users table

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS reset_token      VARCHAR(64)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS reset_expires_at TIMESTAMP    DEFAULT NULL;
