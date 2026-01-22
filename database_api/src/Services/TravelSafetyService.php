<?php
/**
 * Travel Safety Service - Business Logic Layer
 */

namespace App\Services;

class TravelSafetyService
{
    /**
     * Get travel safety configuration
     */
    public function getConfig()
    {
        return [
            'success' => true,
            'config' => [
                'sherpa_element' => [
                    'type' => 'trip',
                    'placement' => 'discovery',
                    'mount_selector' => '#sherpa-trip-element'
                ],
                'styles' => [
                    'hide_entry_header' => true
                ]
            ]
        ];
    }
}

