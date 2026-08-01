-- Description: Add auth_version to users (invalidate sessions on password change) and create ip_rate_limits table for IP-based rate limiting

-- UP

ALTER TABLE users ADD COLUMN IF NOT EXISTS auth_version INT NOT NULL DEFAULT 0 AFTER two_factor_enabled;

CREATE TABLE IF NOT EXISTS ip_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action_key VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ip_action_time (ip_address, action_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DOWN

ALTER TABLE users DROP COLUMN IF EXISTS auth_version;

DROP TABLE IF EXISTS ip_rate_limits;
