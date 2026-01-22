<?php

namespace App\Services;

use App\DAL\YpsilonIframeDAL;

class YpsilonIframeService
{
    private $dal;

    public function __construct(YpsilonIframeDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Get all Ypsilon iframe settings
     * Line: 106-148 (in template)
     */
    public function getAllSettings()
    {
        return [
            'ypsilon_active' => [
                'value' => $this->dal->getYpsilonActiveStatus(),
                'enabled' => $this->dal->getYpsilonActiveStatus() == '1'
            ],
            'aobc_enabled_for' => $this->dal->getAobcEnabledFor(),
            'manage_ticketing_message' => $this->dal->getManageTicketingMessage()
        ];
    }

    /**
     * Get Ypsilon active status
     * Line: 106-108 (in template)
     */
    public function getYpsilonActiveStatus()
    {
        $value = $this->dal->getYpsilonActiveStatus();
        return [
            'value' => $value,
            'enabled' => $value == '1',
            'description' => $value == '1' ? 'Yes' : 'No'
        ];
    }

    /**
     * Update Ypsilon active status
     * Line: 31-54 (in template)
     */
    public function updateYpsilonActiveStatus($value, $updatedBy = 'api_user')
    {
        // Validate value
        if (!in_array($value, ['0', '1'])) {
            throw new \Exception('Invalid value. Must be 0 or 1', 400);
        }
        
        // Update option value
        $updated = $this->dal->updateYpsilonActiveStatus($value);
        
        if (!$updated) {
            throw new \Exception('Failed to update Ypsilon active status', 500);
        }
        
        // Insert history record
        $currentDateAndTime = date('Y-m-d H:i:s');
        $this->dal->insertHistoryRecord(
            '42421',
            'Ypsilon Iframe Visibility',
            $value,
            $updatedBy,
            $currentDateAndTime
        );
        
        // Return updated status
        return $this->getYpsilonActiveStatus();
    }

    /**
     * Get AOBC enabled for users
     * Line: 124-126 (in template)
     */
    public function getAobcEnabledFor()
    {
        return [
            'value' => $this->dal->getAobcEnabledFor()
        ];
    }

    /**
     * Update AOBC enabled for users
     * Line: 56-79 (in template)
     */
    public function updateAobcEnabledFor($value, $updatedBy = 'api_user')
    {
        // Update option value
        $updated = $this->dal->updateAobcEnabledFor($value);
        
        if (!$updated) {
            throw new \Exception('Failed to update AOBC enabled for', 500);
        }
        
        // Insert history record
        $currentDateAndTime = date('Y-m-d H:i:s');
        $this->dal->insertHistoryRecord(
            '42421',
            'AOBC visibility users updated',
            $value,
            $updatedBy,
            $currentDateAndTime
        );
        
        // Return updated value
        return $this->getAobcEnabledFor();
    }

    /**
     * Get manage ticketing message
     * Line: 140-142 (in template)
     */
    public function getManageTicketingMessage()
    {
        return [
            'value' => $this->dal->getManageTicketingMessage()
        ];
    }

    /**
     * Update manage ticketing message
     * Line: 81-104 (in template)
     */
    public function updateManageTicketingMessage($value, $updatedBy = 'api_user')
    {
        // Update option value
        $updated = $this->dal->updateManageTicketingMessage($value);
        
        if (!$updated) {
            throw new \Exception('Failed to update manage ticketing message', 500);
        }
        
        // Insert history record
        $currentDateAndTime = date('Y-m-d H:i:s');
        $this->dal->insertHistoryRecord(
            '42421',
            'manage-ticketing-message',
            $value,
            $updatedBy,
            $currentDateAndTime
        );
        
        // Return updated value
        return $this->getManageTicketingMessage();
    }
}

