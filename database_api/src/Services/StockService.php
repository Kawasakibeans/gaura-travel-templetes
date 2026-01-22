<?php
/**
 * Stock Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\StockDAL;
use Exception;

class StockService
{
    private $stockDAL;

    public function __construct()
    {
        $this->stockDAL = new StockDAL();
    }

    /**
     * Preview stock flight column import
     */
    public function previewStockFlightColumn($csvData)
    {
        if (empty($csvData)) {
            throw new Exception('CSV data is required', 400);
        }
        
        $preview = [];
        $autonumber = 1;
        
        foreach ($csvData as $row) {
            // Skip header row
            if (($row[0] ?? '') === 'auto_id' && ($row[1] ?? '') === 'flight_2') {
                continue;
            }
            
            $autoId = $row[0] ?? '';
            $flight2 = $row[1] ?? '';
            
            if (empty($autoId)) {
                continue;
            }
            
            // Check if stock exists
            $existing = $this->stockDAL->checkStockExistsByAutoId($autoId);
            
            $matchHidden = 'New';
            $match = 'New Record';
            $checked = false;
            
            if ($existing && $existing['auto_id'] == $autoId) {
                $matchHidden = 'Existing';
                $match = 'Existing';
                $checked = true;
            }
            
            $preview[] = [
                'autonumber' => $autonumber,
                'auto_id' => $autoId,
                'flight_2' => $flight2,
                'match_status' => $matchHidden,
                'match' => $match,
                'checked' => $checked
            ];
            
            $autonumber++;
        }
        
        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Import stock flight column
     */
    public function importStockFlightColumn($records, $updatedBy)
    {
        if (empty($records)) {
            throw new Exception('No records provided for import', 400);
        }
        
        $importedCount = 0;
        $now = date('Y-m-d H:i:s');
        
        foreach ($records as $record) {
            $autoId = $record['auto_id'] ?? '';
            $flight2 = $record['flight_2'] ?? '';
            $matchHidden = $record['match_hidden'] ?? '';
            
            // Only process existing records
            if ($matchHidden !== 'Existing') {
                continue;
            }
            
            if (empty($autoId)) {
                continue;
            }
            
            // Update stock record
            $this->stockDAL->updateStockFlight2($autoId, $flight2);
            
            // Insert history update
            $this->stockDAL->insertHistoryUpdate($autoId, 'stock flight2', $flight2, $updatedBy, $now);
            
            $importedCount++;
        }
        
        return [
            'success' => true,
            'message' => 'Updated successfully',
            'imported_count' => $importedCount
        ];
    }

    /**
     * Preview stock price import
     */
    public function previewStockPrice($csvData)
    {
        if (empty($csvData)) {
            throw new Exception('CSV data is required', 400);
        }
        
        $preview = [];
        $autonumber = 1;
        
        foreach ($csvData as $row) {
            // Skip header row
            if (($row[0] ?? '') === 'id' && ($row[1] ?? '') === 'price') {
                continue;
            }
            
            $autoId = $row[0] ?? '';
            $price = $row[1] ?? '';
            
            if (empty($autoId)) {
                continue;
            }
            
            // Check if stock exists
            $existing = $this->stockDAL->checkStockExistsByAutoId($autoId);
            
            $matchHidden = 'New';
            $match = 'New Record';
            $checked = false;
            
            if ($existing && $existing['auto_id'] == $autoId) {
                $matchHidden = 'Existing';
                $match = 'Existing';
                $checked = true;
            }
            
            $preview[] = [
                'autonumber' => $autonumber,
                'auto_id' => $autoId,
                'price' => $price,
                'match_status' => $matchHidden,
                'match' => $match,
                'checked' => $checked
            ];
            
            $autonumber++;
        }
        
        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Import stock price
     */
    public function importStockPrice($records, $updatedBy)
    {
        if (empty($records)) {
            throw new Exception('No records provided for import', 400);
        }
        
        $importedCount = 0;
        $now = date('Y-m-d H:i:s');
        
        foreach ($records as $record) {
            $autoId = $record['auto_id'] ?? '';
            $price = $record['price'] ?? '';
            $matchHidden = $record['match_hidden'] ?? '';
            
            // Only process existing records
            if ($matchHidden !== 'Existing') {
                continue;
            }
            
            if (empty($autoId)) {
                continue;
            }
            
            // Update stock record
            $this->stockDAL->updateStockPrice($autoId, $price);
            
            // Insert history update
            $this->stockDAL->insertHistoryUpdate($autoId, 'stock sub_agent_fare_inr', $price, $updatedBy, $now);
            
            $importedCount++;
        }
        
        return [
            'success' => true,
            'message' => 'Updated successfully',
            'imported_count' => $importedCount
        ];
    }

    /**
     * Preview stock update import
     */
    public function previewStockUpdate($csvData)
    {
        if (empty($csvData)) {
            throw new Exception('CSV data is required', 400);
        }
        
        $preview = [];
        $autonumber = 1;
        
        foreach ($csvData as $row) {
            // Skip header row
            if (($row[0] ?? '') === 'PNR' && ($row[1] ?? '') === 'Stock') {
                continue;
            }
            
            $autoId = $row[0] ?? '';
            $pnr = $row[1] ?? '';
            $updatedStock = $row[2] ?? '';
            
            if (empty($autoId) || empty($pnr)) {
                continue;
            }
            
            // Check if stock exists by pnr
            $existing = $this->stockDAL->checkStockExistsByPnr($pnr);
            
            $matchHidden = 'New';
            $match = 'New';
            $checked = false;
            
            if ($existing && $existing['pnr'] == $pnr) {
                $matchHidden = 'Existing';
                $match = 'Existing & will be overwrite';
                $checked = true;
            }
            
            $preview[] = [
                'autonumber' => $autonumber,
                'auto_id' => $autoId,
                'pnr' => $pnr,
                'updated_stock' => $updatedStock,
                'match_status' => $matchHidden,
                'match' => $match,
                'checked' => $checked
            ];
            
            $autonumber++;
        }
        
        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Import stock update
     */
    public function importStockUpdate($records, $updatedBy)
    {
        if (empty($records)) {
            throw new Exception('No records provided for import', 400);
        }
        
        $importedCount = 0;
        $now = date('Y-m-d H:i:s');
        
        foreach ($records as $record) {
            $autoId = $record['auto_id'] ?? '';
            $pnr = $record['pnr'] ?? '';
            $updatedStock = $record['updated_stock'] ?? '';
            $matchHidden = $record['match_hidden'] ?? '';
            
            // Only process existing records
            if ($matchHidden !== 'Existing') {
                continue;
            }
            
            if (empty($autoId) || empty($pnr)) {
                continue;
            }
            
            // Update stock record (using auto_id, but matching was done by pnr)
            $this->stockDAL->updateStockEndorsementAndPnr($autoId, $pnr, $updatedStock);
            
            // Insert history updates
            $this->stockDAL->insertHistoryUpdate($pnr, 'pnr', $pnr, $updatedBy, $now);
            $this->stockDAL->insertHistoryUpdate($pnr, 'mh_endorsement', $updatedStock, $updatedBy, $now);
            
            $importedCount++;
        }
        
        return [
            'success' => true,
            'message' => 'Updated successfully',
            'imported_count' => $importedCount
        ];
    }

    /**
     * Preview stock PNR update by ID import
     */
    public function previewStockPnrUpdate($csvData)
    {
        if (empty($csvData)) {
            throw new Exception('CSV data is required', 400);
        }
        
        $preview = [];
        $autonumber = 1;
        
        foreach ($csvData as $row) {
            // Skip header row
            if (($row[0] ?? '') === 'auto_id' && ($row[1] ?? '') === 'old_pnr' && ($row[2] ?? '') === 'new_pnr') {
                continue;
            }
            
            $autoId = $row[0] ?? '';
            $oldPnr = $row[1] ?? '';
            $newPnr = $row[2] ?? '';
            
            if (empty($autoId)) {
                continue;
            }
            
            // Check if stock exists
            $existing = $this->stockDAL->checkStockExistsByAutoId($autoId);
            
            $matchHidden = 'New';
            $match = 'New Record';
            $checked = false;
            
            if ($existing && $existing['auto_id'] == $autoId) {
                $matchHidden = 'Existing';
                $match = 'Existing';
                $checked = true;
            }
            
            $preview[] = [
                'autonumber' => $autonumber,
                'auto_id' => $autoId,
                'old_pnr' => $oldPnr,
                'new_pnr' => $newPnr,
                'match_status' => $matchHidden,
                'match' => $match,
                'checked' => $checked
            ];
            
            $autonumber++;
        }
        
        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Import stock PNR update by ID
     */
    public function importStockPnrUpdate($records, $updatedBy)
    {
        if (empty($records)) {
            throw new Exception('No records provided for import', 400);
        }
        
        $importedCount = 0;
        $now = date('Y-m-d H:i:s');
        
        foreach ($records as $record) {
            $autoId = $record['auto_id'] ?? '';
            $oldPnr = $record['old_pnr'] ?? '';
            $newPnr = $record['new_pnr'] ?? '';
            $matchHidden = $record['match_hidden'] ?? '';
            
            // Only process existing records
            if ($matchHidden !== 'Existing') {
                continue;
            }
            
            if (empty($autoId) || empty($newPnr)) {
                continue;
            }
            
            // Update stock PNR
            $this->stockDAL->updateStockPnr($autoId, $newPnr);
            
            // Insert history update with PNR change history
            $pnrChangeHistory = $oldPnr . ' -> ' . $newPnr;
            $this->stockDAL->insertHistoryUpdate($autoId, 'stock pnr update', $pnrChangeHistory, $updatedBy, $now);
            
            $importedCount++;
        }
        
        return [
            'success' => true,
            'message' => 'Updated successfully',
            'imported_count' => $importedCount
        ];
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
            $trips = $this->stockDAL->getStockByTripId($tripId);
            
            if (empty($trips)) {
                return ['success' => true, 'stock_data' => []];
            }
            
            $stockData = [];
            foreach ($trips as $trip) {
                $pricingId = $trip['pricing_ids'] ?? null;
                if (!$pricingId) {
                    continue;
                }
                
                $pricing = $this->stockDAL->getPricingById($pricingId);
                if (!$pricing) {
                    continue;
                }
                
                $maxPax = (int)($pricing['max_pax'] ?? 0);
                $startDate = $trip['start_date'] ?? '';
                
                // Format date for meta key
                $dividedPriceStartDate = str_replace('-', '_', $startDate);
                $searchQuote = 'wt_booked_pax-' . $pricingId . '-' . $dividedPriceStartDate . '-00_00';
                
                $postMetas = $this->stockDAL->getPostMetaByKey($searchQuote);
                $booked = 0;
                foreach ($postMetas as $meta) {
                    $booked += (int)($meta['meta_value'] ?? 0);
                }
                
                $available = $maxPax - $booked;
                
                $stockData[] = [
                    'trip_id' => $trip['id'] ?? $tripId,
                    'trip_title' => $pricing['title'] ?? $trip['post_title'] ?? '',
                    'category_title' => $trip['title'] ?? '',
                    'pricing_ids' => $pricingId,
                    'total_seats' => $maxPax,
                    'booked' => $booked,
                    'available' => $available,
                    'start_date' => $startDate
                ];
            }
            
            return ['success' => true, 'stock_data' => $stockData];
        } catch (\Exception $e) {
            error_log("StockService::getStockByTripId error: " . $e->getMessage());
            error_log("Trip ID: " . $tripId);
            throw $e;
        }
    }

    /**
     * Preview stock adjustment
     */
    public function previewStockAdjustment($pnr = null, $limit = 50)
    {
        $currentDate = date('Y-m-d H:i:s');
        $currentDateStarting = date('Y-m-d') . ' 00:00:00';
        
        if ($pnr) {
            $stocks = $this->stockDAL->getStockAdjustmentByPnr($pnr);
        } else {
            $stocks = $this->stockDAL->getStockAdjustmentBulk($currentDate, $currentDateStarting, $limit);
        }
        
        $preview = [];
        foreach ($stocks as $stock) {
            $tripCode = $stock['trip_id'];
            $depDate = $stock['dep_date'];
            $currentStock = (int)$stock['current_stock'];
            $currentStockDummy = (int)($stock['current_stock_dummy'] ?? 0);
            
            $productInfo = $this->stockDAL->getProductInfo($tripCode, $depDate);
            $pricingId = $productInfo['pricing_id'] ?? '';
            $productId = $productInfo['product_id'] ?? '';
            
            $maxPaxOriginal = 0;
            if ($pricingId) {
                $pricing = $this->stockDAL->getPricingById($pricingId);
                $maxPaxOriginal = (int)($pricing['max_pax'] ?? 0);
            }
            
            $totalPax = $this->stockDAL->getTotalBookedPax($tripCode, $depDate);
            $newMaxPax = $maxPaxOriginal + ($currentStock - $currentStockDummy);
            
            $matchStatus = 'Existing';
            if (!$pricingId) {
                $matchStatus = 'PricingID not found';
            }
            
            $preview[] = [
                'pnr' => $stock['pnr'],
                'dep_date' => date('Y-m-d', strtotime($depDate)),
                'trip_code' => $tripCode,
                'current_stock' => $currentStock,
                'stock_unuse' => $stock['stock_unuse'] ?? 0,
                'pricing_id' => $pricingId,
                'product_id' => $productId,
                'booking_pax' => $totalPax,
                'max_pax_original' => $maxPaxOriginal,
                'max_pax_new' => $newMaxPax,
                'match_status' => $matchStatus
            ];
        }
        
        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Apply stock adjustment
     */
    public function applyStockAdjustment($records, $updatedBy)
    {
        if (empty($records)) {
            throw new Exception('No records provided', 400);
        }
        
        $updatedCount = 0;
        $now = date('Y-m-d H:i:s');
        
        foreach ($records as $record) {
            $pnr = $record['pnr'] ?? '';
            $depDate = $record['dep_date'] ?? '';
            $tripId = $record['trip_id'] ?? '';
            $pricingId = $record['pricing_id'] ?? '';
            $productId = $record['product_id'] ?? '';
            $maxPaxOriginal = (int)($record['max_pax_original'] ?? 0);
            $newMaxPax = (int)($record['new_max_pax'] ?? 0);
            $totalPax = (int)($record['total_pax'] ?? 0);
            
            if (empty($pnr) || empty($pricingId) || empty($productId)) {
                continue;
            }
            
            if ($newMaxPax < 1 && $totalPax == 0) {
                $this->stockDAL->deletePricingAndDate($pricingId, $productId, $depDate);
            } else {
                $this->stockDAL->updatePricingMaxPax($pricingId, $productId, $newMaxPax);
            }
            
            $this->stockDAL->clearCurrentStockDummy($pnr);
            $updatedCount++;
        }
        
        return [
            'success' => true,
            'message' => 'Update successful',
            'updated_count' => $updatedCount
        ];
    }

    /**
     * List stock records
     */
    public function listStockRecords($filters = [])
    {
        $records = $this->stockDAL->listStockRecords($filters);
        
        return [
            'success' => true,
            'count' => count($records),
            'records' => $records
        ];
    }

    /**
     * Get stock record by ID
     */
    public function getStockById($autoId)
    {
        $stock = $this->stockDAL->getStockById($autoId);
        
        if (!$stock) {
            throw new Exception('Stock record not found', 404);
        }
        
        return ['success' => true, 'stock' => $stock];
    }

    /**
     * Create stock record
     */
    public function createStock($data, $updatedBy)
    {
        $now = date('Y-m-d H:i:s');
        
        $stockData = [
            'pnr' => $data['pnr'] ?? '',
            'dep_date' => $data['dep_date'] ?? '',
            'trip_id' => $data['trip_id'] ?? '',
            'original_stock' => (int)($data['original_stock'] ?? 0),
            'current_stock' => (int)($data['current_stock'] ?? 0),
            'stock_release' => (int)($data['stock_release'] ?? 0),
            'stock_unuse' => (int)($data['stock_unuse'] ?? 0),
            'modified_date' => $now,
            'modified_by' => $updatedBy,
            'airline_code' => $data['airline_code'] ?? '',
            'route' => $data['route'] ?? '',
            'aud_fare' => $data['aud_fare'] ?? 0,
            'ind_fare' => $data['ind_fare'] ?? 0,
            'source' => $data['source'] ?? '',
            'route_type' => $data['route_type'] ?? '',
            'flight1' => $data['flight1'] ?? '',
            'flight2' => $data['flight2'] ?? '',
            'domestic_del' => (int)($data['domestic_del'] ?? 0),
            'domestic_amd' => (int)($data['domestic_amd'] ?? 0),
            'domestic_bom' => (int)($data['domestic_bom'] ?? 0),
            'domestic_ccu' => (int)($data['domestic_ccu'] ?? 0),
            'domestic_cok' => (int)($data['domestic_cok'] ?? 0),
            'domestic_maa' => (int)($data['domestic_maa'] ?? 0),
            'domestic_hyd' => (int)($data['domestic_hyd'] ?? 0),
            'domestic_blr' => (int)($data['domestic_blr'] ?? 0),
            'domestic_atq' => (int)($data['domestic_atq'] ?? 0),
            'sydney' => (int)($data['sydney'] ?? 0),
            'adelaide' => (int)($data['adelaide'] ?? 0)
        ];
        
        if (empty($stockData['pnr']) || empty($stockData['trip_id']) || empty($stockData['original_stock'])) {
            throw new Exception('PNR, trip_id, and original_stock are required', 400);
        }
        
        $this->stockDAL->createStock($stockData);
        
        return [
            'success' => true,
            'message' => 'Stock added'
        ];
    }

    /**
     * Update stock record
     */
    public function updateStock($autoId, $data, $updatedBy)
    {
        $now = date('Y-m-d H:i:s');
        
        $updateData = [];
        if (isset($data['pnr'])) $updateData['pnr'] = $data['pnr'];
        if (isset($data['dep_date'])) $updateData['dep_date'] = $data['dep_date'];
        if (isset($data['trip_id'])) $updateData['trip_id'] = $data['trip_id'];
        if (isset($data['original_stock'])) $updateData['original_stock'] = (int)$data['original_stock'];
        if (isset($data['current_stock'])) $updateData['current_stock'] = (int)$data['current_stock'];
        if (isset($data['stock_release'])) $updateData['stock_release'] = (int)$data['stock_release'];
        if (isset($data['stock_unuse'])) $updateData['stock_unuse'] = (int)$data['stock_unuse'];
        if (isset($data['airline_code'])) $updateData['airline_code'] = $data['airline_code'];
        if (isset($data['route'])) $updateData['route'] = $data['route'];
        if (isset($data['aud_fare'])) $updateData['aud_fare'] = (float)$data['aud_fare'];
        if (isset($data['ind_fare'])) $updateData['ind_fare'] = (float)$data['ind_fare'];
        if (isset($data['source'])) $updateData['source'] = $data['source'];
        if (isset($data['route_type'])) $updateData['route_type'] = $data['route_type'];
        if (isset($data['flight1'])) $updateData['flight1'] = $data['flight1'];
        if (isset($data['flight2'])) $updateData['flight2'] = $data['flight2'];
        
        $updateData['modified_date'] = $now;
        $updateData['modified_by'] = $updatedBy;
        
        $this->stockDAL->updateStock($autoId, $updateData);
        
        return [
            'success' => true,
            'message' => 'Stock updated'
        ];
    }

    /**
     * Delete stock record
     */
    public function deleteStock($autoId)
    {
        $this->stockDAL->deleteStock($autoId);
        
        return [
            'success' => true,
            'message' => 'Stock Deleted'
        ];
    }

    /**
     * Preview stock release
     */
    public function previewStockRelease()
    {
        $currentDate = date('Y-m-d') . ' 00:00:00';
        $currentDatePlusThreeStart = date('Y-m-d', strtotime('+3 days')) . ' 00:00:00';
        $currentDatePlusThreeEnd = date('Y-m-d', strtotime('+3 days')) . ' 23:59:59';
        $currentDatePlus30Start = date('Y-m-d', strtotime('+30 days')) . ' 00:00:00';
        $currentDatePlus30End = date('Y-m-d', strtotime('+30 days')) . ' 23:59:59';
        
        $stocks = $this->stockDAL->getStockReleasePreview(
            $currentDatePlusThreeStart,
            $currentDatePlusThreeEnd,
            $currentDatePlus30Start,
            $currentDatePlus30End
        );
        
        $preview = [];
        foreach ($stocks as $stock) {
            $tripCode = $stock['trip_id'];
            $depDate = $stock['dep_date'];
            $currentStock = (int)$stock['current_stock'];
            $stockUnuseOriginal = (int)($stock['stock_unuse'] ?? 0);
            
            $paxCount = $this->stockDAL->getTotalBookedPax($tripCode, $depDate);
            
            $remainingSeats = $stockUnuseOriginal + ($currentStock - $paxCount);
            $stockUnuseNew = $remainingSeats;
            $currentStockNew = $currentStock - ($currentStock - $paxCount);
            
            $productInfo = $this->stockDAL->getProductInfo($tripCode, $depDate);
            $pricingId = $productInfo['pricing_id'] ?? '';
            $productId = $productInfo['product_id'] ?? '';
            
            $maxPaxOriginal = 0;
            if ($pricingId) {
                $pricing = $this->stockDAL->getPricingById($pricingId);
                $maxPaxOriginal = (int)($pricing['max_pax'] ?? 0);
            }
            
            $preview[] = [
                'source' => $stock['source'],
                'pnr' => $stock['pnr'],
                'dep_date' => date('Y-m-d', strtotime($depDate)),
                'trip_code' => $tripCode,
                'current_stock' => $currentStock,
                'stock_unuse_original' => $stockUnuseOriginal,
                'current_stock_new' => $currentStockNew,
                'stock_unuse_new' => $stockUnuseNew,
                'pricing_id' => $pricingId,
                'product_id' => $productId,
                'booking_pax' => $paxCount,
                'max_pax_original' => $maxPaxOriginal
            ];
        }
        
        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Apply stock release
     */
    public function applyStockRelease($records)
    {
        if (empty($records)) {
            throw new Exception('No records provided', 400);
        }
        
        $updatedCount = 0;
        
        foreach ($records as $record) {
            $pnr = $record['pnr'] ?? '';
            $depDate = $record['dep_date'] ?? '';
            $tripId = $record['trip_id'] ?? '';
            $currentStockNew = (int)($record['current_stock_new'] ?? 0);
            $stockUnuseNew = (int)($record['stock_unuse_new'] ?? 0);
            $pricingId = $record['pricing_id'] ?? '';
            $productId = $record['product_id'] ?? '';
            
            if (empty($pnr) || empty($tripId)) {
                continue;
            }
            
            $this->stockDAL->updateStockRelease($tripId, $depDate, $pnr, $currentStockNew, $stockUnuseNew);
            
            if ($pricingId && $productId) {
                $this->stockDAL->updatePricingMaxPax($pricingId, $productId, $currentStockNew);
            }
            
            $updatedCount++;
        }
        
        return [
            'success' => true,
            'message' => 'Update successful',
            'updated_count' => $updatedCount
        ];
    }

    /**
     * Create stock release note
     */
    public function createStockReleaseNote($data, $releaseBy)
    {
        $now = date('Y-m-d H:i:s');
        
        $releaseNoteData = [
            'stock_id' => $data['stock_id'] ?? '',
            'pnr' => $data['pnr'] ?? '',
            'released_type' => $data['released_type'] ?? '',
            'description' => $data['description'] ?? '',
            'released_date' => $data['released_date'] ?? $now,
            'no_of_seat_released' => (int)($data['no_of_seat_released'] ?? 0),
            'amount_lost' => (float)($data['amount_lost'] ?? 0),
            'total_amount' => (float)($data['total_amount'] ?? 0),
            'added_by' => $releaseBy
        ];
        
        if (empty($releaseNoteData['stock_id']) || empty($releaseNoteData['pnr'])) {
            throw new Exception('stock_id and pnr are required', 400);
        }
        
        $this->stockDAL->createStockReleaseNote($releaseNoteData);
        
        // Update stock_release
        $currentStockRelease = (int)($data['current_stock_release'] ?? 0);
        $noOfSeats = $releaseNoteData['no_of_seat_released'];
        $updatedStockRelease = $currentStockRelease + $noOfSeats;
        
        $stock = $this->stockDAL->getStockById($releaseNoteData['stock_id']);
        if ($stock) {
            $updateData = ['stock_release' => $updatedStockRelease];
            $this->stockDAL->updateStock($releaseNoteData['stock_id'], $updateData, $releaseBy);
        }
        
        // Insert history
        $this->stockDAL->insertHistoryUpdate(
            $releaseNoteData['pnr'],
            'Stock Release',
            (string)$currentStockRelease,
            $releaseBy,
            $now
        );
        $this->stockDAL->insertHistoryUpdate(
            $releaseNoteData['pnr'],
            'no of seats',
            (string)$noOfSeats,
            $releaseBy,
            $now
        );
        
        return [
            'success' => true,
            'message' => 'Stock release note added'
        ];
    }

    /**
     * List stock release notes
     */
    public function listStockReleaseNotes($filters = [])
    {
        $notes = $this->stockDAL->listStockReleaseNotes($filters);
        
        return [
            'success' => true,
            'count' => count($notes),
            'release_notes' => $notes
        ];
    }

    /**
     * Get stock release notes by stock ID
     */
    public function getStockReleaseNotesByStockId($stockId)
    {
        $notes = $this->stockDAL->getStockReleaseNotesByStockId($stockId);
        
        return [
            'success' => true,
            'count' => count($notes),
            'release_notes' => $notes
        ];
    }

    /**
     * Update stock release note
     */
    public function updateStockReleaseNote($id, $data)
    {
        $updateData = [];
        if (isset($data['released_type'])) $updateData['released_type'] = $data['released_type'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['no_of_seat_released'])) $updateData['no_of_seat_released'] = (int)$data['no_of_seat_released'];
        if (isset($data['amount_lost'])) $updateData['amount_lost'] = (float)$data['amount_lost'];
        if (isset($data['total_amount'])) $updateData['total_amount'] = (float)$data['total_amount'];
        
        if (empty($updateData)) {
            throw new Exception('No fields to update', 400);
        }
        
        $this->stockDAL->updateStockReleaseNote($id, $updateData);
        
        return [
            'success' => true,
            'message' => 'Note updated'
        ];
    }

    /**
     * Bulk update stock records
     */
    public function bulkUpdateStock($updates, $updatedBy)
    {
        if (empty($updates)) {
            throw new Exception('No updates provided', 400);
        }
        
        $updatedCount = 0;
        $now = date('Y-m-d H:i:s');
        
        foreach ($updates as $update) {
            $autoId = $update['auto_id'] ?? null;
            $fields = $update['fields'] ?? [];
            
            if (empty($autoId) || empty($fields)) {
                continue;
            }
            
            // Get current stock record
            $currentStock = $this->stockDAL->getStockById($autoId);
            if (!$currentStock) {
                continue;
            }
            
            $pnr = $currentStock['pnr'];
            $depDate = date('Y-m-d', strtotime($currentStock['dep_date']));
            $tripId = $currentStock['trip_id'];
            
            // Prepare update data
            $updateData = [];
            $hasChanges = false;
            
            foreach ($fields as $fieldName => $newValue) {
                $oldValue = $currentStock[$fieldName] ?? null;
                
                // Only update if value changed
                if ($oldValue != $newValue) {
                    $updateData[$fieldName] = $newValue;
                    $hasChanges = true;
                    
                    // Handle special fields
                    if (in_array($fieldName, ['stock_release', 'stock_unuse', 'current_stock', 'release_on_amadeus', 'blocked_seat'])) {
                        // Get product info for pricing_id
                        $productInfo = $this->stockDAL->getProductInfo($tripId, $currentStock['dep_date']);
                        $pricingId = $productInfo['pricing_id'] ?? null;
                        
                        if ($pricingId) {
                            $difference = (int)$newValue - (int)$oldValue;
                            $this->stockDAL->insertSeatAvailabilityLog(
                                $pricingId,
                                $oldValue,
                                $newValue,
                                $now,
                                $fieldName,
                                $difference
                            );
                        }
                    }
                    
                    // Log to travel booking update history
                    if (in_array($fieldName, ['stock_release', 'stock_unuse', 'remarks', 'blocked_seat'])) {
                        $this->stockDAL->insertTravelBookingUpdateHistory(
                            $pnr,
                            $fieldName,
                            $newValue,
                            $now,
                            $updatedBy
                        );
                    }
                    
                    // Update release_date if stock_release or stock_unuse changed
                    if (in_array($fieldName, ['stock_release', 'stock_unuse'])) {
                        $this->stockDAL->updateStockReleaseDate($autoId, $now);
                    }
                }
            }
            
            if ($hasChanges) {
                // Update modified_date and modified_by for non-remarks fields
                if (!isset($updateData['remarks'])) {
                    $updateData['modified_date'] = $now;
                    $updateData['modified_by'] = $updatedBy;
                }
                
                $this->stockDAL->updateStock($autoId, $updateData);
                $updatedCount++;
            }
        }
        
        return [
            'success' => true,
            'message' => 'Bulk update successful',
            'updated_count' => $updatedCount
        ];
    }
}

