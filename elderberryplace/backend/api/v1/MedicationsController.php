<?php
// List / search medications for the Rx form.

require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

/**
 * GET /v1/medications
 * Query params:
 *   q=parac        (optional; search generic/brand)
 *   form=tablet    (optional)
 *   strength=500 mg (optional; exact match)
 *   page=1&limit=50 (optional; limit 1..100)
 */
function Meds_list() {
  require_staff_api();
  $pdo = api_db();

  $q = isset($_GET['q']) ? trim($_GET['q']) : '';
  if (strlen($q) > 80) return json_err('BAD_REQUEST','query too long',422,null);

  $form = isset($_GET['form']) ? trim($_GET['form']) : '';
  $strength = isset($_GET['strength']) ? trim($_GET['strength']) : '';

  $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
  if ($limit < 1) $limit = 1; if ($limit > 100) $limit = 100;
  $page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
  $offset = ($page - 1) * $limit;

  $where = [];
  $args  = [];

  if ($q !== '') {
    $where[] = "(generic_name LIKE ? OR brand_name LIKE ?)";
    $like = "%$q%";
    $args[] = $like; $args[] = $like;
  }
  if ($form !== '')     { $where[] = "form = ?";      $args[] = $form; }
  if ($strength !== '') { $where[] = "strength = ?";  $args[] = $strength; }

  $sql = "SELECT id, generic_name, brand_name, form, strength
          FROM medications";
  if ($where) $sql .= " WHERE ".implode(' AND ', $where);
  $sql .= " ORDER BY generic_name, strength
            LIMIT $limit OFFSET $offset"; // ints are sanitized above

  $st = $pdo->prepare($sql);
  $st->execute($args);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as &$r) { $r['id'] = (int)$r['id']; }

  json_ok([
    'items' => $rows,
    'page'  => $page,
    'limit' => $limit
  ]);
}
