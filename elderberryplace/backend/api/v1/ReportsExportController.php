<?php
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';
require_once __DIR__ . '/ReportsController.php';        // reuse queries
require_once __DIR__ . '/../../vendor/fpdf/fpdf.php';    // the library

function Reports_export_pdf() {
  require_staff_api();
  $type = $_GET['type'] ?? 'staff-workload';
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="report.pdf"');

  $pdf = new FPDF(); $pdf->AddPage(); $pdf->SetFont('Arial','B',14);
  if ($type==='staff-workload') {
    // Re-run the same query
    $_GET['from'] = $_GET['from'] ?? date('Y-m-01');
    $_GET['to']   = $_GET['to']   ?? date('Y-m-d');
    ob_start(); Report_staff_workload(); $json = json_decode(ob_get_clean(), true);
    $rows = $json['data']['rows'];

    $pdf->Cell(0,10,'Staff Workload',0,1);
    $pdf->SetFont('Arial','',11);
    foreach ($rows as $r) { $pdf->Cell(120,8,$r['staff_name'],1); $pdf->Cell(40,8,$r['administrations'],1,1); }
  } else {
    // Fallback simple page
    $pdf->Cell(0,10,'Report',0,1);
  }
  $pdf->Output('I');
  exit;
}
