<?php
/**
 * Ypsilon Booking Service - Business Logic Layer
 */

namespace App\Services;

use App\DAL\YpsilonBookingDAL;
use Exception;

class YpsilonBookingService
{
    private $dal;

    public function __construct()
    {
        $this->dal = new YpsilonBookingDAL();
    }
    /**
     * Generate Ypsilon IBE URL from current URL
     */
    public function generateIbeUrl($currentUrl)
    {
        if (empty($currentUrl)) {
            throw new Exception('URL is required', 400);
        }
        
        // Validate URL format
        if (!filter_var($currentUrl, FILTER_VALIDATE_URL)) {
            throw new Exception('Invalid URL format', 400);
        }
        
        // Convert URL: replace 'gauratravel.com.au/booking-confirmation/' with 'flr.ypsilon.net'
        $ibeUrl = str_replace(
            'gauratravel.com.au/booking-confirmation/',
            'flr.ypsilon.net',
            $currentUrl
        );
        
        // Append no-further-redirect parameter if not already present
        if (strpos($ibeUrl, 'no-further-redirect') === false) {
            $separator = strpos($ibeUrl, '?') !== false ? '&' : '?';
            $ibeUrl .= $separator . 'no-further-redirect=1';
        }
        
        // HTML escape for safe use in HTML attributes
        $escapedUrl = htmlspecialchars($ibeUrl, ENT_QUOTES, 'UTF-8');
        
        return [
            'success' => true,
            'original_url' => $currentUrl,
            'ibe_url' => $ibeUrl,
            'ibe_url_escaped' => $escapedUrl,
            'iframe_config' => [
                'container_id' => 'ypsnet-ibe',
                'script_url' => 'https://flr.ypsilon.net/static/resize/ypsnet-ibe.min.js',
                'data_src' => $escapedUrl
            ]
        ];
    }
    
    /**
     * Get iframe HTML snippet for embedding Ypsilon IBE
     */
    public function getIframeSnippet($currentUrl)
    {
        $ibeData = $this->generateIbeUrl($currentUrl);
        
        return [
            'success' => true,
            'html' => '<div id="ypsnet-ibe" style="padding-top:50px;" data-src="' . $ibeData['ibe_url_escaped'] . '"></div>',
            'script_tag' => '<script src="https://flr.ypsilon.net/static/resize/ypsnet-ibe.min.js"></script>',
            'ibe_url' => $ibeData['ibe_url']
        ];
    }

    /**
     * Sync booking data from Ypsilon
     */
    public function syncBooking(array $payload): array
    {
        $orderId = isset($payload['order_id']) ? (int)$payload['order_id'] : null;
        $gdsPaxId = $payload['gds_pax_id'] ?? null;
        $ticketNumber = $payload['ticket_number'] ?? null;
        $baggage = $payload['baggage'] ?? null;
        $meal = $payload['meal'] ?? null;
        $email = $payload['email'] ?? null;
        $phone = $payload['phone'] ?? null;
        $pnr = $payload['pnr'] ?? null;
        $updatedBy = $payload['updated_by'] ?? 'ypsilon_sync';

        if (!$orderId) {
            throw new Exception('order_id is required', 400);
        }

        $timestamp = date('Y-m-d H:i:s');

        // Update ticket number if provided
        if ($ticketNumber !== null) {
            $this->dal->updateTicketNumberIfEmpty($orderId, $gdsPaxId, $ticketNumber);
        }

        // Update baggage and meal if provided
        if ($baggage !== null || $meal !== null) {
            $this->dal->updatePaxBaggageAndMeal($orderId, $gdsPaxId, $baggage, $meal);
        }

        // Update contact information if provided
        if ($email !== null || $phone !== null) {
            $this->dal->updatePaxContactIfEmpty($orderId, $email, $phone);
        }

        // Log history update
        if ($ticketNumber) {
            $this->dal->insertHistoryUpdate($orderId, 'ticket_number', $ticketNumber, $updatedBy, $timestamp);
        }

        return [
            'success' => true,
            'order_id' => $orderId,
            'synced_at' => $timestamp
        ];
    }
}

