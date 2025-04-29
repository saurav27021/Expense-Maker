<?php
session_start();
require_once 'db.php';
require_once 'config.php';
require_once 'vendor/autoload.php';

use Razorpay\Api\Api;

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

// Initialize Razorpay
$api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create_settlement'])) {
            $payer_id = (int)$_SESSION['user_id'];
            $receiver_id = (int)$_POST['receiver_id'];
            $amount = (float)$_POST['amount'];
            $group_id = (int)$_POST['group_id'];

            // Create Razorpay Order
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
                INSERT INTO settlements (group_id, payer_id, receiver_id, amount, razorpay_order_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $group_id,
                $payer_id,
                $receiver_id,
                $amount,
                $razorpayOrder['id']
            ]);

            // Return order details for the payment form
            echo json_encode([
                'success' => true,
                'order_id' => $razorpayOrder['id'],
                'amount' => $amount * 100,
                'key' => RAZORPAY_KEY_ID
            ]);
            exit;
        }

        // Handle payment verification
        if (isset($_POST['verify_payment'])) {
            $payment_id = $_POST['razorpay_payment_id'];
            $order_id = $_POST['razorpay_order_id'];
            $signature = $_POST['razorpay_signature'];

            $api->utility->verifyPaymentSignature([
                'razorpay_payment_id' => $payment_id,
                'razorpay_order_id' => $order_id,
                'razorpay_signature' => $signature
            ]);

            // Update settlement status
            $stmt = $pdo->prepare("
                UPDATE settlements 
                SET status = 'completed', 
                    razorpay_payment_id = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE razorpay_order_id = ?
            ");
            $stmt->execute([$payment_id, $order_id]);

            echo json_encode(['success' => true, 'message' => 'Payment verified successfully']);
            exit;
        }

    } catch (Exception $e) {
        error_log('Settlement error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Get settlement details if order_id is provided
if (isset($_GET['order_id'])) {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               u1.name as payer_name,
               u2.name as receiver_name,
               g.name as group_name
        FROM settlements s
        JOIN users u1 ON s.payer_id = u1.id
        JOIN users u2 ON s.receiver_id = u2.id
        JOIN groups g ON s.group_id = g.id
        WHERE s.razorpay_order_id = ?
    ");
    $stmt->execute([$_GET['order_id']]);
    $settlement = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settle Payment - Expense Maker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-money-bill-wave me-2"></i>
                            Settle Payment
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($settlement)): ?>
                            <div class="settlement-details mb-4">
                                <h5>Settlement Details</h5>
                                <p><strong>Group:</strong> <?php echo htmlspecialchars($settlement['group_name']); ?></p>
                                <p><strong>Amount:</strong> ₹<?php echo number_format($settlement['amount'], 2); ?></p>
                                <p><strong>To:</strong> <?php echo htmlspecialchars($settlement['receiver_name']); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?php echo $settlement['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($settlement['status']); ?>
                                    </span>
                                </p>
                            </div>

                            <?php if ($settlement['status'] === 'pending'): ?>
                                <button id="payButton" class="btn btn-primary">
                                    <i class="fas fa-credit-card me-2"></i>Pay Now
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        <?php if (isset($settlement) && $settlement['status'] === 'pending'): ?>
        document.getElementById('payButton').onclick = function() {
            var options = {
                key: '<?php echo RAZORPAY_KEY_ID; ?>',
                amount: <?php echo $settlement['amount'] * 100; ?>,
                currency: 'INR',
                name: 'Expense Maker',
                description: 'Settlement Payment',
                order_id: '<?php echo $settlement['razorpay_order_id']; ?>',
                handler: function(response) {
                    // Verify payment on server
                    fetch('settle-payment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            'verify_payment': '1',
                            'razorpay_payment_id': response.razorpay_payment_id,
                            'razorpay_order_id': response.razorpay_order_id,
                            'razorpay_signature': response.razorpay_signature
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert('Payment verification failed: ' + data.message);
                        }
                    });
                },
                prefill: {
                    name: '<?php echo htmlspecialchars($_SESSION['name']); ?>',
                    email: '<?php echo htmlspecialchars($_SESSION['email']); ?>'
                },
                theme: {
                    color: '#3498db'
                }
            };
            var rzp = new Razorpay(options);
            rzp.open();
        };
        <?php endif; ?>
    </script>
</body>
</html>
