<?php
/**
 * Domestic Stock Service - Business Logic Layer
 * Handles flight inventory/stock management
 */

namespace App\Services;

use App\DAL\DomesticStockDAL;
use Exception;

class DomesticStockService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new DomesticStockDAL();
    }

    /**
     * Get stock by trip code and date with availability analysis
     */
    public function getStockByTripAndDate($tripCode, $depDate, $pnr = null, $intStatus = null, $domStatus = null)
    {
        if (empty($tripCode) || empty($depDate)) {
            throw new Exception('Trip code and departure date are required', 400);
        }

        // Get international stock (short PNR < 7)
        $internationalStock = $this->dal->getInternationalStock($tripCode, $depDate, $pnr);
        
        // Get domestic stock (long PNR > 9)
        $domesticStock = $this->dal->getDomesticStock($tripCode, $depDate, $pnr);

        // Process and enrich each stock record
        $processedInternational = $this->processStockRecords($internationalStock, 'international');
        $processedDomestic = $this->processStockRecords($domesticStock, 'domestic');

        // Filter by status if provided
        if ($intStatus) {
            $processedInternational = $this->filterByStatus($processedInternational, $intStatus);
        }
        if ($domStatus) {
            $processedDomestic = $this->filterByStatus($processedDomestic, $domStatus);
        }

        return [
            'trip_code' => $tripCode,
            'departure_date' => $depDate,
            'international_stock' => $processedInternational,
            'domestic_stock' => $processedDomestic,
            'international_count' => count($processedInternational),
            'domestic_count' => count($processedDomestic),
            'filters' => [
                'trip_code' => $tripCode,
                'dep_date' => $depDate,
                'pnr' => $pnr,
                'int_status' => $intStatus,
                'dom_status' => $domStatus
            ]
        ];
    }

    /**
     * Get stock by PNR
     */
    public function getStockByPNR($pnr, $stockType = 'both')
    {
        if (empty($pnr)) {
            throw new Exception('PNR is required', 400);
        }

        $results = [];

        if ($stockType === 'international' || $stockType === 'both') {
            $internationalStock = $this->dal->getInternationalStockByPNR($pnr);
            $results['international_stock'] = $this->processStockRecords($internationalStock, 'international');
        }

        if ($stockType === 'domestic' || $stockType === 'both') {
            $domesticStock = $this->dal->getDomesticStockByPNR($pnr);
            $results['domestic_stock'] = $this->processStockRecords($domesticStock, 'domestic');
        }

        return [
            'pnr' => $pnr,
            'stock_type' => $stockType,
            'results' => $results
        ];
    }

    /**
     * Update international stock
     */
    public function updateInternationalStock($id, $data)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid stock ID is required', 400);
        }

        $stock = $this->dal->getInternationalStockById($id);
        if (!$stock) {
            throw new Exception('Stock record not found', 404);
        }

        // Track changes for history
        $updateHistory = [];
        $trackableFields = ['stock_release', 'stock_unuse', 'blocked_seat'];
        
        foreach ($trackableFields as $field) {
            if (isset($data[$field]) && $data[$field] != $stock[$field]) {
                $updateHistory[] = [
                    'field' => $field,
                    'old_value' => $stock[$field],
                    'new_value' => $data[$field]
                ];
            }
        }

        // Update stock
        $this->dal->updateInternationalStock($id, $data);

        // Log history for trackable fields
        if (!empty($updateHistory)) {
            foreach ($updateHistory as $change) {
                $this->dal->logStockUpdate($stock['pnr'], $change['field'], $change['new_value'], $data['updated_by'] ?? 'system');
            }
        }

        // Update release date if stock_release or stock_unuse changed
        if (isset($data['stock_release']) || isset($data['stock_unuse'])) {
            $this->dal->updateReleaseDate($id, 'international');
        }

        return [
            'stock_id' => $id,
            'pnr' => $stock['pnr'],
            'updates' => $updateHistory,
            'message' => 'International stock updated successfully'
        ];
    }

    /**
     * Update domestic stock
     */
    public function updateDomesticStock($id, $data)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid stock ID is required', 400);
        }

        $stock = $this->dal->getDomesticStockById($id);
        if (!$stock) {
            throw new Exception('Stock record not found', 404);
        }

        // Track changes
        $updateHistory = [];
        $trackableFields = ['stock_release', 'stock_unuse', 'blocked_seat'];
        
        foreach ($trackableFields as $field) {
            if (isset($data[$field]) && $data[$field] != $stock[$field]) {
                $updateHistory[] = [
                    'field' => $field,
                    'old_value' => $stock[$field],
                    'new_value' => $data[$field]
                ];
            }
        }

        // Update stock
        $this->dal->updateDomesticStock($id, $data);

        // Log history
        if (!empty($updateHistory)) {
            foreach ($updateHistory as $change) {
                $this->dal->logStockUpdate($stock['pnr'], $change['field'], $change['new_value'], $data['updated_by'] ?? 'system');
            }
        }

        // Update release date
        if (isset($data['stock_release']) || isset($data['stock_unuse'])) {
            $this->dal->updateReleaseDate($id, 'domestic');
        }

        return [
            'stock_id' => $id,
            'pnr' => $stock['pnr'],
            'updates' => $updateHistory,
            'message' => 'Domestic stock updated successfully'
        ];
    }

    /**
     * Private helper methods
     */
    
    private function processStockRecords($records, $type)
    {
        $processed = [];

        foreach ($records as $record) {
            $tripCode = $record['trip_id'];
            $depDate = $record['dep_date'];
            $pnr = $record['pnr'];

            // Get booking count for this trip
            $bookingCount = $this->dal->getBookingCount($tripCode, $depDate);

            // Calculate availability
            $currentStock = (int)$record['current_stock'];
            $stockRelease = (int)$record['stock_release'];
            $stockUnuse = (int)$record['stock_unuse'];
            $blockedSeat = (int)$record['blocked_seat'];

            $availableStock = $currentStock - $stockRelease - $stockUnuse - $blockedSeat;
            $soldOut = ($availableStock <= 0) ? 'Sold-Out' : 'Remaining';

            $processed[] = [
                'auto_id' => $record['auto_id'],
                'pnr' => $pnr,
                'trip_id' => $tripCode,
                'dep_date' => $depDate,
                'source' => $record['source'] ?? null,
                'original_stock' => (int)$record['original_stock'],
                'current_stock' => $currentStock,
                'stock_release' => $stockRelease,
                'stock_unuse' => $stockUnuse,
                'blocked_seat' => $blockedSeat,
                'available_stock' => $availableStock,
                'status' => $soldOut,
                'booking_count' => $bookingCount,
                'release_date' => $record['release_date'] ?? null,
                'stock_type' => $type
            ];
        }

        return $processed;
    }

    private function filterByStatus($records, $status)
    {
        return array_values(array_filter($records, function($record) use ($status) {
            return $record['status'] === $status;
        }));
    }
}

