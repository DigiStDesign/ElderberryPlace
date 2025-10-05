<?php
// /api/v1/controllers/BillingController.php
// Handles billing and receipt API endpoints

require_once __DIR__ . '/../lib/auth_api.php';
require_once __DIR__ . '/../lib/db_api.php';
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/billing_lib.php';

class BillingController {
    
    /**
     * GET /v1/billing/unbilled-items/:resident_user_id
     * Get unbilled items for a resident
     */
    public function getUnbilledItems($resident_user_id) {
        require_role_api(array('ADMIN', 'STAFF'));
        $pdo = api_db();
        
        // Optional date filters
        $period_start = isset($_GET['period_start']) ? $_GET['period_start'] : null;
        $period_end = isset($_GET['period_end']) ? $_GET['period_end'] : null;
        
        $medications = get_unbilled_medications($pdo, $resident_user_id, $period_start, $period_end);
        $services = get_unbilled_services($pdo, $resident_user_id, $period_start, $period_end);
        
        // Calculate preview totals
        $totals = calculate_bill_totals($medications, $services, 0.0, 0.0);
        
        json_ok(array(
            'medications' => $medications,
            'services' => $services,
            'totals' => $totals
        ));
    }
    
    /**
     * POST /v1/billing/bills
     * Create a new bill
     * Body: { resident_user_id, period_start, period_end, due_date, tax_rate, discount_amount, notes }
     */
    public function createBill() {
        require_role_api(array('ADMIN', 'STAFF'));
        require_csrf_api();
        
        $pdo = api_db();
        $body = read_json();
        
        if (empty($body['resident_user_id'])) {
            json_err('INVALID_INPUT', 'resident_user_id is required', 400);
        }
        
        $resident_user_id = (int)$body['resident_user_id'];
        $created_by = current_user_id_api();
        
        try {
            $bill = create_bill($pdo, $resident_user_id, $body, $created_by);
            json_ok($bill, 201);
        } catch (Exception $e) {
            json_err('CREATE_FAILED', $e->getMessage(), 400);
        }
    }
    
    /**
     * GET /v1/billing/bills
     * List bills (optionally filtered by resident)
     * Query params: resident_user_id, status, from_date, to_date
     */
    public function listBills() {
        require_role_api(array('ADMIN', 'STAFF', 'RESIDENT'));
        $pdo = api_db();
        
        $role = current_role_api();
        $current_user_id = current_user_id_api();
        
        $sql = "SELECT b.*, u.full_name AS resident_name, rp.room_number
                FROM bills b
                JOIN users u ON u.id = b.resident_user_id
                LEFT JOIN resident_profiles rp ON rp.user_id = b.resident_user_id
                WHERE 1=1";
        $params = array();
        
        // Residents can only see their own bills
        if ($role === 'RESIDENT') {
            $sql .= " AND b.resident_user_id = ?";
            $params[] = $current_user_id;
        } else if (!empty($_GET['resident_user_id'])) {
            $sql .= " AND b.resident_user_id = ?";
            $params[] = (int)$_GET['resident_user_id'];
        }
        
        if (!empty($_GET['status'])) {
            $sql .= " AND b.status = ?";
            $params[] = $_GET['status'];
        }
        
        if (!empty($_GET['from_date'])) {
            $sql .= " AND b.bill_date >= ?";
            $params[] = $_GET['from_date'];
        }
        
        if (!empty($_GET['to_date'])) {
            $sql .= " AND b.bill_date <= ?";
            $params[] = $_GET['to_date'];
        }
        
        $sql .= " ORDER BY b.bill_date DESC, b.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        json_ok(array('items' => $bills));
    }
    
    /**
     * GET /v1/billing/bills/:bill_id
     * Get bill details with line items
     */
    public function getBill($bill_id) {
        require_role_api(array('ADMIN', 'STAFF', 'RESIDENT'));
        $pdo = api_db();
        
        $bill = get_bill_details($pdo, $bill_id);
        
        if (!$bill) {
            json_err('NOT_FOUND', 'Bill not found', 404);
        }
        
        // Residents can only view their own bills
        $role = current_role_api();
        if ($role === 'RESIDENT' && $bill['resident_user_id'] != current_user_id_api()) {
            json_err('FORBIDDEN', 'Access denied', 403);
        }
        
        json_ok($bill);
    }
    
    /**
     * PUT /v1/billing/bills/:bill_id
     * Update bill (status, notes, etc.)
     * Body: { status, notes, due_date }
     */
    public function updateBill($bill_id) {
        require_role_api(array('ADMIN', 'STAFF'));
        require_csrf_api();
        
        $pdo = api_db();
        $body = read_json();
        
        $bill = get_bill_details($pdo, $bill_id);
        if (!$bill) {
            json_err('NOT_FOUND', 'Bill not found', 404);
        }
        
        $updates = array();
        $params = array();
        
        if (isset($body['status'])) {
            $allowed = array('draft', 'issued', 'paid', 'cancelled');
            if (!in_array($body['status'], $allowed, true)) {
                json_err('INVALID_INPUT', 'Invalid status', 400);
            }
            $updates[] = "status = ?";
            $params[] = $body['status'];
        }
        
        if (isset($body['notes'])) {
            $updates[] = "notes = ?";
            $params[] = $body['notes'];
        }
        
        if (isset($body['due_date'])) {
            $updates[] = "due_date = ?";
            $params[] = $body['due_date'];
        }
        
        if (empty($updates)) {
            json_err('INVALID_INPUT', 'No fields to update', 400);
        }
        
        $params[] = $bill_id;
        $sql = "UPDATE bills SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        json_ok(get_bill_details($pdo, $bill_id));
    }
    
    /**
     * DELETE /v1/billing/bills/:bill_id
     * Cancel a bill (soft delete by setting status to cancelled)
     */
    public function cancelBill($bill_id) {
        require_role_api(array('ADMIN'));
        require_csrf_api();
        
        $pdo = api_db();
        
        $bill = get_bill_details($pdo, $bill_id);
        if (!$bill) {
            json_err('NOT_FOUND', 'Bill not found', 404);
        }
        
        if ($bill['status'] === 'paid') {
            json_err('INVALID_ACTION', 'Cannot cancel a paid bill', 400);
        }
        
        $sql = "UPDATE bills SET status = 'cancelled' WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($bill_id));
        
        json_ok(array('message' => 'Bill cancelled'));
    }
    
    /**
     * POST /v1/billing/receipts
     * Record a payment receipt
     * Body: { bill_id, payment_date, payment_method, amount_paid, reference_number, notes }
     */
    public function createReceipt() {
        require_role_api(array('ADMIN', 'STAFF'));
        require_csrf_api();
        
        $pdo = api_db();
        $body = read_json();
        
        if (empty($body['bill_id']) || empty($body['payment_method']) || empty($body['amount_paid'])) {
            json_err('INVALID_INPUT', 'bill_id, payment_method, and amount_paid are required', 400);
        }
        
        $created_by = current_user_id_api();
        
        try {
            $receipt = record_receipt($pdo, (int)$body['bill_id'], $body, $created_by);
            json_ok($receipt, 201);
        } catch (Exception $e) {
            json_err('CREATE_FAILED', $e->getMessage(), 400);
        }
    }
    
    /**
     * GET /v1/billing/receipts
     * List receipts (optionally filtered)
     * Query params: resident_user_id, bill_id, from_date, to_date
     */
    public function listReceipts() {
        require_role_api(array('ADMIN', 'STAFF', 'RESIDENT'));
        $pdo = api_db();
        
        $role = current_role_api();
        $current_user_id = current_user_id_api();
        
        $sql = "SELECT r.*, b.bill_number, u.full_name AS resident_name
                FROM receipts r
                JOIN bills b ON b.id = r.bill_id
                JOIN users u ON u.id = r.resident_user_id
                WHERE 1=1";
        $params = array();
        
        // Residents can only see their own receipts
        if ($role === 'RESIDENT') {
            $sql .= " AND r.resident_user_id = ?";
            $params[] = $current_user_id;
        } else if (!empty($_GET['resident_user_id'])) {
            $sql .= " AND r.resident_user_id = ?";
            $params[] = (int)$_GET['resident_user_id'];
        }
        
        if (!empty($_GET['bill_id'])) {
            $sql .= " AND r.bill_id = ?";
            $params[] = (int)$_GET['bill_id'];
        }
        
        if (!empty($_GET['from_date'])) {
            $sql .= " AND r.payment_date >= ?";
            $params[] = $_GET['from_date'];
        }
        
        if (!empty($_GET['to_date'])) {
            $sql .= " AND r.payment_date <= ?";
            $params[] = $_GET['to_date'];
        }
        
        $sql .= " ORDER BY r.payment_date DESC, r.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        json_ok(array('items' => $receipts));
    }
    
    /**
     * GET /v1/billing/receipts/:receipt_id
     * Get receipt details
     */
    public function getReceipt($receipt_id) {
        require_role_api(array('ADMIN', 'STAFF', 'RESIDENT'));
        $pdo = api_db();
        
        $sql = "SELECT r.*, b.bill_number, b.grand_total, 
                       u.full_name AS resident_name, u.email AS resident_email,
                       rp.room_number,
                       creator.full_name AS created_by_name
                FROM receipts r
                JOIN bills b ON b.id = r.bill_id
                JOIN users u ON u.id = r.resident_user_id
                LEFT JOIN resident_profiles rp ON rp.user_id = r.resident_user_id
                LEFT JOIN users creator ON creator.id = r.created_by
                WHERE r.id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($receipt_id));
        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$receipt) {
            json_err('NOT_FOUND', 'Receipt not found', 404);
        }
        
        // Residents can only view their own receipts
        $role = current_role_api();
        if ($role === 'RESIDENT' && $receipt['resident_user_id'] != current_user_id_api()) {
            json_err('FORBIDDEN', 'Access denied', 403);
        }
        
        json_ok($receipt);
    }
    
    /**
     * POST /v1/billing/charges/medications
     * Add a medication purchase charge
     * Body: { resident_user_id, medication_id, quantity, unit_price, purchase_date, notes }
     */
    public function addMedicationCharge() {
        require_role_api(array('ADMIN', 'STAFF'));
        require_csrf_api();
        
        $pdo = api_db();
        $body = read_json();
        
        if (empty($body['resident_user_id']) || empty($body['medication_id']) || 
            empty($body['quantity']) || !isset($body['unit_price'])) {
            json_err('INVALID_INPUT', 'resident_user_id, medication_id, quantity, and unit_price are required', 400);
        }
        
        $quantity = (int)$body['quantity'];
        $unit_price = (float)$body['unit_price'];
        $total_price = round($quantity * $unit_price, 2);
        
        $sql = "INSERT INTO medication_purchases 
                (resident_user_id, medication_id, quantity, unit_price, total_price, purchase_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(
            (int)$body['resident_user_id'],
            (int)$body['medication_id'],
            $quantity,
            $unit_price,
            $total_price,
            isset($body['purchase_date']) ? $body['purchase_date'] : date('Y-m-d'),
            isset($body['notes']) ? $body['notes'] : null
        ));
        
        $id = $pdo->lastInsertId();
        
        // Return created charge
        $sql = "SELECT mp.*, m.generic_name, m.brand_name
                FROM medication_purchases mp
                JOIN medications m ON m.id = mp.medication_id
                WHERE mp.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($id));
        
        json_ok($stmt->fetch(PDO::FETCH_ASSOC), 201);
    }
    
    /**
     * POST /v1/billing/charges/services
     * Add a service charge
     * Body: { resident_user_id, service_id, service_name, description, unit_price, quantity, service_date }
     */
    public function addServiceCharge() {
        require_role_api(array('ADMIN', 'STAFF'));
        require_csrf_api();
        
        $pdo = api_db();
        $body = read_json();
        
        if (empty($body['resident_user_id']) || empty($body['service_id']) || 
            empty($body['service_name']) || !isset($body['unit_price'])) {
            json_err('INVALID_INPUT', 'resident_user_id, service_id, service_name, and unit_price are required', 400);
        }
        
        $quantity = isset($body['quantity']) ? (float)$body['quantity'] : 1.0;
        $unit_price = (float)$body['unit_price'];
        $total_price = round($quantity * $unit_price, 2);
        
        $sql = "INSERT INTO service_charges 
                (resident_user_id, schedule_id, service_id, service_name, description, 
                 unit_price, quantity, total_price, service_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(
            (int)$body['resident_user_id'],
            isset($body['schedule_id']) ? (int)$body['schedule_id'] : null,
            (int)$body['service_id'],
            $body['service_name'],
            isset($body['description']) ? $body['description'] : null,
            $unit_price,
            $quantity,
            $total_price,
            isset($body['service_date']) ? $body['service_date'] : date('Y-m-d')
        ));
        
        json_ok(array('id' => $pdo->lastInsertId()), 201);
    }
    
    /**
     * GET /v1/billing/bills/:bill_id/pdf
     * Export bill as PDF
     */
    public function exportBillPDF($bill_id) {
        require_role_api(array('ADMIN', 'STAFF', 'RESIDENT'));
        
        require_once __DIR__ . '/../lib/pdf_export.php';
        
        $pdo = api_db();
        $bill = get_bill_details($pdo, $bill_id);
        
        if (!$bill) {
            json_err('NOT_FOUND', 'Bill not found', 404);
        }
        
        // Residents can only export their own bills
        $role = current_role_api();
        if ($role === 'RESIDENT' && $bill['resident_user_id'] != current_user_id_api()) {
            json_err('FORBIDDEN', 'Access denied', 403);
        }
        
        // Generate and output PDF
        // 'I' = inline display in browser, 'D' = force download
        $output_mode = isset($_GET['download']) && $_GET['download'] === '1' ? 'D' : 'I';
        generate_bill_pdf($bill, $output_mode);
        exit;
    }
    
    /**
     * GET /v1/billing/receipts/:receipt_id/pdf
     * Export receipt as PDF
     */
    public function exportReceiptPDF($receipt_id) {
        require_role_api(array('ADMIN', 'STAFF', 'RESIDENT'));
        
        require_once __DIR__ . '/../lib/pdf_export.php';
        
        $pdo = api_db();
        
        // Get receipt details
        $sql = "SELECT r.*, b.bill_number, b.grand_total, 
                       u.full_name AS resident_name, u.email AS resident_email,
                       rp.room_number,
                       creator.full_name AS created_by_name
                FROM receipts r
                JOIN bills b ON b.id = r.bill_id
                JOIN users u ON u.id = r.resident_user_id
                LEFT JOIN resident_profiles rp ON rp.user_id = r.resident_user_id
                LEFT JOIN users creator ON creator.id = r.created_by
                WHERE r.id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($receipt_id));
        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$receipt) {
            json_err('NOT_FOUND', 'Receipt not found', 404);
        }
        
        // Residents can only export their own receipts
        $role = current_role_api();
        if ($role === 'RESIDENT' && $receipt['resident_user_id'] != current_user_id_api()) {
            json_err('FORBIDDEN', 'Access denied', 403);
        }
        
        // Optionally get full bill details for context
        $bill = null;
        if (isset($_GET['include_bill']) && $_GET['include_bill'] === '1') {
            $bill = get_bill_details($pdo, $receipt['bill_id']);
        }
        
        // Generate and output PDF
        $output_mode = isset($_GET['download']) && $_GET['download'] === '1' ? 'D' : 'I';
        generate_receipt_pdf($receipt, $bill, $output_mode);
        exit;
    }
}