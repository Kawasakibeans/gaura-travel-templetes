<?php

namespace App\Services;

use App\DAL\BackendFunctionsDAL;

class BackendFunctionsService
{
    private $dal;

    public function __construct(BackendFunctionsDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Get incentive dates for a month
     * Line: 10-44 (in template)
     */
    public function getIncentiveDatesForMonth($month)
    {
        // Validate month format (YYYY-MM)
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new \Exception('Month must be in YYYY-MM format', 400);
        }
        
        $results = $this->dal->getIncentiveDatesForMonth($month);
        
        $gatheredDates = [];
        
        foreach ($results as $row) {
            $current = new \DateTime($row['start_date']);
            $endDate = new \DateTime($row['end_date']);
            
            // Loop from start_date to end_date
            while ($current <= $endDate) {
                $monthYear = $current->format('Y-m');
                // Check if the current date's month and year matches the input month and year
                if ($monthYear == $month) {
                    $gatheredDates[$current->format('Y-m-d')] = true; // Use date as key
                }
                $current->modify('+1 day'); // Increment date by 1
            }
        }
        
        // Convert keys back to array
        $uniqueDates = array_keys($gatheredDates);
        sort($uniqueDates);
        
        return ['dates' => $uniqueDates];
    }

    /**
     * Get price per person for pricing_id
     * Line: 46-59 (in template)
     */
    public function getPricePerPerson($pricingId)
    {
        if (!$pricingId) {
            throw new \Exception('pricing_id is required', 400);
        }
        
        $amount = $this->dal->getPricePerPerson($pricingId);
        
        return ['amount' => $amount];
    }

    /**
     * Get product info by tripcode and date
     * Line: 61-81 (in template)
     */
    public function getProductInfoByTripcodeAndDate($tripcode, $date)
    {
        if (!$tripcode) {
            throw new \Exception('tripcode is required', 400);
        }
        if (!$date) {
            throw new \Exception('date is required', 400);
        }
        
        // Validate and format date
        $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            throw new \Exception('date must be in YYYY-MM-DD format', 400);
        }
        $formattedDate = $dateObj->format('Y-m-d');
        
        $result = $this->dal->getProductInfoByTripcodeAndDate($tripcode, $formattedDate);
        
        if (!$result) {
            throw new \Exception('Product not found for the given tripcode and date', 404);
        }
        
        return $result;
    }

    /**
     * Get paid amount for adjustment (G360 version)
     * Line: 83-124 (in template)
     */
    public function getPaidAmountForAdjustmentG360($orderId)
    {
        if (!$orderId) {
            throw new \Exception('old_order_id is required', 400);
        }
        
        $result = $this->dal->getPaidAmountForAdjustmentG360($orderId);
        
        if (!$result) {
            throw new \Exception('Order not found', 404);
        }
        
        return $result;
    }

    /**
     * Get paid amount for adjustment (simple version)
     * Line: 126-142 (in template)
     */
    public function getPaidAmountForAdjustment($orderId)
    {
        if (!$orderId) {
            return 0;
        }
        
        return $this->dal->getPaidAmountForAdjustment($orderId);
    }

    /**
     * Get paid amount for adjustment with deadline check
     * Line: 144-160 (in template)
     */
    public function getPaidAmountForAdjustmentWithDeadline($orderId)
    {
        if (!$orderId) {
            return 0;
        }
        
        return $this->dal->getPaidAmountForAdjustmentWithDeadline($orderId);
    }
}

