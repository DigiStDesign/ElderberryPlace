<?php
// /api/v1/AuthController.php
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

/**
 * POST /v1/auth/login
 * Body: { "username": "...", "password": "..." }
 * Returns: { ok:true, data:{ user:{...}, csrf:"..." } }
 */
function Auth_login() {
    api_start_session(); // starts the ELDERSESS session (see lib/http.php)

    $b = read_json();
    $username = isset($b['username']) ? trim($b['username']) : '';
    $password = isset($b['password']) ? (string)$b['password'] : '';

    if ($username === '' || $password === '') {
        json_err('VALIDATION', 'username and password are required', 422);
    }

    $pdo = api_db();
    $st = $pdo->prepare(
        "SELECT id, username, full_name, role, is_active, password_hash
         FROM users
         WHERE username = ?
         LIMIT 1"
    );
    $st->execute(array($username));
    $u = $st->fetch(PDO::FETCH_ASSOC);

    // Demo DB uses MD5 password hashes (note: case-insensitive compare)
    $ok = $u && (string)$u['is_active'] === '1' && strtolower($u['password_hash']) === md5($password);
    if (!$ok) {
        json_err('UNAUTH', 'Invalid credentials', 401);
    }

    // Mark session as logged in
    $_SESSION['user_id'] = (int)$u['id'];
    $_SESSION['role']    = $u['role'];

    // Regenerate ID without deleting old (safer on some stacks),
    // then explicitly re-issue cookie with path="/"
    session_regenerate_id(false);
    setcookie(session_name(), session_id(), 0, '/', '', false, true); // HttpOnly, not secure on localhost

    json_ok(array(
        'user' => array(
            'id'        => (int)$u['id'],
            'username'  => $u['username'],
            'full_name' => $u['full_name'],
            'role'      => $u['role']
        ),
        'csrf' => csrf_value_api()
    ));
}

/**
 * POST /v1/auth/logout
 */
function Auth_logout() {
    api_start_session();
    $_SESSION = array();

    // Expire the cookie site-wide
    setcookie(session_name(), '', time() - 3600, '/', '', false, true);

    // Destroy server-side session
    if (session_id()) {
        session_destroy();
    }

    json_ok(array('ok' => true));
}

/**
 * GET /v1/me
 * Returns the current user (if logged in)
 */
function Me_get() {
    api_start_session();
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($uid <= 0) {
        json_err('UNAUTH', 'Login required', 401);
    }

    $pdo = api_db();
    $st  = $pdo->prepare(
        "SELECT id, username, full_name, role, is_active, email
         FROM users
         WHERE id = ?
         LIMIT 1"
    );
    $st->execute(array($uid));
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
        json_err('UNAUTH', 'Login required', 401);
    }

    json_ok(array('user' => $u));
}
