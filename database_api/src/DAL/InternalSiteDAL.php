<?php
/**
 * Internal Site DAL - Data Access Layer
 */

namespace App\DAL;

use Exception;

class InternalSiteDAL extends BaseDAL
{
    /**
     * Get post by ID
     */
    public function getPostById($postId)
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $postsTable = $tablePrefix . 'posts';
        
        $sql = "SELECT ID, post_title, post_name, post_content, post_status 
                FROM {$postsTable} 
                WHERE ID = :post_id 
                AND post_type = 'product'
                AND post_status = 'publish'
                LIMIT 1";
        
        $params = [':post_id' => (int)$postId];
        $results = $this->query($sql, $params);
        
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Get post meta value
     */
    public function getPostMeta($postId, $metaKey)
    {
        $tablePrefix = $_ENV['WP_TABLE_PREFIX'] ?? 'wpk4_';
        $postmetaTable = $tablePrefix . 'postmeta';
        
        $sql = "SELECT meta_value 
                FROM {$postmetaTable} 
                WHERE post_id = :post_id 
                AND meta_key = :meta_key
                LIMIT 1";
        
        $params = [
            ':post_id' => $postId,
            ':meta_key' => $metaKey
        ];
        
        $results = $this->query($sql, $params);
        
        if (empty($results)) {
            return null;
        }
        
        $value = $results[0]['meta_value'];
        
        // Try to unserialize if it's serialized
        $unserialized = @unserialize($value);
        return $unserialized !== false ? $unserialized : $value;
    }
}

