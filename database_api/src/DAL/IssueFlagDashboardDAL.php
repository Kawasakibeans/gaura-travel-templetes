<?php
/**
 * Issue Flag Dashboard DAL - Data Access Layer
 */

namespace App\DAL;

use Exception;

class IssueFlagDashboardDAL extends BaseDAL
{
    /**
     * Get ticketing issues - Query 1: Date gap > 10 days
     */
    public function getTicketingIssues1()
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $bookingsTable = $tablePrefix . 'backend_travel_bookings';
        $paxTable = $tablePrefix . 'backend_travel_booking_pax';
        $issueMessageTable = $tablePrefix . 'ticketing_screen_issue_message';
        
        $sql = "
            SELECT DISTINCT
                tb.order_id,
                tb.order_type,
                tb.travel_date,
                tb.trip_code,
                pax.late_modified,
                pax.ticketed_on,
                pax.fname,
                pax.lname,
                ism.date as issue_date
            FROM {$bookingsTable} tb
            INNER JOIN {$paxTable} pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
            LEFT JOIN {$issueMessageTable} ism ON 1=1
            WHERE tb.order_type = 'WPT'
            AND tb.travel_date > CURDATE()
            AND DATEDIFF(
                COALESCE(pax.late_modified, pax.ticketed_on),
                DATE_ADD(CURDATE(), INTERVAL COALESCE(ism.date, 0) DAY)
            ) > 10
            LIMIT 100
        ";
        
        return $this->query($sql, []);
    }
    
    /**
     * Get ticketing issues - Query 2: Ticket number empty but ticketed_on/ticketed_by not empty
     */
    public function getTicketingIssues2()
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $bookingsTable = $tablePrefix . 'backend_travel_bookings';
        $paxTable = $tablePrefix . 'backend_travel_booking_pax';
        
        $sql = "
            SELECT DISTINCT
                tb.order_id,
                tb.order_type,
                tb.travel_date,
                tb.trip_code,
                pax.ticket_number,
                pax.ticketed_on,
                pax.ticketed_by,
                pax.fname,
                pax.lname
            FROM {$bookingsTable} tb
            INNER JOIN {$paxTable} pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
            WHERE tb.travel_date > CURDATE()
            AND (pax.ticket_number IS NULL OR pax.ticket_number = '')
            AND (pax.ticketed_on IS NOT NULL OR pax.ticketed_by IS NOT NULL)
            LIMIT 100
        ";
        
        return $this->query($sql, []);
    }
    
    /**
     * Get ticketing issues - Query 3: Ticket number not empty but ticketed_on/ticketed_by empty
     */
    public function getTicketingIssues3()
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $bookingsTable = $tablePrefix . 'backend_travel_bookings';
        $paxTable = $tablePrefix . 'backend_travel_booking_pax';
        
        $sql = "
            SELECT DISTINCT
                tb.order_id,
                tb.order_type,
                tb.travel_date,
                tb.trip_code,
                pax.ticket_number,
                pax.ticketed_on,
                pax.ticketed_by,
                pax.fname,
                pax.lname
            FROM {$bookingsTable} tb
            INNER JOIN {$paxTable} pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
            WHERE tb.travel_date > CURDATE()
            AND pax.ticket_number IS NOT NULL 
            AND pax.ticket_number != ''
            AND (pax.ticketed_on IS NULL OR pax.ticketed_by IS NULL)
            LIMIT 100
        ";
        
        return $this->query($sql, []);
    }
    
    /**
     * Get ticketing issues - Query 4: Pax status not 'Ticketed' but has ticketing info
     */
    public function getTicketingIssues4()
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $bookingsTable = $tablePrefix . 'backend_travel_bookings';
        $paxTable = $tablePrefix . 'backend_travel_booking_pax';
        
        $sql = "
            SELECT DISTINCT
                tb.order_id,
                tb.order_type,
                tb.travel_date,
                tb.trip_code,
                pax.pax_status,
                pax.ticket_number,
                pax.fname,
                pax.lname
            FROM {$bookingsTable} tb
            INNER JOIN {$paxTable} pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
            WHERE tb.travel_date > CURDATE()
            AND pax.pax_status != 'Ticketed'
            AND (pax.ticket_number IS NOT NULL OR pax.ticketed_on IS NOT NULL OR pax.ticketed_by IS NOT NULL)
            LIMIT 100
        ";
        
        return $this->query($sql, []);
    }
    
    /**
     * Get ticketing issues - Query 5: Name updated field empty but has ticketing info
     */
    public function getTicketingIssues5()
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $bookingsTable = $tablePrefix . 'backend_travel_bookings';
        $paxTable = $tablePrefix . 'backend_travel_booking_pax';
        
        $sql = "
            SELECT DISTINCT
                tb.order_id,
                tb.order_type,
                tb.travel_date,
                tb.trip_code,
                pax.name_updated,
                pax.fname,
                pax.lname
            FROM {$bookingsTable} tb
            INNER JOIN {$paxTable} pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
            WHERE tb.travel_date > CURDATE()
            AND (pax.name_updated IS NULL OR pax.name_updated = '')
            AND (pax.ticket_number IS NOT NULL OR pax.ticketed_on IS NOT NULL OR pax.ticketed_by IS NOT NULL)
            LIMIT 100
        ";
        
        return $this->query($sql, []);
    }
    
    /**
     * Get name update issues
     */
    public function getNameUpdateIssues()
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $bookingsTable = $tablePrefix . 'backend_travel_bookings';
        $paxTable = $tablePrefix . 'backend_travel_booking_pax';
        
        $sql = "
            SELECT DISTINCT
                tb.order_id,
                tb.order_type,
                tb.travel_date,
                tb.trip_code,
                tb.payment_status,
                pax.name_update_check,
                pax.name_update_check_on,
                pax.fname,
                pax.lname
            FROM {$bookingsTable} tb
            INNER JOIN {$paxTable} pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
            WHERE tb.order_type = 'WPT'
            AND tb.travel_date > CURDATE()
            AND tb.payment_status = 'paid'
            AND tb.trip_code NOT LIKE '%QF%'
            AND (
                pax.name_update_check IS NULL 
                OR pax.name_update_check = '' 
                OR pax.name_update_check_on IS NULL
            )
            LIMIT 100
        ";
        
        return $this->query($sql, []);
    }
    
    /**
     * Get PNR validation issues
     */
    public function getPnrValidationIssues()
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $bookingsTable = $tablePrefix . 'backend_travel_bookings';
        $paxTable = $tablePrefix . 'backend_travel_booking_pax';
        $stockTable = $tablePrefix . 'backend_stock_management_sheet';
        
        $sql = "
            SELECT DISTINCT
                tb.order_id,
                tb.order_type,
                tb.travel_date,
                tb.trip_code,
                pax.pnr as pax_pnr,
                (SELECT GROUP_CONCAT(DISTINCT pnr SEPARATOR ', ') 
                 FROM {$stockTable} 
                 WHERE trip_id = tb.trip_code AND dep_date = tb.travel_date) as stock_pnrs
            FROM {$bookingsTable} tb
            INNER JOIN {$paxTable} pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
            WHERE tb.travel_date > CURDATE()
            AND (
                tb.payment_status = 'paid' 
                OR (tb.payment_status = 'partially_paid' AND tb.order_type = 'WPT')
            )
            AND pax.pnr IS NOT NULL
            AND pax.pnr != ''
            AND NOT EXISTS (
                SELECT 1 
                FROM {$stockTable} sms
                WHERE sms.trip_id = tb.trip_code 
                AND sms.dep_date = tb.travel_date
                AND sms.pnr = pax.pnr
            )
            AND EXISTS (
                SELECT 1 
                FROM {$stockTable} sms2
                WHERE sms2.trip_id = tb.trip_code 
                AND sms2.dep_date = tb.travel_date
                AND sms2.pnr IS NOT NULL
                AND sms2.pnr != ''
            )
            LIMIT 100
        ";
        
        return $this->query($sql, []);
    }
    
    /**
     * Get pax count validation issues
     */
    public function getPaxCountValidationIssues()
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $bookingsTable = $tablePrefix . 'backend_travel_bookings';
        $paxTable = $tablePrefix . 'backend_travel_booking_pax';
        
        $sql = "
            SELECT 
                tb.order_id,
                tb.order_type,
                tb.travel_date,
                tb.trip_code,
                tb.total_pax,
                COUNT(
                    CASE 
                        WHEN tb.order_type = 'FIT' AND pax.DOB IS NOT NULL 
                            AND TIMESTAMPDIFF(YEAR, pax.DOB, CURDATE()) < 2 
                        THEN NULL
                        ELSE pax.auto_id 
                    END
                ) as actual_pax_count
            FROM {$bookingsTable} tb
            LEFT JOIN {$paxTable} pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
            WHERE tb.travel_date > CURDATE()
            AND tb.total_pax > 0
            GROUP BY tb.order_id, tb.order_type, tb.travel_date, tb.trip_code, tb.total_pax
            HAVING tb.total_pax != COUNT(
                CASE 
                    WHEN tb.order_type = 'FIT' AND pax.DOB IS NOT NULL 
                        AND TIMESTAMPDIFF(YEAR, pax.DOB, CURDATE()) < 2 
                    THEN NULL
                    ELSE pax.auto_id 
                END
            )
            LIMIT 100
        ";
        
        return $this->query($sql, []);
    }
    
    /**
     * Get payment issues - Query 1: Payment exists but ticket number missing
     */
    public function getPaymentIssues1()
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $bookingsTable = $tablePrefix . 'backend_travel_bookings';
        $paymentTable = $tablePrefix . 'backend_travel_payment_history';
        $paxTable = $tablePrefix . 'backend_travel_booking_pax';
        
        $sql = "
            SELECT DISTINCT
                tb.order_id,
                tb.order_type,
                tb.travel_date,
                tb.trip_code,
                pax.ticket_number,
                ph.auto_id as payment_record_id
            FROM {$bookingsTable} tb
            INNER JOIN {$paymentTable} ph ON tb.order_id = ph.order_id
            INNER JOIN {$paxTable} pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
            WHERE tb.travel_date > CURDATE()
            AND tb.payment_status = 'paid'
            AND (pax.ticket_number IS NULL OR pax.ticket_number = '')
            LIMIT 100
        ";
        
        return $this->query($sql, []);
    }
    
    /**
     * Get payment issues - Query 2: Total amount mismatch
     */
    public function getPaymentIssues2()
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $bookingsTable = $tablePrefix . 'backend_travel_bookings';
        $paymentTable = $tablePrefix . 'backend_travel_payment_history';
        
        $sql = "
            SELECT 
                tb.order_id,
                tb.order_type,
                tb.travel_date,
                tb.trip_code,
                tb.total_amount,
                SUM(ph.trams_received_amount) as total_paid
            FROM {$bookingsTable} tb
            INNER JOIN {$paymentTable} ph ON tb.order_id = ph.order_id
            WHERE tb.travel_date > CURDATE()
            AND tb.payment_status = 'paid'
            GROUP BY tb.order_id, tb.order_type, tb.travel_date, tb.trip_code, tb.total_amount
            HAVING ROUND(tb.total_amount, 2) != ROUND(SUM(ph.trams_received_amount), 2)
            LIMIT 100
        ";
        
        return $this->query($sql, []);
    }
    
    /**
     * Get GDS ticketing issues
     */
    public function getGdsTicketingIssues()
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $bookingsTable = $tablePrefix . 'backend_travel_bookings';
        $paxTable = $tablePrefix . 'backend_travel_booking_pax';
        
        $sql = "
            SELECT DISTINCT
                tb.order_id,
                tb.order_type,
                tb.travel_date,
                tb.trip_code,
                tb.order_date,
                pax.ticket_number,
                pax.fname,
                pax.lname
            FROM {$bookingsTable} tb
            INNER JOIN {$paxTable} pax ON tb.order_id = pax.order_id AND tb.product_id = pax.product_id
            WHERE tb.travel_date > CURDATE()
            AND tb.order_type = 'gds'
            AND tb.payment_status = 'paid'
            AND tb.order_date < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND (pax.ticket_number IS NULL OR pax.ticket_number = '')
            LIMIT 100
        ";
        
        return $this->query($sql, []);
    }
    
    /**
     * Get payment status mismatch issues
     */
    public function getPaymentStatusIssues()
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $bookingsTable = $tablePrefix . 'backend_travel_bookings';
        $paymentTable = $tablePrefix . 'backend_travel_payment_history';
        
        $sql = "
            SELECT DISTINCT
                tb.order_id,
                tb.order_type,
                tb.travel_date,
                tb.trip_code,
                tb.payment_status,
                SUM(ph.trams_received_amount) as total_received
            FROM {$bookingsTable} tb
            INNER JOIN {$paymentTable} ph ON tb.order_id = ph.order_id
            WHERE tb.travel_date > CURDATE()
            AND tb.payment_status NOT IN ('paid', 'refund')
            GROUP BY tb.order_id, tb.order_type, tb.travel_date, tb.trip_code, tb.payment_status
            HAVING SUM(ph.trams_received_amount) > 0
            LIMIT 100
        ";
        
        return $this->query($sql, []);
    }
    
    /**
     * Get duplicate order issues - GDS
     */
    public function getDuplicateOrderIssuesGds()
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $bookingsTable = $tablePrefix . 'backend_travel_bookings';
        
        $sql = "
            SELECT 
                order_id,
                order_type,
                MIN(travel_date) as travel_date,
                MIN(trip_code) as trip_code,
                COUNT(*) as record_count
            FROM {$bookingsTable}
            WHERE travel_date > CURDATE()
            AND order_type = 'gds'
            GROUP BY order_id, order_type
            HAVING COUNT(*) >= 2
            LIMIT 100
        ";
        
        return $this->query($sql, []);
    }
    
    /**
     * Get duplicate order issues - WPT
     */
    public function getDuplicateOrderIssuesWpt()
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $bookingsTable = $tablePrefix . 'backend_travel_bookings';
        
        $sql = "
            SELECT 
                order_id,
                order_type,
                MIN(travel_date) as travel_date,
                MIN(trip_code) as trip_code,
                COUNT(*) as record_count
            FROM {$bookingsTable}
            WHERE travel_date > CURDATE()
            AND order_type = 'WPT'
            GROUP BY order_id, order_type
            HAVING COUNT(*) > 2
            LIMIT 100
        ";
        
        return $this->query($sql, []);
    }
    
    /**
     * Get booking notes issues
     */
    public function getBookingNotesIssues()
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $bookingsTable = $tablePrefix . 'backend_travel_bookings';
        $historyTable = $tablePrefix . 'backend_history_of_updates';
        
        $sql = "
            SELECT DISTINCT
                tb.order_id,
                tb.order_type,
                tb.travel_date,
                tb.trip_code,
                hou.meta_value as note_content,
                hou.updated_on as note_date
            FROM {$bookingsTable} tb
            INNER JOIN {$historyTable} hou ON tb.order_id = hou.type_id
            WHERE tb.travel_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND hou.meta_key = 'Booking Note Category'
            LIMIT 100
        ";
        
        return $this->query($sql, []);
    }
    
    /**
     * Get active issue log issues
     */
    public function getActiveIssueLogIssues()
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $bookingsTable = $tablePrefix . 'backend_travel_bookings';
        $issueLogTable = $tablePrefix . 'backend_travel_booking_issue_log';
        
        $sql = "
            SELECT DISTINCT
                tb.order_id,
                tb.order_type,
                tb.travel_date,
                tb.trip_code,
                tb.payment_status,
                til.auto_id as issue_log_id,
                til.added_on as issue_created
            FROM {$bookingsTable} tb
            INNER JOIN {$issueLogTable} til ON tb.order_id = til.order_id
            WHERE tb.travel_date > CURDATE()
            AND tb.payment_status = 'paid'
            AND til.status = 'active'
            LIMIT 100
        ";
        
        return $this->query($sql, []);
    }
}

