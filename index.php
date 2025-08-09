<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/layout.php';

start_secure_session();
$user = current_user();
$role = $user ? $user['role'] : 'GUEST';

renderHeader('Home');
?>
<main style="max-width:720px;margin:20px auto;">
<?php if ($role === 'GUEST'): ?>

    <h2>Welcome to Elderberry Place</h2>
    <p>Please log in or register to access the portal.</p>
    <div style="display:flex;gap:10px;margin-top:15px;">
        <a href="login.php" style="padding:10px 16px;background:#2a7;color:#fff;border-radius:6px;text-decoration:none;">Login</a>
        <a href="register_visitor.php" style="padding:10px 16px;background:#555;color:#fff;border-radius:6px;text-decoration:none;">Register as Visitor</a>
    </div>

<?php elseif ($role === 'VISITOR'): ?>
    <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?>!</h2>
    <p>This is the visitor dash.</p>

<?php elseif ($role === 'RESIDENT'): ?>
    <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?>!</h2>
    <p>This is the resident dash.</p>

<?php elseif ($role === 'STAFF'): ?>
    <h2>Hello <?php echo htmlspecialchars($user['full_name']); ?> </h2>
    <p>This is the staff dash.</p>

<?php elseif ($role === 'ADMIN'): ?>
    <h2>Hello <?php echo htmlspecialchars($user['full_name']); ?> </h2>
    <p>This is the admin dash.</p>

<?php endif; ?>
</main>
<?php renderFooter(); ?>
