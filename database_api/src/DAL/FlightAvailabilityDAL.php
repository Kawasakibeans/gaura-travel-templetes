<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class FlightAvailabilityDAL extends BaseDAL
{
    /**
     * Insert flight availability check record
     */
    public function insertAvailabilityCheck($data)
    {
        $query = "
            INSERT INTO wpk4_ypsilon_flight_availability_check 
            (user_id, depart_apt, dest_apt, outbound_seat, return_seat, depart_date, return_date, airline, tariffId, session_id)
            VALUES (:user_id, :depart_apt, :dest_apt, :outbound_seat, :return_seat, :depart_date, :return_date, :airline, :tariffId, :session_id)
        ";
        
        $params = [
            'user_id' => $data['user_id'] ?? null,
            'depart_apt' => $data['depart_apt'] ?? null,
            'dest_apt' => $data['dest_apt'] ?? null,
            'outbound_seat' => $data['outbound_seat'] ?? null,
            'return_seat' => $data['return_seat'] ?? null,
            'depart_date' => $data['depart_date'] ?? null,
            'return_date' => $data['return_date'] ?? null,
            'airline' => $data['airline'] ?? null,
            'tariffId' => $data['tariffId'] ?? null,
            'session_id' => $data['session_id'] ?? null
        ];
        
        $this->execute($query, $params);
        return $this->lastInsertId();
    }
    
    /**
     * Insert flight availability check leg record
     */
    public function insertAvailabilityLeg($availId, $legData)
    {
        $query = "
            INSERT INTO wpk4_ypsilon_flight_availability_check_legs 
            (avai_check_id, legId, depApt, depDate, depTime, dstApt, depTerm, arrTerm, arrDate, arrTime, equip, fNo, miles, elapsed, meals, smoker, stops, eticket)
            VALUES (:avai_check_id, :legId, :depApt, :depDate, :depTime, :dstApt, :depTerm, :arrTerm, :arrDate, :arrTime, :equip, :fNo, :miles, :elapsed, :meals, :smoker, :stops, :eticket)
        ";
        
        $params = [
            'avai_check_id' => $availId,
            'legId' => $legData['legId'] ?? null,
            'depApt' => $legData['depApt'] ?? null,
            'depDate' => $legData['depDate'] ?? null,
            'depTime' => $legData['depTime'] ?? null,
            'dstApt' => $legData['dstApt'] ?? null,
            'depTerm' => $legData['depTerm'] ?? null,
            'arrTerm' => $legData['arrTerm'] ?? null,
            'arrDate' => $legData['arrDate'] ?? null,
            'arrTime' => $legData['arrTime'] ?? null,
            'equip' => $legData['equip'] ?? null,
            'fNo' => $legData['fNo'] ?? null,
            'miles' => $legData['miles'] ?? null,
            'elapsed' => $legData['elapsed'] ?? null,
            'meals' => $legData['meals'] ?? null,
            'smoker' => $legData['smoker'] ?? null,
            'stops' => $legData['stops'] ?? null,
            'eticket' => $legData['eticket'] ?? null
        ];
        
        return $this->execute($query, $params);
    }
    
    /**
     * Get availability check by ID
     */
    public function getAvailabilityCheckById($id)
    {
        $query = "
            SELECT * FROM wpk4_ypsilon_flight_availability_check
            WHERE id = :id
        ";
        return $this->queryOne($query, ['id' => $id]);
    }
    
    /**
     * Get legs for an availability check
     */
    public function getAvailabilityLegs($availId)
    {
        $query = "
            SELECT * FROM wpk4_ypsilon_flight_availability_check_legs
            WHERE avai_check_id = :avai_check_id
            ORDER BY legId
        ";
        return $this->query($query, ['avai_check_id' => $availId]);
    }
    
    /**
     * Insert flight availability check record (for Ypsilon API check)
     * Different structure: session_id, tarif_id, outbound_flight_id, return_flight_id
     */
    public function insertAvailabilityCheckFromYpsilon($sessionId, $tarifId, $outboundFlightId = null, $returnFlightId = null)
    {
        $query = "
            INSERT INTO wpk4_ypsilon_flight_availability_check 
            (session_id, tarif_id, outbound_flight_id, return_flight_id, created_at)
            VALUES (:session_id, :tarif_id, :outbound_flight_id, :return_flight_id, NOW())
        ";
        
        $params = [
            'session_id' => $sessionId,
            'tarif_id' => $tarifId,
            'outbound_flight_id' => $outboundFlightId,
            'return_flight_id' => $returnFlightId
        ];
        
        $this->execute($query, $params);
        return $this->lastInsertId();
    }
}

