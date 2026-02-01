<?php
declare(strict_types=1);

namespace App;

class Upload
{
    private string $uploadDir;
    private string $dataPath;

    // Allowed MIME types and their extensions
    private const ALLOWED_IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    private const ALLOWED_DOCUMENT_TYPES = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'application/zip' => 'zip',
    ];

    // File size limits in bytes
    private const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB
    private const MAX_DOCUMENT_SIZE = 10 * 1024 * 1024; // 10MB

    public function __construct(?string $uploadDir = null, ?string $dataPath = null)
    {
        $this->uploadDir = $uploadDir ?? dirname(__DIR__) . '/public/uploads';
        $this->dataPath = $dataPath ?? Config::get('data_path');
        $this->ensureUploadDirectory();
    }

    private function ensureUploadDirectory(): void
    {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Handle file upload
     * @return array{success: bool, url?: string, filename?: string, type?: string, size?: int, error?: string}
     */
    public function handleUpload(array $file): array
    {
        // Validate file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'error' => 'No file uploaded'];
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => $this->getUploadError($file['error'])];
        }

        // Detect MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // Determine file type and validate
        $extension = null;
        $fileType = null;
        $maxSize = 0;

        if (isset(self::ALLOWED_IMAGE_TYPES[$mimeType])) {
            $extension = self::ALLOWED_IMAGE_TYPES[$mimeType];
            $fileType = 'image';
            $maxSize = self::MAX_IMAGE_SIZE;
        } elseif (isset(self::ALLOWED_DOCUMENT_TYPES[$mimeType])) {
            $extension = self::ALLOWED_DOCUMENT_TYPES[$mimeType];
            $fileType = 'document';
            $maxSize = self::MAX_DOCUMENT_SIZE;
        } else {
            return ['success' => false, 'error' => 'File type not allowed: ' . $mimeType];
        }

        // Validate file size
        if ($file['size'] > $maxSize) {
            $maxMB = $maxSize / 1024 / 1024;
            return ['success' => false, 'error' => "File too large. Maximum size is {$maxMB}MB"];
        }

        // Generate secure filename
        $filename = date('Y-m-d') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $destPath = $this->uploadDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['success' => false, 'error' => 'Failed to save file'];
        }

        // Get original filename without extension
        $originalName = pathinfo($file['name'], PATHINFO_FILENAME);

        return [
            'success' => true,
            'url' => '/uploads/' . $filename,
            'filename' => $originalName,
            'type' => $fileType,
            'size' => $file['size'],
        ];
    }

    /**
     * Delete a file from uploads
     */
    public function deleteFile(string $url): bool
    {
        // Extract filename from URL
        $filename = basename($url);
        $filePath = $this->uploadDir . '/' . $filename;

        if (file_exists($filePath) && is_file($filePath)) {
            return unlink($filePath);
        }

        return false;
    }

    /**
     * Get all referenced upload URLs from todos.json
     */
    public function getReferencedUploads(): array
    {
        $referenced = [];

        if (!file_exists($this->dataPath)) {
            return [];
        }

        $content = file_get_contents($this->dataPath);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [];
        }

        // Scan all sections and items
        foreach ($data['sections'] ?? [] as $section) {
            foreach ($section['items'] ?? [] as $item) {
                // Check attachments
                foreach ($item['attachments'] ?? [] as $attachment) {
                    if (!empty($attachment['url'])) {
                        $filename = basename($attachment['url']);
                        $referenced[$filename] = true;
                    }
                }
            }
        }

        return array_keys($referenced);
    }

    /**
     * Get all files in the uploads directory
     */
    public function getActualUploads(): array
    {
        $files = [];
        $entries = scandir($this->uploadDir);

        if ($entries === false) {
            return [];
        }

        foreach ($entries as $entry) {
            // Skip hidden files and system files
            if ($entry[0] === '.' || $entry === '.gitkeep' || $entry === '.gitignore' || $entry === '.DS_Store') {
                continue;
            }

            $filePath = $this->uploadDir . '/' . $entry;
            if (is_file($filePath)) {
                $files[] = $entry;
            }
        }

        return $files;
    }

    /**
     * Get orphaned uploads (files not referenced by any item)
     */
    public function getOrphanedUploads(): array
    {
        $referenced = $this->getReferencedUploads();
        $actual = $this->getActualUploads();

        return array_values(array_diff($actual, $referenced));
    }

    /**
     * Analyze uploads for cleanup
     */
    public function analyzeUploads(): array
    {
        $referenced = $this->getReferencedUploads();
        $actual = $this->getActualUploads();
        $orphaned = array_values(array_diff($actual, $referenced));

        // Calculate orphaned size
        $orphanedSize = 0;
        foreach ($orphaned as $filename) {
            $filePath = $this->uploadDir . '/' . $filename;
            if (file_exists($filePath)) {
                $orphanedSize += filesize($filePath);
            }
        }

        return [
            'total_files' => count($actual),
            'referenced_files' => count($referenced),
            'orphaned_files' => $orphaned,
            'orphaned_count' => count($orphaned),
            'orphaned_bytes' => $orphanedSize,
            'orphaned_human' => $this->formatBytes($orphanedSize),
        ];
    }

    /**
     * Clean up orphaned uploads
     */
    public function cleanupOrphanedUploads(): array
    {
        $orphaned = $this->getOrphanedUploads();
        $deleted = [];
        $freedBytes = 0;

        foreach ($orphaned as $filename) {
            $filePath = $this->uploadDir . '/' . $filename;
            if (file_exists($filePath)) {
                $size = filesize($filePath);
                if (unlink($filePath)) {
                    $deleted[] = $filename;
                    $freedBytes += $size;
                }
            }
        }

        return [
            'deleted_files' => $deleted,
            'deleted_count' => count($deleted),
            'freed_bytes' => $freedBytes,
            'freed_human' => $this->formatBytes($freedBytes),
        ];
    }

    /**
     * Format bytes to human readable string
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    /**
     * Get human-readable upload error message
     */
    private function getUploadError(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension',
            default => 'Unknown upload error',
        };
    }
}
