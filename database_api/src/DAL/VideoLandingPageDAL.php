<?php
/**
 * Video Landing Page Data Access Layer
 * Handles database operations for video content management
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class VideoLandingPageDAL extends BaseDAL
{
    /**
     * Get all videos
     */
    public function getAllVideos($status = 'active')
    {
        $query = "SELECT * FROM wpk4_backend_custom_update_records 
                  WHERE status = ? 
                    AND meta_key IN ('Payment Guide', 'DC Portal', 'Complaints', 'Refunds')
                  ORDER BY auto_id DESC";
        
        return $this->query($query, [$status]);
    }

    /**
     * Get video by ID
     */
    public function getVideoById($id)
    {
        $query = "SELECT * FROM wpk4_backend_custom_update_records WHERE auto_id = ? LIMIT 1";
        return $this->queryOne($query, [$id]);
    }

    /**
     * Get videos by type
     */
    public function getVideosByType($type)
    {
        $query = "SELECT * FROM wpk4_backend_custom_update_records 
                  WHERE meta_key = ? 
                    AND status = 'active'
                  ORDER BY auto_id DESC";
        
        return $this->query($query, [$type]);
    }

    /**
     * Create new video
     */
    public function createVideo($data)
    {
        $query = "INSERT INTO wpk4_backend_custom_update_records 
                  (meta_key, meta_key_note, meta_value, meta_value_note, updated_by, updated_on, status)
                  VALUES (?, ?, ?, ?, ?, NOW(), ?)";
        
        $params = [
            $data['meta_key'],
            $data['meta_key_note'],
            $data['meta_value'],
            $data['meta_value_note'],
            $data['updated_by'] ?? 'system',
            $data['status'] ?? 'active'
        ];

        $this->execute($query, $params);
        return $this->lastInsertId();
    }

    /**
     * Update video
     */
    public function updateVideo($id, $data)
    {
        $setParts = [];
        $params = [];

        $updateableFields = ['meta_key', 'meta_key_note', 'meta_value', 'meta_value_note', 'status', 'updated_by'];

        foreach ($updateableFields as $field) {
            if (array_key_exists($field, $data)) {
                $setParts[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($setParts)) {
            return false;
        }

        $setParts[] = "updated_on = NOW()";
        $setSQL = implode(', ', $setParts);
        
        $query = "UPDATE wpk4_backend_custom_update_records SET $setSQL WHERE auto_id = ?";
        $params[] = $id;

        return $this->execute($query, $params);
    }

    /**
     * Delete video
     */
    public function deleteVideo($id)
    {
        $query = "DELETE FROM wpk4_backend_custom_update_records WHERE auto_id = ?";
        return $this->execute($query, [$id]);
    }
}

