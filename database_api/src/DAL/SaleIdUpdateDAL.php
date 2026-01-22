<?php
/**
 * Sale ID Update Data Access Layer
 * Handles all database operations for sale ID update requests
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class SaleIdUpdateDAL extends BaseDAL
{
    /**
     * Get pending records with optional filters
     * 
     * @param string $orderIdFilter Order ID filter (empty string for all)
     * @param string $dateFilter Date filter (empty string for all)
     * @return array Array of pending records
     */
    public function getPendingRecords($orderIdFilter = '', $dateFilter = '')
    {
        $params = [];
        
        $sql = "
            SELECT * 
            FROM wpk4_saleid_update 
            WHERE 1=1 
              AND LOWER(status) = 'pending'
        ";

        if ($orderIdFilter !== '') {
            $sql .= " AND order_id = :order_id";
            $params['order_id'] = $orderIdFilter;
        }

        if ($dateFilter !== '') {
            $sql .= " AND DATE(created_date) = :date_filter";
            $params['date_filter'] = $dateFilter;
        }

        $sql .= " ORDER BY created_date DESC";

        return $this->query($sql, $params);
    }

    /**
     * Get a single record by ID
     * 
     * @param int $recordId Record ID
     * @return array|null Record data or null if not found
     */
    public function getRecordById($recordId)
    {
        $sql = "
            SELECT * 
            FROM wpk4_saleid_update 
            WHERE id = :id
        ";

        return $this->queryOne($sql, ['id' => $recordId]);
    }

    /**
     * Approve a record and update related tables
     * 
     * @param int $recordId Record ID
     * @param string $newSaleId New sale ID to update
     * @param string $orderId Order ID
     * @param string $modifiedBy User who modified the record
     * @return bool True if successful, false otherwise
     */
    public function approveRecord($recordId, $newSaleId, $orderId, $modifiedBy)
    {
        try {
            $this->beginTransaction();

            // Update saleid_update table
            $sql1 = "
                UPDATE wpk4_saleid_update
                SET is_checked = 1,
                    status = 'approved',
                    modified_date = NOW(),
                    modified_by = :modified_by
                WHERE id = :id
            ";
            $this->execute($sql1, [
                'id' => $recordId,
                'modified_by' => $modifiedBy
            ]);

            // Update backend_travel_bookings table
            $sql2 = "
                UPDATE wpk4_backend_travel_bookings
                SET agent_info = :new_sale_id
                WHERE order_id = :order_id
            ";
            $this->execute($sql2, [
                'new_sale_id' => $newSaleId,
                'order_id' => $orderId
            ]);

            // Update backend_travel_bookings_realtime table
            $sql3 = "
                UPDATE wpk4_backend_travel_bookings_realtime
                SET agent_info = :new_sale_id
                WHERE order_id = :order_id
            ";
            $this->execute($sql3, [
                'new_sale_id' => $newSaleId,
                'order_id' => $orderId
            ]);

            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->rollback();
            error_log('Error approving record: ' . $e->getMessage());
            throw new \Exception('Failed to approve record: ' . $e->getMessage());
        }
    }

    /**
     * Reject a record
     * 
     * @param int $recordId Record ID
     * @param string $modifiedBy User who modified the record
     * @return bool True if successful, false otherwise
     */
    public function rejectRecord($recordId, $modifiedBy)
    {
        $sql = "
            UPDATE wpk4_saleid_update
            SET is_checked = 1,
                status = 'rejected',
                modified_date = NOW(),
                modified_by = :modified_by
            WHERE id = :id
        ";

        return $this->execute($sql, [
            'id' => $recordId,
            'modified_by' => $modifiedBy
        ]);
    }

    /**
     * Update check status
     * 
     * @param int $recordId Record ID
     * @param int $isChecked Check status (0 or 1)
     * @return bool True if successful, false otherwise
     */
    public function updateCheckStatus($recordId, $isChecked)
    {
        $sql = "
            UPDATE wpk4_saleid_update
            SET is_checked = :is_checked
            WHERE id = :id
        ";

        return $this->execute($sql, [
            'id' => $recordId,
            'is_checked' => $isChecked
        ]);
    }

    /**
     * Get unique order IDs for filter dropdown
     * 
     * @return array Array of order IDs
     */
    public function getUniqueOrderIds()
    {
        $sql = "
            SELECT DISTINCT order_id 
            FROM wpk4_saleid_update 
            ORDER BY order_id
        ";

        $results = $this->query($sql);
        $orderIds = [];
        foreach ($results as $row) {
            $orderIds[] = $row['order_id'];
        }
        
        return $orderIds;
    }

    /**
     * Get unique dates for filter dropdown
     * 
     * @return array Array of dates
     */
    public function getUniqueDates()
    {
        $sql = "
            SELECT DISTINCT DATE(created_date) AS date
            FROM wpk4_saleid_update 
            ORDER BY created_date DESC
        ";

        $results = $this->query($sql);
        $dates = [];
        foreach ($results as $row) {
            $dates[] = $row['date'];
        }
        
        return $dates;
    }
}

