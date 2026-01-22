<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class YpsilonUpdateDAL extends BaseDAL
{
    /**
     * Get booking pax by PNR
     */
    public function getBookingPaxByPnr($pnr)
    {
        $query = "
            SELECT * FROM wpk4_backend_travel_booking_pax
            WHERE pnr = :pnr
            ORDER BY auto_id DESC
            LIMIT 1
        ";
        return $this->queryOne($query, ['pnr' => $pnr]);
    }
    
    /**
     * Update booking pax by GDS pax ID
     */
    public function updateBookingPaxByGdsPaxId($orderId, $gdsPaxId, $data)
    {
        return $this->update(
            'wpk4_backend_travel_booking_pax',
            $data,
            [
                'order_id' => $orderId,
                'gds_pax_id' => $gdsPaxId
            ]
        );
    }
    
    /**
     * Update booking pax by name
     */
    public function updateBookingPaxByName($orderId, $fname, $lname, $data)
    {
        return $this->update(
            'wpk4_backend_travel_booking_pax',
            $data,
            [
                'order_id' => $orderId,
                'fname' => $fname,
                'lname' => $lname
            ]
        );
    }
    
    /**
     * Get bookings for update (unticketed, paid/partially paid, GDS orders)
     */
    public function getBookingsForUpdate($travelDateAfter = null, $orderDateAfter = null, $limit = 1)
    {
        $query = "
            SELECT DISTINCT
                booking.order_id,
                booking.source,
                pax.pnr,
                DATE(booking.order_date) AS order_date
            FROM wpk4_backend_travel_bookings booking
            JOIN wpk4_backend_travel_booking_pax pax
                ON booking.order_id = pax.order_id
                AND booking.co_order_id = pax.co_order_id
                AND booking.product_id = pax.product_id
            WHERE (pax.ticket_number IS NULL OR pax.ticket_number = '')
                AND booking.payment_status IN ('partially_paid', 'paid')
                AND booking.order_type = 'gds'
                AND booking.source != 'import'
        ";
        
        $params = [];
        
        if ($travelDateAfter) {
            $query .= " AND booking.travel_date > :travel_date_after";
            $params['travel_date_after'] = $travelDateAfter;
        }
        
        if ($orderDateAfter) {
            $query .= " AND booking.order_date > :order_date_after";
            $params['order_date_after'] = $orderDateAfter;
        }
        
        $query .= " AND LENGTH(pax.pnr) < 8";
        $query .= " ORDER BY booking.order_id DESC";
        $query .= " LIMIT " . (int)$limit;
        
        return $this->query($query, $params);
    }
    
    /**
     * Get GDS consolidator access ID
     */
    public function getGdsConsolidatorAccessId($agent)
    {
        $query = "
            SELECT accessid FROM wpk4_backend_gds_conso_id
            WHERE agent = :agent
        ";
        $result = $this->queryOne($query, ['agent' => $agent]);
        return $result ? $result['accessid'] : null;
    }
    
    /**
     * Get history of updates by order ID and meta key
     */
    public function getHistoryOfUpdates($orderId, $metaKey)
    {
        $query = "
            SELECT * FROM wpk4_backend_history_of_updates
            WHERE type_id = :order_id AND meta_key = :meta_key
            ORDER BY updated_on DESC
            LIMIT 1
        ";
        return $this->queryOne($query, [
            'order_id' => $orderId,
            'meta_key' => $metaKey
        ]);
    }
    
    /**
     * Insert history of updates
     */
    public function insertHistoryOfUpdates($orderId, $metaKey, $metaValue, $updatedBy)
    {
        $query = "
            INSERT INTO wpk4_backend_history_of_updates
            (type_id, meta_key, meta_value, updated_by, updated_on)
            VALUES (:type_id, :meta_key, :meta_value, :updated_by, :updated_on)
        ";
        
        $params = [
            'type_id' => $orderId,
            'meta_key' => $metaKey,
            'meta_value' => $metaValue,
            'updated_by' => $updatedBy,
            'updated_on' => date('Y-m-d H:i:s')
        ];
        
        return $this->insert($query, $params);
    }
    
    /**
     * Get history of meta changes by order ID
     */
    public function getHistoryOfMetaChanges($orderId)
    {
        $query = "
            SELECT * FROM wpk4_backend_history_of_meta_changes
            WHERE type_id = :order_id
            ORDER BY updated_on DESC, divider_id DESC
        ";
        return $this->query($query, ['order_id' => $orderId]);
    }
    
    /**
     * Get latest history of meta changes divider ID
     */
    public function getLatestMetaChangesDividerId($orderId)
    {
        $query = "
            SELECT divider_id FROM wpk4_backend_history_of_meta_changes
            WHERE type_id = :order_id
            ORDER BY updated_on DESC, divider_id DESC
            LIMIT 1
        ";
        $result = $this->queryOne($query, ['order_id' => $orderId]);
        return $result ? (int)$result['divider_id'] : 0;
    }
    
    /**
     * Insert history of meta changes
     */
    public function insertHistoryOfMetaChanges($orderId, $metaKey, $metaValue, $updatedBy, $dividerId)
    {
        $query = "
            INSERT INTO wpk4_backend_history_of_meta_changes
            (type_id, meta_key, meta_value, updated_by, updated_on, divider_id)
            VALUES (:type_id, :meta_key, :meta_value, :updated_by, :updated_on, :divider_id)
        ";
        
        $params = [
            'type_id' => $orderId,
            'meta_key' => $metaKey,
            'meta_value' => $metaValue,
            'updated_by' => $updatedBy,
            'updated_on' => date('Y-m-d H:i:s'),
            'divider_id' => $dividerId
        ];
        
        return $this->insert($query, $params);
    }
    
    /**
     * Delete history of updates
     */
    public function deleteHistoryOfUpdates($orderId, $metaKeyPatterns = [])
    {
        $query = "DELETE FROM wpk4_backend_history_of_updates WHERE type_id = :order_id";
        $params = ['order_id' => $orderId];
        
        if (!empty($metaKeyPatterns)) {
            $conditions = [];
            foreach ($metaKeyPatterns as $pattern) {
                $conditions[] = "meta_key LIKE :pattern_" . count($conditions);
                $params['pattern_' . count($conditions)] = $pattern;
            }
            $query .= " AND (" . implode(" OR ", $conditions) . ")";
        }
        
        return $this->execute($query, $params);
    }
    
    /**
     * Get booking by order ID
     */
    public function getBookingByOrderId($orderId)
    {
        $query = "
            SELECT * FROM wpk4_backend_travel_bookings
            WHERE order_id = :order_id
        ";
        return $this->queryOne($query, ['order_id' => $orderId]);
    }
    
    /**
     * Update booking
     */
    public function updateBooking($orderId, $data)
    {
        return $this->update(
            'wpk4_backend_travel_bookings',
            $data,
            ['order_id' => $orderId]
        );
    }
    
    /**
     * Get airline code by IATA code
     */
    public function getAirlineCodeByIata($iataCode)
    {
        $query = "
            SELECT * FROM wpk4_backend_travel_booking_airline_code
            WHERE iata_code = :iata_code
        ";
        return $this->queryOne($query, ['iata_code' => $iataCode]);
    }
    
    /**
     * Insert GDS PNR checkup record
     */
    public function insertGdsPnrCheckup($pnr, $status, $requestCode, $requestedBy)
    {
        $query = "
            INSERT INTO wpk4_backend_travel_booking_gds_pnr_checkup
            (pnr, status, request_code, requested_by, requested_on)
            VALUES (:pnr, :status, :request_code, :requested_by, :requested_on)
        ";
        
        $params = [
            'pnr' => $pnr,
            'status' => $status,
            'request_code' => $requestCode,
            'requested_by' => $requestedBy,
            'requested_on' => date('Y-m-d H:i:s')
        ];
        
        return $this->insert($query, $params);
    }
}

