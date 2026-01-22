<?php

namespace App\Services;

use App\DAL\TicketNumberDAL;
use Exception;

class TicketNumberService
{
	private $dal;

	public function __construct()
	{
		$this->dal = new TicketNumberDAL();
	}

	/**
	 * Validate and return existing ticket document numbers
	 */
	public function getExistingDocuments(array $documentNumbers): array
	{
		if (!is_array($documentNumbers)) {
			throw new Exception('document_numbers must be an array', 400);
		}

		$normalized = [];
		foreach ($documentNumbers as $doc) {
			if (!is_scalar($doc)) {
				continue;
			}
			$val = trim((string)$doc);
			if ($val !== '') {
				$normalized[] = $val;
			}
		}

		$normalized = array_values(array_unique($normalized));

		if (empty($normalized)) {
			return ['existing_documents' => []];
		}

		$existing = $this->dal->getExistingDocuments($normalized);
		return ['existing_documents' => array_values($existing)];
	}
}


