<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/schedule_functions.php';

renderHeader("Schedule a Service - Staff");

// Get preselected service from link
$preselect_service_id = isset($_GET['service_id']) ? (int) $_GET['service_id'] : 0;

// Fetch dropdown options
$services = $pdo->query("SELECT id, name FROM services ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch STAFF users instead of staff table
$staff = $pdo->query("
    SELECT id, full_name AS name 
    FROM users 
    WHERE role = 'STAFF' AND is_active = 1
    ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$messages = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = (int)$_POST['service_id'];
    $staff_id = (int)$_POST['staff_id'];
    $date = trim($_POST['date']);
    $start = $date . ' ' . trim($_POST['start_time']) . ':00';
    $end = $date . ' ' . trim($_POST['end_time']) . ':00';
    $notes = trim($_POST['notes']);

    if (!isStaffAvailable($pdo, $staff_id, $start, $end)) {
        $messages[] = "❌ Selected staff member is not available for that time.";
    }

    if (empty($messages)) {
        $schedule_id = addServiceSchedule($pdo, $service_id, $start, $end, $notes);
        assignStaffToSchedule($pdo, $staff_id, $schedule_id);
        $messages[] = "✅ Service scheduled successfully!";
        $messages[] = '<a href="schedule_resident.php?schedule_id=' . $schedule_id . '">➡ Add Residents to this session</a>';
    }
}
?>

<main style="padding: 2em;">
    <h2>Schedule a Service (Staff)</h2>

    <?php foreach ($messages as $msg): ?>
        <div class="msg"><?= $msg ?></div>
    <?php endforeach; ?>

    <form method="post">
        <label>Service:</label><br>
        <select name="service_id" required>
            <option value="">-- Select Service --</option>
            <?php foreach ($services as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id'] == $preselect_service_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>

        <label>Staff Member:</label><br>
        <select name="staff_id" required>
            <option value="">-- Select Staff --</option>
            <?php foreach ($staff as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <label>Date:</label><br>
        <input type="date" name="date" required><br><br>

        <label>Start Time:</label><br>
        <input type="time" name="start_time" required><br><br>

        <label>End Time:</label><br>
        <input type="time" name="end_time" required><br><br>

        <label>Notes:</label><br>
        <textarea name="notes" rows="3" cols="40"></textarea><br><br>

        <button type="submit">Create Session</button>
    </form>
</main>

<?php renderFooter(); ?>
