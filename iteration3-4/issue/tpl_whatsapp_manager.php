<?php
/**
 * Template Name: Manage Whatsapp Chat
 * Template Post Type: post, page
 *
 * @package WordPress
 * @subpackage Twenty_Twenty
 * @since Twenty Twenty 1.0
 * @author Sriharshan
 */
get_header(); 
global $current_user;
if(isset($current_user) && $current_user->user_login != '')
{
    $login_user_name = $current_user->user_login;
}
else
{
    $login_user_name = 'agent_1';
}
require_once 'wp-config-custom.php';

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

$contactStmt = $pdo->query("
  SELECT number FROM (
    SELECT sender_id AS number, MAX(id) AS last_id FROM whatsapp_messages WHERE sender_type = 'customer' and sender_id != '$WHATSAPP_API_PHONE_NUMBER' GROUP BY sender_id
    UNION
    SELECT recipient_id AS number, MAX(id) AS last_id FROM whatsapp_messages WHERE sender_type = 'customer' and recipient_id != '$WHATSAPP_API_PHONE_NUMBER' GROUP BY recipient_id
  ) AS contacts
  ORDER BY last_id DESC
");
$existingContacts = $contactStmt->fetchAll(PDO::FETCH_COLUMN);

?>
<style>
.chat-bubble {
  max-width: 70%;
  padding: 10px 15px;
  border-radius: 15px;
  font-size: 14px;
  line-height: 1.4;
  word-wrap: break-word;
}

.from-customer {
  align-self: flex-start;
  background-color: #ffffff;
  border-bottom-left-radius: 0;
  color: #000;
}

.from-business {
  align-self: flex-end;
  background-color: #dcf8c6;
  border-bottom-right-radius: 0;
  color: #000;
}

#templateButtons {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}

.template-btn {
  background-color: #eee;
  border: 1px solid #ccc;
  padding: 5px 10px;
  border-radius: 10px;
  font-size: 13px;
  cursor: pointer;
}

.template-btn:hover {
  background-color: #ddd;
}

</style>
</br></br>
<div id="whatsapp-wrapper" style="display: flex; height: 80vh; width: 95%; margin: auto; border: 1px solid #ccc; font-family: sans-serif;">

    <div id="sidebar" style="width: 100%; border-right: 1px solid #ccc; background: #f0f0f0; overflow-y: auto;">
        <div style="padding: 15px; margin: 0; background: #075E54; color: white; display: flex; justify-content: space-between; align-items: center;">
            <span>Contacts</span>
            <span id="addContactBtn" title="Add Number" style="background: #25d366; color: white; font-size: 18px; font-weight: bold; border-radius: 50%; cursor: pointer; width: 26px; height: 26px; padding: 4px 8px; float: right; min-width: 20px; text-align: center; line-height: 1;">+</span>
        </div>
        <table id="contactList" style="width: 100%; border-collapse: collapse;">
            <thead style="background: #ddd;">
                <tr>
                    <th style="padding: 8px; text-align: left;">&nbsp;</th>
                    <th style="padding: 8px; text-align: left;">Phone</th>
                    <th style="padding: 8px; text-align: left;">Order ID</th>
                    <th style="padding: 8px; text-align: left;">Pax Information</th>
                    <th style="padding: 8px; text-align: left;">Trip code</th>
                    <th style="padding: 8px; text-align: left;">Travel Date</th>
                    <th style="padding: 8px; text-align: left;">Payment</th>
                    <th style="padding: 8px; text-align: left;">PNR</th>
                    <th style="padding: 8px; text-align: left;">PCC</th>
                    <th style="padding: 8px; text-align: left;">Ticket</th>
                    <th style="padding: 8px; text-align: left;">Last Message</th>
                    <th style="padding: 8px; text-align: left;">Time</th>
                    <th style="padding: 8px;">Action</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <!-- Chat Area -->
    <div id="chatPopupContainer" style="display: none;"></div>
    <input type="hidden" id="agent" value="<?php echo $login_user_name; ?>">
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.0/jquery-ui.min.js"></script>
<script>
$(document).on('click', '.expand-btn', function() {
  var button = $(this);
  var autoId = button.data('target').replace('expand_', '');
  var targetRow = $('#expand_' + autoId);
  var contentDiv = $('#expand_content_' + autoId);

  if (targetRow.is(':visible')) {
    targetRow.hide();
    button.text('+');
  } else {
    targetRow.show();
    button.text('-');

    contentDiv.html('<center><h4><em>Loading...</em></h4></center>');

    $.ajax({
      url: '/wp-content/themes/twentytwenty/templates/tpl_manage_ticketing_g360.php',
      type: 'POST',
      data: { auto_id: autoId },
      success: function(response) {
        contentDiv.html(response);
      },
      error: function(xhr, status, error) {
        contentDiv.html('<span style="color:red;">Error loading data</span>');
        console.error('AJAX Error:', status, error);
      }
    });
  }
});

// Add New Contact + Sign
document.getElementById('addContactBtn').addEventListener('click', function () {
  const phone = prompt('Enter WhatsApp number in international format (e.g., 61412345678):');
  if (!phone || !/^\d{9,15}$/.test(phone)) {
    alert('Invalid phone number.');
    return;
  }

  fetch('/wp-content/themes/twentytwenty/templates/tpl_whatsapp_chat_event_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: 'add_contact=1&phone=' + encodeURIComponent(phone)
  })
  .then(res => res.json())
  .then(contact => {
      
      
      console.log(contact);
    if (!contact || !contact.contact) {
      alert('Error adding contact or contact already exists.');
      return;
    }

    const number = contact.contact;
    const fname = contact.fname || '';
    const lname = contact.lname || '';
    const preview = contact.last_message || '';
    const time = contact.last_time || '';
    const unread = contact.unread_count || 0;
    const orderId = '';

    const tbody = document.querySelector('#contactList tbody');
    
    const exists = tbody.querySelector(
        `tr[data-number="${number}"][data-orderid="${orderId}"]`
      );
      if (exists) {
        console.log(`Row for ${number} with orderId ${orderId} already exists`);
        return;
      }
      
    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid #ccc';

    const tdPhone = `<td style="padding: 8px;">+${number}${unread > 0 ? `<span style="background: green; color: white; font-size: 11px; font-weight: bold; border-radius: 50%; padding: 3px 7px; margin-left: 6px;">${unread}</span>` : ''}</td>`;
    const tdDummy = `<td style="padding: 8px;"></td>`;
    const tdMsg = `<td style="padding: 8px;">${preview}</td>`;
    const tdTime = `<td style="padding: 8px;">${time}</td>`;
    const tdBtn = `<td style="padding: 8px;"><button onclick="openChatPopup('${number}')" style="padding: 4px 10px; border: none; background: #25D366; color: white; border-radius: 5px; cursor: pointer;">Open</button></td>`;

    tr.innerHTML = tdDummy + tdPhone + tdDummy + tdDummy + tdDummy + tdDummy + tdDummy + tdDummy + tdDummy + tdDummy + tdMsg + tdTime + tdBtn;
    tbody.prepend(tr);

    openChatPopup(number, orderId);
  })
  .catch(err => {
    console.error('Add contact failed:', err);
    alert('Failed to add contact.');
  });
});

// Include Template Messages
document.querySelectorAll('.template-btn').forEach(button => {
  button.addEventListener('click', function () {
    const templateText = this.getAttribute('data-template');
    document.getElementById('message').value = templateText;
    document.getElementById('message').focus();
  });
});

function markAsReadByAgent(customerNumber) {
  fetch('/wp-content/themes/twentytwenty/templates/tpl_whatsapp_chat_event_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: 'mark_agent_read=1&customer=' + encodeURIComponent(customerNumber)
  })
  .then(res => res.json())
  .then(data => {
    console.log('Marked read by agent:', data);
  })
  .catch(console.error);
}


let selectedCustomer = null;
let selectedOrderId = null;

// Load New messages
function loadChat() {
  if (!selectedCustomer) return;

  fetch('/wp-content/themes/twentytwenty/templates/tpl_whatsapp_chat_event_handler.php?customer=' + selectedCustomer)
    .then(res => res.json())
    .then(data => {
      const box = document.getElementById('chatBox');
      box.innerHTML = '';
      
      const last = data.length ? data[data.length - 1] : null;
      const currentUser = document.getElementById('agent').value;
    
      if (last && last.in_progress && last.in_progress !== currentUser) {
        document.getElementById('chatForm').style.display = 'none';
        document.getElementById('chatForm').insertAdjacentHTML('afterend',
          `<div id="takeoverMessage" style="padding: 10px; background: #ffe0e0; color: #b00020; text-align: center;">
            Taken over by ${last.in_progress}
          </div>`);
      } else {
        document.getElementById('chatForm').style.display = 'flex';
        const takeoverMsg = document.getElementById('takeoverMessage');
        if (takeoverMsg) takeoverMsg.remove();
      }

      let lastDate = null;
      const today = new Date().toDateString();
      const yesterday = new Date(Date.now() - 86400000).toDateString();

      data.forEach(msg => {
        const msgDate = new Date(msg.created_at);
        const dateStr = msgDate.toDateString();

        let groupLabel = '';
        if (dateStr === today) {
          groupLabel = 'Today';
        } else if (dateStr === yesterday) {
          groupLabel = 'Yesterday';
        } else {
          groupLabel = msgDate.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
          });
        }

        if (groupLabel !== lastDate) {
          const groupEl = document.createElement('div');
          groupEl.textContent = groupLabel;
          groupEl.style.cssText = 'text-align: center; font-weight: bold; color: #666; margin: 10px 0;';
          box.appendChild(groupEl);
          lastDate = groupLabel;
        }

        const whoClass = msg.sender_type === 'business' ? 'from-business' : 'from-customer';
        const align = msg.sender_type === 'business' ? 'right' : 'left';
        const content = msg.message.startsWith('[Media]') ? `<i>${msg.message}</i>` : msg.message;

        const time = msgDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        const bubble = document.createElement('div');
        bubble.className = `chat-bubble ${whoClass}`;
        bubble.style.alignSelf = align;
        
        const isBusiness = msg.sender_type === 'business';
        
        bubble.innerHTML = `
          <div>${content}</div>
          <div style="text-align: ${align}; font-size: 11px; color: #555; margin-top: 5px;">
            ${time}
            ${(() => {
              const isBusiness = msg.sender_type === 'business';
              if (!isBusiness) return ''; // No ticks for customer-sent messages
        
              const status = msg.status || '';
              const read = msg.msg_read_customer == 1;
              const delivered = status === 'delivered' || read;
              const sent = status === 'sent' || delivered;
        
              if (read) return `<span style="font-size: 12px; margin-left: 6px; color: blue;">✓✓</span>`;
              if (delivered) return `<span style="font-size: 12px; margin-left: 6px;">✓✓</span>`;
              if (sent) return `<span style="font-size: 12px; margin-left: 6px;">✓</span>`;
              return ''; // fallback if needed
            })()}
          </div>
        `;



        
        /*bubble.innerHTML = `
          <div>${content}</div>
          <div style="text-align: ${align}; font-size: 11px; color: #555; margin-top: 5px;">${time}</div>
        `;*/

        box.appendChild(bubble);
      });

      box.scrollTop = box.scrollHeight;
      markAsReadByAgent(selectedCustomer);
    });
}




// Chat form submission including attachment


let lastTimestamp = null;

function watchForUpdates() {
  if (!selectedCustomer) {
    setTimeout(watchForUpdates, 3000);
    return;
  }

  fetch(`/wp-content/themes/twentytwenty/templates/tpl_whatsapp_chat_event_handler.php?check_update=1&customer=${selectedCustomer}`)
    .then(res => res.json())
    .then(data => {
      if (!data) return;

      if (data.last_updated && data.last_updated !== lastTimestamp) {
        lastTimestamp = data.last_updated;
        loadChat();
      }
    })
    .catch(console.error)
    .finally(() => {
      setTimeout(watchForUpdates, 3000);
    });
}

let lastContactUpdate = null;

function loadContacts() {
  console.log('Fetching contact list...');
  fetch('/wp-content/themes/twentytwenty/templates/tpl_whatsapp_chat_event_handler.php?get_contacts=1')
    .then(res => res.json())
    .then(data => {
      const tbody = document.querySelector('#contactList tbody');
        tbody.innerHTML = '';

      if (!Array.isArray(data) || data.length === 0) {
        list.innerHTML = '<li style="padding: 15px;">No conversations yet.</li>';
        return;
      }

      // Save latest timestamp for polling
      lastContactUpdate = data[0]?.last_activity || null;

      data.forEach(contact => {
          const number = contact.contact;
          const unread = contact.unread_count || 0;
          const preview = contact.last_message || '';
          const time = contact.last_time || '';
          const fname = contact.fname || '';
          const lname = contact.lname || '';
          const fullname = fname + " " + lname;
          
          const orderid = contact.order_id || '';
          const trip_code = contact.trip_code || '';
          const traveldate = contact.travel_date || '';
          const payment_status = contact.payment_status || '';
          const pnr = contact.pnr || '';
          const pcc = contact.pcc || '';
          const paxAutoId = contact.auto_id || '';
          const ticket_number = contact.ticket_number || '';
          
          const order_type = contact.order_type || '';
          const source = contact.source || '';
            
            let source_pcc = '';

            if (order_type === 'WPT') {
              const airlineCode = trip_code.substring(8, 10);
              if (airlineCode === 'SQ') {
                source_pcc = 'Gdeals CCUVS32NQ';
              } else if (airlineCode === 'QF') {
                source_pcc = 'Gdeals MELA821CV';
              }
            
              if (source === 'wpwebsite') {
                source_pcc = 'Gdeals CCUVS32NQ';
              }
            }
            
            if (order_type === 'gds') {
              switch (source) {
                case 'gaurain':
                  source_pcc = 'Sabre 1BIK';
                  break;
                case 'gauraaws':
                  source_pcc = 'Amadeus MELA821CV';
                  break;
                case 'gaura':
                  source_pcc = 'Sabre I5FC';
                  break;
                case 'gaurandcx':
                  source_pcc = 'Amadeus MELA828FN';
                  break;
                case 'gaurainn':
                  source_pcc = 'Amadeus CCUVS32NQ';
                  break;
                case 'shelltechb2bina':
                  source_pcc = 'Amadeus CCUVS32NQ';
                  break;
                case 'shelltechb2bndcxau':
                  source_pcc = 'Amadeus MELA828FN';
                  break;
                case 'shelltechb2b':
                  source_pcc = 'Sabre I5FC';
                  break;
                case 'shelltechb2baws':
                  source_pcc = 'Amadeus MELA828FN';
                  break;
                case 'shelltechb2bin':
                  source_pcc = 'SABRE 1BIK';
                  break;
                case 'shelltechb2bndcxin':
                  source_pcc = 'Amadeus CCUVS32NQ';
                  break;
              }
            
              if (pnr && pnr.startsWith('SQ_')) {
                source_pcc = 'SQ NDC I5FC';
              }
            }

            const exists = tbody.querySelector(
            `tr[data-number="${number}"][data-orderid="${orderid}"]`
          );
          if (exists) {
            return;
          }
        
          const tr = document.createElement('tr');
          tr.style.borderBottom = '1px solid #ccc';
          tr.dataset.number = number;
  tr.dataset.orderid = orderid;
          
          
        
          const tdPhone = `<td style="padding: 8px;">+${number}${unread > 0 ? `<span style="background: green; color: white; font-size: 11px; font-weight: bold; border-radius: 50%; padding: 3px 7px; margin-left: 6px;">${unread}</span>` : ''}</td>`;
          const tdPaxID = `<td style="padding: 8px;"><button class="expand-btn" data-target="expand_${paxAutoId}" style="padding:10px; width:40px; height:40px; margin:0;font-size:16px;">+</button></td>`;
          const tdOrder = `<td style="padding: 8px;">${orderid}</td>`;
          const tdName = `<td style="padding: 8px;">${fullname}</td>`;
          const tdTripcode = `<td style="padding: 8px;">${trip_code}</td>`;
          const tdTravelDate = `<td style="padding: 8px;">${traveldate}</td>`;
          const tdPaymentStatus = `<td style="padding: 8px;">${payment_status}</td>`;
          const tdPNR = `<td style="padding: 8px;">${pnr}</td>`;
          const tdpcc = `<td style="padding: 8px;">${source_pcc}</td>`;
          const tdTicket = `<td style="padding: 8px;">${ticket_number}</td>`;
          const tdMsg = `<td style="padding: 8px;">${preview}</td>`;
          const tdTime = `<td style="padding: 8px;">${time}</td>`;
          const tdBtn = `<td style="padding: 8px;"><button onclick="openChatPopup('${number}', '${orderid}')" style="padding: 4px 10px; border: none; background: #25D366; color: white; border-radius: 5px; cursor: pointer;">Open</button></td>`;
            
            const tdExpand = `<tr id="expand_${paxAutoId}" class="expand-row" style="display:none;">
            <td>
                <div id="expand_content_${paxAutoId}">
                    <center><h4><em>Loading...</em></h4></center>
            </div></td></tr>`;
            
          tr.innerHTML = tdPaxID + tdPhone + tdOrder + tdName + tdTripcode + tdTravelDate + tdPaymentStatus + tdPNR + tdpcc + tdTicket + tdMsg + tdTime + tdBtn;
          tbody.appendChild(tr);
          
          const trExpand = document.createElement('tr');
            trExpand.id = 'expand_' + paxAutoId;
            trExpand.className = 'expand-row';
            trExpand.style.display = 'none';
            
            const tdExpandCell = document.createElement('td');
            tdExpandCell.colSpan = 13;
            tdExpandCell.innerHTML = `<div id="expand_content_${paxAutoId}"><center><h4><em>Loading...</em></h4></center></div>`;
            
            trExpand.appendChild(tdExpandCell);
            tbody.appendChild(trExpand);
        });
    })
    .catch(err => {
      console.error('Failed to load contact list:', err);
    });
}

// Auto-refresh contact list if updated
function watchContactList() {
  fetch('/wp-content/themes/twentytwenty/templates/tpl_whatsapp_chat_event_handler.php?check_contact_update=1')
    .then(res => res.json())
    .then(data => {
      if (data.latest_update && data.latest_update !== lastContactUpdate) {
        console.log('Contact list updated, reloading...');
        loadContacts();
      }
    })
    .finally(() => {
      setTimeout(watchContactList, 5000); // Poll every 5s
    });
}

document.addEventListener('DOMContentLoaded', function () {
  loadContacts(); 
  watchContactList(); // Start polling for contact list changes
});

document.getElementById('in_progress').addEventListener('change', function () {
  const isChecked = this.checked;
  const user = document.getElementById('agent').value;

  fetch('/wp-content/themes/twentytwenty/templates/tpl_whatsapp_chat_event_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: `toggle_in_progress=1&customer=${encodeURIComponent(selectedCustomer)}&user=${encodeURIComponent(user)}&checked=${isChecked ? '1' : '0'}`
  })
  .then(res => res.json())
  .then(data => {
    console.log('In-progress updated:', data);
    loadChat(); // Refresh UI
  });
});

function openChatPopup(number, orderId) {
  selectedCustomer = number;
  selectedOrderId = orderId;
  
  const popupContainer = document.getElementById('chatPopupContainer');
  popupContainer.innerHTML = `
    <div id="chatArea" style="position: fixed; top: 10vh; left: 50%; transform: translateX(-50%); width: 80%; height: 80vh; z-index: 9999; box-shadow: 0 0 20px rgba(0,0,0,0.3); background: #e5ddd5; display: flex; flex-direction: column;">
      <div id="chatHeader" style="padding: 10px 15px; background: #075E54; color: white; font-weight: bold; display: flex; justify-content: space-between; align-items: center;">
        <span>+${number} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 
        <input type="checkbox" id="in_progress" name="in_progress" value="${document.getElementById('agent').value}">
        <label for="in_progress">In Progress</label></span>
        <span onclick="closeChatPopup()" style="cursor: pointer; font-size: 18px; font-weight: bold;">✖</span>
      </div>
      <div></div>
      <div id="chatBox" style="flex: 1; padding: 15px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px;"></div>

      <form id="chatForm" enctype="multipart/form-data" style="display: flex; flex-direction: column; padding: 10px; background: #f7f7f7; border-top: 1px solid #ccc;">
        <div style="display: flex; margin-bottom: 10px;">
          <textarea id="message" placeholder="Type a message..." style="flex: 1; padding: 10px; border-radius: 20px; border: 1px solid #ccc; resize: none; height: 140px;"></textarea>
          <input type="file" id="attachment" style="margin-left: 10px;">
        </div>

        <button type="submit" style="align-self: flex-end; background: #25D366; border: none; color: white; padding: 10px 20px; border-radius: 20px; font-weight: bold;">Send</button>

        
      </form>
    </div>
  `;

  popupContainer.style.display = 'block';
  loadChat();
  watchForUpdates();

  // Re-bind template buttons inside the popup
  document.querySelectorAll('.template-btn').forEach(button => {
    button.addEventListener('click', function () {
      document.getElementById('message').value = this.dataset.template;
    });
  });

  // Bind chat form handler again
  document.getElementById('chatForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!selectedCustomer) return;

    const message = document.getElementById('message').value;
    const fileInput = document.getElementById('attachment');
    const agent = document.getElementById('agent') ? document.getElementById('agent').value : '';

    const formData = new FormData();
    formData.append('send_message', true);
    formData.append('recipient', selectedCustomer);
    formData.append('agent', agent);
    if (fileInput.files.length > 0) {
      formData.append('file', fileInput.files[0]);
    } else {
      //formData.append('message', message);
      //formData.append('message', `Gaura OrderID: ${selectedOrderId}\n{\n${message}\n}`);
        
        let finalMessage = message;

        if (selectedOrderId && selectedOrderId.trim() !== "") {
          finalMessage = `Gaura OrderID: ${selectedOrderId}\n{\n${message}\n}`;
        }
        
        formData.append("message", finalMessage);


    }

    fetch('/wp-content/themes/twentytwenty/templates/tpl_whatsapp_chat_event_handler.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (!data.error) {
        document.getElementById('message').value = '';
        document.getElementById('attachment').value = '';
        loadChat();
      } else {
        console.error(data.error);
      }
    })
    .catch(console.error);
  });

  // Rebind In Progress toggle
  document.getElementById('in_progress').addEventListener('change', function () {
    const isChecked = this.checked;
    const user = document.getElementById('agent') ? document.getElementById('agent').value : '';

    fetch('/wp-content/themes/twentytwenty/templates/tpl_whatsapp_chat_event_handler.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: `toggle_in_progress=1&customer=${encodeURIComponent(selectedCustomer)}&user=${encodeURIComponent(user)}&checked=${isChecked ? '1' : '0'}`
    })
    .then(res => res.json())
    .then(() => loadChat());
  });
}
function closeChatPopup() {
  const popup = document.getElementById('chatPopupContainer');
  popup.innerHTML = '';
  popup.style.display = 'none';
  selectedCustomer = null;
  selectedOrderId = null;
}




</script>
<?php get_footer(); ?>