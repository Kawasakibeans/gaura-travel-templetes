<?php

namespace App\DAL;

use PDO;

class MobileAppDAL
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get option value by option name
     */
    public function getOptionValue($optionName, $defaultValue = '')
    {
        $query = "SELECT option_value FROM wpk4_options 
                  WHERE option_name = :option_name 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':option_name', $optionName);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['option_value'] : $defaultValue;
    }

    /**
     * Update option value by option name
     */
    public function updateOptionValue($optionName, $optionValue)
    {
        // Check if option exists
        $existing = $this->getOptionValue($optionName);
        
        if ($existing !== false && $existing !== null) {
            // Update existing option
            $query = "UPDATE wpk4_options 
                      SET option_value = :option_value 
                      WHERE option_name = :option_name";
        } else {
            // Insert new option
            $query = "INSERT INTO wpk4_options (option_name, option_value) 
                      VALUES (:option_name, :option_value)";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':option_name', $optionName);
        $stmt->bindValue(':option_value', $optionValue);
        
        return $stmt->execute();
    }

    /**
     * Insert app tracking record
     */
    public function insertAppTracking($action, $platform, $userAgent, $ipAddress, $timestamp)
    {
        $query = "INSERT INTO wpk4_mobile_app_tracking 
                  (action, platform, user_agent, ip_address, created_at) 
                  VALUES (:action, :platform, :user_agent, :ip_address, :created_at)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':action', $action);
        $stmt->bindValue(':platform', $platform);
        $stmt->bindValue(':user_agent', $userAgent);
        $stmt->bindValue(':ip_address', $ipAddress);
        $stmt->bindValue(':created_at', $timestamp);
        
        try {
            return $stmt->execute();
        } catch (\PDOException $e) {
            // Table doesn't exist - silently fail
            return false;
        }
    }

    /**
     * Get app settings
     */
    public function getAppSettings()
    {
        return [
            'ios_app_id' => $this->getOptionValue('mobile_app_ios_app_id', '6743376901'),
            'ios_store_url' => $this->getOptionValue('mobile_app_ios_store_url', 'https://apps.apple.com/app/6743376901'),
            'ios_scheme' => $this->getOptionValue('mobile_app_ios_scheme', 'gauratravel://'),
            'android_package' => $this->getOptionValue('mobile_app_android_package', 'au.com.gauratravel.app'),
            'android_store_url' => $this->getOptionValue('mobile_app_android_store_url', 'https://play.google.com/store/apps/details?id=au.com.gauratravel.app'),
            'android_intent' => $this->getOptionValue('mobile_app_android_intent', 'intent://open#Intent;scheme=gauratravel;package=au.com.gauratravel.app;end'),
            'desktop_fallback_url' => $this->getOptionValue('mobile_app_desktop_fallback_url', 'https://gauratravel.com.au/mobile-app')
        ];
    }

    /**
     * Update app settings
     */
    public function updateAppSettings($settings)
    {
        $updated = [];
        foreach ($settings as $key => $value) {
            $optionName = 'mobile_app_' . $key;
            if ($this->updateOptionValue($optionName, $value)) {
                $updated[$key] = $value;
            }
        }
        return $updated;
    }

    /**
     * Get app version info
     */
    public function getAppVersionInfo()
    {
        return [
            'ios_version' => $this->getOptionValue('mobile_app_ios_version', ''),
            'android_version' => $this->getOptionValue('mobile_app_android_version', ''),
            'ios_min_version' => $this->getOptionValue('mobile_app_ios_min_version', ''),
            'android_min_version' => $this->getOptionValue('mobile_app_android_min_version', ''),
            'force_update' => $this->getOptionValue('mobile_app_force_update', '0'),
            'update_message' => $this->getOptionValue('mobile_app_update_message', '')
        ];
    }

    /**
     * Update app version info
     */
    public function updateAppVersionInfo($versionInfo)
    {
        $updated = [];
        foreach ($versionInfo as $key => $value) {
            $optionName = 'mobile_app_' . $key;
            if ($this->updateOptionValue($optionName, $value)) {
                $updated[$key] = $value;
            }
        }
        return $updated;
    }
}

