<?php
// ReportsExportController.php (PHP 5.4 compatible)

require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

/**
 * Try a list of candidate paths for FPDF and require the first that exists.
 * Keeps code portable across Mercury folder layouts.
 */
function _rep_require_fpdf() {
  // Starting points
  $here     = __DIR__;                                // .../www/htdocs/api/v1
  $htdocs   = dirname($here);                         // .../www/htdocs/api
  $webroot  = dirname($htdocs);                       // .../www/htdocs
  $project  = dirname($webroot);                      // .../ict30017
  $acctroot = dirname(dirname(dirname($project)));    // .../s174531x

  $candidates = array(
    // Under webroot
    $webroot . '/vendor/fpdf/fpdf.php',
    $webroot . '/vender/fpdf/fpdf.php',   // if mis-spelled

    // Under project root
    $project . '/vendor/fpdf/fpdf.php',
    $project . '/vender/fpdf/fpdf.php',

    // Under account root
    $acctroot . '/vendor/fpdf/fpdf.php',
    $acctroot . '/vender/fpdf/fpdf.php',
  );

  foreach ($candidates as $p) {
    if (file_exists($p)) {
      require_once $p;
      return;
    }
  }

  // Nothing found: respond with JSON error (no PDF headers yet!)
  header('Content-Type: application/json');
  http_response_code(500);
  echo json_encode(array(
    'status' => 'error',
    'code'   => 'FPDF_NOT_FOUND',
    'message'=> 'FPDF library not found. Checked: ' . implode(', ', $candidates)
  ));
  exit;
}

function _rep_get_param($key, $default) {
  return (isset($_GET[$key]) && $_GET[$key] !== '') ? $_GET[$key] : $default;
}
function _rep_get_datetime_range() {
  $fromDate = _rep_get_param('from', date('Y-m-01'));
  $toDate   = _rep_get_param('to',   date('Y-m-d'));
  return array($fromDate . ' 00:00:00', $toDate . ' 23:59:59');
}
function _rep_render_header($pdf, $title, $from, $to) {
  $pdf->SetFont('Arial', 'B', 14);
  $pdf->Cell(0, 10, $title, 0, 1);
  $pdf->SetFont('Arial', '', 11);
  $pdf->Cell(0, 8, 'From: ' . $from . '   To: ' . $to, 0, 1);
  $pdf->Ln(2);
}

/**
 * GET /v1/reports/export-pdf?type=staff-workload|med-adherence&from=YYYY-MM-DD&to=YYYY-MM-DD
 */
function Reports_export_pdf() {
  require_staff_api();
  _rep_require_fpdf(); // <-- ensures FPDF is loaded or cleanly errors

  $type = _rep_get_param('type', 'staff-workload');

  // Only send PDF headers after we know we can render a PDF
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="report.pdf"');

  $pdf = new FPDF();
  $pdf->AddPage();

  $pdo = api_db();
  list($from, $to) = _rep_get_datetime_range();

  if ($type === 'staff-workload') {
    _rep_render_header($pdf, 'Staff Workload', $from, $to);

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

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(130, 8, 'Staff Name', 1);
    $pdf->Cell(40, 8, 'Administrations', 1, 1, 'R');

    $pdf->SetFont('Arial', '', 11);
    foreach ($rows as $r) {
      $name = isset($r['staff_name']) ? $r['staff_name'] : '';
      $cnt  = isset($r['administrations']) ? (int)$r['administrations'] : 0;
      $pdf->Cell(130, 8, $name, 1);
      $pdf->Cell(40, 8, (string)$cnt, 1, 1, 'R');
    }

  } else if ($type === 'med-adherence') {
    _rep_render_header($pdf, 'Medication Adherence', $from, $to);

    $sql = "
      SELECT
        SUM(CASE WHEN a.outcome = 'given'    THEN 1 ELSE 0 END) AS given,
        SUM(CASE WHEN a.outcome = 'missed'   THEN 1 ELSE 0 END) AS missed,
        SUM(CASE WHEN a.outcome = 'refused'  THEN 1 ELSE 0 END) AS refused,
        SUM(CASE WHEN a.outcome = 'withheld' THEN 1 ELSE 0 END) AS withheld,
        COUNT(ms.id) AS scheduled
      FROM med_schedule ms
      LEFT JOIN administrations a ON a.schedule_id = ms.id
      WHERE ms.due_at BETWEEN ? AND ?
    ";
    $st = $pdo->prepare($sql);
    $st->execute(array($from, $to));
    $r = $st->fetch(PDO::FETCH_ASSOC);

    $scheduled = isset($r['scheduled']) ? (int)$r['scheduled'] : 0;
    $given     = isset($r['given'])     ? (int)$r['given']     : 0;
    $missed    = isset($r['missed'])    ? (int)$r['missed']    : 0;
    $refused   = isset($r['refused'])   ? (int)$r['refused']   : 0;
    $withheld  = isset($r['withheld'])  ? (int)$r['withheld']  : 0;
    $adherence = ($scheduled > 0) ? ((float)$given / $scheduled) : 0.0;

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, 'Scheduled: ' . $scheduled, 0, 1);
    $pdf->Cell(0, 8, 'Given: ' . $given, 0, 1);
    $pdf->Cell(0, 8, 'Missed: ' . $missed, 0, 1);
    $pdf->Cell(0, 8, 'Refused: ' . $refused, 0, 1);
    $pdf->Cell(0, 8, 'Withheld: ' . $withheld, 0, 1);
    $pdf->Cell(0, 8, 'Adherence: ' . number_format($adherence * 100, 2) . '%', 0, 1);

  } else {
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Report', 0, 1);
  }

  $pdf->Output('I');
  exit;
}
