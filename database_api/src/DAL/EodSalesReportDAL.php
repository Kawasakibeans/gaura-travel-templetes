<?php
/**
 * Data access for EOD sales report.
 */

namespace App\DAL;

class EodSalesReportDAL extends BaseDAL
{
    /**
     * @return array<int,string>
     */
    public function getTeams(): array
    {
        $sql = "
            SELECT DISTINCT team_name
            FROM wpk4_backend_agent_nobel_data_eod_sale_booking
            ORDER BY team_name
        ";

        return array_map(static fn ($row) => (string)$row['team_name'], $this->query($sql));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getEodRows(string $date, ?string $team, int $limit): array
    {
        $sql = "
            SELECT booking.*, calls.*
            FROM wpk4_backend_agent_nobel_data_eod_sale_booking booking
            JOIN wpk4_backend_agent_nobel_data_eod_sale_call calls
              ON booking.tsr = calls.tsr
             AND booking.call_date = calls.call_date
            WHERE DATE(booking.call_date) = ?
        ";

        $params = [$date];

        if ($team) {
            $sql .= " AND booking.team_name = ?";
            $params[] = $team;
        }

        $sql .= "
            ORDER BY booking.team_name ASC, booking.agent_name ASC
        ";

        if ($limit > 0) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
        }

        return $this->query($sql, $params);
    }

    /**
     * @param array<int,string> $tsrs
     * @return array<string,array<string,mixed>>
     */
    public function getAgentInfo(array $tsrs): array
    {
        if (empty($tsrs)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($tsrs), '?'));
        $sql = "
            SELECT tsr, team_leader
            FROM wpk4_backend_agent_codes
            WHERE tsr IN ($placeholders)
        ";

        $rows = $this->query($sql, array_values($tsrs));

        $map = [];
        foreach ($rows as $row) {
            $map[(string)$row['tsr']] = $row;
        }

        return $map;
    }

    /**
     * @param array<int,string> $tsrs
     * @return array<string,int>
     */
    public function getBookingPaxByDate(array $tsrs, string $date): array
    {
        if (empty($tsrs)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($tsrs), '?'));
        $params = array_values($tsrs);
        $params[] = $date;

        $sql = "
            SELECT tsr, pax
            FROM wpk4_backend_agent_booking
            WHERE tsr IN ($placeholders)
              AND order_date = ?
        ";

        $rows = $this->query($sql, $params);

        $map = [];
        foreach ($rows as $row) {
            $map[(string)$row['tsr']] = (int)$row['pax'];
        }

        return $map;
    }
}

