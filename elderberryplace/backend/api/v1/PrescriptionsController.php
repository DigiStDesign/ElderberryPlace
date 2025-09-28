<?php
// CRUD for prescriptions and schedule expansion

require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

/**
 * GET /v1/residents/{id}/prescriptions
 */
function Rx_list_by_resident($residentId) { // remove type hint if on PHP <7
  require_staff_api();
  $pdo = api_db();

  $sql = "SELECT rx.*, 
                 m.generic_name, m.brand_name, m.form, m.strength
          FROM prescriptions rx
          JOIN medications m ON m.id = rx.medication_id
          WHERE rx.resident_user_id = ?
          ORDER BY FIELD(rx.status,'active','paused','stopped'), rx.start_date DESC";
  $st = $pdo->prepare($sql);
  $st->execute([(int)$residentId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as &$r) { $r['id'] = (int)$r['id']; $r['prn'] = (int)$r['prn']; }
  json_ok(['items' => $rows]);
}

/**
 * POST /v1/residents/{id}/prescriptions
 */
function Rx_create($residentId) { // remove type hint if on PHP <7
  require_staff_api();
  $b = read_json();

  // basic validation
  $required = ['medication_id','dose','frequency','start_date'];
  foreach ($required as $k) {
    if (!isset($b[$k]) || $b[$k]==='') return json_err('BAD_REQUEST', "Missing field: $k", 422, null);
  }
  if (!isset($b['times']) || !is_array($b['times'])) $b['times'] = [];

  // optional: validate date order
  if (!empty($b['end_date']) && $b['end_date'] < $b['start_date']) {
    return json_err('BAD_REQUEST', 'end_date must be on/after start_date', 422, null);
  }

  // optional: normalize/validate times (HH:mm)
  $times = [];
  foreach ($b['times'] as $t) {
    if (preg_match('/^\d{2}:\d{2}$/', $t)) $times[] = $t;
  }
  $b['times'] = $times;

  $pdo = api_db();
  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare("INSERT INTO prescriptions
      (resident_user_id, medication_id, dose, route, prn, frequency, start_date, end_date,
       times_json, max_daily_dose, instructions, prescriber, status)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'active')");
    $st->execute([
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
    ]);
    $rxId = (int)$pdo->lastInsertId();

    _rx_generate_schedule($pdo, $rxId, $b['start_date'], !empty($b['end_date']) ? $b['end_date'] : null, $b['times']);

    $pdo->commit();
    json_ok(['id' => $rxId], 201);
  } catch (Exception $e) { // use Exception if not on PHP 7+
    $pdo->rollBack();
    return json_err('SERVER_ERROR', 'Failed to create prescription', 500, ['error'=>$e->getMessage()]);
  }
}

/**
 * Helper: expand schedule rows (next 14 days or until end_date)
 */
function _rx_generate_schedule(PDO $pdo, $rxId, $startDate, $endDate, array $times, $window=60, $horizonDays=14) { // remove scalar/nullable types if needed
  if (count($times) === 0) return; // PRN: no fixed times

  $start = new DateTime($startDate.' 00:00:00');
  $end = $endDate ? new DateTime($endDate.' 23:59:59') : (clone $start)->modify("+{$horizonDays} days");

  $ins = $pdo->prepare("INSERT INTO med_schedule (prescription_id, due_at, window_minutes) VALUES (?,?,?)");

  for ($cursor = clone $start; $cursor <= $end; $cursor->modify('+1 day')) {
    $dateStr = $cursor->format('Y-m-d');
    foreach ($times as $t) {
      $dt = DateTime::createFromFormat('Y-m-d H:i', "$dateStr $t");
      if ($dt) $ins->execute([(int)$rxId, $dt->format('Y-m-d H:i:s'), (int)$window]);
    }
  }
}
