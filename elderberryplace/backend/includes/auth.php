<?php
// /includes/auth.php
require_once __DIR__ . '/session.php';

function current_user() {
    start_secure_session();
    if (empty($_SESSION['user_id'])) return null;
    return array(
        'id'        => (int)$_SESSION['user_id'],
        'username'  => isset($_SESSION['username']) ? $_SESSION['username'] : null, // optional
        'full_name' => isset($_SESSION['full_name']) ? $_SESSION['full_name'] : null, // optional
        'role'      => isset($_SESSION['role']) ? $_SESSION['role'] : 'GUEST'
    );
}

function current_role() {
    $u = current_user();
    return $u ? $u['role'] : 'GUEST';
}

function user_has_role($role) {
    return current_role() === $role;
}

function require_login() {
    if (!current_user()) {
        header('Location: /login.php');
        exit;
    }
}
