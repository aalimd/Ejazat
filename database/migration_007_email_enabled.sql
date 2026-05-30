-- Migration 007: Add email_enabled column to organizations table
-- Allows Super Admin to control which organizations can use email

ALTER TABLE organizations
    ADD COLUMN email_enabled TINYINT(1) NOT NULL DEFAULT 1
    COMMENT 'Super Admin can disable email for specific orgs'
    AFTER is_active;
