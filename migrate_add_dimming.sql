-- migrate_add_dimming.sql
-- Run ONCE in pgAdmin on your iot_streetlights database.
-- Adds dimming columns to devices table. Does NOT touch any existing columns.

ALTER TABLE devices
    ADD COLUMN IF NOT EXISTS dim_level  VARCHAR(20)  DEFAULT 'DIM_HIGH',
    ADD COLUMN IF NOT EXISTS dim_pwm    INTEGER      DEFAULT 255;
