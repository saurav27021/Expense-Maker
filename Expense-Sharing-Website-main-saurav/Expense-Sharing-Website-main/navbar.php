<?php
$current_page = basename($_SERVER['PHP_SELF']);
$group_id = $_GET['group_id'] ?? null;
?>
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-wallet me-2"></i>Expense Maker
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if (isset($_SESSION['user_id'])): ?>
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" 
                           href="dashboard.php">
                           <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'expense-history.php' ? 'active' : ''; ?>" 
                           href="expense-history.php">
                           <i class="fas fa-history me-1"></i>History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" 
                           href="reports.php">
                           <i class="fas fa-chart-bar me-1"></i>Reports
                        </a>
                    </li>
                    <?php if ($group_id): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page == 'group.php' ? 'active' : ''; ?>" 
                               href="group.php?group_id=<?php echo $group_id; ?>">
                               <i class="fas fa-users me-1"></i>Group Details
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page == 'add-expense.php' ? 'active' : ''; ?>" 
                               href="add-expense.php?group_id=<?php echo $group_id; ?>">
                               <i class="fas fa-plus me-1"></i>Add Expense
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page == 'settle-up.php' ? 'active' : ''; ?>" 
                               href="settle-up.php?group_id=<?php echo $group_id; ?>">
                               <i class="fas fa-handshake me-1"></i>Settle Up
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page == 'add-payment.php' ? 'active' : ''; ?>" 
                               href="add-payment.php?group_id=<?php echo $group_id; ?>">
                               <i class="fas fa-money-bill me-1"></i>Add Payment
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Account'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user me-1"></i>Profile
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt me-1"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            <?php else: ?>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'login.php' ? 'active' : ''; ?>" 
                           href="login.php">
                           <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'register.php' ? 'active' : ''; ?>" 
                           href="register.php">
                           <i class="fas fa-user-plus me-1"></i>Register
                        </a>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>
