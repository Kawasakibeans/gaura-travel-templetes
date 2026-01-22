<?php
/**
 * Authentication Service - Business Logic Layer
 * Handles customer authentication and account management
 */

namespace App\Services;

use App\DAL\AuthenticationDAL;
use Exception;

class AuthenticationService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AuthenticationDAL();
    }

    /**
     * Get user secret by Firebase ID
     */
    public function getUserSecret($firebaseId)
    {
        if (empty($firebaseId)) {
            throw new Exception('Firebase ID is required', 400);
        }

        $account = $this->dal->getAccountByFirebaseId($firebaseId);

        if (!$account) {
            return [
                'firebase_id' => $firebaseId,
                'secret' => null,
                'exists' => false
            ];
        }

        return [
            'firebase_id' => $firebaseId,
            'secret' => $account['secret'],
            'exists' => true
        ];
    }

    /**
     * Save user secret
     */
    public function saveUserSecret($firebaseId, $secret)
    {
        if (empty($firebaseId)) {
            throw new Exception('Firebase ID is required', 400);
        }

        if (empty($secret)) {
            throw new Exception('Secret is required', 400);
        }

        // Check if already exists
        $existing = $this->dal->getAccountByFirebaseId($firebaseId);

        if ($existing) {
            throw new Exception('Firebase account already exists', 400);
        }

        $accountId = $this->dal->createLoginAccount($firebaseId, $secret);

        return [
            'account_id' => $accountId,
            'firebase_id' => $firebaseId,
            'message' => 'Account created successfully'
        ];
    }

    /**
     * Get customer account by email
     */
    public function getCustomerAccountByEmail($email)
    {
        if (empty($email)) {
            throw new Exception('Email is required', 400);
        }

        $account = $this->dal->getCustomerAccountByEmail($email);

        if (!$account) {
            return [
                'email' => $email,
                'account' => null,
                'exists' => false
            ];
        }

        return [
            'email' => $email,
            'account' => $account,
            'exists' => true
        ];
    }

    /**
     * Create customer account
     */
    public function createCustomerAccount($data)
    {
        // Validate required fields
        $requiredFields = ['email'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        // Check if email already exists
        $existing = $this->dal->getCustomerAccountByEmail($data['email']);
        if ($existing) {
            throw new Exception('Email already registered', 400);
        }

        $accountId = $this->dal->createCustomerAccount($data);

        return [
            'account_id' => $accountId,
            'email' => $data['email'],
            'message' => 'Customer account created successfully'
        ];
    }
}

