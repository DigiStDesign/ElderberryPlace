<?php
// /api/v1/ScheduleController.php  (PHP 5.4)
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

/** POST /v1/service-schedule  {service_id,start_time,end_time,location?,notes?} */
function Sched_add() {
    require_role_api(array('ADMIN','STAFF'));  // CSRF is enforced globally or per-page
    require_csrf_api();

    $b = read_json();
    $service_id = isset($b['service_id']) ? (int)$b['service_id'] : 0;
    $start_time = isset($b['start_time']) ? trim($b['start_time']) : '';
    $end_time   = isset($b['end_time'])   ? trim($b['end_time'])   : '';
    $location   = (isset($b['location']) && $b['location'] !== '') ? $b['location'] : null;
    $notes      = (isset($b['notes'])    && $b['notes']    !== '') ? $b['notes']    : null;

    if (!$service_id || $start_time === '' || $end_time === '') {
        json_err('VALIDATION','service_id, start_time, end_time required',422);
    }
    if (strtotime($end_time) <= strtotime($start_time)) {
        json_err('VALIDATION','end_time must be after start_time',422);
    }

    $pdo = api_db();
    $st = $pdo->prepare("INSERT INTO service_schedule (service_id,start_time,end_time,location,notes)
                         VALUES (?,?,?,?,?)");
    $st->execute(array($service_id, $start_time, $end_time, $location, $notes));
    json_ok(array('id' => (int)$pdo->lastInsertId()), 201);
}

/** POST /v1/staff-assignments  {schedule_id, staff_user_ids:[...]} */
function Sched_assign_staff() {
    require_role_api(array('ADMIN','STAFF'));
    require_csrf_api();

    $b = read_json();
    $schedule_id = isset($b['schedule_id']) ? (int)$b['schedule_id'] : 0;
    $staff_ids   = isset($b['staff_user_ids']) && is_array($b['staff_user_ids']) ? $b['staff_user_ids'] : array();

    if (!$schedule_id || empty($staff_ids)) json_err('VALIDATION','schedule_id and staff_user_ids[] required',422);

    $pdo = api_db();
    // (Optional) also capture service_id for staff_assignments
    $svc = $pdo->prepare("SELECT service_id FROM service_schedule WHERE id=?");
    $svc->execute(array($schedule_id));
    $service_id = $svc->fetchColumn();

    $ins1 = $pdo->prepare("INSERT INTO staff_schedule (staff_user_id, schedule_id) VALUES (?,?)");
    $ins2 = $pdo->prepare("INSERT INTO staff_assignments (service_id, schedule_id, staff_user_id) VALUES (?,?,?)");

    $added = 0;
    foreach ($staff_ids as $sid) {
        $sid = (int)$sid;
        try {
            $ins1->execute(array($sid, $schedule_id));
            $added++;
        } catch (Exception $e) { /* ignore duplicates */ }
        if ($service_id) {
            try { $ins2->execute(array((int)$service_id, $schedule_id, $sid)); } catch (Exception $e) {}
        }
    }
    json_ok(array('added' => $added));
}

/** POST /v1/resident-bookings  {schedule_id, resident_user_ids:[...]} */
function Sched_assign_residents() {
    require_role_api(array('ADMIN','STAFF'));
    require_csrf_api();

    $b = read_json();
    $schedule_id = isset($b['schedule_id']) ? (int)$b['schedule_id'] : 0;
    $resident_ids = isset($b['resident_user_ids']) && is_array($b['resident_user_ids']) ? $b['resident_user_ids'] : array();

    if (!$schedule_id || empty($resident_ids)) json_err('VALIDATION','schedule_id and resident_user_ids[] required',422);

    $pdo = api_db();
    $ins = $pdo->prepare("INSERT INTO resident_schedule (resident_user_id, schedule_id, status)
                          VALUES (?,?, 'BOOKED')");
    $added = 0;
    foreach ($resident_ids as $rid) {
        $rid = (int)$rid;
        try { $ins->execute(array($rid, $schedule_id)); $added++; } catch (Exception $e) { /* dedupe */ }
    }
    json_ok(array('added'=>$added));
}

/** GET /v1/me/resident/services */
function Sched_my_services() {
    require_role_api(array('RESIDENT'));
    $uid = current_user_id_api();
    $pdo = api_db();

    $sql = "
        SELECT
            ss.id           AS schedule_id,
            s.name          AS service_name,
            ss.start_time   AS start_time,
            ss.end_time     AS end_time,
            ss.location     AS location,
            ss.notes        AS notes,
            rs.status       AS booking_status
        FROM resident_schedule rs
        JOIN service_schedule ss ON ss.id = rs.schedule_id
        JOIN services s          ON s.id = ss.service_id
        WHERE rs.resident_user_id = ?
        ORDER BY ss.start_time DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute(array($uid));
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    json_ok($rows);
}

/** GET /v1/me/roster */
function Sched_my_roster() {
    // STAFF can see their own roster; ADMIN can pass ?staff_user_id=ID to inspect
    require_role_api(array('STAFF','ADMIN'));

    $pdo = api_db();
    $uid = current_user_id_api();

    if ($_SESSION['role'] === 'ADMIN' && isset($_GET['staff_user_id'])) {
        $uid = (int)$_GET['staff_user_id'];
    }

    $sql = "
        SELECT 
            sch.start_time,
            sch.end_time,
            s.name AS service_name,
            COALESCE(u.full_name, '(unassigned)') AS resident_name,
            sch.location
        FROM service_schedule sch
        JOIN services s ON s.id = sch.service_id
        LEFT JOIN resident_schedule rs ON rs.schedule_id = sch.id
        LEFT JOIN users u ON u.id = rs.resident_user_id
        WHERE EXISTS (
            SELECT 1 FROM staff_assignments sa
            WHERE sa.schedule_id = sch.id AND sa.staff_user_id = ?
        )
        OR EXISTS (
            SELECT 1 FROM staff_schedule ss
            WHERE ss.schedule_id = sch.id AND ss.staff_user_id = ?
        )
        ORDER BY sch.start_time ASC, u.full_name ASC
    ";

    $st = $pdo->prepare($sql);
    $st->execute(array($uid, $uid));
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    json_ok($rows);
}



/** GET /v1/service-schedule?service_id=ID[&from=YYYY-MM-DD&to=YYYY-MM-DD]
 *  Returns each session + staff/resident names and counts.
 */
function Sched_list() {
    require_role_api(array('ADMIN','STAFF','RESIDENT','VISITOR'));
    $pdo = api_db();

    $service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
    $from = isset($_GET['from']) ? trim($_GET['from']) : '';
    $to   = isset($_GET['to'])   ? trim($_GET['to'])   : '';

    $sql = "
        SELECT
            ss.id,
            ss.service_id,
            s.name AS service_name,
            ss.start_time,
            ss.end_time,
            ss.location,
            ss.notes,
            GROUP_CONCAT(DISTINCT su.full_name ORDER BY su.full_name SEPARATOR ', ') AS staff_names,
            GROUP_CONCAT(DISTINCT ru.full_name ORDER BY ru.full_name SEPARATOR ', ') AS resident_names,
            COUNT(DISTINCT sa.staff_user_id)    AS staff_count,
            COUNT(DISTINCT rs.resident_user_id) AS resident_count
        FROM service_schedule ss
        JOIN services s ON s.id = ss.service_id
        LEFT JOIN staff_assignments sa ON sa.schedule_id = ss.id
        LEFT JOIN users su ON su.id = sa.staff_user_id
        LEFT JOIN resident_schedule rs ON rs.schedule_id = ss.id
        LEFT JOIN users ru ON ru.id = rs.resident_user_id
        WHERE 1=1
    ";
    $args = array();

    if ($service_id) { $sql .= " AND ss.service_id = ?"; $args[] = $service_id; }
    if ($from !== '') { $sql .= " AND ss.start_time >= ?"; $args[] = $from." 00:00:00"; }
    if ($to   !== '') { $sql .= " AND ss.end_time   <= ?"; $args[] = $to  ." 23:59:59"; }

    $sql .= "
        GROUP BY ss.id, ss.service_id, s.name, ss.start_time, ss.end_time, ss.location, ss.notes
        ORDER BY ss.start_time ASC
    ";

    $st = $pdo->prepare($sql);
    $st->execute($args);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // normalize types
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['service_id'] = (int)$r['service_id'];
        $r['staff_count'] = (int)$r['staff_count'];
        $r['resident_count'] = (int)$r['resident_count'];
        if (!isset($r['staff_names']))    $r['staff_names'] = '';
        if (!isset($r['resident_names'])) $r['resident_names'] = '';
    }
    json_ok($rows);
}

/** DELETE /v1/service-schedule/{id}
 *  Hard-cancels a session by deleting it (ON DELETE CASCADE will remove assignments/bookings).
 */
function Sched_cancel($id) {
    require_role_api(array('ADMIN','STAFF'));
    require_csrf_api();

    $pdo = api_db();
    $st  = $pdo->prepare("DELETE FROM service_schedule WHERE id=?");
    $st->execute(array((int)$id));
    json_ok(array('deleted' => ($st->rowCount() > 0)));
}

/** GET /v1/service-schedule/summary?service_id=ID[&from=YYYY-MM-DD&to=YYYY-MM-DD]
 *  Returns sessions with aggregated staff/resident names.
 */
/** GET /v1/service-schedule/summary?service_id&from&to */
function Sched_list_summary() {
    require_role_api(array('ADMIN','STAFF','RESIDENT','VISITOR'));
    $pdo = api_db();

    $service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
    $from = isset($_GET['from']) ? trim($_GET['from']) : '';
    $to   = isset($_GET['to'])   ? trim($_GET['to'])   : '';

    $uid  = current_user_id_api();
    $role = current_role_api();

    // if not resident, make sure the JOIN for booked_by_me never matches
    $uid_for_join = ($role === 'RESIDENT') ? $uid : -1;

    $sql = "
      SELECT
        ss.id,
        s.name AS service_name,
        ss.start_time,
        ss.end_time,
        ss.location,
        ss.notes,
        GROUP_CONCAT(DISTINCT stf.full_name ORDER BY stf.full_name SEPARATOR ', ')  AS staff_names,
        GROUP_CONCAT(DISTINCT res.full_name ORDER BY res.full_name SEPARATOR ', ')  AS resident_names,
        CASE WHEN rs_me.resident_user_id IS NULL THEN 0 ELSE 1 END                  AS booked_by_me
      FROM service_schedule ss
      JOIN services s ON s.id = ss.service_id

      LEFT JOIN staff_schedule sa   ON sa.schedule_id = ss.id
      LEFT JOIN staff_assignments sa2 ON sa2.schedule_id = ss.id
      LEFT JOIN users stf ON stf.id IN (sa.staff_user_id, sa2.staff_user_id)

      LEFT JOIN resident_schedule rs ON rs.schedule_id = ss.id
      LEFT JOIN users res ON res.id = rs.resident_user_id

      LEFT JOIN resident_schedule rs_me
        ON rs_me.schedule_id = ss.id AND rs_me.resident_user_id = ?

      WHERE 1=1
    ";

    $args = array($uid_for_join);

    if ($service_id) { $sql .= " AND ss.service_id = ?"; $args[] = $service_id; }
    if ($from !== '') { $sql .= " AND ss.start_time >= ?"; $args[] = $from." 00:00:00"; }
    if ($to   !== '') { $sql .= " AND ss.end_time   <= ?"; $args[] = $to  ." 23:59:59"; }

    $sql .= "
      GROUP BY ss.id
      ORDER BY ss.start_time ASC
    ";

    $st = $pdo->prepare($sql);
    $st->execute($args);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // normalize nulls to empty strings (optional)
    foreach ($rows as &$r) {
        if (!isset($r['staff_names']))    $r['staff_names']    = '';
        if (!isset($r['resident_names'])) $r['resident_names'] = '';
        $r['booked_by_me'] = (int)$r['booked_by_me'];
    }

    json_ok($rows);
}



/** POST /v1/me/resident/book  {schedule_id}  (books current resident into a session) */
function Sched_self_book() {
    require_role_api(array('RESIDENT'));
    require_csrf_api();

    $b = read_json();
    $schedule_id = isset($b['schedule_id']) ? (int)$b['schedule_id'] : 0;
    if (!$schedule_id) json_err('VALIDATION','schedule_id required',422);

    $uid = current_user_id_api();
    $pdo = api_db();

    // Ensure schedule exists
    $ck = $pdo->prepare("SELECT 1 FROM service_schedule WHERE id=?");
    $ck->execute(array($schedule_id));
    if (!$ck->fetchColumn()) json_err('NOT_FOUND','Session not found',404);

    // Insert booking (ignore if already booked)
    try {
        $ins = $pdo->prepare("INSERT INTO resident_schedule (resident_user_id, schedule_id, status)
                              VALUES (?, ?, 'BOOKED')");
        $ins->execute(array($uid, $schedule_id));
        json_ok(array('booked'=>true), 201);
    } catch (Exception $e) {
        // Likely UNIQUE KEY uq_resident_slot hit (already booked)
        json_ok(array('booked'=>false, 'reason'=>'ALREADY_BOOKED'));
    }
}

/** POST /v1/me/resident/unbook  {schedule_id}  (removes current resident from a session) */
function Sched_self_unbook() {
    require_role_api(array('RESIDENT'));
    require_csrf_api();

    $b = read_json();
    $schedule_id = isset($b['schedule_id']) ? (int)$b['schedule_id'] : 0;
    if (!$schedule_id) json_err('VALIDATION','schedule_id required',422);

    $uid = current_user_id_api();
    $pdo = api_db();

    $st = $pdo->prepare("DELETE FROM resident_schedule WHERE resident_user_id=? AND schedule_id=?");
    $st->execute(array($uid, $schedule_id));
    json_ok(array('removed' => (int)$st->rowCount()), 200);
}

