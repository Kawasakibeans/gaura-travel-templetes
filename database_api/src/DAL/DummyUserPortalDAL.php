<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class DummyUserPortalDAL extends BaseDAL
{
    /**
     * Get user by email
     */
    public function getUserByEmail(string $email): ?array
    {
        $sql = "SELECT * FROM wpk4_backend_user_portal_login WHERE emailid = :email LIMIT 1";
        $result = $this->queryOne($sql, [':email' => $email]);
        return $result ?: null;
    }
    
    /**
     * Get user by ID
     */
    public function getUserById(int $userId): ?array
    {
        $sql = "SELECT * FROM wpk4_backend_user_portal_login WHERE auto_id = :user_id LIMIT 1";
        $result = $this->queryOne($sql, [':user_id' => $userId]);
        return $result ?: null;
    }
    
    /**
     * Authenticate user
     */
    public function authenticateUser(string $email, string $password): ?array
    {
        $sql = "SELECT * FROM wpk4_backend_user_portal_login WHERE emailid = :email AND password = :password LIMIT 1";
        $result = $this->queryOne($sql, [
            ':email' => $email,
            ':password' => $password
        ]);
        return $result ?: null;
    }
    
    /**
     * Register user
     */
    public function registerUser(string $fullname, string $email, string $password, string $status, string $lastLogin): int
    {
        $sql = "
            INSERT INTO wpk4_backend_user_portal_login 
            (fullname, password, emailid, status, lastlogin)
            VALUES (:fullname, :password, :emailid, :status, :lastlogin)
        ";
        
        $this->execute($sql, [
            ':fullname' => $fullname,
            ':password' => $password,
            ':emailid' => $email,
            ':status' => $status,
            ':lastlogin' => $lastLogin
        ]);
        
        return $this->lastInsertId();
    }
    
    /**
     * Update user last login
     */
    public function updateLastLogin(int $userId, string $lastLogin): bool
    {
        $sql = "UPDATE wpk4_backend_user_portal_login SET lastlogin = :lastlogin WHERE auto_id = :user_id";
        return $this->execute($sql, [
            ':lastlogin' => $lastLogin,
            ':user_id' => $userId
        ]);
    }
    
    /**
     * Update user password
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        $sql = "UPDATE wpk4_backend_user_portal_login SET password = :password WHERE auto_id = :user_id";
        return $this->execute($sql, [
            ':password' => $newPassword,
            ':user_id' => $userId
        ]);
    }
    
    /**
     * Get user requests
     */
    public function getUserRequests(int $userId, ?string $caseType = null, int $limit = 10): array
    {
        $sql = "
            SELECT * FROM wpk4_backend_user_portal_requests 
            WHERE user_id = :user_id
        ";
        
        $params = [':user_id' => $userId];
        
        if ($caseType) {
            $sql .= " AND case_type = :case_type";
            $params[':case_type'] = $caseType;
        }
        
        $sql .= " ORDER BY case_id DESC LIMIT " . (int)$limit;
        
        return $this->query($sql, $params);
    }
    
    /**
     * Get request by case ID
     */
    public function getRequestByCaseId(string $caseId): ?array
    {
        $sql = "SELECT * FROM wpk4_backend_user_portal_requests WHERE case_id = :case_id LIMIT 1";
        $result = $this->queryOne($sql, [':case_id' => $caseId]);
        return $result ?: null;
    }
    
    /**
     * Get next case ID
     */
    public function getNextCaseId(): string
    {
        $sql = "SELECT case_id FROM wpk4_backend_user_portal_requests ORDER BY case_id DESC LIMIT 1";
        $result = $this->queryOne($sql);
        
        if (!$result || empty($result['case_id'])) {
            return 'CASE001';
        }
        
        $lastCaseId = $result['case_id'];
        if (preg_match('/CASE(\d+)/', $lastCaseId, $matches)) {
            $nextNum = (int)$matches[1] + 1;
            return 'CASE' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
        }
        
        return 'CASE001';
    }
    
    /**
     * Create request
     */
    public function createRequest(array $data): string
    {
        $caseId = $this->getNextCaseId();
        
        $sql = "
            INSERT INTO wpk4_backend_user_portal_requests 
            (case_id, case_type, reservation_ref, last_response_by, last_response_on, is_seen_by_gt, user_id, status, case_date, priority)
            VALUES (:case_id, :case_type, :reservation_ref, :last_response_by, :last_response_on, :is_seen_by_gt, :user_id, :status, :case_date, :priority)
        ";
        
        $this->execute($sql, [
            ':case_id' => $caseId,
            ':case_type' => $data['case_type'] ?? '',
            ':reservation_ref' => $data['reservation_ref'] ?? '',
            ':last_response_by' => $data['last_response_by'] ?? $data['user_id'] ?? '',
            ':last_response_on' => $data['last_response_on'] ?? date('Y-m-d H:i:s'),
            ':is_seen_by_gt' => $data['is_seen_by_gt'] ?? 0,
            ':user_id' => $data['user_id'] ?? 0,
            ':status' => $data['status'] ?? 'open',
            ':case_date' => $data['case_date'] ?? date('Y-m-d H:i:s'),
            ':priority' => $data['priority'] ?? 'P4'
        ]);
        
        return $caseId;
    }
    
    /**
     * Insert request meta
     */
    public function insertRequestMeta(string $caseId, string $metaKey, string $metaValue): bool
    {
        $sql = "
            INSERT INTO wpk4_backend_user_portal_request_meta 
            (case_id, meta_key, meta_value)
            VALUES (:case_id, :meta_key, :meta_value)
            ON DUPLICATE KEY UPDATE meta_value = :meta_value
        ";
        
        return $this->execute($sql, [
            ':case_id' => $caseId,
            ':meta_key' => $metaKey,
            ':meta_value' => $metaValue
        ]);
    }
}

