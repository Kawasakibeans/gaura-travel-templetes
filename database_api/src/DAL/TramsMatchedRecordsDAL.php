<?php
/**
 * Data access for TRAMS matched records.
 */

namespace App\DAL;

class TramsMatchedRecordsDAL extends BaseDAL
{
    /**
     * @param array<int,string> $invoiceNumbers
     * @return array<int,string>
     */
    public function getExistingInvoiceNumbers(array $invoiceNumbers): array
    {
        if (empty($invoiceNumbers)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($invoiceNumbers), '?'));
        $sql = "
            SELECT invoicenumber
            FROM wpk4_backend_trams_invoice
            WHERE invoicenumber IN ($placeholders)
        ";

        return array_map(
            static fn ($row) => (string)$row['invoicenumber'],
            $this->query($sql, array_values($invoiceNumbers))
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updateInvoice(string $invoiceNumber, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $sets = [];
        $params = [];
        foreach ($data as $column => $value) {
            $sets[] = "{$column} = ?";
            $params[] = $value;
        }
        $params[] = $invoiceNumber;

        $sql = "
            UPDATE wpk4_backend_trams_invoice
            SET " . implode(', ', $sets) . ",
                syncmoddatetime = NOW()
            WHERE invoicenumber = ?
        ";

        $this->execute($sql, $params);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getInvoicesByIssueDate(string $start, string $end): array
    {
        $sql = "
            SELECT
                invoicenumber                AS invoice_number,
                client_linkno                AS client_link_no,
                DATE_FORMAT(issuedate, '%Y-%m-%d') AS issue_date,
                branch_linkno                AS branch_link_no,
                recordlocator                AS record_locator,
                paystatus_linkcode           AS pay_status,
                invoicetype_linkcode         AS invoice_type,
                partpayamt                   AS partial_payment_amount,
                invoicegroup                 AS invoice_group,
                firstinsideagentbkg_linkno   AS first_inside_agent,
                firstoutsideagentbkg_linkno  AS first_outside_agent,
                calcinvoicenumber            AS calculated_invoice_number,
                altinvoicenumber             AS alternate_invoice_number,
                arc_linkno                   AS arc_link_no,
                DATE_FORMAT(pnrcreationdate, '%Y-%m-%d') AS pnr_creation_date,
                receivedby                   AS received_by,
                facturano                    AS factura_no,
                serviciono                   AS servicio_no,
                itininvremarks               AS itinerary_remarks,
                homehost_linkno              AS home_host_link_no,
                marketid                     AS market_id,
                agency_linkno                AS agency_link_no,
                accountingremarks            AS accounting_remarks,
                remarks                      AS remarks
            FROM wpk4_backend_trams_invoice
            WHERE issuedate BETWEEN ? AND ?
            ORDER BY issuedate DESC
        ";

        return $this->query($sql, [$start, $end]);
    }
}

