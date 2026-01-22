<?php
namespace App\DAL;

use Exception;

class ManualBookingDAL extends BaseDAL
{
    public function getLatestOrderId(): ?int
    {
        $sql = "
            SELECT order_id
            FROM wpk4_backend_travel_bookings
            ORDER BY order_id DESC
            LIMIT 1
        ";

        $row = $this->queryOne($sql);
        if (!$row || !isset($row['order_id'])) {
            return null;
        }

        return (int)$row['order_id'];
    }

    public function passengerExists(string $pnr, string $firstName, string $lastName): bool
    {
        $sql = "
            SELECT 1
            FROM wpk4_backend_travel_booking_pax
            WHERE pnr = :pnr
              AND lname LIKE CONCAT('%', :last_name, '%')
              AND fname LIKE CONCAT('%', :first_name, '%')
            LIMIT 1
        ";

        $row = $this->queryOne($sql, [
            ':pnr' => $pnr,
            ':last_name' => $lastName,
            ':first_name' => $firstName,
        ]);

        return !empty($row);
    }

    public function insertRecords(array $records, string $addedBy): int
    {
        if (empty($records)) {
            return 0;
        }

        $bookingSql = "
            INSERT INTO wpk4_backend_travel_bookings
                (order_type, order_id, order_date, t_type, travel_date, return_date,
                 payment_status, total_pax, source, agent_info, added_on, added_by)
            VALUES
                (:order_type, :order_id, :order_date, :t_type, :travel_date, :return_date,
                 :payment_status, :total_pax, :source, :agent_info, :added_on, :added_by)
        ";

        $paxSql = "
            INSERT INTO wpk4_backend_travel_booking_pax
                (order_type, order_id, order_date, salutation, fname, lname, gender,
                 dob, email_pax, pnr, added_on, added_by)
            VALUES
                (:order_type, :order_id, :order_date, :salutation, :fname, :lname, :gender,
                 :dob, :email_pax, :pnr, :added_on, :added_by)
        ";

        $bookingStmt = $this->db->prepare($bookingSql);
        $paxStmt = $this->db->prepare($paxSql);

        try {
            $this->beginTransaction();

            foreach ($records as $record) {
                // Convert empty strings to null for date fields (database doesn't accept empty strings for date/datetime)
                $travelDate = $record['travel_date'] ?? null;
                if ($travelDate === '') {
                    $travelDate = null;
                }

                $returnDate = $record['return_date'] ?? null;
                if ($returnDate === '') {
                    $returnDate = null;
                }

                $bookingStmt->execute([
                    ':order_type' => 'gds',
                    ':order_id' => $record['order_id'],
                    ':order_date' => $record['order_date'],
                    ':t_type' => $record['journey_type'],
                    ':travel_date' => $travelDate,
                    ':return_date' => $returnDate,
                    ':payment_status' => 'partially_paid',
                    ':total_pax' => $record['total_pax'],
                    ':source' => 'import',
                    ':agent_info' => '',
                    ':added_on' => $record['order_date'],
                    ':added_by' => $addedBy,
                ]);

                $paxStmt->execute([
                    ':order_type' => 'gds',
                    ':order_id' => $record['order_id'],
                    ':order_date' => $record['order_date'],
                    ':salutation' => $record['salutation'],
                    ':fname' => $record['first_name'],
                    ':lname' => $record['last_name'],
                    ':gender' => $record['gender'],
                    ':dob' => $record['dob'],
                    ':email_pax' => $record['email'],
                    ':pnr' => $record['pnr'],
                    ':added_on' => $record['order_date'],
                    ':added_by' => $addedBy,
                ]);
            }

            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }

        return count($records);
    }
}


