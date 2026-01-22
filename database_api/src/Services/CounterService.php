<?php

namespace App\Services;

use App\DAL\CounterDAL;

class CounterService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new CounterDAL();
    }

    /**
     * Get trip summary
     */
    public function getTripSummary(): array
    {
        $trips = $this->dal->getTripSummary();
        
        $totals = [
            'total_pax' => 0,
            'partial_paid' => 0,
            'booked' => 0,
            'cancelled' => 0,
            'refund' => 0
        ];
        
        foreach ($trips as $trip) {
            $totals['total_pax'] += (int)($trip['Total_Pax'] ?? 0);
            $totals['partial_paid'] += (int)($trip['Partial_Paid'] ?? 0);
            $totals['booked'] += (int)($trip['Booked'] ?? 0);
            $totals['cancelled'] += (int)($trip['Cancelled'] ?? 0);
            $totals['refund'] += (int)($trip['Refund'] ?? 0);
        }
        
        return [
            'trips' => $trips,
            'totals' => $totals,
            'total_trips' => count($trips)
        ];
    }
}

