<?php
// login.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/login_functions.php';

start_secure_session();

$errors = array();
$next = get_next_url();

if (is_post_request()) {
    $username = trim(get_post('username'));
    $password = get_post('password');

    if ($username === '' || $password === '') {
        $errors[] = 'Username and password are required.';
    } else {
        list($ok, $result) = verify_credentials($username, $password);
        if ($ok) {
            login_user($result);
            redirect_to($next ?: 'index.php'); 
        } else {
            $errors[] = $result; 
        }
    }
}

renderHeader('Login');
?>
<main>
    <h2>Sign in</h2>

    <section style="background:#f4f4f4;padding:10px;margin-bottom:15px;border-radius:6px;">
        <p><strong>Current roles are:</strong></p>
        <ul style="margin:5px 0 10px 20px;">
            <li>ADMIN - login with <code>admin:admin</code></li>
            <li>STAFF - login with <code>staff:staff</code></li>
            <li>RESIDENT - login with <code>resident:resident</code></li>
            <li>VISITOR - login with <code>visitor:visitor</code></li>
        </ul>
    </section>

    <?php render_errors($errors); ?>
    <?php render_login_form($next); ?>
</main>
<?php renderFooter(); ?>
