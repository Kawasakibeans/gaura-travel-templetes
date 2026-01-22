<?php

namespace App\Services;

use App\DAL\FlightFITCheckoutDAL;

class FlightFITCheckoutService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new FlightFITCheckoutDAL();
    }

    /**
     * Get booking info by order ID
     */
    public function getBookingInfo(array $params): array
    {
        $orderId = $params['order_id'] ?? null;
        
        if (empty($orderId)) {
            throw new \Exception('order_id is required');
        }
        
        $orderIdInt = (int)$orderId;
        
        $pnr = $this->dal->getPnrByOrderId($orderIdInt);
        $agentConso = $this->dal->getAgentConsoByOrderId($orderIdInt);
        $orderDate = $this->dal->getOrderDateByOrderId($orderIdInt);
        
        // Format order date
        $orderDateFormatted = null;
        if ($orderDate) {
            $orderDateFormatted = date('Y-m-d', strtotime($orderDate));
        }
        
        return [
            'order_id' => $orderIdInt,
            'pnr' => $pnr,
            'agent_conso' => $agentConso,
            'order_date' => $orderDateFormatted
        ];
    }
    
    /**
     * Get airport info
     */
    public function getAirportInfo(array $params): array
    {
        $airportCode = $params['airport_code'] ?? null;
        
        if (empty($airportCode)) {
            throw new \Exception('airport_code is required');
        }
        
        $airportInfo = $this->dal->getAirportInfo($airportCode);
        
        if (!$airportInfo) {
            return [
                'airport_code' => $airportCode,
                'city' => '',
                'airport_name' => ''
            ];
        }
        
        return [
            'airport_code' => $airportCode,
            'city' => $airportInfo['city'] ?? '',
            'airport_name' => $airportInfo['airpotname'] ?? ''
        ];
    }
}

