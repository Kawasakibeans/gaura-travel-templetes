<?php
/**
 * Market Data Data Access Layer
 * Handles database operations for Cirium market trend data
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class MarketDataDAL extends BaseDAL
{
    private $table = 'wpk4_backend_cirium_market_trend';

    /**
     * Get distinct months (formatted as YYYY-MM)
     */
    public function getMonths(): array
    {
        $sql = "
            SELECT DATE_FORMAT(travel_month, '%Y-%m') AS month 
            FROM {$this->table} 
            GROUP BY month 
            ORDER BY month
        ";
        
        $results = $this->query($sql);
        return array_column($results, 'month');
    }

    /**
     * Get distinct origins
     */
    public function getOrigins(): array
    {
        $sql = "
            SELECT DISTINCT true_orig_code 
            FROM {$this->table} 
            ORDER BY true_orig_code
        ";
        
        $results = $this->query($sql);
        return array_column($results, 'true_orig_code');
    }

    /**
     * Get distinct destinations
     */
    public function getDestinations(): array
    {
        $sql = "
            SELECT DISTINCT true_dest_code 
            FROM {$this->table} 
            ORDER BY true_dest_code
        ";
        
        $results = $this->query($sql);
        return array_column($results, 'true_dest_code');
    }

    /**
     * Get distinct carriers
     */
    public function getCarriers(): array
    {
        $sql = "
            SELECT DISTINCT dom_op_al_name 
            FROM {$this->table} 
            ORDER BY dom_op_al_name
        ";
        
        $results = $this->query($sql);
        return array_column($results, 'dom_op_al_name');
    }

    /**
     * Get distinct stops
     */
    public function getStops(): array
    {
        $sql = "
            SELECT DISTINCT s.stop 
            FROM (
                SELECT stop1 AS stop FROM {$this->table}
                UNION 
                SELECT stop2 AS stop FROM {$this->table}
            ) s 
            WHERE COALESCE(NULLIF(s.stop,''),NULL) IS NOT NULL 
            ORDER BY s.stop
        ";
        
        $results = $this->query($sql);
        return array_column($results, 'stop');
    }

    /**
     * Get market trend rows with filters
     */
    public function getMarketTrendRows(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        
        // Date range filters
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        
        if ($from && $to) {
            $where[] = 'travel_month BETWEEN :from_date AND :to_date';
            $params[':from_date'] = $this->lowerBoundDate($from);
            $params[':to_date'] = $this->upperBoundDate($to);
        } elseif ($from) {
            $where[] = 'travel_month >= :from_date';
            $params[':from_date'] = $this->lowerBoundDate($from);
        } elseif ($to) {
            $where[] = 'travel_month <= :to_date';
            $params[':to_date'] = $this->upperBoundDate($to);
        }
        
        // Carrier filter
        if (!empty($filters['carrier'])) {
            $where[] = 'dom_op_al_name = :carrier';
            $params[':carrier'] = $filters['carrier'];
        }
        
        // Origin filter
        if (!empty($filters['origin'])) {
            $where[] = 'true_orig_code = :origin';
            $params[':origin'] = $filters['origin'];
        }
        
        // Destination filter
        if (!empty($filters['dest'])) {
            $where[] = 'true_dest_code = :dest';
            $params[':dest'] = $filters['dest'];
        }
        
        // Stop filter
        if (!empty($filters['stop'])) {
            $where[] = '(stop1 = :stop OR stop2 = :stop)';
            $params[':stop'] = $filters['stop'];
        }
        
        // Nonstop filter
        if (!empty($filters['nonstop']) && $filters['nonstop'] === '1') {
            $where[] = "(COALESCE(NULLIF(stop1,''),NULL) IS NULL AND COALESCE(NULLIF(stop2,''),NULL) IS NULL)";
        }
        
        $limit = isset($filters['limit']) && $filters['limit'] > 0 ? (int)$filters['limit'] : 0;
        
        $sql = "
            SELECT travel_month, dom_op_al_name, dom_op_al_code, true_orig_code, true_dest_code,
                   route, stop1, stop2, total_pax, pax, fare, rev, poo_orig, poo_dest
            FROM {$this->table}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY travel_month, route
        ";
        
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        return $this->query($sql, $params);
    }

    /**
     * Helper: Convert YYYY-MM or YYYY-MM-DD to lower bound date
     */
    private function lowerBoundDate(?string $value): ?string
    {
        if (!$value) return null;
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $value . '-01';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        return null;
    }

    /**
     * Helper: Convert YYYY-MM or YYYY-MM-DD to upper bound date
     */
    private function upperBoundDate(?string $value): ?string
    {
        if (!$value) return null;
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $dt = \DateTime::createFromFormat('Y-m', $value);
            if (!$dt) return null;
            $dt->modify('last day of this month');
            return $dt->format('Y-m-d');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        return null;
    }
}

