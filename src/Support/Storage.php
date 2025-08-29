<?php
declare(strict_types=1);

namespace OpenWishlist\Support;

/**
 * Simple file storage utilities for images.
 */
final class Storage
{
    /** Get file extension for MIME type. */
    public static function extForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'bin',
        };
    }

    /** Ensure directory exists. */
    public static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new \RuntimeException("Failed to create directory: $dir");
            }
        }
    }

    /** 
     * Build file path for image based on hash.
     * @return array{0: string, 1: string} [absolute_path, relative_path]
     */
    public static function buildImagePath(string $uploadsDir, string $hash, string $ext): array
    {
        // Use first 2 chars of hash for subdirectory (avoids too many files in one dir)
        $subdir = substr($hash, 0, 2);
        $filename = $hash . '.' . $ext;
        
        $absolutePath = $uploadsDir . '/' . $subdir . '/' . $filename;
        $relativePath = 'uploads/' . $subdir . '/' . $filename;
        
        // Ensure subdirectory exists
        self::ensureDir($uploadsDir . '/' . $subdir);
        
        return [$absolutePath, $relativePath];
    }
}