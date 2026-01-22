<?php
/**
 * Ticket Reconciliation Upload DAL
 * Provides DB helpers for CSV verification & updates
 */

namespace App\DAL;

class TicketReconciliationUploadDAL extends BaseDAL
{
    private const PAST_DATA_TABLE = 'wpk4_backend_travel_booking_ticket_number_update_past_data';
    private const PAST_DATA_UPDATE_TABLE = 'wpk4_backend_travel_booking_ticket_number_update_past_data_2';
    private const TICKET_ONLY_TABLE = 'wpk4_backend_travel_booking_ticket_number_hotfile';

    /**
     * Fetch records from the past data table by document numbers
     *
     * @param array<int, string> $documents
     * @return array<string, array<string, mixed>>
     */
    public function getPastDataDocuments(array $documents): array
    {
        if (empty($documents)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($documents as $idx => $document) {
            $key = ':doc_' . $idx;
            $placeholders[] = $key;
            $params[$key] = $document;
        }

        // Based on actual table structure:
        // wpk4_backend_travel_booking_ticket_number_update_past_data doesn't have net_due or Passenger Name
        // We'll return document and set net_due to null, passenger_name to null
        $sql = "
            SELECT 
                document,
                NULL AS net_due,
                NULL AS passenger_name
            FROM " . self::PAST_DATA_TABLE . "
            WHERE document IN (" . implode(',', $placeholders) . ")
        ";

        $rows = $this->query($sql, $params);

        $map = [];
        foreach ($rows as $row) {
            $map[$row['document']] = $row;
        }
        return $map;
    }

    /**
     * Fetch records from the ticket-only table by document
     *
     * @param array<int, string> $documents
     * @return array<string, array<string, mixed>>
     */
    public function getTicketOnlyDocuments(array $documents): array
    {
        if (empty($documents)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($documents as $idx => $document) {
            $key = ':doc_' . $idx;
            $placeholders[] = $key;
            $params[$key] = $document;
        }

        // Based on actual table structure:
        // wpk4_backend_travel_booking_ticket_number_hotfile has net_due but no Passenger Name
        // Use CONCAT to combine pax_fname and pax_lname, or use pax_ticket_name
        $sql = "
            SELECT 
                document,
                net_due,
                COALESCE(
                    NULLIF(CONCAT(COALESCE(pax_fname, ''), ' ', COALESCE(pax_lname, '')), ' '),
                    pax_ticket_name,
                    ''
                ) AS passenger_name
            FROM " . self::TICKET_ONLY_TABLE . "
            WHERE document IN (" . implode(',', $placeholders) . ")
        ";

        $rows = $this->query($sql, $params);

        $map = [];
        foreach ($rows as $row) {
            $map[$row['document']] = $row;
        }
        return $map;
    }

    /**
     * Update record in the past data update table
     *
     * @param array<string, mixed> $payload
     */
    public function updatePastDataRecord(array $payload): array
    {
        $sql = "
            UPDATE " . self::PAST_DATA_UPDATE_TABLE . " SET
                transaction_amount = :transaction_amount,
                fare = :fare,
                vendor = :vendor,
                a_l = :a_l,
                tax = :tax,
                fee = :fee,
                comm = :comm,
                remark = :remark,
                tax_inr = :tax_inr,
                comm_inr = :comm_inr,
                transaction_amount_inr = :transaction_amount_inr,
                fare_inr = :fare_inr,
                added_on = NOW(),
                added_by = :added_by,
                confirmed = 'Confirmed'
            WHERE document = :document
              AND document_type = :document_type
              AND vendor = :vendor
        ";

        $params = [
            ':transaction_amount' => $payload['transaction_amount'] ?? 0,
            ':fare' => $payload['fare'] ?? 0,
            ':vendor' => $payload['vendor'] ?? '',
            ':a_l' => $payload['a_l'] ?? '',
            ':tax' => $payload['tax'] ?? 0,
            ':fee' => $payload['fee'] ?? 0,
            ':comm' => $payload['comm'] ?? 0,
            ':remark' => $payload['remark'] ?? '',
            ':tax_inr' => $payload['tax_inr'] ?? 0,
            ':comm_inr' => $payload['comm_inr'] ?? 0,
            ':transaction_amount_inr' => $payload['transaction_amount_inr'] ?? 0,
            ':fare_inr' => $payload['fare_inr'] ?? 0,
            ':added_by' => $payload['added_by'] ?? 'system',
            ':document' => $payload['document'] ?? '',
            ':document_type' => $payload['document_type'] ?? '',
        ];

        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute($params);

        return [
            'success' => $success,
            'rows_affected' => $success ? $stmt->rowCount() : 0,
        ];
    }
}

