<?php
declare(strict_types=1);

// Health Check endpoint for system diagnostics
// ADMIN ACCESS REQUIRED - Contains sensitive system information

// First check if we have basic requirements for authentication
$vendorPath = __DIR__ . '/../vendor/autoload.php';
$configFile = __DIR__ . '/../config/local.php';

if (!file_exists($vendorPath) || !file_exists($configFile)) {
    http_response_code(503);
    die('System not properly configured');
}

require_once $vendorPath;

use OpenWishlist\Support\Session;
use OpenWishlist\Support\Db;

// Load config and start session
$config = require $configFile;
Session::start($config['session'] ?? []);

// Connect to database
try {
    $pdo = Db::connect($config['db']);
} catch (Exception $e) {
    http_response_code(503);
    die('Database connection failed');
}

// Require admin access - either via session or Basic Auth
$isAuthenticated = false;
$uid = Session::userId();

if ($uid) {
    // Check if session user is admin
    $role = $pdo->prepare('SELECT role FROM users WHERE id=:id');
    $role->execute(['id'=>$uid]);
    if (($role->fetchColumn() ?: 'user') === 'admin') {
        $isAuthenticated = true;
    }
}

if (!$isAuthenticated) {
    // Try Basic Auth as fallback
    if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
        $username = $_SERVER['PHP_AUTH_USER'];
        $password = $_SERVER['PHP_AUTH_PW'];
        
        // Check credentials against database
        $stmt = $pdo->prepare('SELECT id, password_hash, role FROM users WHERE email = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash']) && $user['role'] === 'admin') {
            $isAuthenticated = true;
        }
    }
    
    if (!$isAuthenticated) {
        http_response_code(401);
        header('WWW-Authenticate: Basic realm="OpenWishlist Health Check"');
        die('<!DOCTYPE html><html><head><title>Authentication Required</title></head><body>
            <h1>🔐 Authentication Required</h1>
            <p>Access to the health check requires admin privileges.</p>
            <p>Please authenticate with your admin credentials or <a href="/login">login via web interface</a>.</p>
        </body></html>');
    }
}

$checks = [];
$overallStatus = 'healthy';

// Check 1: PHP Version
$minPhpVersion = '8.2';
$phpVersionOk = version_compare(PHP_VERSION, $minPhpVersion, '>=');
$checks['php_version'] = [
    'name' => 'PHP Version',
    'status' => $phpVersionOk ? 'pass' : 'fail',
    'message' => 'PHP ' . PHP_VERSION . ($phpVersionOk ? ' (OK)' : " (Requires {$minPhpVersion}+)"),
    'details' => [
        'current' => PHP_VERSION,
        'required' => $minPhpVersion . '+',
        'satisfies' => $phpVersionOk
    ]
];

if (!$phpVersionOk) $overallStatus = 'unhealthy';

// Check 2: Required PHP Extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'session', 'curl', 'gd'];
$extensionStatus = 'pass';
$extensionDetails = [];

foreach ($requiredExtensions as $ext) {
    $loaded = extension_loaded($ext);
    $extensionDetails[$ext] = $loaded;
    if (!$loaded) $extensionStatus = 'fail';
}

$checks['php_extensions'] = [
    'name' => 'PHP Extensions',
    'status' => $extensionStatus,
    'message' => $extensionStatus === 'pass' ? 'All required extensions available' : 'Missing required extensions',
    'details' => $extensionDetails
];

if ($extensionStatus === 'fail') $overallStatus = 'unhealthy';

// Check 3: Vendor Directory and Autoloader
$vendorPath = __DIR__ . '/../vendor';
$autoloaderPath = $vendorPath . '/autoload.php';
$vendorOk = is_dir($vendorPath) && file_exists($autoloaderPath);

$checks['composer_dependencies'] = [
    'name' => 'Composer Dependencies',
    'status' => $vendorOk ? 'pass' : 'fail',
    'message' => $vendorOk ? 'Vendor directory and autoloader available' : 'Missing vendor directory or autoloader',
    'details' => [
        'vendor_dir_exists' => is_dir($vendorPath),
        'autoloader_exists' => file_exists($autoloaderPath),
        'vendor_path' => $vendorPath
    ]
];

if (!$vendorOk) $overallStatus = 'unhealthy';

// Check 4: File Permissions
$writableDirectories = [
    'config' => __DIR__ . '/../config',
    'uploads' => __DIR__ . '/../public/uploads',
    'temp' => sys_get_temp_dir()
];

$permissionStatus = 'pass';
$permissionDetails = [];

foreach ($writableDirectories as $name => $path) {
    $writable = is_dir($path) && is_writable($path);
    $permissionDetails[$name] = [
        'path' => $path,
        'exists' => is_dir($path),
        'writable' => $writable
    ];
    if (!$writable && $name !== 'temp') $permissionStatus = 'warning'; // temp dir is optional
}

$checks['file_permissions'] = [
    'name' => 'File Permissions',
    'status' => $permissionStatus,
    'message' => $permissionStatus === 'pass' ? 'All directories writable' : 'Some directories not writable',
    'details' => $permissionDetails
];

if ($permissionStatus === 'fail') $overallStatus = 'unhealthy';

// Check 5: Configuration File
$configFile = __DIR__ . '/../config/local.php';
$configExists = file_exists($configFile);

$checks['configuration'] = [
    'name' => 'Configuration',
    'status' => $configExists ? 'pass' : 'warning',
    'message' => $configExists ? 'Configuration file exists' : 'No configuration file (installer needed)',
    'details' => [
        'config_file' => $configFile,
        'exists' => $configExists
    ]
];

// Check 6: Database Connection (only if config exists)
if ($configExists && $vendorOk) {
    try {
        require_once $autoloaderPath;
        $config = require $configFile;
        
        $dsn = "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // Test basic query
        $pdo->query('SELECT 1');
        
        $checks['database'] = [
            'name' => 'Database Connection',
            'status' => 'pass',
            'message' => 'Database connection successful',
            'details' => [
                'host' => $config['db']['host'],
                'port' => $config['db']['port'],
                'database' => $config['db']['name']
            ]
        ];
        
    } catch (Exception $e) {
        $checks['database'] = [
            'name' => 'Database Connection',
            'status' => 'fail',
            'message' => 'Database connection failed: ' . $e->getMessage(),
            'details' => [
                'error' => $e->getMessage(),
                'config_loaded' => true
            ]
        ];
        $overallStatus = 'unhealthy';
    }
} else {
    $checks['database'] = [
        'name' => 'Database Connection',
        'status' => 'skip',
        'message' => 'Skipped (missing config or dependencies)',
        'details' => [
            'reason' => !$configExists ? 'No configuration' : 'Missing dependencies'
        ]
    ];
}

// Determine HTTP status code
http_response_code($overallStatus === 'healthy' ? 200 : 503);

// Handle different output formats
$format = $_GET['format'] ?? 'html';

if ($format === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $overallStatus,
        'timestamp' => date('c'),
        'checks' => $checks
    ], JSON_PRETTY_PRINT);
    exit;
}

// HTML Output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenWishlist Health Check</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-healthy { background: #d4edda; color: #155724; }
        .status-unhealthy { background: #f8d7da; color: #721c24; }
        .status-warning { background: #fff3cd; color: #856404; }
        
        .overall-status {
            text-align: center;
            margin-bottom: 30px;
            font-size: 18px;
        }
        
        .check-item {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .check-header {
            background: #f8f9fa;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .check-name {
            font-weight: 600;
            color: #333;
        }
        
        .check-pass { background: #d4edda; }
        .check-fail { background: #f8d7da; }
        .check-warning { background: #fff3cd; }
        .check-skip { background: #e2e3e5; }
        
        .check-details {
            padding: 15px 20px;
            background: white;
        }
        
        .check-message {
            margin-bottom: 10px;
            color: #666;
        }
        
        .details-list {
            font-family: monospace;
            font-size: 14px;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
        }
        
        .details-list div {
            margin-bottom: 5px;
        }
        
        .timestamp {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-top: 20px;
        }
        
        .format-links {
            text-align: center;
            margin-top: 20px;
        }
        
        .format-links a {
            margin: 0 10px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏥 System Health Check</h1>
        
        <div class="overall-status">
            System Status: 
            <span class="status-badge status-<?= $overallStatus ?>">
                <?= ucfirst($overallStatus) ?>
            </span>
        </div>
        
        <?php foreach ($checks as $key => $check): ?>
            <div class="check-item">
                <div class="check-header check-<?= $check['status'] ?>">
                    <span class="check-name">
                        <?= $check['status'] === 'pass' ? '✅' : ($check['status'] === 'fail' ? '❌' : ($check['status'] === 'warning' ? '⚠️' : '⏭️')) ?>
                        <?= htmlspecialchars($check['name']) ?>
                    </span>
                    <span class="status-badge status-<?= $check['status'] === 'pass' ? 'healthy' : ($check['status'] === 'fail' ? 'unhealthy' : 'warning') ?>">
                        <?= ucfirst($check['status']) ?>
                    </span>
                </div>
                <div class="check-details">
                    <div class="check-message">
                        <?= htmlspecialchars($check['message']) ?>
                    </div>
                    <?php if (!empty($check['details'])): ?>
                        <div class="details-list">
                            <?php foreach ($check['details'] as $key => $value): ?>
                                <div>
                                    <strong><?= htmlspecialchars($key) ?>:</strong> 
                                    <?= is_bool($value) ? ($value ? 'true' : 'false') : htmlspecialchars((string)$value) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="timestamp">
            Generated at: <?= date('Y-m-d H:i:s T') ?>
        </div>
        
        <div class="format-links">
            <a href="?format=html">HTML</a>
            <a href="?format=json">JSON</a>
        </div>
    </div>
</body>
</html>