<?php
declare(strict_types=1);

// Start session for storing installation log
session_start();

$configFile = __DIR__ . '/../config/local.php';
if (file_exists($configFile)) {
    header('Location: /');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $baseUrl = trim($_POST['base_url'] ?? '');
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbPort = (int)($_POST['db_port'] ?? 3306);
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';
    $env = $_POST['env'] ?? 'prod';
    
    // Validation
    if (empty($baseUrl)) $errors[] = 'Base URL is required';
    if (empty($dbHost)) $errors[] = 'Database host is required';
    if (empty($dbName)) $errors[] = 'Database name is required';
    if (empty($dbUser)) $errors[] = 'Database user is required';
    
    // Test database connection
    if (empty($errors)) {
        try {
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $testPdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
        } catch (PDOException $e) {
            $errors[] = 'Database connection failed: ' . $e->getMessage();
        }
    }
    
    // Generate config if no errors
    if (empty($errors)) {
        $cookieSecure = strpos($baseUrl, 'https://') === 0 ? 'true' : 'false';
        
        $configContent = "<?php\nreturn [\n";
        $configContent .= "    // App\n";
        $configContent .= "    'app' => [\n";
        $configContent .= "        'base_url' => '" . addslashes($baseUrl) . "',\n";
        $configContent .= "        'env' => '" . addslashes($env) . "',\n";
        $configContent .= "    ],\n\n";
        $configContent .= "    // Database (MariaDB/MySQL)\n";
        $configContent .= "    'db' => [\n";
        $configContent .= "        'host' => '" . addslashes($dbHost) . "',\n";
        $configContent .= "        'port' => " . $dbPort . ",\n";
        $configContent .= "        'name' => '" . addslashes($dbName) . "',\n";
        $configContent .= "        'user' => '" . addslashes($dbUser) . "',\n";
        $configContent .= "        'pass' => '" . addslashes($dbPass) . "',\n";
        $configContent .= "        'charset' => 'utf8mb4',\n";
        $configContent .= "        'collation' => 'utf8mb4_unicode_ci',\n";
        $configContent .= "        'driver' => 'mysql',\n";
        $configContent .= "    ],\n\n";
        $configContent .= "    // Session\n";
        $configContent .= "    'session' => [\n";
        $configContent .= "        'name' => 'owl_session',\n";
        $configContent .= "        'cookie_secure' => " . $cookieSecure . ",\n";
        $configContent .= "        'cookie_samesite' => 'Strict',\n";
        $configContent .= "        'cookie_lifetime' => 0,\n";
        $configContent .= "        'idle_timeout_minutes' => 60\n";
        $configContent .= "    ],\n";
        $configContent .= "];\n";
        
        if (file_put_contents($configFile, $configContent)) {
            // Config created successfully, now run migrations
            try {
                $migrationLog = [];
                
                // Check if system_metadata table exists
                $tables = $testPdo->query("SHOW TABLES LIKE 'system_metadata'")->fetchAll();
                $hasMetadataTable = !empty($tables);
                
                if (!$hasMetadataTable) {
                    $migrationLog[] = "Fresh database detected - running all migrations";
                    $currentSchemaVersion = 0;
                } else {
                    // Get current schema version
                    $stmt = $testPdo->prepare("SELECT value FROM system_metadata WHERE `key` = 'schema_version'");
                    $stmt->execute();
                    $currentSchemaVersion = (int)($stmt->fetchColumn() ?: '0');
                    $migrationLog[] = "Existing database detected - current schema version: {$currentSchemaVersion}";
                }
                
                // Find and apply migrations
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
                
                if (!empty($newMigrations)) {
                    $migrationLog[] = "Found " . count($newMigrations) . " migrations to apply";
                    
                    foreach ($newMigrations as $migrationNum => $migrationFile) {
                        $migrationLog[] = "Applying migration {$migrationNum}...";
                        $sql = file_get_contents($migrationFile);
                        $testPdo->exec($sql);
                        $migrationLog[] = "Migration {$migrationNum} applied successfully";
                    }
                } else {
                    $migrationLog[] = "Database schema is up to date";
                }
                
                // Set app version
                $versionFile = __DIR__ . '/../VERSION';
                $fileVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : 'unknown';
                
                if ($hasMetadataTable || !empty($newMigrations)) {
                    $stmt = $testPdo->prepare("INSERT INTO system_metadata (`key`, `value`) VALUES ('app_version', :version) ON DUPLICATE KEY UPDATE `value` = :version");
                    $stmt->execute(['version' => $fileVersion]);
                    $migrationLog[] = "Set app version to {$fileVersion}";
                }
                
                $migrationLog[] = "Installation completed successfully!";
                
                // Store migration log for display
                $_SESSION['installation_log'] = $migrationLog;
                
                $success = true;
                
            } catch (Exception $e) {
                $errors[] = 'Database setup failed: ' . $e->getMessage();
            }
        } else {
            $errors[] = 'Configuration file could not be created';
        }
    }
}

// Don't redirect immediately if we have installation log to show
$showInstallationLog = $success && isset($_SESSION['installation_log']);

if ($success && !$showInstallationLog) {
    header('Location: /');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenWishlist Installation</title>
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
        
        .install-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        
        input, select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
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
        
        .error-item:last-child {
            margin-bottom: 0;
        }
        
        .help-text {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .success-log {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .success-log h2 {
            color: #155724;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .log-entries {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 15px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .log-entry {
            margin-bottom: 5px;
            font-family: monospace;
            font-size: 14px;
            color: #155724;
        }
        
        .log-entry:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <h1>🎁 OpenWishlist</h1>
        <p class="subtitle">Installation & Configuration</p>
        
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <?php foreach ($errors as $error): ?>
                    <div class="error-item">❌ <?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($showInstallationLog): ?>
            <div class="success-log">
                <h2>✅ Installation Completed</h2>
                <div class="log-entries">
                    <?php foreach ($_SESSION['installation_log'] as $entry): ?>
                        <div class="log-entry"><?= htmlspecialchars($entry) ?></div>
                    <?php endforeach; ?>
                </div>
                <p style="margin-top: 20px; text-align: center;">
                    <a href="/" style="
                        display: inline-block;
                        padding: 12px 24px;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        text-decoration: none;
                        border-radius: 6px;
                        font-weight: 600;
                        transition: transform 0.2s ease;
                    " onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                        🚀 Go to Application
                    </a>
                </p>
            </div>
            <?php 
            // Clear the installation log
            unset($_SESSION['installation_log']); 
            ?>
        <?php endif; ?>
        
        <?php if (!$showInstallationLog): ?>
        <form method="POST">
            <div class="form-group">
                <label for="base_url">Base URL</label>
                <input type="url" id="base_url" name="base_url" value="<?= htmlspecialchars($_POST['base_url'] ?? 'http://127.0.0.1:8080') ?>" required>
                <div class="help-text">The complete URL of your OpenWishlist installation</div>
            </div>
            
            <div class="form-group">
                <label for="env">Environment</label>
                <select id="env" name="env">
                    <option value="prod" <?= ($_POST['env'] ?? 'prod') === 'prod' ? 'selected' : '' ?>>Production</option>
                    <option value="dev" <?= ($_POST['env'] ?? '') === 'dev' ? 'selected' : '' ?>>Development</option>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="db_host">Database Host</label>
                    <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? '127.0.0.1') ?>" required>
                </div>
                <div class="form-group">
                    <label for="db_port">Port</label>
                    <input type="number" id="db_port" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>" min="1" max="65535" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="db_name">Database Name</label>
                <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'openwishlist') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_user">Database User</label>
                <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_pass">Database Password</label>
                <input type="password" id="db_pass" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>">
            </div>
            
            <button type="submit">🚀 Start Installation</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>