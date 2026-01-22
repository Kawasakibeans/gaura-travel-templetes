<?php
/**
 * Landing Page Service - Business Logic Layer
 * Handles landing page content management
 */

namespace App\Services;

use App\DAL\LandingPageDAL;
use Exception;

class LandingPageService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new LandingPageDAL();
    }

    /**
     * Get landing page by ID or slug
     */
    public function getLandingPage($identifier)
    {
        if (empty($identifier)) {
            throw new Exception('Page identifier is required', 400);
        }

        // Try to get by ID first
        if (is_numeric($identifier)) {
            $page = $this->dal->getLandingPageById($identifier);
        } else {
            // Get by slug
            $page = $this->dal->getLandingPageBySlug($identifier);
        }

        if (!$page) {
            throw new Exception('Landing page not found', 404);
        }

        return $page;
    }

    /**
     * Get landing page configuration (page data + meta)
     */
    public function getLandingPageConfig($identifier)
    {
        if (empty($identifier)) {
            throw new Exception('Page identifier is required', 400);
        }

        // Get the landing page
        $page = $this->getLandingPage($identifier);
        
        if (!$page) {
            throw new Exception('Landing page not found', 404);
        }

        // Get page meta data
        $meta = $this->dal->getPageMeta($page['ID']);

        // Combine page data and meta
        return [
            'page' => $page,
            'meta' => $meta,
            'config' => [
                'id' => $page['ID'],
                'title' => $page['post_title'],
                'slug' => $page['post_name'],
                'content' => $page['post_content'],
                'status' => $page['post_status'],
                'date' => $page['post_date'],
                'modified' => $page['post_modified'],
                'meta' => $meta
            ]
        ];
    }

    /**
     * Get all landing pages
     */
    public function getAllLandingPages($filters = [])
    {
        $status = $filters['status'] ?? 'publish';
        $postType = $filters['post_type'] ?? 'page';
        $limit = (int)($filters['limit'] ?? 50);
        $offset = (int)($filters['offset'] ?? 0);

        $pages = $this->dal->getAllLandingPages($status, $postType, $limit, $offset);
        $totalCount = $this->dal->getLandingPagesCount($status, $postType);

        return [
            'pages' => $pages,
            'total_count' => $totalCount,
            'filters' => $filters
        ];
    }

    /**
     * Get page meta data
     */
    public function getPageMeta($postId)
    {
        if (empty($postId) || !is_numeric($postId)) {
            throw new Exception('Valid post ID is required', 400);
        }

        $meta = $this->dal->getPageMeta($postId);

        return [
            'post_id' => $postId,
            'meta' => $meta
        ];
    }
}