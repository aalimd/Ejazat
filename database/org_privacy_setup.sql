-- Organization Privacy Control Setup
-- Adds columns to organizations table for invitation code privacy system

-- Add privacy control columns to organizations table
ALTER TABLE organizations ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Organization status (1=active, 0=inactive)';
ALTER TABLE organizations ADD COLUMN IF NOT EXISTS `is_public` TINYINT(1) DEFAULT 1 COMMENT 'Show organization in public list (1=yes, 0=private/code required)';
ALTER TABLE organizations ADD COLUMN IF NOT EXISTS `requires_invitation_code` TINYINT(1) DEFAULT 0 COMMENT 'Force users to enter invitation code (1=yes, 0=no)';

-- Insert invitation codes for existing organizations
-- This generates a random 12-character alphanumeric code for each organization
-- Note: If you prefer, you can manually set these to something memorable
INSERT INTO organization_invitation_codes (organization_id, code, is_active)
SELECT id, 
       CONCAT(
           UPPER(SUBSTR(MD5(CONCAT(id, RAND())), 1, 3)),
           FLOOR(RAND() * 1000),
           UPPER(SUBSTR(MD5(CONCAT(id, RAND())), 1, 3)),
           FLOOR(RAND() * 1000)
       ) as code,
       1 as is_active
FROM organizations
WHERE id NOT IN (SELECT organization_id FROM organization_invitation_codes);
