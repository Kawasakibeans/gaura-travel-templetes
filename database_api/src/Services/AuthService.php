<?php
/**
 * Auth Service - Business Logic Layer for Authentication
 */

namespace App\Services;

use App\DAL\AuthDAL;
use Exception;

class AuthService
{
    private $authDAL;
    
    public function __construct()
    {
        $this->authDAL = new AuthDAL();
    }
    
    /**
     * Authenticate user
     */
    public function login($username, $password)
    {
        if (empty($username)) {
            throw new Exception('Username or email is required', 400);
        }
        
        if (empty($password)) {
            throw new Exception('Password is required', 400);
        }
        
        // Get user by username or email
        $user = $this->authDAL->getUserByLogin($username);
        
        if (!$user) {
            throw new Exception('Invalid username or password', 401);
        }
        
        // Verify password
        // WordPress uses phpass, but we can try to verify using WordPress functions if available
        $passwordValid = false;
        
        if (function_exists('wp_check_password')) {
            // Use WordPress password check if available
            $passwordValid = wp_check_password($password, $user['user_pass'], $user['ID']);
        } else {
            // Fallback: try to verify using PHP's password_verify
            // Note: This may not work for WordPress phpass hashes
            $passwordValid = password_verify($password, $user['user_pass']);
            
            // If password_verify fails, try WordPress hash verification
            if (!$passwordValid) {
                // WordPress uses phpass which is different from bcrypt
                // We need to implement phpass verification or use WordPress functions
                // For now, we'll return an error suggesting to use WordPress functions
                throw new Exception('Password verification requires WordPress functions. Please ensure WordPress is loaded.', 500);
            }
        }
        
        if (!$passwordValid) {
            throw new Exception('Invalid username or password', 401);
        }
        
        // Get user roles
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $capabilities = $this->authDAL->getUserMeta($user['ID'], $tablePrefix . 'capabilities');
        $roles = [];
        
        if (is_array($capabilities)) {
            $roles = array_keys($capabilities);
        }
        
        // Return user data (without password hash)
        return [
            'user_id' => $user['ID'],
            'username' => $user['user_login'],
            'email' => $user['user_email'],
            'display_name' => $user['display_name'],
            'nicename' => $user['user_nicename'],
            'roles' => $roles,
            'redirect_url' => '/dashboard' // Default redirect URL
        ];
    }
}

