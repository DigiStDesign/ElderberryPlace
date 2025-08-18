<?php
// /api/v1/StaffController.php  (PHP 5.4)
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

/** GET /v1/staff/jobs */
function Staff_jobs() {
    require_admin_api();
    $pdo  = api_db();
    $rows = $pdo->query("SELECT id, name FROM staff_jobs ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    json_ok($rows);
}

/** GET /v1/staff */
function Staff_list() {
    require_admin_api();
    $pdo = api_db();
    $sql = "SELECT
                u.id, u.username, u.full_name, u.email, u.is_active,
                sp.staff_job_id, j.name AS job_name, sp.started_on, sp.notes
            FROM users u
            LEFT JOIN staff_profiles sp ON sp.user_id = u.id
            LEFT JOIN staff_jobs j      ON j.id = sp.staff_job_id
            WHERE u.role = 'STAFF'
            ORDER BY u.full_name";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['is_active'] = (int)$r['is_active'];
        if (isset($r['staff_job_id'])) $r['staff_job_id'] = (int)$r['staff_job_id'];
    }
    json_ok($rows);
}

/** POST /v1/staff */
function Staff_create() {
    require_admin_api();
    $b = read_json();
    $username     = isset($b['username']) ? trim($b['username']) : '';
    $full_name    = isset($b['full_name']) ? trim($b['full_name']) : '';
    $email        = (isset($b['email']) && $b['email'] !== '') ? $b['email'] : null;
    $password     = isset($b['password']) ? (string)$b['password'] : '';
    $is_active    = isset($b['is_active']) ? (int)$b['is_active'] : 1;
    $staff_job_id = isset($b['staff_job_id']) ? (int)$b['staff_job_id'] : null;
    $started_on   = (isset($b['started_on']) && $b['started_on'] !== '') ? $b['started_on'] : null;
    $notes        = (isset($b['notes']) && $b['notes'] !== '') ? $b['notes'] : null;

    if ($username === '' || $full_name === '' || $password === '') {
        json_err('VALIDATION', 'username, full_name, password required', 422);
    }

    $pdo = api_db();
    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare("INSERT INTO users (username, full_name, email, password_hash, role, is_active)
                              VALUES (?, ?, ?, ?, 'STAFF', ?)");
        $ins->execute(array($username, $full_name, $email, md5($password), $is_active));
        $uid = (int)$pdo->lastInsertId();

        if ($staff_job_id) {
            $sp = $pdo->prepare("INSERT INTO staff_profiles (user_id, staff_job_id, started_on, notes)
                                 VALUES (?, ?, ?, ?)");
            $sp->execute(array($uid, $staff_job_id, $started_on, $notes));
        }

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

/** PUT /v1/staff/{id} */
function Staff_update($id) {
    require_admin_api();
    $b = read_json();
    $username     = isset($b['username']) ? trim($b['username']) : null;
    $full_name    = isset($b['full_name']) ? trim($b['full_name']) : null;
    $email        = array_key_exists('email', $b) ? ($b['email'] !== '' ? $b['email'] : null) : null;
    $is_active    = isset($b['is_active']) ? (int)$b['is_active'] : null;
    $password     = isset($b['password']) ? (string)$b['password'] : null;
    $staff_job_id = array_key_exists('staff_job_id', $b) ? (int)$b['staff_job_id'] : null;
    $started_on   = array_key_exists('started_on', $b) ? ($b['started_on'] !== '' ? $b['started_on'] : null) : null;
    $notes        = array_key_exists('notes', $b) ? ($b['notes'] !== '' ? $b['notes'] : null) : null;

    $pdo = api_db();
    $pdo->beginTransaction();
    try {
        // users
        $parts = array(); $params = array();
        if ($username !== null)          { $parts[] = "username=?";      $params[] = $username; }
        if ($full_name !== null)         { $parts[] = "full_name=?";     $params[] = $full_name; }
        if ($email !== null || array_key_exists('email', $b))
                                         { $parts[] = "email=?";         $params[] = $email; }
        if ($is_active !== null)         { $parts[] = "is_active=?";     $params[] = $is_active; }
        if ($password !== null && $password !== '')
                                         { $parts[] = "password_hash=?"; $params[] = md5($password); }
        if (!empty($parts)) {
            $params[] = $id;
            $sql = "UPDATE users SET " . implode(',', $parts) . " WHERE id=? AND role='STAFF'";
            $st  = $pdo->prepare($sql);
            $st->execute($params);
        }

        // staff_profiles upsert
        $ex = $pdo->prepare("SELECT 1 FROM staff_profiles WHERE user_id=?");
        $ex->execute(array($id));
        if ($ex->fetchColumn()) {
            $sp_parts = array(); $sp_params = array();
            if ($staff_job_id)                 { $sp_parts[]="staff_job_id=?"; $sp_params[]=$staff_job_id; }
            if (array_key_exists('started_on',$b)) { $sp_parts[]="started_on=?";   $sp_params[]=$started_on; }
            if (array_key_exists('notes',$b))      { $sp_parts[]="notes=?";        $sp_params[]=$notes; }
            if (!empty($sp_parts)) {
                $sp_params[] = $id;
                $sp = $pdo->prepare("UPDATE staff_profiles SET ".implode(',', $sp_parts)." WHERE user_id=?");
                $sp->execute($sp_params);
            }
        } else {
            if ($staff_job_id) {
                $sp = $pdo->prepare("INSERT INTO staff_profiles (user_id, staff_job_id, started_on, notes)
                                     VALUES (?, ?, ?, ?)");
                $sp->execute(array($id, $staff_job_id, $started_on, $notes));
            }
        }

        $pdo->commit();
        json_ok(array('id' => (int)$id));
    } catch (Exception $e) {
        $pdo->rollBack();
        json_err('DB', 'Update failed: ' . $e->getMessage(), 500);
    }
}

/** DELETE /v1/staff/{id} */
function Staff_delete($id) {
    require_admin_api();
    $pdo = api_db();
    $st  = $pdo->prepare("DELETE FROM users WHERE id=? AND role='STAFF'");
    $st->execute(array($id));
    json_ok(array('id' => (int)$id));
}
