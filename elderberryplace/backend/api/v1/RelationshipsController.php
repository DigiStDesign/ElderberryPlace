<?php
// /api/v1/RelationshipsController.php
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

function Rel_types() {
    require_role_api(array('ADMIN','STAFF','VISITOR','RESIDENT'));
    $pdo = api_db();
    $st  = $pdo->query("SELECT id, name FROM relationship_types ORDER BY name");
    json_ok($st->fetchAll(PDO::FETCH_ASSOC)); // <-- raw array (not {items:[]})
}

function Rel_add() {
    require_role_api(array('ADMIN','STAFF')); require_csrf_api();
    $b   = read_json();
    $pdo = api_db();

    $pdo->prepare("
        INSERT INTO visitor_resident_links
            (visitor_user_id, resident_user_id, relationship_type_id, is_primary_contact, start_date, end_date, notes)
        VALUES (?,?,?,?,?,?,?)
    ")->execute(array(
        (int)$b['visitor_user_id'],
        (int)$b['resident_user_id'],
        (int)$b['relationship_type_id'],
        !empty($b['is_primary_contact']) ? 1 : 0,
        !empty($b['start_date']) ? $b['start_date'] : null,
        !empty($b['end_date'])   ? $b['end_date']   : null,
        !empty($b['notes'])      ? $b['notes']      : null
    ));
    json_ok(array('ok'=>true), 201);
}

function Rel_delete($id) {
    require_role_api(array('ADMIN','STAFF')); require_csrf_api();
    $pdo = api_db();
    $pdo->prepare("DELETE FROM visitor_resident_links WHERE id = ?")->execute(array((int)$id));
    json_ok(array('deleted'=>true));
}

function Rel_my_residents() {
    require_role_api(array('VISITOR'));
    $pdo = api_db();
    $me  = current_user_id_api();
    $st  = $pdo->prepare("
        SELECT u.id, u.full_name
        FROM users u
        JOIN visitor_resident_links vrl ON vrl.resident_user_id = u.id
        WHERE u.role='RESIDENT' AND u.is_active=1 AND vrl.visitor_user_id = ?
        ORDER BY u.full_name
    ");
    $st->execute(array($me));
    json_ok(array('items'=>$st->fetchAll(PDO::FETCH_ASSOC)));
}

function Rel_by_resident($residentId) {
    require_role_api(array('ADMIN','STAFF'));
    $pdo = api_db();
    $st  = $pdo->prepare("
        SELECT
            vrl.id,
            vrl.visitor_user_id      AS person_id,
            u.full_name              AS person_name,
            rt.name                  AS relationship_name,
            vrl.is_primary_contact,
            vrl.start_date,
            vrl.end_date,
            vrl.notes
        FROM visitor_resident_links vrl
        JOIN users u ON u.id = vrl.visitor_user_id
        JOIN relationship_types rt ON rt.id = vrl.relationship_type_id
        WHERE vrl.resident_user_id = ?
        ORDER BY u.full_name
    ");
    $st->execute(array((int)$residentId));
    json_ok($st->fetchAll(PDO::FETCH_ASSOC)); // plain array (your Vue expects data.data to be an array)
}

function Rel_by_visitor($visitorId) {
    require_role_api(array('ADMIN','STAFF'));
    $pdo = api_db();
    $st  = $pdo->prepare("
        SELECT
            vrl.id,
            vrl.resident_user_id     AS person_id,
            u.full_name              AS person_name,
            rt.name                  AS relationship_name,
            vrl.is_primary_contact,
            vrl.start_date,
            vrl.end_date,
            vrl.notes
        FROM visitor_resident_links vrl
        JOIN users u ON u.id = vrl.resident_user_id
        JOIN relationship_types rt ON rt.id = vrl.relationship_type_id
        WHERE vrl.visitor_user_id = ?
        ORDER BY u.full_name
    ");
    $st->execute(array((int)$visitorId));
    json_ok($st->fetchAll(PDO::FETCH_ASSOC)); // plain array
}
