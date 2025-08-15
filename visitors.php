<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';

renderHeader("Manage Visitors");

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
   Data access (keep functions small and intention-revealing)
   ============================================================ */
function fetchAllVisitors(PDO $pdo) {
    $sql = "SELECT id, username, full_name, is_active
            FROM users
            WHERE role = 'VISITOR'
            ORDER BY full_name";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function fetchVisitor(PDO $pdo, $id) {
    $sql = "SELECT u.id, u.username, u.full_name, u.is_active, vp.phone, vp.verified
            FROM users u
            LEFT JOIN visitor_profiles vp ON vp.user_id = u.id
            WHERE u.id = :id AND u.role = 'VISITOR'";
    $st = $pdo->prepare($sql);
    $st->execute(array(':id' => (int)$id));
    return $st->fetch(PDO::FETCH_ASSOC);
}

function createVisitor(PDO $pdo, $username, $full_name, $password, $is_active, $phone) {
    // Using MD5 to match demo data in init_db.sql
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            "INSERT INTO users (username, full_name, email, password_hash, role, is_active)
             VALUES (:u, :n, NULL, :p, 'VISITOR', :a)"
        );
        $st->execute(array(
            ':u' => $username,
            ':n' => $full_name,
            ':p' => md5($password),
            ':a' => (int)$is_active
        ));
        $newId = (int)$pdo->lastInsertId();

        if (trim($phone) !== '') {
            $st2 = $pdo->prepare("INSERT INTO visitor_profiles (user_id, phone) VALUES (:id, :ph)");
            $st2->execute(array(':id' => $newId, ':ph' => $phone));
        }

        $pdo->commit();
        return $newId;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function updateVisitor(PDO $pdo, $id, $username, $full_name, $password, $is_active, $phone) {
    $pdo->beginTransaction();
    try {
        if ($password !== '') {
            $sql  = "UPDATE users
                     SET username = :u, full_name = :n, is_active = :a, password_hash = :p
                     WHERE id = :id AND role = 'VISITOR'";
            $args = array(':u'=>$username, ':n'=>$full_name, ':a'=>(int)$is_active, ':p'=>md5($password), ':id'=>(int)$id);
        } else {
            $sql  = "UPDATE users
                     SET username = :u, full_name = :n, is_active = :a
                     WHERE id = :id AND role = 'VISITOR'";
            $args = array(':u'=>$username, ':n'=>$full_name, ':a'=>(int)$is_active, ':id'=>(int)$id);
        }
        $st = $pdo->prepare($sql);
        $st->execute($args);

        // Upsert visitor_profiles.phone
        $chk = $pdo->prepare("SELECT user_id FROM visitor_profiles WHERE user_id = :id");
        $chk->execute(array(':id' => (int)$id));
        if ($chk->fetch()) {
            $up = $pdo->prepare("UPDATE visitor_profiles SET phone = :ph WHERE user_id = :id");
            $up->execute(array(':ph' => $phone, ':id' => (int)$id));
        } else {
            if (trim($phone) !== '') {
                $ins = $pdo->prepare("INSERT INTO visitor_profiles (user_id, phone) VALUES (:id, :ph)");
                $ins->execute(array(':id' => (int)$id, ':ph' => $phone));
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function deleteVisitor(PDO $pdo, $id) {
    $st = $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'VISITOR'");
    $st->execute(array(':id' => (int)$id));
}

/** Read-only summary of a visitor’s existing relationships (for display only) */
function fetchVisitorLinks(PDO $pdo, $visitorId) {
    $sql = "SELECT
                vrl.id,
                u.full_name AS resident_name,
                rt.name     AS relationship_name,
                vrl.is_primary_contact,
                vrl.start_date,
                vrl.end_date,
                vrl.notes
            FROM visitor_resident_links vrl
            JOIN users u ON u.id = vrl.resident_user_id
            JOIN relationship_types rt ON rt.id = vrl.relationship_type_id
            WHERE vrl.visitor_user_id = :vid
            ORDER BY u.full_name";
    $st = $pdo->prepare($sql);
    $st->execute(array(':vid' => (int)$visitorId));
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ============================================================
   Actions (CRUD for visitors only)
   ============================================================ */
$errors   = array();
$messages = array();

if (isset($_POST['add_visitor'])) {
    try {
        $newId = createVisitor(
            $pdo,
            isset($_POST['username']) ? trim($_POST['username']) : '',
            isset($_POST['full_name']) ? trim($_POST['full_name']) : '',
            isset($_POST['password']) ? (string)$_POST['password'] : '',
            isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1,
            isset($_POST['phone']) ? trim($_POST['phone']) : ''
        );
        $messages[] = "Visitor created (#" . (int)$newId . ").";
        header("Location: " . url_for('visitors.php', array('edit' => (int)$newId)));
        exit;
    } catch (Exception $e) {
        $errors[] = "Create failed: " . h($e->getMessage());
    }
}

if (isset($_POST['edit_visitor'])) {
    try {
        updateVisitor(
            $pdo,
            (int)$_POST['id'],
            isset($_POST['username']) ? trim($_POST['username']) : '',
            isset($_POST['full_name']) ? trim($_POST['full_name']) : '',
            isset($_POST['password']) ? (string)$_POST['password'] : '',
            isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1,
            isset($_POST['phone']) ? trim($_POST['phone']) : ''
        );
        $messages[] = "Visitor updated.";
        header("Location: " . url_for('visitors.php', array('edit' => (int)$_POST['id'])));
        exit;
    } catch (Exception $e) {
        $errors[] = "Update failed: " . h($e->getMessage());
    }
}

if (isset($_GET['delete'])) {
    try {
        deleteVisitor($pdo, (int)$_GET['delete']);
        $messages[] = "Visitor deleted.";
        header("Location: " . url_for('visitors.php'));
        exit;
    } catch (Exception $e) {
        $errors[] = "Delete failed: " . h($e->getMessage());
    }
}

/* ============================================================
   Data (for page render)
   ============================================================ */
$visitor_list = fetchAllVisitors($pdo);
$editVisitor  = null;
$visitorLinks = array();

if (isset($_GET['edit'])) {
    $editVisitor = fetchVisitor($pdo, (int)$_GET['edit']);
    if ($editVisitor) {
        $visitorLinks = fetchVisitorLinks($pdo, (int)$editVisitor['id']); // summary only
    }
}
?>
<main style="padding:2em; max-width:1000px; margin:0 auto;">
    <h2><?php echo $editVisitor ? "Edit Visitor" : "Add New Visitor"; ?></h2>

    <?php if (!empty($messages)): ?>
        <div style="background:#e8fff0;border:1px solid #b6e2c5;padding:8px;margin-bottom:10px;">
            <?php foreach ($messages as $m) echo "<div>".h($m)."</div>"; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div style="background:#ffecec;border:1px solid #f5c2c7;padding:8px;margin-bottom:10px;">
            <?php foreach ($errors as $e) echo "<div>".h($e)."</div>"; ?>
        </div>
    <?php endif; ?>

    <form method="post" style="margin-bottom:2em;">
        <input type="hidden" name="id" value="<?php echo $editVisitor ? (int)$editVisitor['id'] : ''; ?>">

        <label>Username:</label><br>
        <input type="text" name="username" required
               value="<?php echo $editVisitor ? h($editVisitor['username']) : ''; ?>"><br>

        <label>Full name:</label><br>
        <input type="text" name="full_name" required
               value="<?php echo $editVisitor ? h($editVisitor['full_name']) : ''; ?>"><br>

        <label>Phone (optional):</label><br>
        <input type="text" name="phone"
               value="<?php echo $editVisitor ? h((string)$editVisitor['phone']) : ''; ?>"><br>

        <label>Password <?php echo $editVisitor ? "(leave blank to keep current)" : ""; ?>:</label><br>
        <input type="password" name="password"><br>

        <label>Status:</label><br>
        <?php $isActive = $editVisitor ? (int)$editVisitor['is_active'] : 1; ?>
        <select name="is_active">
            <option value="1" <?php echo $isActive === 1 ? 'selected' : ''; ?>>Active</option>
            <option value="0" <?php echo $isActive === 0 ? 'selected' : ''; ?>>Inactive</option>
        </select><br><br>

        <?php if ($editVisitor): ?>
            <button type="submit" name="edit_visitor">Update Visitor</button>
            <a href="<?php echo h(url_for('visitors.php')); ?>" style="margin-left:1em;">Cancel</a>
        <?php else: ?>
            <button type="submit" name="add_visitor">Add Visitor</button>
        <?php endif; ?>
    </form>

    <?php if ($editVisitor): ?>
        <hr style="margin:2em 0;">
        <h3>Relationships (summary)</h3>

        <p style="margin:0 0 10px;">
            <a href="<?php echo h(url_for('relationships.php', array('view'=>'visitor','id'=>(int)$editVisitor['id']))); ?>">
                Open full relationships manager
            </a>
        </p>

        <?php if (empty($visitorLinks)): ?>
            <p>No relationships set.</p>
        <?php else: ?>
            <table border="1" cellpadding="8" style="width:100%; border-collapse:collapse;">
                <tr>
                    <th>Resident</th>
                    <th>Relationship</th>
                    <th>Primary</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Notes</th>
                </tr>
                <?php foreach ($visitorLinks as $lnk): ?>
                    <tr>
                        <td><?php echo h($lnk['resident_name']); ?></td>
                        <td><?php echo h($lnk['relationship_name']); ?></td>
                        <td><?php echo ((int)$lnk['is_primary_contact']) ? 'Yes' : 'No'; ?></td>
                        <td><?php echo h((string)$lnk['start_date']); ?></td>
                        <td><?php echo h((string)$lnk['end_date']); ?></td>
                        <td><?php echo h((string)$lnk['notes']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <hr style="margin:2em 0;">
    <h2>Visitors</h2>
    <table border="1" cellpadding="8" style="width:100%; border-collapse:collapse;">
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Full name</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($visitor_list as $v): ?>
            <tr>
                <td><?php echo (int)$v['id']; ?></td>
                <td><?php echo h($v['username']); ?></td>
                <td><?php echo h($v['full_name']); ?></td>
                <td><?php echo ((int)$v['is_active']) ? 'Active' : 'Inactive'; ?></td>
                <td>
                    <a href="<?php echo h(url_for('visitors.php', array('edit'=>(int)$v['id']))); ?>">Edit</a> |
                    <a href="<?php echo h(url_for('relationships.php', array('view'=>'visitor','id'=>(int)$v['id']))); ?>">
                        View relationships
                    </a> |
                    <a href="<?php echo h(url_for('visitors.php', array('delete'=>(int)$v['id']))); ?>"
                       onclick="return confirm('Delete this visitor?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</main>

<?php renderFooter(); ?>
