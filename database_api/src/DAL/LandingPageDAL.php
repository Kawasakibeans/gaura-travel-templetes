<?php
/**
 * Landing Page Data Access Layer
 * Handles database operations for landing pages
 */

namespace App\DAL;

use App\DAL\BaseDAL;

class LandingPageDAL extends BaseDAL
{
    /**
     * Get landing page by ID
     */
    public function getLandingPageById($id)
    {
        $query = "SELECT * FROM wpk4_posts 
                  WHERE ID = ? 
                    AND post_type = 'page' 
                  LIMIT 1";
        
        return $this->queryOne($query, [$id]);
    }

    /**
     * Get landing page by slug
     */
    public function getLandingPageBySlug($slug)
    {
        $query = "SELECT * FROM wpk4_posts 
                  WHERE post_name = ? 
                    AND post_type = 'page' 
                  LIMIT 1";
        
        return $this->queryOne($query, [$slug]);
    }

    /**
     * Get all landing pages
     */
    public function getAllLandingPages($status, $postType, $limit, $offset)
    {
        $query = "SELECT * FROM wpk4_posts 
                  WHERE post_status = ? 
                    AND post_type = ? 
                  ORDER BY post_date DESC 
                  LIMIT ? OFFSET ?";
        
        return $this->query($query, [$status, $postType, $limit, $offset]);
    }

    /**
     * Get landing pages count
     */
    public function getLandingPagesCount($status, $postType)
    {
        $query = "SELECT COUNT(*) as total 
                  FROM wpk4_posts 
                  WHERE post_status = ? 
                    AND post_type = ?";
        
        $result = $this->queryOne($query, [$status, $postType]);
        return (int)$result['total'];
    }

    /**
     * Get page meta data
     */
    public function getPageMeta($postId)
    {
        $query = "SELECT meta_key, meta_value 
                  FROM wpk4_postmeta 
                  WHERE post_id = ?";
        
        $results = $this->query($query, [$postId]);
        
        // Convert to associative array
        $meta = [];
        foreach ($results as $row) {
            $meta[$row['meta_key']] = $row['meta_value'];
        }
        
        return $meta;
    }
}

