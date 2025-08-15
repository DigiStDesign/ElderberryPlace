<?php
require_once __DIR__ . '/config/db.php';     // provides $pdo
require_once __DIR__ . '/includes/layout.php';

renderHeader('Service Sessions');

// ---- Inputs ----
$serviceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
if ($serviceId <= 0) {
    echo "<p>Invalid service id.</p>";
    renderFooter();
    exit;
}

// ---- Helpers (small, readable functions) ----
function fetchService(PDO $pdo, $serviceId) {
    $sql = "SELECT id, name, description FROM services WHERE id = :sid";
    $st  = $pdo->prepare($sql);
    $st->execute(array(':sid' => $serviceId));
    return $st->fetch(PDO::FETCH_ASSOC);
}

function fetchSessionsWithPeople(PDO $pdo, $serviceId) {
    // Uses staff_assignments.staff_user_id and resident_schedule.resident_user_id (new schema)
    $sql = "
        SELECT
            ss.id AS schedule_id,
            ss.start_time,
            ss.end_time,
            ss.location,
            ss.notes,
            -- Staff on this session
            GROUP_CONCAT(DISTINCT u_staff.full_name ORDER BY u_staff.full_name SEPARATOR ', ') AS staff_names,
            -- Residents on this session
            GROUP_CONCAT(DISTINCT u_res.full_name ORDER BY u_res.full_name SEPARATOR ', ')   AS resident_names,
            -- Quick counts by status (optional, useful)
            SUM(CASE WHEN rs.status = 'BOOKED'    THEN 1 ELSE 0 END) AS booked_count,
            SUM(CASE WHEN rs.status = 'ATTENDED'  THEN 1 ELSE 0 END) AS attended_count,
            SUM(CASE WHEN rs.status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled_count,
            SUM(CASE WHEN rs.status = 'NOSHOW'    THEN 1 ELSE 0 END) AS noshow_count
        FROM service_schedule ss
        LEFT JOIN staff_assignments sa
               ON sa.schedule_id = ss.id
              AND sa.service_id  = ss.service_id
        LEFT JOIN users u_staff
               ON u_staff.id = sa.staff_user_id
        LEFT JOIN resident_schedule rs
               ON rs.schedule_id = ss.id
        LEFT JOIN users u_res
               ON u_res.id = rs.resident_user_id
        WHERE ss.service_id = :sid
        GROUP BY ss.id, ss.start_time, ss.end_time, ss.location, ss.notes
        ORDER BY ss.start_time ASC
    ";
    $st = $pdo->prepare($sql);
    $st->execute(array(':sid' => $serviceId));
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// ---- Data load ----
$service  = fetchService($pdo, $serviceId);
$sessions = fetchSessionsWithPeople($pdo, $serviceId);

// ---- Render ----
if (!$service) {
    echo "<p>Service not found.</p>";
    renderFooter();
    exit;
}
?>
<h2><?php echo h($service['name']); ?> — Sessions</h2>
<p><?php echo nl2br(h((string)$service['description'])); ?></p>

<?php if (empty($sessions)): ?>
    <p>No sessions scheduled for this service yet.</p>
<?php else: ?>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Start</th>
                <th>End</th>
                <th>Location</th>
                <th>Staff</th>
                <th>Residents</th>
                <th>Status (Booked/Attended/Cancelled/Noshow)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sessions as $row): ?>
            <tr>
                <td><?php echo h($row['start_time']); ?></td>
                <td><?php echo h($row['end_time']); ?></td>
                <td><?php echo h($row['location']); ?></td>
                <td><?php echo h($row['staff_names'] ?: '—'); ?></td>
                <td><?php echo h($row['resident_names'] ?: '—'); ?></td>
                <td>
                    <?php
                    echo (int)$row['booked_count']    . ' / ';
                    echo (int)$row['attended_count']  . ' / ';
                    echo (int)$row['cancelled_count'] . ' / ';
                    echo (int)$row['noshow_count'];
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php renderFooter(); ?>
