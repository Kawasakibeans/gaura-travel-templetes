<?php
/**
 * Amadeus Name Update Check Data Access Layer
 * Handles database operations for checking passengers that need name updates
 * Uses gaurat_gauratravel database
 */

namespace App\DAL;

use PDO;
use Exception;
use PDOException;

class AmadeusNameUpdateCheckDAL
{
    protected $db;

    public function __construct()
    {
        $this->db = $this->getConnection();
    }

    /**
     * Get database connection to gaurat_gauratravel
     */
    private function getConnection()
    {
        static $pdo = null;
        
        if ($pdo === null) {
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=utf8",
                $_ENV['AMADEUS_DB_HOST'] ?? 'localhost',
                $_ENV['AMADEUS_DB_PORT'] ?? '3306',
                $_ENV['AMADEUS_DB_NAME'] ?? 'gaurat_gauratravel'
            );
            
            $pdo = new PDO($dsn,
                $_ENV['AMADEUS_DB_USER'] ?? 'gaurat_sriharan',
                $_ENV['AMADEUS_DB_PASS'] ?? 'r)?2lc^Q0cAE',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }
        
        return $pdo;
    }

    /**
     * Get passengers that need name update check for an order
     */
    public function getPassengersForNameUpdate($orderId)
    {
        try {
            $query = "
                SELECT 
                    booking.order_id, 
                    pax.pnr, 
                    pax.auto_id as paxauto_id, 
                    pax.salutation, 
                    pax.fname, 
                    pax.lname, 
                    pax.dob, 
                    booking.trip_code, 
                    booking.travel_date 
                FROM wpk4_backend_travel_bookings booking
                JOIN wpk4_backend_travel_booking_pax pax 
                    ON booking.order_id = pax.order_id 
                    AND booking.product_id = pax.product_id
                WHERE booking.order_id = :order_id 
                    AND booking.order_type = 'WPT' 
                    AND booking.payment_status = 'paid' 
                    AND booking.product_id NOT IN ('60107', '60116') 
                    AND (pax.name_update_check_on IS NULL)
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['order_id' => $orderId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("AmadeusNameUpdateCheckDAL::getPassengersForNameUpdate error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Get PNR information from stock management sheet
     */
    public function getPnrInfo($tripCode, $travelDate)
    {
        try {
            $query = "
                SELECT pnr, OID, airline_code 
                FROM wpk4_backend_stock_management_sheet 
                WHERE trip_id = :trip_code 
                    AND dep_date = :travel_date
                LIMIT 1
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'trip_code' => $tripCode,
                'travel_date' => $travelDate
            ]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("AmadeusNameUpdateCheckDAL::getPnrInfo error: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Check if passenger already exists in name update log
     */
    public function checkPassengerExists($pnr, $orderId, $fname, $lname, $dob)
    {
        try {
            $query = "
                SELECT auto_id 
                FROM wpk4_amadeus_name_update_log 
                WHERE pnr = :pnr 
                    AND order_id = :order_id 
                    AND fname = :fname 
                    AND lname = :lname 
                    AND dob = :dob
                LIMIT 1
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'pnr' => $pnr,
                'order_id' => $orderId,
                'fname' => $fname,
                'lname' => $lname,
                'dob' => $dob
            ]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("AmadeusNameUpdateCheckDAL::checkPassengerExists error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if infant booking exists
     */
    public function checkInfantBooking($orderId)
    {
        try {
            $query = "
                SELECT auto_id 
                FROM wpk4_backend_travel_bookings 
                WHERE adult_order = :order_id
                LIMIT 1
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['order_id' => $orderId]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("AmadeusNameUpdateCheckDAL::checkInfantBooking error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get order date
     */
    public function getOrderDate($orderId)
    {
        try {
            $query = "
                SELECT order_date 
                FROM wpk4_backend_travel_bookings 
                WHERE order_id = :order_id
                LIMIT 1
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['order_id' => $orderId]);
            $result = $stmt->fetch();
            return $result ? $result['order_date'] : null;
        } catch (PDOException $e) {
            error_log("AmadeusNameUpdateCheckDAL::getOrderDate error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get max pax and stock for trip
     */
    public function getMaxPaxAndStock($tripCode, $travelDate)
    {
        try {
            $query = "
                SELECT pax, stock 
                FROM wpk4_backend_manage_seat_availability 
                WHERE trip_code = :trip_code 
                    AND travel_date = :travel_date 
                ORDER BY auto_id 
                LIMIT 1
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'trip_code' => $tripCode,
                'travel_date' => $travelDate
            ]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("AmadeusNameUpdateCheckDAL::getMaxPaxAndStock error: " . $e->getMessage());
            return null;
        }
    }
}

