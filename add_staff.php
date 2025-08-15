<?php
require_once __DIR__ . '/config/db.php';   // must set $pdo
require_once __DIR__ . '/includes/layout.php';

start_page();

function start_page() {
    global $pdo;

    $errors = array();
    $jobs = load_staff_jobs($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $form = read_form();
        $errors = validate_form($pdo, $form);

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $userId = create_staff_user($pdo, $form);
                if ($form['staff_job_id']) {
                    create_staff_profile($pdo, $userId, $form['staff_job_id'], $form['started_on']);
                }

                $pdo->commit();
                header('Location: staff.php'); // your listing page
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
            }
        }

        render_page($errors, $jobs, $form);
        return;
    }

    // initial load
    render_page($errors, $jobs, default_form());
}

/** ---------- Data access ---------- */

function load_staff_jobs(PDO $pdo) {
    $stmt = $pdo->query("SELECT id, name FROM staff_jobs ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function username_exists(PDO $pdo, $username) {
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE username = ? LIMIT 1");
    $stmt->execute(array($username));
    return (bool)$stmt->fetchColumn();
}

function email_exists(PDO $pdo, $email) {
    if ($email === '') return false;
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
    $stmt->execute(array($email));
    return (bool)$stmt->fetchColumn();
}

function create_staff_user(PDO $pdo, array $f) {
    // role is fixed to STAFF
    $sql = "INSERT INTO users (username, full_name, email, password_hash, role, is_active)
            VALUES (?, ?, ?, MD5(?), 'STAFF', 1)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($f['username'], $f['full_name'], $f['email'], $f['password']));
    return (int)$pdo->lastInsertId();
}

function create_staff_profile(PDO $pdo, $userId, $staffJobId, $startedOn) {
    $sql = "INSERT INTO staff_profiles (user_id, staff_job_id, started_on)
            VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($userId, (int)$staffJobId, $startedOn ?: null));
}

/** ---------- Form helpers ---------- */

function default_form() {
    return array(
        'username'     => '',
        'full_name'    => '',
        'email'        => '',
        'password'     => '',
        'staff_job_id' => '',
        'started_on'   => ''
    );
}

function read_form() {
    return array(
        'username'     => trim(isset($_POST['username']) ? $_POST['username'] : ''),
        'full_name'    => trim(isset($_POST['full_name']) ? $_POST['full_name'] : ''),
        'email'        => trim(isset($_POST['email']) ? $_POST['email'] : ''),
        'password'     => isset($_POST['password']) ? $_POST['password'] : '',
        'staff_job_id' => isset($_POST['staff_job_id']) ? $_POST['staff_job_id'] : '',
        'started_on'   => isset($_POST['started_on']) ? $_POST['started_on'] : ''
    );
}

function validate_form(PDO $pdo, array $f) {
    $errors = array();

    if ($f['username'] === '') $errors[] = 'Username is required.';
    if ($f['full_name'] === '') $errors[] = 'Full name is required.';
    if ($f['password'] === '') $errors[] = 'Temporary password is required.';

    if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }

    if (username_exists($pdo, $f['username'])) {
        $errors[] = 'Username already exists.';
    }

    if ($f['email'] !== '' && email_exists($pdo, $f['email'])) {
        $errors[] = 'Email already in use.';
    }

    if ($f['staff_job_id'] !== '' && !ctype_digit((string)$f['staff_job_id'])) {
        $errors[] = 'Invalid staff job selection.';
    }

    if ($f['started_on'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['started_on'])) {
        $errors[] = 'Start date must be YYYY-MM-DD.';
    }

    return $errors;
}

/** ---------- Rendering ---------- */

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function render_page(array $errors, array $jobs, array $f) {
    renderHeader('Add Staff Member');
    ?>
    <main>
        <h2>Add New Staff Member</h2>

        <?php if (!empty($errors)): ?>
            <div style="color:#b00020; background:#ffe6e6; padding:10px; border-radius:6px; margin-bottom:12px;">
                <ul style="margin:0 0 0 18px;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= h($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" style="max-width:520px;">
            <label>
                Username<br>
                <input type="text" name="username" required value="<?= h($f['username']) ?>">
            </label><br><br>

            <label>
                Full name<br>
                <input type="text" name="full_name" required value="<?= h($f['full_name']) ?>">
            </label><br><br>

            <label>
                Email (optional)<br>
                <input type="email" name="email" value="<?= h($f['email']) ?>">
            </label><br><br>

            <label>
                Temporary password<br>
                <input type="text" name="password" required placeholder="Set a temp password for first login">
            </label><br><br>

            <label>
                Staff job (optional)<br>
                <select name="staff_job_id">
                    <option value="">-- Select a job --</option>
                    <?php foreach ($jobs as $job): ?>
                        <option value="<?= (int)$job['id'] ?>" <?= ($f['staff_job_id']==$job['id'] ? 'selected' : '') ?>>
                            <?= h($job['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label><br><br>

            <label>
                Start date (optional, YYYY-MM-DD)<br>
                <input type="text" name="started_on" value="<?= h($f['started_on']) ?>" placeholder="2025-08-15">
            </label><br><br>

            <button type="submit">Add Staff</button>
        </form>
    </main>
    <?php
    renderFooter();
}