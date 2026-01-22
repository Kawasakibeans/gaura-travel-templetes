<?php
/**
 * Team Links Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\TeamLinksDAL;
use Exception;

class TeamLinksService
{
    private $teamLinksDAL;

    public function __construct()
    {
        $this->teamLinksDAL = new TeamLinksDAL();
    }

    /**
     * Get team links (if IP is authorized)
     */
    public function getTeamLinks($ipAddress = null)
    {
        // If IP not provided, try to get from request
        if (empty($ipAddress)) {
            $ipAddress = $this->getClientIpAddress();
        }
        
        // Check IP authorization
        $ipCheck = $this->teamLinksDAL->checkIpAddress($ipAddress);
        
        if (!$ipCheck) {
            return [
                'success' => false,
                'ip_authorized' => false,
                'message' => 'This page is not accessible for you.'
            ];
        }
        
        // Team links (hardcoded as per original file)
        $teamLinks = [
            [
                'team_name' => 'Rolls Royce',
                'dashboard_link' => 'http://gtxreport/embed.html?t=d&i=258'
            ],
            [
                'team_name' => 'Aston Martin',
                'dashboard_link' => 'http://gtxreport/embed.html?t=d&i=259'
            ],
            [
                'team_name' => 'Lamborghini',
                'dashboard_link' => 'http://gtxreport/embed.html?t=d&i=260'
            ],
            [
                'team_name' => 'Bentley',
                'dashboard_link' => 'http://gtxreport/embed.html?t=d&i=261'
            ],
            [
                'team_name' => 'Porsche',
                'dashboard_link' => 'http://gtxreport/embed.html?t=d&i=262'
            ],
            [
                'team_name' => 'Maserati',
                'dashboard_link' => 'http://gtxreport/embed.html?t=d&i=263'
            ],
            [
                'team_name' => 'Bugatti',
                'dashboard_link' => 'http://gtxreport/embed.html?t=d&i=264'
            ],
            [
                'team_name' => 'Ferrari',
                'dashboard_link' => 'http://gtxreport/embed.html?t=d&i=265'
            ]
        ];
        
        return [
            'success' => true,
            'ip_authorized' => true,
            'ip_address' => $ipAddress,
            'team_links' => $teamLinks
        ];
    }

    /**
     * Get client IP address from request
     */
    private function getClientIpAddress()
    {
        // Check for IP in various headers (for proxies/load balancers)
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key] ?? null)) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs (X-Forwarded-For can have multiple)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP (allow private and reserved ranges for internal networks)
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        // Fallback to REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

