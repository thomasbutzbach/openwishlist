<?php
declare(strict_types=1);

use OpenWishlist\Support\Session;
use OpenWishlist\Support\Db;

require __DIR__ . '/../vendor/autoload.php';

// Load config
$configFile = __DIR__ . '/../config/local.php';
if (!file_exists($configFile)) {
    die('Missing config/local.php. Please run installer first.');
}
$config = require $configFile;

// Start session
Session::start($config['session'] ?? []);

// Connect to database
$pdo = Db::connect($config['db']);

// Require admin access
$uid = Session::userId();
if (!$uid) {
    header('Location: /login');
    exit;
}

$role = $pdo->prepare('SELECT role FROM users WHERE id=:id');
$role->execute(['id'=>$uid]);
if (($role->fetchColumn() ?: 'user') !== 'admin') {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><title>Access Denied</title></head><body><h1>403 - Admin Access Required</h1><p><a href="/login">Login</a> | <a href="/">Home</a></p></body></html>';
    exit;
}

// Get version info
$versionFile = __DIR__ . '/../VERSION';
$fileVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : 'unknown';

$stmt = $pdo->prepare("SELECT value FROM system_metadata WHERE `key` = 'app_version'");
$stmt->execute();
$dbVersion = $stmt->fetchColumn() ?: 'unknown';

// Check if update is needed
$updateNeeded = version_compare($fileVersion, $dbVersion, '>');

$errors = [];
$success = false;
$updateLog = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_update']) && $updateNeeded) {
    try {
        $updateLog[] = "Starting update from {$dbVersion} to {$fileVersion}...";
        
        // Get current schema version
        $stmt = $pdo->prepare("SELECT value FROM system_metadata WHERE `key` = 'schema_version'");
        $stmt->execute();
        $currentSchemaVersion = (int)($stmt->fetchColumn() ?: '0');
        $updateLog[] = "Current schema version: {$currentSchemaVersion}";
        
        // Find new migrations
        $migrationsDir = __DIR__ . '/../migrations';
        $newMigrations = [];
        
        if (is_dir($migrationsDir)) {
            $files = glob($migrationsDir . '/*_up.sql');
            foreach ($files as $file) {
                if (preg_match('/(\d+)_up\.sql$/', basename($file), $matches)) {
                    $migrationNum = (int)$matches[1];
                    if ($migrationNum > $currentSchemaVersion) {
                        $newMigrations[$migrationNum] = $file;
                    }
                }
            }
        }
        
        ksort($newMigrations);
        $updateLog[] = "Found " . count($newMigrations) . " new migrations to apply";
        
        // Apply migrations (each handles its own transaction)
        foreach ($newMigrations as $migrationNum => $migrationFile) {
            $updateLog[] = "Applying migration {$migrationNum}...";
            $sql = file_get_contents($migrationFile);
            
            // Execute migration as-is (it handles its own transaction)
            $pdo->exec($sql);
            
            $updateLog[] = "Migration {$migrationNum} applied successfully";
        }
        
        // Update app version in separate transaction
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO system_metadata (`key`, `value`) VALUES ('app_version', :version) ON DUPLICATE KEY UPDATE `value` = :version");
        $stmt->execute(['version' => $fileVersion]);
        $pdo->commit();
        
        $updateLog[] = "Updated app version to {$fileVersion}";
        $updateLog[] = "Update completed successfully!";
        
        $success = true;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = "Update failed: " . $e->getMessage();
        $updateLog[] = "ERROR: " . $e->getMessage();
        error_log("Update failed: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    }
}

if ($success) {
    // Clear any session update info
    unset($_SESSION['update_available']);
    
    // Redirect to admin after successful update
    header('Location: /admin?updated=1');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenWishlist Update</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .update-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }
        
        .version-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .version-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .version-row:last-child {
            margin-bottom: 0;
        }
        
        .version-label {
            font-weight: 600;
            color: #333;
        }
        
        .version-value {
            font-family: monospace;
            background: white;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        
        .arrow {
            text-align: center;
            font-size: 20px;
            color: #28a745;
            margin: 15px 0;
        }
        
        .up-to-date {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .up-to-date h2 {
            color: #155724;
            margin-bottom: 10px;
        }
        
        .up-to-date p {
            color: #155724;
            margin-bottom: 0;
        }
        
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            color: #856404;
        }
        
        .warning strong {
            display: block;
            margin-bottom: 5px;
        }
        
        .errors {
            background: #fee;
            border: 1px solid #fcc;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .error-item {
            color: #c33;
            margin-bottom: 5px;
        }
        
        .update-log {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .log-entry {
            margin-bottom: 5px;
            font-family: monospace;
            font-size: 14px;
        }
        
        .log-entry:last-child {
            margin-bottom: 0;
        }
        
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #666;
            text-decoration: none;
        }
        
        .back-link a:hover {
            color: #333;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="update-container">
        <h1>🔄 System Update</h1>
        <p class="subtitle">Update your OpenWishlist installation</p>
        
        <?php if (!$updateNeeded): ?>
            <div class="up-to-date">
                <h2>✅ System Up to Date</h2>
                <p>Your OpenWishlist installation is already running the latest version: <strong><?= htmlspecialchars($dbVersion) ?></strong></p>
            </div>
            
            <div class="back-link">
                <a href="/admin">← Back to Admin Dashboard</a>
            </div>
        <?php else: ?>
            <div class="version-info">
                <div class="version-row">
                    <span class="version-label">Current Version:</span>
                    <span class="version-value"><?= htmlspecialchars($dbVersion) ?></span>
                </div>
                <div class="arrow">⬇</div>
                <div class="version-row">
                    <span class="version-label">New Version:</span>
                    <span class="version-value"><?= htmlspecialchars($fileVersion) ?></span>
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="errors">
                    <?php foreach ($errors as $error): ?>
                        <div class="error-item">❌ <?= htmlspecialchars($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($updateLog)): ?>
                <div class="update-log">
                    <?php foreach ($updateLog as $entry): ?>
                        <div class="log-entry"><?= htmlspecialchars($entry) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($_POST['start_update']) && empty($success)): ?>
                <div class="warning">
                    <strong>⚠️ Important:</strong>
                    Please backup your database before proceeding. This update will modify your database schema and cannot be easily undone.
                </div>
                
                <form method="POST">
                    <input type="hidden" name="start_update" value="1">
                    <button type="submit">🚀 Start Update Process</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>