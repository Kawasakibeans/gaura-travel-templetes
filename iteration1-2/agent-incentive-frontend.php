<?php
/**
 * Template Name: My Incentive
 * Template Post Type: post, page
 */
$host = 'localhost';
$db = 'gaurat_gauratravel';
$user = 'gaurat_sriharan';
$pass = 'r)?2lc^Q0cAE';

$apiUrl = 'https://gauratravel.com.au/wp-content/themes/twentytwenty/templates/tpl_admin_backend_for_credential_pass_main.php';
$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_USERAGENT      => 'GTX-SettingsFetcher/1.0',
    CURLOPT_SSL_VERIFYPEER => true,   // keep true in prod
    CURLOPT_SSL_VERIFYHOST => 2,      // keep strict
]);
$body = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($body === false) {
    die("Failed to load settings: $err");
}
if ($http !== 200) {
    // Show a snippet of body for debugging
    die("Settings endpoint HTTP $http.\n".substr($body, 0, 500));
}

$resp = json_decode($body, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Invalid JSON: ".json_last_error_msg()."\n".substr($body, 0, 500));
}
if (!is_array($resp) || empty($resp['success'])) {
    die("Invalid settings response shape.\n".substr($body, 0, 500));
}

$settings = $resp['data'] ?? [];
foreach ($settings as $k => $v) {
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $k)) {
        $GLOBALS[$k] = $v;
    }
}

$pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get current logged-in user
wp_get_current_user();
global $current_user;
$current_userlogin = $current_user->user_login;  // WordPress username

// Fetch the logged-in user's agent name from wpk4_backend_agent_codes table
$stmt = $pdo->prepare("SELECT agent_name FROM wpk4_backend_agent_codes WHERE wordpress_user_name = ? AND status = 'active' LIMIT 1");
$stmt->execute([$current_userlogin]);
$selected_agent = $stmt->fetch();

if (!$selected_agent) {
    die("No matching agent record found for user: " . htmlspecialchars($current_userlogin));
}


// Get the agent name of the logged-in user
$selected_agent_name = $selected_agent['agent_name'];

$allPeriods = $pdo->query("SELECT DISTINCT period FROM wpk4_backend_incentive_criteria ORDER BY period DESC")->fetchAll(PDO::FETCH_COLUMN);
$latestPeriod = $allPeriods[0] ?? '';

$period = $_GET['period'] ?? $latestPeriod;
$agentName = $selected_agent_name;
$target = isset($_GET['target']) ? (int)$_GET['target'] : 10000;



// Show current + 3 most recent
$periodIndex = array_search($period, $allPeriods);
$periodIndex = $periodIndex !== false ? $periodIndex : 0;

$periods = array_slice($allPeriods, $periodIndex, 10);


$_GET['period'] = $period;
$_GET['agent'] = $agentName;

$data = include 'G360_Dashboard/agent-incentive/get-frontend-incentive-data.php';

include 'G360_Dashboard/agent-incentive/incentive_criteria.php';

$agentList = $data['agents'];
$agentData = null;
foreach ($data['performance'] as $row) {
  if ($row['agent_name'] === $agentName) {
    $agentData = $row;
    break;
  }
}

$summary = [
  'pif' => $agentData['pif'] ?? 0,
  'gtib' => $agentData['gtib'] ?? 0,
  'conversion' => $agentData['conversion'] ?? 0,
  'fcs' => $agentData['fcs'] ?? 0,
  'aht' => $agentData['aht'] ?? 0,
  'qa_compliance' => $agentData['qa_compliance'] ?? 0,
  'noble_login_time' => $agentData['noble_login_time'] ?? 0,
  'gtbk' => $agentData['gtbk'] ?? 0,
  'earned' => 0
];

$loginRequiredHrs = isset($eligibility['noble_login_min_hrs']) ? (float)$eligibility['noble_login_min_hrs'] : 3;
$gtbkLimitHrs     = isset($eligibility['gtbk_max_hrs']) ? (float)$eligibility['gtbk_max_hrs'] : 2;
$gtibRequired     = isset($eligibility['gtib_min_calls']) ? (int)$eligibility['gtib_min_calls'] : 50;
$ahtLimit         = isset($eligibility['aht_max_minutes']) ? (int)$eligibility['aht_max_minutes'] : 25;
$fcsRequiredPct   = isset($eligibility['fcs_min_percent']) ? (float)$eligibility['fcs_min_percent'] : 30;
$requiredConversion   = isset($eligibility['conversion_min_percent']) ? (float)$eligibility['conversion_min_percent'] : 40;
$requiredQA   = isset($eligibility['garland_min_percent']) ? (float)$eligibility['garland_min_percent'] : 80;

$nobleHrs = round($summary['noble_login_time'] / 3600, 1);
$gtbkHrs = round($summary['gtbk'] / 3600, 1);
$QA =  $summary['qa_compliance'] ?? 0;

$conversion = $summary['conversion'] ?? 0;
$fcs = $summary['fcs'];
$aht = $summary['aht'];
$gtib = $summary['gtib'] ?? 0;
$pif = $summary['pif'];

$isLoginEligible = $summary['noble_login_time'] >= ($loginRequiredHrs * 3600);
$isGTBKEligible = $summary['gtbk'] <= ($gtbkLimitHrs * 3600);
$isGTIBEligible = $gtib >= $gtibRequired;
$isAhtEligible = $aht <= ($ahtLimit * 60);
$isFcsEligible = round(($fcs * 100),2) >= $fcsRequiredPct;
$isConvEligible = round(($conversion * 100),2) >= $requiredConversion;
$isQAEligible = $QA >= $requiredQA;

$eligibilityMessages = [];
// if (!$isLoginEligible) $eligibilityMessages[] = '🕒 <b>Noble Login</b> < ' . $loginRequiredHrs . ' hrs';
// if (!$isGTBKEligible) $eligibilityMessages[] = '⏸️ <b>GTBK</b> > ' . $gtbkLimitHrs . ' hrs';
if (!$isGTIBEligible) $eligibilityMessages[] = '📞 <b>GTIB</b> < ' . $gtibRequired;
if (!$isAhtEligible) $eligibilityMessages[] = '⏱️ <b>AHT</b> > ' . $ahtLimit . ' mins';
if (!$isFcsEligible) $eligibilityMessages[] = '🎯 <b>FCS</b> < ' . $fcsRequiredPct . '%'; 
if (!$isConvEligible) $eligibilityMessages[] = '📘 <b>Conversion(PIF)</b> < ' . $requiredConversion . '%';
if (!$isQAEligible) $eligibilityMessages[] = '📘 <b>GARLAND</b> < ' . $requiredQA . '%';

$isAllEligible = $isGTIBEligible && $isAhtEligible && $isFcsEligible && $isConvEligible && $isQAEligible;

$convNeeded = $gtib > 0 && !$isConvEligible ? $requiredConversion - $conversion*100 : 0;
$fcsNeeded = $gtib > 0 && !$isFcsEligible ? ceil(($fcsRequiredPct / 100 * $gtib) - ($fcs * $gtib)) : 0;
$ahtReduceSecs = $aht > ($ahtLimit * 60) ? round($aht - ($ahtLimit * 60)) : 0;

$ahtM = floor($aht / 60);
$ahtS = str_pad($aht % 60, 2, '0', STR_PAD_LEFT);

$summary['kpi_component'] = 0; // Default to 0 if not eligible
$appliedFcsBonus = 1;
$fcsMultiplier = 1;

if ($isAllEligible) {
    $rate = 0;
    $bonus = 0;
    $currentSlab = null;

    foreach ($slabs as $slab) {
        if ($conversion >= ($slab['conversion'] / 100)) {
            $currentSlab = $slab;
        }
    }

    if ($currentSlab) {
        $rate = $currentSlab['rate_per_pax'] ?? 0;
        if ($gtib >= 150) {
            $bonus = $currentSlab['gtib_150_bonus'] ?? 0;
        } elseif ($gtib >= 100) {
            $bonus = $currentSlab['gtib_100_bonus'] ?? 0;
        }
    }

    $earned = max(0, ($pif * $rate));

    $fcsMultiplier = 1;
    krsort($fcs_multipliers);
    foreach ($fcs_multipliers as $thresh => $mult) {
        if (($fcs * 100) >= $thresh) {
            $fcsMultiplier = $mult;
            break;
        }
    }

    $appliedFcsBonus = $fcsMultiplier;
    $summary['kpi_component'] = round($earned * $fcsMultiplier+ $bonus);
}

$summary['daily_component'] = $dailyEarned ?? 0;
$summary['projected'] = $summary['kpi_component'] + $summary['daily_component'];
$summary['earned'] = $isAllEligible ? $summary['projected'] : 0;

$today = new DateTime();
list($startStr, $endStr) = explode('_to_', $period);
$endDate = new DateTime($endStr);

if ($today > $endDate) {
  $summary['earned'] = $isAllEligible ? $summary['projected'] : 0;
} else {
  $summary['earned'] = $summary['projected'];
}

$dailyRaw = $data['daily'] ?? [];
$dailyEarned = 0;
$dailyData = [];
$dateMap = [];

foreach ($dailyRaw as $row) {
    $date = $row['call_date'];
    $dateMap[$date] = $row;
}

$dailyStart = new DateTime(explode('_to_', $period)[0]);
$dailyEnd   = new DateTime(explode('_to_', $period)[1]);

while ($dailyStart <= $dailyEnd) {
    $key = $dailyStart->format('Y-m-d');
    $label = $dailyStart->format('M j');
    $newSales = $dateMap[$key]['new_sale_made_count'] ?? 0;
    $qa = $dateMap[$key]['qa'] ?? 0;

    $pax = $dateMap[$key]['pif'] ?? 0;
    $gtib = $dateMap[$key]['gtib'] ?? 0;
    $conversion = $gtib > 0 ? round(($pax / $gtib) * 100, 2) : 0;
    $dailyfcs = $gtib > 0 ? round(($newSales / $gtib) * 100, 2) : 0;
    
    // ⬇️ pull the day’s on-time flag (expects 'On Time' | 'Late'/'No')
    $isOnTime = isset($dateMap[$key]['on_time']) && $dateMap[$key]['on_time'] === 'On Time';
    
    // (optional) if you also want to enforce daily QA/FCS thresholds for eligibility:
    $meetsDailyFcs = !isset($fcsRequiredPct) || $dailyfcs >= (float)$fcsRequiredPct;
    $meetsDailyQA  = !isset($requiredQA)    || $qa   >= (float)$requiredQA;
    
    $reward = 0;
    $baseReward = 0;
    $dailyFcsMultiplier = 1;
    
    foreach ($daily_incentives as $block) {
        if ($gtib >= $block['min_gtib']) {
            foreach ($block['criteria'] as $crit) {
                if ($conversion >= ($crit['conversion']) && $pax >= $crit['pax']) {
                    $baseReward = (int)$crit['reward'];
    
                    // multiplier by daily FCS
                    $dailyFcsMultiplier = 1;
                    if (!empty($daily_bonus)) {
                        $fcsPercent = (int)round($dailyfcs);
                        foreach ($daily_bonus as $minFcs => $multiplier) {
                            if ($fcsPercent >= (int)$minFcs) $dailyFcsMultiplier = $multiplier;
                        }
                    }
    
                    $calculatedReward = (int)round($baseReward * $dailyFcsMultiplier);
                    $reward = max($reward, $calculatedReward);
                }
            }
        }
    }
    
    // ⬇️ final eligibility gate: must be On Time (and meet optional QA/FCS if desired)
    $eligibleToday = $isOnTime && $meetsDailyFcs && $meetsDailyQA;
    if (!$eligibleToday) {
        // show base/bonus breakdown but pay 0
        $reward = 0;
    }




    $dailyData[] = [
        'date' => $label,
        'pax' => $pax,
        'gtib' => $gtib,
        'conversion' => $conversion,
        'fcs' => $dailyfcs,
        'baseReward' => $baseReward,
        'qa_compliance'     => $qa, 
        'reward' => $reward,
        'multiplier' => $dailyFcsMultiplier,
        'eligible'    => $eligibleToday,      // ⬅️ use the gated eligibility
        'on_time'     => $isOnTime            // ⬅️ optional: for debugging/UI
    ];

    $dailyEarned += $reward;
    $dailyStart->modify('+1 day');
}

$totalEarned = $summary['earned'] + $dailyEarned;
$gap = max(0, $target - $totalEarned);

$summary['projected_kpi'] = $summary['projected'];
$summary['projected'] += $dailyEarned;

$pathways = [];
$bestEffortScore = PHP_INT_MAX;
$bestPathwayIndex = -1;

if ($summary['conversion'] >= 0.50 && $summary['fcs'] >= 0.25 && $summary['conversion'] >= 0.40) {
    $dailyEarn = $currentSlab['rate_per_pax'] * 10;
    $dailyTotal = $dailyEarn * $fcsMultiplier;
    $daysNeeded = ceil($gap / max(1, $dailyTotal));
    $score = $daysNeeded;

    $pathways[] = [
        'title' => 'Daily Bonus Strategy',
        'summary' => 'Maintain 10+ pax/day with 50%+ conversion and 25%+ FCS.',
        'requirements' => ['Daily Pax' => '10+', 'Conversion' => '≥ 50%', 'FCS' => '≥ 25%'],
        'why' => "Earn ₹" . number_format($dailyTotal) . "/day. Just $daysNeeded day(s) needed.",
        'score' => $score
    ];
    $bestEffortScore = $score;
    $bestPathwayIndex = count($pathways) - 1;
}

if ($summary['gtib'] < 100 && $summary['fcs'] < 0.30 && $summary['conversion'] >= 0.40) {
    $gtibLeft = 100 - $summary['gtib'];
    $fcsTargetWins = ceil((0.30 * 100) - ($summary['fcs'] * $summary['gtib']));
    $score = $gtibLeft + $fcsTargetWins;

    $pathways[] = [
        'title' => 'GTIB Volume Strategy',
        'summary' => 'Reach GTIB ≥ 100 and FCS ≥ 30% to unlock full bonus.',
        'requirements' => ['GTIB Needed' => $gtibLeft, 'FCS Wins Needed' => $fcsTargetWins],
        'why' => "Double bonus unlocked. Needs $gtibLeft more calls and $fcsTargetWins better outcomes.",
        'score' => $score
    ];
    if ($score < $bestEffortScore) {
        $bestEffortScore = $score;
        $bestPathwayIndex = count($pathways) - 1;
    }
}

if ($summary['conversion'] >= 0.40) {
    $score = 3 * 20;
    $pathways[] = [
        'title' => 'KPI Sprint Days',
        'summary' => '3 high-output days (20+ pax @ 50% conversion).',
        'requirements' => ['3 Days of Pax' => '≥ 20 pax', 'Conversion' => '≥ 50%', 'QA' => '≥ 85%'],
        'why' => 'A short burst of peak performance closes the gap fast.',
        'score' => $score
    ];
    if ($score < $bestEffortScore) {
        $bestEffortScore = $score;
        $bestPathwayIndex = count($pathways) - 1;
    }
}

// First, let's get the roster_code for the selected agent
$rosterCode = '';
if ($agentName) {
    $stmt = $pdo->prepare("SELECT roster_code FROM wpk4_backend_agent_codes 
                          WHERE agent_name = :agent_name AND status = 'active' LIMIT 1");
    $stmt->execute([':agent_name' => $agentName]);
    $agentCode = $stmt->fetch(PDO::FETCH_ASSOC);
    $rosterCode = $agentCode['roster_code'] ?? '';
}

// Debug output
echo "<!-- Debug: agent_name = '$agentName', roster_code = '$rosterCode', period = '$period' -->";

// Get the agent's target pathway data
$targetPathway = [];
$isFallbackTarget = false;

if ($rosterCode) {
    try {
        // Try exact match for period
        $stmt = $pdo->prepare("SELECT * FROM wpk4_backend_agent_target_pathway 
                              WHERE roster_code = :roster_code AND period = :period
                              LIMIT 1");
        $stmt->execute([':roster_code' => $rosterCode, ':period' => $period]);
        $targetPathway = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$targetPathway) {
            $isFallbackTarget = true;
            echo "<!-- Debug: No exact match found, showing most recent target for roster code -->";

            // Fallback: get latest entry for roster code
            $stmt = $pdo->prepare("SELECT * FROM wpk4_backend_agent_target_pathway 
                                  WHERE roster_code = :roster_code
                                  ORDER BY created_at DESC
                                  LIMIT 1");
            $stmt->execute([':roster_code' => $rosterCode]);
            $targetPathway = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        echo "<!-- Database error: " . htmlspecialchars($e->getMessage()) . " -->";
    }
}


// Debug output of what we found
echo "<!-- Debug: targetPathway = " . print_r($targetPathway, true) . " -->";
// Check if $targetPathway is an array before accessing its key
$targetPathway = is_array($targetPathway) ? $targetPathway : [];
$targetPathway['total_estimate'] = $targetPathway['total_estimate'] ?? 0; // Default to 0 if no target found


function statusIcon($condition) {
    if ($condition === null) return '-';
    return $condition ? '<span class="text-success">✅</span>' : '<span class="text-danger">❌</span>';
}

// Helper function for progress bar html
function progressBar($current, $target) {
    if ($target == 0 || $target === null) return '';
    $percent = min(100, max(0, ($current / $target) * 100));
    $barClass = $percent >= 100 ? 'bg-success' : 'bg-warning';
    return '
    <div class="progress" style="height: 10px; margin-top:5px;">
      <div class="progress-bar '.$barClass.'" role="progressbar" style="width: '.$percent.'%;" aria-valuenow="'.$percent.'" aria-valuemin="0" aria-valuemax="100"></div>
    </div>';
}

$pathway_plan = [];
$gap = max(0, $target - $summary['projected']);

if ($gap <= 0) {
    $pathway_plan[] = "🎉 You've hit your ₹" . number_format($target) . " goal!";
} elseif ($summary['kpi_component'] >= ($target * 0.7)) {
    $pathway_plan[] = "You've secured ₹" . number_format($summary['kpi_component']) . " from KPI. Earn the rest ₹" . number_format($gap) . " from daily wins.";
} elseif ($dailyEarned >= ($target * 0.3)) {
    $pathway_plan[] = "You've earned ₹" . number_format($dailyEarned) . " from daily bonuses. Push the rest ₹" . number_format($gap) . " via your 10-day slab.";
} else {
    $remainingFromKPI = max(0, $target * 0.7 - $summary['kpi_component']);
    $remainingFromDaily = max(0, $target * 0.3 - $dailyEarned);

    $totalGap = $remainingFromKPI + $remainingFromDaily;
    $kpiShare = round($gap * ($remainingFromKPI / $totalGap));
    $dailyShare = $gap - $kpiShare;

    $pathway_plan[] = "You still need ₹" . number_format($gap) . ". Close it with: ₹" . number_format($kpiShare) . " from your KPI slab, and ₹" . number_format($dailyShare) . " from daily wins.";
}

function ist_raw_to_aus_time(int $rawHmsInt, string $dateYmd): string {
    // Parse raw INT as HHMMSS
    $h = intdiv($rawHmsInt, 10000);
    $m = intdiv($rawHmsInt % 10000, 100);
    $s = $rawHmsInt % 100;

    try {
        // Source in IST
        $srcTz = new DateTimeZone('Asia/Kolkata');     // IST (no DST)
        $dstTz = new DateTimeZone('Australia/Sydney'); // AEST/AEDT (DST-aware)

        // Build IST datetime for the given call date
        $dt = new DateTime($dateYmd, $srcTz);
        $dt->setTime($h, $m, $s);

        // Convert to Australia (auto-picks AEST in Sept = +4:30, AEDT in DST = +5:30)
        $dt->setTimezone($dstTz);
        return $dt->format('H:i:s');
    } catch (Throwable $e) {
        // Fallback to raw HH:MM:SS if anything goes wrong
        return sprintf('%02d:%02d:%02d', max(0,min(23,$h)), max(0,min(59,$m)), max(0,min(59,$s)));
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Incentive</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
  <style>
    body { font-family: 'Segoe UI', sans-serif; background: #f4f7fa; padding: 40px; }
    .section {
      background: #fff; border-radius: 10px; padding: 20px;
      margin: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .section h2 {
      border-left: 5px solid #1a73e8; padding-left: 10px; margin-bottom: 10px;
    }
    .highlight { color: #d93025; font-weight: bold; }
    @media (max-width: 576px) {
      .alert-warning span.mx-2 {
        display: none;
      }
      .alert-warning {
        font-size: 0.9rem;
        flex-direction: column;
        align-items: flex-start;
      }
    }
    .card {
      border-radius: 10px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
      transition: transform 0.2s;
      height: 100%;
      display: flex;
      flex-direction: column;
    }
    .card:hover {
      transform: translateY(-2px);
    }
    .card-body {
      flex: 1;
    }
    .card-title {
      font-size: 1rem;
      color: #666;
    }
    .display-4 {
      font-size: 1.8rem;
      font-weight: 600;
    }
    .border-left-primary { border-left: 4px solid #4e73df !important; }
    .border-left-success { border-left: 4px solid #1cc88a !important; }
    .border-left-info { border-left: 4px solid #36b9cc !important; }
    .border-left-warning { border-left: 4px solid #f6c23e !important; }
    .border-left-secondary { border-left: 4px solid #858796 !important; }
    .container-fluid {
      
      margin: 0 auto;
    }
    @media (max-width: 992px) {
      .col-lg-6 {
        flex: 0 0 100%;
        max-width: 100%;
      }
    }
    .slab-box {
    background-color: #f0f8ff;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
    cursor: pointer;
    height: 180px;
  }

  .slab-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
  }

  .slab-content h4 {
    font-weight: bold;
    color: #333;
  }

  .slab-content p {
    font-size: 1.2rem;
    color: #333;
  }

  /* Tooltip styles */
  .slab-box:hover .slab-content {
    display: none;
  }

  .slab-box:hover::after {
    content: attr(data-toggle);
    display: block;
    background-color: rgba(0, 0, 0, 0.8);
    color: #fff;
    padding: 10px;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    border-radius: 5px;
  }
  
  </style>
</head>
<body>

<div class="container-fluid">
  <form class="mb-4" method="GET">
    <div class="form-row">
      <div class="col-md-4 mx-auto">
        <div class="text-center my-3">
          <label class="font-weight-bold mr-2">Period:</label>
          <select name="period" class="form-control d-inline-block w-auto" onchange="this.form.submit()">
          <?php foreach ($periods as $p): ?>
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
        </div>
      </div>
      <!--<div class="col-md-4">-->
      <!--  <label>Agent:</label>-->
      <!--  <select id="agentSelect" name="agent" class="form-control select2" style="width: 100%, height: 37px">-->
      <!--    <option value="">-- Select Agent --</option>-->
      <!--    ?php foreach ($agentList as $a): ?>-->
      <!--      <option value="?= $a ?>" ?= $a === $agentName ? 'selected' : '' ?>>?= $a ?></option>-->
      <!--    ?php endforeach; ?>-->
      <!--  </select>-->
      <!--</div>-->
      <!--<div class="col-md-4">-->
        <!--<label>Target Incentive ₹:</label>-->
        <!--<div class="input-group">-->
        <!--  <input type="number" class="form-control" name="target" value="<?= $target ?>" min="1000" step="500">-->
          <!--<div class="input-group-append">-->
          <!--  <button class="btn btn-primary" type="submit">Update</button>-->
          <!--</div>-->
        </div>
      </div>
    </div>
  </form>
  <?php
    if (strpos($period, '_to_') !== false) {
      list($start, $end) = explode('_to_', $period);
      $startFormatted = strtoupper(date('j', strtotime($start))); // 21
      $endFormatted   = strtoupper(date('j M', strtotime($end)));   // 10JUN25
      $periodText = "$startFormatted - $endFormatted Incentive";
    } else {
      $periodText = htmlspecialchars($period); // fallback
    }
    ?>
  <h1 class="mb-4 text-primary text-center">
  🎯 <?= $periodText ?> – <?= htmlspecialchars($agentRow['agent_name'] ?? 'Agent') ?>
</h1>
  <!--<div class="alert alert-warning d-flex flex-wrap justify-content-center text-center font-weight-bold py-2 mb-4" style="background-color: #fff3cd; border-left: 5px solid #ffc107;">-->
  <!--    🕒 Noble Login: ≥ <?= $loginRequiredHrs ?> hrs -->
  <!--    <span class="mx-2">|</span> -->
  <!--    ⏸️ GTBK Pause: ≤ <?= $gtbkLimitHrs ?> hrs -->
  <!--    <span class="mx-2">|</span> -->
  <!--    📞 GTIB Calls: ≥ <?= $gtibRequired ?>-->
  <!--    <span class="mx-2">|</span> -->
  <!--    ⏱️ AHT: ≤ <?= $ahtLimit ?> mins -->
  <!--    <span class="mx-2">|</span> -->
  <!--    🎯 FCS: ≥ <?= $fcsRequiredPct ?>%-->
  <!--    <span class="mx-2">|</span> -->
  <!--    📘 Conversion: ≥ <?= $requiredConversion ?>%-->
  <!--    <span class="mx-2">|</span>-->
  <!--    📘 GARLAND: ≥ <?= $requiredQA ?>%-->
  <!--    <span class="mx-2">|</span>-->
  <!--  </div>-->

  <!--  <?php if (empty($eligibilityMessages)): ?>-->
  <!--    <div class="alert alert-success text-center font-weight-bold mb-4" style="border-left: 5px solid #28a745;">-->
  <!--      ✅ You're eligible for bonuses! All conditions met.-->
  <!--    </div>-->
  <!--  <?php else: ?>-->
  <!--    <div class="alert alert-danger text-center font-weight-bold mb-4" style="border-left: 5px solid #dc3545;">-->
  <!--      ❌ Not eligible yet – Please improve:-->
  <!--      <br>-->
  <!--      <?= implode(" <span class='mx-2'>|</span> ", $eligibilityMessages) ?>-->
  <!--    </div>-->
  <!--  <?php endif; ?>-->
  <?php if ($agentName): ?>
    <!-- 4 Scorecards Row -->
    <div class="row row-cols-1 row-cols-md-5 g-3">
      <!-- KPI Incentive -->
      <div class="col mb-3">
        <div class="card h-100 border-left-success">
          <div class="card-body">
            <h5 style="font-size: 2 rem; font-weight: bold;" >KPI INCENTIVE</h5>
            <p class="card-text display-4 text-success">
              ₹<?= number_format($summary['kpi_component'] ?? 0) ?>
            </p>
            <small style="font-size: 1 rem; font-weight: bold;">
              <?= $isAllEligible
                ? '✅ Qualified for <b>' . htmlspecialchars($currentSlab['title'] ?? 'Incentive Slab') . '</b>'
                : '🔒 Requirements pending' ?>
            </small>
          </div>
        </div>
      </div>
    
      <!-- Daily Incentive -->
      <div class="col mb-3">
        <div class="card h-100 border-left-info">
          <div class="card-body">
            <h5 style="font-size: 2 rem; font-weight: bold;">DAILY INCENTIVE</h5>
            <p class="card-text display-4 text-info">
              ₹<?= number_format($dailyEarned) ?>
            </p>
            <small style="font-size: 1 rem; font-weight: bold;">
              <?= count(array_filter($dailyData, fn($d) => $d['eligible'])) ?> days qualified
            </small>
          </div>
        </div>
      </div>
    
      <!-- Deduction -->
        <div class="col mb-3">
          <div class="card h-100 border-left-danger">
            <div class="card-body">
              <h5 style="font-size: 2 rem; font-weight: bold;">DEDUCTION</h5>
              <p class="card-text display-4 text-danger">
                <?php 
                  $deductionAmount = ($agentRow['deduction_amount'] ?? 0) + ($agentRow['noble_login_deduction'] * ($summary['earned'] ?? 0)) + ($agentRow['gtbk_deduction'] * ($summary['earned'] ?? 0)); 
                  echo '₹' . number_format($deductionAmount);
                ?>
              </p>
              <?php if ($deductionAmount > 0): ?>
                <small style="font-size: 1.2rem; font-weight: bold;">Zero Pax Days: <?= $agentRow['zero_pax_day'] ?? 0 ?></small><br>
                <small style="font-size: 1.2rem; font-weight: bold;">Noble Login: <?= $nobleHrs ?> hrs</small><br>
                <small style="font-size: 1.2rem; font-weight: bold;">GTBK: <?= $gtbkHrs ?> hrs</small>
              <?php endif; ?>
            </div>
          </div>
        </div>

    
      <!-- Total Incentive -->
        <div class="col mb-3">
          <div class="card h-100 border-left-primary">
            <div class="card-body">
              <h5 style="font-size: 2 rem; font-weight: bold;">INCENTIVE CALCULATION</h5>
        
              <?php
                $baseEarned = $summary['earned'] ?? 0;
                $nobleDeduction = $agentRow['noble_login_deduction'] ?? 0;
                $gtbkdeduction = $agentRow['gtbk_deduction'] ?? 0;
                $manualDeduction = $agentRow['deduction_amount'] ?? 0;
        
                $deductionTotal = $nobleDeduction * $baseEarned;
                $gtbkdeductionTotal = $gtbkdeduction * $baseEarned;
                $totalIncentive = $baseEarned + ($dailyEarned - $deductionTotal - $manualDeduction);
              ?>
        
              <!--<p class="card-text display-4 text-primary">₹?= number_format($totalIncentive) ?></p>-->
              <p style="font-size: 1 rem; font-weight: bold;">
                  Incentive Breakdown: <br class="d-md-none">
                  ₹<?= number_format($baseEarned) ?> (KPI) + ₹<?= number_format($dailyEarned) ?>(Daily)
                </p>
                <p style="font-size: 1 rem; font-weight: bold;">
                  Deductions: <br class="d-md-none">
                  ₹<?= number_format($deductionTotal)?> (Login hrs) + ₹<?= number_format($gtbkdeductionTotal)?> (GTBK) + ₹<?= number_format($manualDeduction) ?>(0 pax day) 
                </p>
        
              <!--<small class="text-muted d-block mb-1">-->
              <!--  ?= $today > $endDate ? 'Final amount' : 'Current total' ?>-->
              <!--</small>-->
        
              <!--<small class="text-muted d-block mb-1">-->
              <!--  Incentive Breakdown: (Base: ₹?= number_format($baseEarned) ?> + Daily: ₹?= number_format($dailyEarned) ?>)-->
              <!--</small>-->
              <!--<small class="text-muted d-block">-->
              <!--  Deductions Applied: (Noble Login: ₹?= number_format($deductionTotal) ?> + Manual: ₹?= number_format($manualDeduction) ?>)-->
              <!--</small>-->
        
            </div>
          </div>
        </div>


    
      <!-- Incentive Payable -->
      <div class="col mb-3">
        <div class="card h-100 border-left-warning">
          <div class="card-body">
            <h5 style="font-size: 2 rem; font-weight: bold;">INCENTIVE PAYABLE AMOUNT</h5>
            <p class="card-text display-4 text-warning">
              ₹<?= number_format($summary['incentive_payable'] ?? (
                  ($summary['earned'] ?? 0) +
                  $dailyEarned -
                  ($agentRow['noble_login_deduction'] * ($summary['earned'] ?? 0)) -
                  ($agentRow['gtbk_deduction'] * ($summary['earned'] ?? 0)) -
                  ($agentRow['deduction_amount'] ?? 0)
              )) ?>
            </p>
            <small style="font-size: 2.5 rem; font-weight: bold;">Creditable amount</small>
          </div>
        </div>
      </div>
    </div>


      <!-- Right Column -->

        <div class="row">
            
  <!-- Left Column: Performance Summary -->
  <div class="col-lg-5">
    <div class="section">
    <h2 class="text-center">📊 KPI Incentive </h2>     
    <div class="alert alert-warning d-flex flex-wrap justify-content-center text-center font-weight-bold py-2 mb-4" style="background-color: #fff3cd; border-left: 5px solid #ffc107; color: black;">
      <!--🕒 Noble Login: ≥ <?= $loginRequiredHrs ?> hrs -->
      <!--<span class="mx-2">|</span> -->
      <!--⏸️ GTBK Pause: ≤ <?= $gtbkLimitHrs ?> hrs -->
      <!--<span class="mx-2">|</span> -->
      📞 GTIB Calls: ≥ <?= $gtibRequired ?>
      <span class="mx-2">|</span> 
      ⏱️ AHT: ≤ <?= $ahtLimit ?> mins 
      <span class="mx-2">|</span> 
      🎯 FCS: ≥ <?= $fcsRequiredPct ?>%
      <span class="mx-2">|</span> 
      📘 Conversion (PIF): ≥ <?= $requiredConversion ?>%
      <span class="mx-2">|</span>
      📘 GARLAND: ≥ <?= $requiredQA ?>%
    </div>    
    <?php
    $period = $_GET['period'] ?? ''; // Example: '2025-06-11_to_2025-06-20'
    
    $today = new DateTime();
    
    $endDateString = '';
    if (strpos($period, '_to_') !== false) {
        list($start, $end) = explode('_to_', $period);
        $endDateString = $end;
    } else {
        // fallback if period is not in expected format
        $endDateString = date('Y-m-d');
    }
    
    $endDate = new DateTime($endDateString);
    $daysLeft = ($today > $endDate) ? 0 : $today->diff($endDate)->days + 1;
    $encouragements = [
      "You’ve still got time to turn it around!",
      "Your bonus is still within reach – go for it!",
      "Let’s finish strong and unlock those rewards!",
      "Every call counts – make them count!",
      "Stay focused and finish on a high!",
      "You’re closer than you think – don’t give up now!",
      "Let’s chase that bonus together!"
    ];
    $randomMessage = $encouragements[array_rand($encouragements)];
    ?>
    <?php if (empty($eligibilityMessages)): ?>
      <div class="alert alert-success text-center font-weight-bold mb-4" style="border-left: 5px solid #28a745;">
        ✅ You're eligible for bonuses! All conditions met.
        <?php if ($daysLeft > 0): ?>
          <br><small>📅 <?= $daysLeft ?> day<?= $daysLeft > 1 ? 's' : '' ?> remaining in this period.</small>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-danger text-center font-weight-bold mb-4" style="border-left: 5px solid #dc3545; color: black;">
      ❌ Not eligible yet – 🎯 Keep pushing!
      <?php if ($daysLeft > 0): ?>
        – 📅 <?= $daysLeft ?> day<?= $daysLeft > 1 ? 's' : '' ?> remaining in this period. 
        <span id="encouragement-message" class="d-block mt-2"></span>
      <?php endif; ?>
        <?= implode(" <span class='mx-2'>|</span> ", $eligibilityMessages) ?>
      </div>
    <?php endif; ?>

    <!--Target Pathway start-->
            <?php if (!empty($targetPathway)): ?>
              <table class="table table-bordered table-hover">
                <tr style="background-color: #ffbb00;">
                  <td colspan="5">
                    🎯 <strong>Target Pathway Details</strong>
                    <?php if ($isFallbackTarget && isset($targetPathway['created_at'])): ?>
                      <span class="badge badge-warning ml-2">
                        Latest target set on <?= date('j M Y', strtotime($targetPathway['created_at'])) ?> used
                      </span>
                    <?php endif; ?>
                  </td>
                </tr>
                    <!-- Base Rate -->
                    <tr>
                        <td><b>Base Rs/Pax</b></td>
                        <td colspan="4">₹<?= number_format($targetPathway['rate'] ?? 0) ?> per PIF</td>
                    </tr>

                    <!-- FCS Rate -->
                    <tr>
                        <td><b>Rs/Pax With FCS Bonus</b></td>
                        <td colspan="4">₹<?= number_format(isset($targetPathway['rate_fcs']) ? $targetPathway['rate_fcs'] : 0) ?></td>
                    </tr>


                    <!-- GTIB Bonus -->
                    <tr>
                        <td><b>GTIB Bonus</b></td>
                        <td colspan="4">₹<?= number_format($targetPathway['gtib_bonus'] ?? 0) ?></td>
                    </tr>

                    <!-- Estimated Total -->
                    <tr>
                        <td><b>Target</b></td>
                        <td colspan="4">₹<?= number_format($targetPathway['total_estimate'] ?? 0) ?></td>
                    </tr>
            </table>
            <table class="table table-bordered table-hover">
                <thead style="background-color: #ffbb00;">
                    <tr>
                        <th>Metric</th>
                        <th>Current</th>
                        <th>Target</th>
                        <th>Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- PIF -->
                    <tr>
                        <td><b>PIF</b></td>
                        <td><?= $pif ?></td>
                        <td><?= isset($targetPathway['min_pif']) ? $targetPathway['min_pif'] : 'N/A' ?></td>
                        <td><?= $pif <= (isset($targetPathway['min_pif']) ? $targetPathway['min_pif'] : 0) ? '❌' : '✅' ?></td>
                        <td>
                            <?php if (isset($targetPathway['min_pif']) && $targetPathway['min_pif'] !== 'N/A'): ?>
                                <?= $pif >= ($targetPathway['min_pif'] ?? 0) ? 'Target achieved' : 'Need ' . (($targetPathway['min_pif'] ?? 0) - $pif) . ' more PIF' ?>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <!-- GTIB -->
                    <?php
                      $actualGtib = (int)($summary['gtib'] ?? 0);
                      $comparisonGtib = isset($targetPathway['min_gtib']) && $targetPathway['min_gtib'] !== 'N/A'
                        ? (int)$targetPathway['min_gtib']
                        : ($gtibRequired ?? 0);
                    ?>
                    
                    <tr>
                      <td><b>GTIB</b></td>
                      <td><?= $actualGtib ?></td>
                      <td>
                        <?= $comparisonGtib > 0 ? $comparisonGtib : 'N/A' ?>
                        <?php if (!isset($targetPathway['min_gtib']) || $targetPathway['min_gtib'] === 'N/A'): ?>
                          <span class="badge badge-info ml-2">From Incentive Rule</span>
                        <?php endif; ?>
                      </td>
                      <td><?= $actualGtib >= $comparisonGtib ? '✅' : '❌' ?></td>
                      <<td>
                      <?php if ($comparisonGtib > 0): ?>
                        <?= $actualGtib >= $comparisonGtib
                          ? (isset($targetPathway['min_gtib']) && $targetPathway['min_gtib'] !== 'N/A'
                              ? 'Target achieved'
                              : 'Condition met')
                          : 'Need ' . ($comparisonGtib - $actualGtib) . ' more calls' ?>
                      <?php else: ?>
                        N/A
                      <?php endif; ?>
                    </td>
                    </tr>
                    
                    <!-- Conversion -->
                    <?php
                      $actualConversion = round($summary['conversion'] * 100, 1);
                      $comparisonConversion = isset($targetPathway['conversion']) && $targetPathway['conversion'] !== 'N/A'
                        ? $targetPathway['conversion']
                        : ($requiredConversion ?? 0);
                    ?>
                    
                    <tr>
                      <td><b>Conversion(PIF)</b></td>
                      <td><?= $actualConversion ?>%</td>
                      <td>
                        <?= $comparisonConversion > 0 ? $comparisonConversion . '%' : 'N/A' ?>
                        <?php if (!isset($targetPathway['conversion']) || $targetPathway['conversion'] === 'N/A'): ?>
                          <span class="badge badge-info ml-2">From Incentive Rule</span>
                        <?php endif; ?>
                      </td>
                      <td><?= $actualConversion >= $comparisonConversion ? '✅' : '❌' ?></td>
                      <td>
                      <?php if ($comparisonConversion > 0): ?>
                        <?= $actualConversion >= $comparisonConversion
                          ? (isset($targetPathway['conversion']) && $targetPathway['conversion'] !== 'N/A'
                              ? 'Target achieved'
                              : 'Condition met')
                          : 'Need ' . round($comparisonConversion - $actualConversion, 1) . '% improvement' ?>
                      <?php else: ?>
                        N/A
                      <?php endif; ?>
                    </td>
                    </tr>

                    <!--FCS required-->
                    <?php
                      $actualFcs = $summary['fcs'] * 100;
                      $comparisonTarget = isset($targetPathway['fcs_mult']) && $targetPathway['fcs_mult'] !== 'N/A'
                        ? $targetPathway['fcs_mult']
                        : ($fcsRequiredPct ?? 0);
                    ?>
                    <tr>
                      <td><b>FCS</b></td>
                      <td><?= round($actualFcs, 1) ?>%</td>
                      <td>
                        <?= $comparisonTarget > 0 ? $comparisonTarget . '%' : 'N/A' ?>
                        <?php if (!isset($targetPathway['fcs_mult']) || $targetPathway['fcs_mult'] === 'N/A'): ?>
                          <span class="badge badge-info ml-2">From Incentive Rule</span>
                        <?php endif; ?>
                      </td>
                      <td><?= $actualFcs >= $comparisonTarget ? '✅' : '❌' ?></td>
                      <td>
                      <?php if ($comparisonTarget > 0): ?>
                        <?= $actualFcs >= $comparisonTarget
                          ? (isset($targetPathway['fcs_mult']) && $targetPathway['fcs_mult'] !== 'N/A'
                              ? 'Target achieved'
                              : 'Condition met')
                          : 'Need ' . round($comparisonTarget - $actualFcs, 1) . '% improvement' ?>
                      <?php else: ?>
                        N/A
                      <?php endif; ?>
                    </td>
                    </tr>

                    <!-- Daily PIF -->
                    <tr>
                        <td><b>Daily PIF</b></td>
                        <td><?= round($pif / max(1, count($dailyData)), 0) ?></td> <!-- Rounded to 3 decimal places -->
                        <td><?= isset($targetPathway['daily_pif']) ? $targetPathway['daily_pif'] : 'N/A' ?></td>
                        <td><?= round($pif / max(1, count($dailyData)), 0) >= (isset($targetPathway['daily_pif']) ? $targetPathway['daily_pif'] : 0) ? '✅' : '❌' ?></td>
                        <td>
                            <?php if (isset($targetPathway['daily_pif']) && $targetPathway['daily_pif'] !== 'N/A'): ?>
                                <?= ($pif / max(1, count($dailyData))) >= ($targetPathway['daily_pif'] ?? 0) ? 'Target achieved' : 'Need ' . round(($targetPathway['daily_pif'] ?? 0) - ($pif / max(1, count($dailyData))), 3) . ' more daily PIF' ?> <!-- Rounded remaining daily PIF to 3 decimal places -->
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Total Incentive -->
                    <tr class="<?= $totalEarned >= ($targetPathway['total_estimate'] ?? 0) ? 'table-success' : 'table-warning' ?>">
                    <td><b>Total Incentive</b></td>
                    <td>₹<?= number_format($totalEarned) ?></td>
                    <td>₹<?= number_format($targetPathway['total_estimate'] ?? 0) ?></td>
                    <td><?= $totalEarned >= ($targetPathway['total_estimate'] ?? 0) && ($targetPathway['total_estimate'] > 0) ? '✅' : '❌' ?></td>
                    <td>
                        <?php if ($targetPathway['total_estimate'] > 0): ?>
                            <?= $totalEarned >= ($targetPathway['total_estimate'] ?? 0) ? 'Congratulations! Target achieved' : '₹' . number_format(($targetPathway['total_estimate'] ?? 0) - $totalEarned) . ' more needed' ?>
                        <?php else: ?>
                            No target set
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
                   
            <table class="table table-bordered table-hover">  

                    <!-- Additional Performance Indicators -->
                    <tr style="background-color: #ffbb00;">
                        <td colspan="5"><b>📌 Eligibility & Additional Metrics</b></td>
                    </tr>

                    <tr>
                        <td><b>Garland Compliance</b></td>
                        <td colspan="4"><?= isset($summary['qa_compliance']) ? round($summary['qa_compliance']) . '%' : 'N/A' ?></td>
                    </tr>
                    <tr>
                        <td><b>AHT</b></td>
                        <td colspan="4" class="<?= !$isAhtEligible ? 'text-danger' : '' ?>">
                            <?= $ahtM ?>:<?= $ahtS ?> mins
                            <?= $isAhtEligible ? '✅' : '🔒 Reduce by ' . ceil($ahtReduceSecs / 60) . ' mins' ?>
                        </td>
                    </tr>
                    <tr>
                        <td><b>Noble Login</b></td>
                        <td colspan="4" class="<?= $isLoginEligible ? 'text-danger' : '' ?>">
                            <?= $nobleHrs ?> hrs
                            <?= !$isLoginEligible ? '✅' : '🔒 Need ' . round($loginRequiredHrs - $nobleHrs, 1) . ' reduce hrs' ?>
                        </td>
                    </tr>
                    <tr>
                        <td><b>GTBK</b></td>
                        <td colspan="4" class="<?= !$isGTBKEligible ? 'text-danger' : '' ?>">
                            <?= $gtbkHrs ?> hrs
                            <?= $isGTBKEligible ? '✅' : ''?>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

  <!-- Right Column: Daily Incentives -->
  <div class="col-lg-7">
      <div class="section">
        <!-- 🔹 Daily Incentives (same container as table) -->
        <h2 class="text-center">📅 Daily Incentives </h2>  
        <div class="alert alert-info text-center font-weight-bold py-3" 
             style="background-color: #d1ecf1; border-left: 5px solid #17a2b8; color: black;">
          📌 Condition: Be logged into <strong>Noble</strong> before shift start time
          <?php if (!empty($fcsRequiredPct)): ?>
            |🎯 FCS ≥ <?= $fcsRequiredPct ?>%
          <?php endif; ?>
          <?php if (!empty($requiredQA)): ?>
            |📘 GARLAND Compliance ≥ <?= $requiredQA ?>%
          <?php endif; ?>
        </div>

        <?php foreach ($daily_incentives as $min_gtib => $daily_incentive): ?>
          <?php
            // Group all criteria under same GTIB + conversion % (assumes they share same conversion per group)
            $pax_rewards = [];
            $conversion = null;
        
            foreach ($daily_incentive['criteria'] as $pax => $criteria) {
                $conversion = $criteria['conversion']; // All assumed same per block
                $pax_rewards[] = "Pax ≥ $pax → reward: ₹" . number_format($criteria['reward'], 0);
            }
          ?>
          <div class="alert alert-warning text-center font-weight-bold py-3" 
               style="background-color: #fff3cd; border-left: 5px solid #ffc107;color: black;">
               
            <div class="mb-1">
              GTIB Calls ≥ <?= $min_gtib ?> | Conv ≥ <?= $conversion ?>%
            </div>
            <div>
              <?= implode(' | ', $pax_rewards) ?>
            </div>
          </div>
        <?php endforeach; ?>

    
        <!-- 🔹 Data Table (same container as incentives) -->
        <table class="table table-striped table-bordered w-100">
          <thead>
            <tr>
              <th>Date</th>
              <th>GTIB</th>
              <th>Pax</th>
              <th>Conv</th>
              <th>FCS</th>
              <th>AHT</th>
               <th>Garland</th>
              <th>Shift Time</th>
              <th>Login Time</th>
              <th>On Time?</th>
              <th>Incentive</th>
              <th>Bonus</th>
              <th>Reward</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($dailyBreakdown as $d): ?>
            <?php
              // one match per row
              $labelForMatch = date('M j', strtotime($d['call_date']));
              $match = null;
              foreach ($dailyData as $dd) { if (($dd['date'] ?? '') === $labelForMatch) { $match = $dd; break; } }
              if (!$match) { $match = ['baseReward'=>0,'reward'=>0,'eligible'=>false]; }
            
              $baseReward = (int)($match['baseReward'] ?? 0);
              $reward     = (int)($match['reward'] ?? 0);
              $bonus      = max(0, $reward - $baseReward);
            
              // on-time gate
              $isOnTime = strtolower(trim((string)($d['on_time'] ?? ''))) === 'on time' || strtolower(trim((string)($d['on_time'] ?? ''))) === 'yes';
              $eligible = (bool)($match['eligible'] ?? false) && $isOnTime;
            
              // **show only when eligible AND amount > 0**
              $showIncentive = $eligible && $baseReward > 0;
              $showBonus     = $eligible && $bonus > 0;
              $showReward    = $eligible && $reward > 0;
            
              // row class: only green when there's a payable reward
              $rowClass = $showReward ? 'table-success' : 'table-secondary';
            ?>
              <?php $rowClass = $showReward ? 'table-success' : 'table-secondary'; ?>
                <tr class="<?= $rowClass ?>">
                <td><?= htmlspecialchars(date('jM', strtotime($d['call_date']))) ?></td>
                <td><?= (int)($d['gtib'] ?? 0) ?></td>
                <td><?= (int)($d['pif'] ?? 0) ?></td>
                <td><?= isset($d['conversion']) ? round($d['conversion'] * 100, 1) . '%' : '-' ?></td>
                <td><?= isset($d['fcs']) ? round($d['fcs'] * 100, 1) . '%' : '-' ?></td>
                <td>
                  <?php
                    $ahtSeconds = round($d['aht'] ?? 0);
                    $ahtM = floor($ahtSeconds / 60);
                    $ahtS = str_pad($ahtSeconds % 60, 2, '0', STR_PAD_LEFT);
                    echo "{$ahtM}:{$ahtS}";
                  ?>
                </td>
                <!-- QA -->
            <?php
              $qaVal = isset($match['qa_compliance']) ? round((float)$match['qa_compliance']) : 0;
            ?>
            <td data-order="<?= $qaVal ?>" data-search="<?= $qaVal ?>">
              <?= $qaVal ?>%
            </td>
                <td>
                  <?php
                    $rawShift = (int)($d['shift_time'] ?? 0);
                    $callDateStr = $d['call_date'] ?? ($endDateString ?? date('Y-m-d'));
                    echo ist_raw_to_aus_time($rawShift, $callDateStr);
                  ?>
                </td>
                <td>
                  <?php
                    $rawLogin = (int)($d['noble_login_time'] ?? 0);
                    $loginH = floor($rawLogin / 10000);
                    $loginM = floor(($rawLogin % 10000) / 100);
                    $loginS = $rawLogin % 100;
                    printf('%02d:%02d:%02d', $loginH, $loginM, $loginS);
                  ?>
                </td>
                <td><?= $isOnTime ? 'Yes' : 'No' ?></td>
            
                <!-- Incentive -->
                    <td>
                      <?php if ($eligible && $baseReward > 0): ?>
                        ₹<?= number_format($baseReward) ?>
                      <?php else: ?>
                        🔒
                      <?php endif; ?>
                    </td>
                    
                    <!-- Bonus -->
                    <td>
                      <?php if ($eligible && $bonus > 0): ?>
                        ₹<?= number_format($bonus) ?>
                      <?php else: ?>
                        🔒
                      <?php endif; ?>
                    </td>
                    
                    <!-- Reward -->
                    <td>
                      <?php if ($eligible && $reward > 0): ?>
                        ₹<?= number_format($reward) ?>
                      <?php else: ?>
                        🔒
                      <?php endif; ?>
                    </td>
            </tr>
            <?php endforeach; ?>
            </tbody>

        </table>
      </div>
    </div>

</div>

 </div>       
    <!-- Modal -->
    <div class="modal fade" id="fullPlanModal" tabindex="-1" role="dialog" aria-labelledby="fullPlanLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="fullPlanLabel">📋 Your Personalized Action Plan</h5>
            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
          </div>
          <div class="modal-body">
            <p><b>Agent:</b> <?= htmlspecialchars($agentName) ?></p>
            <p><b>Target Incentive:</b> ₹<?= number_format($target) ?></p>
            <p><b>Current Earned (Projected):</b> ₹<?= number_format($summary['projected']) ?></p>
            <p><b>Includes:</b> ₹<?= number_format($summary['kpi_component']) ?> KPI + ₹<?= number_format($summary['daily_component']) ?> Daily Incentive</p>
            <p><b>Gap Remaining:</b> ₹<?= number_format($gap) ?></p>
            <hr>
            <?php if (isset($pathways[$bestPathwayIndex]['title'])): ?>
                <h5 class="text-primary">⭐ Recommended Strategy: <?= $pathways[$bestPathwayIndex]['title'] ?></h5>
            <?php endif; ?>
            <?php if (isset($pathways[$bestPathwayIndex]['summary'])): ?>
                <p><?= $pathways[$bestPathwayIndex]['summary'] ?></p>
            <?php endif; ?>
            <ul>
                <?php if (isset($pathways[$bestPathwayIndex]['requirements']) && is_array($pathways[$bestPathwayIndex]['requirements'])): ?>
                    <?php foreach ($pathways[$bestPathwayIndex]['requirements'] as $k => $v): ?>
                        <li><b><?= $k ?>:</b> <?= $v ?></li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
            <?php if (isset($pathways[$bestPathwayIndex]['why'])): ?>
                <p><b>Why this works:</b> <?= $pathways[$bestPathwayIndex]['why'] ?></p>
            <?php endif; ?>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
  // Enable tooltips for all elements with a data-toggle attribute
  $('[data-toggle="tooltip"]').tooltip();

  // Initialize Select2 for the agentSelect element
  $('#agentSelect').select2({
    placeholder: "Search agent...",
    allowClear: true
  });
});

</script>
<script>
  const messages = [
    "You’ve still got time to turn it around!",
    "Your bonus is still within reach – go for it!",
    "Let’s finish strong and unlock those rewards!",
    "Every call counts – make them count!",
    "Stay focused and finish on a high!",
    "You’re closer than you think – don’t give up now!",
    "Let’s chase that bonus together!"
  ];

  let index = 0;
  const msgElement = document.getElementById("encouragement-message");

  function rotateMessage() {
    msgElement.textContent = messages[index];
    index = (index + 1) % messages.length;
  }

  rotateMessage(); // Show first message immediately
  setInterval(rotateMessage, 10000); // Rotate every 10 seconds
</script>
<script>
  const encouragements = [
    "🎯 Keep pushing! Aim for your KPI to unlock incentives.",
    "📈 You're close – just a bit more!",
    "🚀 Stay focused – bonus is within reach!",
    "💪 Every call counts – don't stop now!",
    "🎁 Hit your target to unlock your reward!",
    "🔥 You're capable of amazing results!"
  ];

  document.querySelectorAll("[id^='encourage-']").forEach(el => {
    let i = 0;
    setInterval(() => {
      i = (i + 1) % encouragements.length;
      el.textContent = encouragements[i];
    }, 10000);
  });
</script>

</body>
</html>