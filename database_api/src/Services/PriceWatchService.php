<?php
/**
 * Price Watch Service - Business Logic Layer
 * Handles price watch subscriptions for flight monitoring
 */

namespace App\Services;

use App\DAL\PriceWatchDAL;
use Exception;

class PriceWatchService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new PriceWatchDAL();
    }

    /**
     * Create price watch subscription
     */
    public function createSubscription($data)
    {
        // Validate required fields
        $requiredFields = ['from_city', 'to_city', 'travel_date', 'email_id'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        // Validate email
        if (!filter_var($data['email_id'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address', 400);
        }

        // Format dates
        $data['travel_date'] = $this->formatDate($data['travel_date']);
        
        if (!empty($data['return_date'])) {
            $data['return_date'] = $this->formatDate($data['return_date']);
        }

        $subscriptionId = $this->dal->createSubscription($data);

        return [
            'subscription_id' => $subscriptionId,
            'from_city' => $data['from_city'],
            'to_city' => $data['to_city'],
            'travel_date' => $data['travel_date'],
            'email_id' => $data['email_id'],
            'message' => 'Subscribed successfully'
        ];
    }

    /**
     * Get all subscriptions with filters
     */
    public function getAllSubscriptions($filters = [])
    {
        $email = $filters['email'] ?? null;
        $fromCity = $filters['from_city'] ?? null;
        $toCity = $filters['to_city'] ?? null;
        $limit = (int)($filters['limit'] ?? 100);
        $offset = (int)($filters['offset'] ?? 0);

        $subscriptions = $this->dal->getAllSubscriptions($email, $fromCity, $toCity, $limit, $offset);
        $totalCount = $this->dal->getSubscriptionsCount($email, $fromCity, $toCity);

        return [
            'subscriptions' => $subscriptions,
            'total_count' => $totalCount,
            'filters' => $filters
        ];
    }

    /**
     * Get subscription by ID
     */
    public function getSubscriptionById($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid subscription ID is required', 400);
        }

        $subscription = $this->dal->getSubscriptionById($id);

        if (!$subscription) {
            throw new Exception('Subscription not found', 404);
        }

        return $subscription;
    }

    /**
     * Delete subscription
     */
    public function deleteSubscription($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid subscription ID is required', 400);
        }

        $subscription = $this->dal->getSubscriptionById($id);
        if (!$subscription) {
            throw new Exception('Subscription not found', 404);
        }

        $this->dal->deleteSubscription($id);

        return [
            'subscription_id' => $id,
            'message' => 'Subscription deleted successfully'
        ];
    }

    /**
     * Private helper methods
     */
    
    private function formatDate($date)
    {
        // Try to parse date in d-m-Y format
        $dateObj = \DateTime::createFromFormat('d-m-Y', $date);
        
        if ($dateObj) {
            return $dateObj->format('Y-m-d');
        }

        // If already in Y-m-d format, return as is
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        throw new Exception('Invalid date format. Use YYYY-MM-DD or DD-MM-YYYY', 400);
    }
}

