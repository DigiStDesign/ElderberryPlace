<?php

function getAllServices($pdo)
{
    $stmt = $pdo->query("SELECT * FROM services ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAssignedStaff($pdo, $serviceId)
{
    $stmt = $pdo->prepare("
        SELECT s.id, s.name 
        FROM staff s
        JOIN staff_assignments a ON s.id = a.staff_id
        WHERE a.service_id = ?
        ORDER BY s.name
    ");
    $stmt->execute([$serviceId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllCategories(PDO $pdo)
{
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function renderCategoryDropdown(PDO $pdo, $selectedId = null)
{
    $categories = getAllCategories($pdo);
    echo '<select name="category_id" required>';
    echo '<option value="">-- Select a category --</option>';
    foreach ($categories as $cat) {
        $selected = ($cat['id'] == $selectedId) ? 'selected' : '';
        echo '<option value="' . $cat['id'] . '" ' . $selected . '>' . htmlspecialchars($cat['name']) . '</option>';
    }
    echo '</select>';
}


function handleServiceCreation($pdo, $post)
{
    $errors = [];

    $name = isset($post['name']) ? trim($post['name']) : '';
    $category_id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : null;
    $description = isset($post['description']) ? trim($post['description']) : '';
    $duration_min = isset($post['duration_minutes_min']) ? (int) $post['duration_minutes_min'] : 0;
    $duration_max = isset($post['duration_minutes_max']) ? (int) $post['duration_minutes_max'] : 0;
    $freq_times = isset($post['freq_times']) ? $post['freq_times'] : '';
    $freq_period = isset($post['freq_period']) ? $post['freq_period'] : '';
    $frequency = trim($freq_times . ' ' . $freq_period);
    $frequency = trim($frequency); // just in case both parts are optional
    $cost = isset($post['cost']) ? (float) $post['cost'] : 0;
    $status = isset($post['status']) ? $post['status'] : 'Scheduled';

    if ($name === '')
        $errors[] = "Name is required.";
    if ($cost < 0)
        $errors[] = "Cost cannot be negative.";
    if ($duration_min > $duration_max)
        $errors[] = "Minimum duration cannot exceed maximum.";

    if (!empty($errors)) {
        return $errors;
    }

    $stmt = $pdo->prepare("
        INSERT INTO services 
        (name, category_id, description, duration_minutes_min, duration_minutes_max, frequency, cost, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $category_id, $description, $duration_min, $duration_max, $frequency, $cost, $status]);
    $service_id = $pdo->lastInsertId();

    if (isset($post['assigned_staff']) && is_array($post['assigned_staff'])) {
        $stmt = $pdo->prepare("INSERT INTO staff_assignments (service_id, staff_id) VALUES (?, ?)");
        foreach ($post['assigned_staff'] as $staffId) {
            $stmt->execute([$service_id, $staffId]);
        }
    }

    return [];
}

function updateService($pdo, $serviceId, $post)
{
    $errors = [];

    $name = isset($post['name']) ? trim($post['name']) : '';
    $category_id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : null;
    $description = isset($post['description']) ? trim($post['description']) : '';
    $duration_min = isset($post['duration_minutes_min']) ? (int) $post['duration_minutes_min'] : 0;
    $duration_max = isset($post['duration_minutes_max']) ? (int) $post['duration_minutes_max'] : 0;
    $freq_times = isset($post['freq_times']) ? $post['freq_times'] : '';
    $freq_period = isset($post['freq_period']) ? $post['freq_period'] : '';
    $frequency = trim($freq_times . ' ' . $freq_period);
    $frequency = trim($frequency); // just in case both parts are optional
    $cost = isset($post['cost']) ? (float) $post['cost'] : 0;
    $status = isset($post['status']) ? $post['status'] : 'Scheduled';

    if ($name === '')
        $errors[] = "Name is required.";
    if ($cost < 0)
        $errors[] = "Cost cannot be negative.";
    if ($duration_min > $duration_max)
        $errors[] = "Minimum duration cannot exceed maximum.";

    if (!empty($errors)) {
        return $errors;
    }

    $stmt = $pdo->prepare("
        UPDATE services SET 
            name=?, category_id=?, description=?, 
            duration_minutes_min=?, duration_minutes_max=?, 
            frequency=?, cost=?, status=?
        WHERE id = ?
    ");
    $stmt->execute([
        $name,
        $category_id,
        $description,
        $duration_min,
        $duration_max,
        $frequency,
        $cost,
        $status,
        $serviceId
    ]);

    $stmt = $pdo->prepare("DELETE FROM staff_assignments WHERE service_id = ?");
    $stmt->execute([$serviceId]);

    if (isset($post['assigned_staff']) && is_array($post['assigned_staff'])) {
        $stmt = $pdo->prepare("INSERT INTO staff_assignments (service_id, staff_id) VALUES (?, ?)");
        foreach ($post['assigned_staff'] as $staffId) {
            $stmt->execute([$serviceId, $staffId]);
        }
    }

    return [];
}

function getAllServicesWithStaff(PDO $pdo, $search = '') {
    $where = array();
    $params = array();

    if ($search !== '') {
        // Search in service name, category name, or assigned staff (username/full_name)
        $where[] = "(
            s.name LIKE ?
            OR c.name LIKE ?
            OR EXISTS (
                SELECT 1
                FROM staff_assignments sa
                JOIN users u2 ON u2.id = sa.staff_user_id
                WHERE sa.service_id = s.id
                  AND (u2.username LIKE ? OR u2.full_name LIKE ?)
            )
        )";
        $like = '%' . $search . '%';
        $params[] = $like; // s.name
        $params[] = $like; // c.name
        $params[] = $like; // u2.username
        $params[] = $like; // u2.full_name
    }

    $whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

    // We keep counts as subqueries so grouping isn't needed
    $sql = "
        SELECT
            s.id,
            s.name,
            s.description,
            s.cost,
            s.frequency,
            s.duration_minutes_min,
            s.duration_minutes_max,
            s.status,
            c.name AS category_name,

            -- how many scheduled sessions exist for this service
            (SELECT COUNT(*)
             FROM service_schedule ss
             WHERE ss.service_id = s.id) AS scheduled_count,

            -- how many unique residents are booked across all sessions for this service
            (SELECT COUNT(DISTINCT rs.resident_user_id)
             FROM service_schedule ss2
             JOIN resident_schedule rs ON rs.schedule_id = ss2.id
             WHERE ss2.service_id = s.id) AS resident_count

        FROM services s
        LEFT JOIN categories c ON c.id = s.category_id
        $whereSql
        ORDER BY s.name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function formatDuration($min, $max) {
    $min = (int)$min; $max = (int)$max;
    if ($min && $max && $min !== $max) return $min . '–' . $max . ' mins';
    if ($min) return $min . ' mins';
    if ($max) return $max . ' mins';
    return 'N/A';
}


function deleteServiceWithAssignments($pdo, $serviceId)
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("DELETE FROM staff_assignments WHERE service_id = ?");
        $stmt->execute([$serviceId]);

        $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
        $stmt->execute([$serviceId]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return $e;
    }
}

function renderFrequencyDropdown($namePrefix, $selectedTimes = '', $selectedPeriod = '')
{
    $timesOptions = ['', 'Once', 'Twice', '3x', '4x', '5x'];
    $periodOptions = ['Daily', 'Weekly', 'Fortnightly', 'Monthly', 'As Prescribed'];

    echo '<select name="' . htmlspecialchars($namePrefix . '_times') . '">';
    echo '<option value="">--</option>'; // Optional first field
    foreach ($timesOptions as $option) {
        if ($option === '')
            continue;
        $selected = ($option === $selectedTimes) ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($option) . '"' . $selected . '>' . ucfirst($option) . '</option>';
    }
    echo '</select> ';

    echo '<select name="' . htmlspecialchars($namePrefix . '_period') . '">';
    foreach ($periodOptions as $option) {
        $selected = ($option === $selectedPeriod) ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($option) . '"' . $selected . '>' . ucfirst($option) . '</option>';
    }
    echo '</select>';
}


function renderDropdownFromTable($pdo, $table, $name, $selectedValues = [], $idField = 'id', $labelField = 'name', $multiple = true, $size = 5)
{
    $stmt = $pdo->query("SELECT `$idField`, `$labelField` FROM `$table` ORDER BY `$labelField` ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $multipleAttr = $multiple ? ' multiple' : '';
    $sizeAttr = $multiple ? ' size="' . (int) $size . '"' : '';

    echo '<select name="' . htmlspecialchars($name) . '"' . $multipleAttr . $sizeAttr . '>';
    foreach ($rows as $row) {
        $value = $row[$idField];
        $label = $row[$labelField];
        $selected = in_array($value, $selectedValues) ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
    echo '</select>';
}


function render_service_actions($serviceId, $role)
{
    $html = '<div class="actions" style="margin-top:8px;">';

    if ($role === 'ADMIN') {
        $html .= action_button('edit_service.php?id=' . intval($serviceId), 'Edit');
        $html .= action_button('schedule_staff.php?service_id=' . intval($serviceId), 'Schedule this service (Staff)');
    }

    if ($role === 'RESIDENT') {
        $html .= action_button('schedule_resident.php?service_id=' . intval($serviceId), 'Book this service');
    }

    // Uncomment if STAFF also schedules:
    // if ($role === 'STAFF') {
    //     $html .= action_button('schedule_staff.php?service_id=' . intval($serviceId), 'Schedule this service (Staff)');
    // }

    $html .= '</div>';
    return $html;
}

function action_button($href, $label)
{
    $h = htmlspecialchars($href);
    $l = htmlspecialchars($label);
    return '<a href="' . $h . '" style="
        display:inline-block;
        margin:4px 4px 0 0;
        padding:8px 12px;
        background:#2a7;
        color:#fff;
        text-decoration:none;
        border-radius:5px;
        font-size:14px;
    ">' . $l . '</a>';
}
