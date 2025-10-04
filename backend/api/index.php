<?php
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/v1/routes.php';

api_start_session();

// Log PHP errors to a file (handy on Windows/Laragon)
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/api_error.log');

// Always JSON out
ob_start();
ini_set('display_errors', '0');
ini_set('html_errors', '0');

set_exception_handler(function($ex){
    error_log('EXCEPTION: '.$ex->getMessage());
    json_err('EXCEPTION', $ex->getMessage(), 500);
});
set_error_handler(function($no,$str,$file,$line){
    error_log("PHP[$no] $str @ $file:$line");
    json_err('PHP_ERROR', "$str @ ".basename($file).":$line", 500);
});


$method = $_SERVER['REQUEST_METHOD'];

// Accept: ?r=/v1/... OR PATH_INFO OR pretty URLs
$path = '';
if (isset($_GET['r'])) {
    $path = $_GET['r'];
} elseif (!empty($_SERVER['PATH_INFO'])) {
    $path = $_SERVER['PATH_INFO'];
} else {
    $req  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/'; // e.g. "/v2/api/"
    if (strpos($req, $base) === 0) $path = substr($req, strlen($base)); // e.g. "v1/auth/login"
}

$path = urldecode($path);
$path = '/' . ltrim($path, '/');      // "/v1/auth/login" or "/auth/login"

// Strip any /v{number}/ prefix -> "/auth/login"
$path = preg_replace('#^/v\d+/#', '/', $path);
if ($path === '/v1' || $path === '/v2') $path = '/';

route_v1($method, $path === '' ? '/' : $path);
