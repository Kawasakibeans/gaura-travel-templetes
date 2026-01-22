<?php
namespace App\DAL;

use Exception;

class HotfileIataDAL extends BaseDAL
{
    public function insertRecords(array $records, string $addedBy): int
    {
        if (empty($records)) {
            return 0;
        }

        $sql = "
            INSERT INTO wpk4_backend_travel_booking_ticket_number_hotfile
                (`group`, vendor, iata_no, document, a_l, pax_ticket_name, plated_carrier_code, plated_carrier, dom, published_remit,
                 reporting_system_code, pnr, document_type, issue_date, departure_date, currency, tour_code, conj_tkt_indicator,
                 emd_remarks, cash_fare, credit_fare, gross_fare, comm, fare, cash_tax, credit_tax, yq_tax, yr_tax, tax, transaction_amount,
                 added_on, added_by)
            VALUES
                (:group, :vendor, :iata_no, :document, :a_l, :pax_ticket_name, :plated_carrier_code, :plated_carrier, :dom, :published_remit,
                 :reporting_system_code, :pnr, :document_type, :issue_date, :departure_date, :currency, :tour_code, :conj_tkt_indicator,
                 :emd_remarks, :cash_fare, :credit_fare, :gross_fare, :comm, :fare, :cash_tax, :credit_tax, :yq_tax, :yr_tax, :tax, :transaction_amount,
                 NOW(), :added_by)
        ";

        $stmt = $this->db->prepare($sql);

        try {
            $this->beginTransaction();

            foreach ($records as $record) {
                $stmt->execute([
                    ':group' => $record['group'] ?? null,
                    ':vendor' => $record['vendor'] ?? null,
                    ':iata_no' => $record['iata_no'] ?? null,
                    ':document' => $record['document'] ?? null,
                    ':a_l' => $record['a_l'] ?? null,
                    ':pax_ticket_name' => $record['pax_ticket_name'] ?? null,
                    ':plated_carrier_code' => $record['plated_carrier_code'] ?? null,
                    ':plated_carrier' => $record['plated_carrier'] ?? null,
                    ':dom' => $record['dom'] ?? null,
                    ':published_remit' => $record['published_remit'] ?? null,
                    ':reporting_system_code' => $record['reporting_system_code'] ?? null,
                    ':pnr' => $record['pnr'] ?? null,
                    ':document_type' => $record['document_type'] ?? null,
                    ':issue_date' => $record['issue_date'] ?? null,
                    ':departure_date' => $record['departure_date'] ?? null,
                    ':currency' => $record['currency'] ?? null,
                    ':tour_code' => $record['tour_code'] ?? null,
                    ':conj_tkt_indicator' => $record['conj_tkt_indicator'] ?? null,
                    ':emd_remarks' => $record['emd_remarks'] ?? null,
                    ':cash_fare' => $record['cash_fare'] ?? 0,
                    ':credit_fare' => $record['credit_fare'] ?? 0,
                    ':gross_fare' => $record['gross_fare'] ?? 0,
                    ':comm' => $record['comm'] ?? 0,
                    ':fare' => $record['fare'] ?? 0,
                    ':cash_tax' => $record['cash_tax'] ?? 0,
                    ':credit_tax' => $record['credit_tax'] ?? 0,
                    ':yq_tax' => $record['yq_tax'] ?? 0,
                    ':yr_tax' => $record['yr_tax'] ?? 0,
                    ':tax' => $record['tax'] ?? 0,
                    ':transaction_amount' => $record['transaction_amount'] ?? 0,
                    ':added_by' => $addedBy,
                ]);
            }

            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }

        return count($records);
    }

    public function getRecords(string $fromDate, string $toDate): array
    {
        $sql = "
            SELECT
                h.*,
                (
                    SELECT COUNT(*)
                    FROM wpk4_backend_travel_booking_ticket_number t
                    WHERE COALESCE(NULLIF(t.pnr, ''), '__') = COALESCE(NULLIF(h.pnr, ''), '__')
                      AND COALESCE(NULLIF(t.document, ''), '__') = COALESCE(NULLIF(h.document, ''), '__')
                      AND COALESCE(NULLIF(t.document_type, ''), '__') = COALESCE(NULLIF(h.document_type, ''), '__')
                      AND COALESCE(NULLIF(t.transaction_amount, ''), '0') = COALESCE(NULLIF(h.transaction_amount, ''), '0')
                      AND COALESCE(NULLIF(t.vendor, ''), '__') = COALESCE(NULLIF(h.vendor, ''), '__')
                      AND COALESCE(NULLIF(t.a_l, ''), '__') = COALESCE(NULLIF(h.a_l, ''), '__')
                      AND COALESCE(NULLIF(t.total_doc, ''), '0') = COALESCE(NULLIF(h.total_doc, ''), '0')
                      AND COALESCE(NULLIF(t.tax, ''), '0') = COALESCE(NULLIF(h.tax, ''), '0')
                      AND COALESCE(NULLIF(t.fee, ''), '0') = COALESCE(NULLIF(h.fee, ''), '0')
                      AND COALESCE(NULLIF(t.comm, ''), '0') = COALESCE(NULLIF(h.comm, ''), '0')
                      AND COALESCE(NULLIF(t.pax_ticket_name, ''), '__') = COALESCE(NULLIF(h.pax_ticket_name, ''), '__')
                ) AS match_count
            FROM wpk4_backend_travel_booking_ticket_number_hotfile h
            WHERE DATE(h.issue_date) BETWEEN :from_date AND :to_date
            ORDER BY h.issue_date DESC, h.auto_id DESC
        ";

        return $this->query($sql, [
            ':from_date' => $fromDate,
            ':to_date' => $toDate,
        ]);
    }
}


