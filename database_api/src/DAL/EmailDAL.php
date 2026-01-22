<?php
/**
 * Email Data Access Layer
 * Handles database operations for email-related queries
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class EmailDAL extends BaseDAL
{
    /**
     * Get lead passenger information for an order
     */
    public function getLeadPassenger($orderId)
    {
        $query = "
            SELECT * 
            FROM wpk4_backend_travel_booking_pax 
            WHERE order_id = :order_id 
            ORDER BY auto_id ASC 
            LIMIT 1
        ";
        
        $result = $this->query($query, ['order_id' => $orderId]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get billing email from booking
     */
    public function getBillingEmail($orderId)
    {
        $query = "
            SELECT billing_email 
            FROM wpk4_backend_travel_bookings 
            WHERE order_id = :order_id
        ";
        
        $result = $this->query($query, ['order_id' => $orderId]);
        return !empty($result) ? $result[0]['billing_email'] : null;
    }

    /**
     * Get booking information for email
     */
    public function getBookingForEmail($orderId)
    {
        $query = "
            SELECT * 
            FROM wpk4_backend_travel_bookings 
            WHERE order_id = :order_id
        ";
        
        $result = $this->query($query, ['order_id' => $orderId]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get booking information for tax invoice
     */
    public function getBookingForTaxInvoice($orderId)
    {
        $query = "
            SELECT * 
            FROM wpk4_backend_travel_bookings 
            WHERE order_id = :order_id
        ";
        
        return $this->query($query, ['order_id' => $orderId]);
    }

    /**
     * Get passenger information for tax invoice
     */
    public function getPassengerForTaxInvoice($orderId)
    {
        $query = "
            SELECT * 
            FROM wpk4_backend_travel_booking_pax 
            WHERE order_id = :order_id 
            ORDER BY auto_id ASC 
            LIMIT 1
        ";
        
        $result = $this->query($query, ['order_id' => $orderId]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get booking details for reminder emails (24h, 4d, 7d)
     */
    public function getBookingForReminder($orderId, $daysThreshold = null)
    {
        $daysCondition = '';
        if ($daysThreshold !== null) {
            $daysCondition = "AND (CASE WHEN tb.t_type = 'return' THEN DATEDIFF(tb.travel_date, CURRENT_DATE) <= {$daysThreshold} ELSE TRUE END)";
        }
        
        $query = "
            SELECT 
                tb.product_id, 
                tb.order_id as booking_id, 
                CASE
                    WHEN WEEKDAY(tb.travel_date) = 0 THEN CONCAT('Monday ', DATE_FORMAT(tb.travel_date,'%d %M'))
                    WHEN WEEKDAY(tb.travel_date) = 1 THEN CONCAT('Tuesday ', DATE_FORMAT(tb.travel_date,'%d %M'))
                    WHEN WEEKDAY(tb.travel_date) = 2 THEN CONCAT('Wednesday ', DATE_FORMAT(tb.travel_date,'%d %M'))
                    WHEN WEEKDAY(tb.travel_date) = 3 THEN CONCAT('Thursday ', DATE_FORMAT(tb.travel_date,'%d %M'))
                    WHEN WEEKDAY(tb.travel_date) = 4 THEN CONCAT('Friday ', DATE_FORMAT(tb.travel_date,'%d %M'))
                    WHEN WEEKDAY(tb.travel_date) = 5 THEN CONCAT('Saturday ', DATE_FORMAT(tb.travel_date,'%d %M'))
                    WHEN WEEKDAY(tb.travel_date) = 6 THEN CONCAT('Sunday ', DATE_FORMAT(tb.travel_date,'%d %M'))
                    ELSE ''
                END as travel_date,
                DATE_FORMAT(tb.travel_date,'%Y') as travel_date_year, 
                tb.total_pax,
                tb.trip_code,
                SPLIT_STR(tb.trip_code, '-', 3) as airline
            FROM wpk4_backend_travel_bookings tb
            WHERE LOWER(tb.order_type) = 'wpt' 
              AND tb.order_id = :order_id 
              AND (tb.adult_order = '' OR tb.adult_order IS NULL)
              {$daysCondition}
            ORDER BY tb.order_id ASC
        ";
        
        $result = $this->query($query, ['order_id' => $orderId]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get lead passenger name and PNR for reminder
     */
    public function getLeadPassengerForReminder($orderId)
    {
        $query = "
            SELECT 
                REGEXP_REPLACE(
                    CONCAT(
                        CONCAT(UCASE(LEFT(tbp.fname, 1)), LCASE(SUBSTRING(tbp.fname, 2))),
                        ' ',
                        CONCAT(UCASE(LEFT(tbp.mname, 1)), LCASE(SUBSTRING(tbp.mname, 2))),
                        ' ',
                        CONCAT(UCASE(LEFT(tbp.lname, 1)), LCASE(SUBSTRING(tbp.lname, 2)))
                    ),
                    '[[:space:]]+', ' '
                ) as passenger_name, 
                tbp.pnr 
            FROM wpk4_backend_travel_booking_pax tbp 
            WHERE tbp.order_id = :order_id 
            LIMIT 1
        ";
        
        $result = $this->query($query, ['order_id' => $orderId]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get all passengers for reminder (including adult order passengers)
     */
    public function getPassengersForReminder($orderId, $productId)
    {
        $query = "
            SELECT 
                CONCAT(
                    tbp.salutation, '. ', 
                    REGEXP_REPLACE(
                        CONCAT(
                            CONCAT(UCASE(LEFT(tbp.fname, 1)), LCASE(SUBSTRING(tbp.fname, 2))),
                            ' ',
                            CONCAT(UCASE(LEFT(tbp.mname, 1)), LCASE(SUBSTRING(tbp.mname, 2))),
                            ' ',
                            CONCAT(UCASE(LEFT(tbp.lname, 1)), LCASE(SUBSTRING(tbp.lname, 2)))
                        ),
                        '[[:space:]]+', ' '
                    )
                ) as passenger_name,
                tbp.pnr 
            FROM wpk4_backend_travel_booking_pax tbp 
            WHERE tbp.order_id = :order_id 
              AND tbp.product_id = :product_id
            UNION 
            SELECT  
                CONCAT(
                    tbp.salutation, '. ', 
                    REGEXP_REPLACE(
                        CONCAT(
                            CONCAT(UCASE(LEFT(tbp.fname, 1)), LCASE(SUBSTRING(tbp.fname, 2))),
                            ' ',
                            CONCAT(UCASE(LEFT(tbp.mname, 1)), LCASE(SUBSTRING(tbp.mname, 2))),
                            ' ',
                            CONCAT(UCASE(LEFT(tbp.lname, 1)), LCASE(SUBSTRING(tbp.lname, 2)))
                        ),
                        '[[:space:]]+', ' '
                    )
                ) as passenger_name,
                tbp.pnr 
            FROM wpk4_backend_travel_booking_pax tbp 
            WHERE tbp.order_id = (
                SELECT tb.order_id 
                FROM wpk4_backend_travel_bookings tb 
                WHERE tb.adult_order = :order_id 
                LIMIT 1
            )
        ";
        
        return $this->query($query, [
            'order_id' => $orderId,
            'product_id' => $productId
        ]);
    }

    /**
     * Get days left until travel date
     */
    public function getDaysLeftUntilTravel($orderId, $daysThreshold = null)
    {
        $daysCondition = '';
        if ($daysThreshold !== null) {
            $daysCondition = "AND CASE WHEN tb.t_type = 'return' THEN DATEDIFF(tb.travel_date, CURRENT_DATE) <= {$daysThreshold} ELSE TRUE END";
        }
        
        $query = "
            SELECT DATEDIFF(tb.travel_date, CURRENT_DATE) as total_days_left 
            FROM wpk4_backend_travel_bookings tb 
            WHERE LOWER(tb.order_type) = 'wpt' 
              AND tb.order_id = :order_id
              {$daysCondition}
        ";
        
        $result = $this->query($query, ['order_id' => $orderId]);
        return !empty($result) ? (int)$result[0]['total_days_left'] : null;
    }

    /**
     * Insert email history record
     */
    public function insertEmailHistory($data)
    {
        $query = "
            INSERT INTO wpk4_backend_order_email_history 
            (order_id, email_type, email_address, initiated_date, initiated_by, email_body, email_subject) 
            VALUES 
            (:order_id, :email_type, :email_address, :initiated_date, :initiated_by, :email_body, :email_subject)
        ";
        
        return $this->execute($query, [
            'order_id' => $data['order_id'],
            'email_type' => $data['email_type'],
            'email_address' => $data['email_address'],
            'initiated_date' => $data['initiated_date'],
            'initiated_by' => $data['initiated_by'],
            'email_body' => $data['email_body'] ?? null,
            'email_subject' => $data['email_subject']
        ]);
    }

    /**
     * Update e-ticket status for passengers
     */
    public function updateEticketStatus($orderId, $status)
    {
        $query = "
            UPDATE wpk4_backend_travel_booking_pax
            SET eticket_status = :status
            WHERE order_id = :order_id
        ";
        
        return $this->execute($query, [
            'status' => $status,
            'order_id' => $orderId
        ]);
    }

    /**
     * Insert e-ticket file log
     */
    public function insertEticketFileLog($data)
    {
        $query = "
            INSERT INTO wpk4_backend_travel_booking_eticket_file_log 
            (order_id, file_path, created_at, created_by) 
            VALUES 
            (:order_id, :file_path, :created_at, :created_by)
        ";
        
        return $this->execute($query, [
            'order_id' => $data['order_id'],
            'file_path' => $data['file_path'],
            'created_at' => $data['created_at'],
            'created_by' => $data['created_by'] ?? null
        ]);
    }

    /**
     * Get all bookings for an order (ordered by travel_date)
     */
    public function getAllBookingsForOrder($orderId)
    {
        $query = "
            SELECT * 
            FROM wpk4_backend_travel_bookings 
            WHERE order_id = :order_id 
            ORDER BY travel_date ASC
        ";
        
        return $this->query($query, ['order_id' => $orderId]);
    }

    /**
     * Get history of updates data
     */
    public function getHistoryOfUpdates($orderId, $metaKey = null)
    {
        $metaKeyCondition = '';
        if ($metaKey !== null) {
            $metaKeyCondition = "AND meta_key = :meta_key";
        }
        
        $query = "
            SELECT * 
            FROM wpk4_backend_history_of_updates 
            WHERE type_id = :order_id 
            {$metaKeyCondition}
            ORDER BY auto_id ASC
        ";
        
        $params = ['order_id' => $orderId];
        if ($metaKey !== null) {
            $params['meta_key'] = $metaKey;
        }
        
        return $this->query($query, $params);
    }

    /**
     * Get passengers by product_id
     */
    public function getPassengersByProductId($orderId, $productId = null)
    {
        $productCondition = '';
        if ($productId !== null) {
            $productCondition = "AND product_id = :product_id";
        }
        
        $query = "
            SELECT * 
            FROM wpk4_backend_travel_booking_pax 
            WHERE order_id = :order_id 
            {$productCondition}
            ORDER BY auto_id ASC
        ";
        
        $params = ['order_id' => $orderId];
        if ($productId !== null) {
            $params['product_id'] = $productId;
        }
        
        return $this->query($query, $params);
    }

    /**
     * Get trip extras from wpk4_wt_pricings
     */
    public function getTripExtras($productId, $newProductId = null)
    {
        $productIdToUse = !empty($newProductId) ? $newProductId : $productId;
        $joinCondition = !empty($newProductId) ? 'b.new_product_id = p.trip_id' : 'b.product_id = p.trip_id';
        $whereCondition = !empty($newProductId) ? 'b.new_product_id = :product_id' : 'b.product_id = :product_id';
        
        $query = "
            SELECT p.ID, p.trip_extras 
            FROM wpk4_wt_pricings p 
            JOIN wpk4_backend_travel_bookings b ON {$joinCondition}
            WHERE {$whereCondition}
            LIMIT 1
        ";
        
        $result = $this->query($query, ['product_id' => $productIdToUse]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get custom email itinerary data
     */
    public function getCustomEmailItinerary($orderId, $isEmailed = null)
    {
        $emailedCondition = '';
        if ($isEmailed !== null) {
            $emailedCondition = $isEmailed === '' || $isEmailed === null 
                ? "AND (is_emailed = '' OR is_emailed IS NULL)"
                : "AND is_emailed = :is_emailed";
        }
        
        $query = "
            SELECT * 
            FROM wpk4_backend_travel_booking_custom_email_itinerary 
            WHERE order_id = :order_id 
            {$emailedCondition}
            ORDER BY auto_id ASC
        ";
        
        $params = ['order_id' => $orderId];
        if ($isEmailed !== null && $isEmailed !== '' && $isEmailed !== null) {
            $params['is_emailed'] = $isEmailed;
        }
        
        return $this->query($query, $params);
    }

    /**
     * Get payment history from history_of_updates
     */
    public function getPaymentHistory($orderId)
    {
        $query = "
            SELECT * 
            FROM wpk4_backend_history_of_updates 
            WHERE type_id = :order_id 
            AND meta_key = 'Payments Amount'
            ORDER BY auto_id ASC
        ";
        
        return $this->query($query, ['order_id' => $orderId]);
    }

    /**
     * Get flight leg details from history_of_updates
     */
    public function getFlightLegDetails($orderId)
    {
        $metaKeys = [
            'Flightlegs MarketingCarrier',
            'Flightlegs FlNr',
            'Flightlegs DepApt',
            'Flightlegs DestApt',
            'Flightlegs DepTime',
            'Flightlegs DestTime',
            'Flightlegs CosDescription',
            'Flightlegs Class',
            'Flightlegs Elapsed',
            'Flightlegs Meal',
            'Flightlegs DepTerminal',
            'Flightlegs DestTerminal',
            'Flightlegs Equipment'
        ];
        
        $placeholders = [];
        foreach ($metaKeys as $index => $key) {
            $placeholders[] = ":meta_key_{$index}";
        }
        
        $query = "
            SELECT meta_key, meta_value 
            FROM wpk4_backend_history_of_updates 
            WHERE type_id = :order_id 
            AND meta_key IN (" . implode(', ', $placeholders) . ")
            ORDER BY meta_key, auto_id ASC
        ";
        
        $params = ['order_id' => $orderId];
        foreach ($metaKeys as $index => $key) {
            $params["meta_key_{$index}"] = $key;
        }
        
        return $this->query($query, $params);
    }

    /**
     * Get baggage information from history_of_updates
     */
    public function getBaggageInfo($orderId, $gdsPaxId = null, $departureAirport = null)
    {
        $params = ['order_id' => $orderId];
        
        if ($gdsPaxId !== null && $departureAirport !== null) {
            // Try both possible baggage key formats
            $baggageKey1 = $gdsPaxId . ' - ' . $departureAirport . ' - Baggage';
            // Second format might be slightly different, but based on original code, both keys are the same format
            // The original code checks both but they're the same - keeping for compatibility
            $baggageKey2 = $baggageKey1;
            
            $query = "
                SELECT * 
                FROM wpk4_backend_history_of_updates 
                WHERE type_id = :order_id 
                AND (meta_key = :baggage_key1 OR meta_key = :baggage_key2)
                ORDER BY auto_id ASC
                LIMIT 1
            ";
            
            $params['baggage_key1'] = $baggageKey1;
            $params['baggage_key2'] = $baggageKey2;
        } else {
            $query = "
                SELECT * 
                FROM wpk4_backend_history_of_updates 
                WHERE type_id = :order_id 
                AND meta_key LIKE '%Baggage%'
                ORDER BY auto_id ASC
            ";
        }
        
        return $this->query($query, $params);
    }
}

