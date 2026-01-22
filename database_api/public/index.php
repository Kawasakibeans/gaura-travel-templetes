<?php
/**
 * Database API Layer - Slim Framework 4
 * Entry Point - Optimized for 200+ tables
 */

// Enable all error reporting and logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error.log');

// CRITICAL: Increase execution time to handle large routes file
// TODO: Split routes file to avoid this workaround
ini_set('max_execution_time', 300); // 5 minutes
set_time_limit(300);

// Custom error handler to log everything
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $log = sprintf("[%s] Error %d: %s in %s on line %d\n", 
        date('Y-m-d H:i:s'), $errno, $errstr, $errfile, $errline);
    error_log($log, 3, __DIR__ . '/../error.log');
    echo "<pre>$log</pre>";
});

// Catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $log = sprintf("[%s] FATAL: %s in %s on line %d\n",
            date('Y-m-d H:i:s'), $error['message'], $error['file'], $error['line']);
        error_log($log, 3, __DIR__ . '/../error.log');
        echo "<pre>$log</pre>";
    }
});

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;

// Check if in production mode (reduce logging)
$appEnv = $_ENV['APP_ENV'] ?? 'development';
$isProduction = ($appEnv === 'production');
$debugMode = filter_var($_ENV['APP_DEBUG'] ?? !$isProduction, FILTER_VALIDATE_BOOLEAN);

try {
    if ($debugMode) {
        error_log("[" . date('Y-m-d H:i:s') . "] === API Request Started ===\n", 3, __DIR__ . '/../error.log');
    }

    require __DIR__ . '/../vendor/autoload.php';

    // Load environment variables
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();

    // Create DI container
    $container = new Container();
    AppFactory::setContainer($container);

    // Database connection with timeout settings
    $container->set('db', function () use ($debugMode) {
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=utf8",
            $_ENV['DB_HOST'] ?? 'localhost',
            $_ENV['DB_PORT'] ?? '3306',
            $_ENV['DB_NAME'] ?? 'gt1ybwhome_gt1'
        );
        
        $pdo = new PDO($dsn, 
            $_ENV['DB_USER'] ?? 'gt1ybwhome_gtuser',
            $_ENV['DB_PASS'] ?? '3Ythyfghjr',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 30, // Connection timeout in seconds
            ]
        );
        
        // Set MySQL query timeout (30 seconds)
        $pdo->exec("SET SESSION wait_timeout = 30");
        $pdo->exec("SET SESSION interactive_timeout = 30");
        
        return $pdo;
    });

    // Create Slim app
    $app = AppFactory::create();
    $app->setBasePath('/wp-content/themes/twentytwenty/templates/database_api/public');
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();
    $app->addErrorMiddleware($debugMode, true, true);

    // Error middleware
    $errorMiddleware = $app->addErrorMiddleware($debugMode, true, true);

    // CORS middleware
    $app->add(function (Request $request, $handler) {
        $response = $handler->handle($request);
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-API-Key')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    });

    // OPTIONS handler
    $app->options('/{routes:.+}', fn($req, $res) => $res);

    // Load routes - THIS IS THE BOTTLENECK (19,156 lines)
    // TODO: Split into feature-based route files
    require __DIR__ . '/../routes/api.php';

    $app->run();
    
} catch (Exception $e) {
    $log = sprintf("[%s] EXCEPTION: %s in %s on line %d\nStack trace:\n%s\n",
        date('Y-m-d H:i:s'), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
    error_log($log, 3, __DIR__ . '/../error.log');
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

