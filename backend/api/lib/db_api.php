<?php
// Works whether your app exposes $pdo or db()
function api_db() {
    static $cached = null;
    if ($cached) return $cached;

    require_once __DIR__ . '/../../config/db.php';
    if (isset($pdo) && $pdo instanceof PDO) { $cached = $pdo; return $cached; }
    if (function_exists('db')) {
        $db = db();
        if ($db instanceof PDO) { $cached = $db; return $cached; }
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(array('data'=>null,'error'=>array('code'=>'DB','message'=>'PDO not available')));
    exit;
}