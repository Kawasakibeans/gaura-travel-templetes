<?php
/**
 * Team Links Data Access Layer
 * Handles database operations for team links and IP address checking
 */

namespace App\DAL;

use Exception;
use PDOException;

class TeamLinksDAL extends BaseDAL
{
    /**
     * Check if IP address is authorized
     */
    public function checkIpAddress($ipAddress)
    {
        try {
            $query = "
                SELECT * FROM wpk4_backend_ip_address_checkup 
                WHERE ip_address = :ip_address
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['ip_address' => $ipAddress]);
        } catch (PDOException $e) {
            error_log("TeamLinksDAL::checkIpAddress error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
}

