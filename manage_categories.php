<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/category_functions.php';

$errors = [];
$success = null;

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'add' && !empty($_POST['name'])) {
        $success = addCategory($pdo, $_POST['name']);
    }

    if ($action === 'edit' && !empty($_POST['id']) && !empty($_POST['name'])) {
        $success = updateCategory($pdo, $_POST['id'], $_POST['name']);
    }

    if ($action === 'delete' && !empty($_POST['id'])) {
        $result = deleteCategory($pdo, $_POST['id']);
        if (strpos($result, 'Cannot') !== false) {
            $errors[] = $result;
        } else {
            $success = $result;
        }
    }
}

$categories = getAllCategories($pdo);

renderHeader("Manage Categories");
?>

<main>
    <h2>Manage Categories</h2>

    <?php if ($success): ?>
        <p style="color: green;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <p style="color: red;"><?= htmlspecialchars($error) ?></p>
    <?php endforeach; ?>

    <form method="post">
        <input type="hidden" name="action" value="add">
        <input type="text" name="name" placeholder="New category name" required>
        <button type="submit">Add Category</button>
    </form>

    <hr>

    <table border="1" cellpadding="8">
        <tr>
            <th>Name</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($categories as $cat): ?>
            <tr>
                <td><?= htmlspecialchars($cat['name']) ?></td>
                <td>
                    <form method="post" style="display:inline-block;">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <input type="text" name="name" value="<?= htmlspecialchars($cat['name']) ?>" required>
                        <button type="submit">Update</button>
                    </form>
                    <form method="post" style="display:inline-block;" onsubmit="return confirm('Delete this category?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <button type="submit" style="color:red;">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</main>

<?php renderFooter(); ?>