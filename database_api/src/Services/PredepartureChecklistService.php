<?php
/**
 * Predeparture Checklist Service Layer
 * 
 * Handles business logic for predeparture checklist operations
 */

namespace App\Services;

use App\DAL\PredepartureChecklistDAL;

class PredepartureChecklistService {
    private $dal;

    public function __construct(PredepartureChecklistDAL $dal = null) {
        // If DAL is not provided, create it with default database connection
        if ($dal === null) {
            global $pdo;
            if (!isset($pdo)) {
                // Database connection
                $servername = "localhost";
                $username   = "gaurat_sriharan";
                $password   = "r)?2lc^Q0cAE";
                $dbname     = "gaurat_gauratravel";
                
                $pdo = new \PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                ]);
            }
            $this->dal = new PredepartureChecklistDAL($pdo);
        } else {
            $this->dal = $dal;
        }
    }

    /**
     * Get bookings with filters
     */
    public function getBookings($filters = []) {
        $bookings = $this->dal->getBookings($filters);
        $total = $this->dal->countBookings($filters);
        
        return [
            'bookings' => $bookings,
            'total' => $total,
            'count' => count($bookings),
            'limit' => $filters['limit'] ?? 20
        ];
    }

    /**
     * Get booking details by order ID
     */
    public function getBookingDetails($orderId) {
        $bookings = $this->dal->getBookingByOrderId($orderId);
        
        if (empty($bookings)) {
            throw new \Exception("Booking not found with order ID: $orderId", 404);
        }
        
        return $bookings;
    }

    /**
     * Get PAX list for an order
     */
    public function getPaxList($orderId, $productId = null, $coOrderId = null) {
        return $this->dal->getPaxByOrderId($orderId, $productId, $coOrderId);
    }

    /**
     * Get checklist categories
     */
    public function getChecklistCategories() {
        return $this->dal->getChecklistCategories();
    }

    /**
     * Get checklist for an order and pax
     */
    public function getChecklist($orderId, $paxId) {
        // Get checklist categories
        $categories = $this->dal->getChecklistCategories();
        
        // Get existing checklist items
        $existingItems = $this->dal->getChecklistItems($orderId, $paxId);
        
        // Create a map of existing items by check_title
        $itemsMap = [];
        foreach ($existingItems as $item) {
            $itemsMap[$item['check_title']] = $item;
        }
        
        // Build checklist structure
        $checklist = [];
        foreach ($categories as $category) {
            $checkTitle = str_replace(" ", "_", strtolower($category['option_value']));
            $existing = $itemsMap[$checkTitle] ?? null;
            
            $checklist[] = [
                'category' => $category['option_value'],
                'check_title' => $checkTitle,
                'check_value' => $existing['check_value'] ?? null,
                'check_outcome' => $existing['check_outcome'] ?? null,
                'updated_by' => $existing['updated_by'] ?? null,
                'updated_on' => $existing['updated_on'] ?? null
            ];
        }
        
        return $checklist;
    }

    /**
     * Update checklist item
     */
    public function updateChecklistItem($orderId, $paxId, $checkTitle, $checkValue, $checkOutcome, $updatedBy = 'api') {
        // Validate inputs
        if (empty($orderId) || empty($paxId) || empty($checkTitle)) {
            throw new \Exception('Missing required fields: order_id, pax_id, check_title', 400);
        }

        $this->dal->beginTransaction();
        
        try {
            // Upsert checklist item
            $this->dal->upsertChecklistItem($orderId, $paxId, $checkTitle, $checkValue, $checkOutcome, $updatedBy);
            
            // Mark check as done if checkbox is checked
            if ($checkValue === 'yes') {
                $this->dal->markCheckDone($paxId);
            }
            
            $this->dal->commit();
            
            return [
                'success' => true,
                'message' => 'Checklist item updated successfully'
            ];
        } catch (\Exception $e) {
            $this->dal->rollback();
            throw $e;
        }
    }

    /**
     * Update multiple checklist items (bulk update)
     */
    public function updateChecklistItems($orderId, $paxId, $items, $updatedBy = 'api') {
        if (empty($orderId) || empty($paxId) || empty($items)) {
            throw new \Exception('Missing required fields: order_id, pax_id, items', 400);
        }

        if (!is_array($items)) {
            throw new \Exception('Items must be an array', 400);
        }

        $this->dal->beginTransaction();
        
        try {
            $updated = 0;
            $hasCheckedItem = false;

            foreach ($items as $item) {
                if (empty($item['check_title'])) {
                    continue;
                }

                $checkTitle = $item['check_title'];
                $checkValue = $item['check_value'] ?? '';
                $checkOutcome = $item['check_outcome'] ?? '';

                $this->dal->upsertChecklistItem($orderId, $paxId, $checkTitle, $checkValue, $checkOutcome, $updatedBy);
                
                if ($checkValue === 'yes') {
                    $hasCheckedItem = true;
                }
                
                $updated++;
            }

            // Mark check as done if any item is checked
            if ($hasCheckedItem) {
                $this->dal->markCheckDone($paxId);
            }
            
            $this->dal->commit();
            
            return [
                'success' => true,
                'updated' => $updated,
                'message' => "Updated {$updated} checklist item(s) successfully"
            ];
        } catch (\Exception $e) {
            $this->dal->rollback();
            throw $e;
        }
    }

    /**
     * Get full checklist view for an order (all bookings and PAX)
     */
    public function getOrderChecklist($orderId) {
        $bookings = $this->dal->getBookingByOrderId($orderId);
        
        if (empty($bookings)) {
            throw new \Exception("Booking not found with order ID: $orderId", 404);
        }

        $result = [];
        $categories = $this->dal->getChecklistCategories();

        foreach ($bookings as $booking) {
            $paxList = $this->dal->getPaxByOrderId($orderId, $booking['product_id'], $booking['co_order_id']);
            
            $bookingData = [
                'order_id' => $booking['order_id'],
                'product_id' => $booking['product_id'],
                'co_order_id' => $booking['co_order_id'],
                'product_title' => $booking['product_title'],
                'trip_code' => $booking['trip_code'],
                'travel_date' => $booking['travel_date'],
                'pax' => []
            ];

            foreach ($paxList as $pax) {
                $checklist = $this->getChecklist($orderId, $pax['auto_id']);
                
                $bookingData['pax'][] = [
                    'pax_id' => $pax['auto_id'],
                    'fname' => $pax['fname'],
                    'lname' => $pax['lname'],
                    'is_pre_departure_check_done' => $pax['is_pre_departure_check_done'] ?? null,
                    'checklist' => $checklist
                ];
            }

            $result[] = $bookingData;
        }

        return $result;
    }
}

