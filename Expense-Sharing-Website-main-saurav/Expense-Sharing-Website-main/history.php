<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user's expense history
$stmt = $pdo->prepare("
    SELECT e.*, g.name as group_name, u.username as paid_by_name 
    FROM expenses e 
    JOIN groups g ON e.group_id = g.id 
    JOIN users u ON e.paid_by = u.id 
    WHERE e.group_id IN (
        SELECT group_id 
        FROM group_members 
        WHERE user_id = ?
    ) 
    ORDER BY e.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$expenses = $stmt->fetchAll();

// Get settlements
$stmt = $pdo->prepare("
    SELECT es.*, e.description, g.name as group_name, u.username as paid_to_name 
    FROM expense_splits es 
    JOIN expenses e ON es.expense_id = e.id 
    JOIN groups g ON e.group_id = g.id 
    JOIN users u ON e.paid_by = u.id 
    WHERE es.user_id = ? AND es.paid_at IS NOT NULL 
    ORDER BY es.paid_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$settlements = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - Expense Maker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary-blue: #0d6efd;
        }
        
        .history-tab {
            cursor: pointer;
            padding: 1rem;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .history-tab.active {
            border-bottom-color: var(--primary-blue);
            color: var(--primary-blue);
        }
        
        .history-content {
            display: none;
        }
        
        .history-content.active {
            display: block;
        }

        .expense-card {
            transition: transform 0.3s ease;
        }

        .expense-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="dashboard-body">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <i class="fas fa-wallet"></i> Expense Maker
            </a>
        </div>
        <ul class="sidebar-nav">
            <li class="sidebar-item">
                <a href="dashboard.php" class="sidebar-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="sidebar-item active">
                <a href="history.php" class="sidebar-link">
                    <i class="fas fa-history"></i> History
                </a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php if (isset($_SESSION['avatar']) && $_SESSION['avatar']): ?>
                        <img src="<?php echo htmlspecialchars($_SESSION['avatar']); ?>" alt="Profile">
                    <?php else: ?>
                        <span><?php echo strtoupper(substr($_SESSION['name'] ?? $_SESSION['username'] ?? 'U', 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <div class="user-name">
                        <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username'] ?? 'User'); ?>
                    </div>
                    <a href="logout.php" class="logout-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <nav class="top-nav">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">History</h4>
                    <button id="darkModeToggle" class="btn btn-outline-primary ms-2">
                        <i class="fas fa-moon"></i>
                    </button>
                </div>
            </div>
        </nav>

        <div class="container-fluid mt-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-around mb-4">
                        <div class="history-tab active" data-tab="expenses">
                            <i class="fas fa-receipt me-2"></i>Expenses
                        </div>
                        <div class="history-tab" data-tab="settlements">
                            <i class="fas fa-money-bill-wave me-2"></i>Settlements
                        </div>
                    </div>

                    <!-- Expenses History -->
                    <div class="history-content active" id="expenses-content">
                        <?php if (empty($expenses)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                <h5>No expenses yet</h5>
                                <p class="text-muted">Start by adding expenses to your groups</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($expenses as $expense): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card expense-card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($expense['description']); ?></h5>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <i class="fas fa-users me-1"></i>
                                                        <?php echo htmlspecialchars($expense['group_name']); ?>
                                                    </small>
                                                </p>
                                                <p class="card-text">
                                                    <strong>Amount:</strong> ₹<?php echo number_format($expense['amount'], 2); ?><br>
                                                    <strong>Paid by:</strong> <?php echo htmlspecialchars($expense['paid_by_name']); ?><br>
                                                    <strong>Date:</strong> <?php echo date('M j, Y', strtotime($expense['created_at'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Settlements History -->
                    <div class="history-content" id="settlements-content">
                        <?php if (empty($settlements)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                                <h5>No settlements yet</h5>
                                <p class="text-muted">Your settled payments will appear here</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($settlements as $settlement): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card expense-card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title">Payment to <?php echo htmlspecialchars($settlement['paid_to_name']); ?></h5>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <i class="fas fa-users me-1"></i>
                                                        <?php echo htmlspecialchars($settlement['group_name']); ?>
                                                    </small>
                                                </p>
                                                <p class="card-text">
                                                    <strong>Amount:</strong> ₹<?php echo number_format($settlement['amount'], 2); ?><br>
                                                    <strong>For:</strong> <?php echo htmlspecialchars($settlement['description']); ?><br>
                                                    <strong>Paid on:</strong> <?php echo date('M j, Y', strtotime($settlement['paid_at'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Dark mode toggle
            const darkModeToggle = document.getElementById('darkModeToggle');
            const htmlElement = document.documentElement;
            
            // Check for saved dark mode preference
            const darkMode = localStorage.getItem('darkMode') === 'true';
            if (darkMode) {
                htmlElement.setAttribute('data-theme', 'dark');
                darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            }
            
            // Toggle dark mode
            darkModeToggle.addEventListener('click', function() {
                const isDark = htmlElement.getAttribute('data-theme') === 'dark';
                if (isDark) {
                    htmlElement.removeAttribute('data-theme');
                    localStorage.setItem('darkMode', 'false');
                    this.innerHTML = '<i class="fas fa-moon"></i>';
                } else {
                    htmlElement.setAttribute('data-theme', 'dark');
                    localStorage.setItem('darkMode', 'true');
                    this.innerHTML = '<i class="fas fa-sun"></i>';
                }
            });

            // Tab switching
            const tabs = document.querySelectorAll('.history-tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.history-content').forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding content
                    this.classList.add('active');
                    document.getElementById(`${this.dataset.tab}-content`).classList.add('active');
                });
            });
        });
    </script>
</body>
</html> 