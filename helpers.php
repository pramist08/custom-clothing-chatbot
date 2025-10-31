<?php
// helpers.php - helper functions for DB, RapidAPI, and optional image generation
require_once __DIR__ . '/config.php';

function call_rapidapi_chat($messages, $model="GPT-5-mini") {
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
        error_log("RapidAPI error: $err");
        return null;
    }
    $data = json_decode($resp, true);
    if (!$data) {
        error_log("RapidAPI bad response: " . substr($resp,0,300));
        return null;
    }
    if (!empty($data['choices'][0]['message']['content'])) {
        return $data['choices'][0]['message']['content'];
    }
    if (!empty($data['message']['content'])) {
        return $data['message']['content'];
    }
    return $resp;
}

function call_openai_image($prompt, $size="512x768") {
    if (!USE_OPENAI_IMAGE || !OPENAI_API_KEY) return null;
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
        error_log("OpenAI image error: $err");
        return null;
    }
    $data = json_decode($resp, true);
    if (!$data) {
        error_log("OpenAI image response parse fail");
        return null;
    }
    if (!empty($data['data'][0]['b64_json'])) return $data['data'][0]['b64_json'];
    if (!empty($data['data'][0]['url'])) return $data['data'][0]['url'];
    return null;
}

function save_base64_image($b64, $prefix='preview') {
    if (strpos($b64, 'http') === 0) {
        return $b64;
    }
    if (!is_dir(IMAGE_SAVE_PATH)) @mkdir(IMAGE_SAVE_PATH, 0755, true);
    $data = base64_decode($b64);
    $fname = IMAGE_SAVE_PATH . '/' . $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.png';
    file_put_contents($fname, $data);
    if (defined('BASE_URL') && BASE_URL) {
        return rtrim(BASE_URL, '/') . '/' . ltrim(str_replace('\\','/',$fname), '/');
    }
    return $fname;
}

// Database helpers
function create_order($phone) {
    $db = get_db();
    $stmt = $db->prepare("INSERT INTO orders (phone) VALUES (?)");
    $stmt->execute([$phone]);
    return $db->lastInsertId();
}

function update_order($order_id, $fields) {
    $db = get_db();
    $cols = []; $vals = [];
    foreach ($fields as $k=>$v) { $cols[] = "`$k`=?"; $vals[] = $v; }
    $vals[] = $order_id;
    $sql = "UPDATE orders SET " . implode(",", $cols) . " WHERE id=?";
    $stmt = $db->prepare($sql);
    $stmt->execute($vals);
}

function get_state($phone) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM convo_state WHERE phone=?");
    $stmt->execute([$phone]);
    return $stmt->fetch();
}

function set_state($phone, $order_id, $context_json='', $last_message='', $last_response='') {
    $db = get_db();
    $stmt = $db->prepare("REPLACE INTO convo_state (phone, last_message, last_response, order_id, context_json) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$phone, $last_message, $last_response, $order_id, $context_json]);
}

function clear_state($phone) {
    $db = get_db();
    $stmt = $db->prepare("DELETE FROM convo_state WHERE phone=?");
    $stmt->execute([$phone]);
}
