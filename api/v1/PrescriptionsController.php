<?php
// CRUD for prescriptions and schedule expansion (PHP 5.4 safe)

require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

/**
 * GET /v1/residents/{id}/prescriptions
 */
function Rx_list_by_resident($residentId) {
  require_staff_api();
  $pdo = api_db();

  $sql = "SELECT rx.*, 
                 m.generic_name, m.brand_name, m.form, m.strength
          FROM prescriptions rx
          JOIN medications m ON m.id = rx.medication_id
          WHERE rx.resident_user_id = ?
          ORDER BY FIELD(rx.status,'active','paused','stopped'), rx.start_date DESC";
  $st = $pdo->prepare($sql);
  $st->execute(array((int)$residentId));
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // Cast a few fields to int for consistency
  foreach ($rows as $i => $r) {
    $r['id']  = isset($r['id'])  ? (int)$r['id']  : 0;
    $r['prn'] = isset($r['prn']) ? (int)$r['prn'] : 0;
    $rows[$i] = $r;
  }

  json_ok(array('items' => $rows));
}

/**
 * POST /v1/residents/{id}/prescriptions
 */
function Rx_create($residentId) {
  require_staff_api();
  $b = read_json();

  // basic validation
  $required = array('medication_id','dose','frequency','start_date');
  foreach ($required as $k) {
    if (!isset($b[$k]) || $b[$k] === '') {
      return json_err('BAD_REQUEST', "Missing field: $k", 422, null);
    }
  }
  if (!isset($b['times']) || !is_array($b['times'])) {
    $b['times'] = array();
  }

  // optional: validate date order
  if (!empty($b['end_date']) && $b['end_date'] < $b['start_date']) {
    return json_err('BAD_REQUEST', 'end_date must be on/after start_date', 422, null);
  }

  // optional: normalize/validate times (HH:mm)
  $b['times'] = _rx_normalize_times($b['times']);

  $pdo = api_db();
  $pdo->beginTransaction();

  try {
    $st = $pdo->prepare("INSERT INTO prescriptions
      (resident_user_id, medication_id, dose, route, prn, frequency, start_date, end_date,
       times_json, max_daily_dose, instructions, prescriber, status)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'active')");
    $st->execute(array(
      (int)$residentId,
      (int)$b['medication_id'],
      trim($b['dose']),
      isset($b['route']) ? trim($b['route']) : null,
      !empty($b['prn']) ? 1 : 0,
      trim($b['frequency']),
      $b['start_date'],
      !empty($b['end_date']) ? $b['end_date'] : null,
      json_encode($b['times']),
      isset($b['max_daily_dose']) ? trim($b['max_daily_dose']) : null,
      isset($b['instructions']) ? trim($b['instructions']) : null,
      isset($b['prescriber']) ? trim($b['prescriber']) : null
    ));

    $rxId = (int)$pdo->lastInsertId();

    _rx_generate_schedule($pdo, $rxId, $b['start_date'], !empty($b['end_date']) ? $b['end_date'] : null, $b['times']);

    $pdo->commit();
    json_ok(array('id' => $rxId), 201);

  } catch (Exception $e) {
    $pdo->rollBack();
    return json_err('SERVER_ERROR', 'Failed to create prescription', 500, array('error' => $e->getMessage()));
  }
}

/**
 * Helper: normalize time strings to HH:mm and drop invalid ones
 * @param array $times
 * @return array
 */
function _rx_normalize_times($times) {
  $out = array();
  if (!is_array($times)) return $out;
  foreach ($times as $t) {
    if (is_string($t) && preg_match('/^\d{2}:\d{2}$/', $t)) {
      $out[] = $t;
    }
  }
  return $out;
}

/**
 * Helper: expand schedule rows (next 14 days or until end_date)
 * @param PDO    $pdo
 * @param int    $rxId
 * @param string $startDate  (Y-m-d)
 * @param string $endDate    (Y-m-d or null)
 * @param array  $times      (array of 'HH:mm')
 * @param int    $window
 * @param int    $horizonDays
 */
function _rx_generate_schedule($pdo, $rxId, $startDate, $endDate, $times, $window = 60, $horizonDays = 14) {
  if (!is_array($times) || count($times) === 0) {
    return; // PRN: no fixed times
  }

  // Build DateTime range without using (clone ...)->method() syntax
  $start = new DateTime($startDate . ' 00:00:00');
  if ($endDate) {
    $end = new DateTime($endDate . ' 23:59:59');
  } else {
    $end = clone $start;
    $end->modify('+' . (int)$horizonDays . ' days');
  }

  $ins = $pdo->prepare("INSERT INTO med_schedule (prescription_id, due_at, window_minutes) VALUES (?,?,?)");

  // Iterate days
  $cursor = clone $start;
  while ($cursor <= $end) {
    $dateStr = $cursor->format('Y-m-d');

    // Insert each time on this date
    foreach ($times as $t) {
      $dt = DateTime::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $t);
      if ($dt) {
        $ins->execute(array((int)$rxId, $dt->format('Y-m-d H:i:s'), (int)$window));
      }
    }

    // next day
    $cursor->modify('+1 day');
  }
}
