<?php

namespace App\Services;

use App\DAL\BookingNoteSummaryDAL;

class BookingNoteSummaryService{
    private $bookingNoteSummaryDAL;

    public function __construct()
    {
        $this->bookingNoteSummaryDAL = new BookingNoteSummaryDAL();
    }

    // Get distinct note departments
    public function getDistinctNoteDepartments(): array
    {
        return $this->bookingNoteSummaryDAL->getDistinctNoteDepartments();
    }

    // Get distinct note categories
    public function getDistinctNoteCategories(): array
    {
        return $this->bookingNoteSummaryDAL->getDistinctNoteCategories();
    }
}