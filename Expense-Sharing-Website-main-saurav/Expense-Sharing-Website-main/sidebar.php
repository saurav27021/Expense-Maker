<?php
if (!isset($_SESSION)) {
    session_start();
}
?>
<div class="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="sidebar-brand">
            <i class="fas fa-wallet"></i> Expense Maker
        </a>
    </div>
    <ul class="sidebar-nav">
        <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <a href="dashboard.php" class="sidebar-link">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="sidebar-item <?php echo basename($_SERVER['PHP_SELF']) == 'groups.php' ? 'active' : ''; ?>">
            <a href="groups.php" class="sidebar-link">
                <i class="fas fa-layer-group"></i> My Groups
            </a>
        </li>
        <li class="sidebar-item">
            <a href="#" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                <i class="fas fa-plus-circle"></i> Create Group
            </a>
        </li>
        <li class="sidebar-item">
            <a href="#" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                <i class="fas fa-receipt"></i> Add Expense
            </a>
        </li>
    </ul>
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php 
                    $display_name = $_SESSION['name'] ?? $_SESSION['username'] ?? 'U';
                    echo htmlspecialchars(strtoupper(substr($display_name, 0, 1))); 
                ?>
            </div>
            <div class="user-details">
                <div class="user-name">
                    <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username'] ?? 'User'); ?>
                </div>
                <a href="logout.php" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div> 