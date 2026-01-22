<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class CustomerViewBookingsDAL extends BaseDAL
{
    /**
     * Search bookings by PNR/Order ID and email
     */
    public function searchBookings(?string $searchId = null, ?string $email = null, int $limit = 20): array
    {
        if (!$searchId || !$email) {
            return [];
        }
        
        $isNumeric = ctype_digit($searchId);
        $refType = $isNumeric ? 'orderid' : 'pnr';
        
        if ($refType === 'pnr') {
            $filterSql = "wpk4_backend_travel_booking_pax.pnr LIKE :search_id AND wpk4_backend_travel_booking_pax.email_pax LIKE :email";
        } else {
            $filterSql = "wpk4_backend_travel_booking_pax.order_id = :search_id AND wpk4_backend_travel_booking_pax.email_pax LIKE :email";
        }
        
        $sql = "
            SELECT DISTINCT
                b.*,
                p.pnr,
                p.email_pax,
                p.phone_pax
            FROM wpk4_backend_travel_bookings b
            JOIN wpk4_backend_travel_booking_pax p ON b.order_id = p.order_id 
                AND b.co_order_id = p.co_order_id 
                AND b.product_id = p.product_id
            WHERE {$filterSql}
            ORDER BY p.order_id DESC
            LIMIT " . (int)$limit . "
        ";
        
        $params = [
            ':search_id' => $refType === 'pnr' ? '%' . $searchId . '%' : $searchId,
            ':email' => '%' . $email . '%'
        ];
        
        return $this->query($sql, $params);
    }
    
    /**
     * Get booking by order ID
     */
    public function getBookingByOrderId(string $orderId): ?array
    {
        $sql = "SELECT * FROM wpk4_backend_travel_bookings WHERE order_id = :order_id LIMIT 1";
        $result = $this->queryOne($sql, [':order_id' => $orderId]);
        return $result ?: null;
    }
    
    /**
     * Get booking summary (WPT and GDS)
     */
    public function getBookingSummary(string $orderId): array
    {
        $wptSql = "
            SELECT * FROM wpk4_backend_travel_bookings 
            WHERE order_id = :order_id 
            AND (order_type = 'WPT' OR order_type = '')
            ORDER BY travel_date ASC
        ";
        
        $gdsSql = "
            SELECT * FROM wpk4_backend_travel_bookings 
            WHERE order_id = :order_id 
            AND order_type = 'gds'
            ORDER BY travel_date ASC
        ";
        
        $wptBookings = $this->query($wptSql, [':order_id' => $orderId]);
        $gdsBookings = $this->query($gdsSql, [':order_id' => $orderId]);
        
        return [
            'wpt' => $wptBookings,
            'gds' => $gdsBookings
        ];
    }
    
    /**
     * Get pax details for booking
     */
    public function getPaxDetails(string $orderId, ?string $coOrderId = null, ?string $productId = null): array
    {
        $sql = "
            SELECT * FROM wpk4_backend_travel_booking_pax 
            WHERE order_id = :order_id
        ";
        
        $params = [':order_id' => $orderId];
        
        if ($coOrderId) {
            $sql .= " AND co_order_id = :co_order_id";
            $params[':co_order_id'] = $coOrderId;
        }
        
        if ($productId) {
            $sql .= " AND product_id = :product_id";
            $params[':product_id'] = $productId;
        }
        
        return $this->query($sql, $params);
    }
    
    /**
     * Get contact info (phone/email) for booking
     */
    public function getContactInfo(string $orderId): array
    {
        // Try to get from pax table first
        $sql = "
            SELECT phone_pax, email_pax 
            FROM wpk4_backend_travel_booking_pax 
            WHERE order_id = :order_id 
            AND (phone_pax != '' OR email_pax != '')
            LIMIT 1
        ";
        $result = $this->queryOne($sql, [':order_id' => $orderId]);
        
        $phone = $result['phone_pax'] ?? '';
        $email = $result['email_pax'] ?? '';
        
        // If not found, try history_of_updates
        if (empty($phone)) {
            $sql = "
                SELECT meta_value 
                FROM wpk4_backend_history_of_updates 
                WHERE type_id = :order_id 
                AND meta_key = 'Billing PrivatePhone'
                LIMIT 1
            ";
            $result = $this->queryOne($sql, [':order_id' => $orderId]);
            $phone = $result['meta_value'] ?? '';
        }
        
        if (empty($email)) {
            $sql = "
                SELECT meta_value 
                FROM wpk4_backend_history_of_updates 
                WHERE type_id = :order_id 
                AND meta_key = 'Billing Email'
                LIMIT 1
            ";
            $result = $this->queryOne($sql, [':order_id' => $orderId]);
            $email = $result['meta_value'] ?? '';
        }
        
        return [
            'phone' => $phone,
            'email' => $email
        ];
    }
    
    /**
     * Get payment history
     */
    public function getPaymentHistory(string $orderId): array
    {
        $sql = "
            SELECT * FROM wpk4_backend_travel_payment_history 
            WHERE order_id = :order_id
            ORDER BY process_date DESC
        ";
        
        return $this->query($sql, [':order_id' => $orderId]);
    }
    
    /**
     * Get payment attachments
     */
    public function getPaymentAttachments(string $orderId, ?string $coOrderId = null, ?string $productId = null): array
    {
        $sql = "
            SELECT * FROM wpk4_backend_travel_booking_update_history 
            WHERE order_id = :order_id
            AND meta_key LIKE 'G360Events'
            AND meta_value LIKE 'g360paymentattachments'
        ";
        
        $params = [':order_id' => $orderId];
        
        if ($coOrderId) {
            $sql .= " AND co_order_id = :co_order_id";
            $params[':co_order_id'] = $coOrderId;
        }
        
        if ($productId) {
            $sql .= " AND merging_id = :merging_id";
            $params[':merging_id'] = $productId;
        }
        
        $sql .= " ORDER BY auto_id DESC";
        
        return $this->query($sql, $params);
    }
    
    /**
     * Get portal requests for booking
     */
    public function getPortalRequests(string $orderId, ?array $pnrs = null): array
    {
        $sql = "
            SELECT * FROM wpk4_backend_user_portal_requests 
            WHERE reservation_ref = :order_id
        ";
        
        $params = [':order_id' => $orderId];
        
        if ($pnrs && !empty($pnrs)) {
            $placeholders = [];
            foreach ($pnrs as $i => $pnr) {
                $key = ':pnr' . $i;
                $placeholders[] = $key;
                $params[$key] = $pnr;
            }
            $sql .= " OR reservation_ref IN (" . implode(',', $placeholders) . ")";
        }
        
        return $this->query($sql, $params);
    }
}

