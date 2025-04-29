<?php
// Get total amount owed between users
$stmt = $pdo->prepare("
    WITH user_balances AS (
        SELECT 
            es.user_id,
            SUM(CASE 
                WHEN es.user_id = e.paid_by THEN e.amount
                ELSE -es.amount 
            END) as balance
        FROM expense_splits es
        JOIN expenses e ON es.expense_id = e.id
        WHERE e.group_id = ?
        GROUP BY es.user_id
    )
    SELECT 
        u1.id as payer_id,
        u1.name as payer_name,
        u2.id as receiver_id,
        u2.name as receiver_name,
        ABS(ub1.balance) as amount
    FROM user_balances ub1
    JOIN user_balances ub2 ON ub1.balance < 0 AND ub2.balance > 0
    JOIN users u1 ON ub1.user_id = u1.id
    JOIN users u2 ON ub2.user_id = u2.id
    WHERE ABS(ub1.balance) > 0
");
$stmt->execute([$group_id]);
$settlements = $stmt->fetchAll();
?>

<?php if (!empty($settlements)): ?>
    <div id="settlementsSection" class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-money-bill-wave me-2"></i>
                Settle Up
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>From</th>
                            <th>To</th>
                            <th>Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($settlements as $settlement): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($settlement['payer_name']); ?></td>
                                <td><?php echo htmlspecialchars($settlement['receiver_name']); ?></td>
                                <td>₹<?php echo number_format($settlement['amount'], 2); ?></td>
                                <td>
                                    <?php if ((int)$settlement['payer_id'] === (int)$_SESSION['user_id']): ?>
                                        <button class="btn btn-primary btn-sm settle-btn" 
                                                data-receiver-id="<?php echo $settlement['receiver_id']; ?>"
                                                data-amount="<?php echo $settlement['amount']; ?>"
                                                data-group-id="<?php echo $group_id; ?>"
                                                data-receiver-name="<?php echo htmlspecialchars($settlement['receiver_name']); ?>">
                                            <i class="fas fa-credit-card me-1"></i> Pay
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">Waiting</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.settle-btn').forEach(button => {
            button.addEventListener('click', function() {
                const data = {
                    receiver_id: this.dataset.receiverId,
                    amount: this.dataset.amount,
                    group_id: this.dataset.groupId
                };

                // Create Razorpay order
                fetch('create-razorpay-order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Initialize Razorpay payment
                        const options = {
                            key: data.key,
                            amount: data.amount,
                            currency: 'INR',
                            name: 'Expense Maker',
                            description: 'Settlement Payment to ' + this.dataset.receiverName,
                            order_id: data.order_id,
                            prefill: {
                                name: data.name,
                                email: data.email
                            },
                            handler: function(response) {
                                // Verify payment
                                fetch('verify-payment.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                    },
                                    body: JSON.stringify({
                                        razorpay_payment_id: response.razorpay_payment_id,
                                        razorpay_order_id: response.razorpay_order_id,
                                        razorpay_signature: response.razorpay_signature
                                    })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        window.location.reload();
                                    } else {
                                        alert('Payment verification failed: ' + data.error);
                                    }
                                });
                            },
                            theme: {
                                color: '#3498db'
                            }
                        };
                        const rzp = new Razorpay(options);
                        rzp.open();
                    } else {
                        alert('Error creating payment: ' + data.error);
                    }
                });
            });
        });
    </script>
<?php endif; ?>
