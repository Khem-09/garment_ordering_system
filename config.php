<?php
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'garment_ordering_system');

define('GO_LIVE_DATE', '2025-10-28');

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/classes/Csrf.php';
?>