<?php
// /residents/visitations.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

start_secure_session();
require_login();
if (!user_has_role('RESIDENT')) {
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied. Resident role required.";
    exit;
}
if (!isset($pdo)) { die('Database connection ($pdo) not available.'); }

/* ============== Helpers ============== */

function current_user_id_safe() {
    if (function_exists('current_user')) {
        $u = current_user();
        if ($u && isset($u['id'])) return (int)$u['id'];
    }
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function ensure_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf() {
    if (empty($_POST['csrf']) || empty($_SESSION['csrf_token']) || $_POST['csrf'] !== $_SESSION['csrf_token']) {
        throw new Exception('Invalid CSRF token.');
    }
}

function format_datetime_ymd_hm($dt) {
    return $dt ? date('Y-m-d H:i', strtotime($dt)) : '';
}

function render_status_badge($status) {
    $base = "display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;";
    $map  = array(
        'PENDING'   => 'background:#fff3cd;border:1px solid #ffe69c;',
        'APPROVED'  => 'background:#d1e7dd;border:1px solid #a3cfbb;',
        'DECLINED'  => 'background:#f8d7da;border:1px solid #f1aeb5;',
        'CANCELLED' => 'background:#e2e3e5;border:1px solid #cfd1d4;'
    );
    $style = isset($map[$status]) ? $map[$status] : 'background:#e2e3e5;border:1px solid #cfd1d4;';
    return '<span style="'.$base.$style.'">'.e($status).'</span>';
}

/* ============== Data access ============== */

function fetch_visit_requests_for_resident(PDO $pdo, $residentId) {
    $sql = "
        SELECT vr.id, vr.requested_start, vr.status, vr.notes,
               u.full_name AS visitor_name
        FROM visit_requests vr
        JOIN users u ON u.id = vr.visitor_user_id
        WHERE vr.resident_id = :rid
        ORDER BY vr.requested_start DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':rid', $residentId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetch_request_owned(PDO $pdo, $residentId, $requestId) {
    $sql = "SELECT id, status FROM visit_requests
            WHERE id = :id AND resident_id = :rid LIMIT 1";
    $st  = $pdo->prepare($sql);
    $st->bindValue(':id',  $requestId, PDO::PARAM_INT);
    $st->bindValue(':rid', $residentId, PDO::PARAM_INT);
    $st->execute();
    return $st->fetch(PDO::FETCH_ASSOC);
}

function can_transition($currentStatus, $action) {
    // Allowed:
    //  - PENDING -> APPROVED or DECLINED
    //  - APPROVED -> CANCELLED
    if ($currentStatus === 'PENDING' && ($action === 'APPROVE' || $action === 'DECLINE')) return true;
    if ($currentStatus === 'APPROVED' && $action === 'CANCEL') return true;
    return false;
}

function target_status_for($action) {
    if ($action === 'APPROVE') return 'APPROVED';
    if ($action === 'DECLINE') return 'DECLINED';
    if ($action === 'CANCEL')  return 'CANCELLED';
    return null;
}

function update_request_status(PDO $pdo, $requestId, $residentId, $fromStatus, $toStatus) {
    // Constrain by id + resident_id + expected current status to be safe
    $sql = "UPDATE visit_requests
            SET status = :toStatus
            WHERE id = :id AND resident_id = :rid AND status = :fromStatus";
    $st  = $pdo->prepare($sql);
    $st->bindValue(':toStatus',   $toStatus,   PDO::PARAM_STR);
    $st->bindValue(':id',         $requestId,  PDO::PARAM_INT);
    $st->bindValue(':rid',        $residentId, PDO::PARAM_INT);
    $st->bindValue(':fromStatus', $fromStatus, PDO::PARAM_STR);
    $st->execute();
    return $st->rowCount() === 1;
}

/* ============== Handle Actions ============== */

$residentId = current_user_id_safe();
$flash = array('ok' => '', 'err' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        validate_csrf();

        $action    = isset($_POST['action']) ? strtoupper(trim($_POST['action'])) : '';
        $requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;

        if (!$requestId || !in_array($action, array('APPROVE','DECLINE','CANCEL'))) {
            throw new Exception('Invalid action or request id.');
        }

        $req = fetch_request_owned($pdo, $residentId, $requestId);
        if (!$req) throw new Exception('Request not found or not yours.');

        if (!can_transition($req['status'], $action)) {
            throw new Exception('This action is not allowed from the current status.');
        }

        $to = target_status_for($action);
        if (!$to) throw new Exception('Unknown target status.');

        if (!update_request_status($pdo, $requestId, $residentId, $req['status'], $to)) {
            throw new Exception('Update failed—status may have changed already.');
        }

        $flash['ok'] = "Updated request #{$requestId} to {$to}.";
    } catch (Exception $ex) {
        $flash['err'] = $ex->getMessage();
    }
}

$rows     = fetch_visit_requests_for_resident($pdo, $residentId);
$csrf_tok = ensure_csrf_token();

/* ============== Render ============== */

renderHeader("My Visitations");
?>
<main class="container" style="max-width: 960px; margin: 2rem auto;">
    <h2>My Visitations</h2>
    <p>Approve or decline pending requests. You can cancel a previously approved request.</p>

    <?php if ($flash['err']): ?>
        <div style="background:#fdecea;border:1px solid #f5c2c7;padding:12px;border-radius:8px;margin-bottom:16px;">
            <strong>Error:</strong> <?php echo e($flash['err']); ?>
        </div>
    <?php endif; ?>
    <?php if ($flash['ok']): ?>
        <div style="background:#ecfdf3;border:1px solid #badbcc;padding:12px;border-radius:8px;margin-bottom:16px;">
            <?php echo e($flash['ok']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($rows)): ?>
        <div style="background:#eef2ff;border:1px solid #c7d2fe;padding:12px;border-radius:8px;">
            No visit requests found yet.
        </div>
    <?php else: ?>
        <div style="overflow-x:auto; margin-top:12px;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid #ddd;">Requested Start</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid #ddd;">Visitor</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid #ddd;">Status</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid #ddd;">Notes</th>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid #ddd;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td style="padding:10px; border-bottom:1px solid #f0f0f0;">
                                <?php echo e(format_datetime_ymd_hm($r['requested_start'])); ?>
                            </td>
                            <td style="padding:10px; border-bottom:1px solid #f0f0f0;">
                                <?php echo e($r['visitor_name']); ?>
                            </td>
                            <td style="padding:10px; border-bottom:1px solid #f0f0f0;">
                                <?php echo render_status_badge($r['status']); ?>
                            </td>
                            <td style="padding:10px; border-bottom:1px solid #f0f0f0;">
                                <?php echo nl2br(e($r['notes'])); ?>
                            </td>
                            <td style="padding:10px; border-bottom:1px solid #f0f0f0;">
                                <?php
                                    $id = (int)$r['id'];
                                    $st = $r['status'];
                                    // Show allowed actions by state
                                    if ($st === 'PENDING'):
                                ?>
                                    <form method="post" style="display:inline-block;margin-right:6px;">
                                        <input type="hidden" name="csrf" value="<?php echo e($csrf_tok); ?>">
                                        <input type="hidden" name="request_id" value="<?php echo $id; ?>">
                                        <input type="hidden" name="action" value="APPROVE">
                                        <button type="submit" style="padding:6px 10px;border:none;border-radius:6px;background:#198754;color:#fff;cursor:pointer;">
                                            Approve
                                        </button>
                                    </form>
                                    <form method="post" style="display:inline-block;">
                                        <input type="hidden" name="csrf" value="<?php echo e($csrf_tok); ?>">
                                        <input type="hidden" name="request_id" value="<?php echo $id; ?>">
                                        <input type="hidden" name="action" value="DECLINE">
                                        <button type="submit" style="padding:6px 10px;border:none;border-radius:6px;background:#dc3545;color:#fff;cursor:pointer;">
                                            Decline
                                        </button>
                                    </form>
                                <?php
                                    elseif ($st === 'APPROVED'):
                                ?>
                                    <form method="post" style="display:inline-block;">
                                        <input type="hidden" name="csrf" value="<?php echo e($csrf_tok); ?>">
                                        <input type="hidden" name="request_id" value="<?php echo $id; ?>">
                                        <input type="hidden" name="action" value="CANCEL">
                                        <button type="submit" style="padding:6px 10px;border:none;border-radius:6px;background:#6c757d;color:#fff;cursor:pointer;">
                                            Cancel
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <em>No actions</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
<?php renderFooter(); ?>
