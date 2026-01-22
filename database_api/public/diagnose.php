<?php
/**
 * Diagnostic Script - Run this to identify setup issues
 * Access: https://beta.gauratravel.com.au/wp-content/themes/twentytwenty/templates/database-apis/public/diagnose.php
 */

header('Content-Type: text/html; charset=utf-8');

$issues = [];
$warnings = [];
$success = [];

echo "<!DOCTYPE html><html><head><title>Database API Diagnostics</title>";
echo "<style>
body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
.success { color: green; padding: 10px; background: #e8f5e9; margin: 5px 0; border-left: 4px solid green; }
.warning { color: orange; padding: 10px; background: #fff3e0; margin: 5px 0; border-left: 4px solid orange; }
.error { color: red; padding: 10px; background: #ffebee; margin: 5px 0; border-left: 4px solid red; }
.info { background: #e3f2fd; padding: 10px; margin: 10px 0; border-left: 4px solid #2196F3; }
h1 { color: #333; }
h2 { color: #666; margin-top: 30px; }
code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
.summary { font-size: 20px; font-weight: bold; margin: 20px 0; }
</style></head><body>";

echo "<h1>🔍 Database API Diagnostics</h1>";
echo "<p>Running comprehensive checks...</p>";

// ==========================================
// CHECK 1: PHP Version
// ==========================================
echo "<h2>1. PHP Version</h2>";
$phpVersion = phpversion();
if (version_compare($phpVersion, '7.4.0', '>=')) {
    $success[] = "✅ PHP version $phpVersion is supported (7.4+ required)";
    echo "<div class='success'>✅ PHP version <code>$phpVersion</code> is supported</div>";
} else {
    $issues[] = "❌ PHP version $phpVersion is too old (7.4+ required)";
    echo "<div class='error'>❌ PHP version <code>$phpVersion</code> is too old. Upgrade to 7.4 or higher.</div>";
}

// ==========================================
// CHECK 2: Required PHP Extensions
// ==========================================
echo "<h2>2. Required PHP Extensions</h2>";
$required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'json'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        $success[] = "✅ Extension: $ext";
        echo "<div class='success'>✅ <code>$ext</code> loaded</div>";
    } else {
        $issues[] = "❌ Missing extension: $ext";
        echo "<div class='error'>❌ <code>$ext</code> not loaded. Install with: <code>sudo apt-get install php-$ext</code></div>";
    }
}

// ==========================================
// CHECK 3: Composer Vendor Directory
// ==========================================
echo "<h2>3. Composer Dependencies</h2>";
$vendorPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorPath)) {
    $success[] = "✅ Composer dependencies installed";
    echo "<div class='success'>✅ Vendor directory exists at: <code>$vendorPath</code></div>";
    
    // Check specific packages
    $slimPath = __DIR__ . '/../vendor/slim/slim';
    $phpDiPath = __DIR__ . '/../vendor/php-di/php-di';
    $dotenvPath = __DIR__ . '/../vendor/vlucas/phpdotenv';
    
    if (file_exists($slimPath)) {
        echo "<div class='success'>✅ Slim Framework installed</div>";
    } else {
        $warnings[] = "⚠️ Slim Framework directory not found";
        echo "<div class='warning'>⚠️ Slim Framework directory not found</div>";
    }
    
    if (file_exists($phpDiPath)) {
        echo "<div class='success'>✅ PHP-DI installed</div>";
    }
    
    if (file_exists($dotenvPath)) {
        echo "<div class='success'>✅ PHP dotenv installed</div>";
    }
} else {
    $issues[] = "❌ Composer dependencies not installed";
    echo "<div class='error'>❌ Vendor directory not found. Run: <code>composer install</code></div>";
    echo "<div class='info'>💡 <strong>To fix:</strong><br>";
    echo "cd /var/www/html/wp-content/themes/twentytwenty/templates/database-apis<br>";
    echo "composer install</div>";
}

// ==========================================
// CHECK 4: .env File
// ==========================================
echo "<h2>4. Environment Configuration</h2>";
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $success[] = "✅ .env file exists";
    echo "<div class='success'>✅ .env file exists</div>";
    
    // Try to load it
    if (file_exists($vendorPath)) {
        require_once $vendorPath;
        try {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->safeLoad();
            
            $envVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
            foreach ($envVars as $var) {
                if (isset($_ENV[$var]) && !empty($_ENV[$var])) {
                    echo "<div class='success'>✅ <code>$var</code> is set</div>";
                } else {
                    $warnings[] = "⚠️ $var not set in .env";
                    echo "<div class='warning'>⚠️ <code>$var</code> not set in .env</div>";
                }
            }
        } catch (Exception $e) {
            $warnings[] = "⚠️ Could not load .env: " . $e->getMessage();
            echo "<div class='warning'>⚠️ Could not load .env: " . $e->getMessage() . "</div>";
        }
    }
} else {
    $issues[] = "❌ .env file missing";
    echo "<div class='error'>❌ .env file not found</div>";
    echo "<div class='info'>💡 <strong>To fix:</strong><br>";
    echo "cd /var/www/html/wp-content/themes/twentytwenty/templates/database-apis<br>";
    echo "cp env.example .env<br>";
    echo "nano .env (edit with your database credentials)</div>";
}

// ==========================================
// CHECK 5: Database Connection
// ==========================================
echo "<h2>5. Database Connection</h2>";
if (file_exists($envPath) && file_exists($vendorPath)) {
    try {
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=utf8",
            $_ENV['DB_HOST'] ?? 'localhost',
            $_ENV['DB_PORT'] ?? '3306',
            $_ENV['DB_NAME'] ?? ''
        );
        
        $pdo = new PDO(
            $dsn,
            $_ENV['DB_USER'] ?? '',
            $_ENV['DB_PASS'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        
        $result = $pdo->query('SELECT 1')->fetch();
        $success[] = "✅ Database connection successful";
        echo "<div class='success'>✅ Database connection successful</div>";
        echo "<div class='success'>✅ Connected to: <code>{$_ENV['DB_NAME']}</code> on <code>{$_ENV['DB_HOST']}</code></div>";
        
    } catch (PDOException $e) {
        $issues[] = "❌ Database connection failed: " . $e->getMessage();
        echo "<div class='error'>❌ Database connection failed</div>";
        echo "<div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "<div class='info'>💡 Check your database credentials in .env</div>";
    }
} else {
    $warnings[] = "⚠️ Skipping database test (prerequisites not met)";
    echo "<div class='warning'>⚠️ Skipping database test (install Composer and create .env first)</div>";
}

// ==========================================
// CHECK 6: .htaccess
// ==========================================
echo "<h2>6. URL Rewriting (.htaccess)</h2>";
$htaccessPath = __DIR__ . '/.htaccess';
if (file_exists($htaccessPath)) {
    $success[] = "✅ .htaccess file exists";
    echo "<div class='success'>✅ .htaccess file exists</div>";
    
    $content = file_get_contents($htaccessPath);
    if (strpos($content, 'RewriteEngine On') !== false) {
        echo "<div class='success'>✅ RewriteEngine is enabled</div>";
    } else {
        $warnings[] = "⚠️ RewriteEngine not found in .htaccess";
        echo "<div class='warning'>⚠️ RewriteEngine not found in .htaccess</div>";
    }
} else {
    $issues[] = "❌ .htaccess file missing";
    echo "<div class='error'>❌ .htaccess file missing</div>";
}

// Check if mod_rewrite is loaded (Apache only)
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "<div class='success'>✅ Apache mod_rewrite is enabled</div>";
    } else {
        $issues[] = "❌ Apache mod_rewrite is not enabled";
        echo "<div class='error'>❌ Apache mod_rewrite is not enabled</div>";
        echo "<div class='info'>💡 <strong>To fix:</strong><br>";
        echo "sudo a2enmod rewrite<br>";
        echo "sudo systemctl restart apache2</div>";
    }
} else {
    echo "<div class='warning'>⚠️ Cannot check if mod_rewrite is enabled (not running as Apache module or function not available)</div>";
}

// ==========================================
// CHECK 7: File Permissions
// ==========================================
echo "<h2>7. File Permissions</h2>";
$publicDir = __DIR__;
$parentDir = dirname(__DIR__);

if (is_writable($publicDir)) {
    echo "<div class='success'>✅ <code>public/</code> directory is writable</div>";
} else {
    $warnings[] = "⚠️ public/ directory is not writable";
    echo "<div class='warning'>⚠️ <code>public/</code> directory is not writable</div>";
}

if (is_readable($parentDir)) {
    echo "<div class='success'>✅ Parent directory is readable</div>";
} else {
    $issues[] = "❌ Parent directory is not readable";
    echo "<div class='error'>❌ Parent directory is not readable</div>";
}

// ==========================================
// CHECK 8: Source Files
// ==========================================
echo "<h2>8. Source Files</h2>";
$requiredFiles = [
    'index.php' => __DIR__ . '/index.php',
    'routes/api.php' => dirname(__DIR__) . '/routes/api.php',
    'src/Services/CustomerService.php' => dirname(__DIR__) . '/src/Services/CustomerService.php',
    'src/DAL/CustomerDAL.php' => dirname(__DIR__) . '/src/DAL/CustomerDAL.php',
    'src/DAL/BaseDAL.php' => dirname(__DIR__) . '/src/DAL/BaseDAL.php',
];

foreach ($requiredFiles as $name => $path) {
    if (file_exists($path)) {
        echo "<div class='success'>✅ <code>$name</code> exists</div>";
    } else {
        $issues[] = "❌ Missing file: $name";
        echo "<div class='error'>❌ <code>$name</code> missing</div>";
    }
}

// ==========================================
// SUMMARY
// ==========================================
echo "<h2>📊 Summary</h2>";

$totalChecks = count($success) + count($warnings) + count($issues);
echo "<div class='summary'>";
echo "✅ " . count($success) . " checks passed<br>";
if (count($warnings) > 0) {
    echo "⚠️ " . count($warnings) . " warnings<br>";
}
if (count($issues) > 0) {
    echo "❌ " . count($issues) . " issues found<br>";
}
echo "</div>";

// ==========================================
// NEXT STEPS
// ==========================================
if (count($issues) > 0) {
    echo "<h2>🔧 Issues to Fix</h2>";
    echo "<div class='error'>";
    foreach ($issues as $issue) {
        echo $issue . "<br>";
    }
    echo "</div>";
    
    echo "<h2>📋 Fix Commands</h2>";
    echo "<div class='info'>";
    echo "<code style='display:block; white-space:pre-wrap;'>";
    echo "# Navigate to project directory\n";
    echo "cd /var/www/html/wp-content/themes/twentytwenty/templates/database-apis\n\n";
    
    if (strpos(implode('', $issues), 'Composer') !== false) {
        echo "# Install Composer dependencies\n";
        echo "composer install\n\n";
    }
    
    if (strpos(implode('', $issues), '.env') !== false) {
        echo "# Create .env file\n";
        echo "cp env.example .env\n";
        echo "nano .env  # Edit with your credentials\n\n";
    }
    
    if (strpos(implode('', $issues), 'mod_rewrite') !== false) {
        echo "# Enable mod_rewrite\n";
        echo "sudo a2enmod rewrite\n";
        echo "sudo systemctl restart apache2\n\n";
    }
    
    echo "# Set proper permissions\n";
    echo "sudo chown -R www-data:www-data .\n";
    echo "sudo find . -type f -exec chmod 644 {} \\;\n";
    echo "sudo find . -type d -exec chmod 755 {} \\;\n";
    echo "</code>";
    echo "</div>";
} else if (count($warnings) > 0) {
    echo "<h2>⚠️ Warnings</h2>";
    echo "<div class='warning'>";
    foreach ($warnings as $warning) {
        echo $warning . "<br>";
    }
    echo "</div>";
    echo "<p>The API should work, but there are some non-critical issues.</p>";
} else {
    echo "<div class='success' style='font-size: 20px; text-align: center; padding: 30px;'>";
    echo "🎉 <strong>All checks passed!</strong><br><br>";
    echo "Your API should be working now.<br><br>";
    echo "<a href='v1/health' style='background:#4CAF50;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Test Health Endpoint</a>";
    echo "</div>";
}

echo "<hr style='margin:40px 0;'>";
echo "<h2>🧪 Test Endpoints</h2>";
echo "<div class='info'>";
echo "<strong>Health Check:</strong><br>";
echo "<a href='v1/health' target='_blank'>v1/health</a><br><br>";

echo "<strong>API Info:</strong><br>";
echo "<a href='v1/' target='_blank'>v1/</a><br><br>";

echo "<strong>Payments Endpoint:</strong><br>";
echo "<a href='v1/customers/order/58747/payments' target='_blank'>v1/customers/order/58747/payments</a>";
echo "</div>";

echo "<hr style='margin:40px 0;'>";
echo "<p style='color:#888;text-align:center;'>Diagnostic script completed at " . date('Y-m-d H:i:s') . "</p>";

echo "</body></html>";
?>

