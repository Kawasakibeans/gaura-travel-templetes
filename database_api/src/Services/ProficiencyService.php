<?php
/**
 * Proficiency Service - Business Logic Layer
 * Handles agent proficiency tier reports and analysis
 */

namespace App\Services;

use App\DAL\ProficiencyDAL;
use Exception;

class ProficiencyService
{
    private $dal;

    private $tierRules = [
        'Fundamental Awareness' => ['gtib' => 150, 'conversion' => 0.40, 'fcs' => 0.00, 'aht' => 2400, 'behavioural' => 1],
        'Novice' => ['gtib' => 150, 'conversion' => 0.40, 'fcs' => 0.15, 'aht' => 2400, 'behavioural' => 1],
        'Intermediate' => ['gtib' => 180, 'conversion' => 0.45, 'fcs' => 0.18, 'aht' => 2100, 'behavioural' => 1],
        'Advanced' => ['gtib' => 200, 'conversion' => 0.48, 'fcs' => 0.18, 'aht' => 1800, 'behavioural' => 1],
        'Experts Of GTX' => ['gtib' => 250, 'conversion' => 0.50, 'fcs' => 0.19, 'aht' => 1800, 'behavioural' => 1]
    ];

    public function __construct()
    {
        $this->dal = new ProficiencyDAL();
    }

    /**
     * Get proficiency report by tier
     */
    public function getProficiencyReport($tier, $team, $fromDate, $toDate)
    {
        // Validate inputs
        if (empty($tier) || empty($team) || empty($fromDate) || empty($toDate)) {
            throw new Exception('Tier, team, from_date, and to_date are required', 400);
        }

        // Validate tier
        if (!isset($this->tierRules[$tier])) {
            throw new Exception('Invalid tier. Must be one of: ' . implode(', ', array_keys($this->tierRules)), 400);
        }

        $rules = $this->tierRules[$tier];

        // Get proficiency data
        $agents = $this->dal->getProficiencyData($tier, $team, $fromDate, $toDate);

        // Process and analyze each agent
        $processedAgents = [];
        foreach ($agents as $agent) {
            $analysis = $this->analyzeAgentPerformance($agent, $rules);
            $processedAgents[] = array_merge($agent, $analysis);
        }

        return [
            'tier' => $tier,
            'team' => $team,
            'period' => [
                'from' => $fromDate,
                'to' => $toDate
            ],
            'tier_rules' => $rules,
            'agents' => $processedAgents,
            'total_count' => count($processedAgents)
        ];
    }

    /**
     * Get tier rules
     */
    public function getTierRules()
    {
        return [
            'tiers' => $this->tierRules
        ];
    }

    /**
     * Private helper methods
     */
    
    private function analyzeAgentPerformance($agent, $rules)
    {
        $criteriaMet = 0;

        // Check GTIB
        $gtibPass = $agent['gtib'] >= $rules['gtib'];
        if ($gtibPass) $criteriaMet++;

        // Check Conversion
        $conversionPass = $agent['conversion'] >= $rules['conversion'];
        if ($conversionPass) $criteriaMet++;

        // Check FCS
        $fcsPass = $agent['fcs'] >= $rules['fcs'];
        if ($fcsPass) $criteriaMet++;

        // Check AHT
        $ahtPass = $agent['AHT'] <= $rules['aht'];
        if ($ahtPass) $criteriaMet++;

        // Check Behavioural
        $behaviouralPass = $agent['behavioural'] < $rules['behavioural'];
        if ($behaviouralPass) $criteriaMet++;

        // Determine traffic light
        if ($criteriaMet >= 5) {
            $trafficLight = 'green';
            $summary = 'Met all 5 criteria';
        } elseif ($criteriaMet >= 3) {
            $trafficLight = 'yellow';
            $summary = 'Met 3 or 4 criteria';
        } else {
            $trafficLight = 'red';
            $summary = 'Met less than 3 criteria';
        }

        return [
            'criteria_met' => $criteriaMet,
            'gtib_pass' => $gtibPass,
            'conversion_pass' => $conversionPass,
            'fcs_pass' => $fcsPass,
            'aht_pass' => $ahtPass,
            'behavioural_pass' => $behaviouralPass,
            'traffic_light' => $trafficLight,
            'summary' => $summary,
            'conversion_percentage' => round($agent['conversion'] * 100, 2),
            'fcs_percentage' => round($agent['fcs'] * 100, 2)
        ];
    }
}

