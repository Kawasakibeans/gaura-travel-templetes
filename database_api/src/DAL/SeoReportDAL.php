<?php
/**
 * SEO Report DAL
 * Data access for SEO performance report
 */

namespace App\DAL;

use Exception;

class SeoReportDAL extends BaseDAL
{
    /**
     * Get quarterly performance data
     *
     * @param int $year
     * @return array<int, array<string, mixed>>
     */
    public function getQuarterlyPerformance(int $year = 2025): array
    {
        try {
            $sql = "
                SELECT 
                    id,
                    quarter,
                    year,
                    organic_traffic_target,
                    organic_traffic_actual,
                    organic_traffic_percent_change,
                    organic_revenue_target,
                    organic_revenue_actual,
                    organic_revenue_percent_diff,
                    organic_revenue_dollar_diff,
                    keywords_top3_target,
                    keywords_top3_actual,
                    keywords_top3_percent_change,
                    keywords_4_10,
                    keywords_4_10_percent_change,
                    keywords_total,
                    keywords_total_percent_change
                FROM seo_quarterly_performance
                WHERE year = :year
                ORDER BY 
                    CASE quarter
                        WHEN 'Q1' THEN 1
                        WHEN 'Q2' THEN 2
                        WHEN 'Q3' THEN 3
                        WHEN 'Q4' THEN 4
                    END
            ";

            return $this->query($sql, [':year' => $year]);
        } catch (Exception $e) {
            error_log("SeoReportDAL::getQuarterlyPerformance Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get period-based SEO metrics
     *
     * @param string|null $period Optional period filter (e.g., 'Q2 2025')
     * @return array<int, array<string, mixed>>
     */
    public function getPeriodMetrics(?string $period = null): array
    {
        try {
            $sql = "
                SELECT 
                    id,
                    period,
                    semrush_rank,
                    organic_traffic,
                    backlinks,
                    referring_domains,
                    paid_traffic
                FROM seo_period_metrics
            ";

            $params = [];
            if ($period !== null) {
                $sql .= " WHERE period = :period";
                $params[':period'] = $period;
            }

            $sql .= " ORDER BY period DESC";

            return $this->query($sql, $params);
        } catch (Exception $e) {
            error_log("SeoReportDAL::getPeriodMetrics Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get country traffic breakdown
     *
     * @param string|null $period Optional period filter (e.g., 'Q3 2025')
     * @return array<int, array<string, mixed>>
     */
    public function getCountryTraffic(?string $period = null): array
    {
        try {
            $sql = "
                SELECT 
                    id,
                    period,
                    country_name,
                    country_flag_url,
                    traffic_share_percentage,
                    traffic_count,
                    traffic_count_formatted
                FROM seo_country_traffic
            ";

            $params = [];
            if ($period !== null) {
                $sql .= " WHERE period = :period";
                $params[':period'] = $period;
            }

            $sql .= " ORDER BY period DESC, traffic_share_percentage DESC";

            return $this->query($sql, $params);
        } catch (Exception $e) {
            error_log("SeoReportDAL::getCountryTraffic Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get backlink analytics time series data
     *
     * @param int $months Number of months to retrieve (default 12)
     * @return array<int, array<string, mixed>>
     */
    public function getBacklinkAnalytics(int $months = 12): array
    {
        try {
            // Fix: LIMIT doesn't work with named parameters in PDO, use integer directly
            // Sanitize input to prevent SQL injection
            $months = (int)$months;
            if ($months < 1) {
                $months = 12; // Default to 12 if invalid
            }
            if ($months > 100) {
                $months = 100; // Cap at 100 for safety
            }

            $sql = "
                SELECT 
                    id,
                    report_date,
                    referring_domains_count,
                    scope
                FROM seo_backlink_analytics
                WHERE scope = 'Root Domain'
                ORDER BY report_date DESC
                LIMIT " . $months . "
            ";

            $results = $this->query($sql, []);
            
            // Reverse to show oldest first for chart
            return array_reverse($results);
        } catch (Exception $e) {
            error_log("SeoReportDAL::getBacklinkAnalytics Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate growth between two periods
     *
     * @param string $period1
     * @param string $period2
     * @return array<string, mixed>
     */
    public function getPeriodGrowth(string $period1, string $period2): array
    {
        try {
            // Fix: Use OR instead of IN with named parameters for better compatibility
            $sql = "
                SELECT 
                    period,
                    semrush_rank,
                    organic_traffic,
                    backlinks,
                    referring_domains,
                    paid_traffic
                FROM seo_period_metrics
                WHERE period = :period1 OR period = :period2
                ORDER BY period
            ";

            $results = $this->query($sql, [
                ':period1' => $period1,
                ':period2' => $period2
            ]);

            if (count($results) !== 2) {
                error_log("SeoReportDAL::getPeriodGrowth Warning: Expected 2 periods, got " . count($results));
                return [];
            }

            $p1 = $results[0];
            $p2 = $results[1];

            // Ensure we have the right order (period1 first, period2 second)
            if ($p1['period'] !== $period1) {
                // Swap if needed
                $temp = $p1;
                $p1 = $p2;
                $p2 = $temp;
            }

            // Helper function to parse values like "8.6K" to numbers
            $parseValue = function($val) {
                if (is_numeric($val)) return (float)$val;
                $val = str_replace(['K', 'M'], ['000', '000000'], $val);
                return (float)str_replace(',', '', $val);
            };

            // Calculate growth
            $growth = [
                'semrush_rank' => $this->calculateSemrushRankChange($p1['semrush_rank'], $p2['semrush_rank']),
                'organic_traffic' => $this->calculatePercentageChange($p1['organic_traffic'], $p2['organic_traffic']),
                'backlinks' => $this->calculatePercentageChange($p1['backlinks'], $p2['backlinks']),
                'referring_domains' => $this->calculatePercentageChange($p1['referring_domains'], $p2['referring_domains']),
                'paid_traffic' => $this->calculatePercentageChange($p1['paid_traffic'], $p2['paid_traffic'])
            ];

            return $growth;
        } catch (Exception $e) {
            error_log("SeoReportDAL::getPeriodGrowth Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate percentage change between two values
     */
    private function calculatePercentageChange($val1, $val2): float
    {
        try {
            $parseValue = function($val) {
                // Handle numeric values
                if (is_numeric($val)) {
                    return (float)$val;
                }
                
                // Handle null or empty values
                if (empty($val) || $val === null) {
                    return 0.0;
                }
                
                // Remove commas first
                $val = str_replace(',', '', trim($val));
                
                // Handle K suffix (thousands)
                if (stripos($val, 'K') !== false) {
                    $numericValue = (float)str_ireplace('K', '', $val);
                    return $numericValue * 1000;
                }
                
                // Handle M suffix (millions)
                if (stripos($val, 'M') !== false) {
                    $numericValue = (float)str_ireplace('M', '', $val);
                    return $numericValue * 1000000;
                }
                
                // Fallback: try to parse as float
                return (float)$val;
            };

            $v1 = $parseValue($val1);
            $v2 = $parseValue($val2);

            if ($v1 == 0) return 0;
            return (($v2 - $v1) / $v1) * 100;
        } catch (Exception $e) {
            error_log("SeoReportDAL::calculatePercentageChange Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Calculate Semrush rank change (lower is better, so negative change is positive)
     */
    private function calculateSemrushRankChange($rank1, $rank2): string
    {
        try {
            $parseRank = function($rank) {
                // Handle numeric values
                if (is_numeric($rank)) {
                    return (float)$rank;
                }
                
                // Handle null or empty values
                if (empty($rank) || $rank === null) {
                    return 0.0;
                }
                
                // Remove commas first
                $rank = str_replace(',', '', trim($rank));
                
                // Handle K suffix (thousands)
                if (stripos($rank, 'K') !== false) {
                    $numericValue = (float)str_ireplace('K', '', $rank);
                    return $numericValue * 1000;
                }
                
                // Handle M suffix (millions)
                if (stripos($rank, 'M') !== false) {
                    $numericValue = (float)str_ireplace('M', '', $rank);
                    return $numericValue * 1000000;
                }
                
                // Fallback: try to parse as float
                return (float)$rank;
            };

            $r1 = $parseRank($rank1);
            $r2 = $parseRank($rank2);

            // Calculate the difference
            $difference = $r2 - $r1;
            
            // Format the result with K suffix if >= 1000
            if (abs($difference) >= 1000) {
                $formatted = $difference / 1000;
                // Round to 1 decimal place
                $formatted = round($formatted, 1);
                // Remove trailing .0 if it's a whole number
                if ($formatted == (int)$formatted) {
                    return (string)(int)$formatted . 'K';
                }
                return (string)$formatted . 'K';
            }
            
            // Return as-is if less than 1000
            return (string)(int)$difference;
        } catch (Exception $e) {
            error_log("SeoReportDAL::calculateSemrushRankChange Error: " . $e->getMessage());
            return '0';
        }
    }
}