<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/resident_functions.php';

renderHeader("Manage Residents");

/* ============================================================
   Helpers (small, readable, PHP5-safe)
   ============================================================ */
function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function app_base_path() {
    // Works whether the app is in / or /ElderberryPlace, etc.
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
    return $dir === '' ? '/' : $dir;
}
function url_for($path, array $params = array()) {
    $url = rtrim(app_base_path(), '/') . '/' . ltrim($path, '/');
    if (!empty($params)) {
        $q = http_build_query($params);
        $url .= (strpos($url, '?') === false ? '?' : '&') . $q;
    }
    return $url;
}

/* ============================================================
   Relationship summary (read-only)
   ============================================================ */
function fetchResidentLinks(PDO $pdo, $residentId) {
    $sql = "SELECT
                vrl.id,
                u.full_name AS visitor_name,
                rt.name     AS relationship_name,
                vrl.is_primary_contact,
                vrl.start_date,
                vrl.end_date,
                vrl.notes
            FROM visitor_resident_links vrl
            JOIN users u ON u.id = vrl.visitor_user_id
            JOIN relationship_types rt ON rt.id = vrl.relationship_type_id
            WHERE vrl.resident_user_id = :rid
            ORDER BY u.full_name";
    $st = $pdo->prepare($sql);
    $st->execute(array(':rid' => (int)$residentId));
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function countLinksForResident(PDO $pdo, $residentId) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM visitor_resident_links WHERE resident_user_id = :rid");
    $st->execute(array(':rid' => (int)$residentId));
    return (int)$st->fetchColumn();
}

/* ============================================================
   Actions (existing CRUD kept as-is)
   ============================================================ */
$errors = array();

// CREATE
if (isset($_POST['add_resident'])) {
    $result = addResidentFromForm($pdo, $_POST);
    if ($result['ok']) { header('Location: ' . url_for('residents.php')); exit; }
    $errors = $result['errors'];
}

// UPDATE
if (isset($_POST['edit_resident'])) {
    $result = updateResidentFromForm($pdo, $_POST);
    if ($result['ok']) { header('Location: ' . url_for('residents.php')); exit; }
    $errors = $result['errors'];
}

// DELETE resident user (and cascades profile/links via FKs)
if (isset($_GET['delete']) && ctype_digit((string)$_GET['delete'])) {
    deleteResidentUser($pdo, (int)$_GET['delete']);
    header('Location: ' . url_for('residents.php'));
    exit;
}

/* ============================================================
   Data
   ============================================================ */
$editResident = null;
if (isset($_GET['edit']) && ctype_digit((string)$_GET['edit'])) {
    $editResident = getResidentUserById($pdo, (int)$_GET['edit']); // includes profile
}

$residents = getAllResidentUsers($pdo);

// Preload relationship data when in edit mode (summary only)
$residentLinks = array();
if ($editResident) {
    $residentLinks = fetchResidentLinks($pdo, (int)$editResident['id']);
}

// Simple sticky form container
$form = array(
    'id'         => isset($editResident['id']) ? $editResident['id'] : '',
    'username'   => isset($editResident['username']) ? $editResident['username'] : (isset($_POST['username']) ? $_POST['username'] : ''),
    'full_name'  => isset($editResident['full_name']) ? $editResident['full_name'] : (isset($_POST['full_name']) ? $_POST['full_name'] : ''),
    'email'      => isset($editResident['email']) ? $editResident['email'] : (isset($_POST['email']) ? $_POST['email'] : ''),
    'room_number'=> isset($editResident['room_number']) ? $editResident['room_number'] : (isset($_POST['room_number']) ? $_POST['room_number'] : ''),
    'dob'        => isset($editResident['dob']) ? $editResident['dob'] : (isset($_POST['dob']) ? $_POST['dob'] : ''),
);
?>
<main style="padding: 2em; max-width: 1100px; margin: 0 auto;">
    <h2><?= $editResident ? "Edit Resident" : "Add New Resident" ?></h2>

    <?php if (!empty($errors)): ?>
        <div style="color:#b00020; background:#ffe6e6; padding:10px; border-radius:6px; margin-bottom:12px;">
            <ul style="margin:0 0 0 18px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= h($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Resident form (existing) -->
    <form method="post" style="margin-bottom: 2em; max-width:560px;">
        <input type="hidden" name="id" value="<?= h($form['id']) ?>">

        <label>Username</label><br>
        <input type="text" name="username" required value="<?= h($form['username']) ?>"><br><br>

        <label>Full name</label><br>
        <input type="text" name="full_name" required value="<?= h($form['full_name']) ?>"><br><br>

        <label>Email (optional)</label><br>
        <input type="email" name="email" value="<?= h($form['email']) ?>"><br><br>

        <?php if (!$editResident): ?>
            <label>Temporary password</label><br>
            <input type="text" name="password" required placeholder="Set a temp password for first login"><br><br>
        <?php else: ?>
            <label>Reset password (optional)</label><br>
            <input type="text" name="password" placeholder="Leave blank to keep current password"><br><br>
        <?php endif; ?>

        <label>Room number</label><br>
        <input type="text" name="room_number" value="<?= h($form['room_number']) ?>"><br><br>

        <label>Date of birth (optional, YYYY-MM-DD)</label><br>
        <input type="text" name="dob" value="<?= h($form['dob']) ?>" placeholder="1940-05-12"><br><br>

        <?php if ($editResident): ?>
            <button type="submit" name="edit_resident">Update Resident</button>
            <a href="<?= h(url_for('residents.php')) ?>" style="margin-left: 1em;">Cancel</a>
        <?php else: ?>
            <button type="submit" name="add_resident">Add Resident</button>
        <?php endif; ?>
    </form>

    <!-- Relationship summary (only in edit mode) -->
    <?php if ($editResident): ?>
        <hr style="margin:2em 0;">
        <h3 id="relationships">Relationships (summary)</h3>

        <p style="margin:0 0 10px;">
            <a href="<?= h(url_for('relationships.php', array('view'=>'resident','id'=>(int)$editResident['id']))) ?>">
                Open full relationships manager
            </a>
        </p>

        <?php if (empty($residentLinks)): ?>
            <p>No relationships set.</p>
        <?php else: ?>
            <table border="1" cellpadding="8" style="width:100%; border-collapse:collapse;">
                <tr>
                    <th>Visitor</th>
                    <th>Relationship</th>
                    <th>Primary</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Notes</th>
                </tr>
                <?php foreach ($residentLinks as $lnk): ?>
                    <tr>
                        <td><?= h($lnk['visitor_name']); ?></td>
                        <td><?= h($lnk['relationship_name']); ?></td>
                        <td><?= ((int)$lnk['is_primary_contact']) ? 'Yes' : 'No'; ?></td>
                        <td><?= h((string)$lnk['start_date']); ?></td>
                        <td><?= h((string)$lnk['end_date']); ?></td>
                        <td><?= h((string)$lnk['notes']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <hr style="margin:2em 0;">
    <h2>Resident List</h2>
    <table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse:collapse;">
        <tr style="background:#f8f8f8;">
            <th>Username</th>
            <th>Full Name</th>
            <th>Room</th>
            <th>DOB</th>
            <th>Relationships</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($residents as $r): ?>
            <?php $relCount = countLinksForResident($pdo, (int)$r['id']); ?>
            <tr>
                <td><?= h($r['username']); ?></td>
                <td><?= h($r['full_name']); ?></td>
                <td><?= h($r['room_number']); ?></td>
                <td><?= h($r['dob']); ?></td>
                <td>
                    <?= (int)$relCount ?>
                    <?php echo ($relCount === 1) ? 'link' : 'links'; ?>
                    &nbsp;—&nbsp;
                    <a href="<?= h(url_for('relationships.php', array('view'=>'resident','id'=>(int)$r['id']))) ?>">View relationships</a>
                </td>
                <td>
                    <a href="<?= h(url_for('residents.php', array('edit'=>(int)$r['id']))) ?>">Edit</a> |
                    <a href="<?= h(url_for('residents.php', array('delete'=>(int)$r['id']))) ?>"
                       onclick="return confirm('Delete this resident user and profile?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</main>

<?php renderFooter(); ?>
