<?php
namespace App\Services;

use App\DAL\DateChangeDAL;
use Exception;

class DateChangeService
{
    private $dateChangeDAL;

    public function __construct()
    {
        $this->dateChangeDAL = new DateChangeDAL();
    }

    /**
     * Get date change requests with pagination and filters
     */
    public function getDateChangeRequests($startDate = null, $endDate = null, $filters = [], $page = 1, $rowsPerPage = 20)
    {
        $allRequests = $this->dateChangeDAL->getDateChangeRequests($startDate, $endDate, $filters);

        // Group by case_id to get latest record per case
        $byCase = [];
        foreach ($allRequests as $request) {
            $cid = (string)($request['case_id'] ?? '');
            $ts = strtotime($request['case_date'] ?? '') ?: 0;
            if (!isset($byCase[$cid]) || $ts > $byCase[$cid]['_ts']) {
                $request['_ts'] = $ts;
                $byCase[$cid] = $request;
            }
        }
        $allRequests = array_values($byCase);

        // Process data to match expected format
        $processedRequests = [];
        foreach ($allRequests as $request) {
            // Extract airline code from trip_code
            $airline = '';
            if (!empty($request['trip_code']) && strlen($request['trip_code']) >= 10) {
                $airline = substr($request['trip_code'], 8, 2);
            }

            // Format dates
            $caseDate = '';
            if (!empty($request['case_date'])) {
                try {
                    $caseDateDt = \DateTime::createFromFormat('Y-m-d H:i:s', $request['case_date']);
                    if ($caseDateDt) {
                        $caseDate = $caseDateDt->format('d/m/Y');
                    }
                } catch (Exception $e) {
                    $caseDate = '';
                }
            }

            $travelDate = !empty($request['travel_date']) ? date('d/m/Y', strtotime($request['travel_date'])) : '';
            $lastResponseOn = !empty($request['last_response_on']) ? date('d/m/Y', strtotime($request['last_response_on'])) : '';

            // Get latest cost breakdown
            $costRow = $this->dateChangeDAL->getLatestCostBreakdown(
                isset($request['case_id']) ? trim((string)$request['case_id']) : '',
                isset($request['order_id']) ? trim((string)$request['order_id']) : ''
            );

            $airlineChangeFee = $costRow && isset($costRow['airline_change_fee']) ? (float)$costRow['airline_change_fee'] : '';
            $fareDifference = $costRow && isset($costRow['fare_difference']) ? (float)$costRow['fare_difference'] : '';
            $gauraServiceFee = $costRow && isset($costRow['gaura_service_fee']) ? (float)$costRow['gaura_service_fee'] : '';
            $bufferVal = $costRow && isset($costRow['buffer']) ? (float)$costRow['buffer'] : '';
            $costTaken = $costRow && isset($costRow['total_amount']) ? (float)$costRow['total_amount'] : 0;
            $totalRevenue = (float)$bufferVal + (float)$gauraServiceFee;

            $processedRequests[] = [
                'query_date' => $caseDate,
                'agent' => $request['updated_by'] ?? '',
                'case_id' => $request['case_id'] ?? '',
                'reservation_ref' => $request['reservation_ref'] ?? '',
                'assigned_case_agent' => $request['assigned_case_agent'] ?? '',
                'request_type' => 'datechange',
                'pax_count' => $request['total_pax'] ?? '',
                'airline' => $airline,
                'last_quoted_by' => '',
                'booking_type' => (isset($request['order_type']) && strtolower($request['order_type']) === 'gds') ? 'FIT' : 'GDeals',
                'old_travel_date' => $travelDate,
                'airline_change_fee' => $airlineChangeFee,
                'fare_difference' => $fareDifference,
                'gaura_travel_service_fee' => $gauraServiceFee,
                'buffer' => $bufferVal,
                'cost_given' => $request['total_amount'] ?? 0,
                'expected_cost' => '',
                'cost_taken' => $costTaken,
                'total_revenue' => $totalRevenue,
                'status' => strtolower(trim($request['status'] ?? '')),
                'sub_status' => trim((string)($request['sub_status'] ?? '')),
                'status_date' => $lastResponseOn,
            ];
        }

        // Apply filters
        if (!empty($filters)) {
            $processedRequests = array_values(array_filter($processedRequests, function ($row) use ($filters) {
                if (!empty($filters['status']) && strtolower($row['status']) !== strtolower($filters['status'])) {
                    return false;
                }
                if (!empty($filters['sub_status']) && strtolower((string)($row['sub_status'] ?? '')) !== strtolower((string)$filters['sub_status'])) {
                    return false;
                }
                if (!empty($filters['airline']) && $row['airline'] !== $filters['airline']) {
                    return false;
                }
                if (!empty($filters['booking_type']) && $row['booking_type'] !== $filters['booking_type']) {
                    return false;
                }
                if (!empty($filters['request_type']) && strtolower($row['request_type']) !== strtolower($filters['request_type'])) {
                    return false;
                }
                if (!empty($filters['agent']) && strtolower($row['agent']) !== strtolower($filters['agent'])) {
                    return false;
                }
                if (!empty($filters['assigned_case_agent']) && strtolower($row['assigned_case_agent']) !== strtolower($filters['assigned_case_agent'])) {
                    return false;
                }
                if (!empty($filters['search'])) {
                    $hay = strtolower(implode(' ', array_map('strval', $row)));
                    if (strpos($hay, strtolower($filters['search'])) === false) {
                        return false;
                    }
                }
                return true;
            }));
        }

        // Pagination
        $total = count($processedRequests);
        $start = ($page - 1) * $rowsPerPage;
        $paged = array_slice($processedRequests, $start, $rowsPerPage);

        // Generate HTML (simplified - in real implementation, this would be a proper template)
        $html = '';
        foreach ($paged as $request) {
            $statusClass = strtolower(str_replace(' ', '-', $request['status']));
            $html .= '<tr data-status="' . htmlspecialchars($statusClass) . '">';
            $html .= '<td>' . htmlspecialchars($request['query_date']) . '</td>';
            $html .= '<td><span class="status-badge status-' . htmlspecialchars($statusClass) . '">' . htmlspecialchars($request['status']) . '</span></td>';
            $html .= '<td>' . htmlspecialchars($request['agent']) . '</td>';
            $html .= '<td>' . htmlspecialchars($request['case_id']) . '</td>';
            $html .= '<td>' . htmlspecialchars($request['reservation_ref']) . '</td>';
            $html .= '<td>' . htmlspecialchars($request['request_type']) . '</td>';
            $html .= '<td>' . htmlspecialchars($request['pax_count']) . '</td>';
            $html .= '<td>' . htmlspecialchars($request['airline']) . '</td>';
            $html .= '<td>' . htmlspecialchars($request['booking_type']) . '</td>';
            $html .= '<td>' . ($request['airline_change_fee'] !== '' ? '$' . number_format((float)$request['airline_change_fee'], 2) : '') . '</td>';
            $html .= '<td>' . ($request['fare_difference'] !== '' ? '$' . number_format((float)$request['fare_difference'], 2) : '') . '</td>';
            $html .= '<td>' . ($request['gaura_travel_service_fee'] !== '' ? '$' . number_format((float)$request['gaura_travel_service_fee'], 2) : '') . '</td>';
            $html .= '<td>' . ($request['buffer'] !== '' ? '$' . number_format((float)$request['buffer'], 2) : '') . '</td>';
            $html .= '<td>$' . number_format((float)$request['cost_taken'], 2) . '</td>';
            $html .= '<td>$' . number_format((float)$request['total_revenue'], 2) . '</td>';
            $html .= '<td>' . htmlspecialchars($request['status_date']) . '</td>';
            $html .= '</tr>';
        }

        return [
            'html' => $html,
            'total' => $total
        ];
    }

    /**
     * Add date change record
     */
    public function addDateChangeRecord($data)
    {
        if (empty($data['reservation_ref'])) {
            throw new Exception('Reservation Ref is required', 400);
        }

        // Normalize number strings
        $nf = function($v) {
            if ($v === '' || $v === null) return '0.00';
            $v = preg_replace('/[,\s()]/', '', (string)$v);
            if (preg_match('/^\((.+)\)$/', $v, $m)) {
                $v = '-' . $m[1];
            }
            return number_format((float)$v, 2, '.', '');
        };

        $acf = $nf($data['airline_change_fee'] ?? '');
        $fd = $nf($data['fare_difference'] ?? '');
        $gsf = $nf($data['gaura_service_fee'] ?? '');
        $buf = $nf($data['buffer'] ?? '');
        $tot = $nf($data['total_amount'] ?? '');

        // Get next case ID
        $nextCaseId = $this->dateChangeDAL->getNextCaseId();
        if ($nextCaseId <= 0) {
            $nextCaseId = 1;
        }

        $nowMysql = date('Y-m-d H:i:s');
        $currentUser = $data['current_user'] ?? 'system';

        // Insert into main requests table
        $this->dateChangeDAL->insertDateChangeRecord([
            'case_id' => $nextCaseId,
            'reservation_ref' => $data['reservation_ref'],
            'case_date' => $nowMysql,
            'current_user' => $currentUser
        ]);

        // Insert cost breakdown
        $this->dateChangeDAL->insertCostBreakdown([
            'case_id' => (string)$nextCaseId,
            'reservation_ref' => $data['reservation_ref'],
            'airline_change_fee' => $acf,
            'fare_difference' => $fd,
            'gaura_service_fee' => $gsf,
            'buffer' => $buf,
            'total_amount' => $tot,
            'added_on' => $nowMysql,
            'added_by' => $currentUser
        ]);

        // Insert/Update meta
        $this->dateChangeDAL->upsertCaseMeta($nextCaseId, 'order_id', $data['reservation_ref']);
        $this->dateChangeDAL->upsertCaseMeta($nextCaseId, 'airline_change_fee', $acf);
        $this->dateChangeDAL->upsertCaseMeta($nextCaseId, 'fare_difference', $fd);
        $this->dateChangeDAL->upsertCaseMeta($nextCaseId, 'gaura_service_fee', $gsf);
        $this->dateChangeDAL->upsertCaseMeta($nextCaseId, 'buffer', $buf);
        $this->dateChangeDAL->upsertCaseMeta($nextCaseId, 'total_amount', $tot);

        return [
            'success' => true,
            'message' => 'Record added',
            'case_id' => $nextCaseId
        ];
    }

    /**
     * Get remarks
     */
    public function getRemarks($caseId = null, $reservationRef = null)
    {
        if (!$caseId && !$reservationRef) {
            throw new Exception('Missing case_id / reservation_ref', 400);
        }

        $remarks = $this->dateChangeDAL->getRemarks($caseId, $reservationRef);
        return [
            'success' => true,
            'data' => $remarks ?: []
        ];
    }

    /**
     * Submit remark
     */
    public function submitRemark($data)
    {
        if (empty($data['case_id']) && empty($data['reservation_ref'])) {
            throw new Exception('Missing case_id / reservation_ref', 400);
        }
        if (empty($data['remark'])) {
            throw new Exception('Remark is required', 400);
        }

        $nowMysql = date('Y-m-d H:i:s');
        $createdBy = $data['created_by'] ?? 'system';

        $id = $this->dateChangeDAL->insertRemark([
            'case_id' => $data['case_id'] ?: null,
            'reservation_ref' => $data['reservation_ref'] ?: null,
            'remark' => trim((string)$data['remark']),
            'created_on' => $nowMysql,
            'created_by' => $createdBy
        ]);

        $row = $this->dateChangeDAL->getRemarkById($id);
        return [
            'success' => true,
            'data' => $row
        ];
    }

    /**
     * Get next case ID
     */
    public function getNextCaseId()
    {
        $nextId = $this->dateChangeDAL->getNextCaseId();
        return [
            'success' => true,
            'data' => $nextId
        ];
    }

    /**
     * Update agent assignment
     */
    public function updateAgentAssignment($data)
    {
        if (empty($data['case_id']) && empty($data['reservation_ref'])) {
            throw new Exception('Missing case_id / reservation_ref', 400);
        }
        if (empty($data['success_agent']) && empty($data['failed_agent'])) {
            throw new Exception('At least one agent must be selected', 400);
        }

        $selectedAgent = !empty($data['success_agent']) ? $data['success_agent'] : $data['failed_agent'];
        $agentType = !empty($data['success_agent']) ? 'success' : 'failed';

        $this->dateChangeDAL->updateAgentAssignment(
            $data['case_id'] ?? null,
            $data['reservation_ref'] ?? null,
            $selectedAgent
        );

        // Add remark about agent assignment
        $nowMysql = date('Y-m-d H:i:s');
        $createdBy = $data['created_by'] ?? 'system';
        $remarkText = "Agent assignment updated: {$agentType} agent '{$selectedAgent}' saved to assigned_case_agent column";

        $this->dateChangeDAL->insertRemark([
            'case_id' => $data['case_id'] ?: null,
            'reservation_ref' => $data['reservation_ref'] ?: null,
            'remark' => $remarkText,
            'created_on' => $nowMysql,
            'created_by' => $createdBy
        ]);

        return [
            'success' => true,
            'message' => 'Agent assignment updated successfully'
        ];
    }

    /**
     * Get agent data
     */
    public function getAgentData($caseId, $reservationRef = null)
    {
        if (!$caseId && !$reservationRef) {
            throw new Exception('Missing case_id / reservation_ref', 400);
        }

        if (!$caseId) {
            throw new Exception('case_id is required for agent data', 400);
        }

        $agentData = $this->dateChangeDAL->getAgentData($caseId);
        return [
            'success' => true,
            'data' => $agentData
        ];
    }

    /**
     * Get monthly summary
     */
    public function getMonthlySummary($startDate = null, $endDate = null)
    {
        return $this->dateChangeDAL->getMonthlySummary($startDate, $endDate);
    }

    /**
     * Get filter options
     */
    public function getFilterOptions()
    {
        return $this->dateChangeDAL->getFilterOptions();
    }

    /**
     * Get latest cost breakdown
     */
    public function getLatestCostBreakdown($caseId = null, $orderId = null)
    {
        return $this->dateChangeDAL->getLatestCostBreakdown($caseId, $orderId);
    }
}

