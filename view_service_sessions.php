<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';

if (!isset($_GET['service_id'])) {
    die("Missing service ID.");
}

$service_id = (int) $_GET['service_id'];

// Get service name
$stmt = $pdo->prepare("SELECT name FROM services WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    die("Service not found.");
}

// Get scheduled sessions + staff
$stmt = $pdo->prepare("
    SELECT ss.id, ss.start_time, ss.end_time, ss.notes,
           GROUP_CONCAT(s.name SEPARATOR ', ') AS staff_names
    FROM service_schedule ss
    LEFT JOIN staff_schedule ssch ON ssch.schedule_id = ss.id
    LEFT JOIN staff s ON s.id = ssch.staff_id
    WHERE ss.service_id = ?
    GROUP BY ss.id
    ORDER BY ss.start_time
");
$stmt->execute([$service_id]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderHeader("Scheduled Sessions");
?>

<main>
    <h2>Scheduled Sessions for: <?= htmlspecialchars($service['name']) ?></h2>

    <?php if (empty($sessions)): ?>
        <p>No sessions have been scheduled yet for this service.</p>
    <?php else: ?>
        <table border="1" cellpadding="8" cellspacing="0">
            <tr>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Assigned Staff</th>
                <th>Notes</th>
            </tr>
            <?php foreach ($sessions as $session): ?>
                <tr>
                    <td><?= htmlspecialchars($session['start_time']) ?></td>
                    <td><?= htmlspecialchars($session['end_time']) ?></td>
                    <td><?= htmlspecialchars($session['staff_names'] ?: 'None') ?></td>
                    <td><?= nl2br(htmlspecialchars($session['notes'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <p><a href="services.php">← Back to Services</a></p>
</main>

<?php renderFooter(); ?>
