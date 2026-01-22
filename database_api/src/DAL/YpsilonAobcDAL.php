<?php

namespace App\DAL;

use PDO;

class YpsilonAobcDAL
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get Ypsilon/AOBC active status
     * Line: 54-56 (in template)
     */
    public function getYpsilonActiveStatus()
    {
        $query = "SELECT option_value FROM wpk4_options 
                  WHERE option_name = 'is_ypsilon_active' 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['option_value'] : '0';
    }

    /**
     * Update Ypsilon/AOBC active status
     * Line: 33-35 (in template)
     */
    public function updateYpsilonActiveStatus($value)
    {
        $query = "UPDATE wpk4_options 
                  SET option_value = :option_value 
                  WHERE option_name = 'is_ypsilon_active'";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':option_value', $value);
        
        return $stmt->execute();
    }

    /**
     * Insert history record
     * Line: 40-46 (in template)
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
}

