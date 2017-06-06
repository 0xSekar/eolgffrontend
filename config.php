<?php
// Base directory
define('BASE_DIR', dirname(__FILE__));

$extConfig = BASE_DIR.'/config-ext.php';
if (file_exists($extConfig) && is_file($extConfig)) {
    include_once $extConfig;
}

if (!defined('AREPORTS')) {
	define('AREPORTS', 15);
	define('QREPORTS', 20);
}

if (!defined('SERVERHOST')) {
        define('SERVERHOST', 'job.oldschoolvalue.com');
}

if (!defined('YAHOO_INTEGRATION_DEBUG')) {
    define('YAHOO_INTEGRATION_DEBUG', false);
}

if (!defined('YAHOO_INTEGRATION_URL')) {
    define('YAHOO_INTEGRATION_URL', 'http://query.yahooapis.com');
}

if (!defined('YAHOO_INTEGRATION_PROXY')) {
    define('YAHOO_INTEGRATION_PROXY', null);
}

// Database
if (!defined('DB_FRONTEND_DATABASE')) {
    define('DB_FRONTEND_HOST', 'localhost');
    define('DB_FRONTEND_DATABASE', 'jjun0366_frontend');
    define('DB_FRONTEND_USER', 'wsuser');
    define('DB_FRONTEND_PASSWORD', 'ng2Xfy2SAu');
}

if (!defined('APP_DIR')) {
    define('APP_DIR', '/app');
}
