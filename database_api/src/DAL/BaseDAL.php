<?php
/**
 * Base Data Access Layer
 * Provides common database operations for all DAL classes
 */

namespace App\DAL;

use PDO;
use Exception;

class BaseDAL
{
    protected $db;

    public function __construct()
    {
        $this->db = $this->getConnection();
    }

    /**
     * Get database connection
     */
    private function getConnection()
    {
        static $pdo = null;
        
        if ($pdo === null) {
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=utf8",
                $_ENV['DB_HOST'] ?? 'localhost',
                $_ENV['DB_PORT'] ?? '3306',
                $_ENV['DB_NAME'] ?? 'gt1ybwhome_gt1_gt'
            );
            
            $pdo = new PDO($dsn,
                $_ENV['DB_USER'] ?? 'gt1ybwhome_garu',
                $_ENV['DB_PASS'] ?? 'KJGpvBCym8',
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
        }
        
        return $pdo;
    }

    /**
     * Execute a query and return all results
     */
    protected function query($sql, $params = [])
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Database query error: " . $e->getMessage());
            
            // Check if it's a table not found error
            if (strpos($e->getMessage(), "doesn't exist") !== false || 
                strpos($e->getMessage(), "Base table or view not found") !== false) {
                // Extract table name from error message
                preg_match("/Table '[^']*\.([^']+)' doesn't exist/", $e->getMessage(), $matches);
                $tableName = $matches[1] ?? 'unknown';
                throw new Exception("missing table \"{$tableName}\"", 500);
            }
            
            throw new Exception("Database query failed " . $e->getMessage(), 500);
        }
    }

    /**
     * Execute a query and return single result
     */
    protected function queryOne($sql, $params = [])
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Database query error: " . $e->getMessage());
            
            // Check if it's a table not found error
            if (strpos($e->getMessage(), "doesn't exist") !== false || 
                strpos($e->getMessage(), "Base table or view not found") !== false) {
                // Extract table name from error message
                preg_match("/Table '[^']*\.([^']+)' doesn't exist/", $e->getMessage(), $matches);
                $tableName = $matches[1] ?? 'unknown';
                throw new Exception("missing table \"{$tableName}\"", 500);
            }
            
            throw new Exception("Database query failed " . $e->getMessage(), 500);
        }
    }

    /**
     * Execute a statement (INSERT, UPDATE, DELETE)
     */
    protected function execute($sql, $params = [])
    {
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (Exception $e) {
            error_log("Database execute error: " . $e->getMessage());
            
            // Check if it's a table not found error
            if (strpos($e->getMessage(), "doesn't exist") !== false || 
                strpos($e->getMessage(), "Base table or view not found") !== false) {
                // Extract table name from error message
                preg_match("/Table '[^']*\.([^']+)' doesn't exist/", $e->getMessage(), $matches);
                $tableName = $matches[1] ?? 'unknown';
                throw new Exception("missing table \"{$tableName}\"", 500);
            }
            
            throw new Exception("Database operation failed " . $e->getMessage(), 500);
        }
    }

    /**
     * Get last insert ID
     */
    protected function lastInsertId()
    {
        return $this->db->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit()
    {
        return $this->db->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback()
    {
        return $this->db->rollBack();
    }
}

