<?php
/**
 * Agent Notes DAL
 * Data Access Layer for agent notes operations
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class AgentNotesDAL extends BaseDAL
{
    /**
     * Get agent notes with filters
     */
    public function getAgentNotes(
        ?string $updatedDateStart = null,
        ?string $updatedDateEnd = null,
        ?string $callDate = null,
        ?string $department = null,
        ?string $category = null
    ): array {
        $whereParts = [];
        $params = [];

        // Updated date filter
        if ($updatedDateStart && $updatedDateEnd) {
            $whereParts[] = "DATE(a.updated_on) >= ? AND DATE(a.updated_on) <= ?";
            $params[] = $updatedDateStart;
            $params[] = $updatedDateEnd;
        } elseif ($updatedDateStart) {
            $whereParts[] = "DATE(a.updated_on) >= ?";
            $params[] = $updatedDateStart;
        } else {
            // Default to today if no date specified
            $whereParts[] = "DATE(a.updated_on) = CURRENT_DATE()";
        }

        // Call date filter
        if ($callDate) {
            $whereParts[] = "DATE(e.call_date) = ?";
            $params[] = $callDate;
        }

        // Department filter
        if ($department) {
            $whereParts[] = "b.meta_value = ?";
            $params[] = $department;
        }

        // Category filter
        if ($category) {
            $whereParts[] = "c.meta_value = ?";
            $params[] = $category;
        }

        // Always filter by meta_key and additional_note
        $whereParts[] = "a.meta_key = 'Booking Note Description'";
        $whereParts[] = "a.additional_note = 'Noble'";

        $whereSQL = implode(' AND ', $whereParts);

        $sql = "
            SELECT DISTINCT
                a.auto_id,
                a.type_id,
                a.updated_on,
                d.agent_info,
                d.payment_status,
                a.meta_value AS description,
                b.meta_value AS Department,
                c.meta_value AS Category,
                e.call_date,
                e.call_time,
                e.appl,
                e.email_contant as call_duration,
                e.status,
                e.addi_status,
                e.calling_phone,
                a.updated_by
            FROM wpk4_backend_history_of_updates a
            LEFT JOIN wpk4_backend_history_of_updates b
                ON a.type_id = b.type_id
                AND b.meta_key = 'Booking Note Department'
                AND b.additional_note = 'Noble'
            LEFT JOIN wpk4_backend_history_of_updates c
                ON a.type_id = c.type_id
                AND c.meta_key = 'Booking Note Category'
                AND c.additional_note = 'Noble'
            LEFT JOIN wpk4_backend_travel_bookings d
                ON a.type_id = d.order_id
            LEFT JOIN wpk4_backend_agent_nobel_data_travel e
                ON a.auto_id = e.call_feedback
            WHERE {$whereSQL}
            ORDER BY a.updated_on DESC
        ";

        return $this->query($sql, $params);
    }
}

