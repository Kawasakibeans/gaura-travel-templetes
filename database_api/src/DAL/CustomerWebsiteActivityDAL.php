<?php
/**
 * Customer Website Activity Data Access Layer
 * Handles database operations for customer website activity updates
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class CustomerWebsiteActivityDAL extends BaseDAL
{
    /**
     * Get event log records by date window
     */
    public function getEventLogByDateWindow($from, $to)
    {
        $query = "
            SELECT auto_id, event, meta_key, meta_value, page, added_on, email_id
            FROM wpk4_customer_event_log
            WHERE added_on BETWEEN :from AND :to
            ORDER BY added_on ASC
        ";
        return $this->query($query, ['from' => $from, 'to' => $to]);
    }

    /**
     * Get customer CRN by email
     */
    public function getCrnByEmail($emails)
    {
        if (empty($emails)) {
            return [];
        }

        $chunks = array_chunk($emails, 1000);
        $results = [];
        
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $query = "
                SELECT LOWER(TRIM(email)) AS e, crn
                FROM wpk4_backend_customer_info
                WHERE LOWER(TRIM(email)) IN ({$placeholders})
            ";
            $chunkResults = $this->query($query, $chunk);
            foreach ($chunkResults as $row) {
                $results[strtolower(trim($row['e']))] = $row['crn'];
            }
        }
        
        return $results;
    }

    /**
     * Check if activity_id exists
     */
    public function activityIdExists($activityId)
    {
        $query = "
            SELECT 1 FROM wpk4_backend_customer_website_activity
            WHERE activity_id = :activity_id
            LIMIT 1
        ";
        $result = $this->queryOne($query, ['activity_id' => $activityId]);
        return $result !== false;
    }

    /**
     * Check if activity exists by CRN, type, and date
     */
    public function activityExists($crn, $activityType, $activityDate)
    {
        $query = "
            SELECT 1 FROM wpk4_backend_customer_website_activity
            WHERE crn <=> :crn
            AND activity_type <=> :activity_type
            AND activity_date <=> :activity_date
            LIMIT 1
        ";
        $result = $this->queryOne($query, [
            'crn' => $crn,
            'activity_type' => $activityType,
            'activity_date' => $activityDate
        ]);
        return $result !== false;
    }

    /**
     * Insert website activity record
     */
    public function insertActivity($data)
    {
        $columns = [];
        $values = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            if ($key === 'auto_id') continue;
            $columns[] = "`{$key}`";
            $values[] = ":{$key}";
            $params[$key] = $value;
        }
        
        $query = "
            INSERT INTO wpk4_backend_customer_website_activity
            (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ";
        
        return $this->execute($query, $params);
    }

    /**
     * Update website activity record
     */
    public function updateActivity($crn, $activityType, $activityDate, $data)
    {
        $set = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            if ($key === 'auto_id' || $key === 'crn' || $key === 'activity_type' || $key === 'activity_date') {
                continue;
            }
            if ($key === 'activity_id' && empty($value)) {
                continue;
            }
            $set[] = "`{$key}` = COALESCE(:{$key}, `{$key}`)";
            $params[$key] = $value;
        }
        
        $params['crn'] = $crn;
        $params['activity_type'] = $activityType;
        $params['activity_date'] = $activityDate;
        
        $query = "
            UPDATE wpk4_backend_customer_website_activity
            SET " . implode(', ', $set) . "
            WHERE crn <=> :crn
            AND activity_type <=> :activity_type
            AND activity_date <=> :activity_date
        ";
        
        return $this->execute($query, $params);
    }

    /**
     * Update sessions CRN from event log
     */
    public function updateSessionsCrnFromEventLog($from, $to)
    {
        // Check if gaura_visitor_id column exists
        $columns = $this->getEventLogColumns();
        $hasVid = isset($columns['gaura_visitor_id']);
        $hasCrn = isset($columns['crn']);
        $hasUid = isset($columns['user_unique_id']);
        $hasEmail = isset($columns['email_id']);
        
        if (!$hasVid) {
            return ['ok' => false, 'updated' => 0, 'reason' => 'gaura_visitor_id missing in event_log'];
        }
        
        // Get visitor IDs that need updating
        $query = "
            SELECT DISTINCT el.gaura_visitor_id
            FROM wpk4_customer_event_log el
            JOIN wpk4_backend_customer_attribution_sessions s
                ON s.visitor_id = el.gaura_visitor_id
                AND (s.crn IS NULL OR s.crn = '')
            WHERE el.gaura_visitor_id IS NOT NULL
            AND el.gaura_visitor_id <> ''
            AND el.added_on BETWEEN :from AND :to
        ";
        $visitorIds = $this->query($query, ['from' => $from, 'to' => $to]);
        
        if (empty($visitorIds)) {
            return ['ok' => true, 'updated' => 0, 'filtered' => 0, 'mapped' => 0];
        }
        
        $vidList = array_column($visitorIds, 'gaura_visitor_id');
        $placeholders = implode(',', array_fill(0, count($vidList), '?'));
        
        // Build join conditions dynamically
        $joinUid = $hasUid
            ? "LEFT JOIN wpk4_backend_customer_info ci_uid ON ci_uid.firebase_uid = el.user_unique_id"
            : "LEFT JOIN wpk4_backend_customer_info ci_uid ON 1=0";
        
        $joinEmail = $hasEmail
            ? "LEFT JOIN wpk4_backend_customer_info ci_email ON LOWER(TRIM(ci_email.email)) = LOWER(TRIM(el.email_id))"
            : "LEFT JOIN wpk4_backend_customer_info ci_email ON 1=0";
        
        $elCrnExpr = $hasCrn ? "NULLIF(TRIM(el.crn), '')" : "NULL";
        
        // Get CRN mapping
        $query = "
            SELECT
                el.gaura_visitor_id AS vid,
                COALESCE({$elCrnExpr}, ci_uid.crn, ci_email.crn) AS crn
            FROM wpk4_customer_event_log el
            {$joinUid}
            {$joinEmail}
            WHERE el.gaura_visitor_id IN ({$placeholders})
            AND el.added_on BETWEEN :from AND :to
            GROUP BY el.gaura_visitor_id
            HAVING crn IS NOT NULL AND crn <> ''
        ";
        
        $params = array_merge($vidList, ['from' => $from, 'to' => $to]);
        $crnMap = $this->query($query, $params);
        
        if (empty($crnMap)) {
            return ['ok' => true, 'updated' => 0, 'filtered' => count($vidList), 'mapped' => 0];
        }
        
        // Update sessions
        $updated = 0;
        foreach ($crnMap as $row) {
            $query = "
                UPDATE wpk4_backend_customer_attribution_sessions
                SET crn = :crn, updated_at = NOW()
                WHERE visitor_id = :visitor_id
                AND (crn IS NULL OR crn = '')
            ";
            $updated += $this->execute($query, [
                'crn' => $row['crn'],
                'visitor_id' => $row['vid']
            ]);
        }
        
        return ['ok' => true, 'updated' => $updated, 'filtered' => count($vidList), 'mapped' => count($crnMap)];
    }

    /**
     * Get event log columns
     */
    public function getEventLogColumns()
    {
        $query = "SHOW COLUMNS FROM wpk4_customer_event_log";
        $results = $this->query($query);
        $columns = [];
        foreach ($results as $row) {
            $columns[strtolower($row['Field'])] = true;
        }
        return $columns;
    }
}

