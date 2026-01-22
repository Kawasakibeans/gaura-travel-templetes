<?php

namespace App\Services;

use App\DAL\BookingNoteSummaryDAL;
use App\DAL\CallCenterDAL;
use Exception;

/**
 * G360 Note Report service (gt1).
 *
 * This service mirrors the data logic used by the working AI-server template:
 * - Records are sourced from backend_history_of_updates JOIN backend_travel_bookings (see CallCenterDAL).
 * - Filter dropdowns and counts come from backend_booking_note_summary.
 */
class G360NoteReportService
{
    private CallCenterDAL $callCenterDal;
    private BookingNoteSummaryDAL $noteSummaryDal;

    public function __construct()
    {
        $this->callCenterDal = new CallCenterDAL();
        $this->noteSummaryDal = new BookingNoteSummaryDAL();
    }

    /**
     * @param array<string,mixed> $params
     */
    public function getReport(array $params): array
    {
        $filterDate = $params['filter_date'] ?? date('Y-m-d');
        if (!is_string($filterDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
            throw new Exception('Invalid filter_date. Expected format: YYYY-MM-DD', 400);
        }

        $department = isset($params['department']) && is_string($params['department']) ? trim($params['department']) : null;
        $category = isset($params['category']) && is_string($params['category']) ? trim($params['category']) : null;
        if ($department === '') $department = null;
        if ($category === '') $category = null;

        // Template may send fetch_all=1. Keep compatibility with call-center-data options too.
        $fetchAll = $params['fetch_all'] ?? ($params['all_records'] ?? false);
        $allRecords = filter_var($fetchAll, FILTER_VALIDATE_BOOLEAN);

        $perPage = isset($params['per_page']) ? (int)$params['per_page'] : (isset($params['perPage']) ? (int)$params['perPage'] : 10);
        if ($perPage <= 0 || $perPage > 1000) $perPage = 10;

        $page = isset($params['page']) ? (int)$params['page'] : 1;
        if ($page <= 0) $page = 1;

        $start = isset($params['start']) ? (int)$params['start'] : (($page - 1) * $perPage);
        if ($start < 0) $start = 0;

        $rows = $this->callCenterDal->fetchCallCenterData($filterDate, $department, $category, $allRecords, $start, $perPage);

        // If we're paginating, total is unknown without a separate COUNT query.
        // For now, prefer correctness for fetch_all (the template's current usage).
        $total = $allRecords ? count($rows) : null;

        return [
            'records' => $rows,
            'pagination' => [
                'total' => $total ?? count($rows),
                'per_page' => $perPage,
                'page' => $page,
                'total_pages' => $total !== null ? (int)ceil($total / $perPage) : 1,
                'start' => $start,
                'all_records' => $allRecords,
            ],
        ];
    }

    public function getFilters(): array
    {
        return [
            'departments' => $this->noteSummaryDal->getDistinctNoteDepartments(),
            'categories' => $this->noteSummaryDal->getDistinctNoteCategories(),
        ];
    }

    /**
     * @param array<string,mixed> $params
     */
    public function getCounts(array $params): array
    {
        $filterDate = $params['filter_date'] ?? date('Y-m-d');
        if (!is_string($filterDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
            throw new Exception('Invalid filter_date. Expected format: YYYY-MM-DD', 400);
        }

        $type = $params['type'] ?? '';
        if (!is_string($type) || trim($type) === '') {
            throw new Exception('type is required (department|category|description)', 400);
        }

        try {
            $items = $this->noteSummaryDal->getCountsByType($filterDate, $type);
        } catch (\InvalidArgumentException $e) {
            throw new Exception($e->getMessage(), 400);
        }

        return [
            'type' => strtolower(trim($type)),
            'items' => $items,
        ];
    }
}


