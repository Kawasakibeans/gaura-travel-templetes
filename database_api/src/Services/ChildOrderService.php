<?php

namespace App\Services;

use App\DAL\ChildOrderDAL;

class ChildOrderService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new ChildOrderDAL();
    }

    /**
     * Get child orders
     */
    public function getChildOrders(array $params): array
    {
        $tripCodePattern = $params['trip_code_pattern'] ?? '%SQ%';
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;
        $limit = isset($params['limit']) && is_numeric($params['limit']) ? (int)$params['limit'] : 1000;
        $offset = isset($params['offset']) && is_numeric($params['offset']) ? (int)$params['offset'] : 0;
        
        $orders = $this->dal->getChildOrders($tripCodePattern, $startDate, $endDate, $limit, $offset);
        $total = $this->dal->getChildOrdersCount($tripCodePattern, $startDate, $endDate);
        
        return [
            'orders' => $orders,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
}

