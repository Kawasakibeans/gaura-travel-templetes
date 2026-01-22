<?php
/**
 * Service for HubSpot call data lookups.
 */

namespace App\Services;

use App\DAL\HubspotCallDataDAL;
use DateInterval;
use DateTime;
use Exception;

class HubspotCallDataService
{
    private HubspotCallDataDAL $dal;

    public function __construct()
    {
        $this->dal = new HubspotCallDataDAL();
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function getCallEmailMappings(array $filters): array
    {
        $date = isset($filters['date']) ? $this->parseDate($filters['date']) : null;
        if (!$date) {
            $date = (new DateTime('today'))->sub(new DateInterval('P1D'));
        }

        $rows = $this->dal->getCallsByDate($date->format('Y-m-d'));

        $result = [];
        foreach ($rows as $row) {
            $phone = preg_replace('/\D+/', '', (string)($row['phone'] ?? ''));
            if ($phone === '') {
                $result[] = [
                    'phone' => $row['phone'],
                    'call_date' => $row['call_date'],
                    'email' => null,
                    'source' => null,
                ];
                continue;
            }

            $pattern = '%' . $phone . '%';
            $email = null;
            $source = null;

            $booking = $this->dal->findBookingEmail($pattern);
            if ($booking && !empty($booking['email_pax'])) {
                $email = $booking['email_pax'];
                $source = 'booking_pax';
            } else {
                $passenger = $this->dal->findPassengerEmail($pattern);
                if ($passenger && !empty($passenger['email_address'])) {
                    $email = $passenger['email_address'];
                    $source = 'travel_passenger';
                }
            }

            $result[] = [
                'phone' => $row['phone'],
                'call_date' => $row['call_date'],
                'email' => $email,
                'source' => $source,
                'hubspot_last_call_date' => $this->toIso8601($row['call_date'] ?? null),
            ];
        }

        return [
            'date' => $date->format('Y-m-d'),
            'total_calls' => count($result),
            'calls' => $result,
        ];
    }

    private function parseDate(mixed $value): ?DateTime
    {
        if (!is_string($value)) {
            return null;
        }

        $dt = DateTime::createFromFormat('Y-m-d', $value);
        if ($dt instanceof DateTime) {
            return $dt;
        }

        return null;
    }

    private function toIso8601(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date(DATE_ISO8601, $timestamp);
    }
}

