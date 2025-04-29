<?php
session_start();
require_once 'db.php';
require_once 'config.php';
require_once 'vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$group_id) {
    header('Location: dashboard.php');
    exit();
}

// Get group details and check if user is creator
$stmt = $pdo->prepare("SELECT name, created_by FROM groups WHERE id = ?");
$stmt->execute([$group_id]);
$group = $stmt->fetch();

if (!$group || (int)$group['created_by'] !== (int)$_SESSION['user_id']) {
    header('Location: group.php?id=' . $group_id);
    exit();
}

// Generate unique invite token and code
function generateInviteToken() {
    return bin2hex(random_bytes(32));
}

function generateInviteCode() {
    // Generate a code using uppercase letters (excluding I and O) and numbers (excluding 0 and 1)
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

// Handle email invitation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['invite_email']) && isset($_POST['email'])) {
            $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
            if (!$email) {
                throw new Exception('Invalid email address');
            }

            // Check if user is already a member
            $stmt = $pdo->prepare("
                SELECT 1 FROM users u
                JOIN group_members gm ON u.id = gm.user_id
                WHERE u.email = ? AND gm.group_id = ?
            ");
            $stmt->execute([$email, $group_id]);
            if ($stmt->fetch()) {
                throw new Exception('This user is already a member of the group');
            }

            // Check if there's already a pending invite
            $stmt = $pdo->prepare("
                SELECT 1 FROM group_invites
                WHERE email = ? AND group_id = ? AND status = 'pending'
            ");
            $stmt->execute([$email, $group_id]);
            if ($stmt->fetch()) {
                throw new Exception('An invitation has already been sent to this email');
            }

            // Generate token and code
            $token = generateInviteToken();
            $invite_code = generateInviteCode();

            // Insert invite into database
            $stmt = $pdo->prepare("
                INSERT INTO group_invites (group_id, email, token, invite_code, status, invited_by, expires_at)
                VALUES (?, ?, ?, ?, 'pending', ?, NOW() + INTERVAL 7 DAY)
            ");
            $stmt->execute([$group_id, $email, $token, $invite_code, $_SESSION['user_id']]);
            $invite_id = $pdo->lastInsertId();
            
            // Get the invite details
            $stmt = $pdo->prepare("
                SELECT id, expires_at, created_at as created_time 
                FROM group_invites 
                WHERE id = ?
            ");
            $stmt->execute([$invite_id]);
            $debug_result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Send email with both link and code
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = SMTP_PORT;

                $mail->setFrom(SMTP_FROM_EMAIL, 'Expense Maker');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Invitation to join ' . htmlspecialchars($group['name']) . ' on Expense Maker';
                
                $invite_link = SITE_URL . '/join-group.php?token=' . $token;
                
                $mail->Body = "
                    <h2>You've been invited to join " . htmlspecialchars($group['name']) . "</h2>
                    <p>You can join the group in two ways:</p>
                    <p>1. Click this link: <a href='{$invite_link}'>{$invite_link}</a></p>
                    <p>2. Or use this invitation code: <strong>{$invite_code}</strong></p>
                    <p>This invitation will expire in 7 days.</p>
                ";

                $mail->send();
                $success = 'Invitation sent successfully! Debug: Created at ' . $debug_result['created_time'] . ', expires at ' . $debug_result['expires_at'];
            } catch (Exception $e) {
                throw new Exception('Failed to send invitation email: ' . $mail->ErrorInfo);
            }
        } 
        elseif (isset($_POST['resend_invite'])) {
            $invite_id = (int)$_POST['invite_id'];
            $stmt = $pdo->prepare("
                SELECT * FROM group_invites 
                WHERE id = ? AND group_id = ? AND status = 'pending'
            ");
            $stmt->execute([$invite_id, $group_id]);
            $invite = $stmt->fetch();
            
            if ($invite) {
                // Generate new token and code
                $token = generateInviteToken();
                $invite_code = generateInviteCode();
                
                // Update invite
                $stmt = $pdo->prepare("
                    UPDATE group_invites 
                    SET token = ?, invite_code = ?, expires_at = NOW() + INTERVAL 7 DAY
                    WHERE id = ?
                ");
                $stmt->execute([$token, $invite_code, $invite_id]);
                
                // Resend email
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = SMTP_HOST;
                    $mail->SMTPAuth = true;
                    $mail->Username = SMTP_USERNAME;
                    $mail->Password = SMTP_PASSWORD;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = SMTP_PORT;

                    $mail->setFrom(SMTP_FROM_EMAIL, 'Expense Maker');
                    $mail->addAddress($invite['email']);

                    $mail->isHTML(true);
                    $mail->Subject = 'Invitation to join ' . htmlspecialchars($group['name']) . ' on Expense Maker';
                    
                    $invite_link = SITE_URL . '/join-group.php?token=' . $token;
                    
                    $mail->Body = "
                        <h2>You've been invited to join " . htmlspecialchars($group['name']) . "</h2>
                        <p>You can join the group in two ways:</p>
                        <p>1. Click this link: <a href='{$invite_link}'>{$invite_link}</a></p>
                        <p>2. Or use this invitation code: <strong>{$invite_code}</strong></p>
                        <p>This invitation will expire in 7 days.</p>
                    ";

                    $mail->send();
                    $success = 'Invitation resent successfully!';
                } catch (Exception $e) {
                    throw new Exception('Failed to send invitation email: ' . $mail->ErrorInfo);
                }
            }
        }
        elseif (isset($_POST['cancel_invite'])) {
            $invite_id = (int)$_POST['invite_id'];
            $stmt = $pdo->prepare("
                UPDATE group_invites 
                SET status = 'cancelled' 
                WHERE id = ? AND group_id = ? AND status = 'pending'
            ");
            $stmt->execute([$invite_id, $group_id]);
            if ($stmt->rowCount() > 0) {
                $success = 'Invitation cancelled successfully!';
            }
        }
        elseif (isset($_POST['generate_invite_link'])) {
            // Generate token and code
            $token = generateInviteToken();
            $invite_code = generateInviteCode();
            
            // Insert invite into database
            $stmt = $pdo->prepare("
                INSERT INTO group_invites (group_id, token, invite_code, status, invited_by, expires_at)
                VALUES (?, ?, ?, 'pending', ?, NOW() + INTERVAL 7 DAY)
            ");
            $stmt->execute([$group_id, $token, $invite_code, $_SESSION['user_id']]);
            $invite_id = $pdo->lastInsertId();
            
            // Get the invite details
            $stmt = $pdo->prepare("
                SELECT id, expires_at, created_at as created_time 
                FROM group_invites 
                WHERE id = ?
            ");
            $stmt->execute([$invite_id]);
            $debug_result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Generate invite link and QR code
            $invite_link = SITE_URL . '/join-group.php?token=' . $token;
            $qrCode = QrCode::create($invite_link);
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            $qrDataUri = $result->getDataUri();
            
            // Store for the view
            $_SESSION['invite_link'] = $invite_link;
            $_SESSION['invite_code'] = $invite_code;
            $success = "Invite link generated! Debug: Created at " . $debug_result['created_time'] . ", expires at " . $debug_result['expires_at'];
        }
    } catch (Exception $e) {
        $error = 'Failed to process invitation: ' . $e->getMessage();
    }
}

// Get existing invites
$stmt = $pdo->prepare("
    SELECT gi.id, gi.group_id, gi.token, gi.invite_code, gi.email, gi.status,
           gi.created_at, gi.expires_at,
           g.name as group_name,
           u.username as invited_by_name
    FROM group_invites gi
    JOIN groups g ON gi.group_id = g.id
    LEFT JOIN users u ON gi.invited_by = u.id
    WHERE gi.group_id = ?
    ORDER BY gi.created_at DESC
");
$stmt->execute([$group_id]);
$invites = $stmt->fetchAll();

// Generate invite link and QR code
$token = generateInviteToken();
$invite_code = generateInviteCode();
$invite_link = SITE_URL . '/join-group.php?token=' . $token;
$qrCode = QrCode::create($invite_link);
$writer = new PngWriter();
$result = $writer->write($qrCode);
$qrDataUri = $result->getDataUri();

// For the copy link functionality
$_SESSION['join_url'] = $invite_link;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invite Members - <?php echo htmlspecialchars($group['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body">
                        <h2 class="card-title mb-4">
                            <i class="fas fa-user-plus me-2"></i>
                            Invite Members to <?php echo htmlspecialchars($group['name']); ?>
                        </h2>
                        
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="invite-options">
                            <!-- Email Invite Form -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-envelope me-2"></i>
                                        Invite by Email
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" class="mb-3">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="email" name="email" required>
                                        </div>
                                        <button type="submit" name="invite_email" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-2"></i>
                                            Send Invitation
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- QR Code Section -->
                            <div class="card mb-4" id="qrSection">
                                <div class="card-header" style="cursor: pointer;" onclick="toggleSections('qrSection')">
                                    <h5 class="mb-0">
                                        <i class="fas fa-qrcode me-2"></i>
                                        Share QR Code
                                    </h5>
                                </div>
                                <div class="card-body text-center">
                                    <?php if (isset($_SESSION['invite_code'])): ?>
                                        <div class="mb-3">
                                            <h5>Invitation Code:</h5>
                                            <div class="code-display p-2 bg-light rounded border d-inline-block">
                                                <span class="h5 mb-0 font-monospace"><?php echo htmlspecialchars($_SESSION['invite_code']); ?></span>
                                                <button class="btn btn-sm btn-outline-primary ms-2" onclick="copyCode('<?php echo htmlspecialchars($_SESSION['invite_code']); ?>')">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="qr-code-container mb-3">
                                            <img src="<?php echo $qrDataUri; ?>" alt="QR Code" class="img-fluid">
                                        </div>
                                        <div class="d-grid gap-2 col-md-6 mx-auto">
                                            <button class="btn btn-outline-primary" onclick="copyJoinLink()">
                                                <i class="fas fa-copy me-2"></i>
                                                Copy Join Link
                                            </button>
                                            <button class="btn btn-info" onclick="togglePendingSection()">
                                                <i class="fas fa-clock me-2"></i>
                                                Show Pending Invitations
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <form method="post" class="mb-3">
                                            <button type="submit" name="generate_invite_link" class="btn btn-primary">
                                                <i class="fas fa-plus-circle me-2"></i>
                                                Generate New Invite
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                </div>
                            </div>

                            <!-- Pending Invitations -->
                            <div class="card" id="pendingSection" style="display: none;">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <i class="fas fa-clock me-2"></i>
                                            Pending Invitations
                                        </h5>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="togglePendingSection()">
                                            <i class="fas fa-arrow-left me-1"></i> Back
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Email/Code</th>
                                                    <th>Created By</th>
                                                    <th>Status</th>
                                                    <th>Debug</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($invites as $invite): ?>
                                                    <tr>
                                                        <td>
                                                            <?php if ($invite['email']): ?>
                                                                <i class="fas fa-envelope text-primary"></i> Email
                                                            <?php else: ?>
                                                                <i class="fas fa-link text-success"></i> Link
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($invite['email']): ?>
                                                                <?php echo htmlspecialchars($invite['email']); ?>
                                                            <?php else: ?>
                                                                <div class="code-display p-2 bg-light rounded border">
                                                                    <span class="h5 mb-0 font-monospace"><?php echo htmlspecialchars($invite['invite_code']); ?></span>
                                                                    <button class="btn btn-sm btn-outline-primary ms-2" onclick="copyCode('<?php echo htmlspecialchars($invite['invite_code']); ?>')">
                                                                        <i class="fas fa-copy"></i>
                                                                    </button>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($invite['invited_by_name']); ?></td>
                                                        <td>
                                                            <?php if ($invite['status'] === 'expired'): ?>
                                                                <span class="badge bg-secondary">Expired</span>
                                                            <?php elseif ($invite['status'] === 'accepted'): ?>
                                                                <span class="badge bg-success">Accepted</span>
                                                            <?php elseif ($invite['status'] === 'cancelled'): ?>
                                                                <span class="badge bg-danger">Cancelled</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning">Pending</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <small>
                                                                Created: <?php echo date('Y-m-d H:i:s', strtotime($invite['created_at'])); ?><br>
                                                                Expires: <?php echo date('Y-m-d H:i:s', strtotime($invite['expires_at'])); ?><br>
                                                                Now: <?php echo date('Y-m-d H:i:s'); ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <?php if ($invite['status'] === 'pending' || $invite['status'] === 'active'): ?>
                                                                <form method="post" class="d-inline">
                                                                    <input type="hidden" name="invite_id" value="<?php echo $invite['id']; ?>">
                                                                    <?php if ($invite['email']): ?>
                                                                        <button type="submit" name="resend_invite" class="btn btn-sm btn-primary">
                                                                            <i class="fas fa-paper-plane"></i> Resend
                                                                        </button>
                                                                    <?php endif; ?>
                                                                    <button type="submit" name="cancel_invite" class="btn btn-sm btn-danger">
                                                                        <i class="fas fa-times"></i> Cancel
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePendingSection() {
            const pendingSection = document.getElementById('pendingSection');
            pendingSection.style.display = pendingSection.style.display === 'none' ? 'block' : 'none';
        }

        // Add click event to all rows in the table to show pending section
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.querySelector('table');
            if (table) {
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    row.style.cursor = 'pointer';
                    row.addEventListener('click', function() {
                        const pendingSection = document.getElementById('pendingSection');
                        pendingSection.style.display = 'block';
                    });
                });
            }
        });
        
        // Show pending invitations when new invite is created
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert && successAlert.textContent.includes('Invite link generated')) {
                const pendingSection = document.getElementById('pendingSection');
                pendingSection.style.display = 'block';
            }
        });

        // Copy Invitation Code
        function copyCode(code) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).then(() => {
                    showCopyToast('Invitation code copied!');
                }).catch(err => {
                    alert('Failed to copy code: ' + err);
                });
            } else {
                // fallback for unsupported browsers
                const tempInput = document.createElement('input');
                tempInput.value = code;
                document.body.appendChild(tempInput);
                tempInput.select();
                document.execCommand('copy');
                document.body.removeChild(tempInput);
                showCopyToast('Invitation code copied!');
            }
        }

        // Copy Join Link
        function copyJoinLink() {
            const joinUrl = '<?php echo isset($_SESSION['invite_link']) ? $_SESSION['invite_link'] : ''; ?>';
            if (!joinUrl) {
                alert('No join link available.');
                return;
            }
            if (navigator.clipboard) {
                navigator.clipboard.writeText(joinUrl).then(() => {
                    showCopyToast('Invitation link copied!');
                }).catch(err => {
                    alert('Failed to copy link: ' + err);
                });
            } else {
                // fallback for unsupported browsers
                const tempInput = document.createElement('input');
                tempInput.value = joinUrl;
                document.body.appendChild(tempInput);
                tempInput.select();
                document.execCommand('copy');
                document.body.removeChild(tempInput);
                showCopyToast('Invitation link copied!');
            }
        }

        // Show toast/alert for copy action
        function showCopyToast(message) {
            let toast = document.getElementById('copy-toast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'copy-toast';
                toast.style.position = 'fixed';
                toast.style.bottom = '30px';
                toast.style.left = '50%';
                toast.style.transform = 'translateX(-50%)';
                toast.style.background = '#20c997';
                toast.style.color = 'white';
                toast.style.padding = '12px 32px';
                toast.style.borderRadius = '24px';
                toast.style.fontSize = '1rem';
                toast.style.boxShadow = '0 4px 16px rgba(0,0,0,0.12)';
                toast.style.zIndex = 9999;
                document.body.appendChild(toast);
            }
            toast.textContent = message;
            toast.style.display = 'block';
            setTimeout(() => { toast.style.display = 'none'; }, 1800);
        }
    </script>
</body>
</html>
