<?php
// webhook.php - Twilio WhatsApp webhook (uses RapidAPI GPT-5 chat for replies)
// Fully safe TwiML version with logging

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/xml; charset=utf-8');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_log("Webhook loaded successfully");

// Step 1: Log raw incoming Twilio POST
log_step('Webhook received', $_POST);

// --- Extract WhatsApp message details ---
$from = $_POST['From'] ?? 'test';
$body = trim($_POST['Body'] ?? 'test');
$numMedia = intval($_POST['NumMedia'] ?? 0);
$mediaUrl = $numMedia > 0 ? ($_POST['MediaUrl0'] ?? null) : null;

log_webhook_message($from, $body, $numMedia, $mediaUrl);



log_step('Parsed incoming data', [
    'from' => $from,
    'body' => $body,
    'numMedia' => $numMedia,
    'mediaUrl' => $mediaUrl
]);

if (!$from) {
    log_step('Error: Missing From number');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo "<Response><Message>Missing From number.</Message></Response>";
    exit;
}

// Step 2: Ensure uploads folder exists
if (!file_exists(IMAGE_SAVE_PATH)) {
    mkdir(IMAGE_SAVE_PATH, 0777, true);
    log_step('Created image upload directory', IMAGE_SAVE_PATH);
}

// Step 3: Load or create conversation state & order
$state = get_state($from);
if (!$state) {
    $order_id = create_order($from);
    $context = [
        "phone" => $from,
        "order_id" => $order_id,
        "fields" => (object)[
            "clothing_type" => "",
            "design_image_url" => "",
            "size" => "",
            "color" => "",
            "address" => ""
        ]
    ];
    set_state($from, $order_id, json_encode($context), '', '');
    $user_input = "start conversation";
    log_step('New order created', ['order_id' => $order_id, 'phone' => $from]);
} else {
    $order_id = $state['order_id'];
    $context = json_decode($state['context_json'] ?? '{}', true) ?? ["phone"=>$from,"order_id"=>$order_id,"fields"=>[]];
    $user_input = $body;
    log_step('Loaded existing order', ['order_id' => $order_id, 'context' => $context]);
}

// Step 4: Handle uploaded image
if ($mediaUrl) {
    $context['fields']['design_image_url'] = $mediaUrl;
    update_order($order_id, ['design_image_url' => $mediaUrl]);
    $user_input .= "\n[media uploaded: $mediaUrl]";
    log_step('Media uploaded', ['mediaUrl' => $mediaUrl]);
}

// Step 5: Prepare system prompt and messages
$system_prompt = <<<'SYS'
You are CustomClothBot — a WhatsApp assistant that collects custom clothing orders.
RESPOND with valid JSON only (no extra text) using these keys:
- reply: string (message to user)
- action: string (one of: "ask", "wait_image", "generate_preview", "confirm", "done", "error")
- fields: object with keys clothing_type, design_image_url, size, color, address
- image_prompt: string|null (if action == "generate_preview")
Rules:
1) Use the 'fields' object to send any fields you've learned.
2) If address is incomplete, ask the user for full address in format: House number, Building, Area, City, State, Pincode, Country.
3) If user uploaded an image, include its URL in fields.design_image_url.
4) When all fields are filled and user confirmed, set action="done" and reply "We will update you with a price within 2 to 3 hours."
5) Keep 'reply' conversational and concise. Do not include JSON inside reply.
SYS;

$messages = [
    ["role"=>"system", "content"=>$system_prompt],
    ["role"=>"system", "content"=>"Current known fields: " . json_encode($context['fields'] ?? (object)[])],
    ["role"=>"user", "content"=>$user_input]
];

log_step('Prepared AI request', $messages);

// Step 6: Call RapidAPI AI
$ai_text = call_rapidapi_chat($messages);
log_step('AI raw response', $ai_text);

if ($ai_text === null) {
    log_step('AI service unavailable');
    $fallback = "Sorry — AI service unavailable right now.";
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo "<Response><Message>" . htmlspecialchars($fallback, ENT_XML1, 'UTF-8') . "</Message></Response>";
    exit;
}

// Step 7: Parse AI JSON
$parsed = json_decode($ai_text, true);
if (!$parsed) {
    set_state($from, $order_id, json_encode($context), $user_input, $ai_text);
    log_step('Error: Invalid AI JSON response', $ai_text);
    $err = "Sorry, I couldn't understand the AI reply. Please try again.";
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo "<Response><Message>" . htmlspecialchars($err, ENT_XML1, 'UTF-8') . "</Message></Response>";
    exit;
}

log_step('Parsed AI response', $parsed);

// Step 8: Save updated fields
$fields = $parsed['fields'] ?? [];
if (!empty($fields)) {
    $db_update = [];
    foreach (['clothing_type','size','color','address','design_image_url'] as $k) {
        if (isset($fields[$k]) && $fields[$k] !== '') $db_update[$k] = $fields[$k];
    }
    if (!empty($db_update)) {
        update_order($order_id, $db_update);
        log_step('Updated order fields in DB', $db_update);
    }
    $context['fields'] = $fields;
}

// Step 9: Save conversation state
set_state($from, $order_id, json_encode($context), $user_input, $ai_text);
log_step('State saved', ['order_id' => $order_id, 'context' => $context]);

// Step 10: Handle AI action
$action = $parsed['action'] ?? 'ask';
$reply = trim($parsed['reply'] ?? "Sorry, I couldn't understand that.");
log_step('Action to perform', ['action' => $action, 'reply' => $reply]);

echo '<?xml version="1.0" encoding="UTF-8"?>';

if ($action === 'generate_preview') {
    $image_prompt = $parsed['image_prompt'] ?? null;
    $preview_url = null;

    if ($image_prompt && defined('USE_OPENAI_IMAGE') && USE_OPENAI_IMAGE && defined('OPENAI_API_KEY') && OPENAI_API_KEY) {
        $b64orurl = call_openai_image($image_prompt);
        if ($b64orurl) {
            $preview_url = save_base64_image($b64orurl, 'preview');
        }
    }

    if (!$preview_url) {
        $txt = urlencode(($fields['size'] ?? 'M') . ' ' . ($fields['color'] ?? 'black') . ' ' . ($fields['clothing_type'] ?? 'dress'));
        $preview_url = "https://via.placeholder.com/512x768.png?text={$txt}";
    }

    update_order($order_id, ['preview_image_url' => $preview_url]);
    log_step('Generated preview', ['url' => $preview_url]);

    echo "<Response><Message><Body>" . htmlspecialchars($reply, ENT_XML1, 'UTF-8') . "</Body><Media>" . htmlspecialchars($preview_url, ENT_XML1, 'UTF-8') . "</Media></Message></Response>";
    log_step('Sent preview message to Twilio', ['reply' => $reply, 'preview_url' => $preview_url]);
    exit;
}

if (in_array($action, ['confirm', 'ask', 'wait_image', 'done'])) {
    if ($action === 'done') {
        update_order($order_id, ['status' => 'pending_price']);
        clear_state($from);
        log_step('Order completed', ['order_id' => $order_id]);
    }
    echo "<Response><Message>" . htmlspecialchars($reply, ENT_XML1, 'UTF-8') . "</Message></Response>";
    log_step('Sent reply to Twilio', ['reply' => $reply]);
    exit;
}

// Fallback
log_step('Fallback executed', ['reply' => $reply]);
echo "<Response><Message>" . htmlspecialchars($reply, ENT_XML1, 'UTF-8') . "</Message></Response>";
exit;
?>
