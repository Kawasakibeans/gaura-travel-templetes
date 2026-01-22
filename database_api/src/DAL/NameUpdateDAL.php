<?php
/**
 * Name Update Data Access Layer
 * Handles database operations for Amadeus name updates
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class NameUpdateDAL extends BaseDAL
{
    /**
     * Get passengers requiring name updates
     */
    public function getPendingNameUpdates($orderId = null)
    {
        $whereParts = [
            "booking.order_type = 'WPT'",
            "booking.payment_status = 'paid'",
            "booking.product_id NOT IN ('60107', '60116')"
        ];
        $params = [];

        if ($orderId) {
            $whereParts[] = "booking.order_id = ?";
            $params[] = $orderId;
        }

        $whereSQL = implode(' AND ', $whereParts);

        $query = "SELECT booking.order_id, pax.pnr, pax.auto_id as paxauto_id, 
                         pax.salutation, pax.fname, pax.lname, pax.dob, 
                         booking.trip_code, booking.travel_date 
                  FROM wpk4_backend_travel_bookings booking
                  JOIN wpk4_backend_travel_booking_pax pax 
                    ON booking.order_id = pax.order_id 
                    AND booking.product_id = pax.product_id
                  WHERE $whereSQL";

        return $this->query($query, $params);
    }

    /**
     * Get stock info (PNR, airline, office ID)
     */
    public function getStockInfo($tripCode, $travelDate)
    {
        $query = "SELECT pnr, OID, airline_code 
                  FROM wpk4_backend_stock_management_sheet 
                  WHERE trip_id = ? AND dep_date = ? 
                  LIMIT 1";
        
        return $this->queryOne($query, [$tripCode, $travelDate]);
    }

    /**
     * Check if name update already logged
     */
    public function checkNameUpdateLog($pnr, $orderId, $fname, $lname, $dob)
    {
        $query = "SELECT auto_id 
                  FROM wpk4_amadeus_name_update_log 
                  WHERE pnr = ? 
                    AND order_id = ? 
                    AND fname = ? 
                    AND lname = ? 
                    AND dob = ?
                  LIMIT 1";
        
        $result = $this->queryOne($query, [$pnr, $orderId, $fname, $lname, $dob]);
        return !empty($result);
    }

    /**
     * Create name update log
     */
    public function createNameUpdateLog($data)
    {
        $query = "INSERT INTO wpk4_amadeus_name_update_log 
                  (pnr, order_id, fname, lname, dob, airline, office_id, 
                   trip_code, travel_date, status, created_at, comments)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        
        $params = [
            $data['pnr'],
            $data['order_id'],
            $data['fname'],
            $data['lname'],
            $data['dob'],
            $data['airline'] ?? null,
            $data['office_id'] ?? null,
            $data['trip_code'] ?? null,
            $data['travel_date'] ?? null,
            $data['status'] ?? 'pending',
            $data['comments'] ?? null
        ];

        $this->execute($query, $params);
        return $this->lastInsertId();
    }

    /**
     * Get name update history
     */
    public function getNameUpdateHistory($orderId = null, $pnr = null)
    {
        $whereParts = [];
        $params = [];

        if ($orderId) {
            $whereParts[] = "order_id = ?";
            $params[] = $orderId;
        }

        if ($pnr) {
            $whereParts[] = "pnr = ?";
            $params[] = $pnr;
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        $query = "SELECT * FROM wpk4_amadeus_name_update_log 
                  $whereSQL 
                  ORDER BY created_at DESC";
        
        return $this->query($query, $params);
    }

    /**
     * Check for infant bookings
     */
    public function hasInfantBookings($orderId)
    {
        $query = "SELECT auto_id 
                  FROM wpk4_backend_travel_bookings 
                  WHERE adult_order = ? 
                  LIMIT 1";
        
        $result = $this->queryOne($query, [$orderId]);
        return !empty($result);
    }

    /**
     * Update passenger name update status
     */
    public function updatePaxNameUpdateStatus($paxId, $status = 'Name Updated', $checkOn = null, $check = 'Amadeus Name Update')
    {
        $checkOn = $checkOn ?? date('Y-m-d H:i:s');
        
        $query = "
            UPDATE wpk4_backend_travel_booking_pax 
            SET pax_status = :status, 
                name_update_check_on = :check_on, 
                name_update_check = :check
            WHERE auto_id = :pax_id
        ";
        
        return $this->execute($query, [
            'status' => $status,
            'check_on' => $checkOn,
            'check' => $check,
            'pax_id' => $paxId
        ]);
    }

    /**
     * Get passenger meal and wheelchair preferences
     */
    public function getPaxMealWheelchair($paxId)
    {
        $query = "
            SELECT meal, wheelchair 
            FROM wpk4_backend_travel_booking_pax 
            WHERE auto_id = :pax_id
            LIMIT 1
        ";
        
        return $this->queryOne($query, ['pax_id' => $paxId]);
    }

    /**
     * Get order date by order ID
     */
    public function getOrderDate($orderId)
    {
        $query = "
            SELECT order_date 
            FROM wpk4_backend_travel_bookings 
            WHERE order_id = :order_id
            LIMIT 1
        ";
        
        $result = $this->queryOne($query, ['order_id' => $orderId]);
        return $result ? $result['order_date'] : null;
    }

    /**
     * Get seat availability (pax and stock) by trip code and travel date
     */
    public function getSeatAvailability($tripCode, $travelDate)
    {
        $query = "
            SELECT pax, stock 
            FROM wpk4_backend_manage_seat_availability 
            WHERE trip_code = :trip_code 
            AND travel_date = :travel_date 
            ORDER BY auto_id 
            LIMIT 1
        ";
        
        return $this->queryOne($query, [
            'trip_code' => $tripCode,
            'travel_date' => $travelDate
        ]);
    }

    /**
     * Create SSR update log entry
     */
    public function createSSRUpdateLog($data)
    {
        $query = "
            INSERT INTO wpk4_amadeus_ssr_update_log 
            (pnr, office_id, order_id, pax_id, amadeus_reference, request, response, status, added_by, ssr_type)
            VALUES (:pnr, :office_id, :order_id, :pax_id, :amadeus_reference, :request, :response, :status, :added_by, :ssr_type)
        ";
        
        $this->execute($query, [
            'pnr' => $data['pnr'],
            'office_id' => $data['office_id'] ?? null,
            'order_id' => $data['order_id'],
            'pax_id' => $data['pax_id'],
            'amadeus_reference' => $data['amadeus_reference'] ?? null,
            'request' => $data['request'] ?? null,
            'response' => $data['response'] ?? null,
            'status' => $data['status'] ?? 'SUCCESS',
            'added_by' => $data['added_by'] ?? 'system',
            'ssr_type' => $data['ssr_type'] ?? 'Meal'
        ]);
        
        return $this->lastInsertId();
    }

    /**
     * Create name update log with full details (including session, token, request, response)
     */
    public function createNameUpdateLogFull($data)
    {
        $query = "
            INSERT INTO wpk4_amadeus_name_update_log 
            (pnr, office_id, order_id, fname, lname, salutation, dob, status, session, token, request, response, added_by, airline, order_date, travel_date, pax_type, infant_booking_id)
            VALUES (:pnr, :office_id, :order_id, :fname, :lname, :salutation, :dob, :status, :session, :token, :request, :response, :added_by, :airline, :order_date, :travel_date, :pax_type, :infant_booking_id)
        ";
        
        $this->execute($query, [
            'pnr' => $data['pnr'],
            'office_id' => $data['office_id'] ?? null,
            'order_id' => $data['order_id'],
            'fname' => $data['fname'],
            'lname' => $data['lname'],
            'salutation' => $data['salutation'] ?? null,
            'dob' => $data['dob'] ?? null,
            'status' => $data['status'] ?? 'UNKNOWN',
            'session' => $data['session'] ?? null,
            'token' => $data['token'] ?? null,
            'request' => $data['request'] ?? null,
            'response' => $data['response'] ?? null,
            'added_by' => $data['added_by'] ?? 'system',
            'airline' => $data['airline'] ?? null,
            'order_date' => $data['order_date'] ?? null,
            'travel_date' => $data['travel_date'] ?? null,
            'pax_type' => $data['pax_type'] ?? 'ADT',
            'infant_booking_id' => $data['infant_booking_id'] ?? null
        ]);
        
        return $this->lastInsertId();
    }

    /**
     * Get passengers for name update (with null check_on condition)
     */
    public function getPassengersForNameUpdate($orderId, $includeInfants = false, $requirePaid = false)
    {
        // Match original query structure exactly - start from bookings table
        $productFilter = $includeInfants 
            ? "booking.product_id IN ('60107', '60116')" 
            : "booking.product_id NOT IN ('60107', '60116')";
        
        $paymentFilter = $requirePaid ? "AND booking.payment_status = 'paid'" : "";
        
        $query = "
            SELECT booking.order_id, pax.pnr, pax.auto_id as paxauto_id, 
                   pax.salutation, pax.fname, pax.lname, pax.dob, 
                   booking.trip_code, booking.travel_date, booking.adult_order
            FROM wpk4_backend_travel_bookings booking
            JOIN wpk4_backend_travel_booking_pax pax 
              ON booking.order_id = pax.order_id 
              AND booking.product_id = pax.product_id
            WHERE booking.order_id = :order_id 
              AND booking.order_type = 'WPT' 
              {$paymentFilter}
              AND {$productFilter}
              AND (pax.name_update_check_on IS NULL)
        ";
        
        return $this->query($query, ['order_id' => $orderId]);
    }

    /**
     * Get adult order passengers (for infant linking)
     */
    public function getAdultOrderPassengers($adultOrderId)
    {
        $query = "
            SELECT booking.order_id, pax.pnr, pax.auto_id as paxauto_id, 
                   pax.salutation, pax.fname, pax.lname, pax.dob, 
                   booking.trip_code, booking.travel_date, booking.adult_order
            FROM wpk4_backend_travel_bookings booking
            JOIN wpk4_backend_travel_booking_pax pax 
              ON booking.order_id = pax.order_id 
              AND booking.product_id = pax.product_id
            WHERE booking.order_id = :adult_order_id 
              AND booking.order_type = 'WPT' 
              AND booking.payment_status = 'paid' 
              AND booking.product_id NOT IN ('60107', '60116')
              AND (pax.name_update_check_on IS NULL)
        ";
        
        return $this->query($query, ['adult_order_id' => $adultOrderId]);
    }
}

