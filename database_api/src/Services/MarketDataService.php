<?php
/**
 * Market Data Service
 * Business logic for market data endpoints
 */

namespace App\Services;

use App\DAL\MarketDataDAL;

class MarketDataService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new MarketDataDAL();
    }

    /**
     * Get metadata (distinct values for filters)
     */
    public function getMetadata(): array
    {
        return [
            'months' => $this->dal->getMonths(),
            'origins' => $this->dal->getOrigins(),
            'destinations' => $this->dal->getDestinations(),
            'carriers' => $this->dal->getCarriers(),
            'stops' => $this->dal->getStops()
        ];
    }

    /**
     * Get market trend rows
     */
    public function getMarketTrendRows(array $params): array
    {
        $filters = [
            'from' => $params['from'] ?? null,
            'to' => $params['to'] ?? null,
            'carrier' => $params['carrier'] ?? null,
            'origin' => $params['origin'] ?? null,
            'dest' => $params['dest'] ?? null,
            'stop' => $params['stop'] ?? null,
            'nonstop' => $params['nonstop'] ?? null,
            'limit' => isset($params['limit']) ? (int)$params['limit'] : 0
        ];
        
        $rows = $this->dal->getMarketTrendRows($filters);
        
        // Transform rows to match expected format
        $transformed = [];
        foreach ($rows as $row) {
            $poo = (float)($row['poo_orig'] ?? 0) > (float)($row['poo_dest'] ?? 0) ? 'AU' : 'Non-AU';
            $nonstop = empty($row['stop1']) && empty($row['stop2']);
            
            $transformed[] = [
                'month' => $row['travel_month'],
                'origin' => $row['true_orig_code'],
                'destination' => $row['true_dest_code'],
                'od' => $row['route'],
                'carrier' => $row['dom_op_al_name'],
                'carrier_code' => $row['dom_op_al_code'],
                'passengers' => (int)($row['pax'] ?? $row['total_pax'] ?? 0),
                'avg_fare_usd' => isset($row['fare']) && $row['fare'] !== null ? (float)$row['fare'] : null,
                'revenue_usd' => isset($row['rev']) && $row['rev'] !== null ? (float)$row['rev'] : null,
                'connection_points' => implode('·', array_filter([$row['stop1'] ?? '', $row['stop2'] ?? ''])),
                'nonstop' => $nonstop,
                'poo' => $poo,
                'directional' => $poo === 'AU' ? 'outbound' : 'inbound'
            ];
        }
        
        return $transformed;
    }
}

