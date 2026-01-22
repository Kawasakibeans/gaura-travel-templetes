<?php
namespace App\Services;

use App\DAL\HoytsVoucherDAL;
use Exception;

class HoytsVoucherService
{
    private HoytsVoucherDAL $dal;

    public function __construct()
    {
        $this->dal = new HoytsVoucherDAL();
    }

    public function getVoucher(array $filters = []): array
    {
        $voucher = null;

        if (!empty($filters['id'])) {
            $id = (int)$filters['id'];
            if ($id <= 0) {
                throw new Exception('id must be a positive integer', 400);
            }
            $voucher = $this->dal->getVoucherById($id);
        } else {
            $voucher = $this->dal->getNextAvailableVoucher();
        }

        if (!$voucher) {
            throw new Exception('No active voucher found', 404);
        }

        $voucher['expires_at_formatted'] = $this->formatDate($voucher['expires_at']);
        $voucher['created_at_formatted'] = $this->formatDateTime($voucher['created_at']);

        return $voucher;
    }

    private function formatDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        $ts = strtotime($date);
        return $ts ? date('M d, Y', $ts) : $date;
    }

    private function formatDateTime(?string $dateTime): ?string
    {
        if (!$dateTime) {
            return null;
        }

        $ts = strtotime($dateTime);
        return $ts ? date('M d, Y H:i', $ts) : $dateTime;
    }
}


