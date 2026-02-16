<?php
session_start();

/* ===== DATABASE CONNECTION ===== */
$conn = new mysqli("localhost", "root", "", "college_event");

/* Create database if not exists */
$conn->query("CREATE DATABASE IF NOT EXISTS college_event");

/* Select database */
$conn->select_db("college_event");

/* Create table if not exists */
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
)");

$message = "";

/* ===== LOGIN LOGIC ===== */
if(isset($_POST['login'])) {

    $username = $_POST['username'];
    $password = $_POST['password'];

    // Secure prepared statement
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if($stmt->num_rows > 0) {

        $stmt->bind_result($id, $db_username, $db_password);
        $stmt->fetch();

        if(password_verify($password, $db_password)) {
            $_SESSION['user'] = $db_username;

            // Redirect to dashboard (you can create dashboard.php)
            header("Location: dashboard.php");
            exit();
        } else {
            $message = "Invalid Password!";
        }

    } else {
        $message = "User not found!";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - College Event System</title>
    <link rel="stylesheet" href="index.css">
</head>
<body>

<div class="stars"></div>

<div class="top">
    <h1>COLLEGE <span>EVENT SYSTEM</span></h1>
</div>

<div class="hud">

    <div class="menu">
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
    </div>

    <div class="center">
        <h2>Login</h2>

        <?php if($message != "") { ?>
            <p><?php echo $message; ?></p>
        <?php } ?>

        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <br><br>
            <input type="password" name="password" placeholder="Password" required>
            <br><br>
            <button type="submit" name="login" class="btn">Login</button>
        </form>
    </div>

</div>

</body>
</html>
