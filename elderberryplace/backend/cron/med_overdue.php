<?php
// Run from CLI or Task Scheduler/Cron to open 'overdue' alerts for late doses.

error_reporting(E_ALL); ini_set('display_errors', 1);

// Load PDO helper from your API
require_once dirname(__DIR__) . '/api/v1/lib/db_api.php'; // -> backend/api/v1/lib/db_api.php

$pdo = api_db();

// Find doses that are past the window and have no administration
$sql = "
SELECT ms.id AS schedule_id,
       rx.resident_user_id,
       ms.due_at,
       ms.window_minutes,
       TIMESTAMPDIFF(MINUTE, ms.due_at, NOW()) AS minutes_late
FROM med_schedule ms
JOIN prescriptions rx ON rx.id = ms.prescription_id
LEFT JOIN administrations a ON a.schedule_id = ms.id
WHERE a.id IS NULL
  AND NOW() > DATE_ADD(ms.due_at, INTERVAL ms.window_minutes MINUTE)
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Insert one alert per schedule (relies on unique index if you ran patch_03_alerts.sql)
$ins = $pdo->prepare("
  INSERT IGNORE INTO med_alerts (resident_user_id, schedule_id, type, message, state)
  VALUES (?, ?, 'overdue', ?, 'open')
");

$count = 0;
foreach ($rows as $r) {
  $msg = sprintf(
    "Medication overdue by %d min (due %s, window %d min)",
    max(1,(int)$r['minutes_late']), $r['due_at'], (int)$r['window_minutes']
  );
  $ok = $ins->execute([(int)$r['resident_user_id'], (int)$r['schedule_id'], $msg]);
  if ($ok) $count += $ins->rowCount(); // rowCount=0 if INSERT IGNORE hit duplicate
}

echo "[med_overdue] alerts created: $count\n";
