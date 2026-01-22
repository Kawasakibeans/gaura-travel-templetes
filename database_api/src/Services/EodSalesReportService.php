<?php
/**
 * Service for EOD sales reporting.
 */

namespace App\Services;

use App\DAL\EodSalesReportDAL;
use DateInterval;
use DateTime;
use Exception;

class EodSalesReportService
{
    private EodSalesReportDAL $dal;

    public function __construct()
    {
        $this->dal = new EodSalesReportDAL();
    }

    public function listTeams(): array
    {
        return [
            'teams' => $this->dal->getTeams(),
        ];
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function getReport(array $filters): array
    {
        $date = isset($filters['call_date']) ? $this->parseDate($filters['call_date']) : null;
        if (!$date) {
            $date = (new DateTime('today'))->sub(new DateInterval('P1D'));
        }

        $team = isset($filters['team']) ? trim((string)$filters['team']) : null;
        if ($team === '') {
            $team = null;
        }

        $limit = isset($filters['limit']) ? max(0, (int)$filters['limit']) : 150;

        $rows = $this->dal->getEodRows($date->format('Y-m-d'), $team, $limit);

        $tsrs = [];
        foreach ($rows as $row) {
            if (!empty($row['tsr'])) {
                $tsrs[$row['tsr']] = $row['tsr'];
            }
        }

        $agentInfo = $this->dal->getAgentInfo(array_values($tsrs));
        $bookingPax = $this->dal->getBookingPaxByDate(array_values($tsrs), $date->format('Y-m-d'));

        $reportRows = [];
        foreach ($rows as $row) {
            $tsr = (string)($row['tsr'] ?? '');
            $pax = $bookingPax[$tsr] ?? 0;
            $gtib = (float)($row['GTIB'] ?? 0);

            $paxGtib = ($gtib > 0 && $pax > 0)
                ? round(($pax / $gtib) * 100, 2)
                : 0.0;

            $reportRows[] = [
                'call_date' => $row['call_date'],
                'tsr' => $tsr,
                'agent_name' => $row['agent_name'],
                'team_name' => $row['team_name'],
                'team_leader' => $agentInfo[$tsr]['team_leader'] ?? null,
                'noble_login_time' => $this->secondsToTime($row['noble_login_time'] ?? null),
                'total_call_time' => $this->formatDuration($row['total_call_time'] ?? null),
                'total_idle_time' => $this->formatDuration($row['total_idle_time'] ?? null),
                'total_pause_time' => $this->formatDuration($row['total_pause_time'] ?? null),
                'total_gtib_taken' => (int)($row['total_gtib_taken'] ?? 0),
                'gtib_aht' => $this->formatNumber($row['gtib_AHT'] ?? null, 2),
                'lt45' => (int)($row['lt45'] ?? 0),
                'between_45_60' => (int)($row['bt_45_60'] ?? 0),
                'gt60' => (int)($row['gt60'] ?? 0),
                'oth_calls' => (int)($row['oth_call_taken'] ?? 0),
                'oth_aht' => $this->formatDuration($row['ob_AHT'] ?? null, 8),
                'ob_calls' => (int)($row['ob_call_taken'] ?? 0),
                'total_calls' => (int)(($row['total_gtib_taken'] ?? 0) + ($row['oth_call_taken'] ?? 0)),
                'sales_made' => (int)($row['GTIB'] ?? 0),
                'conversion_percent' => $paxGtib,
                'fcs_percent' => $this->formatNumber($row['FCS'] ?? null, 2),
            ];
        }

        return [
            'call_date' => $date->format('Y-m-d'),
            'team' => $team,
            'limit' => $limit,
            'rows' => $reportRows,
        ];
    }

    private function secondsToTime($seconds): ?string
    {
        $seconds = (int)$seconds;
        if ($seconds <= 0) {
            return null;
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    private function formatDuration($value, int $length = 8): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = str_pad((string)$value, 6, '0', STR_PAD_LEFT);
        $chunks = str_split($string, 2);
        $formatted = implode(':', $chunks);

        return substr($formatted, 0, $length);
    }

    private function formatNumber($value, int $precision): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float)$value, $precision);
    }

    private function parseDate(mixed $value): ?DateTime
    {
        if (!is_string($value)) {
            return null;
        }

        $dt = DateTime::createFromFormat('Y-m-d', $value);
        if ($dt instanceof DateTime) {
            return $dt;
        }

        return null;
    }
}

