<?php
/**
 * Data access for refund requests dashboard.
 */

namespace App\DAL;

class RefundRequestsDashboardDAL extends BaseDAL
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function getCases(string $startDate, string $endDate, ?string $status = null): array
    {
        $sql = "
            SELECT
                case_id,
                DATE(case_date) AS case_date,
                status,
                sub_status,
                priority,
                TRIM(reservation_ref) AS order_id_req
            FROM wpk4_backend_user_portal_requests
            WHERE case_type = 'refund'
              AND DATE(case_date) BETWEEN ? AND ?
        ";

        $params = [$startDate, $endDate];

        // Handle status filtering
        if ($status !== null && $status !== '') {
            if ($status === 'All Pending') {
                $sql .= " AND status = 'open'";
            } elseif ($status === 'All Airline Pending') {
                $sql .= " AND status = 'open' AND sub_status IN ('Refund FUP with Airline', 'Refund Applied')";
            } elseif (in_array(strtolower($status), ['open', 'processing', 'following up', 'follow-up rejected', 'follow-up accepted', 'awaiting ho', 'waiting binal approval', 'ready to be processed', 'need to be processed after travel date', 'refund applied', 'refund fup with airline', 'refund received'], true)) {
                if (strtolower($status) === 'open') {
                    $sql .= " AND status = 'open'";
                } else {
                    $sql .= " AND status = 'open' AND sub_status = ?";
                    $params[] = $status;
                }
            } elseif (in_array(strtolower($status), ['fail', 'success', 'invalid'], true)) {
                $sql .= " AND status = ?";
                $params[] = strtolower($status);
            }
        } else {
            // Default: exclude success and fail (show pending/open cases)
            $sql .= " AND status NOT IN ('success', 'fail')";
        }

        $sql .= " ORDER BY case_date DESC, case_id DESC";

        return $this->query($sql, $params);
    }

    /**
     * @param array<int|string> $caseIds
     * @return array<int,array<array<string,string>>>
     */
    public function getMetaForCases(array $caseIds): array
    {
        if (empty($caseIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($caseIds), '?'));
        $sql = "
            SELECT case_id, meta_key, meta_value
            FROM wpk4_backend_user_portal_request_meta
            WHERE case_id IN ($placeholders)
        ";

        $rows = $this->query($sql, array_values($caseIds));

        $grouped = [];
        foreach ($rows as $row) {
            $cid = (int)$row['case_id'];
            $grouped[$cid][] = [
                'meta_key' => (string)$row['meta_key'],
                'meta_value' => (string)$row['meta_value'],
            ];
        }

        return $grouped;
    }

    /**
     * @param array<int|string> $orderIds
     * @return array<string,string>
     */
    public function getPnrByOrderIds(array $orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $sql = "
            SELECT order_id, pnr
            FROM wpk4_backend_travel_booking_pax
            WHERE order_id IN ($placeholders)
              AND COALESCE(pnr, '') <> ''
        ";

        $rows = $this->query($sql, array_values($orderIds));

        $map = [];
        foreach ($rows as $row) {
            $oid = (string)$row['order_id'];
            $pnr = trim((string)$row['pnr']);
            if ($pnr === '') {
                continue;
            }
            $map[$oid] = $pnr;
        }

        return $map;
    }

    /**
     * @param array<int|string> $orderIds
     * @return array<string,string>
     */
    public function getTicketsByOrderIds(array $orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $sql = "
            SELECT order_id, ticket_number
            FROM wpk4_backend_travel_booking_pax
            WHERE order_id IN ($placeholders)
              AND COALESCE(ticket_number, '') <> ''
        ";

        $rows = $this->query($sql, array_values($orderIds));

        $tickets = [];
        foreach ($rows as $row) {
            $oid = (string)$row['order_id'];
            $ticket = trim((string)$row['ticket_number']);
            if ($ticket === '') {
                continue;
            }
            $tickets[$oid] = ($tickets[$oid] ?? []);
            if (!in_array($ticket, $tickets[$oid], true)) {
                $tickets[$oid][] = $ticket;
            }
        }

        $result = [];
        foreach ($tickets as $oid => $values) {
            $result[$oid] = implode(', ', $values);
        }

        return $result;
    }

    /**
     * @param array<int|string> $orderIds
     * @return array<int,array<string,mixed>>
     */
    public function getPaymentRowsByOrderIds(array $orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $sql = "
            SELECT
                tph.order_id,
                tph.trams_received_amount,
                COALESCE(NULLIF(aba.account_name, ''), NULLIF(tph.payment_method, '')) AS payment_label
            FROM wpk4_backend_travel_payment_history AS tph
            LEFT JOIN wpk4_backend_accounts_bank_account AS aba
                   ON aba.bank_id = tph.payment_method
            WHERE tph.order_id IN ($placeholders)
        ";

        return $this->query($sql, array_values($orderIds));
    }

    public function deleteMeta(int $caseId, string $metaKey): void
    {
        $sql = "
            DELETE FROM wpk4_backend_user_portal_request_meta
            WHERE case_id = ? AND meta_key = ?
        ";
        $this->execute($sql, [$caseId, $metaKey]);
    }

    public function insertMeta(int $caseId, string $metaKey, string $value): void
    {
        $sql = "
            INSERT INTO wpk4_backend_user_portal_request_meta (case_id, meta_key, meta_value)
            VALUES (?, ?, ?)
        ";
        $this->execute($sql, [$caseId, $metaKey, $value]);
    }
}
