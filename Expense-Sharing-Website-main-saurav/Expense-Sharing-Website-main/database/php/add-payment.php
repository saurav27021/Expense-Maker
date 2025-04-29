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

$user_id = $_SESSION['user_id'];
$error = null;
$success = null;

// Initialize Razorpay
$razorpay = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

// Get user's groups
$stmt = $pdo->prepare("
    SELECT g.* 
    FROM groups g 
    INNER JOIN group_members gm ON g.id = gm.group_id 
    WHERE gm.user_id = ?
");
$stmt->execute([$user_id]);
$groups = $stmt->fetchAll();

// Get user info
$stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $group_id = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        
        if (!$group_id || !$amount || $amount <= 0) {
            throw new Exception("Invalid input parameters");
        }

        // Create Razorpay Order
        $orderData = [
            'receipt' => 'exp_' . time(),
            'amount' => $amount * 100, // Convert to paise
            'currency' => 'INR',
            'notes' => [
                'group_id' => $group_id,
                'user_id' => $user_id,
                'description' => $description
            ]
        ];
        
        $razorpayOrder = $razorpay->order->create($orderData);

        // Store order details in session for verification
        $_SESSION['razorpay_order_id'] = $razorpayOrder['id'];
        $_SESSION['payment_details'] = [
            'group_id' => $group_id,
            'amount' => $amount,
            'description' => $description
        ];

        $success = [
            'order_id' => $razorpayOrder['id'],
            'amount' => $amount * 100,
            'currency' => 'INR',
            'name' => $user['name'],
            'email' => $user['email']
        ];

    } catch (Exception $e) {
        error_log("Payment Error: " . $e->getMessage());
        $error = "Failed to create payment. Please try again.";
    }
}

include 'header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="card-title mb-0">Add Payment</h3>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form id="payment-form" method="POST">
                        <div class="mb-3">
                            <label for="group" class="form-label">Select Group</label>
                            <select class="form-select" id="group" name="group_id" required>
                                <option value="">Choose a group...</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>">
                                        <?php echo htmlspecialchars($group['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount (₹)</label>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   step="0.01" min="0.01" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description" 
                                   maxlength="255" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary" id="submit-btn">
                                Proceed to Pay
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($success): ?>
<!-- Razorpay payment form will be shown here -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
var options = {
    "key": "<?php echo RAZORPAY_KEY_ID; ?>",
    "amount": "<?php echo $success['amount']; ?>",
    "currency": "<?php echo $success['currency']; ?>",
    "name": "Expense Maker",
    "description": "Group Payment",
    "image": "<?php echo SITE_URL; ?>/assets/img/logo.png",
    "order_id": "<?php echo $success['order_id']; ?>",
    "handler": function (response) {
        // Send payment verification details to the server
        window.location.href = 'verify-payment.php?razorpay_payment_id=' + response.razorpay_payment_id + 
                             '&razorpay_order_id=' + response.razorpay_order_id + 
                             '&razorpay_signature=' + response.razorpay_signature;
    },
    "prefill": {
        "name": "<?php echo htmlspecialchars($success['name']); ?>",
        "email": "<?php echo htmlspecialchars($success['email']); ?>"
    },
    "theme": {
        "color": "#0d6efd"
    }
};
var rzp = new Razorpay(options);
document.getElementById('submit-btn').onclick = function(e) {
    rzp.open();
    e.preventDefault();
}
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
