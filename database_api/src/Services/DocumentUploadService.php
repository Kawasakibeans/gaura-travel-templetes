<?php
/**
 * Document Upload Service - Business Logic Layer
 * 
 * Note: This service acts as a proxy/wrapper for external AI server operations.
 * The actual document processing is handled by an external AI server.
 */

namespace App\Services;

use Exception;

class DocumentUploadService
{
    private $aiServerUrl;
    
    public function __construct()
    {
        // AI server URL - should be configured via environment variable
        $this->aiServerUrl = $_ENV['AI_SERVER_URL'] ?? 'http://localhost:8000';
    }
    
    /**
     * Upload document to AI server
     * 
     * Note: This is a placeholder. The actual implementation should proxy
     * the file upload to the external AI server via upload_ai_proxy.php
     */
    public function uploadDocument($file, $bot)
    {
        // Validate bot selection
        $validBots = ['gauri', 'hema'];
        if (!in_array($bot, $validBots)) {
            throw new Exception('Invalid bot selection. Must be "gauri" or "hema"', 400);
        }
        
        // Validate file
        if (empty($file)) {
            throw new Exception('File is required', 400);
        }
        
        // File validation should be done in the proxy/upload handler
        // This service just validates the parameters
        
        return [
            'success' => true,
            'message' => 'Document upload initiated. Processing is handled by external AI server via proxy.',
            'note' => 'This endpoint should proxy to upload_ai_proxy.php which handles the actual upload to AI server'
        ];
    }
    
    /**
     * Check processing status
     */
    public function checkStatus($jobId, $bot)
    {
        // Validate bot selection
        $validBots = ['gauri', 'hema'];
        if (!in_array($bot, $validBots)) {
            throw new Exception('Invalid bot selection. Must be "gauri" or "hema"', 400);
        }
        
        if (empty($jobId)) {
            throw new Exception('Job ID is required', 400);
        }
        
        return [
            'success' => true,
            'message' => 'Status check initiated. Processing is handled by external AI server via proxy.',
            'note' => 'This endpoint should proxy to upload_ai_proxy.php which handles the actual status check'
        ];
    }
    
    /**
     * List document IDs
     */
    public function listDocuments($bot)
    {
        // Validate bot selection
        $validBots = ['gauri', 'hema'];
        if (!in_array($bot, $validBots)) {
            throw new Exception('Invalid bot selection. Must be "gauri" or "hema"', 400);
        }
        
        return [
            'success' => true,
            'message' => 'Document list initiated. Processing is handled by external AI server via proxy.',
            'note' => 'This endpoint should proxy to upload_ai_proxy.php which handles the actual list operation'
        ];
    }
    
    /**
     * Delete document
     */
    public function deleteDocument($docId, $bot)
    {
        // Validate bot selection
        $validBots = ['gauri', 'hema'];
        if (!in_array($bot, $validBots)) {
            throw new Exception('Invalid bot selection. Must be "gauri" or "hema"', 400);
        }
        
        if (empty($docId)) {
            throw new Exception('Document ID is required', 400);
        }
        
        return [
            'success' => true,
            'message' => 'Document deletion initiated. Processing is handled by external AI server via proxy.',
            'note' => 'This endpoint should proxy to upload_ai_proxy.php which handles the actual deletion'
        ];
    }
}

