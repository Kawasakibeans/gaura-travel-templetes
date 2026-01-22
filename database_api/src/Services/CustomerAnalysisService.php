<?php
/**
 * Customer Analysis Service
 * Business logic for customer analytics endpoints
 */

namespace App\Services;

use App\DAL\CustomerAnalysisDAL;

class CustomerAnalysisService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new CustomerAnalysisDAL();
    }

    /**
     * Get customer analysis data
     */
    public function getCustomerAnalysis(array $params): array
    {
        // Parse date range
        $dr = $params['dr'] ?? '';
        $start = $params['start'] ?? '';
        $end = $params['end'] ?? '';
        
        $tz = new \DateTimeZone('Australia/Melbourne');
        $today = new \DateTime('today', $tz);
        $defStart = (clone $today)->modify('-1 days');
        $defEnd = (clone $today);
        
        if ($dr !== '') {
            $parts = preg_split('/\s+to\s+/i', $dr);
            if (count($parts) === 1) $parts[1] = $parts[0];
            $S = \DateTime::createFromFormat('Y-m-d', $parts[0], $tz);
            $E = \DateTime::createFromFormat('Y-m-d', $parts[1], $tz);
        } else {
            $S = $start ? \DateTime::createFromFormat('Y-m-d', $start, $tz) : null;
            $E = $end ? \DateTime::createFromFormat('Y-m-d', $end, $tz) : null;
        }
        
        if (!$S && !$E) { $S = $defStart; $E = $defEnd; }
        if ($S && !$E) { $E = clone $S; }
        if ($E && !$S) { $S = clone $E; }
        if ($S > $E) { [$S, $E] = [$E, $S]; }
        
        $startDate = $S->format('Y-m-d');
        $endDate = $E->format('Y-m-d');
        $startFull = $S->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        $endFull = $E->setTime(23, 59, 59)->format('Y-m-d H:i:s');
        
        // Build CRN union
        $this->dal->buildCRNUnion($startFull, $endFull);
        
        // Get all data
        $customers = $this->dal->getCustomersFromHistory($startDate, $endDate);
        $activities = $this->dal->getActivities($startFull, $endFull);
        $calls = $this->dal->getCalls($startFull, $endFull);
        $bookings = $this->dal->getBookings($startFull, $endFull);
        $lastBookingDates = $this->dal->getLastBookingDates();
        $adSpend = $this->dal->getAdSpend($startDate, $endDate);
        
        // Process data (simplified - full processing would be done here)
        // This is a simplified version - the full processing logic from fetch-customer-analysis-db.php
        // would need to be ported here
        
        return [
            'ok' => true,
            'range' => ['start' => $startDate, 'end' => $endDate],
            'customers' => $customers,
            'activities' => $activities,
            'calls' => $calls,
            'bookings' => $bookings,
            'last_booking_dates' => $lastBookingDates,
            'ad_spend' => $adSpend,
            'total_customers' => count($customers),
            'total_activities' => count($activities),
            'total_calls' => count($calls),
            'total_bookings' => count($bookings)
        ];
    }
}

