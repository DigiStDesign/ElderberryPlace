<?php
// api/v1/routes.php  (PHP 5.4-safe)

require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';

require_once __DIR__ . '/AuthController.php';
require_once __DIR__ . '/ResidentsController.php';
require_once __DIR__ . '/VisitorsController.php';
require_once __DIR__ . '/StaffController.php';
require_once __DIR__ . '/RelationshipsController.php';
require_once __DIR__ . '/VisitsController.php';

require_once __DIR__ . '/ServicesController.php';
require_once __DIR__ . '/CategoriesController.php';
require_once __DIR__ . '/ScheduleController.php';

/** ---- Medication / Alerts / Reports ---- */
require_once __DIR__ . '/MedicationsController.php';
require_once __DIR__ . '/PrescriptionsController.php';
require_once __DIR__ . '/MedScheduleController.php';
require_once __DIR__ . '/MedAlertsController.php';
require_once __DIR__ . '/ReportsController.php';

/** ---- ICT-211: Incidents (Enhanced Reporting) ---- */
require_once __DIR__ . '/IncidentsController.php';

/** ---- ICT-214: PDF export ---- */
require_once __DIR__ . '/ReportsExportController.php';

function route_v1($method, $path) {
    // --- Auth / CSRF
    if ($method === 'GET'  && $path === '/csrf')        { json_ok(array('csrf' => csrf_value_api())); return; }
    if ($method === 'POST' && $path === '/auth/login')  { Auth_login();  return; }
    if ($method === 'POST' && $path === '/auth/logout') { Auth_logout(); return; }
    if ($method === 'GET'  && $path === '/me')          { Me_get();      return; }

    // --- Residents CRUD
    if ($path === '/residents' && $method === 'GET')    { Residents_list();   return; }
    if ($path === '/residents' && $method === 'POST')   { Residents_create(); return; }
    if (preg_match('#^/residents/(\d+)$#', $path, $m)) {
        if     ($method === 'PUT')    { Residents_update((int)$m[1]); return; }
        elseif ($method === 'DELETE') { Residents_delete((int)$m[1]); return; }
    }

    // --- Visitors CRUD
    if ($method === 'GET'  && $path === '/visitors')    { Visitors_list();   return; }
    if ($method === 'POST' && $path === '/visitors')    { Visitors_create(); return; }
    if (preg_match('#^/visitors/(\d+)$#', $path, $m)) {
        if ($method === 'GET')    { Visitors_get((int)$m[1]);    return; }
        if ($method === 'PUT')    { Visitors_update((int)$m[1]); return; }
        if ($method === 'DELETE') { Visitors_delete((int)$m[1]); return; }
    }

    // --- Staff CRUD
    if ($path === '/staff/jobs' && $method === 'GET')   { Staff_jobs();   return; }
    if ($path === '/staff'      && $method === 'GET')   { Staff_list();   return; }
    if ($path === '/staff'      && $method === 'POST')  { Staff_create(); return; }
    if (preg_match('#^/staff/(\d+)$#', $path, $m)) {
        if     ($method === 'PUT')    { Staff_update((int)$m[1]); return; }
        elseif ($method === 'DELETE') { Staff_delete((int)$m[1]); return; }
    }

    // --- Relationships
    if ($method === 'GET'  && $path === '/relationships/types') { Rel_types();        return; }
    if ($method === 'POST' && $path === '/relationships')       { Rel_add();          return; }
    if ($method === 'DELETE' && preg_match('#^/relationships/(\d+)$#',$path,$m)) { Rel_delete((int)$m[1]); return; }
    if ($method === 'GET' && $path === '/me/related-residents') { Rel_my_residents(); return; } // VISITOR
    if (preg_match('#^/relationships/by-resident/(\d+)$#', $path, $m) && $method === 'GET') { Rel_by_resident((int)$m[1]); return; }
    if (preg_match('#^/relationships/by-visitor/(\d+)$#',   $path, $m) && $method === 'GET') { Rel_by_visitor((int)$m[1]);  return; }

    // --- Visit requests
    if ($method === 'POST' && $path === '/visit-requests')     { Visits_create();       return; } // VISITOR
    if ($method === 'GET'  && $path === '/me/visit-requests')  { Visits_my_requests();  return; } // VISITOR
    if ($method === 'GET'  && $path === '/me/visitations')     { Visits_for_resident(); return; } // RESIDENT
    if ($method === 'POST' && preg_match('#^/visit-requests/(\d+)/status$#',$path,$m)) { Visits_change_status((int)$m[1]); return; }

    // --- Categories
    if ($path === '/categories' && $method === 'GET')  { Cat_list();   return; }
    if ($path === '/categories' && $method === 'POST') { Cat_create(); return; }
    if (preg_match('#^/categories/(\d+)$#', $path, $m)) {
        if ($method === 'PUT')    { Cat_update((int)$m[1]); return; }
        if ($method === 'DELETE') { Cat_delete((int)$m[1]); return; }
    }

    // --- Services
    if ($path === '/services' && $method === 'GET')  { Services_list();   return; }
    if ($path === '/services' && $method === 'POST') { Services_create(); return; }
    if (preg_match('#^/services/(\d+)$#', $path, $m)) {
        if ($method === 'GET')    { Services_get((int)$m[1]);    return; }
        if ($method === 'PUT')    { Services_update((int)$m[1]); return; }
        if ($method === 'DELETE') { Services_delete((int)$m[1]); return; }
    }

    // --- Scheduling
    if ($method === 'POST' && $path === '/service-schedule')           { Sched_add();            return; } // STAFF/ADMIN
    if ($path === '/service-schedule' && $method === 'GET')            { Sched_list();           return; }
    if (preg_match('#^/service-schedule/(\d+)$#', $path, $m) && $method === 'DELETE') { Sched_cancel((int)$m[1]); return; }
    if ($path === '/me/resident/book'   && $method === 'POST')         { Sched_self_book();      return; }
    if ($path === '/me/resident/unbook' && $method === 'POST')         { Sched_self_unbook();    return; }
    if ($path === '/service-schedule/summary' && $method === 'GET')    { Sched_list_summary();   return; }
    if ($method === 'POST' && $path === '/staff-assignments')          { Sched_assign_staff();   return; } // STAFF/ADMIN
    if ($method === 'POST' && $path === '/resident-bookings')          { Sched_assign_residents(); return; } // STAFF/ADMIN
    if ($method === 'GET'  && $path === '/me/resident/services')       { Sched_my_services();    return; } // RESIDENT
    if ($method === 'GET'  && $path === '/me/roster')                  { Sched_my_roster();      return; } // STAFF

    /** ---------- Medication catalogue ---------- */
    if ($method === 'GET' && $path === '/medications') { Meds_list(); return; }

    /** ---------- Prescriptions ---------- */
    if (preg_match('#^/residents/(\d+)/prescriptions$#', $path, $m)) {
        if ($method === 'GET')  { Rx_list_by_resident((int)$m[1]); return; }
        if ($method === 'POST') { Rx_create((int)$m[1]);           return; }
    }

    /** ---------- Medication schedule / MAR ---------- */
    if (preg_match('#^/residents/(\d+)/med-due$#', $path, $m) && $method === 'GET') {
        Med_due_for_resident((int)$m[1]); return;
    }
    if (preg_match('#^/med-schedule/(\d+)/administer$#', $path, $m) && $method === 'POST') {
        Med_administer((int)$m[1]); return;
    }

    /** ---------- Alerts ---------- */
    if ($method === 'GET' && $path === '/med-alerts') { MedAlerts_list(); return; }
    if (preg_match('#^/med-alerts/(\d+)$#', $path, $m) && $method === 'PATCH')) {
        MedAlerts_update((int)$m[1]); return;
    }

    /** ---------- Reports (Medication) ---------- */
    if ($method === 'GET' && $path === '/reports/med-adherence')       { Report_med_adherence();       return; }
    if ($method === 'GET' && $path === '/reports/med-missed-by-shift') { Report_med_missed_by_shift(); return; }

    /** ---------- ICT-211: Incidents + New Reports ---------- */
    // Incidents
    if ($method === 'GET'  && $path === '/incidents') { Incidents_list();  return; }
    if ($method === 'POST' && $path === '/incidents') { Incidents_create(); return; }

    // New reports added for Enhanced Reporting
    if ($method === 'GET' && $path === '/reports/staff-workload')     { Report_staff_workload();     return; }
    if ($method === 'GET' && $path === '/reports/compliance-summary') { Report_compliance_summary(); return; }

    /** ---------- ICT-214: PDF export ---------- */
    if ($method === 'GET' && $path === '/reports/export/pdf') { Reports_export_pdf(); return; }

    // --- Fallback
    json_err('NOT_FOUND','No route',404,array('method'=>$method,'path'=>$path));
}
