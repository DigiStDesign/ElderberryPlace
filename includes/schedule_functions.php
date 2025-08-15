<?php
// includes/schedule_functions.php
// Schema references (from init_db.sql):
// - service_schedule(id, service_id, start_time, end_time, ...)
// - staff_assignments(id, service_id, schedule_id, staff_user_id, ...)
// - resident_schedule(id, resident_user_id, schedule_id, status, ...)
// - users(id, role, is_active, ...)

// -------------------------------
// Small, readable helpers (PHP5)
// -------------------------------

/** Safe overlap check: returns true if [aStart, aEnd) overlaps [bStart, bEnd) */
function timesOverlap($aStart, $aEnd, $bStart, $bEnd) {
    return (strtotime($aStart) < strtotime($bEnd)) && (strtotime($aEnd) > strtotime($bStart));
}

/** Ensure we only operate on active STAFF accounts (soft guard) */
function ensureStaff(PDO $pdo, $userId) {
    $st = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND role = 'STAFF' AND is_active = 1 LIMIT 1");
    $st->execute(array((int)$userId));
    return (bool)$st->fetchColumn();
}

/** Ensure we only operate on active RESIDENT accounts (soft guard) */
function ensureResident(PDO $pdo, $userId) {
    $st = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND role = 'RESIDENT' AND is_active = 1 LIMIT 1");
    $st->execute(array((int)$userId));
    return (bool)$st->fetchColumn();
}

/** Fetch service_id for a schedule slot */
function lookupServiceIdForSchedule(PDO $pdo, $scheduleId) {
    $st = $pdo->prepare("SELECT service_id FROM service_schedule WHERE id = ? LIMIT 1");
    $st->execute(array((int)$scheduleId));
    $svc = $st->fetchColumn();
    return $svc === false ? null : (int)$svc;
}

/** Fetch start/end for a schedule slot (returns array('start_time'=>'...','end_time'=>'...') or null) */
function lookupScheduleWindow(PDO $pdo, $scheduleId) {
    $st = $pdo->prepare("SELECT start_time, end_time FROM service_schedule WHERE id = ? LIMIT 1");
    $st->execute(array((int)$scheduleId));
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? $row : null;
}

// -----------------------------------------------------
// STAFF scheduling (kept for backwards compatibility)
// -----------------------------------------------------

/**
 * Returns true if the staff member has NO conflicting assignment.
 * Conflict exists if any existing assignment overlaps the requested [start, end).
 */
function isStaffAvailable(PDO $pdo, $staffUserId, $startDateTime, $endDateTime) {
    if (strtotime($endDateTime) <= strtotime($startDateTime)) return false;

    $sql = "
        SELECT 1
        FROM staff_assignments sa
        JOIN service_schedule ss ON ss.id = sa.schedule_id
        WHERE sa.staff_user_id = ?
          AND ss.start_time < ?   -- existing starts before requested end
          AND ss.end_time   > ?   -- existing ends after requested start
        LIMIT 1
    ";
    $st = $pdo->prepare($sql);
    $st->execute(array((int)$staffUserId, $endDateTime, $startDateTime));

    // If any row exists there is a clash => NOT available
    return $st->fetchColumn() === false;
}

/** Create a scheduled instance of a service and return new schedule_id */
function addServiceSchedule(PDO $pdo, $serviceId, $startDateTime, $endDateTime, $notes = '') {
    $st = $pdo->prepare("
        INSERT INTO service_schedule (service_id, start_time, end_time, notes)
        VALUES (?, ?, ?, ?)
    ");
    $st->execute(array((int)$serviceId, $startDateTime, $endDateTime, ($notes !== '' ? $notes : null)));
    return (int)$pdo->lastInsertId();
}

/** Assign a STAFF user to an existing schedule slot */
function assignStaffToSchedule(PDO $pdo, $staffUserId, $scheduleId) {
    $svcId = lookupServiceIdForSchedule($pdo, $scheduleId);
    if ($svcId === null) throw new Exception('Invalid schedule ID.');

    $st = $pdo->prepare("
        INSERT INTO staff_assignments (service_id, schedule_id, staff_user_id)
        VALUES (?, ?, ?)
    ");
    $st->execute(array($svcId, (int)$scheduleId, (int)$staffUserId));
}

// -----------------------------------------------------
// RESIDENT scheduling (new helpers for your UI screens)
// -----------------------------------------------------

/**
 * Returns true if the resident has NO conflicting booking in resident_schedule.
 * We detect overlap by joining to service_schedule and checking windows.
 */
function isResidentAvailable(PDO $pdo, $residentUserId, $startDateTime, $endDateTime) {
    if (strtotime($endDateTime) <= strtotime($startDateTime)) return false;

    $sql = "
        SELECT 1
        FROM resident_schedule rs
        JOIN service_schedule ss ON ss.id = rs.schedule_id
        WHERE rs.resident_user_id = ?
          AND ss.start_time < ?   -- existing starts before requested end
          AND ss.end_time   > ?   -- existing ends after requested start
        LIMIT 1
    ";
    $st = $pdo->prepare($sql);
    $st->execute(array((int)$residentUserId, $endDateTime, $startDateTime));

    // If any row exists there is a clash => NOT available
    return $st->fetchColumn() === false;
}

/**
 * Bulk-assign one or more RESIDENT users to a schedule slot.
 * - Uses the unique key (resident_user_id, schedule_id) to avoid duplicates.
 * - If already present we keep the existing status.
 */
function assignResidentsToSchedule(PDO $pdo, array $residentUserIds, $scheduleId, $defaultStatus = 'BOOKED') {
    if (empty($residentUserIds)) return;

    // Soft validation of schedule & time window (optional but nice)
    $win = lookupScheduleWindow($pdo, $scheduleId);
    if (!$win) throw new Exception('Invalid schedule slot.');

    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare("
            INSERT INTO resident_schedule (resident_user_id, schedule_id, status)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE status = status
        ");

        foreach ($residentUserIds as $rid) {
            $rid = (int)$rid;
            // Optional guards: active resident and availability
            if (!ensureResident($pdo, $rid)) continue;
            if (!isResidentAvailable($pdo, $rid, $win['start_time'], $win['end_time'])) continue;

            $ins->execute(array($rid, (int)$scheduleId, $defaultStatus));
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
