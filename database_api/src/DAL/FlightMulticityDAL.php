<?php
namespace App\DAL;

class FlightMulticityDAL extends BaseDAL
{
    public function getIpAccess(string $ipAddress): array
    {
        $sql = "
            SELECT *
            FROM wpk4_backend_ip_address_checkup
            WHERE ip_address = :ip
        ";

        return $this->query($sql, [':ip' => $ipAddress]);
    }

    public function getCustomerEmailByUid(string $uid): ?string
    {
        $sql = "
            SELECT email
            FROM wpk4_customer_accounts
            WHERE uid = :uid
            LIMIT 1
        ";

        $row = $this->queryOne($sql, [':uid' => $uid]);
        return $row['email'] ?? null;
    }

    public function emailExistsInPaxDatabase(string $email): bool
    {
        $sql = "
            SELECT auto_id
            FROM wpk4_backend_travel_bookings_pax_email_db
            WHERE email = :email
            LIMIT 1
        ";

        $row = $this->queryOne($sql, [':email' => $email]);
        return !empty($row);
    }

    public function getDatesForTrip(int $tripId, string $pattern, bool $prefixMatch = false): array
    {
        $likePattern = $prefixMatch ? $pattern . '%' : $pattern;

        $sql = "
            SELECT dates.start_date, dates.pricing_ids
            FROM wpk4_wt_dates AS dates
            LEFT JOIN wpk4_wt_excluded_dates_times AS exclude
                ON dates.trip_id = exclude.trip_id
               AND dates.start_date = exclude.start_date
            WHERE dates.trip_id = :trip_id
              AND DATE(dates.start_date) > CURRENT_DATE
              AND dates.start_date LIKE :pattern
              AND exclude.start_date IS NULL
            ORDER BY dates.start_date ASC
        ";

        return $this->query($sql, [
            ':trip_id' => $tripId,
            ':pattern' => $likePattern,
        ]);
    }

    public function getPricingByIds(array $pricingIds): array
    {
        if (empty($pricingIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($pricingIds), '?'));
        $sql = "
            SELECT id, min_pax, max_pax, trip_extras
            FROM wpk4_wt_pricings
            WHERE id IN ($placeholders)
        ";

        return $this->query($sql, $pricingIds);
    }

    public function getPriceCategories(array $pricingIds, int $categoryId = 953): array
    {
        if (empty($pricingIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($pricingIds), '?'));
        $params = $pricingIds;
        $params[] = $categoryId;

        $sql = "
            SELECT pricing_id, regular_price, sale_price
            FROM wpk4_wt_price_category_relation
            WHERE pricing_id IN ($placeholders)
              AND pricing_category_id = ?
        ";

        return $this->query($sql, $params);
    }

    public function getBookedPaxCounts(array $metaKeys): array
    {
        if (empty($metaKeys)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($metaKeys), '?'));
        $sql = "
            SELECT meta_key, meta_value
            FROM wpk4_postmeta
            WHERE meta_key IN ($placeholders)
        ";

        return $this->query($sql, $metaKeys);
    }
}
