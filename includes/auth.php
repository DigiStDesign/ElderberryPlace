<?php
// includes/auth.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session.php';

// --- DB ---
function db() {
    global $pdo;
    return $pdo;
}

// --- User lookup (by username) ---
function find_user_by_username($username) {
    $sql = "SELECT id, username, full_name, password_hash, role, is_active
            FROM users WHERE username = :u LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute(array(':u' => $username));
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- Super basic password helpers (MD5) ---
function hash_password($password) {
    return md5($password);
}
function check_password($password, $stored_hash) {
    return md5($password) === $stored_hash;
}

// --- Auth core ---
function login_user($user) {
    start_secure_session();
    $_SESSION['user'] = array(
        'id'        => (int)$user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role'      => $user['role']
    );
    session_regenerate_id(true);
}
function logout_user() {
    start_secure_session();
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'],
            !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
    }
    session_destroy();
}
function current_user() {
    start_secure_session();
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}
function current_role() {
    $u = current_user();
    return $u ? $u['role'] : null;
}
function is_logged_in() { return current_user() !== null; }
function user_has_role($roles) {
    $role = current_role();
    if ($role === null) return false;
    return is_array($roles) ? in_array($role, $roles) : ($role === $roles);
}
function require_login() {
    if (!is_logged_in()) {
        $next = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        header('Location: /login.php?next=' . urlencode($next));
        exit;
    }
}
function require_role($roles) {
    require_login();
    if (!user_has_role($roles)) {
        header('HTTP/1.1 403 Forbidden');
        echo "<h1>403 Forbidden</h1><p>You don't have permission to access this page.</p>";
        exit;
    }
}

// --- Verify credentials (username + password) ---
function verify_credentials($username, $password) {
    $user = find_user_by_username($username);
    if (!$user || empty($user['is_active'])) return array(false, 'Invalid credentials.');
    if (!check_password($password, $user['password_hash'])) return array(false, 'Invalid credentials.');
    return array(true, $user);
}
