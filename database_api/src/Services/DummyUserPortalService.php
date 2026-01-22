<?php

namespace App\Services;

use App\DAL\DummyUserPortalDAL;

class DummyUserPortalService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new DummyUserPortalDAL();
    }

    /**
     * Login user
     */
    public function login(array $params): array
    {
        $email = $params['email'] ?? $params['username'] ?? null;
        $password = $params['password'] ?? null;
        
        if (!$email || !$password) {
            throw new \Exception('Email and password are required');
        }
        
        $encryptedPassword = md5($password);
        $user = $this->dal->authenticateUser($email, $encryptedPassword);
        
        if (!$user) {
            throw new \Exception('Invalid credentials');
        }
        
        // Update last login
        $this->dal->updateLastLogin($user['auto_id'], date('Y-m-d H:i:s'));
        
        // Remove password from response
        unset($user['password']);
        
        return $user;
    }
    
    /**
     * Register user
     */
    public function register(array $params): array
    {
        $fullname = $params['fullname'] ?? null;
        $email = $params['email'] ?? $params['emailid'] ?? null;
        $password = $params['password'] ?? null;
        $securityCode = $params['security_code'] ?? $params['status'] ?? '';
        
        if (!$fullname || !$email || !$password) {
            throw new \Exception('Fullname, email, and password are required');
        }
        
        // Check if user exists
        $existing = $this->dal->getUserByEmail($email);
        if ($existing) {
            throw new \Exception('User already exists');
        }
        
        $encryptedPassword = md5($password);
        $userId = $this->dal->registerUser($fullname, $email, $encryptedPassword, $securityCode, date('Y-m-d H:i:s'));
        
        return [
            'user_id' => $userId,
            'email' => $email,
            'fullname' => $fullname
        ];
    }
    
    /**
     * Get user profile
     */
    public function getUserProfile(int $userId): array
    {
        $user = $this->dal->getUserById($userId);
        
        if (!$user) {
            throw new \Exception('User not found');
        }
        
        unset($user['password']);
        
        return $user;
    }
    
    /**
     * Update password
     */
    public function updatePassword(array $params): bool
    {
        $userId = $params['user_id'] ?? null;
        $currentPassword = $params['current_password'] ?? null;
        $newPassword = $params['new_password'] ?? null;
        
        if (!$userId || !$currentPassword || !$newPassword) {
            throw new \Exception('user_id, current_password, and new_password are required');
        }
        
        $user = $this->dal->getUserById($userId);
        if (!$user) {
            throw new \Exception('User not found');
        }
        
        $currentEncrypted = md5($currentPassword);
        if ($user['password'] !== $currentEncrypted) {
            throw new \Exception('Current password is incorrect');
        }
        
        $newEncrypted = md5($newPassword);
        return $this->dal->updatePassword($userId, $newEncrypted);
    }
    
    /**
     * Get user requests
     */
    public function getUserRequests(array $params): array
    {
        $userId = $params['user_id'] ?? null;
        if (!$userId) {
            throw new \Exception('user_id is required');
        }
        
        $caseType = $params['case_type'] ?? null;
        $limit = isset($params['limit']) && is_numeric($params['limit']) ? (int)$params['limit'] : 10;
        
        return $this->dal->getUserRequests($userId, $caseType, $limit);
    }
    
    /**
     * Get request details
     */
    public function getRequestDetails(string $caseId): array
    {
        $request = $this->dal->getRequestByCaseId($caseId);
        
        if (!$request) {
            throw new \Exception('Request not found');
        }
        
        return $request;
    }
    
    /**
     * Create request
     */
    public function createRequest(array $params): array
    {
        $userId = $params['user_id'] ?? null;
        $caseType = $params['case_type'] ?? null;
        
        if (!$userId || !$caseType) {
            throw new \Exception('user_id and case_type are required');
        }
        
        $caseId = $this->dal->createRequest([
            'case_type' => $caseType,
            'reservation_ref' => $params['reservation_ref'] ?? '',
            'user_id' => $userId,
            'status' => 'open',
            'case_date' => date('Y-m-d H:i:s'),
            'last_response_on' => date('Y-m-d H:i:s'),
            'last_response_by' => $userId,
            'is_seen_by_gt' => 0,
            'priority' => $params['priority'] ?? 'P4'
        ]);
        
        // Insert meta data if provided
        if (isset($params['meta']) && is_array($params['meta'])) {
            foreach ($params['meta'] as $key => $value) {
                $this->dal->insertRequestMeta($caseId, $key, $value);
            }
        }
        
        return [
            'case_id' => $caseId,
            'status' => 'success'
        ];
    }
}

