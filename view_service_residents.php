<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';

if (!isset($_GET['service_id'])) {
    die("Missing service ID.");
}

$service_id = (int) $_GET['service_id'];

// Fetch service info
$stmt = $pdo->prepare("SELECT name FROM services WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$service) {
    die("Service not found.");
}

// Fetch resident signups across all scheduled sessions
$stmt = $pdo->prepare("
    SELECT rs.id, r.name AS resident_name, ss.start_time, ss.end_time
    FROM resident_schedule rs
    JOIN residents r ON r.id = rs.resident_id
    JOIN service_schedule ss ON ss.id = rs.schedule_id
    WHERE ss.service_id = ?
    ORDER BY ss.start_time, r.name
");
$stmt->execute([$service_id]);
$residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderHeader("Resident Signups");
?>

<main>
    <h2>Resident Signups for: <?= htmlspecialchars($service['name']) ?></h2>

    <?php if (empty($residents)): ?>
        <p>No residents have been booked for this service yet.</p>
    <?php else: ?>
        <table border="1" cellpadding="8" cellspacing="0">
            <tr>
                <th>Resident</th>
                <th>Start Time</th>
                <th>End Time</th>
            </tr>
            <?php foreach ($residents as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['resident_name']) ?></td>
                    <td><?= htmlspecialchars($r['start_time']) ?></td>
                    <td><?= htmlspecialchars($r['end_time']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <p><a href="services.php">← Back to Services</a></p>
</main>

<?php renderFooter(); ?>
