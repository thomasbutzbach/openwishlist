<?php
declare(strict_types=1);

namespace OpenWishlist\Support;

final class Version
{
    private static ?string $cached = null;
    
    public static function current(): string
    {
        if (self::$cached === null) {
            $versionFile = __DIR__ . '/../../VERSION';
            if (!file_exists($versionFile)) {
                return 'unknown';
            }
            
            self::$cached = trim(file_get_contents($versionFile));
            
            // Validate semantic versioning format
            if (!preg_match('/^\d+\.\d+\.\d+(-[a-zA-Z0-9\-\.]+)?$/', self::$cached)) {
                return 'invalid';
            }
        }
        
        return self::$cached;
    }
    
    public static function isPreRelease(): bool
    {
        $version = self::current();
        return str_contains($version, '-') || version_compare($version, '1.0.0', '<');
    }
    
    public static function formatDisplay(): string
    {
        $version = self::current();
        
        if (self::isPreRelease()) {
            return "v{$version} (Pre-Release)";
        }
        
        return "v{$version}";
    }
}