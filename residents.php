<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/resident_functions.php';

renderHeader("Manage Residents");

// Handle CREATE
if (isset($_POST['add_resident'])) {
    addResident($pdo, $_POST['name'], $_POST['room_number']);
}

// Handle DELETE
if (isset($_GET['delete'])) {
    deleteResident($pdo, $_GET['delete']);
}

// Handle UPDATE
if (isset($_POST['edit_resident'])) {
    updateResident($pdo, $_POST['id'], $_POST['name'], $_POST['room_number']);
}

// Fetch all residents
$residents = getAllResidents($pdo);

// Fetch single resident for edit mode
$editResident = null;
if (isset($_GET['edit'])) {
    $editResident = getResidentById($pdo, $_GET['edit']);
}
?>

<main style="padding: 2em;">
    <h2><?= $editResident ? "Edit Resident" : "Add New Resident" ?></h2>

    <form method="post" style="margin-bottom: 2em;">
        <input type="hidden" name="id" value="<?= isset($editResident['id']) ? $editResident['id'] : '' ?>">

        <label>Name:</label><br>
        <input type="text" name="name" required value="<?= isset($editResident['name']) ? htmlspecialchars($editResident['name']) : '' ?>"><br>

        <label>Room Number:</label><br>
        <input type="text" name="room_number" required value="<?= isset($editResident['room_number']) ? htmlspecialchars($editResident['room_number']) : '' ?>"><br><br>

        <?php if ($editResident): ?>
            <button type="submit" name="edit_resident">Update Resident</button>
            <a href="residents.php" style="margin-left: 1em;">Cancel</a>
        <?php else: ?>
            <button type="submit" name="add_resident">Add Resident</button>
        <?php endif; ?>
    </form>

    <h2>Resident List</h2>
    <table border="1" cellpadding="8">
        <tr>
            <th>Name</th>
            <th>Room</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($residents as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['name']) ?></td>
                <td><?= htmlspecialchars($r['room_number']) ?></td>
                <td>
                    <a href="residents.php?edit=<?= $r['id'] ?>">Edit</a> |
                    <a href="residents.php?delete=<?= $r['id'] ?>" onclick="return confirm('Delete this resident?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</main>

<?php renderFooter(); ?>
