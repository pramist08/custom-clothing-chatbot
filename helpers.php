<?php
// helpers.php - helper functions for DB, RapidAPI, logging, and optional image generation
require_once __DIR__ . '/config.php';

// ---------- Logging Setup ----------
define('LOG_FILE', __DIR__ . '/errorlog.txt');

/**
 * Write a log entry with a timestamp and optional data.
 * Example: log_step("Step 1: Received webhook", $_POST);
 */
function log_step($step, $data = null) {
    $timestamp = date("Y-m-d H:i:s");
    $entry = "\n[$timestamp] $step\n";

    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $entry .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            $entry .= $data . "\n";
        }
    }

    $entry .= str_repeat('-', 100) . "\n";
    file_put_contents(LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
}

// ---------- RapidAPI Chat ----------
function call_rapidapi_chat($messages, $model="GPT-5-mini") {
    log_step("Step 1: Preparing RapidAPI call", $messages);

    $url = "https://" . RAPIDAPI_HOST . "/";
    $payload = [
        'model' => $model,
        'messages' => $messages
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "x-rapidapi-host: " . RAPIDAPI_HOST,
        "x-rapidapi-key: " . RAPIDAPI_KEY
    ]);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        log_step("RapidAPI Error", $err);
        return null;
    }

    log_step("Step 2: RapidAPI Raw Response", $resp);

    $data = json_decode($resp, true);
    if (!$data) {
        log_step("Step 3: RapidAPI JSON Parse Failed", substr($resp, 0, 500));
        return null;
    }

    if (!empty($data['choices'][0]['message']['content'])) {
        log_step("Step 4: RapidAPI Parsed Response", $data['choices'][0]['message']['content']);
        return $data['choices'][0]['message']['content'];
    }

    if (!empty($data['message']['content'])) {
        log_step("Step 4: RapidAPI Parsed Response (Alt Format)", $data['message']['content']);
        return $data['message']['content'];
    }

    log_step("Step 4: RapidAPI Unknown Response Structure", $data);
    return $resp;
}

// ---------- OpenAI Image Generation ----------
function call_openai_image($prompt, $size="512x768") {
    if (!USE_OPENAI_IMAGE || !OPENAI_API_KEY) return null;
    log_step("Generating Image", ["prompt" => $prompt]);

    $url = "https://api.openai.com/v1/images/generations";
    $payload = [
        "prompt" => $prompt,
        "n" => 1,
        "size" => $size
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . OPENAI_API_KEY
    ]);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        log_step("OpenAI Image Error", $err);
        return null;
    }

    $data = json_decode($resp, true);
    if (!$data) {
        log_step("OpenAI Image JSON Parse Fail", $resp);
        return null;
    }

    log_step("OpenAI Image API Response", $data);
    if (!empty($data['data'][0]['b64_json'])) return $data['data'][0]['b64_json'];
    if (!empty($data['data'][0]['url'])) return $data['data'][0]['url'];
    return null;
}

// ---------- Save Base64 Image ----------
function save_base64_image($b64, $prefix='preview') {
    if (strpos($b64, 'http') === 0) return $b64;
    if (!is_dir(IMAGE_SAVE_PATH)) @mkdir(IMAGE_SAVE_PATH, 0755, true);

    $data = base64_decode($b64);
    $fname = IMAGE_SAVE_PATH . '/' . $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.png';
    file_put_contents($fname, $data);

    $url = defined('BASE_URL') && BASE_URL
        ? rtrim(BASE_URL, '/') . '/public/uploads/' . basename($fname)
        : $fname;

    log_step("Image Saved", ["file" => $fname, "url" => $url]);
    return $url;
}

// ---------- Database Helpers ----------
function create_order($phone) {
    $db = get_db();
    $stmt = $db->prepare("INSERT INTO orders (phone) VALUES (?)");
    $stmt->execute([$phone]);
    $id = $db->lastInsertId();
    log_step("Order Created", ["phone" => $phone, "order_id" => $id]);
    return $id;
}

function update_order($order_id, $fields) {
    $db = get_db();
    $cols = []; $vals = [];
    foreach ($fields as $k=>$v) { $cols[] = "`$k`=?"; $vals[] = $v; }
    $vals[] = $order_id;
    $sql = "UPDATE orders SET " . implode(",", $cols) . " WHERE id=?";
    $stmt = $db->prepare($sql);
    $stmt->execute($vals);
    log_step("Order Updated", ["order_id" => $order_id, "fields" => $fields]);
}

function get_state($phone) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM convo_state WHERE phone=?");
    $stmt->execute([$phone]);
    $state = $stmt->fetch();
    log_step("Get State", ["phone" => $phone, "state" => $state]);
    return $state;
}

function set_state($phone, $order_id, $context_json='', $last_message='', $last_response='') {
    $db = get_db();
    $stmt = $db->prepare("REPLACE INTO convo_state (phone, last_message, last_response, order_id, context_json) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$phone, $last_message, $last_response, $order_id, $context_json]);
    log_step("Set State", ["phone" => $phone, "order_id" => $order_id]);
}

function clear_state($phone) {
    $db = get_db();
    $stmt = $db->prepare("DELETE FROM convo_state WHERE phone=?");
    $stmt->execute([$phone]);
    log_step("Clear State", ["phone" => $phone]);
}
