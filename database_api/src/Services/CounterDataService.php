<?php

namespace App\Services;

use App\DAL\CounterDataDAL;

class CounterDataService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new CounterDataDAL();
    }

    /**
     * Get booking details
     */
    public function getBookingDetails(array $params): array
    {
        $productId = $params['product_id'] ?? null;
        $arrivalDate = $params['arrival_date'] ?? null;
        
        if (!$productId || !$arrivalDate) {
            throw new \Exception('product_id and arrival_date are required');
        }
        
        $paymentStatuses = null;
        if (isset($params['payment_status'])) {
            $paymentStatuses = is_array($params['payment_status']) 
                ? $params['payment_status'] 
                : [$params['payment_status']];
        }
        
        $limit = isset($params['limit']) && is_numeric($params['limit']) ? (int)$params['limit'] : 1000;
        $offset = isset($params['offset']) && is_numeric($params['offset']) ? (int)$params['offset'] : 0;
        
        $bookings = $this->dal->getBookingDetails($productId, $arrivalDate, $paymentStatuses, $limit, $offset);
        $total = $this->dal->getBookingDetailsCount($productId, $arrivalDate, $paymentStatuses);
        
        return [
            'bookings' => $bookings,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
}

