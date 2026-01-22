<?php
/**
 * Test Page Service - Business Logic Layer
 */

namespace App\Services;

class TestPageService
{
    /**
     * Get test page configuration
     */
    public function getConfig()
    {
        return [
            'success' => true,
            'config' => [
                'content' => 'tetettttt',
                'container_width' => '1170px',
                'bitrix24_form' => [
                    'id' => '7',
                    'lang' => 'en',
                    'sec' => '3gkhjx',
                    'type' => 'inline',
                    'script_url' => 'https://gtxportal.globaltravelxperts.com/bitrix/js/crm/form_loader.js'
                ]
            ]
        ];
    }
}

