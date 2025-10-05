<?php
// Due doses and administration recording (MAR)

require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

/**
 * GET /v1/residents/{id}/med-due?from=YYYY-MM-DD HH:MM&to=YYYY-MM-DD HH:MM
 */
function Med_due_for_resident($residentId) { // drop type hints if older PHP
        require_role_api(array('ADMIN', 'STAFF'));
  $pdo = api_db();

  $from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d 00:00:00');
  $to   = isset($_GET['to'])   ? $_GET['to']   : date('Y-m-d 23:59:59');

  $sql = "SELECT 
            ms.id AS schedule_id, ms.due_at, ms.window_minutes,
            rx.id AS rx_id, rx.dose, rx.route, rx.prn,
            m.generic_name, m.brand_name, m.form, m.strength,
            (SELECT a.outcome FROM administrations a 
              WHERE a.schedule_id = ms.id 
              ORDER BY a.id DESC LIMIT 1) AS outcome
          FROM med_schedule ms
          JOIN prescriptions rx ON rx.id = ms.prescription_id
          JOIN medications m ON m.id = rx.medication_id
          WHERE rx.resident_user_id = ?
            AND ms.due_at BETWEEN ? AND ?
          ORDER BY ms.due_at ASC";

  $st = $pdo->prepare($sql);
  $st->execute([(int)$residentId, $from, $to]);

  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as &$r) {
    $r['schedule_id'] = (int)$r['schedule_id'];
    $r['rx_id']       = (int)$r['rx_id'];
    $r['prn']         = (int)$r['prn'];
  }
  json_ok(['items' => $rows]);
}

/**
 * POST /v1/med-schedule/{id}/administer
 * Body: { "outcome":"given|refused|withheld|missed", "dose_given":"1 tab", "notes":"", "staff_user_id": 2, "witness_user_id": null }
 */
function Med_administer($scheduleId) { // drop type hint if older PHP
        require_role_api(array('ADMIN', 'STAFF'));
  $b = read_json();

  // Normalize + validate
  $outcome = isset($b['outcome']) ? strtolower(trim($b['outcome'])) : '';
  $allowed = ['given','refused','withheld','missed'];
  if (!in_array($outcome, $allowed, true) || empty($b['staff_user_id'])) {
    // use json_error(...) if that's your helper name
    return json_err('BAD_REQUEST', 'Missing/invalid fields: outcome, staff_user_id', 422, null);
  }

  $staffId   = (int)$b['staff_user_id'];
  $witnessId = isset($b['witness_user_id']) ? (int)$b['witness_user_id'] : null;

  $pdo = api_db();

  // Ensure the schedule exists
  $chk = $pdo->prepare("SELECT id FROM med_schedule WHERE id=?");
  $chk->execute([(int)$scheduleId]);
  if (!$chk->fetchColumn()) {
    return json_err('NOT_FOUND', 'Schedule item not found', 404, null);
  }

  // Optional: prevent duplicate recording for this schedule (idempotency)
  $exists = $pdo->prepare("SELECT id FROM administrations WHERE schedule_id=? LIMIT 1");
  $exists->execute([(int)$scheduleId]);
  if ($exists->fetchColumn()) {
    return json_err('CONFLICT', 'Administration already recorded for this schedule item', 409, null);
  }

  try {
    $st = $pdo->prepare("INSERT INTO administrations
        (schedule_id, administered_at, outcome, dose_given, notes, staff_user_id, witness_user_id)
        VALUES (?, NOW(), ?, ?, ?, ?, ?)");
    $st->execute([
      (int)$scheduleId,
      $outcome,
      isset($b['dose_given']) ? $b['dose_given'] : null,
      isset($b['notes']) ? $b['notes'] : null,
      $staffId,
      $witnessId
    ]);
    json_ok(['id' => (int)$pdo->lastInsertId()], 201);
  } catch (PDOException $e) {
    // FK or other DB error
    return json_err('SERVER_ERROR', 'Failed to record administration', 500, ['error' => $e->getMessage()]);
  }
}
