-- migrate_status_enum.sql
-- Run this ONCE in your PostgreSQL database (iot_streetlights)
-- Adds OVERCURRENT and FAILED to the street_light_data status column
-- (the original enum only had ON, OFF, FAIL — which caused silent insert failures)

-- Step 1: change the column from enum to plain text (simplest approach for PostgreSQL)
ALTER TABLE street_light_data
    ALTER COLUMN status TYPE VARCHAR(20);

-- Step 2: add a CHECK constraint so only valid values are accepted
ALTER TABLE street_light_data
    ADD CONSTRAINT chk_status
    CHECK (status IN ('ON', 'OFF', 'FAILED', 'OVERCURRENT'));

-- Optional: clean up old blank / null status values left from earlier bad inserts
UPDATE street_light_data SET status = 'OFF' WHERE status IS NULL OR status = '';
