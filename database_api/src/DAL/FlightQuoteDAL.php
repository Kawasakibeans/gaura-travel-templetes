<?php
/**
 * Flight Quote Data Access Layer
 * Handles database operations for flight quote backend
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class FlightQuoteDAL extends BaseDAL
{
    /**
     * Create a regular quote
     */
    public function createQuote(array $data): int
    {
        $sql = "
            INSERT INTO wpk4_quote (
                user_id, current_price, depart_apt, dest_apt, depart_date, return_date,
                adult_count, child_count, infant_count, total_pax, from_country, to_country,
                to_product_id, return_product_id, url, is_gdeals, name, email, phone_num,
                tsr, call_record_id, adult_price, child_price, infant_price, total_price,
                depart_time, return_time, airline_code, is_multicity
            ) VALUES (
                :user_id, :current_price, :depart_apt, :dest_apt, :depart_date, :return_date,
                :adult_count, :child_count, :infant_count, :total_pax, :from_country, :to_country,
                :to_product_id, :return_product_id, :url, :is_gdeals, :name, :email, :phone_num,
                :tsr, :call_record_id, :adult_price, :child_price, :infant_price, :total_price,
                :depart_time, :return_time, :airline_code, :is_multicity
            )
        ";
        
        $params = [
            ':user_id' => $data['user_id'] ?? null,
            ':current_price' => $data['current_price'] ?? 0,
            ':depart_apt' => $data['depart_apt'] ?? '',
            ':dest_apt' => $data['dest_apt'] ?? '',
            ':depart_date' => $data['depart_date'] ?? null,
            ':return_date' => $data['return_date'] ?? null,
            ':adult_count' => $data['adult_count'] ?? 0,
            ':child_count' => $data['child_count'] ?? 0,
            ':infant_count' => $data['infant_count'] ?? 0,
            ':total_pax' => $data['total_pax'] ?? 0,
            ':from_country' => $data['from_country'] ?? '',
            ':to_country' => $data['to_country'] ?? '',
            ':to_product_id' => $data['to_product_id'] ?? null,
            ':return_product_id' => $data['return_product_id'] ?? null,
            ':url' => $data['url'] ?? '',
            ':is_gdeals' => $data['is_gdeals'] ?? 0,
            ':name' => $data['name'] ?? '',
            ':email' => $data['email'] ?? '',
            ':phone_num' => $data['phone_num'] ?? '',
            ':tsr' => $data['tsr'] ?? '',
            ':call_record_id' => $data['call_record_id'] ?? '',
            ':adult_price' => $data['adult_price'] ?? $data['current_price'] ?? 0,
            ':child_price' => $data['child_price'] ?? 0,
            ':infant_price' => $data['infant_price'] ?? 0,
            ':total_price' => $data['total_price'] ?? 0,
            ':depart_time' => $data['depart_time'] ?? '',
            ':return_time' => $data['return_time'] ?? '',
            ':airline_code' => $data['airline_code'] ?? '',
            ':is_multicity' => $data['is_multicity'] ?? 0
        ];
        
        $this->execute($sql, $params);
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * Create a multicity quote
     */
    public function createMulticityQuote(array $data): int
    {
        $sql = "
            INSERT INTO wpk4_quote_multicity (
                id, user_id, current_price, lowest_price, adult_count, child_count, infant_count,
                total_pax, from_country, to_country, url, is_gdeals, name, email, phone_num,
                tsr, call_record_id, adult_price, child_price, infant_price, total_price,
                airline_code, depart_time, return_time, return_date,
                depart_apt1, dest_apt1, depart_date1, product_id1,
                depart_apt2, dest_apt2, depart_date2, product_id2,
                depart_apt3, dest_apt3, depart_date3, product_id3,
                depart_apt4, dest_apt4, depart_date4, product_id4
            ) VALUES (
                :id, :user_id, :current_price, :lowest_price, :adult_count, :child_count, :infant_count,
                :total_pax, :from_country, :to_country, :url, :is_gdeals, :name, :email, :phone_num,
                :tsr, :call_record_id, :adult_price, :child_price, :infant_price, :total_price,
                :airline_code, :depart_time, :return_time, :return_date,
                :depart_apt1, :dest_apt1, :depart_date1, :product_id1,
                :depart_apt2, :dest_apt2, :depart_date2, :product_id2,
                :depart_apt3, :dest_apt3, :depart_date3, :product_id3,
                :depart_apt4, :dest_apt4, :depart_date4, :product_id4
            )
        ";
        
        $params = [
            ':id' => $data['id'] ?? null,
            ':user_id' => $data['user_id'] ?? null,
            ':current_price' => $data['current_price'] ?? 0,
            ':lowest_price' => $data['lowest_price'] ?? $data['current_price'] ?? 0,
            ':adult_count' => $data['adult_count'] ?? 0,
            ':child_count' => $data['child_count'] ?? 0,
            ':infant_count' => $data['infant_count'] ?? 0,
            ':total_pax' => $data['total_pax'] ?? 0,
            ':from_country' => $data['from_country'] ?? '',
            ':to_country' => $data['to_country'] ?? '',
            ':url' => $data['url'] ?? '',
            ':is_gdeals' => $data['is_gdeals'] ?? 0,
            ':name' => $data['name'] ?? '',
            ':email' => $data['email'] ?? '',
            ':phone_num' => $data['phone_num'] ?? '',
            ':tsr' => $data['tsr'] ?? '',
            ':call_record_id' => $data['call_record_id'] ?? '',
            ':adult_price' => $data['adult_price'] ?? $data['current_price'] ?? 0,
            ':child_price' => $data['child_price'] ?? 0,
            ':infant_price' => $data['infant_price'] ?? 0,
            ':total_price' => $data['total_price'] ?? 0,
            ':airline_code' => $data['airline_code'] ?? '',
            ':depart_time' => $data['depart_time'] ?? '',
            ':return_time' => $data['return_time'] ?? '',
            ':return_date' => $data['return_date'] ?? null,
            ':depart_apt1' => $data['depart_apt1'] ?? null,
            ':dest_apt1' => $data['dest_apt1'] ?? null,
            ':depart_date1' => $data['depart_date1'] ?? null,
            ':product_id1' => $data['product_id1'] ?? null,
            ':depart_apt2' => $data['depart_apt2'] ?? null,
            ':dest_apt2' => $data['dest_apt2'] ?? null,
            ':depart_date2' => $data['depart_date2'] ?? null,
            ':product_id2' => $data['product_id2'] ?? null,
            ':depart_apt3' => $data['depart_apt3'] ?? null,
            ':dest_apt3' => $data['dest_apt3'] ?? null,
            ':depart_date3' => $data['depart_date3'] ?? null,
            ':product_id3' => $data['product_id3'] ?? null,
            ':depart_apt4' => $data['depart_apt4'] ?? null,
            ':dest_apt4' => $data['dest_apt4'] ?? null,
            ':depart_date4' => $data['depart_date4'] ?? null,
            ':product_id4' => $data['product_id4'] ?? null
        ];
        
        $this->execute($sql, $params);
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * Get passenger info by phone number
     */
    public function getPassengerByPhone(string $phone): ?array
    {
        $sql = "
            SELECT CONCAT(fname, ' ', lname) AS full_name, email_address 
            FROM wpk4_backend_travel_passenger 
            WHERE phone_number = :phone 
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [':phone' => $phone]);
        
        if ($result !== false && is_array($result)) {
            return $result;
        }
        
        // If not found in passenger table, check quote table
        $sql = "
            SELECT name AS full_name, email as email_address
            FROM wpk4_quote
            WHERE phone_num = :phone
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [':phone' => $phone]);
        if ($result === false || !is_array($result)) {
            return null;
        }
        return $result;
    }
    
    /**
     * Get Nobel data by phone (last 8 digits)
     */
    public function getNobelDataByPhone(string $last8): ?array
    {
        $sql = "
            SELECT d_record_id as call_record_id, tsr 
            FROM wpk4_backend_agent_nobel_data_inboundcall_rec_quote 
            WHERE ani_phone = :last8
            ORDER BY rowid DESC 
            LIMIT 1
        ";
        
        $result = $this->queryOne($sql, [':last8' => $last8]);
        if ($result === false || !is_array($result)) {
            return null;
        }
        return $result;
    }
}

