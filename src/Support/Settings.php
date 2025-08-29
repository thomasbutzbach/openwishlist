<?php
declare(strict_types=1);

namespace OpenWishlist\Support;

use PDO;

final class Settings
{
    public static function load(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT `key`, `value`, `type` FROM settings ORDER BY `key`');
        $settings = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = self::parseValue($row['value'], $row['type']);
        }
        
        return $settings;
    }
    
    public static function get(PDO $pdo, string $key, mixed $default = null): mixed
    {
        $stmt = $pdo->prepare('SELECT `value`, `type` FROM settings WHERE `key` = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return $default;
        }
        
        return self::parseValue($row['value'], $row['type']);
    }
    
    public static function set(PDO $pdo, string $key, mixed $value, string $type = 'string', ?string $groupName = null): void
    {
        $serializedValue = self::serializeValue($value, $type);
        
        $stmt = $pdo->prepare('
            INSERT INTO settings (`key`, `value`, `type`, `group_name`) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                `value` = VALUES(`value`), 
                `type` = VALUES(`type`), 
                `group_name` = VALUES(`group_name`)
        ');
        
        $stmt->execute([$key, $serializedValue, $type, $groupName]);
    }
    
    private static function parseValue(string $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            'url', 'email', 'secret', 'string' => $value,
            default => $value,
        };
    }
    
    private static function serializeValue(mixed $value, string $type): string
    {
        return match ($type) {
            'int' => (string) (int) $value,
            'bool' => $value ? '1' : '0',
            'json' => json_encode($value),
            'url', 'email', 'secret', 'string' => (string) $value,
            default => (string) $value,
        };
    }
}