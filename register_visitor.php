<?php
// register_visitor.php
require_once __DIR__ . '/config/db.php';   // defines global $pdo
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

start_secure_session();

$errors = array();

/**
 * Detect which password column is present so we match your current schema.
 * - Use MD5 into `password_hash` if it exists (legacy/insecure but demo-ok).
 * - Otherwise write plaintext into `password` (as requested for demo).
 */
function detect_password_column($pdo)
{
    $cols = array('password_hash', 'password');
    foreach ($cols as $c) {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM users LIKE ?');
        $stmt->execute(array($c));
        if ($stmt->fetch(PDO::FETCH_ASSOC)) return $c;
    }
    return null;
}

function username_exists($pdo, $username)
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $stmt->execute(array($username));
    return ((int)$stmt->fetchColumn()) > 0;
}

function insert_visitor($pdo, $full_name, $username, $password, $passwordColumn)
{
    if ($passwordColumn === 'password_hash') {
        $sql  = 'INSERT INTO users (username, full_name, password_hash, role, is_active)
                 VALUES (?, ?, MD5(?), "VISITOR", 1)';
        $args = array($username, $full_name, $password);
    } elseif ($passwordColumn === 'password') {
        $sql  = 'INSERT INTO users (username, full_name, password, role, is_active)
                 VALUES (?, ?, ?, "VISITOR", 1)';
        $args = array($username, $full_name, $password);
    } else {
        return false;
    }

    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($args)) {
        return $pdo->lastInsertId();
    }
    return false;
}

// use $pdo directly from db.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim(isset($_POST['full_name']) ? $_POST['full_name'] : '');
    $username  = trim(isset($_POST['username'])  ? $_POST['username']  : '');
    $password  = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm   = isset($_POST['confirm'])  ? $_POST['confirm']  : '';

    if ($full_name === '' || $username === '' || $password === '') {
        $errors[] = 'All fields are required.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors) && username_exists($pdo, $username)) {
        $errors[] = 'That username is already taken.';
    }

    if (empty($errors)) {
        $pwdCol = detect_password_column($pdo);
        if ($pwdCol === null) {
            $errors[] = 'User table has no compatible password column.';
        } else {
            $newId = insert_visitor($pdo, $full_name, $username, $password, $pwdCol);
            if ($newId !== false) {
                $user = array(
                    'id'        => $newId,
                    'username'  => $username,
                    'full_name' => $full_name,
                    'role'      => 'VISITOR'
                );
                if (function_exists('login_user')) {
                    login_user($user);
                }
                header('Location: index.php');
                exit;
            } else {
                $errors[] = 'Could not create account. Please try again.';
            }
        }
    }
}

renderHeader('Register as Visitor');
?>
<main>
  <h2>Register as Visitor</h2>

  <?php if (!empty($errors)): ?>
    <div style="background:#ffe6e6;border:1px solid #e0b4b4;padding:10px;border-radius:6px;margin:12px 0;">
      <?php foreach ($errors as $e): ?>
        <p style="margin:0 0 6px 0;"><?php echo htmlspecialchars($e); ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="register_visitor.php" autocomplete="off" style="max-width:420px;">
    <div style="margin-bottom:10px;">
      <label for="full_name">Full name</label><br>
      <input type="text" id="full_name" name="full_name" required
             style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
    </div>

    <div style="margin-bottom:10px;">
      <label for="username">Username</label><br>
      <input type="text" id="username" name="username" required
             style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
    </div>

    <div style="margin-bottom:10px;">
      <label for="password">Password</label><br>
      <input type="password" id="password" name="password" required
             style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
    </div>

    <div style="margin-bottom:12px;">
      <label for="confirm">Confirm password</label><br>
      <input type="password" id="confirm" name="confirm" required
             style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
    </div>

    <button type="submit"
            style="padding:8px 14px;border:0;border-radius:6px;background:#2a7;color:#fff;cursor:pointer;">
      Create visitor account
    </button>

    <a href="login.php" style="margin-left:10px;">Already have an account? Log in</a>
  </form>
</main>
<?php renderFooter(); ?>
