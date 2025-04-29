<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get group ID from URL
$group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get group details
$stmt = $pdo->prepare("
    SELECT g.*, u.name as creator_name 
    FROM groups g 
    JOIN users u ON g.created_by = u.id 
    WHERE g.id = ? AND g.id IN (
        SELECT group_id 
        FROM group_members 
        WHERE user_id = ?
    )
");
$stmt->execute([$group_id, $_SESSION['user_id']]);
$group = $stmt->fetch();

if (!$group) {
    header("Location: dashboard.php");
    exit();
}

// Get group history (temporarily disabled)
$history = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group History - Expense Maker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
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
                <a href="group-history.php?id=<?php echo $group_id; ?>" class="sidebar-link">
                    <i class="fas fa-history"></i> Group History
                </a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
                    <a href="logout.php" class="logout-link">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="top-nav">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center">
                    <button class="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="top-nav-right">
                        <a href="group-details.php?id=<?php echo $group_id; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to Group
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Group History Content -->
        <div class="container-fluid py-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">History for <?php echo htmlspecialchars($group['name']); ?></h5>
                </div>
                <div class="card-body">
                    <?php if (empty($history)): ?>
                        <div class="empty-state">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <h5>No History Yet</h5>
                            <p class="text-muted">No actions have been recorded for this group yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($history as $event): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker">
                                        <?php
                                        $icon = 'fa-info-circle';
                                        $color = 'text-primary';
                                        if ($event['action'] == 'created') {
                                            $icon = 'fa-plus-circle';
                                            $color = 'text-success';
                                        } elseif ($event['action'] == 'completed') {
                                            $icon = 'fa-check-circle';
                                            $color = 'text-success';
                                        } elseif ($event['action'] == 'archived') {
                                            $icon = 'fa-archive';
                                            $color = 'text-warning';
                                        } elseif ($event['action'] == 'deleted') {
                                            $icon = 'fa-trash-alt';
                                            $color = 'text-danger';
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon; ?> <?php echo $color; ?>"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-header">
                                            <span class="timeline-title"><?php echo ucfirst($event['action']); ?></span>
                                            <span class="timeline-time">
                                                <?php echo date('M d, Y h:i A', strtotime($event['performed_at'])); ?>
                                            </span>
                                        </div>
                                        <div class="timeline-body">
                                            <?php echo htmlspecialchars($event['performer_name']); ?> 
                                            <?php echo $event['action']; ?> the group
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar
        document.querySelector('.sidebar-toggle').addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');
        });
    </script>
</body>
</html> 