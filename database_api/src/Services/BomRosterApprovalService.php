<?php
/**
 * Service layer for BOM roster approvals.
 */

namespace App\Services;

use App\DAL\BomRosterApprovalDAL;
use DateTime;
use Exception;

class BomRosterApprovalService
{
    private BomRosterApprovalDAL $dal;

    public function __construct()
    {
        $this->dal = new BomRosterApprovalDAL();
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function listRequests(array $filters): array
    {
        $status = isset($filters['status']) ? strtolower((string)$filters['status']) : 'pending';
        $manager = isset($filters['sales_manager']) ? trim((string)$filters['sales_manager']) : null;
        if ($manager === '') {
            $manager = null;
        }

        if ($status === 'processed') {
            $data = $this->dal->getProcessedRequests($manager);
        } else {
            $status = 'pending';
            $data = $this->dal->getPendingRequests($manager);
        }

        return [
            'status' => $status,
            'sales_manager' => $manager,
            'requests' => $data,
        ];
    }

    public function listSalesManagers(): array
    {
        return [
            'sales_managers' => $this->dal->getSalesManagers(),
        ];
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function listLeaveRequests(array $filters): array
    {
        $manager = isset($filters['sales_manager']) ? trim((string)$filters['sales_manager']) : null;
        if ($manager === '') {
            $manager = null;
        }

        return [
            'sales_manager' => $manager,
            'leave_requests' => $this->dal->getLeaveRequests($manager),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function decide(array $payload): array
    {
        $id = isset($payload['request_id']) ? (int)$payload['request_id'] : 0;
        $action = isset($payload['action']) ? strtolower(trim((string)$payload['action'])) : '';

        if ($id <= 0) {
            throw new Exception('request_id must be provided', 400);
        }
        if (!in_array($action, ['approve', 'reject'], true)) {
            throw new Exception('action must be approve or reject', 400);
        }

        $request = $this->dal->getRequestById($id);
        if (!$request) {
            throw new Exception('Roster request not found', 404);
        }

        $newStatus = $action === 'approve' ? 'Provision Approve' : 'Rejected';
        $this->dal->updateRequestStatus($id, $newStatus);

        $updates = [];

        if ($action === 'approve') {
            $updates = $this->applyApprovalSideEffects($request);
        }

        return [
            'status' => 'success',
            'request_id' => $id,
            'new_status' => $newStatus,
            'side_effects' => $updates,
        ];
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,string>
     */
    private function applyApprovalSideEffects(array $request): array
    {
        $rosterCode = (string)($request['roster_code'] ?? '');
        if ($rosterCode === '') {
            return [];
        }

        $type = (string)($request['type'] ?? '');
        $effects = [];

        switch ($type) {
            case 'RDO Change Request':
                $requestedRdo = (string)($request['requested_rdo'] ?? '');
                if ($requestedRdo !== '') {
                    $rdo = strtolower(substr($requestedRdo, 0, 3));
                    $this->dal->updateAvailabilityRdo($rosterCode, $rdo);
                    $effects['availability_sheet'] = 'rdo:' . $rdo;
                }
                break;

            case 'Shift Change Request':
                $requestedShift = (string)($request['requested_shift'] ?? '');
                $normalized = $this->normalizeShift($requestedShift);
                if ($normalized !== null) {
                    $this->dal->updateRosterShift($rosterCode, $normalized);
                    $effects['roster_shift'] = $normalized;
                }
                break;

            default:
                // no action required
        }

        return $effects;
    }

    private function normalizeShift(string $raw): ?string
    {
        $clean = preg_replace('/[^0-9:apm\s]/i', '', $raw);
        $clean = trim($clean);
        if ($clean === '') {
            return null;
        }

        $time = DateTime::createFromFormat('g:ia', strtolower($clean));
        if (!$time) {
            $time = DateTime::createFromFormat('H:i', $clean);
        }
        if (!$time) {
            $time = DateTime::createFromFormat('Hi', preg_replace('/[^0-9]/', '', $clean));
        }
        if (!$time) {
            return null;
        }

        return $time->format('Hi');
    }
}

