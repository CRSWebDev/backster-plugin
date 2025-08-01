<?php

namespace CRSCompany\Backster\Classes;

/**
 * Backup Manager Class
 * 
 * Handles database and project backups with static methods
 */
class BackupManager
{
    /**
     * Create a complete backup (database + project files)
     * 
     * @param string|null $backupDir Custom backup directory path
     * @return array Returns backup result with success status and file info
     */
    public static function createCompleteBackup($type = 'daily')
    {
        // Generate backup directory if not provided
        if ($type === 'daily') {
            $backupDir = base_path('../backups/daily/' . date('Y-m-d_H-i-s'));
        } elseif ($type === 'weekly') {
            $backupDir = base_path('../backups/weekly/' . date('Y-m-d_H-i-s'));
        } else {
            throw new \Exception('Invalid backup type: ' . $type);
        }

        // Ensure backup directory exists
        if (!file_exists($backupDir)) {
            if (!mkdir($backupDir, 0755, true)) {
                return [
                    'success' => false,
                    'error' => 'Failed to create backup directory',
                    'path' => $backupDir
                ];
            }
        }

        $result =  self::createProjectBackup($backupDir);

        // Keep a maximum of 5 backups in the backup directory (daily/weekly)
        $parentDir = dirname($backupDir);
        if (is_dir($parentDir)) {
            $backupFolders = array_filter(glob($parentDir . '/*'), 'is_dir');
            // Sort by folder name descending (newest first)
            usort($backupFolders, function($a, $b) {
                return strcmp($b, $a);
            });
            // Remove oldest if more than 5
            if (count($backupFolders) > 5) {
                $toDelete = array_slice($backupFolders, 5);
                foreach ($toDelete as $dir) {
                    // Recursively delete directory
                    $it = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
                    $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
                    foreach($files as $file) {
                        if ($file->isDir()){
                            rmdir($file->getRealPath());
                        } else {
                            unlink($file->getRealPath());
                        }
                    }
                    rmdir($dir);
                }
            }
        }

        return $result;
    }

    /**
     * Create a database backup
     * 
     * @param string $backupDir The backup directory path
     * @return array Returns backup result with success status and file info
     */
    public static function createDatabaseBackup($backupDir)
    {
        $connection = config('database.default');
        $dbConfig = config("database.connections.$connection");

        $extension = '.sql';
        if ($dbConfig['driver'] === 'sqlite') {
            $extension = '.sqlite';
        }

        $outputPath = $backupDir . '/db-' . $dbConfig['driver'] . '-' . date('Y-m-d_H-i-s') . $extension;

        try {
            $result = null;
            $output = [];

            if ($dbConfig['driver'] === 'mysql') {
                $result = self::backupMySQL($dbConfig, $outputPath, $output);
            } elseif ($dbConfig['driver'] === 'pgsql') {
                $result = self::backupPostgreSQL($dbConfig, $outputPath, $output);
            } elseif ($dbConfig['driver'] === 'sqlite') {
                $result = self::backupSQLite($dbConfig, $outputPath, $output);
            } else {
                throw new \Exception('Unsupported database driver: ' . $dbConfig['driver']);
            }

            if ($result['success']) {
                // Verify file was created
                if (!file_exists($outputPath)) {
                    return [
                        'success' => false,
                        'error' => 'Backup file not created',
                        'path' => $outputPath
                    ];
                }

                $fileSize = filesize($outputPath);

                return [
                    'success' => true,
                    'file' => $outputPath,
                    'size' => $fileSize,
                    'driver' => $dbConfig['driver']
                ];
            } else {
                return $result;
            }

        } catch (\Exception $e) {
            \Log::error('Backup exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Backup failed',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Create a project backup (files only)
     * 
     * @param string $backupDir The backup directory path
     * @return array Returns backup result with success status and file info
     */
    public static function createProjectBackup($backupDir)
    {
        $outputPath = $backupDir . '/web-' . date('Y-m-d_H-i-s') . '.zip';

        try {
            // Create temporary directory for backup contents
            $tempDir = sys_get_temp_dir() . '/project_backup_' . uniqid();
            if (!mkdir($tempDir, 0755, true)) {
                return [
                    'success' => false,
                    'error' => 'Failed to create temporary directory',
                    'path' => $tempDir
                ];
            }

            // Copy project files (excluding cache and logs)
            $projectRoot = base_path();
            $exclusions = [
                'storage/logs',
                'storage/framework/cache',
                'storage/framework/sessions',
                'storage/framework/views',
                'storage/cms/cache',
                'storage/cms/combiner',
                'storage/cms/twig'
            ];

            if (!self::copyProjectFiles($projectRoot, $tempDir . '/project', $exclusions)) {
                return [
                    'success' => false,
                    'error' => 'Failed to copy project files'
                ];
            }

            // Create database backup in the backup directory (outside the zip)
            $dbResult = self::createDatabaseBackup($backupDir);
            if (!$dbResult['success']) {
                return [
                    'success' => false,
                    'error' => 'Database backup failed',
                    'details' => $dbResult
                ];
            }

            // Create zip file
            if (!self::createZipArchive($tempDir, $outputPath)) {
                return [
                    'success' => false,
                    'error' => 'Failed to create zip archive'
                ];
            }

            // Clean up temporary directory
            self::removeDirectory($tempDir);

            // Verify zip was created
            if (!file_exists($outputPath)) {
                return [
                    'success' => false,
                    'error' => 'Backup zip file not created',
                    'path' => $outputPath
                ];
            }

            $fileSize = filesize($outputPath);

            return [
                'success' => true,
                'file' => $outputPath,
                'size' => $fileSize,
                'database_backup' => $dbResult
            ];

        } catch (\Exception $e) {
            \Log::error('Project backup exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Project backup failed',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Backup MySQL database
     */
    private static function backupMySQL($dbConfig, $outputPath, &$output)
    {
        $host = $dbConfig['host'];
        $port = isset($dbConfig['port']) ? $dbConfig['port'] : 3306;
        $database = $dbConfig['database'];
        $username = $dbConfig['username'];
        $password = $dbConfig['password'];

        // Escape shell arguments
        $host = escapeshellarg($host);
        $port = escapeshellarg($port);
        $database = escapeshellarg($database);
        $username = escapeshellarg($username);
        $outputPathEscaped = escapeshellarg($outputPath);

        // Build mysqldump command
        $cmd = "mysqldump --user={$username} --password=" . escapeshellarg($password) .
            " --host={$host} --port={$port} {$database} > {$outputPathEscaped}";

        $result = null;
        exec($cmd . ' 2>&1', $output, $result);

        if ($result !== 0) {
            \Log::error('MySQL backup failed: ' . implode("\n", $output));
            return [
                'success' => false,
                'error' => 'MySQL backup failed',
                'details' => $output,
                'command' => $cmd
            ];
        }

        return ['success' => true];
    }

    /**
     * Backup PostgreSQL database
     */
    private static function backupPostgreSQL($dbConfig, $outputPath, &$output)
    {
        $host = $dbConfig['host'];
        $port = isset($dbConfig['port']) ? $dbConfig['port'] : 5432;
        $database = $dbConfig['database'];
        $username = $dbConfig['username'];
        $password = $dbConfig['password'];

        // Set PGPASSWORD env variable for pg_dump
        $env = "PGPASSWORD=" . escapeshellarg($password);

        $host = escapeshellarg($host);
        $port = escapeshellarg($port);
        $database = escapeshellarg($database);
        $username = escapeshellarg($username);
        $outputPathEscaped = escapeshellarg($outputPath);

        $cmd = "{$env} pg_dump -U {$username} -h {$host} -p {$port} -F c -b -v -f {$outputPathEscaped} {$database}";

        $result = null;
        exec($cmd . ' 2>&1', $output, $result);

        if ($result !== 0) {
            \Log::error('PostgreSQL backup failed: ' . implode("\n", $output));
            return [
                'success' => false,
                'error' => 'PostgreSQL backup failed',
                'details' => $output,
                'command' => $cmd
            ];
        }

        return ['success' => true];
    }

    /**
     * Backup SQLite database
     */
    private static function backupSQLite($dbConfig, $outputPath, &$output)
    {
        $database = $dbConfig['database'];

        // Check if sqlite3 command is available
        $sqlite3Available = shell_exec('which sqlite3 2>/dev/null');
        if (empty($sqlite3Available)) {
            \Log::error('sqlite3 command not found on system');
            return [
                'success' => false,
                'error' => 'sqlite3 command not available on system'
            ];
        }

        // Check if database file exists
        if (!file_exists($database)) {
            \Log::error('SQLite database file not found: ' . $database);
            return [
                'success' => false,
                'error' => 'Database file not found',
                'path' => $database
            ];
        }

        $databaseEscaped = escapeshellarg($database);
        $outputPathEscaped = escapeshellarg($outputPath);
        $cmd = "sqlite3 {$databaseEscaped} .dump > {$outputPathEscaped}";

        $result = null;
        exec($cmd . ' 2>&1', $output, $result);

        if ($result !== 0) {
            \Log::error('SQLite backup failed with code: ' . $result);
            \Log::error('SQLite backup output: ' . implode("\n", $output));
            return [
                'success' => false,
                'error' => 'SQLite backup failed',
                'code' => $result,
                'details' => $output,
                'command' => $cmd
            ];
        }

        return ['success' => true];
    }

    /**
     * Copy project files recursively, excluding specified directories
     */
    private static function copyProjectFiles($source, $destination, $exclusions)
    {
        if (!is_dir($source)) {
            return false;
        }

        if (!mkdir($destination, 0755, true)) {
            return false;
        }

        $dir = opendir($source);
        if (!$dir) {
            return false;
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $sourcePath = $source . '/' . $file;
            $destPath = $destination . '/' . $file;

            // Check if this path should be excluded
            $relativePath = str_replace(base_path() . '/', '', $sourcePath);
            $shouldExclude = false;

            foreach ($exclusions as $exclusion) {
                if (strpos($relativePath, $exclusion) === 0) {
                    $shouldExclude = true;
                    break;
                }
            }

            if ($shouldExclude) {
                continue;
            }

            if (is_dir($sourcePath)) {
                if (!self::copyProjectFiles($sourcePath, $destPath, $exclusions)) {
                    return false;
                }
            } else {
                if (!copy($sourcePath, $destPath)) {
                    return false;
                }
            }
        }

        closedir($dir);
        return true;
    }

    /**
     * Create a zip archive from a directory
     */
    private static function createZipArchive($sourceDir, $outputPath)
    {
        $zip = new \ZipArchive();

        if ($zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }

        return $zip->close();
    }

    /**
     * Remove a directory and all its contents recursively
     */
    private static function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
} 