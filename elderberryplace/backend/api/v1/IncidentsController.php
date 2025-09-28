<?php
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

function _inc_allowed($val, array $allowed) {
  return in_array(strtolower($val), $allowed, true);
}

/**
 * GET /v1/incidents
 * Query:
 *   resident_id?=123
 *   from?=YYYY-MM-DD
 *   to?=YYYY-MM-DD
 *   type?=medication|fall|behaviour|infection|other
 *   severity?=low|moderate|high|critical
 *   page?=1  limit?=50   (limit capped 1..100)
 */
function Incidents_list() {
  require_staff_api();
  $pdo = api_db();

  $allowedTypes    = ['medication','fall','behaviour','infection','other'];
  $allowedSeverity = ['low','moderate','high','critical'];

  $where = [];
  $args  = [];

  if (!empty($_GET['resident_id'])) { $where[] = "i.resident_user_id = ?"; $args[] = (int)$_GET['resident_id']; }
  if (!empty($_GET['from']))        { $where[] = "i.occurred_at >= ?";     $args[] = $_GET['from'].' 00:00:00'; }
  if (!empty($_GET['to']))          { $where[] = "i.occurred_at <= ?";     $args[] = $_GET['to'].' 23:59:59'; }
  if (!empty($_GET['type'])) {
    if (!_inc_allowed($_GET['type'], $allowedTypes)) return json_err('BAD_REQUEST','Invalid type',422,null);
    $where[] = "i.type = ?"; $args[] = strtolower($_GET['type']);
  }
  if (!empty($_GET['severity'])) {
    if (!_inc_allowed($_GET['severity'], $allowedSeverity)) return json_err('BAD_REQUEST','Invalid severity',422,null);
    $where[] = "i.severity = ?"; $args[] = strtolower($_GET['severity']);
  }

  // Pagination
  $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
  if ($limit < 1) $limit = 1; if ($limit > 100) $limit = 100;
  $page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
  $offset = ($page - 1) * $limit;

  // Base SELECT
  $sqlBase = "FROM incidents i
              LEFT JOIN users r ON r.id=i.resident_user_id
              LEFT JOIN users s ON s.id=i.reported_by";
  $sqlWhere = $where ? (" WHERE ".implode(' AND ', $where)) : '';

  // Total count (for pagination UI)
  $cnt = $pdo->prepare("SELECT COUNT(*) ".$sqlBase.$sqlWhere);
  $cnt->execute($args);
  $total = (int)$cnt->fetchColumn();

  // Page of rows
  $sql = "SELECT i.*, r.full_name AS resident_name, s.full_name AS reporter_name
          ".$sqlBase.$sqlWhere."
          ORDER BY i.occurred_at DESC
          LIMIT $limit OFFSET $offset";
  $st = $pdo->prepare($sql);
  $st->execute($args);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as &$row) { $row['id']=(int)$row['id']; }

  json_ok(['items'=>$rows, 'page'=>$page, 'limit'=>$limit, 'total'=>$total]);
}

/**
 * POST /v1/incidents
 * Body:
 *  {
 *    "resident_user_id": 12,           // optional
 *    "type": "medication|fall|behaviour|infection|other",
 *    "severity": "low|moderate|high|critical",
 *    "occurred_at": "YYYY-MM-DD HH:MM:SS",   // or "YYYY-MM-DD"
 *    "reported_by": 5,
 *    "description": "...",              // optional
 *    "action_taken": "..."              // optional
 *  }
 */
function Incidents_create() {
  require_staff_api();
  $b = read_json();

  $allowedTypes    = ['medication','fall','behaviour','infection','other'];
  $allowedSeverity = ['low','moderate','high','critical'];

  // Required fields
  foreach (['type','severity','occurred_at','reported_by'] as $k) {
    if (empty($b[$k])) return json_err('BAD_REQUEST', "Missing: $k", 422, null);
  }
  // Validate enums
  if (!in_array(strtolower($b['type']), $allowedTypes, true)) {
    return json_err('BAD_REQUEST', 'Invalid type', 422, null);
  }
  if (!in_array(strtolower($b['severity']), $allowedSeverity, true)) {
    return json_err('BAD_REQUEST', 'Invalid severity', 422, null);
  }
  // Normalise occurred_at (allow date-only)
  $occurred = trim($b['occurred_at']);
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $occurred)) {
    $occurred .= ' 00:00:00';
  }

  try {
    $pdo = api_db();
    $st = $pdo->prepare("INSERT INTO incidents
      (resident_user_id, type, severity, occurred_at, reported_by, description, action_taken)
      VALUES (?,?,?,?,?,?,?)");
    $st->execute([
      !empty($b['resident_user_id']) ? (int)$b['resident_user_id'] : null,
      strtolower($b['type']),
      strtolower($b['severity']),
      $occurred,
      (int)$b['reported_by'],
      isset($b['description'])  ? $b['description']  : null,
      isset($b['action_taken']) ? $b['action_taken'] : null
    ]);
    json_ok(['id' => (int)$pdo->lastInsertId()], 201);
  } catch (PDOException $e) {
    return json_err('SERVER_ERROR', 'Failed to create incident', 500, array('error'=>$e->getMessage()));
  }
}
