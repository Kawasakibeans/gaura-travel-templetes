<?php

namespace App\Services;

use App\DAL\AmadeusEndorsementBackendDAL;
use Exception;

class AmadeusEndorsementBackendService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AmadeusEndorsementBackendDAL();
    }

    /**
     * Get endorsement IDs and prices for date range
     */
    public function getEndorsementIdsAndPrices(string $startDate, string $endDate): array
    {
        if (empty($startDate) || empty($endDate)) {
            throw new Exception('Start date and end date are required', 400);
        }

        $results = $this->dal->getEndorsementIdsAndPrices($startDate, $endDate);

        if (empty($results)) {
            return [
                'success' => false,
                'endorsement_id' => [],
                'aud_fare' => []
            ];
        }

        $endorsementIds = [];
        $prices = [];

        foreach ($results as $row) {
            if (!empty($row['mh_endorsement'])) {
                $endorsementIds[] = $row['mh_endorsement'];
            }
            if (!empty($row['aud_fare'])) {
                $prices[] = $row['aud_fare'];
            }
        }

        // Remove duplicates and sort
        $endorsementIds = array_unique($endorsementIds);
        $prices = array_unique($prices);
        sort($endorsementIds);
        sort($prices);

        return [
            'success' => true,
            'endorsement_id' => array_values($endorsementIds),
            'aud_fare' => array_values($prices)
        ];
    }
}

