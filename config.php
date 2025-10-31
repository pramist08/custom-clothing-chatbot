<?php
// config.php - simple config without dotenv. Edit values below before deploying.

// ---- MySQL (edit to match your DB on Render or local) ----
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'clothing_bot');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- Public base URL (used to build image URLs if needed) ----
define('BASE_URL', 'https://your-public-domain.com'); // set to your Render service URL

// ---- Twilio WhatsApp number (used for reference only) ----
define('TWILIO_WHATSAPP_NUMBER', 'whatsapp:+1415XXXXXXX');

// ---- RapidAPI GPT-5 settings ----
define('RAPIDAPI_HOST', 'chat-gpt26.p.rapidapi.com');
define('RAPIDAPI_KEY', 'YOUR_API_KEY_HERE'); // replace when ready

// ---- Optional OpenAI (for image generation) ----
define('USE_OPENAI_IMAGE', false);
define('OPENAI_API_KEY', 'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');

// ---- Image save path ----
define('IMAGE_SAVE_PATH', __DIR__ . '/public/uploads');

// ---- DB helper ----
function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
