<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/schedule_functions.php';

renderHeader("Schedule a Service - Add Residents");

$messages = [];
$selectedService = isset($_GET['service_id']) ? (int)$_GET['service_id'] : null;

// Get all services
$services = $pdo->query("SELECT id, name FROM services ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get all residents
$residents = $pdo->query("SELECT id, name FROM residents ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// If a service is selected, load its scheduled sessions
$sessions = [];
if ($selectedService) {
    $stmt = $pdo->prepare("
        SELECT id, start_time, end_time
        FROM service_schedule
        WHERE service_id = ?
        ORDER BY start_time
    ");
    $stmt->execute([$selectedService]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule_id = (int)$_POST['schedule_id'];
    $resident_ids = isset($_POST['residents']) ? $_POST['residents'] : [];

    // Get time range for availability check
    $stmt = $pdo->prepare("SELECT start_time, end_time FROM service_schedule WHERE id = ?");
    $stmt->execute([$schedule_id]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        $messages[] = "❌ Invalid session selected.";
    } else {
        $start = $schedule['start_time'];
        $end = $schedule['end_time'];

        $unavailable = [];
        foreach ($resident_ids as $rid) {
            if (!isResidentAvailable($pdo, $rid, $start, $end)) {
                $unavailable[] = $rid;
            }
        }

        if (empty($unavailable)) {
            assignResidentsToSchedule($pdo, $resident_ids, $schedule_id);
            $messages[] = "✅ Residents successfully added to the session.";
        } else {
            $messages[] = "❌ Some residents are unavailable during that time.";
        }
    }
}
?>

<main style="padding: 2em;">
    <h2>Assign Residents to a Scheduled Session</h2>

    <?php foreach ($messages as $msg): ?>
        <div class="msg"><?= $msg ?></div>
    <?php endforeach; ?>

    <form method="get">
        <label>Select Service:</label>
        <select name="service_id" onchange="this.form.submit()">
            <option value="">-- Choose Service --</option>
            <?php foreach ($services as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $selectedService == $s['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit">View Sessions</button></noscript>
    </form>

    <?php if (!empty($sessions)): ?>
        <form method="post">
            <input type="hidden" name="service_id" value="<?= $selectedService ?>">

            <label>Choose Scheduled Session:</label><br>
            <select name="schedule_id" required>
                <?php foreach ($sessions as $sess): ?>
                    <option value="<?= $sess['id'] ?>">
                        <?= htmlspecialchars($sess['start_time']) ?> – <?= htmlspecialchars($sess['end_time']) ?>
                    </option>
                <?php endforeach; ?>
            </select><br><br>

            <label>Residents to Assign:</label><br>
            <?php foreach ($residents as $res): ?>
                <label>
                    <input type="checkbox" name="residents[]" value="<?= $res['id'] ?>">
                    <?= htmlspecialchars($res['name']) ?>
                </label><br>
            <?php endforeach; ?><br>

            <button type="submit">Assign to Session</button>
        </form>
    <?php elseif ($selectedService): ?>
        <p>No sessions scheduled yet for this service.</p>
    <?php endif; ?>
</main>

<?php renderFooter(); ?>
