-- Migration: 004_add_org_columns
-- Description: Add missing columns to organizations table for visibility control and email toggle
-- UP
ALTER TABLE organizations ADD COLUMN IF NOT EXISTS is_public TINYINT(1) DEFAULT 0 AFTER status;
ALTER TABLE organizations ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1 AFTER is_public;
ALTER TABLE organizations ADD COLUMN IF NOT EXISTS requires_invitation_code TINYINT(1) DEFAULT 0 AFTER is_active;
ALTER TABLE organizations ADD COLUMN IF NOT EXISTS email_enabled TINYINT(1) DEFAULT 1 AFTER requires_invitation_code;
-- DOWN
ALTER TABLE organizations DROP COLUMN IF EXISTS is_public;
ALTER TABLE organizations DROP COLUMN IF EXISTS is_active;
ALTER TABLE organizations DROP COLUMN IF EXISTS requires_invitation_code;
ALTER TABLE organizations DROP COLUMN IF EXISTS email_enabled;
