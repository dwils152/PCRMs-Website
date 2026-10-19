<?php

// Loaded first by every page (through catalog.php): response headers, error
// display and session cookie settings.

// Errors go to the server log, never to the page. Set PCRMS_DEBUG=1 locally
// to see them in the browser.
if (!filter_var(getenv('PCRMS_DEBUG'), FILTER_VALIDATE_BOOLEAN)) {
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');

if (!function_exists('pcrms_security_headers')) {
    // The only inline script is the one-line `js` class setter in layout.php;
    // if it changes, update its hash here:
    //   printf "%s" "<script body>" | openssl dgst -sha256 -binary | base64
    function pcrms_security_headers()
    {
        if (headers_sent()) {
            return;
        }
        header_remove('X-Powered-By');
        header("Content-Security-Policy: default-src 'self'; "
            . "script-src 'self' 'sha256-DamCoPgB/VkDCPsu0R6X4sJsz1c66vGlUGBXLu04Yxg='; "
            . "style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; "
            . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }
}

if (!function_exists('pcrms_session_start')) {
    // Search tokens live in the session for up to six hours.
    function pcrms_session_start()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '21600');
        session_name('PCRMSSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

pcrms_security_headers();
