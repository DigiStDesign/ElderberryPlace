<?php
// /api/v1/ServicesController.php  (PHP 5.4)
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

/** GET /v1/services */
function Services_list() {
    // Viewing services could be public. If you prefer to lock it: require_admin_api();
    $pdo = api_db();
    $sql = "SELECT
                s.id,
                s.name,
                s.category_id,
                c.name AS category_name,
                s.description,
                s.duration_minutes_min,
                s.duration_minutes_max,
                s.frequency,
                s.cost,
                s.status
            FROM services s
            JOIN categories c ON c.id = s.category_id
            ORDER BY s.name";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    // Normalize types a bit for frontend
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['category_id'] = (int)$r['category_id'];
        if (isset($r['duration_minutes_min']) && $r['duration_minutes_min'] !== null) {
            $r['duration_minutes_min'] = (int)$r['duration_minutes_min'];
        }
        if (isset($r['duration_minutes_max']) && $r['duration_minutes_max'] !== null) {
            $r['duration_minutes_max'] = (int)$r['duration_minutes_max'];
        }
        // cost can be null or decimal-as-string; leave as-is for display
    }
    json_ok($rows);
}

/** (optional) GET /v1/services/{id} */
function Services_get($id) {
    $pdo = api_db();
    $st = $pdo->prepare("SELECT
                s.id,
                s.name,
                s.category_id,
                c.name AS category_name,
                s.description,
                s.duration_minutes_min,
                s.duration_minutes_max,
                s.frequency,
                s.cost,
                s.status
            FROM services s
            JOIN categories c ON c.id = s.category_id
            WHERE s.id = ?
            LIMIT 1");
    $st->execute(array((int)$id));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_err('NOT_FOUND','Service not found',404);
    $row['id'] = (int)$row['id'];
    $row['category_id'] = (int)$row['category_id'];
    if (isset($row['duration_minutes_min']) && $row['duration_minutes_min'] !== null) {
        $row['duration_minutes_min'] = (int)$row['duration_minutes_min'];
    }
    if (isset($row['duration_minutes_max']) && $row['duration_minutes_max'] !== null) {
        $row['duration_minutes_max'] = (int)$row['duration_minutes_max'];
    }
    json_ok($row);
}

/** POST /v1/services */
function Services_create() {
    require_admin_api();
    require_csrf_api();

    $b = read_json();

    $name   = isset($b['name']) ? trim($b['name']) : '';
    $catId  = isset($b['category_id']) ? (int)$b['category_id'] : 0;
    $status = isset($b['status']) ? (string)$b['status'] : 'Inactive';

    if ($name === '' || !$catId) {
        json_err('VALIDATION','name and category_id are required',422);
    }

    // Optional fields
    $desc   = isset($b['description']) && $b['description'] !== '' ? $b['description'] : null;
    $durMin = isset($b['duration_minutes_min']) && $b['duration_minutes_min'] !== '' ? (int)$b['duration_minutes_min'] : null;
    $durMax = isset($b['duration_minutes_max']) && $b['duration_minutes_max'] !== '' ? (int)$b['duration_minutes_max'] : null;
    $freq   = isset($b['frequency']) && $b['frequency'] !== '' ? $b['frequency'] : null;

    // cost can be null; accept string like "75.00"
    $cost   = null;
    if (array_key_exists('cost', $b) && $b['cost'] !== '' && $b['cost'] !== null) {
        $cost = (string)$b['cost'];
    }

    // Status must be one of: Active, Scheduled, Inactive
    if (!in_array($status, array('Active','Scheduled','Inactive'))) {
        $status = 'Inactive';
    }

    $pdo = api_db();
    $st = $pdo->prepare("INSERT INTO services
        (name, category_id, description, duration_minutes_min, duration_minutes_max, frequency, cost, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $st->execute(array($name, $catId, $desc, $durMin, $durMax, $freq, $cost, $status));

    json_ok(array('id' => (int)$pdo->lastInsertId()), 201);
}

/** PUT /v1/services/{id} */
function Services_update($id) {
    require_admin_api();
    require_csrf_api();

    $b = read_json();
    $id = (int)$id;

    // Build dynamic SET parts
    $parts = array(); $params = array();

    if (isset($b['name']))                     { $parts[] = "name=?";                    $params[] = trim($b['name']); }
    if (isset($b['category_id']))              { $parts[] = "category_id=?";             $params[] = (int)$b['category_id']; }
    if (array_key_exists('description', $b))   { $parts[] = "description=?";             $params[] = ($b['description'] !== '' ? $b['description'] : null); }
    if (array_key_exists('duration_minutes_min',$b)) { $parts[] = "duration_minutes_min=?"; $params[] = ($b['duration_minutes_min'] !== '' ? (int)$b['duration_minutes_min'] : null); }
    if (array_key_exists('duration_minutes_max',$b)) { $parts[] = "duration_minutes_max=?"; $params[] = ($b['duration_minutes_max'] !== '' ? (int)$b['duration_minutes_max'] : null); }
    if (array_key_exists('frequency', $b))     { $parts[] = "frequency=?";               $params[] = ($b['frequency'] !== '' ? $b['frequency'] : null); }
    if (array_key_exists('cost', $b))          { $parts[] = "cost=?";                    $params[] = ($b['cost'] !== '' && $b['cost'] !== null ? (string)$b['cost'] : null); }
    if (isset($b['status'])) {
        $st = (string)$b['status'];
        if (!in_array($st, array('Active','Scheduled','Inactive'))) $st = 'Inactive';
        $parts[] = "status=?"; $params[] = $st;
    }

    if (empty($parts)) json_ok(array('id'=>$id)); // nothing to update

    $params[] = $id;
    $sql = "UPDATE services SET " . implode(',', $parts) . " WHERE id=?";
    $pdo = api_db();
    $up  = $pdo->prepare($sql);
    $up->execute($params);

    json_ok(array('id'=>$id));
}

/** DELETE /v1/services/{id} */
function Services_delete($id) {
    require_admin_api();
    require_csrf_api();

    $pdo = api_db();
    $st  = $pdo->prepare("DELETE FROM services WHERE id=?");
    $st->execute(array((int)$id));
    json_ok(array('id'=>(int)$id));
}
