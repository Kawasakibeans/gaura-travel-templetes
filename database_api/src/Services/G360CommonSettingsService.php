<?php
/**
 * G360 Common Settings Service
 * Handles business logic for G360 settings management
 */

namespace App\Services;

use App\DAL\G360CommonSettingsDAL;

class G360CommonSettingsService
{
    private $dal;
    
    public function __construct()
    {
        $this->dal = new G360CommonSettingsDAL();
    }
    
    /**
     * Get setting value
     */
    public function getSetting(array $params): array
    {
        $metaKey = $params['meta_key'] ?? 'is_flights_internal_select_pax_enabled';
        
        $value = $this->dal->getSetting($metaKey);
        
        return [
            'meta_key' => $metaKey,
            'meta_value' => $value ?? 'no',
            'enabled' => ($value && strtolower($value) === 'yes')
        ];
    }
    
    /**
     * Update setting value
     */
    public function updateSetting(array $params): array
    {
        $metaKey = $params['meta_key'] ?? 'is_flights_internal_select_pax_enabled';
        $metaValue = ($params['meta_value'] ?? 'no') === 'yes' ? 'yes' : 'no';
        $updatedBy = $params['updated_by'] ?? 'api';
        $updatedOn = date('Y-m-d H:i:s');
        
        $success = $this->dal->updateSetting($metaKey, $metaValue, $updatedBy, $updatedOn);
        
        if (!$success) {
            throw new \Exception('Failed to update setting');
        }
        
        return [
            'meta_key' => $metaKey,
            'meta_value' => $metaValue,
            'updated_by' => $updatedBy,
            'updated_on' => $updatedOn
        ];
    }
    
    /**
     * Get all settings
     */
    public function getAllSettings(): array
    {
        return $this->dal->getAllSettings();
    }
}

