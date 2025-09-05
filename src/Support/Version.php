<?php
declare(strict_types=1);

namespace OpenWishlist\Support;

final class Version
{
    private static ?string $cached = null;
    private static ?\PDO $pdo = null;
    
    public static function setPdo(\PDO $pdo): void
    {
        self::$pdo = $pdo;
    }
    
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
    
    public static function installed(): string
    {
        if (self::$pdo === null) {
            // Fallback to file version if no PDO available
            return self::current();
        }
        
        try {
            $stmt = self::$pdo->prepare("SELECT value FROM system_metadata WHERE `key` = 'app_version'");
            $stmt->execute();
            $version = $stmt->fetchColumn();
            
            return $version ?: self::current();
        } catch (\Exception $e) {
            // Fallback to file version on error
            return self::current();
        }
    }
    
    public static function isPreRelease(): bool
    {
        $version = self::current();
        return str_contains($version, '-') || version_compare($version, '1.0.0', '<');
    }
    
    public static function formatDisplay(): string
    {
        $version = self::installed(); // Use installed version instead of file version
        
        if (self::isPreRelease()) {
            return "v{$version} (Pre-Release)";
        }
        
        return "v{$version}";
    }
}