<?php
/**
 * Interim Performance Remark Data Access Layer
 * Handles all database operations for interim performance remarks
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class InterimPerformanceRemarkDAL extends BaseDAL
{
    /**
     * Get remark history for a TSR
     */
    public function getRemarkHistory(string $tsr): array
    {
        $sql = "
            SELECT 
                date_range_start, 
                date_range_end, 
                remark, 
                COALESCE(updated_at, created_at) AS last_updated
            FROM wpk4_backend_interim_performance_remark 
            WHERE tsr = :tsr 
            ORDER BY date_range_start DESC
        ";
        
        return $this->query($sql, [':tsr' => $tsr]);
    }

    /**
     * Check if a remark record exists
     */
    public function remarkExists(string $tsr, string $dateRangeStart, string $dateRangeEnd): bool
    {
        $sql = "
            SELECT COUNT(*) as count 
            FROM wpk4_backend_interim_performance_remark 
            WHERE tsr = :tsr 
            AND date_range_start = :date_range_start 
            AND date_range_end = :date_range_end
        ";
        
        $result = $this->queryOne($sql, [
            ':tsr' => $tsr,
            ':date_range_start' => $dateRangeStart,
            ':date_range_end' => $dateRangeEnd
        ]);
        
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Insert a new remark
     */
    public function insertRemark(string $tsr, string $dateRangeStart, string $dateRangeEnd, string $remark, string $createdAt, string $updatedAt): bool
    {
        $sql = "
            INSERT INTO wpk4_backend_interim_performance_remark 
            (tsr, date_range_start, date_range_end, remark, created_at, updated_at)
            VALUES (:tsr, :date_range_start, :date_range_end, :remark, :created_at, :updated_at)
        ";
        
        return $this->execute($sql, [
            ':tsr' => $tsr,
            ':date_range_start' => $dateRangeStart,
            ':date_range_end' => $dateRangeEnd,
            ':remark' => $remark,
            ':created_at' => $createdAt,
            ':updated_at' => $updatedAt
        ]);
    }

    /**
     * Update an existing remark
     */
    public function updateRemark(string $tsr, string $dateRangeStart, string $dateRangeEnd, string $remark, string $updatedAt): bool
    {
        $sql = "
            UPDATE wpk4_backend_interim_performance_remark 
            SET remark = :remark, updated_at = :updated_at
            WHERE tsr = :tsr 
            AND date_range_start = :date_range_start 
            AND date_range_end = :date_range_end
        ";
        
        return $this->execute($sql, [
            ':tsr' => $tsr,
            ':date_range_start' => $dateRangeStart,
            ':date_range_end' => $dateRangeEnd,
            ':remark' => $remark,
            ':updated_at' => $updatedAt
        ]);
    }
}

