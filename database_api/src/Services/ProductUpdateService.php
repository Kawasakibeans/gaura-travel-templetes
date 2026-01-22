<?php
/**
 * Product Update Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\ProductUpdateDAL;
use Exception;

class ProductUpdateService
{
    private $productUpdateDAL;

    public function __construct()
    {
        $this->productUpdateDAL = new ProductUpdateDAL();
    }

    /**
     * Update products to stock product manager table
     */
    public function updateProductsToTable()
    {
        $products = $this->productUpdateDAL->findMissingProducts();
        
        if (empty($products)) {
            return [
                'success' => true,
                'message' => 'No products to add. All products are already in the table.',
                'products_added' => 0,
                'details' => []
            ];
        }
        
        $added = 0;
        $details = [];
        $currentTime = date('Y-m-d H:i:s');
        
        foreach ($products as $product) {
            $tripId = $product['id'];
            $pricingIds = $product['pricing_ids'];
            $endDate = $product['end_date'];
            $tripCode = $product['trip_code'] ?? '';
            
            // Format travel date
            $travelDate = date('Y-m-d', strtotime($endDate)) . ' 00:00:00';
            
            // Get itinerary data
            $itineraryData = $this->productUpdateDAL->getProductItinerary($tripId);
            $itineraryContent = $itineraryData['meta_value'] ?? '';
            
            // Get product details
            $productDetails = $this->productUpdateDAL->getProductDetails($tripId);
            if (!$productDetails) {
                continue; // Skip if product not found
            }
            
            $title = $productDetails['post_title'] ?? '';
            $url = $productDetails['post_name'] ?? '';
            
            try {
                $this->productUpdateDAL->insertProductToStockManager([
                    'product_id' => $tripId,
                    'product_title' => $title,
                    'product_url' => $url,
                    'pricing_id' => $pricingIds,
                    'trip_code' => $tripCode,
                    'travel_date' => $travelDate,
                    'itinerary' => $itineraryContent,
                    'added_date' => $currentTime
                ]);
                
                $added++;
                $details[] = [
                    'product_id' => $tripId,
                    'pricing_id' => $pricingIds,
                    'trip_code' => $tripCode,
                    'product_title' => $title
                ];
            } catch (Exception $e) {
                error_log("Failed to add product {$tripId}: " . $e->getMessage());
                // Continue with next product
            }
        }
        
        return [
            'success' => true,
            'message' => 'Products has been added to table',
            'products_added' => $added,
            'details' => $details
        ];
    }
}

