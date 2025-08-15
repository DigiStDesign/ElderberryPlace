<?php
// /includes/session.php

if (!function_exists('start_secure_session')) {
    function start_secure_session() {
        if (session_id()) return;
        $p = session_get_cookie_params();
        // lifetime 0 (session), path '/', reuse other flags
        session_set_cookie_params(0, '/', $p['domain'], $p['secure'], $p['httponly']);
        session_start();
    }
}

if (!function_exists('csrf_value')) {
    function csrf_value() {
        if (!session_id()) { start_secure_session(); }
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
        }
        return $_SESSION['csrf'];
    }
}
