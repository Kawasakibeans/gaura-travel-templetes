<?php

namespace App\Services;

use App\DAL\AirlinesPaymentDetailsDAL;
use Exception;

class AirlinesPaymentDetailsService
{
	private $dal;

	public function __construct()
	{
		$this->dal = new AirlinesPaymentDetailsDAL();
	}

	public function updateField(int $autoId, array $body): array
	{
		if ($autoId <= 0) {
			throw new Exception('Valid auto_id is required', 400);
		}

		$column = isset($body['column_name']) ? trim((string)$body['column_name']) : '';
		if ($column === '') {
			throw new Exception('column_name is required', 400);
		}
		if (!array_key_exists('value', $body)) {
			throw new Exception('value is required', 400);
		}
		$value = $body['value'];

		// Basic validation for known date fields
		$dateFields = [
			'ticketing_deadline','payment_deadline','deposit_deadline',
			'deposited_on','paid_on','refund_on','split_on','done_on','ticketed_on'
		];
		if (in_array($column, $dateFields, true)) {
			if (!is_string($value) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) {
				throw new Exception($column . ' must be YYYY-MM-DD', 400);
			}
		}

		$ok = $this->dal->updateFieldById($autoId, $column, $value);

		return [
			'auto_id' => $autoId,
			'column_name' => $column,
			'value' => $value,
			'updated' => (bool)$ok
		];
	}

	public function updateDepositLastModified(int $autoId, array $body): array
	{
		if ($autoId <= 0) {
			throw new Exception('Valid auto_id is required', 400);
		}
		$modifiedBy = isset($body['modified_by']) ? trim((string)$body['modified_by']) : '';
		$modifiedOn = isset($body['modified_on']) ? trim((string)$body['modified_on']) : '';

		if ($modifiedBy === '') {
			throw new Exception('modified_by is required', 400);
		}
		if ($modifiedOn === '') {
			$modifiedOn = date('Y-m-d H:i:s');
		} else {
			if (!preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/', $modifiedOn)) {
				throw new Exception('modified_on must be YYYY-MM-DD HH:MM:SS', 400);
			}
		}

		$ok = $this->dal->updateDepositLastModified($autoId, $modifiedOn, $modifiedBy);

		return [
			'auto_id' => $autoId,
			'deposit_last_modified_on' => $modifiedOn,
			'deposit_last_modified_by' => $modifiedBy,
			'updated' => (bool)$ok
		];
	}

	public function updatePaidLastModified(int $autoId, array $body): array
	{
		if ($autoId <= 0) {
			throw new Exception('Valid auto_id is required', 400);
		}
		$modifiedBy = isset($body['modified_by']) ? trim((string)$body['modified_by']) : '';
		$modifiedOn = isset($body['modified_on']) ? trim((string)$body['modified_on']) : '';

		if ($modifiedBy === '') {
			throw new Exception('modified_by is required', 400);
		}
		if ($modifiedOn === '') {
			$modifiedOn = date('Y-m-d H:i:s');
		} else {
			if (!preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/', $modifiedOn)) {
				throw new Exception('modified_on must be YYYY-MM-DD HH:MM:SS', 400);
			}
		}

		$ok = $this->dal->updatePaidLastModified($autoId, $modifiedOn, $modifiedBy);

		return [
			'auto_id' => $autoId,
			'paid_last_modified_on' => $modifiedOn,
			'paid_last_modified_by' => $modifiedBy,
			'updated' => (bool)$ok
		];
	}

	public function updateTicketedLastModified(int $autoId, array $body): array
	{
		if ($autoId <= 0) {
			throw new Exception('Valid auto_id is required', 400);
		}
		$modifiedBy = isset($body['modified_by']) ? trim((string)$body['modified_by']) : '';
		$modifiedOn = isset($body['modified_on']) ? trim((string)$body['modified_on']) : '';

		if ($modifiedBy === '') {
			throw new Exception('modified_by is required', 400);
		}
		if ($modifiedOn === '') {
			$modifiedOn = date('Y-m-d H:i:s');
		} else {
			if (!preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/', $modifiedOn)) {
				throw new Exception('modified_on must be YYYY-MM-DD HH:MM:SS', 400);
			}
		}

		$ok = $this->dal->updateTicketedLastModified($autoId, $modifiedOn, $modifiedBy);

		return [
			'auto_id' => $autoId,
			'ticketed_last_modified_on' => $modifiedOn,
			'ticketed_last_modified_by' => $modifiedBy,
			'updated' => (bool)$ok
		];
	}

	public function updateRefundUpdated(int $autoId, array $body): array
	{
		if ($autoId <= 0) {
			throw new Exception('Valid auto_id is required', 400);
		}
		$updatedBy = isset($body['updated_by']) ? trim((string)$body['updated_by']) : '';
		$updatedOn = isset($body['updated_on']) ? trim((string)$body['updated_on']) : '';

		if ($updatedBy === '') {
			throw new Exception('updated_by is required', 400);
		}
		if ($updatedOn === '') {
			$updatedOn = date('Y-m-d H:i:s');
		} else {
			if (!preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/', $updatedOn)) {
				throw new Exception('updated_on must be YYYY-MM-DD HH:MM:SS', 400);
			}
		}

		$ok = $this->dal->updateRefundUpdated($autoId, $updatedOn, $updatedBy);

		return [
			'auto_id' => $autoId,
			'refund_updated_on' => $updatedOn,
			'refund_updated_by' => $updatedBy,
			'updated' => (bool)$ok
		];
	}

	public function updateSplitUpdated(int $autoId, array $body): array
	{
		if ($autoId <= 0) {
			throw new Exception('Valid auto_id is required', 400);
		}
		$updatedBy = isset($body['updated_by']) ? trim((string)$body['updated_by']) : '';
		$updatedOn = isset($body['updated_on']) ? trim((string)$body['updated_on']) : '';

		if ($updatedBy === '') {
			throw new Exception('updated_by is required', 400);
		}
		if ($updatedOn === '') {
			$updatedOn = date('Y-m-d H:i:s');
		} else {
			if (!preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/', $updatedOn)) {
				throw new Exception('updated_on must be YYYY-MM-DD HH:MM:SS', 400);
			}
		}

		$ok = $this->dal->updateSplitUpdated($autoId, $updatedOn, $updatedBy);

		return [
			'auto_id' => $autoId,
			'split_updated_on' => $updatedOn,
			'split_updated_by' => $updatedBy,
			'updated' => (bool)$ok
		];
	}

	public function updateDoneUpdated(int $autoId, array $body): array
	{
		if ($autoId <= 0) {
			throw new Exception('Valid auto_id is required', 400);
		}
		$updatedBy = isset($body['updated_by']) ? trim((string)$body['updated_by']) : '';
		$updatedOn = isset($body['updated_on']) ? trim((string)$body['updated_on']) : '';

		if ($updatedBy === '') {
			throw new Exception('updated_by is required', 400);
		}
		if ($updatedOn === '') {
			$updatedOn = date('Y-m-d H:i:s');
		} else {
			if (!preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/', $updatedOn)) {
				throw new Exception('updated_on must be YYYY-MM-DD HH:MM:SS', 400);
			}
		}

		$ok = $this->dal->updateDoneUpdated($autoId, $updatedOn, $updatedBy);

		return [
			'auto_id' => $autoId,
			'done_updated_on' => $updatedOn,
			'done_updated_by' => $updatedBy,
			'updated' => (bool)$ok
		];
	}

	public function getById(int $autoId): array
	{
		if ($autoId <= 0) {
			throw new Exception('Valid auto_id is required', 400);
		}
		$row = $this->dal->getById($autoId);
		if (!$row) {
			throw new Exception('Airlines payment details row not found', 404);
		}
		return $row;
	}

	public function createEmdPayment(array $data): array
	{
		$airlinesPaymentDetailsId = isset($data['airlines_payment_details_id']) ? (int)$data['airlines_payment_details_id'] : 0;
		$pnr = isset($data['pnr']) ? trim((string)$data['pnr']) : '';
		$paymentType = isset($data['payment_type']) ? trim((string)$data['payment_type']) : '';
		$paymentDate = isset($data['payment_date']) ? trim((string)$data['payment_date']) : '';
		$paymentBy = isset($data['payment_by']) ? trim((string)$data['payment_by']) : '';
		$emdReference = isset($data['emd_reference']) ? trim((string)$data['emd_reference']) : '';
		$amount = $data['amount'] ?? null;
		$description = isset($data['description']) ? (string)$data['description'] : null;
		$addedBy = isset($data['added_by']) ? trim((string)$data['added_by']) : '';

		if ($airlinesPaymentDetailsId <= 0) throw new Exception('airlines_payment_details_id is required', 400);
		if ($pnr === '') throw new Exception('pnr is required', 400);
		if ($paymentType === '') throw new Exception('payment_type is required', 400);
		if ($paymentDate === '' || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $paymentDate)) {
			throw new Exception('payment_date must be YYYY-MM-DD', 400);
		}
		if ($paymentBy === '') throw new Exception('payment_by is required', 400);
		if ($emdReference === '') throw new Exception('emd_reference is required', 400);
		if (!is_numeric($amount)) throw new Exception('amount must be numeric', 400);
		if ($addedBy === '') throw new Exception('added_by is required', 400);

		$id = $this->dal->insertEmdPayment(
			$airlinesPaymentDetailsId,
			$pnr,
			$paymentType,
			$paymentDate,
			$paymentBy,
			$emdReference,
			$amount,
			$description,
			$addedBy
		);

		return [
			'emd_id' => $id,
			'airlines_payment_details_id' => $airlinesPaymentDetailsId,
			'pnr' => $pnr,
			'payment_type' => $paymentType,
			'payment_date' => $paymentDate,
			'payment_by' => $paymentBy,
			'emd_reference' => $emdReference,
			'amount' => (float)$amount,
			'description' => $description,
			'added_by' => $addedBy,
			'created' => true
		];
	}

	public function listByGroupName(array $query): array
	{
		$groupName = isset($query['group_name']) ? trim((string)$query['group_name']) : '';
		if ($groupName === '') {
			throw new Exception('group_name is required', 400);
		}
		return $this->dal->getByGroupName($groupName);
	}

	public function createAirlinesPaymentDetails(array $data): array
	{
		$groupName = isset($data['group_name']) ? trim((string)$data['group_name']) : '';
		$pnr = isset($data['pnr']) ? trim((string)$data['pnr']) : '';
		$groupId = isset($data['group_id']) ? trim((string)$data['group_id']) : '';
		$depositDeadline = isset($data['deposit_deadline']) ? trim((string)$data['deposit_deadline']) : '';
		$paymentDeadline = isset($data['payment_deadline']) ? trim((string)$data['payment_deadline']) : '';
		$ticketingDeadline = isset($data['ticketing_deadline']) ? trim((string)$data['ticketing_deadline']) : '';
		$depositAmount = $data['deposit_amount'] ?? null;
		$outstandingAmount = $data['outstanding_amount'] ?? null;
		$emd = isset($data['emd']) ? trim((string)$data['emd']) : '';
		$totalAmount = $data['total_amount'] ?? null;
		$status = isset($data['status']) ? trim((string)$data['status']) : '';

		if ($groupName === '') throw new Exception('group_name is required', 400);
		if ($pnr === '') throw new Exception('pnr is required', 400);
		if ($groupId === '') throw new Exception('group_id is required', 400);
		foreach (['depositDeadline','paymentDeadline','ticketingDeadline'] as $d) {
			$val = $$d;
			if ($val === '' || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $val)) {
				throw new Exception(strtolower(preg_replace('/([A-Z])/', '_$1', $d)) . ' must be YYYY-MM-DD', 400);
			}
		}
		if (!is_numeric($depositAmount)) throw new Exception('deposit_amount must be numeric', 400);
		if (!is_numeric($outstandingAmount)) throw new Exception('outstanding_amount must be numeric', 400);
		if ($emd === '') throw new Exception('emd is required', 400);
		if (!is_numeric($totalAmount)) throw new Exception('total_amount must be numeric', 400);
		if ($status === '') throw new Exception('status is required', 400);

		$id = $this->dal->insertAirlinesPaymentDetails(
			$groupName,
			$pnr,
			$groupId,
			$depositDeadline,
			$paymentDeadline,
			$ticketingDeadline,
			$depositAmount,
			$outstandingAmount,
			$emd,
			$totalAmount,
			$status
		);

		return [
			'auto_id' => $id,
			'group_name' => $groupName,
			'pnr' => $pnr,
			'group_id' => $groupId,
			'deposit_deadline' => $depositDeadline,
			'payment_deadline' => $paymentDeadline,
			'ticketing_deadline' => $ticketingDeadline,
			'deposit_amount' => (float)$depositAmount,
			'outstanding_amount' => (float)$outstandingAmount,
			'emd' => $emd,
			'total_amount' => (float)$totalAmount,
			'status' => $status,
			'created' => true
		];
	}

	public function clearDepositDeadline(array $data): array
	{
		$pnr = isset($data['pnr']) ? trim((string)$data['pnr']) : '';
		$groupId = isset($data['group_id']) ? trim((string)$data['group_id']) : '';

		if ($pnr === '') {
			throw new Exception('pnr is required', 400);
		}
		if ($groupId === '') {
			throw new Exception('group_id is required', 400);
		}

		$ok = $this->dal->clearDepositDeadline($pnr, $groupId);

		return [
			'pnr' => $pnr,
			'group_id' => $groupId,
			'deposit_deadline_cleared' => (bool)$ok
		];
	}

	public function clearPaymentDeadline(array $data): array
	{
		$pnr = isset($data['pnr']) ? trim((string)$data['pnr']) : '';
		$groupId = isset($data['group_id']) ? trim((string)$data['group_id']) : '';

		if ($pnr === '') {
			throw new Exception('pnr is required', 400);
		}
		if ($groupId === '') {
			throw new Exception('group_id is required', 400);
		}

		$ok = $this->dal->clearPaymentDeadline($pnr, $groupId);

		return [
			'pnr' => $pnr,
			'group_id' => $groupId,
			'payment_deadline_cleared' => (bool)$ok
		];
	}

	public function clearTicketingDeadline(array $data): array
	{
		$pnr = isset($data['pnr']) ? trim((string)$data['pnr']) : '';
		$groupId = isset($data['group_id']) ? trim((string)$data['group_id']) : '';

		if ($pnr === '') {
			throw new Exception('pnr is required', 400);
		}
		if ($groupId === '') {
			throw new Exception('group_id is required', 400);
		}

		$ok = $this->dal->clearTicketingDeadline($pnr, $groupId);

		return [
			'pnr' => $pnr,
			'group_id' => $groupId,
			'ticketing_deadline_cleared' => (bool)$ok
		];
	}

	public function listEmdByPaymentDetailsId(int $airlinesPaymentDetailsId): array
	{
		if ($airlinesPaymentDetailsId <= 0) {
            throw new Exception('Valid airlines_payment_details_id is required', 400);
        }
		return $this->dal->getEmdByPaymentDetailsId($airlinesPaymentDetailsId);
	}
}


