<?php
/**
 * Pricing Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\PricingDAL;
use Exception;

class PricingService
{
    private $pricingDAL;

    public function __construct()
    {
        $this->pricingDAL = new PricingDAL();
    }

    /**
     * Convert date from d/m/Y to Y-m-d
     */
    private function convertDate($date)
    {
        if (empty($date)) {
            return '';
        }
        
        $dateObj = \DateTime::createFromFormat('d/m/Y', $date);
        if ($dateObj === false) {
            return '';
        }
        
        return $dateObj->format('Y-m-d');
    }

    /**
     * Preview pricing import
     */
    public function previewPricing($csvData)
    {
        if (empty($csvData)) {
            throw new Exception('CSV data is required', 400);
        }
        
        $preview = [];
        $autonumber = 1;
        
        foreach ($csvData as $row) {
            // Skip header row
            if (($row[0] ?? '') === 'ID' && ($row[1] ?? '') === 'Title') {
                continue;
            }
            
            $pricingId = $row[0] ?? '';
            $title = $row[1] ?? '';
            $minPax = $row[2] ?? '';
            $maxPax = $row[3] ?? '';
            $tripExtras = $row[4] ?? '';
            
            if (empty($pricingId)) {
                continue;
            }
            
            // Check if pricing exists
            $existing = $this->pricingDAL->checkPricingExists($pricingId);
            
            $matchHidden = 'New';
            $match = 'New';
            $checked = false;
            
            if ($existing && $existing['id'] == $pricingId) {
                $matchHidden = 'Existing';
                $match = 'Existing & will be overwrite';
                $checked = true;
            }
            
            $preview[] = [
                'autonumber' => $autonumber,
                'pricing_id' => $pricingId,
                'title' => $title,
                'min_pax' => $minPax,
                'max_pax' => $maxPax,
                'trip_extras' => $tripExtras,
                'match_status' => $matchHidden,
                'match' => $match,
                'checked' => $checked
            ];
            
            $autonumber++;
        }
        
        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Import pricing records
     */
    public function importPricing($records, $updatedBy)
    {
        if (empty($records)) {
            throw new Exception('No records provided for import', 400);
        }
        
        $importedCount = 0;
        $now = date('Y-m-d H:i:s');
        
        foreach ($records as $record) {
            $pricingId = $record['pricing_id'] ?? '';
            $title = $record['title'] ?? '';
            $minPax = $record['min_pax'] ?? '';
            $maxPax = $record['max_pax'] ?? '';
            $tripExtras = $record['trip_extras'] ?? '';
            $matchHidden = $record['match_hidden'] ?? '';
            
            if (empty($pricingId)) {
                continue;
            }
            
            // Replace _@_ with spaces in title
            $title = str_replace('_@_', ' ', $title);
            
            $pricingData = [
                'id' => $pricingId,
                'title' => $title,
                'min_pax' => $minPax,
                'max_pax' => $maxPax,
                'trip_extras' => $tripExtras
            ];
            
            if ($matchHidden === 'New') {
                $this->pricingDAL->insertPricing($pricingData);
            } else {
                $this->pricingDAL->updatePricing($pricingData);
            }
            
            // Insert history updates
            $this->pricingDAL->insertHistoryUpdate($pricingId, 'pricing_id', $pricingId, $updatedBy, $now);
            $this->pricingDAL->insertHistoryUpdate($pricingId, 'title', $title, $updatedBy, $now);
            $this->pricingDAL->insertHistoryUpdate($pricingId, 'min_pax', $minPax, $updatedBy, $now);
            $this->pricingDAL->insertHistoryUpdate($pricingId, 'max_pax', $maxPax, $updatedBy, $now);
            $this->pricingDAL->insertHistoryUpdate($pricingId, 'trip_extras', $tripExtras, $updatedBy, $now);
            
            $importedCount++;
        }
        
        return [
            'success' => true,
            'message' => 'Updated successfully',
            'imported_count' => $importedCount
        ];
    }

    /**
     * Preview category relation import
     */
    public function previewCategoryRelation($csvData)
    {
        if (empty($csvData)) {
            throw new Exception('CSV data is required', 400);
        }
        
        $preview = [];
        $autonumber = 1;
        
        foreach ($csvData as $row) {
            // Skip header row
            if (($row[0] ?? '') === 'pricing_id' && ($row[1] ?? '') === 'pricing_category_id') {
                continue;
            }
            
            $pricingId = (int)($row[0] ?? 0);
            $categoryRelationId = (int)($row[1] ?? 0);
            $regularPrice = $row[2] ?? '';
            $salePrice = $row[3] ?? '';
            $isSale = $row[4] ?? '';
            
            if (empty($pricingId) || empty($categoryRelationId)) {
                continue;
            }
            
            // Check if category relation exists
            $existing = $this->pricingDAL->checkCategoryRelationExists($pricingId, $categoryRelationId);
            
            $matchHidden = 'New';
            $match = 'New';
            $checked = false;
            $idFromTable = '';
            
            if ($existing) {
                $matchHidden = 'Existing';
                $match = 'Existing & will be overwrite';
                $checked = true;
                $idFromTable = $existing['id'] ?? '';
            }
            
            $preview[] = [
                'autonumber' => $autonumber,
                'pricing_id' => $pricingId,
                'pricing_category_id' => $categoryRelationId,
                'regular_price' => $regularPrice,
                'sale_price' => $salePrice,
                'is_sale' => $isSale,
                'match_status' => $matchHidden,
                'match' => $match,
                'checked' => $checked,
                'id_from_table' => $idFromTable
            ];
            
            $autonumber++;
        }
        
        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Preview category relation with price validation
     */
    public function previewCategoryRelationWithValidation($csvData, $mup = 170)
    {
        if (empty($csvData)) {
            throw new Exception('CSV data is required', 400);
        }
        
        $preview = [];
        $autonumber = 1;
        
        foreach ($csvData as $row) {
            // Skip header row
            if (($row[0] ?? '') === 'pricing_id' && ($row[1] ?? '') === 'pricing_category_id') {
                continue;
            }
            
            $pricingId = (int)($row[0] ?? 0);
            $categoryRelationId = (int)($row[1] ?? 0);
            $regularPrice = $row[2] ?? '';
            $salePrice = (float)($row[3] ?? 0);
            $isSale = $row[4] ?? '';
            
            if (empty($pricingId) || empty($categoryRelationId)) {
                continue;
            }
            
            // Check if category relation exists
            $existing = $this->pricingDAL->checkCategoryRelationExists($pricingId, $categoryRelationId);
            $idFromTable = $existing ? ($existing['id'] ?? '') : '';
            
            // Get product info
            $productInfo = $this->pricingDAL->getProductInfoByPricingId($pricingId);
            $tripCode = $productInfo ? ($productInfo['trip_code'] ?? '') : '';
            $travelDate = $productInfo ? ($productInfo['travel_dated'] ?? '') : '';
            
            // Get stock price
            $priceFromStocks = 0;
            if ($tripCode && $travelDate) {
                $priceFromStocks = $this->pricingDAL->getStockPrice($tripCode, $travelDate);
            }
            
            // Calculate difference and validate
            $difference = $salePrice - ($priceFromStocks + $mup);
            $isPriceValid = ($difference >= -10);
            
            $matchHidden = 'New';
            $match = 'New';
            $checked = false;
            
            if ($existing) {
                $matchHidden = 'Existing';
                $match = 'Existing & will be overwrite';
                $checked = true;
            }
            
            // Override with price validation result
            if ($isPriceValid) {
                $matchHidden = 'Existing';
                $match = '';
                $checked = true;
            } else {
                $matchHidden = 'New';
                $match = 'Recheck Price *';
                $checked = false;
            }
            
            $preview[] = [
                'autonumber' => $autonumber,
                'pricing_id' => $pricingId,
                'pricing_category_id' => $categoryRelationId,
                'regular_price' => $regularPrice,
                'sale_price' => $salePrice,
                'is_sale' => $isSale,
                'mup' => $mup,
                'price_from_stocks' => $priceFromStocks,
                'difference' => $difference,
                'is_price_valid' => $isPriceValid,
                'match_status' => $matchHidden,
                'match' => $match,
                'checked' => $checked,
                'id_from_table' => $idFromTable
            ];
            
            $autonumber++;
        }
        
        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Import category relation records
     */
    public function importCategoryRelation($records, $updatedBy)
    {
        if (empty($records)) {
            throw new Exception('No records provided for import', 400);
        }
        
        $importedCount = 0;
        $now = date('Y-m-d H:i:s');
        
        foreach ($records as $record) {
            $pricingId = $record['pricing_id'] ?? '';
            $categoryRelationId = $record['pricing_category_id'] ?? '';
            $regularPrice = $record['regular_price'] ?? '';
            $salePrice = $record['sale_price'] ?? '';
            $isSale = $record['is_sale'] ?? '';
            $matchHidden = $record['match_hidden'] ?? '';
            $idFromTable = $record['id_from_table'] ?? '';
            
            if (empty($pricingId) || empty($categoryRelationId)) {
                continue;
            }
            
            $categoryRelationData = [
                'pricing_id' => $pricingId,
                'pricing_category_id' => $categoryRelationId,
                'regular_price' => $regularPrice,
                'sale_price' => $salePrice,
                'is_sale' => $isSale
            ];
            
            if ($matchHidden === 'New') {
                $this->pricingDAL->insertCategoryRelation($categoryRelationData);
            } else {
                $categoryRelationData['id'] = $idFromTable;
                $this->pricingDAL->updateCategoryRelation($categoryRelationData);
            }
            
            // Insert history updates
            $this->pricingDAL->insertHistoryUpdate($pricingId, 'pricing_id', $pricingId, $updatedBy, $now);
            $this->pricingDAL->insertHistoryUpdate($pricingId, 'pricing_category_id', $categoryRelationId, $updatedBy, $now);
            $this->pricingDAL->insertHistoryUpdate($pricingId, 'regular_price', $regularPrice, $updatedBy, $now);
            $this->pricingDAL->insertHistoryUpdate($pricingId, 'sale_price', $salePrice, $updatedBy, $now);
            $this->pricingDAL->insertHistoryUpdate($pricingId, 'is_sale', $isSale, $updatedBy, $now);
            
            $importedCount++;
        }
        
        return [
            'success' => true,
            'message' => 'Updated successfully',
            'imported_count' => $importedCount
        ];
    }

    /**
     * Preview dates import
     */
    public function previewDates($csvData)
    {
        if (empty($csvData)) {
            throw new Exception('CSV data is required', 400);
        }
        
        $preview = [];
        $autonumber = 1;
        
        foreach ($csvData as $row) {
            // Skip header row
            if (($row[0] ?? '') === 'pricing_ids' && ($row[1] ?? '') === 'title') {
                continue;
            }
            
            $pricingIds = $row[0] ?? '';
            $title = $row[1] ?? '';
            $recurring = $row[2] ?? '';
            $years = $row[3] ?? '';
            $months = $row[4] ?? '';
            $dateDays = $row[5] ?? '';
            $startDate = $row[6] ?? '';
            $endDate = $row[7] ?? '';
            
            if (empty($pricingIds)) {
                continue;
            }
            
            // Convert dates
            $startDateBackend = $this->convertDate($startDate);
            $endDateBackend = $this->convertDate($endDate);
            
            // Check if date exists
            $existing = $this->pricingDAL->checkDateExists($pricingIds);
            
            $matchHidden = 'New';
            $match = 'New';
            $checked = false;
            $idFromTable = '';
            
            if ($existing && $existing['pricing_ids'] == $pricingIds) {
                $matchHidden = 'Existing';
                $match = 'Existing & will be overwrite';
                $checked = true;
                $idFromTable = $existing['id'] ?? '';
            }
            
            $preview[] = [
                'autonumber' => $autonumber,
                'pricing_id' => $pricingIds,
                'title' => $title,
                'recurring' => $recurring,
                'years' => $years,
                'months' => $months,
                'date_days' => $dateDays,
                'start_date' => $startDate,
                'start_date_backend' => $startDateBackend,
                'end_date' => $endDate,
                'end_date_backend' => $endDateBackend,
                'match_status' => $matchHidden,
                'match' => $match,
                'checked' => $checked,
                'id_from_table' => $idFromTable
            ];
            
            $autonumber++;
        }
        
        return ['success' => true, 'preview' => $preview];
    }

    /**
     * Import dates records
     */
    public function importDates($records, $updatedBy)
    {
        if (empty($records)) {
            throw new Exception('No records provided for import', 400);
        }
        
        $importedCount = 0;
        $now = date('Y-m-d H:i:s');
        
        foreach ($records as $record) {
            $pricingIds = $record['pricing_id'] ?? '';
            $title = $record['title'] ?? '';
            $recurring = $record['recurring'] ?? '';
            $years = $record['years'] ?? '';
            $months = $record['months'] ?? '';
            $dateDays = $record['date_days'] ?? '';
            $startDate = $record['start_date_backend'] ?? '';
            $endDate = $record['end_date_backend'] ?? '';
            $matchHidden = $record['match_hidden'] ?? '';
            
            if (empty($pricingIds)) {
                continue;
            }
            
            $dateData = [
                'pricing_ids' => $pricingIds,
                'title' => $title,
                'recurring' => $recurring,
                'years' => $years,
                'months' => $months,
                'date_days' => $dateDays,
                'start_date' => $startDate,
                'end_date' => $endDate
            ];
            
            if ($matchHidden === 'New') {
                $this->pricingDAL->insertDate($dateData);
            } else {
                $this->pricingDAL->updateDate($dateData);
            }
            
            // Insert history updates
            $this->pricingDAL->insertHistoryUpdate($pricingIds, 'pricing_id', $pricingIds, $updatedBy, $now);
            $this->pricingDAL->insertHistoryUpdate($pricingIds, 'title', $title, $updatedBy, $now);
            $this->pricingDAL->insertHistoryUpdate($pricingIds, 'recurring', $recurring, $updatedBy, $now);
            $this->pricingDAL->insertHistoryUpdate($pricingIds, 'years', $years, $updatedBy, $now);
            $this->pricingDAL->insertHistoryUpdate($pricingIds, 'months', $months, $updatedBy, $now);
            $this->pricingDAL->insertHistoryUpdate($pricingIds, 'date_days', $dateDays, $updatedBy, $now);
            $this->pricingDAL->insertHistoryUpdate($pricingIds, 'start_date', $startDate, $updatedBy, $now);
            $this->pricingDAL->insertHistoryUpdate($pricingIds, 'end_date', $endDate, $updatedBy, $now);
            
            $importedCount++;
        }
        
        return [
            'success' => true,
            'message' => 'Updated successfully',
            'imported_count' => $importedCount
        ];
    }
}

