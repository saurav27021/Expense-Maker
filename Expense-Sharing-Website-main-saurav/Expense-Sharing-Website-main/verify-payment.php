<?php
session_start();
require_once 'db.php';
require_once 'config.php';
require_once 'vendor/autoload.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['razorpay_payment_id']) || 
        !isset($data['razorpay_order_id']) || 
        !isset($data['razorpay_signature'])) {
        throw new Exception('Missing payment verification parameters');
    }

    // Initialize Razorpay
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

    // Verify signature
    $attributes = [
        'razorpay_payment_id' => $data['razorpay_payment_id'],
        'razorpay_order_id' => $data['razorpay_order_id'],
        'razorpay_signature' => $data['razorpay_signature']
    ];

    $api->utility->verifyPaymentSignature($attributes);

    // Get settlement details
    $stmt = $pdo->prepare("
        SELECT s.*, u.username as recipient_name, u.email as recipient_email 
        FROM settlements s
        JOIN users u ON s.recipient_id = u.id 
        WHERE s.payment_reference = ? AND s.status = 'pending'
    ");
    $stmt->execute([$data['razorpay_order_id']]);
    $settlement = $stmt->fetch();

    if (!$settlement) {
        throw new Exception('Invalid or already processed settlement');
    }

    // Update settlement status
    $stmt = $pdo->prepare("
        UPDATE settlements 
        SET status = 'completed',
            payment_id = ?,
            payment_status = 'success',
            completed_at = CURRENT_TIMESTAMP,
            payment_details = ?
        WHERE id = ?
    ");

    $payment_details = json_encode([
        'payment_id' => $data['razorpay_payment_id'],
        'order_id' => $data['razorpay_order_id'],
        'recipient_name' => $settlement['recipient_name'],
        'recipient_email' => $settlement['recipient_email'],
        'amount' => $settlement['amount'],
        'completed_at' => date('Y-m-d H:i:s')
    ]);

    $stmt->execute([$data['razorpay_payment_id'], $payment_details, $settlement['id']]);

    // Add to payment history
    $stmt = $pdo->prepare("
        INSERT INTO payment_history (
            settlement_id, 
            payer_id, 
            recipient_id, 
            amount, 
            payment_id, 
            payment_details
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $settlement['id'],
        $_SESSION['user_id'],
        $settlement['recipient_id'],
        $settlement['amount'],
        $data['razorpay_payment_id'],
        $payment_details
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Payment verified successfully',
        'payment_details' => json_decode($payment_details)
    ]);

} catch (SignatureVerificationError $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid payment signature'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
