<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/visitor_functions.php';

renderHeader("Manage Visitors");

// CREATE
if (isset($_POST['add_visitor'])) {
    addVisitor(
        $pdo,
        isset($_POST['username']) ? $_POST['username'] : '',
        isset($_POST['full_name']) ? $_POST['full_name'] : '',
        isset($_POST['password']) ? $_POST['password'] : '',
        isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1
    );
}

// DELETE
if (isset($_GET['delete'])) {
    deleteVisitor($pdo, (int)$_GET['delete']);
}

// UPDATE
if (isset($_POST['edit_visitor'])) {
    updateVisitor(
        $pdo,
        (int)$_POST['id'],
        isset($_POST['username']) ? $_POST['username'] : '',
        isset($_POST['full_name']) ? $_POST['full_name'] : '',
        // password optional on edit — only update if provided
        isset($_POST['password']) ? $_POST['password'] : '',
        isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1
    );
}

// DATA
$visitor_list = getAllVisitors($pdo);
$editVisitor = null;
if (isset($_GET['edit'])) {
    $editVisitor = getVisitorById($pdo, (int)$_GET['edit']);
}
?>
<main style="padding: 2em; max-width: 900px; margin: 0 auto;">
    <h2><?= $editVisitor ? "Edit Visitor" : "Add New Visitor" ?></h2>

    <form method="post" style="margin-bottom: 2em;">
        <input type="hidden" name="id" value="<?= $editVisitor ? (int)$editVisitor['id'] : '' ?>">

        <label>Username:</label><br>
        <input type="text" name="username" required
               value="<?= $editVisitor ? htmlspecialchars($editVisitor['username']) : '' ?>"><br>

        <label>Full name:</label><br>
        <input type="text" name="full_name" required
               value="<?= $editVisitor ? htmlspecialchars($editVisitor['full_name']) : '' ?>"><br>

        <label>Password <?= $editVisitor ? "(leave blank to keep current)" : "" ?>:</label><br>
        <input type="password" name="password"><br>

        <label>Status:</label><br>
        <select name="is_active">
            <?php
                $isActive = $editVisitor ? (int)$editVisitor['is_active'] : 1;
            ?>
            <option value="1" <?= $isActive === 1 ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= $isActive === 0 ? 'selected' : '' ?>>Inactive</option>
        </select><br><br>

        <?php if ($editVisitor): ?>
            <button type="submit" name="edit_visitor">Update Visitor</button>
            <a href="/visitors.php" style="margin-left: 1em;">Cancel</a>
        <?php else: ?>
            <button type="submit" name="add_visitor">Add Visitor</button>
        <?php endif; ?>
    </form>

    <h2>Visitors</h2>
    <table border="1" cellpadding="8" style="width:100%; border-collapse: collapse;">
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Full name</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($visitor_list as $v): ?>
            <tr>
                <td><?= (int)$v['id'] ?></td>
                <td><?= htmlspecialchars($v['username']) ?></td>
                <td><?= htmlspecialchars($v['full_name']) ?></td>
                <td><?= $v['is_active'] ? 'Active' : 'Inactive' ?></td>
                <td>
                    <a href="/visitors.php?edit=<?= (int)$v['id'] ?>">Edit</a> |
                    <a href="/visitors.php?delete=<?= (int)$v['id'] ?>"
                       onclick="return confirm('Delete this visitor?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</main>

<?php renderFooter(); ?>
