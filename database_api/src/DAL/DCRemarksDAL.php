<?php
namespace App\DAL;

class DCRemarksDAL extends BaseDAL
{
    /**
     * Fetch DC remarks filtered by case_id and reservation_ref.
     *
     * @param string $caseId
     * @param string $reservationRef
     * @return array
     */
    public function getRemarks(string $caseId, string $reservationRef): array
    {
        $sql = "
            SELECT *
            FROM wpk4_backend_dc_remark
            WHERE case_id = ?
              AND reservation_ref = ?
            ORDER BY created_on DESC
        ";

        return $this->query($sql, [$caseId, $reservationRef]);
    }

    /**
     * Insert a new DC remark record.
     */
    public function createRemark(
        string $caseId,
        string $reservationRef,
        string $remark,
        ?string $requestType,
        ?string $failedReason,
        string $createdBy,
        string $remarkType,
        string $createdOn
    ): array {
        $sql = "
            INSERT INTO wpk4_backend_dc_remark
                (case_id, reservation_ref, remark, request_type, failed_reason, created_by, created_on, remark_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $this->execute($sql, [
            $caseId,
            $reservationRef,
            $remark,
            $requestType,
            $failedReason,
            $createdBy,
            $createdOn,
            $remarkType,
        ]);

        $row = $this->queryOne(
            "SELECT * FROM wpk4_backend_dc_remark WHERE case_id = ? AND reservation_ref = ? ORDER BY created_on DESC LIMIT 1",
            [$caseId, $reservationRef]
        );

        return $row ?? [];
    }
}


