<?php
/**
 * Template Name: Incentive Pathway
 * Template Post Type: post, page
 */


// All database operations are handled through API endpoints defined in database_api_test_pamitha/routes/api.php

// API Configuration
$apiBaseUrl = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates/database_api/public';

// API Helper Functions
function fetchIncentiveCriteriaPeriodsFromAPI(): array {
    global $apiBaseUrl;
    $endpoint = rtrim($apiBaseUrl, '/') . '/v1/incentive-criteria-periods';
    
    try {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("API Error for incentive-criteria-periods: " . $curlError);
            return [];
        }
        
        if ($httpCode !== 200) {
            error_log("API HTTP Error for incentive-criteria-periods: Status code " . $httpCode . ", Response: " . $response);
            return [];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("API JSON Error for incentive-criteria-periods: " . json_last_error_msg() . ", Response: " . $response);
            return [];
        }
        
        // Handle different response formats
        if (isset($data['data']) && is_array($data['data'])) {
            $periods = array_column($data['data'], 'period');
            return array_unique($periods);
        } elseif (isset($data['periods']) && is_array($data['periods'])) {
            return $data['periods'];
        } elseif (is_array($data) && !empty($data)) {
            return array_unique($data);
        }
        
        return [];
    } catch (Exception $e) {
        error_log("API Exception for incentive-criteria-periods: " . $e->getMessage());
        return [];
    }
}

function fetchAgentTargetPathwayListFromAPI(string $rosterCode, string $period): ?array {
    global $apiBaseUrl;
    $endpoint = rtrim($apiBaseUrl, '/') . '/v1/agent-target-pathway/list';
    
    $params = [
        'roster_code' => $rosterCode,
        'period' => $period
    ];
    
    $url = $endpoint . '?' . http_build_query($params);
    
    try {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("API Error for agent-target-pathway/list: " . $curlError);
            return null;
        }
        
        if ($httpCode !== 200) {
            error_log("API HTTP Error for agent-target-pathway/list: Status code " . $httpCode . ", Response: " . $response);
            return null;
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("API JSON Error for agent-target-pathway/list: " . json_last_error_msg() . ", Response: " . $response);
            return null;
        }
        
        // Handle different response formats
        if (isset($data['data']) && is_array($data['data'])) {
            return !empty($data['data']) ? $data['data'][0] : null;
        } elseif (is_array($data) && !empty($data)) {
            return $data;
        }
        
        return null;
    } catch (Exception $e) {
        error_log("API Exception for agent-target-pathway/list: " . $e->getMessage());
        return null;
    }
}

function insertAgentTargetPathwayHistoryFromAPI(array $historyData): bool {
    global $apiBaseUrl;
    $endpoint = rtrim($apiBaseUrl, '/') . '/v1/agent-target-pathway/history';
    
    try {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($historyData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("API Error for agent-target-pathway/history: " . $curlError);
            return false;
        }
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            error_log("API HTTP Error for agent-target-pathway/history: Status code " . $httpCode . ", Response: " . $response);
            return false;
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("API JSON Error for agent-target-pathway/history: " . json_last_error_msg() . ", Response: " . $response);
            return false;
        }
        
        return isset($data['success']) ? $data['success'] : ($httpCode === 200 || $httpCode === 201);
    } catch (Exception $e) {
        error_log("API Exception for agent-target-pathway/history: " . $e->getMessage());
        return false;
    }
}

function fetchAgentNameByRosterCodeFromAPI(string $rosterCode): ?string {
    global $apiBaseUrl;
    $endpoint = rtrim($apiBaseUrl, '/') . '/v1/agent-codes-agent-name-by-roster';
    
    $params = [
        'roster_code' => $rosterCode
    ];
    
    $url = $endpoint . '?' . http_build_query($params);
    
    try {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("API Error for agent-codes-agent-name-by-roster: " . $curlError);
            return null;
        }
        
        if ($httpCode !== 200) {
            error_log("API HTTP Error for agent-codes-agent-name-by-roster: Status code " . $httpCode . ", Response: " . $response);
            return null;
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("API JSON Error for agent-codes-agent-name-by-roster: " . json_last_error_msg() . ", Response: " . $response);
            return null;
        }
        
        // Handle different response formats
        if (isset($data['data']['agent_name'])) {
            return $data['data']['agent_name'];
        } elseif (isset($data['agent_name'])) {
            return $data['agent_name'];
        } elseif (isset($data['data']) && is_array($data['data']) && isset($data['data'][0]['agent_name'])) {
            return $data['data'][0]['agent_name'];
        }
        
        return null;
    } catch (Exception $e) {
        error_log("API Exception for agent-codes-agent-name-by-roster: " . $e->getMessage());
        return null;
    }
}

function upsertAgentTargetPathwayFromAPI(array $pathwayData): bool {
    global $apiBaseUrl;
    $endpoint = rtrim($apiBaseUrl, '/') . '/v1/agent-target-pathway';
    
    try {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pathwayData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("API Error for agent-target-pathway: " . $curlError);
            return false;
        }
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            error_log("API HTTP Error for agent-target-pathway: Status code " . $httpCode . ", Response: " . $response);
            return false;
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("API JSON Error for agent-target-pathway: " . json_last_error_msg() . ", Response: " . $response);
            return false;
        }
        
        return isset($data['success']) ? $data['success'] : ($httpCode === 200 || $httpCode === 201);
    } catch (Exception $e) {
        error_log("API Exception for agent-target-pathway: " . $e->getMessage());
        return false;
    }
}

function fetchActiveAgentCodesFromAPI(): array {
    global $apiBaseUrl;
    $endpoint = rtrim($apiBaseUrl, '/') . '/v1/agent-codes-active-roster';
    
    try {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("API Error for agent-codes-active-roster: " . $curlError);
            return [];
        }
        
        if ($httpCode !== 200) {
            error_log("API HTTP Error for agent-codes-active-roster: Status code " . $httpCode . ", Response: " . $response);
            return [];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("API JSON Error for agent-codes-active-roster: " . json_last_error_msg() . ", Response: " . $response);
            return [];
        }
        
        // Handle different response formats
        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        } elseif (is_array($data)) {
            return $data;
        }
        
        return [];
    } catch (Exception $e) {
        error_log("API Exception for agent-codes-active-roster: " . $e->getMessage());
        return [];
    }
}

$allPeriods = fetchIncentiveCriteriaPeriodsFromAPI();
// Sort descending (most recent first) if not already sorted
rsort($allPeriods);
$latestPeriod = $allPeriods[0] ?? '';

$period = $_GET['period'] ?? $latestPeriod;
$periodIndex = array_search($period, $allPeriods);
$periodIndex = $periodIndex !== false ? $periodIndex : 0;
$periods = array_slice($allPeriods, $periodIndex, 4);

$selectedPeriod = $period;

// Fetch incentive criteria from API instead of requiring local file
function fetchIncentiveCriteriaFromAPI(string $period): array {
    global $apiBaseUrl;
    $endpoint = rtrim($apiBaseUrl, '/') . '/v1/agent-incentive/criteria';
    
    $params = ['period' => $period];
    $url = $endpoint . '?' . http_build_query($params);
    
    try {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($curlError)) {
            error_log("API Error for agent-incentive/criteria: " . $curlError);
            return [];
        }
        
        if ($httpCode !== 200) {
            error_log("API HTTP Error for agent-incentive/criteria: Status code " . $httpCode . ", Response: " . $response);
            return [];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("API JSON Error for agent-incentive/criteria: " . json_last_error_msg() . ", Response: " . $response);
            return [];
        }
        
        // Handle different response formats
        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        } elseif (is_array($data)) {
            return $data;
        }
        
        return [];
    } catch (Exception $e) {
        error_log("API Exception for agent-incentive/criteria: " . $e->getMessage());
        return [];
    }
}

// Fetch criteria data from API
$criteriaData = fetchIncentiveCriteriaFromAPI($period);

// Initialize variables with defaults
$slabs = $criteriaData['slabs'] ?? [];
$fcs_multipliers = $criteriaData['fcs_multipliers'] ?? [];
$daily_fcs_multipliers = $criteriaData['daily_fcs_multipliers'] ?? [];
$daily_bonus = $criteriaData['daily_bonus'] ?? [];
$eligibility = $criteriaData['eligibility'] ?? [];
$daily_eligibility = $criteriaData['daily_eligibility'] ?? [];
$daily_incentives = $criteriaData['daily_incentives'] ?? [];

// Convert slabs array to indexed array if it's an associative array
if (!empty($slabs) && is_array($slabs)) {
    // Check if it's an associative array (keys are not sequential numbers)
    $keys = array_keys($slabs);
    $isAssociative = !empty($keys) && !is_numeric($keys[0]);
    
    if ($isAssociative) {
        // Convert associative array to indexed array
        $slabsArray = [];
        foreach ($slabs as $key => $slab) {
            if (is_array($slab)) {
                // Ensure conversion is set
                if (!isset($slab['conversion'])) {
                    $slab['conversion'] = is_numeric($key) ? (int)$key : 0;
                }
                $slabsArray[] = $slab;
            }
        }
        $slabs = $slabsArray;
    } else {
        // Already indexed, just ensure conversion is set
        foreach ($slabs as &$slab) {
            if (is_array($slab) && !isset($slab['conversion'])) {
                $slab['conversion'] = 0;
            }
        }
        unset($slab);
    }
} else {
    $slabs = [];
}

// Handle selected FCS Multiplier (pick highest selected)
$fcsMultiplier = 1;
if (!empty($_GET['fcs'])) {
    $fcsValues = array_map('floatval', $_GET['fcs']);
    $fcsMultiplier = max($fcsValues);
}

// Handle selected bonus types
$selectedBonuses = $_GET['bonus'] ?? [];
$gtibBonusType = $selectedBonuses[0] ?? null;

// === AJAX handler for saving selection ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_pathway') {
    header('Content-Type: application/json');

    $roster_code = $_POST['roster_code'] ?? '';
    $period = $_POST['period'] ?? '';
    $target = isset($_POST['target']) ? (int)$_POST['target'] : 0;
    $conversion = isset($_POST['conversion']) ? (int)$_POST['conversion'] : 0;
    $rate = isset($_POST['rate']) ? (float)$_POST['rate'] : 0;
    $fcs_mult = isset($_POST['fcs_mult']) ? (float)$_POST['fcs_mult'] : 0;
    $rate_fcs = isset($_POST['rate_fcs']) ? (float)$_POST['rate_fcs'] : 0;
    $gtib_bonus = isset($_POST['gtib_bonus']) ? (float)$_POST['gtib_bonus'] : 0;
    $min_gtib = isset($_POST['min_gtib']) ? (int)$_POST['min_gtib'] : 0;
    $min_pif = isset($_POST['min_pif']) ? (int)$_POST['min_pif'] : 0;
    $daily_pif = isset($_POST['daily_pif']) ? (int)$_POST['daily_pif'] : 0;
    $total_estimate = isset($_POST['total_estimate']) ? (float)$_POST['total_estimate'] : 0;

    if (!$roster_code || !$period || !$target || !$conversion) {
        echo json_encode(['success' => false, 'message' => 'Missing required data']);
        exit;
    }
    // Insert data to pathway_history table 
    // First check if record exists
    $existingPathway = fetchAgentTargetPathwayListFromAPI($roster_code, $period);
    // NEWCODE
    // If exists, copy to history before updating
    if ($existingPathway) {
        $historyData = [
            'roster_code' => $existingPathway['roster_code'],
            'target' => $existingPathway['target'],
            'period' => $existingPathway['period'],
            'conversion' => $existingPathway['conversion'],
            'rate' => $existingPathway['rate'],
            'fcs_mult' => $existingPathway['fcs_mult'],
            'rate_fcs' => $existingPathway['rate_fcs'],
            'gtib_bonus' => $existingPathway['gtib_bonus'],
            'min_gtib' => $existingPathway['min_gtib'],
            'min_pif' => $existingPathway['min_pif'],
            'daily_pif' => $existingPathway['daily_pif'],
            'total_estimate' => $existingPathway['total_estimate'],
            'created_at' => $existingPathway['created_at'] ?? date('Y-m-d H:i:s')
        ];
        
        insertAgentTargetPathwayHistoryFromAPI($historyData);
    }
    
    // Insert/update the pathway

    $agent_name = fetchAgentNameByRosterCodeFromAPI($roster_code);

    if (!$agent_name) {
        echo json_encode(['success' => false, 'message' => 'Agent not found']);
        exit;
    }

    $pathwayData = [
        'roster_code' => $roster_code,
        'target' => $target,
        'period' => $period,
        'conversion' => $conversion,
        'rate' => $rate,
        'fcs_mult' => $fcs_mult,
        'rate_fcs' => $rate_fcs,
        'gtib_bonus' => $gtib_bonus,
        'min_gtib' => $min_gtib,
        'min_pif' => $min_pif,
        'daily_pif' => $daily_pif,
        'total_estimate' => $total_estimate
    ];
    
    $success = upsertAgentTargetPathwayFromAPI($pathwayData);

    echo json_encode(['success' => $success]);
    exit;
}

$target = isset($_GET['target']) ? (int)$_GET['target'] : 0;
$periodList = $allPeriods;
$agents = fetchActiveAgentCodesFromAPI();
// Sort by agent_name if not already sorted
usort($agents, function($a, $b) {
    return strcmp($a['agent_name'] ?? '', $b['agent_name'] ?? '');
});

$pathways = [];
foreach ($slabs as $slab) {
    $conv = (int) $slab['conversion'];
    $rate = $slab['rate_per_pax'] ?? 0;
    if ($rate <= 0) continue;

    $rateFCS = $rate * $fcsMultiplier;
    $gtibBonus = $gtibBonusType ? ($slab[$gtibBonusType] ?? 0) : 0;

    $remainingIncentive = max(0, $target - $gtibBonus);
    $requiredPIF = ceil($remainingIncentive / $rateFCS);
    $fcsRequiredPct   = isset($eligibility['fcs_min_percent']) ? (float)$eligibility['fcs_min_percent'] : 30;
    $dailyPIF = ceil($requiredPIF / 10);
    $basereward = $requiredPIF * $rate;
    $finalEstimate = $requiredPIF * $rateFCS + $gtibBonus; 
    $minGTIBRaw = ceil($requiredPIF / ($conv / 100));
    $minGTIBFromPolicy = (int)($eligibility['gtib_min_calls'] ?? 0);
    $minGTIB = max($minGTIBRaw, $minGTIBFromPolicy);
    
    // 🧠 Apply threshold forcing if GTIB bonus is selected
    $isManualBonusSelected = false;
    $selectedBonusGTIB = null;
    
    if ($gtibBonusType && preg_match('/^gtib_(\d+)_bonus$/', $gtibBonusType, $match)) {
        $selectedBonusGTIB = (int)$match[1];
        $isManualBonusSelected = true;
    
        // Force the GTIB if it's below threshold
        if ($minGTIB < $selectedBonusGTIB) {
            $minGTIB = $selectedBonusGTIB;
            // 🔁 Recalculate PIF and Incentives
            $requiredPIF = ceil($minGTIB * ($conv / 100));
            $dailyPIF = ceil($requiredPIF / 10);
            $basereward = $requiredPIF * $rate;
            $finalEstimate = $requiredPIF * $rateFCS + $gtibBonus;
        }
    }


    $efficiency = $finalEstimate / max(1, ($minGTIB + $requiredPIF)); // avoid division by 0
    
    // 🧠 Detect if user selected GTIB bonus manually
    if (preg_match('/^gtib_(\d+)_bonus$/', $gtibBonusType, $m)) {
        $selectedBonusGTIB = (int)$m[1];
        $isManualBonusSelected = true;
    }
    
    $gtibBonuses = [];

// 🧠 Collect all GTIB bonuses for current conversion
foreach ($slabs as $slab) {
    if ((int)($slab['conversion'] ?? 0) !== $conv) continue;

    foreach ($slab as $key => $val) {
        if (preg_match('/^gtib_(\d+)_bonus$/', $key, $m)) {
            $gtibBonuses[(int)$m[1]] = $val;
        }
    }
}

// ✅ Sort keys descending to get the highest threshold first
krsort($gtibBonuses);

// 🔥 Get max threshold
$maxAvailableGTIBBonusThreshold = key($gtibBonuses); // will be the highest threshold
$minGTIBBonusThreshold = $gtibBonuses ? min(array_keys($gtibBonuses)) : PHP_INT_MAX;


    
    // ❌ Skip pathway if GTIB requirement is below the policy-defined minimum
    // if (
    //     $minGTIBRaw < $minGTIBFromPolicy ||
    //     ($isManualBonusSelected &&
    //      $maxAvailableGTIBBonusThreshold !== null &&
    //      $selectedBonusGTIB < $maxAvailableGTIBBonusThreshold &&
    //      $minGTIBRaw > $maxAvailableGTIBBonusThreshold)
    // ) {
    //     echo "<!-- Skipped pathway: GTIB Raw = {$minGTIBRaw}, Min Policy = {$minGTIBFromPolicy}, Selected Bonus = {$isManualBonusSelected}, Max Available Bonus Threshold = {$maxAvailableGTIBBonusThreshold} -->";
    //     continue;
    // }

    if (!$isManualBonusSelected && $minGTIB > $minGTIBRaw) {
        $requiredPIF = ceil($minGTIB * ($conv / 100));
        $dailyPIF = ceil($requiredPIF / 10);
        $basereward = $requiredPIF * $rate;
        $finalEstimate = $requiredPIF * $rateFCS; // No GTIB bonus in this case
    }

    $buffer = 500000; // Show plans up to ₹500,000 above the target
    $shouldDisplay = true;

    if (!$isManualBonusSelected && $minGTIB > $minGTIBBonusThreshold) {
        // ❌ Hide if no bonus selected and GTIB exceeds lowest threshold
        $shouldDisplay = false;
    }
    
    if ($isManualBonusSelected && $minGTIB > $maxAvailableGTIBBonusThreshold) {
        // ❌ Hide if bonus selected and GTIB exceeds max eligible threshold
        $shouldDisplay = false;
    }
    
    if (
        $shouldDisplay &&
        $finalEstimate >= $target &&
        $finalEstimate <= ($target + $buffer)
    ) {
        $pathways[] = [
            'conversion' => $conv,
            'rate' => $rate,
            'fcs_mult' => $fcsMultiplier,
            'rate_fcs' => $rateFCS,
            'rate_per_pax' => $rate,
            'gtib_bonus' => $gtibBonus,
            'min_gtib' => $minGTIB,
            'min_pif' => $requiredPIF,
            'daily_pif' => $dailyPIF,
            'total_estimate' => $finalEstimate,
            'efficiency' => $efficiency,
            'base_reward' => $basereward,
            'reward' => $finalEstimate,
            'gtib' => $minGTIB,
            'pif' => $requiredPIF
        ];
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <meta charset="UTF-8">
  <title>Agent Incentive Pathway Planner</title>
  <style>
    body { font-family: sans-serif; background: #f8f9fa; padding: 20px;font-size: 28px; }
    table { width: 100%; border-collapse: collapse; background: #fff; margin-top: 20px;font-size: 22px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
    th { background: #ffd207; }
    .highlight { color: green; font-weight: bold; }
    .btn { padding: 6px 12px; background: #007bff; color: white; border: none; cursor: pointer;font-size: 20px; }
    select, input { padding: 6px; }

    /* Modal styles */
    #agentModal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0; top: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.5);
      justify-content: center;
      align-items: center;
    }
    #agentModalContent {
      background: white;
      padding: 20px;
      border-radius: 8px;
      width: 700px;
      height:700px;
      max-width: 90%;
    }
    #agentModal select {
      width: 100%;
      font-size: 16px;
      margin-bottom: 12px;
      height: 80%;
    }
  .btn-group-toggle input[type="checkbox"] {
    display: none;
  }

  .btn-group-toggle .btn.active {
    background-color: #007bff;
    color: #fff;
    font-weight: bold;
    border-color: #007bff;
  }

  .btn-group-toggle .btn.btn-outline-success.active {
    background-color: #28a745;
    border-color: #28a745;
  }
  label.greyed-out {
      opacity: 0.5;
      cursor: not-allowed;
    }
  .btn-group-toggle input[type="checkbox"] {
    display: none;
  }
  .btn-group-toggle .btn.active {
    font-weight: bold;
    background-color: #007bff;
    color: #fff;
    border-color: #007bff;
  }
  .btn-group-toggle .btn.btn-outline-success.active {
    background-color: #28a745;
    border-color: #28a745;
  }
  .fa-info-circle:hover {
    color: #0056b3;
  }
  .pick-select-btn {
      font-weight: bold;
      font-size: 16px;
      padding: 8px 16px;
      border-radius: 6px;
      background: linear-gradient(90deg, #ffc107, #ff9800);
      border: none;
      color: #000;
      display: inline-block;
      margin: auto;
      transition: transform 0.2s ease;
    }
    
    .pick-select-btn:hover {
      transform: scale(1.05);
      background: linear-gradient(90deg, #ffb300, #ff7043);
      color: #fff;
    }
    #scenarioForecastModal .modal-dialog {
      max-width: 100%;
      width: 100%;
      margin: 0;
    }
    
    #scenarioForecastModal .modal-content {
      width: 100%;
      border-radius: 0;
    }

    #forecastModalBody table {
      font-size: 20px;
      width: 100%;
      table-layout: auto;
    }
    
    #forecastModalBody th,
    #forecastModalBody td {
      padding: 10px 15px;
      word-wrap: break-word;
    }
    .btn-group-toggle .btn {
      font-size: 20px;        /* Increase text size */
      padding: 18px 22px;     /* Increase padding for bigger button */
      border-radius: 6px;     /* Optional: rounded corners */
    }
    /* FCS Buttons */
    .form-group .btn-outline-info {
      font-size: 22px;
      padding: 10px 20px;
    }
    
    /* GTIB Bonus Buttons */
    .form-group .btn-outline-success {
      font-size: 22px;
      padding: 10px 20px;
    }
    /* Make all toggle buttons bold */
    .btn-group-toggle .btn {
      font-weight: bold;
    }
    th {
      text-align: center !important;
      vertical-align: middle;
      font-size: 20px;
    }
    td {
      font-size: 26px; /* Adjust size as needed */
    }

  </style>
</head>
<body>

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">


<h2 class="mb-4 text-primary">🎯 Incentive Pathway Planner</h2>
<!-- Video Section -->
  <div style="width: 1300px; margin: 32px auto 16px auto; padding: 0 8px;">
    <div style="font-family:'Orbitron', Arial, sans-serif; font-size:1.35rem; font-weight:1200; color:#000000; text-align:center; margin-bottom:12px;">
      Incentive Pathway Instruction Video
    </div>
    <!--<div style="color:#f7f7f7; background:rgba(20,24,38,0.92); border-radius:10px; padding:14px 20px 14px 20px; text-align:center; font-size:1.1rem; margin-bottom:22px; box-shadow:0 1px 6px #2227;">-->
    <!--  Get ready for some MAYHEM on the racetrack this month? <br>-->
    <!--  Round 3 is already here, so there's no time like the present to get revved up and ready to go<br>-->
    <!--  <em>Track your progress, compare results, and cheer on your team!</em>-->
    <!--</div>-->
    <div style="border-radius:16px; overflow:hidden; box-shadow:0 4px 32px #0007;">
      <iframe width="100%" height="285"
        src="https://www.youtube.com/embed/tkhGB6fsfGU?si=Gond0B_SBIw0rN6u"
        title="Incentive Pathway Instruction Video"
        frameborder="0"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        referrerpolicy="strict-origin-when-cross-origin"
        allowfullscreen>
      </iframe>
    </div>
  </div>
<div class="d-flex justify-content-center">
<!--Filter and option-->
<form method="GET" class="bg-white shadow rounded p-3" style="font-size: 18px;">
  <table class="table table-bordered mb-0" style="width: auto;">
    <thead class="thead-light text-center">
      <tr>
        <th>🎯 Target</th>
        <th>📅 Period</th>
        <th>FCS</th>
        <th>GTIB</th>
        <th>🔄 Calculate</th>
        <!--<th>📄 Download My Target Commitment</th>-->
      </tr>
    </thead>
    <tbody class="text-center align-middle">
      <tr>
        <!-- Target input -->
        <td>
          <label style="font-weight: bold;">
            <input type="number" name="target" class="form-control text-center" style="height: 70px; font-size: 20px; width: 130px;" value="<?= htmlspecialchars($target) ?>" required>
          </label>
        </td>


        <!-- Period select -->
        <td>
      <select name="period" class="form-control form-control-lg text-center" style="width: 170px; font-size: 20px; height: 70px;">
          <?php foreach ($periodList as $p): ?>
            <?php
              // Convert "2025-05-21_to_2025-05-31" to "21 – 31 MAY"
              $formattedLabel = $p;
              if (strpos($p, '_to_') !== false) {
                list($start, $end) = explode('_to_', $p);
                $startDay = date('j', strtotime($start));
                $endDay = date('j', strtotime($end));
                $month = strtoupper(date('M', strtotime($end))); // using end date for month
                $formattedLabel = "$startDay – $endDay $month";
              }
            ?>
            <option value="<?= $p ?>" <?= $p === $period ? 'selected' : '' ?>><?= $formattedLabel ?></option>
          <?php endforeach; ?>
        </select>
      
    </td>


        <!-- FCS checkboxes -->
        <td>
          <div class="btn-group-toggle" data-toggle="buttons">
            <?php foreach ($fcs_multipliers as $key => $value): ?>
              <?php $isChecked = in_array((string)$value, $_GET['fcs'] ?? []); ?>
              <label class="btn btn-outline-info mr-1 <?= $isChecked ? 'active' : '' ?>" style="font-weight: bold;">
                <input type="checkbox" name="fcs[]" value="<?= $value ?>" autocomplete="off" <?= $isChecked ? 'checked' : '' ?>>
                <?= htmlspecialchars($key) ?>%
              </label>
            <?php endforeach; ?>
          </div>
        </td>

        <!-- GTIB checkboxes -->
        <td>
          <div class="btn-group-toggle" data-toggle="buttons">
            <?php
              $bonusTypes = [];
              foreach ($slabs as $slab) {
                foreach ($slab as $key => $val) {
                  if (preg_match('/^gtib_\\d+_bonus$/', $key)) {
                    $bonusTypes[$key] = true;
                  }
                }
              }
            ?>
            <?php foreach (array_keys($bonusTypes) as $bonusKey): ?>
              <?php $isChecked = in_array($bonusKey, $_GET['bonus'] ?? []); ?>
              <label class="btn btn-outline-success mr-1 <?= $isChecked ? 'active' : '' ?>" style="font-weight: bold;">
                <input type="checkbox" name="bonus[]" value="<?= $bonusKey ?>" autocomplete="off" <?= $isChecked ? 'checked' : '' ?>>
                <?= strtoupper(str_replace('_', ' ', $bonusKey)) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </td>

        <!-- Submit -->
        <td>
          <button type="submit" class="btn btn-primary" style="height: 70px; font-size: 20px; min-width: 200px;">
            <i class="fas fa-sync-alt"></i> Calculate
          </button>
        </td>
        <td>
          <button id="downloadTargetPdfBtn" style="display: none;" class="btn btn-warning mt-3">
                  📄 Download My Target Commitment
                </button>
        </td>
      </tr>
    </tbody>
  </table>
</form>
</div>



<!--//Download PDF start-->
<div style="height:0; overflow:hidden;">
<div id="pdfContent" style="width: 800px; margin: 0 auto; font-family: 'Helvetica', sans-serif; padding: 20px;">
  <!-- Header Banner -->
  <div style="background-color: #2173CB; color: white; text-align: center; padding: 10px 0; font-size: 18px; font-weight: bold;">
    🎯 Personal Commitment - Incentive Target
  </div>

  <!-- Info Box -->
  <div style="border: 1px solid #000; border-radius: 6px; padding: 15px; margin-top: 20px;">
    <p>👤 <strong>Agent Name:</strong> <span id="agentName"></span></p>
    <p>📅 <strong>Target Period:</strong> <span id="targetPeriod"></span></p>
    <p>💰 <strong>Target Amount:</strong> ₹<span id="targetAmount"></span></p>
    <p>📞 <strong>GTIB Calls Required:</strong> <span id="gtib"></span></p>
    <p>🎫 <strong>PIF (Paid in Full):</strong> <span id="pif"></span></p>
    <p>📈 <strong>FCS:</strong><span id="fcs"></span>%</p>
    <p>📊 <strong>Conversion:</strong> <span id="conversion"></span>%</p>
  </div>

  <!-- Motivation Box -->
  <div style="background-color: #fff3cd; padding: 15px; margin-top: 25px; border-radius: 4px; font-style: italic;">
    ✨ This is more than just numbers. It’s your promise to grow,<br />
    to push boundaries, and to achieve greatness.<br />
    Sign below, own your journey, and make it count.
  </div>

  <!-- Signature Line -->
  <div style="margin-top: 100px;">
    <hr style="width: 200px; border-top: 1px solid #999;" />
    <p style="font-size: 12px;">Agent Signature</p>
  </div>

  <!-- Footer -->
  <p style="font-size: 30px; color: #666;">Generated on: <span id="generatedTime"></span></p>
</div>
</div>



<script>
  document.querySelectorAll('#modeSelector input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', () => {
      document.querySelector('form').submit();
    });
  });
</script>
<?php
// ✅ Calculate which pathway has the best bonus efficiency
// $bestEfficiency = 0;
// $bestEfficiencyIndex = -1;
// foreach ($pathways as $index => $p) {
//     if ($p['efficiency'] > $bestEfficiency) {
//         $bestEfficiency = $p['efficiency'];
//         $bestEfficiencyIndex = $index;
//     }
// }
// 🔽 Sort by conversion descending
usort($pathways, function ($a, $b) {
    return (int)$b['conversion'] <=> (int)$a['conversion'];
});
?>

<?php if (!empty($pathways)): ?>
  <table>
   <thead>
  <tr>
    <th>
      Conv (PIF)
      <i class="fas fa-info-circle text-primary ml-1"
         style="cursor:pointer;"
         data-toggle="modal"
         data-target="#conversionInfoModal"
         title="What does this mean?"></i>
    </th>
    <th>Min GTIB</th>
    <th>Min PIF</th>
    <th>Daily PIF</th>
    <th>Base/Pax</th>
    <th>FCS/Pax</th>
    <th>Total/pax</th>    
    <th>Base Incentive</th>
    <th>
      <?= isset($_GET['bonus'][0]) ? ucwords(str_replace('_', ' ', $_GET['bonus'][0])) : 'GTIB BONUS' ?>
    </th>
    <th>Total Incentive</th>
    <th>Select</th>
  </tr>
</thead>
<tbody>
      <?php
//$fcsLabelsByValue = array_flip($fcs_multipliers);
?>

<?php foreach ($pathways as $i => $p): ?>
    <?php
    $conversion = $p['conversion'];
    $minGTIB = $p['min_gtib'];
    $dailyPIF = $p['daily_pif'] ?? 0;
    $targetAmount = $p['reward'];
    $pif = $p['pif'];
    $fcs = $p['fcs_mult'];
    $fcsPercent = null;
            foreach ($fcs_multipliers as $key => $value) {
                if ((string)$value === (string)$fcs) {
                    $fcsPercent = htmlspecialchars($key) . '%';
                    break;
                }
            }
            if (!$fcsPercent) {
                $fcsPercent = $fcsRequiredPct . '%';
            }
    $rateFcs = $p['rate_fcs'];
    $basereward = $p['base_reward'];
    $rate = $p['rate_per_pax'];

    // // Determine FCS percentage label
    // $fcsPercent = isset($fcsLabelsByValue[$fcs]) ? $fcsLabelsByValue[$fcs] . '%' : (($fcs * 100) . '%');

    // GTIB logic
    $selectedBonuses = $_GET['bonus'] ?? [];
    $gtibBonusType = $selectedBonuses[0] ?? null;
    $selectedBonusGTIB = null;
    if (preg_match('/^gtib_(\d+)_bonus$/', $gtibBonusType, $m)) {
        $selectedBonusGTIB = (int)$m[1];
    }

    // Try auto-apply GTIB bonus from slab
    $gtibBonus = 0;
    $autoBonusApplied = false;
    $autoBonusKey = null;
    foreach ($slabs as $slab) {
        if ((int)($slab['conversion'] ?? 0) !== (int)$conversion) continue;
        foreach ($slab as $key => $val) {
            if (preg_match('/^gtib_(\d+)_bonus$/', $key, $m)) {
                $threshold = (int)$m[1];
                if ($minGTIB >= $threshold) {
                    $gtibBonus = (float)$val;
                    $autoBonusApplied = true;
                    $autoBonusKey = $key;
                }
            }
        }
    }

    // If no auto bonus, fallback to selected bonus
    if (!$autoBonusApplied && $gtibBonusType) {
        foreach ($slabs as $slab) {
            if ((int)($slab['conversion'] ?? 0) !== (int)$conversion) continue;
            if (isset($slab[$gtibBonusType])) {
                $gtibBonus = (float)$slab[$gtibBonusType];
                $autoBonusKey = $gtibBonusType;
            }
        }
    }

    // Eligibility flags (instead of skipping)
    $isEligibleGTIB = true;
    $isEligibleDailyPIF = true;
    $gtibBadge = '';
    $dailyPIFBadge = '';

    if ($isManualBonusSelected && $minGTIB === $selectedBonusGTIB) {
        $gtibBadge = "<span class='badge badge-info'>Forced to Threshold</span>";
    }

    if ($dailyPIF > 13) {
        $isEligibleDailyPIF = false;
        $dailyPIFBadge = "<span class='badge badge-warning'>Daily PIF too high</span>";
    }

    $isEligible = $isEligibleGTIB && $isEligibleDailyPIF;

    $gtibBonusFormatted = '₹' . number_format($gtibBonus);
    $gtibBonusTooltip = $autoBonusApplied
        ? "GTIB Bonus '{$autoBonusKey}' auto-applied since min GTIB ($minGTIB) ≥ threshold."
        : "Selected GTIB Bonus: " . ucwords(str_replace('_', ' ', $autoBonusKey ?? 'None'));

    $minPIF = ceil($targetAmount / ($rateFcs > 0 ? $rateFcs : 1));
    $autoAppliedRequiredPIF = $minPIF;
    if ($autoBonusApplied && $rateFcs > 0) {
        $remainingIncentiveAutoApplied = max(0, $targetAmount - $gtibBonus);
        $autoAppliedRequiredPIF = ceil($remainingIncentiveAutoApplied / $rateFcs);
    }

    if (!isset($bestEfficiency) || $p['efficiency'] > $bestEfficiency) {
        $bestEfficiency = $p['efficiency'];
        $bestEfficiencyIndex = $i;
    }
    ?>

    <tr class="<?= $isEligible ? '' : 'text-muted' ?>"
        data-conversion="<?= $conversion ?>"
        data-rate_fcs="<?= $rateFcs ?>"
        data-fcs_mult="<?= $fcsPercent ?>"
        data-fcs_mult_bonus="<?= $fcs ?>"
        data-rate_per_pax="<?= $rate ?>"
        data-base_reward="<?= $basereward ?>"
        data-gtib_bonus="<?= $gtibBonus ?>"
        data-min_gtib="<?= $minGTIB ?>"
        data-min_pif="<?= $p['min_pif'] ?>"
        data-daily_pif="<?= $dailyPIF ?>"
        data-total_estimate="<?= $rateFcs * $autoAppliedRequiredPIF + $gtibBonus ?>"
    >
        <td>
            <?= $conversion ?>%
            <i class="fas fa-info-circle text-primary ml-1" style="cursor:pointer;"
               data-toggle="modal" data-target="#conversionExplainModal<?= $i ?>"
               title="Explain conversion logic"></i>
        </td>
        <td><?= $minGTIB ?> <?= $gtibBadge ?></td>
        <td><?= $autoAppliedRequiredPIF ?></td>
        <td><?= $dailyPIF ?> <?= $dailyPIFBadge ?></td>
        <td><?= '₹' . number_format($rate) ?></td>
        <td><?= '₹' . number_format($rateFcs - $rate) ?></td>
        <td><?= '₹' . number_format($rateFcs) ?></td>
        <td><?= '₹' . number_format($rateFcs * $autoAppliedRequiredPIF) ?></td>
        <td>
            <?= $gtibBonusFormatted ?>
            <i class="fas fa-info-circle text-secondary ml-1" title="<?= $gtibBonusTooltip ?>" style="cursor: help;"></i>
        </td>
        <td>
            <?= '₹' . number_format($rateFcs * $autoAppliedRequiredPIF + $gtibBonus) ?>
            <?php if ($i === $bestEfficiencyIndex): ?>
                🏆
            <?php endif; ?>
        </td>
        <td style="text-align: center;">
            <?php if ($isEligible): ?>
                <button class="btn pick-select-btn selectBtn" type="button">✨ Select</button>
            <?php else: ?>
                <span class="badge badge-secondary">Not Eligible</span>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>




    <!-- MODALS -->
    <?php foreach ($pathways as $i => $p): ?>
      <?php
        $conversion = $p['conversion'];
        $targetAmount = $p['reward'];
        $rateFcs = $p['rate_fcs'];
        $rate = $p['rate_per_pax'];
        $fcs = $p['fcs_mult'];
        $minGTIB = $p['min_gtib'];
        $selectedBonuses = $_GET['bonus'] ?? [];
        $gtibBonusType = $selectedBonuses[0] ?? null;
        $gtibBonus = 0;
        $autoBonusApplied = false;
        $autoBonusKey = null;
    
        // Auto-apply GTIB bonus
        foreach ($slabs as $slab) {
          if ((int)($slab['conversion'] ?? 0) !== (int)$conversion) continue;
          foreach ($slab as $key => $val) {
            if (preg_match('/^gtib_(\d+)_bonus$/', $key, $m)) {
              $threshold = (int)$m[1];
              if ($minGTIB >= $threshold) {
                $gtibBonus = (float)$val;
                $autoBonusApplied = true;
                $autoBonusKey = $key;
              }
            }
          }
        }
    
        // Fallback to selected bonus if needed
        if (!$autoBonusApplied && $gtibBonusType) {
          foreach ($slabs as $slab) {
            if ((int)($slab['conversion'] ?? 0) !== (int)$conversion) continue;
            if (isset($slab[$gtibBonusType])) {
              $gtibBonus = (float)$slab[$gtibBonusType];
              $autoBonusKey = $gtibBonusType;
            }
          }
        }
    
        // FCS Label
        $fcsPercent = null;
        foreach ($fcs_multipliers as $key => $value) {
          if ((string)$value === (string)$fcs) {
            $fcsPercent = htmlspecialchars($key) . '%';
            break;
          }
        }
        if (!$fcsPercent) {
          $fcsPercent = $fcsRequiredPct . '%'; // fallback if not matched
        }

    
        // PIF calculations
        $minPIF = ceil($targetAmount / ($rateFcs > 0 ? $rateFcs : 1));
        if ($autoBonusApplied && $rateFcs > 0) {
          $remainingIncentiveAutoApplied = max(0, $targetAmount - $gtibBonus);
          $autoAppliedRequiredPIF = ceil($remainingIncentiveAutoApplied / $rateFcs);
        } else {
          $remainingIncentiveAutoApplied = null;
          $autoAppliedRequiredPIF = $minPIF;
        }
    
        $rewardFormatted = '₹' . number_format($targetAmount);
        $gtibBonusFormatted = '₹' . number_format($gtibBonus);
        $remainingFormatted = '₹' . number_format($remainingIncentiveAutoApplied ?? 0);
      ?>
        
      <div class="modal fade" id="conversionExplainModal<?= $i ?>" tabindex="-1" role="dialog" aria-labelledby="conversionExplainModalLabel<?= $i ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
          <div class="modal-content shadow">
            <div class="modal-header bg-info text-white">
              <h5 class="modal-title">Conversion Details for <?= $conversion ?>%</h5>
              <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
              <p>
                You aim to handle more than <strong><?= $minGTIB ?></strong> GTIB calls this period, 
                with an FCS of <strong><?= $fcsPercent ?></strong> and a conversion rate of <strong><?= $conversion ?>%</strong>.
              </p>
              <p>
                Therefore, to earn your target incentive of <strong><?= $rewardFormatted ?></strong>, you’ll need to achieve:
              </p>
              <ul>
                <li>GTIBs (calls handled): <strong><?= $minGTIB ?></strong></li>
                <li>PIFs (Passenger): <strong><?= $autoAppliedRequiredPIF ?></strong></li>
                <li>Conv: <strong><?= $conversion .'%' ?></strong></li>
                <li>FCS: <strong><?= $fcsPercent ?></strong></li>
                
              </ul>
              <p class="text-muted">
                These values are dynamically calculated based on your selected reward, FCS, GTIB bonus eligibility, and conversion pathway.
              </p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

<?php else: ?>
  <p>No incentive pathways found for this configuration.</p>
<?php endif; ?>



<!-- ✅ Modal for selecting agent -->
<div id="agentModal" style="display:none; position:fixed; z-index:1000; left:0; top:0; right:0; bottom:0; background:rgba(0,0,0,0.5); justify-content:center; align-items:center;">
  <div id="agentModalContent" style="background:white; padding:20px; border-radius:8px; width:700px; max-width:90%; height:600px;">
    <h3>Select Agent</h3>

    <input type="text" id="agentSearchBox" placeholder="Search agent..." style="width:100%; margin-bottom:10px; padding:5px;" />

    <select id="agentSelect" size="6" style="width:100%; font-size:16px; height:70%;">
      <option value="">-- Select Agent --</option>
      <?php foreach ($agents as $agent): ?>
        <option value="<?= htmlspecialchars($agent['roster_code']) ?>">
          <?= htmlspecialchars($agent['agent_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <br><br>
    <button id="confirmAgent" class="btn" disabled>Confirm</button>
    <button id="cancelAgent" class="btn" style="background:#6c757d; margin-left:10px;">Cancel</button>
  </div>
</div>

<!-- Pathway explaination Modal -->
<div class="modal fade" id="conversionInfoModal" tabindex="-1" role="dialog" aria-labelledby="conversionInfoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content shadow-lg">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="conversionInfoModalLabel">Understanding Conversion</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p><strong>Conversion</strong> in this context refers to the percentage of successful Paid in Full Passenger of your total GTIB calls handled.</p>
        <ul class="mb-2">
          <li>If you achieve <strong>40% conversion</strong> or more, you qualify for higher bonus slabs.</li>
          <li>Based on your <strong>GTIBs</strong> and <strong>FCS</strong> selection, your reward amount will be dynamically calculated.</li>
          <!--<li>Some pathways offer daily bonuses depending on your daily eligibility and targets.</li>-->
        </ul>
        <p>You can experiment by changing target, GTIB bonus, and FCS options to find the most rewarding pathway for the selected period.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Scenario Forecast Modal -->
<div class="modal fade" id="scenarioForecastModal" tabindex="-1" role="dialog" aria-labelledby="forecastModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content shadow">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="forecastModalLabel">📈 What If You Try a Bit More?</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="forecastModalBody">
        <!-- JS will insert forecast table here -->
      </div>
      <div class="modal-footer">
        <button type="button" id="applyOriginalBtn" class="btn btn-primary">Apply</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<?php
// Build conversion to rate mapping for JS
$rateByConversion = [];
foreach ($slabs as $slab) {
    $convKey = (int)($slab['conversion'] ?? 0);
    if ($convKey > 0 && isset($slab['rate_per_pax'])) {
        $rateByConversion[$convKey] = (float)$slab['rate_per_pax'];
    }
}
?>

<script>
  const agentSelect = document.getElementById('agentSelect');
  const confirmBtn = document.getElementById('confirmAgent');
  const cancelBtn = document.getElementById('cancelAgent');
  const modal = document.getElementById('agentModal');
  const rateByConversion = <?= json_encode($rateByConversion) ?>;
  const fcs_multipliers = <?= json_encode($fcs_multipliers) ?>;
  let selectedRow = null;

  document.querySelectorAll('.selectBtn').forEach(btn => {
    btn.addEventListener('click', e => {
      selectedRow = e.target.closest('tr');
        const conv = parseFloat(selectedRow.dataset.conversion);
        const fcs = parseFloat(selectedRow.dataset.fcs_mult);
        const rate = parseFloat(selectedRow.dataset.rate_per_pax);
        const rateFcs = parseFloat(selectedRow.dataset.rate_fcs) || 0;
        const rateFcsbonus = parseFloat(selectedRow.dataset.fcs_mult_bonus) || 0;
        // const rate_per_pax = parseFloat(selectedRow.dataset.rate_per_pax);
        const reward = parseFloat(selectedRow.dataset.total_estimate);
        const gtib = parseInt(selectedRow.dataset.min_gtib);
        const pif = parseInt(selectedRow.dataset.min_pif);
        
        showScenarioForecastModal({ conv, fcs, rate, reward, gtib, pif });


      modal.style.display = 'flex';
      agentSelect.selectedIndex = 0;
      confirmBtn.disabled = true;
      document.getElementById('agentSearchBox').value = '';
    });
  });

  agentSelect.addEventListener('change', () => {
    confirmBtn.disabled = !agentSelect.value;
  });

  cancelBtn.addEventListener('click', () => {
    modal.style.display = 'none';
    selectedRow = null;
  });

  document.getElementById('agentSearchBox').addEventListener('keyup', function () {
    const filter = this.value.toLowerCase();
    const options = agentSelect.getElementsByTagName('option');
    for (let i = 1; i < options.length; i++) {
      const txt = options[i].textContent || options[i].innerText;
      options[i].style.display = txt.toLowerCase().includes(filter) ? '' : 'none';
    }
  });

  confirmBtn.addEventListener('click', () => {
      if (!selectedRow || !agentSelect.value) return;
    
      // Get the values - use modal selection if available, otherwise use base row
      
      const data = {
        action: 'save_pathway',
        roster_code: agentSelect.value,
        period: <?= json_encode($period) ?>,
        target: <?= json_encode($target) ?>,
        conversion: selectedForecastData ? selectedForecastData.conv : null,
        rate: selectedRow.dataset.rate_per_pax,
        fcs_mult: selectedForecastData ? selectedForecastData.fcs : null,
        rate_fcs: selectedForecastData ? selectedForecastData.rate : null,
        gtib_bonus: selectedRow.dataset.gtib_bonus, // Always from original row
        min_gtib: selectedForecastData ? selectedForecastData.gtib : null,
        min_pif: selectedForecastData ? Math.ceil(selectedForecastData.pif) : null,
        daily_pif:  selectedRow.dataset.daily_pif,
        total_estimate: selectedForecastData ? selectedForecastData.reward : null,
      };
    
      fetch(location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams(data)
      })
      .then(res => res.json())
      .then(res => {
        if (res.success) {
          alert('Agent target pathway saved successfully!');
          document.getElementById('downloadTargetPdfBtn').style.display = 'inline-block';
          modal.style.display = 'none';
        } else {
          alert('Save failed: ' + (res.message || 'Unknown error'));
        }
      })
      .catch(() => alert('Network error'));
    });
  

 document.getElementById('downloadTargetPdfBtn').addEventListener('click', () => {
  const element = document.getElementById('pdfContent');

  // ✅ Temporarily show hidden element
  element.style.display = 'block';

  // Inject dynamic values
  function formatPeriod(periodStr) {
  if (!periodStr.includes('_to_')) return periodStr;

  const [start, end] = periodStr.split('_to_');

  const startDate = new Date(start);
  const endDate = new Date(end);

  const startDay = startDate.getDate().toString().padStart(2, '0');
  const endDay = endDate.getDate().toString().padStart(2, '0');

  const month = endDate.toLocaleString('en-US', { month: 'short' }).toUpperCase(); // JUN
  const year = endDate.getFullYear().toString().slice(-2); // 25

  return `${startDay} - ${endDay} ${month} ${year}`;
}

  const agentName = agentSelect.options[agentSelect.selectedIndex].text;
    const period = <?= json_encode($period) ?>;
    
    // Use forecast data if available, otherwise use selectedRow attributes
    const target = selectedForecastData
      ? Math.round(selectedForecastData.reward)
      : Number(selectedRow.getAttribute('data-total_estimate') ?? 0);
    
    const gtib = selectedForecastData
      ? Math.round(selectedForecastData.gtib)
      : selectedRow.getAttribute('data-min_gtib') ?? 'N/A';
    
    const pif = selectedForecastData
      ? Math.round(selectedForecastData.pif)
      : selectedRow.getAttribute('data-min_pif') ?? 'N/A';
    
    const fcs = selectedForecastData
      ? selectedForecastData.fcs
      : selectedRow.getAttribute('data-fcs_mult') ?? 'N/A';
    
    const conv = selectedForecastData
      ? selectedForecastData.conv
      : selectedRow.getAttribute('data-conversion') ?? 'N/A';


  document.getElementById('agentName').textContent = agentName;
  document.getElementById('targetPeriod').textContent = formatPeriod(period);
  document.getElementById('targetAmount').textContent = target.toLocaleString();
  document.getElementById('gtib').textContent = gtib;
  document.getElementById('pif').textContent = pif;
  document.getElementById('fcs').textContent = fcs;
  document.getElementById('conversion').textContent = conv;
  document.getElementById('generatedTime').textContent = new Date().toLocaleString();

  // Generate PDF
  html2pdf()
    .set({
      margin: 10,
      filename: `Target_Commitment_${period}.pdf`,
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2 },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    })
    .from(element)
    .save()
    .then(() => {
      // ✅ Hide again after saving
      element.style.display = 'none';
    });
});



</script>

<script>
let selectedForecastData = null; // 🧠 Stores selected forecast values
let originalForecastData = null; // 🧠 Stores original values (no forecast)

function showScenarioForecastModal({ conv, fcs, rate, reward, gtib, pif,pax }) {
  const scenarios = [];
function getRateForConversion(c) {
    return rateByConversion[c] || rate;
  }  
function getFcsLabel(fcsValue) {
    for (const [percent, multiplier] of Object.entries(fcs_multipliers)) {
      if (String(multiplier) === String(fcsValue)) {
        return percent + '%';
      }
    }
    return (fcsValue * 100).toFixed(0) + '%';
  }
  const fcsLabel = getFcsLabel(fcs);
  const fcsMultiplier = parseFloat(selectedRow.dataset.fcs_mult_bonus) || 1;
  const rateFcs = rate * fcsMultiplier;
  const currentRate = getRateForConversion(conv);
 

  // Base case
  scenarios.push({
    label: 'Current',
    conv,
    gtib,
    pif,
    rate: rateFcs,
    fcs,
    reward
  });

  // +5% Conversion
  const gtib5 = gtib;
  const conv5 = conv + 5;
  const pif5 = gtib5*(conv5/100);
  const rate5 = (rateByConversion[conv5] ?? 1000)*fcsMultiplier;
  const rewardConv5 = pif5 * rate5;  
  scenarios.push({
    label: '+5% Conversion',
    conv: conv5,
    gtib: gtib5,
    pif: pif5,
    rate: rate5,    
    fcs,
    reward: rewardConv5
  });

  // +10% conversion
  const gtib10 = gtib;
  const conv10 = conv + 10;
  const pif10 = gtib10*(conv10/100);
  const rate10 = (rateByConversion[conv10] ?? 1000)*fcsMultiplier;
  const rewardConv10 = pif10 * rate10;
  scenarios.push({
    label: '+10% Conversion',
    conv: conv10,
    fcs,
    rate: rate10,
    pif: pif10,
    gtib: gtib10,
    reward: rewardConv10
  });
  
  // Find most efficient
  let bestEff = 0;
  let bestIndex = 0;
  scenarios.forEach((s, i) => {
    const eff = s.reward / (s.gtib + s.pif);
    if (eff > bestEff) {
      bestEff = eff;
      bestIndex = i;
    }
  });
  
  

  // Store original
  originalForecastData = scenarios[0];
  selectedForecastData = scenarios[0];
  
  

  let html = `
    <table class="table table-bordered table-sm mb-0">
      <thead class="thead-light">
        <tr>
          <th>Select</th>
          <th>Scenario</th>
          <th>Conversion</th>
          <th>GTIB</th>
          <th>PIF</th>
          <th>Rs/Pax</th>
          <th>FCS</th>
          <th>Total Incentive</th>
          <th>Note</th>
        </tr>
      </thead>
      <tbody>
  `;

  scenarios.forEach((s, i) => {
    const checked = i === 0 ? 'checked' : '';
    const isBest = i === bestIndex;
    
    const basePif = scenarios[0].pif;
    const diffPif = Math.max(0, s.pif - basePif);
    const diffAmount = Math.round(s.reward - reward);
    const diffPct = ((diffAmount / reward) * 100).toFixed(0);
    
    const remark = (i === 0)
      ? 'Base scenario'
      : `Convert ${s.pif.toFixed(0)} (${diffPif.toFixed(0)} more) PIF to achieve ₹${Math.round(s.reward).toLocaleString()} – that’s ${diffPct}% more`;

    html += `
       <tr>
        <td>
          <input type="radio" name="forecastOption" value="${i}" ${checked}>
        </td>
        <td>${s.label}</td>
        <td>${s.conv}%</td>
        <td>${s.gtib.toFixed(0)}</td>
        <td>${s.pif.toFixed(0)}</td>
        <td>₹${s.rate}</td>
        <td>${s.fcs}%</td>
        <td>
          ₹${Math.round(s.reward).toLocaleString()}
          
        </td>
        <td>${remark}</td>
      </tr>
    `;
  });

  html += '</tbody></table>';
  document.getElementById('forecastModalBody').innerHTML = html;

  // Listen to radio changes
  document.querySelectorAll('input[name="forecastOption"]').forEach((input) => {
    input.addEventListener('change', (e) => {
      selectedForecastData = scenarios[parseInt(e.target.value)];
    });
  });

  $('#scenarioForecastModal').modal('show');
}
</script>
<script>
document.getElementById('applyOriginalBtn').addEventListener('click', () => {
  $('#scenarioForecastModal').modal('hide');
  openAgentModal();
});

// document.getElementById('applyForecastBtn').addEventListener('click', () => {
//   $('#scenarioForecastModal').modal('hide');
//   openAgentModal();
// });

function openAgentModal() {
  // Set selectedRow manually based on selectedForecastData
  const row = document.createElement('tr');
  row.setAttribute('data-conversion', selectedForecastData.conv);
  row.setAttribute('data-rate', selectedForecastData.rate.toFixed(2));
  row.setAttribute('data-fcs_mult', selectedForecastData.fcs.toFixed(2));
  row.setAttribute('data-rate_fcs', selectedForecastData.rate_fcs.toFixed(2));
  row.setAttribute('data-rate_per_pax', selectedForecastData.rate_per_pax.toFixed(2));
  row.setAttribute('data-gtib_bonus', '0'); // Adjust if dynamic bonus is needed
  row.setAttribute('data-min_gtib', selectedForecastData.gtib);
  row.setAttribute('data-min_pif', selectedForecastData.pif);
  row.setAttribute('data-daily_pif', Math.ceil(selectedForecastData.pif / 10));
  row.setAttribute('data-total_estimate', selectedForecastData.reward);

  selectedRow = row;
  modal.style.display = 'flex';
}
</script>



<!--Allowed Combinations-->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function updateCheckboxStates(groupName) {
  const boxes = document.querySelectorAll(`input[name="${groupName}[]"]`);
  const checkedBoxes = Array.from(boxes).filter(cb => cb.checked);

  boxes.forEach(cb => {
    const label = cb.closest('label');
    const shouldDisable = checkedBoxes.length > 0 && !cb.checked;

    cb.dataset.disabled = shouldDisable ? 'true' : 'false';
    label.classList.toggle('greyed-out', shouldDisable);
  });
}

// Show clear message if user tries to select another option
function interceptDisabledClicks() {
  document.querySelectorAll('input[name="fcs[]"], input[name="bonus[]"]').forEach(cb => {
    cb.addEventListener('click', (e) => {
      if (cb.dataset.disabled === 'true') {
        e.preventDefault();
        const group = cb.name.includes('fcs') ? 'FCS Multiplier' : 'GTIB Bonus';
        Swal.fire({
          icon: 'warning',
          title: `${group} Already Selected`,
          text: `Please uncheck the current ${group.toLowerCase()} option before selecting a new one.`,
          confirmButtonText: 'OK'
        });
      }
    });
  });
}

function attachAutoSubmit() {
  document.querySelectorAll('input[name="fcs[]"], input[name="bonus[]"]').forEach(cb => {
    cb.addEventListener('change', () => {
      updateCheckboxStates('fcs');
      updateCheckboxStates('bonus');
      document.querySelector('form').submit();
    });
  });
}

document.addEventListener('DOMContentLoaded', function () {
  updateCheckboxStates('fcs');
  updateCheckboxStates('bonus');
  interceptDisabledClicks();
  attachAutoSubmit();
});
</script>

<!--min gtib tooltip-->
<script>
  (function () {
    ('[data-toggle="tooltip"]').tooltip();
  });
</script>

<!-- Bootstrap 4 JS dependencies -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

</body>
</html>
