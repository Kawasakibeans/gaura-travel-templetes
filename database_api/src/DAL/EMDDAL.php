<?php
/**
 * EMD Data Access Layer (DAL)
 * 
 * Handles all database operations for EMD reconciliation
 */

namespace App\DAL;

class EMDDAL {
    private $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Insert EMD record into non-IATA table
     */
    public function insertEMD($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO wpk4_backend_travel_booking_ticket_number_non_iata
            (`group`, vendor, iata_no, document, a_l, pax_ticket_name, plated_carrier_code, plated_carrier, dom, published_remit,
             reporting_system_code, pnr, document_type, issue_date, departure_date, currency, tour_code, conj_tkt_indicator,
             emd_remarks, cash_fare, credit_fare, gross_fare, comm, fare, cash_tax, credit_tax, yq_tax, yr_tax, tax, transaction_amount, net_due,
             added_on, added_by, fee)
            VALUES
            (:group, :vendor, :iata_no, :document, :a_l, :pax_ticket_name, :plated_carrier_code, :plated_carrier, :dom, :published_remit,
             :reporting_system_code, :pnr, :document_type, :issue_date, :departure_date, :currency, :tour_code, :conj_tkt_indicator,
             :emd_remarks, :cash_fare, :credit_fare, :gross_fare, :comm, :fare, :cash_tax, :credit_tax, :yq_tax, :yr_tax, :tax, :transaction_amount, :net_due,
             NOW(), :added_by, :fee)
        ");
        
        return $stmt->execute($data);
    }

    /**
     * Check if EMD document exists
     */
    public function emdExists($document) {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM wpk4_backend_travel_booking_ticket_number_non_iata 
            WHERE document = :d LIMIT 1
        ");
        $stmt->execute([':d' => $document]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Get flights for a travel date
     */
    public function getFlightsByTravelDate($travelDate) {
        $sql = "
            SELECT DISTINCT
              CASE 
                WHEN b.trip_code LIKE 'QF067%' THEN 'QF067'
                WHEN b.trip_code LIKE 'QF068%' THEN 'QF068'
                WHEN b.trip_code LIKE 'QF069%' THEN 'QF069'
                WHEN b.trip_code LIKE 'QF070%' THEN 'QF070'
                ELSE MID(b.trip_code, 9, 5) 
              END AS int_flight
            FROM wpk4_backend_travel_bookings b
            WHERE b.order_type <> 'gds'
              AND b.payment_status = 'paid'
              AND MID(b.trip_code, 9, 2) <> 'SQ'
              AND MID(b.trip_code, 9, 2) <> 'MH'
              AND LENGTH(b.trip_code) > 10
              AND DATE(b.travel_date) = :tdate
            ORDER BY int_flight ASC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':tdate' => $travelDate]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    /**
     * Get PAX list by travel date and flight
     */
    public function getPaxByTravelDateAndFlight($travelDate, $intFlight) {
        $sql = "
          SELECT
            p.auto_id AS pax_id,
            (p.trip_price_individual
              - CASE WHEN IFNULL(b.total_pax,0) = 0 THEN 0
                     ELSE IFNULL(b.discount_given,0) / NULLIF(b.total_pax,0)
                END
            ) AS order_amnt,
            p.product_id,
            b.order_id,
            b.payment_status,
            b.trip_code,
            b.travel_date,
            CASE 
              WHEN b.trip_code LIKE 'QF067%' THEN 'QF067'
              WHEN b.trip_code LIKE 'QF068%' THEN 'QF068'
              WHEN b.trip_code LIKE 'QF069%' THEN 'QF069'
              WHEN b.trip_code LIKE 'QF070%' THEN 'QF070'
              ELSE MID(b.trip_code, 9, 5) 
            END AS int_flight,
            p.fname,
            p.lname,
            COALESCE(t.pnr, p.pnr) AS pnr,
            COALESCE(p.ticket_number, t.document) AS tkt_pax,
            0 AS net_due,
            b.source,
            b.order_type
          FROM wpk4_backend_travel_booking_pax p
          LEFT JOIN wpk4_backend_travel_bookings b
                 ON p.order_id = b.order_id
                AND p.co_order_id = b.co_order_id
                AND p.product_id = b.product_id
          LEFT JOIN wpk4_backend_travel_booking_ticket_number t
                 ON p.auto_id = t.pax_id
          WHERE DATE(b.travel_date) = :tdate
            AND (
                CASE 
                  WHEN b.trip_code LIKE 'QF067%' THEN 'QF067'
                  WHEN b.trip_code LIKE 'QF068%' THEN 'QF068'
                  WHEN b.trip_code LIKE 'QF069%' THEN 'QF069'
                  WHEN b.trip_code LIKE 'QF070%' THEN 'QF070'
                  ELSE MID(b.trip_code, 9, 5) 
                END
            ) = :int_flight
            AND b.payment_status = 'paid'
            AND b.order_type <> 'gds'
            AND MID(b.trip_code, 9, 2) NOT IN ('SQ','MH')
          ORDER BY b.order_id, p.auto_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':tdate', $travelDate);
        $stmt->bindValue(':int_flight', $intFlight);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get recent EMDs
     */
    public function getRecentEMDs($limit = 30) {
        $stmt = $this->pdo->prepare("
            SELECT document, vendor, COALESCE(net_due,0) AS net_due, added_on
            FROM wpk4_backend_travel_booking_ticket_number_non_iata
            WHERE document IS NOT NULL AND document <> ''
            ORDER BY added_on DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Search EMDs with filters
     */
    public function searchEMDs($filters = []) {
        $where = [];
        $params = [];

        if (!empty($filters['document'])) {
            $where[] = "document LIKE :document";
            $params[':document'] = '%' . $filters['document'] . '%';
        }

        if (!empty($filters['vendor'])) {
            $where[] = "vendor LIKE :vendor";
            $params[':vendor'] = '%' . $filters['vendor'] . '%';
        }

        if (!empty($filters['pnr'])) {
            $where[] = "pnr LIKE :pnr";
            $params[':pnr'] = '%' . $filters['pnr'] . '%';
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(added_on) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(added_on) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['min_amount'])) {
            $where[] = "net_due >= :min_amount";
            $params[':min_amount'] = $filters['min_amount'];
        }

        if (!empty($filters['max_amount'])) {
            $where[] = "net_due <= :max_amount";
            $params[':max_amount'] = $filters['max_amount'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 50;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;
        $limit = max(1, min(100, $limit)); // Clamp between 1 and 100

        $sql = "
            SELECT 
                document, 
                vendor, 
                pnr,
                pax_ticket_name,
                document_type,
                COALESCE(net_due,0) AS net_due,
                COALESCE(transaction_amount,0) AS transaction_amount,
                added_on,
                added_by
            FROM wpk4_backend_travel_booking_ticket_number_non_iata
            $whereClause
            ORDER BY added_on DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get count of EMDs matching filters
     */
    public function countEMDs($filters = []) {
        $where = [];
        $params = [];

        if (!empty($filters['document'])) {
            $where[] = "document LIKE :document";
            $params[':document'] = '%' . $filters['document'] . '%';
        }

        if (!empty($filters['vendor'])) {
            $where[] = "vendor LIKE :vendor";
            $params[':vendor'] = '%' . $filters['vendor'] . '%';
        }

        if (!empty($filters['pnr'])) {
            $where[] = "pnr LIKE :pnr";
            $params[':pnr'] = '%' . $filters['pnr'] . '%';
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(added_on) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(added_on) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['min_amount'])) {
            $where[] = "net_due >= :min_amount";
            $params[':min_amount'] = $filters['min_amount'];
        }

        if (!empty($filters['max_amount'])) {
            $where[] = "net_due <= :max_amount";
            $params[':max_amount'] = $filters['max_amount'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT COUNT(*) as total
            FROM wpk4_backend_travel_booking_ticket_number_non_iata
            $whereClause
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Get PAX order amount
     */
    public function getPaxOrderAmount($paxId) {
        $stmt = $this->pdo->prepare("
            SELECT
                p.auto_id AS pax_id,
                (p.trip_price_individual
                  - CASE WHEN IFNULL(b.total_pax,0) = 0 THEN 0
                         ELSE IFNULL(b.discount_given,0) / NULLIF(b.total_pax,0)
                    END
                ) AS order_amnt
            FROM wpk4_backend_travel_booking_pax p
            LEFT JOIN wpk4_backend_travel_bookings b
                   ON p.order_id = b.order_id
                  AND p.co_order_id = b.co_order_id
                  AND p.product_id = b.product_id
            WHERE p.auto_id = :pax_id
            LIMIT 1
        ");
        $stmt->execute([':pax_id' => $paxId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Insert reconciliation record
     */
    public function insertReconciliation($paxId, $remark, $addedBy = null) {
        $reconTable = 'wpk4_backend_ticket_reconciliation';
        $variant = $this->getReconciliationVariant();
        
        if (!$variant) {
            return false;
        }

        if ($variant === 1) {
            $stmt = $this->pdo->prepare("
                INSERT INTO $reconTable (pax_id, remark, added_on, added_by)
                VALUES (:pax_id, :remark, NOW(), :by)
            ");
            return $stmt->execute([
                ':pax_id' => $paxId,
                ':remark' => $remark,
                ':by' => $addedBy ?? 'api'
            ]);
        } elseif ($variant === 2) {
            $stmt = $this->pdo->prepare("
                INSERT INTO $reconTable (pax_id, remark, created_at, created_by)
                VALUES (:pax_id, :remark, NOW(), :by)
            ");
            return $stmt->execute([
                ':pax_id' => $paxId,
                ':remark' => $remark,
                ':by' => $addedBy ?? 'api'
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO $reconTable (pax_id, remark)
                VALUES (:pax_id, :remark)
            ");
            return $stmt->execute([
                ':pax_id' => $paxId,
                ':remark' => $remark
            ]);
        }
    }

    /**
     * Update ticket order amount
     */
    public function updateTicketOrderAmount($paxId, $orderAmount) {
        if (!$this->tableHasColumns('wpk4_backend_ticket', ['pax_id', 'order_amnt'])) {
            return false;
        }
        
        $stmt = $this->pdo->prepare("
            UPDATE wpk4_backend_ticket 
            SET order_amnt = :amt 
            WHERE pax_id = :pax_id
        ");
        return $stmt->execute([
            ':amt' => $orderAmount,
            ':pax_id' => $paxId
        ]);
    }

    /**
     * Get reconciliation table variant
     */
    private function getReconciliationVariant() {
        $reconTable = 'wpk4_backend_ticket_reconciliation';
        
        if ($this->tableHasColumns($reconTable, ['pax_id', 'remark', 'added_on', 'added_by'])) {
            return 1;
        } elseif ($this->tableHasColumns($reconTable, ['pax_id', 'remark', 'created_at', 'created_by'])) {
            return 2;
        } elseif ($this->tableHasColumns($reconTable, ['pax_id', 'remark'])) {
            return 3;
        }
        
        return null;
    }

    /**
     * Check if table has specified columns
     */
    public function tableHasColumns($table, $cols) {
        $in = implode(',', array_fill(0, count($cols), '?'));
        $sql = "
            SELECT COUNT(*) AS cnt
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME IN ($in)
        ";
        $stmt = $this->pdo->prepare($sql);
        $args = array_merge([$table], $cols);
        $stmt->execute($args);
        $want = count($cols);
        $have = (int)$stmt->fetchColumn();
        return $have === $want;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }
}

