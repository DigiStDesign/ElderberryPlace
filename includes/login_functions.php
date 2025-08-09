<?php
// -------- Helpers (keep PHP5.4-friendly) --------
function is_post_request() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function get_post($key, $default = '') {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

function get_next_url() {
    // Prefer POST (preserves value after failed submit), else GET
    if (isset($_POST['next'])) return $_POST['next'];
    if (isset($_GET['next'])) return $_GET['next'];
    return '/';
}

function redirect_to($url) {
    header('Location: ' . $url);
    exit;
}

function render_errors($errors) {
    if (empty($errors)) return;
    echo '<div class="alert alert-danger" style="background:#ffe6e6;border:1px solid #e0b4b4;padding:10px;border-radius:6px;margin:12px 0;">';
    foreach ($errors as $e) {
        echo '<p style="margin:0 0 6px 0;">' . htmlspecialchars($e) . '</p>';
    }
    echo '</div>';
}

function render_login_form($next) {
    // Keep names/ids simple for PHP 5.4
    $nextSafe = htmlspecialchars($next);
    echo '
    <form method="post" action="login.php" autocomplete="off">
        <input type="hidden" name="next" value="' . $nextSafe . '" />

        <div class="form-group" style="margin-bottom:10px;">
            <label for="username">Username</label><br />
            <input type="text" name="username" id="username" required
                   style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;" />
        </div>

        <div class="form-group" style="margin-bottom:12px;">
            <label for="password">Password</label><br />
            <input type="password" name="password" id="password" required
                   style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;" />
        </div>

        <button type="submit"
                style="padding:8px 14px;border:0;border-radius:6px;background:#2a7;color:#fff;cursor:pointer;">
            Sign in
        </button>
    </form>';
}
