<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class TicketNumberDAL extends BaseDAL
{
	/**
	 * Return the subset of provided document numbers that already exist
	 */
	public function getExistingDocuments(array $documentNumbers): array
	{
		if (empty($documentNumbers)) {
			return [];
		}

		$placeholders = implode(',', array_fill(0, count($documentNumbers), '?'));
		$sql = "SELECT document FROM wpk4_backend_travel_booking_ticket_number WHERE document IN ({$placeholders})";

		$rows = $this->query($sql, array_values($documentNumbers));
		return array_map(static function ($row) { return $row['document']; }, $rows);
	}
}


