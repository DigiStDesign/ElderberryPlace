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
function getAllServicesWithStaff($pdo, $searchTerm = '')
{
    $params = [];
    $sql = "
        SELECT s.*, c.name AS category_name
        FROM services s
        JOIN categories c ON s.category_id = c.id
    ";

    if ($searchTerm !== '') {
        $sql .= "
            WHERE s.name LIKE :search
               OR c.name LIKE :search
               OR s.id IN (
                   SELECT a.service_id
                   FROM staff_assignments a
                   JOIN staff st ON a.staff_id = st.id
                   WHERE st.name LIKE :search
               )
        ";
        $params['search'] = '%' . $searchTerm . '%';
    }

    $sql .= " ORDER BY s.name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Reuse prepared statements for efficiency
    $staffStmt = $pdo->prepare("
        SELECT s.name
        FROM staff s
        JOIN staff_assignments a ON s.id = a.staff_id
        WHERE a.service_id = ?
    ");

    $scheduleStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM service_schedule sc
        JOIN staff_schedule ss ON ss.schedule_id = sc.id
        WHERE sc.service_id = ?
    ");

    // Add new prepared statement
    $residentCountStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM resident_schedule rs
        JOIN service_schedule ss ON rs.schedule_id = ss.id
        WHERE ss.service_id = ?
    ");

    foreach ($services as &$service) {
        $staffStmt->execute([$service['id']]);
        $service['assigned_staff'] = $staffStmt->fetchAll(PDO::FETCH_COLUMN);

        $scheduleStmt->execute([$service['id']]);
        $service['scheduled_count'] = (int)$scheduleStmt->fetchColumn();

        $residentCountStmt->execute([$service['id']]);
        $service['resident_count'] = (int)$residentCountStmt->fetchColumn();
    }

    unset($service);
    return $services;
}

function formatDuration($min, $max)
{
    return ($min === $max) ? "$min minutes" : "$min-$max minutes";
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
