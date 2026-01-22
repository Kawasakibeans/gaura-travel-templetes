<?php

namespace App\Services;

use App\DAL\YpsilonAobcDAL;

class YpsilonAobcService
{
    private $dal;

    public function __construct(YpsilonAobcDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Get Ypsilon/AOBC status
     * Line: 54-56 (in template)
     */
    public function getStatus()
    {
        $value = $this->dal->getYpsilonActiveStatus();
        
        $statusMap = [
            '0' => [
                'value' => '0',
                'ypsilon_enabled' => false,
                'aobc_enabled' => true,
                'description' => 'Disable Ypsilon & Enable AOBC'
            ],
            '1' => [
                'value' => '1',
                'ypsilon_enabled' => true,
                'aobc_enabled' => false,
                'description' => 'Enable Ypsilon & Disable AOBC'
            ],
            '2' => [
                'value' => '2',
                'ypsilon_enabled' => true,
                'aobc_enabled' => true,
                'description' => 'Enable both Ypsilon & AOBC'
            ],
            '3' => [
                'value' => '3',
                'ypsilon_enabled' => false,
                'aobc_enabled' => false,
                'description' => 'Disable both Ypsilon & AOBC'
            ]
        ];
        
        return $statusMap[$value] ?? $statusMap['0'];
    }

    /**
     * Update Ypsilon/AOBC status
     * Line: 29-52 (in template)
     */
    public function updateStatus($value, $updatedBy = 'api_user')
    {
        // Validate value
        $validValues = ['0', '1', '2', '3'];
        if (!in_array($value, $validValues)) {
            throw new \Exception('Invalid status value. Must be 0, 1, 2, or 3', 400);
        }
        
        // Update option value
        $updated = $this->dal->updateYpsilonActiveStatus($value);
        
        if (!$updated) {
            throw new \Exception('Failed to update status', 500);
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
        return $this->getStatus();
    }
}

