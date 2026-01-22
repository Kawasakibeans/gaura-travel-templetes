<?php
/**
 * Template Name: Flight page Testing V2
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */

get_header();
include('wp-config-custom.php');
include "tpl_contralized_functions.php";
if(!session_id() || session_id() == '' || !isset($_SESSION) || session_status() === PHP_SESSION_NONE)
{
    session_start();
}

try {
    $endpoint = 'https://gauratravel.com.au/wp-content/themes/twentytwenty/templates/tpl_admin_backend_for_credential_pass.php';

    // Keys to fetch
    $keys = [
        'GDEAL_COUPON_ENABLED',
        'GDEAL_COUPON_EXPIRY_DATE',
        'GDEAL_COUPON_CODE',
        'GDEAL_COUPON_ID',
        'GDEAL_COUPON_TYPE',
        'GDEAL_COUPON_VALUE',
        'YPSILON_API_ENDPOINT_FLIGHTS',
        'YPSILON_API_VERSION_FLIGHTS',
        'YPSILON_API_AUTH_FLIGHTS',
        
        'GDeal_Deposit_Amount',
    ];

    // Build POST
    $ch = curl_init($endpoint);
    $payload = http_build_query(['keys' => $keys], '', '&');

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
    ]);

    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($err) {
        throw new RuntimeException("cURL error: {$err}");
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("HTTP {$status}: {$body}");
    }

    $json = json_decode($body, true);
    if (!is_array($json) || empty($json['ok'])) {
        $msg = is_array($json) && isset($json['message']) ? $json['message'] : 'Unknown error';
        throw new RuntimeException("Endpoint error: {$msg}");
    }

    $cfg = $json['data'] ?? [];

    // Assign values
    $GDEAL_COUPON_ENABLED      = (string)($cfg['GDEAL_COUPON_ENABLED'] ?? '');
    $GDEAL_COUPON_EXPIRY_DATE  = (string)($cfg['GDEAL_COUPON_EXPIRY_DATE'] ?? '');
    $GDEAL_COUPON_CODE         = (string)($cfg['GDEAL_COUPON_CODE'] ?? '');
    $GDEAL_COUPON_ID           = (string)($cfg['GDEAL_COUPON_ID'] ?? '');
    $GDEAL_COUPON_TYPE         = (string)($cfg['GDEAL_COUPON_TYPE'] ?? '');
    $GDEAL_COUPON_VALUE        = (string)($cfg['GDEAL_COUPON_VALUE'] ?? '');
    
    $YPSILON_API_ENDPOINT_FLIGHTS        = (string)($cfg['YPSILON_API_ENDPOINT_FLIGHTS'] ?? '');
    $YPSILON_API_VERSION_FLIGHTS        = (string)($cfg['YPSILON_API_VERSION_FLIGHTS'] ?? '');
    $YPSILON_API_AUTH_FLIGHTS        = (string)($cfg['YPSILON_API_AUTH_FLIGHTS'] ?? '');
    
    $GDeal_Deposit_Amount       = $cfg['GDeal_Deposit_Amount'] ?? '5';

    // now you can use those in your payment logic
} catch (Throwable $e) {
    error_log('Config fetch failed: ' . $e->getMessage());
    // handle gracefully
}

$_SESSION['isWebBooking'] = '1';
    
echo do_shortcode('[cws-flight-book]');

function check_ypsilon_fare($from, $to, $date, $airline)
{
    global $YPSILON_API_ENDPOINT_FLIGHTS, $YPSILON_API_VERSION_FLIGHTS, $YPSILON_API_AUTH_FLIGHTS;
    
    $curl = curl_init();
    //echo $from . ' - ' .$to. ' - ' . $date. ' - ' . $airline.'</br>';
    curl_setopt_array($curl, array(
      CURLOPT_URL => $YPSILON_API_ENDPOINT_FLIGHTS,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS =>'<?xml version=\'1.0\' encoding=\'UTF-8\'?><fareRequest xmlns:shared="http://ypsilon.net/shared" da="true"><vcrs><vcr>'.$airline.'</vcr></vcrs><alliances/><shared:fareTypes/><tourOps/><flights><flight depDate="'.$date.'" dstApt="'.$to.'" depApt="'.$from.'"/></flights><paxes><pax gender="M" surname="Klenz" firstname="Hans A ADT" dob="1970-12-12"/></paxes><paxTypes/><options><limit>1</limit><offset>0</offset><vcrSummary>false</vcrSummary><waitOnList><waitOn>ALL</waitOn></waitOnList></options><coses><cos>E</cos></coses><agentCodes><agentCode>gaura</agentCode></agentCodes><directFareConsos><directFareConso>gaura</directFareConso></directFareConsos></fareRequest>',
      CURLOPT_HTTPHEADER => array(
        'accept: application/xml',
        'accept-encoding: gzip',
        'api-version: ' . $YPSILON_API_VERSION_FLIGHTS , 
        'accessmode: agency',
        'accessid: gaura gaura',
        'authmode: pwd',
        'authorization: Basic '. $YPSILON_API_AUTH_FLIGHTS,
        'content-Length: 642',
        'Connection: close',
        'Content-Type: text/plain'
      ),
    ));
    
    $response = curl_exec($curl);
    
    curl_close($curl);
    
    
    if ($response) {
        $xml = simplexml_load_string($response);
        //echo '<pre>';
    //print_r($xml);
    //echo '</pre>';
        if ($xml && isset($xml->tarifs->tarif)) {
            // Retrieve total amount
            $adtSell = (string)$xml->tarifs->tarif['adtSell'];
            $adtTax = (string)$xml->tarifs->tarif['adtTax'];
            $total_amount = (float)$adtTax + (float)$adtSell;
            
            // Retrieve baggage allowance
            $baggage_allowance = null;
            if (isset($xml->tarifs->tarif->fareXRefs->fareXRef->flights->flight->legXRefs->legXRef)) 
            {
                $legXRefId = (string)$xml->tarifs->tarif->fareXRefs->fareXRef->flights->flight->legXRefs->legXRef['legXRefId'];

                foreach ($xml->serviceMappings->map as $map) 
                {
                    if ((string)$map['elemID'] === $legXRefId) 
                    {
                        $serviceID = (string)$map['serviceID'];
                        //echo $legXRefId.'</br>';
                        $xml->registerXPathNamespace('shared', 'http://ypsilon.net/shared');

                        // Find the serviceGroup with identifier 'baggage'
                        $baggageGroups = $xml->xpath('//shared:specialServices/serviceGroup[@identifier="baggage"]');
                        if ($baggageGroups && count($baggageGroups) > 0) {
                            // Iterate through the items in the selectionGroup
                            foreach ($baggageGroups as $baggageGroup) 
                            {
                                foreach ($baggageGroup->service->selectionGroup->item as $item) {

                                    if ((string)$item['id'] === $serviceID) {
                                        $baggage_allowance = (string)$item['totalAllowance'] . ' ' . (string)$item['unit'];
                                        break 2; // Exit both loops once found
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return [
                'total_amount' => $total_amount,
                'baggage' => $baggage_allowance ?: 'Not Available'
            ];
        } else {
            return '';
        }
    } else {
        return '';
    }
}


?>

<?php
// Initialize Offer array
$offers = [];
if(isset($ypsilonResults))
{
    foreach ($ypsilonResults['offers'] as $offer) {
        $offers[] = [
            "@type" => "Offer",
            "name" => "Flight from {$offer['departure_airport']} to {$offer['arrival_airport']} - {$offer['fare_type']}",
            "url" => "https://gauratravel.com.au/flights/?type={$offer['flight_type']}&class={$offer['class']}&adt={$offer['adt']}&chd={$offer['chd']}&inf={$offer['inf']}&depdate1={$offer['depdate1']}&depapt1={$offer['departure_airport']}&dstapt1={$offer['arrival_airport']}",
            "availability" => "https://schema.org/InStock",
            "validFrom" => $offer['bookingdate'],
            "priceSpecification" => [
                "@type" => "PriceSpecification",
                "price" => $offer['total_price'],
                "priceCurrency" => $offer['currency']
            ],
            "itemOffered" => [
                "@type" => "Flight",
                "flightNumber" => $offer['flight_number'] ?? "",
                "departureAirport" => [
                    "@type" => "Airport",
                    "iataCode" => $offer['departure_airport'],
                    "name" => $offer['departure_airport_name'] ?? "",
                    "address" => [
                        "@type" => "PostalAddress",
                        "addressCountry" => $offer['departure_country']
                    ]
                ],
                "arrivalAirport" => [
                    "@type" => "Airport",
                    "iataCode" => $offer['arrival_airport'],
                    "name" => $offer['arrival_airport_name'] ?? "",
                    "address" => [
                        "@type" => "PostalAddress",
                        "addressCountry" => $offer['arrival_country']
                    ]
                ],
                "departureTime" => date(DATE_ATOM, strtotime($offer['outbounddate'])),
                "arrivalTime" => date(DATE_ATOM, strtotime($offer['inbounddate'])),
                "carrier" => [
                    "@type" => "Airline",
                    "name" => $offer['airline_name'] ?? "",
                    "iataCode" => $offer['airline']
                ]
            ],
            "eligibleQuantity" => [
                "@type" => "QuantitativeValue",
                "value" => $offer['pax'],
                "unitCode" => "C62"
            ],
            "seller" => [
                "@type" => "TravelAgency",
                "name" => "Gaura Travel",
                "url" => "https://gauratravel.com.au/",
                "address" => [
                    "@type" => "PostalAddress",
                    "streetAddress" => "Suite 3, Level 6, 60 Albert Road",
                    "addressLocality" => "South Melbourne",
                    "addressRegion" => "VIC",
                    "postalCode" => "3205",
                    "addressCountry" => "AU"
                ]
            ]
        ];
    }
}
// Wrap everything in ItemList
$schema = [
    "@context" => "https://schema.org",
    "@type" => "ItemList",
    "name" => "Flight Search Results",
    "itemListElement" => $offers
];

// Output JSON-LD into the page head
echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
?>
   
<style> 
/* custom - flight -deals */

.card {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    grid-column-gap: 0.75rem;
    box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;
    margin-bottom: 1.2rem;
    position: relative;
}

.card-content {
    display: grid;
    grid-template-columns: 1fr 400px;
    background: white;
    border-radius: 10px;
    padding: 0.75rem;
}

.grid-layout-4 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    grid-column-gap: 0.75rem;
    grid-row-gap: 0.75rem;
    max-width: 60%;
    flex: 1;
    justify-items: center;
}

.air-line-img {
    width: 120px;
    height: 25px;
}

.destination {
    font-size: 2.95rem;
    margin: 0;
}
.price2 {
    display: flex;
    flex-direction: column;
    align-items: center;
}
.price {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.discounted-price {
    font-size: 25px;
    font-weight: 600;
}

.original-price {
    font-size: 18px;
    color: #8a8a8a;
}

.book-now {
    border: 0;
    background-color: #ffbb00;
    color: white;
    border-radius: 5px;
}

.book-nowreturn, .book-nononeway {
    border: 0;
    background-color: #ffbb00;
    color: white;
    border-radius: 5px;
}

.offer {
    background: #06a10a;
    padding: 7px 7px;
    color: #fff;
    align-self: end;
}

.month {
    font-size: 18px;
    color: #8a8a8a;
}

.p-tb-4 {
    padding: 8px 0px;
}

.m-0 {
    margin: 0;
    margin-left: 2rem;
}

.deal {
    margin-top: 1rem;
    margin-left: 1rem;
    max-width: 785px;
}


@media screen and (min-width: 1024px) {
    .deal {
        max-width: 985px;
    }
}

/* ribbon tag */
.dealwrapper {
    max-width: 350px;
    background: #ffffff;
    border-radius: 8px;
    -webkit-box-shadow: 0px 0px 50px rgba(0, 0, 0, 0.15);
    -moz-box-shadow: 0px 0px 50px rgba(0, 0, 0, 0.15);
    box-shadow: 0px 0px 50px rgba(0, 0, 0, 0.15);
    position: relative;
}

.ribbon-wrapper {
    width: 100px;
    height: 100px;
    overflow: hidden;
    position: absolute;
    z-index: 1;
}

.ribbon-tag {
    text-align: center;
    -webkit-transform: rotate(318deg);
    -moz-transform: rotate(318deg);
    -ms-transform: rotate(318deg);
    -o-transform: rotate(318deg);
    position: relative;
    padding: 8px 0;
    left: -41px;
    top: 7px;
    width: 150px;
    color: #ffffff;
    -webkit-box-shadow: 0px 0px 3px rgba(0, 0, 0, 0.3);
    -moz-box-shadow: 0px 0px 3px rgba(0, 0, 0, 0.3);
    box-shadow: 0px 0px 3px rgba(0, 0, 0, 0.3);
    text-shadow: rgba(255, 255, 255, 0.5) 0px 1px 0px;
    background: #343434;
    font-size: 22px;
    background: #343434;
}

.ribbon-tag:before,
.ribbon-tag:after {
    content: "";
    border-top: 3px solid #50504f;
    border-left: 3px solid transparent;
    border-right: 3px solid transparent;
    position: absolute;
    bottom: -3px;
}

.ribbon-tag:before {
    left: 0;
}

.ribbon-tag:after {
    right: 0;
}

.dealwrapper.yellow .ribbon-tag {
    background: #ffbb00;
}

.flight-id {
    font-size: 14px;
    font-weight: 300;
    color: #8a8a8a;
}

.offer-price {
    /*display: flex;*/
    align-items: center;
    gap: 0.3rem;
}

.src-dts {
    display: flex;
    justify-content: space-evenly;
    align-items: center;
    /*margin-top: 25px;*/
}

.seat-avail-wrapper {
    padding: 5px;
}

.seat-avail {
    color: #c12d2a;
}

.flight-id {
    width: 25%;
    margin-top: 10px;
    background-color: #8a8a8a;
    color: white;
}

.journey-time {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.m-t-25 {
    margin-top: 25px;
}

.offer-container {
    transform: skew(160deg);
}

.card a,
.card a:hover {
    text-decoration: none;
    color: white;
}

.m-0 img {
    width: 120px;
    height: 100%;
}

.complimentary-lounge-img {
    position: absolute;
    width: 130px;
    top: 12%;
    height: 55%;
    right: 25%;
}

.extra {
   
    flex-direction: row;
    gap: 10px;
    margin-top: 10px;
}
@media screen and (min-width: 768px) {
    .extra {
         display: flex;
    }
}

@media screen and (min-width: 1024px) {
    .wptravel-archive-wrapper {
        margin: auto;
        max-width: 80%;
    }

    .destination-content {
        font-size: 16px;
    }
}

@media screen and (max-width: 1024px) {
    .wptravel-archive-wrapper {
        margin: auto;
        max-width: 95%;
    }

    .complimentary-lounge-img {
        position: absolute;
        width: 104px;
        top: 3%;
        height: 38%;
        right: 29%;
    }
}

@media screen and (max-width: 820px) {

    .wptravel-archive-wrapper {
        margin: auto;
        max-width: 95%;
    }

    .display-inlne {
        display: inline-block;
    }

    .deal {
        margin-top: 1rem;
        margin-left: 2rem;
    }

    .card-content {
        position: relative;
    }

    .month {
        /*position: absolute;*/
        bottom: 6px;
        right: 25px;
    }

    .src-dts-info {
        display: flex;
        flex-direction: column;
        align-items: center;
        
    }

    .grid-layout-4 {
        align-items: center;
    }

}

@media screen and (max-width: 750px) {

    .card-content {
        grid-template-columns: 1fr;
    }

    .src-dts {
        margin-left:50px;
        align-items: stretch;
    }

    .destination {
        font-size: 14pt;
        font-weight: bold;
    }

    .wptravel-archive-wrapper {
        margin: auto;
        padding: 15px;
    }

    
    .destination-content {
        font-size: 12px;
    }

    .discounted-price {
        font-size: 16pt;
        font-weight: 600;
    }

    .original-price {
        font-size: 14pt;
        color: #8a8a8a;
    }

    .display-inlne {
        display: inline-block;
    }

    .deal {
        position: relative;
        margin-left: 0;
    }

    .month {
        right: 10px;
        bottom: 0;
    }

    .grid-layout-4 {
        max-width: 70%;
    }

    .m-0 {
        margin-left: 0;
    }


}

@media screen and (max-width: 425px) {
    .complimentary-lounge-img {
        position: absolute;
        width: 104px;
        top: 12%;
        height: 38%;
        right: 18%;
    }
}

    @media screen and (min-width: 320px) and (max-width: 375px) {
        .complimentary-lounge-img {
            position: absolute;
            width: 91px;
            top: 13%;
            height: 32%;
            right: 0%;
        }

    }
    
    
    @media screen and (max-width: 768px) 
    { 
        .lockinprice_top 
        {
            padding:0px 0px 0px 100px !important;
            display:block;
        }
    } 
    
    @media screen and (min-width: 768px) 
    { 
        .lockinprice_top 
        {
            display:none;
        }
    }
@media screen and (min-width: 769px) 
{    
.price_tag_main {
    margin-top:20px;
    display: flex;
    justify-content: space-between; 
    gap: 5px; 
}

.price_column_1, .price_column_2  {
    flex: 1; 
    text-align: center; 
    padding: 10px; 
}   
}
@media screen and (max-width: 768px) 
{
    .price_tag_main {
        margin-top:0;
        display: grid;
        gap: 10px; 
        width:100%;
        text-align: center;
    
    }
    .price_column_1, .price_column_2  {
        width:100%;
        justify-items: center;
    }
    .price_column_1
    {
        margin-top:20px;
    }
}

</style>

<?php
$months = array('january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december');

$enter = 0;
$finalmonts = array();

if (isset($_GET['depdate1']) && isset($_GET['retdate1'])) 
{
    $dep_date_ymd = '';
    $ret_date_ymd = '';
    
    $from = strtolower(date('F', strtotime($_GET['depdate1'])));

    $to =  strtolower(date('F', strtotime($_GET['retdate1'])));

    if ($_GET['depdate1'] != '' && $_GET['retdate1'] != '') 
    {
        foreach ($months as $key => $value) {
            if ($from == $value) {
                $enter = 1;
            }
            if ($enter == 1) {
                array_push($finalmonts, $value);
            }
            if ($to == $value) {
                $enter = 0;
            }
        }
        $dep_date_ymd = date('Y-m-d', strtotime($_GET['depdate1']));
        $ret_date_ymd = date('Y-m-d', strtotime($_GET['retdate1']));
    } 
    elseif ($_GET['depdate1'] != '' && $_GET['retdate1'] == '') 
    {
        array_push($finalmonts, $from);
        $dep_date_ymd = date('Y-m-d', strtotime($_GET['depdate1']));
    } 
    elseif ($_GET['depdate1'] == '' && $_GET['retdate1'] != '') 
    {
        array_push($finalmonts, $to);
        $ret_date_ymd = date('Y-m-d', strtotime($_GET['retdate1']));
    } else 
    {
        $finalmonts = $months;
    }
}
else 
{
    $from = strtolower(date('F', strtotime(date("Y-m-d"))));

    $to =  strtolower(date('F', strtotime(date("Y-m-d"))));
    
    $finalmonts = $months;
}

if(!isset($dep_date_ymd) || ( isset($dep_date_ymd) && $dep_date_ymd == '' ) )
{
    $dep_date_ymd = date("Y-m-d");
}
$dep_date_ymd_cropped = '';
$ret_date_ymd_cropped = '';

if(isset($dep_date_ymd) && $dep_date_ymd != '')
{
    $dep_date_ymd_cropped = substr($dep_date_ymd, 0, 7);
}

if(isset($ret_date_ymd) && $ret_date_ymd != '')
{
    $ret_date_ymd_cropped = substr($ret_date_ymd, 0, 7);
}

global $current_user; 
wp_get_current_user();
        
        
$currnt_userlogn = $current_user->user_login;
        
$dstapt1 = '';
$depapt1 = '';

if (isset($_GET['dstapt1'])) {
    $dstapt1 = $_GET['dstapt1'];
}

if (isset($_GET['depapt1'])) {
    $depapt1 = $_GET['depapt1'];
}

$the_query = new WP_Query(array(
    'post_type' => 'itineraries',
    'post_status' => array('private', 'publish'),
    'tax_query' => array(
        'relation' => 'AND',
        array(
            'taxonomy' => 'travel_locations',
            'field' => 'slug',
            'terms' => $dstapt1,
        ),
        array(
            'taxonomy' => 'origin',
            'field' => 'slug',
            'terms' => $depapt1,
        ),
        array(
            'taxonomy' => 'month',
            'field' => 'slug',
            'terms' => $from,
        )
    ),
));

$the_query_return = new WP_Query(array(
    'post_type' => 'itineraries',
    'post_status' => array('private', 'publish'),
    'tax_query' => array(
        'relation' => 'AND',
        array(
            'taxonomy' => 'travel_locations',
            'field' => 'slug',
            'terms' => $depapt1,
        ),
        array(
            'taxonomy' => 'origin',
            'field' => 'slug',
            'terms' => $dstapt1,
        ),
        array(
            'taxonomy' => 'month',
            'field' => 'slug',
            'terms' => $to,
        )
    ),
));


?>
<center>
<?php
if(!isset($_GET['agent']))
{
    echo '</br>';     
}
?>
<h1 style="font-size:48px; margin-bottom:10px;">Flight Search</h1>
<h5 style="margin-top:18px;">Discover exclusive flight deals to India with Gaura Travel, with round-the-clock customer support.</h5>
<h6 style="margin:15px; font-weight:400; font-size: 1.6rem; text-transform: none;">Lock in now from $5, and pay the rest later.</h6>
<h6 style="margin:15px; font-weight:400; font-size: 1.6rem; text-transform: none;">Contact our Customer Care team available 24*7 at <a href="tel:1300359463">1300 359 463</a> to book your journey hassle-free.</h6>
<table style="width:300px; border:none; text-align:center;">
    <tr>
        <td style="width:150px; border:none; text-align:center;">
            <div class="vc_btn3-container  city_section_v2_button vc_btn3-center"><a class="vc_general vc_btn3 vc_btn3-size-sm vc_btn3-shape-rounded vc_btn3-style-modern vc_btn3-color-grey" href="tel:1300359463" title="">Call Now</a></div>
        </td>
        <td style="width:150px; border:none; text-align:center;">
            <div class="vc_btn3-container  city_section_v2_button vc_btn3-center"><a class="vc_general vc_btn3 vc_btn3-size-sm vc_btn3-shape-rounded vc_btn3-style-modern vc_btn3-color-grey" href="/offer/" target="_blank;" title="">Know More</a></div>
        </td>
    </tr>
</table>
</center>
<?php
if(isset($currnt_userlogn) && $currnt_userlogn == 'sriharshans')
    {
        //echo '<pre>';
        //print_r($the_query);
        //echo '</pre>';
    }
$is_monthly_stock_note_to_show = 0;
if ($the_query->have_posts())
{
    
?>
<div class="combine-deal-custom wptravel-layout-v2">
    <div class="title-section new_custom_product_section"></div>
    <div class="wp-travel-archive-content ">
        <script type="text/javascript">
        function toggleItinerary(tripId) {
            var itineraryDiv = document.getElementById(tripId + '_trip_itinerary');
            var button = document.getElementById('button_' + tripId);
            
            // Check if the selected div is currently visible
            var isCurrentlyVisible = itineraryDiv.style.display === "block";
        
            // Hide all itinerary divs
            var allItineraryDivs = document.querySelectorAll('[id$="_trip_itinerary"]');
            allItineraryDivs.forEach(function(div) {
                div.style.display = "none";
            });
        
            // If the selected div was not visible, show it; otherwise, it remains hidden
            if (!isCurrentlyVisible) {
                itineraryDiv.style.display = "block";
                button.innerHTML = '<i class="fa fa-chevron-up"></i> Itinerary';
            } else {
                itineraryDiv.style.display = "none";
                button.innerHTML = '<i class="fa fa-chevron-down"></i> Itinerary';
            }
        }

        function togglereturnItinerary(tripId) {
            
            var itineraryDiv = document.getElementById(tripId + '_returntrip_itinerary');
            var button = document.getElementById('button_return_' + tripId);
            
            // Check if the selected div is currently visible
            var isCurrentlyVisible = itineraryDiv.style.display === "block";
            
            var allItineraryDivs = document.querySelectorAll('[id$="_returntrip_itinerary"]');
            allItineraryDivs.forEach(function(div) {
                div.style.display = "none";
            });
            
            if (!isCurrentlyVisible) {
                itineraryDiv.style.display = "block";
                button.innerHTML = '<i class="fa fa-chevron-up"></i> Itinerary';
            } else {
                itineraryDiv.style.display = "none";
                button.innerHTML = '<i class="fa fa-chevron-down"></i> Itinerary';
            }
        }
        
        function updateURLWithOnGoingDate(startDate) {
            // Convert the start date to the required format (dd-mm-yyyy)
            var formattedDate = new Date(startDate).toLocaleDateString('en-GB').split('/').join('-');
        
            // Get the current URL
            var currentUrl = new URL(window.location.href);
        
            // Update the `depdate1` parameter in the URL
            currentUrl.searchParams.set('depdate1', formattedDate);
            
            currentUrl.searchParams.delete('get_flexible_date');
        
            // Reload the page with the new URL
            window.location.href = currentUrl.toString();
        }
        
        function updateURLWithReturnDate(startDate) {
            // Convert the start date to the required format (dd-mm-yyyy)
            var formattedDate = new Date(startDate).toLocaleDateString('en-GB').split('/').join('-');
        
            // Get the current URL
            var currentUrl = new URL(window.location.href);
        
            // Update the `depdate1` parameter in the URL
            currentUrl.searchParams.set('retdate1', formattedDate);
            
            currentUrl.searchParams.delete('get_flexible_date');
        
            // Reload the page with the new URL
            window.location.href = currentUrl.toString();
        }

        function flexibleDateButton()
        {
            var currentUrl = new URL(window.location.href);
            currentUrl += "&get_flexible_date=true";
            window.location.href = currentUrl;
        }
        </script>
        <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const carousels = document.querySelectorAll('[data-product-carousel]');
                                    
                                        carousels.forEach(function(carousel) {
                                            const datesCarousel = carousel.querySelector('.dates-carousel');
                                            const leftArrow = carousel.querySelector('.left-arrow');
                                            const rightArrow = carousel.querySelector('.right-arrow');
                                            let isDown = false;
                                            let startX;
                                            let scrollLeft;
                                    
                                            leftArrow.classList.add('disabled'); // Initially disable the left arrow
                                    
                                            // Event listeners for the draggable functionality
                                            datesCarousel.addEventListener('mousedown', (e) => {
                                                isDown = true;
                                                datesCarousel.classList.add('active');
                                                startX = e.pageX - datesCarousel.offsetLeft;
                                                scrollLeft = datesCarousel.scrollLeft;
                                            });
                                            datesCarousel.addEventListener('mouseleave', () => {
                                                isDown = false;
                                                datesCarousel.classList.remove('active');
                                            });
                                            datesCarousel.addEventListener('mouseup', () => {
                                                isDown = false;
                                                datesCarousel.classList.remove('active');
                                                updateArrowState(datesCarousel, leftArrow, rightArrow); // Update arrows state after drag ends
                                            });
                                            datesCarousel.addEventListener('mousemove', (e) => {
                                                if (!isDown) return;
                                                e.preventDefault();
                                                const x = e.pageX - datesCarousel.offsetLeft;
                                                const walk = (x - startX) * 3; //scroll-fast
                                                datesCarousel.scrollLeft = scrollLeft - walk;
                                            });
                                    
                                            // Arrow click functionality
                                            rightArrow.addEventListener('click', () => {
                                                datesCarousel.scrollLeft += 200; // Adjust the scroll value as needed
                                                updateArrowState(datesCarousel, leftArrow, rightArrow);
                                            });
                                    
                                            leftArrow.addEventListener('click', () => {
                                                datesCarousel.scrollLeft -= 200; // Adjust the scroll value as needed
                                                updateArrowState(datesCarousel, leftArrow, rightArrow);
                                            });
                                    
                                            // Function to update the state of the arrows based on the scroll position
                                            function updateArrowState(carousel, leftArrow, rightArrow) {
                                                if (carousel.scrollLeft > 0) {
                                                    leftArrow.classList.remove('disabled');
                                                } else {
                                                    leftArrow.classList.add('disabled');
                                                }
                                                const maxScrollLeft = carousel.scrollWidth - carousel.clientWidth;
                                                if (carousel.scrollLeft < maxScrollLeft - 1) { // Adjust if necessary
                                                    rightArrow.classList.remove('disabled');
                                                } else {
                                                    rightArrow.classList.add('disabled');
                                                }
                                            }
                                    
                                            // Initialize arrows state based on initial content
                                            updateArrowState(datesCarousel, leftArrow, rightArrow);
                                        });
                                    });
                                </script>
                                <style>
                        /* Modal Box */
                        .model-stockbox2 {
                            position: fixed;
                            top: 50%;
                            left: 50%;
                            transform: translate(-50%, -50%);
                            background-color: #FFF;
                            font-size:17px;
                            color: black;
                            width: 90%;
                            max-width: 600px;
                            height:250px;
                            padding: 20px;
                            z-index: 10000;
                            display: none;
                            border-radius: 5px;
                            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
                        }
                    
                        .model-stockbox-content2 {
                            text-align: center;
                            font-size: 20px;
                        }
                    
                        .close-button2 {
                            position: absolute;
                            top: 0px;
                            right: 8px;
                            color: black;
                            font-size: 26px;
                            font-weight: bold;
                            cursor: pointer;
                        }
                    
                        .close-button2:hover {
                            color: red;
                        }
                        #modal-stockbox-overlay2 {
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background-color: rgba(0, 0, 0, 0.6);
                            z-index: 9999;
                            display: none;
                        }
                    </style>

<div id="modal-stockbox-overlay2"></div>
<div id="stock-model-stockbox2" class="model-stockbox2">
                        <span class="close-button2">&times;</span>
                        <div class="model-stockbox-content2">
                            </br>
                            <p id="model-stockbox-message2"></p>
                        </div>
                    </div>
                    
                                <style>
                                .pay_selected
                                {
                                    background-color:#ffbb00;
                                }
                                    .dates-carousel.active {
                                        cursor: grabbing;
                                        cursor: -webkit-grabbing;
                                    }
                                    
                                    .carousel-arrow.disabled {
                                        opacity: 0.5;
                                        pointer-events: none;
                                    }
        
                                    .carousel-container {
                                        position: relative;
                                        margin: auto;
                                        max-width:520px;
                                    }
                                    
                                    .dates-carousel {
                                        display: flex;
                                        overflow-x: auto;
                                        scroll-behavior: smooth;
                                        overflow: hidden;
                                        margin:0px 35px;
                                    }
                                    
                                    @media screen and (max-width: 1023px) 
                                    {
                                        .carousel-container {
                                            position: relative;
                                            margin: auto;
                                            max-width:330px;
                                        }
                                        
                                        .dates-carousel {
                                            
                                            margin:0px 25px;
                                        }
                
                                    }
                                    .carousel-arrow {
                                        position: absolute;
                                        top: 50%;
                                        transform: translateY(-50%);
                                        cursor: pointer;
                                        color: black;
                                        z-index: 10;
                                        user-select: none;
                                    }
                                    .carousel-arrow.disabled {
                                        opacity: 0.5;
                                        pointer-events: none; /* Prevents clicks on the disabled arrow */
                                    }
                                    .left-arrow {
                                        left: 0;
                                        padding:1px 4px !important;
                                        margin:0px 4px 0px 0px;
                                        border:1px solid #ffbb00;
                                        color:black;
                                    }
                                    .right-arrow {
                                        right: 0;
                                        padding:1px 4px !important;
                                        margin:0px 0px 0px 4px;
                                        border:1px solid #ffbb00;
                                        color:black;
                                    }  
                                </style>
        <?php
        $is_promotion_on = 1; // if there are any promotion ongoing then make it 1. else 0
        $promotion_deposit_amount = $GDeal_Deposit_Amount; // fixed amount for deposit during the promotion
        
        $promotion_deposit_amount = number_format((float)$promotion_deposit_amount, 2, '.', '');
        
        $is_discount_on = 0;
        $discount_percentage = 0;
        
        $is_coupon_on = 0;
        $is_count_amount = 0;
        
        
        
        $is_gaura_agent = 0;
        if($currnt_userlogn != '' )
        {
            $is_gaura_agent = 1;
        }
        
        function is_slicePayEligible($total_booking_charge, $dep_date_ymd)
        {
            // Convert the departure date to a DateTime object
            $departure_date = new DateTime($dep_date_ymd);
            $current_date = new DateTime();
        
            // Calculate the difference in days between today and the departure date
            $interval = $current_date->diff($departure_date);
            $days_difference = (int)$interval->format('%r%a'); // Include negative values if any
        
            // Check if the departure date is greater than 14 days from today
            if ($days_difference > 14) {
                // Calculate the number of weeks between today and the departure date
                $number_of_weeks = ceil($days_difference / 7) - 3;
                if($number_of_weeks > 0)
                {
                    // Calculate total payable: 8% of the total booking charge
                    $total_payable = $total_booking_charge + (0.06 * $total_booking_charge);
            
                    // Calculate deposit payable: 5% of the total payable
                    $deposit_payable = 0.05 * $total_payable;
            
                    // Calculate balance payable
                    $balance_payable = $total_payable - $deposit_payable;
            
                    // Calculate weekly installment amount
                    $weekly_installment = $balance_payable / $number_of_weeks;
            
                    // Return the results as an associative array
                    return [
                        'is_eligible' => true,
                        'total_payable' => round($total_payable),
                        'deposit_payable' => round($deposit_payable),
                        'balance_payable' => round($balance_payable),
                        'weekly_installment' => round($weekly_installment),
                        'number_of_weeks' => $number_of_weeks,
                    ];
                }
                else
                {
                    return [
                        'is_eligible' => false,
                        'message' => 'Departure date is not greater than 14 days from today.'
                    ];
                }
            }
        
            // If not eligible, return is_eligible as false
            return [
                'is_eligible' => false,
                'message' => 'Departure date is not greater than 14 days from today.'
            ];
        }
        if( isset($_SESSION['userId']) || $is_gaura_agent == 1 )
        {
            global $wpdb;
            $user_id_for_promo = ($_SESSION['userId'] ?? false) ? $_SESSION['userId'] : '' ;
            $query_promo = $wpdb->prepare(
                "SELECT email FROM wpk4_customer_accounts WHERE uid = %s ",
                $user_id_for_promo
            ); 
            $result_promo = $wpdb->get_var($query_promo);
            //echo $result_promo.'</br>';
            $query_promo_is_email_existing = $wpdb->prepare(
                "SELECT auto_id FROM wpk4_backend_travel_bookings_pax_email_db WHERE email = %s ",
                $result_promo
            ); 
            $result_promo_is_email_existing = $wpdb->get_var($query_promo_is_email_existing);
            //echo $result_promo_is_email_existing.'</br>';
            if ( ( $is_gaura_agent == 1 || $result_promo_is_email_existing ) && isset($_GET['depdate1']) && $_GET['depdate1'] != '' && isset($_GET['retdate1']) && $_GET['retdate1'] != '') 
            {
                //echo '<center><img src="https://gauratravel.com.au/wp-content/uploads/2024/11/Voucher-v2.jpg" style="width:auto; height:200px;"></br></br></center>';
                $is_coupon_on = 0;
                $is_count_amount = 25;
                $is_count_amount_total = 50;
            } 
            else 
            {
                $is_coupon_on = 0;
                $is_count_amount = 0;
            }
            ?>
            <style>
                        /* Fullscreen Loader */
                        #fullscreen-loader {
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background-color: rgba(0, 0, 0, 0.8);
                            z-index: 9999;
                            display: flex;
                            justify-content: center;
                            align-items: center;
                        }
                    
                        .loader-container {
                            text-align: center;
                            color: yellow;
                        }
                    
                        .spinner {
                            border: 4px solid rgba(255, 255, 255, 0.3);
                            border-top: 4px solid yellow;
                            border-radius: 50%;
                            width: 40px;
                            height: 40px;
                            animation: spin 1s linear infinite;
                            margin: 10px auto;
                        }
                    
                        @keyframes spin {
                            0% {
                                transform: rotate(0deg);
                            }
                            100% {
                                transform: rotate(360deg);
                            }
                        }
                        /* Modal Box */
                        .model-stockbox {
                            position: fixed;
                            top: 50%;
                            left: 50%;
                            transform: translate(-50%, -50%);
                            background-color: #FFF;
                            font-size:17px;
                            color: black;
                            width: 90%;
                            max-width: 600px;
                            height:180px;
                            padding: 20px;
                            z-index: 10000;
                            display: none;
                            border-radius: 5px;
                            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
                        }
                    
                        .model-stockbox-content {
                            text-align: center;
                            font-size: 20px;
                        }
                    
                        .close-button {
                            position: absolute;
                            top: 0px;
                            right: 8px;
                            color: black;
                            font-size: 26px;
                            font-weight: bold;
                            cursor: pointer;
                        }
                    
                        .close-button:hover {
                            color: red;
                        }
                        #modal-stockbox-overlay {
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background-color: rgba(0, 0, 0, 0.6);
                            z-index: 9999;
                            display: none;
                        }
                    </style>

                    <div id="modal-stockbox-overlay"></div>
                    
                    <div id="fullscreen-loader" style="display: none;">
                        <div class="loader-container">
                            <img src = 'https://gauratravel.com.au/wp-content/uploads/2024/11/GT_LoadingAnimation-1.gif' style = 'width:400px;'>
                            <!--<div class="spinner"></div>
                            <p>Revalidating the trip... Please wait.</p>-->
                        </div>
                    </div>
                    
                    <div id="stock-model-stockbox" class="model-stockbox">
                        <span class="close-button">&times;</span>
                        <div class="model-stockbox-content">
                            </br>
                            <p id="model-stockbox-message"></p>
                        </div>
                    </div>
            <?php
            
            if(isset($_GET['depdate1']) && $_GET['depdate1'] != '' && ( ( isset($_GET['retdate1']) && $_GET['retdate1'] == '' ) || !isset($_GET['retdate1']) ) && 1 == 1)
            {
                $debug_test = 1;
                
                $return_travel_packages = [];
    
                if ($the_query->have_posts() ) {
                    
                    $is_single_stock_found = array();
                    
                    $adult_count = $_GET['adt'];
                    $child_count = $_GET['chd'];
                    $pax_count = $adult_count + $child_count;
                    
                    
                    
                    foreach ($the_query->posts as $outbound_post) 
                    {
                        
                        //foreach ($the_query_return->posts as $return_post) 
                        {
                            
                            $is_seat_available_based_on_stock = false;
                            
                            $total_amount_for_tour = 0;
                            $total_amount_outbound = 0;
                            $total_amount_return = 0;
                            
                            $is_no_stock_found_ongoing = 0;
                            $is_no_stock_found_return = 0;
                
                            // Calculate the total amount for the outbound trip
                            $trip_id_org = $outbound_post->ID;
                            $get_results_by_dep_date = $wpdb->get_results( "
                            SELECT dates.start_date, dates.pricing_ids 
                            FROM wpk4_wt_dates dates 
                            LEFT JOIN wpk4_wt_excluded_dates_times AS exclude 
                            ON dates.trip_id = exclude.trip_id AND dates.start_date = exclude.start_date 
                            WHERE dates.trip_id = '$trip_id_org' AND date(dates.start_date) > CURRENT_DATE AND dates.start_date LIKE '$dep_date_ymd' 
                            AND exclude.start_date IS NULL
                            "); 
                            
                            $num_rows_ongoing = count($get_results_by_dep_date);
                            if($num_rows_ongoing > 0)
                            {
                                $is_no_stock_found_ongoing = 1;
                            }
                             if(isset($currnt_userlogn) && $currnt_userlogn == 'sriharshans')
                                            {
                                                 //echo '<pre>';
                                                 //print_r($return_travel_packages);
                                                 //echo '</pre>';
                                            }
                            foreach($get_results_by_dep_date as $get_row_by_dep_date)
                            { 
                                $pricing_id_loop = $get_row_by_dep_date->pricing_ids;
                                $start_date_loop = $get_row_by_dep_date->start_date;
                                    $max_pax_loop = 0;
                                    $get_extras_by_pricingid = $wpdb->get_results( "SELECT max_pax FROM wpk4_wt_pricings where id='$pricing_id_loop'"); 
                                    foreach($get_extras_by_pricingid as $get_extra_by_pricingid )
                                    { 
                                        $max_pax_loop = $get_extra_by_pricingid->max_pax;
                                    } 
                                    
                                    $start_date_loop_date = str_replace("-", "_", $start_date_loop);
                                    $meta_checkup = 'wt_booked_pax-'.$pricing_id_loop.'-'.$start_date_loop_date;
                                        
                                        $total_booked_for_trip = 0;
                                        $get_postmeta_stock = $wpdb->get_results( "SELECT meta_value FROM wpk4_postmeta where meta_key LIKE '$meta_checkup%'"); 
                                        foreach($get_postmeta_stock as $get_postmeta_stock_row )
                                        {
                                            $total_booked_for_trip = $get_postmeta_stock_row->meta_value;
                                        }
                                        
                                        $available_seat_for_trip = $max_pax_loop - $total_booked_for_trip;
                                        
                                        //echo $meta_checkup . ' -> ' . $available_seat_for_trip .' > '. $pax_count.'</br>';
                                        if($available_seat_for_trip >= $pax_count) 
                                        {
                                            $is_seat_available_based_on_stock = true;
                                        }
                                            
                                $get_results_by_pricingid = $wpdb->get_results( "
                                SELECT regular_price, sale_price 
                                FROM wpk4_wt_price_category_relation 
                                WHERE pricing_id='$pricing_id_loop' AND pricing_category_id='953'
                                "); 
                
                                foreach($get_results_by_pricingid as $get_row_by_pricingid)
                                { 
                                    $total_amount_for_tour += (float)$get_row_by_pricingid->sale_price;
                                    $total_amount_outbound = $get_row_by_pricingid->sale_price;
                                }
                            }	
                            
                            
                
                            
                            if(isset($_GET['get_flexible_date']) && $_GET['get_flexible_date'] == true)//srihars
                            {
                                
                                // Calculate the total amount for the outbound trip
                                $trip_id_org = $outbound_post->ID;
                                $get_results_by_dep_date = $wpdb->get_results( "
                                SELECT dates.start_date, dates.pricing_ids 
                                FROM wpk4_wt_dates dates 
                                LEFT JOIN wpk4_wt_excluded_dates_times AS exclude 
                                ON dates.trip_id = exclude.trip_id AND dates.start_date = exclude.start_date 
                                WHERE dates.trip_id = '$trip_id_org' AND date(dates.start_date) > CURRENT_DATE AND dates.start_date LIKE '$dep_date_ymd_cropped%' 
                                AND exclude.start_date IS NULL
                                "); 
                                foreach($get_results_by_dep_date as $get_row_by_dep_date)
                                { 
                                    $pricing_id_loop = $get_row_by_dep_date->pricing_ids;
                                    $start_date_loop = $get_row_by_dep_date->start_date;
                                    $max_pax_loop = 0;
                                    $get_extras_by_pricingid = $wpdb->get_results( "SELECT max_pax FROM wpk4_wt_pricings where id='$pricing_id_loop'"); 
                                    foreach($get_extras_by_pricingid as $get_extra_by_pricingid )
                                    { 
                                        $max_pax_loop = $get_extra_by_pricingid->max_pax;
                                    } 
                                    
                                    $start_date_loop_date = str_replace("-", "_", $start_date_loop);
                                    $meta_checkup = 'wt_booked_pax-'.$pricing_id_loop.'-'.$start_date_loop_date;
                                        
                                        $total_booked_for_trip = 0;
                                        $get_postmeta_stock = $wpdb->get_results( "SELECT meta_value FROM wpk4_postmeta where meta_key LIKE '$meta_checkup%'"); 
                                        foreach($get_postmeta_stock as $get_postmeta_stock_row )
                                        {
                                            $total_booked_for_trip = $get_postmeta_stock_row->meta_value;
                                        }
                                        
                                        $available_seat_for_trip = $max_pax_loop - $total_booked_for_trip;
                                        
                                        //echo $meta_checkup . ' -> ' . $available_seat_for_trip .' > '. $pax_count.'</br>';
                                        if($available_seat_for_trip >= $pax_count) 
                                        {
                                            $is_seat_available_based_on_stock = true;
                                        }
                
                                    $get_results_by_pricingid = $wpdb->get_results( "
                                    SELECT regular_price, sale_price 
                                    FROM wpk4_wt_price_category_relation 
                                    WHERE pricing_id='$pricing_id_loop' AND pricing_category_id='953'
                                    "); 
                    
                                    foreach($get_results_by_pricingid as $get_row_by_pricingid)
                                    { 
                                        
                                        $total_amount_for_tour += (float)$get_row_by_pricingid->sale_price;
                                        $total_amount_outbound = $get_row_by_pricingid->sale_price;
                                        
                                        $dep_date_ymd = $get_row_by_dep_date->start_date;
                                        
                                        $is_no_stock_found_ongoing = 1;
                                    }
                                }
                                
                            }
                            
                            $exists = false;
                            foreach ($return_travel_packages as $package) {
                                if ($package['outbound']->ID == $outbound_post->ID) {
                                    $exists = true;
                                    break;
                                }
                            }
                
                            if($total_amount_outbound != 0 && $outbound_post != '' && !$exists && $is_seat_available_based_on_stock )
                            {
                                // Store the trip details along with the total amount
                                $return_travel_packages[] = [
                                    'outbound' => $outbound_post,
                                    'total_amount_outbound' => $total_amount_outbound,
                                    'total_amount_for_tour' => $total_amount_for_tour,
                                    'outbounddate' => $dep_date_ymd,
                                ];
                            }
                            $is_single_stock_found[] = $is_no_stock_found_ongoing;
                        }
                        
                    }
                    
                    if(!in_array("1", $is_single_stock_found) || count($return_travel_packages) === 0)
                            {
                                
                                ?>
                                <style>
                                    /* The Modal (background) */
                                    .modal {
                                      display: none; /* Hidden by default */
                                      position: fixed; /* Stay in place */
                                      z-index: 1; /* Sit on top */
                                      padding-top: 250px; /* Location of the box */
                                      left: 0;
                                      top: 0;
                                      width: 100%; /* Full width */
                                      height: 100%; /* Full height */
                                      overflow: auto; /* Enable scroll if needed */
                                      background-color: rgb(0,0,0); /* Fallback color */
                                      background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
                                    }
                                    
                                    /* Modal Content */
                                    .modal-content {
                                      position: relative;
                                      background-color: #fefefe;
                                      margin: auto;
                                      padding: 0;
                                      border: 1px solid #888;
                                      width: 80%;
                                      box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2),0 6px 20px 0 rgba(0,0,0,0.19);
                                      -webkit-animation-name: animatetop;
                                      -webkit-animation-duration: 0.4s;
                                      animation-name: animatetop;
                                      animation-duration: 0.4s
                                    }
                                    
                                    /* Add Animation */
                                    @-webkit-keyframes animatetop {
                                      from {top:-300px; opacity:0} 
                                      to {top:0; opacity:1}
                                    }
                                    
                                    @keyframes animatetop {
                                      from {top:-300px; opacity:0}
                                      to {top:0; opacity:1}
                                    }
                                    
                                    /* The Close Button */
                                    .close {
                                      color: white;
                                      float: right;
                                      font-size: 28px;
                                      font-weight: bold;
                                    }
                                    
                                    .close:hover,
                                    .close:focus {
                                      color: #000;
                                      text-decoration: none;
                                      cursor: pointer;
                                    }
                                    
                                    .modal-header {
                                      padding: 2px 16px;
                                      background-color: #ffbb00;
                                      color: black;
                                    }
                                    
                                    .modal-header h6
                                    {
                                        margin:20px 0px;
                                    }
                                    
                                    .modal-body {padding: 2px 16px;}
                                    
                                    .modal-footer {
                                      padding: 2px 16px;
                                      background-color: #ffbb00;
                                      color: white;
                                    }
                                </style>
                                <div id="myModald" class="mofdal">
                
                                  <!-- Modal content -->
                                  <div class="modal-content">
                                    <!--<div class="modal-header">
                                      <span class="close">&times;</span>
                                      <h6>GDeals are unavailable</h6>
                                    </div>-->
                                    <div class="modal-body">
                                        </br>
                                        <center>
                                        <p>Sorry, there are no GDeals available for the selected dates.</p>
                                        <p>If your dates are flexible, please click on View Flexible Dates.</p>
                                        <p>For alternative options and booking assistance, Call Us Anytime – We're here 24/7 to help you!</p>
                                        
                                            <button style="background-color:black; color: #ffbb00; width:200px; margin-top:7px; height:30px; padding:1px !important; font-size:12px;" onclick="flexibleDateButton()">View flexible dates</button>
                                            
                                            <a href='tel:1300359463'><button style="background-color:black; color: #ffbb00; width:200px; margin-top:7px; height:30px; padding:1px !important; font-size:12px;">Call Now</button></a>
                                        </center>
                                        </br>
                                    </div>
                                    <!--<div class="modal-footer">
                                    </div>-->
                                  </div>
                                
                                </div>
                                
                                
                                <script type="text/javascript">
                                    var modal = document.getElementById("myModal");
                
                                    // Get the <span> element that closes the modal
                                    var span = document.getElementsByClassName("close")[0];
                                    
                                    // When the user clicks the button, open the modal 
                                      modal.style.display = "block";
                                    
                                    // When the user clicks on <span> (x), close the modal
                                    span.onclick = function() {
                                      modal.style.display = "none";
                                    }
                                    
                                    // When the user clicks anywhere outside of the modal, close it
                                    window.onclick = function(event) {
                                      if (event.target == modal) {
                                        modal.style.display = "none";
                                      }
                                    }
                                </script>
                                <?php
                            }
                
                }
                // Sort the return_travel_packages array by total_amount_for_tour
                usort($return_travel_packages, function($a, $b) {
                    return $a['total_amount_for_tour'] <=> $b['total_amount_for_tour'];
                });
   
                ?>
                <div id="wptravel-archive-wrapper" class="wptravel-archive-wrapper">
                    <?php 
                    $row_going_id = 1;
                    $row_return_id = 1;
                    foreach ($return_travel_packages as $package)
                    {
                        $cart_array = array();
                        $cart_array_combination_ongoing = array();
                        
                        $adult_count = $_GET['adt'];
                        $child_count = $_GET['chd'];
                        $pax_count = $adult_count + $child_count;
                        
                        $outbound_post = $package['outbound'];
                        //$return_post = $package['return'];
                        
                        if(isset($_GET['get_flexible_date']) && $_GET['get_flexible_date'] == true)
                        {
                            $dep_date_ymd = $package['outbounddate'];
                            //$ret_date_ymd = $package['returndate'];
                        }
                            
                        
                        $total_amount_for_tour = 0;
                        
                        // ONEWAY
                        {
                            global $wp_travel_itinerary;
                            $trip_id_org = $outbound_post->ID;
                            $enable_sale = WP_Travel_Helpers_Trips::is_sale_enabled(array('trip_id' => $outbound_post->ID));
                            $group_size  = wptravel_get_group_size($outbound_post->ID);
                            $start_date  = get_post_meta($outbound_post->ID, 'wp_travel_start_date', true);
                            $end_date    = get_post_meta($outbound_post->ID, 'wp_travel_end_date', true);
                            
                            $outbound_trip_link = get_permalink($outbound_post->ID);
            
                            $args  = $args_regular = array('trip_id' => $outbound_post->ID); // phpcs:ignore
            
                            $args_regular['is_regular_price'] = true;
                                
                            $regular_price_loop = 0;
                            $sale_price_loop = 0;
                            $get_results_by_dep_date = $wpdb->get_results( "
                            SELECT dates.start_date, dates.id, dates.pricing_ids FROM wpk4_wt_dates dates LEFT JOIN wpk4_wt_excluded_dates_times AS exclude 
                                                        ON dates.trip_id = exclude.trip_id AND dates.start_date = exclude.start_date 
                                    WHERE dates.trip_id = '$trip_id_org' AND date(dates.start_date) > CURRENT_DATE AND dates.start_date LIKE '$dep_date_ymd'
                                    AND exclude.start_date IS NULL
                            "); 
                            $min_pax = 0;
                            $min_pax = 1;
                            $trip_extras_string = '';
                            foreach($get_results_by_dep_date as $get_row_by_dep_date )
                            { 
                                $pricing_id_loop = $get_row_by_dep_date->pricing_ids;
                                $date_id_loop = $get_row_by_dep_date->id;
                                
                                $get_extras_by_pricingid = $wpdb->get_results( "SELECT min_pax, max_pax, trip_extras FROM wpk4_wt_pricings where id='$pricing_id_loop'"); 
                                foreach($get_extras_by_pricingid as $get_extra_by_pricingid )
                                { 
                                    $trip_extras_string = $get_extra_by_pricingid->trip_extras;
                                    $min_pax = $get_extra_by_pricingid->min_pax;
                                    $max_pax = $get_extra_by_pricingid->max_pax;
                                }
                                
                                $get_results_by_pricingid = $wpdb->get_results( "SELECT regular_price, sale_price FROM wpk4_wt_price_category_relation where pricing_id='$pricing_id_loop' AND pricing_category_id='953'"); 
                                foreach($get_results_by_pricingid as $get_row_by_pricingid )
                                { 
                                    $regular_price_loop = $get_row_by_pricingid->regular_price;
                                    $sale_price_loop = $get_row_by_pricingid->sale_price;
                                }
                            }
                                
                            $available_dates = '';
                            $available_month = '';
                            $loop_counter = 0;
                            $is_break = 0;
                            
                            $available_dates .= '<div class="carousel-container" data-product-carousel>';
                            $available_dates .= '<div class="carousel-arrow left-arrow" data-arrow="left"><i class="fa fa-chevron-left" aria-hidden="true"></i></div>'; // Left arrow
                            $available_dates .= '<div class="dates-carousel">'; // Carousel for the dates
                            
                            if($dep_date_ymd_cropped != '')
                            {
                                $get_results_by_dep_month = $wpdb->get_results( "SELECT dates.start_date, dates.pricing_ids FROM wpk4_wt_dates dates 
                                    LEFT JOIN wpk4_wt_excluded_dates_times AS exclude ON dates.trip_id = exclude.trip_id AND dates.start_date = exclude.start_date
                                    WHERE dates.trip_id = '$trip_id_org' AND date(dates.start_date) > CURRENT_DATE AND dates.start_date LIKE '$dep_date_ymd_cropped%' AND exclude.start_date IS NULL"); 
                                foreach($get_results_by_dep_month as $get_row_by_dep_month )
                                { 
                                    $pricing_id_month_loop = $get_row_by_dep_month->pricing_ids;
                                    $start_date_month_loop = $get_row_by_dep_month->start_date;
                        
                                    $get_results_by_month_pricingid = $wpdb->get_results( "SELECT regular_price, sale_price FROM wpk4_wt_price_category_relation where pricing_id='$pricing_id_month_loop' AND pricing_category_id='953'"); 
                                    foreach($get_results_by_month_pricingid as $get_row_by_month_pricingid )
                                    { 
                                        $regular_price_month_loop = $get_row_by_month_pricingid->regular_price;
                                        $sale_price_month_loop = $get_row_by_month_pricingid->sale_price;
                                        //date('F', strtotime($start_date_month_loop))
                                        $available_month = '<span style="font-size:13px; font-weight:700; padding:5px 6px; background-color:#ffbb00; color:black; margin:0px 5px; border-radius:3px 3px 3px 3px; white-space: nowrap; ">'.date('M', strtotime($start_date_month_loop)).'</span>';
                                        
                                        if($dep_date_ymd == $start_date_month_loop)
                                        {
                                            $available_dates .= '<span id="payment_amount_selector" class="pay_selected" style="width:100px !important; display: inline-block; white-space: nowrap; font-size:13px; padding:5px 6px; border:1px solid #ffbb00; color:black; margin:5px 3px 5px 3px !important; border-radius:3px 3px 3px 3px;">
                                                        <i class="fa fa-calendar-o" style="color:#210301;"></i> <b>'.date('d', strtotime($start_date_month_loop)) . '</b> - $<font style="font-size:14px; font-weight:600;">' . $sale_price_month_loop.'</font></span>';
                                        }
                                        else
                                        {
                                            $available_dates .= '<span id="payment_amount_selector" class="pay_not_selected" onclick="updateURLWithOnGoingDate(\'' . $start_date_month_loop . '\')"  style="cursor: pointer; width:100px !important; display: inline-block; white-space: nowrap; font-size:13px; padding:5px 6px; border:1px solid #ffbb00; color:black; margin:5px 3px 5px 3px !important; border-radius:3px 3px 3px 3px;">
                                                        <i class="fa fa-calendar-o" style="color:#210301;"></i> <b>'.date('d', strtotime($start_date_month_loop)) . '</b> - $<font style="font-size:14px; font-weight:600;">' . $sale_price_month_loop.'</font></span>';
                                        }
                                        
                                        $loop_counter++;
                                    }
                                    
                                    if($is_break == 1)
                                    {
                                        break;
                                    }
                                }
                            }
                            
                            if($ret_date_ymd_cropped != '')
                            {
                                $loop_counter = 0;
                                $is_break = 0;
                                $get_results_by_dep_month = $wpdb->get_results( "SELECT dates.start_date, dates.pricing_ids FROM wpk4_wt_dates dates LEFT JOIN wpk4_wt_excluded_dates_times AS exclude 
                                                        ON dates.trip_id = exclude.trip_id AND dates.start_date = exclude.start_date WHERE dates.trip_id = '$trip_id_org' AND date(dates.start_date) > CURRENT_DATE AND dates.start_date LIKE '$ret_date_ymd_cropped%' 
                                                        AND exclude.start_date IS NULL "); 
                                foreach($get_results_by_dep_month as $get_row_by_dep_month )
                                { 
                                    $pricing_id_month_loop = $get_row_by_dep_month->pricing_ids;
                                    $start_date_month_loop = $get_row_by_dep_month->start_date;
                        
                                    $get_results_by_month_pricingid = $wpdb->get_results( "SELECT regular_price, sale_price FROM wpk4_wt_price_category_relation where pricing_id='$pricing_id_month_loop' AND pricing_category_id='953'"); 
                                    
                                    foreach($get_results_by_month_pricingid as $get_row_by_month_pricingid )
                                    { 
                                        $regular_price_month_loop = $get_row_by_month_pricingid->regular_price;
                                        $sale_price_month_loop = $get_row_by_month_pricingid->sale_price;
                        
                                        $available_month = '<span style="font-size:13px; font-weight:700; padding:5px 6px; background-color:#ffbb00; color:black; margin:0px 5px; border-radius:3px 3px 3px 3px; white-space: nowrap; ">'.date('M', strtotime($start_date_month_loop)).'</span>';
                                        $available_dates .= '<span style="cursor: pointer; width:100px !important; display: inline-block; white-space: nowrap; font-size:13px; padding:5px 6px; border:1px solid #ffbb00; color:black; margin:5px 3px 5px 3px !important; border-radius:3px 3px 3px 3px;">
                                                        <i class="fa fa-calendar-o" style="color:#210301;"></i> <b>'.date('d', strtotime($start_date_month_loop)) . '</b> - $<font style="font-size:14px; font-weight:600;">' . $sale_price_month_loop.'</font></span>';
                                        $loop_counter++;
                                    }
                                    
                                    if($is_break == 1)
                                    {
                                        break;
                                    }
                                }
                            }
                            
                            $available_dates .= '</div>'; // Close .dates-carousel
                            $available_dates .= '<div class="carousel-arrow right-arrow" data-arrow="right"><i class="fa fa-chevron-right" aria-hidden="true"></i></div>'; // Right arrow
                            $available_dates .= '</div>'; // Close .carousel-container
                        
                            $trip_price = WP_Travel_Helpers_Pricings::get_price($args);
                            $regular_price = WP_Travel_Helpers_Pricings::get_price($args_regular);
                        
                            $locations = get_the_terms($outbound_post->ID, 'travel_locations');
                            $trip_locations = get_the_terms($outbound_post->ID, 'travel_locations');
                            $location_name = '';
                            $location_link = '';
                            if ($locations && is_array($locations)) 
                            {
                                $first_location = array_shift($locations);
                                $location_name  = $first_location->name;
                                $location_link  = get_term_link($first_location->term_id, 'travel_locations');
                            }
                            
                            $is_trip_for_live = '';
                            $is_trip_for_live = get_post_meta($outbound_post->ID, '_yoast_wpseo_primary_status', true);
                            
                            $wp_travel_trip_itinerary_data_org = get_post_meta($trip_id_org, 'wp_travel_trip_itinerary_data', true);
                            
                            $start_day = strtotime($dep_date_ymd);
                            $current_day = strtotime(date('Y-m-d'));
                            
                            $travel_vs_current_date_difference = ( $start_day - $current_day ) / (60 * 60 * 24);

                            
                            // fetch product details
                            if (isset($wp_travel_trip_itinerary_data_org) && !empty($wp_travel_trip_itinerary_data_org))
                            {
                                $wptravel_index = 1;
                                $itinerary_location_array = array();
                                $itinerary_time_array = array();
                                $itinerary_flight_array = array();
                                $itinerary_date_array = array();
                                $itinerary_datedecider_array = array();
                                $itinerary_array_counter = 0;
                                $itinerary_counter = 0;
                                
                                foreach ($wp_travel_trip_itinerary_data_org as $wptravel_itinerary)
                                {
                                    $wptravel_time_format = get_option('time_format');
                                    $wptravel_itinerary_label = '';
                                    $wptravel_itinerary_title = '';
                                    $wptravel_itinerary_desc  = '';
                                    $wptravel_itinerary_date  = '';
                                    $wptravel_itinerary_time  = '';
                                    $itinerary_counter = 1;
                                    $is_itinerary_available = 1;
                                    $wptravel_itinerary_label = stripslashes($wptravel_itinerary['label']);
                        
                                    $wptravel_itinerary_title = stripslashes($wptravel_itinerary['title']);
                        
                                    $wptravel_itinerary_desc = stripslashes($wptravel_itinerary['desc']);
                        
                                    $wptravel_itinerary_date = wptravel_format_date($wptravel_itinerary['date']);
                        
                                    $wptravel_itinerary_time = stripslashes($wptravel_itinerary['time']);
                                    $wptravel_itinerary_time = date($wptravel_time_format, strtotime($wptravel_itinerary_time));
                        
                                    $itinerary_location_array[$itinerary_array_counter] = $wptravel_itinerary_label; // destination
                                    $itinerary_time_array[$itinerary_array_counter] = $wptravel_itinerary_time; // flight time
                                    $itinerary_flight_array[$itinerary_array_counter] = $wptravel_itinerary_title; // flight number
                                    //$itinerary_date_array[$itinerary_array_counter] = $traveldate_fxed; // flight date
                                    $itinerary_datedecider_array[$itinerary_array_counter] = strip_tags($wptravel_itinerary_desc); // arrival or departure define
                        
                                    $wptravel_index++;
                                    $itinerary_array_counter++;
                                }
                            }
                        
                            $wptravel_travel_outline = get_post_meta($trip_id_org, 'wp_travel_outline', true );
                            $wp_travel_outline_dom = new DOMDocument();
                            //$wp_travel_outline_dom->loadHTML($wptravel_travel_outline);
                            if (!empty($wptravel_travel_outline)) {
                                $wp_travel_outline_dom->loadHTML($wptravel_travel_outline);
                            } else {
                                $wp_travel_outline_dom->loadHTML("<div></div>");
                            }
                            $journey_duration_ele = $wp_travel_outline_dom->getElementsByTagName('p');
                        
                            $total_duration = "";
                            
                            foreach($journey_duration_ele as $node) 
                            {
                                $journery_dur = explode(":", $node->textContent)[0];
                                if(strtolower($journery_dur) == "journey duration")
                                {
                                    $total_duration = explode(":", $node->textContent)[1];
                                }
                            }
                        
                            $trip_wp_title = get_post_field('post_title', $trip_id_org);
                            $trip_title_arr = explode(" ", $trip_wp_title);
                        
                            $has_complimentary_lounge = false;
                            for($i = 0; $i < count($trip_title_arr); $i++)
                            {
                                if(str_contains(strtolower($trip_title_arr[$i]),'complimentary'))
                                {
                                    $has_complimentary_lounge = true;
                                }
                            }
                            ?>
                            <!-- Start of GDeals flight view -->
                            <div class="card" style="margin-bottom: 20px !important; ">
                                <div class="dealwrapper yellow">
                                    <div class="ribbon-wrapper">
                                        <div class="ribbon-tag">GDeals</div>
                                    </div>
                                </div>
                                <div class="card-content">
                                    <div class="deal">
                                        <div class="lockinprice_top">
                                            &nbsp;
                                        </div>
                                        <div class="src-dts">
                                            <h2 class="m-0" style="text-align:left;">
                                                <?php
                                                    // fetch airline images
                                                    $airline_arr = array("virgin australia" ,"thai","singapore","qatar" ,"srilankan","airindia","scoot","emirates","sabre",
                                                    "jetstar","cathay","malaysia","qantas","eithad");
                                                    $airline_obj = array(
                                                        "virgin australia" => "img-virginaus",
                                                        "thai" => "img-thai",
                                                        "singapore" => "img-singapore",
                                                        "qatar" => "img-qatar",
                                                        "srilankan" => "img-srilankan",
                                                        "airindia" => "img-airindia",
                                                        "scoot" => "img-scoot",
                                                        "emirates" => "img-emirates",
                                                        "sabre" => "img-sabre",
                                                        "jetstar" => "img-jetstar",
                                                        "cathay" => "img-cathay",
                                                        "malaysia" => "img-malaysia",
                                                        "qantas" => "img-qantas",
                                                        "eithad" => "img-eithad",
                                                        "default" => "img-default"
                                                    );
                                                    $trip_wp_title = get_post_field('post_title', $trip_id_org);
                                                    $array_source = explode(" ", $trip_wp_title);
                                                    $airline_name = '';
                                                    for($i = 0; $i < count($array_source); $i++){
                                                        $val = strtolower(preg_replace('/-/','',$array_source[$i]));
                                                        if(in_array($val, $airline_arr)){
                                                            $airline_name = $val;
                                                            break;
                                                        }
                                                    }
                            
                                                    if($airline_name == ''){
                                                        $airline_name = "default";
                                                    }
                            
                                                    $img_href ="https://".$_SERVER['SERVER_NAME']."/wp-content/uploads/2023/09/".$airline_obj[$airline_name].".png";
                                                    //echo '<p style="font-size:12px;letter-spacing:1px;">'.$trip_id_org . ' ' . $dep_date_ymd.'</p>';
                                                    ?>
                                                    <a href="#"><img src=<?php echo $img_href ?> alt=<?php echo $airline_name ?> /></a>
                                            </h2>
                                            <div class="grid-layout-4">
                                                <div class="src-dts-info">
                                                    <p class="destination">
                                                        <?php 
                                                        $src_title_depart=$itinerary_location_array[0];
                                                        $offset = strpos($src_title_depart,"(");
                                                        echo substr($src_title_depart, $offset+1, 3);   
                                                        ?>
                                                    </p>
                                                    <span class="destination-content">
                                                        <?php
                                                        $trip_wp_title = get_post_field('post_title', $trip_id_org);
                                                        $array_source = explode(" ", $trip_wp_title);
                                                        echo $array_source[0];
                                                        ?>
                                                    </span>
                                                    <span class="destination-content display-inlne">
                                                        <?php
                                                        echo "(" . $itinerary_time_array[0] . ")";
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="journey-time">
                                                    <i class='fa fa-arrow-right'></i>
                                                    <span class="destination-content">
                                                        <?php echo $total_duration; ?>
                                                    </span>
                                                </div>
                                                <div class="src-dts-info">
                                                    <p class="destination">
                                                        <?php 
                                                        $src_title_arr=$itinerary_location_array[count($itinerary_location_array)-1];
                                                        $offset = strpos($src_title_arr,"(");
                                                        echo substr($src_title_arr, $offset+1, 3); 
                                                        ?>
                                                    </p>
                                                    <span class="destination-content">
                                                        <?php
                                                        $trip_wp_title = get_post_field('post_title', $outbound_post->ID);
                                                        $array_destination = explode(" ", $trip_wp_title);
                                                        echo $array_destination[2];
                                                        ?>
                                                    </span>
                                                    <span class="destination-content display-inlne">
                                                        <?php
                                                        echo "(" . $itinerary_time_array[count($itinerary_time_array) - 1] . ")";
                                                        ?>
                                                    </span>
                                                </div>
                                            </div>
                            
                                            <?php if ($has_complimentary_lounge) : ?>
                                                <div>
                                                    <img class="complimentary-lounge-img" src=<?php echo "https://".$_SERVER['SERVER_NAME']."/wp-content/uploads/2023/09/img-lounge.png"?> alt="Complimentary Lounge" />
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="extra">
                                            <?php
                                                    $wptravel_travel_outline = get_post_meta( $trip_id_org, 'wp_travel_outline', true );
                                                	$adult_baggage_pattern = '/Adult:\s*(\d+)\s*Kg/i';
                                                    // Use preg_match to capture the value
                                                    if (preg_match($adult_baggage_pattern, $wptravel_travel_outline, $adult_matches)) {
                                                        $adult_kg_value = $adult_matches[1];
                                                    } else {
                                                        $adult_kg_value = '';
                                                    }
                                                    
                                                    $child_baggage_pattern = '/Child:\s*(\d+)\s*Kg/i';
                                                    // Use preg_match to capture the value
                                                    if (preg_match($child_baggage_pattern, $wptravel_travel_outline, $child_matches)) {
                                                        $child_kg_value = $child_matches[1];
                                                    } else {
                                                        $child_kg_value = '';
                                                    }
                                                    
                                                    if ($adult_kg_value != '' && $child_kg_value != '' ) 
                                                    {
                                                        $lowest_kg_value = max($adult_kg_value, $child_kg_value);
                                                        $final_baggage = '<i class="fa fa-suitcase" aria-hidden="true"></i> '.$lowest_kg_value.'kg';
                                                    } 
                                                    else 
                                                    {
                                                        $lowest_kg_value = '';
                                                        $final_baggage = "";
                                                    }
                                                    ?>
                                                    
                                            
                                            <div class="month">
                                                <?php
                                                if( $sale_price_loop == 0 && $available_dates != '')
                                                {
                                                    echo '<table style="border:0; margin:0px; padding:0px;"><tr style="border:0; margin:0px; padding:0px;"><td style="border:0; margin:0px; padding:0px;">'.$available_month .'</td><td style="border:0; margin:0px; padding:0px;">'. $available_dates.'</td></tr></table>';
                                                }
                                                else
                                                {
                                                    echo '<table style="border:0; margin:0px; padding:0px;"><tr style="border:0; margin:0px; padding:0px;"><td style="border:0; margin:0px; padding:0px;">'.$available_month .'</td><td style="border:0; margin:0px; padding:0px;">'. $available_dates.'</td></tr></table>';
                                                    ?>
                                                    <!--
                                                    <i class="fa fa-calendar" style="color: #8a8a8a;">
                                                        <span style="font-family: Poppins, sans-serif;font-weight: 400;">
                                                            <?php
                                                        $trip_wp_title = get_post_field('post_title', $trip_id_org);
                                                        $array_source = explode(" ", $trip_wp_title);
                                                        echo $array_source[count($array_source)-1];
                                                    ?>
                                                        </span>
                                                    </i>-->
                                                <?php
                                                }
                                                ?>
                                            </div>
                                            
                                                <?php 
                                                if(isset($airline_obj[$airline_name]) && $airline_obj[$airline_name] == 'img-cathay')
                                                {
                                                    ?>
                                                    <button class="baggage_button" style="background-color:black; color: #ffbb00; border-radius: 5px; width:100px; margin-top:7px; height:30px; padding:1px !important; font-size:12px;">
                                                    <?php echo $final_baggage;  ?>
                                                    </button>
                                                    <?php
                                                }
                                                else
                                                {
                                                    ?>
                                                    <button class="baggage_button" style="background-color:black; color: #ffbb00; border-radius: 5px; width:100px; margin-top:7px; height:30px; padding:1px !important; font-size:12px;">
                                                    <?php echo $final_baggage;  ?>
                                                    </button>
                                                    <?php
                                                }
                                                ?>
                                            
                                            <?php if (1 == 2 && $has_complimentary_lounge) : ?>
                                            <div class="complimentary-lounge-txt">
                                                <i class="fa fa-coffee" style="color: #8a8a8a;">
                                                    <span style="font-family: Poppins, sans-serif;font-weight: 400;">
                                                        Complimentary Lounge
                                                    </span>
                                                </i>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="complimentary-lounge-txt">
                                                <?php
                                                echo '<div class="itinerary_div" style="display:none;" id="'.$row_going_id.'_trip_itinerary">';
                                                $is_itinerary = true;
                                                if($is_itinerary)
                                                {
                                                	$traveldate_fxed = $dep_date_ymd;
                                                	
                                                	$wptravel_itineraries_r = get_post_meta( $trip_id_org, 'wp_travel_trip_itinerary_data', true );
                                                	$trip_wp_title = get_post_field( 'post_title', $trip_id_org );
                                                	$wptravel_travel_outline = get_post_meta( $trip_id_org, 'wp_travel_outline', true );
                                                	$productid_for_wptravel_product = $trip_id_org;
                                                	
                                                	if ( isset( $wptravel_itineraries_r ) && ! empty( $wptravel_itineraries_r ) ) : 
                                                		$wptravel_index = 1;
                                                		$itinerary_location_array = array();
                                                		$itinerary_time_array = array();
                                                		$itinerary_flight_array = array();
                                                		$itinerary_date_array = array();
                                                		$itinerary_datedecider_array = array();
                                                		$itinerary_array_counter = 0;
                                                		$itinerary_counter=0;	
                                                		foreach ( $wptravel_itineraries_r as $wptravel_itinerary ) : 
                                                			$wptravel_time_format = get_option( 'time_format' );
                                                			$wptravel_itinerary_label = '';
                                                			$wptravel_itinerary_title = '';
                                                			$wptravel_itinerary_desc  = '';
                                                			$wptravel_itinerary_date  = '';
                                                			$wptravel_itinerary_time  = '';
                                                			$itinerary_counter = 1;
                                                			$is_itinerary_available = 1;
                                                				$wptravel_itinerary_label = stripslashes( $wptravel_itinerary['label'] );
                                                			
                                                				$wptravel_itinerary_title = stripslashes( $wptravel_itinerary['title'] );
                                                			
                                                				$wptravel_itinerary_desc = stripslashes( $wptravel_itinerary['desc'] );
                                                			
                                                				$wptravel_itinerary_date = wptravel_format_date( $wptravel_itinerary['date'] );
                                                			
                                                				$wptravel_itinerary_time = stripslashes( $wptravel_itinerary['time'] );
                                                				$wptravel_itinerary_time = date( $wptravel_time_format, strtotime( $wptravel_itinerary_time ) ); 
                                                		    $itinerary_location_array[$itinerary_array_counter] = $wptravel_itinerary_label; // destination
                                                			$itinerary_time_array[$itinerary_array_counter] = $wptravel_itinerary_time; // flight time
                                                			$itinerary_flight_array[$itinerary_array_counter] = $wptravel_itinerary_title; // flight number
                                                			$itinerary_date_array[$itinerary_array_counter] = $traveldate_fxed; // flight date
                                                			$itinerary_datedecider_array[$itinerary_array_counter] = strip_tags($wptravel_itinerary_desc); // arrival or departure define
                                                					
                                                			$wptravel_index++;
                                                			$itinerary_array_counter++;
                                                			
                                                		endforeach;
                                                	endif; 
                                                	// DEFINE EXTRA DAYS
                                                    echo '</br></br>';
                                                    
                                                    $wptravel_travel_outline = get_post_meta( $productid_for_wptravel_product, 'wp_travel_outline', true );
                                                    //$pattern_to_remove_itinerary_table = '/<table class="tg">(.*?)<\/table>/s';
                                                    //$wptravel_travel_outline = preg_replace($pattern_to_remove_itinerary_table, '', $wptravel_travel_outline);
                                                    $pattern_to_remove_itinerary_table = '/Want to book(.*?)\[\/embed\]/s';
                                                    $wptravel_travel_outline = preg_replace($pattern_to_remove_itinerary_table, '', $wptravel_travel_outline);
    
                                                	//$itinerary_vals .= $wptravel_travel_outline;
                                                	echo $wptravel_travel_outline;
                                                	
                                                	$departure_date_plus_one = date("d/m/Y", strtotime("1 day", strtotime($traveldate_fxed))); 
                                                	$departure_date_plus_two = date("d/m/Y", strtotime("2 day", strtotime($traveldate_fxed)));
                                                	$departure_date_plus_three = date("d/m/Y", strtotime("3 day", strtotime($traveldate_fxed)));
                                                	$departure_date_plus_four = date("d/m/Y", strtotime("4 day", strtotime($traveldate_fxed)));
                                                    if ( is_array( $itinerary_location_array ) ) {
                                                	$length_aray = count($itinerary_location_array);
                                                	$itinerary_vals = '<center><table class="m_-8969220568537220410 tripitinerary wp-travel-table-content trip_'.$trip_id_org.'" cellpadding="0" cellspacing="0" style="width:100%; text-align:left; border: 1px solid #e1e1e; border-collapse: collapse; margin:10px 0px 10px 0px; font-size:14px;">
                                                	<thead>
                                                	  <tr style="background: #f0f0f0; border:none;">
                                                		 <th style="width:20%">From</th>
                                                		 <th style="width:20%">To</th>
                                                		 <th style="width:20%">Flight</th>
                                                		 <th style="width:20%">Departure</th>
                                                		 <th style="width:20%">Arrival</th>
                                                	  </tr>
                                                	</thead>
                                                	<tbody>';
                                                            }
                                                            else {
                                                    // Handle the case when $itinerary_location_array is null
                                                    $itinerary_vals .= '<p>No itinerary locations found.</p>';
                                                    }
                                                	// SECTION TO DIVIDE WAITING, SELF TRANSFER AND FLIGHT INFO
                                                	$is_printed_destination = '';
                                                	for ($i = 0; $i < $length_aray; $i++) {
                                                		if($itinerary_location_array[$i] == 'WAIT')
                                                		{
                                                			$is_printed_destination = '';
                                                		}
                                                		else if($itinerary_location_array[$i] == 'SELF-TRANSFER')
                                                		{
                                                			$is_printed_destination = '';
                                                		}
                                                		else
                                                		{
                                                			if ($is_printed_destination == '') 
                                                            {
                                                                 
                                                                $date1 = date("Y-m-d", strtotime($itinerary_datedecider_array[$i]))." ".date("H:i:s", strtotime($itinerary_time_array[$i]));
                                                                if(isset($itinerary_datedecider_array[$i+1]) && isset($itinerary_time_array[$i+1]))
                                                                {
                                                                    $date2 = date("Y-m-d", strtotime($itinerary_datedecider_array[$i+1]))." ".date("H:i:s", strtotime($itinerary_time_array[$i+1]));
                                                                    $dateDiff = intval((strtotime($date2) - strtotime($date1)) / 60);
                                                                }
                                                                else
                                                                {
                                                                    $dateDiff = intval((strtotime($date1) - strtotime($date1)) / 60);
                                                                }
                                                                
                                                                $hours = intval($dateDiff / 60); 
                                                                $minutes = $dateDiff % 60;
                                                                
                                                                $time_duration = $hours.":".$minutes;
                                                                $airline_from_itinerary = substr($itinerary_flight_array[$i],0,2);
                                                    
                                                				$itinerary_vals .= "<tr>";
                                                				$itinerary_vals .= '<td style="width:20%">'.$itinerary_location_array[$i].'</td>';
                                                				if(isset($itinerary_location_array[$i+1])) { 
                                                				    $itinerary_vals .= '<td style="width:20%">'.$itinerary_location_array[$i+1].'</td>';
                                                				}
                                                				$itinerary_vals .= '<td style="width:20%">'.$itinerary_flight_array[$i].'</td>';
                                                				$itinerary_vals .= '<td style="width:20%">'.$itinerary_time_array[$i].'</br>'.$itinerary_datedecider_array[$i].'</td>';
                                                				if(isset($itinerary_time_array[$i+1]) && isset($itinerary_datedecider_array[$i+1])) { 
                                                				    $itinerary_vals .= '<td style="width:20%">'.$itinerary_time_array[$i+1].'</br>'.$itinerary_datedecider_array[$i+1].'</td>';
                                                				}
                                                				$itinerary_vals .= "</tr>";
                                                				$empty = '';
                                                				$itinerary_vals .= "<tr style='border:none;'>";
                                                				$itinerary_vals .= '<td colspan="2" style="border:none;">   Class: Economy
                                                				           </td>';
                                                				$itinerary_vals .= '<td colspan="2" style="border:none;">Operated by: '.$airline_from_itinerary.'</td>';
                                                				$itinerary_vals .= '<td style="border:none;"></td>';
                                                				$itinerary_vals .= "</tr>";
                                                				
                                                				$is_printed_destination = 'yes';
                                                            }
                                                            else
                                                            {
                                                                $is_printed_destination = '';
                                                            }
                                                		}
                                                	}
                                                	$itinerary_vals .= "</tbody></table></center>";
                                                	
                                                	// REPLACE ALL THE FIXED WORDS AS DEPARTURE AND ARRIVAL ON BACKEND
                                                	$itinerary_vals = str_replace("Departure Date", '' ,$itinerary_vals);
                                                	$itinerary_vals = str_replace("Arrival Date", '' ,$itinerary_vals);
                                                	
                                                	$itinerary_vals = str_replace("Selected Date +4", $departure_date_plus_four ,$itinerary_vals);
                                                	$itinerary_vals = str_replace("Selected Date+4", $departure_date_plus_four ,$itinerary_vals);
                                                	
                                                	$itinerary_vals = str_replace("Selected Date +3", $departure_date_plus_three ,$itinerary_vals);
                                                	$itinerary_vals = str_replace("Selected Date+3", $departure_date_plus_three ,$itinerary_vals);
                                                	
                                                	$itinerary_vals = str_replace("Selected Date +2", $departure_date_plus_two ,$itinerary_vals);
                                                	$itinerary_vals = str_replace("Selected Date+2", $departure_date_plus_two ,$itinerary_vals);
                                                	
                                                	$itinerary_vals = str_replace("Selected Date+1", $departure_date_plus_one ,$itinerary_vals);
                                                	$itinerary_vals = str_replace("Selected Date +1", $departure_date_plus_one ,$itinerary_vals);
                                                	
                                                	$traveldate_fxed_1 = date("d/m/Y", strtotime($traveldate_fxed)); 
                                                	
                                                	
                                                	$itinerary_vals = str_replace("Selected Date", $traveldate_fxed_1 ,$itinerary_vals);
                                                	
                                                	$itinerary_vals = str_replace("Selected Date", $traveldate_fxed_1 ,$itinerary_vals);
                                                	
                                                	$trip_code_post = get_post_meta($trip_id_org, 'wp_travel_trip_code', true);
	                                                $itinerary_details = getGDealFlightItinerary($trip_code_post, $dep_date_ymd);
	                                                if($itinerary_details != '')
	                                                {
                                                	    echo $itinerary_details;
	                                                }
	                                                else
	                                                {
	                                                    echo $itinerary_vals;
	                                                }
                                                	
                                                	$order_id_for_itinerary = '42342434';
                                                	$wptravel_product_information_ordered = get_post_meta( $order_id_for_itinerary, 'order_items_data', true ); // get order product info for trip extras
                                                    if (is_array($wptravel_product_information_ordered) || is_object($wptravel_product_information_ordered)) 
                                                    {
                                                        foreach ($wptravel_product_information_ordered as $key => $value) {
                                                            // Check if the 'trip_extras' key and 'id' key exist
                                                            if (isset($value['trip_extras']['id'])) {
                                                                // Extract "trip_extras" IDs
                                                                $tripExtrasIds = $value['trip_extras']['id'];
                                                                echo '<h6>Additional services</h6>';
                                                                // Print or use the values as needed
                                                                foreach ($tripExtrasIds as $value) {
                                                                    echo '<li>'.get_the_title( $value ).'</li>';
                                                                }
                                                            } else {
                                                                echo "No additional services available.";
                                                            }
                                                        }
                                                	}
                                                	echo '</br>';
                                                }
                                                echo '</div>';
                                                ?>
                                            </div>
                                    </div>
                                    
                                    <div class="price_tag_main">
                                        <div class="price_column_1"><!--
                                            <div class="offer-price">
                                                <?php apply_filters('wp_trave_archives_page_trip_save_offer', wptravel_save_offer($outbound_post->ID), $outbound_post->ID); ?>
                                                <?php if ($sale_price_loop != 0)
                                                {
                                                    ?>
                                                    <label class="discounted-price">
                                                        <?php
                                                        echo apply_filters('wp_travel_archives_page_trip_price', wptravel_get_formated_price_currency($sale_price_loop), $outbound_post->ID); //phpcs:ignore 
                                                        if($is_discount_on == 1 && $discount_percentage != '')
                                                        {
                                                            //$sale_price_loop = $sale_price_loop - ($sale_price_loop * 0.10);
                                                        }
                                                        $individual_amount_for_tour = (float)$sale_price_loop;
                                                        $total_amount_for_tour += (float)$sale_price_loop;
                                                        ?>
                                                    </label>
                                                    <?php
                                                }
                                                else
                                                {
                                                    ?>
                                                    <label class="discounted-price">
                                                    <?php
                                                       echo apply_filters('wp_travel_archives_page_trip_price', wptravel_get_formated_price_currency($trip_price), $outbound_post->ID); //phpcs:ignore 
                                                       $is_monthly_stock_note_to_show = 1;
                                                       if($is_discount_on == 1 && $discount_percentage != '')
                                                        {
                                                            //$trip_price = $trip_price - ($trip_price * 0.10);
                                                        }
                                                       $individual_amount_for_tour = (float)$trip_price;
                                                       $total_amount_for_tour += (float)$trip_price;
                                                    ?>
                                                    </label>
                                                    <?php
                                                }
                                                ?>
                                            </div>-->
                                            <?php
                                            $total_booking_charge = number_format((float)$total_amount_for_tour, 2, '.', '');
                                            $total_booking_charge2 = number_format((float)$total_amount_for_tour, 0, '.', '');
                                            
                                            $airline_arr = array("virgin australia" ,"thai","singapore","qatar" ,"srilanka","airindia","scoot","emirates","sabre",
                                                    "jetstar","cathay","malaysia","qantas","eithad");
                                                    $airline_obj = array(
                                                        "virgin australia" => "VA",
                                                        "thai" => "TG",
                                                        "singapore" => "SQ",
                                                        "qatar" => "QR",
                                                        "srilanka" => "UL",
                                                        "airindia" => "AI",
                                                        "scoot" => "TR",
                                                        "emirates" => "EK",
                                                        "jetstar" => "JQ",
                                                        "cathay" => "CX",
                                                        "malaysia" => "MH",
                                                        "qantas" => "QF",
                                                        "eithad" => "EY"
                                                    );
                                                    $trip_wp_title = get_post_field('post_title', $trip_id_org);
                                                    $array_source = explode(" ", $trip_wp_title);
                                                    $airline_name = '';
                                                    for($i = 0; $i < count($array_source); $i++){
                                                        $val = strtolower(preg_replace('/-/','',$array_source[$i]));
                                                        if(in_array($val, $airline_arr)){
                                                            $airline_name = $val;
                                                            break;
                                                        }
                                                    }
                            
                                                    //$airline_code = $airline_obj[$airline_name];
                                                    if (!empty($airline_name) && isset($airline_obj[$airline_name])) {
                                                        $airline_code = $airline_obj[$airline_name];
                                                    } else {
                                                        $airline_code = '';
                                                        error_log("Undefined array key: " . $airline_name);
                                                    }

                                                    
                                            ?>
                                            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                                            <script>
                                            $(document).ready(function () {
                                                $('.package').each(function (index, element) {
                                                    // Get data attributes for this package
                                                    const packageElement = $(element);
                                                    const gdealsPrice = <?php echo $total_booking_charge2; ?>;
                                                    const from = packageElement.data('from');
                                                    const to = packageElement.data('to');
                                                    const triptype = packageElement.data('type');
                                                    const date = packageElement.data('date');
                                                    const airline = packageElement.data('airline');
                                                    const priceElementId = `#ypsilon-price-${index + 1}`;
                                            
                                                    // Make the AJAX call for this package
                                                    $.ajax({
                                                        url: '/wp-content/themes/twentytwenty/templates/tpl_check_ypsilon_fare_ajax.php',
                                                        method: 'POST',
                                                        data: { from, to, date, airline, triptype },
                                                        dataType: 'json',
                                                        success: function (response) {
                                                            console.log(`Response for package ${index + 1}:`, response);
                                                            if (response.status === 'success' && response.total_amount) 
                                                            {
                                                                if(gdealsPrice < response.total_amount)
                                                                {
                                                                    const ypsilonPriceText = `
                                                                    <div class="offer-price ypsilon_pricing_box" 
                                                                         data-message="This price represents the current market rate for the same route with the same airline if not booked through Gaura Travel. Prices are dynamic and may vary across other websites or travel agencies. \nLuggage included: ${response.baggage}">
                                                                        <label class="discounted-price" 
                                                                               style="font-size:20px; text-decoration: line-through; color: gray; cursor: pointer;">
                                                                            $${response.total_amount}
                                                                            <i class="fa fa-info-circle" style="vertical-align: super; font-size:11px;"></i>
                                                                        </label>
                                                                    </div>
                                                                `;
                                                                $(priceElementId).html(ypsilonPriceText);
                                                                }
                                                            }
                                                        },
                                                        error: function (xhr, status, error) {
                                                            console.error(`Error fetching price for package ${index + 1}:`, error);
                                            console.error("Status:", status);
                                            console.error("Response Text:", xhr.responseText);
                                            $(priceElementId).text("Error fetching price.");
                                                        }
                                                    });
                                                });
                                            });
                                            </script>
                                            <?php

                                            if(isset($_GET['depapt1']) && isset($_GET['depapt1']) && $dep_date_ymd && $airline_code != '')
                                            {
                                                ?>
                                                <div class="package" data-from="<?php echo $_GET["depapt1"]; ?>" data-to="<?php echo $_GET["dstapt1"]; ?>" data-date="<?php echo $dep_date_ymd; ?>" data-type="oneway" data-airline="<?php echo $airline_code; ?>"> 
                                                    <div id="ypsilon-price-<?php echo $row_going_id; ?>"></div>
                                                </div>
                                                <?php
                                            }

                                            if($lowest_kg_value != '')
                                            {
                                                $lowest_kg_value = ''.$lowest_kg_value . 'kg';
                                            }
                                            
                                            echo '<div class="offer-price gdeals_price_box" data-message="This price is Gaura Travel’s exclusive GDeals fare, which includes a '.$lowest_kg_value.' baggage allowance, onboard meals, and inflight entertainment with this award-winning airline."><label class="discounted-price">$'.$total_booking_charge2.'<i class="fa fa-info-circle" style="vertical-align: super; font-size:11px;"></i></label>
                                            </div>';
                                            
                                            
                                            $is_slicepay_eligible = is_slicePayEligible($total_amount_for_tour, $dep_date_ymd);
                                            //echo $travel_vs_current_date_difference . ' ' . $current_day .' ' .  $start_day;
                                            if($travel_vs_current_date_difference > 31 && $is_slicepay_eligible['is_eligible'] == 1)
                                            {
                                                echo 'OR';
                                                echo '<div class="offer-price" style="margin-top:10px;"><label class="discounted-price">$' . $is_slicepay_eligible['weekly_installment'] . '/wk</label></div>';
                                            }
                                            
                                            ?>
                                            
                                        </div>
                                        <div class="price_column_2">
                                            <!--<button class="book-now" id="return_add_booking"><a href="<?php echo $outbound_trip_link; ?>">View</a></button>-->
                                            <?php
                                            $trip_extras_ids = explode(',', $trip_extras_string);
        
                                            // Create an array of quantities, all set to 1
                                            $trip_extras_qty = array_fill(0, count($trip_extras_ids), 1);
                    
                                            
                                            $individual_amount_for_tour_pax = $individual_amount_for_tour * $pax_count ;
                                            
                                            if( $is_coupon_on == 1 && $is_count_amount != '' )
                                            {
                                                $individual_amount_for_tour_pax = (float)$individual_amount_for_tour_pax - (float)$is_count_amount;
                                            }
    
                                            $individual_total_partial_amount = ($individual_amount_for_tour_pax * 0.05) ;
                                            $individual_partial_amount = ($individual_amount_for_tour * 0.05);
                                            
                                            if($is_promotion_on == 1 && $promotion_deposit_amount != '' )
                                            {
                                                $individual_partial_amount = $promotion_deposit_amount;
                                                $individual_total_partial_amount = $promotion_deposit_amount * $pax_count;
                                            }
                                            //echo $pricing_id_loop;
                                            // Create the array structure for the current trip package
                                            $cart_array_combination_ongoing = array(
                                                'max_available' => $max_pax,
                                                'min_available' => $min_pax,
                                                'trip_start_date' => $dep_date_ymd,
                                                'currency' => '$',
                                                'trip' => array(),
                                                'enable_partial' => 1,
                                                'partial_payout_figure' => $promotion_deposit_amount,
                                                'trip_price_partial' => $individual_total_partial_amount,
                                                'pricing_id' => $pricing_id_loop,
                                                'arrival_date' => $dep_date_ymd,
                                                'date_id' => $date_id_loop,
                                                'departure_date' => $dep_date_ymd,
                                                'trip_extras' => array(
                                                    'id' => $trip_extras_ids,
                                                    'qty' => $trip_extras_qty
                                                ),
                                                'trip_id' => $trip_id_org,
                                                'trip_price' => $individual_amount_for_tour_pax,
                                                'pax' => $pax_count,
                                                'price_key' => ''
                                            );
                                        
                                            $cart_array_combination_ongoing['trip']['953'] = array(
                                                'pax' => $adult_count,
                                                'price' => $individual_amount_for_tour,
                                                'price_partial' => $individual_partial_amount,
                                                'type' => 'custom',
                                                'custom_label' => 'Adult',
                                                'price_per' => 'person'
                                            );
                                            
                                            $cart_array_combination_ongoing['trip']['954'] = array(
                                                'pax' => $child_count,
                                                'price' => $individual_amount_for_tour,
                                                'price_partial' => $individual_partial_amount,
                                                'type' => 'custom',
                                                'custom_label' => 'Child',
                                                'price_per' => 'person'
                                            );
                    
                                            // Add this combination to the main $cart_array with a unique key
                                            $unique_key = $trip_id_org . '_' . $dep_date_ymd . '_' . $pricing_id_loop;
                                            $cart_array[$unique_key] = $cart_array_combination_ongoing;
        
                                            $cart_json = json_encode($cart_array);
                                            
                                            if(isset($currnt_userlogn) && $currnt_userlogn == 'sriharshans')
                                            {
                                                // echo '<pre>';
                                                // print_r($cart_array);
                                                // echo '</pre>';
                                            }
                                            
                                            ?>
                                            <!--<button class="book-now" id="return_add_booking"><a href="<?php //echo $return_trip_link; ?>">View</a></button>
                                            <button class="book-now" data-summary='<?php //echo $cart_json; ?>' id="BuyThisTripNow">Book Now</button>-->
                                            <button class="book-nononeway" data-summary='<?php echo htmlspecialchars($cart_json, ENT_QUOTES, 'UTF-8'); ?>' id="BuyThisTripNow">Book Now</button></br>
                                            <button class="toggle_itinerary_button" style="background-color:black; color: #ffbb00; border-radius: 5px; width:100px; margin-top:7px; height:30px; padding:1px !important; font-size:12px;" id="button_<?php echo $row_going_id; ?>" onclick="toggleItinerary(<?php echo $row_going_id; ?>)">
                                                <i class="fa fa-chevron-down"></i> Itinerary
                                            </button>
                                            <?php
                                            if(intval($lowest_kg_value) > 30)
                                                {
                                                    ?>
                                                    <style>
                                                        @keyframes blink {
                                                            0%, 100%, 40%, 60% { opacity: 1; }
                                                            50% { opacity: 0; }
                                                        }
                                                    
                                                        .baggageblink {
                                                            
                                                            animation: blink 1s infinite !important;
                                                        }
                                                    </style>
                                                    <style>
                                                       .baggage_tag {
                                                            background-color: #ff9900;
                                                            color: white !important;
                                                            border: 2px solid #ff9900;
                                                            padding: 10px 45px;
                                                            font-size: 14px;
                                                            font-weight: bold;
                                                            text-align: center;
                                                            cursor: pointer;
                                                            display: inline-block;
                                                            border-radius: 5px 50px 5px 5px; /* Tag shape */
                                                            position: relative;
                                                            margin-top:30px;
                                                            text-decoration: none;
                                                            box-shadow: 3px 4px 8px rgba(0, 0, 0, 0.3);
                                                        }
                                                    
                                                        /* Hole effect to make it look like a luggage tag */
                                                        .baggage_tag::before {
                                                            content: "";
                                                            width: 12px;
                                                            height: 12px;
                                                            background-color: white;
                                                            border-radius: 50%;
                                                            position: absolute;
                                                            top: 10px;
                                                            left: 10px;
                                                            border: 2px solid #ff9900;
                                                        }
                                                    
                                                        /* String effect */
                                                        .baggage_tag::after {
                                                            content: "";
                                                            width: 4px;
                                                            height: 30px;
                                                            background-color: #ff9900;
                                                            position: absolute;
                                                            top: -30px;
                                                            left: 15px;
                                                            border-radius: 2px;
                                                        }
                                                    
                                                        /* Hover effect */
                                                        .baggage_tag:hover {
                                                            background-color: #ff9900;
                                                            box-shadow: 4px 6px 12px rgba(0, 0, 0, 0.5);
                                                        }
                                                    </style>
                                                    
                                                    <a href="#" class="baggage_tag">
                                                        Baggage <br>
                                                        <font class="baggageblink" style="font-size:21px;"><?php echo $final_baggage; ?></font>
                                                    </a>


                                                    <!--
                                                    <button class="baggage_button baggageblink" style="background-color:black; color: #ffbb00; border-radius: 5px; width:100px; margin-top:7px; height:30px; padding:1px !important; font-size:14px;">
                                                    Jambo Baggage allowed</br>
                                                    <?php echo $final_baggage;  ?>
                                                    </button>-->
                                                    <?php
                                                }
                                            ?>
                                        </div>
                                    </div>
                                    
                                </div>
                                <style>
    @keyframes blink {
        0%, 100%, 40%, 60% { opacity: 1; }
        50% { opacity: 0; }
    }

    .lockinprice {
        font-size: 20px;
    }

    .lockin-span {
        white-space: nowrap;
    }

    .inclusions_text {
        margin-top: 12px;
    }

    .responsive-container {
        display: grid;
        grid-template-columns: 1fr 1fr; /* Default for larger screens */
        gap: 1rem;
        width: 100%; /* Full width */
        background: white;
        border-radius: 10px;
        padding: 0.75rem;
        text-align:center;
    }

    .includes_div {
        display: flex;
        flex-wrap: nowrap; /* Prevent wrapping on mobile */
        align-items: center;
    }

    .responsive-container > div {
        max-width: 100%; /* Ensure the content spans fully in its cell */
    }
    
    .includes_image
    {
        width:65px;
        height:65px;
    }

    @media screen and (max-width: 750px) {
        .responsive-container {
            grid-template-columns: 1fr; /* Switch to single-column layout on smaller screens */
        }

        .lockinprice {
            text-align: center;
            padding: 0;
        }

        .lockin-span {
            white-space: normal;
            font-size: 12px;
        }
        .includes_image
        {
            width:40px;
            height:40px;
        }
        .inclusions_text {
            margin-top: 7px;
        }
    }
</style>



                                
                                <div class="responsive-container">
    <div>
        <?php
            
            if( $travel_vs_current_date_difference > 31 ) 
            {
                ?>
                <div class="lockinprice" style="margin: 15px 0px 20px 0px;">
                    <span class="lockin-span" style="font-weight:600; padding:9px 18px; background-color:#ffbb00; color:white; margin:0px 5px; border-radius:3px;">
                        <i class="fa fa-lock"></i> GUARANTEED LOCK IN NOW WITH $<?php echo $promotion_deposit_amount; ?> FOR UPTO 96 HRS
                    </span>
                </div>
                <?php
            } 
            else 
            {
                ?>
                <div class="lockinprice" style="margin: 15px 0px 20px 0px;">
                    <span class="lockin-span" style="font-weight:600; padding:9px 18px; background-color:#ffbb00; color:white; margin:0px 5px; border-radius:3px;">
                        <i class="fa fa-lock"></i> GUARANTEED LOCK IN NOW WITH $<?php echo $promotion_deposit_amount; ?> UNTIL 23:59 TODAY
                    </span>
                </div>
                <?php
            }
        ?>
    </div>
    <div>
        <div class="includes_div">
            <img src="https://gauratravel.com.au/wp-content/uploads/2024/12/Icon_food_black_text.png" class="includes_image">&nbsp;
            <span class="inclusions_text">+</span>&nbsp;
            <img src="https://gauratravel.com.au/wp-content/uploads/2024/12/Icon_baggage_black_text.png" class="includes_image">&nbsp;
            <span class="inclusions_text">+</span>&nbsp;
            <img src="https://gauratravel.com.au/wp-content/uploads/2024/12/Icon_entertainment_black_text.png" class="includes_image">
            <span class="inclusions_text">&nbsp;&nbsp;INCLUDED IN EVERY FARE</span>
        </div>
    </div>
</div>

                                
                            </div>
                            <?php
                            
        
                            $row_going_id++;
                        }
                        
                        
                        //echo '<pre>';
                        //print_r($cart_array);
                        //echo '</pre>';
                        
                    }
                    ?>
                <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Loader functions
                    function showLoader() {
                        $("#fullscreen-loader").fadeIn();
                    }
                
                    function hideLoader() {
                        $("#fullscreen-loader").fadeOut();
                    }
                
                    // Modal functions
                    function showModelStockBox(message) {
                        const overlay = document.getElementById('modal-stockbox-overlay');
                        const modal = document.getElementById('stock-model-stockbox');
                        const messageBox = document.getElementById('model-stockbox-message');
                        messageBox.innerText = message;
                    
                        // Show overlay and modal
                        overlay.style.display = 'block';
                        modal.style.display = 'block';
    
                        //$("#model-stockbox-message").text(message);
                        //$("#stock-model-stockbox").fadeIn();
                    }
                
                    function hideModelStockBox() {
                        const overlay = document.getElementById('modal-stockbox-overlay');
                        const modal = document.getElementById('stock-model-stockbox');
                        
                        // Hide overlay and modal
                        overlay.style.display = 'none';
                        modal.style.display = 'none';
    
                        $("#stock-model-stockbox").fadeOut();
                    }
                
                    // Close modal when close button is clicked
                    $(".close-button").click(function() {
                        hideModelStockBox();
                    });
                
                    // Handle book button click
                    $('.book-nononeway').on('click', function(e) {
                        e.preventDefault();
                
                        let cartArray = $(this).data('summary');
                
                        // Extract `pricing_id` and `pax` from `cartArray`
                        let pricingId = null;
                        let pax = null;
                
                        for (let key in cartArray) {
                            if (cartArray.hasOwnProperty(key)) {
                                pricingId = cartArray[key].pricing_id;
                                pax = cartArray[key].pax;
                                break; // Break after the first top-level item
                            }
                        }
                
                        // Debugging: Check extracted values
                        //console.log("Pricing ID:", pricingId, "PAX:", pax);
                
                        if (!pricingId || !pax) {
                            showModelStockBox("Invalid data provided. Please try again.");
                            return;
                        }
                
                        // Step 1: Check stock availability
                        $.ajax({
                            url: '/wp-content/themes/twentytwenty/templates/custom-cart-handler-revalidate-stock-before-checkout.php',
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                pricing_id: pricingId,
                                pax: pax
                            },
                            beforeSend: function() {
                                showLoader();
                            },
                            success: function(response) {
                                setTimeout(function() 
                                {
                                    hideLoader();
                                    if (response.stock_available) {
                                        // Step 2: Add item to cart
                                        $.ajax({
                                            url: '/wp-content/themes/twentytwenty/templates/custom-cart-handler.php',
                                            type: 'POST',
                                            dataType: 'json',
                                            data: {
                                                args: cartArray
                                            },
                                            success: function(response) {
                                                if (response.success) {
                                                    //alert("Checkout successful!");
                                                    window.location.href="/flights-checkout/";
                                                } else {
                                                    //alert('no stock');
                                                    //showModelStockBox("Failed to add item to cart. " + response.message);
                                                    showModelStockBox("Sorry, all seats for this flight are sold out. Kindly select a different flight option. For further assistance, please call our 24/7 customer support at 1300 359 463.");
                                                }
                                            },
                                            error: function(jqXHR, textStatus, errorThrown) {
                                                //alert('no return');
                                                //showModelStockBox("Error: " + textStatus + " - " + errorThrown);
                                                showModelStockBox("Sorry, all seats for this flight are sold out. Kindly select a different flight option. For further assistance, please call our 24/7 customer support at 1300 359 463.");
                                            }
                                        });
                                    } else {
                                        //alert('failed call');
                                        showModelStockBox("Sorry, all seats for this flight are sold out. Kindly select a different flight option. For further assistance, please call our 24/7 customer support at 1300 359 463.");
                                    }
                                }, 3000);
                            },
                            error: function(jqXHR, textStatus, errorThrown) {
                                setTimeout(function() 
                                {
                                    hideLoader();
                                    //alert('failed call main');
                                    showModelStockBox("Sorry, all seats for this flight are sold out. Kindly select a different flight option. For further assistance, please call our 24/7 customer support at 1300 359 463.");
                                    //showModelStockBox("Stock validation error: " + textStatus + " - " + errorThrown);
                                }, 3000);
                            }
                        });
                    });
                });
                </script>
                </div>
                
                <?php
            }
            if(isset($_GET['depdate1']) && $_GET['depdate1'] != '' && isset($_GET['retdate1']) && $_GET['retdate1'] != '' && 1 == 1) // Return trip combined
            {
                $depatrue_pricing_id = '';
                $return_pricing_id = '';
                $debug_test = 1;
                
                //echo '<center><button style="background-color:black; color: #ffbb00; width:200px; margin-top:7px; height:30px; padding:1px !important; font-size:12px;" onclick="flexibleDateButton()">Get Flexible Date</button></center>';
    
                $return_travel_packages = [];
    
    if ($the_query->have_posts() && $the_query_return->have_posts()) {
        
        $is_single_stock_found = array();
        
        $adult_count = $_GET['adt'];
        $child_count = $_GET['chd'];
        $pax_count = $adult_count + $child_count;
        
        foreach ($the_query->posts as $outbound_post) {
            foreach ($the_query_return->posts as $return_post) {
                $total_amount_for_tour = 0;
                $total_amount_outbound = 0;
                $total_amount_return = 0;
                
                $is_seat_available_based_on_stock_oneway = false;
                $is_seat_available_based_on_stock_return = false;
                
                $is_no_stock_found_ongoing = 0;
                $is_no_stock_found_return = 0;
    
                // Calculate the total amount for the outbound trip
                $trip_id_org = $outbound_post->ID;
                $get_results_by_dep_date = $wpdb->get_results( "
                SELECT dates.start_date, dates.pricing_ids 
                FROM wpk4_wt_dates dates 
                LEFT JOIN wpk4_wt_excluded_dates_times AS exclude 
                ON dates.trip_id = exclude.trip_id AND dates.start_date = exclude.start_date 
                WHERE dates.trip_id = '$trip_id_org' AND date(dates.start_date) > CURRENT_DATE AND dates.start_date LIKE '$dep_date_ymd' 
                AND exclude.start_date IS NULL
                "); 
                
                $num_rows_ongoing = count($get_results_by_dep_date);
                if($num_rows_ongoing > 0)
                {
                    $is_no_stock_found_ongoing = 1;
                }
                
                foreach($get_results_by_dep_date as $get_row_by_dep_date)
                { 
                    $pricing_id_loop = $get_row_by_dep_date->pricing_ids;
                    
                    $start_date_loop = $get_row_by_dep_date->start_date;
                        $max_pax_loop = 0;
                        $get_extras_by_pricingid = $wpdb->get_results( "SELECT max_pax FROM wpk4_wt_pricings where id='$pricing_id_loop'"); 
                        foreach($get_extras_by_pricingid as $get_extra_by_pricingid )
                        { 
                            $max_pax_loop = $get_extra_by_pricingid->max_pax;
                        } 
                        
                        $start_date_loop_date = str_replace("-", "_", $start_date_loop);
                        $meta_checkup = 'wt_booked_pax-'.$pricing_id_loop.'-'.$start_date_loop_date;
                            
                            $total_booked_for_trip = 0;
                            $get_postmeta_stock = $wpdb->get_results( "SELECT meta_value FROM wpk4_postmeta  where meta_key LIKE '$meta_checkup%'"); 
                            foreach($get_postmeta_stock as $get_postmeta_stock_row )
                            {
                                $total_booked_for_trip += (float)$get_postmeta_stock_row->meta_value;
                            }
                            
                            $available_seat_for_trip = $max_pax_loop - $total_booked_for_trip;
                            
                             //echo $meta_checkup . ' -> ' . $available_seat_for_trip .' > '. $pax_count.'</br>';
                            if($available_seat_for_trip >= $pax_count) 
                            {
                                $is_seat_available_based_on_stock_oneway = true;
                            }
                            
                    $get_results_by_pricingid = $wpdb->get_results( "
                    SELECT regular_price, sale_price 
                    FROM wpk4_wt_price_category_relation 
                    WHERE pricing_id='$pricing_id_loop' AND pricing_category_id='953'
                    "); 
    
                    foreach($get_results_by_pricingid as $get_row_by_pricingid)
                    { 
                        $total_amount_for_tour += (float)$get_row_by_pricingid->sale_price;
                        $total_amount_outbound = $get_row_by_pricingid->sale_price;
                    }
                }	
    
                // Calculate the total amount for the return trip
                $trip_id_org = $return_post->ID;
                $get_results_by_dep_date = $wpdb->get_results( "
                SELECT dates.start_date, dates.pricing_ids 
                FROM wpk4_wt_dates dates 
                LEFT JOIN wpk4_wt_excluded_dates_times AS exclude 
                ON dates.trip_id = exclude.trip_id AND dates.start_date = exclude.start_date 
                WHERE dates.trip_id = '$trip_id_org' AND date(dates.start_date) > CURRENT_DATE AND dates.start_date LIKE '$ret_date_ymd' 
                AND exclude.start_date IS NULL
                "); 
                
                $num_rows_return = count($get_results_by_dep_date);
                if($num_rows_return > 0)
                {
                    $is_no_stock_found_return = 1;
                }
                //echo $num_rows_ongoing . ' ' . $num_rows_return .'</br>';
                
                foreach($get_results_by_dep_date as $get_row_by_dep_date)
                { 
                    $pricing_id_loop = $get_row_by_dep_date->pricing_ids;
                    
                    $start_date_loop = $get_row_by_dep_date->start_date;
                        
                        $get_extras_by_pricingid = $wpdb->get_results( "SELECT max_pax FROM wpk4_wt_pricings where id='$pricing_id_loop'"); 
                        foreach($get_extras_by_pricingid as $get_extra_by_pricingid )
                        { 
                            $max_pax_loop = $get_extra_by_pricingid->max_pax;
                        } 
                        $start_date_loop_date = str_replace("-", "_", $start_date_loop);
                        $meta_checkup = 'wt_booked_pax-'.$pricing_id_loop.'-'.$start_date_loop_date;
                            
                            $total_booked_for_trip = 0;
                            $get_postmeta_stock = $wpdb->get_results( "SELECT meta_value FROM wpk4_postmeta  where meta_key LIKE '$meta_checkup%'"); 
                            foreach($get_postmeta_stock as $get_postmeta_stock_row )
                            {
                                $total_booked_for_trip += (float)$get_postmeta_stock_row->meta_value;
                            }
                            
                            $available_seat_for_trip = $max_pax_loop - $total_booked_for_trip;
                            
                            // echo $meta_checkup . ' -> ' . $available_seat_for_trip .' > '. $pax_count.'</br>';
                            if($available_seat_for_trip >= $pax_count) 
                            {
                                $is_seat_available_based_on_stock_return = true;
                            }
                            
                            
                    $get_results_by_pricingid = $wpdb->get_results( "
                    SELECT regular_price, sale_price 
                    FROM wpk4_wt_price_category_relation 
                    WHERE pricing_id='$pricing_id_loop' AND pricing_category_id='953'
                    "); 
    
                    foreach($get_results_by_pricingid as $get_row_by_pricingid)
                    { 
                        $total_amount_for_tour += (float)$get_row_by_pricingid->sale_price;
                        $total_amount_return = $get_row_by_pricingid->sale_price;
                    }
                }
                
                if(isset($_GET['get_flexible_date']) && $_GET['get_flexible_date'] == true)//srihars
                {
                    
                    // Calculate the total amount for the outbound trip
                    $trip_id_org = $outbound_post->ID;
                    $get_results_by_dep_date = $wpdb->get_results( "
                    SELECT dates.start_date, dates.pricing_ids 
                    FROM wpk4_wt_dates dates 
                    LEFT JOIN wpk4_wt_excluded_dates_times AS exclude 
                    ON dates.trip_id = exclude.trip_id AND dates.start_date = exclude.start_date 
                    WHERE dates.trip_id = '$trip_id_org' AND date(dates.start_date) > CURRENT_DATE AND dates.start_date LIKE '$dep_date_ymd_cropped%' 
                    AND exclude.start_date IS NULL
                    "); 
                    foreach($get_results_by_dep_date as $get_row_by_dep_date)
                    { 
                        $pricing_id_loop = $get_row_by_dep_date->pricing_ids;
                        $start_date_loop = $get_row_by_dep_date->start_date;
                        $max_pax_loop = 0;
                        $get_extras_by_pricingid = $wpdb->get_results( "SELECT max_pax FROM wpk4_wt_pricings where id='$pricing_id_loop'"); 
                        foreach($get_extras_by_pricingid as $get_extra_by_pricingid )
                        { 
                            $max_pax_loop = $get_extra_by_pricingid->max_pax;
                        } 
                        
                        $start_date_loop_date = str_replace("-", "_", $start_date_loop);
                        $meta_checkup = 'wt_booked_pax-'.$pricing_id_loop.'-'.$start_date_loop_date;
                            
                            $total_booked_for_trip = 0;
                            $get_postmeta_stock = $wpdb->get_results( "SELECT meta_value FROM wpk4_postmeta  where meta_key LIKE '$meta_checkup%'"); 
                            foreach($get_postmeta_stock as $get_postmeta_stock_row )
                            {
                                $total_booked_for_trip += (float)$get_postmeta_stock_row->meta_value;
                            }
                            
                            $available_seat_for_trip = $max_pax_loop - $total_booked_for_trip;
                            
                            // echo $meta_checkup . ' -> ' . $available_seat_for_trip .' > '. $pax_count.'</br>';
                            if($available_seat_for_trip >= $pax_count) 
                            {
                                $is_seat_available_based_on_stock_oneway = true;
                            }
                        
                        $get_results_by_pricingid = $wpdb->get_results( "
                        SELECT regular_price, sale_price 
                        FROM wpk4_wt_price_category_relation 
                        WHERE pricing_id='$pricing_id_loop' AND pricing_category_id='953'
                        "); 
        
                        foreach($get_results_by_pricingid as $get_row_by_pricingid)
                        { 
                            
                            $total_amount_for_tour += (float)$get_row_by_pricingid->sale_price;
                            $total_amount_outbound = $get_row_by_pricingid->sale_price;
                            
                            $dep_date_ymd = $get_row_by_dep_date->start_date;
                            
                            $is_no_stock_found_ongoing = 1;
                        }
                    }
                    
                    // Calculate the total amount for the return trip
                    $trip_id_org = $return_post->ID;
                    $get_results_by_dep_date = $wpdb->get_results( "
                    SELECT dates.start_date, dates.pricing_ids 
                    FROM wpk4_wt_dates dates 
                    LEFT JOIN wpk4_wt_excluded_dates_times AS exclude 
                    ON dates.trip_id = exclude.trip_id AND dates.start_date = exclude.start_date 
                    WHERE dates.trip_id = '$trip_id_org' AND date(dates.start_date) > CURRENT_DATE AND dates.start_date LIKE '$ret_date_ymd_cropped%' 
                    AND exclude.start_date IS NULL
                    "); 
                    foreach($get_results_by_dep_date as $get_row_by_dep_date)
                    { 
                        $pricing_id_loop = $get_row_by_dep_date->pricing_ids;
                        $start_date_loop = $get_row_by_dep_date->start_date;
                        
                        $get_extras_by_pricingid = $wpdb->get_results( "SELECT max_pax FROM wpk4_wt_pricings where id='$pricing_id_loop'"); 
                        foreach($get_extras_by_pricingid as $get_extra_by_pricingid )
                        { 
                            $max_pax_loop = $get_extra_by_pricingid->max_pax;
                        } 
                        
                        $start_date_loop_date = str_replace("-", "_", $start_date_loop);
                        $meta_checkup = 'wt_booked_pax-'.$pricing_id_loop.'-'.$start_date_loop_date;
                            
                            $total_booked_for_trip = 0;
                            $get_postmeta_stock = $wpdb->get_results( "SELECT meta_value FROM wpk4_postmeta  where meta_key LIKE '$meta_checkup%'"); 
                            foreach($get_postmeta_stock as $get_postmeta_stock_row )
                            {
                                $total_booked_for_trip += (float)$get_postmeta_stock_row->meta_value;
                            }
                            
                            $available_seat_for_trip = $max_pax_loop - $total_booked_for_trip;
                            
                            // echo $meta_checkup . ' -> ' . $available_seat_for_trip .' > '. $pax_count.'</br>';
                            if($available_seat_for_trip >= $pax_count) 
                            {
                                $is_seat_available_based_on_stock_return = true;
                            }
                        
                        $get_results_by_pricingid = $wpdb->get_results( "
                        SELECT regular_price, sale_price 
                        FROM wpk4_wt_price_category_relation 
                        WHERE pricing_id='$pricing_id_loop' AND pricing_category_id='953'
                        "); 
        
                        foreach($get_results_by_pricingid as $get_row_by_pricingid)
                        { 
                            
                            $total_amount_for_tour += (float)$get_row_by_pricingid->sale_price;
                            $total_amount_return = $get_row_by_pricingid->sale_price;
                            
                            $ret_date_ymd = $get_row_by_dep_date->start_date;
                            
                            $is_no_stock_found_return = 1;
                        }
                    }
                }
                
                //echo $outbound_post->ID . ' - ' . $return_post->ID . ' - ' . $dep_date_ymd .' - ' . $ret_date_ymd .'</br>';
                
                
                if($total_amount_return != 0 && $total_amount_outbound != 0 && $outbound_post != '' && $return_post != '' && $is_seat_available_based_on_stock_oneway && $is_seat_available_based_on_stock_return)
                {
                    // Store the trip details along with the total amount
                    $return_travel_packages[] = [
                        'outbound' => $outbound_post,
                        'return' => $return_post,
                        'total_amount_outbound' => $total_amount_outbound,
                        'total_amount_return' => $total_amount_return,
                        'total_amount_for_tour' => $total_amount_for_tour,
                        'outbounddate' => $dep_date_ymd,
                        'returndate' => $ret_date_ymd,
                    ];
                }
                $is_single_stock_found[] = $is_no_stock_found_ongoing .' - ' . $is_no_stock_found_return;
            }
            
        }
        
        //print_r($is_single_stock_found);
        
        if(!in_array("1 - 1", $is_single_stock_found) || count($return_travel_packages) === 0)
        //if( ($is_no_stock_found_ongoing == 0 || $is_no_stock_found_return == 0 || ( $is_no_stock_found_ongoing == 0 && $is_no_stock_found_return == 0 )) && current_user_can( 'administrator' ))
                {
                    
                    ?>
                    <style>
                        /* The Modal (background) */
                        .modal {
                          display: none; /* Hidden by default */
                          position: fixed; /* Stay in place */
                          z-index: 1; /* Sit on top */
                          padding-top: 250px; /* Location of the box */
                          left: 0;
                          top: 0;
                          width: 100%; /* Full width */
                          height: 100%; /* Full height */
                          overflow: auto; /* Enable scroll if needed */
                          background-color: rgb(0,0,0); /* Fallback color */
                          background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
                        }
                        
                        /* Modal Content */
                        .modal-content {
                          position: relative;
                          background-color: #fefefe;
                          margin: auto;
                          padding: 0;
                          border: 1px solid #888;
                          width: 80%;
                          box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2),0 6px 20px 0 rgba(0,0,0,0.19);
                          -webkit-animation-name: animatetop;
                          -webkit-animation-duration: 0.4s;
                          animation-name: animatetop;
                          animation-duration: 0.4s
                        }
                        
                        /* Add Animation */
                        @-webkit-keyframes animatetop {
                          from {top:-300px; opacity:0} 
                          to {top:0; opacity:1}
                        }
                        
                        @keyframes animatetop {
                          from {top:-300px; opacity:0}
                          to {top:0; opacity:1}
                        }
                        
                        /* The Close Button */
                        .close {
                          color: white;
                          float: right;
                          font-size: 28px;
                          font-weight: bold;
                        }
                        
                        .close:hover,
                        .close:focus {
                          color: #000;
                          text-decoration: none;
                          cursor: pointer;
                        }
                        
                        .modal-header {
                          padding: 2px 16px;
                          background-color: #ffbb00;
                          color: black;
                        }
                        
                        .modal-header h6
                        {
                            margin:20px 0px;
                        }
                        
                        .modal-body {padding: 2px 16px;}
                        
                        .modal-footer {
                          padding: 2px 16px;
                          background-color: #ffbb00;
                          color: white;
                        }
                    </style>
                    <div id="myMfodal" class="modfal">
    
                      <!-- Modal content -->
                      <div class="modal-content">
                        <!--<div class="modal-header">
                          <span class="close">&times;</span>
                          <h6>Seats unavailable</h6>
                        </div>-->
                        <div class="modal-body">
                            </br>
                            <center>
                            <p>Sorry, there are no GDeals available for the selected dates.</p>
                            <p>If your dates are flexible, please click on View Flexible Dates.</p>
                            <p>For alternative options and booking assistance, Call Us Anytime – We're here 24/7 to help you!</p>
                            <center>
                                <button style="background-color:black; color: #ffbb00; width:200px; margin-top:7px; height:30px; padding:1px !important; font-size:12px;" onclick="flexibleDateButton()">View flexible dates</button>
                                
                                <a href='tel:1300359463'><button style="background-color:black; color: #ffbb00; width:200px; margin-top:7px; height:30px; padding:1px !important; font-size:12px;">Call Now</button></a>
                            </center>
                            </br>
                        </div>
                        <!--<div class="modal-footer">-->
                        </div>
                      </div>
                    
                    </div>
                    
                    
                    <script type="text/javascript">
                        var modal = document.getElementById("myModal");
    
                        // Get the <span> element that closes the modal
                        var span = document.getElementsByClassName("close")[0];
                        
                        // When the user clicks the button, open the modal 
                          modal.style.display = "block";
                        
                        // When the user clicks on <span> (x), close the modal
                        span.onclick = function() {
                          modal.style.display = "none";
                        }
                        
                        // When the user clicks anywhere outside of the modal, close it
                        window.onclick = function(event) {
                          if (event.target == modal) {
                            modal.style.display = "none";
                          }
                        }
                    </script>
                    <?php
                }
    
    }
    // Sort the return_travel_packages array by total_amount_for_tour
        usort($return_travel_packages, function($a, $b) {
            return $a['total_amount_for_tour'] <=> $b['total_amount_for_tour'];
        });
                
                ?>
                <div id="wptravel-archive-wrapper" class="wptravel-archive-wrapper">
                    <?php 
                    $row_going_id = 1;
                    $row_return_id = 1;
                    foreach ($return_travel_packages as $package)
                    {
                        $cart_array = array();
                        $cart_array_combination_ongoing = array();
                        
                        $adult_count = $_GET['adt'];
                        $child_count = $_GET['chd'];
                        $pax_count = $adult_count + $child_count;
                        
                        $outbound_post = $package['outbound'];
                        $return_post = $package['return'];
                        
                        if(isset($_GET['get_flexible_date']) && $_GET['get_flexible_date'] == true)
                        {
                            $dep_date_ymd = $package['outbounddate'];
                            $ret_date_ymd = $package['returndate'];
                        }
                            
                        $start_day = strtotime($dep_date_ymd);
                        $current_day = strtotime(date('Y-m-d'));
                                                    
                        $travel_vs_current_date_difference = ( $start_day - $current_day ) / (60 * 60 * 24);

                        $total_amount_for_tour = 0;
                        $initial_flight = '';
                        // ONEWAY
                        {
                            global $wp_travel_itinerary;
                            $trip_id_org = $outbound_post->ID;
                            $enable_sale = WP_Travel_Helpers_Trips::is_sale_enabled(array('trip_id' => $outbound_post->ID));
                            $group_size  = wptravel_get_group_size($outbound_post->ID);
                            $start_date  = get_post_meta($outbound_post->ID, 'wp_travel_start_date', true);
                            $end_date    = get_post_meta($outbound_post->ID, 'wp_travel_end_date', true);
                            
                            $outbound_trip_link = get_permalink($outbound_post->ID);
            
                            $args  = $args_regular = array('trip_id' => $outbound_post->ID); // phpcs:ignore
            
                            $args_regular['is_regular_price'] = true;
                                
                            $regular_price_loop = 0;
                            $sale_price_loop = 0;
                            $get_results_by_dep_date = $wpdb->get_results( "
                            SELECT dates.start_date, dates.id, dates.pricing_ids FROM wpk4_wt_dates dates LEFT JOIN wpk4_wt_excluded_dates_times AS exclude 
                                                        ON dates.trip_id = exclude.trip_id AND dates.start_date = exclude.start_date 
                                    WHERE dates.trip_id = '$trip_id_org' AND date(dates.start_date) > CURRENT_DATE AND dates.start_date LIKE '$dep_date_ymd'
                                    AND exclude.start_date IS NULL
                            "); 
                            $min_pax = 0;
                            $min_pax = 1;
                            $trip_extras_string = '';
                            foreach($get_results_by_dep_date as $get_row_by_dep_date )
                            { 
                                $pricing_id_loop = $get_row_by_dep_date->pricing_ids;
                                $date_id_loop = $get_row_by_dep_date->id;
                                
                                $get_extras_by_pricingid = $wpdb->get_results( "SELECT min_pax, max_pax, trip_extras FROM wpk4_wt_pricings where id='$pricing_id_loop'"); 
                                foreach($get_extras_by_pricingid as $get_extra_by_pricingid )
                                { 
                                    $trip_extras_string = $get_extra_by_pricingid->trip_extras;
                                    $min_pax = $get_extra_by_pricingid->min_pax;
                                    $max_pax = $get_extra_by_pricingid->max_pax;
                                }
                                
                                $get_results_by_pricingid = $wpdb->get_results( "SELECT regular_price, sale_price FROM wpk4_wt_price_category_relation where pricing_id='$pricing_id_loop' AND pricing_category_id='953'"); 
                                foreach($get_results_by_pricingid as $get_row_by_pricingid )
                                { 
                                    $regular_price_loop = $get_row_by_pricingid->regular_price;
                                    $sale_price_loop = $get_row_by_pricingid->sale_price;
                                }
                            }
                                
                            $available_dates = '';
                            $available_month = '';
                            $loop_counter = 0;
                            $is_break = 0;
                            
                            $available_dates .= '<div class="carousel-container" data-product-carousel>';
                            $available_dates .= '<div class="carousel-arrow left-arrow" data-arrow="left"><i class="fa fa-chevron-left" aria-hidden="true"></i></div>'; // Left arrow
                            $available_dates .= '<div class="dates-carousel">'; // Carousel for the dates
                            
                            if($dep_date_ymd_cropped != '')
                            {
                                $get_results_by_dep_month = $wpdb->get_results( "SELECT dates.start_date, dates.pricing_ids FROM wpk4_wt_dates dates 
                                    LEFT JOIN wpk4_wt_excluded_dates_times AS exclude ON dates.trip_id = exclude.trip_id AND dates.start_date = exclude.start_date
                                    WHERE dates.trip_id = '$trip_id_org' AND date(dates.start_date) > CURRENT_DATE AND dates.start_date LIKE '$dep_date_ymd_cropped%' AND exclude.start_date IS NULL"); 
                                foreach($get_results_by_dep_month as $get_row_by_dep_month )
                                { 
                                    $pricing_id_month_loop = $get_row_by_dep_month->pricing_ids;
                                    $start_date_month_loop = $get_row_by_dep_month->start_date;
                        
                                    $get_results_by_month_pricingid = $wpdb->get_results( "SELECT regular_price, sale_price FROM wpk4_wt_price_category_relation where pricing_id='$pricing_id_month_loop' AND pricing_category_id='953'"); 
                                    foreach($get_results_by_month_pricingid as $get_row_by_month_pricingid )
                                    { 
                                        $regular_price_month_loop = $get_row_by_month_pricingid->regular_price;
                                        $sale_price_month_loop = $get_row_by_month_pricingid->sale_price;
                                        //date('F', strtotime($start_date_month_loop))
                                        $available_month = '<span style="font-size:13px; font-weight:700; padding:5px 6px; background-color:#ffbb00; color:black; margin:0px 5px; border-radius:3px 3px 3px 3px; white-space: nowrap; ">'.date('M', strtotime($start_date_month_loop)).'</span>';
                                        
                                        if($dep_date_ymd == $start_date_month_loop)
                                        {
                                            $available_dates .= '<span id="payment_amount_selector" class="pay_selected" style="width:100px !important; display: inline-block; white-space: nowrap; font-size:13px; padding:5px 6px; border:1px solid #ffbb00; color:black; margin:5px 3px 5px 3px !important; border-radius:3px 3px 3px 3px;">
                                                        <i class="fa fa-calendar-o" style="color:#210301;"></i> <b>'.date('d', strtotime($start_date_month_loop)) . '</b> - $<font style="font-size:14px; font-weight:600;">' . $sale_price_month_loop.'</font></span>';
                                        }
                                        else
                                        {
                                            $available_dates .= '<span id="payment_amount_selector" class="pay_not_selected" onclick="updateURLWithOnGoingDate(\'' . $start_date_month_loop . '\')"  style="cursor: pointer; width:100px !important; display: inline-block; white-space: nowrap; font-size:13px; padding:5px 6px; border:1px solid #ffbb00; color:black; margin:5px 3px 5px 3px !important; border-radius:3px 3px 3px 3px;">
                                                        <i class="fa fa-calendar-o" style="color:#210301;"></i> <b>'.date('d', strtotime($start_date_month_loop)) . '</b> - $<font style="font-size:14px; font-weight:600;">' . $sale_price_month_loop.'</font></span>';
                                        }
                                        
                                        $loop_counter++;
                                    }
                                    
                                    if($is_break == 1)
                                    {
                                        break;
                                    }
                                }
                            }
                            
                            if($ret_date_ymd_cropped != '')
                            {
                                $loop_counter = 0;
                                $is_break = 0;
                                $get_results_by_dep_month = $wpdb->get_results( "SELECT dates.start_date, dates.pricing_ids FROM wpk4_wt_dates dates LEFT JOIN wpk4_wt_excluded_dates_times AS exclude 
                                                        ON dates.trip_id = exclude.trip_id AND dates.start_date = exclude.start_date WHERE dates.trip_id = '$trip_id_org' AND date(dates.start_date) > CURRENT_DATE AND dates.start_date LIKE '$ret_date_ymd_cropped%' 
                                                        AND exclude.start_date IS NULL "); 
                                foreach($get_results_by_dep_month as $get_row_by_dep_month )
                                { 
                                    $pricing_id_month_loop = $get_row_by_dep_month->pricing_ids;
                                    $start_date_month_loop = $get_row_by_dep_month->start_date;
                        
                                    $get_results_by_month_pricingid = $wpdb->get_results( "SELECT regular_price, sale_price FROM wpk4_wt_price_category_relation where pricing_id='$pricing_id_month_loop' AND pricing_category_id='953'"); 
                                    
                                    foreach($get_results_by_month_pricingid as $get_row_by_month_pricingid )
                                    { 
                                        $regular_price_month_loop = $get_row_by_month_pricingid->regular_price;
                                        $sale_price_month_loop = $get_row_by_month_pricingid->sale_price;
                        
                                        $available_month = '<span style="font-size:13px; font-weight:700; padding:5px 6px; background-color:#ffbb00; color:black; margin:0px 5px; border-radius:3px 3px 3px 3px; white-space: nowrap; ">'.date('M', strtotime($start_date_month_loop)).'</span>';
                                        $available_dates .= '<span style="cursor: pointer; width:100px !important; display: inline-block; white-space: nowrap; font-size:13px; padding:5px 6px; border:1px solid #ffbb00; color:black; margin:5px 3px 5px 3px !important; border-radius:3px 3px 3px 3px;">
                                                        <i class="fa fa-calendar-o" style="color:#210301;"></i> <b>'.date('d', strtotime($start_date_month_loop)) . '</b> - $<font style="font-size:14px; font-weight:600;">' . $sale_price_month_loop.'</font></span>';
                                        $loop_counter++;
                                    }
                                    
                                    if($is_break == 1)
                                    {
                                        break;
                                    }
                                }
                            }
                            
                            $is_month_eligible_for_lock_price = $start_date_month_loop;
                            
                            $available_dates .= '</div>'; // Close .dates-carousel
                            $available_dates .= '<div class="carousel-arrow right-arrow" data-arrow="right"><i class="fa fa-chevron-right" aria-hidden="true"></i></div>'; // Right arrow
                            $available_dates .= '</div>'; // Close .carousel-container
                        
                            $trip_price = WP_Travel_Helpers_Pricings::get_price($args);
                            $regular_price = WP_Travel_Helpers_Pricings::get_price($args_regular);
                        
                            $locations = get_the_terms($outbound_post->ID, 'travel_locations');
                            $trip_locations = get_the_terms($outbound_post->ID, 'travel_locations');
                            $location_name = '';
                            $location_link = '';
                            if ($locations && is_array($locations)) 
                            {
                                $first_location = array_shift($locations);
                                $location_name  = $first_location->name;
                                $location_link  = get_term_link($first_location->term_id, 'travel_locations');
                            }
                            
                            $is_trip_for_live = '';
                            $is_trip_for_live = get_post_meta($outbound_post->ID, '_yoast_wpseo_primary_status', true);
                            
                            $wp_travel_trip_itinerary_data_org = get_post_meta($trip_id_org, 'wp_travel_trip_itinerary_data', true);
                            
                            // fetch product details
                            if (isset($wp_travel_trip_itinerary_data_org) && !empty($wp_travel_trip_itinerary_data_org))
                            {
                                $wptravel_index = 1;
                                $itinerary_location_array = array();
                                $itinerary_time_array = array();
                                $itinerary_flight_array = array();
                                $itinerary_date_array = array();
                                $itinerary_datedecider_array = array();
                                $itinerary_array_counter = 0;
                                $itinerary_counter = 0;
                                
                                foreach ($wp_travel_trip_itinerary_data_org as $wptravel_itinerary)
                                {
                                    $wptravel_time_format = get_option('time_format');
                                    $wptravel_itinerary_label = '';
                                    $wptravel_itinerary_title = '';
                                    $wptravel_itinerary_desc  = '';
                                    $wptravel_itinerary_date  = '';
                                    $wptravel_itinerary_time  = '';
                                    $itinerary_counter = 1;
                                    $is_itinerary_available = 1;
                                    $wptravel_itinerary_label = stripslashes($wptravel_itinerary['label']);
                        
                                    $wptravel_itinerary_title = stripslashes($wptravel_itinerary['title']);
                        
                                    $wptravel_itinerary_desc = stripslashes($wptravel_itinerary['desc']);
                        
                                    $wptravel_itinerary_date = wptravel_format_date($wptravel_itinerary['date']);
                        
                                    $wptravel_itinerary_time = stripslashes($wptravel_itinerary['time']);
                                    $wptravel_itinerary_time = date($wptravel_time_format, strtotime($wptravel_itinerary_time));
                        
                                    $itinerary_location_array[$itinerary_array_counter] = $wptravel_itinerary_label; // destination
                                    $itinerary_time_array[$itinerary_array_counter] = $wptravel_itinerary_time; // flight time
                                    $itinerary_flight_array[$itinerary_array_counter] = $wptravel_itinerary_title; // flight number
                                    //$itinerary_date_array[$itinerary_array_counter] = $traveldate_fxed; // flight date
                                    $itinerary_datedecider_array[$itinerary_array_counter] = strip_tags($wptravel_itinerary_desc); // arrival or departure define
                        
                                    $wptravel_index++;
                                    $itinerary_array_counter++;
                                }
                            }
                        
                            $wptravel_travel_outline = get_post_meta($trip_id_org, 'wp_travel_outline', true );
                            $wp_travel_outline_dom = new DOMDocument();
                            if (!empty($wptravel_travel_outline)) {
                                $wp_travel_outline_dom->loadHTML($wptravel_travel_outline);
                            } else {
                                $wp_travel_outline_dom->loadHTML("<div></div>");
                            }
                            //$wp_travel_outline_dom->loadHTML($wptravel_travel_outline);
                            
                            $journey_duration_ele = $wp_travel_outline_dom->getElementsByTagName('p');
                        
                            $total_duration = "";
                            
                            foreach($journey_duration_ele as $node) 
                            {
                                $journery_dur = explode(":", $node->textContent)[0];
                                if(strtolower($journery_dur) == "journey duration")
                                {
                                    $total_duration = explode(":", $node->textContent)[1];
                                }
                            }
                        
                            $trip_wp_title = get_post_field('post_title', $trip_id_org);
                            $trip_title_arr = explode(" ", $trip_wp_title);
                        
                            $has_complimentary_lounge = false;
                            for($i = 0; $i < count($trip_title_arr); $i++)
                            {
                                if(str_contains(strtolower($trip_title_arr[$i]),'complimentary'))
                                {
                                    $has_complimentary_lounge = true;
                                }
                            }
                            ?>
                            <!-- Start of GDeals flight view -->
                            <div class="card" style="margin-bottom: 0rem !important; box-shadow: rgba(100, 100, 111, 0.2) 0px -7px 29px -7px;">
                                <div class="dealwrapper yellow">
                                    <div class="ribbon-wrapper">
                                        <div class="ribbon-tag">GDeals</div>
                                    </div>
                                </div>
                                <div class="card-content">
                                    
                                    <div class="deal">
                                        <div class="lockinprice_top">
                                            &nbsp;
                                        </div>
                                        <div class="src-dts">
                                            <h2 class="m-0">
                                                <?php
                                                    // fetch airline images
                                                    $airline_arr = array("virgin australia" ,"thai","singapore","qatar" ,"srilanka","airindia","scoot","emirates","sabre",
                                                    "jetstar","cathay","malaysia","qantas","eithad");
                                                    $airline_obj = array(
                                                        "virgin australia" => "img-virginaus",
                                                        "thai" => "img-thai",
                                                        "singapore" => "img-singapore",
                                                        "qatar" => "img-qatar",
                                                        "srilanka" => "img-srilanka",
                                                        "airindia" => "img-airindia",
                                                        "scoot" => "img-scoot",
                                                        "emirates" => "img-emirates",
                                                        "sabre" => "img-sabre",
                                                        "jetstar" => "img-jetstar",
                                                        "cathay" => "img-cathay",
                                                        "malaysia" => "img-malaysia",
                                                        "qantas" => "img-qantas",
                                                        "eithad" => "img-eithad",
                                                        "default" => "img-default"
                                                    );
                                                    $trip_wp_title = get_post_field('post_title', $trip_id_org);
                                                    $array_source = explode(" ", $trip_wp_title);
                                                    $airline_name = '';
                                                    for($i = 0; $i < count($array_source); $i++){
                                                        $val = strtolower(preg_replace('/-/','',$array_source[$i]));
                                                        if(in_array($val, $airline_arr)){
                                                            $airline_name = $val;
                                                            break;
                                                        }
                                                    }
                            
                                                    if($airline_name == ''){
                                                        $airline_name = "default";
                                                    }

                                                    $img_href ="https://".$_SERVER['SERVER_NAME']."/wp-content/uploads/2023/09/".$airline_obj[$airline_name].".png";
                                                    //echo '<p style="font-size:12px;letter-spacing:1px;">'.$trip_id_org . ' ' . $dep_date_ymd.'</p>';
                                                    ?>
                                                    <a href="#"><img src=<?php echo $img_href ?> alt=<?php echo $airline_name ?> /></a>
                                                    
                                                    <?php
                                                    $airline_arr = array("virgin australia" ,"thai","singapore","qatar" ,"srilanka","airindia","scoot","emirates","sabre",
                                                    "jetstar","cathay","malaysia","qantas","eithad");
                                                    $airline_obj = array(
                                                        "virgin australia" => "VA",
                                                        "thai" => "TG",
                                                        "singapore" => "SQ",
                                                        "qatar" => "QR",
                                                        "srilanka" => "UL",
                                                        "airindia" => "AI",
                                                        "scoot" => "TR",
                                                        "emirates" => "EK",
                                                        "jetstar" => "JQ",
                                                        "cathay" => "CX",
                                                        "malaysia" => "MH",
                                                        "qantas" => "QF",
                                                        "eithad" => "EY"
                                                    );
                                                    $trip_wp_title = get_post_field('post_title', $trip_id_org);
                                                    $array_source = explode(" ", $trip_wp_title);
                                                    $airline_name = '';
                                                    for($i = 0; $i < count($array_source); $i++){
                                                        $val = strtolower(preg_replace('/-/','',$array_source[$i]));
                                                        if(in_array($val, $airline_arr)){
                                                            $airline_name = $val;
                                                            break;
                                                        }
                                                    }
                                                    
                                                    if(isset($airline_obj[$airline_name]) && $airline_obj[$airline_name] != '')
                                                    {
                                                        $initial_flight = $airline_obj[$airline_name];
                                                    }
                                                    else
                                                    {
                                                        $initial_flight = '';
                                                    }
                                                    
                                                    ?>
                                            </h2>
                                            <div class="grid-layout-4">
                                                <div class="src-dts-info">
                                                    <p class="destination">
                                                        <?php 
                                                        $src_title_depart=$itinerary_location_array[0];
                                                        $offset = strpos($src_title_depart,"(");
                                                        echo substr($src_title_depart, $offset+1, 3);   
                                                        ?>
                                                    </p>
                                                    <span class="destination-content">
                                                        <?php
                                                        $trip_wp_title = get_post_field('post_title', $trip_id_org);
                                                        $array_source = explode(" ", $trip_wp_title);
                                                        echo $array_source[0];
                                                        ?>
                                                    </span>
                                                    <span class="destination-content display-inlne">
                                                        <?php
                                                        echo "(" . $itinerary_time_array[0] . ")";
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="journey-time">
                                                    <i class='fa fa-arrow-right'></i>
                                                    <span class="destination-content">
                                                        <?php echo $total_duration; ?>
                                                    </span>
                                                </div>
                                                <div class="src-dts-info">
                                                    <p class="destination">
                                                        <?php 
                                                        $src_title_arr=$itinerary_location_array[count($itinerary_location_array)-1];
                                                        $offset = strpos($src_title_arr,"(");
                                                        echo substr($src_title_arr, $offset+1, 3); 
                                                        ?>
                                                    </p>
                                                    <span class="destination-content">
                                                        <?php
                                                        $trip_wp_title = get_post_field('post_title', $outbound_post->ID);
                                                        $array_destination = explode(" ", $trip_wp_title);
                                                        echo $array_destination[2];
                                                        ?>
                                                    </span>
                                                    <span class="destination-content display-inlne">
                                                        <?php
                                                        echo "(" . $itinerary_time_array[count($itinerary_time_array) - 1] . ")";
                                                        ?>
                                                    </span>
                                                </div>
                                            </div>
                            
                                            <?php if ($has_complimentary_lounge) : ?>
                                                <div>
                                                    <img class="complimentary-lounge-img" src=<?php echo "https://".$_SERVER['SERVER_NAME']."/wp-content/uploads/2023/09/img-lounge.png"?> alt="Complimentary Lounge" />
                                                </div>
                                            <?php endif; ?>
                            
                                            
                                        </div>
                                        <div class="extra">
                                            
                                            
                                            <?php
                                                    $wptravel_travel_outline = get_post_meta( $trip_id_org, 'wp_travel_outline', true );
                                                	$adult_baggage_pattern = '/Adult:\s*(\d+)\s*Kg/i';
                                                    // Use preg_match to capture the value
                                                    if (preg_match($adult_baggage_pattern, $wptravel_travel_outline, $adult_matches)) {
                                                        $adult_kg_value = $adult_matches[1];
                                                    } else {
                                                        $adult_kg_value = '';
                                                    }
                                                    
                                                    $child_baggage_pattern = '/Child:\s*(\d+)\s*Kg/i';
                                                    // Use preg_match to capture the value
                                                    if (preg_match($child_baggage_pattern, $wptravel_travel_outline, $child_matches)) {
                                                        $child_kg_value = $child_matches[1];
                                                    } else {
                                                        $child_kg_value = '';
                                                    }
                                                    
                                                    if ($adult_kg_value != '' && $child_kg_value != '' ) 
                                                    {
                                                        $lowest_kg_value = max($adult_kg_value, $child_kg_value);
                                                        $final_baggage = '<i class="fa fa-suitcase" aria-hidden="true"></i> '.$lowest_kg_value.'kg';
                                                    } 
                                                    else 
                                                    {
                                                        $final_baggage = "";
                                                    }
                                                    ?>
                                                    
                                            
                                            <div class="month">
                                                <?php
                                                if( $sale_price_loop == 0 && $available_dates != '')
                                                {
                                                    echo '<table style="border:0; margin:0px; padding:0px;"><tr style="border:0; margin:0px; padding:0px;"><td style="border:0; margin:0px; padding:0px;">'.$available_month .'</td><td style="border:0; margin:0px; padding:0px;">'. $available_dates.'</td></tr></table>';
                                                }
                                                else
                                                {
                                                    echo '<table style="border:0; margin:0px; padding:0px;"><tr style="border:0; margin:0px; padding:0px;"><td style="border:0; margin:0px; padding:0px;">'.$available_month .'</td><td style="border:0; margin:0px; padding:0px;">'. $available_dates.'</td></tr></table>';
                                                    ?>
                                                    <!--
                                                    <i class="fa fa-calendar" style="color: #8a8a8a;">
                                                        <span style="font-family: Poppins, sans-serif;font-weight: 400;">
                                                            <?php
                                                        $trip_wp_title = get_post_field('post_title', $trip_id_org);
                                                        $array_source = explode(" ", $trip_wp_title);
                                                        echo $array_source[count($array_source)-1];
                                                    ?>
                                                        </span>
                                                    </i>-->
                                                <?php
                                                }
                                                ?>
                                            </div><button class="baggage_button" style="background-color:black; color: #ffbb00; border-radius: 5px; width:100px; margin-top:7px; height:30px; padding:1px !important; font-size:12px;">
                                                        <?php echo $final_baggage; ?>
                                                    </button>
                                            <button class="toggle_itinerary_button" style="background-color:black; color: #ffbb00; border-radius: 5px; width:100px; margin-top:7px; height:30px; padding:1px !important; font-size:12px;" id="button_<?php echo $row_going_id; ?>" onclick="toggleItinerary(<?php echo $row_going_id; ?>)">
                                                <i class="fa fa-chevron-down"></i> Itinerary
                                        </button>
                                            <?php if (1 == 2 && $has_complimentary_lounge) : ?>
                                            <div class="complimentary-lounge-txt">
                                                <i class="fa fa-coffee" style="color: #8a8a8a;">
                                                    <span style="font-family: Poppins, sans-serif;font-weight: 400;">
                                                        Complimentary Lounge
                                                    </span>
                                                </i>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="complimentary-lounge-txt">
                                                <?php
                                                echo '<div class="itinerary_div" style="display:none;" id="'.$row_going_id.'_trip_itinerary">';
                                                $is_itinerary = true;
                                                if($is_itinerary)
                                                {
                                                	$traveldate_fxed = $dep_date_ymd;
                                                	
                                                	$wptravel_itineraries_r = get_post_meta( $trip_id_org, 'wp_travel_trip_itinerary_data', true );
                                                	$trip_wp_title = get_post_field( 'post_title', $trip_id_org );
                                                	$wptravel_travel_outline = get_post_meta( $trip_id_org, 'wp_travel_outline', true );
                                                	$productid_for_wptravel_product = $trip_id_org;
                                                	
                                                	if ( isset( $wptravel_itineraries_r ) && ! empty( $wptravel_itineraries_r ) ) : 
                                                		$wptravel_index = 1;
                                                		$itinerary_location_array = array();
                                                		$itinerary_time_array = array();
                                                		$itinerary_flight_array = array();
                                                		$itinerary_date_array = array();
                                                		$itinerary_datedecider_array = array();
                                                		$itinerary_array_counter = 0;
                                                		$itinerary_counter=0;	
                                                		foreach ( $wptravel_itineraries_r as $wptravel_itinerary ) : 
                                                			$wptravel_time_format = get_option( 'time_format' );
                                                			$wptravel_itinerary_label = '';
                                                			$wptravel_itinerary_title = '';
                                                			$wptravel_itinerary_desc  = '';
                                                			$wptravel_itinerary_date  = '';
                                                			$wptravel_itinerary_time  = '';
                                                			$itinerary_counter = 1;
                                                			$is_itinerary_available = 1;
                                                				$wptravel_itinerary_label = stripslashes( $wptravel_itinerary['label'] );
                                                			
                                                				$wptravel_itinerary_title = stripslashes( $wptravel_itinerary['title'] );
                                                			
                                                				$wptravel_itinerary_desc = stripslashes( $wptravel_itinerary['desc'] );
                                                			
                                                				$wptravel_itinerary_date = wptravel_format_date( $wptravel_itinerary['date'] );
                                                			
                                                				$wptravel_itinerary_time = stripslashes( $wptravel_itinerary['time'] );
                                                				$wptravel_itinerary_time = date( $wptravel_time_format, strtotime( $wptravel_itinerary_time ) ); 
                                                		    $itinerary_location_array[$itinerary_array_counter] = $wptravel_itinerary_label; // destination
                                                			$itinerary_time_array[$itinerary_array_counter] = $wptravel_itinerary_time; // flight time
                                                			$itinerary_flight_array[$itinerary_array_counter] = $wptravel_itinerary_title; // flight number
                                                			$itinerary_date_array[$itinerary_array_counter] = $traveldate_fxed; // flight date
                                                			$itinerary_datedecider_array[$itinerary_array_counter] = strip_tags($wptravel_itinerary_desc); // arrival or departure define
                                                					
                                                			$wptravel_index++;
                                                			$itinerary_array_counter++;
                                                			
                                                		endforeach;
                                                	endif; 
                                                	// DEFINE EXTRA DAYS
                                                    echo '</br></br>';
                                                    
                                                    $wptravel_travel_outline = get_post_meta( $productid_for_wptravel_product, 'wp_travel_outline', true );
                                                    //$pattern_to_remove_itinerary_table = '/<table class="tg">(.*?)<\/table>/s';
                                                    //$wptravel_travel_outline = preg_replace($pattern_to_remove_itinerary_table, '', $wptravel_travel_outline);
                                                    $pattern_to_remove_itinerary_table = '/Want to book(.*?)\[\/embed\]/s';
                                                    $wptravel_travel_outline = preg_replace($pattern_to_remove_itinerary_table, '', $wptravel_travel_outline);
    
                                                	//$itinerary_vals .= $wptravel_travel_outline;
                                                	echo $wptravel_travel_outline;
                                                	
                                                	$departure_date_plus_one = date("d/m/Y", strtotime("1 day", strtotime($traveldate_fxed))); 
                                                	$departure_date_plus_two = date("d/m/Y", strtotime("2 day", strtotime($traveldate_fxed)));
                                                	$departure_date_plus_three = date("d/m/Y", strtotime("3 day", strtotime($traveldate_fxed)));
                                                	$departure_date_plus_four = date("d/m/Y", strtotime("4 day", strtotime($traveldate_fxed)));
                                                    if ( is_array( $itinerary_location_array ) ) {
                                                	$length_aray = count($itinerary_location_array);
                                                	$itinerary_vals = '<center><table class="m_-8969220568537220410 tripitinerary wp-travel-table-content trip_'.$trip_id_org.'" cellpadding="0" cellspacing="0" style="width:100%; text-align:left; border: 1px solid #e1e1e; border-collapse: collapse; margin:10px 0px 10px 0px; font-size:14px;">
                                                	<thead>
                                                	  <tr style="background: #f0f0f0; border:none;">
                                                		 <th style="width:20%">From</th>
                                                		 <th style="width:20%">To</th>
                                                		 <th style="width:20%">Flight</th>
                                                		 <th style="width:20%">Departure</th>
                                                		 <th style="width:20%">Arrival</th>
                                                	  </tr>
                                                	</thead>
                                                	<tbody>';
                                                            }
                                                            else {
                                                    // Handle the case when $itinerary_location_array is null
                                                    $itinerary_vals .= '<p>No itinerary locations found.</p>';
                                                    }
                                                	// SECTION TO DIVIDE WAITING, SELF TRANSFER AND FLIGHT INFO
                                                	$is_printed_destination = '';
                                                	for ($i = 0; $i < $length_aray; $i++) {
                                                		if($itinerary_location_array[$i] == 'WAIT')
                                                		{
                                                			$is_printed_destination = '';
                                                		}
                                                		else if($itinerary_location_array[$i] == 'SELF-TRANSFER')
                                                		{
                                                			$is_printed_destination = '';
                                                		}
                                                		else
                                                		{
                                                			if ($is_printed_destination == '') 
                                                            {
                                                                 
                                                                $date1 = date("Y-m-d", strtotime($itinerary_datedecider_array[$i]))." ".date("H:i:s", strtotime($itinerary_time_array[$i]));
                                                                if(isset($itinerary_datedecider_array[$i+1]) && isset($itinerary_time_array[$i+1]))
                                                                {
                                                                    $date2 = date("Y-m-d", strtotime($itinerary_datedecider_array[$i+1]))." ".date("H:i:s", strtotime($itinerary_time_array[$i+1]));
                                                                    $dateDiff = intval((strtotime($date2) - strtotime($date1)) / 60);
                                                                }
                                                                else
                                                                {
                                                                    $dateDiff = intval((strtotime($date1) - strtotime($date1)) / 60);
                                                                }
                                                                
                                                                $hours = intval($dateDiff / 60); 
                                                                $minutes = $dateDiff % 60;
                                                                
                                                                $time_duration = $hours.":".$minutes;
                                                                $airline_from_itinerary = substr($itinerary_flight_array[$i],0,2);
                                                    
                                                				$itinerary_vals .= "<tr>";
                                                				$itinerary_vals .= '<td style="width:20%">'.$itinerary_location_array[$i].'</td>';
                                                				if(isset($itinerary_location_array[$i+1])) { 
                                                				    $itinerary_vals .= '<td style="width:20%">'.$itinerary_location_array[$i+1].'</td>';
                                                				}
                                                				$itinerary_vals .= '<td style="width:20%">'.$itinerary_flight_array[$i].'</td>';
                                                				$itinerary_vals .= '<td style="width:20%">'.$itinerary_time_array[$i].'</br>'.$itinerary_datedecider_array[$i].'</td>';
                                                				if(isset($itinerary_time_array[$i+1]) && isset($itinerary_datedecider_array[$i+1])) { 
                                                				    $itinerary_vals .= '<td style="width:20%">'.$itinerary_time_array[$i+1].'</br>'.$itinerary_datedecider_array[$i+1].'</td>';
                                                				}
                                                				$itinerary_vals .= "</tr>";
                                                				$empty = '';
                                                				$itinerary_vals .= "<tr style='border:none;'>";
                                                				$itinerary_vals .= '<td colspan="2" style="border:none;">   Class: Economy
                                                				           </td>';
                                                				$itinerary_vals .= '<td colspan="2" style="border:none;">Operated by: '.$airline_from_itinerary.'</td>';
                                                				$itinerary_vals .= '<td style="border:none;"></td>';
                                                				$itinerary_vals .= "</tr>";
                                                				
                                                				$is_printed_destination = 'yes';
                                                            }
                                                            else
                                                            {
                                                                $is_printed_destination = '';
                                                            }
                                                		}
                                                	}
                                                	$itinerary_vals .= "</tbody></table></center>";
                                                	
                                                	// REPLACE ALL THE FIXED WORDS AS DEPARTURE AND ARRIVAL ON BACKEND
                                                	$itinerary_vals = str_replace("Departure Date", '' ,$itinerary_vals);
                                                	$itinerary_vals = str_replace("Arrival Date", '' ,$itinerary_vals);
                                                	
                                                	$itinerary_vals = str_replace("Selected Date +4", $departure_date_plus_four ,$itinerary_vals);
                                                	$itinerary_vals = str_replace("Selected Date+4", $departure_date_plus_four ,$itinerary_vals);
                                                	
                                                	$itinerary_vals = str_replace("Selected Date +3", $departure_date_plus_three ,$itinerary_vals);
                                                	$itinerary_vals = str_replace("Selected Date+3", $departure_date_plus_three ,$itinerary_vals);
                                                	
                                                	$itinerary_vals = str_replace("Selected Date +2", $departure_date_plus_two ,$itinerary_vals);
                                                	$itinerary_vals = str_replace("Selected Date+2", $departure_date_plus_two ,$itinerary_vals);
                                                	
                                                	$itinerary_vals = str_replace("Selected Date+1", $departure_date_plus_one ,$itinerary_vals);
                                                	$itinerary_vals = str_replace("Selected Date +1", $departure_date_plus_one ,$itinerary_vals);
                                                	
                                                	$traveldate_fxed_1 = date("d/m/Y", strtotime($traveldate_fxed)); 
                                                	
                                                	
                                                	$itinerary_vals = str_replace("Selected Date", $traveldate_fxed_1 ,$itinerary_vals);
                                                	
                                                	$itinerary_vals = str_replace("Selected Date", $traveldate_fxed_1 ,$itinerary_vals);
                                                	
                                                	
                                                	$trip_code_post = get_post_meta($trip_id_org, 'wp_travel_trip_code', true);
	                                                $itinerary_details = getGDealFlightItinerary($trip_code_post, $dep_date_ymd);
	                                                if($itinerary_details != '')
	                                                {
                                                	    echo $itinerary_details;
	                                                }
	                                                else
	                                                {
	                                                    echo $itinerary_vals;
	                                                }
                                                	
                                                	$order_id_for_itinerary = '42342434';
                                                	$wptravel_product_information_ordered = get_post_meta( $order_id_for_itinerary, 'order_items_data', true ); // get order product info for trip extras
                                                    if (is_array($wptravel_product_information_ordered) || is_object($wptravel_product_information_ordered)) 
                                                    {
                                                        foreach ($wptravel_product_information_ordered as $key => $value) {
                                                            // Check if the 'trip_extras' key and 'id' key exist
                                                            if (isset($value['trip_extras']['id'])) {
                                                                // Extract "trip_extras" IDs
                                                                $tripExtrasIds = $value['trip_extras']['id'];
                                                                echo '<h6>Additional services</h6>';
                                                                // Print or use the values as needed
                                                                foreach ($tripExtrasIds as $value) {
                                                                    echo '<li>'.get_the_title( $value ).'</li>';
                                                                }
                                                            } else {
                                                                echo "No additional services available.";
                                                            }
                                                        }
                                                	}
                                                	echo '</br>';
                                                }
                                                echo '</div>';
                                                ?>
                                            </div>
                                    </div>
                                    <div class="price_tag_main">
                                        <div class="price_column_1">&nbsp;</div>
                                        <div class="price_column_2">
                                        <!--
                                        <div class="offer-price">
                                            <?php apply_filters('wp_trave_archives_page_trip_save_offer', wptravel_save_offer($outbound_post->ID), $outbound_post->ID); ?>
                                            <?php if ($sale_price_loop != 0)
                                            {
                                                ?>
                                                <label class="discounted-price">
                                                    <?php
                                                    echo apply_filters('wp_travel_archives_page_trip_price', wptravel_get_formated_price_currency($sale_price_loop), $outbound_post->ID); //phpcs:ignore 
                                                    if($is_discount_on == 1 && $discount_percentage != '')
                                                    {
                                                        //$sale_price_loop = $sale_price_loop - ($sale_price_loop * 0.10);
                                                    }
                                                    $individual_amount_for_tour = (float)$sale_price_loop;
                                                    $total_amount_for_tour += (float)$sale_price_loop;
                                                    ?>
                                                </label>
                                                <?php
                                            }
                                            else
                                            {
                                                ?>
                                                <label class="discounted-price">
                                                <?php
                                                   echo apply_filters('wp_travel_archives_page_trip_price', wptravel_get_formated_price_currency($trip_price), $outbound_post->ID); //phpcs:ignore 
                                                   $is_monthly_stock_note_to_show = 1;
                                                   if($is_discount_on == 1 && $discount_percentage != '')
                                                    {
                                                        //$trip_price = $trip_price - ($trip_price * 0.10);
                                                    }
                                                   $individual_amount_for_tour = (float)$trip_price;
                                                   $total_amount_for_tour += (float)$trip_price;
                                                ?>
                                                </label>
                                                <?php
                                            }
                                            ?>
                                        </div>-->
                                        <!--<button class="book-now" id="return_add_booking"><a href="<?php echo $outbound_trip_link; ?>">View</a></button>-->
                                        <?php
                                            if(intval($lowest_kg_value) > 30)
                                                {
                                                    ?>
                                                    <style>
                                                        @keyframes blink {
                                                            0%, 100%, 40%, 60% { opacity: 1; }
                                                            50% { opacity: 0; }
                                                        }
                                                    
                                                        .baggageblink {
                                                            
                                                            animation: blink 1s infinite !important;
                                                        }
                                                    </style>
                                                    <style>
                                                       .baggage_tag {
                                                            background-color: #ff9900;
                                                            color: white !important;
                                                            border: 2px solid #ff9900;
                                                            padding: 6px 35px;
                                                            font-size: 13px;
                                                            font-weight: bold;
                                                            text-align: center;
                                                            cursor: pointer;
                                                            display: inline-block;
                                                            border-radius: 5px 50px 5px 5px; /* Tag shape */
                                                            position: relative;
                                                            margin-top:25px;
                                                            text-decoration: none;
                                                            box-shadow: 3px 4px 8px rgba(0, 0, 0, 0.3);
                                                        }
                                                    
                                                        /* Hole effect to make it look like a luggage tag */
                                                        .baggage_tag::before {
                                                            content: "";
                                                            width: 10px;
                                                            height: 10px;
                                                            background-color: white;
                                                            border-radius: 50%;
                                                            position: absolute;
                                                            top: 8px;
                                                            left: 8px;
                                                            border: 2px solid #ff9900;
                                                        }
                                                    
                                                        /* String effect */
                                                        .baggage_tag::after {
                                                            content: "";
                                                            width: 4px;
                                                            height: 25px;
                                                            background-color: #ff9900;
                                                            position: absolute;
                                                            top: -25px;
                                                            left: 12px;
                                                            border-radius: 2px;
                                                        }
                                                    
                                                        /* Hover effect */
                                                        .baggage_tag:hover {
                                                            background-color: #ff9900;
                                                            box-shadow: 4px 6px 12px rgba(0, 0, 0, 0.5);
                                                        }
                                                    </style>
                                                    
                                                    <a href="#" class="baggage_tag">
                                                        Baggage <br>
                                                        <font class="baggageblink" style="font-size:17px;"><?php echo $final_baggage; ?></font>
                                                    </a>
                                                    <?php
                                                }
                                            ?>
                                         </div>   
                                    </div>
                                </div>
                            </div>
                            <?php
                            $trip_extras_ids = explode(',', $trip_extras_string);
    
                            // Create an array of quantities, all set to 1
                            $trip_extras_qty = array_fill(0, count($trip_extras_ids), 1);
                            
                            $individual_amount_for_tour_pax = $individual_amount_for_tour * $pax_count ;
                            
                            if( $is_coupon_on == 1 && $is_count_amount != '' )
                            {
                                $individual_amount_for_tour_pax = (float)$individual_amount_for_tour_pax - (float)$is_count_amount;
                            }
                            
                            $individual_total_partial_amount = ($individual_amount_for_tour_pax * 0.05) ;
                            $individual_partial_amount = ($individual_amount_for_tour * 0.05);
                            
                            
                                        
                            if($is_promotion_on == 1 && $promotion_deposit_amount != '' )
                            {
                                $individual_partial_amount = $promotion_deposit_amount;
                                $individual_total_partial_amount = $promotion_deposit_amount * $pax_count;
                            }
                            
                            $depatrue_pricing_id = $pricing_id_loop;
                            //echo $depatrue_pricing_id;
                                        
                            // Create the array structure for the current trip package
                            $cart_array_combination_ongoing = array(
                                'max_available' => $max_pax,
                                'min_available' => $min_pax,
                                'trip_start_date' => $dep_date_ymd,
                                'currency' => '$',
                                'trip' => array(),
                                'enable_partial' => 1,
                                'partial_payout_figure' => $promotion_deposit_amount,
                                'trip_price_partial' => $individual_total_partial_amount,
                                'pricing_id' => $pricing_id_loop,
                                'arrival_date' => $dep_date_ymd,
                                'date_id' => $date_id_loop,
                                'departure_date' => $dep_date_ymd,
                                'trip_extras' => array(
                                    'id' => $trip_extras_ids,
                                    'qty' => $trip_extras_qty
                                ),
                                'trip_id' => $trip_id_org,
                                'trip_price' => $individual_amount_for_tour_pax,
                                'pax' => $pax_count,
                                'price_key' => ''
                            );
                        
                            $cart_array_combination_ongoing['trip']['953'] = array(
                                'pax' => $adult_count,
                                'price' => $individual_amount_for_tour,
                                'price_partial' => $individual_partial_amount,
                                'type' => 'custom',
                                'custom_label' => 'Adult',
                                'price_per' => 'person'
                            );
                            
                            $cart_array_combination_ongoing['trip']['954'] = array(
                                'pax' => $child_count,
                                'price' => $individual_amount_for_tour,
                                'price_partial' => $individual_partial_amount,
                                'type' => 'custom',
                                'custom_label' => 'Child',
                                'price_per' => 'person'
                            );
    
                            // Add this combination to the main $cart_array with a unique key
                            $unique_key = $trip_id_org . '_' . $dep_date_ymd . '_' . $pricing_id_loop;
                            $cart_array[$unique_key] = $cart_array_combination_ongoing;
    
                            $row_going_id++;
                        }
                        
                        // RETURN
                        {
                            $cart_array_combination_return = array();
                            
                            global $wp_travel_itinerary;
                            $trip_id_org = $return_post->ID;
                            $enable_sale = WP_Travel_Helpers_Trips::is_sale_enabled(array('trip_id' => $return_post->ID));
                            $group_size  = wptravel_get_group_size($return_post->ID);
                            $start_date  = get_post_meta($return_post->ID, 'wp_travel_start_date', true);
                            $end_date    = get_post_meta($return_post->ID, 'wp_travel_end_date', true);
                            
                            $return_trip_link = get_permalink($return_post->ID);
    
                            $args  = $args_regular = array('trip_id' => $return_post->ID); // phpcs:ignore
            
                            $args_regular['is_regular_price'] = true;
                                
                            $regular_price_loop = 0;
                            $sale_price_loop = 0;
                            
                            $min_pax = 0;
                            $min_pax = 1;
                            $trip_extras_string = '';
                            
                            $get_results_by_dep_date = $wpdb->get_results( "
                            SELECT dates.start_date, dates.id, dates.pricing_ids FROM wpk4_wt_dates dates LEFT JOIN wpk4_wt_excluded_dates_times AS exclude 
                                                        ON dates.trip_id = exclude.trip_id AND dates.start_date = exclude.start_date 
                                    WHERE dates.trip_id = '$trip_id_org' AND date(dates.start_date) > CURRENT_DATE AND dates.start_date LIKE '$ret_date_ymd'
                                    AND exclude.start_date IS NULL
                            "); 
                            
                            foreach($get_results_by_dep_date as $get_row_by_dep_date )
                            { 
                                $pricing_id_loop = $get_row_by_dep_date->pricing_ids;
                                $date_id_loop = $get_row_by_dep_date->id;
                                
                                $get_extras_by_pricingid = $wpdb->get_results( "SELECT min_pax, max_pax, trip_extras FROM wpk4_wt_pricings where id='$pricing_id_loop'"); 
                                foreach($get_extras_by_pricingid as $get_extra_by_pricingid )
                                { 
                                    $trip_extras_string = $get_extra_by_pricingid->trip_extras;
                                    $min_pax = $get_extra_by_pricingid->min_pax;
                                    $max_pax = $get_extra_by_pricingid->max_pax;
                                }
                                
                                $get_results_by_pricingid = $wpdb->get_results( "SELECT regular_price, sale_price FROM wpk4_wt_price_category_relation where pricing_id='$pricing_id_loop' AND pricing_category_id='953'"); 
                                foreach($get_results_by_pricingid as $get_row_by_pricingid )
                                { 
                                    $regular_price_loop = $get_row_by_pricingid->regular_price;
                                    $sale_price_loop = $get_row_by_pricingid->sale_price;
                                }
                            }
                                
                            $available_dates = '';
                            $available_month = '';
                            $loop_counter = 0;
                            $is_break = 0;
                            
                            $available_dates .= '<div class="carousel-container" data-product-carousel>';
                            $available_dates .= '<div class="carousel-arrow left-arrow" data-arrow="left"><i class="fa fa-chevron-left" aria-hidden="true"></i></div>'; // Left arrow
                            $available_dates .= '<div class="dates-carousel">'; // Carousel for the dates
                            /*
                            if($dep_date_ymd_cropped != '')
                            {
                                $get_results_by_dep_month = $wpdb->get_results( "SELECT dates.start_date, dates.pricing_ids FROM wpk4_wt_dates dates 
                                    LEFT JOIN wpk4_wt_excluded_dates_times AS exclude ON dates.trip_id = exclude.trip_id AND dates.start_date = exclude.start_date
                                    WHERE dates.trip_id = '$trip_id_org' AND date(dates.start_date) > CURRENT_DATE AND dates.start_date LIKE '$dep_date_ymd_cropped%' AND exclude.start_date IS NULL"); 
                                foreach($get_results_by_dep_month as $get_row_by_dep_month )
                                { 
                                    $pricing_id_month_loop = $get_row_by_dep_month->pricing_ids;
                                    $start_date_month_loop = $get_row_by_dep_month->start_date;
                        
                                    $get_results_by_month_pricingid = $wpdb->get_results( "SELECT regular_price, sale_price FROM wpk4_wt_price_category_relation where pricing_id='$pricing_id_month_loop' AND pricing_category_id='953'"); 
                                    foreach($get_results_by_month_pricingid as $get_row_by_month_pricingid )
                                    { 
                                        $regular_price_month_loop = $get_row_by_month_pricingid->regular_price;
                                        $sale_price_month_loop = $get_row_by_month_pricingid->sale_price;
                                        
                                        
                        
                                        $available_month = '<span style="font-size:13px; font-weight:700; padding:5px 6px; background-color:#ffbb00; color:black; margin:0px 5px; border-radius:3px 3px 3px 3px; white-space: nowrap; ">'.date('M', strtotime($start_date_month_loop)).'</span>';
                                        
                                        if($ret_date_ymd == $start_date_month_loop)
                                        {
                                            $available_dates .= '<span id="payment_amount_selector" class="pay_selected" style="width:100px !important; display: inline-block; white-space: nowrap; font-size:13px; padding:5px 6px; border:1px solid #ffbb00; color:black; margin:5px 3px 5px 3px !important; border-radius:3px 3px 3px 3px;">
                                                        <i class="fa fa-calendar-o" style="color:#210301;"></i> <b>'.date('d', strtotime($start_date_month_loop)) . '</b> - $<font style="font-size:14px; font-weight:600;">' . $sale_price_month_loop.'</font></span>';
                                        }
                                        else
                                        {
                                            $available_dates .= '<span id="payment_amount_selector" class="pay_not_selected" onclick="updateURLWithOnGoingDate(\'' . $start_date_month_loop . '\')"  style="cursor: pointer; width:100px !important; display: inline-block; white-space: nowrap; font-size:13px; padding:5px 6px; border:1px solid #ffbb00; color:black; margin:5px 3px 5px 3px !important; border-radius:3px 3px 3px 3px;">
                                                        <i class="fa fa-calendar-o" style="color:#210301;"></i> <b>'.date('d', strtotime($start_date_month_loop)) . '</b> - $<font style="font-size:14px; font-weight:600;">' . $sale_price_month_loop.'</font></span>';
                                        }
                                        
                                        $loop_counter++;
                                    }
                                    
                                    if($is_break == 1)
                                    {
                                        break;
                                    }
                                }
                            }*/
                           
                            
                            if($ret_date_ymd_cropped != '')
                            {
                                $loop_counter = 0;
                                $is_break = 0;
                                $get_results_by_dep_month = $wpdb->get_results( "SELECT dates.start_date, dates.pricing_ids FROM wpk4_wt_dates dates LEFT JOIN wpk4_wt_excluded_dates_times AS exclude 
                                                        ON dates.trip_id = exclude.trip_id AND date(dates.start_date) > CURRENT_DATE AND dates.start_date = exclude.start_date WHERE dates.trip_id = '$trip_id_org' AND dates.start_date LIKE '$ret_date_ymd_cropped%' 
                                                        AND exclude.start_date IS NULL "); 
                                foreach($get_results_by_dep_month as $get_row_by_dep_month )
                                { 
                                    $pricing_id_month_loop = $get_row_by_dep_month->pricing_ids;
                                    $start_date_month_loop = $get_row_by_dep_month->start_date;
                        
                                    $get_results_by_month_pricingid = $wpdb->get_results( "SELECT regular_price, sale_price FROM wpk4_wt_price_category_relation where pricing_id='$pricing_id_month_loop' AND pricing_category_id='953'"); 
                                    
                                    foreach($get_results_by_month_pricingid as $get_row_by_month_pricingid )
                                    { 
                                        $regular_price_month_loop = $get_row_by_month_pricingid->regular_price;
                                        $sale_price_month_loop = $get_row_by_month_pricingid->sale_price;
                                        
                                        
                                        $available_month = '<span style="font-size:13px; font-weight:700; padding:5px 6px; background-color:#ffbb00; color:black; margin:0px 5px; border-radius:3px 3px 3px 3px; white-space: nowrap; ">'.date('M', strtotime($start_date_month_loop)).'</span>';
                                        
                                        if($ret_date_ymd == $start_date_month_loop)
                                        {
                                            $available_dates .= '<span id="payment_amount_selector" class="pay_selected" style="width:100px !important; display: inline-block; white-space: nowrap; font-size:13px; padding:5px 6px; border:1px solid #ffbb00; color:black; margin:5px 3px 5px 3px !important; border-radius:3px 3px 3px 3px;">
                                                        <i class="fa fa-calendar-o" style="color:#210301;"></i> <b>'.date('d', strtotime($start_date_month_loop)) . '</b> - $<font style="font-size:14px; font-weight:600;">' . $sale_price_month_loop.'</font></span>';
                                        }
                                        else
                                        {
                                            $available_dates .= '<span id="payment_amount_selector" onclick="updateURLWithReturnDate(\'' . $start_date_month_loop . '\')"  class="pay_not_selected" style="cursor: pointer; width:100px !important; display: inline-block; white-space: nowrap; font-size:13px; padding:5px 6px; border:1px solid #ffbb00; color:black; margin:5px 3px 5px 3px !important; border-radius:3px 3px 3px 3px;">
                                                        <i class="fa fa-calendar-o" style="color:#210301;"></i> <b>'.date('d', strtotime($start_date_month_loop)) . '</b> - $<font style="font-size:14px; font-weight:600;">' . $sale_price_month_loop.'</font></span>';
                                        }
                                        
                                        $loop_counter++;
                                    }
                                    
                                    if($is_break == 1)
                                    {
                                        break;
                                    }
                                }
                            }
                            
                            $available_dates .= '</div>'; // Close .dates-carousel
                            $available_dates .= '<div class="carousel-arrow right-arrow" data-arrow="right"><i class="fa fa-chevron-right" aria-hidden="true"></i></div>'; // Right arrow
                            $available_dates .= '</div>'; // Close .carousel-container
                        
                            $trip_price = WP_Travel_Helpers_Pricings::get_price($args);
                            $regular_price = WP_Travel_Helpers_Pricings::get_price($args_regular);
                        
                            $locations = get_the_terms($return_post->ID, 'travel_locations');
                            $trip_locations = get_the_terms($return_post->ID, 'travel_locations');
                            $location_name = '';
                            $location_link = '';
                            if ($locations && is_array($locations)) 
                            {
                                $first_location = array_shift($locations);
                                $location_name  = $first_location->name;
                                $location_link  = get_term_link($first_location->term_id, 'travel_locations');
                            }
                            
                            $is_trip_for_live = '';
                            $is_trip_for_live = get_post_meta($return_post->ID, '_yoast_wpseo_primary_status', true);
                            
                            $wp_travel_trip_itinerary_data_org = get_post_meta($trip_id_org, 'wp_travel_trip_itinerary_data', true);
                            
                            // fetch product details
                            if (isset($wp_travel_trip_itinerary_data_org) && !empty($wp_travel_trip_itinerary_data_org))
                            {
                                $wptravel_index = 1;
                                $itinerary_location_array = array();
                                $itinerary_time_array = array();
                                $itinerary_flight_array = array();
                                $itinerary_date_array = array();
                                $itinerary_datedecider_array = array();
                                $itinerary_array_counter = 0;
                                $itinerary_counter = 0;
                                
                                foreach ($wp_travel_trip_itinerary_data_org as $wptravel_itinerary)
                                {
                                    $wptravel_time_format = get_option('time_format');
                                    $wptravel_itinerary_label = '';
                                    $wptravel_itinerary_title = '';
                                    $wptravel_itinerary_desc  = '';
                                    $wptravel_itinerary_date  = '';
                                    $wptravel_itinerary_time  = '';
                                    $itinerary_counter = 1;
                                    $is_itinerary_available = 1;
                                    $wptravel_itinerary_label = stripslashes($wptravel_itinerary['label']);
                        
                                    $wptravel_itinerary_title = stripslashes($wptravel_itinerary['title']);
                        
                                    $wptravel_itinerary_desc = stripslashes($wptravel_itinerary['desc']);
                        
                                    $wptravel_itinerary_date = wptravel_format_date($wptravel_itinerary['date']);
                        
                                    $wptravel_itinerary_time = stripslashes($wptravel_itinerary['time']);
                                    $wptravel_itinerary_time = date($wptravel_time_format, strtotime($wptravel_itinerary_time));
                        
                                    $itinerary_location_array[$itinerary_array_counter] = $wptravel_itinerary_label; // destination
                                    $itinerary_time_array[$itinerary_array_counter] = $wptravel_itinerary_time; // flight time
                                    $itinerary_flight_array[$itinerary_array_counter] = $wptravel_itinerary_title; // flight number
                                    //$itinerary_date_array[$itinerary_array_counter] = $traveldate_fxed; // flight date
                                    $itinerary_datedecider_array[$itinerary_array_counter] = strip_tags($wptravel_itinerary_desc); // arrival or departure define
                        
                                    $wptravel_index++;
                                    $itinerary_array_counter++;
                                }
                            }
                            $wp_travel_outline_dom = '';
                            $journey_duration_ele = '';
                            $wptravel_travel_outline = get_post_meta($trip_id_org, 'wp_travel_outline', true );
                            if (!empty($wptravel_travel_outline))
                            {
                                $wp_travel_outline_dom = new DOMDocument();
                                $wp_travel_outline_dom->loadHTML($wptravel_travel_outline);
                                $journey_duration_ele = $wp_travel_outline_dom->getElementsByTagName('p');
                            }
                            
                        
                            $total_duration = "";
                            
                            foreach($journey_duration_ele as $node) 
                            {
                                $journery_dur = explode(":", $node->textContent)[0];
                                if(strtolower($journery_dur) == "journey duration")
                                {
                                    $total_duration = explode(":", $node->textContent)[1];
                                }
                            }
                        
                            $trip_wp_title = get_post_field('post_title', $trip_id_org);
                            $trip_title_arr = explode(" ", $trip_wp_title);
                        
                            $has_complimentary_lounge = false;
                            for($i = 0; $i < count($trip_title_arr); $i++)
                            {
                                if(str_contains(strtolower($trip_title_arr[$i]),'complimentary'))
                                {
                                    $has_complimentary_lounge = true;
                                }
                            }
                            ?>
                            <!-- Start of GDeals flight view -->
                            <div class="card" style="box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px -7px;">
                                <div class="card-content">
                                    <div class="deal">
                                        <div class="src-dts">
                                            <h2 class="m-0">
                                                <?php
                                                    // fetch airline images
                                                    $airline_arr = array("virgin australia" ,"thai","singapore","qatar" ,"srilanka","airindia","scoot","emirates","sabre",
                                                    "jetstar","cathay","malaysia","qantas","eithad");
                                                    $airline_obj = array(
                                                        "virgin australia" => "img-virginaus",
                                                        "thai" => "img-thai",
                                                        "singapore" => "img-singapore",
                                                        "qatar" => "img-qatar",
                                                        "srilanka" => "img-srilanka",
                                                        "airindia" => "img-airindia",
                                                        "scoot" => "img-scoot",
                                                        "emirates" => "img-emirates",
                                                        "sabre" => "img-sabre",
                                                        "jetstar" => "img-jetstar",
                                                        "cathay" => "img-cathay",
                                                        "malaysia" => "img-malaysia",
                                                        "qantas" => "img-qantas",
                                                        "eithad" => "img-eithad",
                                                        "default" => "img-default"
                                                    );
                                                    $trip_wp_title = get_post_field('post_title', $trip_id_org);
                                                    $array_source = explode(" ", $trip_wp_title);
                                                    $airline_name = '';
                                                    for($i = 0; $i < count($array_source); $i++){
                                                        $val = strtolower(preg_replace('/-/','',$array_source[$i]));
                                                        if(in_array($val, $airline_arr)){
                                                            $airline_name = $val;
                                                            break;
                                                        }
                                                    }
                            
                                                    if($airline_name == ''){
                                                        $airline_name = "default";
                                                    }
                            
                                                    $img_href ="https://".$_SERVER['SERVER_NAME']."/wp-content/uploads/2023/09/".$airline_obj[$airline_name].".png";
                                                    //echo '<p style="font-size:12px; letter-spacing:1px;">'.$trip_id_org . ' ' . $ret_date_ymd.'</p>';
                                                    ?>
                                                <a href="#"><img src=<?php echo $img_href ?> alt=<?php echo $airline_name ?> /></a>
                                            </h2>
                                            <div class="grid-layout-4">
                                                <div class="src-dts-info">
                                                    <p class="destination">
                                                        <?php 
                                                        $src_title_depart=$itinerary_location_array[0];
                                                        $offset = strpos($src_title_depart,"(");
                                                        echo substr($src_title_depart, $offset+1, 3);   
                                                        ?>
                                                    </p>
                                                    <span class="destination-content">
                                                        <?php
                                                        $trip_wp_title = get_post_field('post_title', $trip_id_org);
                                                        $array_source = explode(" ", $trip_wp_title);
                                                        echo $array_source[0];
                                                        ?>
                                                    </span>
                                                    <span class="destination-content display-inlne">
                                                        <?php
                                                        echo "(" . $itinerary_time_array[0] . ")";
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="journey-time">
                                                    <i class='fa fa-arrow-right'></i>
                                                    <span class="destination-content">
                                                        <?php echo $total_duration; ?>
                                                    </span>
                                                </div>
                                                <div class="src-dts-info">
                                                    <p class="destination">
                                                        <?php 
                                                        $src_title_arr=$itinerary_location_array[count($itinerary_location_array)-1];
                                                        $offset = strpos($src_title_arr,"(");
                                                        echo substr($src_title_arr, $offset+1, 3); 
                                                        ?>
                                                    </p>
                                                    <span class="destination-content">
                                                        <?php
                                                        $trip_wp_title = get_post_field('post_title', $return_post->ID);
                                                        $array_destination = explode(" ", $trip_wp_title);
                                                        echo $array_destination[2];
                                                        ?>
                                                    </span>
                                                    <span class="destination-content display-inlne">
                                                        <?php
                                                        echo "(" . $itinerary_time_array[count($itinerary_time_array) - 1] . ")";
                                                        ?>
                                                    </span>
                                                </div>
                                            </div>
                            
                                            <?php if ($has_complimentary_lounge) : ?>
                                                <div>
                                                    <img class="complimentary-lounge-img" src=<?php echo "https://".$_SERVER['SERVER_NAME']."/wp-content/uploads/2023/09/img-lounge.png"?> alt="Complimentary Lounge" />
                                                </div>
                                            <?php endif; ?>
                            
                                            
                                        </div>
                                        
                                        <div class="extra">
                                            
                                            <?php
                                                    $wptravel_travel_outline = get_post_meta( $trip_id_org, 'wp_travel_outline', true );
                                                	$adult_baggage_pattern = '/Adult:\s*(\d+)\s*Kg/i';
                                                    // Use preg_match to capture the value
                                                    if (preg_match($adult_baggage_pattern, $wptravel_travel_outline, $adult_matches)) {
                                                        $adult_kg_value = $adult_matches[1];
                                                    } else {
                                                        $adult_kg_value = '';
                                                    }
                                                    
                                                    $child_baggage_pattern = '/Child:\s*(\d+)\s*Kg/i';
                                                    // Use preg_match to capture the value
                                                    if (preg_match($child_baggage_pattern, $wptravel_travel_outline, $child_matches)) {
                                                        $child_kg_value = $child_matches[1];
                                                    } else {
                                                        $child_kg_value = '';
                                                    }
                                                    
                                                    if ($adult_kg_value != '' && $child_kg_value != '' ) 
                                                    {
                                                        $lowest_kg_value = max($adult_kg_value, $child_kg_value);
                                                        $final_baggage = '<i class="fa fa-suitcase" aria-hidden="true"></i> '.$lowest_kg_value.'kg';
                                                    } 
                                                    else 
                                                    {
                                                        $final_baggage = "";
                                                    }
                                                    ?>
                                                    
                                            
                                            <div class="month">
                                                <?php
                                                if( $sale_price_loop == 0 && $available_dates != '')
                                                {
                                                    echo '<table style="border:0; margin:0px; padding:0px;"><tr style="border:0; margin:0px; padding:0px;"><td style="border:0; margin:0px; padding:0px;">'.$available_month .'</td><td style="border:0; margin:0px; padding:0px;">'. $available_dates.'</td></tr></table>';
                                                }
                                                else
                                                {
                                                    echo '<table style="border:0; margin:0px; padding:0px;"><tr style="border:0; margin:0px; padding:0px;"><td style="border:0; margin:0px; padding:0px;">'.$available_month .'</td><td style="border:0; margin:0px; padding:0px;">'. $available_dates.'</td></tr></table>';
                                                    ?>
                                                    <!--
                                                    <i class="fa fa-calendar" style="color: #8a8a8a;">
                                                        <span style="font-family: Poppins, sans-serif;font-weight: 400;">
                                                            <?php
                                                        $trip_wp_title = get_post_field('post_title', $trip_id_org);
                                                        $array_source = explode(" ", $trip_wp_title);
                                                        echo $array_source[count($array_source)-1];
                                                    ?>
                                                        </span>
                                                    </i>-->
                                                <?php
                                                }
                                                ?>
                                            </div>
                                            <button class="baggage_button" style="background-color:black; color: #ffbb00; border-radius: 5px; width:100px; margin-top:7px; height:30px; padding:1px !important; font-size:12px;">
                                                        <?php echo $final_baggage; ?>
                                                    </button>
                                            <button class="toggle_itinerary_button" style="background-color:black; color: #ffbb00; border-radius: 5px; width:100px; margin-top:7px; height:30px; padding:1px !important; font-size:12px;" id="button_return_<?php echo $row_return_id; ?>" onclick="togglereturnItinerary(<?php echo $row_return_id; ?>)">
                                                <i class="fa fa-chevron-down"></i> Itinerary
                                            </button>
                                            <?php if (1 == 2 && $has_complimentary_lounge) : ?>
                                            <div class="complimentary-lounge-txt">
                                                <i class="fa fa-coffee" style="color: #8a8a8a;">
                                                    <span style="font-family: Poppins, sans-serif;font-weight: 400;">
                                                        Complimentary Lounge
                                                    </span>
                                                </i>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="complimentary-lounge-txt">
                                                <?php
                                                echo '<div class="itinerary_div" style="display:none;" id="'.$row_return_id.'_returntrip_itinerary">';
                                                $is_itinerary = true;
                                                if($is_itinerary)
                                                {
                                                	$traveldate_fxed = $ret_date_ymd;
                                                	
                                                	$wptravel_itineraries_r = get_post_meta( $trip_id_org, 'wp_travel_trip_itinerary_data', true );
                                                	$trip_wp_title = get_post_field( 'post_title', $trip_id_org );
                                                	$wptravel_travel_outline = get_post_meta( $trip_id_org, 'wp_travel_outline', true );
                                                	$productid_for_wptravel_product = $trip_id_org;
                                                	
                                                	if ( isset( $wptravel_itineraries_r ) && ! empty( $wptravel_itineraries_r ) ) : 
                                                		$wptravel_index = 1;
                                                		$itinerary_location_array = array();
                                                		$itinerary_time_array = array();
                                                		$itinerary_flight_array = array();
                                                		$itinerary_date_array = array();
                                                		$itinerary_datedecider_array = array();
                                                		$itinerary_array_counter = 0;
                                                		$itinerary_counter=0;	
                                                		foreach ( $wptravel_itineraries_r as $wptravel_itinerary ) : 
                                                			$wptravel_time_format = get_option( 'time_format' );
                                                			$wptravel_itinerary_label = '';
                                                			$wptravel_itinerary_title = '';
                                                			$wptravel_itinerary_desc  = '';
                                                			$wptravel_itinerary_date  = '';
                                                			$wptravel_itinerary_time  = '';
                                                			$itinerary_counter = 1;
                                                			$is_itinerary_available = 1;
                                                				$wptravel_itinerary_label = stripslashes( $wptravel_itinerary['label'] );
                                                			
                                                				$wptravel_itinerary_title = stripslashes( $wptravel_itinerary['title'] );
                                                			
                                                				$wptravel_itinerary_desc = stripslashes( $wptravel_itinerary['desc'] );
                                                			
                                                				$wptravel_itinerary_date = wptravel_format_date( $wptravel_itinerary['date'] );
                                                			
                                                				$wptravel_itinerary_time = stripslashes( $wptravel_itinerary['time'] );
                                                				$wptravel_itinerary_time = date( $wptravel_time_format, strtotime( $wptravel_itinerary_time ) ); 
                                                		    $itinerary_location_array[$itinerary_array_counter] = $wptravel_itinerary_label; // destination
                                                			$itinerary_time_array[$itinerary_array_counter] = $wptravel_itinerary_time; // flight time
                                                			$itinerary_flight_array[$itinerary_array_counter] = $wptravel_itinerary_title; // flight number
                                                			$itinerary_date_array[$itinerary_array_counter] = $traveldate_fxed; // flight date
                                                			$itinerary_datedecider_array[$itinerary_array_counter] = strip_tags($wptravel_itinerary_desc); // arrival or departure define
                                                					
                                                			$wptravel_index++;
                                                			$itinerary_array_counter++;
                                                			
                                                		endforeach;
                                                	endif; 
                                                	// DEFINE EXTRA DAYS
                                                	echo '</br></br>';
                                                	
                                                	$wptravel_travel_outline = get_post_meta( $productid_for_wptravel_product, 'wp_travel_outline', true );
                                                    $pattern_to_remove_itinerary_table = '/Want to book(.*?)\[\/embed\]/s';
                                                    $wptravel_travel_outline = preg_replace($pattern_to_remove_itinerary_table, '', $wptravel_travel_outline);
    
                                                    //$pattern_to_remove_itinerary_table = '/<table class="tg">(.*?)<\/table>/s';
                                                    //$wptravel_travel_outline = preg_replace($pattern_to_remove_itinerary_table, '', $wptravel_travel_outline);
                                                    echo $wptravel_travel_outline;
                                                	//$itinerary_vals .= $wptravel_travel_outline;
                                                	
                                                	$departure_date_plus_one = date("d/m/Y", strtotime("1 day", strtotime($traveldate_fxed))); 
                                                	$departure_date_plus_two = date("d/m/Y", strtotime("2 day", strtotime($traveldate_fxed)));
                                                	$departure_date_plus_three = date("d/m/Y", strtotime("3 day", strtotime($traveldate_fxed)));
                                                	$departure_date_plus_four = date("d/m/Y", strtotime("4 day", strtotime($traveldate_fxed)));
                                                    if ( is_array( $itinerary_location_array ) ) {
                                                	$length_aray = count($itinerary_location_array);
                                                	$itinerary_vals .= '<center><table class="m_-8969220568537220410 tripitinerary wp-travel-table-content trip_'.$trip_id_org.'" cellpadding="0" cellspacing="0" style="width:100%; text-align:left; border: 1px solid #e1e1e; border-collapse: collapse; margin:10px 0px 10px 0px; font-size:14px;">
                                                	<thead>
                                                	  <tr style="background: #f0f0f0; border:none;">
                                                		 <th style="width:20%">From</th>
                                                		 <th style="width:20%">To</th>
                                                		 <th style="width:20%">Flight</th>
                                                		 <th style="width:20%">Departure</th>
                                                		 <th style="width:20%">Arrival</th>
                                                	  </tr>
                                                	</thead>
                                                	<tbody>';
                                                            }
                                                            else {
                                                    // Handle the case when $itinerary_location_array is null
                                                    $itinerary_vals .= '<p>No itinerary locations found.</p>';
                                                    }
                                                	// SECTION TO DIVIDE WAITING, SELF TRANSFER AND FLIGHT INFO
                                                	$is_printed_destination = '';
                                                	for ($i = 0; $i < $length_aray; $i++) {
                                                		if($itinerary_location_array[$i] == 'WAIT')
                                                		{
                                                			$is_printed_destination = '';
                                                		}
                                                		else if($itinerary_location_array[$i] == 'SELF-TRANSFER')
                                                		{
                                                			$is_printed_destination = '';
                                                		}
                                                		else
                                                		{
                                                			if ($is_printed_destination == '') 
                                                            {
                                                                 
                                                                $date1 = date("Y-m-d", strtotime($itinerary_datedecider_array[$i]))." ".date("H:i:s", strtotime($itinerary_time_array[$i]));
                                                                if(isset($itinerary_datedecider_array[$i+1]) && isset($itinerary_time_array[$i+1]))
                                                                {
                                                                    $date2 = date("Y-m-d", strtotime($itinerary_datedecider_array[$i+1]))." ".date("H:i:s", strtotime($itinerary_time_array[$i+1]));
                                                                    $dateDiff = intval((strtotime($date2) - strtotime($date1)) / 60);
                                                                }
                                                                else
                                                                {
                                                                    $dateDiff = intval((strtotime($date1) - strtotime($date1)) / 60);
                                                                }
                                                                
                                                                $hours = intval($dateDiff / 60); 
                                                                $minutes = $dateDiff % 60;
                                                                
                                                                $time_duration = $hours.":".$minutes;
                                                                $airline_from_itinerary = substr($itinerary_flight_array[$i],0,2);
                                                    
                                                				$itinerary_vals .= "<tr>";
                                                				$itinerary_vals .= '<td style="width:20%">'.$itinerary_location_array[$i].'</td>';
                                                				if(isset($itinerary_location_array[$i+1])) { 
                                                				    $itinerary_vals .= '<td style="width:20%">'.$itinerary_location_array[$i+1].'</td>';
                                                				}
                                                				$itinerary_vals .= '<td style="width:20%">'.$itinerary_flight_array[$i].'</td>';
                                                				$itinerary_vals .= '<td style="width:20%">'.$itinerary_time_array[$i].'</br>'.$itinerary_datedecider_array[$i].'</td>';
                                                				if(isset($itinerary_time_array[$i+1]) && isset($itinerary_datedecider_array[$i+1])) { 
                                                				    $itinerary_vals .= '<td style="width:20%">'.$itinerary_time_array[$i+1].'</br>'.$itinerary_datedecider_array[$i+1].'</td>';
                                                				}
                                                				$itinerary_vals .= "</tr>";
                                                				$empty = '';
                                                				$itinerary_vals .= "<tr style='border:none;'>";
                                                				$itinerary_vals .= '<td colspan="2" style="border:none;">   Class: Economy
                                                				           </td>';
                                                				$itinerary_vals .= '<td colspan="2" style="border:none;">Operated by: '.$airline_from_itinerary.'</td>';
                                                				$itinerary_vals .= '<td style="border:none;"></td>';
                                                				$itinerary_vals .= "</tr>";
                                                				
                                                				$is_printed_destination = 'yes';
                                                            }
                                                            else
                                                            {
                                                                $is_printed_destination = '';
                                                            }
                                                		}
                                                	}
                                                	$itinerary_vals .= "</tbody></table></center>";
                                                	
                                                	
                                                	
                                                	// REPLACE ALL THE FIXED WORDS AS DEPARTURE AND ARRIVAL ON BACKEND
                                                	$itinerary_vals = str_replace("Departure Date", '' ,$itinerary_vals);
                                                	$itinerary_vals = str_replace("Arrival Date", '' ,$itinerary_vals);
                                                	
                                                	$itinerary_vals = str_replace("Selected Date +4", $departure_date_plus_four ,$itinerary_vals);
                                                	$itinerary_vals = str_replace("Selected Date+4", $departure_date_plus_four ,$itinerary_vals);
                                                	
                                                	$itinerary_vals = str_replace("Selected Date +3", $departure_date_plus_three ,$itinerary_vals);
                                                	$itinerary_vals = str_replace("Selected Date+3", $departure_date_plus_three ,$itinerary_vals);
                                                	
                                                	$itinerary_vals = str_replace("Selected Date +2", $departure_date_plus_two ,$itinerary_vals);
                                                	$itinerary_vals = str_replace("Selected Date+2", $departure_date_plus_two ,$itinerary_vals);
                                                	
                                                	$itinerary_vals = str_replace("Selected Date+1", $departure_date_plus_one ,$itinerary_vals);
                                                	$itinerary_vals = str_replace("Selected Date +1", $departure_date_plus_one ,$itinerary_vals);
                                                	
                                                	$traveldate_fxed_1 = date("d/m/Y", strtotime($traveldate_fxed)); 
                                                	
                                                	
                                                	$itinerary_vals = str_replace("Selected Date", $traveldate_fxed_1 ,$itinerary_vals);
                                                	
                                                	$itinerary_vals = str_replace("Selected Date", $traveldate_fxed_1 ,$itinerary_vals);
                                                	
                                                	$trip_code_post = get_post_meta($trip_id_org, 'wp_travel_trip_code', true);
	                                                $itinerary_details = getGDealFlightItinerary($trip_code_post, $ret_date_ymd);
	                                                if($itinerary_details != '')
	                                                {
                                                	    echo $itinerary_details;
	                                                }
	                                                else
	                                                {
	                                                    echo $itinerary_vals;
	                                                }
                                                	
                                                	$order_id_for_itinerary = '42342434';
                                                	$wptravel_product_information_ordered = get_post_meta( $order_id_for_itinerary, 'order_items_data', true ); // get order product info for trip extras
                                                    if (is_array($wptravel_product_information_ordered) || is_object($wptravel_product_information_ordered)) 
                                                    {
                                                        foreach ($wptravel_product_information_ordered as $key => $value) {
                                                            // Check if the 'trip_extras' key and 'id' key exist
                                                            if (isset($value['trip_extras']['id'])) {
                                                                // Extract "trip_extras" IDs
                                                                $tripExtrasIds = $value['trip_extras']['id'];
                                                                echo '<h6>Additional services</h6>';
                                                                // Print or use the values as needed
                                                                foreach ($tripExtrasIds as $value) {
                                                                    echo '<li>'.get_the_title( $value ).'</li>';
                                                                }
                                                            } else {
                                                                echo "No additional services available.";
                                                            }
                                                        }
                                                	}
                                                	echo '</br>';
                                                }
                                                echo '</div>';
                                                ?>
                                            </div>
                                    </div>
                                    <div class="price_tag_main">
                                        <div class="price_column_1">
                                        <!--
                                        <div class="offer-price">
                                            <?php apply_filters('wp_trave_archives_page_trip_save_offer', wptravel_save_offer($return_post->ID), $return_post->ID); ?>
                                            <?php if ($sale_price_loop != 0)
                                            {
                                                ?>
                                                <label class="discounted-price">
                                                    <?php
                                                   echo apply_filters('wp_travel_archives_page_trip_price', wptravel_get_formated_price_currency($sale_price_loop), $return_post->ID); //phpcs:ignore 
                                                   if($is_discount_on == 1 && $discount_percentage != '')
                                                    {
                                                        //$sale_price_loop = $sale_price_loop - ($sale_price_loop * 0.10);

                                                    }
                                                   $individual_amount_for_tour = (float)$sale_price_loop;
                                                   $total_amount_for_tour += (float)$sale_price_loop;
                                                ?>
                                                </label>
                                                <?php
                                            }
                                            else
                                            {
                                                ?>
                                                <label class="discounted-price">
                                                <?php
                                                   echo apply_filters('wp_travel_archives_page_trip_price', wptravel_get_formated_price_currency($trip_price), $return_post->ID); //phpcs:ignore 
                                                   if($is_discount_on == 1 && $discount_percentage != '')
                                                    {
                                                        //$trip_price = $trip_price - ($trip_price * 0.10);
                                                    }
                                                   $individual_amount_for_tour = (float)$trip_price;
                                                   $is_monthly_stock_note_to_show = 1;
                                                   $total_amount_for_tour += (float)$trip_price;
                                                ?>
                                                </label>
                                                <?php
                                            }
                                            ?>
                                        </div>-->
                                        <?php
                                        
                                        if( $is_coupon_on == 1 && $is_count_amount_total != '' )
                                        {
                                            $total_amount_for_tour = (float)$total_amount_for_tour - (float)$is_count_amount_total;
                                        }
                                        
                                        if($initial_flight == '')
                                        {
                                            $airline_arr = array("virgin australia" ,"thai","singapore","qatar" ,"srilanka","airindia","scoot","emirates","sabre",
                                                    "jetstar","cathay","malaysia","qantas","eithad");
                                                    $airline_obj = array(
                                                        "virgin australia" => "VA",
                                                        "thai" => "TG",
                                                        "singapore" => "SQ",
                                                        "qatar" => "QR",
                                                        "srilanka" => "UL",
                                                        "airindia" => "AI",
                                                        "scoot" => "TR",
                                                        "emirates" => "EK",
                                                        "jetstar" => "JQ",
                                                        "cathay" => "CX",
                                                        "malaysia" => "MH",
                                                        "qantas" => "QF",
                                                        "eithad" => "EY"
                                                    );
                                                    $trip_wp_title = get_post_field('post_title', $trip_id_org);
                                                    $array_source = explode(" ", $trip_wp_title);
                                                    $airline_name = '';
                                                    for($i = 0; $i < count($array_source); $i++){
                                                        $val = strtolower(preg_replace('/-/','',$array_source[$i]));
                                                        if(in_array($val, $airline_arr)){
                                                            $airline_name = $val;
                                                            break;
                                                        }
                                                    }
                            
                                                    $initial_flight = $airline_obj[$airline_name];
                                        }
                                        
                                        $total_booking_charge = number_format((float)$total_amount_for_tour, 2, '.', '');
                                        $total_booking_charge2 = number_format((float)$total_amount_for_tour, 0, '.', '');
                                        
                                        //$airline_code = $initial_flight;
                                        if (isset($initial_flight) && $initial_flight != '') 
                                        {
                                            $airline_code = $initial_flight;
                                        } 
                                        else 
                                        {
                                            $airline_code = '';
                                            error_log("Undefined array key: " . $initial_flight);
                                        }
											?>
                                            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                                            <script>
                                            $(document).ready(function () {
                                                $('.package').each(function (index, element) {
                                                    // Get data attributes for this package
                                                    const packageElement = $(element);
                                                    const gdealsPrice = <?php echo $total_booking_charge2; ?>;
                                                    const from = packageElement.data('from');
                                                    const to = packageElement.data('to');
                                                    const date = packageElement.data('date');
                                                    const returndate = packageElement.data('returndate');
                                                    const triptype = packageElement.data('type');
                                                    const airline = packageElement.data('airline');
                                                    const priceElementId = `#ypsilon-price-${index + 1}`;
                                            
                                                    // Make the AJAX call for this package
                                                    $.ajax({
                                                        url: '/wp-content/themes/twentytwenty/templates/tpl_check_ypsilon_fare_ajax.php',
                                                        method: 'POST',
                                                        data: { from, to, date, returndate, airline, triptype },
                                                        dataType: 'json',
                                                        success: function (response) {
                                                            //console.log(`Response for package ${index + 1}:`, response);
                                                            if (response.status === 'success' && response.total_amount) 
                                                            {
                                                                if(gdealsPrice < response.total_amount)
                                                                {
                                                                    const ypsilonPriceText = `
                                                                    <div class="offer-price ypsilon_pricing_box" 
                                                                        data-message="This price represents the current market rate for the same route with the same airline if not booked through Gaura Travel. Prices are dynamic and may vary across other websites or travel agencies. \nLuggage included: ${response.baggage}"
                                                                        >
                                                                        <label class="discounted-price" 
                                                                               style="font-size:20px; text-decoration: line-through; color: gray; cursor: pointer;">
                                                                            $${response.total_amount}
                                                                            <i class="fa fa-info-circle" style="vertical-align: super; font-size:11px;"></i>
                                                                        </label>
                                                                    </div>
                                                                `;
                                                                $(priceElementId).html(ypsilonPriceText);
                                                                }
                                                            }
                                                        },
                                                        error: function (xhr, status, error) {
                                                            
                                                        }
                                                    });
                                                });
                                            });
                                            </script>
                                            <?php
                                            
                                            $dep_date_ymd_yps = date('Y-m-d', strtotime($_GET['depdate1']));
                                            $ret_date_ymd_yps = date('Y-m-d', strtotime($_GET['retdate1']));
                                            
                                            if(isset($_GET['depapt1']) && isset($_GET['depapt1']) && $dep_date_ymd_yps && $ret_date_ymd_yps && $airline_code != '')
                                            {
                                                $row_going_id_div = $row_going_id - 1;
                                                ?>
                                                <div class="package" data-from="<?php echo $_GET["depapt1"]; ?>" data-to="<?php echo $_GET["dstapt1"]; ?>" data-date="<?php echo $dep_date_ymd_yps; ?>" data-returndate="<?php echo $ret_date_ymd_yps; ?>" data-type="return" data-airline="<?php echo $airline_code; ?>"> 
                                                    <div id="ypsilon-price-<?php echo $row_going_id_div; ?>"></div>
                                                </div>
                                                <?php
                                            }

                                            if($lowest_kg_value != '')
                                            {
                                                $lowest_kg_value = ' including '.$lowest_kg_value . 'kg baggage';
                                            }
                                            
                                            echo '<div class="offer-price gdeals_price_box" data-message="This price is Gaura Travel’s exclusive GDeals fare, which includes a '.$lowest_kg_value.' baggage allowance, onboard meals, and inflight entertainment with this award-winning airline."><label class="discounted-price">$'.$total_booking_charge2.'<i class="fa fa-info-circle" style="vertical-align: super; font-size:11px;"></i></label>
                                            </div>';
                                            
                                            
                                            
                                            
                                        
                                        
                                        //echo '<div class="offer-price"><label class="discounted-price">$'.$total_booking_charge2.'</label></div>';
                                        $is_slicepay_eligible = is_slicePayEligible($total_amount_for_tour, $dep_date_ymd);
                                            
                                        if($travel_vs_current_date_difference > 31 && $is_slicepay_eligible['is_eligible'] == 1)
                                        {
                                            echo 'OR';
                                            echo '<div class="offer-price" style="margin-top:10px;"><label class="discounted-price">$' . $is_slicepay_eligible['weekly_installment'] . '/wk</label></div>';
                                        }
                                        ?>
                                        </div>
                                        <div class="price_column_2">
                                        <?php
                                        $trip_extras_ids = explode(',', $trip_extras_string);
    
                                        // Create an array of quantities, all set to 1
                                        $trip_extras_qty = array_fill(0, count($trip_extras_ids), 1);
                
                                        $individual_amount_for_tour_pax = $individual_amount_for_tour * $pax_count ;
                                        
                                        if( $is_coupon_on == 1 && $is_count_amount != '' )
                                        {
                                            $individual_amount_for_tour_pax = (float)$individual_amount_for_tour_pax - (float)$is_count_amount;
                                        }
                                        
                                        $individual_total_partial_amount = ($individual_amount_for_tour_pax * 0.05)  ;
                                        $individual_partial_amount = ($individual_amount_for_tour * 0.05);
                                        
                                        $return_pricing_id = $pricing_id_loop;
                              
                                        if($is_promotion_on == 1 && $promotion_deposit_amount != '' )
                                        {
                                            $individual_partial_amount = 0;
                                            $individual_total_partial_amount = 0 * $pax_count;
                                        }
                                        // Create the array structure for the current trip package
                                        $cart_array_combination_return = array(
                                            'max_available' => $max_pax,
                                            'min_available' => $min_pax,
                                            'trip_start_date' => $ret_date_ymd,
                                            'currency' => '$',
                                            'trip' => array(),
                                            'enable_partial' => 1,
                                            'partial_payout_figure' => $promotion_deposit_amount,
                                            'trip_price_partial' => $individual_total_partial_amount,
                                            'pricing_id' => $pricing_id_loop,
                                            'arrival_date' => $ret_date_ymd,
                                            'date_id' => $date_id_loop,
                                            'departure_date' => $ret_date_ymd,
                                            'trip_extras' => array(
                                                'id' => $trip_extras_ids,
                                                'qty' => $trip_extras_qty
                                            ),
                                            'trip_id' => $trip_id_org,
                                            'trip_price' => $individual_amount_for_tour_pax,
                                            'pax' => $pax_count,
                                            'price_key' => '',
                                            'coupon' => array(
                                                'coupon_id' => ''
                                            ),
                                        );
                                        
                                        $cart_array_combination_return['trip']['953'] = array(
                                            'pax' => $adult_count,
                                            'price' => $individual_amount_for_tour,
                                            'price_partial' => $individual_partial_amount,
                                            'type' => 'custom',
                                            'custom_label' => 'Adult',
                                            'price_per' => 'person'
                                        );
                                        
                                        $cart_array_combination_return['trip']['954'] = array(
                                            'pax' => $child_count,
                                            'price' => $individual_amount_for_tour,
                                            'price_partial' => $individual_partial_amount,
                                            'type' => 'custom',
                                            'custom_label' => 'Child',
                                            'price_per' => 'person'
                                        );
                
                                        // Add this combination to the main $cart_array with a unique key
                                        $unique_key = $trip_id_org . '_' . $ret_date_ymd . '_' . $pricing_id_loop;
                                        $cart_array[$unique_key] = $cart_array_combination_return;
                                            //echo $return_pricing_id;
                                        //echo '<pre>';
                                        //print_r($cart_array);
                                        ///echo '</pre>';
                                        $cart_json = json_encode($cart_array);
                                        
                                        //echo '<pre>';
                                        //print_r($cart_json);
                                        //echo '</pre>';
                                        ?>
                                        <!--<button class="book-now" id="return_add_booking"><a href="<?php echo $return_trip_link; ?>">View</a></button>
                                        <button class="book-now" data-summary='<?php echo $cart_json; ?>' id="BuyThisTripNow">Book Now</button>-->
                                        <button class="book-nowreturn" data-summary='<?php echo htmlspecialchars($cart_json, ENT_QUOTES, 'UTF-8'); ?>' id="BuyThisTripNow">Book Now</button>
                                        <?php
                                        $baggage_numeric_value = '';
                                        // Use regex to extract the first numeric value from the string
                                        preg_match('/\d+/', $lowest_kg_value, $matches_for_baggage);
                                        
                                        if (!empty($matches_for_baggage)) 
                                        {
                                            $baggage_numeric_value = (int) $matches_for_baggage[0]; // Convert extracted value to an integer
                                        } 

                                            if(intval($baggage_numeric_value) > 30)
                                                {
                                                    ?>
                                                    <style>
                                                        @keyframes blink {
                                                            0%, 100%, 40%, 60% { opacity: 1; }
                                                            50% { opacity: 0; }
                                                        }
                                                    
                                                        .baggageblink {
                                                            
                                                            animation: blink 1s infinite !important;
                                                        }
                                                    </style>
                                                    <style>
                                                       .baggage_tag {
                                                            background-color: #ff9900;
                                                            color: white !important;
                                                            border: 2px solid #ff9900;
                                                            padding: 6px 35px;
                                                            font-size: 13px;
                                                            font-weight: bold;
                                                            text-align: center;
                                                            cursor: pointer;
                                                            display: inline-block;
                                                            border-radius: 5px 50px 5px 5px; /* Tag shape */
                                                            position: relative;
                                                            margin-top:25px;
                                                            text-decoration: none;
                                                            box-shadow: 3px 4px 8px rgba(0, 0, 0, 0.3);
                                                        }
                                                    
                                                        /* Hole effect to make it look like a luggage tag */
                                                        .baggage_tag::before {
                                                            content: "";
                                                            width: 10px;
                                                            height: 10px;
                                                            background-color: white;
                                                            border-radius: 50%;
                                                            position: absolute;
                                                            top: 8px;
                                                            left: 8px;
                                                            border: 2px solid #ff9900;
                                                        }
                                                    
                                                        /* String effect */
                                                        .baggage_tag::after {
                                                            content: "";
                                                            width: 4px;
                                                            height: 25px;
                                                            background-color: #ff9900;
                                                            position: absolute;
                                                            top: -25px;
                                                            left: 12px;
                                                            border-radius: 2px;
                                                        }
                                                    
                                                        /* Hover effect */
                                                        .baggage_tag:hover {
                                                            background-color: #ff9900;
                                                            box-shadow: 4px 6px 12px rgba(0, 0, 0, 0.5);
                                                        }
                                                    </style>
                                                    
                                                    <a href="#" class="baggage_tag">
                                                        Baggage <br>
                                                        <font class="baggageblink" style="font-size:17px;"><?php echo $final_baggage; ?></font>
                                                    </a>
                                                    <?php
                                                }
                                            ?>
                                        </div>   
                                    </div>
                                </div>
                                <style>
    @keyframes blink {
        0%, 100%, 40%, 60% { opacity: 1; }
        50% { opacity: 0; }
    }

    .lockinprice {
        font-size: 20px;
    }

    .lockin-span {
        white-space: nowrap;
    }

    .inclusions_text {
        margin-top: 12px;
    }

    .responsive-container {
        display: grid;
        grid-template-columns: 1fr 1fr; /* Default for larger screens */
        gap: 1rem;
        width: 100%; /* Full width */
        background: white;
        border-radius: 10px;
        padding: 0.75rem;
        text-align:center;
    }

    .includes_div {
        display: flex;
        flex-wrap: nowrap; /* Prevent wrapping on mobile */
        align-items: center;
    }

    .responsive-container > div {
        max-width: 100%; /* Ensure the content spans fully in its cell */
    }
    
    .includes_image
    {
        width:65px;
        height:65px;
    }

    @media screen and (max-width: 750px) {
        .responsive-container {
            grid-template-columns: 1fr; /* Switch to single-column layout on smaller screens */
        }

        .lockinprice {
            text-align: center;
            padding: 0;
        }

        .lockin-span {
            white-space: normal;
            font-size: 12px;
        }
        .includes_image
        {
            width:40px;
            height:40px;
        }
        .inclusions_text {
            margin-top: 7px;
        }
    }
</style>



                                
<div class="responsive-container">
    <div>
        <?php
                //$start_day = (int)date('m', strtotime($is_month_eligible_for_lock_price));
                //$current_day = (int)date('m');
                if( $travel_vs_current_date_difference > 31 ) 
                {
                ?>
                <div class="lockinprice" style="margin: 15px 0px 20px 0px;">
                    <span class="lockin-span" style="font-weight:600; padding:9px 18px; background-color:#ffbb00; color:white; margin:0px 5px; border-radius:3px;">
                        <i class="fa fa-lock"></i> GUARANTEED LOCK IN NOW WITH $<?php echo $promotion_deposit_amount; ?> FOR UPTO 96 HRS
                    </span>
                </div>
                <?php
                } 
                else 
                {
                ?>
                <div class="lockinprice" style="margin: 15px 0px 20px 0px;">
                    <span class="lockin-span" style="font-weight:600; padding:9px 18px; background-color:#ffbb00; color:white; margin:0px 5px; border-radius:3px;">
                        <i class="fa fa-lock"></i> GUARANTEED LOCK IN NOW WITH $<?php echo $promotion_deposit_amount; ?> UNTIL 23:59 TODAY
                    </span>
                </div>
                <?php
                }
        ?>
    </div>
    <div>
        <div class="includes_div">
            <img src="https://gauratravel.com.au/wp-content/uploads/2024/12/Icon_food_black_text.png" class="includes_image">&nbsp;
            <span class="inclusions_text">+</span>&nbsp;
            <img src="https://gauratravel.com.au/wp-content/uploads/2024/12/Icon_baggage_black_text.png" class="includes_image">&nbsp;
            <span class="inclusions_text">+</span>&nbsp;
            <img src="https://gauratravel.com.au/wp-content/uploads/2024/12/Icon_entertainment_black_text.png" class="includes_image">
            <span class="inclusions_text">&nbsp;&nbsp;INCLUDED IN EVERY FARE</span>
        </div>
    </div>
</div>
                            </div>
                            <?php
    
                            $row_return_id++;
                            
                            // echo '<pre>';
                            // print_r($cart_json);
                            // echo '</pre>';
                        }
                        
                        //echo '<pre>';
                        //print_r($cart_array);
                        //echo '</pre>';
                        
    
                        ?>
                    <!-- End of of GDeals flight view -->
                    <?php 
                    }
                    ?>
                <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Loader functions
                    function showLoader() {
                        $("#fullscreen-loader").fadeIn();
                    }
                
                    function hideLoader() {
                        $("#fullscreen-loader").fadeOut();
                    }
                
                    // Modal functions
                    function showModelStockBox(message) {
                        const overlay = document.getElementById('modal-stockbox-overlay');
                        const modal = document.getElementById('stock-model-stockbox');
                        const messageBox = document.getElementById('model-stockbox-message');
                        messageBox.innerText = message;
                    
                        // Show overlay and modal
                        overlay.style.display = 'block';
                        modal.style.display = 'block';
    
                        //$("#model-stockbox-message").text(message);
                        //$("#stock-model-stockbox").fadeIn();
                    }
                
                    function hideModelStockBox() {
                        const overlay = document.getElementById('modal-stockbox-overlay');
                        const modal = document.getElementById('stock-model-stockbox');
                        
                        // Hide overlay and modal
                        overlay.style.display = 'none';
                        modal.style.display = 'none';
    
                        $("#stock-model-stockbox").fadeOut();
                    }
                
                    // Close modal when close button is clicked
                    $(".close-button").click(function() {
                        hideModelStockBox();
                    });
                
                    // Handle book button click
                    $('.book-nowreturn').on('click', function(e) {
                        e.preventDefault();
                
                        let cartArray = $(this).data('summary');
                
                        // Extract `pricing_id` and `pax` from `cartArray`
                        let pricingId = null;
                        let pricingIdReturn = null;
                        let pax = null;
                        let count = 0;
                        for (let key in cartArray) {
                            if (cartArray.hasOwnProperty(key)) {
                                if (count === 0) {
                                    pricingId = cartArray[key].pricing_id; // Assign first pricing_id
                                } else if (count === 1) {
                                    pricingIdReturn = cartArray[key].pricing_id; // Assign second pricing_id
                                    break; // Exit loop after assigning the second pricing_id
                                }
                                pax = cartArray[key].pax; // Capture pax (can be updated to your logic)
                                count++; // Increment counter
                            }
                        }
                
                        // Debugging: Check extracted values
                        console.log("Pricing ID:", pricingId, "Pricing ID 2:", pricingIdReturn, "PAX:", pax);
                
                        if (!pricingId || !pricingIdReturn || !pax) {
                            showModelStockBox("Invalid data provided. Please try again.");
                            return;
                        }
                
                        // Step 1: Check stock availability
                        $.ajax({
                            url: '/wp-content/themes/twentytwenty/templates/custom-cart-handler-revalidate-stock-before-checkout.php',
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                pricing_id: pricingId,
                                pricing_id_return: pricingIdReturn,
                                pax: pax
                            },
                            beforeSend: function() {
                                showLoader();
                            },
                            success: function(response) {
                                //alert(JSON.stringify(response));
                                setTimeout(function() 
                                {
                                    
                                    const GDEAL_COUPON_ENABLED     = <?php echo json_encode((int)$GDEAL_COUPON_ENABLED); ?>;
                                    const GDEAL_COUPON_EXPIRY_DATE = <?php echo json_encode($GDEAL_COUPON_EXPIRY_DATE); ?>; // expect "YYYY-MM-DD"
                                    const GDEAL_COUPON_CODE        = <?php echo json_encode($GDEAL_COUPON_CODE); ?>;
                                    const GDEAL_COUPON_ID          = <?php echo json_encode($GDEAL_COUPON_ID); ?>;
                                    const GDEAL_COUPON_TYPE        = <?php echo json_encode($GDEAL_COUPON_TYPE); ?>;
                                    const GDEAL_COUPON_VALUE       = <?php echo json_encode($GDEAL_COUPON_VALUE); ?>;
                                    
                                    /* ---- Prepare coupon (if enabled + not expired) ---- */
                                    const todayStr = new Date().toISOString().slice(0, 10); // "YYYY-MM-DD"
                                    let couponPayload = null;
                                    if(GDEAL_COUPON_ENABLED === 1 && GDEAL_COUPON_EXPIRY_DATE && GDEAL_COUPON_EXPIRY_DATE >= todayStr )
                                    {
                                        couponPayload = {
                                            coupon_code:  GDEAL_COUPON_CODE,
                                            coupon_id:    GDEAL_COUPON_ID,
                                            coupon_type:  GDEAL_COUPON_TYPE,
                                            coupon_value: GDEAL_COUPON_VALUE
                                          };
                                        
                                    }
                                    /* 
                                    data: {
                                                args: cartArray
                                            },
                                            
                                    data: {
                                                args: cartArray,
                                                add_coupon: {
                                                coupon_code: "Gaura5",
                                                coupon_id: "273455",
                                                coupon_type: "percentage",
                                                coupon_value: "5"
                                                }
                                            },
                                                }*/
                                    hideLoader();
                                    if (response.stock_available) {
                                        // Step 2: Add item to cart
                                        $.ajax({
                                            url: '/wp-content/themes/twentytwenty/templates/custom_cart_hander_2.php',
                                            type: 'POST',
                                            dataType: 'json',
                                            
                                            data: couponPayload
                                              ? { args: cartArray, add_coupon: couponPayload }
                                              : { args: cartArray },
                                            
                                            success: function(response) {
                                                if (response.success) {
                                                    //alert("Checkout successful!");
                                                    window.location.href="/flights-checkout/";
                                                } else {
                                                    //alert('no stock');
                                                    //showModelStockBox("Failed to add item to cart. " + response.message);
                                                    showModelStockBox("Sorry, all seats for this flight are sold out. Kindly select a different flight option. For further assistance, please call our 24/7 customer support at 1300 359 463.");
                                                }
                                            },
                                            error: function(jqXHR, textStatus, errorThrown) {
                                                //alert('no return');
                                                //showModelStockBox("Error: " + textStatus + " - " + errorThrown);
                                                showModelStockBox("Sorry, all seats for this flight are sold out. Kindly select a different flight option. For further assistance, please call our 24/7 customer support at 1300 359 463.");
                                            }
                                        });
                                    } else {
                                        //alert('failed call');
                                        showModelStockBox("Sorry, all seats for this flight are sold out. Kindly select a different flight option. For further assistance, please call our 24/7 customer support at 1300 359 463.");
                                    }
                                }, 3000);
                            },
                            error: function(jqXHR, textStatus, errorThrown) {
                                setTimeout(function() 
                                {
                                    hideLoader();
                                    //alert('failed call main');
                                    showModelStockBox("Sorry, all seats for this flight are sold out. Kindly select a different flight option. For further assistance, please call our 24/7 customer support at 1300 359 463.");
                                    //showModelStockBox("Stock validation error: " + textStatus + " - " + errorThrown);
                                }, 3000);
                            }
                        });
                    });
                });
                </script>
                        <script type="text/javascript">
                        /*
                        jQuery(document).ready(function($) {
                            $('.book-nowreturn').on('click', function(e) {
                                e.preventDefault();
                        
                                var cartArray = $(this).data('summary');
                                
                                // AJAX request
                                $.ajax({
                                    url: '/wp-content/themes/twentytwenty/templates/custom-cart-handler.php',
                                    type: 'POST',
                                    dataType: 'json', // Expect a JSON response
                                    
                                    data: { 
                                        args: cartArray  // Pass the cart array as data
                                    },
                                    success: function(response) {
                                        if (response.success) {
                                            window.location.href="/flights-checkout/";
                                            //alert(response.message + 'Item added to cart!');
                                        } else {
                                            //alert(response.message + 'Failed to add item to cart.');
                                        }
                                    },
                                    error: function(jqXHR, textStatus, errorThrown) {
                                        alert('AJAX error: ' + textStatus + ' : ' + errorThrown);
                                    }
                                });
                            });
                        });
                        */
                        </script>
                </div>
                
                <?php
            }
            if( (!isset($_GET['depdate1'])) || ( isset($_GET['depdate1']) && $_GET['depdate1'] == '' ) || !isset($_GET['retdate1']) )
            {
                ?>
                <style>
                        /* The Modal (background) */
                        .modal {
                          display: none; /* Hidden by default */
                          position: fixed; /* Stay in place */
                          z-index: 1; /* Sit on top */
                          padding-top: 250px; /* Location of the box */
                          left: 0;
                          top: 0;
                          width: 100%; /* Full width */
                          height: 100%; /* Full height */
                          overflow: auto; /* Enable scroll if needed */
                          background-color: rgb(0,0,0); /* Fallback color */
                          background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
                        }
                        
                        /* Modal Content */
                        .modal-content {
                          position: relative;
                          background-color: #fefefe;
                          margin: auto;
                          padding: 0;
                          border: 1px solid #888;
                          width: 80%;
                          box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2),0 6px 20px 0 rgba(0,0,0,0.19);
                          -webkit-animation-name: animatetop;
                          -webkit-animation-duration: 0.4s;
                          animation-name: animatetop;
                          animation-duration: 0.4s
                        }
                        
                        /* Add Animation */
                        @-webkit-keyframes animatetop {
                          from {top:-300px; opacity:0} 
                          to {top:0; opacity:1}
                        }
                        
                        @keyframes animatetop {
                          from {top:-300px; opacity:0}
                          to {top:0; opacity:1}
                        }
                        
                        /* The Close Button */
                        .close {
                          color: white;
                          float: right;
                          font-size: 28px;
                          font-weight: bold;
                        }
                        
                        .close:hover,
                        .close:focus {
                          color: #000;
                          text-decoration: none;
                          cursor: pointer;
                        }
                        
                        .modal-header {
                          padding: 2px 16px;
                          background-color: #ffbb00;
                          color: black;
                        }
                        
                        .modal-header h6
                        {
                            margin:20px 0px;
                        }
                        
                        .modal-body {padding: 2px 16px;}
                        
                        .modal-footer {
                          padding: 2px 16px;
                          background-color: #ffbb00;
                          color: white;
                        }
                    </style>
                    <div id="myMfodal" class="modfal">
    
                      <!-- Modal content -->
                      <div class="modal-content">
                        <!--<div class="modal-header">
                          <span class="close">&times;</span>
                          <h6>Seats unavailable</h6>
                        </div>-->
                        <div class="modal-body">
                            </br>
                            <p>Kindly select the dates to view GDeals.</p>
                            <p>However, We have other options available, scroll down.</p>
                            <center>
                                <!--<button style="background-color:black; color: #ffbb00; width:200px; margin-top:7px; height:30px; padding:1px !important; font-size:12px;" onclick="flexibleDateButton()">View flexible dates</button>
                                
                                <a href='tel:1300359463'><button style="background-color:black; color: #ffbb00; width:200px; margin-top:7px; height:30px; padding:1px !important; font-size:12px;">Call Now</button></a>-->
                            </center>
                            </br>
                        </div>
                        <!--<div class="modal-footer">-->
                        </div>
                      </div>
                    
                    </div>
                    
                    
                    <script type="text/javascript">
                        var modal = document.getElementById("myModal");
    
                        // Get the <span> element that closes the modal
                        var span = document.getElementsByClassName("close")[0];
                        
                        // When the user clicks the button, open the modal 
                          modal.style.display = "block";
                        
                        // When the user clicks on <span> (x), close the modal
                        span.onclick = function() {
                          modal.style.display = "none";
                        }
                        
                        // When the user clicks anywhere outside of the modal, close it
                        window.onclick = function(event) {
                          if (event.target == modal) {
                            modal.style.display = "none";
                          }
                        }
                    </script>
                <?php
            }
            
            $hubspot_add_to_cart_date = date("Y-m-d");
            if(isset($_SESSION['userEmailID']) && $_SESSION['userEmailID'] != '')
            {
            ?>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script>
            $(document).ready(function() {
                $("#BuyThisTripNow").on("click", function() {
                    var userEmail = "<?php echo $_SESSION['userEmailID']; ?>"; // Get the email from session
                    var currentDate = "<?php echo $hubspot_add_to_cart_date; ?>"; // Format: YYYY-MM-DD
                    
                    $.ajax({
                        url: "/wp-content/themes/twentytwenty/templates/tpl_flight_results_ajax_add_to_cart_hubspot_update.php",
                        type: "POST",
                        data: { email: userEmail, date: currentDate },
                        success: function(response) {
                            console.log("HubSpot updated successfully:", response);
                        },
                        error: function(xhr, status, error) {
                            console.error("Error updating HubSpot:", error);
                        }
                    });
                });
            });
            </script>
            <?php
            }
        }
        else
        {
            ?>
            <style>
            .background-container {
              background-image: url('https://gauratravel.com.au/wp-content/uploads/2024/09/blured-placeholder-img.jpg');
              background-size: contain;
              background-position: center;
              background-repeat: no-repeat;
              width: 100%;
              position: relative;
              display: flex;
              align-items: center;
              justify-content: center;
            }
            
            .background-container::before {
              content: "";
              position: absolute;
              top: 0;
              left: 0;
              width: 100%;
              height: 100%;
              background-color: rgba(0, 0, 0, 0.1);
              z-index: 1;
            }
            
            .forms-container {
              display: flex;
              flex-wrap: wrap; /* Allows items to wrap onto the next line as needed */
              justify-content: center;
              width: 80%; /* Adjust the width as needed */
              max-width: 960px; /* Maximum width to avoid overly wide forms on larger screens */
              position: relative;
              z-index: 2;
              padding: 20px;
              background: rgba(255, 255, 255, 0.85);
              border-radius: 8px;
              box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            }
            
            .form-section {
              width: 50%; /* Full width on smaller screens */
              padding: 15px;
            }
            
            .form-group {
              margin-bottom: 15px;
            }
            
            @media (max-width: 768px) {
                .background-container 
                {
                    height: 500px;
                }
            }
            
            @media (min-width: 768px) {
                .background-container 
                {
                    height: 750px;
                }
            }
            
            /* Media Queries */
            @media (max-width: 768px) {
              .form-section {
                width: 100%; /* Each section takes full width on smaller screens */
              }
            }
            .haAclf, .qJTHM, #gsi_283358_173758, .g_id_signin iframe, .g_id_signin #container, .g_id_signin iframe button
            {
                width:100% !important;
            }
            #googleSignIn {
                width: 100%;
                max-width: 300px;
            }

                </style>
                <!-- Full-page background image container -->
                <div class="background-container">
                    <!-- Container for both forms -->
                    <div class="forms-container">
                        <center>
                            <h6 style="margin-top:15px; margin-bottom:15px; text-transform: none;">We have found Exclusive GDeals with Affordable Prices with Premium Airlines.</br>Simply Log In to unlock unparalleled access to these Special Offers!</h6>
                            <p style="font-size:12px;">Feel free to scroll through for other available flight options.</p>
                            </br></br>
                            <button onclick="showModal()" style="padding:15px; background-color: #ffbb00; margin:0; font-size:11px; color:black; font-weight:600;">Login / Register</button>
                        </center>
                  </div>
                </div>
                    <?php
                }
                ?>
            </div>
        </div>
<?php }

global $wt_cart;
//print_r($wt_cart);
if($is_monthly_stock_note_to_show == 100000)
{
    ?>
    <font style="font-size:14px; color:red; padding-left:15px;">*</font> <font style="font-size:14px;">Seats are available only for the month.</font>
    <?php
}
?>
<?php
$is_ypsilon = get_option( 'is_ypsilon_active' );
if (
    ($is_ypsilon == 1 || $is_ypsilon == 2) &&
    isset($_GET['agent']) &&
    isset($_GET['type']) &&
    isset($_GET['deptime']) &&
    isset($_GET['flexible_date']) &&
    isset($_GET['depshift']) &&
    isset($_GET['retshift']) &&
    isset($_GET['class']) &&
    isset($_GET['pax_type']) &&
    isset($_GET['direct_only']) &&
    isset($_GET['st']) &&
    isset($_GET['adt']) &&
    isset($_GET['chd']) &&
    isset($_GET['inf']) &&
    isset($_GET['depdate1']) &&
    isset($_GET['retdate1']) &&
    isset($_GET['depapt1']) &&
    isset($_GET['dstapt1'])
) {
?>

<div id="ypsnet-ibe" style="padding-top:50px;"
    data-src="https://flr.ypsilon.net/?agent=<?php echo $_GET['agent'];?>&type=<?php echo $_GET['type'];?>&deptime=<?php echo $_GET['deptime'];?>&flexible_date=<?php echo $_GET['flexible_date'];?>&depshift=<?php echo $_GET['depshift'];?>&retshift=<?php echo $_GET['retshift'];?>&class=<?php echo $_GET['class'];?>&pax_type=<?php echo $_GET['pax_type'];?>&direct_only=<?php echo $_GET['direct_only'];?>&sid=d22006iqbr4szuz9mry5hcjn0sklw2&st=<?php echo $_GET['st'];?>&aid=gaura&lang=en_GB&conso=gaura&adt=<?php echo $_GET['adt'];?>&chd=<?php echo $_GET['chd'];?>&inf=<?php echo $_GET['inf'];?>&depdate1=<?php echo $_GET['depdate1'];?>&retdate1=<?php echo $_GET['retdate1'];?>&depapt1=<?php echo $_GET['depapt1'];?>&dstapt1=<?php echo $_GET['dstapt1'];?>">
</div>
<?php }
if($is_ypsilon == 0 || $is_ypsilon == 2)
{
$departDate = date('Y-m-d', strtotime($_GET['depdate1']));
$returnDate = date('Y-m-d', strtotime($_GET['retdate1']));
$isReturn = 1;
if($_GET['retdate1'] != '')
{
    $isReturn = 2;
}
?>
<iframe src="https://www.bookingconnect.app/Gauratravel/flightresults?Return=<?php echo $isReturn; ?>&Origin=<?php echo $_GET['depapt1']; ?>&Destination=<?php echo $_GET['dstapt1']; ?>&
DepartDate=<?php echo $departDate; ?>&ReturnDate=<?php echo $returnDate; ?>&Adults=<?php echo $_GET['adt']; ?>&Children=<?php echo $_GET['chd']; ?>&Infants=<?php echo $_GET['inf']; ?>&
Evoucher=&Cabin=Economy&id=ab32c622-858f-437c-ac32-2ef9642fae5d" style="border:none; height:1250px; width:100%;" title="booking connect response"></iframe>
<?php
}

$aobc_enabled_users = get_option( 'AOBC enabled for' );
$aobc_user_names = array_map('trim', explode(',', $aobc_enabled_users));

if (isset($currnt_userlogn) && in_array($currnt_userlogn, $aobc_user_names)) 
{
$departDate = date('Y-m-d', strtotime($_GET['depdate1']));
$returnDate = date('Y-m-d', strtotime($_GET['retdate1']));
$isReturn = 1;
if($_GET['retdate1'] != '')
{
    $isReturn = 2;
}
?>
<iframe src="https://www.bookingconnect.app/Gauratravel/flightresults?Return=<?php echo $isReturn; ?>&Origin=<?php echo $_GET['depapt1']; ?>&Destination=<?php echo $_GET['dstapt1']; ?>&
DepartDate=<?php echo $departDate; ?>&ReturnDate=<?php echo $returnDate; ?>&Adults=<?php echo $_GET['adt']; ?>&Children=<?php echo $_GET['chd']; ?>&Infants=<?php echo $_GET['inf']; ?>&
Evoucher=&Cabin=Economy&id=ab32c622-858f-437c-ac32-2ef9642fae5d" style="border:none; height:1250px; width:100%;" title="booking connect response"></iframe>
<?php
}
?>
<script>
/*
window.onload = function() {
    var div = document.getElementById('ypsnet-ibe');
    var iframe = div.querySelector('iframe');
    iframe.onload = function() {
        window.scrollTo(0, 0);
        
        div.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
        
    };
};*/
document.addEventListener("DOMContentLoaded", function () {
    window.addEventListener("message", function (event) {
        // Check if the event is related to the iframe scroll
        if (event.data && event.data.scroll === true && event.data["scroll-top"] === true) {
            // Force scrolling to the top of the page
            window.scrollTo(0, 0);
        }
    });
});

$(document).ready(function () {
    // Attach the event listener to a parent element
    $(document).on('click', '.ypsilon_pricing_box', function () {
        let message = $(this).data("message");
        showModelStockBox(message);
    });
});
/*$(".ypsilon_pricing_box").on('click', function() {
    let message = $(this).data("message");
    showModelStockBox(message);
});*/

$(".gdeals_price_box").on('click', function() {
    let message = $(this).data("message");
    showModelStockBox(message);
});

function showModelStockBox(message) 
{
    const overlay = document.getElementById('modal-stockbox-overlay2');
    const modal = document.getElementById('stock-model-stockbox2');
    const messageBox = document.getElementById('model-stockbox-message2');
    messageBox.innerText = message;
                        
    // Show overlay and modal
    overlay.style.display = 'block';
    modal.style.display = 'block';
}

function hideModelStockBox() {
                        const overlay = document.getElementById('modal-stockbox-overlay2');
                        const modal = document.getElementById('stock-model-stockbox2');
                        
                        // Hide overlay and modal
                        overlay.style.display = 'none';
                        modal.style.display = 'none';
    
                        $("#stock-model-stockbox2").fadeOut();
                    }
                
                    // Close modal when close button is clicked
                    $(".close-button2").click(function() {
                        hideModelStockBox();
                    });
                                            
</script>
<script src="https://flr.ypsilon.net/static/resize/ypsnet-ibe.min.js"></script>
<style type="text/css">
    .footer_grey_sectionn,
    .footer_grey_sectionnn {
        /*display: none !important;*/
    }
</style>


<?php
if(isset($currnt_userlogn) && ($currnt_userlogn == 'sriharshans' || $currnt_userlogn == 'lee'))
{
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), null);
    wp_enqueue_style('custom-modal', get_template_directory_uri() . '/css/custom-modal.css', array(), null);
    ?>
    
    <!-- Price-Watch Sidebar -->
<div id="price-watch-bar">Price Watch</div>

<!-- Price-Watch Popup -->
<div id="price-watch-popup" class="popup custom-modal">
    <div class="custom-modal-dialog">
      <div class="popup-content">
        <span class="close-icon" id="close-popup">&#10006;</span>
    
        <h4>GDeals Price Watch</h4>
        
        <!-- Flight Details -->
        <div id="flight-info">
            <p><strong>From:</strong> <span id="departure-airport"></span></p>
            <p><strong>To:</strong> <span id="destination-airport"></span></p>
            <p><strong>Departure Date:</strong> <span id="departure-date"></span></p>
            <p id="return-date-section"><strong>Return Date:</strong> <span id="return-day"></span></p>
        </div>
        <p>
            Get email updates on the prices of your flights
        </p>
        
        <!-- Email Subscription -->
        <input type="email" id="watch-email" placeholder="Enter your email">
        <button id="subscribe-btn">Subscribe</button>
      </div>
  </div>
</div>
<style>
    /* Sidebar Button */
    #price-watch-bar {
      position: fixed;
      top: 40%;
      left: 0;
      background: #ffbb00;
      color: white;
      padding: 10px;
      writing-mode: vertical-rl; /* Rotate text */
      transform: translateY(-50%);
      cursor: pointer;
      font-weight: bold;
      border-radius: 5px;
      z-index: 10000;
      display: none;
    }
    
    /* Popup Styling */
    .popup {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      justify-content: center;
      align-items: center;
      z-index: 10000;
    }
    
    .popup-content {
      position: relative;
      background: white;
      padding: 20px;
      border-radius: 8px;
      width: 450px;
      text-align: center;
    }
    
    #flight-info p {
      font-size: 16px;
      margin: 5px 0;
    }
    
    #watch-email {
      width: 100%;
      padding: 8px;
      margin-top: 10px;
      border: 1px solid #ccc;
      border-radius: 5px;
    }
    
    #subscribe-btn {
      width: 100%;
      padding: 10px;
      margin-top: 10px;
      background: #ffbb00;
      color: white;
      border: none;
      cursor: pointer;
    }
    
    .close-icon {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 18px;
    cursor: pointer;
    color: black;
}

</style>
<script>
    const urlParams = new URLSearchParams(window.location.search);
    document.addEventListener("DOMContentLoaded", function () {
        if (!urlParams.get("depapt1") || !urlParams.get("dstapt1") || !urlParams.get("depdate1")) {
            document.getElementById("price-watch-bar").style.display = "none";
        } else{
            document.getElementById("price-watch-bar").style.display = "flex";
        }
    })

    // Show Popup on Clicking Price-Watch Bar
    document.getElementById("price-watch-bar").addEventListener("click", function () {
        document.getElementById("price-watch-popup").style.display = "flex";
        getFlightInfoFromURL(); // Load flight details
    });
    
    // Close Popup on Clicking Close Button
    document.getElementById("close-popup").addEventListener("click", function () {
        document.getElementById("price-watch-popup").style.display = "none";
    });
    
    // Close popup when clicking outside of the popup content
    document.getElementById('price-watch-popup').addEventListener('click', function(event) {
      if (event.target === document.getElementById('price-watch-popup')) {
        document.getElementById('price-watch-popup').style.display = 'none';
      }
    });
    
    
    // Extract Flight Info from URL Parameters
    function getFlightInfoFromURL() {
        const departure = `${urlParams.get("fromc")} (${urlParams.get("depapt1")})`; 
        const destination = `${urlParams.get("toc")} (${urlParams.get("dstapt1")})`; 

        document.getElementById("departure-airport").textContent = departure || "Not Provided";
        document.getElementById("destination-airport").textContent = destination || "Not Provided";
        document.getElementById("departure-date").textContent = urlParams.get("depdate1") || "Not Provided";
        document.getElementById("return-day").textContent = urlParams.get("retdate1") || "Not Provided";

        if (!urlParams.get("retdate1")){
            document.getElementById("return-date-section").style.display = "none";
        }
    }
    
    // Handle Subscription Click
    document.getElementById("subscribe-btn").addEventListener("click", function () {
        const email = document.getElementById("watch-email").value;
        document.getElementById("watch-email").value = ""; // Clear input after subscribing

        // Validate email address
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!email) {
            alert("Please enter an email!");
            return;
        }
    
        if (!emailRegex.test(email)) {
            alert("Please enter a valid email address!");
            return;
        }
        
        // Get Itenary Info
        const from = urlParams.get("depapt1");
        const to = urlParams.get("dstapt1");
        const depart_date = urlParams.get("depdate1");
        const return_date = urlParams.get("retdate1") || "";
        
        $.ajax({
                        url: "/wp-content/themes/twentytwenty/templates/tpl_price_watch_submission.php",
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            from: from,
                            to: to,
                            depart_date: depart_date,
                            return_date: return_date,
                            email: email,
                            
                        },
                        success: function(response) {
                            alert("Subscribed successfully with email: " + email);
                            window.location.reload();
                            
                        },
                        error: function(xhr, status, error) {
                            console.error("Error updating HubSpot:", error);
                        }
                    });
    });

</script>
    <?php
}
?>
<?php get_footer(); ?>