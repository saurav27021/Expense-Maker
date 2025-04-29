<?php
session_start();
include 'db.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $sql = "SELECT password FROM users WHERE id = '$user_id'";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (password_verify($current_password, $row['password'])) {
                if ($new_password === $confirm_password) {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = "UPDATE users SET password = '$hashed' WHERE id = '$user_id'";
                    if ($conn->query($update) === TRUE) {
                        header("Location: profile.html");
                        exit;
                    } else {
                        echo "Error updating password.";
                    }
                } else {
                    echo "New passwords do not match.";
                }
            } else {
                echo "Current password is incorrect.";
            }
        } else {
            echo "User not found.";
        }
    } else {
        header("Location: login.html");
        exit;
    }
}
?>
