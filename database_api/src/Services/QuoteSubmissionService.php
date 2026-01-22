<?php
/**
 * Quote Submission Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\QuoteSubmissionDAL;
use Exception;

class QuoteSubmissionService
{
    private $quoteDAL;
    
    public function __construct()
    {
        $this->quoteDAL = new QuoteSubmissionDAL();
    }
    
    /**
     * Submit quote
     */
    public function submitQuote($phone, $flights)
    {
        // Validate flights array
        if (empty($flights) || !is_array($flights)) {
            throw new Exception('flights array is required and must not be empty', 400);
        }
        
        // Validate each flight data
        foreach ($flights as $index => $flightData) {
            if (!isset($flightData['xml'])) {
                throw new Exception("Flight data at index $index is missing 'xml' field", 400);
            }
            if (!isset($flightData['outboundFlight'])) {
                throw new Exception("Flight data at index $index is missing 'outboundFlight' field", 400);
            }
            if (!isset($flightData['returnFlight'])) {
                throw new Exception("Flight data at index $index is missing 'returnFlight' field", 400);
            }
            if (!isset($flightData['package'])) {
                throw new Exception("Flight data at index $index is missing 'package' field", 400);
            }
        }
        
        // Insert main quote record
        $name = "test1"; // Default name as per original code
        $quoteId = $this->quoteDAL->insertMainQuote($phone, $name);
        
        if (!$quoteId) {
            throw new Exception('Failed to create quote record', 500);
        }
        
        // Prepare upload directory
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . "/wp-content/uploads/quotes/";
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                throw new Exception('Failed to create upload directory', 500);
            }
        }
        
        // Insert quote options and save files
        $uniqueId = 1;
        foreach ($flights as $flightData) {
            // Save XML content to file
            $xmlContent = json_encode($flightData['xml'], JSON_PRETTY_PRINT);
            $xmlFilePath = $uploadDir . $quoteId . '_' . $uniqueId . ".txt";
            
            if (file_put_contents($xmlFilePath, $xmlContent) === false) {
                error_log("Failed to save XML file: $xmlFilePath");
                // Continue processing even if file save fails
            }
            
            // Prepare JSON data
            $outboundJson = json_encode($flightData['outboundFlight']);
            $returnJson = json_encode($flightData['returnFlight']);
            $packageJson = json_encode($flightData['package']);
            
            // Insert quote option
            $this->quoteDAL->insertQuoteOption($quoteId, $outboundJson, $returnJson, $packageJson);
            
            $uniqueId++;
        }
        
        return [
            'success' => true,
            'message' => 'Quote has been successfully saved!',
            'data' => [
                'quote_id' => $quoteId
            ]
        ];
    }
}

