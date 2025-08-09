<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/layout.php';

start_secure_session();
renderHeader('Roster');

// --- DB handle ---
$pdo = db();

// --- load staff list for the dropdown ---
$staffStmt = $pdo->query("SELECT id, name FROM staff ORDER BY name");
$staff = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

// chosen staff (default to first in list if none provided)
$selectedStaffId = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : (count($staff) ? (int)$staff[0]['id'] : 0);

// --- fetch bookings for the chosen staffer ---
$rows = array();
if ($selectedStaffId) {
    $sql = "
        SELECT 
            sch.start_time,
            sch.end_time,
            s.name AS service_name,
            COALESCE(r.name, '(unassigned)') AS resident_name,
            sch.location
        FROM staff_schedule ss
        JOIN service_schedule sch   ON sch.id = ss.schedule_id
        JOIN services s             ON s.id = sch.service_id
        LEFT JOIN resident_schedule rs ON rs.schedule_id = sch.id
        LEFT JOIN residents r          ON r.id = rs.resident_id
        WHERE ss.staff_id = :sid
        ORDER BY sch.start_time ASC, r.name ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':sid' => $selectedStaffId));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<main style="max-width:900px;margin:20px auto;">
    <h2>Staff Roster</h2>

        <section style="background:#f4f4f4;padding:10px;margin-bottom:15px;border-radius:6px;">
        <p><strong>Demo Only:</strong></p>
        <p style="margin:0;font-size:12px;color:#666;">Right now you select the staff member whom you want to view the roster of from the dropdown menu. Later this will get the currently logged in staff member from the $session.</p>
    </section>

    <form method="get" action="roster.php" style="margin:12px 0 18px;">
        <label for="staff_id"><strong>Select staff member:</strong></label>
        <select id="staff_id" name="staff_id" onchange="this.form.submit()"
                style="margin-left:8px;padding:6px 8px;">
            <?php foreach ($staff as $st): 
                $sel = ($selectedStaffId === (int)$st['id']) ? 'selected' : ''; ?>
                <option value="<?=(int)$st['id']?>" <?=$sel?>><?=htmlspecialchars($st['name'])?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit">Load</button></noscript>
    </form>

    <?php if (!$selectedStaffId): ?>
        <p>No staff members found.</p>
    <?php else: ?>
        <?php if (empty($rows)): ?>
            <p>No bookings for this staff member (yet).</p>
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
    <?php endif; ?>
</main>
<?php renderFooter(); ?>
