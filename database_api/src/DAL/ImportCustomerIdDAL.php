<?php
namespace App\DAL;

use Exception;

class ImportCustomerIdDAL extends BaseDAL
{
    /**
     * Get pax record by auto_id
     */
    public function getPaxByAutoId($autoId)
    {
        $query = "SELECT * FROM wpk4_backend_travel_booking_pax WHERE auto_id = :auto_id";
        return $this->queryOne($query, ['auto_id' => $autoId]);
    }

    /**
     * Validate CSV data - check which auto_ids exist
     */
    public function validateCsvData($csvRows)
    {
        $results = [];
        
        foreach ($csvRows as $row) {
            $autoId = $row['pax_auto_id'] ?? $row[0] ?? null;
            $familyId = $row['familyid'] ?? $row[1] ?? null;
            $customerId = $row['customerid'] ?? $row[2] ?? null;
            
            // Skip header row
            if ($autoId === 'pax_auto_id' || empty($autoId)) {
                continue;
            }
            
            $paxRecord = $this->getPaxByAutoId($autoId);
            
            $isMatched = false;
            $orderId = null;
            $status = 'New Record';
            
            if ($paxRecord) {
                $isMatched = true;
                $orderId = $paxRecord['order_id'] ?? null;
                $status = 'Existing';
            }
            
            $results[] = [
                'auto_id' => $autoId,
                'order_id' => $orderId,
                'familyid' => $familyId,
                'customerid' => $customerId,
                'status' => $status,
                'is_matched' => $isMatched
            ];
        }
        
        return $results;
    }

    /**
     * Update customer_id in pax table
     */
    public function updateCustomerId($autoId, $customerId)
    {
        $query = "
            UPDATE wpk4_backend_travel_booking_pax 
            SET customer_id = :customer_id
            WHERE auto_id = :auto_id
        ";
        return $this->execute($query, [
            'customer_id' => $customerId,
            'auto_id' => $autoId
        ]);
    }

    /**
     * Update family_id in bookings table
     */
    public function updateFamilyId($orderId, $familyId)
    {
        $query = "
            UPDATE wpk4_backend_travel_bookings 
            SET family_id = :family_id
            WHERE order_id = :order_id
        ";
        return $this->execute($query, [
            'family_id' => $familyId,
            'order_id' => $orderId
        ]);
    }

    /**
     * Batch update customer_id and family_id
     */
    public function batchUpdate($updates)
    {
        $updatedCount = 0;
        
        foreach ($updates as $update) {
            $autoId = $update['auto_id'] ?? null;
            $orderId = $update['order_id'] ?? null;
            $familyId = $update['family_id'] ?? null;
            $customerId = $update['customer_id'] ?? null;
            
            if (!$autoId || !$orderId || !$familyId || !$customerId) {
                continue;
            }
            
            // Update customer_id
            if ($this->updateCustomerId($autoId, $customerId)) {
                $updatedCount++;
            }
            
            // Update family_id
            $this->updateFamilyId($orderId, $familyId);
        }
        
        return $updatedCount;
    }
}

