<?php
/**
 * Service for FIT checkout new customer storage.
 */

namespace App\Services;

use App\DAL\FitCheckoutDAL;
use DateTime;
use Exception;

class FitCheckoutNewService
{
    private FitCheckoutDAL $dal;

    public function __construct()
    {
        $this->dal = new FitCheckoutDAL();
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function storePassengers(array $payload): array
    {
        $billing = $payload['billing'] ?? null;
        $paxes = $payload['pax'] ?? null;

        if (!is_array($billing) || !is_array($paxes)) {
            throw new Exception('billing and pax payloads are required', 400);
        }

        $orderId = $this->dal->getMaxG360OrderId() + 1;
        $now = (new DateTime('now'))->format('Y-m-d H:i:s');

        $travelDate = $this->convertToDate($billing['depDate'] ?? null);
        $returnDate = $this->convertToDate($billing['retDate'] ?? null);

        $totalPax = $this->countPassengers($paxes);

        $bookingData = [
            'order_type' => 'fit',
            'family_id' => $billing['familyId'] ?? null,
            't_type' => $billing['type'] ?? null,
            'travel_date' => $travelDate,
            'return_date' => $returnDate,
            'total_pax' => $totalPax,
            'agent_info' => $billing['agent'] ?? null,
            'total_amount' => $billing['totalAmount'] ?? null,
            'billing_email' => $billing['email'] ?? null,
            'billing_phone' => $billing['phone'] ?? null,
            'payment_status' => 'partially_paid',
            'order_id' => $orderId,
            'order_date' => $now,
        ];

        $this->dal->insertG360Booking($bookingData);

        $addressData = [
            'order_id' => $orderId,
            'street' => $billing['street'] ?? null,
            'city' => $billing['location'] ?? null,
            'country' => $billing['country'] ?? null,
            'postal_code' => $billing['zipcode'] ?? null,
            'filled_address' => trim(($billing['street'] ?? '') . ' ' . ($billing['location'] ?? '')),
        ];

        $addressId = $this->dal->insertYpsilonAddress($addressData);

        $pnr = $payload['pnr'] ?? ($payload['fileKey'] ?? null);
        $customerIds = [];

        foreach (['ADT', 'CHD', 'INF'] as $type) {
            if (empty($paxes[$type]) || !is_array($paxes[$type])) {
                continue;
            }

            foreach ($paxes[$type] as $passenger) {
                $customerId = $passenger['customerId'] ?? null;
                if ($customerId) {
                    $customerIds[] = $customerId;
                }

                $this->dal->insertG360BookingPax([
                    'order_date' => $now,
                    'added_on' => $now,
                    'order_type' => 'fit',
                    'order_id' => $orderId,
                    'customer_id' => $customerId,
                    'payment_status' => 'partially_paid',
                    'salutation' => $passenger['title'] ?? null,
                    'fname' => $passenger['firstname'] ?? null,
                    'lname' => $passenger['surname'] ?? null,
                    'dob' => $passenger['dob'] ?? null,
                    'country' => $billing['country'] ?? null,
                    'phone_pax' => $billing['phone'] ?? null,
                    'email_pax' => $billing['email'] ?? null,
                    'pnr' => $pnr,
                    'gender' => $passenger['gender'] ?? null,
                ]);
            }
        }

        return [
            'status' => 'success',
            'order_id' => $orderId,
            'address_id' => $addressId,
            'customer_ids' => array_values(array_unique(array_filter($customerIds))),
        ];
    }

    private function convertToDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        $parts = preg_split('/[\/\-]/', $date);
        if (!$parts || count($parts) !== 3) {
            return null;
        }

        [$day, $month, $year] = $parts;
        if (strlen($year) === 2) {
            $year = '20' . $year;
        }

        return sprintf('%04d-%02d-%02d', (int)$year, (int)$month, (int)$day);
    }

    /**
     * @param array<string,array<int,mixed>> $paxes
     */
    private function countPassengers(array $paxes): int
    {
        $total = 0;
        foreach (['ADT', 'CHD', 'INF'] as $type) {
            if (!empty($paxes[$type]) && is_array($paxes[$type])) {
                $total += count($paxes[$type]);
            }
        }
        return $total;
    }
}

