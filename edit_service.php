<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/service_functions.php';

$errors = [];

if (!isset($_GET['id'])) {
    die("Missing service ID.");
}
$service_id = (int) $_GET['id'];

// Fetch existing service
$stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    die("Service not found.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $errors = updateService($pdo, $service_id, $_POST);

    if (empty($errors)) {
        header("Location: services.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $result = deleteServiceWithAssignments($pdo, $service_id);

    if ($result === true) {
        header("Location: services.php");
        exit;
    } else {
        $errors[] = "Error deleting service: " . $result->getMessage();
    }
}


renderHeader("Edit Service");
?>

<main>
    <h2>Edit Service: <?= htmlspecialchars($service['name']) ?></h2>

    <?php if (!empty($errors)): ?>
        <div style="color:red;">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post">
        <label>Name:<br>
            <input type="text" name="name" value="<?= htmlspecialchars($service['name']) ?>" required>
        </label><br><br>

        <label>Category:<br>
            <?php renderCategoryDropdown($pdo, isset($service['category_id']) ? $service['category_id'] : null); ?>
        </label><br><br>

        <label>Description:<br>
            <textarea name="description" rows="4" cols="40"><?= htmlspecialchars($service['description']) ?></textarea>
        </label><br><br>

        <label>Duration (Min–Max minutes):<br>
            <input type="number" name="duration_minutes_min" value="<?= $service['duration_minutes_min'] ?>"
                style="width: 70px"> –
            <input type="number" name="duration_minutes_max" value="<?= $service['duration_minutes_max'] ?>"
                style="width: 70px">
        </label><br><br>

        <label>Frequency:<br>
            <?php renderFrequencyDropdown('freq'); ?>
        </label><br><br>

        <label>Cost ($ per session):<br>
            <input type="number" name="cost" step="0.01" value="<?= $service['cost'] ?>">
        </label><br><br>

        <label>Status:<br>
            <select name="status">
                <option value="Active" <?= $service['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                <option value="Scheduled" <?= $service['status'] === 'Scheduled' ? 'selected' : '' ?>>Scheduled</option>
                <option value="Inactive" <?= $service['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </label><br><br>

        <button type="submit" name="save">Save Changes</button>
        <button type="submit" name="delete"
            onclick="return confirm('Are you sure you want to delete this service?')">Delete Service</button>
    </form>
</main>

<?php renderFooter(); ?>