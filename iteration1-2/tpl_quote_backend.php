<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
global $wpdb;
header('Content-Type: application/json');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Ensure PHPMailer classes exist
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
    require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
    require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['product_id']) && isset($_GET['travel_date'])) {
 
    // Sanitize and validate input parameters
    $product_id   = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
    $travel_date  = isset($_GET['travel_date']) ? sanitize_text_field($_GET['travel_date']) : '';
    $category_id  = '953'; // Static for now, make dynamic if needed

    
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT wbspm.product_id, wbspm.trip_code, wbspm.product_title ,wwpcr.regular_price as price, (wbmsa.stock - wbmsa.pax) as availability FROM wpk4_backend_stock_product_manager AS wbspm

            -- Join with price category relation
            JOIN wpk4_wt_price_category_relation AS wwpcr 
                ON wbspm.pricing_id = wwpcr.pricing_id
            
            -- Join with seat availability table
            JOIN wpk4_backend_manage_seat_availability AS wbmsa 
                ON wbspm.pricing_id = wbmsa.pricing_id
            
            -- Filters
            WHERE wwpcr.pricing_category_id = %s
              AND wbspm.product_id = %d
              AND wbspm.travel_date LIKE %s;
            ",
            $category_id,
            $product_id,
            '%' . $wpdb->esc_like($travel_date) . '%'
        )
    );
    
    echo json_encode(["message" => 'success', "itinerary" => $results]);
    exit;
}

// query for get user-related info
if (isset($_GET['query'])) {
    global $wpdb;
    $search_term = sanitize_text_field($_GET['query']);

    // Fetch users whose usernames match the input
    $users = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, user_email, display_name FROM wpk4_users WHERE user_login LIKE %s or user_email LIKE %s LIMIT 10",
            "%" . $wpdb->esc_like($search_term) . "%",
            "%" . $wpdb->esc_like($search_term) . "%"
        )
    );
    // Return JSON response
    echo json_encode($users);
    exit;
} 


// update quote status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ===== SEND QUOTE EMAIL =====
    if (isset($_POST['action']) && $_POST['action'] === 'send_quote_email') {
        $email = sanitize_text_field($_POST['email']);
        $url   = sanitize_text_field($_POST['url']);
        $paxname   = ucfirst(strtolower(sanitize_text_field($_POST['paxname'])));
        $agentname   = ucfirst(strtolower(sanitize_text_field($_POST['agentname'])));

        if (empty($email) || empty($url)) {
            echo json_encode(['error' => 'Missing email or URL']);
            exit;
        }
        $to = '';
        $subject = "Your Flight Quote from Gaura Travel";
        $message = "
            Dear $paxname,<br><br>
            This is your ready link. By clicking it, you can view the quote provided by your travel advisor ($agentname), and proceed with your booking by clicking on Book Now.<br><br>
            <a href='$url' target='_blank'>Click Here</a><br><br>
            Thank you for choosing Gaura Travel!
        ";
        /*
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Gaura Travel <donotreply@gauratravel.com.au>" . "\r\n";
        
        if (mail('sriharshans@gauratravel.com.au', $subject, $message, $headers)) {
            echo json_encode(['success' => 'Email sent via PHP mail()']);
        } else {
            echo json_encode(['error' => 'Failed to send email via mail()']);
        }
        exit;*/

        
        $mail = new PHPMailer();
        $mail->isSMTP();
        $mail->Host = 'smtp.office365.com';
        $mail->Port = 587;
        $mail->SMTPAuth = true;
        $mail->Username = 'donotreply@gauratravel.com.au';
		$mail->Password = 'P/738625763818ob';
        $mail->SMTPSecure = 'tls';
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $mail->From = 'donotreply@gauratravel.com.au';
        $mail->FromName = 'Gaura Travel';
        //$mail->SMTPDebug  = 2;
        $mail->addAddress($email, 'Passenger');
        //$mail->addAddress('sriharshans@gauratravel.com.au', 'Passenger');
        //$mail->addAddress('leen@gauratravel.com.au', 'Passenger');
        //$mail->addAddress('ashwinis@gauratravel.com.au', 'Passenger');
        $mail->isHTML(true);
        $mail->Subject = "Your Flight Quote from Gaura Travel";
        $mail->Body = $message;


        try {
            if ($mail->send()) {
                echo json_encode(['success' => "Email sent to $email"]);
                //exit;
            } else {
                echo json_encode(['error' => 'Email failed: ' . $mail->ErrorInfo]);
                //exit;
            }
        } catch (Exception $e) {
            echo json_encode(['error' => 'Mailer Exception: ' . $e->getMessage()]);
            exit;
        }
        
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'send_quote_email_multicity') {
        $email = sanitize_text_field($_POST['email']);
        $url   = sanitize_text_field($_POST['url']);
        $paxname   = ucfirst(strtolower(sanitize_text_field($_POST['paxname'])));
        $agentname   = ucfirst(strtolower(sanitize_text_field($_POST['agentname'])));

        if (empty($email) || empty($url)) {
            echo json_encode(['error' => 'Missing email or URL']);
            exit;
        }
        $to = '';
        $subject = "Your Flight Quote from Gaura Travel";
        $message = "
            Dear $paxname,<br><br>
            This is your ready link. By clicking it, you can view the quote provided by your travel advisor ($agentname), and proceed with your booking by clicking on Book Now.<br><br>
            <a href='$url' target='_blank'>Click Here</a><br><br>
            Thank you for choosing Gaura Travel!
        ";
        /*
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Gaura Travel <donotreply@gauratravel.com.au>" . "\r\n";
        
        if (mail('sriharshans@gauratravel.com.au', $subject, $message, $headers)) {
            echo json_encode(['success' => 'Email sent via PHP mail()']);
        } else {
            echo json_encode(['error' => 'Failed to send email via mail()']);
        }
        exit;*/

        
        $mail = new PHPMailer();
        $mail->isSMTP();
        $mail->Host = 'smtp.office365.com';
        $mail->Port = 587;
        $mail->SMTPAuth = true;
        $mail->Username = 'donotreply@gauratravel.com.au';
		$mail->Password = 'P/738625763818ob';
        $mail->SMTPSecure = 'tls';
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $mail->From = 'donotreply@gauratravel.com.au';
        $mail->FromName = 'Gaura Travel';
        //$mail->SMTPDebug  = 2;
        $mail->addAddress($email, 'Passenger');
        //$mail->addAddress('sriharshans@gauratravel.com.au', 'Passenger');
        //$mail->addAddress('leen@gauratravel.com.au', 'Passenger');
        //$mail->addAddress('ashwinis@gauratravel.com.au', 'Passenger');
        $mail->isHTML(true);
        $mail->Subject = "Your Flight Quote from Gaura Travel";
        $mail->Body = $message;


        try {
            if ($mail->send()) {
                echo json_encode(['success' => "Email sent to $email"]);
                //exit;
            } else {
                echo json_encode(['error' => 'Email failed: ' . $mail->ErrorInfo]);
                //exit;
            }
        } catch (Exception $e) {
            echo json_encode(['error' => 'Mailer Exception: ' . $e->getMessage()]);
            exit;
        }
        
    }

    // ===== UPDATE QUOTE STATUS =====
    if (isset($_POST['phone'])) 
    {
        global $wpdb;
    
        $phone = sanitize_text_field($_POST['phone']);
    
        if (!empty($phone)) {
            $table_quote = 'wpk4_quote';
            $table_pax = 'wpk4_backend_travel_booking_pax';
            $table_bookings = 'wpk4_backend_travel_bookings';
            $table_agents = 'wpk4_backend_agent_codes';
    
            $query = "
                UPDATE $table_quote AS q
                INNER JOIN $table_pax AS p ON q.phone_num = p.phone_pax
                INNER JOIN $table_bookings AS b ON p.order_id = b.order_id
                INNER JOIN $table_agents AS a ON q.tsr = a.tsr
                SET q.status = 1
                WHERE a.sales_id = b.agent_info
                  AND DATE(b.order_date) = CURDATE()
                  AND q.phone_num = %s
            ";
    
            $prepared_query = $wpdb->prepare($query, $phone);
            $result = $wpdb->query($prepared_query);
    
            wp_send_json([
                'success' => $result !== false,
                'rows_updated' => $result
            ]);
        } else {
            wp_send_json_error(['error' => 'Phone number is empty']);
        }
    }

    echo json_encode(['error' => 'Invalid POST action']);
    exit;
}
/*
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['phone'])) {
    global $wpdb;

    $phone = sanitize_text_field($_POST['phone']);

    if (!empty($phone)) {
        $table_quote = 'wpk4_quote';
        $table_pax = 'wpk4_backend_travel_booking_pax';
        $table_bookings = 'wpk4_backend_travel_bookings';
        $table_agents = 'wpk4_backend_agent_codes';

        $query = "
            UPDATE $table_quote AS q
            INNER JOIN $table_pax AS p ON q.phone_num = p.phone_pax
            INNER JOIN $table_bookings AS b ON p.order_id = b.order_id
            INNER JOIN $table_agents AS a ON q.tsr = a.tsr
            SET q.status = 1
            WHERE a.sales_id = b.agent_info
              AND DATE(b.order_date) = CURDATE()
              AND q.phone_num = %s
        ";

        $prepared_query = $wpdb->prepare($query, $phone);
        $result = $wpdb->query($prepared_query);

        wp_send_json([
            'success' => $result !== false,
            'rows_updated' => $result
        ]);
    } else {
        wp_send_json_error(['error' => 'Phone number is empty']);
    }
}
*/

    echo json_encode(['error' => 'Invalid request method']);
    exit;
