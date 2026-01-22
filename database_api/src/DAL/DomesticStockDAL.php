<?php
/**
 * Domestic Stock Data Access Layer
 * Handles database operations for flight inventory/stock
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class DomesticStockDAL extends BaseDAL
{
    /**
     * Get international stock (short PNR < 7)
     */
    public function getInternationalStock($tripCode, $depDate, $pnr = null)
    {
        $whereParts = ["trip_id LIKE ?", "DATE(dep_date) = ?", "CHAR_LENGTH(pnr) < 7"];
        $params = ['%' . $tripCode . '%', $depDate];

        if ($pnr) {
            $whereParts[] = "pnr LIKE ?";
            $params[] = '%' . $pnr . '%';
        }

        $whereSQL = implode(' AND ', $whereParts);
        
        $query = "SELECT * FROM wpk4_backend_stock_management_sheet 
                  WHERE $whereSQL 
                  ORDER BY dep_date ASC 
                  LIMIT 10";

        return $this->query($query, $params);
    }

    /**
     * Get domestic stock (long PNR > 9)
     */
    public function getDomesticStock($tripCode, $depDate, $pnr = null)
    {
        $whereParts = ["trip_id LIKE ?", "DATE(dep_date) = ?", "CHAR_LENGTH(pnr) > 9"];
        $params = ['%' . $tripCode . '%', $depDate];

        if ($pnr) {
            $whereParts[] = "pnr LIKE ?";
            $params[] = '%' . $pnr . '%';
        }

        $whereSQL = implode(' AND ', $whereParts);
        
        $query = "SELECT * FROM wpk4_backend_stock_management_sheet_child 
                  WHERE $whereSQL 
                  ORDER BY dep_date ASC 
                  LIMIT 10";

        return $this->query($query, $params);
    }

    /**
     * Get international stock by PNR
     */
    public function getInternationalStockByPNR($pnr)
    {
        $query = "SELECT * FROM wpk4_backend_stock_management_sheet 
                  WHERE pnr LIKE ? 
                  ORDER BY dep_date ASC";
        
        return $this->query($query, ['%' . $pnr . '%']);
    }

    /**
     * Get domestic stock by PNR
     */
    public function getDomesticStockByPNR($pnr)
    {
        $query = "SELECT * FROM wpk4_backend_stock_management_sheet_child 
                  WHERE pnr LIKE ? 
                  ORDER BY dep_date ASC";
        
        return $this->query($query, ['%' . $pnr . '%']);
    }

    /**
     * Get international stock by ID
     */
    public function getInternationalStockById($id)
    {
        $query = "SELECT * FROM wpk4_backend_stock_management_sheet WHERE auto_id = ? LIMIT 1";
        return $this->queryOne($query, [$id]);
    }

    /**
     * Get domestic stock by ID
     */
    public function getDomesticStockById($id)
    {
        $query = "SELECT * FROM wpk4_backend_stock_management_sheet_child WHERE auto_id = ? LIMIT 1";
        return $this->queryOne($query, [$id]);
    }

    /**
     * Get booking count for trip code and date
     */
    public function getBookingCount($tripCode, $depDate)
    {
        $query = "SELECT COUNT(*) as booking_count 
                  FROM wpk4_backend_travel_bookings 
                  WHERE trip_code = ? 
                    AND travel_date LIKE ? 
                    AND payment_status IN ('paid', 'partially_paid')";
        
        $result = $this->queryOne($query, [$tripCode, $depDate . '%']);
        return (int)$result['booking_count'];
    }

    /**
     * Update international stock
     */
    public function updateInternationalStock($id, $data)
    {
        $setParts = [];
        $params = [];

        $updateableFields = ['source', 'current_stock', 'stock_release', 'stock_unuse', 'blocked_seat'];

        foreach ($updateableFields as $field) {
            if (array_key_exists($field, $data)) {
                $setParts[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($setParts)) {
            return false;
        }

        $setSQL = implode(', ', $setParts);
        $query = "UPDATE wpk4_backend_stock_management_sheet SET $setSQL WHERE auto_id = ?";
        $params[] = $id;

        return $this->execute($query, $params);
    }

    /**
     * Update domestic stock
     */
    public function updateDomesticStock($id, $data)
    {
        $setParts = [];
        $params = [];

        $updateableFields = ['source', 'current_stock', 'stock_release', 'stock_unuse', 'blocked_seat'];

        foreach ($updateableFields as $field) {
            if (array_key_exists($field, $data)) {
                $setParts[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($setParts)) {
            return false;
        }

        $setSQL = implode(', ', $setParts);
        $query = "UPDATE wpk4_backend_stock_management_sheet_child SET $setSQL WHERE auto_id = ?";
        $params[] = $id;

        return $this->execute($query, $params);
    }

    /**
     * Update release date
     */
    public function updateReleaseDate($id, $type)
    {
        $currentTimestamp = date('Y-m-d H:i:s');
        
        if ($type === 'international') {
            $query = "UPDATE wpk4_backend_stock_management_sheet 
                      SET release_date = ? 
                      WHERE auto_id = ?";
        } else {
            $query = "UPDATE wpk4_backend_stock_management_sheet_child 
                      SET release_date = ? 
                      WHERE auto_id = ?";
        }

        return $this->execute($query, [$currentTimestamp, $id]);
    }

    /**
     * Log stock update
     */
    public function logStockUpdate($pnr, $metaKey, $metaValue, $updatedBy)
    {
        $currentTimestamp = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO wpk4_backend_travel_booking_update_history 
                  (order_id, meta_key, meta_value, updated_time, updated_user) 
                  VALUES (?, ?, ?, ?, ?)";
        
        return $this->execute($query, [$pnr, $metaKey, $metaValue, $currentTimestamp, $updatedBy]);
    }

    /**
     * Get product info
     */
    public function getProductInfo($tripCode, $travelDate)
    {
        $query = "SELECT * FROM wpk4_backend_stock_product_manager 
                  WHERE trip_code = ? 
                    AND travel_date LIKE ? 
                  LIMIT 1";
        
        return $this->queryOne($query, [$tripCode, $travelDate . '%']);
    }

    /**
     * Get excluded dates
     */
    public function getExcludedDates($productId, $depDate)
    {
        $query = "SELECT * FROM wpk4_wt_excluded_dates_times 
                  WHERE trip_id = ? 
                    AND start_date LIKE ? 
                  LIMIT 1";
        
        return $this->queryOne($query, [$productId, $depDate . '%']);
    }
}

