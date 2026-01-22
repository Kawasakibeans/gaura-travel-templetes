<?php
/**
 * Service for FIT checkout existing customers lookup.
 */

namespace App\Services;

use App\DAL\FitCheckoutDAL;
use Exception;

class FitCheckoutExistingService
{
    private FitCheckoutDAL $dal;

    public function __construct()
    {
        $this->dal = new FitCheckoutDAL();
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function getCustomers(array $filters): array
    {
        $idsParam = $filters['customer_ids'] ?? null;
        if ($idsParam === null) {
            throw new Exception('customer_ids query parameter is required', 400);
        }

        if (is_string($idsParam)) {
            $customerIds = array_filter(array_map('intval', preg_split('/[,\s]+/', $idsParam)));
        } elseif (is_array($idsParam)) {
            $customerIds = array_filter(array_map('intval', $idsParam));
        } else {
            throw new Exception('customer_ids must be an array or comma-separated list', 400);
        }

        if (empty($customerIds)) {
            throw new Exception('At least one valid customer_id is required', 400);
        }

        $passengers = $this->dal->getPassengersByCustomerIds($customerIds);

        $billing = $this->dal->getBillingDataByCustomerId($customerIds[0]) ?? null;

        return [
            'customer_ids' => $customerIds,
            'total_passengers' => count($passengers),
            'passengers' => $passengers,
            'billing' => $billing,
        ];
    }
}

