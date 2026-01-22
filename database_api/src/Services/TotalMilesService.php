<?php
/**
 * Total miles service
 */

namespace App\Services;

use App\DAL\TotalMilesDAL;

class TotalMilesService
{
    private TotalMilesDAL $dal;

    public function __construct()
    {
        $this->dal = new TotalMilesDAL();
    }

    public function listTotals(): array
    {
        $rows = $this->dal->getTotals();

        $totals = array_map(function (array $row) {
            return [
                'tsr' => $row['tsr'],
                'agent_name' => $row['agent_name'],
                'total_miles' => (float)$row['total_miles'],
            ];
        }, $rows);

        return [
            'total_agents' => count($totals),
            'totals' => $totals,
        ];
    }

    public function listTransactions(array $filters = []): array
    {
        $tsr = isset($filters['tsr']) ? trim($filters['tsr']) : null;
        $agent = isset($filters['agent_name']) ? trim($filters['agent_name']) : null;

        $rows = $this->dal->getTransactions($tsr !== '' ? $tsr : null, $agent !== '' ? $agent : null);

        $transactions = array_map(function (array $row) {
            return [
                'id' => $row['id'] ?? null,
                'tsr' => $row['tsr'],
                'agent_name' => $row['agent_name'],
                'points' => (float)$row['points'],
                'transaction_type' => $row['transaction_type'] ?? null,
                'transaction_date' => $row['transaction_date'] ?? null,
                'reference' => $row['reference'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }, $rows);

        return [
            'filters' => [
                'tsr' => $tsr,
                'agent_name' => $agent,
            ],
            'total_transactions' => count($transactions),
            'transactions' => $transactions,
        ];
    }
}

