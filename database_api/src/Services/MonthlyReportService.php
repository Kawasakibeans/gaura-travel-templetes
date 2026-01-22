<?php
/**
 * Monthly Report Service
 * Business logic for monthly report endpoints
 */

namespace App\Services;

use App\DAL\MonthlyReportDAL;

class MonthlyReportService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new MonthlyReportDAL();
    }

    /**
     * Update FCS inbound call data for a specific date
     */
    public function updateFCSInboundCall(array $params): array
    {
        $callDate = $params['call_date'] ?? date('Y-m-d', strtotime('-2 days'));
        
        // Get data from source table
        $rows = $this->dal->getInboundCallData($callDate);
        
        $inserted = 0;
        $updated = 0;
        
        foreach ($rows as $row) {
            $exists = $this->dal->inboundCallExists($row['call_date'], $row['tsr']);
            
            $data = [
                'call_date' => $row['call_date'],
                'tsr' => $row['tsr'],
                'team_name' => $row['team_name'],
                'gtib_count' => (int)$row['gtib_count'],
                'sale_made_count' => (int)$row['sale_made_count'],
                'new_sale_made_count' => (int)$row['new_sale_made_count'],
                'non_sale_made_count' => (int)$row['non_sale_made_count'],
                'abandoned' => (int)$row['abandoned'],
                'fcs' => (float)$row['FCS'],
                'fcs_old' => (float)$row['FCS_old'],
                'aht' => $row['aht'],
                'rec_duration' => (int)$row['rec_duration'],
                'agent_name' => $row['agent_name'],
                'shift_time' => $row['shift_time']
            ];
            
            if (!$exists) {
                $this->dal->insertInboundCall($data);
                $inserted++;
            } else {
                // Check if update is needed by comparing with existing record
                $existingRow = $this->dal->getExistingInboundCall($row['call_date'], $row['tsr']);
                
                if ($existingRow) {
                    $shouldUpdate = false;
                    $fieldsToCheck = ['gtib_count', 'new_sale_made_count', 'fcs', 'aht', 'rec_duration', 'shift_time'];
                    
                    foreach ($fieldsToCheck as $field) {
                        $existingVal = trim((string)($existingRow[$field] ?? '0'));
                        $newVal = trim((string)($data[$field] ?? '0'));
                        
                        if ($existingVal !== $newVal) {
                            $shouldUpdate = true;
                            break;
                        }
                    }
                    
                    if ($shouldUpdate) {
                        $this->dal->updateInboundCall($row['call_date'], $row['tsr'], $data);
                        $updated++;
                    }
                }
            }
        }
        
        return [
            'success' => true,
            'call_date' => $callDate,
            'inserted' => $inserted,
            'updated' => $updated,
            'total_records' => count($rows),
            'message' => "Processed $inserted inserts and $updated updates"
        ];
    }
}

