<?php

namespace App\Services;

use App\DAL\NobelPostgresQuestDAL;

class NobelPostgresQuestService
{
    private $dal;

    public function __construct(NobelPostgresQuestDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Capture hmny_qa_eval_quest data from PostgreSQL
     * Line: 50-141 (in template)
     */
    public function captureQaEvalQuest($targetDate = null)
    {
        if ($targetDate === null) {
            $targetDate = date('Y-m-d', strtotime('-1 day'));
        }
        
        $captureFromDate = date('d/m/Y', strtotime($targetDate));
        $captureFromDateYmd = $targetDate;
        
        $existingRowids = $this->dal->getExistingRowids('wpk4_backend_agent_nobel_data_qa_evaluation_quest', 'qa_date', $captureFromDateYmd);
        $pgData = $this->dal->getHmnyQaEvalQuestData($captureFromDate);
        
        $inserted = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($pgData as $row) {
            $rowid = trim($row['rowid'] ?? '');
            
            if (!in_array($rowid, $existingRowids)) {
                try {
                    $this->dal->insertQaEvalQuestData($row);
                    $inserted++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'rowid' => $rowid,
                        'error' => $e->getMessage()
                    ];
                }
            } else {
                $skipped++;
            }
        }
        
        return [
            'type' => 'qa_eval_quest',
            'target_date' => $targetDate,
            'total_records' => count($pgData),
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => count($errors),
            'error_details' => $errors
        ];
    }

    /**
     * Capture hmny_qa_eval_notes data from PostgreSQL
     * Line: 145-215 (in template)
     */
    public function captureQaEvalNotes($targetDate = null)
    {
        if ($targetDate === null) {
            $targetDate = date('Y-m-d', strtotime('-1 day'));
        }
        
        $captureFromDate = date('d/m/Y', strtotime($targetDate));
        $captureFromDateYmd = $targetDate;
        
        $existingRowids = $this->dal->getExistingRowids('wpk4_backend_agent_nobel_data_qa_evaluation_notes', 'qa_date', $captureFromDateYmd);
        $pgData = $this->dal->getHmnyQaEvalNotesData($captureFromDate);
        
        $inserted = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($pgData as $row) {
            $rowid = trim($row['rowid'] ?? '');
            
            if (!in_array($rowid, $existingRowids)) {
                try {
                    $this->dal->insertQaEvalNotesData($row);
                    $inserted++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'rowid' => $rowid,
                        'error' => $e->getMessage()
                    ];
                }
            } else {
                $skipped++;
            }
        }
        
        return [
            'type' => 'qa_eval_notes',
            'target_date' => $targetDate,
            'total_records' => count($pgData),
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => count($errors),
            'error_details' => $errors
        ];
    }

    /**
     * Capture hmny_qa_eval data from PostgreSQL
     * Line: 219-287 (in template)
     */
    public function captureQaEval($targetDate = null)
    {
        if ($targetDate === null) {
            $targetDate = date('Y-m-d', strtotime('-1 day'));
        }
        
        $captureFromDateYmd = $targetDate;
        
        $pgData = $this->dal->getHmnyQaEvalData($captureFromDateYmd);
        
        $inserted = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($pgData as $row) {
            $rowid = trim($row['rowid'] ?? '');
            
            // Check if rowid already exists
            if (!$this->dal->checkRowidExists('wpk4_backend_agent_nobel_data_qa_evaluation', $rowid)) {
                try {
                    $this->dal->insertQaEvalData($row);
                    $inserted++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'rowid' => $rowid,
                        'error' => $e->getMessage()
                    ];
                }
            } else {
                $skipped++;
            }
        }
        
        return [
            'type' => 'qa_eval',
            'target_date' => $targetDate,
            'total_records' => count($pgData),
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => count($errors),
            'error_details' => $errors
        ];
    }
}

