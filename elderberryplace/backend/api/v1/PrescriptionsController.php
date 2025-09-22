<?php
// CRUD for prescriptions and schedule expansion

require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

/**
 * GET /v1/residents/{id}/prescriptions
 */
function Rx_list_by_resident(int $residentId) {
  require_staff_api();
  $pdo = api_db();

  $sql = "SELECT rx.*, 
                 m.generic_name, m.brand_name, m.form, m.strength
          FROM prescriptions rx
          JOIN medications m ON m.id = rx.medication_id
          WHERE rx.resident_user_id = ?
          ORDER BY FIELD(rx.status,'active','paused','stopped'), rx.start_date DESC";
  $st = $pdo->prepare($sql);
  $st->execute([$residentId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as &$r) { $r['id'] = (int)$r['id']; $r['prn'] = (int)$r['prn']; }
  json_ok(['items' => $rows]);
}

/**
 * POST /v1/residents/{id}/prescriptions
 * Body:
 * {
 *   "medication_id": 1, "dose": "1 tablet", "route": "PO",
 *   "prn": false, "frequency": "BID",
 *   "start_date": "2025-09-22", "end_date": "2025-10-22",
 *   "times": ["08:00","20:00"], "max_daily_dose":"4 tablets",
 *   "instructions":"with food", "prescriber":"Dr Smith"
 * }
 */
function Rx_create(int $residentId) {
  require_staff_api();
  $b = read_json();

  // basic validation
  $required = ['medication_id','dose','frequency','start_date'];
  foreach ($required as $k) {
    if (!isset($b[$k]) || $b[$k]==='') return json_error(422, "Missing field: $k");
  }
  if (!isset($b['times']) || !is_array($b['times'])) $b['times'] = [];

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

    _rx_generate_schedule($pdo, $rxId, $b['start_date'], $b['end_date'] ?? null, $b['times']);

    $pdo->commit();
    json_ok(['id' => $rxId], 201);
  } catch (Throwable $e) {
    $pdo->rollBack();
    json_error(500, 'Failed to create prescription: '.$e->getMessage());
  }
}

/**
 * Helper: expand schedule rows (next 14 days or until end_date)
 */
function _rx_generate_schedule(PDO $pdo, int $rxId, string $startDate, ?string $endDate, array $times, int $window=60, int $horizonDays=14) {
  // if PRN (no times), do nothing
  if (count($times) === 0) return;

  $start = new DateTime($startDate.' 00:00:00');
  $end = $endDate ? new DateTime($endDate.' 23:59:59') : (clone $start)->modify("+{$horizonDays} days");

  $ins = $pdo->prepare("INSERT INTO med_schedule (prescription_id, due_at, window_minutes) VALUES (?,?,?)");

  $cursor = clone $start;
  while ($cursor <= $end) {
    $dateStr = $cursor->format('Y-m-d');
    foreach ($times as $t) {
      // Expect HH:mm
      $dt = DateTime::createFromFormat('Y-m-d H:i', "$dateStr $t");
      if ($dt === false) continue;
      $ins->execute([$rxId, $dt->format('Y-m-d H:i:s'), $window]);
    }
    $cursor->modify('+1 day');
  }
}
