<?php
/**
 * Price Watch Data Access Layer
 * Handles database operations for price watch subscriptions
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class PriceWatchDAL extends BaseDAL
{
    /**
     * Create price watch subscription
     */
    public function createSubscription($data)
    {
        if (!empty($data['return_date'])) {
            $query = "INSERT INTO wpk4_backend_price_watch 
                      (from_city, to_city, travel_date, return_date, email_id, added_on)
                      VALUES (?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $data['from_city'],
                $data['to_city'],
                $data['travel_date'],
                $data['return_date'],
                $data['email_id']
            ];
        } else {
            $query = "INSERT INTO wpk4_backend_price_watch 
                      (from_city, to_city, travel_date, email_id, added_on)
                      VALUES (?, ?, ?, ?, NOW())";
            
            $params = [
                $data['from_city'],
                $data['to_city'],
                $data['travel_date'],
                $data['email_id']
            ];
        }

        $this->execute($query, $params);
        return $this->lastInsertId();
    }

    /**
     * Get all subscriptions with filters
     */
    public function getAllSubscriptions($email, $fromCity, $toCity, $limit, $offset)
    {
        $whereParts = [];
        $params = [];

        if ($email) {
            $whereParts[] = "email_id = ?";
            $params[] = $email;
        }

        if ($fromCity) {
            $whereParts[] = "from_city = ?";
            $params[] = $fromCity;
        }

        if ($toCity) {
            $whereParts[] = "to_city = ?";
            $params[] = $toCity;
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        $query = "SELECT * FROM wpk4_backend_price_watch 
                  $whereSQL 
                  ORDER BY added_on DESC 
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;

        return $this->query($query, $params);
    }

    /**
     * Get subscriptions count
     */
    public function getSubscriptionsCount($email, $fromCity, $toCity)
    {
        $whereParts = [];
        $params = [];

        if ($email) {
            $whereParts[] = "email_id = ?";
            $params[] = $email;
        }

        if ($fromCity) {
            $whereParts[] = "from_city = ?";
            $params[] = $fromCity;
        }

        if ($toCity) {
            $whereParts[] = "to_city = ?";
            $params[] = $toCity;
        }

        $whereSQL = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';
        
        $query = "SELECT COUNT(*) as total FROM wpk4_backend_price_watch $whereSQL";
        
        $result = $this->queryOne($query, $params);
        return (int)$result['total'];
    }

    /**
     * Get subscription by ID
     */
    public function getSubscriptionById($id)
    {
        $query = "SELECT * FROM wpk4_backend_price_watch WHERE auto_id = ? LIMIT 1";
        return $this->queryOne($query, [$id]);
    }

    /**
     * Delete subscription
     */
    public function deleteSubscription($id)
    {
        $query = "DELETE FROM wpk4_backend_price_watch WHERE auto_id = ?";
        return $this->execute($query, [$id]);
    }
}

