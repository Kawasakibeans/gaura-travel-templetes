<?php
/**
 * FIT checkout agent service.
 */

namespace App\Services;

use App\DAL\FitCheckoutDAL;
use Exception;

class FitCheckoutService
{
    private FitCheckoutDAL $dal;

    public function __construct()
    {
        $this->dal = new FitCheckoutDAL();
    }

    public function getBillingData(array $filters): array
    {
        $customerId = isset($filters['customer_id']) ? (int)$filters['customer_id'] : 0;
        if ($customerId <= 0) {
            throw new Exception('customer_id is required and must be a positive integer', 400);
        }

        $row = $this->dal->getBillingDataByCustomerId($customerId);
        if (!$row) {
            throw new Exception('Billing data not found for provided customer_id', 404);
        }

        return [
            'customer_id' => $customerId,
            'billing' => $row,
        ];
    }
}

