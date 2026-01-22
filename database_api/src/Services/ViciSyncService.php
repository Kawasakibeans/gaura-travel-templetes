<?php
/**
 * Synchronise vicidial_closer_log to flylanka_flib_call.
 */

namespace App\Services;

use Exception;
use mysqli;

class ViciSyncService
{
    private const SRC_HOST = '124.43.65.14';
    private const SRC_USER = 'cron';
    private const SRC_PASS = '1234';
    private const SRC_DB = 'asterisk';

    private const DEST_HOST_ENV = 'DB_HOST';
    private const DEST_USER_ENV = 'DB_USER';
    private const DEST_PASS_ENV = 'DB_PASS';
    private const DEST_DB_ENV = 'DB_NAME';

    private const DEST_DEFAULT_HOST = 'localhost';
    private const DEST_DEFAULT_USER = 'gt1ybwhome_gtuser';
    private const DEST_DEFAULT_PASS = '3Ythyfghjr';
    private const DEST_DEFAULT_DB = 'gt1ybwhome_gt1';

    private const BATCH_SIZE_DEFAULT = 2000;
    private const TABLE_SRC = 'vicidial_closer_log';
    private const TABLE_DEST = 'flylanka_flib_call';

    /**
     * Run the sync process.
     *
     * @param array{batch_size?:int,max_batches?:int} $options
     * @return array<string, mixed>
     * @throws Exception
     */
    public function run(array $options = []): array
    {
        $batchSize = isset($options['batch_size']) ? max(1, (int)$options['batch_size']) : self::BATCH_SIZE_DEFAULT;
        $maxBatches = isset($options['max_batches']) ? max(1, (int)$options['max_batches']) : null;

        $src = $this->connect(self::SRC_HOST, self::SRC_USER, self::SRC_PASS, self::SRC_DB);
        $dest = $this->connect(
            getenv(self::DEST_HOST_ENV) ?: self::DEST_DEFAULT_HOST,
            getenv(self::DEST_USER_ENV) ?: self::DEST_DEFAULT_USER,
            getenv(self::DEST_PASS_ENV) ?: self::DEST_DEFAULT_PASS,
            getenv(self::DEST_DB_ENV) ?: self::DEST_DEFAULT_DB
        );

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $dest->set_charset('utf8mb4');
        $src->set_charset('utf8mb4');

        $maxDestId = $this->getMaxDestId($dest);
        $totalToCopy = $this->countSourceRows($src, $maxDestId);

        if ($totalToCopy === 0) {
            $src->close();
            $dest->close();
            return [
                'message' => 'Nothing to copy',
                'processed' => 0,
                'last_closecallid' => $maxDestId,
            ];
        }

        $select = $src->prepare("
            SELECT
                closecallid, lead_id, list_id, campaign_id, call_date,
                start_epoch, end_epoch, length_in_sec, status, phone_code,
                phone_number, `user`, comments, processed, queue_seconds,
                user_group, xfercallid, term_reason, uniqueid, agent_only,
                queue_position, called_count
            FROM " . self::TABLE_SRC . "
            WHERE closecallid > ?
            ORDER BY closecallid
            LIMIT ?
        ");

        $insert = $dest->prepare("
            INSERT INTO " . self::TABLE_DEST . " (
                closecallid, lead_id, list_id, campaign_id, call_date,
                start_epoch, end_epoch, length_in_sec, status, phone_code,
                phone_number, `user`, comments, processed, queue_seconds,
                user_group, xfercallid, term_reason, uniqueid, agent_only,
                queue_position, called_count
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?
            )
            ON DUPLICATE KEY UPDATE
                lead_id = VALUES(lead_id),
                list_id = VALUES(list_id),
                campaign_id = VALUES(campaign_id),
                call_date = VALUES(call_date),
                start_epoch = VALUES(start_epoch),
                end_epoch = VALUES(end_epoch),
                length_in_sec = VALUES(length_in_sec),
                status = VALUES(status),
                phone_code = VALUES(phone_code),
                phone_number = VALUES(phone_number),
                `user` = VALUES(`user`),
                comments = VALUES(comments),
                processed = VALUES(processed),
                queue_seconds = VALUES(queue_seconds),
                user_group = VALUES(user_group),
                xfercallid = VALUES(xfercallid),
                term_reason = VALUES(term_reason),
                uniqueid = VALUES(uniqueid),
                agent_only = VALUES(agent_only),
                queue_position = VALUES(queue_position),
                called_count = VALUES(called_count)
        ");

        $processed = 0;
        $batches = 0;
        $lastId = $maxDestId;

        while (true) {
            if ($maxBatches !== null && $batches >= $maxBatches) {
                break;
            }

            $select->bind_param('ii', $lastId, $batchSize);
            $select->execute();
            $result = $select->get_result();
            if ($result->num_rows === 0) {
                break;
            }

            $dest->begin_transaction();
            while ($row = $result->fetch_assoc()) {
                $insert->bind_param(
                    'iiissiiiisssssdsissssii',
                    $row['closecallid'],
                    $row['lead_id'],
                    $row['list_id'],
                    $row['campaign_id'],
                    $row['call_date'],
                    $row['start_epoch'],
                    $row['end_epoch'],
                    $row['length_in_sec'],
                    $row['status'],
                    $row['phone_code'],
                    $row['phone_number'],
                    $row['user'],
                    $row['comments'],
                    $row['processed'],
                    $row['queue_seconds'],
                    $row['user_group'],
                    $row['xfercallid'],
                    $row['term_reason'],
                    $row['uniqueid'],
                    $row['agent_only'],
                    $row['queue_position'],
                    $row['called_count']
                );
                $insert->execute();

                $processed++;
                $lastId = (int)$row['closecallid'];
            }
            $dest->commit();
            $batches++;

            if ($result->num_rows < $batchSize) {
                break;
            }
        }

        $select->close();
        $insert->close();
        $src->close();
        $dest->close();

        return [
            'message' => 'Sync completed',
            'processed' => $processed,
            'batches' => $batches,
            'remaining' => max(0, $totalToCopy - $processed),
            'last_closecallid' => $lastId,
        ];
    }

    private function connect(string $host, string $user, string $pass, string $db): mysqli
    {
        $conn = @new mysqli($host, $user, $pass, $db);
        if ($conn->connect_errno) {
            throw new Exception(sprintf('Database connection failed (%s/%s): %s', $host, $db, $conn->connect_error), 500);
        }
        return $conn;
    }

    private function getMaxDestId(mysqli $dest): int
    {
        $sql = "SELECT COALESCE(MAX(closecallid), 0) AS max_id FROM " . self::TABLE_DEST;
        $result = $dest->query($sql);
        $row = $result ? $result->fetch_assoc() : ['max_id' => 0];
        $result?->free();

        return (int)($row['max_id'] ?? 0);
    }

    private function countSourceRows(mysqli $src, int $afterId): int
    {
        $stmt = $src->prepare("
            SELECT COUNT(*) AS total
            FROM " . self::TABLE_SRC . "
            WHERE closecallid > ?
        ");
        $stmt->bind_param('i', $afterId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        return (int)($row['total'] ?? 0);
    }
}

