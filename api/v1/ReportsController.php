<?php
// ReportsController.php (PHP 5.4 compatible)

require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

/**
 * Small helpers (PHP 5-friendly)
 */
function _get_param($key, $default) {
  return (isset($_GET[$key]) && $_GET[$key] !== '') ? $_GET[$key] : $default;
}

function _get_datetime_range() {
  // Defaults: from = first day of month, to = today
  $fromDate = _get_param('from', date('Y-m-01'));
  $toDate   = _get_param('to',   date('Y-m-d'));
  return array($fromDate . ' 00:00:00', $toDate . ' 23:59:59');
}

/** -----------------------------------------------
 *  Medication adherence summary
 *  GET /v1/reports/med-adherence?from=YYYY-MM-DD&to=YYYY-MM-DD
 *  ----------------------------------------------*/
function Report_med_adherence() {
  require_staff_api();
  list($from, $to) = _get_datetime_range();
  $pdo = api_db();

  $sql = "
    SELECT
      COALESCE(SUM(a.outcome='given'),0)     AS given,
      COALESCE(SUM(a.outcome='missed'),0)    AS missed,
      COALESCE(SUM(a.outcome='refused'),0)   AS refused,
      COALESCE(SUM(a.outcome='withheld'),0)  AS withheld,
      COUNT(ms.id)                            AS scheduled
    FROM med_schedule ms
    LEFT JOIN administrations a ON a.schedule_id = ms.id
    WHERE ms.due_at BETWEEN ? AND ?
  ";
  $st = $pdo->prepare($sql);
  $st->execute(array($from, $to));
  $r = $st->fetch(PDO::FETCH_ASSOC);

  $scheduled = $r ? (int)$r['scheduled'] : 0;
  $given     = $r ? (int)$r['given']     : 0;
  $missed    = $r ? (int)$r['missed']    : 0;
  $refused   = $r ? (int)$r['refused']   : 0;
  $withheld  = $r ? (int)$r['withheld']  : 0;

  $adherence = ($scheduled > 0) ? ((float)$given / $scheduled) : 0.0;

  json_ok(array('summary' => array(
    'from'       => $from,
    'to'         => $to,
    'scheduled'  => $scheduled,
    'given'      => $given,
    'missed'     => $missed,
    'refused'    => $refused,
    'withheld'   => $withheld,
    'adherence'  => $adherence
  )));
}

/** -----------------------------------------------
 *  Medication missed by shift (AM/PM/Night)
 *  GET /v1/reports/med-missed-by-shift?from=&to=
 *  ----------------------------------------------*/
function Report_med_missed_by_shift() {
  require_staff_api();
  list($from, $to) = _get_datetime_range();
  $pdo = api_db();

  $sql = "
    SELECT
      DATE(ms.due_at) AS day,
      CASE
        WHEN HOUR(ms.due_at) BETWEEN 7  AND 14 THEN 'AM'
        WHEN HOUR(ms.due_at) BETWEEN 15 AND 22 THEN 'PM'
        ELSE 'Night'
      END AS shift,
      SUM(CASE WHEN a.id IS NULL OR a.outcome='missed' THEN 1 ELSE 0 END) AS missed,
      COUNT(ms.id) AS scheduled
    FROM med_schedule ms
    LEFT JOIN administrations a ON a.schedule_id = ms.id
    WHERE ms.due_at BETWEEN ? AND ?
    GROUP BY day, shift
    ORDER BY day, FIELD(shift,'AM','PM','Night')
  ";
  $st = $pdo->prepare($sql);
  $st->execute(array($from, $to));
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as $i => $row) {
    $row['missed']    = isset($row['missed'])    ? (int)$row['missed']    : 0;
    $row['scheduled'] = isset($row['scheduled']) ? (int)$row['scheduled'] : 0;
    $rows[$i] = $row;
  }

  json_ok(array('rows' => $rows, 'from' => $from, 'to' => $to));
}

/** -----------------------------------------------
 *  Staff workload (count of administrations per staff)
 *  GET /v1/reports/staff-workload?from=YYYY-MM-DD&to=YYYY-MM-DD
 *  ----------------------------------------------*/
function Report_staff_workload() {
  require_staff_api();
  list($from, $to) = _get_datetime_range();
  $pdo = api_db();

  $sql = "
    SELECT
      u.id AS staff_user_id,
      u.full_name AS staff_name,
      COUNT(a.id) AS administrations
    FROM users u
    LEFT JOIN administrations a
      ON a.staff_user_id = u.id
     AND a.administered_at BETWEEN ? AND ?
    WHERE u.role IN ('staff','nurse','caregiver','carer')
    GROUP BY u.id, u.full_name
    ORDER BY administrations DESC, staff_name ASC
  ";
  $st = $pdo->prepare($sql);
  $st->execute(array($from, $to));
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as $i => $r) {
    $r['staff_user_id']   = isset($r['staff_user_id'])   ? (int)$r['staff_user_id']   : 0;
    $r['administrations'] = isset($r['administrations']) ? (int)$r['administrations'] : 0;
    $rows[$i] = $r;
  }

  json_ok(array('from' => $from, 'to' => $to, 'rows' => $rows));
}

/** -----------------------------------------------
 *  Compliance incidents summary
 *  GET /v1/reports/compliance-summary?from=&to=&resident_id=
 *  ----------------------------------------------*/
function Report_compliance_summary() {
  require_staff_api();
  list($from, $to) = _get_datetime_range();
  $pdo = api_db();

  $where = array("occurred_at BETWEEN ? AND ?");
  $args  = array($from, $to);

  if (isset($_GET['resident_id']) && $_GET['resident_id'] !== '') {
    $where[] = "resident_user_id = ?";
    $args[]  = (int)$_GET['resident_id'];
  }

  $sql = "
    SELECT type, severity, COUNT(*) AS cnt
    FROM incidents
    WHERE " . implode(' AND ', $where) . "
    GROUP BY type, severity
    ORDER BY type, FIELD(severity,'low','moderate','high','critical')
  ";

  $st = $pdo->prepare($sql);
  $st->execute($args);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as $i => $r) {
    $r['cnt'] = isset($r['cnt']) ? (int)$r['cnt'] : 0;
    $rows[$i] = $r;
  }

  json_ok(array('from' => $from, 'to' => $to, 'rows' => $rows));
}
