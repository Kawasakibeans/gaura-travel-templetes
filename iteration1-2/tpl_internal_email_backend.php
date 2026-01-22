<?php

header("Content-Type: application/json"); // Ensure JSON response
global $wpdb;

// Include WordPress core (needed to access WP functions and classes)
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

$current_user_id = get_current_user_id();

// get email
if (isset($_GET['id'])) {
    $email_id = intval($_GET['id']);
    $email = $wpdb->get_row("SELECT * FROM wpk4_internal_emails WHERE id = $email_id");
    $sender_id = $email->sender_id;
    $receiver_id = $email->receiver_id;
    if ($email) {
        // Mark email as read
        $wpdb->update('wpk4_internal_emails', ['is_read' => 1], ['id' => $email_id]);

        // Fetch sender's name
        $sender_name = get_userdata($sender_id)->display_name;
        
        // Fetch sender's email
        $sender_email = get_userdata($sender_id)->user_email;

        // Fetch receiver's name
        $receiver_name = get_userdata($receiver_id)->display_name;
        
        // Fetch sender's email
        $receiver_email = get_userdata($receiver_id)->user_email;
        // Return email details as JSON
        echo json_encode([
            'subject' => esc_html($email->subject),
            'message' => nl2br(esc_html($email->message)),
            'sender'  => esc_html($sender_name),
            'sender_id' => $sender_id,
            'receiver'  => esc_html($receiver_name),
            'receiver_id' => $receiver_id,
            // 'parent_email_id' => $parent_email_id,
            'date'    => date('d M Y, H:i', strtotime($email->created_at)),
            'email'   => $sender_email
        ]);
    } else {
        echo json_encode(['error' => 'Email not found']);
    }
    exit;
}

// get email and its related child email (replies)
if (isset($_GET['thread_id'])) {
    $email_id = intval($_GET['thread_id']);
    $wpdb->update('wpk4_internal_emails', ['is_read' => 1], ['id' => $email_id]);

    $root_email = $wpdb->get_row("SELECT * FROM wpk4_internal_emails WHERE id = $email_id");

    if ($root_email) {
        $thread_root_id = $root_email->parent_email_id ?: $root_email->id;

        $thread_emails = $wpdb->get_results("
            SELECT * FROM wpk4_internal_emails
            WHERE id = $thread_root_id OR parent_email_id = $thread_root_id
            ORDER BY created_at ASC
        ");

        $thread_data = array_map(function($email) {
            return [
                'id'        => $email->id,
                'sender'    => get_userdata($email->sender_id)->display_name,
                'receiver'  => get_userdata($email->receiver_id)->display_name,
                // 'receiver_login' => get_userdata($email->receiver_id)->user_login,
                'subject'   => esc_html($email->subject),
                'message'   => nl2br(esc_html($email->message)),
                'created_at'=> date('d M Y, H:i', strtotime($email->created_at)),
                'parent_email_id' => $email->parent_email_id,
                'sender_id' => $email->sender_id,
                'sender_email' => get_userdata($email->receiver_id)->user_email,
            ];
        }, $thread_emails);

        echo json_encode($thread_data);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Thread not found']);
    }
    exit;
}

// send an email (create a new email)
if (isset($_POST['receiver']) && isset($_POST['subject']) && isset($_POST['message']) && isset($_POST['is_draft']) ) {

    $receiver = sanitize_text_field($_POST['receiver']);
    $subject = sanitize_text_field($_POST['subject']);
    $message = sanitize_textarea_field($_POST['message']);
    $is_draft = $_POST['is_draft'];
    $draft_id = isset($_POST['draft_id']) ? intval($_POST['draft_id']) : null;

    // set parent_id if it is sent from the front-end
    $parent_email_id = isset($_POST['parent_email_id']) ? intval($_POST['parent_email_id']) : null;
    if ($draft_id) {
        $updated = $wpdb->update(
            "wpk4_internal_emails",
            [
                "receiver_id" => $receiver,
                "subject"     => $subject,
                "message"     => $message,
                "is_draft"    => $is_draft,
                "created_at"  => current_time('mysql')
                ],
                ["id" => $draft_id, "sender_id" => $current_user_id]
            );
        if ($updated !== false) {
            echo json_encode(["status" => "success", "type" => "updated"]);
            exit;
        }
    }
    
    
    // $receiver = $wpdb->get_var($wpdb->prepare("SELECT ID FROM wpk4_users WHERE id = %s", $receiver));
    
    if ($receiver) {
        $wpdb->insert("wpk4_internal_emails", [
            "sender_id" => $current_user_id,
            "receiver_id" => $receiver,
            "parent_email_id" => $parent_email_id,
            "subject" => $subject,
            "message" => $message,
            "created_at" => current_time('mysql'),
            "is_read" => 0,
            "is_draft" => $is_draft
        ]);
        http_response_code(200);

        echo json_encode(["status" => "success"]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Email not found']);
    }
    exit;
}

// query for get user-related info
if (isset($_GET['query'])) {
    global $wpdb;
    $search_term = sanitize_text_field($_GET['query']);

$excluse_params = "(wpk4_usermeta.meta_value != 'a:0:{}' AND wpk4_usermeta.meta_value != 'a:1:{s:8:\"customer\";b:1;}')";

// Fetch users whose usernames or emails match the input
$users = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT wpk4_users.id, user_email, display_name 
         FROM wpk4_users 
         JOIN wpk4_usermeta ON wpk4_users.id = wpk4_usermeta.user_id 
         WHERE (wpk4_users.user_login LIKE %s OR wpk4_users.user_email LIKE %s) 
         AND wpk4_usermeta.meta_key = 'wpk4_capabilities' 
         AND $excluse_params 
         LIMIT 20",
        "%" . $wpdb->esc_like($search_term) . "%",
        "%" . $wpdb->esc_like($search_term) . "%"
    )
);

    // Return JSON response
    echo json_encode($users);
    exit;
} 

if (isset($_GET['fetch_inbox'])) {
    global $wpdb;

    // Get the latest emails for the user
    // $emails = $wpdb->get_results(
    //     $wpdb->prepare(
    //         "SELECT e.id, e.subject, DATE_FORMAT(e.created_at, '%d %b %Y, %H:%i') AS formatted_date, e.is_read, u.display_name AS sender_name 
    //          FROM wpk4_internal_emails e 
    //          JOIN wpk4_users u ON e.sender_id = u.ID 
    //          WHERE e.receiver_id = %d 
    //          ORDER BY e.created_at DESC",
    //         $current_user_id
    //     )
    // );
    $sql = "
         SELECT id, sender_id, receiver_id, subject, message, parent_email_id, is_read, DATE_FORMAT(created_at, '%%d %%b %%Y, %%H:%%i') as formatted_date FROM wpk4_internal_emails
    WHERE parent_email_id IS NULL
    AND (
        receiver_id = $current_user_id
        OR (
            sender_id = $current_user_id
            AND EXISTS (
                SELECT 1 FROM wpk4_internal_emails AS replies
                WHERE replies.parent_email_id = wpk4_internal_emails.id
            )
        )
    ) AND is_draft = 0
    ORDER BY created_at DESC
    ";
    
    $emails = $wpdb->get_results(
        $wpdb->prepare($sql)
    );
    foreach ($emails as &$email) {
        $sender = get_userdata($email->sender_id);
        $receiver = get_userdata($email->receiver_id);
        
        $email->sender_name = $sender ? $sender->display_name : 'Unknown';
        $email->receiver_name = $receiver ? $receiver->display_name : 'Unknown';
    }
    echo json_encode($emails);
    exit;
}

$type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
if($type){
    if ($type === 'inbox') {
            $sql = "
             SELECT id, sender_id, receiver_id, subject, message, parent_email_id, is_read, DATE_FORMAT(created_at, '%%d %%b %%Y, %%H:%%i') as formatted_date FROM wpk4_internal_emails
        WHERE parent_email_id IS NULL
        AND (
            receiver_id = $current_user_id
            OR (
                sender_id = $current_user_id
                AND EXISTS (
                    SELECT 1 FROM wpk4_internal_emails AS replies
                    WHERE replies.parent_email_id = wpk4_internal_emails.id
                )
            )
        ) AND is_draft = 0
        ORDER BY created_at DESC
        ";
        
        $emails = $wpdb->get_results(
            $wpdb->prepare($sql)
        );
        foreach ($emails as &$email) {
            $sender = get_userdata($email->sender_id);
            $receiver = get_userdata($email->receiver_id);
            
            $email->sender_name = $sender ? $sender->display_name : 'Unknown';
            $email->receiver_name = $receiver ? $receiver->display_name : 'Unknown';
        }
        echo json_encode($emails);
        exit;
    }
    
    else if ($type === 'sent') {
        $emails = $wpdb->get_results(" 
            SELECT id, sender_id, receiver_id, subject, message, parent_email_id, is_read, DATE_FORMAT(created_at, '%d %b %Y, %H:%i') as formatted_date FROM wpk4_internal_emails
            WHERE parent_email_id IS NULL
            AND sender_id = $current_user_id
            AND is_draft = 0
            ORDER BY created_at DESC
        ");
        
        foreach ($emails as &$email) {
            $sender = get_userdata($email->sender_id);
            $receiver = get_userdata($email->receiver_id);
            
            $email->sender_name = $sender ? $sender->display_name : 'Unknown';
            $email->receiver_name = $receiver ? $receiver->display_name : 'Unknown';
        }
        echo json_encode($emails);
        exit;
    }
    
    else if ($type === 'draft') {
        $emails = $wpdb->get_results("
            SELECT id, sender_id, receiver_id, subject, message, parent_email_id, is_read, DATE_FORMAT(created_at, '%d %b %Y, %H:%i') as formatted_date FROM wpk4_internal_emails 
            WHERE sender_id = $current_user_id
            AND is_draft = 1
            ORDER BY created_at DESC
        ");
        
        foreach ($emails as &$email) {
            $receiver = get_userdata($email->receiver_id);
            $email->receiver_name = $receiver ? $receiver->display_name : 'Unknown';
        }
        echo json_encode($emails);
        exit;
    }
}

// fallback
echo json_encode(['error' => 'Invalid request']);
http_response_code(400);
exit;



?>