<?php
namespace App\DAL;

class GTIBAgentProductivityDAL extends BaseDAL
{
    public function getKeyMetrics(string $date): ?array
    {
        $sql = "
            SELECT
                SUM(combined.pax) AS total_pax,
                SUM(combined.gdeals) AS gdeals,
                SUM(combined.fit) AS fit,
                SUM(combined.gtib_count) AS total_gtib,
                ROUND(IF(SUM(combined.gtib_count) > 0, SUM(combined.pax) / SUM(combined.gtib_count), 0), 4) AS conversion,
                ROUND(IF(SUM(combined.gtib_count) > 0, SUM(combined.new_sale_made_count) / SUM(combined.gtib_count), 0), 4) AS fcs,
                SEC_TO_TIME(ROUND(IF(SUM(combined.gtib_count) > 0, SUM(combined.rec_duration) / SUM(combined.gtib_count), 0))) AS aht
            FROM (
                SELECT a.agent_name, 0 AS pax, 0 AS fit, 0 AS pif, 0 AS gdeals, a.team_name, a.gtib_count,
                       a.pif_sale_made_count, a.new_sale_made_count, a.non_sale_made_count, a.rec_duration
                FROM wpk4_backend_agent_inbound_call a
                LEFT JOIN wpk4_backend_agent_codes c
                    ON a.tsr = c.tsr
                   AND c.status = 'active'
                WHERE a.call_date = :report_date_1

                UNION ALL

                SELECT a.agent_name, a.pax, a.fit, a.pif, a.gdeals, a.team_name, 0 AS gtib_count,
                       0 AS pif_sale_made_count, 0 AS new_sale_made_count, 0 AS non_sale_made_count, 0 AS rec_duration
                FROM wpk4_backend_agent_booking a
                LEFT JOIN wpk4_backend_agent_codes c
                    ON a.tsr = c.tsr
                   AND c.status = 'active'
                WHERE DATE(a.order_date) = :report_date_2
            ) AS combined
        ";

        $row = $this->queryOne($sql, [
            ':report_date_1' => $date,
            ':report_date_2' => $date
        ]);
        return $row ?: null;
    }

    public function getAgentRecords(string $date, ?string $team = null, ?string $manager = null): array
    {
        $sql = "
            SELECT *
            FROM wpk4_agent_productivity_report_June_2025
            WHERE Date = :report_date
        ";

        $params = [':report_date' => $date];

        if ($team) {
            $sql .= " AND Team_name = :team_name";
            $params[':team_name'] = $team;
        }

        if ($manager) {
            $sql .= " AND SM_name = :manager_name";
            $params[':manager_name'] = $manager;
        }

        // Order by Team_name only, since Agent_name column may not exist
        // The service will handle any additional sorting if needed
        $sql .= " ORDER BY Team_name ASC";

        return $this->query($sql, $params);
    }

    public function getTeams(): array
    {
        $sql = "
            SELECT DISTINCT Team_name
            FROM wpk4_agent_productivity_report_June_2025
            WHERE Team_name IS NOT NULL AND Team_name <> ''
            ORDER BY Team_name ASC
        ";

        return $this->query($sql);
    }

    public function getManagers(): array
    {
        $sql = "
            SELECT DISTINCT SM_name
            FROM wpk4_agent_productivity_report_June_2025
            WHERE SM_name IS NOT NULL AND SM_name <> ''
            ORDER BY SM_name ASC
        ";

        return $this->query($sql);
    }
}


