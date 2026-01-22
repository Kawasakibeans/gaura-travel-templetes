<?php
/**
 * Quote Submission Data Access Layer
 * Handles database operations for quote submissions
 */

namespace App\DAL;

use Exception;
use PDOException;

class QuoteSubmissionDAL extends BaseDAL
{
    /**
     * Insert main quote record
     */
    public function insertMainQuote($phoneNumber, $name)
    {
        try {
            $query = "
                INSERT INTO wpk4_booking_quotes_main (phone_number, name) 
                VALUES (:phone_number, :name)
            ";
            
            $this->execute($query, [
                'phone_number' => $phoneNumber,
                'name' => $name
            ]);
            
            return $this->lastInsertId();
        } catch (PDOException $e) {
            error_log("QuoteSubmissionDAL::insertMainQuote error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Insert quote option
     */
    public function insertQuoteOption($quoteId, $outboundJson, $returnJson, $packageJson)
    {
        try {
            $query = "
                INSERT INTO wpk4_booking_quotes_options (quote_id, outbound_trip, return_trip, package) 
                VALUES (:quote_id, :outbound_json, :return_json, :package_json)
            ";
            
            return $this->execute($query, [
                'quote_id' => $quoteId,
                'outbound_json' => $outboundJson,
                'return_json' => $returnJson,
                'package_json' => $packageJson
            ]);
        } catch (PDOException $e) {
            error_log("QuoteSubmissionDAL::insertQuoteOption error: " . $e->getMessage());
            throw new Exception("Database insert failed: " . $e->getMessage(), 500);
        }
    }
}

