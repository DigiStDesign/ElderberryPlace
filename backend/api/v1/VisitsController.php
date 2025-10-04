<?php
// /api/v1/VisitsController.php  (PHP 5.4)
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

/**
 * POST /v1/visit-requests
 * VISITOR creates a request to visit a resident they’re linked to.
 */
function Visits_create() {
    require_role_api(array('VISITOR'));
    require_csrf_api();

    $b = read_json();
    $resident_user_id = isset($b['resident_user_id']) ? (int)$b['resident_user_id'] : 0;
    $requested_start  = isset($b['requested_start']) ? trim($b['requested_start']) : '';
    $requested_end    = isset($b['requested_end']) ? trim($b['requested_end']) : null;
    $notes            = isset($b['notes']) ? trim($b['notes']) : null;

    if (!$resident_user_id || $requested_start === '') {
        json_err('VALIDATION','resident_user_id and requested_start are required',422);
    }

    $pdo = api_db();
    $me  = current_user_id_api();

    // Ensure the visitor has a relationship with this resident
    $chk = $pdo->prepare("
        SELECT 1 FROM visitor_resident_links
        WHERE visitor_user_id = ? AND resident_user_id = ? LIMIT 1
    ");
    $chk->execute(array($me, $resident_user_id));
    if (!$chk->fetchColumn()) {
        json_err('FORBIDDEN','You are not linked to this resident',403);
    }

    $ins = $pdo->prepare("
        INSERT INTO visit_requests
          (visitor_user_id, resident_user_id, requested_start, requested_end, notes, status)
        VALUES (?,?,?,?,?, 'PENDING')
    ");
    $ins->execute(array($me, $resident_user_id, $requested_start, $requested_end ? $requested_end : null, $notes ? $notes : null));

    json_ok(array('id' => (int)$pdo->lastInsertId()), 201);
}

/**
 * GET /v1/me/visit-requests
 * VISITOR sees their own submitted requests.
 */
function Visits_my_requests() {
    require_role_api(array('VISITOR'));
    $pdo = api_db();
    $me  = current_user_id_api();

    $st = $pdo->prepare("
        SELECT vr.id, vr.requested_start, vr.requested_end, vr.status, vr.notes,
               u.full_name AS resident_name
        FROM visit_requests vr
        JOIN users u ON u.id = vr.resident_user_id
        WHERE vr.visitor_user_id = ?
        ORDER BY vr.requested_start DESC
    ");
    $st->execute(array($me));
    json_ok($st->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * GET /v1/me/visitations
 * RESIDENT sees requests addressed to them (PENDING/APPROVED/etc.)
 */
function Visits_for_resident() {
    require_role_api(array('RESIDENT'));
    $pdo = api_db();
    $me  = current_user_id_api();

    $st = $pdo->prepare("
        SELECT vr.id, vr.requested_start, vr.requested_end, vr.status, vr.notes,
               v.full_name AS visitor_name
        FROM visit_requests vr
        JOIN users v ON v.id = vr.visitor_user_id
        WHERE vr.resident_user_id = ?
        ORDER BY vr.requested_start DESC
    ");
    $st->execute(array($me));
    json_ok($st->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * POST /v1/visit-requests/{id}/status   { action: 'APPROVE'|'DECLINE'|'CANCEL' }
 * Only the RESIDENT recipient can change status.
 */
function Visits_change_status($id) {
    require_role_api(array('RESIDENT'));
    require_csrf_api();

    $id = (int)$id;
    if (!$id) json_err('VALIDATION','Invalid id',422);

    $pdo = api_db();
    $me  = current_user_id_api();
    $b   = read_json();
    $action = isset($b['action']) ? strtoupper(trim($b['action'])) : '';

    if (!in_array($action, array('APPROVE','DECLINE','CANCEL'))) {
        json_err('VALIDATION','Invalid action',422);
    }

    // Load the request and ensure it belongs to this resident
    $rq = $pdo->prepare("SELECT id, status FROM visit_requests WHERE id=? AND resident_user_id=? LIMIT 1");
    $rq->execute(array($id, $me));
    $row = $rq->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_err('NOT_FOUND','Request not found or not yours',404);

    $from = $row['status'];
    // Allowed transitions: PENDING -> APPROVED/DECLINED; APPROVED -> CANCELLED
    $ok = false; $to = null;
    if ($from === 'PENDING' && ($action === 'APPROVE' || $action === 'DECLINE')) { $ok = true; $to = ($action === 'APPROVE') ? 'APPROVED' : 'DECLINED'; }
    if ($from === 'APPROVED' && $action === 'CANCEL') { $ok = true; $to = 'CANCELLED'; }

    if (!$ok) json_err('INVALID_STATE','Action not allowed from current status',409);

    $up = $pdo->prepare("
        UPDATE visit_requests
        SET status = ?
        WHERE id = ? AND resident_user_id = ? AND status = ?
        LIMIT 1
    ");
    $up->execute(array($to, $id, $me, $from));

    if ($up->rowCount() !== 1) {
        json_err('CONFLICT','Update failed—status may have changed',409);
    }

    json_ok(array('id'=>$id,'from'=>$from,'to'=>$to));
}
