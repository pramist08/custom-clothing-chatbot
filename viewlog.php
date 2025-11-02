<?php
// viewlog.php — simple viewer for your bot logs
$log_file = __DIR__ . '/errorlog.txt';

if (!file_exists($log_file)) {
    echo "<h3>No log file found.</h3>";
    exit;
}

echo "<h2>Bot Log Viewer</h2>";
echo "<pre style='background:#111;color:#0f0;padding:15px;border-radius:8px;font-size:14px;'>";
readfile($log_file);
echo "</pre>";
?>
