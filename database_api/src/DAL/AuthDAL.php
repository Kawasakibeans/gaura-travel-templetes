<?php
/**
 * Auth DAL - Data Access Layer for Authentication
 */

namespace App\DAL;

use Exception;

class AuthDAL extends BaseDAL
{
    /**
     * Get user by username or email
     */
    public function getUserByLogin($login)
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $usersTable = $tablePrefix . 'users';
        
        // Use two different parameter names since PDO doesn't support same parameter twice
        $sql = "SELECT ID, user_login, user_email, user_pass, user_nicename, display_name 
                FROM {$usersTable} 
                WHERE user_login = :login1 OR user_email = :login2
                LIMIT 1";
        
        $params = [
            ':login1' => $login,
            ':login2' => $login
        ];
        $results = $this->query($sql, $params);
        
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Get user meta value
     */
    public function getUserMeta($userId, $metaKey)
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $usermetaTable = $tablePrefix . 'usermeta';
        
        $sql = "SELECT meta_value 
                FROM {$usermetaTable} 
                WHERE user_id = :user_id 
                AND meta_key = :meta_key
                LIMIT 1";
        
        $params = [
            ':user_id' => $userId,
            ':meta_key' => $metaKey
        ];
        
        $results = $this->query($sql, $params);
        
        if (empty($results)) {
            return null;
        }
        
        $value = $results[0]['meta_value'];
        
        // Try to unserialize if it's serialized
        $unserialized = @unserialize($value);
        return $unserialized !== false ? $unserialized : $value;
    }
    
    /**
     * Verify WordPress password hash
     * WordPress uses phpass password hashing
     */
    public function verifyPassword($password, $hash)
    {
        // WordPress uses phpass library for password hashing
        // We need to use WordPress's password verification function
        // For API, we can use PHP's password_verify if hash is bcrypt,
        // or implement phpass verification
        
        // Check if it's a WordPress hash (starts with $P$ or $2a$)
        if (strpos($hash, '$P$') === 0) {
            // WordPress phpass hash - need to use WordPress function
            // For now, we'll try to use WordPress's password check if available
            if (function_exists('wp_check_password')) {
                return wp_check_password($password, $hash);
            }
            
            // Fallback: try to verify using PHP's password_verify if it's bcrypt
            // Note: WordPress phpass is different from bcrypt, so this may not work
            return password_verify($password, $hash);
        } else {
            // Try standard password_verify
            return password_verify($password, $hash);
        }
    }
    
    /**
     * Get user capabilities/roles
     */
    public function getUserRoles($userId)
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $capabilities = $this->getUserMeta($userId, $tablePrefix . 'capabilities');
        
        if (empty($capabilities)) {
            return [];
        }
        
        if (is_array($capabilities)) {
            return array_keys($capabilities);
        }
        
        return [];
    }
}

