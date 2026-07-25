<?php
session_start();
require_once '../includes/db_connetc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    try {
        $stmt = $conn->prepare("
            SELECT u.* 
            FROM Users u
            WHERE u.username = ? AND u.role = 'admin'
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];

            header("Location: ../Admin/admin_dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Invalid credentials or you are not an admin.";
            header("Location: ../login.php");
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header("Location: ../login.php");
        exit();
    }
}
