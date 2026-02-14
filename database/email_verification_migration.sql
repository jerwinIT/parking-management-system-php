-- Email Verification System - Database Migration
-- Run this SQL to add email verification functionality

-- Add verification columns to users table
ALTER TABLE users 
ADD COLUMN email_verified TINYINT(1) DEFAULT 0 AFTER email,
ADD COLUMN verification_token VARCHAR(64) NULL AFTER email_verified,
ADD COLUMN verification_token_expires DATETIME NULL AFTER verification_token;

-- Optional: Add index for faster token lookups
ALTER TABLE users 
ADD INDEX idx_verification_token (verification_token);

-- Update existing users to verified (optional - if you want existing users to stay active)
-- UPDATE users SET email_verified = 1 WHERE email_verified = 0;
