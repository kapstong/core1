<?php
/**
 * Automated Database Backup System
 * Run this script via cron job daily
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Logger.php';

class DatabaseBackup {
    private $backupDir;
    private $maxBackups;
    private $db;

    public function __construct() {
        $this->backupDir = __DIR__ . '/../../backups';
        $this->maxBackups = 30; // Keep last 30 days of backups
        $this->db = new Database();

        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0777, true);
        }
    }

    public function createBackup() {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = $this->backupDir . '/backup_' . $timestamp . '.sql';

            // Get database credentials
            $dbConfig = $this->db->getConfig();

            // Create backup command
            $command = sprintf(
                'mysqldump --host=%s --user=%s --password=%s %s > %s',
                escapeshellarg($dbConfig['host']),
                escapeshellarg($dbConfig['user']),
                escapeshellarg($dbConfig['pass']),
                escapeshellarg($dbConfig['dbname']),
                escapeshellarg($filename)
            );

            // Execute backup
            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                throw new Exception("Database backup failed with error code: $returnVar");
            }

            // Compress backup
            $zipCommand = sprintf('gzip %s', escapeshellarg($filename));
            exec($zipCommand);

            // Log success
            Logger::info("Database backup created successfully: backup_{$timestamp}.sql.gz");

            // Cleanup old backups
            $this->cleanupOldBackups();

            return true;
        } catch (Exception $e) {
            Logger::error("Database backup failed: " . $e->getMessage());
            return false;
        }
    }

    private function cleanupOldBackups() {
        $files = glob($this->backupDir . '/backup_*.sql.gz');
        
        // Sort files by modification time
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Keep only the most recent backups
        if (count($files) > $this->maxBackups) {
            for ($i = $this->maxBackups; $i < count($files); $i++) {
                unlink($files[$i]);
                Logger::info("Deleted old backup: " . basename($files[$i]));
            }
        }
    }

    public function verifyBackup($filename) {
        try {
            // Create test database
            $testDb = 'backup_test_' . time();
            $dbConfig = $this->db->getConfig();
            
            $conn = new PDO("mysql:host={$dbConfig['host']}", $dbConfig['user'], $dbConfig['pass']);
            $conn->exec("CREATE DATABASE IF NOT EXISTS $testDb");

            // Uncompress backup if needed
            if (substr($filename, -3) === '.gz') {
                $tempFile = str_replace('.gz', '', $filename);
                $command = sprintf('gunzip -c %s > %s', escapeshellarg($filename), escapeshellarg($tempFile));
                exec($command);
                $filename = $tempFile;
            }

            // Restore to test database
            $command = sprintf(
                'mysql --host=%s --user=%s --password=%s %s < %s',
                escapeshellarg($dbConfig['host']),
                escapeshellarg($dbConfig['user']),
                escapeshellarg($dbConfig['pass']),
                escapeshellarg($testDb),
                escapeshellarg($filename)
            );

            exec($command, $output, $returnVar);

            // Cleanup
            $conn->exec("DROP DATABASE IF EXISTS $testDb");

            if (isset($tempFile)) {
                unlink($tempFile);
            }

            return $returnVar === 0;
        } catch (Exception $e) {
            Logger::error("Backup verification failed: " . $e->getMessage());
            return false;
        }
    }

    public function restoreBackup($filename) {
        try {
            if (!file_exists($filename)) {
                throw new Exception("Backup file not found: $filename");
            }

            // Verify backup first
            if (!$this->verifyBackup($filename)) {
                throw new Exception("Backup verification failed");
            }

            $dbConfig = $this->db->getConfig();

            // Uncompress if needed
            if (substr($filename, -3) === '.gz') {
                $tempFile = str_replace('.gz', '', $filename);
                $command = sprintf('gunzip -c %s > %s', escapeshellarg($filename), escapeshellarg($tempFile));
                exec($command);
                $filename = $tempFile;
            }

            // Restore backup
            $command = sprintf(
                'mysql --host=%s --user=%s --password=%s %s < %s',
                escapeshellarg($dbConfig['host']),
                escapeshellarg($dbConfig['user']),
                escapeshellarg($dbConfig['pass']),
                escapeshellarg($dbConfig['dbname']),
                escapeshellarg($filename)
            );

            exec($command, $output, $returnVar);

            // Cleanup
            if (isset($tempFile)) {
                unlink($tempFile);
            }

            if ($returnVar !== 0) {
                throw new Exception("Database restore failed with error code: $returnVar");
            }

            Logger::info("Database restored successfully from: " . basename($filename));
            return true;
        } catch (Exception $e) {
            Logger::error("Database restore failed: " . $e->getMessage());
            return false;
        }
    }
}

// Run backup if script is executed directly
if (php_sapi_name() === 'cli') {
    $backup = new DatabaseBackup();
    if ($backup->createBackup()) {
        echo "Backup completed successfully.\n";
        exit(0);
    } else {
        echo "Backup failed. Check error logs.\n";
        exit(1);
    }
}