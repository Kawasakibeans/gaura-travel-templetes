<?php
/**
 * Simple Test Script - Check Basic Setup
 */
header('Content-Type: application/json');

$status = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'tests' => []
];

// Test 1: PHP is working
$status['tests']['php_working'] = true;

// Test 2: Check vendor/autoload.php
$vendorPath = __DIR__ . '/../vendor/autoload.php';
$status['tests']['vendor_exists'] = file_exists($vendorPath);
$status['vendor_path'] = $vendorPath;

// Test 3: Try to load autoloader
if ($status['tests']['vendor_exists']) {
    try {
        require_once $vendorPath;
        $status['tests']['autoload_loaded'] = true;
    } catch (Exception $e) {
        $status['tests']['autoload_loaded'] = false;
        $status['tests']['autoload_error'] = $e->getMessage();
    }
} else {
    $status['tests']['autoload_loaded'] = false;
    $status['tests']['autoload_error'] = 'vendor/autoload.php not found - Run: composer install';
}

// Test 4: Check .env file
$envPath = __DIR__ . '/../.env';
$status['tests']['env_exists'] = file_exists($envPath);
$status['env_path'] = $envPath;

// Test 5: Try to load .env
if ($status['tests']['env_exists'] && $status['tests']['autoload_loaded']) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->safeLoad();
        $status['tests']['env_loaded'] = true;
        $status['tests']['env_vars'] = [
            'DB_HOST' => isset($_ENV['DB_HOST']) ? 'set' : 'missing',
            'DB_NAME' => isset($_ENV['DB_NAME']) ? 'set' : 'missing',
            'DB_USER' => isset($_ENV['DB_USER']) ? 'set' : 'missing',
            'DB_PASS' => isset($_ENV['DB_PASS']) ? 'not shown' : 'missing',
        ];
    } catch (Exception $e) {
        $status['tests']['env_loaded'] = false;
        $status['tests']['env_error'] = $e->getMessage();
    }
}

// Test 6: Check if Slim is available
if ($status['tests']['autoload_loaded']) {
    $status['tests']['slim_exists'] = class_exists('Slim\Factory\AppFactory');
}

// Test 7: Check PHP extensions
$status['tests']['extensions'] = [
    'PDO' => extension_loaded('pdo'),
    'PDO_MySQL' => extension_loaded('pdo_mysql'),
    'mbstring' => extension_loaded('mbstring'),
    'json' => extension_loaded('json'),
];

// Test 8: Check source files
$status['tests']['source_files'] = [
    'routes/api.php' => file_exists(__DIR__ . '/../routes/api.php'),
    'src/Services/CustomerService.php' => file_exists(__DIR__ . '/../src/Services/CustomerService.php'),
    'src/DAL/CustomerDAL.php' => file_exists(__DIR__ . '/../src/DAL/CustomerDAL.php'),
    'src/DAL/BaseDAL.php' => file_exists(__DIR__ . '/../src/DAL/BaseDAL.php'),
];

// Test 9: Database connection (if .env loaded)
if ($status['tests']['env_loaded']) {
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
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $pdo->query('SELECT 1');
        $status['tests']['database_connection'] = true;
    } catch (PDOException $e) {
        $status['tests']['database_connection'] = false;
        $status['tests']['database_error'] = $e->getMessage();
    }
}

// Determine overall status
$allPassed = true;
$criticalIssues = [];

if (!$status['tests']['vendor_exists']) {
    $allPassed = false;
    $criticalIssues[] = 'Composer dependencies not installed. Run: composer install';
}

if (!$status['tests']['env_exists']) {
    $allPassed = false;
    $criticalIssues[] = '.env file missing. Run: cp env.example .env';
}

foreach ($status['tests']['extensions'] as $ext => $loaded) {
    if (!$loaded) {
        $allPassed = false;
        $criticalIssues[] = "PHP extension '$ext' not loaded";
    }
}

$status['overall'] = $allPassed ? 'READY' : 'ISSUES_FOUND';
$status['critical_issues'] = $criticalIssues;

echo json_encode($status, JSON_PRETTY_PRINT);
?>

