<?php
// CORS headers for local frontend
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: http://localhost:5173");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
function json_ok($data = array(), $status = 200, $meta = array()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode(array('ok'=>true,'data'=>$data,'meta'=>$meta));
    exit;
}
function json_err($code, $message, $status = 400, $meta = array()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode(array('ok'=>false,'error'=>array('code'=>$code,'message'=>$message),'meta'=>$meta));
    exit;
}
function read_json() {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    return is_array($j) ? $j : array();
}

function api_start_session() {
    if (session_id()) return;

    // DEV on http://localhost: set a simple, site-wide cookie
    ini_set('session.cookie_secure', 0);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_path', '/');

    // Unique name avoids clashes with any legacy PHPSESSID cookie
    session_name('ELDERSESS');
    session_set_cookie_params(0, '/', '', false, true); // lifetime 0, path "/", secure=false, httponly=true
    session_start();

    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
    }
}
function csrf_value_api() {
    return isset($_SESSION['csrf']) ? $_SESSION['csrf'] : '';
}
