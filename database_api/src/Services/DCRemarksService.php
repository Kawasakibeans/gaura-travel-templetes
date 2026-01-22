<?php
namespace App\Services;

use App\DAL\DCRemarksDAL;
use Exception;

class DCRemarksService
{
    private DCRemarksDAL $dal;

    public function __construct()
    {
        $this->dal = new DCRemarksDAL();
    }

    /**
     * Retrieve DC remarks using required filters.
     *
     * @param array $filters
     * @return array
     * @throws Exception
     */
    public function getRemarks(array $filters): array
    {
        $caseId = isset($filters['case_id']) ? trim((string)$filters['case_id']) : '';
        $reservationRef = isset($filters['reservation_ref']) ? trim((string)$filters['reservation_ref']) : '';

        if ($caseId === '' || $reservationRef === '') {
            throw new Exception('case_id and reservation_ref are required', 400);
        }

        $remarks = $this->dal->getRemarks($caseId, $reservationRef);

        return [
            'case_id' => $caseId,
            'reservation_ref' => $reservationRef,
            'remarks' => $remarks,
            'count' => count($remarks),
        ];
    }
    
    /**
     * Create a new DC remark.
     *
     * @param array $payload
     * @return array
     * @throws Exception
     */
    public function createRemark(array $payload): array
    {
        // Validate required fields
        $caseId = isset($payload['case_id']) ? trim((string)$payload['case_id']) : '';
        $reservationRef = isset($payload['reservation_ref']) ? trim((string)$payload['reservation_ref']) : '';
        $remark = isset($payload['remark']) ? trim((string)$payload['remark']) : '';
        $createdBy = isset($payload['created_by']) ? trim((string)$payload['created_by']) : 'system';
        $remarkType = isset($payload['remark_type']) ? trim((string)$payload['remark_type']) : 'info';
        
        if ($caseId === '') {
            throw new Exception('case_id is required', 400);
        }
        
        if ($reservationRef === '') {
            throw new Exception('reservation_ref is required', 400);
        }
        
        if ($remark === '') {
            throw new Exception('remark is required', 400);
        }
        
        // Optional fields
        $requestType = isset($payload['request_type']) ? trim((string)$payload['request_type']) : null;
        $failedReason = isset($payload['failed_reason']) ? trim((string)$payload['failed_reason']) : null;
        $createdOn = isset($payload['created_on']) ? trim((string)$payload['created_on']) : date('Y-m-d H:i:s');
        
        // Call DAL to create remark
        $result = $this->dal->createRemark(
            $caseId,
            $reservationRef,
            $remark,
            $requestType,
            $failedReason,
            $createdBy,
            $remarkType,
            $createdOn
        );
        
        return $result;
    }
}


