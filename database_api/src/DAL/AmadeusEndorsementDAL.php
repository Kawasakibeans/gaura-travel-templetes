<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class AmadeusEndorsementDAL extends BaseDAL
{
    /**
     * Check IP address access
     */
    public function checkIpAddress(string $ipAddress): ?array
    {
        $sql = "SELECT * FROM wpk4_backend_ip_address_checkup WHERE ip_address = ? LIMIT 1";
        $result = $this->queryOne($sql, [$ipAddress]);
        return ($result === false) ? null : $result;
    }

    /**
     * Get stock management sheet records with filters
     */
    public function getStockManagementRecords(
        ?string $tripCode = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $endorsementId = null,
        ?string $price = null,
        ?string $pnr = null,
        bool $exactMatch = false
    ): array
    {
        $whereParts = [];
        $params = [];

        // Trip code filter
        if (!empty($tripCode)) {
            $whereParts[] = "trip_id LIKE ?";
            $params[] = "%{$tripCode}%";
        } else {
            $whereParts[] = "trip_id != 'TEST_DMP_ID'";
        }

        // Date range filter
        if (!empty($startDate) && !empty($endDate)) {
            $whereParts[] = "dep_date >= ? AND dep_date <= ?";
            $params[] = $startDate;
            $params[] = $endDate;
        } else {
            $whereParts[] = "trip_id != 'TEST_DMP_ID'";
        }

        // Endorsement ID filter
        if (!empty($endorsementId)) {
            $whereParts[] = "mh_endorsement = ?";
            $params[] = $endorsementId;
        } else {
            $whereParts[] = "trip_id != 'TEST_DMP_ID'";
        }

        // Price filter
        if (!empty($price)) {
            $whereParts[] = "aud_fare = ?";
            $params[] = $price;
        } else {
            $whereParts[] = "trip_id != 'TEST_DMP_ID'";
        }

        // PNR filter
        if (!empty($pnr)) {
            if ($exactMatch) {
                $whereParts[] = "pnr LIKE ?";
                $params[] = $pnr;
            } else {
                $whereParts[] = "pnr LIKE ?";
                $params[] = "%{$pnr}%";
            }
        } else {
            $whereParts[] = "trip_id != 'TEST_DMP_ID'";
        }

        $whereSQL = implode(' AND ', $whereParts);

        $sql = "
            SELECT * 
            FROM wpk4_backend_stock_management_sheet 
            WHERE {$whereSQL}
            ORDER BY dep_date ASC
        ";

        return $this->query($sql, $params);
    }

    /**
     * Get stock management records by endorsement IDs
     */
    public function getStockManagementByEndorsementIds(array $endorsementIds): array
    {
        if (empty($endorsementIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($endorsementIds), '?'));
        
        $sql = "
            SELECT * 
            FROM wpk4_backend_stock_management_sheet 
            WHERE mh_endorsement IN ({$placeholders})
            ORDER BY group_name ASC
        ";

        return $this->query($sql, $endorsementIds);
    }

    /**
     * Update group name for stock management record
     */
    public function updateGroupName(int $autoId, string $groupName): bool
    {
        $sql = "
            UPDATE wpk4_backend_stock_management_sheet 
            SET group_name = ? 
            WHERE auto_id = ?
        ";

        return $this->execute($sql, [$groupName, $autoId]);
    }

    /**
     * Update endorsement ID for stock management record
     */
    public function updateEndorsementId(int $autoId, string $endorsementId): bool
    {
        $sql = "
            UPDATE wpk4_backend_stock_management_sheet 
            SET mh_endorsement = ? 
            WHERE auto_id = ?
        ";

        return $this->execute($sql, [$endorsementId, $autoId]);
    }

    /**
     * Update endorsement added by
     */
    public function updateEndorsementAddedBy(int $autoId, string $addedBy, string $addedOn): bool
    {
        $sql = "
            UPDATE wpk4_backend_stock_management_sheet 
            SET endrosement_added_by = ?, 
                endrosement_added_on = ? 
            WHERE auto_id = ? 
                AND endrosement_added_by IS NULL
        ";

        return $this->execute($sql, [$addedBy, $addedOn, $autoId]);
    }

    /**
     * Update endorsement confirmed by
     */
    public function updateEndorsementConfirmedBy(int $autoId, string $confirmedBy, string $confirmedOn): bool
    {
        $sql = "
            UPDATE wpk4_backend_stock_management_sheet 
            SET endrosement_confirmed_by = ?, 
                endrosement_confirmed_on = ? 
            WHERE auto_id = ? 
                AND endrosement_confirmed_by IS NULL
        ";

        return $this->execute($sql, [$confirmedBy, $confirmedOn, $autoId]);
    }

    /**
     * Insert history of updates
     */
    public function insertHistoryOfUpdates(string $typeId, string $metaKey, string $metaValue, string $updatedBy, string $updatedOn): bool
    {
        $sql = "
            INSERT INTO wpk4_backend_history_of_updates 
            (type_id, meta_key, meta_value, updated_by, updated_on) 
            VALUES (?, ?, ?, ?, ?)
        ";

        return $this->execute($sql, [$typeId, $metaKey, $metaValue, $updatedBy, $updatedOn]);
    }
}

