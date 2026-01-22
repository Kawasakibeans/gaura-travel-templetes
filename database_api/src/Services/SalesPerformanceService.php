<?php

namespace App\Services;

use App\DAL\SalesPerformanceDAL;
use Exception;

class SalesPerformanceService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new SalesPerformanceDAL();
    }

    /**
     * Get sales dashboard data
     */
    public function getSalesData(array $params): array
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $start = $params['start'] ?? $params['start_date'] ?? $yesterday;
        $end = $params['end'] ?? $params['end_date'] ?? $yesterday;

        if (strtotime($end) < strtotime($start)) {
            throw new Exception('End date must be >= start date');
        }

        // Calculate previous period
        $s = strtotime($start . ' 00:00:00');
        $e = strtotime($end . ' 23:59:59');
        $len = max(1, (int)floor(($e - $s) / 86400) + 1);
        $pe = strtotime('-1 day', $s);
        $ps = $pe - ($len - 1) * 86400;
        $prev_start = date('Y-m-d', $ps);
        $prev_end = date('Y-m-d', $pe);

        $filters = [
            'product' => isset($params['product']) && is_array($params['product']) 
                ? array_filter($params['product']) 
                : [],
            'route' => strtoupper($params['route'] ?? '')
        ];

        $airline_heat = strtoupper($params['airline'] ?? $params['airline_heat'] ?? '');
        $ap_prod = strtoupper($params['ap_product'] ?? 'ALL');
        if (!in_array($ap_prod, ['ALL', 'FIT', 'GDEALS'], true)) {
            $ap_prod = 'ALL';
        }

        // KPIs
        $currK = $this->dal->getKPIs($start, $end, $filters);
        $prevK = $this->dal->getKPIs($prev_start, $prev_end, $filters);

        // Revenue
        $ordersCurr = $this->dal->getRevenueData($start, $end, $filters);
        $ordersPrev = $this->dal->getRevenueData($prev_start, $prev_end, $filters);

        $fitOk = true;
        $fitMapCurr = [];
        $fitMapPrev = [];
        try {
            $fitOrdersCurr = $this->dal->getFitRevenueDetail($start, $end);
            $fitOrdersPrev = $this->dal->getFitRevenueDetail($prev_start, $prev_end);
            
            foreach ($fitOrdersCurr as $r) {
                $fitMapCurr[$r['order_id']] = (float)$r['fit_amount_clean'];
            }
            foreach ($fitOrdersPrev as $r) {
                $fitMapPrev[$r['order_id']] = (float)$r['fit_amount_clean'];
            }
        } catch (\Throwable $e) {
            $fitOk = false;
        }

        $revenue_curr = 0.0;
        foreach ($ordersCurr as $o) {
            $amt = ($o['product_kind'] === 'FIT' && isset($fitMapCurr[$o['order_id']])) 
                ? $fitMapCurr[$o['order_id']] 
                : $o['amount_clean'];
            if ($amt !== null) {
                $revenue_curr += (float)$amt;
            }
        }

        $revenue_prev = 0.0;
        foreach ($ordersPrev as $o) {
            $amt = ($o['product_kind'] === 'FIT' && isset($fitMapPrev[$o['order_id']])) 
                ? $fitMapPrev[$o['order_id']] 
                : $o['amount_clean'];
            if ($amt !== null) {
                $revenue_prev += (float)$amt;
            }
        }

        $currK['revenue'] = $revenue_curr;
        $prevK['revenue'] = $revenue_prev;

        // Split by product
        $rowsSplit = $this->dal->getSplitByProduct($start, $end, $filters);
        $split = [
            'FIT' => ['tickets' => 0, 'paid_tickets' => 0, 'revenue' => 0, 'min_paid_price' => null],
            'GDEALS' => ['tickets' => 0, 'paid_tickets' => 0, 'revenue' => 0, 'min_paid_price' => null],
        ];

        foreach ($rowsSplit as $r) {
            $split[$r['product']]['tickets'] = (int)$r['tickets'];
            $split[$r['product']]['paid_tickets'] = (int)$r['paid_tickets'];
            $split[$r['product']]['min_paid_price'] = $r['min_paid_price'] === null ? null : (float)$r['min_paid_price'];
        }

        $revSplit = ['FIT' => 0.0, 'GDEALS' => 0.0];
        foreach ($ordersCurr as $o) {
            $amt = ($o['product_kind'] === 'FIT' && isset($fitMapCurr[$o['order_id']])) 
                ? $fitMapCurr[$o['order_id']] 
                : $o['amount_clean'];
            if ($amt !== null) {
                $revSplit[$o['product_kind']] += (float)$amt;
            }
        }
        $split['FIT']['revenue'] = $revSplit['FIT'];
        $split['GDEALS']['revenue'] = $revSplit['GDEALS'];

        // Cancel breakdown
        $currBreak = ['canceled' => 0, 'refund' => 0, 'voucher_submited' => 0, 'waiting_voucher' => 0, 'other' => 0];
        $prevBreak = ['canceled' => 0, 'refund' => 0, 'voucher_submited' => 0, 'waiting_voucher' => 0, 'other' => 0];

        foreach ($this->dal->getCancelBreakdown($start, $end, $filters) as $r) {
            $k = $r['ps'];
            if (!isset($currBreak[$k])) {
                $k = 'other';
            }
            $currBreak[$k] += (int)$r['cnt'];
        }

        foreach ($this->dal->getCancelBreakdown($prev_start, $prev_end, $filters) as $r) {
            $k = $r['ps'];
            if (!isset($prevBreak[$k])) {
                $k = 'other';
            }
            $prevBreak[$k] += (int)$r['cnt'];
        }

        // Tickets per day
        $byDay = [];
        foreach ($this->dal->getTicketsPerDay($start, $end, $filters) as $r) {
            $byDay[] = [$r['d'], (int)$r['tickets']];
        }

        // Airline data
        $airPaidCurr = [];
        foreach ($this->dal->getAirlinePaidTickets($start, $end, $filters) as $r) {
            $airPaidCurr[$r['air']] = (int)$r['paid'];
        }

        $ordersCurrLabeled = $this->dal->getOrdersLabeled($start, $end, $filters);
        $ordersPrevLabeled = $this->dal->getOrdersLabeled($prev_start, $prev_end, $filters);

        $airRevCurr = [];
        foreach ($ordersCurrLabeled as $r) {
            $oid = $r['order_id'];
            $air = $r['air'] ?: '';
            if ($air === '') continue;
            $amt = ($r['product_kind'] === 'FIT' && isset($fitMapCurr[$oid])) 
                ? $fitMapCurr[$oid] 
                : $r['amount_clean'];
            if ($amt === null) continue;
            if (!isset($airRevCurr[$air])) {
                $airRevCurr[$air] = 0.0;
            }
            $airRevCurr[$air] += (float)$amt;
        }

        $airRevPrev = [];
        foreach ($ordersPrevLabeled as $r) {
            $oid = $r['order_id'];
            $air = $r['air'] ?: '';
            if ($air === '') continue;
            $amt = ($r['product_kind'] === 'FIT' && isset($fitMapPrev[$oid])) 
                ? $fitMapPrev[$oid] 
                : $r['amount_clean'];
            if ($amt === null) continue;
            if (!isset($airRevPrev[$air])) {
                $airRevPrev[$air] = 0.0;
            }
            $airRevPrev[$air] += (float)$amt;
        }

        $airCurr = [];
        foreach ($airRevCurr as $air => $rev) {
            $airCurr[] = [
                'air' => $air,
                'paid' => $airPaidCurr[$air] ?? 0,
                'revenue' => $rev,
                'profit' => $rev * 0.05
            ];
        }

        $airPrev = [];
        foreach ($airRevPrev as $air => $rev) {
            $airPrev[$air] = ['revenue' => $rev, 'profit' => $rev * 0.05];
        }

        $airList = [];
        foreach ($airCurr as $row) {
            $code = $row['air'];
            $paid = $row['paid'] ?? 0;
            $revC = $row['revenue'] ?? 0.0;
            $profC = $row['profit'] ?? 0.0;
            $profP = $airPrev[$code]['profit'] ?? 0.0;
            $pctP = $profP > 0 ? (($profC - $profP) / $profP * 100.0) : null;
            $airList[] = [
                'air' => $code,
                'paid' => $paid,
                'revenue' => $revC,
                'profit' => $profC,
                'pct_profit' => $pctP
            ];
        }

        $airTop = $airList;
        usort($airTop, fn($a, $b) => $b['profit'] <=> $a['profit']);
        $airTop = array_slice($airTop, 0, 5);

        $airBottom = array_values(array_filter($airList, fn($x) => ($x['paid'] ?? 0) > 0));
        usort($airBottom, fn($a, $b) => $a['profit'] <=> $b['profit']);
        $airBottom = array_slice($airBottom, 0, 5);

        // Routes growth/drop
        $routePaidCurr = [];
        foreach ($this->dal->getRoutesGrowth($start, $end, $filters) as $r) {
            $routePaidCurr[$r['route']] = (int)$r['paid'];
        }

        $routePaidPrev = [];
        foreach ($this->dal->getRoutesGrowth($prev_start, $prev_end, $filters) as $r) {
            $routePaidPrev[$r['route']] = (int)$r['paid'];
        }

        $routeRevCurr = [];
        foreach ($ordersCurrLabeled as $r) {
            $rt = $r['route'];
            if (!$rt) continue;
            if (preg_match('/AUS|IND|ORG|DES/', $rt) || $rt === 'FROM-TO') continue;
            $amt = ($r['product_kind'] === 'FIT' && isset($fitMapCurr[$r['order_id']])) 
                ? $fitMapCurr[$r['order_id']] 
                : $r['amount_clean'];
            if ($amt === null) continue;
            if (!isset($routeRevCurr[$rt])) {
                $routeRevCurr[$rt] = 0.0;
            }
            $routeRevCurr[$rt] += (float)$amt;
        }

        $rows = [];
        $allRoutes = array_unique(array_merge(
            array_keys($routePaidCurr),
            array_keys($routePaidPrev),
            array_keys($routeRevCurr)
        ));

        foreach ($allRoutes as $rt) {
            $cP = $routePaidCurr[$rt] ?? 0;
            $pP = $routePaidPrev[$rt] ?? 0;
            $d = $cP - $pP;
            $pct = ($pP == 0 ? null : ($d / $pP * 100.0));
            $rev = $routeRevCurr[$rt] ?? 0.0;
            $rows[] = [
                'route' => $rt,
                'prev' => $pP,
                'curr' => $cP,
                'diff' => $d,
                'pct' => $pct,
                'rev' => $rev,
                'profit' => $rev * 0.05
            ];
        }

        $growth = $rows;
        usort($growth, fn($a, $b) => ($b['diff'] <=> $a['diff']) ?: ($b['curr'] <=> $a['curr']));
        $growth = array_slice($growth, 0, 5);

        $drop = $rows;
        usort($drop, fn($a, $b) => ($a['diff'] <=> $b['diff']) ?: ($a['curr'] <=> $b['curr']));
        $drop = array_slice($drop, 0, 5);

        // Materialization
        $materialisationRaw = $this->dal->getMaterialization12m($airline_heat);
        $materialisation = is_array($materialisationRaw) && isset($materialisationRaw['months']) 
            ? $materialisationRaw 
            : ['months' => [], 'airlineTypes' => [], 'percent' => [], 'airlines' => [], 'routes' => []];

        // Advance Purchase
        $apRows = $this->dal->getAdvancePurchase($start, $end, $airline_heat, $ap_prod);
        $apBuckets = ['<30', '30–45', '45–60', '60–120', '120–180', '>180'];

        $apRoutes = [];
        $apCells = [];
        $y = 0;
        $routeTotals = [];

        foreach ($apRows as $r) {
            $vals = [
                (int)$r['b1'],
                (int)$r['b2'],
                (int)$r['b3'],
                (int)$r['b4'],
                (int)$r['b5'],
                (int)$r['b6']
            ];
            $tot = array_sum($vals);
            $apRoutes[] = $r['route'];
            $routeTotals[] = $tot;
            foreach ($vals as $x => $v) {
                $pct = ($tot > 0) ? round($v / $tot * 100, 1) : 0.0;
                $apCells[] = ['x' => $x, 'y' => $y, 'v' => $v, 'pct' => $pct, 't' => $tot];
            }
            $y++;
        }

        // Sort routes by total descending
        $n = count($apRoutes);
        if ($n > 0) {
            $order = range(0, $n - 1);
            array_multisort($routeTotals, SORT_DESC, $order, $apRoutes);
            $yMap = [];
            foreach ($order as $newY => $oldY) {
                $yMap[$oldY] = $newY;
            }
            foreach ($apCells as &$c) {
                if (isset($yMap[$c['y']])) {
                    $c['y'] = $yMap[$c['y']];
                }
            }
            unset($c);
        }

        // Cancel rates
        $currTickets = (int)$currK['tickets'];
        $prevTickets = (int)$prevK['tickets'];
        $currCancel = array_sum($currBreak);
        $prevCancel = array_sum($prevBreak);
        $cancelRateCurr = $currTickets > 0 ? ($currCancel / $currTickets * 100.0) : 0.0;
        $cancelRatePrev = $prevTickets > 0 ? ($prevCancel / $prevTickets * 100.0) : 0.0;
        $cancelDeltaPP = $cancelRateCurr - $cancelRatePrev;
        $cancelDeltaPct = $cancelRatePrev > 0 ? (($cancelRateCurr - $cancelRatePrev) / $cancelRatePrev * 100.0) : null;

        // Price buckets
        $priceBuckets = $this->dal->getPriceBuckets($start, $end, $filters);

        return [
            'meta' => [
                'tz' => 'Australia/Melbourne',
                'start' => $start,
                'end' => $end,
                'prev_start' => $prev_start,
                'prev_end' => $prev_end,
                'filters' => [
                    'route' => $filters['route'],
                    'airline_heat' => $airline_heat,
                    'product' => $filters['product'],
                    'ap_product' => $ap_prod
                ],
                'fit_revenue_source' => $fitOk ? 'pax_logic' : 'fallback_clean_amount'
            ],
            'kpis' => [
                'tickets' => [
                    'curr' => (int)$currK['tickets'],
                    'prev' => (int)$prevK['tickets'],
                    'fit' => (int)($split['FIT']['tickets'] ?? 0),
                    'gdeals' => (int)($split['GDEALS']['tickets'] ?? 0)
                ],
                'paidTickets' => [
                    'curr' => (int)$currK['paid_tickets'],
                    'prev' => (int)$prevK['paid_tickets'],
                    'fit' => (int)($split['FIT']['paid_tickets'] ?? 0),
                    'gdeals' => (int)($split['GDEALS']['paid_tickets'] ?? 0)
                ],
                'pctPaid' => [
                    'curr' => ($currK['tickets'] > 0 ? $currK['paid_tickets'] / $currK['tickets'] * 100.0 : 0.0),
                    'prev' => ($prevK['tickets'] > 0 ? $prevK['paid_tickets'] / $prevK['tickets'] * 100.0 : 0.0),
                    'fit' => (($split['FIT']['tickets'] ?? 0) > 0 ? $split['FIT']['paid_tickets'] / $split['FIT']['tickets'] * 100.0 : 0.0),
                    'gdeals' => (($split['GDEALS']['tickets'] ?? 0) > 0 ? $split['GDEALS']['paid_tickets'] / $split['GDEALS']['tickets'] * 100.0 : 0.0)
                ],
                'revenue' => [
                    'curr' => $revenue_curr,
                    'prev' => $revenue_prev,
                    'fit' => (float)($split['FIT']['revenue'] ?? 0),
                    'gdeals' => (float)($split['GDEALS']['revenue'] ?? 0)
                ],
                'profit' => [
                    'curr' => $revenue_curr * 0.05,
                    'prev' => $revenue_prev * 0.05,
                    'fit' => (float)($split['FIT']['revenue'] ?? 0) * 0.05,
                    'gdeals' => (float)($split['GDEALS']['revenue'] ?? 0) * 0.05
                ],
                'minPricePaid' => [
                    'curr' => $currK['min_paid_price'] === null ? null : (float)$currK['min_paid_price'],
                    'prev' => $prevK['min_paid_price'] === null ? null : (float)$prevK['min_paid_price'],
                    'fit' => $split['FIT']['min_paid_price'] === null ? null : (float)$split['FIT']['min_paid_price'],
                    'gdeals' => $split['GDEALS']['min_paid_price'] === null ? null : (float)$split['GDEALS']['min_paid_price']
                ],
                'minProfitPerTicket' => [
                    'curr' => $currK['min_paid_price'] === null ? null : (float)$currK['min_paid_price'] * 0.05,
                    'prev' => $prevK['min_paid_price'] === null ? null : (float)$prevK['min_paid_price'] * 0.05,
                    'fit' => $split['FIT']['min_paid_price'] === null ? null : (float)$split['FIT']['min_paid_price'] * 0.05,
                    'gdeals' => $split['GDEALS']['min_paid_price'] === null ? null : (float)$split['GDEALS']['min_paid_price'] * 0.05
                ],
                'cancelled' => [
                    'rate' => [
                        'curr' => $cancelRateCurr,
                        'prev' => $cancelRatePrev,
                        'delta_pp' => $cancelDeltaPP,
                        'delta_pct' => $cancelDeltaPct
                    ],
                    'breakdown' => $currBreak
                ]
            ],
            'series' => ['byDay' => $byDay],
            'byAir' => [
                'curr' => $airCurr,
                'prev' => $airPrev
            ],
            'byRoute' => [],
            'materialisation12m' => $materialisation,
            'advancePurchase' => [
                'buckets' => $apBuckets,
                'routes' => $apRoutes,
                'cells' => $apCells,
                'mode' => 'percent',
                'product' => $ap_prod
            ],
            'tops' => [
                'routes_growth' => $growth,
                'routes_drop' => $drop,
                'airlines_top_profit' => $airTop,
                'airlines_bottom_profit' => $airBottom
            ],
            'priceBuckets' => $priceBuckets
        ];
    }

    /**
     * Get revenue data
     */
    public function getRevenueData(array $params): array
    {
        $from = $params['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to = $params['to'] ?? date('Y-m-d');

        $filters = [
            'airlines' => isset($params['airlines']) ? array_filter(array_map('trim', explode(',', $params['airlines']))) : [],
            'routes' => isset($params['routes']) ? array_filter(array_map('trim', explode(',', $params['routes']))) : [],
            'payment_status' => isset($params['payment_status']) ? array_filter(array_map('trim', explode(',', $params['payment_status']))) : [],
            'route_types' => isset($params['route_types']) ? array_filter(array_map('trim', explode(',', $params['route_types']))) : []
        ];

        $rows = $this->dal->getRevenueDataWithFilters($from, $to, $filters);

        return [
            'ok' => true,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'airlines' => $filters['airlines'],
                'routes' => $filters['routes'],
                'payment_status' => !empty($filters['payment_status']) ? $filters['payment_status'] : ['<> pending']
            ],
            'count' => count($rows),
            'rows' => $rows
        ];
    }

    /**
     * Get advance purchase booking data
     */
    public function getAdvancePurchaseBookingData(array $params): array
    {
        $from = $params['from'] ?? '2000-01-01';
        $to = $params['to'] ?? '2100-01-01';
        $limit = isset($params['limit']) ? min((int)$params['limit'], 500000) : 200000;

        $filters = [
            'type' => $params['type'] ?? 'All',
            'route_type' => $params['route_type'] ?? 'All',
            'airlines' => $params['airlines'] ?? 'All',
            'route' => $params['route'] ?? 'All',
            'limit' => $limit
        ];

        $rows = $this->dal->getAdvancePurchaseBookingData($from, $to, $filters);

        // Normalize
        foreach ($rows as &$r) {
            $r['order_id'] = (string)$r['order_id'];
            $r['booking_date'] = substr((string)$r['booking_date'], 0, 10);
            $r['travel_date'] = substr((string)$r['travel_date'], 0, 10);
            $r['pax'] = (int)($r['pax'] ?? 0);
            $r['order_type'] = (string)$r['order_type'];
            $r['route_type'] = (string)$r['route_type'];
            $r['route'] = (string)$r['route'];
            $r['airlines'] = (string)$r['airlines'];
        }

        return [
            'data' => $rows,
            'meta' => [
                'count' => count($rows),
                'type' => $filters['type'],
                'route_type' => $filters['route_type'],
                'airlines' => $filters['airlines'],
                'route' => $filters['route']
            ]
        ];
    }

    /**
     * Get materialization months
     */
    public function getMaterializationMonths(): array
    {
        return $this->dal->getMaterializationMonths();
    }

    /**
     * Get materialization data
     */
    public function getMaterializationData(?string $travelMonth = null): array
    {
        if (!$travelMonth) {
            return $this->getMaterializationMonths();
        }

        return $this->dal->getMaterializationDataForMonth($travelMonth);
    }

    /**
     * Get pricing velocity data
     */
    public function getPricingVelocityData(): array
    {
        return $this->dal->getPricingVelocityData();
    }

    /**
     * Save AI summary
     */
    public function saveAISummary(array $data): ?int
    {
        return $this->dal->saveAISummary($data);
    }
}

