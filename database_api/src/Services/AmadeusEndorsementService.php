<?php

namespace App\Services;

use App\DAL\AmadeusEndorsementDAL;
use Exception;

class AmadeusEndorsementService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new AmadeusEndorsementDAL();
    }

    /**
     * Check IP address access
     */
    public function checkIpAccess(string $ipAddress): array
    {
        if (empty($ipAddress)) {
            throw new Exception('IP address is required', 400);
        }

        try {
            $result = $this->dal->checkIpAddress($ipAddress);
            $hasAccess = ($result !== null && is_array($result));

            return [
                'has_access' => $hasAccess,
                'ip_address' => $ipAddress,
                'ip_details' => $hasAccess ? $result : null
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to check IP address: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get stock management records with filters
     */
    public function getStockManagementRecords(array $filters = []): array
    {
        $tripCode = $filters['tripcode'] ?? null;
        $dateRange = $filters['date'] ?? null;
        $endorsementId = $filters['end_id'] ?? null;
        $price = $filters['price'] ?? null;
        $pnr = $filters['pnr'] ?? null;
        $exactMatch = $filters['exactmatch'] ?? false;

        $startDate = null;
        $endDate = null;

        // Parse date range if provided
        if (!empty($dateRange)) {
            $dates = explode(' - ', $dateRange);
            if (count($dates) == 2) {
                $startDate = trim($dates[0]);
                $endDate = trim($dates[1]);
            }
        }

        // If no filters provided, return empty result (matching original behavior)
        if (empty($tripCode) && empty($startDate) && empty($endorsementId) && empty($pnr)) {
            return [
                'records' => [],
                'total_count' => 0
            ];
        }

        $records = $this->dal->getStockManagementRecords(
            $tripCode,
            $startDate,
            $endDate,
            $endorsementId,
            $price,
            $pnr,
            $exactMatch
        );

        return [
            'records' => $records,
            'total_count' => count($records),
            'filters' => $filters
        ];
    }

    /**
     * Get stock management records by endorsement IDs
     */
    public function getStockManagementByEndorsementIds(array $endorsementIds): array
    {
        if (empty($endorsementIds)) {
            throw new Exception('Endorsement IDs are required', 400);
        }

        $records = $this->dal->getStockManagementByEndorsementIds($endorsementIds);

        return [
            'records' => $records,
            'total_count' => count($records),
            'endorsement_ids' => $endorsementIds
        ];
    }

    /**
     * Update group name for multiple records
     */
    public function updateGroupName(array $autoIds, string $groupName, string $updatedBy): array
    {
        if (empty($autoIds)) {
            throw new Exception('Auto IDs are required', 400);
        }

        if (empty($groupName)) {
            throw new Exception('Group name is required', 400);
        }

        $updatedOn = date('Y-m-d H:i:s');
        $updated = [];
        $failed = [];

        foreach ($autoIds as $autoId) {
            try {
                if ($this->dal->updateGroupName((int)$autoId, $groupName)) {
                    $this->dal->insertHistoryOfUpdates(
                        (string)$autoId,
                        'group_name',
                        $groupName,
                        $updatedBy,
                        $updatedOn
                    );
                    $updated[] = $autoId;
                } else {
                    $failed[] = $autoId;
                }
            } catch (Exception $e) {
                $failed[] = $autoId;
            }
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
            'total_updated' => count($updated),
            'total_failed' => count($failed)
        ];
    }

    /**
     * Update endorsement fields (bulk update)
     */
    public function updateEndorsementFields(array $updates, string $updatedBy): array
    {
        if (empty($updates)) {
            throw new Exception('Updates are required', 400);
        }

        $updatedOn = date('Y-m-d H:i:s');
        $results = [
            'endorsement_id' => [],
            'group_name' => [],
            'endorsement_added_by' => [],
            'endorsement_confirmed_by' => []
        ];

        foreach ($updates as $update) {
            $autoId = $update['auto_id'] ?? null;
            if (!$autoId) {
                continue;
            }

            // Update endorsement ID
            if (isset($update['mh_endorsement'])) {
                try {
                    if ($this->dal->updateEndorsementId((int)$autoId, $update['mh_endorsement'])) {
                        $this->dal->insertHistoryOfUpdates(
                            (string)$autoId,
                            'mh_endorsement',
                            $update['mh_endorsement'],
                            'endorsement_update_' . $updatedBy,
                            $updatedOn
                        );
                        $results['endorsement_id'][] = $autoId;
                    }
                } catch (Exception $e) {
                    // Log error but continue
                }
            }

            // Update group name
            if (isset($update['group_name'])) {
                try {
                    if ($this->dal->updateGroupName((int)$autoId, $update['group_name'])) {
                        $this->dal->insertHistoryOfUpdates(
                            (string)$autoId,
                            'group_name',
                            $update['group_name'],
                            'endorsement_update_' . $updatedBy,
                            $updatedOn
                        );
                        $results['group_name'][] = $autoId;
                    }
                } catch (Exception $e) {
                    // Log error but continue
                }
            }

            // Update endorsement added by
            if (isset($update['endrosement_added_by'])) {
                try {
                    if ($this->dal->updateEndorsementAddedBy(
                        (int)$autoId,
                        $update['endrosement_added_by'],
                        $updatedOn
                    )) {
                        $this->dal->insertHistoryOfUpdates(
                            (string)$autoId,
                            'endrosement_added_by',
                            $update['endrosement_added_by'],
                            'endorsement_update_' . $updatedBy,
                            $updatedOn
                        );
                        $results['endorsement_added_by'][] = $autoId;
                    }
                } catch (Exception $e) {
                    // Log error but continue
                }
            }

            // Update endorsement confirmed by
            if (isset($update['endrosement_confirmed_by'])) {
                try {
                    if ($this->dal->updateEndorsementConfirmedBy(
                        (int)$autoId,
                        $update['endrosement_confirmed_by'],
                        $updatedOn
                    )) {
                        $this->dal->insertHistoryOfUpdates(
                            (string)$autoId,
                            'endrosement_confirmed_by',
                            $update['endrosement_confirmed_by'],
                            'endorsement_update_' . $updatedBy,
                            $updatedOn
                        );
                        $results['endorsement_confirmed_by'][] = $autoId;
                    }
                } catch (Exception $e) {
                    // Log error but continue
                }
            }
        }

        return $results;
    }
}

