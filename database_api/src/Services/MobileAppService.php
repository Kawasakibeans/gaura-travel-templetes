<?php

namespace App\Services;

use App\DAL\MobileAppDAL;

class MobileAppService
{
    private $dal;

    public function __construct(MobileAppDAL $dal)
    {
        $this->dal = $dal;
    }

    /**
     * Get mobile app settings
     * Line: 38-42 (in template)
     */
    public function getAppSettings()
    {
        return $this->dal->getAppSettings();
    }

    /**
     * Update mobile app settings
     */
    public function updateAppSettings($settings, $updatedBy = 'api_user')
    {
        $validKeys = [
            'ios_app_id',
            'ios_store_url',
            'ios_scheme',
            'android_package',
            'android_store_url',
            'android_intent',
            'desktop_fallback_url'
        ];
        
        $filteredSettings = [];
        foreach ($validKeys as $key) {
            if (isset($settings[$key])) {
                $filteredSettings[$key] = $settings[$key];
            }
        }
        
        if (empty($filteredSettings)) {
            throw new \Exception('No valid settings provided', 400);
        }
        
        $updated = $this->dal->updateAppSettings($filteredSettings);
        
        if (empty($updated)) {
            throw new \Exception('Failed to update app settings', 500);
        }
        
        return $this->getAppSettings();
    }

    /**
     * Track app open/download
     */
    public function trackAppAction($action, $platform, $userAgent = '', $ipAddress = '')
    {
        $validActions = ['open', 'download', 'install'];
        if (!in_array($action, $validActions)) {
            throw new \Exception('Invalid action. Must be: open, download, or install', 400);
        }
        
        $validPlatforms = ['ios', 'android', 'desktop'];
        if (!in_array($platform, $validPlatforms)) {
            throw new \Exception('Invalid platform. Must be: ios, android, or desktop', 400);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        
        $result = $this->dal->insertAppTracking($action, $platform, $userAgent, $ipAddress, $timestamp);
        
        return [
            'tracked' => $result,
            'action' => $action,
            'platform' => $platform,
            'timestamp' => $timestamp
        ];
    }

    /**
     * Get app version information
     */
    public function getAppVersionInfo()
    {
        return $this->dal->getAppVersionInfo();
    }

    /**
     * Update app version information
     */
    public function updateAppVersionInfo($versionInfo, $updatedBy = 'api_user')
    {
        $validKeys = [
            'ios_version',
            'android_version',
            'ios_min_version',
            'android_min_version',
            'force_update',
            'update_message'
        ];
        
        $filteredVersionInfo = [];
        foreach ($validKeys as $key) {
            if (isset($versionInfo[$key])) {
                $filteredVersionInfo[$key] = $versionInfo[$key];
            }
        }
        
        if (empty($filteredVersionInfo)) {
            throw new \Exception('No valid version info provided', 400);
        }
        
        // Validate force_update
        if (isset($filteredVersionInfo['force_update']) && !in_array($filteredVersionInfo['force_update'], ['0', '1'])) {
            throw new \Exception('force_update must be 0 or 1', 400);
        }
        
        $updated = $this->dal->updateAppVersionInfo($filteredVersionInfo);
        
        if (empty($updated)) {
            throw new \Exception('Failed to update app version info', 500);
        }
        
        return $this->getAppVersionInfo();
    }
}

