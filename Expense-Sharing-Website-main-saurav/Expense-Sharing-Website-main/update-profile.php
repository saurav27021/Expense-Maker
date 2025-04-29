<?php
session_start();
include 'db.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $sql = "UPDATE users SET name = '$name', email = '$email' WHERE id = '$user_id'";
        if ($conn->query($sql) === TRUE) {
            header("Location: profile.html");
            exit;
        } else {
            echo "Error updating profile.";
        }
    } else {
        header("Location: login.html");
        exit;
    }
}
?>
