<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/staff_functions.php';

renderHeader("Manage Staff");

// Handle CREATE
if (isset($_POST['add_staff'])) {
    $result = addStaffFromForm($pdo, $_POST);
    if ($result['ok']) {
        header('Location: staff.php');
        exit;
    }
    $errors = $result['errors'];
}

// Handle UPDATE
if (isset($_POST['edit_staff'])) {
    $result = updateStaffFromForm($pdo, $_POST);
    if ($result['ok']) {
        header('Location: staff.php');
        exit;
    }
    $errors = $result['errors'];
}

// Handle DELETE
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if (ctype_digit((string)$id)) {
        deleteStaffUser($pdo, (int)$id);
    }
    header('Location: staff.php');
    exit;
}

// For edit mode
$editStaff = null;
if (isset($_GET['edit']) && ctype_digit((string)$_GET['edit'])) {
    $editStaff = getStaffUserById($pdo, (int)$_GET['edit']); // includes profile + job
}

// Data for form
$jobs = getAllStaffJobs($pdo);

// List all staff
$staff_list = getAllStaffUsers($pdo);

// Preserve form values if validation failed
$form = array(
    'id'           => isset($editStaff['id']) ? $editStaff['id'] : '',
    'username'     => isset($editStaff['username']) ? $editStaff['username'] : (isset($_POST['username']) ? $_POST['username'] : ''),
    'full_name'    => isset($editStaff['full_name']) ? $editStaff['full_name'] : (isset($_POST['full_name']) ? $_POST['full_name'] : ''),
    'email'        => isset($editStaff['email']) ? $editStaff['email'] : (isset($_POST['email']) ? $_POST['email'] : ''),
    'staff_job_id' => isset($editStaff['staff_job_id']) ? $editStaff['staff_job_id'] : (isset($_POST['staff_job_id']) ? $_POST['staff_job_id'] : ''),
    'started_on'   => isset($editStaff['started_on']) ? $editStaff['started_on'] : (isset($_POST['started_on']) ? $_POST['started_on'] : ''),
);
?>
<main style="padding: 2em; max-width: 900px;">
    <h2><?= $editStaff ? "Edit Staff Member" : "Add New Staff Member" ?></h2>

    <?php if (!empty($errors)): ?>
        <div style="color:#b00020; background:#ffe6e6; padding:10px; border-radius:6px; margin-bottom:12px;">
            <ul style="margin:0 0 0 18px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" style="margin-bottom: 2em;">
        <input type="hidden" name="id" value="<?= htmlspecialchars($form['id'], ENT_QUOTES, 'UTF-8') ?>">

        <label>Username:</label><br>
        <input type="text" name="username" required value="<?= htmlspecialchars($form['username'], ENT_QUOTES, 'UTF-8') ?>"><br><br>

        <label>Full name:</label><br>
        <input type="text" name="full_name" required value="<?= htmlspecialchars($form['full_name'], ENT_QUOTES, 'UTF-8') ?>"><br><br>

        <label>Email (optional):</label><br>
        <input type="email" name="email" value="<?= htmlspecialchars($form['email'], ENT_QUOTES, 'UTF-8') ?>"><br><br>

        <?php if (!$editStaff): ?>
            <label>Temporary password:</label><br>
            <input type="text" name="password" required placeholder="Set a temp password for first login"><br><br>
        <?php else: ?>
            <label>Reset password (optional):</label><br>
            <input type="text" name="password" placeholder="Leave blank to keep current password"><br><br>
        <?php endif; ?>

        <label>Staff job (optional):</label><br>
        <select name="staff_job_id">
            <option value="">-- Select a job --</option>
            <?php foreach ($jobs as $job): ?>
                <option value="<?= (int)$job['id'] ?>" <?= ($form['staff_job_id'] == $job['id'] ? 'selected' : '') ?>>
                    <?= htmlspecialchars($job['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>

        <label>Start date (optional, YYYY-MM-DD):</label><br>
        <input type="text" name="started_on" value="<?= htmlspecialchars($form['started_on'], ENT_QUOTES, 'UTF-8') ?>" placeholder="2025-08-15"><br><br>

        <?php if ($editStaff): ?>
            <button type="submit" name="edit_staff">Update Staff</button>
            <a href="staff.php" style="margin-left: 1em;">Cancel</a>
        <?php else: ?>
            <button type="submit" name="add_staff">Add Staff</button>
        <?php endif; ?>
    </form>

    <h2>Staff Directory</h2>
    <table border="1" cellpadding="8" cellspacing="0">
        <tr style="background:#f8f8f8;">
            <th>Username</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Job</th>
            <th>Started</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($staff_list as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['username'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($s['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($s['email'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($s['job_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($s['started_on'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <a href="staff.php?edit=<?= (int)$s['id'] ?>">Edit</a> |
                    <a href="staff.php?delete=<?= (int)$s['id'] ?>"
                       onclick="return confirm('Delete this staff member? This will remove their profile too.');">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</main>

<?php renderFooter(); ?>
