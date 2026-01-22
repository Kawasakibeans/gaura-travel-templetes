<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class PaymentReconciliationDAL extends BaseDAL
{
    /**
     * Get Tram payment records
     */
    public function getTramRecords(?string $startDate = null, ?string $endDate = null): array
    {
        $sql = "
            SELECT 
                paymentno, 
                payment_date, 
                remarks AS trams_remark, 
                name AS customer_name, 
                amount, 
                voucher_linkno 
            FROM wpk4_backend_payment_trams 
            WHERE bank_linkno = 5 AND paymethod_linkno = 6
        ";

        $params = [];
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " AND payment_date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        return $this->query($sql, $params);
    }

    /**
     * Get Bank payment records
     */
    public function getBankRecords(?string $startDate = null, ?string $endDate = null): array
    {
        $sql = "
            SELECT date, amount, description, type 
            FROM wpk4_backend_payment_banks
        ";

        $params = [];
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " WHERE date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        return $this->query($sql, $params);
    }

    /**
     * Get total bank amount for date range
     */
    public function getTotalBankAmount(string $startDate, string $endDate): float
    {
        $sql = "
            SELECT SUM(amount) as total 
            FROM wpk4_backend_payment_banks 
            WHERE date BETWEEN ? AND ?
        ";

        $result = $this->queryOne($sql, [$startDate, $endDate]);
        return $result ? (float)$result['total'] : 0.0;
    }

    /**
     * Get total tram amount for date range
     */
    public function getTotalTramAmount(string $startDate, string $endDate): float
    {
        $sql = "
            SELECT SUM(amount) as total 
            FROM wpk4_backend_payment_trams 
            WHERE payment_date BETWEEN ? AND ?
        ";

        $result = $this->queryOne($sql, [$startDate, $endDate]);
        return $result ? (float)$result['total'] : 0.0;
    }
}

