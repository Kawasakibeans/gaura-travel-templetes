<?php
/**
 * Letter Coaching Session Data Access Layer
 */

namespace App\DAL;

class LetterCoachingSessionDAL extends BaseDAL
{
    /**
     * List agents available in the performance review table
     */
    public function listAgents(): array
    {
        $sql = "SELECT name FROM wpk4_backend_employee_performance_reviews ORDER BY name";
        return $this->query($sql);
    }

    /**
     * Fetch a single performance review by agent code and date
     */
    public function getReview(string $agentCode, string $date): ?array
    {
        $sql = "
            SELECT *
            FROM wpk4_backend_employee_performance_reviews
            WHERE name LIKE ?
              AND DATE(updated_at) = ?
            LIMIT 1
        ";

        $result = $this->queryOne($sql, ['%|' . $agentCode, $date]);
        return $result ?: null;
    }
}

