<?php
/**
 * Nobel Insert Table Cron PIF DAL
 * Data Access Layer for updating PIF data in agent booking table
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class NobelInsertTableCronPifDAL extends BaseDAL
{
    /**
     * Update agent booking PIF data for last 4 days
     */
    public function updateAgentBookingPifData(): int
    {
        $sql = "
            UPDATE wpk4_backend_agent_booking ab
            JOIN (
                SELECT
                    DATE(a.order_date) AS order_date,
                    COUNT(DISTINCT CONCAT(b2.fname, b2.lname, a.agent_info, DATE(a.order_date))) AS pif,
                    COUNT(DISTINCT CONCAT(b.fname, b.lname, a.agent_info, DATE(a.order_date))) AS pax,
                    COUNT(DISTINCT CONCAT(b3.fname, b3.lname, a.agent_info, DATE(a.order_date))) AS fit,
                    COUNT(DISTINCT CONCAT(b4.fname, b4.lname, a.agent_info, DATE(a.order_date))) AS gdeals,
                    c.tsr
                FROM
                    wpk4_backend_travel_bookings a
                LEFT JOIN
                    wpk4_backend_travel_booking_pax b ON a.order_id = b.order_id 
                    AND (DATEDIFF(a.travel_date, b.dob) / 365) > 2 
                    AND DATE(b.order_date) BETWEEN (CURRENT_DATE() - INTERVAL 4 DAY) AND (CURRENT_DATE() - INTERVAL 1 DAY)
                LEFT JOIN
                    wpk4_backend_travel_booking_pax b2 ON a.order_id = b2.order_id 
                    AND a.payment_status = 'paid' 
                    AND (DATEDIFF(a.travel_date, b2.dob) / 365) > 2 
                    AND DATE(b2.order_date) BETWEEN (CURRENT_DATE() - INTERVAL 4 DAY) AND (CURRENT_DATE() - INTERVAL 1 DAY)
                LEFT JOIN
                    wpk4_backend_travel_booking_pax b3 ON a.order_id = b3.order_id 
                    AND (DATEDIFF(a.travel_date, b3.dob) / 365) > 2  
                    AND b3.order_type = 'gds' 
                    AND DATE(b3.order_date) BETWEEN (CURRENT_DATE() - INTERVAL 4 DAY) AND (CURRENT_DATE() - INTERVAL 1 DAY)
                LEFT JOIN
                    wpk4_backend_travel_booking_pax b4 ON a.order_id = b4.order_id 
                    AND (DATEDIFF(a.travel_date, b4.dob) / 365) > 2  
                    AND b4.order_type = 'wpt' 
                    AND DATE(b4.order_date) BETWEEN (CURRENT_DATE() - INTERVAL 4 DAY) AND (CURRENT_DATE() - INTERVAL 1 DAY)
                LEFT JOIN
                    wpk4_backend_agent_codes c ON a.agent_info = c.sales_id 
                    AND c.status = 'active'
                WHERE
                    DATE(a.order_date) BETWEEN (CURRENT_DATE() - INTERVAL 4 DAY) AND (CURRENT_DATE() - INTERVAL 1 DAY)
                    AND a.source <> 'import'
                GROUP BY
                    DATE(a.order_date), c.tsr
            ) AS temp_result ON ab.tsr = temp_result.tsr 
                AND DATE(ab.order_date) = DATE(temp_result.order_date)
            SET
                ab.pif = temp_result.pif,
                ab.pax = temp_result.pax,
                ab.gdeals = temp_result.gdeals,
                ab.fit = temp_result.fit
            WHERE 
                DATE(ab.order_date) BETWEEN (CURRENT_DATE() - INTERVAL 4 DAY) AND (CURRENT_DATE() - INTERVAL 1 DAY) 
                AND ab.tsr = temp_result.tsr 
                AND DATE(ab.order_date) = DATE(temp_result.order_date)
        ";
        
        $this->execute($sql, []);
        
        // Note: Affected rows count is not easily available with PDO
        // The update operation is successful if no exception is thrown
        return 1;
    }
}

