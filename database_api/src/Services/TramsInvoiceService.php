<?php
/**
 * TRAMS Invoice Service - Business Logic Layer
 * Handles business logic for TRAMS invoice reconciliation
 */

namespace App\Services;

use App\DAL\TramsInvoiceDAL;
use Exception;

class TramsInvoiceService
{
    private $tramsInvoiceDAL;

    public function __construct()
    {
        $this->tramsInvoiceDAL = new TramsInvoiceDAL();
    }

    /**
     * Check if invoices exist
     * 
     * @param array $invoiceNumbers Array of invoice numbers
     * @return array Array with existing invoice numbers
     */
    public function checkInvoiceExistence($invoiceNumbers)
    {
        if (empty($invoiceNumbers)) {
            throw new Exception('Invoice numbers array is required', 400);
        }

        if (!is_array($invoiceNumbers)) {
            throw new Exception('Invoice numbers must be an array', 400);
        }

        // Limit to 1000 invoices per request
        if (count($invoiceNumbers) > 1000) {
            throw new Exception('Maximum 1000 invoice numbers allowed per request', 400);
        }

        $existing = $this->tramsInvoiceDAL->checkInvoiceExistence($invoiceNumbers);
        
        return [
            'success' => true,
            'total_checked' => count($invoiceNumbers),
            'existing_count' => count($existing),
            'not_found_count' => count($invoiceNumbers) - count($existing),
            'existing' => $existing,
            'not_found' => array_diff($invoiceNumbers, $existing)
        ];
    }

    /**
     * Update invoice record
     * 
     * @param array $data Invoice data
     * @return array Update result
     */
    public function updateInvoice($data)
    {
        if (empty($data['invoicenumber'])) {
            throw new Exception('Invoice number is required', 400);
        }

        // Parse dates if provided
        if (isset($data['issuedate'])) {
            $data['issuedate'] = $this->parseDate($data['issuedate']);
        }
        if (isset($data['pnrcreationdate'])) {
            $data['pnrcreationdate'] = $this->parseDate($data['pnrcreationdate']);
        }

        // Extract numeric value for partial payment amount
        if (isset($data['partpayamt'])) {
            $data['partpayamt'] = $this->extractNumeric($data['partpayamt']);
        }

        $result = $this->tramsInvoiceDAL->updateInvoice($data);
        
        if ($result === true) {
            return [
                'success' => true,
                'message' => 'Invoice updated successfully',
                'invoice_number' => $data['invoicenumber']
            ];
        } else {
            return [
                'success' => false,
                'message' => $result,
                'invoice_number' => $data['invoicenumber']
            ];
        }
    }

    /**
     * Batch update invoices
     * 
     * @param array $invoices Array of invoice data arrays
     * @return array Batch update results
     */
    public function batchUpdateInvoices($invoices)
    {
        if (empty($invoices)) {
            throw new Exception('Invoices array is required', 400);
        }

        if (!is_array($invoices)) {
            throw new Exception('Invoices must be an array', 400);
        }

        // Limit to 100 invoices per batch
        if (count($invoices) > 100) {
            throw new Exception('Maximum 100 invoices allowed per batch update', 400);
        }

        // Process each invoice data
        foreach ($invoices as &$invoice) {
            if (isset($invoice['Issue Date'])) {
                $invoice['issuedate'] = $this->parseDate($invoice['Issue Date']);
            }
            if (isset($invoice['PNR Creation Date'])) {
                $invoice['pnrcreationdate'] = $this->parseDate($invoice['PNR Creation Date']);
            }
            if (isset($invoice['Partial Payment Amount'])) {
                $invoice['partpayamt'] = $this->extractNumeric($invoice['Partial Payment Amount']);
            }
            
            // Map field names
            $invoice['invoicenumber'] = $invoice['Invoice #'] ?? $invoice['invoicenumber'] ?? '';
            $invoice['client_linkno'] = $invoice['Client Link No'] ?? $invoice['client_linkno'] ?? null;
            $invoice['branch_linkno'] = $invoice['Branch Link No'] ?? $invoice['branch_linkno'] ?? null;
            $invoice['recordlocator'] = $invoice['Record Locator'] ?? $invoice['recordlocator'] ?? null;
            $invoice['paystatus_linkcode'] = $invoice['Pay Status'] ?? $invoice['paystatus_linkcode'] ?? null;
            $invoice['invoicetype_linkcode'] = $invoice['Invoice Type'] ?? $invoice['invoicetype_linkcode'] ?? null;
            $invoice['invoicegroup'] = $invoice['Invoice Group'] ?? $invoice['invoicegroup'] ?? null;
            $invoice['firstinsideagentbkg_linkno'] = $invoice['First Inside Agent'] ?? $invoice['firstinsideagentbkg_linkno'] ?? null;
            $invoice['firstoutsideagentbkg_linkno'] = $invoice['First Outside Agent'] ?? $invoice['firstoutsideagentbkg_linkno'] ?? null;
            $invoice['calcinvoicenumber'] = $invoice['Calculated Invoice Number'] ?? $invoice['calcinvoicenumber'] ?? null;
            $invoice['altinvoicenumber'] = $invoice['Alternate Invoice Number'] ?? $invoice['altinvoicenumber'] ?? null;
            $invoice['arc_linkno'] = $invoice['ARC Link No'] ?? $invoice['arc_linkno'] ?? null;
            $invoice['receivedby'] = $invoice['Received By'] ?? $invoice['receivedby'] ?? null;
            $invoice['facturano'] = $invoice['Factura No'] ?? $invoice['facturano'] ?? null;
            $invoice['serviciono'] = $invoice['Servicio No'] ?? $invoice['serviciono'] ?? null;
            $invoice['itininvremarks'] = $invoice['Itinerary Remarks'] ?? $invoice['itininvremarks'] ?? null;
            $invoice['homehost_linkno'] = $invoice['Home Host Link No'] ?? $invoice['homehost_linkno'] ?? null;
            $invoice['marketid'] = $invoice['Market ID'] ?? $invoice['marketid'] ?? null;
            $invoice['agency_linkno'] = $invoice['Agency Link No'] ?? $invoice['agency_linkno'] ?? null;
            $invoice['accountingremarks'] = $invoice['Accounting Remarks'] ?? $invoice['accountingremarks'] ?? null;
            $invoice['remarks'] = $invoice['Remarks'] ?? $invoice['remarks'] ?? null;
        }

        $result = $this->tramsInvoiceDAL->batchUpdateInvoices($invoices);
        
        return [
            'success' => count($result['errors']) === 0,
            'updated' => $result['updated'],
            'total' => $result['total'],
            'errors' => $result['errors']
        ];
    }

    /**
     * Get invoice details
     * 
     * @param string $invoiceNumber Invoice number
     * @return array Invoice data
     */
    public function getInvoiceDetails($invoiceNumber)
    {
        if (empty($invoiceNumber)) {
            throw new Exception('Invoice number is required', 400);
        }

        $invoice = $this->tramsInvoiceDAL->getInvoiceDetails($invoiceNumber);
        
        if (!$invoice) {
            throw new Exception('Invoice not found', 404);
        }

        return [
            'success' => true,
            'invoice' => $invoice
        ];
    }

    /**
     * Parse date from various formats
     * 
     * @param string $excelDate Date string
     * @return string|null Parsed date in Y-m-d format or null
     */
    private function parseDate($excelDate)
    {
        if (empty($excelDate)) {
            return null;
        }

        $excelDate = trim($excelDate);

        // If already in YYYY-MM-DD format, return as is
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $excelDate)) {
            return $excelDate;
        }

        // Handle Excel numeric dates
        if (is_numeric($excelDate) && $excelDate > 25569) {
            $unixDate = ($excelDate - 25569) * 86400;
            return date('Y-m-d', $unixDate);
        }

        $excelDate = strtoupper($excelDate);

        // Handle airline date format (e.g., 26JUN23)
        if (preg_match('/^\d{1,2}[A-Z]{3}\d{2}$/', $excelDate)) {
            $date = \DateTime::createFromFormat('dMy', $excelDate);
            return $date ? $date->format('Y-m-d') : null;
        }

        // Try multiple common formats
        $formats = ['Y-m-d', 'Y/m/d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'j/n/Y', 'j-n-Y', 'dMY'];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $excelDate);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Extract numeric value from string
     * 
     * @param mixed $value Value to extract numeric from
     * @return float Numeric value
     */
    private function extractNumeric($value)
    {
        if (empty($value)) {
            return 0;
        }
        
        $cleaned = preg_replace('/[^0-9.-]/', '', str_replace(',', '', (string)$value));
        preg_match('/-?\d+\.?\d*/', $cleaned, $matches);
        return isset($matches[0]) ? floatval($matches[0]) : 0;
    }
}

