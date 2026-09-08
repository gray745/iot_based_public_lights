-- =============================================================================
-- IoT Based Public Street Lights — Full PostgreSQL Setup
-- Database: iot_based_public_lights
-- Run this entire file once in pgAdmin Query Tool (F5)
-- Converted from MariaDB dump + all migration files merged in
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. DROP existing tables (clean slate — safe to re-run)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS alerts            CASCADE;
DROP TABLE IF EXISTS street_light_data CASCADE;
DROP TABLE IF EXISTS devices           CASCADE;
DROP TABLE IF EXISTS users             CASCADE;

-- -----------------------------------------------------------------------------
-- 2. CREATE TABLES
-- -----------------------------------------------------------------------------

-- users
CREATE TABLE users (
    id               SERIAL       PRIMARY KEY,
    name             VARCHAR(100) NOT NULL,
    email            VARCHAR(150) NOT NULL UNIQUE,
    password         VARCHAR(255) NOT NULL,
    role             VARCHAR(10)  NOT NULL DEFAULT 'user'
                         CHECK (role IN ('admin', 'user')),
    reset_token      VARCHAR(64)  DEFAULT NULL,
    reset_expires_at TIMESTAMP    DEFAULT NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- devices  (includes command + dimming columns from migration files)
CREATE TABLE devices (
    id               SERIAL       PRIMARY KEY,
    device_id        VARCHAR(100) NOT NULL UNIQUE,
    location         VARCHAR(255) DEFAULT NULL,
    phone_number     VARCHAR(25)  DEFAULT NULL,
    last_alert       TIMESTAMP    DEFAULT NULL,
    command          VARCHAR(10)  NOT NULL DEFAULT 'ON',
    command_sent_at  TIMESTAMP    DEFAULT NULL,
    dim_level        VARCHAR(20)  NOT NULL DEFAULT 'DIM_HIGH',
    dim_pwm          INTEGER      NOT NULL DEFAULT 255,
    created_at       TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- street_light_data  (status as VARCHAR with CHECK — replaces old ENUM)
CREATE TABLE street_light_data (
    id         SERIAL       PRIMARY KEY,
    device_id  VARCHAR(100) NOT NULL,
    current    FLOAT        DEFAULT NULL,
    brightness FLOAT        DEFAULT NULL,
    status     VARCHAR(20)  DEFAULT 'OFF'
                   CHECK (status IN ('ON', 'OFF', 'FAILED', 'OVERCURRENT')),
    timestamp  TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- alerts
CREATE TABLE alerts (
    id         SERIAL       PRIMARY KEY,
    device_id  VARCHAR(100) DEFAULT NULL,
    alert_type VARCHAR(100) DEFAULT NULL,
    message    TEXT         DEFAULT NULL,
    sent_to    VARCHAR(150) DEFAULT NULL,
    sent_at    TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- -----------------------------------------------------------------------------
-- 3. INDEXES
-- -----------------------------------------------------------------------------
CREATE INDEX idx_sld_device_id  ON street_light_data (device_id);
CREATE INDEX idx_sld_timestamp  ON street_light_data (timestamp);
CREATE INDEX idx_alerts_device  ON alerts (device_id);
CREATE INDEX idx_alerts_sent_at ON alerts (sent_at);

-- -----------------------------------------------------------------------------
-- 4. SEED DATA — Users (from original dump, passwords unchanged)
-- -----------------------------------------------------------------------------
INSERT INTO users (id, name, email, password, role, created_at) VALUES
(2,  'NIYIGENA',     'niyclem2020@gmail.com',      '$2y$10$y4AZyCWJLns7OUiU1CxmsuP2GUN8lxgJNQHWt5z4d3wkga0Q/p4VG', 'user',  '2025-10-03 05:31:46'),
(6,  'IRADUKUNDA',   'dskgroupltd2020@gmail.com',  '$2y$10$0CDz00SaZoxVNHbXvGs1E.NQ1LeuJ75.avChO4oxDluExRPUKNIqK', 'user',  '2025-10-11 13:54:36'),
(7,  'Ncuti Honore', 'honoreken2000@gmail.com',    '$2y$10$myKb5Cj6IozMQEqGuNUhq.o2TQeTqlQOQpq.FoG54UdkAcAY.DFPW', 'user',  '2025-10-11 16:19:18'),
(9,  'kiriku',       'niclem2020@gmail.com',       '$2y$10$MCG4UIcX1fXGBegkqVhLr.t8IyxeGHMc7IaxtzZSZIddsBryUGz5W', 'user',  '2025-10-15 15:18:13');

-- Sync the serial sequence so next INSERT gets id >= 11
SELECT setval('users_id_seq', 10);

-- -----------------------------------------------------------------------------
-- 5. SEED DATA — Street light readings (from original dump)
--    Blank/empty status values replaced with 'OFF' (invalid for the CHECK constraint)
-- -----------------------------------------------------------------------------
INSERT INTO street_light_data (id, device_id, current, brightness, status, timestamp) VALUES
(2,  'STL002', 1.1,   5,   'ON',  '2025-10-01 06:05:00'),
(3,  'STL003', 0,     0,   'OFF', '2025-10-01 06:10:00'),
(4,  'STL004', 0.8,   75,  'ON',  '2025-10-01 06:15:00'),
(5,  'STL005', 1,     18,  'ON',  '2025-10-01 06:20:00'),
(6,  'STL006', 1.3,   90,  'ON',  '2025-10-01 06:25:00'),
(7,  'STL007', 0,     0,   'OFF', '2025-10-01 06:30:00'),
(8,  'STL008', 1.1,   50,  'ON',  '2025-10-01 06:35:00'),
(9,  'STL009', 0.9,   15,  'ON',  '2025-10-01 06:40:00'),
(10, 'STL010', 1.2,   85,  'ON',  '2025-10-01 06:45:00'),
(40, 'SL001', -0.12,  233, 'OFF', '2025-10-18 13:31:44'),
(41, 'SL001', -0.23,  230, 'OFF', '2025-10-18 13:31:52'),
(42, 'SL001', -0.3,   222, 'OFF', '2025-10-18 13:32:01'),
(43, 'SL001',  0,     228, 'OFF', '2025-10-18 13:32:09'),
(44, 'SL001',  0,     202, 'OFF', '2025-10-18 13:32:23'),
(45, 'SL001', -0.23,  190, 'OFF', '2025-10-18 13:32:35'),
(46, 'SL001',  0,     186, 'OFF', '2025-10-18 13:32:44'),
(47, 'SL001', -0.23,  189, 'OFF', '2025-10-18 13:32:56'),
(48, 'SL001',  0.12,  192, 'ON',  '2025-10-18 13:33:04'),
(49, 'SL001', -0.23,  216, 'OFF', '2025-10-18 13:33:13'),
(50, 'SL001',  0,     218, 'OFF', '2025-10-18 13:33:22'),
(51, 'SL001',  0,     206, 'OFF', '2025-10-18 13:33:35'),
(52, 'SL001',  0,     197, 'OFF', '2025-10-18 13:33:47'),
(53, 'SL001',  0.32,  199, 'ON',  '2025-10-18 13:34:00'),
(54, 'SL001', -0.09,  188, 'OFF', '2025-10-18 13:34:09'),
(55, 'SL001', -0.15,  186, 'OFF', '2025-10-18 13:34:18'),
(56, 'SL001',  0.14,  188, 'ON',  '2025-10-18 13:34:31'),
(57, 'SL001', -0.12,  183, 'OFF', '2025-10-18 13:34:40'),
(58, 'SL001',  0.45,  194, 'ON',  '2025-10-18 13:34:48'),
(59, 'SL001', -0.23,  207, 'OFF', '2025-10-18 13:34:57'),
(60, 'SL001', -0.23,  230, 'OFF', '2025-10-18 13:35:05'),
(61, 'SL001',  0,     214, 'OFF', '2025-10-18 13:39:46'),
(62, 'SL001',  0.36,  221, 'ON',  '2025-10-18 13:39:59'),
(63, 'SL001', -0.33,  45,  'OFF', '2025-10-18 13:40:08'),
(64, 'SL001',  0.18,  34,  'ON',  '2025-10-18 13:40:17'),
(65, 'SL001',  0,     34,  'OFF', '2025-10-18 13:40:26'),
(66, 'SL001',  0.22,  230, 'ON',  '2025-10-18 13:40:39'),
(67, 'SL001', -0.26,  217, 'OFF', '2025-10-18 13:40:48'),
(68, 'SL001', -0.19,  255, 'OFF', '2025-10-18 13:40:56'),
(69, 'SL001', -0.19,  255, 'OFF', '2025-10-18 13:41:05'),
(70, 'SL001', -0.2,   22,  'OFF', '2025-10-18 13:41:14'),
(71, 'SL001', -4.21,  0,   'OFF', '2025-10-18 14:05:12'),
(72, 'SL001', -4.21,  0,   'OFF', '2025-10-18 14:05:21'),
(73, 'SL001', -0.35,  255, 'OFF', '2025-10-18 14:05:29'),
(74, 'SL001', -4.21,  2,   'OFF', '2025-10-18 14:05:38'),
(75, 'SL001', -0.65,  146, 'OFF', '2025-10-18 14:05:46'),
(76, 'SL001', -3.44,  0,   'OFF', '2025-10-18 14:05:55'),
(77, 'SL001',  0.53,  255, 'ON',  '2025-10-18 14:06:03'),
(78, 'SL001', -4.21,  83,  'OFF', '2025-10-18 14:06:12'),
(79, 'SL001', -4.21,  9,   'OFF', '2025-10-18 14:06:21'),
(80, 'SL001', -3.44,  20,  'OFF', '2025-10-18 14:06:29'),
(81, 'SL001', -1.32,  255, 'OFF', '2025-10-18 14:06:38'),
(82, 'SL001', -4.21,  0,   'OFF', '2025-10-18 14:06:46'),
(83, 'SL001',  1.1,   46,  'ON',  '2025-10-18 14:06:55'),
(84, 'SL001', -4.21,  255, 'OFF', '2025-10-18 14:07:03'),
(85, 'SL001',  0.55,  126, 'ON',  '2025-10-18 14:07:12'),
(86, 'SL001',  0,     174, 'OFF', '2025-10-18 14:07:21'),
(87, 'SL001', -0.64,  161, 'OFF', '2025-10-18 14:07:34'),
(88, 'SL001', -0.1,   175, 'OFF', '2025-10-18 14:07:43');

-- Sync the serial sequence so next INSERT gets id >= 89
SELECT setval('street_light_data_id_seq', 88);

-- -----------------------------------------------------------------------------
-- 6. SEED DATA — Auto-register SL001 device
-- -----------------------------------------------------------------------------
INSERT INTO devices (device_id, command, dim_level, dim_pwm)
VALUES ('SL001', 'ON', 'DIM_HIGH', 255)
ON CONFLICT (device_id) DO NOTHING;

-- =============================================================================
-- Done.
-- Tables: users, devices, street_light_data, alerts
-- All original data from iot_streetlights.sql included
-- All migrations (command, dimming, password reset, status enum) merged in
-- =============================================================================
