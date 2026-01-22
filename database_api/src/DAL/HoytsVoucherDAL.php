<?php
namespace App\DAL;

class HoytsVoucherDAL extends BaseDAL
{
    public function getVoucherById(int $id): ?array
    {
        $sql = "
            SELECT
                id,
                voucher_code,
                email,
                hoyts_url,
                status,
                expires_at,
                created_at
            FROM wpk4_backend_hoyts_vouchers
            WHERE id = :id
              AND status = 'active'
              AND email IS NULL
            LIMIT 1
        ";

        $row = $this->queryOne($sql, [':id' => $id]);
        return $row ?: null;
    }

    public function getNextAvailableVoucher(): ?array
    {
        $sql = "
            SELECT
                id,
                voucher_code,
                email,
                hoyts_url,
                status,
                expires_at,
                created_at
            FROM wpk4_backend_marketing_hoyts_vouchers
            WHERE status = 'active'
              AND email IS NULL
            ORDER BY COALESCE(created_at, '1970-01-01 00:00:00') ASC
            LIMIT 1
        ";

        $row = $this->queryOne($sql);
        return $row ?: null;
    }
}


