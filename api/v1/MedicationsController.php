<?php
// /api/v1/MedicationsController.php
// List / search medications for the Rx form (PHP 5.4 safe)

require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

/**
 * GET /v1/medications
 * Query params:
 *   q=parac        (optional; search generic/brand; space-separated terms are ANDed)
 *   form=tablet    (optional; exact match)
 *   strength=500 mg (optional; exact match)
 *   page=1&limit=50 (optional; limit 1..100)
 */
function Meds_list() {
        require_role_api(array('ADMIN', 'STAFF'));

  $pdo = api_db();

  $q        = isset($_GET['q'])        ? trim($_GET['q'])        : '';
  if (strlen($q) > 80) return json_err('BAD_REQUEST', 'query too long', 422, null);

  $form     = isset($_GET['form'])     ? trim($_GET['form'])     : '';
  $strength = isset($_GET['strength']) ? trim($_GET['strength']) : '';

  $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
  if ($limit < 1) $limit = 1; if ($limit > 100) $limit = 100;
  $page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
  $offset = ($page - 1) * $limit;

  $where = array();
  $args  = array();

  // Tokenize q on whitespace and AND the tokens
  if ($q !== '') {
    $parts = preg_split('/\s+/', $q);
    foreach ($parts as $p) {
      $p = trim($p);
      if ($p === '') continue;
      $where[] = "(generic_name LIKE ? OR brand_name LIKE ?)";
      $like = '%' . $p . '%';
      $args[] = $like; // generic_name
      $args[] = $like; // brand_name
    }
  }

  if ($form !== '')     { $where[] = "form = ?";      $args[] = $form; }
  if ($strength !== '') { $where[] = "strength = ?";  $args[] = $strength; }

  $sql = "SELECT id, generic_name, brand_name, form, strength
          FROM medications";
  if (!empty($where)) $sql .= " WHERE " . implode(' AND ', $where);
  $sql .= " ORDER BY generic_name, strength
            LIMIT $limit OFFSET $offset";

  $st = $pdo->prepare($sql);
  $st->execute($args);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as &$r) { $r['id'] = (int)$r['id']; }

  json_ok(array(
    'items' => $rows,
    'page'  => $page,
    'limit' => $limit
  ));
}

/**
 * POST /v1/medications
 * Body: { generic_name, brand_name?, form, strength }
 */
function Meds_create() {
  // Allow ADMIN or STAFF to curate the list
  if (function_exists('require_role_api')) {
    require_role_api(array('ADMIN','STAFF'));
  } else {
    require_staff_api();
  }

  $b = read_json();
  $generic = isset($b['generic_name']) ? trim($b['generic_name']) : '';
  $brand   = isset($b['brand_name'])   ? trim($b['brand_name'])   : null;
  $form    = isset($b['form'])         ? trim($b['form'])         : '';
  $str     = isset($b['strength'])     ? trim($b['strength'])     : '';

  if ($generic === '' || $form === '' || $str === '') {
    return json_err('BAD_REQUEST','generic_name, form, strength required',422,null);
  }

  $pdo = api_db();
  try {
    $st = $pdo->prepare("INSERT INTO medications (generic_name, brand_name, form, strength)
                         VALUES (?,?,?,?)");
    $st->execute(array($generic, $brand, $form, $str));
    json_ok(array('id' => (int)$pdo->lastInsertId()), 201);
  } catch (Exception $e) {
    // Unique index on (generic_name,strength,form) triggers 1062
    if (strpos($e->getMessage(),'1062') !== false) {
      return json_err('DUPLICATE','Medication already exists',409,null);
    }
    return json_err('DB','Insert failed',500,array('error'=>$e->getMessage()));
  }
}
