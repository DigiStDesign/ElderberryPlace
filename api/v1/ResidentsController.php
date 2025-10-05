<?php
// /api/v1/ResidentsController.php  (PHP 5.4)
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

/** GET /v1/residents */
/** GET /v1/residents?q=&page=1&limit=50 */
/** GET /v1/residents?q=&page=1&limit=50 */
function Residents_list() {
    // Allow STAFF and ADMIN to read residents
         require_role_api(array('ADMIN', 'STAFF'));

    $pdo   = api_db();
    $q     = isset($_GET['q']) ? trim($_GET['q']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    if ($limit < 1) $limit = 1; if ($limit > 100) $limit = 100;
    $page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    $where = array("u.role = 'RESIDENT'");
    $args  = array();

    if ($q !== '') {
        // tokenized search across name/username/email
        $parts = preg_split('/\s+/', $q);
        foreach ($parts as $p) {
            if ($p === '') continue;
            $where[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
            $like = '%'.$p.'%';
            $args[] = $like; $args[] = $like; $args[] = $like;
        }
    }

    $sqlWhere = $where ? (' WHERE '.implode(' AND ', $where)) : '';

    // total for paging
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM users u LEFT JOIN resident_profiles rp ON rp.user_id=u.id'.$sqlWhere);
    $cnt->execute($args);
    $total = (int)$cnt->fetchColumn();

    $sql = "SELECT
              u.id, u.username, u.full_name, u.email, u.is_active,
              rp.room_number, rp.dob
            FROM users u
            LEFT JOIN resident_profiles rp ON rp.user_id = u.id
            ".$sqlWhere."
            ORDER BY u.full_name ASC
            LIMIT ".$limit." OFFSET ".$offset;

    $st = $pdo->prepare($sql);
    $st->execute($args);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['is_active'] = (int)$r['is_active'];
    }

    json_ok(array('items' => $rows, 'page'=>$page, 'limit'=>$limit, 'total'=>$total));
}



/** POST /v1/residents */
function Residents_create() {
    require_admin_api();
    $b = read_json();

    $username    = isset($b['username']) ? trim($b['username']) : '';
    $full_name   = isset($b['full_name']) ? trim($b['full_name']) : '';
    $email       = isset($b['email']) && $b['email'] !== '' ? $b['email'] : null;
    $password    = isset($b['password']) ? (string)$b['password'] : '';
    $room_number = isset($b['room_number']) ? trim($b['room_number']) : null;
    $dob         = isset($b['dob']) && $b['dob'] !== '' ? $b['dob'] : null;

    if ($username === '' || $full_name === '' || $password === '') {
        json_err('VALIDATION', 'username, full_name, password required', 422);
    }

    $pdo = api_db();
    $pdo->beginTransaction();
    try {
        // users
        $ins = $pdo->prepare("INSERT INTO users (username, full_name, email, password_hash, role, is_active)
                              VALUES (?, ?, ?, ?, 'RESIDENT', 1)");
        $ins->execute(array($username, $full_name, $email, md5($password)));
        $uid = (int)$pdo->lastInsertId();

        // resident_profiles
        $rp = $pdo->prepare("INSERT INTO resident_profiles (user_id, dob, room_number, care_notes)
                             VALUES (?, ?, ?, NULL)");
        $rp->execute(array($uid, $dob, $room_number));

        $pdo->commit();
        json_ok(array('id' => $uid), 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        if (strpos($e->getMessage(), '1062') !== false) {
            json_err('DUPLICATE', 'Username already exists', 409);
        }
        json_err('DB', 'Insert failed: ' . $e->getMessage(), 500);
    }
}

/** PUT /v1/residents/{id} */
function Residents_update($id) {
    require_admin_api();
    $b = read_json();

    $username    = array_key_exists('username', $b)    ? trim($b['username'])    : null;
    $full_name   = array_key_exists('full_name', $b)   ? trim($b['full_name'])   : null;
    $email       = array_key_exists('email', $b)       ? ($b['email'] !== '' ? $b['email'] : null) : null;
    $password    = array_key_exists('password', $b)    ? (string)$b['password']  : null;
    $room_number = array_key_exists('room_number', $b) ? trim($b['room_number']) : null;
    $dob         = array_key_exists('dob', $b)         ? ($b['dob'] !== '' ? $b['dob'] : null) : null;

    $pdo = api_db();
    $pdo->beginTransaction();
    try {
        // update users
        $parts = array(); $params = array();
        if ($username !== null)    { $parts[] = "username=?";      $params[] = $username; }
        if ($full_name !== null)   { $parts[] = "full_name=?";     $params[] = $full_name; }
        if ($email !== null || array_key_exists('email',$b))
                                    { $parts[] = "email=?";        $params[] = $email; }
        if ($password !== null && $password !== '')
                                    { $parts[] = "password_hash=?";$params[] = md5($password); }
        if (!empty($parts)) {
            $params[] = $id;
            $sql = "UPDATE users SET ".implode(',', $parts)." WHERE id=? AND role='RESIDENT'";
            $st  = $pdo->prepare($sql);
            $st->execute($params);
        }

        // upsert resident_profiles
        $ex = $pdo->prepare("SELECT 1 FROM resident_profiles WHERE user_id=?");
        $ex->execute(array($id));
        if ($ex->fetchColumn()) {
            $rp_parts = array(); $rp_params = array();
            if (array_key_exists('dob',$b))         { $rp_parts[]="dob=?";         $rp_params[]=$dob; }
            if (array_key_exists('room_number',$b)) { $rp_parts[]="room_number=?"; $rp_params[]=$room_number; }
            if (!empty($rp_parts)) {
                $rp_params[] = $id;
                $rp = $pdo->prepare("UPDATE resident_profiles SET ".implode(',', $rp_parts)." WHERE user_id=?");
                $rp->execute($rp_params);
            }
        } else {
            $rp = $pdo->prepare("INSERT INTO resident_profiles (user_id, dob, room_number, care_notes)
                                 VALUES (?, ?, ?, NULL)");
            $rp->execute(array($id, $dob, $room_number));
        }

        $pdo->commit();
        json_ok(array('id' => (int)$id));
    } catch (Exception $e) {
        $pdo->rollBack();
        json_err('DB', 'Update failed: ' . $e->getMessage(), 500);
    }
}

/** DELETE /v1/residents/{id} */
function Residents_delete($id) {
    require_admin_api();
    $pdo = api_db();
    $st  = $pdo->prepare("DELETE FROM users WHERE id=? AND role='RESIDENT'");
    $st->execute(array($id));
    json_ok(array('id' => (int)$id));
}
