<?php
return [
    // App
    'app' => [
        'base_url' => 'http://127.0.0.1:8080',
        'env' => 'dev',
    ],

    // Database (MariaDB/MySQL)
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'openwishlist',
        'user' => 'owl',
        'pass' => 'change-me',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'driver' => 'mysql',
    ],

    // Session
    'session' => [
        'name' => 'owl_session',
        'cookie_secure' => false, // true when using HTTPS
        'cookie_samesite' => 'Strict', // 'Strict' or 'Lax'
        'cookie_lifetime' => 0,     // 0 = session cookie
        'idle_timeout_minutes' => 60
    ],
];
