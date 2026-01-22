<?php
/**
 * Template Name: Internal Email
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 * @author Franco
 */
get_header();?>
<div class='wpb_column vc_column_container vc_col-sm-12' id='manage_bookings' style='width:95%;margin:auto;padding:50px 0px;'>
<?php
date_default_timezone_set("Australia/Melbourne"); 
error_reporting(E_ALL);
include("wp-config-custom.php");
$current_time = date('Y-m-d H:i:s');

global $current_user;
$currnt_userlogn = isset($current_user->user_login) ? $current_user->user_login : '';

// Get IP address if not already defined
if (!isset($ip_address)) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
}

// Check IP and user login
$is_ip_matched = 0;
if (isset($mysqli) && $ip_address && $currnt_userlogn) {
    $query_ip_selection = "SELECT * FROM wpk4_backend_ip_address_checkup where ip_address='" . mysqli_real_escape_string($mysqli, $ip_address) . "'";
    $result_ip_selection = mysqli_query($mysqli, $query_ip_selection);
    if ($result_ip_selection) {
        $row_ip_selection = mysqli_fetch_assoc($result_ip_selection);
        $is_ip_matched = mysqli_num_rows($result_ip_selection);
    }
}

if($is_ip_matched > 0 && isset($currnt_userlogn) && $currnt_userlogn != '')
{
    
$current_user_id = get_current_user_id();

// Load WordPress to access constants
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php')) {
    require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
}

// Use API_BASE_URL constant if defined, otherwise use default
if (defined('API_BASE_URL')) {
    /** @var string $api_url */
    $api_url = constant('API_BASE_URL');
} else {
    $api_url = 'https://gt1.yourbestwayhome.com.au/wp-content/themes/twentytwenty/templates/database_api/public/v1';
}

/**
 * Helper function to get emails via API
 */
function getEmailsViaAPI($api_url, $endpoint, $user_id) {
    $ch = curl_init($api_url . $endpoint . '?user_id=' . $user_id);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['data'])) {
            return $data['data'];
        }
    }
    return [];
}

// Get emails via API
$emails = getEmailsViaAPI($api_url, '/internal-emails/inbox', $current_user_id);
$sent_emails = getEmailsViaAPI($api_url, '/internal-emails/sent', $current_user_id);
$draft_emails = getEmailsViaAPI($api_url, '/internal-emails/draft', $current_user_id);

// Convert to objects for compatibility with existing code
$emails = array_map(function($email) {
    return (object)$email;
}, $emails);
$sent_emails = array_map(function($email) {
    return (object)$email;
}, $sent_emails);
$draft_emails = array_map(function($email) {
    return (object)$email;
}, $draft_emails);
// echo print_r($emails); 
//echo "<script>console.log(" . json_encode($emails) . ");</script>";
//William debugging 12/12/2025 - $emails is empty, getEmailsViaAPI is returning empty array
?>

    <button class='email-button' id="inbox-button">📥 Inbox</button>
    <button class='email-button' id="sent-button">📤 Sent</button>
    <button class='email-button' id="draft-button">📄 Draft</button>
    <button class='email-button' id="compose-btn" onclick="showComposeModal()">✉️ Compose</button>


    <div class="inbox-container" id="inbox-container">
        <div id="email-list">
            <h2>📥 Inbox</h2>
            <table>                 
            <?php if(!empty($emails)): ?>
                <thead>
                    <tr>
                        <th>From</th>
                        <th>To</th>
                        <th>Subject</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                        <?php foreach ($emails as $email) : ?>
                                <tr data-id="<?php echo $email->id ?>" class="<?php if(isset($email->is_read) && $email->is_read == 1) { echo 'read'; } else { echo 'unread'; } ?> row2" onclick="openEmail(<?php echo $email->id ?>)">
                                        <td><?php echo isset($email->sender_name) ? esc_html($email->sender_name) : 'Unknown' ?></td>
                                        <td><?php echo isset($email->receiver_name) ? esc_html($email->receiver_name) : 'Unknown' ?></td>
                                        
                                        <td><?php echo esc_html($email->subject) ?></td>
                                        <td><?php echo isset($email->formatted_date) ? esc_html($email->formatted_date) : (isset($email->created_at) ? date('d M Y, H:i', strtotime($email->created_at)) : '') ?></td>
                                </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div>Your inbox is empty:)</div>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    
        <!-- Email Content Display Section -->
        <div id="email-container" class='email-container'>
            <button id='back-button' onclick="backToInbox('inbox')">
                back
            </button>
            <p id='email-content-container'>
                there should email's content
            </p>
            <button onclick="openReply()">Reply</button>

        </div>
    </div>
    <!-- Sent emails container (initially hidden) -->
    <div id="sent-container" class="inbox-container" style="display:none;" >
        <div id='sent-email-list'>
            <h2>📤 Sent Emails</h2>
            <table>                 
            <?php if(!empty($sent_emails)): ?>
                <thead>
                    <tr>
                        <th>From</th>
                        <th>To</th>
                        <th>Subject</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                        <?php foreach ($sent_emails as $email) : ?>
                                <tr data-id = "<?= $email->id ?>" class="read row2" onclick="openSentEmail(<?= $email->id ?>)">
                                        <td><?= isset($email->sender_name) ? esc_html($email->sender_name) : 'Unknown' ?></td>
                                        <td><?= isset($email->receiver_name) ? esc_html($email->receiver_name) : 'Unknown' ?></td>
                                        
                                        <td><?= esc_html($email->subject) ?></td>
                                        <td><?= isset($email->formatted_date) ? esc_html($email->formatted_date) : (isset($email->created_at) ? date('d M Y, H:i', strtotime($email->created_at)) : '') ?></td>
                                </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div>Your have't sent any emails:)</div>
                    <?php endif; ?>
                </tbody>
        </table>
        </div>
        <!-- Email Content Display Section -->
        <div id="sent-email-container" class='email-container'>
            <button id='back-button' onclick="backToInbox('sent')">
                back
            </button>
            <p id='sent-email-content-container'>
                there should email's content
            </p>
            <button onclick="openReply()">Reply</button>

        </div>
    </div>
    <div id="draft-container" class="inbox-container" style="display:none;">
        <div id='draft-email-list'>
            <h2>📄 Drafts</h2>
            <table>
                <thead>
                  <tr>
                    <th>To</th>
                    <th>Subject</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($draft_emails as $draft) : ?>
                    <tr data-id = "<?= $draft->id ?>" onclick="editDraft(<?= $draft->id ?>)" class='row2'>
                      <td><?= isset($draft->receiver_name) ? esc_html($draft->receiver_name) : '(Not Set)' ?></td>
                      <td><?= esc_html($draft->subject) ?></td>
                      <td><?= isset($draft->formatted_date) ? esc_html($draft->formatted_date) : (isset($draft->created_at) ? date('d M Y, H:i', strtotime($draft->created_at)) : '') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div id="compose-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeComposeModal()">&times;</span>
            <h2>Compose Email</h2>
            <form id="compose-form">
                <input type="hidden" id="draft-id" name="draft_id" value="">
                <label for="receiver">To:</label>
                <input type="text" id="receiver" name="receiver" data-sender-id="" placeholder="Type a username..." autocomplete="off">
                <ul id="user-suggestions" class="suggestions-list hidden"></ul>
    
                <label for="subject">Subject:</label>
                <input type="text" id="subject" name="subject" required>
    
                <label for="message">Message:</label>
                <textarea id="message" name="message" required></textarea>
    
                <button type="submit">Send</button>
                <button type="button" id="save-draft-btn">Save as Draft</button>

            </form>
            <div id="compose-loading" class="hidden">Sending...</div>
        </div>
    </div>
    
    
    <script>
    // when an email is opened, it will fetch the email details  
        function openEmail(emailId) {
            console.log("email opened");
            
            // Show loading spinner while fetching the email
            document.getElementById('email-content-container').innerHTML = `
                <div class="loading-container">
                    <div class="spinner"></div>
                    <p>Loading email...</p>
                </div>
            `;
            
            document.getElementById("email-list").style.display = 'none';
            document.getElementById("email-container").style.display = 'block';
            // Fetch email content using AJAX
            fetch("/wp-content/themes/twentytwenty/templates/tpl_internal_email_backend.php?thread_id=" + emailId, {
                credentials: 'include' // Include cookies for WordPress authentication
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Check for error response
                if (data.error) {
                    console.error("API Error:", data.error, data.message || '');
                    document.getElementById('email-content-container').innerHTML = `
                        <div class="error-container">
                            <p>Error: ${data.error}</p>
                            ${data.message ? `<p>${data.message}</p>` : ''}
                        </div>
                    `;
                    return;
                }
                
                // Ensure data is an array
                if (!Array.isArray(data)) {
                    console.error("Invalid data format:", data);
                    document.getElementById('email-content-container').innerHTML = `
                        <div class="error-container">
                            <p>Invalid response format</p>
                        </div>
                    `;
                    return;
                }
                
                let html = '';
                data.forEach((thread, index) => {
                    html += `
                        <div class="email-content-container">
                            <h3>${thread.subject}</h3>
                            <p><strong>From:</strong> ${thread.sender} (${thread.sender_email})</p>
                            <p><strong>Date:</strong> ${thread.created_at}</p>
                            <br>
                            <p>${thread.message}</p>
                            <hr>

                        </div>
                    `
                })
                
                document.getElementById('email-content-container').innerHTML = html;

                
                const lastIndex = data.length - 1;
                //  Store for reply
                localStorage.setItem("lastOpenedEmail", JSON.stringify({
                    emailId: emailId,
                    sender_email: data[lastIndex].sender_email || data[lastIndex].email || '',
                    sender_id: data[lastIndex].sender_id,
                    sender_name: data[lastIndex].sender,
                    subject: data[lastIndex].subject,
                    message: data[lastIndex].message
                }));
                
                // Mark email as read in the UI
                let row = document.querySelector(`tr[data-id='${emailId}']`);
                if (row) {
                    row.classList.remove("unread");
                    row.classList.add("read");
                }
            })
            .catch(error => console.error("Error fetching email:", error));
        }
        
        // when an sent email is opened, it will fetch the email details  
        function openSentEmail(emailId) {
            console.log("email opened");
            
            // Show loading spinner while fetching the email
            document.getElementById('sent-email-content-container').innerHTML = `
                <div class="loading-container">
                    <div class="spinner"></div>
                    <p>Loading email...</p>
                </div>
            `;
            
            document.getElementById("sent-email-list").style.display = 'none';
            document.getElementById("sent-email-container").style.display = 'block';
            // Fetch email content using AJAX
            fetch("/wp-content/themes/twentytwenty/templates/tpl_internal_email_backend.php?thread_id=" + emailId, {
                credentials: 'include' // Include cookies for WordPress authentication
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Check for error response
                if (data.error) {
                    console.error("API Error:", data.error, data.message || '');
                    document.getElementById('sent-email-content-container').innerHTML = `
                        <div class="error-container">
                            <p>Error: ${data.error}</p>
                            ${data.message ? `<p>${data.message}</p>` : ''}
                        </div>
                    `;
                    return;
                }
                
                // Ensure data is an array
                if (!Array.isArray(data)) {
                    console.error("Invalid data format:", data);
                    document.getElementById('sent-email-content-container').innerHTML = `
                        <div class="error-container">
                            <p>Invalid response format</p>
                        </div>
                    `;
                    return;
                }
                
                let html = '';
                data.forEach((thread, index) => {
                    html += `
                        <div class="sent-email-content-container">
                            <h3>${thread.subject}</h3>
                            <p><strong>From:</strong> ${thread.sender} (${thread.sender_email})</p>
                            <p><strong>Date:</strong> ${thread.created_at}</p>
                            <br>
                            <p>${thread.message}</p>
                            <hr>
                        </div>
                    `
                })
                
                document.getElementById('sent-email-content-container').innerHTML = html;

                const lastIndex = data.length - 1;
                //  Store for reply
                localStorage.setItem("lastOpenedEmail", JSON.stringify({
                    emailId: emailId,
                    sender_email: data[lastIndex].sender_email || data[lastIndex].email || '',
                    sender_id: data[lastIndex].sender_id,
                    sender_name: data[lastIndex].sender,
                    subject: data[lastIndex].subject,
                    message: data[lastIndex].message
                }));
                
                // Mark email as read in the UI
                let row = document.querySelector(`tr[data-id='${emailId}']`);
                if (row) {
                    row.classList.remove("unread");
                    row.classList.add("read");
                }
            })
            .catch(error => console.error("Error fetching email:", error));
        
        }
        
        // hide email content and show email list when back button is clicked
        function backToInbox(option) {
            if (option === 'inbox'){
                document.getElementById("email-list").style.display = 'block';
                document.getElementById("email-container").style.display = 'none';
            }else if(option === 'sent'){
                document.getElementById("sent-email-list").style.display = 'block';
                document.getElementById("sent-email-container").style.display = 'none';
            }
            
            localStorage.removeItem('lastOpenedEmail');

        } 
        
        // Function to show the modal
        function showComposeModal() {
            document.getElementById("sent-container").style.display = 'none';
            document.getElementById("draft-container").style.display = 'none';
            document.getElementById("inbox-container").style.display = 'block';
            
            // remove localstorage
            localStorage.removeItem('lastOpenedEmail');
            // show the compose-email modal
            document.getElementById('compose-modal').style.display = 'block';
        }
        
        // Function to close the modal
        function closeComposeModal() {
            document.getElementById('compose-modal').style.display = 'none';
            document.getElementById("receiver").value = ""
            document.getElementById("receiver").setAttribute("data-sender-id", "");

            document.getElementById("subject").value = "";
        }
        
        // open compose modal when reply is clicked and prefill the detail.    
        function openReply() {
            const emailData = JSON.parse(localStorage.getItem("lastOpenedEmail")); // previously stored when opening email
            if (!emailData) return;
        
            // Pre-fill the compose modal
            document.getElementById("receiver").value = emailData.sender_name;
            document.getElementById("receiver").setAttribute("data-sender-id", emailData.sender_id);

            document.getElementById("subject").value = "Re: " + emailData.subject;
            // document.getElementById("message").value = `\n\n--- Original Message ---\n${emailData.message}`;
        
            // Show modal
            document.getElementById("compose-modal").style.display = "block";
    }
        
        document.getElementById("compose-form").addEventListener("submit", function(e) {
            e.preventDefault();
            submitEmail("sent");
        });
        
        document.getElementById("save-draft-btn").addEventListener("click", function(e) {
            e.preventDefault();
            submitEmail("draft");
        })
        
        function submitEmail(status) {
             
            let receiver = document.getElementById("receiver");
            const receiverId = receiver.getAttribute("data-sender-id");
            let subject = document.getElementById("subject").value;
            let message = document.getElementById("message").value;
            const draftId = document.getElementById("draft-id").value;

            
            let is_draft = 0;
            
            if (status === 'draft'){
                is_draft = 1;
            }
            
            
        
            // Convert data to URL-encoded format
            let formData = new URLSearchParams();
            formData.append("receiver", receiverId);
            formData.append("subject", subject);
            formData.append("message", message);
            formData.append("is_draft", is_draft);
            
            //William Debugging 12/12/2025: draftId is an empty string
            if (draftId) {
                formData.append("draft_id", draftId);
            }
        
            
            if (localStorage.getItem("lastOpenedEmail")){
                const openedEmail = JSON.parse(localStorage.getItem("lastOpenedEmail"));
                formData.append("parent_email_id", openedEmail.emailId);
            }
            
            document.getElementById("compose-loading").classList.remove("hidden");
            
        
            fetch(`/wp-content/themes/twentytwenty/templates/tpl_internal_email_backend.php`, {
                method: "POST",
                credentials: 'include', // Include cookies for WordPress authentication
                body: formData
            })
            .then(response =>{
                 if (response.status == '200'){
                    alert("Email Sent!");

                    document.getElementById("compose-modal").style.display = "none";
                    document.getElementById("compose-form").reset();
                    document.getElementById("compose-loading").classList.add("hidden");
                }
                else {
                    alert("Failed to send email.");
                    document.getElementById("compose-form").reset();
                    document.getElementById("compose-loading").classList.add("hidden");
                }
            })
            .then(data => {
                fetchDraftUpdates();
                fetchSentUpdates();
            })
        }
        
        
        document.addEventListener("DOMContentLoaded", function () {
            let receiverInput = document.getElementById("receiver");
            let suggestionsBox = document.getElementById("user-suggestions");
            
            function debounce(func, delay) {
                let timeout;
                return function (...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), delay);
                };
        }
        
            receiverInput.addEventListener("input", function () {
                debouncedFetchUsers(this.value.trim());
    
            });
            
            function fetchUsers(query) {
        
                if (query.length < 2) {
                    suggestionsBox.classList.add("hidden");
                    return;
                }
        
                fetch(`/wp-content/themes/twentytwenty/templates/tpl_internal_email_backend.php?query=${query}`, {
                    credentials: 'include' // Include cookies for WordPress authentication
                })
                    .then(response => response.json())
                    .then(users => {
                        suggestionsBox.innerHTML = "";
                        if (users.length === 0) {
                            suggestionsBox.classList.add("hidden");
                            return;
                        }
                        console.log(users);
                        
                        // iterate through users and create a dropdown list
                        users.forEach(user => {
                            let li = document.createElement("li");
                            li.textContent = `${user.display_name} (${user.user_email})` ;
                            li.onclick = function () {
                                receiverInput.value = user.display_name;
                                receiverInput.setAttribute("data-sender-id", user.id);
                                suggestionsBox.classList.add("hidden");
                            };
                            suggestionsBox.appendChild(li);
                        });
        
                        suggestionsBox.classList.remove("hidden");
                    })
                    .catch(error => console.error("Error fetching users:", error));
            }
            
            let debouncedFetchUsers = debounce(fetchUsers, 300);
    
            receiverInput.addEventListener("input", function () {
                debouncedFetchUsers(this.value.trim());
            });
    
        
            // Hide dropdown when clicking outside
            document.addEventListener("click", function (e) {
                if (!receiverInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                    suggestionsBox.classList.add("hidden");
                }
            });
    });
            
        
        function fetchInboxUpdates() {
            fetch(`/wp-content/themes/twentytwenty/templates/tpl_internal_email_backend.php?type=inbox`, {
                credentials: 'include' // Include cookies for WordPress authentication
            })
                .then(response => response.json())
                .then(data => {
                    // Check for error response
                    if (data.error) {
                        console.error("Error fetching inbox updates:", data.error, data.message || '');
                        return;
                    }
                    // Ensure data is an array
                    if (Array.isArray(data) && data.length > 0) {
                        updateInboxUI(data);
                    }
                })
                .catch(error => console.error("Error fetching new emails:", error));
    }
        // Update inbox table dynamically
        function updateInboxUI(emails) {
            const inboxTableBody = document.querySelector("#email-list tbody");
            
            emails.forEach(email => {
                // Check if the email is already in the inbox (avoid duplicates)
                if (!document.querySelector(`tr[data-id="${email.id}"]`)) {
                    const newRow = document.createElement("tr");
                    newRow.setAttribute("data-id", email.id);
                    newRow.className = email.is_read === 1 ? "read row2" : "unread row2";
                    newRow.onclick = () => openEmail(email.id);
                    newRow.innerHTML = `
                        <td>${email.sender_name}</td>
                        <td>${email.receiver_name}</td>
                        <td>${email.subject}</td>
                        <td>${email.formatted_date}</td>
                    `;
        
                    // Add new email at the **top** of the inbox
                    inboxTableBody.prepend(newRow);
                }
            });
        }
        
        function fetchSentUpdates() {
            console.log("updating sent emails")
            fetch(`/wp-content/themes/twentytwenty/templates/tpl_internal_email_backend.php?type=sent`, {
                credentials: 'include' // Include cookies for WordPress authentication
            })
                .then(response => response.json())
                .then(data => {
                    // Check for error response
                    if (data.error) {
                        console.error("Error fetching sent updates:", data.error, data.message || '');
                        return;
                    }
                    // Ensure data is an array
                    if (Array.isArray(data) && data.length > 0) {
                        updateSentUI(data);
                    }
                })
                .catch(error => console.error("Error fetching new emails:", error));
        }
        // Update sent table dynamically
        function updateSentUI(emails) {
            const sentTableBody = document.querySelector("#sent-email-list tbody");
            emails.forEach(email => {
                // Check if the email is already in the sent emails table (avoid duplicates)
                if (!document.querySelector(`#sent-email-list tbody tr[data-id="${email.id}"]`)) {
                    const newRow = document.createElement("tr");
                    newRow.setAttribute("data-id", email.id);
                    newRow.className = email.is_read === 1 ? "read row2" : "unread row2";
                    newRow.onclick = () => openSentEmail(email.id);
                    newRow.innerHTML = `
                        <td>${email.sender_name}</td>
                        <td>${email.receiver_name}</td>
                        <td>${email.subject}</td>
                        <td>${email.formatted_date}</td>
                    `;
        
                    // Add new email at the **top** of the inbox
                    sentTableBody.prepend(newRow);
                }
            });
        }
        
        function fetchDraftUpdates() {
            console.log("updating draft emails")
            fetch(`/wp-content/themes/twentytwenty/templates/tpl_internal_email_backend.php?type=draft`, {
                credentials: 'include' // Include cookies for WordPress authentication
            })
                .then(response => response.json())
                .then(data => {
                    // Check for error response
                    if (data.error) {
                        console.error("Error fetching draft updates:", data.error, data.message || '');
                        return;
                    }
                    // Ensure data is an array
                    if (Array.isArray(data)) {
                        updateDraftUI(data);
                    }
                })
                .catch(error => console.error("Error fetching new emails:", error));
        }
        // Update sent table dynamically
        function updateDraftUI(emails) {
            const draftTableBody = document.querySelector("#draft-email-list tbody");
             // Collect all current email IDs from the latest fetch
            const incomingIds = emails.map(email => email.id.toString());
        
            // Remove rows that are no longer present
            draftTableBody.querySelectorAll("tr").forEach(row => {
                const rowId = row.getAttribute("data-id");
                if (!incomingIds.includes(rowId)) {
                    row.remove();
                }
            });
            
            console.log(draftTableBody);
        
            emails.forEach(email => {
                // Check if the email is already in the sent emails table (avoid duplicates)
                if (!document.querySelector(`#draft-email-list tbody tr[data-id="${email.id}"]`)) {
                    const newRow = document.createElement("tr");
                    newRow.setAttribute("data-id", email.id);
                    newRow.className = email.is_read === 1 ? "read row2" : "unread row2";
                    newRow.onclick = () => editDraft(email.id);
                    newRow.innerHTML = `
                        <td>${email.receiver_name}</td>
                        <td>${email.subject}</td>
                        <td>${email.formatted_date}</td>
                    `;
        
                    // Add new email at the **top** of the inbox
                    draftTableBody.prepend(newRow);
                }
            });
        }
        
        // Auto-refresh inbox every 10 seconds
        setInterval(fetchInboxUpdates, 10000);
        
        // Function for editing draft
        function editDraft(id) {
            fetch(`/wp-content/themes/twentytwenty/templates/tpl_internal_email_backend.php?id=${id}`, {
                credentials: 'include' // Include cookies for WordPress authentication
            })
            .then(res => res.json())
            .then(data=>{
                // populate modal with data
                    document.getElementById('compose-modal').style.display = 'block';
                    // Get receiver name - API returns receiver_id, we need to handle it
                    const receiverId = data.receiver_id;
                    if (receiverId) {
                        // For now, we'll need to search for the user or use receiver_id
                        // The API should return receiver name, but if not, we'll use receiver_id
                        document.getElementById("receiver").value = data.receiver || '';
                        document.getElementById("receiver").setAttribute("data-sender-id", receiverId);
                    } else {
                        document.getElementById("receiver").value = '';
                        document.getElementById("receiver").setAttribute("data-sender-id", "");
                    }
                    document.getElementById("subject").value = data.subject || '';
                    document.getElementById("message").value = data.message || '';
                    document.getElementById("draft-id").value = id;

            })
        }
    
        //event listenr for clicking 'sent' button
        document.getElementById('sent-button').addEventListener('click', function () {
            //show sent container, hide other containers
            document.getElementById('sent-container').style.display = 'block';
            document.getElementById('inbox-container').style.display = 'none';
            document.getElementById('draft-container').style.display = 'none';
            // fetchSentUpdates();

        });
        
        document.getElementById('inbox-button').addEventListener('click', function () {
            // Show inbox, hide other containers
            document.getElementById('inbox-container').style.display = 'block';
            document.getElementById('sent-container').style.display = 'none';
            document.getElementById('draft-container').style.display = 'none';
        });
        
        document.getElementById('draft-button').addEventListener('click', function () {
            // remove last opened email first
            localStorage.removeItem('lastOpenedEmail');
            // Show draft, hide other containers
            document.getElementById('draft-container').style.display = 'block';
            document.getElementById('inbox-container').style.display = 'none';
            document.getElementById('sent-container').style.display = 'none';
            // fetchDraftUpdates();

        });
    
    </script>
    
    <style>
    .email-button {
        border-radius: 15px;
        background-color: #ffbb00
    }
    
    .inbox-container {
        max-width: 800px;
        margin: auto;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
        box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    .unread {
        font-weight: bold;
        background: #fff3cd;
    }
    .read {
        background: #f8f9fa;
    }
    .row2:hover {
        cursor: pointer;
    }
    .email-list {
        display: none;
    }
    .email-container {
        display: none;
    }
    
    /* Loading Spinner Styles */
    .loading-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    
    .spinner {
        width: 40px;
        height: 40px;
        border: 5px solid #f3f3f3;
        border-top: 5px solid #007bff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Modal Background */
    .modal {
        display: none;
        position: fixed;
        z-index: 1;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.4);
    }
    
    /* Modal Content */
    .modal-content {
        background-color: white;
        padding: 20px;
        width: 400px;
        margin: 10% auto;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }
    
    /* Close Button */
    .close {
        float: right;
        font-size: 28px;
        cursor: pointer;
    }
    
    /* Form Styling */
    input, textarea {
        width: 100%;
        padding: 10px;
        margin: 5px 0;
    }
    
    button {
        padding: 10px;
        border: none;
        cursor: pointer;
        margin: 20px;
    }
    
    /* Loading Indicator */
    .hidden {
        display: none;
    }
    
    .suggestions-list {
        position: absolute;
        background: white;
        border: 1px solid #ddd;
        list-style: none;
        padding: 5px;
        margin-top: 5px;
        width: 300px;
        max-height: 150px;
        overflow-y: auto;
        box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.2);
        z-index: 1000;
    }

    .suggestions-list.hidden {
        display: none;
    }
    
    .suggestions-list li {
        padding: 8px;
        cursor: pointer;
    }
    
    .suggestions-list li:hover {
        background-color: #f0f0f0;
    }
    
    /* Error Container Styles */
    .error-container {
        padding: 20px;
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        border-radius: 4px;
        color: #721c24;
        margin: 20px 0;
    }
    
    .error-container p {
        margin: 5px 0;
    }
    </style>
</div>
<?php
}
?>
<?php get_footer(); ?>