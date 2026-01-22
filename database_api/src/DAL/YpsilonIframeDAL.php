<?php

namespace App\DAL;

use PDO;

class YpsilonIframeDAL
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get option value by option name
     * Line: 106-108, 124-126, 140-142 (in template)
     */
    public function getOptionValue($optionName, $defaultValue = '')
    {
        $query = "SELECT option_value FROM wpk4_options 
                  WHERE option_name = :option_name 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':option_name', $optionName);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['option_value'] : $defaultValue;
    }

    /**
     * Update option value by option name
     * Line: 35-36, 60-61, 85-86 (in template)
     */
    public function updateOptionValue($optionName, $optionValue)
    {
        // Check if option exists
        $existing = $this->getOptionValue($optionName);
        
        if ($existing !== false && $existing !== null) {
            // Update existing option
            $query = "UPDATE wpk4_options 
                      SET option_value = :option_value 
                      WHERE option_name = :option_name";
        } else {
            // Insert new option
            $query = "INSERT INTO wpk4_options (option_name, option_value) 
                      VALUES (:option_name, :option_value)";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':option_name', $optionName);
        $stmt->bindValue(':option_value', $optionValue);
        
        return $stmt->execute();
    }

    /**
     * Insert history record
     * Line: 42-48, 67-73, 92-98 (in template)
     */
    public function insertHistoryRecord($typeId, $metaKey, $metaValue, $updatedBy, $updatedOn)
    {
        $query = "INSERT INTO wpk4_backend_history_of_updates 
                  (type_id, meta_key, meta_value, updated_by, updated_on) 
                  VALUES (:type_id, :meta_key, :meta_value, :updated_by, :updated_on)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':type_id', $typeId);
        $stmt->bindValue(':meta_key', $metaKey);
        $stmt->bindValue(':meta_value', $metaValue);
        $stmt->bindValue(':updated_by', $updatedBy);
        $stmt->bindValue(':updated_on', $updatedOn);
        
        return $stmt->execute();
    }

    /**
     * Get Ypsilon active status
     * Line: 106-108 (in template)
     */
    public function getYpsilonActiveStatus()
    {
        return $this->getOptionValue('is_ypsilon_active', '0');
    }

    /**
     * Update Ypsilon active status
     * Line: 35-36 (in template)
     */
    public function updateYpsilonActiveStatus($value)
    {
        return $this->updateOptionValue('is_ypsilon_active', $value);
    }

    /**
     * Get AOBC enabled for users
     * Line: 124-126 (in template)
     */
    public function getAobcEnabledFor()
    {
        return $this->getOptionValue('AOBC enabled for', '');
    }

    /**
     * Update AOBC enabled for users
     * Line: 60-61 (in template)
     */
    public function updateAobcEnabledFor($value)
    {
        return $this->updateOptionValue('AOBC enabled for', $value);
    }

    /**
     * Get manage ticketing message
     * Line: 140-142 (in template)
     */
    public function getManageTicketingMessage()
    {
        return $this->getOptionValue('manage-ticketing-message', '');
    }

    /**
     * Update manage ticketing message
     * Line: 85-86 (in template)
     */
    public function updateManageTicketingMessage($value)
    {
        return $this->updateOptionValue('manage-ticketing-message', $value);
    }
}

