<?php
/**
 * Data access for ticketed review metrics.
 */

namespace App\DAL;

class TicketedReviewDAL extends BaseDAL
{
    /**
     * @return array<int,string>
     */
    public function getAgents(): array
    {
        $sql = "
            SELECT DISTINCT agent_name
            FROM wpk4_backend_agent_codes
            WHERE location = 'BOM'
              AND status = 'active'
            ORDER BY agent_name ASC
        ";

        return array_map(static fn ($row) => (string)$row['agent_name'], $this->query($sql));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getAggregatedRange(string $start, string $end, ?string $agent = null): array
    {
        $sql = "
            SELECT
                date,
                SUM(fit_ticketed) AS fit_ticketed,
                SUM(gdeal_ticketed) AS gdeal_ticketed,
                SUM(ticket_issued) AS ticket_issued,
                0 AS ctg,
                0 AS gkt_iata,
                0 AS ifn_iata,
                0 AS gilpin,
                0 AS CCUVS32NQ,
                0 AS MELA821CV,
                0 AS I5FC,
                0 AS MELA828FN,
                0 AS CCUVS32MV
            FROM wpk4_agent_after_sale_productivity_report
            WHERE date BETWEEN ? AND ?
              AND agent_name <> 'ABDN'
        ";

        $params = [$start, $end];
        if ($agent) {
            $sql .= " AND agent_name = ?";
            $params[] = $agent;
        }

        $sql .= ' GROUP BY date ORDER BY date ASC';

        return $this->query($sql, $params);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getAgentDetailsForDate(string $date, ?string $agent = null): array
    {
        $sql = "
            SELECT agent_name,
                fit_ticketed,
                gdeal_ticketed,
                ticket_issued,
                0 AS ctg,
                0 AS gkt_iata,
                0 AS ifn_iata,
                0 AS gilpin,
                0 AS CCUVS32NQ,
                0 AS MELA821CV,
                0 AS I5FC,
                0 AS MELA828FN,
                0 AS CCUVS32MV
            FROM wpk4_agent_after_sale_productivity_report
            WHERE date = ?
              AND agent_name <> 'ABDN'
              AND ticket_issued > 0
        ";

        $params = [$date];
        if ($agent) {
            $sql .= ' AND agent_name = ?';
            $params[] = $agent;
        }

        $sql .= ' ORDER BY agent_name ASC';

        return $this->query($sql, $params);
    }
}

