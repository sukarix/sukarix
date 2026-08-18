<?php

declare(strict_types=1);

namespace Sukarix\Utils;

/**
 * Generic filesystem helpers shared across Sukarix applications.
 */
class FileSystemUtils
{
    public static function createDirectory($path)
    {
        if (!is_dir($path) && !mkdir($path, 0o777, true) && !is_dir($path)) {
            throw new \RuntimeException(\sprintf('Directory "%s" was not created', $path));
        }
    }

    public static function isDirectoryEmpty(string $dir): bool
    {
        if (!is_readable($dir) || !is_dir($dir)) {
            return false; // Directory doesn't exist or isn't readable
        }

        $iterator = new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS);

        return !$iterator->valid(); // Returns false if there are files
    }

    /**
     * Scan a directory and return all file paths matching a specific pattern.
     *
     * @param string $directory the directory to scan
     * @param string $pattern   the file extension or pattern to match
     *
     * @return array list of matching file paths
     */
    public static function scanDirectory(string $directory, string $pattern): array
    {
        $files = [];

        if (!is_dir($directory)) {
            throw new \InvalidArgumentException("The directory '{$directory}' does not exist.");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (str_ends_with($file->getFilename(), $pattern)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Ensure the directory exists and is writable. Create it if necessary.
     *
     * @throws \RuntimeException
     */
    public static function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw new \RuntimeException("Directory '{$directory}' could not be created.");
        }

        if (!is_writable($directory)) {
            throw new \RuntimeException("Directory '{$directory}' is not writable.");
        }
    }
}
