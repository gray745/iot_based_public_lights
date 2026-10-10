-- Run this once in your PostgreSQL database (iot_streetlights)
-- Adds remote control columns to the devices table

ALTER TABLE devices
  ADD COLUMN IF NOT EXISTS command          VARCHAR(10) DEFAULT 'AUTO',
  ADD COLUMN IF NOT EXISTS command_sent_at  TIMESTAMP   DEFAULT NULL;
