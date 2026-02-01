<?php

declare(strict_types=1);

namespace App;

/**
 * Centralized file operations with atomic writes and backup-on-delete
 *
 * Provides safe file I/O for JSON data files with:
 * - File locking to prevent corruption from concurrent writes
 * - Automatic backups before destructive operations
 * - Cleanup of old backups to prevent unbounded growth
 */
class FileOperations
{
    private string $dataDir;
    private string $backupDir;
    private int $maxBackups;

    public function __construct(?string $dataDir = null, int $maxBackups = 30)
    {
        $this->dataDir = $dataDir ?? dirname(__DIR__) . '/data';
        $this->backupDir = $this->dataDir . '/.backups';
        $this->maxBackups = $maxBackups;
    }

    /**
     * Write JSON data to a file atomically with file locking
     */
    public function writeJson(string $filename, array $data): bool
    {
        $path = $this->dataDir . '/' . $filename;

        // Ensure data directory exists
        if (!is_dir($this->dataDir)) {
            if (!mkdir($this->dataDir, 0755, true)) {
                return false;
            }
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        // Use file locking for atomic writes
        $fp = fopen($path, 'c');
        if ($fp === false) {
            return false;
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return false;
        }

        ftruncate($fp, 0);
        $written = fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $written !== false;
    }

    /**
     * Read JSON data from a file
     */
    public function readJson(string $filename): ?array
    {
        $path = $this->dataDir . '/' . $filename;

        if (!file_exists($path) || !is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Delete a file with automatic backup
     */
    public function deleteWithBackup(string $filename): bool
    {
        $path = $this->dataDir . '/' . $filename;

        if (!file_exists($path)) {
            return false;
        }

        // Create backup before delete
        $this->createBackup($filename);

        return unlink($path);
    }

    /**
     * Check if a file exists
     */
    public function exists(string $filename): bool
    {
        return file_exists($this->dataDir . '/' . $filename);
    }

    /**
     * Get file info
     */
    public function getFileInfo(string $filename): ?array
    {
        $path = $this->dataDir . '/' . $filename;

        if (!file_exists($path)) {
            return null;
        }

        return [
            'path' => $path,
            'size' => filesize($path),
            'lastModified' => date('c', filemtime($path)),
        ];
    }

    /**
     * Create a timestamped backup of a file
     */
    public function createBackup(string $filename): bool
    {
        $path = $this->dataDir . '/' . $filename;

        if (!file_exists($path) || !is_file($path)) {
            return false;
        }

        // Ensure backup directory exists
        if (!is_dir($this->backupDir)) {
            if (!mkdir($this->backupDir, 0755, true)) {
                return false;
            }
        }

        $timestamp = date('Y-m-d_H-i-s');
        $backupFilename = $timestamp . '_' . str_replace('/', '_', $filename);
        $backupPath = $this->backupDir . '/' . $backupFilename;

        $result = copy($path, $backupPath);

        // Clean up old backups
        $this->cleanupOldBackups($filename);

        return $result;
    }

    /**
     * Clean up old backups for a specific file, keeping only the most recent
     */
    private function cleanupOldBackups(string $filename): void
    {
        if (!is_dir($this->backupDir)) {
            return;
        }

        $pattern = '*_' . str_replace('/', '_', $filename);
        $backups = glob($this->backupDir . '/' . $pattern);

        if ($backups === false || count($backups) <= $this->maxBackups) {
            return;
        }

        // Sort by modification time (newest first)
        usort($backups, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Remove old backups beyond the limit
        $toDelete = array_slice($backups, $this->maxBackups);
        foreach ($toDelete as $backup) {
            unlink($backup);
        }
    }

    /**
     * List all backups for a file
     */
    public function listBackups(string $filename): array
    {
        if (!is_dir($this->backupDir)) {
            return [];
        }

        $pattern = '*_' . str_replace('/', '_', $filename);
        $backups = glob($this->backupDir . '/' . $pattern);

        if ($backups === false) {
            return [];
        }

        // Sort by modification time (newest first)
        usort($backups, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return array_map(function ($path) {
            return [
                'path' => $path,
                'filename' => basename($path),
                'size' => filesize($path),
                'created' => date('c', filemtime($path)),
            ];
        }, $backups);
    }

    /**
     * Restore a file from a backup
     */
    public function restoreFromBackup(string $backupFilename): bool
    {
        $backupPath = $this->backupDir . '/' . $backupFilename;

        if (!file_exists($backupPath)) {
            return false;
        }

        // Extract original filename from backup name (remove timestamp prefix)
        // Format: 2025-01-01_12-00-00_filename.json
        $parts = explode('_', $backupFilename, 4);
        if (count($parts) < 4) {
            return false;
        }

        $originalFilename = $parts[3];
        $targetPath = $this->dataDir . '/' . $originalFilename;

        // Backup current file before restoring
        if (file_exists($targetPath)) {
            $this->createBackup($originalFilename);
        }

        return copy($backupPath, $targetPath);
    }

    /**
     * Get the data directory path
     */
    public function getDataDir(): string
    {
        return $this->dataDir;
    }

    /**
     * Get the backup directory path
     */
    public function getBackupDir(): string
    {
        return $this->backupDir;
    }
}
