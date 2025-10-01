<?php
// /api/v1/lib/pdf_export.php
// PDF generation for bills and receipts using FPDF

require_once __DIR__ . '/../../../lib/fpdf.php'; // Adjust path to your FPDF location

/**
 * Base PDF class with common header/footer
 */
class ElderberryPDF extends FPDF {
    protected $facility_name = 'Elderberry Place';
    protected $facility_address = '123 Elderberry Lane, Tranquility VIC 3901';
    protected $facility_phone = '(03) 9123 4567';
    protected $facility_email = 'admin@elderberryplace.org.au';
    
    function Header() {
        // Logo (if you have one - optional)
        // $this->Image('logo.png', 10, 6, 30);
        
        // Facility name
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(40, 70, 150);
        $this->Cell(0, 10, $this->facility_name, 0, 1, 'C');
        
        // Facility details
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 5, $this->facility_address, 0, 1, 'C');
        $this->Cell(0, 5, 'Phone: ' . $this->facility_phone . ' | Email: ' . $this->facility_email, 0, 1, 'C');
        
        // Line break
        $this->Ln(5);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

/**
 * Generate Bill PDF
 * 
 * @param array $bill Bill details from get_bill_details()
 * @param string $output_mode 'I' = inline, 'D' = download, 'S' = string
 * @return string|void PDF content or output directly
 */
function generate_bill_pdf($bill, $output_mode = 'I') {
    $pdf = new ElderberryPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    // Document title
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 10, 'TAX INVOICE', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Bill information box (left side)
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(95, 6, 'Bill Number: ' . $bill['bill_number'], 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(95, 6, 'Date: ' . format_date($bill['bill_date']), 0, 1, 'R');
    
    if ($bill['due_date']) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(95, 6, 'Status: ' . strtoupper($bill['status']), 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(95, 6, 'Due Date: ' . format_date($bill['due_date']), 0, 1, 'R');
    }
    
    if ($bill['period_start'] && $bill['period_end']) {
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->Cell(0, 5, 'Billing Period: ' . format_date($bill['period_start']) . ' to ' . format_date($bill['period_end']), 0, 1);
    }
    
    $pdf->Ln(5);
    
    // Bill To section
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 7, 'BILL TO', 0, 1, 'L', true);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, $bill['resident_name'], 0, 1);
    if ($bill['room_number']) {
        $pdf->Cell(0, 5, 'Room: ' . $bill['room_number'], 0, 1);
    }
    if ($bill['resident_email']) {
        $pdf->Cell(0, 5, 'Email: ' . $bill['resident_email'], 0, 1);
    }
    
    $pdf->Ln(8);
    
    // Line items table header
    $pdf->SetFillColor(40, 70, 150);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(90, 7, 'Description', 1, 0, 'L', true);
    $pdf->Cell(30, 7, 'Quantity', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Unit Price', 1, 0, 'R', true);
    $pdf->Cell(40, 7, 'Total', 1, 1, 'R', true);
    
    // Line items
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetFillColor(250, 250, 250);
    $fill = false;
    
    foreach ($bill['items'] as $item) {
        $pdf->Cell(90, 6, $item['description'], 1, 0, 'L', $fill);
        $pdf->Cell(30, 6, number_format($item['quantity'], 2), 1, 0, 'C', $fill);
        $pdf->Cell(30, 6, '$' . number_format($item['unit_price'], 2), 1, 0, 'R', $fill);
        $pdf->Cell(40, 6, '$' . number_format($item['total_price'], 2), 1, 1, 'R', $fill);
        $fill = !$fill;
    }
    
    $pdf->Ln(3);
    
    // Totals section
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(150, 6, '', 0, 0);
    $pdf->Cell(30, 6, 'Subtotal:', 0, 0, 'R');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(10, 6, '$' . number_format($bill['subtotal_medications'] + $bill['subtotal_services'], 2), 0, 1, 'R');
    
    if ($bill['tax_amount'] > 0) {
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(150, 6, '', 0, 0);
        $pdf->Cell(30, 6, 'Tax (GST):', 0, 0, 'R');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(10, 6, '$' . number_format($bill['tax_amount'], 2), 0, 1, 'R');
    }
    
    if ($bill['discount_amount'] > 0) {
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(150, 6, '', 0, 0);
        $pdf->Cell(30, 6, 'Discount:', 0, 0, 'R');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(10, 6, '-$' . number_format($bill['discount_amount'], 2), 0, 1, 'R');
    }
    
    // Grand total
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetFillColor(40, 70, 150);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(150, 8, '', 0, 0);
    $pdf->Cell(30, 8, 'TOTAL:', 1, 0, 'R', true);
    $pdf->Cell(10, 8, '$' . number_format($bill['grand_total'], 2), 1, 1, 'R', true);
    
    $pdf->SetTextColor(0, 0, 0);
    
    // Payment status
    if (!empty($bill['receipts'])) {
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, 'Payment History:', 0, 1);
        $pdf->SetFont('Arial', '', 9);
        foreach ($bill['receipts'] as $r) {
            $pdf->Cell(0, 5, format_date($r['payment_date']) . ' - ' . ucfirst($r['payment_method']) . ': $' . number_format($r['amount_paid'], 2) . ' (Ref: ' . $r['receipt_number'] . ')', 0, 1);
        }
        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, 'Amount Paid: $' . number_format($bill['amount_paid'], 2), 0, 1);
        $pdf->Cell(0, 6, 'Balance Due: $' . number_format($bill['balance'], 2), 0, 1);
    }
    
    // Notes
    if ($bill['notes']) {
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->MultiCell(0, 5, 'Notes: ' . $bill['notes']);
    }
    
    // Footer message
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->MultiCell(0, 4, "Thank you for choosing Elderberry Place. For billing inquiries, please contact our accounts department.\nPayment is due within 30 days of invoice date unless otherwise specified.");
    
    // Output
    $filename = 'Bill_' . $bill['bill_number'] . '.pdf';
    return $pdf->Output($output_mode, $filename);
}

/**
 * Generate Receipt PDF
 * 
 * @param array $receipt Receipt details
 * @param array $bill Bill details (optional, for context)
 * @param string $output_mode 'I' = inline, 'D' = download, 'S' = string
 * @return string|void PDF content or output directly
 */
function generate_receipt_pdf($receipt, $bill = null, $output_mode = 'I') {
    $pdf = new ElderberryPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    // Document title
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->SetTextColor(0, 150, 70);
    $pdf->Cell(0, 10, 'PAYMENT RECEIPT', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Receipt information
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 7, 'Receipt Number: ' . $receipt['receipt_number'], 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Payment Date: ' . format_date($receipt['payment_date']), 0, 1);
    $pdf->Cell(0, 6, 'Payment Method: ' . ucfirst(str_replace('_', ' ', $receipt['payment_method'])), 0, 1);
    
    if ($receipt['reference_number']) {
        $pdf->Cell(0, 6, 'Reference Number: ' . $receipt['reference_number'], 0, 1);
    }
    
    $pdf->Ln(5);
    
    // Received from section
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 7, 'RECEIVED FROM', 0, 1, 'L', true);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, $receipt['resident_name'], 0, 1);
    if (!empty($receipt['room_number'])) {
        $pdf->Cell(0, 5, 'Room: ' . $receipt['room_number'], 0, 1);
    }
    if (!empty($receipt['resident_email'])) {
        $pdf->Cell(0, 5, 'Email: ' . $receipt['resident_email'], 0, 1);
    }
    
    $pdf->Ln(8);
    
    // Payment details box
    $pdf->SetFillColor(250, 255, 250);
    $pdf->SetDrawColor(0, 150, 70);
    $pdf->SetLineWidth(0.5);
    $pdf->Rect($pdf->GetX(), $pdf->GetY(), 190, 40, 'D');
    
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 7, 'Payment Details', 0, 1, 'C');
    $pdf->Ln(2);
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(95, 6, 'Bill Number:', 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(95, 6, $receipt['bill_number'], 0, 1);
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(95, 6, 'Bill Total:', 0, 0);
    $pdf->Cell(95, 6, '$' . number_format($receipt['grand_total'], 2), 0, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(95, 8, 'Amount Paid:', 0, 0);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(0, 150, 70);
    $pdf->Cell(95, 8, '$' . number_format($receipt['amount_paid'], 2), 0, 1);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(5);
    
    // Show bill summary if provided
    if ($bill && !empty($bill['items'])) {
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(0, 7, 'BILL SUMMARY', 0, 1, 'L', true);
        
        $pdf->SetFont('Arial', '', 9);
        foreach ($bill['items'] as $item) {
            $pdf->Cell(140, 5, $item['description'], 0, 0);
            $pdf->Cell(50, 5, '$' . number_format($item['total_price'], 2), 0, 1, 'R');
        }
    }
    
    // Notes
    if ($receipt['notes']) {
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->MultiCell(0, 5, 'Notes: ' . $receipt['notes']);
    }
    
    // Signature line
    $pdf->Ln(15);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(95, 5, '', 0, 0);
    $pdf->Cell(95, 5, '_________________________________', 0, 1, 'C');
    $pdf->Cell(95, 5, '', 0, 0);
    $pdf->Cell(95, 5, 'Authorized Signature', 0, 1, 'C');
    
    if (!empty($receipt['created_by_name'])) {
        $pdf->Cell(95, 5, '', 0, 0);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(95, 5, 'Processed by: ' . $receipt['created_by_name'], 0, 1, 'C');
    }
    
    // Footer message
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->MultiCell(0, 4, "This is an official receipt for payment received. Please retain for your records.\nFor inquiries regarding this receipt, please contact our accounts department.");
    
    // Output
    $filename = 'Receipt_' . $receipt['receipt_number'] . '.pdf';
    return $pdf->Output($output_mode, $filename);
}

/**
 * Helper function to format dates
 */
function format_date($date) {
    if (!$date) return '';
    $dt = new DateTime($date);
    return $dt->format('d M Y');
}