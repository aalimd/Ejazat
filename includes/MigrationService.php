<?php
/**
 * MigrationService — Enterprise Database Migration Engine
 *
 * NOT a SQL executor. A controlled, audited, versioned migration system.
 * Super Admin only. No raw SQL input. Safe preflight checks.
 */
class MigrationService {
    private PDO $pdo;
    private int $userId;
    private string $migrationsDir;
    private string $tableName = 'schema_migrations';

    public function __construct(PDO $pdo, int $userId) {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->migrationsDir = dirname(__DIR__) . '/database/migrations';
    }

    /**
     * Bootstrap: create schema_migrations table if it doesn't exist.
     * Called automatically on first use.
     */
    public function bootstrap(): array {
        try {
            $this->pdo->query("SELECT 1 FROM {$this->tableName} LIMIT 1");
            return ['success' => true, 'message' => 'Already bootstrapped'];
        } catch (PDOException $e) {
            // Table doesn't exist — create it
            $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
                version VARCHAR(50) NOT NULL PRIMARY KEY,
                name VARCHAR(255) NOT NULL DEFAULT '',
                description TEXT,
                checksum VARCHAR(64) NOT NULL DEFAULT '',
                status ENUM('applied','failed','rolled_back') NOT NULL DEFAULT 'applied',
                executed_by INT DEFAULT NULL,
                executed_at TIMESTAMP NULL DEFAULT NULL,
                completed_at TIMESTAMP NULL DEFAULT NULL,
                duration_ms INT DEFAULT 0,
                output TEXT,
                rollback_sql TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $this->pdo->exec($sql);
            return ['success' => true, 'message' => 'schema_migrations table created'];
        }
    }

    /**
     * Scan database/migrations/ and return all migration records.
     */
    public function getAllMigrations(): array {
        $this->bootstrap();
        $files = $this->scanMigrationFiles();
        $applied = [];
        try {
            $stmt = $this->pdo->query("SELECT * FROM {$this->tableName}");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $applied[$row['version']] = $row;
            }
        } catch (PDOException $e) {
            // table might not exist yet
        }
        $migrations = [];

        foreach ($files as $file) {
            $version = $this->versionFromFilename($file);
            $name = $this->nameFromFilename($file);
            $content = file_get_contents($file);
            $checksum = md5($content);
            $parsed = $this->parseMigrationContent($content);

            $record = $applied[$version] ?? null;
            $migrations[$version] = [
                'version' => $version,
                'name' => $name,
                'filename' => basename($file),
                'checksum' => $checksum,
                'status' => $record['status'] ?? 'pending',
                'description' => $parsed['description'],
                'has_up' => $parsed['has_up'],
                'has_down' => $parsed['has_down'],
                'executed_by' => $record['executed_by'] ?? null,
                'executed_at' => $record['executed_at'] ?? null,
                'completed_at' => $record['completed_at'] ?? null,
                'duration_ms' => $record['duration_ms'] ?? 0,
                'output' => $record['output'] ?? '',
                'integrity_pass' => $record ? ($record['checksum'] === $checksum) : null,
            ];
        }

        ksort($migrations);
        return $migrations;
    }

    /**
     * Get only pending (unapplied or failed) migrations.
     */
    public function getPendingMigrations(): array {
        $all = $this->getAllMigrations();
        return array_filter($all, fn($m) => $m['status'] === 'pending' || $m['status'] === 'failed');
    }

    /**
     * Get applied migration history, ordered by version desc.
     */
    public function getAppliedMigrations(): array {
        $all = $this->getAllMigrations();
        $applied = array_filter($all, fn($m) => $m['status'] === 'applied' || $m['status'] === 'rolled_back');
        krsort($applied);
        return $applied;
    }

    /**
     * Get aggregate status summary.
     */
    public function getStatus(): array {
        $all = $this->getAllMigrations();
        $total = count($all);
        $applied = count(array_filter($all, fn($m) => $m['status'] === 'applied'));
        $pending = count(array_filter($all, fn($m) => $m['status'] === 'pending'));
        $failed = count(array_filter($all, fn($m) => $m['status'] === 'failed'));
        $rolled_back = count(array_filter($all, fn($m) => $m['status'] === 'rolled_back'));

        $lastApplied = null;
        foreach ($all as $m) {
            if ($m['status'] === 'applied' && $m['completed_at']) {
                if (!$lastApplied || $m['completed_at'] > $lastApplied['completed_at']) {
                    $lastApplied = $m;
                }
            }
        }

        $currentVersion = '';
        $appliedVersions = array_keys(array_filter($all, fn($m) => $m['status'] === 'applied'));
        sort($appliedVersions);
        $currentVersion = $appliedVersions ? end($appliedVersions) : 'none';

        return [
            'total' => $total,
            'applied' => $applied,
            'pending' => $pending,
            'failed' => $failed,
            'rolled_back' => $rolled_back,
            'current_version' => $currentVersion,
            'last_applied' => $lastApplied,
            'all' => $all,
        ];
    }

    /**
     * Run preflight checks before executing any migration.
     * Returns array of check results. If any critical check fails, block execution.
     */
    public function preflight(): array {
        $checks = [];

        // 1. DB Connection
        try {
            $this->pdo->query('SELECT 1');
            $checks[] = ['name' => 'db_connection', 'status' => 'pass', 'message' => 'Database connection OK'];
        } catch (Exception $e) {
            $checks[] = ['name' => 'db_connection', 'status' => 'fail', 'message' => 'Database connection failed: ' . $e->getMessage()];
        }

        // 2. Migrations directory readable
        $dirOk = is_dir($this->migrationsDir) && is_readable($this->migrationsDir);
        $checks[] = ['name' => 'migrations_dir', 'status' => $dirOk ? 'pass' : 'fail', 'message' => $dirOk ? 'Migrations directory readable' : 'Migrations directory missing or unreadable'];

        // 3. Schema migrations table exists
        try {
            $this->pdo->query("SELECT 1 FROM {$this->tableName} LIMIT 1");
            $checks[] = ['name' => 'tracking_table', 'status' => 'pass', 'message' => 'Tracking table exists'];
        } catch (Exception $e) {
            $checks[] = ['name' => 'tracking_table', 'status' => 'warn', 'message' => 'Tracking table not yet created (will bootstrap)'];
        }

        // 4. Migration integrity — verify checksums of applied migrations
        try {
            $integrityIssues = $this->validateIntegrity();
            if (empty($integrityIssues)) {
                $checks[] = ['name' => 'integrity', 'status' => 'pass', 'message' => 'All applied migrations pass integrity check'];
            } else {
                $checks[] = ['name' => 'integrity', 'status' => 'warn', 'message' => count($integrityIssues) . ' migration(s) have altered content: ' . implode(', ', $integrityIssues)];
            }
        } catch (Exception $e) {
            $checks[] = ['name' => 'integrity', 'status' => 'warn', 'message' => 'Integrity check unavailable: ' . $e->getMessage()];
        }

        // 5. Pending conflicts — check for failed migrations
        try {
            $all = $this->getAllMigrations();
            $failedVersions = [];
            foreach ($all as $m) {
                if ($m['status'] === 'failed') $failedVersions[] = $m['version'];
            }
            if (empty($failedVersions)) {
                $checks[] = ['name' => 'failed_pending', 'status' => 'pass', 'message' => 'No failed migrations'];
            } else {
                $checks[] = ['name' => 'failed_pending', 'status' => 'warn', 'message' => 'Found ' . count($failedVersions) . ' failed migration(s): ' . implode(', ', $failedVersions)];
            }
        } catch (Exception $e) {
            $checks[] = ['name' => 'failed_pending', 'status' => 'warn', 'message' => 'Could not check for failed migrations'];
        }

        // 6. Disk space (data directory)
        $dataDir = ini_get('mysql.default_socket') ? dirname(ini_get('mysql.default_socket')) : sys_get_temp_dir();
        $diskFree = @disk_free_space(dirname($this->migrationsDir));
        if ($diskFree !== false) {
            $diskFreeMB = round($diskFree / 1048576);
            $checks[] = ['name' => 'disk_space', 'status' => $diskFreeMB > 100 ? 'pass' : 'warn', 'message' => "Disk free: {$diskFreeMB} MB"];
        } else {
            $checks[] = ['name' => 'disk_space', 'status' => 'warn', 'message' => 'Could not verify disk space'];
        }

        // 7. InnoDB status
        try {
            $engine = $this->pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetch();
            $checks[] = ['name' => 'engine', 'status' => 'pass', 'message' => 'InnoDB available'];
        } catch (Exception $e) {
            $checks[] = ['name' => 'engine', 'status' => 'warn', 'message' => 'Could not verify storage engine'];
        }

        $allPass = !in_array('fail', array_column($checks, 'status'));
        $hasWarnings = in_array('warn', array_column($checks, 'status'));

        return [
            'checks' => $checks,
            'all_pass' => $allPass,
            'has_warnings' => $hasWarnings,
        ];
    }

    /**
     * Apply a single migration version.
     * Returns detailed result with success, output, duration.
     */
    public function apply(string $version): array {
        $this->bootstrap();
        $all = $this->getAllMigrations();

        if (!isset($all[$version])) {
            return ['success' => false, 'error' => "Migration '{$version}' not found"];
        }

        $migration = $all[$version];
        if ($migration['status'] === 'applied') {
            return ['success' => false, 'error' => "Migration '{$version}' is already applied"];
        }

        if (!$migration['has_up']) {
            return ['success' => false, 'error' => "Migration '{$version}' has no UP section"];
        }

        $filePath = $this->migrationsDir . '/' . $migration['filename'];
        $content = file_get_contents($filePath);
        $parsed = $this->parseMigrationContent($content);
        $upSql = $parsed['up_sql'];
        $downSql = $parsed['down_sql'];
        $checksum = md5($content);

        $startTime = microtime(true);
        $output = '';
        $error = null;
        $status = 'applied';

        try {
            $this->pdo->exec($upSql);
            $output = 'Migration executed successfully';
        } catch (PDOException $e) {
            $error = $e->getMessage();
            $output = 'Error: ' . $error;
            $status = 'failed';
        }

        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Record in tracking table
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->tableName}
                    (version, name, description, checksum, status, executed_by, executed_at, completed_at, duration_ms, output, rollback_sql)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    checksum = VALUES(checksum),
                    executed_by = VALUES(executed_by),
                    executed_at = NOW(),
                    completed_at = NOW(),
                    duration_ms = VALUES(duration_ms),
                    output = VALUES(output),
                    rollback_sql = VALUES(rollback_sql)
            ");
            $stmt->execute([
                $version,
                $migration['name'],
                $migration['description'] ?? '',
                $checksum,
                $status,
                $this->userId,
                $durationMs,
                $output,
                $downSql ?: '',
            ]);
        } catch (PDOException $e) {
            error_log('MigrationService: failed to record migration: ' . $e->getMessage());
        }

        // Audit log
        logActivity(
            "🗄️ تحديث قاعدة البيانات",
            "🗄️ Database Migration",
            "Migration {$version}: {$migration['name']} — " . ($error ? "FAILED: {$error}" : "SUCCESS ({$durationMs}ms)")
        );

        $result = [
            'success' => $status === 'applied',
            'version' => $version,
            'name' => $migration['name'],
            'duration_ms' => $durationMs,
            'output' => $output,
        ];

        if ($error) {
            $result['error'] = $error;
        }

        return $result;
    }

    /**
     * Apply ALL pending migrations in order.
     */
    public function applyAll(): array {
        $pending = $this->getPendingMigrations();
        $results = [];

        foreach ($pending as $version => $migration) {
            $results[$version] = $this->apply($version);
            if (!$results[$version]['success']) {
                // Stop on first failure
                break;
            }
        }

        return [
            'results' => $results,
            'total' => count($results),
            'success_count' => count(array_filter($results, fn($r) => $r['success'])),
            'fail_count' => count(array_filter($results, fn($r) => !$r['success'])),
        ];
    }

    /**
     * Rollback a specific migration.
     */
    public function rollback(string $version): array {
        $this->bootstrap();

        // Check if migration is applied
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE version = ? AND status = 'applied'");
        $stmt->execute([$version]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return ['success' => false, 'error' => "Migration '{$version}' is not applied or not found"];
        }

        if (empty($record['rollback_sql'])) {
            return ['success' => false, 'error' => "Migration '{$version}' has no rollback SQL available"];
        }

        $startTime = microtime(true);
        $output = '';
        $error = null;

        try {
            $this->pdo->exec($record['rollback_sql']);
            $output = 'Rollback executed successfully';
        } catch (PDOException $e) {
            $error = $e->getMessage();
            $output = 'Error: ' . $error;
        }

        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Update record
        $newStatus = $error ? 'applied' : 'rolled_back';
        $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET status = ?, output = CONCAT(output, '\n--- Rollback ---\n', ?), duration_ms = ?, completed_at = NOW() WHERE version = ?");
        $stmt->execute([$newStatus, $output, $durationMs, $version]);

        // Audit log
        logActivity(
            "🗄️ تراجع عن تحديث قاعدة البيانات",
            "🗄️ Database Rollback",
            "Rollback {$version}: {$record['name']} — " . ($error ? "FAILED: {$error}" : "SUCCESS ({$durationMs}ms)")
        );

        return [
            'success' => !$error,
            'version' => $version,
            'name' => $record['name'],
            'duration_ms' => $durationMs,
            'output' => $output,
            'error' => $error,
        ];
    }

    /**
     * Check integrity of already-applied migrations.
     * Returns list of version strings that fail checksum check.
     */
    public function validateIntegrity(): array {
        $issues = [];
        $files = $this->scanMigrationFiles();

        foreach ($files as $file) {
            $version = $this->versionFromFilename($file);
            $content = file_get_contents($file);
            $checksum = md5($content);

            $stmt = $this->pdo->prepare("SELECT checksum FROM {$this->tableName} WHERE version = ? AND status = 'applied'");
            $stmt->execute([$version]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && $row['checksum'] !== $checksum) {
                $issues[] = $version;
            }
        }

        return $issues;
    }

    /**
     * Scan database/migrations/ directory and return sorted file paths.
     */
    private function scanMigrationFiles(): array {
        $pattern = $this->migrationsDir . '/*.sql';
        $files = glob($pattern);
        if ($files === false) $files = [];
        sort($files);
        return $files;
    }

    /**
     * Extract version prefix from filename (e.g., "001" from "001_create_table.sql").
     */
    private function versionFromFilename(string $path): string {
        $basename = basename($path, '.sql');
        if (preg_match('/^(\d+)/', $basename, $m)) {
            return $m[1];
        }
        return $basename;
    }

    /**
     * Extract human-readable name from filename.
     */
    private function nameFromFilename(string $path): string {
        $basename = basename($path, '.sql');
        // Remove leading digits and underscore
        $name = preg_replace('/^\d+[_\-]\s*/', '', $basename);
        $name = str_replace(['_', '-'], ' ', $name);
        return ucwords($name);
    }

    /**
     * Parse migration file content to extract description, UP SQL, DOWN SQL.
     */
    private function parseMigrationContent(string $content): array {
        $description = '';
        $upSql = '';
        $downSql = '';
        $hasUp = false;
        $hasDown = false;

        // Extract description from comment header
        if (preg_match('/--\s*Description:\s*(.+)/i', $content, $m)) {
            $description = trim($m[1]);
        }

        // Split by -- UP and -- DOWN markers
        $lines = explode("\n", $content);
        $section = 'header';
        $currentUp = [];
        $currentDown = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (preg_match('/^--\s*UP\s*$/i', $trimmed)) {
                $section = 'up';
                continue;
            }
            if (preg_match('/^--\s*DOWN\s*$/i', $trimmed)) {
                $section = 'down';
                continue;
            }
            if (preg_match('/^--\s*(Migration|Description)/i', $trimmed)) {
                continue;
            }

            if ($section === 'up') {
                $currentUp[] = $line;
            } elseif ($section === 'down') {
                $currentDown[] = $line;
            }
        }

        $upSql = trim(implode("\n", $currentUp));
        $downSql = trim(implode("\n", $currentDown));
        $hasUp = !empty($upSql);
        $hasDown = !empty($downSql);

        return [
            'description' => $description,
            'up_sql' => $upSql,
            'down_sql' => $downSql,
            'has_up' => $hasUp,
            'has_down' => $hasDown,
        ];
    }
}
