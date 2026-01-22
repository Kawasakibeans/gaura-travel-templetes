<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class AmadeusEndorsementBackendDAL extends BaseDAL
{
    /**
     * Get distinct endorsement IDs and AUD fares for date range
     */
    public function getEndorsementIdsAndPrices(string $startDate, string $endDate): array
    {
        $sql = "
            SELECT DISTINCT mh_endorsement, aud_fare
            FROM wpk4_backend_stock_management_sheet 
            WHERE DATE(dep_date) >= ? AND DATE(dep_date) <= ?
        ";

        return $this->query($sql, [$startDate, $endDate]);
    }
}

