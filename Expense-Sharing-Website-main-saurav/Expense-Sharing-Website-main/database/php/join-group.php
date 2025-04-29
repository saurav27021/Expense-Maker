<?php
session_start();
require_once 'db.php';
require_once 'config.php';

$error = null;
$success = null;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle invitation token or code
if (isset($_POST['invite_code'])) {
    $invite_code = $_POST['invite_code'];
    
    try {
        // Get invitation details
        $stmt = $pdo->prepare("
            SELECT gi.*, g.name as group_name, 
                   u.username as inviter_name
            FROM group_invites gi
            JOIN groups g ON gi.group_id = g.id
            LEFT JOIN users u ON gi.invited_by = u.id
            WHERE gi.invite_code = ?
            AND gi.status = 'pending'
            AND gi.expires_at > NOW()
        ");
        $stmt->execute([$invite_code]);
        $invite = $stmt->fetch();

        if (!$invite) {
            // Check if invite exists but is invalid
            $stmt = $pdo->prepare("
                SELECT gi.*, g.name as group_name, 
                       u.username as inviter_name
                FROM group_invites gi
                JOIN groups g ON gi.group_id = g.id
                LEFT JOIN users u ON gi.invited_by = u.id
                WHERE gi.invite_code = ?
            ");
            $stmt->execute([$invite_code]);
            error_log('Debug: Searching for invite code: ' . $invite_code);
            $debug_invite = $stmt->fetch();
            
            if ($debug_invite) {
                if ($debug_invite['status'] !== 'pending') {
                    throw new Exception('This invitation has already been used');
                }
                if (strtotime($debug_invite['expires_at']) <= time()) {
                    throw new Exception('This invitation has expired');
                }
            } else {
                throw new Exception('Invalid invitation code. Debug: Code=' . htmlspecialchars($invite_code));
            }
        }

        // For email invites, verify email matches
        if ($invite['email'] !== null && $invite['email'] !== $_SESSION['email']) {
            throw new Exception('This invitation was sent to a different email address');
        }

        // Check if already a member
        $stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$invite['group_id'], $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $_SESSION['info'] = 'You are already a member of this group.';
            header("Location: group-details.php?id=" . $invite['group_id']);
            exit();
        }

        // Add user to group and update invitation status
        $pdo->beginTransaction();

        try {
            // Add user to group
            $stmt = $pdo->prepare("
                INSERT INTO group_members (group_id, user_id, role, joined_at)
                VALUES (?, ?, 'member', NOW())
            ");
            $stmt->execute([$invite['group_id'], $_SESSION['user_id']]);

            // Update invitation status
            $stmt = $pdo->prepare("
                UPDATE group_invites 
                SET status = 'accepted', 
                    accepted_at = NOW(),
                    accepted_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $invite['id']]);

            $pdo->commit();
            $_SESSION['success'] = 'Successfully joined ' . htmlspecialchars($invite['group_name']);
            header("Location: group-details.php?id=" . $invite['group_id']);
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Group - Expense Maker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body text-center">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-home me-2"></i>Go to Dashboard
                            </a>
                        <?php else: ?>
                            <h2 class="mb-4">Join a Group</h2>
                            <p class="text-muted mb-4">Enter your invitation code below:</p>
                            <form method="POST" class="mb-4">
                                <div class="input-group mb-3">
                                    <input type="text" name="invite_code" class="form-control" placeholder="Enter invitation code" required>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt me-2"></i>Join Group
                                    </button>
                                </div>
                            </form>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-home me-2"></i>Back to Dashboard
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
