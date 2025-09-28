<?php
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

/** GET /v1/med-alerts?state=open&resident_id=&type= */
function MedAlerts_list() {
  require_staff_api();
  $pdo = api_db();

  $where = ["1=1"]; $args = [];

  if (!empty($_GET['state'])) {
    $state = $_GET['state'];
    if (!in_array($state, ['open','resolved'], true)) {
      return json_err('BAD_REQUEST','Invalid state',422,null);
    }
    $where[] = "a.state = ?"; $args[] = $state;
  }

  if (!empty($_GET['resident_id'])) {
    $where[] = "a.resident_user_id = ?"; $args[] = (int)$_GET['resident_id'];
  }

  if (!empty($_GET['type'])) {
    $type = $_GET['type'];
    if (!in_array($type, ['overdue','allergy','prn_limit'], true)) {
      return json_err('BAD_REQUEST','Invalid type',422,null);
    }
    $where[] = "a.type = ?"; $args[] = $type;
  }

  $sql = "SELECT a.id, a.type, a.message, a.state, a.created_at, a.resident_user_id, a.schedule_id,
                 r.full_name AS resident_name, ms.due_at
          FROM med_alerts a
          LEFT JOIN users r ON r.id = a.resident_user_id
          LEFT JOIN med_schedule ms ON ms.id = a.schedule_id
          WHERE ".implode(' AND ', $where)."
          ORDER BY a.created_at DESC";

  $st = $pdo->prepare($sql); $st->execute($args);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['resident_user_id'] = (int)$r['resident_user_id'];
    if (isset($r['schedule_id'])) $r['schedule_id'] = (int)$r['schedule_id'];
  }

  json_ok(['items'=>$rows]);
}

/** PATCH /v1/med-alerts/{id} body: { state:"resolved"| "open", resolved_by:<userId> } */
function MedAlerts_update($id) {
  require_staff_api();
  $b = read_json();

  $state = isset($b['state']) ? $b['state'] : 'resolved';
  if (!in_array($state, ['open','resolved'], true)) {
    return json_err('BAD_REQUEST','Invalid state',422,null);
  }
  $by = isset($b['resolved_by']) ? (int)$b['resolved_by'] : null;

  $pdo = api_db();
  $st = $pdo->prepare("UPDATE med_alerts
                       SET state=?, resolved_at = CASE WHEN ?='resolved' THEN NOW() ELSE NULL END,
                           resolved_by = CASE WHEN ?='resolved' THEN ? ELSE NULL END
                       WHERE id=?");
  $st->execute([$state, $state, $state, $by, (int)$id]);

  if ($st->rowCount() === 0) {
    return json_err('NOT_FOUND','Alert not found',404,null);
  }
  json_ok(['ok'=>true]);
}
