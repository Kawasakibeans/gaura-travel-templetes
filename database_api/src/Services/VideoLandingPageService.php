<?php
/**
 * Video Landing Page Service - Business Logic Layer
 * Handles video content management for customer-facing pages
 */

namespace App\Services;

use App\DAL\VideoLandingPageDAL;
use Exception;

class VideoLandingPageService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new VideoLandingPageDAL();
    }

    /**
     * Get all video content
     */
    public function getAllVideos($status = 'active')
    {
        $videos = $this->dal->getAllVideos($status);

        return [
            'videos' => $videos,
            'total_count' => count($videos),
            'status_filter' => $status
        ];
    }

    /**
     * Get video by ID
     */
    public function getVideoById($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid video ID is required', 400);
        }

        $video = $this->dal->getVideoById($id);

        if (!$video) {
            throw new Exception('Video not found', 404);
        }

        return $video;
    }

    /**
     * Get videos by type
     */
    public function getVideosByType($type)
    {
        $validTypes = ['Payment Guide', 'DC Portal', 'Complaints', 'Refunds'];
        
        if (!in_array($type, $validTypes)) {
            throw new Exception('Invalid type. Must be: Payment Guide, DC Portal, Complaints, or Refunds', 400);
        }

        $videos = $this->dal->getVideosByType($type);

        return [
            'type' => $type,
            'videos' => $videos,
            'total_count' => count($videos)
        ];
    }

    /**
     * Create new video
     */
    public function createVideo($data)
    {
        // Validate required fields
        $requiredFields = ['meta_key', 'meta_key_note', 'meta_value', 'meta_value_note'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        // Validate type
        $validTypes = ['Payment Guide', 'DC Portal', 'Complaints', 'Refunds'];
        if (!in_array($data['meta_key'], $validTypes)) {
            throw new Exception('Invalid type', 400);
        }

        $videoId = $this->dal->createVideo($data);

        return [
            'video_id' => $videoId,
            'type' => $data['meta_key'],
            'message' => 'Video created successfully'
        ];
    }

    /**
     * Update video
     */
    public function updateVideo($id, $data)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid video ID is required', 400);
        }

        $video = $this->dal->getVideoById($id);
        if (!$video) {
            throw new Exception('Video not found', 404);
        }

        $this->dal->updateVideo($id, $data);

        return [
            'video_id' => $id,
            'message' => 'Video updated successfully'
        ];
    }

    /**
     * Delete video
     */
    public function deleteVideo($id)
    {
        if (empty($id) || !is_numeric($id)) {
            throw new Exception('Valid video ID is required', 400);
        }

        $video = $this->dal->getVideoById($id);
        if (!$video) {
            throw new Exception('Video not found', 404);
        }

        $this->dal->deleteVideo($id);

        return [
            'video_id' => $id,
            'type' => $video['meta_key'],
            'message' => 'Video deleted successfully'
        ];
    }
}

