<?php
// /residents/my_services.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

start_secure_session();
require_login();

// Must be RESIDENT
if (!user_has_role('RESIDENT')) {
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied. Resident role required.";
    exit;
}

if (!isset($pdo)) { die('Database connection ($pdo) not available.'); }

/* ------------ Small helpers ------------ */

function current_resident_id() {
    if (function_exists('current_user')) {
        $u = current_user();
        if ($u && isset($u['id'])) return (int)$u['id'];
    }
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function format_dt($dt) {
    return $dt ? date('Y-m-d H:i', strtotime($dt)) : '';
}

/**
 * Derive a friendly status using start/end vs now:
 *  - If end < now  => Completed
 *  - If start > now => Upcoming
 *  - Else => In progress
 */
function derive_status($start, $end) {
    $now = time();
    $st  = strtotime($start);
    $en  = strtotime($end);
    if ($en && $en < $now) return 'Completed';
    if ($st && $st > $now) return 'Upcoming';
    return 'In progress';
}

function status_badge($status) {
    $base = "display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;";
    $map  = array(
        'Upcoming'     => 'background:#cff4fc;border:1px solid #9eeaf9;',
        'In progress'  => 'background:#fff3cd;border:1px solid #ffe69c;',
        'Completed'    => 'background:#e2e3e5;border:1px solid #cfd1d4;'
    );
    $style = isset($map[$status]) ? $map[$status] : 'background:#e2e3e5;border:1px solid #cfd1d4;';
    return '<span style="'.$base.$style.'">'.e($status).'</span>';
}

/**
 * Fetch resident’s sessions with optional filter:
 *  $filter = 'all' | 'upcoming' | 'completed'
 */
function fetch_services_for_resident(PDO $pdo, $residentId, $filter) {
    $baseSql = "
        SELECT
            ss.id           AS schedule_id,
            s.name          AS service_name,
            ss.start_time   AS start_time,
            ss.end_time     AS end_time,
            ss.location     AS location,
            ss.notes        AS notes
        FROM resident_schedule rs
        JOIN service_schedule ss ON ss.id = rs.schedule_id
        JOIN services s          ON s.id = ss.service_id
        WHERE rs.resident_id = :rid
    ";

    // Apply simple time-based filter
    if ($filter === 'upcoming') {
        $baseSql .= " AND ss.start_time > NOW() ";
    } elseif ($filter === 'completed') {
        $baseSql .= " AND ss.end_time   < NOW() ";
    }

    $baseSql .= " ORDER BY ss.start_time DESC";

    $st = $pdo->prepare($baseSql);
    $st->bindValue(':rid', (int)$residentId, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ------------ Page data ------------ */

$residentId = current_resident_id();
$view = isset($_GET['view']) ? strtolower(trim($_GET['view'])) : 'all';
if (!in_array($view, array('all','upcoming','completed'))) $view = 'all';

$rows = fetch_services_for_resident($pdo, $residentId, $view);

/* ------------ Render ------------ */

renderHeader("My Services");
?>
<main class="container" style="max-width: 960px; margin: 2rem auto;">
    <h2>My Services</h2>
    <p>All services you’ve been booked into.</p>

    <!-- Filters -->
    <div style="margin: 8px 0 16px;">
        <a href="?view=all" style="margin-right:10px;<?= $view==='all'?'font-weight:bold;':'' ?>">All</a>
        <a href="?view=upcoming" style="margin-right:10px;<?= $view==='upcoming'?'font-weight:bold;':'' ?>">Upcoming</a>
        <a href="?view=completed" style="<?= $view==='completed'?'font-weight:bold;':'' ?>">Completed</a>
    </div>

    <?php if (empty($rows)): ?>
        <div style="background:#eef2ff;border:1px solid #c7d2fe;padding:12px;border-radius:8px;">
            No services found for this filter.
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid #ddd;">Service</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid #ddd;">Start</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid #ddd;">End</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid #ddd;">Location</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid #ddd;">Status</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid #ddd;">Notes</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                        $status = derive_status($r['start_time'], $r['end_time']);
                    ?>
                    <tr>
                        <td style="padding:10px; border-bottom:1px solid #f0f0f0;"><?php echo e($r['service_name']); ?></td>
                        <td style="padding:10px; border-bottom:1px solid #f0f0f0;"><?php echo e(format_dt($r['start_time'])); ?></td>
                        <td style="padding:10px; border-bottom:1px solid #f0f0f0;"><?php echo e(format_dt($r['end_time'])); ?></td>
                        <td style="padding:10px; border-bottom:1px solid #f0f0f0;"><?php echo e($r['location']); ?></td>
                        <td style="padding:10px; border-bottom:1px solid #f0f0f0;"><?php echo status_badge($status); ?></td>
                        <td style="padding:10px; border-bottom:1px solid #f0f0f0;"><?php echo nl2br(e($r['notes'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p style="margin-top:16px;">
        <a href="/residents/index.php">Back to Resident Dashboard</a>
    </p>
</main>
<?php renderFooter(); ?>
