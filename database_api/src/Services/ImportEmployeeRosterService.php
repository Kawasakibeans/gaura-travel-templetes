<?php
namespace App\Services;

use App\DAL\ImportEmployeeRosterDAL;
use Exception;

class ImportEmployeeRosterService
{
    private $importEmployeeRosterDAL;

    public function __construct()
    {
        $this->importEmployeeRosterDAL = new ImportEmployeeRosterDAL();
    }

    /**
     * Preview CSV data
     */
    public function previewCsvData($csvData, $header = null)
    {
        if (empty($csvData) || !is_array($csvData)) {
            throw new Exception('CSV data is required and must be an array', 400);
        }

        // Extract month and year from header if provided
        $month = null;
        $year = null;
        $daysInMonth = 31;

        if ($header) {
            foreach ($header as $colName) {
                if (preg_match('/(\d{1,2})[-\/]([A-Za-z]+)[-\/]?(\d{4})?/', $colName, $matches)) {
                    $monthStr = ucfirst(strtolower($matches[2]));
                    if (!empty($matches[3])) {
                        $year = (int)$matches[3];
                    }
                    break;
                }
            }

            if ($year === null) {
                $year = (int)date('Y');
            }

            if ($monthStr) {
                try {
                    $month = date('F', strtotime("1 $monthStr"));
                    $currentMonth = date('m', strtotime($month));
                    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $year);
                } catch (Exception $e) {
                    $month = date('F');
                }
            } else {
                $month = date('F');
            }
        } else {
            $month = date('F');
            $year = (int)date('Y');
        }

        return [
            'success' => true,
            'data' => [
                'header' => $header ?: [],
                'rows' => $csvData,
                'month' => $month,
                'year' => $year,
                'days_in_month' => $daysInMonth
            ]
        ];
    }

    /**
     * Import employee roster records
     */
    public function importRosterRecords($records, $month, $year)
    {
        if (empty($records) || !is_array($records)) {
            throw new Exception('Records array is required', 400);
        }

        if (empty($month) || empty($year)) {
            throw new Exception('Month and year are required', 400);
        }

        $importedCount = 0;

        foreach ($records as $record) {
            if (empty($record['employee_code']) || empty($record['employee_name'])) {
                continue; // Skip invalid records
            }

            $data = [
                'employee_code' => $record['employee_code'],
                'employee_name' => $record['employee_name'],
                'department' => $record['department'] ?? '',
                'team' => $record['team'] ?? '',
                'tl' => $record['tl'] ?? '',
                'sm' => $record['sm'] ?? '',
                'shift_type' => $record['shift_type'] ?? '',
                'shift_time' => $record['shift_time'] ?? '',
                'rdo' => $record['rdo'] ?? '',
                'month' => $month,
                'year' => (int)$year
            ];

            // Add day columns
            for ($day = 1; $day <= 31; $day++) {
                $dayColumn = "day_$day";
                $data[$dayColumn] = $record[$dayColumn] ?? $record["day_{$day}"] ?? '';
            }

            if ($this->importEmployeeRosterDAL->importRosterRecord($data)) {
                $importedCount++;
            }
        }

        return [
            'success' => true,
            'message' => 'Data Imported Successfully',
            'imported_count' => $importedCount
        ];
    }

    /**
     * Get employee roster data
     */
    public function getEmployeeRosterData($month, $year, $filters = [], $viewType = 'view')
    {
        if (empty($month) || empty($year)) {
            throw new Exception('Month and year are required', 400);
        }

        $results = $this->importEmployeeRosterDAL->getEmployeeRosterData($month, $year, $filters, $viewType);

        // Format shift_time and day values (pad 3-digit times to 4 digits)
        foreach ($results as &$row) {
            if (isset($row['shift_time']) && ctype_digit($row['shift_time']) && strlen($row['shift_time']) == 3) {
                $row['shift_time'] = str_pad($row['shift_time'], 4, "0", STR_PAD_LEFT);
            }

            for ($day = 1; $day <= 31; $day++) {
                $dayColumn = "day_$day";
                if (isset($row[$dayColumn]) && ctype_digit($row[$dayColumn]) && strlen($row[$dayColumn]) == 3) {
                    $row[$dayColumn] = str_pad($row[$dayColumn], 4, "0", STR_PAD_LEFT);
                }
            }
        }

        return $results;
    }

    /**
     * Get filter options
     */
    public function getFilterOptions()
    {
        return $this->importEmployeeRosterDAL->getFilterOptions();
    }
}

