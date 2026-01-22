<?php
/**
 * Customer Search Data Access Layer
 * Handles database operations for customer search
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class CustomerSearchDAL extends BaseDAL
{
    /**
     * Search customers by multiple criteria
     */
    public function searchCustomers($customerId, $familyId, $profileId, $orderId, $email, $phone, $limit)
    {
        $whereParts = [];
        $params = [];

        if ($customerId) {
            $whereParts[] = "passenger.customer_id = ?";
            $params[] = $customerId;
        }

        if ($familyId) {
            $whereParts[] = "passenger.family_id = ?";
            $params[] = $familyId;
        }

        if ($profileId) {
            $whereParts[] = "passenger.trams_profile_id = ?";
            $params[] = $profileId;
        }

        if ($orderId) {
            $whereParts[] = "bookings.order_id = ?";
            $params[] = $orderId;
        }

        if ($email) {
            $whereParts[] = "passenger.email_address = ?";
            $params[] = $email;
        }

        if ($phone) {
            $whereParts[] = "passenger.phone_number = ?";
            $params[] = $phone;
        }

        // Add default condition if no filters
        if (empty($whereParts)) {
            $whereParts[] = "passenger.customer_id IS NOT NULL";
        }

        $whereSQL = implode(' AND ', $whereParts);

        $query = "SELECT 
            passenger.customer_id, 
            passenger.family_id, 
            passenger.fname, 
            passenger.lname, 
            passenger.email_address, 
            passenger.phone_number, 
            bookings.order_id 
        FROM wpk4_backend_travel_passenger passenger
        LEFT JOIN wpk4_backend_travel_passenger_address ads 
            ON ads.address_id = passenger.address_id 
        LEFT JOIN wpk4_backend_travel_booking_pax pax 
            ON pax.fname = passenger.fname 
            AND pax.email_pax = passenger.email_address
        LEFT JOIN wpk4_backend_travel_bookings bookings 
            ON bookings.order_id = pax.order_id
        WHERE $whereSQL
        ORDER BY passenger.customer_id DESC 
        LIMIT ?";

        $params[] = $limit;

        return $this->query($query, $params);
    }

    /**
     * Get bookings by phone number
     */
    public function getBookingsByPhone($phoneNums, $limit)
    {
        $query = "SELECT bookings.*, pax.phone_pax, pax.phone_pax_cropped
        FROM wpk4_backend_travel_bookings bookings
        LEFT JOIN wpk4_backend_travel_booking_pax pax 
            ON bookings.order_id = pax.order_id
        WHERE pax.phone_pax_cropped = ?
        ORDER BY pax.auto_id DESC 
        LIMIT ?";

        return $this->query($query, [$phoneNums, $limit]);
    }

    /**
     * Get quotes by phone number
     */
    public function getQuotesByPhone($phone, $limit)
    {
        $query = "SELECT *
        FROM wpk4_quote
        WHERE phone_num LIKE ?
        ORDER BY id DESC 
        LIMIT ?";

        $phonePattern = '%' . $phone . '%';
        return $this->query($query, [$phonePattern, $limit]);
    }

    /**
     * Get customer by ID
     */
    public function getCustomerById($customerId)
    {
        $query = "SELECT passenger.*, ads.*
        FROM wpk4_backend_travel_passenger passenger
        LEFT JOIN wpk4_backend_travel_passenger_address ads 
            ON ads.address_id = passenger.address_id
        WHERE passenger.customer_id = ?
        LIMIT 1";

        return $this->queryOne($query, [$customerId]);
    }

    /**
     * Get customer's bookings
     */
    public function getCustomerBookings($customerId)
    {
        $query = "SELECT bookings.*
        FROM wpk4_backend_travel_bookings bookings
        LEFT JOIN wpk4_backend_travel_booking_pax pax 
            ON bookings.order_id = pax.order_id
        WHERE pax.customer_id = ?
        ORDER BY bookings.order_date DESC";

        return $this->query($query, [$customerId]);
    }
}

