<?php
// /api/lib/auth_api.php (PHP 5.4-safe)
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/db_api.php';

function current_user_id_api() {
    api_start_session();
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}
function current_role_api() {
    api_start_session();
    return isset($_SESSION['role']) ? strtoupper($_SESSION['role']) : 'GUEST';
}

function require_login_api() {
    api_start_session();
    if (!current_user_id_api()) {
        json_err('UNAUTH', 'Login required', 401);
    }
}

function require_role_api($roles) {
    api_start_session();
    $want = array();
    foreach ((array)$roles as $r) { $want[] = strtoupper(trim($r)); }
    $have = current_role_api(); // already uppercased
    if (!in_array($have, $want, true)) {
        json_err('FORBIDDEN', 'Insufficient role', 403);
    }
}

// convenience
function require_admin_api() { require_role_api('ADMIN'); }



// CSRF: require for unsafe methods
function require_csrf_api() {
    api_start_session();
    $m = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
    if ($m === 'GET' || $m === 'HEAD' || $m === 'OPTIONS') return; // not required
    $hdr = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
    if ($hdr === '' || $hdr !== csrf_value_api()) {
        json_err('CSRF', 'Invalid or missing CSRF token', 403);
    }
}
