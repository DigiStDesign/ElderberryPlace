<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/layout.php';

start_secure_session();
require_login(); // Ensure user is logged in

// Get current user info
$currentUser = current_user();

// Only allow staff to view this page
if ($currentUser['role'] !== 'STAFF') {
    die('Access denied.');
}

renderHeader('My Roster');

$pdo = db();

// Fetch bookings for the logged-in staff member
$sql = "
    SELECT 
        sch.start_time,
        sch.end_time,
        s.name AS service_name,
        COALESCE(u.full_name, '(unassigned)') AS resident_name,
        sch.location
    FROM staff_assignments sa
    JOIN service_schedule sch ON sch.id = sa.schedule_id
    JOIN services s          ON s.id = sch.service_id
    LEFT JOIN resident_schedule rs ON rs.schedule_id = sch.id
    LEFT JOIN users u              ON u.id = rs.resident_user_id AND u.role = 'RESIDENT'
    WHERE sa.staff_user_id = :sid
    ORDER BY sch.start_time ASC, u.full_name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':sid' => $currentUser['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<main style="max-width:900px;margin:20px auto;">
    <h2>My Roster</h2>

    <?php if (empty($rows)): ?>
        <p>No bookings assigned to you (yet).</p>
    <?php else: ?>
        <table border="0" cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:#f2f2f2;border-bottom:1px solid #ddd;">
                    <th align="left">Start</th>
                    <th align="left">End</th>
                    <th align="left">Service</th>
                    <th align="left">Resident</th>
                    <th align="left">Location</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr style="border-bottom:1px solid #eee;">
                        <td><?=htmlspecialchars($r['start_time'])?></td>
                        <td><?=htmlspecialchars($r['end_time'])?></td>
                        <td><?=htmlspecialchars($r['service_name'])?></td>
                        <td><?=htmlspecialchars($r['resident_name'])?></td>
                        <td><?=htmlspecialchars($r['location'])?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>
<?php renderFooter(); ?>
