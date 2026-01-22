<?php
/**
 * Product Service Layer
 * 
 * Handles business logic for product management operations
 */

namespace App\Services;

use App\DAL\ProductDAL;

class ProductService {
    private $dal;

    public function __construct(ProductDAL $dal = null) {
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
            $this->dal = new ProductDAL($pdo);
        } else {
            $this->dal = $dal;
        }
    }

    /**
     * Get available products for insertion
     */
    public function getAvailableProducts() {
        return $this->dal->getAvailableProducts();
    }

    /**
     * Insert products (bulk insert from available products)
     */
    public function insertProducts($addedBy = 'api') {
        $availableProducts = $this->dal->getAvailableProducts();
        $inserted = 0;
        $errors = [];

        $this->dal->beginTransaction();
        
        try {
            foreach ($availableProducts as $product) {
                if ($product['end_date'] == '0000-00-00') {
                    continue;
                }

                $tripId = $product['id'];
                $pricingId = (int)$product['pricing_ids'];
                $travelDate = date('Y-m-d', strtotime($product['end_date'])) . ' 00:00:00';
                $tripCode = $product['trip_code'];

                // Get product details
                $productDetails = $this->dal->getProductDetails($tripId);
                if (!$productDetails) {
                    $errors[] = "Product details not found for trip ID: $tripId";
                    continue;
                }

                // Get itinerary
                $itinerary = $this->dal->getProductItinerary($tripId);

                $data = [
                    'product_id' => $tripId,
                    'product_title' => $productDetails['post_title'],
                    'product_url' => $productDetails['post_name'],
                    'pricing_id' => $pricingId,
                    'trip_code' => $tripCode,
                    'travel_date' => $travelDate,
                    'travel_time' => '',
                    'itinerary' => $itinerary
                ];

                if ($this->dal->insertProduct($data)) {
                    $inserted++;
                } else {
                    $errors[] = "Failed to insert product ID: $tripId";
                }
            }

            $this->dal->commit();
            
            return [
                'inserted' => $inserted,
                'total_available' => count($availableProducts),
                'errors' => $errors
            ];
        } catch (Exception $e) {
            $this->dal->rollback();
            throw $e;
        }
    }

    /**
     * Get products with filters
     */
    public function getProducts($filters = []) {
        $products = $this->dal->getProducts($filters);
        $total = $this->dal->countProducts($filters);
        
        return [
            'products' => $products,
            'total' => $total,
            'count' => count($products),
            'limit' => $filters['limit'] ?? 20,
            'offset' => $filters['offset'] ?? 0
        ];
    }

    /**
     * Get product by ID
     */
    public function getProductById($autoId) {
        $product = $this->dal->getProductById($autoId);
        
        if (!$product) {
            throw new \Exception("Product not found with ID: $autoId", 404);
        }
        
        return $product;
    }

    /**
     * Create a new product
     */
    public function createProduct($data) {
        // Validate required fields
        $required = ['product_id', 'product_title', 'product_url', 'pricing_id', 'trip_code', 'travel_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Missing required field: $field", 400);
            }
        }

        // Format travel_date if needed
        if (isset($data['travel_date']) && !strpos($data['travel_date'], ' ')) {
            $data['travel_date'] = $data['travel_date'] . ' 00:00:00';
        }

        if ($this->dal->insertProduct($data)) {
            return ['success' => true, 'message' => 'Product created successfully'];
        }
        
        throw new \Exception('Failed to create product', 500);
    }

    /**
     * Update product
     */
    public function updateProduct($autoId, $data) {
        // Check if product exists
        $existing = $this->dal->getProductById($autoId);
        if (!$existing) {
            throw new \Exception('Product not found', 404);
        }

        // Format travel_date if needed
        if (isset($data['travel_date']) && !strpos($data['travel_date'], ' ')) {
            $data['travel_date'] = $data['travel_date'] . ' 00:00:00';
        }

        // Merge with existing data to preserve fields not being updated
        $updateData = array_merge($existing, $data);
        
        if ($this->dal->updateProduct($autoId, $updateData)) {
            return ['success' => true, 'message' => 'Product updated successfully'];
        }
        
        throw new \Exception('Failed to update product', 500);
    }

    /**
     * Delete product
     */
    public function deleteProduct($autoId) {
        // Check if product exists
        $existing = $this->dal->getProductById($autoId);
        if (!$existing) {
            throw new \Exception('Product not found', 404);
        }

        if ($this->dal->deleteProduct($autoId)) {
            return ['success' => true, 'message' => 'Product deleted successfully'];
        }
        
        throw new \Exception('Failed to delete product', 500);
    }
}

