<?php
// /visitors/book_visit.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

start_secure_session();
require_login(); // must be logged in

// --- Role guard: must be VISITOR ---
if (!user_has_role('VISITOR')) {
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied. Visitor role required.";
    exit;
}

if (!isset($pdo)) { die('Database connection ($pdo) not available. Check config/db.php'); }

/* ----------------- Helpers ----------------- */
function current_user_id() {
    if (function_exists('current_user')) {
        $u = current_user();
        if ($u && isset($u['id'])) return (int)$u['id'];
    }
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

/**
 * Return active RESIDENT users that this visitor has a relationship with.
 * (Any relationship type; you can add date filters if you want “active only”.)
 */
function fetch_related_residents(PDO $pdo, $visitorUserId) {
    $sql = "
        SELECT DISTINCT u.id, u.full_name
        FROM visitor_resident_links vrl
        JOIN users u ON u.id = vrl.resident_user_id
        WHERE vrl.visitor_user_id = :vid
          AND u.role = 'RESIDENT'
          AND u.is_active = 1
        ORDER BY u.full_name ASC
    ";
    $st = $pdo->prepare($sql);
    $st->execute(array(':vid' => (int)$visitorUserId));
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Quick check to enforce relationship on POST */
function visitor_has_relationship(PDO $pdo, $visitorUserId, $residentUserId) {
    $st = $pdo->prepare("
        SELECT 1
        FROM visitor_resident_links
        WHERE visitor_user_id = :vid
          AND resident_user_id = :rid
        LIMIT 1
    ");
    $st->execute(array(':vid' => (int)$visitorUserId, ':rid' => (int)$residentUserId));
    return (bool)$st->fetchColumn();
}

function combine_date_time($date, $time) {
    $date = trim($date);
    $time = trim($time);
    if ($date === '' || $time === '') return null; // expect YYYY-MM-DD + HH:MM
    return $date . ' ' . $time . ':00';
}

function validate_request_input($resident_id, $date, $time) {
    $errors = array();
    if (!$resident_id || !is_numeric($resident_id)) $errors[] = "Please select a resident.";
    if ($date === '')  $errors[] = "Please choose a date.";
    if ($time === '')  $errors[] = "Please choose a time.";
    if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errors[] = "Date format must be YYYY-MM-DD.";
    if ($time !== '' && !preg_match('/^\d{2}:\d{2}$/', $time))       $errors[] = "Time format must be HH:MM (24-hour).";
    return $errors;
}

/**
 * NEW SCHEMA: uses resident_user_id (not resident_id).
 */
function save_visit_request(PDO $pdo, $visitor_user_id, $resident_id, $requested_start, $notes) {
    $sql = "INSERT INTO visit_requests
            (visitor_user_id, resident_user_id, requested_start, requested_end, notes, status)
            VALUES (:visitor_user_id, :resident_user_id, :requested_start, NULL, :notes, 'PENDING')";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':visitor_user_id',  (int)$visitor_user_id, PDO::PARAM_INT);
    $stmt->bindValue(':resident_user_id', (int)$resident_id,     PDO::PARAM_INT);
    $stmt->bindValue(':requested_start',  $requested_start,      PDO::PARAM_STR);
    // allow NULL notes safely
    if ($notes === '') { $notes = null; }
    $stmt->bindValue(':notes', $notes, $notes === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    return $stmt->execute();
}

/* --------------- Handle Request --------------- */
$errors  = array();
$success = false;

$visitor_id = current_user_id();

// Preload the allowed resident list for this visitor
$residents = fetch_related_residents($pdo, $visitor_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resident_id = isset($_POST['resident_id']) ? (int)$_POST['resident_id'] : 0; // users.id (RESIDENT)
    $date        = isset($_POST['date']) ? trim($_POST['date']) : '';
    $time        = isset($_POST['time']) ? trim($_POST['time']) : '';
    $notes       = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    $errors = validate_request_input($resident_id, $date, $time);

    // Enforce relationship server-side as well (don’t trust only the dropdown)
    if ($resident_id && !visitor_has_relationship($pdo, $visitor_id, $resident_id)) {
        $errors[] = "You can only request visits with residents you’re linked to.";
    }

    $requested_start = combine_date_time($date, $time);
    if ($requested_start === null) {
        $errors[] = "Invalid date/time.";
    }

    if (!$visitor_id) {
        $errors[] = "Session error: visitor not found.";
    }

    if (empty($errors)) {
        try {
            $success = save_visit_request($pdo, $visitor_id, $resident_id, $requested_start, $notes);
            if (!$success) {
                $errors[] = "Could not save your request. Please try again.";
            }
        } catch (Exception $e) {
            $errors[] = "Database error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}

/* --------------- Render Page --------------- */
renderHeader("Request a Visit");
?>
<main class="container" style="max-width: 720px; margin: 2rem auto;">
    <h2>Request a Visit</h2>
    <p>Pick a resident you’re linked with, choose a date and time, and submit your request. The resident will then review it.</p>

    <?php if (!empty($errors)): ?>
        <div style="background:#fdecea;border:1px solid #f5c2c7;padding:12px;border-radius:8px;margin-bottom:16px;">
            <strong>There were some problems:</strong>
            <ul style="margin:8px 0 0 18px;">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div style="background:#ecfdf3;border:1px solid #badbcc;padding:12px;border-radius:8px;margin-bottom:16px;">
            <strong>Request submitted!</strong> It’s now marked as <em>PENDING</em>.
        </div>
    <?php endif; ?>

    <?php if (empty($residents)): ?>
        <div style="background:#fff3cd;border:1px solid #ffe69c;padding:12px;border-radius:8px;margin-bottom:16px;">
            You don’t have any linked residents yet. Please ask staff to add a relationship for you
            (or use the relationships page if you have access) before requesting a visit.
        </div>
    <?php else: ?>
        <form method="post" action="book_visit.php" style="display:grid;gap:12px;">
            <label>
                Resident
                <select name="resident_id" required style="width:100%;padding:8px;">
                    <option value="">-- Select a resident --</option>
                    <?php foreach ($residents as $r): ?>
                        <option value="<?php echo (int)$r['id']; ?>">
                            <?php echo htmlspecialchars($r['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Date
                <input type="date" name="date" required style="width:100%;padding:8px;">
            </label>

            <label>
                Time
                <input type="time" name="time" required style="width:100%;padding:8px;">
            </label>

            <label>
                Notes (optional)
                <textarea name="notes" rows="4" placeholder="Anything we should know?" style="width:100%;padding:8px;"></textarea>
            </label>

            <button type="submit" style="padding:10px 14px;border:none;border-radius:8px;background:#2b6cb0;color:#fff;cursor:pointer;">
                Submit Request
            </button>
        </form>
    <?php endif; ?>

    <p style="margin-top:16px;">
        <a href="index.php">Back to Visitor Dashboard</a>
    </p>
</main>
<?php renderFooter(); ?>
