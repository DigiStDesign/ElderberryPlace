<?php
function addServiceSchedule($pdo, $service_id, $start, $end, $notes)
{
    $stmt = $pdo->prepare("
        INSERT INTO service_schedule (service_id, start_time, end_time, notes)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$service_id, $start, $end, $notes]);
    return $pdo->lastInsertId();
}


function assignStaffToSchedule($pdo, $staff_id, $schedule_id) {
    $stmt = $pdo->prepare("INSERT INTO staff_schedule (staff_id, schedule_id) VALUES (?, ?)");
    $stmt->execute([$staff_id, $schedule_id]);
}

function assignResidentsToSchedule($pdo, $resident_ids, $schedule_id) {
    $stmt = $pdo->prepare("INSERT INTO resident_schedule (resident_id, schedule_id) VALUES (?, ?)");
    foreach ($resident_ids as $resident_id) {
        $stmt->execute([$resident_id, $schedule_id]);
    }
}

function isStaffAvailable($pdo, $staff_id, $start, $end) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM staff_schedule ss
        JOIN service_schedule s ON ss.schedule_id = s.id
        WHERE ss.staff_id = ?
        AND (s.start_time < ? AND s.end_time > ?)
    ");
    $stmt->execute([$staff_id, $end, $start]);
    return $stmt->fetchColumn() == 0;
}

function isResidentAvailable($pdo, $resident_id, $start, $end) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM resident_schedule rs
        JOIN service_schedule s ON rs.schedule_id = s.id
        WHERE rs.resident_id = ?
        AND (s.start_time < ? AND s.end_time > ?)
    ");
    $stmt->execute([$resident_id, $end, $start]);
    return $stmt->fetchColumn() == 0;
}