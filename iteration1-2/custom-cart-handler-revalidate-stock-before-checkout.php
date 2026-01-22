<?php
date_default_timezone_set("Australia/Melbourne");
// Include WordPress core (needed to access WP functions and classes)
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

if (isset($_POST['pricing_id']) && !isset($_POST['pricing_id_return'])) 
{
    $pricing_id = intval($_POST['pricing_id']);
    if(isset($_POST['pax']))
    {
        $pax = intval($_POST['pax']);
    }
    else
    {
        $pax = 1;
    }

    // Database connection
    global $wpdb;

    // Query to check stock
    //$result = $wpdb->get_row($wpdb->prepare("SELECT stock, pax FROM wpk4_backend_manage_seat_availability WHERE pricing_id = %d", $pricing_id));
    
    $query = $wpdb->prepare("SELECT stock, pax FROM wpk4_backend_manage_seat_availability WHERE pricing_id = %d", $pricing_id);
    $result = $wpdb->get_row($query);

    if ($result === null) {
        error_log("Results not found for SELECT stock, pax FROM wpk4_backend_manage_seat_availability WHERE pricing_id = $pricing_id");
        echo json_encode(['stock_available' => false, 'count' => 0, 'count2' => 0]);
    }
    if(isset($result))
    {
        $stock_count = $result->stock;
        $pax_count = $result->pax;
        $available = (int)$stock_count - (int)$pax_count;
        $count_available = $available . ' ' . $pax;
        if ($result && $available >= $pax) {
            echo json_encode(['stock_available' => true, 'count' => $count_available]);
        } else {
            echo json_encode(['stock_available' => false, 'count' => $count_available]);
        }
    }
    exit;
}

if (isset($_POST['pricing_id']) && isset($_POST['pricing_id_return'])) 
{
    $pricing_id = intval($_POST['pricing_id']);
    $pricing_id_return = intval($_POST['pricing_id_return']);
    $pax = intval($_POST['pax']);

    // Database connection
    global $wpdb;

    // Query to check stock
    $result = $wpdb->get_row($wpdb->prepare("SELECT stock, pax FROM wpk4_backend_manage_seat_availability WHERE pricing_id = %d", $pricing_id));
    
    if ($result === null) 
    {
        error_log("Results not found for SELECT stock, pax FROM wpk4_backend_manage_seat_availability WHERE pricing_id = $pricing_id");
        echo json_encode(['stock_available' => false, 'count' => 0, 'count2' => 0]);
    }
    if(isset($result))
    {
        $stock_count = $result->stock;
        $pax_count = $result->pax;
        $available = (int)$stock_count - (int)$pax_count;
        $count_available = $available . ' ' . $pax;
        
        $result_2 = $wpdb->get_row($wpdb->prepare("SELECT stock, pax FROM wpk4_backend_manage_seat_availability WHERE pricing_id = %d", $pricing_id));
        $stock_count_2 = $result_2->stock;
        $pax_count_2 = $result_2->pax;
        $available_2 = (int)$stock_count_2 - (int)$pax_count_2;
        $count_available_2 = $available_2 . ' ' . $pax_count_2;
        
        if ($result && $result_2 && $available >= $pax && $available_2 >= $pax) {
            echo json_encode(['stock_available' => true, 'count' => $count_available, 'count2' => $count_available_2]);
        } else {
            echo json_encode(['stock_available' => false, 'count' => $count_available, 'count2' => $count_available_2]);
        }
    }
    exit;
}
//echo json_encode(['stock_available' => false, 'count' => 0]);
exit;

?>