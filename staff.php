<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/staff_functions.php';

renderHeader("Manage Staff");

// Handle CREATE
if (isset($_POST['add_staff'])) {
    addStaff($pdo, $_POST['name'], $_POST['role'], $_POST['email']);
}

// Handle DELETE
if (isset($_GET['delete'])) {
    deleteStaff($pdo, $_GET['delete']);
}

// Handle UPDATE
if (isset($_POST['edit_staff'])) {
    updateStaff($pdo, $_POST['id'], $_POST['name'], $_POST['role'], $_POST['email']);
}

// Fetch all staff
$staff_list = getAllStaff($pdo);

// Fetch for edit mode
$editStaff = null;
if (isset($_GET['edit'])) {
    $editStaff = getStaffById($pdo, $_GET['edit']);
}
?>

<main style="padding: 2em;">
    <h2><?= $editStaff ? "Edit Staff Member" : "Add New Staff Member" ?></h2>

    <form method="post" style="margin-bottom: 2em;">
        <input type="hidden" name="id" value="<?= isset($editStaff['id']) ? $editStaff['id'] : '' ?>">

        <label>Name:</label><br>
        <input type="text" name="name" required value="<?= isset($editStaff['name']) ? htmlspecialchars($editStaff['name']) : '' ?>"><br>

        <label>Role:</label><br>
        <input type="text" name="role" required value="<?= isset($editStaff['role']) ? htmlspecialchars($editStaff['role']) : '' ?>"><br>

        <label>Email:</label><br>
        <input type="email" name="email" required value="<?= isset($editStaff['email']) ? htmlspecialchars($editStaff['email']) : '' ?>"><br><br>

        <?php if ($editStaff): ?>
            <button type="submit" name="edit_staff">Update Staff</button>
            <a href="staff.php" style="margin-left: 1em;">Cancel</a>
        <?php else: ?>
            <button type="submit" name="add_staff">Add Staff</button>
        <?php endif; ?>
    </form>

    <h2>Staff Directory</h2>
    <table border="1" cellpadding="8">
        <tr>
            <th>Name</th>
            <th>Role</th>
            <th>Email</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($staff_list as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['name']) ?></td>
                <td><?= htmlspecialchars($s['role']) ?></td>
                <td><?= htmlspecialchars($s['email']) ?></td>
                <td>
                    <a href="staff.php?edit=<?= $s['id'] ?>">Edit</a> |
                    <a href="staff.php?delete=<?= $s['id'] ?>" onclick="return confirm('Delete this staff member?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</main>

<?php renderFooter(); ?>
