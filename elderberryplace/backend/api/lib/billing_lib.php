<?php
// /api/v1/lib/billing_lib.php
// Billing and receipt business logic functions

/**
 * Generate a unique bill number
 * Format: INV-YYYY-NNNNN (e.g., INV-2025-00001)
 * 
 * @param PDO $pdo Database connection
 * @return string Generated bill number
 */
function generate_bill_number($pdo) {
    $year = date('Y');
    $prefix = 'INV-' . $year . '-';
    
    // Get the highest bill number for this year
    $sql = "SELECT bill_number FROM bills 
            WHERE bill_number LIKE ? 
            ORDER BY bill_number DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($prefix . '%'));
    $last = $stmt->fetchColumn();
    
    if ($last) {
        // Extract sequence number and increment
        $seq = (int)substr($last, -5) + 1;
    } else {
        $seq = 1;
    }
    
    return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
}

/**
 * Generate a unique receipt number
 * Format: RCT-YYYY-NNNNN (e.g., RCT-2025-00001)
 * 
 * @param PDO $pdo Database connection
 * @return string Generated receipt number
 */
function generate_receipt_number($pdo) {
    $year = date('Y');
    $prefix = 'RCT-' . $year . '-';
    
    $sql = "SELECT receipt_number FROM receipts 
            WHERE receipt_number LIKE ? 
            ORDER BY receipt_number DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($prefix . '%'));
    $last = $stmt->fetchColumn();
    
    if ($last) {
        $seq = (int)substr($last, -5) + 1;
    } else {
        $seq = 1;
    }
    
    return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
}

/**
 * Calculate bill totals from line items
 * 
 * @param array $medications Array of medication line items
 * @param array $services Array of service line items
 * @param float $tax_rate Tax rate (e.g., 0.10 for 10% GST)
 * @param float $discount Flat discount amount
 * @return array Calculated totals
 */
function calculate_bill_totals($medications, $services, $tax_rate = 0.0, $discount = 0.0) {
    $med_subtotal = 0.0;
    $svc_subtotal = 0.0;
    
    foreach ($medications as $item) {
        $med_subtotal += floatval($item['total_price']);
    }
    
    foreach ($services as $item) {
        $svc_subtotal += floatval($item['total_price']);
    }
    
    $subtotal = $med_subtotal + $svc_subtotal;
    $tax_amount = round($subtotal * $tax_rate, 2);
    $grand_total = round($subtotal + $tax_amount - $discount, 2);
    
    return array(
        'subtotal_medications' => round($med_subtotal, 2),
        'subtotal_services' => round($svc_subtotal, 2),
        'subtotal' => round($subtotal, 2),
        'tax_amount' => $tax_amount,
        'discount_amount' => round($discount, 2),
        'grand_total' => $grand_total
    );
}

/**
 * Get unbilled medication purchases for a resident
 * 
 * @param PDO $pdo Database connection
 * @param int $resident_user_id Resident user ID
 * @param string|null $period_start Start date (YYYY-MM-DD)
 * @param string|null $period_end End date (YYYY-MM-DD)
 * @return array Array of unbilled medication purchases
 */
function get_unbilled_medications($pdo, $resident_user_id, $period_start = null, $period_end = null) {
    $sql = "SELECT mp.*, m.generic_name, m.brand_name, m.form, m.strength
            FROM medication_purchases mp
            JOIN medications m ON m.id = mp.medication_id
            LEFT JOIN bill_items bi ON bi.item_type = 'medication' AND bi.reference_id = mp.id
            WHERE mp.resident_user_id = ?
              AND bi.id IS NULL"; // not yet billed
    
    $params = array($resident_user_id);
    
    if ($period_start) {
        $sql .= " AND mp.purchase_date >= ?";
        $params[] = $period_start;
    }
    if ($period_end) {
        $sql .= " AND mp.purchase_date <= ?";
        $params[] = $period_end;
    }
    
    $sql .= " ORDER BY mp.purchase_date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get unbilled service charges for a resident
 * 
 * @param PDO $pdo Database connection
 * @param int $resident_user_id Resident user ID
 * @param string|null $period_start Start date (YYYY-MM-DD)
 * @param string|null $period_end End date (YYYY-MM-DD)
 * @return array Array of unbilled service charges
 */
function get_unbilled_services($pdo, $resident_user_id, $period_start = null, $period_end = null) {
    $sql = "SELECT sc.*
            FROM service_charges sc
            LEFT JOIN bill_items bi ON bi.item_type = 'service' AND bi.reference_id = sc.id
            WHERE sc.resident_user_id = ?
              AND bi.id IS NULL"; // not yet billed
    
    $params = array($resident_user_id);
    
    if ($period_start) {
        $sql .= " AND sc.service_date >= ?";
        $params[] = $period_start;
    }
    if ($period_end) {
        $sql .= " AND sc.service_date <= ?";
        $params[] = $period_end;
    }
    
    $sql .= " ORDER BY sc.service_date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Create a bill for a resident
 * 
 * @param PDO $pdo Database connection
 * @param int $resident_user_id Resident user ID
 * @param array $params Bill parameters (period_start, period_end, due_date, etc.)
 * @param int|null $created_by Staff user ID who creates the bill
 * @return array Created bill with items
 * @throws Exception on error
 */
function create_bill($pdo, $resident_user_id, $params, $created_by = null) {
    $pdo->beginTransaction();
    
    try {
        // Get unbilled items
        $period_start = isset($params['period_start']) ? $params['period_start'] : null;
        $period_end = isset($params['period_end']) ? $params['period_end'] : null;
        
        $medications = get_unbilled_medications($pdo, $resident_user_id, $period_start, $period_end);
        $services = get_unbilled_services($pdo, $resident_user_id, $period_start, $period_end);
        
        if (empty($medications) && empty($services)) {
            throw new Exception('No unbilled items found for this resident in the specified period');
        }
        
        // Calculate totals
        $tax_rate = isset($params['tax_rate']) ? floatval($params['tax_rate']) : 0.0;
        $discount = isset($params['discount_amount']) ? floatval($params['discount_amount']) : 0.0;
        $totals = calculate_bill_totals($medications, $services, $tax_rate, $discount);
        
        // Generate bill number
        $bill_number = generate_bill_number($pdo);
        
        // Insert bill header
        $sql = "INSERT INTO bills 
                (bill_number, resident_user_id, bill_date, due_date, period_start, period_end,
                 subtotal_medications, subtotal_services, tax_amount, discount_amount, grand_total,
                 status, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(
            $bill_number,
            $resident_user_id,
            isset($params['bill_date']) ? $params['bill_date'] : date('Y-m-d'),
            isset($params['due_date']) ? $params['due_date'] : null,
            $period_start,
            $period_end,
            $totals['subtotal_medications'],
            $totals['subtotal_services'],
            $totals['tax_amount'],
            $totals['discount_amount'],
            $totals['grand_total'],
            isset($params['status']) ? $params['status'] : 'draft',
            isset($params['notes']) ? $params['notes'] : null,
            $created_by
        ));
        
        $bill_id = $pdo->lastInsertId();
        
        // Insert medication line items
        $line_order = 0;
        $item_sql = "INSERT INTO bill_items 
                     (bill_id, item_type, reference_id, description, quantity, unit_price, total_price, line_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $item_stmt = $pdo->prepare($item_sql);
        
        foreach ($medications as $med) {
            $desc = $med['generic_name'];
            if ($med['brand_name']) $desc .= ' (' . $med['brand_name'] . ')';
            if ($med['strength']) $desc .= ' ' . $med['strength'];
            if ($med['form']) $desc .= ' ' . $med['form'];
            
            $item_stmt->execute(array(
                $bill_id,
                'medication',
                $med['id'],
                $desc,
                $med['quantity'],
                $med['unit_price'],
                $med['total_price'],
                ++$line_order
            ));
        }
        
        // Insert service line items
        foreach ($services as $svc) {
            $desc = $svc['service_name'];
            if ($svc['description']) $desc .= ' - ' . $svc['description'];
            
            $item_stmt->execute(array(
                $bill_id,
                'service',
                $svc['id'],
                $desc,
                $svc['quantity'],
                $svc['unit_price'],
                $svc['total_price'],
                ++$line_order
            ));
        }
        
        $pdo->commit();
        
        // Return created bill with items
        return get_bill_details($pdo, $bill_id);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Get bill details with line items
 * 
 * @param PDO $pdo Database connection
 * @param int $bill_id Bill ID
 * @return array|null Bill details or null if not found
 */
function get_bill_details($pdo, $bill_id) {
    // Get bill header
    $sql = "SELECT b.*, 
                   u.full_name AS resident_name,
                   u.email AS resident_email,
                   rp.room_number,
                   creator.full_name AS created_by_name
            FROM bills b
            JOIN users u ON u.id = b.resident_user_id
            LEFT JOIN resident_profiles rp ON rp.user_id = b.resident_user_id
            LEFT JOIN users creator ON creator.id = b.created_by
            WHERE b.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($bill_id));
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bill) {
        return null;
    }
    
    // Get line items
    $sql = "SELECT * FROM bill_items WHERE bill_id = ? ORDER BY line_order ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($bill_id));
    $bill['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get receipts (payments)
    $sql = "SELECT * FROM receipts WHERE bill_id = ? ORDER BY payment_date ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($bill_id));
    $bill['receipts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate amount paid
    $amount_paid = 0.0;
    foreach ($bill['receipts'] as $r) {
        $amount_paid += floatval($r['amount_paid']);
    }
    $bill['amount_paid'] = round($amount_paid, 2);
    $bill['balance'] = round(floatval($bill['grand_total']) - $amount_paid, 2);
    
    return $bill;
}

/**
 * Record a payment receipt
 * 
 * @param PDO $pdo Database connection
 * @param int $bill_id Bill ID
 * @param array $params Payment parameters
 * @param int|null $created_by Staff user ID who records payment
 * @return array Created receipt
 * @throws Exception on error
 */
function record_receipt($pdo, $bill_id, $params, $created_by = null) {
    $pdo->beginTransaction();
    
    try {
        // Get bill details
        $bill = get_bill_details($pdo, $bill_id);
        if (!$bill) {
            throw new Exception('Bill not found');
        }
        
        if ($bill['status'] === 'cancelled') {
            throw new Exception('Cannot record payment for a cancelled bill');
        }
        
        $amount_paid = floatval($params['amount_paid']);
        if ($amount_paid <= 0) {
            throw new Exception('Payment amount must be greater than zero');
        }
        
        if ($amount_paid > $bill['balance']) {
            throw new Exception('Payment amount exceeds outstanding balance');
        }
        
        // Generate receipt number
        $receipt_number = generate_receipt_number($pdo);
        
        // Insert receipt
        $sql = "INSERT INTO receipts 
                (receipt_number, bill_id, resident_user_id, payment_date, payment_method,
                 amount_paid, reference_number, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(
            $receipt_number,
            $bill_id,
            $bill['resident_user_id'],
            isset($params['payment_date']) ? $params['payment_date'] : date('Y-m-d'),
            $params['payment_method'],
            $amount_paid,
            isset($params['reference_number']) ? $params['reference_number'] : null,
            isset($params['notes']) ? $params['notes'] : null,
            $created_by
        ));
        
        $receipt_id = $pdo->lastInsertId();
        
        // Update bill status if fully paid
        $new_balance = $bill['balance'] - $amount_paid;
        if (abs($new_balance) < 0.01) { // allow for rounding
            $update_sql = "UPDATE bills SET status = 'paid' WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute(array($bill_id));
        } else if ($bill['status'] === 'draft') {
            // Move to issued if first payment received
            $update_sql = "UPDATE bills SET status = 'issued' WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute(array($bill_id));
        }
        
        $pdo->commit();
        
        // Get receipt details
        $sql = "SELECT r.*, b.bill_number, b.grand_total, u.full_name AS resident_name
                FROM receipts r
                JOIN bills b ON b.id = r.bill_id
                JOIN users u ON u.id = r.resident_user_id
                WHERE r.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($receipt_id));
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}