<?php
declare(strict_types=1);

namespace OpenWishlist\Support;

use PDO;
use PDOException;

final class Db
{
    public static function connect(array $cfg): PDO
    {
        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['driver'] ?? 'mysql',
            $cfg['host'] ?? '127.0.0.1',
            (int)($cfg['port'] ?? 3306),
            $cfg['name'] ?? 'openwishlist',
            $cfg['charset'] ?? 'utf8mb4'
        );
        $pdo = new PDO($dsn, $cfg['user'] ?? 'root', $cfg['pass'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }
}
