-- Migration: 001_create_schema_migrations
-- Description: Create schema_migrations tracking table for migration management
-- UP
CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(50) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT,
    checksum VARCHAR(64) NOT NULL DEFAULT '',
    status ENUM('applied', 'failed', 'rolled_back') NOT NULL DEFAULT 'applied',
    executed_by INT DEFAULT NULL,
    executed_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    duration_ms INT DEFAULT 0,
    output TEXT,
    rollback_sql TEXT,
    FOREIGN KEY (executed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- DOWN
DROP TABLE IF EXISTS schema_migrations;
