<?php
/**
 * Customer Info Data Access Layer
 * Handles database operations for customer info ETL operations
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class CustomerInfoDAL extends BaseDAL
{
    /**
     * Check if CRN exists
     */
    public function crnExists($crn)
    {
        $query = "SELECT 1 FROM wpk4_backend_customer_info WHERE crn = :crn LIMIT 1";
        $result = $this->queryOne($query, ['crn' => $crn]);
        return $result !== false;
    }

    /**
     * Get existing CRN by email or phone
     */
    public function getExistingCrnByEmailPhone($email, $phone)
    {
        $email = $email ? strtolower(trim($email)) : '';
        $phone = $phone ? trim($phone) : '';
        
        if ($email === '' && $phone === '') {
            return null;
        }

        $conditions = [];
        $params = [];
        
        if ($email !== '') {
            $conditions[] = "(email IS NOT NULL AND LOWER(TRIM(email)) = LOWER(TRIM(:email)))";
            $params['email'] = $email;
        }
        
        if ($phone !== '') {
            $conditions[] = "(phone IS NOT NULL AND phone = :phone)";
            $params['phone'] = $phone;
        }
        
        $query = "
            SELECT crn FROM wpk4_backend_customer_info
            WHERE " . implode(' OR ', $conditions) . "
            LIMIT 1
        ";
        
        $result = $this->queryOne($query, $params);
        return $result ? $result['crn'] : null;
    }

    /**
     * Insert customer contact (with duplicate check)
     */
    public function insertContactStrict($data)
    {
        $email = isset($data['email']) ? strtolower(trim($data['email'])) : '';
        $phone = isset($data['phone']) ? trim($data['phone']) : '';
        
        $existing = $this->getExistingCrnByEmailPhone($email ?: null, $phone ?: null);
        if ($existing) {
            return $existing;
        }
        
        if ($email === '' && $phone === '') {
            return false;
        }

        // Generate unique CRN
        do {
            $crn = $this->generateUUID();
            $exists = $this->crnExists($crn);
        } while ($exists);

        $conditions = [];
        $params = [
            'crn' => $crn,
            'firebase_uid' => $data['firebase_uid'] ?? null,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'fname' => $data['fname'] ?? null,
            'lname' => $data['lname'] ?? null,
            'gender' => $data['gender'] ?? null,
            'dob' => $data['dob'] ?? null,
            'lifecycle' => $data['lifecycle'] ?? null,
            'retention' => $data['retention'] ?? null
        ];

        if ($email !== '' && $phone !== '') {
            $conditions[] = "( (email IS NOT NULL AND LOWER(TRIM(email)) = LOWER(TRIM(:email))) OR (phone IS NOT NULL AND phone = :phone) )";
            $params['email_check'] = $email;
            $params['phone_check'] = $phone;
        } elseif ($email !== '') {
            $conditions[] = "(email IS NOT NULL AND LOWER(TRIM(email)) = LOWER(TRIM(:email)))";
            $params['email_check'] = $email;
        } else {
            $conditions[] = "(phone IS NOT NULL AND phone = :phone)";
            $params['phone_check'] = $phone;
        }

        $query = "
            INSERT INTO wpk4_backend_customer_info
            (crn, firebase_uid, email, phone, fname, lname, gender, dob, lifecycle, retention, created_at, updated_at)
            SELECT
                :crn, :firebase_uid, :email, :phone, :fname, :lname, :gender, :dob, :lifecycle, :retention, NOW(), NOW()
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM wpk4_backend_customer_info
                WHERE " . implode(' OR ', $conditions) . "
            )
        ";

        $n = $this->execute($query, $params);
        if ($n === 1) {
            return $crn;
        }

        // Check again if it was inserted by another process
        $existing = $this->getExistingCrnByEmailPhone($email ?: null, $phone ?: null);
        return $existing ?: false;
    }

    /**
     * Get PAX records by date window
     */
    public function getPaxByDateWindow($from, $to)
    {
        $query = "
            SELECT order_id, order_date, fname, lname, gender, dob, email_pax, phone_pax
            FROM wpk4_backend_travel_booking_pax
            WHERE order_date BETWEEN :from AND :to
        ";
        return $this->query($query, ['from' => $from, 'to' => $to]);
    }

    /**
     * Get event log records by date window
     */
    public function getEventLogByDateWindow($from, $to)
    {
        $query = "
            SELECT auto_id, email_id, user_unique_id, added_on, user as name, meta_value
            FROM wpk4_customer_event_log
            WHERE added_on BETWEEN :from AND :to
        ";
        return $this->query($query, ['from' => $from, 'to' => $to]);
    }

    /**
     * Get inbound call records by date window
     */
    public function getInboundCallsByDateWindow($from, $to)
    {
        $query = "
            SELECT record_id, call_date, ani_acode, ani_phone, ani_country_id
            FROM wpk4_backend_agent_nobel_data_inboundcall_rec
            WHERE call_date BETWEEN :from AND :to
        ";
        return $this->query($query, ['from' => $from, 'to' => $to]);
    }

    /**
     * Get quote records by date window
     */
    public function getQuotesByDateWindow($from, $to)
    {
        $query = "
            SELECT id, email, phone_num, quoted_at, name
            FROM wpk4_quote
            WHERE quoted_at BETWEEN :from AND :to
        ";
        return $this->query($query, ['from' => $from, 'to' => $to]);
    }

    /**
     * Update PAX CRN by order_id
     */
    public function updatePaxCrnByOrderId($crn, $orderId)
    {
        $query = "
            UPDATE wpk4_backend_travel_booking_pax
            SET crn = :crn
            WHERE order_id = :order_id
        ";
        return $this->execute($query, ['crn' => $crn, 'order_id' => $orderId]);
    }

    /**
     * Update event log CRN by order and email
     */
    public function updateEventLogCrnByOrderEmail($crn, $from, $to, $email, $orderId)
    {
        $query = "
            UPDATE wpk4_customer_event_log
            SET crn = :crn
            WHERE added_on BETWEEN :from AND :to
            AND (crn IS NULL OR crn = '')
            AND email_id = :email
            AND (
                meta_value LIKE CONCAT('%order_id=', :order_id, '%')
                OR meta_value REGEXP CONCAT('(^|[?&])order_id=', :order_id)
            )
        ";
        return $this->execute($query, [
            'crn' => $crn,
            'from' => $from,
            'to' => $to,
            'email' => $email,
            'order_id' => $orderId
        ]);
    }

    /**
     * Update event log CRN by order only
     */
    public function updateEventLogCrnByOrder($crn, $from, $to, $orderId)
    {
        $query = "
            UPDATE wpk4_customer_event_log
            SET crn = :crn
            WHERE added_on BETWEEN :from AND :to
            AND (crn IS NULL OR crn = '')
            AND (
                meta_value LIKE CONCAT('%order_id=', :order_id, '%')
                OR meta_value REGEXP CONCAT('(^|[?&])order_id=', :order_id)
            )
        ";
        return $this->execute($query, [
            'crn' => $crn,
            'from' => $from,
            'to' => $to,
            'order_id' => $orderId
        ]);
    }

    /**
     * Update quote CRN by email/phone
     */
    public function updateQuoteCrn($crn, $from, $to, $email, $phone)
    {
        $conditions = [];
        $params = ['crn' => $crn, 'from' => $from, 'to' => $to];
        
        if ($email !== '') {
            $conditions[] = "email = :email";
            $params['email'] = $email;
        }
        
        if ($phone !== '') {
            $conditions[] = "phone_num = :phone";
            $params['phone'] = $phone;
        }
        
        if (empty($conditions)) {
            return 0;
        }
        
        $query = "
            UPDATE wpk4_quote
            SET crn = :crn
            WHERE quoted_at BETWEEN :from AND :to
            AND (crn IS NULL OR crn = '')
            AND (" . implode(' OR ', $conditions) . ")
        ";
        
        return $this->execute($query, $params);
    }

    /**
     * Update inbound call CRN by phone
     */
    public function updateInboundCrnByPhone($crn, $from, $to, $phone)
    {
        $query = "
            UPDATE wpk4_backend_agent_nobel_data_inboundcall_rec
            SET crn = :crn
            WHERE call_date BETWEEN :from AND :to
            AND (crn IS NULL OR crn = '')
            AND CONCAT('61', ani_acode, ani_phone) = :phone
        ";
        return $this->execute($query, [
            'crn' => $crn,
            'from' => $from,
            'to' => $to,
            'phone' => $phone
        ]);
    }

    /**
     * Update event log CRN by email only
     */
    public function updateEventLogCrnByEmail($crn, $from, $to, $email)
    {
        $query = "
            UPDATE wpk4_customer_event_log
            SET crn = :crn
            WHERE added_on BETWEEN :from AND :to
            AND (crn IS NULL OR crn = '')
            AND email_id = :email
        ";
        return $this->execute($query, [
            'crn' => $crn,
            'from' => $from,
            'to' => $to,
            'email' => $email
        ]);
    }

    /**
     * Generate UUID v4
     */
    private function generateUUID()
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);
        return vsprintf('%s%s%s%s-%s%s-%s%s-%s%s-%s%s%s%s%s%s', str_split($hex, 2));
    }
}

