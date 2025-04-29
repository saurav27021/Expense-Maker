<?php
session_start();
require_once 'db.php';
require_once 'config.php';
require 'vendor/autoload.php';
use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? 'pdf';

// Get user's expense data
$stmt = $pdo->prepare("
    SELECT 
        e.description,
        e.amount,
        e.category,
        g.name as group_name,
        u.name as paid_by_name,
        e.created_at,
        CASE WHEN e.paid_by = ? THEN e.amount ELSE 
            (SELECT amount FROM expense_splits WHERE expense_id = e.id AND user_id = ?) 
        END as user_share
    FROM expenses e
    JOIN groups g ON e.group_id = g.id
    JOIN users u ON e.paid_by = u.id
    JOIN group_members gm ON e.group_id = gm.group_id
    WHERE gm.user_id = ?
    ORDER BY e.created_at DESC
");
$stmt->execute([$user_id, $user_id, $user_id]);
$expenses = $stmt->fetchAll();

if ($type === 'excel') {
    // Create Excel file
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set headers
    $sheet->setCellValue('A1', 'Date');
    $sheet->setCellValue('B1', 'Description');
    $sheet->setCellValue('C1', 'Category');
    $sheet->setCellValue('D1', 'Group');
    $sheet->setCellValue('E1', 'Amount');
    $sheet->setCellValue('F1', 'Your Share');
    $sheet->setCellValue('G1', 'Paid By');

    // Add data
    $row = 2;
    foreach ($expenses as $expense) {
        $sheet->setCellValue('A' . $row, date('Y-m-d', strtotime($expense['created_at'])));
        $sheet->setCellValue('B' . $row, $expense['description']);
        $sheet->setCellValue('C' . $row, $expense['category']);
        $sheet->setCellValue('D' . $row, $expense['group_name']);
        $sheet->setCellValue('E' . $row, $expense['amount']);
        $sheet->setCellValue('F' . $row, $expense['user_share']);
        $sheet->setCellValue('G' . $row, $expense['paid_by_name']);
        $row++;
    }

    // Auto-size columns
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Generate Excel file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="expense_report.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} else {
    // Create PDF
    $dompdf = new Dompdf();
    
    $html = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f5f5f5; }
            h1 { color: #333; }
            .total { font-weight: bold; margin-top: 20px; }
        </style>
    </head>
    <body>
        <h1>Expense Report</h1>
        <table>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Category</th>
                <th>Group</th>
                <th>Amount</th>
                <th>Your Share</th>
                <th>Paid By</th>
            </tr>';
    
    $total_amount = 0;
    $total_share = 0;
    
    foreach ($expenses as $expense) {
        $total_amount += $expense['amount'];
        $total_share += $expense['user_share'];
        
        $html .= sprintf('
            <tr>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>₹%.2f</td>
                <td>₹%.2f</td>
                <td>%s</td>
            </tr>',
            date('Y-m-d', strtotime($expense['created_at'])),
            htmlspecialchars($expense['description']),
            htmlspecialchars($expense['category']),
            htmlspecialchars($expense['group_name']),
            $expense['amount'],
            $expense['user_share'],
            htmlspecialchars($expense['paid_by_name'])
        );
    }
    
    $html .= sprintf('
        </table>
        <div class="total">
            <p>Total Amount: ₹%.2f</p>
            <p>Your Total Share: ₹%.2f</p>
        </div>
    </body>
    </html>', $total_amount, $total_share);
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    
    // Output PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment;filename="expense_report.pdf"');
    echo $dompdf->output();
    exit;
}
?>
