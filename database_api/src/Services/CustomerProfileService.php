<?php
/**
 * Customer Profile Service - Business Logic Layer
 * Handles customer profile aggregation
 */

namespace App\Services;

use App\DAL\CustomerProfileDAL;
use Exception;

class CustomerProfileService
{
    private $profileDAL;

    public function __construct()
    {
        $this->profileDAL = new CustomerProfileDAL();
    }

    /**
     * Update customer profiles for a date window
     */
    public function updateCustomerProfiles($from, $to)
    {
        if (empty($from) || empty($to)) {
            throw new Exception('From and To dates are required', 400);
        }

        // Get active CRNs
        $crnRows = $this->profileDAL->getActiveCrnsInWindow($from, $to);
        
        if (empty($crnRows)) {
            return [
                'ok' => true,
                'window' => [$from, $to],
                'updated' => 0,
                'inserted' => 0,
                'note' => 'No CRNs with activity in window.'
            ];
        }

        $crns = array_column($crnRows, 'crn');
        
        // Get aggregates
        $websiteAgg = $this->profileDAL->getWebsiteActivityAggregates($crns);
        $bookingAgg = $this->profileDAL->getBookingActivityAggregates($crns);
        $callAgg = $this->profileDAL->getCallActivityAggregates($crns);
        $quoteAgg = $this->profileDAL->getQuoteAggregates($crns);
        $prefRoute = $this->profileDAL->getPreferredRoute($crns);
        $prefAirline = $this->profileDAL->getPreferredAirline($crns);
        $prefMonth = $this->profileDAL->getPreferredMonth($crns);
        $bookingUTM = $this->profileDAL->getBookingUTMSources($crns);
        $attrUTM = $this->profileDAL->getAttributionUTMSources($crns);

        // Build maps
        $webBy = [];
        foreach ($websiteAgg as $r) {
            $webBy[$r['crn']] = $r;
        }
        
        $bkBy = [];
        foreach ($bookingAgg as $r) {
            $bkBy[$r['crn']] = $r;
        }
        
        $callsBy = [];
        foreach ($callAgg as $r) {
            $callsBy[$r['crn']] = $r;
        }
        
        $quotesBy = [];
        foreach ($quoteAgg as $r) {
            $quotesBy[$r['crn']] = $r;
        }

        // Process preferred route/airline/month
        $prefRouteBy = [];
        foreach ($prefRoute as $r) {
            $cr = $r['crn'];
            $c = (int)$r['c'];
            if (!isset($prefRouteBy[$cr]) || $prefRouteBy[$cr]['c'] < $c) {
                $prefRouteBy[$cr] = ['route' => $r['route'], 'c' => $c];
            }
        }

        $prefAirBy = [];
        foreach ($prefAirline as $r) {
            $cr = $r['crn'];
            $c = (int)$r['c'];
            if (!isset($prefAirBy[$cr]) || $prefAirBy[$cr]['c'] < $c) {
                $prefAirBy[$cr] = ['airlines' => $r['airlines'], 'c' => $c];
            }
        }

        $prefMonthBy = [];
        foreach ($prefMonth as $r) {
            $cr = $r['crn'];
            $c = (int)$r['c'];
            if (!isset($prefMonthBy[$cr]) || $prefMonthBy[$cr]['c'] < $c) {
                $prefMonthBy[$cr] = ['mname' => $r['mname'], 'c' => $c];
            }
        }

        // Process UTM sources
        $srcByCrn = [];
        foreach ($bookingUTM as $r) {
            $srcByCrn[$r['crn']][] = $r;
        }
        foreach ($attrUTM as $r) {
            $srcByCrn[$r['crn']][] = $r;
        }

        $updated = 0;
        $inserted = 0;

        foreach ($crns as $crn) {
            $w = $webBy[$crn] ?? null;
            $b = $bkBy[$crn] ?? null;
            $c = $callsBy[$crn] ?? null;
            $q = $quotesBy[$crn] ?? null;

            $data = $this->buildProfileData($crn, $w, $b, $c, $q, $prefRouteBy, $prefAirBy, $prefMonthBy, $srcByCrn);

            if ($this->profileDAL->profileExists($crn)) {
                $this->profileDAL->updateProfile($crn, $data);
                $updated++;
            } else {
                $this->profileDAL->insertProfile($data);
                $inserted++;
            }
        }

        return [
            'ok' => true,
            'window' => [$from, $to],
            'crns_considered' => count($crns),
            'inserted' => $inserted,
            'updated' => $updated
        ];
    }

    private function buildProfileData($crn, $w, $b, $c, $q, $prefRouteBy, $prefAirBy, $prefMonthBy, $srcByCrn)
    {
        $totalLogins = (int)($w['total_logins'] ?? 0);
        $totalSearch = (int)($w['total_search'] ?? 0);
        $totalCheckout = (int)($w['total_checkout'] ?? 0);
        $totalBookings = (int)($b['total_bookings'] ?? 0);
        $fit = (int)($b['fit'] ?? 0);
        $gdeals = (int)($b['gdeals'] ?? 0);
        $totalValue = (float)($b['total_value'] ?? 0);

        $firstRegister = $w['first_activity'] ?? null;
        $lastActivity = $w['last_activity'] ?? null;
        $firstBooking = $b['first_booking'] ?? null;
        $lastBooking = $b['last_booking'] ?? null;
        $firstTravel = $b['first_travel'] ?? null;
        $lastTravel = $b['last_travel'] ?? null;
        $firstCallDt = $c['first_call_dt'] ?? null;
        $lastCallDt = $c['last_call_dt'] ?? null;
        $firstQuoteDt = $q['first_quote_dt'] ?? null;
        $lastQuoteDt = $q['last_quote_dt'] ?? null;

        $prefRouteVal = $prefRouteBy[$crn]['route'] ?? null;
        $prefAirVal = $prefAirBy[$crn]['airlines'] ?? null;
        $prefMonthVal = $prefMonthBy[$crn]['mname'] ?? null;

        $totalGtibCalls = (int)($c['total_gtib_calls'] ?? 0);
        $totalNonGtibCalls = (int)($c['total_non_gtib_calls'] ?? 0);
        $totalQuotes = (int)($q['total_quotes'] ?? 0);

        // Calculate booking frequency
        $bookingFrequency = 0.0;
        if ($firstBooking && $lastBooking) {
            $spanDays = (strtotime($lastBooking) - strtotime($firstBooking)) / 86400.0;
            $years = $spanDays > 0 ? ($spanDays / 365.25) : 1.0;
            $bookingFrequency = round($totalBookings / $years, 2);
        }

        // Determine tier
        $customerTier = 'White';
        if ($totalBookings >= 1 && $totalBookings <= 10) {
            $customerTier = 'Bronze';
        } elseif ($totalBookings <= 20) {
            $customerTier = 'Silver';
        } elseif ($totalBookings <= 30) {
            $customerTier = 'Gold';
        } elseif ($totalBookings > 30) {
            $customerTier = 'Platinum';
        }

        // Process UTM sources
        $srcCands = $srcByCrn[$crn] ?? [];
        $picked = $this->chooseFirstLastSources($srcCands);
        $firstSrc = $picked['first'];
        $lastSrc = $picked['last'];

        // Calculate create_date and last_updated_date
        $earliest = array_filter([$firstRegister, $firstBooking, $firstTravel, $firstCallDt, $firstQuoteDt]);
        $latest = array_filter([$lastActivity, $lastBooking, $lastTravel, $lastCallDt, $lastQuoteDt]);
        
        if (!empty($srcCands)) {
            $dates = array_column($srcCands, 'dt');
            $dates = array_filter($dates);
            if ($dates) {
                $earliest[] = min($dates);
                $latest[] = max($dates);
            }
        }
        
        $createDate = $earliest ? min($earliest) : date('Y-m-d H:i:s');
        $lastUpdatedDt = $latest ? max($latest) : date('Y-m-d H:i:s');

        return [
            'crn' => $crn,
            'total_logins' => $totalLogins,
            'total_search' => $totalSearch,
            'total_checkout' => $totalCheckout,
            'total_bookings' => $totalBookings,
            'fit' => $fit,
            'gdeals' => $gdeals,
            'total_value' => $totalValue,
            'prefered_route' => $prefRouteVal,
            'preferred_airlines' => $prefAirVal,
            'prefered_month' => $prefMonthVal,
            'first_utm_campaign' => $firstSrc['utm_campaign'] ?? null,
            'first_utm_source' => $firstSrc['utm_source'] ?? null,
            'first_utm_medium' => $firstSrc['utm_medium'] ?? null,
            'first_utm_final_source' => $firstSrc['utm_final_source'] ?? null,
            'last_utm_campaign' => $lastSrc['utm_campaign'] ?? null,
            'last_utm_source' => $lastSrc['utm_source'] ?? null,
            'last_utm_medium' => $lastSrc['utm_medium'] ?? null,
            'last_utm_final_source' => $lastSrc['utm_final_source'] ?? null,
            'first_register_date' => $firstRegister,
            'last_activity_date' => $lastActivity,
            'first_booking_date' => $firstBooking,
            'last_booking_date' => $lastBooking,
            'first_travel_date' => $firstTravel,
            'last_travel_date' => $lastTravel,
            'cutomer_tier' => $customerTier,
            'booking_frequency' => $bookingFrequency,
            'booking_score' => 0,
            'marketing_score' => 0,
            'orm_score' => 0,
            'total_gtib_calls' => $totalGtibCalls,
            'total_non_gtib_calls' => $totalNonGtibCalls,
            'first_call_date' => $firstCallDt,
            'last_call_date' => $lastCallDt,
            'total_quotes' => $totalQuotes,
            'first_quote_date' => $firstQuoteDt,
            'last_quote_date' => $lastQuoteDt,
            'create_date' => $createDate,
            'last_updated_date' => $lastUpdatedDt
        ];
    }

    private function chooseFirstLastSources($cands)
    {
        $good = [];
        $any = [];
        
        foreach ($cands as $c) {
            $src = $c['utm_source'] ?? null;
            $fin = $c['utm_final_source'] ?? ($src ?? null);
            if ((!$src || trim($src) === '') && (!$fin || trim($fin) === '')) {
                continue;
            }
            
            $row = [
                'date' => $c['dt'] ?? '1970-01-01 00:00:00',
                'utm_campaign' => $c['utm_campaign'] ?? null,
                'utm_source' => $src,
                'utm_medium' => $c['utm_medium'] ?? null,
                'utm_final_source' => $fin
            ];
            
            $any[] = $row;
            if (!$this->isBadSource($src)) {
                $good[] = $row;
            }
        }
        
        $cmpAsc = function($a, $b) {
            return strcmp($a['date'], $b['date']);
        };
        $cmpDesc = function($a, $b) {
            return strcmp($b['date'], $a['date']);
        };
        
        usort($good, $cmpAsc);
        usort($any, $cmpAsc);
        $first = $good ? $good[0] : ($any[0] ?? null);
        
        usort($good, $cmpDesc);
        usort($any, $cmpDesc);
        $last = $good ? $good[0] : ($any[0] ?? null);
        
        $blank = [
            'utm_campaign' => null,
            'utm_source' => null,
            'utm_medium' => null,
            'utm_final_source' => null
        ];
        
        return [
            'first' => $first ?: $blank,
            'last' => $last ?: $blank
        ];
    }

    private function isBadSource($src)
    {
        if ($src === null) return true;
        $s = trim(strtolower($src));
        if ($s === '') return true;
        if ($s === 'direct') return true;
        if ($s === '(data not available)') return true;
        if (strpos($s, 'yahoo.com') !== false) return true;
        return false;
    }
}

