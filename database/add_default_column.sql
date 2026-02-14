-- Migration: Add is_default column to vehicles table
-- Run this if your vehicles table doesn't have is_default yet

USE parking_management_db;

ALTER TABLE vehicles 
ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER color;

-- Set the first vehicle per user as default (optional)
UPDATE vehicles v1
SET is_default = 1
WHERE v1.id = (
    SELECT v2.id FROM (SELECT * FROM vehicles) v2
    WHERE v2.user_id = v1.user_id
    ORDER BY v2.created_at ASC
    LIMIT 1
);
