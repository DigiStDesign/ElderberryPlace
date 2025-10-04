<?php
// /api/v1/CategoriesController.php (PHP 5.4)
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

/** GET /v1/categories */
function Cat_list() {
    require_admin_api();
    $pdo = api_db();
    $sql = "
        SELECT c.id, c.name, COUNT(s.id) AS service_count
        FROM categories c
        LEFT JOIN services s ON s.category_id = c.id
        GROUP BY c.id, c.name
        ORDER BY c.name
    ";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['service_count'] = (int)$r['service_count'];
    }
    json_ok($rows);
}

/** POST /v1/categories {name} */
function Cat_create() {
    require_admin_api(); require_csrf_api();
    $b = read_json();
    $name = isset($b['name']) ? trim($b['name']) : '';
    if ($name === '') json_err('VALIDATION','name required',422);

    $pdo = api_db();
    try {
        $st = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        $st->execute(array($name));
        json_ok(array('id'=>(int)$pdo->lastInsertId()),201);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), '1062') !== false) {
            json_err('DUPLICATE','Category name already exists',409);
        }
        json_err('DB','Insert failed: '.$e->getMessage(),500);
    }
}

/** PUT /v1/categories/{id} {name} */
function Cat_update($id) {
    require_admin_api(); require_csrf_api();
    $b = read_json();
    $name = isset($b['name']) ? trim($b['name']) : '';
    if ($name === '') json_err('VALIDATION','name required',422);

    $pdo = api_db();
    try {
        $st = $pdo->prepare("UPDATE categories SET name=? WHERE id=?");
        $st->execute(array($name,(int)$id));
        json_ok(array('id'=>(int)$id));
    } catch (Exception $e) {
        if (strpos($e->getMessage(), '1062') !== false) {
            json_err('DUPLICATE','Category name already exists',409);
        }
        json_err('DB','Update failed: '.$e->getMessage(),500);
    }
}

/** DELETE /v1/categories/{id} */
function Cat_delete($id) {
    require_admin_api(); require_csrf_api();
    $pdo = api_db();

    // forbid delete if in use
    $ck = $pdo->prepare("SELECT COUNT(*) FROM services WHERE category_id=?");
    $ck->execute(array((int)$id));
    if ((int)$ck->fetchColumn() > 0) {
        json_err('IN_USE','Category has services and cannot be deleted',409);
    }

    $st = $pdo->prepare("DELETE FROM categories WHERE id=?");
    $st->execute(array((int)$id));
    json_ok(array('deleted'=>($st->rowCount()>0)));
}
