<?php
/**
 * Service for booking passenger lookup by phone.
 */

namespace App\Services;

use App\DAL\BookingPaxDAL;
use Exception;

class BookingPaxService
{
    private BookingPaxDAL $dal;

    public function __construct()
    {
        $this->dal = new BookingPaxDAL();
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function searchByPhone(array $filters): array
    {
        $phone = isset($filters['phone']) ? preg_replace('/\D+/', '', (string)$filters['phone']) : '';
        if ($phone === '') {
            throw new Exception('phone is required', 400);
        }

        $suffix = substr($phone, -8);
        if ($suffix === '') {
            throw new Exception('phone must contain at least one digit', 400);
        }

        $passengers = $this->dal->getPassengersByPhoneSuffix($suffix);

        $unique = [];
        $normalized = [];
        foreach ($passengers as $row) {
            $fullName = trim(sprintf(
                '%s %s %s',
                $row['fname'] ?? '',
                $row['mname'] ?? '',
                $row['lname'] ?? ''
            ));

            $key = strtolower(preg_replace('/\s+/', ' ', $fullName));
            if ($key === '') {
                $key = spl_object_hash((object)$row);
            }

            if (isset($normalized[$key])) {
                continue;
            }
            $normalized[$key] = true;

            $unique[] = [
                'customer_id' => $row['customer_id'],
                'name' => $fullName,
                'salutation' => $row['salutation'],
                'fname' => $row['fname'],
                'mname' => $row['mname'],
                'lname' => $row['lname'],
                'gender' => $row['gender'],
                'dob' => $row['dob'],
                'formatted_dob' => $this->formatDob($row['dob'] ?? null),
                'phone' => $row['phone_pax'],
                'email' => $row['email_pax'],
                'payment_status' => $row['payment_status'],
            ];
        }

        $billing = $this->dal->getBillingByPhoneLike('%' . $phone . '%');

        return [
            'requested_phone' => $phone,
            'matches' => $unique,
            'count' => count($unique),
            'billing' => $billing ?: null,
        ];
    }

    private function formatDob(?string $dob): ?string
    {
        if (!$dob) {
            return null;
        }

        $timestamp = strtotime($dob);
        if ($timestamp === false) {
            return null;
        }

        return date('F j, Y', $timestamp);
    }
}

