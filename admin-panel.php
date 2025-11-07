<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <h1>Welcome Admin!</h1>
    <nav>
        <a href="manage_users.php">Manage Users</a>
        <a href="manage_movies.php">Manage Movies</a>
        <a href="logout.php">Logout</a>
    </nav>
</body>
</html>