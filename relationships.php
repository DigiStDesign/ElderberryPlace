<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';

renderHeader('Visitor–Resident Relationships');

/* ------------------ Helpers ------------------ */
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function app_base_path() {
    $dir = rtrim(str_replace('\\','/', dirname($_SERVER['PHP_SELF'])), '/');
    return $dir === '' ? '/' : $dir;
}
function url_for($path, array $params = array()) {
    $url = rtrim(app_base_path(), '/') . '/' . ltrim($path, '/');
    if (!empty($params)) {
        $q = http_build_query($params);
        $url .= (strpos($url,'?') === false ? '?' : '&') . $q;
    }
    return $url;
}

/* ------------------ Data access ------------------ */
function getVisitors(PDO $pdo){
    $sql = "SELECT id, full_name FROM users WHERE role='VISITOR' AND is_active=1 ORDER BY full_name";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
function getResidents(PDO $pdo){
    $sql = "SELECT id, full_name FROM users WHERE role='RESIDENT' AND is_active=1 ORDER BY full_name";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
function getRelTypes(PDO $pdo){
    return $pdo->query("SELECT id, name FROM relationship_types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}
function linksForVisitor(PDO $pdo, $visitorId){
    $sql = "SELECT vrl.id, u.full_name AS resident_name, vrl.resident_user_id, rt.name AS rel_name,
                   vrl.is_primary_contact, vrl.start_date, vrl.end_date, vrl.notes
            FROM visitor_resident_links vrl
            JOIN users u ON u.id = vrl.resident_user_id
            JOIN relationship_types rt ON rt.id = vrl.relationship_type_id
            WHERE vrl.visitor_user_id = :vid
            ORDER BY u.full_name";
    $st = $pdo->prepare($sql);
    $st->execute(array(':vid'=>(int)$visitorId));
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
function linksForResident(PDO $pdo, $residentId){
    $sql = "SELECT vrl.id, u.full_name AS visitor_name, vrl.visitor_user_id, rt.name AS rel_name,
                   vrl.is_primary_contact, vrl.start_date, vrl.end_date, vrl.notes
            FROM visitor_resident_links vrl
            JOIN users u ON u.id = vrl.visitor_user_id
            JOIN relationship_types rt ON rt.id = vrl.relationship_type_id
            WHERE vrl.resident_user_id = :rid
            ORDER BY u.full_name";
    $st = $pdo->prepare($sql);
    $st->execute(array(':rid'=>(int)$residentId));
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
function addLink(PDO $pdo, $visitorId, $residentId, $relTypeId, $isPrimary, $startDate, $endDate, $notes){
    $sql = "INSERT INTO visitor_resident_links
            (visitor_user_id, resident_user_id, relationship_type_id, is_primary_contact, start_date, end_date, notes)
            VALUES (:v,:r,:t,:p,:sd,:ed,:n)";
    $st = $pdo->prepare($sql);
    $st->execute(array(
        ':v'=>(int)$visitorId, ':r'=>(int)$residentId, ':t'=>(int)$relTypeId,
        ':p'=>(int)$isPrimary,
        ':sd'=>($startDate!==''?$startDate:null),
        ':ed'=>($endDate!==''?$endDate:null),
        ':n'=>($notes!==''?$notes:null)
    ));
}
function deleteLink(PDO $pdo, $id){
    $st = $pdo->prepare("DELETE FROM visitor_resident_links WHERE id=:id");
    $st->execute(array(':id'=>(int)$id));
}

/* ------------------ Actions ------------------ */
$messages = array();
$errors   = array();

if (isset($_POST['create_link'])) {
    try {
        addLink(
            $pdo,
            isset($_POST['visitor_user_id']) ? (int)$_POST['visitor_user_id'] : 0,
            isset($_POST['resident_user_id']) ? (int)$_POST['resident_user_id'] : 0,
            isset($_POST['relationship_type_id']) ? (int)$_POST['relationship_type_id'] : 0,
            isset($_POST['is_primary_contact']) ? 1 : 0,
            isset($_POST['start_date']) ? trim($_POST['start_date']) : '',
            isset($_POST['end_date']) ? trim($_POST['end_date']) : '',
            isset($_POST['notes']) ? trim($_POST['notes']) : ''
        );
        $messages[] = "Relationship created.";
        // redirect to keep things tidy (and to avoid resubmits)
        $params = array(
            'view' => isset($_POST['after_view']) ? $_POST['after_view'] : 'visitor',
            'id'   => isset($_POST['after_id']) ? (int)$_POST['after_id'] : 0
        );
        header("Location: " . url_for('relationships.php', $params));
        exit;
    } catch (Exception $e) {
        $errors[] = "Create failed: " . h($e->getMessage());
    }
}

if (isset($_GET['delete_link'])) {
    try {
        deleteLink($pdo, (int)$_GET['delete_link']);
        $messages[] = "Relationship removed.";
        header("Location: " . url_for('relationships.php', array(
            'view'=> isset($_GET['view']) ? $_GET['view'] : 'visitor',
            'id'  => isset($_GET['id']) ? (int)$_GET['id'] : 0
        )));
        exit;
    } catch (Exception $e) {
        $errors[] = "Delete failed: " . h($e->getMessage());
    }
}

/* ------------------ Load lists + current selection ------------------ */
$visitors = getVisitors($pdo);
$residents= getResidents($pdo);
$types    = getRelTypes($pdo);

$view   = isset($_GET['view']) && $_GET['view']==='resident' ? 'resident' : 'visitor';
$activeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$rows = array();
if ($view === 'visitor' && $activeId>0) {
    $rows = linksForVisitor($pdo, $activeId);
}
if ($view === 'resident' && $activeId>0) {
    $rows = linksForResident($pdo, $activeId);
}
?>
<main style="padding:2em; max-width:1100px; margin:0 auto;">
    <h2>Visitor–Resident Relationships</h2>

    <?php if (!empty($messages)): ?>
        <div style="background:#e8fff0;border:1px solid #b6e2c5;padding:8px;margin-bottom:10px;">
            <?php foreach($messages as $m) echo "<div>".h($m)."</div>"; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div style="background:#ffecec;border:1px solid #f5c2c7;padding:8px;margin-bottom:10px;">
            <?php foreach($errors as $e) echo "<div>".h($e)."</div>"; ?>
        </div>
    <?php endif; ?>

    <!-- Top filter: choose perspective + person -->
    <form method="get" style="display:flex;gap:10px;align-items:flex-end;margin-bottom:1em;">
        <div>
            <label>View by:</label><br>
            <select name="view" onchange="this.form.submit()">
                <option value="visitor" <?php echo $view==='visitor'?'selected':''; ?>>Visitor</option>
                <option value="resident" <?php echo $view==='resident'?'selected':''; ?>>Resident</option>
            </select>
        </div>
        <div>
            <label><?php echo $view==='visitor'?'Visitor':'Resident'; ?>:</label><br>
            <select name="id">
                <option value="0">-- Select --</option>
                <?php if ($view==='visitor'): ?>
                    <?php foreach ($visitors as $v): ?>
                        <option value="<?php echo (int)$v['id']; ?>" <?php echo $activeId===(int)$v['id']?'selected':''; ?>>
                            <?php echo h($v['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($residents as $r): ?>
                        <option value="<?php echo (int)$r['id']; ?>" <?php echo $activeId===(int)$r['id']?'selected':''; ?>>
                            <?php echo h($r['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <button type="submit">Load</button>
        <div style="margin-left:auto">
            <!-- Quick links back to people pages (optional) -->
            <a href="<?php echo h(url_for('visitors.php')); ?>">Visitors</a> |
            <a href="<?php echo h(url_for('residents.php')); ?>">Residents</a>
        </div>
    </form>

    <!-- List -->
    <?php if ($activeId === 0): ?>
        <p>Select a <?php echo $view==='visitor'?'visitor':'resident'; ?> to see relationships.</p>
    <?php else: ?>
        <?php if (empty($rows)): ?>
            <p>No relationships found.</p>
        <?php else: ?>
            <table border="1" cellpadding="8" style="width:100%; border-collapse:collapse;">
                <tr>
                    <?php if ($view==='visitor'): ?>
                        <th>Resident</th>
                    <?php else: ?>
                        <th>Visitor</th>
                    <?php endif; ?>
                    <th>Relationship</th>
                    <th>Primary</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <?php
                            echo h($view==='visitor' ? $row['resident_name'] : $row['visitor_name']);
                            ?>
                        </td>
                        <td><?php echo h($row['rel_name']); ?></td>
                        <td><?php echo ((int)$row['is_primary_contact']) ? 'Yes' : 'No'; ?></td>
                        <td><?php echo h((string)$row['start_date']); ?></td>
                        <td><?php echo h((string)$row['end_date']); ?></td>
                        <td><?php echo h((string)$row['notes']); ?></td>
                        <td>
                            <a href="<?php
                                echo h(url_for('relationships.php', array(
                                    'view'=>$view, 'id'=>$activeId, 'delete_link'=>(int)$row['id']
                                )));
                            ?>" onclick="return confirm('Remove this relationship?');">Remove</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <!-- Add new relationship -->
        <h3 style="margin-top:1.5em;">Add Relationship</h3>
        <form method="post" style="background:#f9f9f9;padding:12px;border-radius:6px;">
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <div>
                    <label>Visitor:</label><br>
                    <select name="visitor_user_id" required>
                        <option value="">-- Visitor --</option>
                        <?php foreach ($visitors as $v): ?>
                            <option value="<?php echo (int)$v['id']; ?>"
                                <?php echo ($view==='visitor' && $activeId===(int)$v['id'])?'selected':''; ?>>
                                <?php echo h($v['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Resident:</label><br>
                    <select name="resident_user_id" required>
                        <option value="">-- Resident --</option>
                        <?php foreach ($residents as $r): ?>
                            <option value="<?php echo (int)$r['id']; ?>"
                                <?php echo ($view==='resident' && $activeId===(int)$r['id'])?'selected':''; ?>>
                                <?php echo h($r['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Relation to Resident:</label><br>
                    <select name="relationship_type_id" required>
                        <option value="">-- Type --</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?php echo (int)$t['id']; ?>"><?php echo h($t['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Primary?</label><br>
                    <input type="checkbox" name="is_primary_contact" value="1">
                </div>
                <div>
                    <label>Start:</label><br>
                    <input type="date" name="start_date">
                </div>
                <div>
                    <label>End:</label><br>
                    <input type="date" name="end_date">
                </div>
            </div>
            <div style="margin-top:10px;">
                <label>Notes:</label><br>
                <input type="text" name="notes" style="width:100%;" placeholder="Optional note">
            </div>

            <!-- keep context after add -->
            <input type="hidden" name="after_view" value="<?php echo h($view); ?>">
            <input type="hidden" name="after_id" value="<?php echo (int)$activeId; ?>">

            <div style="margin-top:10px;">
                <button type="submit" name="create_link">Create</button>
            </div>
        </form>
    <?php endif; ?>
</main>

<?php renderFooter(); ?>
