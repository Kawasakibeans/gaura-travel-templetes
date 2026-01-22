<?php
namespace App\Services;

use App\DAL\FlightMulticityDAL;
use DateTime;
use Exception;

class FlightMulticityService
{
    private FlightMulticityDAL $dal;

    public function __construct()
    {
        $this->dal = new FlightMulticityDAL();
    }

    public function checkIp(array $filters): array
    {
        $ip = isset($filters['ip_address']) ? trim((string)$filters['ip_address']) : '';
        if ($ip === '') {
            throw new Exception('ip_address is required', 400);
        }

        $records = $this->dal->getIpAccess($ip);

        return [
            'ip_address' => $ip,
            'records' => $records,
            'allowed' => !empty($records),
        ];
    }

    public function getPromoEligibility(array $filters): array
    {
        $userId = isset($filters['user_id']) ? trim((string)$filters['user_id']) : null;
        $email = isset($filters['email']) ? trim(strtolower((string)$filters['email'])) : null;
        $isGauraAgent = isset($filters['is_gaura_agent']) ? (bool)$filters['is_gaura_agent'] : false;
        $departure = isset($filters['departure_date']) ? trim((string)$filters['departure_date']) : null;
        $return = isset($filters['return_date']) ? trim((string)$filters['return_date']) : null;

        if (!$email && $userId) {
            $email = $this->dal->getCustomerEmailByUid($userId);
        }

        $emailExists = $email ? $this->dal->emailExistsInPaxDatabase($email) : false;
        $hasTripDates = $departure !== null && $return !== null && $departure !== '' && $return !== '';
        $eligible = ($isGauraAgent || $emailExists) && $hasTripDates;

        return [
            'user_id' => $userId,
            'email' => $email,
            'email_exists' => $emailExists,
            'is_gaura_agent' => $isGauraAgent,
            'has_trip_dates' => $hasTripDates,
            'eligible' => $eligible,
        ];
    }

    public function getTripAvailability(array $filters): array
    {
        $tripId = isset($filters['trip_id']) ? (int)$filters['trip_id'] : 0;
        if ($tripId <= 0) {
            throw new Exception('trip_id must be a positive integer', 400);
        }

        $departureDate = $this->validateDate($filters['departure_date'] ?? null, 'departure_date');
        $passengers = isset($filters['passengers']) ? max(1, (int)$filters['passengers']) : 1;
        $flexible = isset($filters['flexible']) ? (bool)$filters['flexible'] : false;
        $returnDate = isset($filters['return_date']) && $filters['return_date'] !== ''
            ? $this->validateDate($filters['return_date'], 'return_date')
            : null;

        $departureData = $this->buildAvailability($tripId, $departureDate, $passengers, $flexible);
        $returnData = $returnDate ? $this->buildAvailability($tripId, $returnDate, $passengers, $flexible) : [];

        return [
            'trip_id' => $tripId,
            'passengers' => $passengers,
            'flexible' => $flexible,
            'departure' => $departureData,
            'return' => $returnData,
        ];
    }

    private function buildAvailability(int $tripId, string $date, int $passengers, bool $flexible): array
    {
        $pattern = $flexible ? substr($date, 0, 7) : $date;
        $rows = $this->dal->getDatesForTrip($tripId, $pattern, $flexible);

        if (empty($rows)) {
            return [];
        }

        $pricingIds = [];
        $datePricingMap = [];
        foreach ($rows as $row) {
            $startDate = $row['start_date'];
            $ids = array_filter(array_map('trim', explode(',', (string)$row['pricing_ids'])));
            foreach ($ids as $id) {
                $pricingIds[] = (int)$id;
                $datePricingMap[$startDate][] = (int)$id;
            }
        }

        $pricingIds = array_values(array_unique($pricingIds));
        $pricingInfo = $this->indexBy($this->dal->getPricingByIds($pricingIds), 'id');
        $pricingPrices = $this->indexBy($this->dal->getPriceCategories($pricingIds), 'pricing_id');

        $metaKeys = [];
        foreach ($datePricingMap as $startDate => $ids) {
            $formattedDate = str_replace('-', '_', $startDate);
            foreach ($ids as $pricingId) {
                $metaKeys[] = sprintf('wt_booked_pax-%d-%s', $pricingId, $formattedDate);
            }
        }
        $bookedCounts = $this->indexBy($this->dal->getBookedPaxCounts($metaKeys), 'meta_key');

        $results = [];
        foreach ($datePricingMap as $startDate => $ids) {
            $pricingOptions = [];
            foreach ($ids as $pricingId) {
                $info = $pricingInfo[$pricingId] ?? null;
                $priceRow = $pricingPrices[$pricingId] ?? null;
                if (!$info || !$priceRow) {
                    continue;
                }

                $formattedDate = str_replace('-', '_', $startDate);
                $metaKey = sprintf('wt_booked_pax-%d-%s', $pricingId, $formattedDate);
                $booked = isset($bookedCounts[$metaKey]['meta_value']) ? (int)$bookedCounts[$metaKey]['meta_value'] : 0;
                $availableSeats = max(0, ((int)$info['max_pax']) - $booked);

                $pricingOptions[] = [
                    'pricing_id' => $pricingId,
                    'sale_price' => isset($priceRow['sale_price']) ? (float)$priceRow['sale_price'] : null,
                    'regular_price' => isset($priceRow['regular_price']) ? (float)$priceRow['regular_price'] : null,
                    'min_pax' => (int)($info['min_pax'] ?? 0),
                    'max_pax' => (int)($info['max_pax'] ?? 0),
                    'available_seats' => $availableSeats,
                    'can_accommodate' => $availableSeats >= $passengers,
                ];
            }

            if (!empty($pricingOptions)) {
                $results[] = [
                    'start_date' => $startDate,
                    'pricing_options' => $pricingOptions,
                ];
            }
        }

        return $results;
    }

    private function validateDate(?string $date, string $field): string
    {
        if (!$date) {
            throw new Exception($field . ' is required', 400);
        }

        $dt = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt) {
            throw new Exception($field . ' must be in Y-m-d format', 400);
        }

        return $dt->format('Y-m-d');
    }

    private function indexBy(array $rows, string $key): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            if (isset($row[$key])) {
                $indexed[$row[$key]] = $row;
            }
        }

        return $indexed;
    }
}
