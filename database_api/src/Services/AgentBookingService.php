<?php
/**
 * Agent booking service.
 */

namespace App\Services;

use App\DAL\AgentBookingDAL;
use DateInterval;
use DateTime;
use Exception;

class AgentBookingService
{
    private AgentBookingDAL $dal;

    public function __construct()
    {
        $this->dal = new AgentBookingDAL();
    }

    public function listPaymentMethods(): array
    {
        return [
            'payment_methods' => $this->dal->getPaymentMethods(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createBooking(array $payload): array
    {
        $email = isset($payload['email']) ? trim((string)$payload['email']) : '';
        $amount = isset($payload['amount']) ? (float)$payload['amount'] : 0.0;
        $paymentMethod = isset($payload['payment_method']) ? trim((string)$payload['payment_method']) : '';
        $addedBy = isset($payload['added_by']) ? trim((string)$payload['added_by']) : '';
        $paymentReference = isset($payload['payment_reference']) ? trim((string)$payload['payment_reference']) : null;
        $remark = isset($payload['remark']) ? trim((string)$payload['remark']) : '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Valid email is required', 400);
        }
        if ($amount <= 0) {
            throw new Exception('amount must be greater than zero', 400);
        }
        if ($paymentMethod === '') {
            throw new Exception('payment_method is required', 400);
        }
        if ($addedBy === '') {
            throw new Exception('added_by is required', 400);
        }

        try {
            $this->dal->beginTransaction();

            $autoId = $this->dal->insertBookingShell($addedBy);
            $booking = $this->dal->getBookingByAutoId($autoId);
            if (!$booking) {
                throw new Exception('Failed to load created booking record', 500);
            }

            $orderId = (int)$booking['order_id'];
            $orderDate = (string)$booking['order_date'];

            $this->dal->insertBookingPax($orderId, $orderDate, $email, $addedBy);

            $deadline = (new DateTime($orderDate))
                ->add(new DateInterval('PT96H'))
                ->format('Y-m-d H:i:s');

            $this->dal->insertPaymentHistory(
                $orderId,
                $remark,
                $amount,
                $paymentReference,
                $paymentMethod,
                $orderDate,
                $addedBy,
                $deadline
            );

            $this->dal->commit();
        } catch (Exception $e) {
            $this->dal->rollback();
            throw $e;
        }

        $fresh = $this->dal->getBookingByAutoId($autoId);

        return [
            'message' => 'Agent booking created successfully',
            'booking' => $fresh,
        ];
    }

    public function getBooking(int $autoId): array
    {
        if ($autoId <= 0) {
            throw new Exception('auto_id must be a positive integer', 400);
        }

        $booking = $this->dal->getBookingByAutoId($autoId);
        if (!$booking) {
            throw new Exception('Booking not found', 404);
        }

        return [
            'booking' => $booking,
        ];
    }

    public function getStockProduct(string $tripCode, string $travelDate): array
    {
        if ($tripCode === '') {
            throw new Exception('trip_code is required', 400);
        }
        $date = $this->normaliseDate($travelDate, 'travel_date');

        $row = $this->dal->getStockProduct($tripCode, $date);
        if (!$row) {
            throw new Exception('Stock product not found', 404);
        }

        return [
            'trip_code' => $tripCode,
            'travel_date' => $date,
            'stock_product' => $row,
        ];
    }

    public function getLastLargeOrder(): array
    {
        $row = $this->dal->getLastLargeOrder();
        if (!$row) {
            return ['order' => null];
        }

        return ['order' => $row];
    }

    public function getTripPnr(string $tripCode, string $travelDate): array
    {
        if ($tripCode === '') {
            throw new Exception('trip_code is required', 400);
        }
        $date = $this->normaliseDate($travelDate, 'travel_date');

        $pnr = $this->dal->getPnrForTrip($tripCode, $date);
        if (!$pnr) {
            throw new Exception('PNR not found for provided trip/date', 404);
        }

        return [
            'trip_code' => $tripCode,
            'travel_date' => $date,
            'pnr' => $pnr,
        ];
    }

    private function normaliseDate(string $value, string $field): string
    {
        $value = trim($value);
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        if ($dt === false) {
            throw new Exception("$field must be formatted as YYYY-MM-DD", 400);
        }
        return $dt->format('Y-m-d');
    }
}

