<?php
/**
 * Error Check - Try to load index.php and catch errors
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Attempting to Load Slim App...</h1>";
echo "<pre>";

try {
    echo "1. Checking vendor/autoload.php...\n";
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    
    if (!file_exists($autoloadPath)) {
        throw new Exception("❌ vendor/autoload.php not found at: $autoloadPath\n\nFIX: Run 'composer install'");
    }
    echo "✅ vendor/autoload.php exists\n\n";
    
    echo "2. Loading autoloader...\n";
    require $autoloadPath;
    echo "✅ Autoloader loaded\n\n";
    
    echo "3. Checking .env file...\n";
    $envPath = __DIR__ . '/../.env';
    if (!file_exists($envPath)) {
        throw new Exception("❌ .env file not found at: $envPath\n\nFIX: Run 'cp env.example .env' and edit it");
    }
    echo "✅ .env file exists\n\n";
    
    echo "4. Loading environment variables...\n";
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
    echo "✅ Environment loaded\n\n";
    
    echo "5. Checking Slim Framework...\n";
    if (!class_exists('Slim\Factory\AppFactory')) {
        throw new Exception("❌ Slim Framework not found\n\nFIX: Run 'composer install'");
    }
    echo "✅ Slim Framework available\n\n";
    
    echo "6. Creating Slim app...\n";
    use Slim\Factory\AppFactory;
    use DI\Container;
    
    $container = new Container();
    AppFactory::setContainer($container);
    echo "✅ Container created\n\n";
    
    echo "7. Testing database connection...\n";
    $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=utf8",
        $_ENV['DB_HOST'] ?? 'localhost',
        $_ENV['DB_PORT'] ?? '3306',
        $_ENV['DB_NAME'] ?? ''
    );
    
    $pdo = new PDO($dsn, 
        $_ENV['DB_USER'] ?? '',
        $_ENV['DB_PASS'] ?? '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $pdo->query('SELECT 1');
    echo "✅ Database connected successfully\n";
    echo "   Host: " . ($_ENV['DB_HOST'] ?? 'localhost') . "\n";
    echo "   Database: " . ($_ENV['DB_NAME'] ?? 'not set') . "\n\n";
    
    echo "8. Checking source files...\n";
    $files = [
        'routes/api.php',
        'src/Services/CustomerService.php',
        'src/DAL/CustomerDAL.php',
        'src/DAL/BaseDAL.php',
    ];
    
    foreach ($files as $file) {
        $path = __DIR__ . '/../' . $file;
        if (!file_exists($path)) {
            throw new Exception("❌ Missing file: $file");
        }
        echo "✅ $file exists\n";
    }
    
    echo "\n\n";
    echo "=".str_repeat("=", 60)."=\n";
    echo "🎉 ALL CHECKS PASSED!\n";
    echo "=".str_repeat("=", 60)."=\n\n";
    echo "Your Slim app should be working now.\n";
    echo "If you're still getting 500 errors, check Apache error logs:\n";
    echo "  tail -f /var/log/apache2/error.log\n\n";
    
    echo "Try accessing:\n";
    echo "  • v1/health\n";
    echo "  • v1/customers/order/58747/payments\n";
    
} catch (Exception $e) {
    echo "\n\n";
    echo "=".str_repeat("=", 60)."=\n";
    echo "❌ ERROR FOUND!\n";
    echo "=".str_repeat("=", 60)."=\n\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    if (strpos($e->getMessage(), 'vendor/autoload.php') !== false) {
        echo "TO FIX:\n";
        echo "1. SSH into your server\n";
        echo "2. cd /var/www/html/wp-content/themes/twentytwenty/templates/database-apis\n";
        echo "3. composer install\n";
    } elseif (strpos($e->getMessage(), '.env') !== false) {
        echo "TO FIX:\n";
        echo "1. SSH into your server\n";
        echo "2. cd /var/www/html/wp-content/themes/twentytwenty/templates/database-apis\n";
        echo "3. cp env.example .env\n";
        echo "4. nano .env (edit with your database credentials)\n";
    }
} catch (PDOException $e) {
    echo "\n\n";
    echo "=".str_repeat("=", 60)."=\n";
    echo "❌ DATABASE CONNECTION ERROR!\n";
    echo "=".str_repeat("=", 60)."=\n\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    echo "TO FIX:\n";
    echo "Check your database credentials in .env file\n";
}

echo "</pre>";
?>

