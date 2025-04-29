<?php
session_start();
require_once 'db.php';
require_once 'config.php';
require_once 'vendor/autoload.php';

use Razorpay\Api\Api;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['amount']) || !isset($data['receiver_id']) || !isset($data['group_id'])) {
        throw new Exception('Missing required parameters');
    }

    $amount = (float)$data['amount'];
    $receiver_id = (int)$data['receiver_id'];
    $group_id = (int)$data['group_id'];
    $payer_id = (int)$_SESSION['user_id'];

    // Initialize Razorpay
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

    // Create order
    $orderData = [
        'receipt' => 'settlement_' . time(),
        'amount' => $amount * 100, // Convert to paise
        'currency' => 'INR',
        'notes' => [
            'group_id' => $group_id,
            'payer_id' => $payer_id,
            'receiver_id' => $receiver_id
        ]
    ];

    $razorpayOrder = $api->order->create($orderData);

    // Save settlement details
    $stmt = $pdo->prepare("
        INSERT INTO settlements (
            group_id, 
            paid_by, 
            paid_to, 
            amount,
            payment_reference,
            payment_id,
            payment_status,
            status
        ) VALUES (?, ?, ?, ?, ?, NULL, NULL, 'pending')
    ");
    
    $stmt->execute([
        $group_id,
        $payer_id,
        $receiver_id,
        $amount,
        $razorpayOrder['id']
    ]);

    // Get payer details for Razorpay form
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$payer_id]);
    $payer = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'order_id' => $razorpayOrder['id'],
        'amount' => $amount * 100,
        'key' => RAZORPAY_KEY_ID,
        'name' => $payer['name'],
        'email' => $payer['email']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
