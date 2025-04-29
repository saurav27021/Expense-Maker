<?php
session_start();
require_once 'db.php';
require_once 'config.php';
require_once 'includes/settlement_calculator.php';
require_once 'includes/razorpay-config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

if (!$group_id) {
    header('Location: dashboard.php');
    exit();
}

// Get group members
$stmt = $pdo->prepare("
    SELECT gm.user_id, u.name 
    FROM group_members gm
    JOIN users u ON u.id = gm.user_id
    WHERE gm.group_id = ?
");
$stmt->execute([$group_id]);
$members = $stmt->fetchAll();

// Get all expenses for the group
$stmt = $pdo->prepare("
    SELECT e.*, es.user_id as split_user_id, es.amount as split_amount
    FROM expenses e
    JOIN expense_splits es ON e.id = es.expense_id
    WHERE e.group_id = ?
");
$stmt->execute([$group_id]);
$expenses_data = $stmt->fetchAll();

// Organize expenses with their splits
$expenses = [];
foreach ($expenses_data as $row) {
    if (!isset($expenses[$row['id']])) {
        $expenses[$row['id']] = [
            'id' => $row['id'],
            'paid_by' => $row['paid_by'],
            'amount' => $row['amount'],
            'splits' => []
        ];
    }
    $expenses[$row['id']]['splits'][] = [
        'user_id' => $row['split_user_id'],
        'amount' => $row['split_amount']
    ];
}

// Calculate settlements
$calculator = new SettlementCalculator();
$calculator->calculateBalances($expenses, $members);
$settlements = $calculator->simplifyDebts();
$balances = $calculator->getBalances();

// Handle settlement recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_settlement'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO settlements (group_id, from_user_id, to_user_id, amount, date)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $group_id,
            $_POST['from_user_id'],
            $_POST['to_user_id'],
            $_POST['amount']
        ]);
        
        $pdo->commit();
        header('Location: settle-up.php?group_id=' . $group_id . '&success=1');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Failed to record settlement';
    }
}

// Get user names for display
$user_names = [];
foreach ($members as $member) {
    $user_names[$member['user_id']] = $member['name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settle Up - Expense Maker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Settlement Summary</h2>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Settlement recorded successfully!</div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <h3>Current Balances</h3>
                <div class="list-group mb-4">
                    <?php foreach ($balances as $user_id => $balance): ?>
                        <div class="list-group-item">
                            <strong><?php echo htmlspecialchars($user_names[$user_id]); ?></strong>:
                            <span class="<?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                ₹<?php echo number_format(abs($balance), 2); ?>
                                <?php echo $balance >= 0 ? 'to receive' : 'to pay'; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="col-md-6">
                <h3>Suggested Settlements</h3>
                <?php if (empty($settlements)): ?>
                    <div class="alert alert-info">All settled up! No payments needed.</div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($settlements as $settlement): ?>
                            <div class="list-group-item">
                                <form method="post" class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($user_names[$settlement['from_user_id']]); ?></strong>
                                        should pay
                                        <strong>₹<?php echo number_format($settlement['amount'], 2); ?></strong>
                                        to
                                        <strong><?php echo htmlspecialchars($user_names[$settlement['to_user_id']]); ?></strong>
                                    </div>
                                    <input type="hidden" name="from_user_id" value="<?php echo $settlement['from_user_id']; ?>">
                                    <input type="hidden" name="to_user_id" value="<?php echo $settlement['to_user_id']; ?>">
                                    <input type="hidden" name="amount" value="<?php echo $settlement['amount']; ?>">
                                    <button type="button" class="btn btn-primary btn-sm pay-now" 
                                        data-amount="<?php echo $settlement['amount']; ?>" 
                                        data-from="<?php echo $settlement['from_user_id']; ?>" 
                                        data-to="<?php echo $settlement['to_user_id']; ?>"
                                        data-group="<?php echo $group_id; ?>"
                                        data-name="<?php echo htmlspecialchars($user_names[$settlement['to_user_id']]); ?>">Pay Now</button>
                                    <button type="submit" name="record_settlement" class="btn btn-success btn-sm">Record Manual Payment</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.querySelectorAll('.pay-now').forEach(button => {
        button.addEventListener('click', function() {
            const amount = this.dataset.amount;
            const fromUser = this.dataset.from;
            const toUser = this.dataset.to;
            const groupId = this.dataset.group;
            const toName = this.dataset.name;

            const options = {
                key: '<?php echo RAZORPAY_KEY_ID; ?>', 
                amount: Math.round(amount * 100), // Amount in paise
                currency: 'INR',
                name: 'Expense Maker',
                description: 'Payment to ' + toName,
                handler: function(response) {
                    // Verify payment on server
                    fetch('verify-payment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_order_id: response.razorpay_order_id,
                            razorpay_signature: response.razorpay_signature,
                            amount: amount,
                            from_user_id: fromUser,
                            to_user_id: toUser,
                            group_id: groupId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = 'settle-up.php?group_id=' + groupId + '&success=1';
                        } else {
                            alert('Payment verification failed: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Payment verification failed. Please contact support.');
                    });
                },
                prefill: {
                    name: '<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>',
                    email: '<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>'
                },
                theme: {
                    color: '#3498db'
                }
            };

            const rzp = new Razorpay(options);
            rzp.open();
        });
    });
    </script>
</body>
</html>