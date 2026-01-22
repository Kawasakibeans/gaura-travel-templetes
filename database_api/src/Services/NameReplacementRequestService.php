<?php

namespace App\Services;

use App\DAL\NameReplacementRequestDAL;

class NameReplacementRequestService
{
    private $dal;

    public function __construct(NameReplacementRequestDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Get datechange name replacement candidates
     * Line: 12-48 (in template)
     */
    public function getDatechangeCandidates($limit = 50)
    {
        $candidates = $this->dal->getDatechangeNameReplacementCandidates($limit);
        
        return [
            'type' => 'datechange',
            'candidates' => $candidates,
            'count' => count($candidates)
        ];
    }

    /**
     * Get refund name replacement candidates
     * Line: 51-92 (in template)
     */
    public function getRefundCandidates($limit = 50)
    {
        $baseBookings = $this->dal->getRefundBaseBookings($limit);
        
        $allCandidates = [];
        foreach ($baseBookings as $baseBooking) {
            $orderId = $baseBooking['order_id'];
            $tripCode = $baseBooking['trip_code'];
            $travelDate = $baseBooking['travel_date'];
            
            $candidates = $this->dal->getRefundNameReplacementCandidates($tripCode, $travelDate, $orderId);
            $allCandidates = array_merge($allCandidates, $candidates);
        }
        
        return [
            'type' => 'refund',
            'candidates' => $allCandidates,
            'count' => count($allCandidates)
        ];
    }

    /**
     * Process datechange name replacement (get and update)
     * Line: 12-48 (in template)
     */
    public function processDatechangeNameReplacement($limit = 50, $autoUpdate = false)
    {
        $candidates = $this->dal->getDatechangeNameReplacementCandidates($limit);
        
        $updated = [];
        $currentTime = date('Y-m-d H:i:s');
        
        foreach ($candidates as $candidate) {
            $orderId = $candidate['order_id'];
            $productId = $candidate['product_id'];
            
            if ($autoUpdate) {
                $result = $this->dal->updatePaxStatusToNameReplacement($orderId, $productId, $currentTime);
                if ($result) {
                    $updated[] = [
                        'order_id' => $orderId,
                        'product_id' => $productId,
                        'updated' => true
                    ];
                }
            } else {
                $updated[] = [
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'updated' => false
                ];
            }
        }
        
        return [
            'type' => 'datechange',
            'candidates' => $candidates,
            'updated' => $updated,
            'total_candidates' => count($candidates),
            'total_updated' => $autoUpdate ? count($updated) : 0
        ];
    }

    /**
     * Process refund name replacement (get and update)
     * Line: 51-92 (in template)
     */
    public function processRefundNameReplacement($limit = 50, $autoUpdate = false)
    {
        $baseBookings = $this->dal->getRefundBaseBookings($limit);
        
        $allCandidates = [];
        $updated = [];
        $currentTime = date('Y-m-d H:i:s');
        
        foreach ($baseBookings as $baseBooking) {
            $orderId = $baseBooking['order_id'];
            $tripCode = $baseBooking['trip_code'];
            $travelDate = $baseBooking['travel_date'];
            
            $candidates = $this->dal->getRefundNameReplacementCandidates($tripCode, $travelDate, $orderId);
            
            foreach ($candidates as $candidate) {
                $allCandidates[] = $candidate;
                
                $candidateOrderId = $candidate['order_id'];
                $candidateProductId = $candidate['product_id'];
                
                if ($autoUpdate) {
                    $result = $this->dal->updatePaxStatusToNameReplacement($candidateOrderId, $candidateProductId, $currentTime);
                    if ($result) {
                        $updated[] = [
                            'order_id' => $candidateOrderId,
                            'product_id' => $candidateProductId,
                            'updated' => true
                        ];
                    }
                } else {
                    $updated[] = [
                        'order_id' => $candidateOrderId,
                        'product_id' => $candidateProductId,
                        'updated' => false
                    ];
                }
            }
        }
        
        return [
            'type' => 'refund',
            'candidates' => $allCandidates,
            'updated' => $updated,
            'total_candidates' => count($allCandidates),
            'total_updated' => $autoUpdate ? count($updated) : 0
        ];
    }

    /**
     * Update pax status to name replacement request
     */
    public function updatePaxStatusToNameReplacement($orderId, $productId, $modifiedBy = 'api_user')
    {
        if (empty($orderId)) {
            throw new \Exception('order_id is required', 400);
        }
        if (empty($productId)) {
            throw new \Exception('product_id is required', 400);
        }
        
        $currentTime = date('Y-m-d H:i:s');
        $result = $this->dal->updatePaxStatusToNameReplacement($orderId, $productId, $currentTime, $modifiedBy);
        
        if (!$result) {
            throw new \Exception('Failed to update pax status', 500);
        }
        
        return [
            'order_id' => $orderId,
            'product_id' => $productId,
            'pax_status' => 'Name replacement request',
            'updated' => true
        ];
    }
}

