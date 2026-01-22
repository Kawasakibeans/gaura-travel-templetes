<?php
/**
 * Gaura Grand Prix Data Access Layer
 * Handles database operations for Gaura Grand Prix championship data
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class GauraGrandPrixDAL extends BaseDAL
{
    /**
     * Get GTIB data by team
     */
    public function getGTIBData(string $startDate, string $endDate): array
    {
        try {
            $sql = "
                SELECT 
                    a.team_name, 
                    SUM(a.gtib_count) as gtib 
                FROM wpk4_backend_agent_inbound_call a
                LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active'
                WHERE a.call_date BETWEEN :start_date AND :end_date 
                AND a.team_name NOT IN ('Sales Manager', 'Trainer', 'Others')
                GROUP BY a.team_name 
                ORDER BY gtib DESC
            ";
            
            return $this->query($sql, [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ]);
        } catch (\Exception $e) {
            error_log("GauraGrandPrixDAL::getGTIBData error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get FCS data by team
     */
    public function getFCSData(string $startDate, string $endDate): array
    {
        try {
            $sql = "
                SELECT 
                    a.team_name, 
                    ROUND((SUM(a.new_sale_made_count) / SUM(a.gtib_count) * 100), 2) as fcs 
                FROM wpk4_backend_agent_inbound_call a
                LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active'
                WHERE a.call_date BETWEEN :start_date AND :end_date 
                AND a.team_name NOT IN ('Sales Manager', 'Trainer', 'Others')
                GROUP BY a.team_name 
                ORDER BY fcs DESC
            ";
            
            return $this->query($sql, [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ]);
        } catch (\Exception $e) {
            error_log("GauraGrandPrixDAL::getFCSData error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get conversion data by team
     */
    public function getConversionData(string $startDate, string $endDate): array
    {
        try {
            $sql = "
                SELECT
                    a.team_name,
                    SUM(b.pax) as total_pax,
                    SUM(a.gtib_count) as total_gtib,
                    ROUND(SUM(b.pax) / SUM(a.gtib_count) * 100, 2) AS conversion
                FROM wpk4_backend_agent_inbound_call a
                LEFT JOIN wpk4_backend_agent_booking b ON a.tsr = b.tsr AND a.call_date = b.order_date
                LEFT JOIN wpk4_backend_agent_codes c ON a.tsr = c.tsr AND c.status = 'active'
                WHERE a.call_date BETWEEN :start_date AND :end_date
                AND a.team_name NOT IN ('Sales Manager', 'Trainer', 'Others')
                GROUP BY a.team_name
                ORDER BY conversion DESC
            ";
            
            return $this->query($sql, [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ]);
        } catch (\Exception $e) {
            error_log("GauraGrandPrixDAL::getConversionData error: " . $e->getMessage());
            return [];
        }
    }
}

