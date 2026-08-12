<?php
/**
 * Razorpay keys: same file for test (dev) and live (production).
 * Switch via RAZORPAY_MODE=live or APP_ENV=production in .env (see .env.sample).
 */
require_once __DIR__ . '/load_env.php';

// Helper: read from .env (getenv or $_ENV)
$env = function ($key, $default = '') {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    return isset($_ENV[$key]) ? $_ENV[$key] : $default;
};

// Mode: 'live' only if RAZORPAY_MODE=live or APP_ENV=production
$modeVar = $env('RAZORPAY_MODE');
$appEnv = $env('APP_ENV');
$razorpayMode = (strtolower($modeVar) === 'live' || strtolower($appEnv) === 'production') ? 'live' : 'test';

if ($razorpayMode === 'live') {
    define('RAZORPAY_KEY_ID',     $env('RAZORPAY_LIVE_KEY_ID',     'rzp_live_XXXXXXXX'));
    define('RAZORPAY_KEY_SECRET', $env('RAZORPAY_LIVE_KEY_SECRET', 'your_live_secret'));
} else {
    define('RAZORPAY_KEY_ID',     $env('RAZORPAY_KEY_ID',     'rzp_test_SBL7rlydGBwjSb'));
    define('RAZORPAY_KEY_SECRET', $env('RAZORPAY_KEY_SECRET', 'apfkz7CO1LoTpeb6TZK8BSM5'));
}

define('RAZORPAY_MODE', $razorpayMode);
define('RAZORPAY_CURRENCY', 'INR');
