<?php

namespace App\DAL;

class SalesPerformanceDAL extends BaseDAL
{
    /**
     * Clean trip code expression
     */
    private function getCleanTripCodeExpr(string $alias = ''): string
    {
        $prefix = $alias ? $alias . '.' : '';
        return "REGEXP_REPLACE(REGEXP_REPLACE(TRIM({$prefix}trip_code), '[^A-Za-z0-9-]', ''), '-{2,}', '-')";
    }

    /**
     * Clean trip ID expression
     */
    private function getCleanTripIdExpr(string $alias = ''): string
    {
        $prefix = $alias ? $alias . '.' : '';
        return "REGEXP_REPLACE(REGEXP_REPLACE(TRIM({$prefix}trip_id), '[^A-Za-z0-9-]', ''), '-{2,}', '-')";
    }

    /**
     * Get route expression
     */
    private function getRouteExpr(string $alias = ''): string
    {
        $cleanTC = $this->getCleanTripCodeExpr($alias);
        return "SUBSTRING($cleanTC, 1, 7)";
    }

    /**
     * Get airline expression
     */
    private function getAirlineExpr(string $alias = ''): string
    {
        $cleanTC = $this->getCleanTripCodeExpr($alias);
        return "SUBSTRING($cleanTC, 9, 2)";
    }

    /**
     * Get product expression
     */
    private function getProductExpr(string $alias = ''): string
    {
        $prefix = $alias ? $alias . '.' : '';
        $cleanTC = $this->getCleanTripCodeExpr($alias);
        return "CASE
            WHEN LOWER({$prefix}order_type)='wpt' THEN 'GDEALS'
            WHEN LOWER({$prefix}order_type)='gds' THEN 'FIT'
            WHEN CHAR_LENGTH($cleanTC) > 12 THEN 'GDEALS'
            ELSE 'FIT'
        END";
    }

    /**
     * Get amount numeric expression
     */
    private function getAmountNumExpr(string $alias = ''): string
    {
        $prefix = $alias ? $alias . '.' : '';
        return "NULLIF(CAST(NULLIF(REGEXP_REPLACE({$prefix}total_amount, '[^0-9.-]', ''), '') AS DECIMAL(15,2)), 0)";
    }

    /**
     * Build WHERE clause for date range and filters
     */
    private function buildWhereClause(string $startDate, string $endDate, array $filters = []): array
    {
        $where = "WHERE order_date >= :start AND order_date <= :end";
        $params = [
            ':start' => $startDate . ' 00:00:00',
            ':end' => $endDate . ' 23:59:59'
        ];

        $routeExpr = $this->getRouteExpr();
        if (!empty($filters['route'])) {
            $where .= " AND $routeExpr LIKE :route";
            $params[':route'] = '%' . $filters['route'] . '%';
        }

        if (!empty($filters['product'])) {
            $productExpr = $this->getProductExpr();
            $clauses = [];
            if (in_array('FIT', $filters['product'], true)) {
                $clauses[] = "(LOWER(order_type)='gds' OR (LOWER(order_type) NOT IN ('gds','wpt') AND CHAR_LENGTH({$this->getCleanTripCodeExpr()}) <= 12))";
            }
            if (in_array('GDEALS', $filters['product'], true)) {
                $clauses[] = "(LOWER(order_type)='wpt' OR (LOWER(order_type) NOT IN ('gds','wpt') AND CHAR_LENGTH({$this->getCleanTripCodeExpr()}) > 12))";
            }
            if ($clauses) {
                $where .= " AND (" . implode(' OR ', $clauses) . ")";
            }
        }

        return [$where, $params];
    }

    /**
     * Get KPIs
     */
    public function getKPIs(string $startDate, string $endDate, array $filters = []): ?array
    {
        [$where, $params] = $this->buildWhereClause($startDate, $endDate, $filters);
        $amountNum = $this->getAmountNumExpr();

        $sql = "
            SELECT
                COALESCE(SUM(total_pax),0) AS tickets,
                COALESCE(SUM(CASE WHEN payment_status IN ('paid','partially_paid') THEN total_pax ELSE 0 END),0) AS paid_tickets,
                0 AS revenue,
                AVG(CASE
                    WHEN payment_status IN ('paid','partially_paid')
                    AND $amountNum IS NOT NULL
                    AND $amountNum>0
                    AND total_pax IS NOT NULL AND total_pax>0
                    THEN $amountNum/total_pax END) AS min_paid_price
            FROM wpk4_backend_travel_bookings
            $where
        ";

        $result = $this->queryOne($sql, $params);
        return ($result === false) ? null : $result;
    }

    /**
     * Get revenue data (order-level)
     */
    public function getRevenueData(string $startDate, string $endDate, array $filters = []): array
    {
        [$where, $params] = $this->buildWhereClause($startDate, $endDate, $filters);
        $amountNum = $this->getAmountNumExpr();
        $productExpr = $this->getProductExpr();

        $sql = "
            SELECT order_id,
                   MAX($amountNum) AS amount_clean,
                   MAX($productExpr) AS product_kind
            FROM wpk4_backend_travel_bookings
            $where AND payment_status IN ('paid','partially_paid')
            GROUP BY order_id
        ";

        return $this->query($sql, $params);
    }

    /**
     * Get FIT revenue detail
     */
    public function getFitRevenueDetail(string $startDate, string $endDate): array
    {
        $amountNum = $this->getAmountNumExpr('b');
        $sql = "
            SELECT
                b.order_id,
                MAX($amountNum) AS fit_amount_clean
            FROM wpk4_backend_travel_bookings b
            WHERE b.order_date >= :start AND b.order_date <= :end
            AND LOWER(b.order_type) = 'gds'
            AND b.payment_status IN ('paid','partially_paid')
            GROUP BY b.order_id
        ";

        return $this->query($sql, [
            ':start' => $startDate . ' 00:00:00',
            ':end' => $endDate . ' 23:59:59'
        ]);
    }

    /**
     * Get split by product
     */
    public function getSplitByProduct(string $startDate, string $endDate, array $filters = []): array
    {
        [$where, $params] = $this->buildWhereClause($startDate, $endDate, $filters);
        $amountNum = $this->getAmountNumExpr();
        $productExpr = $this->getProductExpr();

        $sql = "
            SELECT
                $productExpr AS product,
                COALESCE(SUM(total_pax),0) AS tickets,
                COALESCE(SUM(CASE WHEN payment_status IN ('paid','partially_paid') THEN total_pax ELSE 0 END),0) AS paid_tickets,
                AVG(CASE
                    WHEN payment_status IN ('paid','partially_paid')
                    AND $amountNum IS NOT NULL
                    AND $amountNum>0
                    AND total_pax IS NOT NULL AND total_pax>0
                    THEN $amountNum/total_pax END) AS min_paid_price
            FROM wpk4_backend_travel_bookings
            $where
            GROUP BY $productExpr
        ";

        return $this->query($sql, $params);
    }

    /**
     * Get cancel breakdown
     */
    public function getCancelBreakdown(string $startDate, string $endDate, array $filters = []): array
    {
        [$where, $params] = $this->buildWhereClause($startDate, $endDate, $filters);

        $sql = "
            SELECT payment_status AS ps, COUNT(*) AS cnt 
            FROM wpk4_backend_travel_bookings 
            $where AND payment_status NOT IN ('paid','partially_paid') 
            GROUP BY payment_status
        ";

        return $this->query($sql, $params);
    }

    /**
     * Get tickets per day
     */
    public function getTicketsPerDay(string $startDate, string $endDate, array $filters = []): array
    {
        [$where, $params] = $this->buildWhereClause($startDate, $endDate, $filters);

        $sql = "
            SELECT 
                DATE(order_date) AS d, 
                COALESCE(SUM(
                    CASE 
                        WHEN order_type = 'WPT' AND t_type = 'return' THEN total_pax / 2 
                        ELSE total_pax 
                    END
                ), 0) AS tickets
            FROM wpk4_backend_travel_bookings
            $where
            GROUP BY DATE(order_date)
            ORDER BY DATE(order_date)
        ";

        return $this->query($sql, $params);
    }

    /**
     * Get airline paid tickets
     */
    public function getAirlinePaidTickets(string $startDate, string $endDate, array $filters = []): array
    {
        [$where, $params] = $this->buildWhereClause($startDate, $endDate, $filters);
        $airlineExpr = $this->getAirlineExpr();

        $sql = "
            SELECT $airlineExpr AS air,
                   COALESCE(SUM(CASE WHEN payment_status IN ('paid','partially_paid') THEN total_pax ELSE 0 END),0) AS paid
            FROM wpk4_backend_travel_bookings
            $where
            GROUP BY $airlineExpr
        ";

        return $this->query($sql, $params);
    }

    /**
     * Get orders with route and airline labels
     */
    public function getOrdersLabeled(string $startDate, string $endDate, array $filters = []): array
    {
        [$where, $params] = $this->buildWhereClause($startDate, $endDate, $filters);
        $amountNum = $this->getAmountNumExpr();
        $productExpr = $this->getProductExpr();
        $routeExpr = $this->getRouteExpr();
        $airlineExpr = $this->getAirlineExpr();

        $sql = "
            WITH oc AS (
                SELECT order_id,
                       MAX($amountNum) AS amount_clean,
                       MAX($productExpr) AS product_kind
                FROM wpk4_backend_travel_bookings
                $where AND payment_status IN ('paid','partially_paid')
                GROUP BY order_id
            ),
            rnk AS (
                SELECT order_id, $routeExpr AS route, $airlineExpr AS air, total_pax, order_date,
                       ROW_NUMBER() OVER (PARTITION BY order_id ORDER BY total_pax DESC, order_date ASC) AS rn
                FROM wpk4_backend_travel_bookings
                $where
            )
            SELECT oc.order_id, oc.amount_clean, oc.product_kind, rnk.route, rnk.air
            FROM oc 
            JOIN rnk ON rnk.order_id = oc.order_id AND rnk.rn = 1
        ";

        return $this->query($sql, $params);
    }

    /**
     * Get routes growth/drop
     */
    public function getRoutesGrowth(string $startDate, string $endDate, array $filters = []): array
    {
        [$where, $params] = $this->buildWhereClause($startDate, $endDate, $filters);
        $routeExpr = $this->getRouteExpr();

        $sql = "
            SELECT $routeExpr AS route,
                   COALESCE(SUM(CASE WHEN payment_status IN ('paid','partially_paid') THEN total_pax ELSE 0 END),0) AS paid
            FROM wpk4_backend_travel_bookings
            $where
            GROUP BY $routeExpr
        ";

        return $this->query($sql, $params);
    }

    /**
     * Get materialization data (12 months)
     */
    public function getMaterialization12m(?string $airlineHeat = null): array
    {
        $fixedEnd = new \DateTime('2026-03-31');
        $fixedMonth = (clone $fixedEnd)->modify('first day of this month');
        $startMonth = (new \DateTime('first day of this month'))->modify('-4 months');
        
        if ($startMonth > $fixedMonth) {
            $startMonth = (clone $fixedMonth)->modify('-4 months');
        }

        $months = [];
        $cur = clone $startMonth;
        while ($cur <= $fixedMonth) {
            $months[] = $cur->format('Y-m');
            $cur->modify('+1 month');
        }

        $travStart = (clone $startMonth)->format('Y-m-d');
        $travEnd = $fixedEnd->format('Y-m-d');

        $cleanTID = $this->getCleanTripIdExpr();
        $airStockExpr = "SUBSTRING($cleanTID, 9, 2)";
        $routeStockExpr = "SUBSTRING($cleanTID, 1, 7)";
        $originStockExpr = "SUBSTRING($cleanTID, 1, 3)";
        $destStockExpr = "SUBSTRING($cleanTID, 5, 3)";
        $jkeyStock = "CASE WHEN $airStockExpr='QF' THEN RIGHT($cleanTID,5) ELSE $cleanTID END";

        $AUS_SET_SQL = "'MEL','SYD','BNE','ADL','PER','CBR','OOL','CNS','DRW','HBA'";
        $routeStockExclude = "($routeStockExpr NOT LIKE '%AUS%' AND $routeStockExpr NOT LIKE '%IND%' AND $routeStockExpr NOT LIKE '%ORG%' AND $routeStockExpr NOT LIKE '%DES%' AND $routeStockExpr <> 'FROM-TO')";

        $QF_WHITELIST = [
            'DEL-MEL-QF070-QF070',
            'MEL-DEL-QF069-QF069',
            'SYD-BLR-QF067-QF067',
            'BLR-SYD-QF068-QF068'
        ];
        $QF_WHITELIST_SQL = "'" . implode("','", $QF_WHITELIST) . "'";

        // Stock aggregation
        $sqlStockAgg = "
            WITH stock_raw AS (
                SELECT
                    DATE(dep_date) AS ymd,
                    DATE_FORMAT(dep_date,'%Y-%m') AS ym,
                    $airStockExpr AS air,
                    CASE
                        WHEN $destStockExpr IN ($AUS_SET_SQL) THEN 'Inbound'
                        WHEN $originStockExpr IN ($AUS_SET_SQL) THEN 'Outbound'
                        ELSE 'Other'
                    END AS dir,
                    $jkeyStock AS jkey,
                    original_stock
                FROM wpk4_backend_stock_management_sheet
                WHERE dep_date >= :trav_start AND dep_date <= :trav_end
                AND $routeStockExclude
                AND original_stock > 0
                AND ($airStockExpr <> 'QF' OR $cleanTID IN ($QF_WHITELIST_SQL))
        ";

        $paramsStock = [':trav_start' => $travStart, ':trav_end' => $travEnd];
        if ($airlineHeat) {
            $sqlStockAgg .= " AND $airStockExpr = :airline_heat";
            $paramsStock[':airline_heat'] = $airlineHeat;
        }

        $sqlStockAgg .= "
            )
            SELECT ymd, ym, air, dir, jkey, SUM(original_stock) AS stock
            FROM stock_raw
            GROUP BY ymd, ym, air, dir, jkey
        ";

        $stockRows = $this->query($sqlStockAgg, $paramsStock);

        // PAX aggregation
        $cleanTC = $this->getCleanTripCodeExpr();
        $routeExpr = $this->getRouteExpr();
        $airlineExpr = $this->getAirlineExpr();
        $productExpr = $this->getProductExpr();
        $originExpr = "SUBSTRING($cleanTC, 1, 3)";
        $destExpr = "SUBSTRING($cleanTC, 5, 3)";
        $jkeyPax = "CASE WHEN $airlineExpr='QF' THEN RIGHT($cleanTC,5) ELSE $cleanTC END";

        $sqlPaxAgg = "
            SELECT
                DATE(travel_date) AS ymd,
                DATE_FORMAT(travel_date,'%Y-%m') AS ym,
                $airlineExpr AS air,
                CASE
                    WHEN $destExpr IN ($AUS_SET_SQL) THEN 'Inbound'
                    WHEN $originExpr IN ($AUS_SET_SQL) THEN 'Outbound'
                    ELSE 'Other'
                END AS dir,
                $jkeyPax AS jkey,
                SUM(total_pax) AS pax
            FROM wpk4_backend_travel_bookings
            WHERE travel_date >= :trav_start AND travel_date <= :trav_end
            AND $productExpr = 'GDEALS'
            AND payment_status IN ('paid','partially_paid')
        ";

        $paramsPax = [':trav_start' => $travStart, ':trav_end' => $travEnd];
        if ($airlineHeat) {
            $sqlPaxAgg .= " AND $airlineExpr = :airline_heat";
            $paramsPax[':airline_heat'] = $airlineHeat;
        }

        $sqlPaxAgg .= " GROUP BY ymd, ym, air, dir, jkey";
        $paxRows = $this->query($sqlPaxAgg, $paramsPax);

        // Join and aggregate
        $byKey = [];
        foreach ($stockRows as $s) {
            $key = $s['ym'] . '|' . $s['air'] . '|' . $s['dir'] . '|' . $s['jkey'];
            if (!isset($byKey[$key])) {
                $byKey[$key] = ['ym' => $s['ym'], 'air' => $s['air'], 'dir' => $s['dir'], 'stock' => 0, 'pax' => 0];
            }
            $byKey[$key]['stock'] += (int)$s['stock'];
        }

        foreach ($paxRows as $p) {
            $key = $p['ym'] . '|' . $p['air'] . '|' . $p['dir'] . '|' . $p['jkey'];
            if (isset($byKey[$key])) {
                $byKey[$key]['pax'] += (int)$p['pax'];
            }
        }

        // Roll up to month + airline + direction
        $matMap = [];
        foreach ($byKey as $agg) {
            if ($agg['dir'] === 'Other') continue;
            $key = $agg['ym'] . '|' . $agg['air'] . '|' . $agg['dir'];
            if (!isset($matMap[$key])) {
                $matMap[$key] = ['ym' => $agg['ym'], 'air' => $agg['air'], 'dir' => $agg['dir'], 'stock' => 0, 'pax' => 0];
            }
            $matMap[$key]['stock'] += $agg['stock'];
            $matMap[$key]['pax'] += $agg['pax'];
        }

        // Build heatmap structure
        $atypeSet = [];
        $byKeyFinal = [];
        $mIndex = array_flip($months);

        foreach ($matMap as $agg) {
            $ym = $agg['ym'];
            $air = $agg['air'];
            $dir = $agg['dir'];
            $s = (int)$agg['stock'];
            if ($s <= 0) continue;
            $p = (int)$agg['pax'];
            $atype = $air . '-' . $dir;
            $atypeSet[$atype] = ['air' => $air, 'dir' => $dir];
            $byKeyFinal[$ym . '|' . $air . '|' . $dir] = ['p' => $p, 's' => $s];
        }

        // Sort rows by airline code, then Inbound before Outbound
        $airDirOrder = ['Inbound' => 0, 'Outbound' => 1];
        $atypeList = array_keys($atypeSet);
        usort($atypeList, function($a, $b) use ($airDirOrder) {
            [$aa, $da] = explode('-', $a, 2);
            [$bb, $db] = explode('-', $b, 2);
            return ($aa === $bb)
                ? (($airDirOrder[$da] ?? 9) <=> ($airDirOrder[$db] ?? 9))
                : ($aa <=> $bb);
        });
        $yIndex = array_flip($atypeList);

        // % materialised = pax / stock, capped 0..100
        $percentCells = [];
        foreach ($byKeyFinal as $k => $agg) {
            list($ym, $air, $dir) = explode('|', $k, 3);
            $atype = $air . '-' . $dir;
            if (!isset($mIndex[$ym]) || !isset($yIndex[$atype])) continue;
            $p = (int)$agg['p'];
            $s = (int)$agg['s'];
            $v = ($s > 0) ? ($p / $s * 100.0) : 0.0;
            if ($v < 0) $v = 0.0;
            if ($v > 100) $v = 100.0;
            $percentCells[] = ['x' => $mIndex[$ym], 'y' => $yIndex[$atype], 'v' => round($v, 1), 'p' => $p, 's' => $s];
        }

        return [
            'months' => $months,
            'airlineTypes' => $atypeList,
            'percent' => $percentCells,
            'airlines' => $atypeList,
            'routes' => $atypeList
        ];
    }

    /**
     * Get advance purchase data
     */
    public function getAdvancePurchase(string $startDate, string $endDate, ?string $airlineHeat = null, ?string $apProduct = null): array
    {
        $where = "WHERE order_date >= :start AND order_date <= :end";
        $params = [
            ':start' => $startDate . ' 00:00:00',
            ':end' => $endDate . ' 23:59:59'
        ];

        $routeExpr = $this->getRouteExpr();
        $airlineExpr = $this->getAirlineExpr();
        $productExpr = $this->getProductExpr();

        if ($airlineHeat) {
            $where .= " AND $airlineExpr = :airline_heat";
            $params[':airline_heat'] = $airlineHeat;
        }

        if ($apProduct && $apProduct !== 'ALL') {
            $where .= " AND $productExpr = :ap_product";
            $params[':ap_product'] = $apProduct;
        }

        $sql = "
            SELECT $routeExpr AS route,
                COALESCE(SUM(CASE WHEN DATEDIFF(travel_date, DATE(order_date)) < 30 THEN total_pax ELSE 0 END),0) AS b1,
                COALESCE(SUM(CASE WHEN DATEDIFF(travel_date, DATE(order_date)) BETWEEN 30 AND 45 THEN total_pax ELSE 0 END),0) AS b2,
                COALESCE(SUM(CASE WHEN DATEDIFF(travel_date, DATE(order_date)) BETWEEN 46 AND 60 THEN total_pax ELSE 0 END),0) AS b3,
                COALESCE(SUM(CASE WHEN DATEDIFF(travel_date, DATE(order_date)) BETWEEN 61 AND 120 THEN total_pax ELSE 0 END),0) AS b4,
                COALESCE(SUM(CASE WHEN DATEDIFF(travel_date, DATE(order_date)) BETWEEN 121 AND 180 THEN total_pax ELSE 0 END),0) AS b5,
                COALESCE(SUM(CASE WHEN DATEDIFF(travel_date, DATE(order_date)) > 180 THEN total_pax ELSE 0 END),0) AS b6
            FROM wpk4_backend_travel_bookings
            $where
            GROUP BY $routeExpr
        ";

        return $this->query($sql, $params);
    }

    /**
     * Get price buckets
     */
    public function getPriceBuckets(string $startDate, string $endDate, array $filters = []): array
    {
        [$where, $params] = $this->buildWhereClause($startDate, $endDate, $filters);
        $routeExprB = $this->getRouteExpr('b');
        $productExprB = $this->getProductExpr('b');
        $amountNumB = $this->getAmountNumExpr('b');
        $pPrice = "CAST(NULLIF(REGEXP_REPLACE(p.trip_price_individual, '[^0-9.-]', ''), '') AS DECIMAL(12,2))";

        // GDEALS per-ticket from PAX cleaned price
        $sqlGdeals = "
            SELECT $pPrice AS price
            FROM wpk4_backend_travel_booking_pax p
            JOIN wpk4_backend_travel_bookings b ON p.order_id=b.order_id AND p.product_id=b.product_id
            $where AND b.order_type='wpt'
        ";

        $gdealsRows = $this->query($sqlGdeals, $params);

        // FIT per-ticket via FARE/TAX logic
        $sqlFit = "
            SELECT
                COALESCE(tax.tax_amt,0) + COALESCE(f.PriceSell,0) AS price
            FROM wpk4_backend_travel_booking_pax p
            JOIN wpk4_backend_travel_bookings b ON p.order_id=b.order_id AND p.product_id=b.product_id
            LEFT JOIN wpk4_ypsilon_bookings_table_fare f ON p.gds_pax_id = f.PaxId
            LEFT JOIN (
                SELECT PaxId, SUM(Amount) AS tax_amt
                FROM wpk4_ypsilon_bookings_table_tax
                GROUP BY PaxId
            ) tax ON tax.PaxId = f.PaxId
            $where AND ($productExprB='FIT')
        ";

        $fitRows = $this->query($sqlFit, $params);

        // Fallback if primary empty
        if (count($gdealsRows) === 0) {
            $sqlGdealsFB = "
                SELECT $amountNumB AS amt, b.total_pax
                FROM wpk4_backend_travel_bookings b
                $where AND ($productExprB='GDEALS')
                AND $amountNumB IS NOT NULL AND $amountNumB>0
                AND b.total_pax IS NOT NULL AND b.total_pax>0
            ";
            $gdealsRows = $this->query($sqlGdealsFB, $params);
        }

        if (count($fitRows) === 0) {
            $sqlFitFB = "
                SELECT $amountNumB AS amt, b.total_pax
                FROM wpk4_backend_travel_bookings b
                $where AND ($productExprB='FIT')
                AND $amountNumB IS NOT NULL AND $amountNumB>0
                AND b.total_pax IS NOT NULL AND b.total_pax>0
            ";
            $fitRows = $this->query($sqlFitFB, $params);
        }

        // Process and bin
        $items_g = [];
        $items_f = [];
        $excluded_g = 0;
        $excluded_f = 0;

        foreach ($gdealsRows as $r) {
            $v = (float)($r['price'] ?? ($r['amt'] ?? 0) / max(1, (int)($r['total_pax'] ?? 1)));
            if ($v >= 400 && $v < 2000) {
                $items_g[] = ['p' => $v, 'w' => isset($r['total_pax']) ? (int)$r['total_pax'] : 1];
            } else {
                $excluded_g += isset($r['total_pax']) ? (int)$r['total_pax'] : 1;
            }
        }

        foreach ($fitRows as $r) {
            $v = (float)($r['price'] ?? ($r['amt'] ?? 0) / max(1, (int)($r['total_pax'] ?? 1)));
            if ($v >= 400 && $v < 2000) {
                $items_f[] = ['p' => $v, 'w' => isset($r['total_pax']) ? (int)$r['total_pax'] : 1];
            } else {
                $excluded_f += isset($r['total_pax']) ? (int)$r['total_pax'] : 1;
            }
        }

        // Bin to fixed $100 buckets from 400..1999
        $minBin = 400;
        $stepBin = 100;
        $endEdge = 2000;
        $edges = [];
        for ($x = $minBin; $x <= $endEdge; $x += $stepBin) {
            $edges[] = (float)$x;
        }

        $labels = [];
        for ($i = 0; $i < count($edges) - 1; $i++) {
            $L = $edges[$i];
            $R = $edges[$i + 1] - 1;
            $labels[] = '$' . number_format($L, 0) . '–' . number_format($R, 0);
        }

        $binWeighted = function (array $items, array $edges) {
            $bins = max(0, count($edges) - 1);
            $counts = array_fill(0, $bins, 0);
            if (!$bins) return $counts;
            $start = $edges[0];
            $step = $edges[1] - $edges[0];
            $last = $edges[$bins];
            foreach ($items as $it) {
                $p = $it['p'];
                $w = (int)$it['w'];
                if (!is_numeric($p) || $w <= 0) continue;
                if ($p < $start || $p >= $last) continue;
                $idx = (int)floor(($p - $start) / $step);
                if ($idx < 0) $idx = 0;
                if ($idx >= $bins) $idx = $bins - 1;
                $counts[$idx] += $w;
            }
            return $counts;
        };

        $gCounts = $binWeighted($items_g, $edges);
        $fCounts = $binWeighted($items_f, $edges);

        return [
            'edges' => $edges,
            'labels' => $labels,
            'gdeals_pax' => $gCounts,
            'fit_pax' => $fCounts,
            'meta' => [
                'rule' => 'fixed_100_bins_cutoff_2000',
                'start' => $minBin,
                'step' => $stepBin,
                'cutoff_max' => 2000,
                'primary_gdeals' => (count($gdealsRows) > 0 && !isset($gdealsRows[0]['amt'])),
                'primary_fit' => (count($fitRows) > 0 && !isset($fitRows[0]['amt'])),
                'excluded_outliers' => ['gdeals' => $excluded_g, 'fit' => $excluded_f]
            ]
        ];
    }

    /**
     * Get revenue data with filters
     */
    public function getRevenueDataWithFilters(string $from, string $to, array $filters = []): array
    {
        $where = "WHERE DATE(a.order_date) BETWEEN :from AND :to";
        $params = [':from' => $from, ':to' => $to];

        if (!empty($filters['airlines'])) {
            $placeholders = [];
            foreach ($filters['airlines'] as $i => $airline) {
                $key = ':airline' . $i;
                $placeholders[] = $key;
                $params[$key] = $airline;
            }
            $where .= " AND MID(a.trip_code,9,2) IN (" . implode(',', $placeholders) . ")";
        }

        if (!empty($filters['routes'])) {
            $placeholders = [];
            foreach ($filters['routes'] as $i => $route) {
                $key = ':route' . $i;
                $placeholders[] = $key;
                $params[$key] = $route;
            }
            $where .= " AND LEFT(a.trip_code,7) IN (" . implode(',', $placeholders) . ")";
        }

        if (!empty($filters['payment_status'])) {
            $placeholders = [];
            foreach ($filters['payment_status'] as $i => $status) {
                $key = ':payment' . $i;
                $placeholders[] = $key;
                $params[$key] = $status;
            }
            $where .= " AND a.payment_status IN (" . implode(',', $placeholders) . ")";
        } else {
            $where .= " AND a.payment_status <> :pending";
            $params[':pending'] = 'pending';
        }

        if (!empty($filters['route_types'])) {
            $placeholders = [];
            foreach ($filters['route_types'] as $i => $type) {
                $key = ':route_type' . $i;
                $placeholders[] = $key;
                $params[$key] = $type;
            }
            $where .= " AND CASE 
                WHEN b.country = 'AU' THEN 'Outbound'
                WHEN b.country = 'IN' THEN 'Inbound'
                ELSE 'Others'
            END IN (" . implode(',', $placeholders) . ")";
        }

        $sql = "
            SELECT
                a.order_id,
                DATE(a.order_date) AS booking_date,
                DATE(a.travel_date) AS travel_date,
                a.payment_status AS payment_status,
                CASE WHEN order_type = 'wpt' AND t_type = 'return' THEN total_amount / 2 ELSE total_amount END AS revenue,
                ROUND(CASE WHEN order_type = 'wpt' AND t_type = 'return' THEN total_pax / 2 ELSE total_pax END,0) AS pax,
                CASE WHEN a.order_type = 'Gds' THEN 'FIT' ELSE 'Gdeals' END AS order_type,
                CASE 
                    WHEN b.country = 'AU' THEN 'Outbound'
                    WHEN b.country = 'IN' THEN 'Inbound'
                    ELSE 'Others'
                END AS route_type,
                LEFT(a.trip_code,7) AS route,
                MID(a.trip_code,9,2) AS airlines
            FROM wpk4_backend_travel_bookings a
            LEFT JOIN airport_list b ON LEFT(a.trip_code,3) = b.airpotcode
            $where
            ORDER BY a.order_date ASC, a.order_id ASC
        ";

        return $this->query($sql, $params);
    }

    /**
     * Get advance purchase booking data
     */
    public function getAdvancePurchaseBookingData(string $from, string $to, array $filters = []): array
    {
        $where = "WHERE a.order_date IS NOT NULL
            AND a.travel_date IS NOT NULL
            AND a.payment_status IN ('paid','partially_paid')
            AND DATE(a.travel_date) BETWEEN :from AND :to";
        $params = [':from' => $from, ':to' => $to];

        if (!empty($filters['type']) && $filters['type'] !== 'All') {
            if ($filters['type'] === 'FIT') {
                $where .= " AND a.order_type = 'Gds'";
            } elseif ($filters['type'] === 'Gdeals') {
                $where .= " AND (a.order_type <> 'Gds' OR a.order_type IS NULL)";
            }
        }

        if (!empty($filters['route_type']) && $filters['route_type'] !== 'All') {
            $where .= " AND CASE 
                WHEN b.country = 'AU' THEN 'Outbound'
                WHEN b.country = 'IN' THEN 'Inbound'
                ELSE 'Others'
            END = :route_type";
            $params[':route_type'] = $filters['route_type'];
        }

        if (!empty($filters['airlines']) && $filters['airlines'] !== 'All') {
            $where .= " AND MID(a.trip_code,9,2) = :airlines";
            $params[':airlines'] = $filters['airlines'];
        }

        if (!empty($filters['route']) && $filters['route'] !== 'All') {
            $where .= " AND LEFT(a.trip_code,7) = :route";
            $params[':route'] = $filters['route'];
        }

        $limit = isset($filters['limit']) ? min((int)$filters['limit'], 500000) : 200000;
        $where .= " LIMIT " . (int)$limit;

        $sql = "
            SELECT
                a.order_id,
                DATE(a.order_date) AS booking_date,
                DATE(a.travel_date) AS travel_date,
                a.total_pax AS pax,
                CASE WHEN (a.order_type) = 'Gds' THEN 'FIT' ELSE 'Gdeals' END AS order_type,
                CASE 
                    WHEN b.country = 'AU' THEN 'Outbound'
                    WHEN b.country = 'IN' THEN 'Inbound'
                    ELSE 'Others'
                END AS route_type,
                LEFT(a.trip_code,7) AS route,
                MID(a.trip_code,9,2) AS airlines
            FROM wpk4_backend_travel_bookings a
            LEFT JOIN airport_list b ON LEFT(a.trip_code,3) = b.airpotcode
            $where
            ORDER BY a.travel_date ASC, a.order_date ASC
        ";

        return $this->query($sql, $params);
    }

    /**
     * Get materialization months
     */
    public function getMaterializationMonths(): array
    {
        $twelve_months_ago = date('Y-m-01', strtotime('-20 months'));
        $sql = "
            SELECT DISTINCT DATE_FORMAT(dep_date, '%Y-%m') AS month
            FROM wpk4_backend_stock_management_sheet
            WHERE dep_date >= :start_date
            ORDER BY month ASC
        ";

        $results = $this->query($sql, [':start_date' => $twelve_months_ago]);
        return array_map(function($r) {
            return date('M-y', strtotime($r['month']));
        }, $results);
    }

    /**
     * Get materialization data for travel month
     */
    public function getMaterializationDataForMonth(string $travelMonth): array
    {
        $map = [
            'Jan'=>'01','Feb'=>'02','Mar'=>'03','Apr'=>'04','May'=>'05','Jun'=>'06',
            'Jul'=>'07','Aug'=>'08','Sep'=>'09','Oct'=>'10','Nov'=>'11','Dec'=>'12'
        ];
        [$m, $y] = explode('-', $travelMonth);
        $targetTravelMonth = "20$y-" . $map[$m];
        $travelDate = new \DateTime("$targetTravelMonth-01");

        // Stock per airline
        $sqlStock = "
            SELECT airline_code AS airline, SUM(original_stock) AS stock
            FROM wpk4_backend_stock_management_sheet
            WHERE DATE_FORMAT(dep_date, '%Y-%m') = :month
            GROUP BY airline_code
        ";
        $stocks = $this->query($sqlStock, [':month' => $targetTravelMonth]);
        $stockMap = [];
        foreach ($stocks as $row) {
            $stockMap[$row['airline']] = (int)$row['stock'];
        }

        // Total stock
        $sqlTotalStock = "
            SELECT SUM(original_stock) AS total_stock
            FROM wpk4_backend_stock_management_sheet
            WHERE DATE_FORMAT(dep_date, '%Y-%m') = :month
        ";
        $totalStockResult = $this->queryOne($sqlTotalStock, [':month' => $targetTravelMonth]);
        $totalStockForTravelMonth = (int)($totalStockResult['total_stock'] ?? 0);

        // Build 21 months range
        $orderMonths = [];
        for ($i = 20; $i >= 0; $i--) {
            $d = (clone $travelDate)->modify("-{$i} months");
            $orderMonths[] = $d->format('Y-m');
        }

        // Monthly bookings
        $sqlBookings = "
            SELECT MID(b.trip_code, 9, 2) AS airline,
                   SUM(b.total_pax) AS pax,
                   DATE_FORMAT(b.order_date, '%Y-%m') AS order_month
            FROM wpk4_backend_travel_bookings b
            WHERE b.payment_status IN ('paid', 'partially_paid')
            AND b.order_type <> 'gds'
            AND b.product_id NOT IN (60116,60107)
            AND DATE_FORMAT(b.travel_date, '%Y-%m') = :month
            GROUP BY airline, order_month
        ";
        $bookings = $this->query($sqlBookings, [':month' => $targetTravelMonth]);

        // Map bookings
        $monthlyPax = [];
        $airlineSet = [];
        foreach ($bookings as $r) {
            $a = $r['airline'];
            $m = $r['order_month'];
            $monthlyPax[$a][$m] = (int)$r['pax'];
            $airlineSet[$a] = true;
        }
        foreach (array_keys($stockMap) as $a) {
            $airlineSet[$a] = true;
        }
        $airlineList = array_values(array_keys($airlineSet));
        sort($airlineList);

        // Build output
        $output = [];
        $cumAirline = array_fill_keys($airlineList, 0);
        $cumTotal = 0;

        foreach ($orderMonths as $orderMonth) {
            $target = $this->targetCurve($travelDate, $orderMonth);
            $totalPaxThisMonth = 0;

            foreach ($airlineList as $airline) {
                $monthlyPaxValue = (int)($monthlyPax[$airline][$orderMonth] ?? 0);
                $cumAirline[$airline] += $monthlyPaxValue;
                $stock = (int)($stockMap[$airline] ?? 0);

                $monthlyPct = $stock > 0 ? round(($monthlyPaxValue / $stock) * 100, 1) : 0.0;
                $cumuPct = $stock > 0 ? round(($cumAirline[$airline] / $stock) * 100, 1) : 0.0;

                $output[] = [
                    'airline' => $airline,
                    'order_month' => $orderMonth,
                    'travel_month' => $targetTravelMonth,
                    'pax' => $monthlyPaxValue,
                    'cum_pax' => $cumAirline[$airline],
                    'stock' => $stock,
                    'actual' => $monthlyPct,
                    'cum_actual' => $cumuPct,
                    'target' => $target
                ];

                $totalPaxThisMonth += $monthlyPaxValue;
            }

            // TOTAL row
            $cumTotal += $totalPaxThisMonth;
            $monthlyTotalPct = $totalStockForTravelMonth > 0 ? round(($totalPaxThisMonth / $totalStockForTravelMonth) * 100, 1) : 0.0;
            $cumuTotalPct = $totalStockForTravelMonth > 0 ? round(($cumTotal / $totalStockForTravelMonth) * 100, 1) : 0.0;

            $output[] = [
                'airline' => 'TOTAL',
                'order_month' => $orderMonth,
                'travel_month' => $targetTravelMonth,
                'pax' => $totalPaxThisMonth,
                'cum_pax' => $cumTotal,
                'stock' => $totalStockForTravelMonth,
                'actual' => $monthlyTotalPct,
                'cum_actual' => $cumuTotalPct,
                'target' => $target
            ];
        }

        return $output;
    }

    /**
     * Target curve calculation
     */
    private function targetCurve(\DateTime $travelDate, string $orderMonth): int
    {
        $o = \DateTime::createFromFormat('Y-m', $orderMonth);
        $diff = (($travelDate->format('Y') - $o->format('Y')) * 12) + ($travelDate->format('m') - $o->format('m'));
        return match(true) {
            $diff === 6, $diff === 5 => 5,
            $diff === 4 => 10,
            $diff === 3 => 20,
            $diff === 2 => 40,
            $diff === 1 => 60,
            $diff === 0 => 80,
            default => 0,
        };
    }

    /**
     * Get pricing velocity data
     */
    public function getPricingVelocityData(): array
    {
        $cleanTC = $this->getCleanTripCodeExpr('s');
        $cleanTID = $this->getCleanTripIdExpr('s');
        $airStockExpr = "SUBSTRING($cleanTID, 9, 2)";
        $routeStockExpr = "SUBSTRING($cleanTID, 1, 7)";

        $sql = "
            SELECT 
                s.auto_id AS stock_id,
                s.airline_code as Airlines,
                s.route as route,
                s.trip_id,
                s.route_type,
                s.dep_date,
                DATEDIFF(s.dep_date, CURRENT_DATE) AS dtd,
                s.current_stock as Stock,
                s.original_stock as original_stock,
                COALESCE(b.paid_pax, 0) AS paid_pax,
                COALESCE(c.partially_paid_pax, 0) AS partially_paid_pax,
                COALESCE(b.paid_pax, 0) + COALESCE(c.partially_paid_pax, 0) as pax,   
                s.current_stock - (COALESCE(b.paid_pax, 0) + COALESCE(c.partially_paid_pax, 0)) as unsold,
                COALESCE(s.aud_fare, 0) + COALESCE(s.tax, 0) as cost,
                p.sale_price,
                sa.pricing_id
            FROM wpk4_backend_stock_management_sheet s
            LEFT JOIN (
                SELECT 
                    trip_code COLLATE utf8mb4_general_ci AS trip_code,
                    travel_date,
                    SUM(total_pax) AS paid_pax
                FROM wpk4_backend_travel_bookings
                WHERE payment_status = 'paid'
                GROUP BY trip_code, travel_date
            ) b ON s.trip_id COLLATE utf8mb4_general_ci = b.trip_code AND s.dep_date = b.travel_date
            LEFT JOIN (
                SELECT 
                    trip_code COLLATE utf8mb4_general_ci AS trip_code,
                    travel_date,
                    SUM(total_pax) AS partially_paid_pax
                FROM wpk4_backend_travel_bookings
                WHERE payment_status = 'partially_paid'
                GROUP BY trip_code, travel_date
            ) c ON s.trip_id COLLATE utf8mb4_general_ci = c.trip_code AND s.dep_date = c.travel_date
            LEFT JOIN wpk4_backend_manage_seat_availability sa 
                ON s.trip_id = sa.trip_code AND s.dep_date = sa.travel_date 
            LEFT JOIN (
                SELECT 
                    pricing_id, pricing_category_id, sale_price
                FROM wpk4_wt_price_category_relation
                WHERE pricing_category_id = '953'
            ) p ON sa.pricing_id = p.pricing_id 
            WHERE s.dep_date >= CURRENT_DATE
        ";

        $baseRows = $this->query($sql);

        // Get velocity data
        $sqlVel = "
            SELECT
                trip_code COLLATE utf8mb4_general_ci AS trip_code,
                travel_date,
                SUM(CASE WHEN DATE(order_date) >= CURDATE() - INTERVAL 7 DAY THEN total_pax ELSE 0 END) AS v7_pax,
                SUM(CASE WHEN DATE(order_date) >= CURDATE() - INTERVAL 14 DAY THEN total_pax ELSE 0 END) AS v14_pax,
                SUM(CASE WHEN DATE(order_date) >= CURDATE() - INTERVAL 30 DAY THEN total_pax ELSE 0 END) AS v30_pax
            FROM wpk4_backend_travel_bookings
            WHERE payment_status IN ('paid','partially_paid')
            GROUP BY trip_code, travel_date
        ";

        $velRows = $this->query($sqlVel);
        $velMap = [];
        foreach ($velRows as $v) {
            $key = $v['trip_code'] . '|' . $v['travel_date'];
            $velMap[$key] = $v;
        }

        // Merge velocity into base rows
        $result = [];
        foreach ($baseRows as $row) {
            $key = $row['trip_id'] . '|' . $row['dep_date'];
            $stock = max(0, (int)$row['Stock']);
            $v7 = isset($velMap[$key]) ? (int)$velMap[$key]['v7_pax'] : 0;
            $v14 = isset($velMap[$key]) ? (int)$velMap[$key]['v14_pax'] : 0;
            $v30 = isset($velMap[$key]) ? (int)$velMap[$key]['v30_pax'] : 0;

            $result[] = [
                'stock_id' => $row['stock_id'],
                'airline' => $row['Airlines'],
                'route' => $row['route'],
                'trip_id' => $row['trip_id'],
                'route_type' => $row['route_type'],
                'dep_date' => $row['dep_date'],
                'dtd' => isset($row['dtd']) ? (int)$row['dtd'] : null,
                'stock' => $stock,
                'original_stock' => (int)($row['original_stock'] ?? 0),
                'paid_pax' => (int)($row['paid_pax'] ?? 0),
                'partially_paid_pax' => (int)($row['partially_paid_pax'] ?? 0),
                'pax' => (int)($row['pax'] ?? 0),
                'unsold' => (int)($row['unsold'] ?? 0),
                'cost' => isset($row['cost']) ? (float)$row['cost'] : null,
                'sale_price' => isset($row['sale_price']) ? (float)$row['sale_price'] : null,
                'pricing_id' => $row['pricing_id'] ?? null,
                'v7' => $stock > 0 ? round($v7 / $stock, 2) : 0.0,
                'v14' => $stock > 0 ? round($v14 / $stock, 2) : 0.0,
                'v30' => $stock > 0 ? round($v30 / $stock, 2) : 0.0
            ];
        }

        return $result;
    }

    /**
     * Save AI summary
     */
    public function saveAISummary(array $data): ?int
    {
        // Use PDO instead of wpdb
        $table = 'wpk4_ai_sales_summaries'; // Adjust table name based on your prefix
        
        // Create table if missing
        $create_sql = "
            CREATE TABLE IF NOT EXISTS `$table` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                model VARCHAR(64) NOT NULL,
                start_date DATE NULL,
                end_date DATE NULL,
                prev_start DATE NULL,
                prev_end DATE NULL,
                route_filter VARCHAR(64) NULL,
                airline_heat VARCHAR(16) NULL,
                ap_product VARCHAR(16) NULL,
                product_filters VARCHAR(128) NULL,
                payload_json LONGTEXT NULL,
                summary_text MEDIUMTEXT NULL,
                prompt_tokens INT NULL,
                completion_tokens INT NULL,
                total_tokens INT NULL,
                request_ms INT NULL,
                summary_hash CHAR(64) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_hash (summary_hash),
                KEY idx_period (start_date, end_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        try {
            $this->execute($create_sql, []);
        } catch (Exception $e) {
            error_log("Failed to create table $table: " . $e->getMessage());
            // Continue anyway - table might already exist
        }
    
        $period = $data['period'] ?? [];
        $start_date = isset($period['start']) ? substr($period['start'], 0, 10) : null;
        $end_date = isset($period['end']) ? substr($period['end'], 0, 10) : null;
        $prev_start = isset($period['prev_start']) ? substr($period['prev_start'], 0, 10) : null;
        $prev_end = isset($period['prev_end']) ? substr($period['prev_end'], 0, 10) : null;
    
        $filters = $period['filters'] ?? [];
        $route_filter = isset($filters['route']) ? substr((string)$filters['route'], 0, 64) : null;
        $airline_heat = isset($filters['airline_heat']) ? substr((string)$filters['airline_heat'], 0, 16) : null;
        $ap_product = isset($filters['ap_product']) ? substr((string)$filters['ap_product'], 0, 16) : null;
        $product_filters = (!empty($filters['product']) && is_array($filters['product']))
            ? substr(implode(',', $filters['product']), 0, 128) : null;
    
        $model = $data['model'] ?? 'gpt-5-chat-latest';
        $text = $data['summary_text'] ?? '';
        $payload_json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $usage = $data['usage'] ?? [];
        $prompt_tokens = isset($usage['prompt_tokens']) ? (int)$usage['prompt_tokens'] : null;
        $completion_tokens = isset($usage['completion_tokens']) ? (int)$usage['completion_tokens'] : null;
        $total_tokens = isset($usage['total_tokens']) ? (int)$usage['total_tokens'] : null;
        $request_ms = isset($data['request_ms']) ? (int)$data['request_ms'] : null;
    
        $summary_hash = hash('sha256', json_encode([
            'start' => $start_date, 'end' => $end_date,
            'prev_start' => $prev_start, 'prev_end' => $prev_end,
            'route' => $route_filter, 'airline_heat' => $airline_heat,
            'ap_product' => $ap_product, 'products' => $product_filters,
            'model' => $model, 'text' => $text
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    
        $sql = "
            INSERT INTO `$table`
                (model, start_date, end_date, prev_start, prev_end,
                 route_filter, airline_heat, ap_product, product_filters,
                 payload_json, summary_text,
                 prompt_tokens, completion_tokens, total_tokens, request_ms, summary_hash)
            VALUES
                (:model, :start_date, :end_date, :prev_start, :prev_end,
                 :route_filter, :airline_heat, :ap_product, :product_filters,
                 :payload_json, :summary_text,
                 :prompt_tokens, :completion_tokens, :total_tokens, :request_ms, :summary_hash)
            ON DUPLICATE KEY UPDATE
                updated_at = CURRENT_TIMESTAMP,
                summary_text = VALUES(summary_text),
                payload_json = VALUES(payload_json),
                model = VALUES(model),
                start_date = VALUES(start_date),
                end_date = VALUES(end_date),
                prev_start = VALUES(prev_start),
                prev_end = VALUES(prev_end),
                route_filter = VALUES(route_filter),
                airline_heat = VALUES(airline_heat),
                ap_product = VALUES(ap_product),
                product_filters = VALUES(product_filters),
                prompt_tokens = VALUES(prompt_tokens),
                completion_tokens = VALUES(completion_tokens),
                total_tokens = VALUES(total_tokens),
                request_ms = VALUES(request_ms)
        ";
    
        $params = [
            ':model' => $model,
            ':start_date' => $start_date,
            ':end_date' => $end_date,
            ':prev_start' => $prev_start,
            ':prev_end' => $prev_end,
            ':route_filter' => $route_filter,
            ':airline_heat' => $airline_heat,
            ':ap_product' => $ap_product,
            ':product_filters' => $product_filters,
            ':payload_json' => $payload_json,
            ':summary_text' => $text,
            ':prompt_tokens' => $prompt_tokens,
            ':completion_tokens' => $completion_tokens,
            ':total_tokens' => $total_tokens,
            ':request_ms' => $request_ms,
            ':summary_hash' => $summary_hash
        ];
    
        try {
            $this->execute($sql, $params);
            
            // Get the inserted ID
            $summary_id = $this->lastInsertId();
            
            if ($summary_id) {
                return (int)$summary_id;
            } else {
                // If no insert ID (duplicate key update), fetch the existing ID
                $fetch_sql = "SELECT id FROM `$table` WHERE summary_hash = :summary_hash LIMIT 1";
                $result = $this->queryOne($fetch_sql, [':summary_hash' => $summary_hash]);
                return $result ? (int)$result['id'] : null;
            }
        } catch (Exception $e) {
            error_log("Failed to save AI summary: " . $e->getMessage());
            throw new Exception("Failed to save AI summary: " . $e->getMessage(), 500);
        }
    }
}

