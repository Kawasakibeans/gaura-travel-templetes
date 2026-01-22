<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class AfterSalesCallMetricsDAL extends BaseDAL
{
    /**
     * Get after-sales call metrics with date range and optional agent filter
     */
    public function getAfterSalesCallMetrics(?string $startDate, ?string $endDate, ?string $agentName = null): array
    {
        $where = ["agent_name <> 'ABDN'"];
        $params = [];

        if ($startDate) {
            $where[] = "`date` >= :start_date";
            $params['start_date'] = $startDate;
        }

        if ($endDate) {
            $where[] = "`date` <= :end_date";
            $params['end_date'] = $endDate;
        }

        if ($agentName && trim($agentName) !== '') {
            $where[] = "agent_name = :agent_name";
            $params['agent_name'] = trim($agentName);
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT 
                DATE(`date`) AS call_date,
                agent_name,
                SUM(gtcs) AS total_gtcs_calls,
                SUM(gtpy) AS total_gtpy_calls,
                SUM(gtet) AS total_gtet_calls,
                SUM(gtdc) AS total_gtdc_calls,
                SUM(gtrf) AS total_gtrf_calls,
                SUM(gtcs + gtpy + gtet + gtdc + gtrf) AS total_inbound_calls,
                SUM(call_duration) AS total_call_duration,
                SUM(fit_ticketed) AS fit_ticketed,
                SUM(gdeal_ticketed) AS gdeal_ticketed,
                SUM(ticket_issued) AS ticket_issued
            FROM wpk4_agent_after_sale_productivity_report
            {$whereSql}
            GROUP BY DATE(`date`), agent_name
            ORDER BY call_date DESC, agent_name ASC
        ";

        return $this->query($sql, $params);
    }

    /**
     * Get agent data for a specific date
     */
    public function getAgentDataByDate(string $date, ?string $agentName = null): array
    {
        $where = [
            "DATE(`date`) = DATE(:date)",
            "agent_name <> 'ABDN'"
        ];
        $params = ['date' => $date];

        if ($agentName && trim($agentName) !== '') {
            $where[] = "agent_name = :agent_name";
            $params['agent_name'] = trim($agentName);
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT 
                agent_name,
                `date` AS call_date,
                gtcs,
                gtpy,
                gtet,
                gtdc,
                gtrf,
                (gtcs + gtpy + gtet + gtdc + gtrf) AS total_inbound_calls,
                call_duration,
                fit_ticketed,
                gdeal_ticketed,
                ticket_issued,
                dc_request,
                dc_case_success,
                dc_case_fail,
                dc_case_pending,
                fit_audit,
                gdeal_audit,
                ticket_audited
            FROM wpk4_agent_after_sale_productivity_report
            {$whereSql}
            ORDER BY agent_name ASC, `date` ASC
        ";

        return $this->query($sql, $params);
    }

    /**
     * Get distinct agents list
     */
    public function getDistinctAgents(): array
    {
        $sql = "
            SELECT DISTINCT agent_name
            FROM wpk4_agent_after_sale_productivity_report
            WHERE agent_name <> 'ABDN'
            ORDER BY agent_name ASC
        ";

        return $this->query($sql, []);
    }
}