<?php


header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || strtoupper($_SESSION['user']['role'] ?? '') !== 'ADMIN') {
    http_response_code(403);
    die('Access denied. Admin privileges required.');
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/functions_audit.php';

$pdo = $pdo ?? $conn ?? Database::getInstance()->getConnection();

$fpdf_path = __DIR__ . '/../../../fpdf/fpdf.php';
if (!file_exists($fpdf_path)) {
    die('<b>FPDF library not found.</b><br>
         Expected at: ' . htmlspecialchars($fpdf_path) . '<br><br>
         <b>How to fix:</b> Ensure the FPDF library is uploaded to your project directory.');
}
require_once $fpdf_path;

$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 1000) : 500;

$filters = [
    'role'   => !empty($_GET['role'])   ? $_GET['role']   : null,
    'action' => !empty($_GET['action']) ? $_GET['action'] : null,
    'from'   => !empty($_GET['from'])   ? $_GET['from']   : null,
    'to'     => !empty($_GET['to'])     ? $_GET['to']     : null
];

$logs = fetch_recent_audit_logs(
    $limit, 
    $filters['role'],
    $filters['action'],
    $filters['from'],
    $filters['to']
);

$logs = array_filter($logs, function($log) {
    return strtoupper($log['role'] ?? '') !== 'ADMIN';
});

class AuditPDF extends FPDF {
    protected $col_widths = [15, 35, 30, 25, 45, 80, 47];

    function Header() {
        // Main Header Banner
        $this->SetFillColor(13, 148, 136); // Teal color
        $this->Rect(10, 10, 277, 20, 'F');
        
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 18);
        $this->Cell(0, 20, 'PAWAdopt - System Audit Report', 0, 1, 'C');
        
        $this->Ln(5);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetTextColor(120, 120, 120);
        $this->SetFont('Arial', 'I', 8);
        
        // Page numbers
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
        
        // Timestamp & branding
        $this->SetX(10);
        $this->Cell(0, 10, 'Generated: ' . date('Y-m-d H:i:s') . ' | PAWAdopt Security System', 0, 0, 'L');
    }
    
    function TableHeader() {
        $this->SetFillColor(241, 245, 249);
        $this->SetTextColor(30, 41, 59);
        $this->SetDrawColor(203, 213, 225);
        $this->SetFont('Arial', 'B', 9);
        
        $headers = ['#', 'Date/Time', 'Username', 'Role', 'Action', 'Details', 'IP Address'];
        
        foreach ($this->col_widths as $i => $w) {
            $this->Cell($w, 10, $headers[$i], 1, 0, 'C', true);
        }
        $this->Ln();
    }
    
    function TableRow($row, $alternate = false) {
        if ($alternate) {
            $this->SetFillColor(248, 250, 252);
        } else {
            $this->SetFillColor(255, 255, 255);
        }
        
        $this->SetTextColor(51, 65, 85);
        $this->SetFont('Arial', '', 8);
        
        $displayAction = function_exists('format_action_name') ? format_action_name($row['action']) : $row['action'];
        $details = $row['details'] ?: 'No additional details recorded.';
        if (strlen($details) > 65) $details = substr($details, 0, 62) . '...';
        
        $this->Cell($this->col_widths[0], 8, $row['id'], 1, 0, 'C', true);
        $this->Cell($this->col_widths[1], 8, date('Y-m-d H:i', strtotime($row['created_at'])), 1, 0, 'L', true);
        $this->Cell($this->col_widths[2], 8, substr($row['username'], 0, 18), 1, 0, 'L', true);
        $this->Cell($this->col_widths[3], 8, $row['role'], 1, 0, 'C', true);
        $this->Cell($this->col_widths[4], 8, substr($displayAction, 0, 25), 1, 0, 'L', true);
        $this->Cell($this->col_widths[5], 8, $details, 1, 0, 'L', true);
        $this->Cell($this->col_widths[6], 8, $row['ip_address'], 1, 0, 'L', true);
        $this->Ln();
    }

    function StatisticsSection($pdo, $days = 30) {
        $this->Ln(10);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(15, 23, 42);
        $this->Cell(0, 10, 'Summary Statistics (Exclude Admins)', 0, 1);
        
        $this->SetDrawColor(13, 148, 136);
        $this->Line($this->GetX(), $this->GetY(), $this->GetX() + 277, $this->GetY());
        $this->Ln(3);

        try {
            // Stats by Role
            $stmt = $pdo->prepare('SELECT role, COUNT(*) as count FROM audit_logs WHERE role != "ADMIN" GROUP BY role');
            $stmt->execute();
            $role_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(0, 8, 'Records by Role:', 0, 1);
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(100, 100, 100);
            
            foreach ($role_counts as $role => $count) {
                $this->Cell(10); // Indent
                $this->Cell(0, 6, ' - ' . ucfirst(strtolower($role)) . ': ' . $count . ' logs recorded.', 0, 1);
            }
            
            // Stats by Action
            $this->Ln(4);
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor(15, 23, 42);
            $this->Cell(0, 8, 'Top System Actions:', 0, 1);
            
            $stmt = $pdo->prepare('SELECT action, COUNT(*) as count FROM audit_logs WHERE role != "ADMIN" GROUP BY action ORDER BY count DESC LIMIT 5');
            $stmt->execute();
            $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(100, 100, 100);
            foreach ($actions as $act) {
                $this->Cell(10);
                $this->Cell(0, 6, ' - ' . (function_exists('format_action_name') ? format_action_name($act['action']) : $act['action']) . ': ' . $act['count'], 0, 1);
            }

        } catch (Exception $e) {
            $this->SetFont('Arial', 'I', 9);
            $this->Cell(0, 10, 'Statistical analysis currently unavailable.', 0, 1);
        }
    }
}

// 7. PDF GENERATION
if (ob_get_length()) ob_end_clean(); // Prevent output corruption

$pdf = new AuditPDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

// metadata section
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(0, 8, 'Report Identifier: AUD-' . date('YmdHis'), 0, 1, 'R');
$pdf->Cell(0, 6, 'Generated By: ' . htmlspecialchars($_SESSION['username'] ?? 'Admin'), 0, 1, 'R');
$pdf->Cell(0, 6, 'Total Non-Admin Records: ' . count($logs), 0, 1, 'R');

// Filter display
if (array_filter($filters)) {
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'I', 9);
    $active_filters = [];
    foreach ($filters as $k => $v) if ($v) $active_filters[] = ucfirst($k) . ': ' . $v;
    $pdf->Cell(0, 6, 'Active Filters: ' . implode(' | ', $active_filters), 0, 1, 'L');
}

$pdf->Ln(5);

// 8. DATA TABLE RENDER
$pdf->TableHeader();

$alternate = false;
if (!empty($logs)) {
    foreach ($logs as $log) {
        $pdf->TableRow($log, $alternate);
        $alternate = !$alternate;
    }
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(277, 20, 'No audit logs were found matching your criteria.', 1, 1, 'C');
}

// 9. STATISTICS RENDER
if ($pdf->GetY() > 150) $pdf->AddPage(); // Page break if running out of space
$pdf->StatisticsSection($pdo);

// 10. FINAL OUTPUT
if (ob_get_length()) ob_end_clean();
$filename = 'PAWAdopt_Audit_Report_' . date('Y-m-d_H-i') . '.pdf';
$pdf->Output('D', $filename);
exit;
