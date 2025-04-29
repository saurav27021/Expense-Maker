<?php
session_start();
require_once 'db.php';
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get all expenses for the user's groups
$stmt = $pdo->prepare("
    SELECT e.*, g.name as group_name, u.name as paid_by_name,
           e.paid_by as added_by, u.name as added_by_name,
           GROUP_CONCAT(DISTINCT gm.user_id) as participant_ids,
           GROUP_CONCAT(DISTINCT u2.name) as participant_names
    FROM expenses e
    JOIN groups g ON e.group_id = g.id
    JOIN users u ON e.paid_by = u.id
    JOIN expense_splits es ON e.id = es.expense_id
    JOIN group_members gm ON es.user_id = gm.user_id
    JOIN users u2 ON gm.user_id = u2.id
    WHERE e.group_id IN (
        SELECT group_id FROM group_members WHERE user_id = ?
    )
    GROUP BY e.id
    ORDER BY e.created_at DESC
");
$stmt->execute([$user_id]);
$expenses = $stmt->fetchAll();

// Get total spent and owed
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN e.paid_by = ? THEN e.amount ELSE 0 END) as total_spent,
        SUM(CASE WHEN es.user_id = ? THEN es.amount ELSE 0 END) as total_share
    FROM expenses e
    JOIN expense_splits es ON e.id = es.expense_id
    WHERE e.group_id IN (
        SELECT group_id FROM group_members WHERE user_id = ?
    )
");
$stmt->execute([$user_id, $user_id, $user_id]);
$totals = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense History - Expense Maker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow">
                    <div class="card-body">
                        <h2 class="card-title mb-4">
                            <i class="fas fa-history me-2"></i>
                            Expense History
                        </h2>

                        <!-- Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h5 class="card-title">Total Spent</h5>
                                        <h3>₹<?php echo number_format($totals['total_spent'] ?? 0, 2); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <h5 class="card-title">Your Share</h5>
                                        <h3>₹<?php echo number_format($totals['total_share'] ?? 0, 2); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h5 class="card-title">Balance</h5>
                                        <h3>₹<?php echo number_format(($totals['total_spent'] ?? 0) - ($totals['total_share'] ?? 0), 2); ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Expense List -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Group</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Added By</th>
                                        <th>Participants</th>
                                        <th>Your Share</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($expenses): ?>
                                        <?php foreach ($expenses as $expense): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($expense['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($expense['group_name']); ?></td>
                                                <td><?php echo htmlspecialchars($expense['description']); ?></td>
                                                <td>₹<?php echo number_format($expense['amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($expense['added_by_name']); ?></td>
                                                <td>
                                                    <?php 
                                                    $names = explode(',', $expense['participant_names']);
                                                    echo implode(', ', array_map('htmlspecialchars', $names));
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $participant_ids = explode(',', $expense['participant_ids']);
                                                    $share = $expense['amount'] / count($participant_ids);
                                                    echo '₹' . number_format($share, 2);
                                                    ?>
                                                </td>
                                                <td>
                                                    <a href="view-expense.php?id=<?php echo $expense['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($expense['added_by'] == $user_id): ?>
                                                        <a href="edit-expense.php?id=<?php echo $expense['id']; ?>" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No expenses found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
