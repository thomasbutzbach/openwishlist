<?php
declare(strict_types=1);

namespace OpenWishlist\Support;

final class Str
{
    public static function randomSlug(int $len = 10): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $out;
    }
}
