<?php
/**
 * Airline Deposit Loss DAL
 * Handles data access for airline deposit loss analytics
 */

namespace App\DAL;

use Exception;

class AirlineDepositLossDAL extends BaseDAL
{
    private const TABLE = 'wpk4_backend_airline_deposit_loss';

    /**
     * Get distinct airlines for filters
     */
    public function getDistinctAirlines(): array
    {
        $sql = "SELECT DISTINCT airline 
                FROM " . self::TABLE . " 
                WHERE airline IS NOT NULL AND airline <> ''
                ORDER BY airline";

        $rows = $this->query($sql);
        return array_map(static fn($row) => $row['airline'], $rows);
    }

    /**
     * Get distinct periods for filters
     */
    public function getDistinctPeriods(): array
    {
        $sql = "SELECT DISTINCT period 
                FROM " . self::TABLE . " 
                WHERE period IS NOT NULL AND period <> ''
                ORDER BY period";

        $rows = $this->query($sql);
        return array_map(static fn($row) => $row['period'], $rows);
    }

    /**
     * Fetch airline deposit loss records with optional filters and pagination
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDepositLossRecords(
        ?string $airline,
        ?string $period,
        ?int $limit,
        ?int $offset
    ): array {
        $params = [];
        $sql = "SELECT 
                    airline,
                    period,
                    emd_no,
                    pnr,
                    trip_code,
                    deposit_paid,
                    deposit_paid_from,
                    deposit_date,
                    final_payment,
                    final_payment_paid_from,
                    final_payment_date,
                    flight_date,
                    ticket_used_amount,
                    deposit_refund,
                    refunded_date,
                    flown_forfeited_drop_cancelled_amt,
                    flown_forfeited_drop_cancelled
                FROM " . self::TABLE . "
                WHERE 1=1";

        if (!empty($airline)) {
            $sql .= " AND airline = :airline";
            $params[':airline'] = $airline;
        }

        if (!empty($period)) {
            $sql .= " AND period = :period";
            $params[':period'] = $period;
        }

        $sql .= " ORDER BY period DESC, airline ASC, deposit_date DESC";

        if ($limit !== null) {
            $limit = max(1, (int)$limit);
            $offset = max(0, (int)($offset ?? 0));
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }

        return $this->query($sql, $params);
    }

    /**
     * Fetch totals for numeric columns based on filters
     */
    public function getDepositLossTotals(?string $airline, ?string $period): array
    {
        $params = [];
        $sql = "SELECT 
                    SUM(COALESCE(deposit_paid, 0)) AS total_deposit_paid,
                    SUM(COALESCE(final_payment, 0)) AS total_final_payment,
                    SUM(COALESCE(ticket_used_amount, 0)) AS total_ticket_used_amount,
                    SUM(COALESCE(deposit_refund, 0)) AS total_deposit_refund,
                    SUM(COALESCE(flown_forfeited_drop_cancelled_amt, 0)) AS total_flown_forfeited_amt
                FROM " . self::TABLE . "
                WHERE 1=1";

        if (!empty($airline)) {
            $sql .= " AND airline = :airline";
            $params[':airline'] = $airline;
        }

        if (!empty($period)) {
            $sql .= " AND period = :period";
            $params[':period'] = $period;
        }

        $totals = $this->queryOne($sql, $params);

        return [
            'total_deposit_paid' => (float)($totals['total_deposit_paid'] ?? 0),
            'total_final_payment' => (float)($totals['total_final_payment'] ?? 0),
            'total_ticket_used_amount' => (float)($totals['total_ticket_used_amount'] ?? 0),
            'total_deposit_refund' => (float)($totals['total_deposit_refund'] ?? 0),
            'total_flown_forfeited_amt' => (float)($totals['total_flown_forfeited_amt'] ?? 0),
        ];
    }

    /**
     * Count total records for pagination metadata
     */
    public function getTotalCount(?string $airline, ?string $period): int
    {
        $params = [];
        $sql = "SELECT COUNT(*) AS total FROM " . self::TABLE . " WHERE 1=1";

        if (!empty($airline)) {
            $sql .= " AND airline = :airline";
            $params[':airline'] = $airline;
        }

        if (!empty($period)) {
            $sql .= " AND period = :period";
            $params[':period'] = $period;
        }

        $row = $this->queryOne($sql, $params);
        return (int)($row['total'] ?? 0);
    }
}

