<?php
/**
 * Observation dashboard service
 */

namespace App\Services;

use App\DAL\ObservationDashboardDAL;
use DateTime;
use Exception;

class ObservationDashboardService
{
    private ObservationDashboardDAL $dal;

    public function __construct()
    {
        $this->dal = new ObservationDashboardDAL();
    }

    /**
     * Build dashboard summary for the supplied date.
     *
     * @param array<string, mixed> $filters
     */
    public function getSummary(array $filters = []): array
    {
        $dateValue = $filters['date'] ?? (new DateTime('today'))->format('Y-m-d');
        $date = $this->normaliseDate($dateValue, 'date');

        $abandoned = $this->dal->getAbandonedCallStats($date);
        $callCounts = $this->dal->getCallCounts($date);
        $durations = $this->dal->getDurationBuckets($date);
        $metrics = $this->dal->getKeyMetrics($date);

        $abandonedNormalised = [
            'total' => (int)($abandoned['abandoned_calls'] ?? 0),
            'breakdown' => [
                'GTIB' => (int)($abandoned['gtib_abandoned'] ?? 0),
                'GTDC' => (int)($abandoned['gtdc_abandoned'] ?? 0),
                'GTCS' => (int)($abandoned['gtcs_abandoned'] ?? 0),
                'GTPY' => (int)($abandoned['gtpy_abandoned'] ?? 0),
                'GTET' => (int)($abandoned['gtet_abandoned'] ?? 0),
                'GTRF' => (int)($abandoned['gtrf_abandoned'] ?? 0),
            ],
        ];

        $callCountsNormalised = [
            'GTIB' => (int)($callCounts['gtib_callcount'] ?? 0),
            'GTDC' => (int)($callCounts['gtdc_callcount'] ?? 0),
            'GTCS' => (int)($callCounts['gtcs_callcount'] ?? 0),
            'GTPY' => (int)($callCounts['gtpy_callcount'] ?? 0),
            'GTET' => (int)($callCounts['gtet_callcount'] ?? 0),
            'GTRF' => (int)($callCounts['gtrf_callcount'] ?? 0),
        ];

        $durationNormalised = [];
        foreach ($durations as $key => $value) {
            $durationNormalised[$key] = (int)$value;
        }

        $totalGtib = (float)($metrics['total_gtib'] ?? 0);
        $ahtSeconds = (float)($metrics['aht_seconds'] ?? 0);
        $conversionRatio = (float)($metrics['conversion_ratio'] ?? 0);
        $fcsRatio = (float)($metrics['fcs_ratio'] ?? 0);

        $metricsNormalised = [
            'total_pax' => (int)($metrics['total_pax'] ?? 0),
            'gdeals' => (int)($metrics['gdeals'] ?? 0),
            'fit' => (int)($metrics['fit'] ?? 0),
            'total_gtib' => (int)$totalGtib,
            'conversion_ratio' => round($conversionRatio, 4),
            'conversion_percent' => round($conversionRatio * 100, 2),
            'fcs_ratio' => round($fcsRatio, 4),
            'fcs_percent' => round($fcsRatio * 100, 2),
            'aht_seconds' => round($ahtSeconds, 2),
            'aht_formatted' => $this->formatSeconds($ahtSeconds),
        ];

        return [
            'date' => $date,
            'abandoned_calls' => $abandonedNormalised,
            'call_counts' => $callCountsNormalised,
            'duration_buckets' => $durationNormalised,
            'key_metrics' => $metricsNormalised,
        ];
    }

    /**
     * Convert seconds to h:m:s representation.
     */
    private function formatSeconds(float $seconds): string
    {
        $seconds = max(0, (int)round($seconds));
        $hours = (int)floor($seconds / 3600);
        $minutes = (int)floor(($seconds % 3600) / 60);
        $remaining = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remaining);
    }

    /**
     * Normalise incoming date parameter.
     */
    private function normaliseDate(string $value, string $field): string
    {
        $value = trim($value);
        if (strcasecmp($value, 'today') === 0) {
            return (new DateTime('today'))->format('Y-m-d');
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);
        if ($date === false) {
            throw new Exception(sprintf('%s must be a valid date (Y-m-d)', $field), 400);
        }

        return $date->format('Y-m-d');
    }
}

