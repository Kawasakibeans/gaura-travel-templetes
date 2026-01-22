<?php

namespace App\DAL;

use App\DAL\BaseDAL;

class ClientBaseManagerDAL extends BaseDAL
{
    /**
     * Get updated clients
     */
    public function getUpdatedClients(?string $clientId = null, ?string $typeOfRemark = null): array
    {
        $sql = "SELECT updated_on FROM wpk4_backend_travel_client_balance WHERE date(updated_on) <= CURDATE() ORDER BY updated_on DESC LIMIT 1";
        $result = $this->queryOne($sql);
        
        if (!$result) {
            return [];
        }
        
        $fixedDate = date('Y-m-d', strtotime($result['updated_on']));
        
        $sql = "SELECT * FROM wpk4_backend_travel_client_balance WHERE invoice_total != 0 AND status = 'updated' AND date(updated_on) >= :fixed_date";
        $params = [':fixed_date' => $fixedDate];
        
        if ($clientId) {
            $sql .= " AND client_id LIKE :client_id";
            $params[':client_id'] = '%' . $clientId . '%';
        }
        
        if ($typeOfRemark) {
            $sql .= " AND type_of_remark LIKE :type_of_remark";
            $params[':type_of_remark'] = '%' . $typeOfRemark . '%';
        }
        
        return $this->query($sql, $params);
    }
    
    /**
     * Get distinct remark types
     */
    public function getDistinctRemarkTypes(): array
    {
        $sql = "SELECT DISTINCT type_of_remark FROM wpk4_backend_travel_client_balance WHERE type_of_remark IS NOT NULL AND type_of_remark != ''";
        return $this->query($sql);
    }
    
    /**
     * Get remark type counts
     */
    public function getRemarkTypeCounts(): array
    {
        $sql = "SELECT updated_on FROM wpk4_backend_travel_client_balance WHERE date(updated_on) <= CURDATE() ORDER BY updated_on DESC LIMIT 1";
        $result = $this->queryOne($sql);
        
        if (!$result) {
            return [];
        }
        
        $fixedDate = date('Y-m-d', strtotime($result['updated_on']));
        
        $sql = "
            SELECT 
                type_of_remark,
                COUNT(*) AS count
            FROM wpk4_backend_travel_client_balance
            WHERE type_of_remark IS NOT NULL 
            AND type_of_remark != ''
            AND status = 'updated'
            AND date(updated_on) >= :fixed_date
            GROUP BY type_of_remark
        ";
        
        return $this->query($sql, [':fixed_date' => $fixedDate]);
    }
    
    /**
     * Update client
     */
    public function updateClient(string $clientId, ?string $recentOrderId = null, ?string $typeOfRemark = null, ?string $remark = null): bool
    {
        $updates = [];
        $params = [':client_id' => $clientId];
        
        if ($recentOrderId !== null) {
            $updates[] = "recent_order_id = :recent_order_id";
            $params[':recent_order_id'] = $recentOrderId;
        }
        
        if ($typeOfRemark !== null) {
            $updates[] = "type_of_remark = :type_of_remark";
            $params[':type_of_remark'] = $typeOfRemark;
        }
        
        if ($remark !== null) {
            $updates[] = "remark = :remark";
            $params[':remark'] = $remark;
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $sql = "UPDATE wpk4_backend_travel_client_balance SET " . implode(', ', $updates) . " WHERE client_id = :client_id";
        
        return $this->execute($sql, $params);
    }
    
    /**
     * Get client by ID
     */
    public function getClientById(string $clientId): ?array
    {
        $sql = "SELECT * FROM wpk4_backend_travel_client_balance WHERE client_id = :client_id";
        $result = $this->queryOne($sql, [':client_id' => $clientId]);
        return $result ?: null;
    }
    
    /**
     * Insert or update client from import
     */
    public function upsertClient(string $clientName, string $clientId, string $phone, string $invoiceTotal, string $currentTime, string $currentUser): bool
    {
        $existing = $this->getClientById($clientId);
        
        if ($existing) {
            $sql = "
                UPDATE wpk4_backend_travel_client_balance 
                SET invoice_total = :invoice_total,
                    updated_on = :updated_on,
                    updated_by = :updated_by,
                    status = 'updated'
                WHERE client_id = :client_id
            ";
            $params = [
                ':invoice_total' => $invoiceTotal,
                ':updated_on' => $currentTime,
                ':updated_by' => $currentUser,
                ':client_id' => $clientId
            ];
        } else {
            $sql = "
                INSERT INTO wpk4_backend_travel_client_balance 
                (client_name, client_id, phone, invoice_total, status, updated_on, updated_by)
                VALUES (:client_name, :client_id, :phone, :invoice_total, 'updated', :updated_on, :updated_by)
            ";
            $params = [
                ':client_name' => $clientName,
                ':client_id' => $clientId,
                ':phone' => $phone,
                ':invoice_total' => $invoiceTotal,
                ':updated_on' => $currentTime,
                ':updated_by' => $currentUser
            ];
        }
        
        return $this->execute($sql, $params);
    }
}

