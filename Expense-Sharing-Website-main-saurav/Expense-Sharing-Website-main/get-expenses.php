<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';

$user_id = $_SESSION['user_id'];

$sql = "SELECT e.*, 
       (SELECT GROUP_CONCAT(CONCAT(s.amount, ':', s.status, ':', COALESCE(s.payment_reference, ''), ':', COALESCE(s.payment_method, '')) SEPARATOR '|')
        FROM settlements s 
        WHERE (s.from_user_id = e.paid_by OR s.to_user_id = e.paid_by) 
        AND s.group_id = e.group_id) as settlement_info
       FROM expenses e 
       WHERE e.paid_by = ? 
       ORDER BY e.date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$expenses = [];
while ($row = $result->fetch_assoc()) {
    $expenses[] = $row;
}

echo json_encode($expenses);
?>
