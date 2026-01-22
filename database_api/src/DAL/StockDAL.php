<?php
/**
 * Stock Data Access Layer
 * Handles database operations for stock import (flight column, price, update)
 */

namespace App\DAL;

use Exception;
use PDOException;

class StockDAL extends BaseDAL
{
    /**
     * Check if stock record exists by auto_id
     */
    public function checkStockExistsByAutoId($autoId)
    {
        try {
            $query = "
                SELECT * 
                FROM wpk4_backend_stock_management_sheet 
                WHERE auto_id = :auto_id
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['auto_id' => $autoId]);
        } catch (PDOException $e) {
            error_log("StockDAL::checkStockExistsByAutoId error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Check if stock record exists by pnr
     */
    public function checkStockExistsByPnr($pnr)
    {
        try {
            $query = "
                SELECT * 
                FROM wpk4_backend_stock_management_sheet 
                WHERE BINARY pnr = :pnr
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['pnr' => $pnr]);
        } catch (PDOException $e) {
            error_log("StockDAL::checkStockExistsByPnr error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update stock record's flight2 column
     */
    public function updateStockFlight2($autoId, $flight2)
    {
        try {
            $query = "
                UPDATE wpk4_backend_stock_management_sheet 
                SET flight2 = :flight_2
                WHERE auto_id = :auto_id
            ";
            
            return $this->execute($query, [
                'auto_id' => $autoId,
                'flight_2' => $flight2
            ]);
        } catch (PDOException $e) {
            error_log("StockDAL::updateStockFlight2 error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update stock record's sub_agent_fare_inr column
     */
    public function updateStockPrice($autoId, $price)
    {
        try {
            $query = "
                UPDATE wpk4_backend_stock_management_sheet 
                SET sub_agent_fare_inr = :price
                WHERE auto_id = :auto_id
            ";
            
            return $this->execute($query, [
                'auto_id' => $autoId,
                'price' => $price
            ]);
        } catch (PDOException $e) {
            error_log("StockDAL::updateStockPrice error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update stock record's mh_endorsement and pnr columns
     */
    public function updateStockEndorsementAndPnr($autoId, $pnr, $mhEndorsement)
    {
        try {
            $query = "
                UPDATE wpk4_backend_stock_management_sheet 
                SET mh_endorsement = :mh_endorsement, 
                    pnr = :pnr
                WHERE auto_id = :auto_id
            ";
            
            return $this->execute($query, [
                'auto_id' => $autoId,
                'pnr' => $pnr,
                'mh_endorsement' => $mhEndorsement
            ]);
        } catch (PDOException $e) {
            error_log("StockDAL::updateStockEndorsementAndPnr error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update stock record's pnr by auto_id
     */
    public function updateStockPnr($autoId, $newPnr)
    {
        try {
            $query = "
                UPDATE wpk4_backend_stock_management_sheet 
                SET pnr = :new_pnr
                WHERE auto_id = :auto_id
            ";
            
            return $this->execute($query, [
                'auto_id' => $autoId,
                'new_pnr' => $newPnr
            ]);
        } catch (PDOException $e) {
            error_log("StockDAL::updateStockPnr error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Insert history update record
     */
    public function insertHistoryUpdate($typeId, $metaKey, $metaValue, $updatedBy, $updatedOn)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_history_of_updates 
                (type_id, meta_key, meta_value, updated_by, updated_on) 
                VALUES 
                (:type_id, :meta_key, :meta_value, :updated_by, :updated_on)
            ";
            
            return $this->execute($query, [
                'type_id' => $typeId,
                'meta_key' => $metaKey,
                'meta_value' => $metaValue,
                'updated_by' => $updatedBy,
                'updated_on' => $updatedOn
            ]);
        } catch (PDOException $e) {
            error_log("StockDAL::insertHistoryUpdate error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }

    // ============================================
    // STOCK MANAGEMENT METHODS
    // ============================================

    /**
     * Get stock by trip ID
     */
    public function getStockByTripId($tripId)
    {
        try {
            $query = "
                SELECT p.id, p.post_title, d.trip_id, d.start_date, d.pricing_ids, d.title 
                FROM wpk4_posts p 
                LEFT JOIN wpk4_wt_dates d ON p.id = d.trip_id 
                WHERE p.id = :trip_id 
                  AND p.post_type = 'itineraries' 
                  AND p.post_status = 'publish' 
                ORDER BY d.start_date ASC
            ";
            
            return $this->query($query, ['trip_id' => $tripId]);
        } catch (PDOException $e) {
            error_log("StockDAL::getStockByTripId error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Get pricing by ID
     */
    public function getPricingById($pricingId)
    {
        try {
            $query = "SELECT * FROM wpk4_wt_pricings WHERE id = :pricing_id LIMIT 1";
            return $this->queryOne($query, ['pricing_id' => $pricingId]);
        } catch (PDOException $e) {
            error_log("StockDAL::getPricingById error: " . $e->getMessage());
            return null; // Return null if not found
        }
    }

    /**
     * Get post meta by key
     */
    public function getPostMetaByKey($metaKey)
    {
        try {
            $query = "SELECT * FROM wpk4_postmeta WHERE meta_key = :meta_key";
            return $this->query($query, ['meta_key' => $metaKey]);
        } catch (PDOException $e) {
            error_log("StockDAL::getPostMetaByKey error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get stock adjustment preview (by PNR)
     */
    public function getStockAdjustmentByPnr($pnr)
    {
        try {
            $query = "
                SELECT * FROM wpk4_backend_stock_management_sheet 
                WHERE pnr = :pnr 
                  AND (current_stock_dummy != '' AND current_stock_dummy IS NOT NULL) 
                ORDER BY dep_date ASC
            ";
            
            return $this->query($query, ['pnr' => $pnr]);
        } catch (PDOException $e) {
            error_log("StockDAL::getStockAdjustmentByPnr error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Get stock adjustment preview (bulk - recent changes)
     */
    public function getStockAdjustmentBulk($currentDate, $currentDateStarting, $limit = 50)
    {
        try {
            $limit = (int)$limit;
            $query = "
                SELECT * FROM wpk4_backend_stock_management_sheet 
                WHERE modified_date <= :current_date 
                  AND modified_date >= :current_date_starting 
                  AND (current_stock_dummy != '' AND current_stock_dummy IS NOT NULL) 
                ORDER BY dep_date ASC 
                LIMIT {$limit}
            ";
            
            return $this->query($query, [
                'current_date' => $currentDate,
                'current_date_starting' => $currentDateStarting
            ]);
        } catch (PDOException $e) {
            error_log("StockDAL::getStockAdjustmentBulk error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Get product info by trip code and date
     */
    public function getProductInfo($tripCode, $depDate)
    {
        try {
            $query = "
                SELECT product_id, pricing_id 
                FROM wpk4_backend_stock_product_manager 
                WHERE trip_code = :trip_code AND travel_date = :travel_date
                LIMIT 1
            ";
            
            return $this->queryOne($query, [
                'trip_code' => $tripCode,
                'travel_date' => $depDate
            ]);
        } catch (PDOException $e) {
            error_log("StockDAL::getProductInfo error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get total booked pax (paid/partially_paid)
     */
    public function getTotalBookedPax($tripCode, $depDate)
    {
        try {
            $query = "
                SELECT SUM(total_pax) as total_pax 
                FROM wpk4_backend_travel_bookings 
                WHERE trip_code = :trip_code 
                  AND travel_date = :travel_date 
                  AND (payment_status = 'paid' OR payment_status = 'partially_paid')
            ";
            
            $result = $this->queryOne($query, [
                'trip_code' => $tripCode,
                'travel_date' => $depDate
            ]);
            
            return (int)($result['total_pax'] ?? 0);
        } catch (PDOException $e) {
            error_log("StockDAL::getTotalBookedPax error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Update pricing max_pax
     */
    public function updatePricingMaxPax($pricingId, $productId, $newMaxPax)
    {
        try {
            $query = "
                UPDATE wpk4_wt_pricings 
                SET max_pax = :new_max_pax
                WHERE id = :pricing_id AND trip_id = :product_id
            ";
            
            return $this->execute($query, [
                'pricing_id' => $pricingId,
                'product_id' => $productId,
                'new_max_pax' => $newMaxPax
            ]);
        } catch (PDOException $e) {
            error_log("StockDAL::updatePricingMaxPax error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Delete pricing and date records
     */
    public function deletePricingAndDate($pricingId, $productId, $depDate)
    {
        try {
            // Delete pricing
            $query1 = "
                DELETE FROM wpk4_wt_pricings 
                WHERE id = :pricing_id AND trip_id = :product_id
            ";
            $this->execute($query1, [
                'pricing_id' => $pricingId,
                'product_id' => $productId
            ]);
            
            // Delete date
            $query2 = "
                DELETE FROM wpk4_wt_dates 
                WHERE pricing_ids = :pricing_id 
                  AND trip_id = :product_id 
                  AND end_date = :dep_date
            ";
            $this->execute($query2, [
                'pricing_id' => $pricingId,
                'product_id' => $productId,
                'dep_date' => $depDate
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("StockDAL::deletePricingAndDate error: " . $e->getMessage());
            throw new Exception("Database delete failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Clear current_stock_dummy for PNR
     */
    public function clearCurrentStockDummy($pnr)
    {
        try {
            $query = "
                UPDATE wpk4_backend_stock_management_sheet 
                SET current_stock_dummy = '' 
                WHERE pnr = :pnr
            ";
            
            return $this->execute($query, ['pnr' => $pnr]);
        } catch (PDOException $e) {
            error_log("StockDAL::clearCurrentStockDummy error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * List stock records with filters
     */
    public function listStockRecords($filters = [])
    {
        try {
            $where = [];
            $params = [];
            
            if (!empty($filters['tripcode'])) {
                $where[] = "trip_id LIKE :tripcode";
                $params['tripcode'] = '%' . $filters['tripcode'] . '%';
            } else {
                $where[] = "trip_id != 'TEST_DMP_ID'";
            }
            
            if (!empty($filters['date'])) {
                // Format: YYYY-MM-DD - YYYY-MM-DD
                $dateParts = explode(' - ', $filters['date']);
                if (count($dateParts) == 2) {
                    $where[] = "DATE(dep_date) >= :depdate_start AND DATE(dep_date) <= :depdate_end";
                    $params['depdate_start'] = trim($dateParts[0]);
                    $params['depdate_end'] = trim($dateParts[1]);
                }
            } else {
                $where[] = "trip_id != 'TEST_DMP_ID'";
            }
            
            if (!empty($filters['pnr'])) {
                if (!empty($filters['exactmatch'])) {
                    $where[] = "pnr = :pnr";
                } else {
                    $where[] = "pnr LIKE :pnr";
                }
                $params['pnr'] = !empty($filters['exactmatch']) ? $filters['pnr'] : '%' . $filters['pnr'] . '%';
            } else {
                $where[] = "trip_id != 'TEST_DMP_ID'";
            }
            
            if (!empty($filters['reldate'])) {
                // Format: YYYY-MM-DD - YYYY-MM-DD
                $dateParts = explode(' - ', $filters['reldate']);
                if (count($dateParts) == 2) {
                    $where[] = "release_date >= :reldate_start AND release_date <= :reldate_end";
                    $params['reldate_start'] = trim($dateParts[0]) . ' 00:00:00';
                    $params['reldate_end'] = trim($dateParts[1]) . ' 23:59:59';
                }
            } else {
                $where[] = "trip_id != 'TEST_DMP_ID'";
            }
            
            $whereClause = implode(' AND ', $where);
            
            $query = "
                SELECT * FROM wpk4_backend_stock_management_sheet 
                WHERE {$whereClause}
                ORDER BY dep_date ASC
            ";
            
            return $this->query($query, $params);
        } catch (PDOException $e) {
            error_log("StockDAL::listStockRecords error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Get stock record by ID
     */
    public function getStockById($autoId)
    {
        try {
            $query = "
                SELECT * FROM wpk4_backend_stock_management_sheet 
                WHERE auto_id = :auto_id
                LIMIT 1
            ";
            
            return $this->queryOne($query, ['auto_id' => $autoId]);
        } catch (PDOException $e) {
            error_log("StockDAL::getStockById error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Create stock record
     */
    public function createStock($data)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_stock_management_sheet 
                (pnr, dep_date, trip_id, original_stock, current_stock, stock_release, stock_unuse, 
                 modified_date, modified_by, airline_code, route, aud_fare, ind_fare, source, 
                 route_type, flight1, flight2, domestic_del, domestic_amd, domestic_bom, 
                 domestic_ccu, domestic_cok, domestic_maa, domestic_hyd, domestic_blr, 
                 domestic_atq, sydney, adelaide) 
                VALUES 
                (:pnr, :dep_date, :trip_id, :original_stock, :current_stock, :stock_release, 
                 :stock_unuse, :modified_date, :modified_by, :airline_code, :route, :aud_fare, 
                 :ind_fare, :source, :route_type, :flight1, :flight2, :domestic_del, 
                 :domestic_amd, :domestic_bom, :domestic_ccu, :domestic_cok, :domestic_maa, 
                 :domestic_hyd, :domestic_blr, :domestic_atq, :sydney, :adelaide)
            ";
            
            return $this->execute($query, $data);
        } catch (PDOException $e) {
            error_log("StockDAL::createStock error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update stock record
     */
    public function updateStock($autoId, $data)
    {
        try {
            $fields = [];
            $params = ['auto_id' => $autoId];
            
            $allowedFields = [
                'pnr', 'dep_date', 'trip_id', 'original_stock', 'current_stock', 
                'stock_release', 'stock_unuse', 'modified_date', 'modified_by',
                'airline_code', 'route', 'aud_fare', 'ind_fare', 'source',
                'route_type', 'flight1', 'flight2', 'domestic_del', 'domestic_amd',
                'domestic_bom', 'domestic_ccu', 'domestic_cok', 'domestic_maa',
                'domestic_hyd', 'domestic_blr', 'domestic_atq', 'sydney', 'adelaide'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "{$field} = :{$field}";
                    $params[$field] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $query = "
                UPDATE wpk4_backend_stock_management_sheet SET 
                " . implode(', ', $fields) . "
                WHERE auto_id = :auto_id
            ";
            
            return $this->execute($query, $params);
        } catch (PDOException $e) {
            error_log("StockDAL::updateStock error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Delete stock record
     */
    public function deleteStock($autoId)
    {
        try {
            $query = "
                DELETE FROM wpk4_backend_stock_management_sheet 
                WHERE auto_id = :auto_id
            ";
            
            return $this->execute($query, ['auto_id' => $autoId]);
        } catch (PDOException $e) {
            error_log("StockDAL::deleteStock error: " . $e->getMessage());
            throw new Exception("Database delete failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Get stock release preview
     */
    public function getStockReleasePreview($currentDatePlusThreeStart, $currentDatePlusThreeEnd, 
                                          $currentDatePlus30Start, $currentDatePlus30End)
    {
        try {
            $query = "
                SELECT * FROM wpk4_backend_stock_management_sheet 
                WHERE ((source = 'TRAU' OR source = 'SQIN') 
                  AND dep_date >= :date_3_start 
                  AND dep_date <= :date_3_end)
                  OR (source = 'QFAU' 
                  AND dep_date >= :date_30_start 
                  AND dep_date <= :date_30_end)
                ORDER BY source ASC, dep_date ASC
            ";
            
            return $this->query($query, [
                'date_3_start' => $currentDatePlusThreeStart,
                'date_3_end' => $currentDatePlusThreeEnd,
                'date_30_start' => $currentDatePlus30Start,
                'date_30_end' => $currentDatePlus30End
            ]);
        } catch (PDOException $e) {
            error_log("StockDAL::getStockReleasePreview error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update stock release
     */
    public function updateStockRelease($tripId, $depDate, $pnr, $currentStockNew, $stockUnuseNew)
    {
        try {
            $query = "
                UPDATE wpk4_backend_stock_management_sheet 
                SET current_stock = :current_stock_new,
                    stock_unuse = :stock_unuse_new
                WHERE trip_id = :trip_id 
                  AND dep_date = :dep_date 
                  AND pnr = :pnr
            ";
            
            return $this->execute($query, [
                'trip_id' => $tripId,
                'dep_date' => $depDate,
                'pnr' => $pnr,
                'current_stock_new' => $currentStockNew,
                'stock_unuse_new' => $stockUnuseNew
            ]);
        } catch (PDOException $e) {
            error_log("StockDAL::updateStockRelease error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Insert seat availability log
     */
    public function insertSeatAvailabilityLog($pricingId, $originalPax, $newPax, $updatedOn, $updatedBy, $changedPaxCount)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_manage_seat_availability_log 
                (pricing_id, original_pax, new_pax, updated_on, updated_by, changed_pax_count) 
                VALUES 
                (:pricing_id, :original_pax, :new_pax, :updated_on, :updated_by, :changed_pax_count)
            ";
            
            return $this->execute($query, [
                'pricing_id' => $pricingId,
                'original_pax' => $originalPax,
                'new_pax' => $newPax,
                'updated_on' => $updatedOn,
                'updated_by' => $updatedBy,
                'changed_pax_count' => $changedPaxCount
            ]);
        } catch (PDOException $e) {
            error_log("StockDAL::insertSeatAvailabilityLog error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Insert travel booking update history
     */
    public function insertTravelBookingUpdateHistory($orderId, $metaKey, $metaValue, $updatedTime, $updatedUser)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_travel_booking_update_history 
                (order_id, meta_key, meta_value, updated_time, updated_user) 
                VALUES 
                (:order_id, :meta_key, :meta_value, :updated_time, :updated_user)
            ";
            
            return $this->execute($query, [
                'order_id' => $orderId,
                'meta_key' => $metaKey,
                'meta_value' => $metaValue,
                'updated_time' => $updatedTime,
                'updated_user' => $updatedUser
            ]);
        } catch (PDOException $e) {
            error_log("StockDAL::insertTravelBookingUpdateHistory error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update stock release date
     */
    public function updateStockReleaseDate($autoId, $releaseDate)
    {
        try {
            $query = "
                UPDATE wpk4_backend_stock_management_sheet 
                SET release_date = :release_date
                WHERE auto_id = :auto_id
            ";
            
            return $this->execute($query, [
                'auto_id' => $autoId,
                'release_date' => $releaseDate
            ]);
        } catch (PDOException $e) {
            error_log("StockDAL::updateStockReleaseDate error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Create stock release note
     */
    public function createStockReleaseNote($data)
    {
        try {
            $query = "
                INSERT INTO wpk4_backend_stock_management_sheet_release_note
                (stock_id, pnr, released_type, description, released_date, no_of_seat_released, 
                 amount_lost, total_amount, added_by) 
                VALUES 
                (:stock_id, :pnr, :released_type, :description, :released_date, :no_of_seat_released, 
                 :amount_lost, :total_amount, :added_by)
            ";
            
            return $this->execute($query, $data);
        } catch (PDOException $e) {
            error_log("StockDAL::createStockReleaseNote error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Update stock release note
     */
    public function updateStockReleaseNote($id, $data)
    {
        try {
            $fields = [];
            $params = ['id' => $id];
            
            $allowedFields = [
                'released_type', 'description', 'no_of_seat_released', 
                'amount_lost', 'total_amount'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "{$field} = :{$field}";
                    $params[$field] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $query = "
                UPDATE wpk4_backend_stock_management_sheet_release_note SET 
                " . implode(', ', $fields) . "
                WHERE id = :id
            ";
            
            return $this->execute($query, $params);
        } catch (PDOException $e) {
            error_log("StockDAL::updateStockReleaseNote error: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * List stock release notes with filters
     */
    public function listStockReleaseNotes($filters = [])
    {
        try {
            $where = [];
            $params = [];
            
            if (!empty($filters['tripcode'])) {
                $where[] = "stms.trip_id = :tripcode";
                $params['tripcode'] = $filters['tripcode'];
            } else {
                $where[] = "stms.auto_id != 'TEST_DMP_ID'";
            }
            
            if (!empty($filters['date'])) {
                $where[] = "stms.dep_date = :traveldate";
                $params['traveldate'] = $filters['date'];
            } else {
                $where[] = "stms.auto_id != 'TEST_DMP_ID'";
            }
            
            if (!empty($filters['pnr'])) {
                $where[] = "stmsrn.pnr = :pnr";
                $params['pnr'] = $filters['pnr'];
            } else {
                $where[] = "stms.auto_id != 'TEST_DMP_ID'";
            }
            
            if (!empty($filters['reldate'])) {
                $where[] = "stmsrn.added_on LIKE :reldate";
                $params['reldate'] = '%' . $filters['reldate'] . '%';
            } else {
                $where[] = "stms.auto_id != 'TEST_DMP_ID'";
            }
            
            $whereClause = implode(' AND ', $where);
            
            $query = "
                SELECT apd.group_name, apd.group_id, DATE(stms.dep_date) as travel_date,
                       stms.trip_id as trip_code, stmsrn.*
                FROM wpk4_backend_stock_management_sheet stms
                JOIN wpk4_backend_stock_management_sheet_release_note stmsrn 
                  ON stms.auto_id = stmsrn.stock_id
                LEFT JOIN wpk4_backend_airlines_payment_details apd 
                  ON apd.pnr = stmsrn.pnr
                WHERE {$whereClause}
                ORDER BY stmsrn.added_on DESC
            ";
            
            return $this->query($query, $params);
        } catch (PDOException $e) {
            error_log("StockDAL::listStockReleaseNotes error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Get stock release notes by stock ID
     */
    public function getStockReleaseNotesByStockId($stockId)
    {
        try {
            $query = "
                SELECT apd.group_name, apd.group_id, apd.pnr,
                       DATE(stms.dep_date) as travel_date,
                       stms.trip_id as trip_code,
                       stmsrn.*
                FROM wpk4_backend_stock_management_sheet stms
                JOIN wpk4_backend_stock_management_sheet_release_note stmsrn 
                  ON stms.auto_id = stmsrn.stock_id
                LEFT JOIN wpk4_backend_airlines_payment_details apd 
                  ON apd.pnr = SUBSTRING(stmsrn.pnr, 1, 6)
                WHERE stms.auto_id = :stock_id
                ORDER BY stmsrn.added_on DESC
            ";
            
            return $this->query($query, ['stock_id' => $stockId]);
        } catch (PDOException $e) {
            error_log("StockDAL::getStockReleaseNotesByStockId error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }
}

