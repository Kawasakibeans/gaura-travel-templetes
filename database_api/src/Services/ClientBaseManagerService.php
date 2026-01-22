<?php

namespace App\Services;

use App\DAL\ClientBaseManagerDAL;

class ClientBaseManagerService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new ClientBaseManagerDAL();
    }

    /**
     * Get updated clients
     */
    public function getUpdatedClients(array $params): array
    {
        $clientId = $params['client_id'] ?? null;
        $typeOfRemark = $params['type_of_remark'] ?? null;
        
        $clients = $this->dal->getUpdatedClients($clientId, $typeOfRemark);
        
        return [
            'clients' => $clients,
            'total' => count($clients)
        ];
    }
    
    /**
     * Get distinct remark types
     */
    public function getDistinctRemarkTypes(): array
    {
        return $this->dal->getDistinctRemarkTypes();
    }
    
    /**
     * Get remark type counts
     */
    public function getRemarkTypeCounts(): array
    {
        return $this->dal->getRemarkTypeCounts();
    }
    
    /**
     * Update client
     */
    public function updateClient(array $params): bool
    {
        $clientId = $params['client_id'] ?? null;
        if (!$clientId) {
            throw new \Exception('client_id is required');
        }
        
        $recentOrderId = $params['recent_order_id'] ?? null;
        $typeOfRemark = $params['type_of_remark'] ?? null;
        $remark = $params['remark'] ?? null;
        
        return $this->dal->updateClient($clientId, $recentOrderId, $typeOfRemark, $remark);
    }
    
    /**
     * Bulk update clients
     */
    public function bulkUpdateClients(array $updates): array
    {
        $results = [];
        foreach ($updates as $update) {
            $clientId = $update['client_id'] ?? null;
            if (!$clientId) {
                continue;
            }
            
            $results[] = [
                'client_id' => $clientId,
                'success' => $this->dal->updateClient(
                    $clientId,
                    $update['recent_order_id'] ?? null,
                    $update['type_of_remark'] ?? null,
                    $update['remark'] ?? null
                )
            ];
        }
        
        return $results;
    }
    
    /**
     * Import clients from CSV data
     */
    public function importClients(array $clients, string $currentUser): array
    {
        $currentTime = date('Y-m-d H:i:s');
        $results = [];
        
        foreach ($clients as $client) {
            $clientName = $client['client_name'] ?? '';
            $clientId = $client['client_id'] ?? '';
            $phone = $client['phone'] ?? '';
            $invoiceTotal = $client['invoice_total'] ?? '0';
            
            if (!$clientId) {
                continue;
            }
            
            $results[] = [
                'client_id' => $clientId,
                'success' => $this->dal->upsertClient($clientName, $clientId, $phone, $invoiceTotal, $currentTime, $currentUser)
            ];
        }
        
        return $results;
    }
}

