<?php

namespace App\DAL;

class BookingNoteSummaryDAL extends BaseDAL
{
    // Get distinct note departments
    public function getDistinctNoteDepartments()
    {
        $prefix = $this->tablePrefix();
        return $this->query("SELECT DISTINCT note_department AS meta_value 
            FROM {$prefix}backend_booking_note_summary 
            WHERE note_department IS NOT NULL AND note_department <> '' 
            ORDER BY note_department");
    }

    // Get distinct note categories
    public function getDistinctNoteCategories()
    {
        $prefix = $this->tablePrefix();
        return $this->query("SELECT DISTINCT note_category AS meta_value 
            FROM {$prefix}backend_booking_note_summary 
            WHERE note_category IS NOT NULL AND note_category <> '' 
            ORDER BY note_category");
    }

    /**
     * Get counts grouped by a note field for a single day.
     *
     * @param string $filterDate YYYY-MM-DD
     * @param string $type One of: Department, Category, Description (case-insensitive)
     */
    public function getCountsByType(string $filterDate, string $type): array
    {
        $prefix = $this->tablePrefix();

        $type = strtolower(trim($type));
        $columnMap = [
            'department' => 'note_department',
            'category' => 'note_category',
            'description' => 'note_description',
        ];

        if (!isset($columnMap[$type])) {
            throw new \InvalidArgumentException("Invalid type: {$type}");
        }

        $column = $columnMap[$type];
        $startDate = $filterDate . ' 00:00:00';
        $endDate = $filterDate . ' 23:59:59';

        $sql = "
            SELECT
                {$column} AS group_name,
                COUNT(*) AS count
            FROM {$prefix}backend_booking_note_summary bns
            WHERE bns.updated_on BETWEEN :start_date AND :end_date
              AND {$column} IS NOT NULL AND {$column} <> ''
              AND bns.note_id IS NOT NULL
            GROUP BY {$column}
            ORDER BY count DESC
        ";

        return $this->query($sql, [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }
}