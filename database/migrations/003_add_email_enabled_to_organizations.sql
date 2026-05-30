-- Migration: 003_add_email_enabled_to_organizations
-- Description: Add email_enabled column to organizations table for per-org email control
-- UP
ALTER TABLE organizations
    ADD COLUMN IF NOT EXISTS email_enabled TINYINT(1) NOT NULL DEFAULT 1
    COMMENT 'Super Admin can disable email for specific orgs'
    AFTER is_active;
-- DOWN
ALTER TABLE organizations DROP COLUMN IF EXISTS email_enabled;
