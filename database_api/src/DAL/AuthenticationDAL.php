<?php
/**
 * Authentication Data Access Layer
 * Handles database operations for authentication
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class AuthenticationDAL extends BaseDAL
{
    /**
     * Get account by Firebase ID
     */
    public function getAccountByFirebaseId($firebaseId)
    {
        $query = "SELECT * FROM wpk4_backend_login_accounts WHERE firebase_id = ? LIMIT 1";
        return $this->queryOne($query, [$firebaseId]);
    }

    /**
     * Create login account
     */
    public function createLoginAccount($firebaseId, $secret)
    {
        $query = "INSERT INTO wpk4_backend_login_accounts (firebase_id, secret, created_at) 
                  VALUES (?, ?, NOW())";
        
        $this->execute($query, [$firebaseId, $secret]);
        return $this->lastInsertId();
    }

    /**
     * Get customer account by email
     */
    public function getCustomerAccountByEmail($email)
    {
        $query = "SELECT * FROM wpk4_customer_accounts WHERE email = ? ORDER BY auto_id ASC LIMIT 1";
        return $this->queryOne($query, [$email]);
    }

    /**
     * Create customer account
     */
    public function createCustomerAccount($data)
    {
        $query = "INSERT INTO wpk4_customer_accounts 
                  (email, firebase_uid, display_name, phone_number, created_at)
                  VALUES (?, ?, ?, ?, NOW())";
        
        $params = [
            $data['email'],
            $data['firebase_uid'] ?? null,
            $data['display_name'] ?? null,
            $data['phone_number'] ?? null
        ];

        $this->execute($query, $params);
        return $this->lastInsertId();
    }
}

