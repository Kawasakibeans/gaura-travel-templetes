<?php
namespace App\Services;

use App\DAL\GChatAccountDAL;
use Exception;

class GChatAccountService
{
    private GChatAccountDAL $dal;

    public function __construct()
    {
        $this->dal = new GChatAccountDAL();
    }

    public function createAccount(array $payload): array
    {
        $email = isset($payload['email']) ? strtolower(trim((string)$payload['email'])) : '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Valid email is required', 400);
        }

        $fullName = isset($payload['full_name']) ? trim((string)$payload['full_name']) : '';
        if ($fullName === '') {
            throw new Exception('Full name is required', 400);
        }

        $createdBy = isset($payload['created_by']) && trim((string)$payload['created_by']) !== ''
            ? trim((string)$payload['created_by'])
            : 'system';

        $id = $this->dal->createAccount($email, $fullName, $createdBy);

        return [
            'id' => $id,
            'email' => $email,
            'full_name' => $fullName,
            'created_by' => $createdBy,
        ];
    }

    public function listAccounts(array $filters = []): array
    {
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 200;
        if ($limit <= 0 || $limit > 500) {
            $limit = 200;
        }

        return $this->dal->listAccounts($limit);
    }
}


