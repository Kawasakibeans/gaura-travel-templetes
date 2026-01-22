

<?php
/**
 * Template Name: Quote Details
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 */
get_header();


$quote_id = intval($_GET['quote_id']);

global $wpdb;

// Get main quote
$quote = $wpdb->get_row(
        $wpdb->prepare("
        SELECT r.rec_duration as duration, r.rec_status as call_status, q.*, u.display_name 
         FROM wpk4_quote q 
         LEFT JOIN wpk4_users u ON q.user_id = u.ID
         LEFT JOIN wpk4_backend_agent_nobel_data_call_rec r ON q.call_record_id = r.d_record_id
         WHERE q.id = %d
         ", $quote_id));
        // add 'AND depart_date >= CURDATE()' if only need quote that has depart date later than today

// Get subquotes
$subquotes = $wpdb->get_results($wpdb->prepare("SELECT * FROM wpk4_quote_G360 WHERE original_quote_id = %d ORDER BY quoted_at desc", $quote_id));
$min_price = null;
// Get min price
if (!empty($subquotes)) {
    $min_price = min(array_map(function($q) { return floatval($q->current_price); }, $subquotes));
}
?>
<style>
    h2 {
        text-align: center;
    }
    .quote-info-list {
        display: flex;
        flex-wrap: wrap;
        list-style: none;
        padding: 0;
        margin: 0;
        gap: 20px; /* optional spacing between items */
    }

    .quote-info-list li {
        background: #f9f9f9;
        padding: 10px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        min-width: 200px;
        flex: 1 1 auto;
    }
    
    .table-container {
        width: 100%;
        overflow-x: auto;
        padding: 0px 20px;
    }
    
    .info-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;    
    }
    
    .info-table th,
    .info-table td {
        padding: 10px 15px;
        border: 1px solid #ddd;
        text-align: center;
        word-wrap: break-word;
        overflow: visible;
        text-overflow: initial;
        white-space: normal;
    }
        
    .info-table th {
        padding: 5px 5px;
        background-color: #0073aa;
        color: white;
        vertical-align: middle;  /* Centers the text vertically */
        line-height: 1.5;        /* Adjust line height if necessary for better alignment */
    }
        
    .info-table tbody td {
        padding: 5px;
        border: 1px solid #ddd;
        word-wrap: break-word;
        overflow: visible;
        text-overflow: initial;
        white-space: normal;
    }
    
    .view-button {
        padding: 6px 12px;
        background-color: #FFBB00;
        border-radius: 5px;
        font-size: 14px
    }
    
    .highlight-row {
        background-color: #e6ffe6; /* light green background */
        font-weight: bold;
    }
    .lowest-badge {
        background-color: #28a745;
        color: #fff;
        font-size: 10px;
        font-weight: bold;
        padding: 2px 6px;
        border-radius: 4px;
        margin-left: 6px;
        cursor: help;
        display: inline-block;
    }
    
    tr.highlight-row:hover .lowest-badge {
        background-color: #218838;
}        

</style>

<!--Main quote's info-->
<h2>Quote</h2>


<div class="table-container">
    <table class="info-table">
        <colgroup>
            <col style="width: 80px;">    <!-- Call ID -->
            <col style="width: 80px;">    <!-- Quote ID -->
            <col style="width: 80px;">    <!-- From -->
            <col style="width: 80px;">    <!-- To -->
            <col style="width: 120px;">   <!-- Depart Date -->
            <col style="width: 80px;">    <!-- Price -->
            <col style="width: 100px;">   <!-- Quoted At -->
            <col style="width: 120px;">   <!-- Pax Name -->
            <col style="width: 200px;">   <!-- Email -->
            <col style="width: 150px;">   <!-- Phone -->
            <col style="width: 80px;">    <!-- URL -->
        </colgroup>
        <thead>
            <tr>
                <th>Call ID</th>
                <th>Quote ID</th>
                <th>From</th>
                <th>To</th>
                <th>Travel Date</th>
                <th>Price</th>
                <th>Quoted At</th>
                <th>Pax Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>URL</th>
            </tr>
        </thead>
        <tbody>
                <tr class="<?php echo floatval($quote->current_price) == $min_price ? 'highlight-row' : ''; ?>">
                    <td><?php echo esc_html($quote->call_record_id); ?></td>
                    <td><?php echo esc_html($quote->id); ?></td>
                    <td><?php echo esc_html($quote->depart_apt); ?></td>
                    <td><?php echo esc_html($quote->dest_apt); ?></td>
                    <td><?php echo esc_html($quote->depart_date); ?></td>
                    <td>
                        $<?php echo number_format($quote->current_price, 2); ?>
                        <?php if (floatval($quote->current_price) == $min_price): ?>
                            <span class="lowest-badge" title="This is the lowest quoted price">Lowest</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($quote->quoted_at); ?></td>
                    <td><?php echo esc_html($quote->name); ?></td>
                    <td><?php echo esc_html($quote->email); ?> <br> <button class='view-button' onclick="sendPriceUpdateEmail('<?php echo esc_html($quote->original_quote_id); ?>', '<?php echo $quote->current_price; ?>', this)">Send Email</button></td>
                    <td><?php echo esc_html($quote->phone_num); ?> <br> <button class='view-button' onclick="sendPriceUpdateSMS('<?php echo esc_html($quote->original_quote_id); ?>', '<?php echo $quote->current_price; ?>', this)">Send SMS</button></td>
                    <td>
                        <?php if(!empty($quote->url) && $quote->url != '0'):?>
                            <a target="_blank" href="https://gauratravel.com.au/flights/<?php echo esc_html($quote->url); ?>">Link</a>
                        <?php else: ?>
                            N/A
                        <?php endif;?>
                    </td>
                </tr>
        </tbody>
    </table>
</div>

<!--Sub quotes table-->
<h2>G360 Quotes</h2>
<?php if (!empty($subquotes)) : ?>
    <div class="table-container">
    <table class="info-table">
        <colgroup>
            <col style="width: 80px;">    <!-- Call ID -->
            <col style="width: 80px;">    <!-- Quote ID -->
            <col style="width: 80px;">    <!-- From -->
            <col style="width: 80px;">    <!-- To -->
            <col style="width: 120px;">   <!-- Depart Date -->
            <col style="width: 80px;">    <!-- Price -->
            <col style="width: 100px;">   <!-- Quoted At -->
            <col style="width: 120px;">   <!-- Pax Name -->
            <col style="width: 200px;">   <!-- Email -->
            <col style="width: 150px;">   <!-- Phone -->
            <col style="width: 80px;">    <!-- URL -->
        </colgroup>
        <thead>
            <tr>
                <th>Call ID</th>
                <th>Original Quote ID</th>
                <th>From</th>
                <th>To</th>
                <th>Travel Date</th>
                <th>Price</th>
                <th>Quoted At</th>
                <th>Pax Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>URL</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($subquotes as $quote): ?>
                <tr class="<?php echo floatval($quote->current_price) == $min_price ? 'highlight-row' : ''; ?>">
                    <td><?php echo esc_html($quote->call_record_id); ?></td>
                    <td><?php echo esc_html($quote->original_quote_id); ?></td>
                    <td><?php echo esc_html($quote->depart_apt); ?></td>
                    <td><?php echo esc_html($quote->dest_apt); ?></td>
                    <td><?php echo esc_html($quote->depart_date); ?></td>
                    <td>
                        $<?php echo number_format($quote->current_price, 2); ?>
                        <?php if (floatval($quote->current_price) == $min_price): ?>
                            <span class="lowest-badge" title="This is the lowest quoted price">Lowest</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($quote->quoted_at); ?></td>
                    <td><?php echo esc_html($quote->name); ?></td>
                    <td><?php echo esc_html($quote->email); ?> <br> <button class='view-button' onclick="sendPriceUpdateEmail('<?php echo esc_html($quote->original_quote_id); ?>', '<?php echo $quote->current_price; ?>', this)">Send Email</button></td>
                    <td><?php echo esc_html($quote->phone_num); ?> <br> <button class='view-button' onclick="sendPriceUpdateSMS('<?php echo esc_html($quote->original_quote_id); ?>', '<?php echo $quote->current_price; ?>', this)">Send SMS</button></td>
                    <td>
                        <?php if(!empty($quote->url) && $quote->url != '0'):?>
                            <a target="_blank" href="https://gauratravel.com.au/flights/<?php echo esc_html($quote->url); ?>">Link</a>
                        <?php else: ?>
                            N/A
                        <?php endif;?>
                    </td>
                </tr>
            <?php endforeach; ?>

        </tbody>
    </table>
</div>
<?php else : ?>
    <p>No subquotes found.</p>
<?php endif; ?>

<!--<?php print_r( $quote);?>-->

<script>
        // handler for sending price update email
        function sendPriceUpdateEmail(quoteId, price, button){
            button.disabled = true;
            button.textContent = 'Sending...';
            fetch('/wp-content/themes/twentytwenty/templates/tpl_quote_backend.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    quote_id: quoteId,
                    price: price,
                    email: '1'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.message) {
                    button.textContent = 'Sent ✅';
                } else {
                    button.textContent = 'Failed ❌';
                }
            })
            .catch(error => {
                console.error('Email sending failed:', error);
                alert('Failed to send email.');
                button.textContent = 'Failed ❌';
            })
            .finally(() => {
                setTimeout(() => {
                    button.disabled = false;
                    button.textContent = 'Send Email';
                }, 2000); // Revert after delay
            });
    
        }
        
        // handler for sending price update sms
        function sendPriceUpdateSMS(quoteId, price, button){
            button.disabled = true;
            button.textContent = 'Sending...';
            fetch('/wp-content/themes/twentytwenty/templates/tpl_quote_backend.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    quote_id: quoteId,
                    price: price,
                    sms: '1'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.message) {
                    button.textContent = 'Sent ✅';
                } else {
                    button.textContent = 'Failed ❌';
                }
            })
            .catch(error => {
                console.error('Email sending failed:', error);
                alert('Failed to send sms.');
                button.textContent = 'Failed ❌';

            })
            .finally(() => {
                setTimeout(() => {
                    button.disabled = false;
                    button.textContent = 'Send SMS';
                }, 2000); // Revert after delay
            });
    
        }
        
        // add event listener for update button        
        document.querySelectorAll("#update-button").forEach(button => {
            button.addEventListener("click", function () {
                const phone = this.getAttribute("data-phone");
        
                fetch('/wp-content/themes/twentytwenty/templates/tpl_quote_backend.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        phone: phone
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if(data.rows_updated > 0){
                            alert('Status updated successfully');
                            // Get the parent <li> of the clicked button
                            const td = button.closest('li');
                            // Replace the contents with "Converted"
                            td.innerHTML = '<strong>Status: </strong> Converted';
                        }else{
                            alert('No Bookings found');
                        }
                    } else {
                        alert("Failed to update status.");
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("An error occurred.");
                });
            });
});
</script>

<?php get_footer(); ?>