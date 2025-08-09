<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/schedule_functions.php';

renderHeader("Schedule a Service - Add Residents");

$messages = array();
$selectedService = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

/* ---------------- Data: Services & Sessions ---------------- */

$services = $pdo->query("SELECT id, name FROM services ORDER BY name ASC")
               ->fetchAll(PDO::FETCH_ASSOC);

$sessions = array();
if ($selectedService) {
    $stmt = $pdo->prepare("
        SELECT id, start_time, end_time
        FROM service_schedule
        WHERE service_id = ?
        ORDER BY start_time
    ");
    $stmt->execute(array($selectedService));
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------------- Data: Residents from users ---------------- */

$residents = $pdo->query("
    SELECT id, full_name
    FROM users
    WHERE role = 'RESIDENT' AND is_active = 1
    ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* ---------------- Handle submission ---------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
    $resident_ids = isset($_POST['residents']) ? $_POST['residents'] : array();

    // Load the session time window
    $stmt = $pdo->prepare("SELECT start_time, end_time FROM service_schedule WHERE id = ?");
    $stmt->execute(array($schedule_id));
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        $messages[] = "❌ Invalid session selected.";
    } else {
        $start = $schedule['start_time'];
        $end   = $schedule['end_time'];

        $unavailable = array();
        foreach ($resident_ids as $rid) {
            $rid = (int)$rid; // user.id for RESIDENT
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
                <option value="<?= (int)$s['id'] ?>" <?= $selectedService == $s['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit">View Sessions</button></noscript>
    </form>

    <?php if (!empty($sessions)): ?>
        <form method="post">
            <input type="hidden" name="service_id" value="<?= (int)$selectedService ?>">

            <label>Choose Scheduled Session:</label><br>
            <select name="schedule_id" required>
                <?php foreach ($sessions as $sess): ?>
                    <option value="<?= (int)$sess['id'] ?>">
                        <?= htmlspecialchars($sess['start_time']) ?> – <?= htmlspecialchars($sess['end_time']) ?>
                    </option>
                <?php endforeach; ?>
            </select><br><br>

            <label>Residents to Assign:</label><br>
            <?php foreach ($residents as $res): ?>
                <label>
                    <input type="checkbox" name="residents[]" value="<?= (int)$res['id'] ?>">
                    <?= htmlspecialchars($res['full_name']) ?>
                </label><br>
            <?php endforeach; ?><br>

            <button type="submit">Assign to Session</button>
        </form>
    <?php elseif ($selectedService): ?>
        <p>No sessions scheduled yet for this service.</p>
    <?php endif; ?>
</main>

<?php renderFooter(); ?>
