<?php
require_once __DIR__ . '/config.php';

try {
    $db = get_db(); // uses your existing helper function
    echo "<h3>✅ Database connected successfully!</h3>";
} catch (PDOException $e) {
    echo "<h3>❌ Database connection failed:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>
