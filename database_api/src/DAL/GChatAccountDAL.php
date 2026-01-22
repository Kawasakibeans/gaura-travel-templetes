<?php
namespace App\DAL;

use PDO;

class GChatAccountDAL extends BaseDAL
{
    /**
     * Insert a new GChat account audit entry.
     */
    public function createAccount(string $email, string $fullName, string $createdBy): int
    {
        $sql = "
            INSERT INTO wpk4_gchat_account_management
                (email_id, full_name, created_at, created_by)
            VALUES
                (:email, :full_name, NOW(), :created_by)
        ";

        $this->execute($sql, [
            ':email' => $email,
            ':full_name' => $fullName,
            ':created_by' => $createdBy,
        ]);

        return (int)$this->lastInsertId();
    }

    /**
     * List recently created GChat accounts.
     */
    public function listAccounts(int $limit = 200): array
    {
        $sql = "
            SELECT
                id,
                email_id,
                full_name,
                created_at,
                created_by
            FROM wpk4_gchat_account_management
            ORDER BY created_at DESC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}


