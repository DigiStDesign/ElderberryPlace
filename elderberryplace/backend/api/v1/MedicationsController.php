<?php
// List / search medications for the Rx form.

require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

/**
 * GET /v1/medications
 * Optional query: q=parac
 */
function Meds_list() {
  require_staff_api();
  $pdo = api_db();

  $q = isset($_GET['q']) ? trim($_GET['q']) : '';
  if ($q !== '') {
    $like = '%' . $q . '%';
    $st = $pdo->prepare("SELECT id, generic_name, brand_name, form, strength
                         FROM medications
                         WHERE generic_name LIKE ? OR brand_name LIKE ?
                         ORDER BY generic_name, strength");
    $st->execute([$like, $like]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $rows = $pdo->query("SELECT id, generic_name, brand_name, form, strength
                         FROM medications
                         ORDER BY generic_name, strength")->fetchAll(PDO::FETCH_ASSOC);
  }
  foreach ($rows as &$r) { $r['id'] = (int)$r['id']; }
  json_ok(['items' => $rows]);
}
