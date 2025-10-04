<?php
// /api/v1/VisitorsController.php
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

function Visitors_list() {
    require_role_api(array('ADMIN','STAFF'));
    $pdo = api_db();
    $st = $pdo->query("
        SELECT u.id, u.username, u.full_name, u.email, u.is_active, vp.phone
        FROM users u
        LEFT JOIN visitor_profiles vp ON vp.user_id = u.id
        WHERE u.role = 'VISITOR'
        ORDER BY u.full_name
    ");
    json_ok($st->fetchAll(PDO::FETCH_ASSOC)); // return raw array (not {items:[]})
}

function Visitors_get($id) {
    require_role_api(array('ADMIN','STAFF'));
    $pdo = api_db();
    $st = $pdo->prepare("
        SELECT u.id, u.username, u.full_name, u.email, u.is_active, vp.phone
        FROM users u
        LEFT JOIN visitor_profiles vp ON vp.user_id = u.id
        WHERE u.id = ? AND u.role = 'VISITOR' LIMIT 1
    ");
    $st->execute(array((int)$id));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_err('NOT_FOUND','Visitor not found',404);
    json_ok($row);
}

function Visitors_create() {
    require_role_api(array('ADMIN','STAFF')); require_csrf_api();
    $b = read_json();
    $pdo = api_db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("
            INSERT INTO users (username, full_name, email, password_hash, role, is_active)
            VALUES (?, ?, ?, ?, 'VISITOR', ?)
        ");
        $email = isset($b['email']) && $b['email'] !== '' ? $b['email'] : null;
        $st->execute(array(
            trim($b['username']),
            trim($b['full_name']),
            $email,
            md5($b['password']),             // demo parity
            (int)$b['is_active']
        ));
        $uid = (int)$pdo->lastInsertId();

        if (!empty($b['phone'])) {
            $px = $pdo->prepare("INSERT INTO visitor_profiles (user_id, phone) VALUES (?,?)");
            $px->execute(array($uid, trim($b['phone'])));
        }
        $pdo->commit();
        json_ok(array('id'=>$uid), 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        json_err('DB', $e->getMessage(), 500);
    }
}

function Visitors_update($id) {
    require_role_api(array('ADMIN','STAFF')); require_csrf_api();
    $b = read_json();
    $pdo = api_db();
    $pdo->beginTransaction();
    try {
        $sets = array(); $args = array();
        if (array_key_exists('username',$b))   { $sets[]="username=?";       $args[] = trim($b['username']); }
        if (array_key_exists('full_name',$b))  { $sets[]="full_name=?";      $args[] = trim($b['full_name']); }
        if (array_key_exists('email',$b))      { $sets[]="email=?";          $args[] = ($b['email']!==''?$b['email']:null); }
        if (array_key_exists('is_active',$b))  { $sets[]="is_active=?";      $args[] = (int)$b['is_active']; }
        if (!empty($b['password']))            { $sets[]="password_hash=?";  $args[] = md5($b['password']); }

        if (!empty($sets)) {
            $sql = "UPDATE users SET ".implode(',', $sets)." WHERE id=? AND role='VISITOR'";
            $args[] = (int)$id;
            $pdo->prepare($sql)->execute($args);
        }

        // upsert phone
        $ck = $pdo->prepare("SELECT 1 FROM visitor_profiles WHERE user_id=?");
        $ck->execute(array((int)$id));
        if ($ck->fetchColumn()) {
            $pdo->prepare("UPDATE visitor_profiles SET phone=? WHERE user_id=?")
                ->execute(array(isset($b['phone']) ? ($b['phone']!==''?$b['phone']:null) : null, (int)$id));
        } else if (!empty($b['phone'])) {
            $pdo->prepare("INSERT INTO visitor_profiles (user_id, phone) VALUES (?,?)")
                ->execute(array((int)$id, trim($b['phone'])));
        }

        $pdo->commit();
        json_ok(array('ok'=>true));
    } catch (Exception $e) {
        $pdo->rollBack();
        json_err('DB', $e->getMessage(), 500);
    }
}

function Visitors_delete($id) {
    require_role_api(array('ADMIN','STAFF')); require_csrf_api();
    $pdo = api_db();
    $st  = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'VISITOR'");
    $st->execute(array((int)$id));
    json_ok(array('deleted' => $st->rowCount() > 0));
}
