<?php

namespace App\Services;

use App\DAL\FlightQuoteDAL;

class FlightQuoteService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new FlightQuoteDAL();
    }

    /**
     * Create a regular quote
     */
    public function createQuote(array $params): array
    {
        // Validate required fields
        $required = ['current_price', 'depart_apt', 'dest_apt', 'depart_date', 'total_pax', 'name', 'email', 'phone_num'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw new \Exception("$field is required");
            }
        }
        
        // Convert date format if needed
        $departDate = $params['depart_date'];
        if (strpos($departDate, '-') && strlen($departDate) === 10) {
            // Already in Y-m-d format
        } else {
            // Try to convert from d-m-Y format
            try {
                $date = \DateTime::createFromFormat('d-m-Y', $departDate);
                if ($date) {
                    $departDate = $date->format('Y-m-d');
                }
            } catch (\Exception $e) {
                throw new \Exception("Invalid depart_date format");
            }
        }
        
        $returnDate = null;
        if (!empty($params['return_date'])) {
            $returnDate = $params['return_date'];
            if (strpos($returnDate, '-') && strlen($returnDate) === 10) {
                // Already in Y-m-d format
            } else {
                try {
                    $date = \DateTime::createFromFormat('d-m-Y', $returnDate);
                    if ($date) {
                        $returnDate = $date->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    // Keep original if conversion fails
                }
            }
        }
        
        $quoteData = [
            'user_id' => $params['user_id'] ?? null,
            'current_price' => (float)$params['current_price'],
            'depart_apt' => $params['depart_apt'],
            'dest_apt' => $params['dest_apt'],
            'depart_date' => $departDate,
            'return_date' => $returnDate,
            'adult_count' => (int)($params['adtCount'] ?? $params['adult_count'] ?? 0),
            'child_count' => (int)($params['chdCount'] ?? $params['child_count'] ?? 0),
            'infant_count' => (int)($params['infCount'] ?? $params['infant_count'] ?? 0),
            'total_pax' => (int)$params['total_pax'],
            'from_country' => $params['from_country'] ?? '',
            'to_country' => $params['to_country'] ?? '',
            'to_product_id' => $params['to_product_id'] ?? null,
            'return_product_id' => $params['return_product_id'] ?? null,
            'url' => $params['url'] ?? '',
            'is_gdeals' => !empty($params['to_product_id']) ? 1 : 0,
            'name' => $params['name'],
            'email' => $params['email'],
            'phone_num' => $params['phone_num'],
            'tsr' => $params['tsr'] ?? '',
            'call_record_id' => $params['call_record_id'] ?? '',
            'adult_price' => (float)$params['current_price'],
            'child_price' => (float)($params['chd_price'] ?? 0),
            'infant_price' => (float)($params['inf_price'] ?? 0),
            'total_price' => (float)($params['total_price'] ?? $params['current_price']),
            'depart_time' => $params['deptime'] ?? $params['depart_time'] ?? '',
            'return_time' => $params['rettime'] ?? $params['return_time'] ?? '',
            'airline_code' => $params['airline_code'] ?? '',
            'is_multicity' => 0
        ];
        
        $quoteId = $this->dal->createQuote($quoteData);
        
        return [
            'success' => true,
            'message' => 'Quote saved successfully',
            'quote_id' => $quoteId,
            'price' => $quoteData['current_price'],
            'url' => $quoteData['url']
        ];
    }
    
    /**
     * Create a multicity quote
     */
    public function createMulticityQuote(array $params): array
    {
        // Validate required fields
        $required = ['current_price', 'total_pax', 'name', 'email', 'phone_num'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw new \Exception("$field is required");
            }
        }
        
        // Collect segments (up to 4)
        $segments = [];
        for ($i = 1; $i <= 4; $i++) {
            if (!empty($params["depart_apt$i"]) && !empty($params["dest_apt$i"])) {
                $segments[$i] = [
                    'depart_apt' => $params["depart_apt$i"],
                    'dest_apt' => $params["dest_apt$i"],
                    'depart_date' => !empty($params["depart_date$i"]) ? $params["depart_date$i"] : null,
                    'product_id' => !empty($params["product_id$i"]) ? (int)$params["product_id$i"] : null
                ];
            }
        }
        
        if (empty($segments)) {
            throw new \Exception("At least one segment is required");
        }
        
        // First create entry in wpk4_quote table
        $quoteData = [
            'user_id' => $params['user_id'] ?? null,
            'current_price' => 0,
            'depart_apt' => '',
            'dest_apt' => '',
            'depart_date' => null,
            'return_date' => null,
            'adult_count' => (int)($params['adtCount'] ?? $params['adult_count'] ?? 0),
            'child_count' => (int)($params['chdCount'] ?? $params['child_count'] ?? 0),
            'infant_count' => (int)($params['infCount'] ?? $params['infant_count'] ?? 0),
            'total_pax' => (int)$params['total_pax'],
            'from_country' => '',
            'to_country' => '',
            'to_product_id' => null,
            'return_product_id' => null,
            'url' => $params['url'] ?? '',
            'is_gdeals' => !empty($segments[1]['product_id']) ? 1 : 0,
            'name' => $params['name'],
            'email' => $params['email'],
            'phone_num' => $params['phone_num'],
            'tsr' => $params['tsr'] ?? '',
            'call_record_id' => $params['call_record_id'] ?? '',
            'adult_price' => 0,
            'child_price' => 0,
            'infant_price' => 0,
            'total_price' => 0,
            'depart_time' => '',
            'return_time' => '',
            'airline_code' => $params['airline_code'] ?? '',
            'is_multicity' => 1
        ];
        
        $quoteId = $this->dal->createQuote($quoteData);
        
        // Now create multicity entry
        $multicityData = [
            'id' => $quoteId,
            'user_id' => $params['user_id'] ?? null,
            'current_price' => (float)$params['current_price'],
            'lowest_price' => (float)$params['current_price'],
            'adult_count' => (int)($params['adtCount'] ?? $params['adult_count'] ?? 0),
            'child_count' => (int)($params['chdCount'] ?? $params['child_count'] ?? 0),
            'infant_count' => (int)($params['infCount'] ?? $params['infant_count'] ?? 0),
            'total_pax' => (int)$params['total_pax'],
            'from_country' => '',
            'to_country' => '',
            'url' => $params['url'] ?? '',
            'is_gdeals' => !empty($segments[1]['product_id']) ? 1 : 0,
            'name' => $params['name'],
            'email' => $params['email'],
            'phone_num' => $params['phone_num'],
            'tsr' => $params['tsr'] ?? '',
            'call_record_id' => $params['call_record_id'] ?? '',
            'adult_price' => (float)$params['current_price'],
            'child_price' => (float)($params['chd_price'] ?? 0),
            'infant_price' => (float)($params['inf_price'] ?? 0),
            'total_price' => (float)($params['total_price'] ?? $params['current_price']),
            'airline_code' => $params['airline_code'] ?? '',
            'depart_time' => '',
            'return_time' => '',
            'return_date' => null
        ];
        
        // Add segment data
        for ($i = 1; $i <= 4; $i++) {
            if (isset($segments[$i])) {
                $multicityData["depart_apt$i"] = $segments[$i]['depart_apt'];
                $multicityData["dest_apt$i"] = $segments[$i]['dest_apt'];
                $multicityData["depart_date$i"] = $segments[$i]['depart_date'];
                $multicityData["product_id$i"] = $segments[$i]['product_id'];
            } else {
                $multicityData["depart_apt$i"] = null;
                $multicityData["dest_apt$i"] = null;
                $multicityData["depart_date$i"] = null;
                $multicityData["product_id$i"] = null;
            }
        }
        
        $multicityId = $this->dal->createMulticityQuote($multicityData);
        
        return [
            'success' => true,
            'message' => 'Multicity quote saved successfully',
            'quote_id' => $multicityId,
            'segments' => $segments,
            'total_price' => $multicityData['total_price'],
            'call_record_id' => $multicityData['call_record_id']
        ];
    }
    
    /**
     * Get passenger info by phone
     */
    public function getPassengerInfo(array $params): array
    {
        $phone = $params['phone'] ?? null;
        
        if (empty($phone)) {
            throw new \Exception('phone is required');
        }
        
        $passenger = $this->dal->getPassengerByPhone($phone);
        
        // Get last 8 digits for Nobel lookup
        $last8 = substr(preg_replace('/\D/', '', $phone), -8);
        $nobelData = $this->dal->getNobelDataByPhone($last8);
        
        return [
            'success' => true,
            'data' => [
                'passenger' => $passenger,
                'nobel_data' => $nobelData
            ]
        ];
    }
}

