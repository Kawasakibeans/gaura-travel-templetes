<?php
/**
 * G360 Common Settings Data Access Layer
 * Handles database operations for G360 settings management
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class G360CommonSettingsDAL extends BaseDAL
{
    /**
     * Get setting value by meta key
     */
    public function getSetting(string $metaKey): ?string
    {
        try {
            $sql = "
                SELECT meta_value 
                FROM wpk4_g360_settings 
                WHERE meta_key = :meta_key 
                LIMIT 1
            ";
            
            $result = $this->queryOne($sql, [':meta_key' => $metaKey]);
            if ($result === false || !is_array($result)) {
                return null;
            }
            return $result['meta_value'] ?? null;
        } catch (\Exception $e) {
            error_log("G360CommonSettingsDAL::getSetting error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update setting value by meta key
     */
    public function updateSetting(string $metaKey, string $metaValue, string $updatedBy, string $updatedOn): bool
    {
        // Check if setting exists
        $existing = $this->getSetting($metaKey);
        
        if ($existing === null) {
            // Insert new setting
            $sql = "
                INSERT INTO wpk4_g360_settings (meta_key, meta_value, updated_by, updated_on)
                VALUES (:meta_key, :meta_value, :updated_by, :updated_on)
            ";
            $params = [
                ':meta_key' => $metaKey,
                ':meta_value' => $metaValue,
                ':updated_by' => $updatedBy,
                ':updated_on' => $updatedOn
            ];
        } else {
            // Update existing setting
            $sql = "
                UPDATE wpk4_g360_settings 
                SET meta_value = :meta_value, 
                    updated_by = :updated_by, 
                    updated_on = :updated_on
                WHERE meta_key = :meta_key
            ";
            $params = [
                ':meta_key' => $metaKey,
                ':meta_value' => $metaValue,
                ':updated_by' => $updatedBy,
                ':updated_on' => $updatedOn
            ];
        }
        
        // Use BaseDAL's execute method for consistent error handling
        return $this->execute($sql, $params) !== false;
    }
    
    /**
     * Get all settings
     */
    public function getAllSettings(): array
    {
        try {
            $sql = "
                SELECT meta_key, meta_value, updated_by, updated_on 
                FROM wpk4_g360_settings 
                ORDER BY meta_key
            ";
            
            return $this->query($sql, []);
        } catch (\Exception $e) {
            error_log("G360CommonSettingsDAL::getAllSettings error: " . $e->getMessage());
            return [];
        }
    }
}

