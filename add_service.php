<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/service_functions.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = handleServiceCreation($pdo, $_POST);

    if (empty($errors)) {
        header("Location: services.php");
        exit;
    }
}

renderHeader("Add New Service");
?>

<main>
    <h2>Add a New Service</h2>

    <?php if (!empty($errors)): ?>
        <div style="color: red;">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post">
        <label>Name:<br>
            <input type="text" name="name" required>
        </label><br><br>

        <label>Category:<br>
            <?php renderCategoryDropdown($pdo, isset($service['category_id']) ? $service['category_id'] : null); ?>
        </label><br><br>

        <label>Description:<br>
            <textarea name="description" rows="4" cols="40"></textarea>
        </label><br><br>

        <label>Duration (Min–Max minutes):<br>
            <input type="number" name="duration_minutes_min" style="width: 70px"> –
            <input type="number" name="duration_minutes_max" style="width: 70px">
        </label><br><br>

        <label>Frequency:<br>
            <?php renderFrequencyDropdown('freq'); ?>
        </label><br><br>

        <label>Cost ($ per session):<br>
            <input type="number" name="cost" step="0.01">
        </label><br><br>

        <label>Status:<br>
            <select name="status">
                <option value="Active">Active</option>
                <option value="Scheduled" selected>Scheduled</option>
                <option value="Inactive">Inactive</option>
            </select>
        </label><br><br>


        <button type="submit">Add Service</button>
    </form>
</main>

<?php renderFooter(); ?>