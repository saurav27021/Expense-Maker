<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = $_POST['email'];
    $name = $_POST['name'];

    if ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        try {
            // Check if username exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = "Username already exists";
            } else {
                // Check if email exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = "Email already registered";
                } else {
                    // Create new user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, name) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $email, $name]);
                    
                    $success = "Account created successfully! Please log in.";
                    header("refresh:2;url=login.php");
                }
            }
        } catch (PDOException $e) {
            $error = "An error occurred. Please try again.";
            error_log("Signup error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Expense Maker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        .particles-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            pointer-events: none;
            transform-style: preserve-3d;
            animation: floatBubble 15s infinite;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.4);
            will-change: transform, opacity;
        }

        @keyframes floatBubble {
            0% {
                transform: translate3d(0, 100vh, 0);
                opacity: 0;
            }
            20% {
                opacity: 1;
            }
            80% {
                opacity: 0.8;
            }
            100% {
                transform: translate3d(var(--tx), -100vh, var(--tz));
                opacity: 0;
            }
        }

        .signup-container {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.95);
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            backdrop-filter: blur(10px);
            margin: 10px auto;
        }

        .signup-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .signup-header h1 {
            color: #333;
            font-size: 1.75rem;
            margin-bottom: 0.25rem;
        }

        .signup-header p {
            color: #666;
            font-size: 1rem;
            margin-bottom: 0;
        }

        .form-group {
            margin-bottom: 1rem;
            position: relative;
        }

        .form-control {
            height: 40px;
            padding: 0.5rem 1rem;
            font-size: 0.95rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            padding: 0;
            font-size: 1.1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            height: 40px;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
            margin-bottom: 0.75rem;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 7px 14px rgba(0,0,0,0.1);
        }

        .divider {
            text-align: center;
            margin: 1rem 0;
            position: relative;
        }

        .divider::before,
        .divider::after {
            content: "";
            position: absolute;
            top: 50%;
            width: 45%;
            height: 1px;
            background-color: #ddd;
        }

        .divider::before {
            left: 0;
        }

        .divider::after {
            right: 0;
        }

        .google-btn {
            width: 100%;
            background: #fff;
            border: 1px solid #ddd;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 1rem;
            color: #333;
            transition: all 0.3s ease;
        }

        .google-btn:hover {
            background: #f8f9fa;
            border-color: #667eea;
        }

        .login-link {
            text-align: center;
            margin-top: 1rem;
            color: #666;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 0.5rem 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        @media (max-height: 700px) {
            .signup-container {
                padding: 1rem;
            }
            
            .form-group {
                margin-bottom: 0.75rem;
            }
            
            .signup-header {
                margin-bottom: 1rem;
            }
            
            .signup-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="particles-container">
        <?php 
        for($i = 0; $i < 40; $i++): 
            $size = rand(8, 20);
            $translateX = rand(-100, 100);
            $translateZ = rand(0, 100);
            $delay = $i * 0.3;
            $duration = rand(8, 15);
        ?>
            <div class="particle" style="
                left: <?php echo rand(0, 100); ?>%;
                width: <?php echo $size; ?>px;
                height: <?php echo $size; ?>px;
                animation-delay: <?php echo $delay; ?>s;
                animation-duration: <?php echo $duration; ?>s;
                --tx: <?php echo $translateX; ?>px;
                --tz: <?php echo $translateZ; ?>px;
                background: rgba(255, 255, 255, <?php echo rand(15, 30)/100; ?>);
            "></div>
        <?php endfor; ?>
    </div>

    <div class="signup-container">
        <div class="signup-header">
            <h1>Create Account</h1>
            <p>Join Expense Maker today</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="signup.php">
            <div class="form-group">
                <input type="text" class="form-control" name="name" placeholder="Full Name" required>
            </div>
            <div class="form-group">
                <input type="email" class="form-control" name="email" placeholder="Email Address" required>
            </div>
            <div class="form-group">
                <input type="text" class="form-control" name="username" placeholder="Username" required>
            </div>
            <div class="form-group">
                <input type="password" class="form-control" name="password" id="password" placeholder="Password" required>
                <button type="button" class="password-toggle" onclick="togglePassword('password')">
                    <i class="far fa-eye"></i>
                </button>
            </div>
            <div class="form-group">
                <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                    <i class="far fa-eye"></i>
                </button>
            </div>
            <button type="submit" class="btn btn-primary">Create Account</button>
        </form>

        <div class="divider">or</div>

        <div id="g_id_onload"
             data-client_id="YOUR_GOOGLE_CLIENT_ID"
             data-context="signup"
             data-ux_mode="popup"
             data-callback="handleGoogleSignIn"
             data-auto_prompt="false">
        </div>

        <div class="g_id_signin"
             data-type="standard"
             data-shape="rectangular"
             data-theme="outline"
             data-text="signup_with"
             data-size="large"
             data-logo_alignment="center"
             data-width="100%">
        </div>

        <div class="login-link">
            Already have an account? <a href="login.php">Log In</a>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function handleGoogleSignIn(response) {
            // Send the response.credential to your server
            fetch('google-signin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    credential: response.credential
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    alert(data.message || 'Error signing in with Google');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error signing in with Google');
            });
        }
    </script>
</body>
</html> 