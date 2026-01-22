<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class AirlinesPaymentDetailsDAL extends BaseDAL
{
	/**
	 * Update a single field for an airlines payment details row by auto_id.
	 * Column name is validated against a whitelist to prevent SQL injection.
	 */
	public function updateFieldById(int $autoId, string $columnName, $value): bool
	{

		$sql = "UPDATE wpk4_backend_airlines_payment_details SET `$columnName` = :val WHERE auto_id = :auto_id";
		return $this->execute($sql, [
			'val' => $value,
			'auto_id' => $autoId
		]);
	}

	/**
	 * Atomically update deposit_last_modified_on/by for a row by auto_id.
	 */
	public function updateDepositLastModified(int $autoId, string $modifiedOn, string $modifiedBy): bool
	{
		$sql = "
			UPDATE wpk4_backend_airlines_payment_details
			SET deposit_last_modified_on = :modified_on,
			    deposit_last_modified_by = :modified_by
			WHERE auto_id = :auto_id
		";
		return $this->execute($sql, [
			'modified_on' => $modifiedOn,
			'modified_by' => $modifiedBy,
			'auto_id' => $autoId
		]);
	}

	/**
	 * Atomically update paid_last_modified_on/by for a row by auto_id.
	 */
	public function updatePaidLastModified(int $autoId, string $modifiedOn, string $modifiedBy): bool
	{

		$sql = "
			UPDATE wpk4_backend_airlines_payment_details
			SET paid_last_modified_on = :modified_on,
			    paid_last_modified_by = :modified_by
			WHERE auto_id = :auto_id
		";
		return $this->execute($sql, [
			'modified_on' => $modifiedOn,
			'modified_by' => $modifiedBy,
			'auto_id' => $autoId
		]);
	}

	/**
	 * Atomically update ticketed_last_modified_on/by for a row by auto_id.
	 */
	public function updateTicketedLastModified(int $autoId, string $modifiedOn, string $modifiedBy): bool
	{

		$sql = "
			UPDATE wpk4_backend_airlines_payment_details
			SET ticketed_last_modified_on = :modified_on,
			    ticketed_last_modified_by = :modified_by
			WHERE auto_id = :auto_id
		";
		return $this->execute($sql, [
			'modified_on' => $modifiedOn,
			'modified_by' => $modifiedBy,
			'auto_id' => $autoId
		]);
	}

	/**
	 * Atomically update refund_updated_on/by for a row by auto_id.
	 */
	public function updateRefundUpdated(int $autoId, string $updatedOn, string $updatedBy): bool
	{
		$sql = "
			UPDATE wpk4_backend_airlines_payment_details
			SET refund_updated_on = :updated_on,
			    refund_updated_by = :updated_by
			WHERE auto_id = :auto_id
		";
		return $this->execute($sql, [
			'updated_on' => $updatedOn,
			'updated_by' => $updatedBy,
			'auto_id' => $autoId
		]);
	}

	/**
	 * Atomically update split_updated_on/by for a row by auto_id.
	 */
	public function updateSplitUpdated(int $autoId, string $updatedOn, string $updatedBy): bool
	{
		$sql = "
			UPDATE wpk4_backend_airlines_payment_details
			SET split_updated_on = :updated_on,
			    split_updated_by = :updated_by
			WHERE auto_id = :auto_id
		";
		return $this->execute($sql, [
			'updated_on' => $updatedOn,
			'updated_by' => $updatedBy,
			'auto_id' => $autoId
		]);
	}

	/**
	 * Atomically update done_updated_on/by for a row by auto_id.
	 */
	public function updateDoneUpdated(int $autoId, string $updatedOn, string $updatedBy): bool
	{
		$sql = "
			UPDATE wpk4_backend_airlines_payment_details
			SET done_updated_on = :updated_on,
			    done_updated_by = :updated_by
			WHERE auto_id = :auto_id
		";
		return $this->execute($sql, [
			'updated_on' => $updatedOn,
			'updated_by' => $updatedBy,
			'auto_id' => $autoId
		]);
	}

	/**
	 * Get a single airlines payment details row by auto_id
	 */
	public function getById(int $autoId): ?array
	{
		$sql = "SELECT * FROM wpk4_backend_airlines_payment_details WHERE auto_id = :auto_id LIMIT 1";
		$row = $this->queryOne($sql, ['auto_id' => $autoId]);
		return $row ?: null;
	}

	/**
	 * Insert an EMD payment record
	 */
	public function insertEmdPayment(
		int $airlinesPaymentDetailsId,
		string $pnr,
		string $paymentType,
		string $paymentDate,
		string $paymentBy,
		string $emdReference,
		$amount,
		?string $description,
		string $addedBy
	): int {
		$sql = "
			INSERT INTO wpk4_backend_airlines_payment_details_emd
				(airlines_payment_details_id, pnr, payment_type, payment_date, payment_by, emd_reference, amount, description, added_by)
			VALUES
				(:airlines_payment_details_id, :pnr, :payment_type, :payment_date, :payment_by, :emd_reference, :amount, :description, :added_by)
		";
		$this->execute($sql, [
			'airlines_payment_details_id' => $airlinesPaymentDetailsId,
			'pnr' => $pnr,
			'payment_type' => $paymentType,
			'payment_date' => $paymentDate,
			'payment_by' => $paymentBy,
			'emd_reference' => $emdReference,
			'amount' => $amount,
			'description' => $description,
			'added_by' => $addedBy
		]);
		return (int)$this->lastInsertId();
	}

	/**
	 * Get all airlines payment details by group_name
	 */
	public function getByGroupName(string $groupName): array
	{

		$sql = "SELECT * FROM wpk4_backend_airlines_payment_details WHERE group_name = :group_name";
		return $this->query($sql, ['group_name' => $groupName]);
	}

	/**
	 * Insert into airlines payment details
	 */
	public function insertAirlinesPaymentDetails(
		string $groupName,
		string $pnr,
		string $groupId,
		string $depositDeadline,
		string $paymentDeadline,
		string $ticketingDeadline,
		$depositAmount,
		$outstandingAmount,
		string $emd,
		$totalAmount,
		string $status
	): int {
		$sql = "
			INSERT INTO wpk4_backend_airlines_payment_details
				(group_name, pnr, group_id, deposit_deadline, payment_deadline, ticketing_deadline, deposit_amount, outstanding_amount, emd, total_amount, status)
			VALUES
				(:group_name, :pnr, :group_id, :deposit_deadline, :payment_deadline, :ticketing_deadline, :deposit_amount, :outstanding_amount, :emd, :total_amount, :status)
		";
		$this->execute($sql, [
			'group_name' => $groupName,
			'pnr' => $pnr,
			'group_id' => $groupId,
			'deposit_deadline' => $depositDeadline,
			'payment_deadline' => $paymentDeadline,
			'ticketing_deadline' => $ticketingDeadline,
			'deposit_amount' => $depositAmount,
			'outstanding_amount' => $outstandingAmount,
			'emd' => $emd,
			'total_amount' => $totalAmount,
			'status' => $status
		]);
		return (int)$this->lastInsertId();
	}

	/**
	 * Clear deposit_deadline by pnr and group_id
	 */
	public function clearDepositDeadline(string $pnr, string $groupId): bool
	{
		$sql = "
			UPDATE wpk4_backend_airlines_payment_details
			SET deposit_deadline = NULL
			WHERE pnr = :pnr AND group_id = :group_id
		";
		return $this->execute($sql, [
			'pnr' => $pnr,
			'group_id' => $groupId
		]);
	}

	/**
	 * Clear payment_deadline by pnr and group_id
	 */
	public function clearPaymentDeadline(string $pnr, string $groupId): bool
	{
		$sql = "
			UPDATE wpk4_backend_airlines_payment_details
			SET payment_deadline = NULL
			WHERE pnr = :pnr AND group_id = :group_id
		";
		return $this->execute($sql, [
			'pnr' => $pnr,
			'group_id' => $groupId
		]);
	}

	/**
	 * Clear ticketing_deadline by pnr and group_id
	 */
	public function clearTicketingDeadline(string $pnr, string $groupId): bool
	{
		$sql = "
			UPDATE wpk4_backend_airlines_payment_details
			SET ticketing_deadline = NULL
			WHERE pnr = :pnr AND group_id = :group_id
		";
		return $this->execute($sql, [
			'pnr' => $pnr,
			'group_id' => $groupId
		]);
	}

	/**
	 * Get EMD records by airlines_payment_details_id ordered by added_on DESC
	 */
	public function getEmdByPaymentDetailsId(int $airlinesPaymentDetailsId): array
	{
		$sql = "
			SELECT *
			FROM wpk4_backend_airlines_payment_details_emd
			WHERE airlines_payment_details_id = :id
			ORDER BY added_on DESC
		";
		return $this->query($sql, ['id' => $airlinesPaymentDetailsId]);
	}
}


