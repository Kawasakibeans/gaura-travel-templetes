<?php
/**
 * TRAMS Invoice Data Access Layer
 * Handles all database operations for TRAMS invoice reconciliation
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class TramsInvoiceDAL extends BaseDAL
{
    /**
     * Check if invoices exist in database
     * 
     * @param array $invoiceNumbers Array of invoice numbers
     * @return array Array of existing invoice numbers
     */
    public function checkInvoiceExistence($invoiceNumbers)
    {
        if (empty($invoiceNumbers)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($invoiceNumbers), '?'));
        $sql = "SELECT invoicenumber FROM wpk4_backend_trams_invoice WHERE invoicenumber IN ($placeholders)";

        $results = $this->query($sql, array_values($invoiceNumbers));
        
        $existing = [];
        foreach ($results as $row) {
            $existing[] = $row['invoicenumber'];
        }
        
        return $existing;
    }

    /**
     * Update invoice record
     * 
     * @param array $data Invoice data
     * @return bool|string True on success, error message on failure
     */
    public function updateInvoice($data)
    {
        $sql = "UPDATE wpk4_backend_trams_invoice SET 
                client_linkno = ?,
                issuedate = ?,
                branch_linkno = ?,
                recordlocator = ?,
                paystatus_linkcode = ?,
                invoicetype_linkcode = ?,
                partpayamt = ?,
                invoicegroup = ?,
                firstinsideagentbkg_linkno = ?,
                firstoutsideagentbkg_linkno = ?,
                calcinvoicenumber = ?,
                altinvoicenumber = ?,
                arc_linkno = ?,
                pnrcreationdate = ?,
                receivedby = ?,
                facturano = ?,
                serviciono = ?,
                itininvremarks = ?,
                homehost_linkno = ?,
                syncmoddatetime = NOW(),
                marketid = ?,
                agency_linkno = ?,
                accountingremarks = ?,
                remarks = ?
            WHERE invoicenumber = ?";
        
        $params = [
            $data['client_linkno'] ?? null,
            $data['issuedate'] ?? null,
            $data['branch_linkno'] ?? null,
            $data['recordlocator'] ?? null,
            $data['paystatus_linkcode'] ?? null,
            $data['invoicetype_linkcode'] ?? null,
            $data['partpayamt'] ?? 0,
            $data['invoicegroup'] ?? null,
            $data['firstinsideagentbkg_linkno'] ?? null,
            $data['firstoutsideagentbkg_linkno'] ?? null,
            $data['calcinvoicenumber'] ?? null,
            $data['altinvoicenumber'] ?? null,
            $data['arc_linkno'] ?? null,
            $data['pnrcreationdate'] ?? null,
            $data['receivedby'] ?? null,
            $data['facturano'] ?? null,
            $data['serviciono'] ?? null,
            $data['itininvremarks'] ?? null,
            $data['homehost_linkno'] ?? null,
            $data['marketid'] ?? null,
            $data['agency_linkno'] ?? null,
            $data['accountingremarks'] ?? null,
            $data['remarks'] ?? null,
            $data['invoicenumber']
        ];

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            if ($stmt->rowCount() == 0) {
                return 'No record updated for Invoice: ' . ($data['invoicenumber'] ?? '');
            }
            
            return true;
        } catch (\Exception $e) {
            return 'SQL Error: ' . $e->getMessage();
        }
    }

    /**
     * Get invoice details
     * 
     * @param string $invoiceNumber Invoice number
     * @return array|null Invoice data or null if not found
     */
    public function getInvoiceDetails($invoiceNumber)
    {
        $sql = "
            SELECT * 
            FROM wpk4_backend_trams_invoice 
            WHERE invoicenumber = :invoice_number
            LIMIT 1
        ";

        $result = $this->queryOne($sql, ['invoice_number' => $invoiceNumber]);
        
        return $result;
    }

    /**
     * Batch update invoices
     * 
     * @param array $invoices Array of invoice data arrays
     * @return array Results with updated count and errors
     */
    public function batchUpdateInvoices($invoices)
    {
        $updated = 0;
        $errors = [];

        foreach ($invoices as $invoice) {
            $result = $this->updateInvoice($invoice);
            if ($result === true) {
                $updated++;
            } else {
                $errors[] = "Error updating invoice {$invoice['invoicenumber']}: " . $result;
            }
        }

        return [
            'updated' => $updated,
            'errors' => $errors,
            'total' => count($invoices)
        ];
    }
}

